"""
libook_scraper.py
Скрапер для utd.libook.xyz с использованием Playwright (Chromium).

Исправления:
- Листание графиков: проходим ВСЕ страницы по кругу (1→2→...→N→1 и т.д.)
  пока не соберём все уникальные страницы.
- Метод collect_search_results: собирает все заголовки/ссылки/ID по запросу.
- Сохранение в SQLite через database.py.
"""

import asyncio
import os
import re
from pathlib import Path
from urllib.parse import urlencode, urlparse

from playwright.async_api import async_playwright, TimeoutError as PlaywrightTimeoutError

import database as db_module


# ─────────────────────────── helpers ────────────────────────────

def extract_article_id(url: str) -> str | None:
    m = re.search(r"/topics/(\d+)", url)
    return m.group(1) if m else None


def sanitize_filename(name: str) -> str:
    return re.sub(r'[\\/*?:"<>|]', "_", name).strip()[:200]


def has_cjk(text: str) -> bool:
    return bool(re.search(r"[\u3400-\u9fff]", text or ""))


def title_from_url_slug(url: str) -> str:
    """Best-effort English title from a Libook/UpToDate content slug."""
    parsed = urlparse(url)
    parts = [p for p in parsed.path.split("/") if p]
    if not parts:
        return ""

    slug = ""
    if "contents" in parts:
        idx = parts.index("contents") + 1
        if idx < len(parts) and re.fullmatch(r"[a-z]{2}(?:-[A-Za-z]+)?", parts[idx]):
            idx += 1
        if idx < len(parts):
            slug = parts[idx]
    else:
        slug = parts[-1]

    if not slug or slug in {"topics", "image"}:
        return ""

    words = slug.replace("-", " ").strip()
    if not words:
        return ""

    lowered = words.lower()
    if lowered.endswith(" beyond the basics"):
        base = words[: -len(" beyond the basics")].strip()
        return f"Patient education: {base[:1].upper()}{base[1:]} (Beyond the Basics)"
    if lowered.endswith(" the basics"):
        base = words[: -len(" the basics")].strip()
        return f"Patient education: {base[:1].upper()}{base[1:]} (The Basics)"

    return words[:1].upper() + words[1:]


# ─────────────────────────── main class ─────────────────────────

