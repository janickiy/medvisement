"""
scheduler.py — Планировщик параллельного парсинга.

- Каждый аккаунт работает в отдельном asyncio-потоке.
- Соблюдаются ограничения: max_per_day и interval_min.
- Управляется через start() / stop() — вызывается из Flask.
"""

import asyncio
import threading
import time
from datetime import datetime
from typing import Optional

import database as db_module
from libook_scraper import LibookScraper

# ── Глобальное состояние ─────────────────────────────────────────

_scheduler_thread: Optional[threading.Thread] = None
_stop_event = threading.Event()
_status: dict = {
    "running": False,
    "mode": "",
    "workers": {},   # login → {"status": str, "last_article": str, "count_today": int}
}


def get_status() -> dict:
    accounts = db_module.get_all_accounts()
    workers = {}
    for acc in accounts:
        workers[acc["login"]] = {
            "status": _status["workers"].get(acc["login"], {}).get("status", "idle"),
            "last_article": _status["workers"].get(acc["login"], {}).get("last_article", ""),
            "count_today": db_module.get_daily_count(acc["id"]),
        }
    return {
        "running": _status["running"],
        "mode": _status.get("mode", ""),
        "workers": workers,
    }


def _set_worker_status(login: str, status: str, last_article: str = ""):
    _status["workers"].setdefault(login, {})
    _status["workers"][login]["status"] = status
    if last_article:
        _status["workers"][login]["last_article"] = last_article


# ── Worker для одного аккаунта ───────────────────────────────────

async def _collect_query(account: dict, query: dict, stop_event: asyncio.Event) -> bool:
    """Собирает выдачу одного активного запроса и обновляет очередь."""
    login = account["login"]
    refresh_existing = bool(query.get("last_collected_at"))
    _set_worker_status(login, f"collecting: {query['query']}")

    from playwright.async_api import async_playwright

    async with async_playwright() as pw:
        scraper = LibookScraper(
            login=login,
            password=account["password"],
            proxy=account["proxy"],
            search_query=query["query"],
            account_id=account["id"],
            headless=True,
        )
        browser, context = await scraper._make_context(pw)
        page = await context.new_page()
        try:
            await scraper._login(page)
            await scraper._save_session(context)
            print(
                f"[worker:{login}] Сбор результатов для '{query['query']}' "
                f"({'refresh' if refresh_existing else 'first pass'}) …"
            )
            await scraper.collect_search_results(
                page,
                query_id=query["id"],
                refresh_existing=refresh_existing,
            )
            return True
        except Exception as e:
            print(f"[worker:{login}] ❌ Ошибка сбора '{query['query']}': {e}")
            db_module.log_parse(account["id"], None, "collect_error", f"{query['query']}: {e}")
            _set_worker_status(login, f"collect error: {str(e)[:80]}")
            return False
        finally:
            await scraper._save_session(context)
            await context.close()
            await browser.close()


async def _scrape_item(account: dict, item: dict) -> bool:
    login = account["login"]
    article_id = item["article_id"]
    title = item["title"]
    url = item["url"]
    search_query = item["query"]

    print(f"[worker:{login}] Скачиваем: '{title}' ({article_id})")
    _set_worker_status(login, f"scraping: {title}", last_article=title)

    from playwright.async_api import async_playwright

    try:
        async with async_playwright() as pw:
            scraper = LibookScraper(
                login=login,
                password=account["password"],
                proxy=account["proxy"],
                search_query=search_query,
                account_id=account["id"],
                headless=True,
            )
            browser, context = await scraper._make_context(pw)
            page = await context.new_page()
            try:
                await scraper._login(page)
                await scraper._save_session(context)
                await scraper._goto(page, url, label="статью")
                success = await scraper.scrape_article(page, title, url)
                if success:
                    _set_worker_status(login, "idle", last_article=title)
                else:
                    _set_worker_status(login, "error on last article", last_article=title)
                return success
            finally:
                await scraper._save_session(context)
                await context.close()
                await browser.close()
    except Exception as e:
        print(f"[worker:{login}] ❌ {e}")
        db_module.log_parse(account["id"], article_id, "error", str(e))
        _set_worker_status(login, f"error: {str(e)[:80]}")
        return False


def _get_todo(query_id: int | None):
    if query_id:
        return db_module.get_articles_not_downloaded_for_query(query_id)
    return db_module.get_articles_not_downloaded()


