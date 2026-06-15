import asyncio
import threading

from flask import Blueprint, redirect, url_for

import database as db_module


retry_bp = Blueprint("retry", __name__)


@retry_bp.route("/articles/<article_id>/retry", methods=["POST"])
def retry_article(article_id: str):
    """
    Снимает needs_manual_review и запускает перескачивание статьи в фоне.
    После успешного скачивания автвыгрузка в WP произойдёт внутри scrape_article().
    """
    art = db_module.get_article(article_id)
    if not art:
        return redirect(url_for("articles_list"))

    db_module.clear_article_manual_review(article_id)

    # Найдём аккаунт, которым статья была скачана
    accounts = db_module.get_all_accounts()
    acc = next((a for a in accounts if a["login"] == art.get("account_login")), None)
    if not acc:
        # Если аккаунта уже нет — не можем перескачать
        return redirect(url_for("article_detail", article_id=article_id))

    url = art["url"]
    title = art["title"]
    search_query = art["search_query"]

    def _bg():
        async def _run():
            from playwright.async_api import async_playwright
            from libook_scraper import LibookScraper

            async with async_playwright() as pw:
                scraper = LibookScraper(
                    login=acc["login"],
                    password=acc["password"],
                    proxy=acc.get("proxy", ""),
                    search_query=search_query,
                    account_id=acc["id"],
                    headless=True,
                )
                browser, context = await scraper._make_context(pw)
                page = await context.new_page()
                try:
                    await scraper._login(page)
                    await scraper._save_session(context)
                    await scraper._goto(page, url, label="статью")
                    await scraper.scrape_article(page, title, url)
                finally:
                    await scraper._save_session(context)
                    await context.close()
                    await browser.close()

        loop = asyncio.new_event_loop()
        loop.run_until_complete(_run())
        loop.close()

    threading.Thread(target=_bg, daemon=True).start()
    return redirect(url_for("article_detail", article_id=article_id))