class LibookScraper:
    """
    Параметры:
        login          – логин (email)
        password       – пароль
        proxy          – строка вида "socks5://host:port"
                         или "socks5://host:port"
        search_query   – поисковый запрос, например "migraine"
        article_title  – название статьи (поиск по подстроке, без учёта регистра)
                         Если None — скрапер только собирает результаты поиска.
        account_id     – ID аккаунта в БД (для логирования)
        base_dir       – корневая папка (по умолчанию рядом с файлом)
        headless       – фоновый режим браузера
    """

    BASE_URL = "https://utd.libook.xyz"

    def __init__(
        self,
        login: str,
        password: str,
        proxy: str,
        search_query: str,
        article_title: str | None = None,
        account_id: int | None = None,
        base_dir: str | None = None,
        headless: bool = False,
    ):
        self.login = login
        self.password = password
        self.proxy = proxy
        self.search_query = search_query
        self.article_title = article_title
        self.account_id = account_id
        self.headless = headless

        script_dir = Path(base_dir) if base_dir else Path(__file__).resolve().parent
        self.session_dir = script_dir / "session_data" / sanitize_filename(login)
        self.articles_dir = script_dir / "downloaded_articles"
        self.session_dir.mkdir(parents=True, exist_ok=True)
        self.articles_dir.mkdir(parents=True, exist_ok=True)

    # ── proxy ────────────────────────────────────────────────────

    def _build_proxy_config(self) -> dict:
        raw = self.proxy.strip()
        if not raw:
            return {}
        if "://" not in raw:
            raw = f"socks5://{raw}"
        from urllib.parse import urlparse
        parsed = urlparse(raw)
        cfg: dict = {"server": f"{parsed.scheme}://{parsed.hostname}:{parsed.port}"}
        if parsed.username:
            cfg["username"] = parsed.username
        if parsed.password:
            cfg["password"] = parsed.password
        return cfg

    # ── browser ──────────────────────────────────────────────────

    async def _make_context(self, playwright):
        proxy_cfg = self._build_proxy_config()
        browser = await playwright.chromium.launch(
            headless=self.headless,
            proxy=proxy_cfg if proxy_cfg else None,
            ignore_default_args=["--enable-automation"],
            args=[
                "--disable-blink-features=AutomationControlled",
                "--start-maximized",
            ],
        )
        storage_file = self.session_dir / "storage_state.json"
        storage_state = str(storage_file) if storage_file.exists() else None
        context = await browser.new_context(
            storage_state=storage_state,
            viewport={"width": 1280, "height": 900},
            user_agent=(
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/122.0.0.0 Safari/537.36"
            ),
        )
        return browser, context

    async def _save_session(self, context):
        storage_file = str(self.session_dir / "storage_state.json")
        await context.storage_state(path=storage_file)
        print(f"[session] Сессия сохранена: {storage_file}")

    async def _soft_wait_for_network(self, page, timeout: int = 5000):
        """Best-effort ожидание: SPA может держать сеть активной бесконечно."""
        try:
            await page.wait_for_load_state("networkidle", timeout=timeout)
        except PlaywrightTimeoutError:
            pass

    async def _goto(self, page, url: str, label: str = "page", timeout: int = 90000):
        print(f"[nav] Открываем {label}: {url}")
        response = await page.goto(url, wait_until="domcontentloaded", timeout=timeout)
        await self._soft_wait_for_network(page)
        return response

    # ── login ────────────────────────────────────────────────────

    async def _login(self, page):
        print(f"[login] Открываем {self.BASE_URL} …")
        await self._goto(page, self.BASE_URL, label="главную")

        if await page.query_selector("#id_username"):
            print("[login] Вводим логин/пароль …")
            await page.fill("#id_username", self.login)
            await page.fill("#id_password", self.password)
            await page.click("button[type='submit']")
            await self._soft_wait_for_network(page)
            print(f"[login] Редирект: {page.url}")

        signin_btn = await page.query_selector(
            "form[action*='/api/auth/signin/libook'] button[type='submit']"
        )
        if signin_btn:
            print("[login] Sign in with libook …")
            await signin_btn.click()
            await self._soft_wait_for_network(page)
            print(f"[login] После входа: {page.url}")

    # ── collect all search results ───────────────────────────────

    def _search_priorities(self) -> list[int]:
        raw = os.getenv("LIBOOK_SEARCH_PRIORITIES", "0,1,2,3")
        priorities: list[int] = []
        for chunk in raw.split(","):
            chunk = chunk.strip()
            if not chunk:
                continue
            try:
                priorities.append(int(chunk))
            except ValueError:
                print(f"[collect] ⚠ Некорректный sp='{chunk}', пропускаем.")
        return priorities or [0]

    def _normalize_search_result(self, item: dict) -> dict | None:
        if item.get("type") != "medical":
            return None

        article_id = str(item.get("id") or "").strip()
        if not article_id:
            return None

        raw_url = str(item.get("url") or "").strip()
        title = str(item.get("title") or "").strip()
        slug_title = title_from_url_slug(raw_url)
        if slug_title and (has_cjk(title) or not title):
            title = slug_title

        if not title:
            title = f"Article {article_id}"

        return {
            "article_id": article_id,
            "title": title,
            # Canonical English topic URL. The API often returns zh-Hans slugs,
            # while article pages and the existing parser logic work by topic ID.
            "url": f"{self.BASE_URL}/contents/topics/{article_id}",
        }

    async def _collect_search_results_from_api(self, page) -> list[dict]:
        """
        Libook caps a single search priority at ~150 results. Querying the API
        across priorities (ALL, ADULT, PEDIATRIC, PATIENT) exposes many more
        unique medical topics than the rendered Show More list.
        """
        by_id: dict[str, dict] = {}
        priorities = self._search_priorities()
        max_offset = int(os.getenv("LIBOOK_MAX_SEARCH_OFFSET", "5000"))
        api_timeout_ms = int(os.getenv("LIBOOK_API_TIMEOUT_MS", "60000"))
        api_retries = int(os.getenv("LIBOOK_API_RETRIES", "3"))
        no_progress_limit = int(os.getenv("LIBOOK_API_NO_PROGRESS_PAGES", "3"))

        for sp in priorities:
            offset = 1
            priority_name = f"sp={sp}"
            seen_for_priority: set[str] = set()
            no_progress_pages = 0

            while offset <= max_offset:
                url = f"{self.BASE_URL}/api/search?" + urlencode({
                    "search": self.search_query,
                    "searchOffset": offset,
                    "sp": sp,
                })
                response = None
                for attempt in range(1, api_retries + 1):
                    try:
                        response = await page.request.get(url, timeout=api_timeout_ms)
                        break
                    except Exception as exc:
                        if attempt >= api_retries:
                            print(
                                f"[collect] ⚠ API {priority_name} offset={offset}: "
                                f"{exc.__class__.__name__}"
                            )
                        else:
                            print(
                                f"[collect] API {priority_name} offset={offset}: "
                                f"retry {attempt}/{api_retries} после ошибки {exc.__class__.__name__}"
                            )
                            await page.wait_for_timeout(1500 * attempt)

                if response is None:
                    break

                if not response.ok:
                    print(f"[collect] ⚠ API {priority_name} offset={offset}: HTTP {response.status}")
                    break

                payload = await response.json()
                data = payload.get("data") or {}
                priority_name = data.get("priority") or priority_name
                rows = data.get("searchResults") or []
                total = int(data.get("total") or 0)
                count = int(data.get("count") or len(rows) or 0)
                step = len(rows) or count

                if not rows or step <= 0:
                    break

                added_this_page = 0
                added_for_priority = 0
                for item in rows:
                    parsed = self._normalize_search_result(item)
                    if not parsed:
                        continue
                    article_id = parsed["article_id"]
                    if article_id not in seen_for_priority:
                        seen_for_priority.add(article_id)
                        added_for_priority += 1
                    if article_id not in by_id:
                        by_id[article_id] = parsed
                        added_this_page += 1

                print(
                    f"[collect] API {priority_name}: offset={offset}, "
                    f"rows={len(rows)}, new={added_this_page}, "
                    f"priority_new={added_for_priority}, reported_total={total}"
                )

                # Libook often reports total≈150 even when deeper offsets still
                # return real results. Do not stop by reported_total; stop only
                # when pages stop bringing new IDs for this priority.
                if added_for_priority <= 0:
                    no_progress_pages += 1
                    if no_progress_pages >= no_progress_limit:
                        print(
                            f"[collect] API {priority_name}: {no_progress_pages} "
                            "страниц без новых ID, завершаем приоритет."
                        )
                        break
                else:
                    no_progress_pages = 0

                if total and offset + step > total:
                    print(
                        f"[collect] API {priority_name}: reported_total={total} "
                        "достигнут, продолжаем проверять deeper offsets."
                    )
                offset += step

            if offset > max_offset:
                print(
                    f"[collect] API {priority_name}: достигнут защитный лимит "
                    f"offset={max_offset}."
                )

        return list(by_id.values())

    async def _collect_search_results_from_dom(self, page) -> list[dict]:
        """Fallback: old rendered-page collection via Show More."""
        try:
            await page.wait_for_selector("h3.MuiAccordion-heading", timeout=15000)
        except PlaywrightTimeoutError:
            print("[collect] ⚠ Результаты поиска не появились.")
            return []

        # Нажимаем Show More пока кнопка реально добавляет новые результаты.
        # На Libook кнопка иногда остается видимой после конца выдачи, поэтому
        # без проверки прогресса сбор может зациклиться.
        max_show_more_clicks = int(os.getenv("LIBOOK_MAX_SHOW_MORE_CLICKS", "120"))
        show_more_clicks = 0
        no_progress_clicks = 0
        while True:
            more_btn = await page.query_selector(
                "div button.MuiButtonBase-root.MuiButton-root.MuiButton-outlined"
                ".MuiButton-outlinedPrimary.MuiButton-sizeMedium"
            )
            if more_btn:
                btn_text = (await more_btn.inner_text()).strip()
                if "show more" in btn_text.lower():
                    if show_more_clicks >= max_show_more_clicks:
                        print(f"[collect] Достигнут лимит Show More ({max_show_more_clicks}), сохраняем текущие результаты.")
                        break

                    before_count = await page.locator("h3.MuiAccordion-heading").count()
                    print("[collect] Нажимаем 'Show More Results' …")
                    await more_btn.click()
                    show_more_clicks += 1
                    await page.wait_for_timeout(3000)
                    await self._soft_wait_for_network(page)
                    after_count = await page.locator("h3.MuiAccordion-heading").count()
                    if after_count <= before_count:
                        no_progress_clicks += 1
                        print(f"[collect] Show More не добавил новые статьи ({after_count}); попытка без прогресса {no_progress_clicks}/3.")
                    else:
                        no_progress_clicks = 0

                    if no_progress_clicks >= 3:
                        print("[collect] Show More больше не увеличивает выдачу, завершаем сбор.")
                        break
                    continue
            break

        # Собираем все заголовки + ссылки
        results = await page.evaluate("""() => {
            const items = [];
            document.querySelectorAll('h3.MuiAccordion-heading').forEach(h3 => {
                const a = h3.closest('a') || h3.querySelector('a');
                let url = '';
                if (a) {
                    url = a.href;
                } else {
                    // Ищем ближайший родительский/дочерний a
                    const parent = h3.closest('[href]');
                    if (parent) url = parent.href;
                }
                // Пробуем взять href из атрибута data или из accordion
                if (!url) {
                    const accordion = h3.closest('.MuiAccordion-root');
                    if (accordion) {
                        const link = accordion.querySelector('a[href*="/topics/"]');
                        if (link) url = link.href;
                    }
                }
                items.push({ title: h3.innerText.trim(), url });
            });
            return items;
        }""")

        parsed = []
        for r in results:
            title = r.get("title", "").strip()
            url = r.get("url", "").strip()
            article_id = extract_article_id(url) if url else None
            if title:
                parsed.append({
                    "article_id": article_id or "",
                    "title": title,
                    "url": url,
                })
        return parsed

    async def collect_search_results(
        self,
        page,
        query_id: int | None = None,
        refresh_existing: bool = False,
    ) -> list[dict]:
        """
        Собирает статьи по поисковому запросу.
        Возвращает список: [{article_id, title, url}, ...]
        Если query_id передан — сохраняет в БД.
        """
        search_url = f"{self.BASE_URL}/contents/search?search={self.search_query}"
        print(f"[collect] Переходим: {search_url}")
        await self._goto(page, search_url, label="поиск")

        if os.getenv("LIBOOK_USE_DOM_SEARCH", "").lower() in {"1", "true", "yes"}:
            parsed = await self._collect_search_results_from_dom(page)
        else:
            parsed = await self._collect_search_results_from_api(page)
            if not parsed:
                print("[collect] API не вернул статьи, пробуем DOM fallback.")
                parsed = await self._collect_search_results_from_dom(page)

        print(f"[collect] Найдено статей: {len(parsed)}")

        if query_id:
            stats = db_module.save_search_results(
                query_id,
                parsed,
                refresh_existing=refresh_existing,
            )
            print(
                "[collect] Результаты сохранены в БД "
                f"(query_id={query_id}, new={stats.get('new', 0)}, "
                f"refresh={stats.get('refreshed', 0)})"
            )

        return parsed

    # ── find & open article ──────────────────────────────────────

    async def _find_article(self, page) -> tuple[str, str] | None:
        target = self.article_title.lower()

        while True:
            headings = await page.query_selector_all("h3.MuiAccordion-heading")
            for h in headings:
                text = (await h.inner_text()).strip()
                if target in text.lower():
                    print(f"[find] Статья найдена: '{text}'")
                    async with page.expect_navigation(wait_until="domcontentloaded", timeout=90000):
                        await h.click()
                    await self._soft_wait_for_network(page)
                    return text, page.url

            more_btn = await page.query_selector(
                "div button.MuiButtonBase-root.MuiButton-root.MuiButton-outlined"
                ".MuiButton-outlinedPrimary.MuiButton-sizeMedium"
            )
            if more_btn and "show more" in (await more_btn.inner_text()).lower():
                print("[find] Нажимаем 'Show More Results' …")
                await more_btn.click()
                await page.wait_for_timeout(3000)
                await self._soft_wait_for_network(page)
                continue

            print(f"[find] Статья '{self.article_title}' не найдена.")
            return None

    # ── wait for JS render ───────────────────────────────────────

    async def _wait_for_topic_content(self, page):
        print("[article] Ожидаем генерации #topicContent …")
        await page.wait_for_selector("#topicContent", timeout=30000)
        for attempt in range(60):
            result = await page.evaluate("""() => {
                const el = document.querySelector('#topicContent');
                if (!el) return { ready: false, reason: 'no element', textLen: 0 };
                const textLen = el.innerText.trim().length;
                const hasLoader = !!(
                    el.querySelector('[class*="loading"]') ||
                    el.querySelector('[class*="spinner"]') ||
                    el.querySelector('[class*="skeleton"]') ||
                    el.querySelector('[class*="Skeleton"]') ||
                    el.querySelector('[class*="Loading"]')
                );
                return textLen > 100 && !hasLoader
                    ? { ready: true, textLen }
                    : { ready: false, reason: hasLoader ? 'has_loader' : 'too_short', textLen };
            }""")
            if result.get("ready"):
                print(f"[article] Готов ({result['textLen']} симв.), ждём доп. рендер …")
                await page.wait_for_timeout(2000)
                return
            print(f"[article] {attempt+1}/60 — {result.get('reason')} ({result.get('textLen',0)} симв.)")
            await page.wait_for_timeout(1000)
        print("[article] ⚠ Таймаут 60 сек, сохраняем как есть.")

    async def _get_page_article_title(self, page) -> str:
        """Extract the rendered English article title from the topic page."""
        try:
            return await page.evaluate("""() => {
                const bad = new Set(['back', 'outline', 'graphics', 'related topics', 'references']);
                const selectors = [
                    '[data-testid*="title" i]',
                    '[class*="topicTitle"]',
                    '[class*="topic-title"]',
                    '[class*="Title"]',
                    '[class*="title"]'
                ];
                const candidates = [];
                for (const selector of selectors) {
                    document.querySelectorAll(selector).forEach(el => {
                        (el.innerText || '').split('\\n').forEach(part => {
                            const text = part.replace(/^←\\s*/, '').trim();
                            if (text.length < 8) return;
                            if (bad.has(text.toLowerCase())) return;
                            candidates.push(text);
                        });
                    });
                }
                return candidates[0] || '';
            }""")
        except Exception:
            return ""

    # ── save article ─────────────────────────────────────────────

    async def _save_article(self, page, title: str, url: str) -> tuple[str, str]:
        """Возвращает (article_id, article_html)."""
        article_id = extract_article_id(url)
        if not article_id:
            article_id = sanitize_filename(title)
            print(f"[save] ⚠ ID не найден в URL, используем: {article_id}")

        await self._wait_for_topic_content(page)
        rendered_title = (await self._get_page_article_title(page)).strip()
        if rendered_title:
            title = rendered_title

        topic_html = await page.evaluate("""() => {
            const el = document.querySelector('#topicContent');
            return el ? el.outerHTML : '<div id="topicContent">NOT FOUND</div>';
        }""")
        styles_html = await page.evaluate("""() => {
            return Array.from(
                document.querySelectorAll('style, link[rel="stylesheet"]')
            ).map(el => el.outerHTML).join('\\n');
        }""")

        full_html = f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="article-id" content="{article_id}">
  <meta name="article-url" content="{url}">
  <meta name="article-title" content="{title.replace('"', '&quot;')}">
  <meta name="search-query" content="{self.search_query}">
  {styles_html}