async def _account_worker(
    account: dict,
    stop_event: asyncio.Event,
    *,
    query_id: int | None = None,
    article_id: str | None = None,
):
    login = account["login"]
    print(f"[worker:{login}] Запущен.")
    _set_worker_status(login, "started")

    while not stop_event.is_set():
        # Проверяем лимит на день
        daily = db_module.get_daily_count(account["id"])
        if daily >= account["max_per_day"]:
            print(f"[worker:{login}] Достигнут дневной лимит ({daily}/{account['max_per_day']}), ждём.")
            _set_worker_status(login, f"daily limit reached ({daily}/{account['max_per_day']})")
            # Ждём до следующего дня или остановки
            for _ in range(3600):
                if stop_event.is_set():
                    break
                await asyncio.sleep(1)
            continue

        if article_id:
            item = db_module.get_search_result(article_id)
            if not item:
                print(f"[worker:{login}] Выбранная статья {article_id} не найдена в результатах.")
                _set_worker_status(login, f"article not found: {article_id}")
                break
            if not item.get("query_active"):
                print(f"[worker:{login}] Запрос выбранной статьи отключён, пропускаем.")
                _set_worker_status(login, "selected article query disabled")
                break
            await _scrape_item(account, item)
            break

        # Получаем следующую статью для скачивания
        todo = _get_todo(query_id)
        if not todo:
            # Все статьи скачаны — собираем следующий активный запрос.
            query = db_module.get_next_query_for_collection(query_id)
            if not query:
                print(f"[worker:{login}] Нет активных поисковых запросов, ожидание.")
                _set_worker_status(login, "no queries, waiting")
                await asyncio.sleep(60)
                continue

            await _collect_query(account, query, stop_event)
            continue

        # Берём первую незагруженную статью
        item = todo[0]
        await _scrape_item(account, item)

        # Интервал между статьями
        interval_sec = account["interval_min"] * 60
        print(f"[worker:{login}] Ожидание {account['interval_min']} мин. перед следующей статьёй …")
        for _ in range(interval_sec):
            if stop_event.is_set():
                break
            await asyncio.sleep(1)

    print(f"[worker:{login}] Остановлен.")
    _set_worker_status(login, "stopped")


# ── Запуск всех воркеров в одном event loop ──────────────────────

def _run_scheduler(
    stop_threading_event: threading.Event,
    *,
    query_id: int | None = None,
    article_id: str | None = None,
):
    loop = asyncio.new_event_loop()
    asyncio.set_event_loop(loop)
    stop_async_event = asyncio.Event()

    async def _main():
        accounts = [a for a in db_module.get_all_accounts() if a["active"]]
        if not accounts:
            print("[scheduler] Нет активных аккаунтов.")
            return

        # Для ручного выбора запроса/статьи используем один аккаунт, чтобы не получить дубли.
        worker_accounts = accounts[:1] if (query_id or article_id) else accounts
        print(f"[scheduler] Запускаем {len(worker_accounts)} воркеров …")
        tasks = [
            _account_worker(
                acc,
                stop_async_event,
                query_id=query_id,
                article_id=article_id,
            )
            for acc in worker_accounts
        ]
        await asyncio.gather(*tasks, return_exceptions=True)

    # Поток от Flask → asyncio event
    def _watch_stop():
        while not stop_threading_event.is_set():
            time.sleep(0.5)
        stop_async_event.set()

    watcher = threading.Thread(target=_watch_stop, daemon=True)
    watcher.start()

    try:
        loop.run_until_complete(_main())
    finally:
        loop.close()


# ── Public API ───────────────────────────────────────────────────

def start(query_id: int | None = None, article_id: str | None = None):
    global _scheduler_thread, _stop_event
    if _status["running"]:
        print("[scheduler] Уже запущен.")
        return
    _stop_event = threading.Event()
    _status["running"] = True
    if article_id:
        _status["mode"] = f"article:{article_id}"
    elif query_id:
        _status["mode"] = f"query:{query_id}"
    else:
        _status["mode"] = "active-queries"

    def _thread_target():
        _run_scheduler(_stop_event, query_id=query_id, article_id=article_id)
        _status["running"] = False
        _status["mode"] = ""
        print("[scheduler] Все воркеры завершены.")

    _scheduler_thread = threading.Thread(target=_thread_target, daemon=True)
    _scheduler_thread.start()
    print("[scheduler] Запущен.")


def stop():
    global _stop_event
    if not _status["running"]:
        print("[scheduler] Не запущен.")
        return
    print("[scheduler] Остановка …")
    _stop_event.set()
