"""
libook_scraper.py
Скрапер для utd.libook.xyz с использованием Playwright (Chromium).

Структура сохранения:
  downloaded_articles/
    {article_id}/
      meta.json          ← id, url, title, search_query
      article.html       ← содержимое #topicContent (после полной генерации JS)
      graphics/
        graphics_meta.json
        graphic_1.html   ← div.graphic страница 1 из N
        graphic_2.html
        ...
        graphic_N.html
"""

import asyncio
import json
import os
import re
from pathlib import Path

from playwright.async_api import async_playwright, TimeoutError as PlaywrightTimeoutError


# ─────────────────────────── helpers ────────────────────────────

def extract_article_id(url: str) -> str | None:
    """Извлекает числовой ID статьи из URL вида .../topics/5245"""
    m = re.search(r"/topics/(\d+)", url)
    return m.group(1) if m else None


def sanitize_filename(name: str) -> str:
    return re.sub(r'[\\/*?:"<>|]', "_", name).strip()[:200]


# ─────────────────────────── main class ─────────────────────────

class LibookScraper:
    """
    Параметры:
        login          – логин (email)
        password       – пароль
        proxy          – строка вида "socks5://host:port"
                         или "socks5://host:port" (без аутентификации)
        search_query   – поисковый запрос, например "migraine"
        article_title  – название статьи (поиск по подстроке, без учёта регистра)
        base_dir       – корневая папка скрипта (по умолчанию рядом с файлом)
        headless       – запускать браузер в фоновом режиме (по умолчанию False)
    """

    BASE_URL = "https://utd.libook.xyz"

    def __init__(
        self,
        login: str,
        password: str,
        proxy: str,
        search_query: str,
        article_title: str,
        base_dir: str | None = None,
        headless: bool = False,
    ):
        self.login = login
        self.password = password
        self.proxy = proxy
        self.search_query = search_query
        self.article_title = article_title
        self.headless = headless

        script_dir = Path(base_dir) if base_dir else Path(__file__).resolve().parent
        self.session_dir = script_dir / "session_data"
        self.articles_dir = script_dir / "downloaded_articles"
        self.session_dir.mkdir(parents=True, exist_ok=True)
        self.articles_dir.mkdir(parents=True, exist_ok=True)

    # ── proxy parsing ────────────────────────────────────────────

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

    # ── browser / context ────────────────────────────────────────

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

    # ── login flow ───────────────────────────────────────────────

    async def _login(self, page):
        print(f"[login] Открываем {self.BASE_URL} …")
        await page.goto(self.BASE_URL, wait_until="networkidle")

        # Шаг 1: поля username / password
        if await page.query_selector("#id_username"):
            print("[login] Найдена форма логина, вводим данные …")
            await page.fill("#id_username", self.login)
            await page.fill("#id_password", self.password)
            await page.click("button[type='submit']")
            await page.wait_for_load_state("networkidle")
            print(f"[login] После отправки формы: {page.url}")

        # Шаг 2: промежуточная страница "Sign in with libook"
        signin_btn = await page.query_selector(
            "form[action*='/api/auth/signin/libook'] button[type='submit']"
        )
        if signin_btn:
            print("[login] Найдена кнопка 'Sign in with libook', нажимаем …")
            await signin_btn.click()
            await page.wait_for_load_state("networkidle")
            print(f"[login] После нажатия: {page.url}")

    # ── search ───────────────────────────────────────────────────

    async def _search(self, page):
        search_url = f"{self.BASE_URL}/contents/search?search={self.search_query}"
        print(f"[search] Переходим к результатам поиска: {search_url}")
        await page.goto(search_url, wait_until="networkidle")
        try:
            await page.wait_for_selector("h3.MuiAccordion-heading", timeout=15000)
        except PlaywrightTimeoutError:
            print("[search] Результаты поиска не появились за 15 сек.")

    # ── find article ─────────────────────────────────────────────

    async def _find_article(self, page) -> tuple[str, str] | None:
        """
        Ищет статью по названию, при необходимости нажимает 'Show More Results'.
        Возвращает (title, url) или None.
        """
        target = self.article_title.lower()

        while True:
            headings = await page.query_selector_all("h3.MuiAccordion-heading")
            for h in headings:
                text = (await h.inner_text()).strip()
                if target in text.lower():
                    print(f"[find] Найдена статья: '{text}'")
                    async with page.expect_navigation(wait_until="networkidle"):
                        await h.click()
                    article_url = page.url
                    print(f"[find] URL статьи: {article_url}")
                    return text, article_url

            # Кнопка «Show More Results»
            more_btn = await page.query_selector("div button.MuiButton-outlinedPrimary")
            if more_btn:
                btn_text = (await more_btn.inner_text()).strip()
                if "show more" in btn_text.lower():
                    print("[find] Нажимаем 'Show More Results' …")
                    await more_btn.click()
                    await page.wait_for_timeout(3000)
                    await page.wait_for_load_state("networkidle")
                    continue

            print(f"[find] Статья '{self.article_title}' не найдена.")
            return None

    # ── wait for JS-rendered content ─────────────────────────────

    async def _wait_for_topic_content(self, page):
        """
        Ждёт пока #topicContent будет полностью сгенерирован JS.
        Критерий: элемент существует, содержит >100 символов текста,
        нет активных лоадеров/спиннеров/скелетонов.
        """
        print("[article] Ожидаем генерации #topicContent …")

        await page.wait_for_selector("#topicContent", timeout=30000)

        for attempt in range(60):  # до 60 секунд
            result = await page.evaluate("""() => {
                const el = document.querySelector('#topicContent');
                if (!el) return { ready: false, reason: 'no element', textLen: 0 };

                const textLen = el.innerText.trim().length;

                // Признаки незавершённой загрузки
                const hasLoader = !!(
                    el.querySelector('[class*="loading"]') ||
                    el.querySelector('[class*="spinner"]') ||
                    el.querySelector('[class*="skeleton"]') ||
                    el.querySelector('[class*="Skeleton"]') ||
                    el.querySelector('[class*="Loading"]')
                );

                if (textLen > 100 && !hasLoader) {
                    return { ready: true, textLen };
                }
                return { ready: false, reason: hasLoader ? 'has_loader' : 'too_short', textLen };
            }""")

            if result.get("ready"):
                print(f"[article] #topicContent готов ({result['textLen']} симв. текста)")
                # Дополнительная пауза — даём добить отложенный рендер (картинки, таблицы)
                await page.wait_for_timeout(2000)
                return

            print(f"[article] Попытка {attempt+1}/60 — {result.get('reason')} "
                  f"({result.get('textLen', 0)} симв.)")
            await page.wait_for_timeout(1000)

        print("[article] ⚠ Таймаут (60 сек), сохраняем как есть.")

    # ── save article html ────────────────────────────────────────

    async def _save_article(self, page, title: str, url: str) -> tuple[Path, str]:
        """
        Сохраняет статью в папку, названную по ID статьи.
        Возвращает (article_dir, article_id).
        """
        article_id = extract_article_id(url)
        if not article_id:
            article_id = sanitize_filename(title)
            print(f"[save] ⚠ ID не найден в URL, используем имя: {article_id}")
        else:
            print(f"[save] ID статьи: {article_id}")

        article_dir = self.articles_dir / article_id
        article_dir.mkdir(parents=True, exist_ok=True)

        # Ждём полной отрисовки JS-контента
        await self._wait_for_topic_content(page)

        # Забираем outerHTML #topicContent
        topic_html = await page.evaluate("""() => {
            const el = document.querySelector('#topicContent');
            return el ? el.outerHTML : '<div id="topicContent">NOT FOUND</div>';
        }""")

        # Собираем теги стилей (для корректного отображения при просмотре HTML)
        styles_html = await page.evaluate("""() => {
            return Array.from(
                document.querySelectorAll('style, link[rel="stylesheet"]')
            ).map(el => el.outerHTML).join('\\n');
        }""")

        article_html = f"""<!DOCTYPE html>
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
<!-- ============================================================ -->
<!-- ARTICLE ID    : {article_id}                                 -->
<!-- SOURCE URL    : {url}                                        -->
<!-- TITLE         : {title}                                      -->
<!-- SEARCH QUERY  : {self.search_query}                          -->
<!-- ============================================================ -->
{topic_html}
</body>
</html>"""

        html_path = article_dir / "article.html"
        html_path.write_text(article_html, encoding="utf-8")
        print(f"[save] article.html сохранён: {html_path}")

        # meta.json
        meta = {
            "id": article_id,
            "url": url,
            "title": title,
            "search_query": self.search_query,
        }
        meta_path = article_dir / "meta.json"
        meta_path.write_text(json.dumps(meta, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"[save] meta.json сохранён: {meta_path}")

        return article_dir, article_id

    # ── graphics tab ─────────────────────────────────────────────

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

    async def _wait_for_indicator_change(self, page, prev_text: str, timeout_ms: int = 8000) -> str:
        """Ждёт изменения индикатора страницы (N Of M), возвращает новый текст."""
        step = 300
        elapsed = 0
        while elapsed < timeout_ms:
            await page.wait_for_timeout(step)
            elapsed += step
            cur = await self._get_indicator_text(page)
            if cur and cur != prev_text:
                print(f"[graphics] Индикатор: '{prev_text}' → '{cur}'")
                return cur
        print(f"[graphics] ⚠ Индикатор не изменился за {timeout_ms} мс.")
        return await self._get_indicator_text(page)

    async def _save_graphic_page(
        self,
        page,
        graphics_dir: Path,
        article_id: str,
        page_num: int,
        total: int,
        indicator: str,
        graphics_meta: dict,
    ):
        html = await self._get_graphic_html(page)
        file_name = f"graphic_{page_num}.html"
        path = graphics_dir / file_name
        path.write_text(
            f"<!-- Article ID : {article_id} -->\n"
            f"<!-- Page       : {page_num} of {total} -->\n"
            f"<!-- Indicator  : {indicator} -->\n"
            f"{html}",
            encoding="utf-8",
        )
        graphics_meta["pages"][str(page_num)] = {
            "indicator": indicator,
            "file": file_name,
        }
        print(f"[graphics] Сохранена страница {page_num}/{total}: {path}")

    async def _process_graphics_tab(self, page, article_dir: Path, article_id: str):
        print("[graphics] Ищем вкладку с tabindex='-1' role='tab' …")

        tab = await page.query_selector('button[tabindex="-1"][role="tab"]')
        if not tab:
            print("[graphics] Вкладка не найдена, пропускаем.")
            return

        tab_text = (await tab.inner_text()).strip()
        print(f"[graphics] Кликаем по вкладке: '{tab_text}'")
        await tab.click()
        await page.wait_for_timeout(2000)

        # Ищем элемент коллекции
        collection_item = await page.query_selector('[class*="page_collection_item"] p')
        if not collection_item:
            print("[graphics] '[class*=\"page_collection_item\"] p' не найден, пропускаем.")
            return

        item_text = (await collection_item.inner_text()).strip()
        print(f"[graphics] Кликаем по элементу коллекции: '{item_text}'")
        await collection_item.click()
        await page.wait_for_timeout(2000)

        # Ждём тулбар
        try:
            await page.wait_for_selector(".graphic__toolbar", timeout=10000)
        except PlaywrightTimeoutError:
            print("[graphics] Модальное окно (.graphic__toolbar) не появилось, пропускаем.")
            return

        print("[graphics] Модальное окно открыто.")

        # Читаем индикатор "N Of M"
        indicator_text = await self._get_indicator_text(page)
        print(f"[graphics] Начальный индикатор: '{indicator_text}'")

        total_pages = 1
        current_page = 1
        m = re.search(r"(\d+)\s+Of\s+(\d+)", indicator_text, re.IGNORECASE)
        if m:
            current_page = int(m.group(1))
            total_pages = int(m.group(2))
            print(f"[graphics] Страница {current_page} из {total_pages}")
        else:
            print("[graphics] ⚠ Не удалось распарсить индикатор, считаем total=1")

        graphics_dir = article_dir / "graphics"
        graphics_dir.mkdir(exist_ok=True)

        graphics_meta: dict = {
            "article_id": article_id,
            "collection_item": item_text,
            "total_pages": total_pages,
            "pages": {},
        }

        # Сохраняем текущую (обычно последнюю) страницу
        await self._save_graphic_page(
            page, graphics_dir, article_id,
            current_page, total_pages, indicator_text, graphics_meta
        )

        # Листаем назад до страницы 1
        pages_to_fetch = current_page - 1  # сколько раз нажать «предыдущая»
        for _ in range(pages_to_fetch):
            prev_btn = await page.query_selector(
                ".graphic__toolbar button.MuiButtonGroup-firstButton"
            )
            if not prev_btn:
                print("[graphics] Кнопка 'предыдущая' не найдена, останавливаемся.")
                break

            prev_indicator = await self._get_indicator_text(page)
            print(f"[graphics] Нажимаем 'предыдущая' (индикатор: '{prev_indicator}') …")
            await prev_btn.click()

            new_indicator = await self._wait_for_indicator_change(page, prev_indicator)
            # Дополнительная пауза для завершения рендера
            await page.wait_for_timeout(1500)

            m2 = re.search(r"(\d+)\s+Of\s+(\d+)", new_indicator, re.IGNORECASE)
            pg_num = int(m2.group(1)) if m2 else (current_page - _ - 1)

            await self._save_graphic_page(
                page, graphics_dir, article_id,
                pg_num, total_pages, new_indicator, graphics_meta
            )

        # graphics_meta.json
        meta_path = graphics_dir / "graphics_meta.json"
        meta_path.write_text(
            json.dumps(graphics_meta, ensure_ascii=False, indent=2),
            encoding="utf-8"
        )
        print(f"[graphics] graphics_meta.json сохранён: {meta_path}")
        print(f"[graphics] ✅ Сохранено страниц: {len(graphics_meta['pages'])} из {total_pages}")

    # ── entry point ──────────────────────────────────────────────

    async def run(self):
        async with async_playwright() as pw:
            browser, context = await self._make_context(pw)
            page = await context.new_page()

            try:
                # 1. Авторизация
                await self._login(page)
                await self._save_session(context)

                # 2. Поиск
                await self._search(page)

                # 3. Нахождение и переход к статье
                result = await self._find_article(page)
                if not result:
                    print("[done] ❌ Статья не найдена. Завершение.")
                    return

                title, url = result

                # 4. Сохранение HTML статьи (с ожиданием JS-рендера)
                article_dir, article_id = await self._save_article(page, title, url)

                # 5. Графическая вкладка
                await self._process_graphics_tab(page, article_dir, article_id)

                print("\n" + "=" * 60)
                print("✅ Все данные успешно получены и сохранены!")
                print(f"   ID статьи  : {article_id}")
                print(f"   URL        : {url}")
                print(f"   Папка      : {article_dir}")
                print("=" * 60)

            except Exception as exc:
                print(f"[error] ❌ {exc}")
                raise
            finally:
                await self._save_session(context)
                await context.close()
                await browser.close()


# ─────────────────────────── CLI entry ──────────────────────────

async def main():
    login = os.getenv("LIBOOK_LOGIN")
    password = os.getenv("LIBOOK_PASSWORD")
    if not login or not password:
        raise RuntimeError("Set LIBOOK_LOGIN and LIBOOK_PASSWORD before running the scraper.")

    scraper = LibookScraper(
        login=login,
        password=password,
        proxy=os.getenv("LIBOOK_PROXY", ""),
        search_query=os.getenv("LIBOOK_SEARCH_QUERY", "migraine"),
        article_title=os.getenv("LIBOOK_ARTICLE_TITLE", "Cervicogenic headache"),   # поиск по подстроке, без учёта регистра
        headless=False,             # True — фоновый режим
    )
    await scraper.run()


if __name__ == "__main__":
    asyncio.run(main())