</head>
<body>
<!-- ARTICLE ID   : {article_id} -->
<!-- SOURCE URL   : {url} -->
<!-- TITLE        : {title} -->
<!-- SEARCH QUERY : {self.search_query} -->
{topic_html}
</body>
</html>"""

        print(f"[save] article_id={article_id}, title='{title}'")
        return article_id, full_html

    # ── graphics: parse indicator ─────────────────────────────────

    @staticmethod
    def _parse_indicator(text: str) -> tuple[int, int] | None:
        m = re.search(r"(\d+)\s+Of\s+(\d+)", text, re.IGNORECASE)
        if m:
            return int(m.group(1)), int(m.group(2))
        return None

    async def _get_indicator_text(self, page) -> str:
        return await page.evaluate("""() => {
            const btn = document.querySelector(
                '.graphic__toolbar button.MuiButtonGroup-middleButton'
            );
            return btn ? btn.textContent.trim() : '';
        }""")

    async def _get_graphic_html(self, page) -> str:
        return await page.evaluate("""() => {
            const el = document.querySelector('div.graphic');
            return el ? el.outerHTML : '<div class="graphic">NOT FOUND</div>';
        }""")

    async def _wait_for_indicator_change(self, page, prev_text: str, timeout_ms=8000) -> str:
        step, elapsed = 300, 0
        while elapsed < timeout_ms:
            await page.wait_for_timeout(step)
            elapsed += step
            cur = await self._get_indicator_text(page)
            if cur and cur != prev_text:
                return cur
        return await self._get_indicator_text(page)

    async def _process_graphics_tab(self, page, article_id: str) -> list[dict]:
        """
        Возвращает список собранных графиков:
        [{ page_num, total_pages, indicator, html }, ...]
        Алгоритм:
        1. Сохраняем текущую.
        2. Листаем НАЗАД до страницы 1.
        3. Листаем ВПЕРЁД до страницы total_pages.
        4. Дубликаты игнорируются.
        """
        print("[graphics] Ищем вкладку …")
        tab = await page.query_selector('button[tabindex="-1"][role="tab"]')
        if not tab:
            print("[graphics] Вкладка не найдена, пропускаем.")
            return []

        await tab.click()
        await page.wait_for_timeout(2000)

        collection_item = await page.query_selector('[class*="page_collection_item"] p')
        if not collection_item:
            print("[graphics] Элемент коллекции не найден, пропускаем.")
            return []

        item_text = (await collection_item.inner_text()).strip()
        print(f"[graphics] Кликаем по '{item_text}' …")
        await collection_item.click()
        await page.wait_for_timeout(2000)

        try:
            await page.wait_for_selector(".graphic__toolbar", timeout=10000)
        except PlaywrightTimeoutError:
            print("[graphics] Модальное окно не открылось, пропускаем.")
            return []

        # Получаем общее количество страниц
        indicator_text = await self._get_indicator_text(page)
        parsed = self._parse_indicator(indicator_text)

        if not parsed:
            print("[graphics] ⚠ Не удалось распарсить индикатор.")
            total_pages = 1
            current_page = 1
        else:
            current_page, total_pages = parsed
            print(f"[graphics] Всего страниц: {total_pages}, текущая: {current_page}")

        collected: dict[int, dict] = {}  # page_num → data

        # ── Вспомогательная функция сбора (с проверкой на дубли) ──
        async def _grab_current():
            nonlocal current_page, total_pages
            ind = await self._get_indicator_text(page)
            p = self._parse_indicator(ind)

            # Обновляем текущую страницу из реального индикатора
            if p:
                current_page, total_pages = p

            pg_num = current_page

            if pg_num not in collected:
                html = await self._get_graphic_html(page)
                collected[pg_num] = {
                    "page_num": pg_num,
                    "total_pages": total_pages,
                    "indicator": ind,
                    "html": html,
                }
                print(f"[graphics] Сохранена страница {pg_num}/{total_pages}")
            else:
                print(f"[graphics] Страница {pg_num} уже собрана, пропускаем.")
            return pg_num

        # 0. Сохраняем стартовую страницу
        await _grab_current()

        # ── ФАЗА 1: Идём НАЗАД до страницы 1 ──
        print("[graphics] Фаза 1: Движение к первой странице...")
        while current_page > 1:
            prev_btn = await page.query_selector(".graphic__toolbar button.MuiButtonGroup-firstButton")
            if not prev_btn:
                print("[graphics] Кнопка 'предыдущая' не найдена, стоп.")
                break

            prev_ind = await self._get_indicator_text(page)
            try:
                await prev_btn.click()
            except Exception as e:
                print(f"[graphics] ⚠ Ошибка клика 'предыдущая': {e}")
                break

            # Ждём изменения индикатора
            new_ind = await self._wait_for_indicator_change(page, prev_ind, timeout_ms=15000)
            await page.wait_for_timeout(1500)  # доп. рендер контента

            # Если индикатор не изменился — застряли
            if new_ind == prev_ind:
                print("[graphics] Индикатор не изменился, прекращаем движение назад.")
                break

            await _grab_current()

        # ── ФАЗА 2: Идём ВПЕРЁД до последней страницы ──
        # Убеждаемся, что мы точно на странице 1 перед началом (на случай сбоев)
        # Но цикл while сам проверит current_page < total_pages
        print("[graphics] Фаза 2: Движение к последней странице...")

        # Обновляем текущее состояние перед стартом второй фазы
        ind = await self._get_indicator_text(page)
        p = self._parse_indicator(ind)
        if p:
            current_page, total_pages = p

        while current_page < total_pages:
            next_btn = await page.query_selector(".graphic__toolbar button.MuiButtonGroup-lastButton")
            if not next_btn:
                print("[graphics] Кнопка 'следующая' не найдена, стоп.")
                break

            prev_ind = await self._get_indicator_text(page)
            try:
                await next_btn.click()
            except Exception as e:
                print(f"[graphics] ⚠ Ошибка клика 'следующая': {e}")
                break

            new_ind = await self._wait_for_indicator_change(page, prev_ind, timeout_ms=15000)
            await page.wait_for_timeout(1500)

            if new_ind == prev_ind:
                print("[graphics] Индикатор не изменился, прекращаем движение вперёд.")
                break

            await _grab_current()

        # ── Финализация ──
        pages = sorted(collected.values(), key=lambda x: x["page_num"])
        print(f"[graphics] Итого собрано уникальных: {len(pages)}/{total_pages}")
        return pages

    # ── main scrape one article ───────────────────────────────────

    async def scrape_article(self, page, title: str, url: str) -> bool:
        """
        Скрапит одну конкретную статью (страница уже открыта).
        Сохраняет в БД.
        """
        article_id = None
        try:
            article_id, article_html = await self._save_article(page, title, url)

            # Пытаемся собрать графики, но не падаем, если что-то пошло не так
            needs_review = False
            try:
                graphics = await self._process_graphics_tab(page, article_id)
            except Exception as ge:
                print(f"[graphics] ❌ Ошибка при сборе графиков для {article_id}: {ge}")
                graphics = []
                needs_review = True

            graphics_meta = {
                "total_pages": graphics[0]["total_pages"] if graphics else 0,
                "pages": [{"page_num": g["page_num"], "indicator": g["indicator"]} for g in graphics],
            }

            content_changed = db_module.save_article(
                article_id=article_id,
                title=title,
                url=url,
                search_query=self.search_query,
                account_login=self.login,
                article_html=article_html,
                graphics_meta=graphics_meta,
            )

            for g in graphics:
                db_module.save_article_graphic(
                    article_id=article_id,
                    page_num=g["page_num"],
                    total_pages=g["total_pages"],
                    indicator=g["indicator"],
                    html=g["html"],
                )

            # Если были проблемы с графиками — помечаем для ручной проверки
            if needs_review:
                try:
                    db_module.mark_article_needs_review(article_id)
                except Exception as me:
                    print(f"[scrape_article] ⚠ Не удалось пометить статью {article_id} для ревью: {me}")

            if self.account_id:
                db_module.increment_daily_count(self.account_id)
                db_module.log_parse(self.account_id, article_id, "success")

            # Автоматическая выгрузка в WordPress после сохранения.
            # При повторном обходе неизменные статьи не трогаем, чтобы не плодить лишние обновления.
            saved_article = db_module.get_article(article_id) or {}
            should_export = content_changed or not saved_article.get("exported_to_wp")
            if should_export:
                try:
                    from wp_export_routes import _export_single_article_to_wp

                    exported = _export_single_article_to_wp(article_id)
                    print(f"[wp-export] Автовыгрузка статьи {article_id}: {'OK' if exported else 'skip'}")
                except Exception as we:
                    print(f"[wp-export] ⚠ Ошибка автвыгрузки статьи {article_id}: {we}")
            else:
                print(f"[wp-export] Статья {article_id} не изменилась, выгрузку пропускаем.")

            print(f"\n✅ Статья {article_id} сохранена в БД.")
            return True

        except Exception as e:
            print(f"[scrape_article] ❌ {e}")
            if self.account_id:
                db_module.log_parse(self.account_id, article_id or url, "error", str(e))
            # Если статья была частично сохранена — помечаем для ревью
            if article_id:
                try:
                    db_module.mark_article_needs_review(article_id)
                except Exception:
                    pass
            return False

    # ── run: single article by title ────────────────────────────

    async def run(self):
        async with async_playwright() as pw:
            browser, context = await self._make_context(pw)
            page = await context.new_page()
            try:
                await self._login(page)
                await self._save_session(context)

                search_url = f"{self.BASE_URL}/contents/search?search={self.search_query}"
                await self._goto(page, search_url, label="поиск")
                try:
                    await page.wait_for_selector("h3.MuiAccordion-heading", timeout=15000)
                except PlaywrightTimeoutError:
                    pass

                if self.article_title:
                    result = await self._find_article(page)
                    if not result:
                        print(f"[done] ❌ Статья '{self.article_title}' не найдена.")
                        return
                    title, url = result
                    await self.scrape_article(page, title, url)
                else:
                    print("[done] article_title не задан, только поиск.")

            except Exception as e:
                print(f"[run] ❌ {e}")
                raise
            finally:
                await self._save_session(context)
                await context.close()
                await browser.close()

    # ── run: collect only ─────────────────────────────────────────

    async def run_collect_only(self, query_id: int | None = None) -> list[dict]:
        """Только собирает результаты поиска без скачивания статей."""
        async with async_playwright() as pw:
            browser, context = await self._make_context(pw)
            page = await context.new_page()
            try:
                await self._login(page)
                await self._save_session(context)
                return await self.collect_search_results(page, query_id)
            finally:
                await self._save_session(context)
                await context.close()
                await browser.close()


# ─────────────────────────── CLI entry ──────────────────────────

'''async def main():
    db_module.init_db()
    scraper = LibookScraper(
        login=os.getenv("LIBOOK_LOGIN", ""),
        password=os.getenv("LIBOOK_PASSWORD", ""),
        proxy=os.getenv("LIBOOK_PROXY", ""),
        search_query="migraine",
        article_title="Migraine",
        headless=False,
    )
    await scraper.run()


if __name__ == "__main__":
    asyncio.run(main())'''
