"""
database.py — SQLite schema and helpers via sqlite3 (no ORM dependency).
"""

import sqlite3
import json
from pathlib import Path
from contextlib import contextmanager

DB_PATH = Path(__file__).resolve().parent / "libook.db"


def get_connection() -> sqlite3.Connection:
    conn = sqlite3.connect(str(DB_PATH))
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA foreign_keys=ON")
    return conn


@contextmanager
def db():
    conn = get_connection()
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def init_db():
    with db() as conn:
        conn.executescript("""
        -- Пользователи/аккаунты
        CREATE TABLE IF NOT EXISTS accounts (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            login       TEXT NOT NULL UNIQUE,
            password    TEXT NOT NULL,
            proxy       TEXT NOT NULL DEFAULT '',
            active      INTEGER NOT NULL DEFAULT 1,
            max_per_day INTEGER NOT NULL DEFAULT 20,
            interval_min INTEGER NOT NULL DEFAULT 10,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        -- Поисковые запросы
        CREATE TABLE IF NOT EXISTS search_queries (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            query      TEXT NOT NULL UNIQUE,
            active     INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            last_collected_at TEXT
        );

        -- Результаты поиска (все найденные статьи)
        CREATE TABLE IF NOT EXISTS search_results (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            query_id     INTEGER NOT NULL REFERENCES search_queries(id),
            article_id   TEXT NOT NULL,
            title        TEXT NOT NULL,
            url          TEXT NOT NULL,
            downloaded   INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(query_id, article_id)
        );

        -- Скачанные статьи
        CREATE TABLE IF NOT EXISTS articles (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            article_id      TEXT NOT NULL UNIQUE,
            title           TEXT NOT NULL,
            url             TEXT NOT NULL,
            search_query    TEXT NOT NULL,
            account_login   TEXT NOT NULL,
            article_html    TEXT,
            graphics_meta   TEXT,
            wp_post_id      TEXT,
            exported_to_wp  INTEGER NOT NULL DEFAULT 0,
            needs_manual_review INTEGER NOT NULL DEFAULT 0,
            content_changed INTEGER NOT NULL DEFAULT 1,
            downloaded_at   TEXT NOT NULL DEFAULT (datetime('now'))
        );

        -- HTML-данные из графических страниц (по одной записи на каждую graphic_N)
        CREATE TABLE IF NOT EXISTS article_graphics (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            article_id  TEXT NOT NULL REFERENCES articles(article_id),
            page_num    INTEGER NOT NULL,
            total_pages INTEGER NOT NULL,
            indicator   TEXT,
            html        TEXT,
            UNIQUE(article_id, page_num)
        );

        -- Лог парсинга
        CREATE TABLE IF NOT EXISTS parse_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id  INTEGER REFERENCES accounts(id),
            article_id  TEXT,
            status      TEXT,
            message     TEXT,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        -- Счётчики за день (для rate-limiting)
        CREATE TABLE IF NOT EXISTS daily_counts (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id  INTEGER NOT NULL REFERENCES accounts(id),
            date        TEXT NOT NULL,
            count       INTEGER NOT NULL DEFAULT 0,
            UNIQUE(account_id, date)
        );

        -- Глобальные настройки приложения
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        );
        """)
        cur = conn.execute("PRAGMA table_info(search_queries)")
        existing_query_cols = {row["name"] for row in cur.fetchall()}
        if "last_collected_at" not in existing_query_cols:
            conn.execute("ALTER TABLE search_queries ADD COLUMN last_collected_at TEXT")

        # Миграция существующей таблицы articles — добавляем поля для WordPress, если их ещё нет
        cur = conn.execute("PRAGMA table_info(articles)")
        existing_cols = {row["name"] for row in cur.fetchall()}
        if "wp_post_id" not in existing_cols:
            conn.execute("ALTER TABLE articles ADD COLUMN wp_post_id TEXT")
        if "exported_to_wp" not in existing_cols:
            conn.execute("ALTER TABLE articles ADD COLUMN exported_to_wp INTEGER NOT NULL DEFAULT 0")
        if "needs_manual_review" not in existing_cols:
            conn.execute("ALTER TABLE articles ADD COLUMN needs_manual_review INTEGER NOT NULL DEFAULT 0")
        if "content_changed" not in existing_cols:
            conn.execute("ALTER TABLE articles ADD COLUMN content_changed INTEGER NOT NULL DEFAULT 1")
    print(f"[db] База данных инициализирована: {DB_PATH}")


# ─── helpers ────────────────────────────────────────────────────

def get_all_accounts():
    with db() as conn:
        return [dict(r) for r in conn.execute("SELECT * FROM accounts ORDER BY id").fetchall()]


def get_setting(key: str, default: str = "") -> str:
    with db() as conn:
        row = conn.execute("SELECT value FROM settings WHERE key=?", (key,)).fetchone()
        return row["value"] if row else default


def set_setting(key: str, value: str):
    with db() as conn:
        conn.execute(
            "INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value",
            (key, value),
        )

def upsert_account(login, password, proxy, active, max_per_day, interval_min, acc_id=None):
    with db() as conn:
        if acc_id:
            conn.execute(
                "UPDATE accounts SET login=?,password=?,proxy=?,active=?,max_per_day=?,interval_min=? WHERE id=?",
                (login, password, proxy, active, max_per_day, interval_min, acc_id)
            )
        else:
            conn.execute(
                "INSERT INTO accounts(login,password,proxy,active,max_per_day,interval_min) VALUES(?,?,?,?,?,?)",
                (login, password, proxy, active, max_per_day, interval_min)
            )

def delete_account(acc_id):
    with db() as conn:
        conn.execute("DELETE FROM parse_log WHERE account_id=?", (acc_id,))
        conn.execute("DELETE FROM daily_counts WHERE account_id=?", (acc_id,))
        conn.execute("DELETE FROM accounts WHERE id=?", (acc_id,))

def get_all_queries():
    with db() as conn:
        return [dict(r) for r in conn.execute("SELECT * FROM search_queries ORDER BY query COLLATE NOCASE, id").fetchall()]


def get_all_queries_with_stats():
    with db() as conn:
        return [dict(r) for r in conn.execute("""
            SELECT
                sq.*,
                COUNT(sr.id) AS total_found,
                COALESCE(SUM(CASE WHEN sr.downloaded = 1 THEN 1 ELSE 0 END), 0) AS downloaded_count,
                COUNT(sr.id) - COALESCE(SUM(CASE WHEN sr.downloaded = 1 THEN 1 ELSE 0 END), 0) AS remaining_count
            FROM search_queries sq
            LEFT JOIN search_results sr ON sr.query_id = sq.id
            GROUP BY sq.id
            ORDER BY sq.query COLLATE NOCASE, sq.id
        """).fetchall()]


def get_active_query(query_id: int):
    with db() as conn:
        row = conn.execute(
            "SELECT * FROM search_queries WHERE id=? AND active=1",
            (query_id,),
        ).fetchone()
        return dict(row) if row else None


def get_next_query_for_collection(query_id: int | None = None):
    """Возвращает активный запрос для очередного обхода.

    Если конкретный запрос не выбран, первыми идут ещё ни разу не обходившиеся,
    затем самые давно обходившиеся.
    """
    with db() as conn:
        if query_id:
            row = conn.execute(
                "SELECT * FROM search_queries WHERE id=? AND active=1",
                (query_id,),
            ).fetchone()
        else:
            row = conn.execute("""
                SELECT *
                FROM search_queries
                WHERE active=1
                ORDER BY
                    CASE WHEN last_collected_at IS NULL THEN 0 ELSE 1 END,
                    datetime(last_collected_at) ASC,
                    id ASC
                LIMIT 1
            """).fetchone()
        return dict(row) if row else None

def upsert_query(query, active, q_id=None):
    with db() as conn:
        if q_id:
            conn.execute("UPDATE search_queries SET query=?,active=? WHERE id=?", (query, active, q_id))
        else:
            conn.execute("INSERT OR IGNORE INTO search_queries(query,active) VALUES(?,?)", (query, active))

def delete_query(q_id):
    with db() as conn:
        conn.execute("DELETE FROM search_queries WHERE id=?", (q_id,))

def mark_query_collected(query_id: int):
    with db() as conn:
        conn.execute(
            "UPDATE search_queries SET last_collected_at=datetime('now') WHERE id=?",
            (query_id,),
        )


def save_search_results(query_id: int, results: list[dict], refresh_existing: bool = False):
    """results: [{article_id, title, url}, ...]"""
    with db() as conn:
        exists = conn.execute("SELECT 1 FROM search_queries WHERE id=?", (query_id,)).fetchone()
        if not exists:
            print(f"[db] Поисковый запрос id={query_id} не найден, результаты не сохранены.")
            return {"total": 0, "new": 0, "refreshed": 0}

        existing_ids = {
            row["article_id"]
            for row in conn.execute(
                "SELECT article_id FROM search_results WHERE query_id=?",
                (query_id,),
            ).fetchall()
        }
        new_count = 0
        refreshed_count = 0

        for r in results:
            article_id = r["article_id"]
            if article_id not in existing_ids:
                new_count += 1
            elif refresh_existing:
                refreshed_count += 1
            conn.execute(
                """
                INSERT INTO search_results(query_id,article_id,title,url)
                VALUES(?,?,?,?)
                ON CONFLICT(query_id, article_id) DO UPDATE SET
                    title=excluded.title,
                    url=excluded.url,
                    downloaded=CASE
                        WHEN ? = 1 THEN 0
                        ELSE search_results.downloaded
                    END
                """,
                (query_id, article_id, r["title"], r["url"], 1 if refresh_existing else 0)
            )
        conn.execute(
            "UPDATE search_queries SET last_collected_at=datetime('now') WHERE id=?",
            (query_id,),
        )
        return {"total": len(results), "new": new_count, "refreshed": refreshed_count}

def get_search_results(query_id: int):
    with db() as conn:
        return [dict(r) for r in conn.execute(
            "SELECT * FROM search_results WHERE query_id=? ORDER BY id", (query_id,)
        ).fetchall()]


def get_search_result(article_id: str):
    with db() as conn:
        row = conn.execute("""
            SELECT sr.*, sq.query, sq.active AS query_active
            FROM search_results sr
            JOIN search_queries sq ON sq.id = sr.query_id
            WHERE sr.article_id=?
            ORDER BY sq.active DESC, sr.id ASC
            LIMIT 1
        """, (article_id,)).fetchone()
        return dict(row) if row else None


def get_search_results_for_selector(active_only: bool = True):
    with db() as conn:
        active_sql = "WHERE sq.active=1" if active_only else ""
        return [dict(r) for r in conn.execute(f"""
            SELECT sr.article_id, sr.title, sr.downloaded, sq.query, sq.id AS query_id
            FROM search_results sr
            JOIN search_queries sq ON sq.id = sr.query_id
            {active_sql}
            ORDER BY sq.query COLLATE NOCASE, sr.title COLLATE NOCASE
        """).fetchall()]

def save_article(article_id, title, url, search_query, account_login, article_html, graphics_meta):
    with db() as conn:
        graphics_json = json.dumps(graphics_meta, ensure_ascii=False) if graphics_meta else None
        old = conn.execute(
            "SELECT article_html, graphics_meta FROM articles WHERE article_id=?",
            (article_id,),
        ).fetchone()
        changed = 1
        if old:
            changed = 0 if (old["article_html"] == article_html and old["graphics_meta"] == graphics_json) else 1

        # ВАЖНО: не используем INSERT OR REPLACE, чтобы не терять exported_to_wp/wp_post_id/needs_manual_review
        conn.execute("""
            INSERT INTO articles
                (article_id, title, url, search_query, account_login, article_html, graphics_meta, content_changed)
            VALUES (?,?,?,?,?,?,?,?)
            ON CONFLICT(article_id) DO UPDATE SET
                title=excluded.title,
                url=excluded.url,
                search_query=excluded.search_query,
                account_login=excluded.account_login,
                article_html=excluded.article_html,
                graphics_meta=excluded.graphics_meta,
                content_changed=excluded.content_changed,
                downloaded_at=datetime('now')
        """, (
            article_id,
            title,
            url,
            search_query,
            account_login,
            article_html,
            graphics_json,
            changed,
        ))
        # Помечаем в search_results как скачанный
        conn.execute(
            "UPDATE search_results SET downloaded=1 WHERE article_id=?", (article_id,)
        )
        return bool(changed)


def mark_article_exported(article_id: str, wp_post_id: str | None):
    """Помечает статью как выгруженную в WordPress и сохраняет ID поста."""
    with db() as conn:
        conn.execute(
            "UPDATE articles SET exported_to_wp=1, wp_post_id=? WHERE article_id=?",
            (wp_post_id, article_id),
        )


def set_article_wp_status(article_id: str, *, exported_to_wp: bool, wp_post_id: str | None = None):
    """Обновляет статус выгрузки статьи в WP (используется при REST-проверке существования)."""
    with db() as conn:
        conn.execute(
            "UPDATE articles SET exported_to_wp=?, wp_post_id=? WHERE article_id=?",
            (1 if exported_to_wp else 0, wp_post_id, article_id),
        )


def mark_article_needs_review(article_id: str):
    """Помечает статью как требующую ручной проверки."""
    with db() as conn:
        conn.execute(
            "UPDATE articles SET needs_manual_review=1 WHERE article_id=?",
            (article_id,),
        )


def clear_article_manual_review(article_id: str):
    """Снимает флаг ручной проверки."""
    with db() as conn:
        conn.execute(
            "UPDATE articles SET needs_manual_review=0 WHERE article_id=?",
            (article_id,),
        )

def save_article_graphic(article_id, page_num, total_pages, indicator, html):
    with db() as conn:
        conn.execute("""
            INSERT OR REPLACE INTO article_graphics
                (article_id, page_num, total_pages, indicator, html)
            VALUES (?,?,?,?,?)
        """, (article_id, page_num, total_pages, indicator, html))

def get_article(article_id):
    with db() as conn:
        row = conn.execute("SELECT * FROM articles WHERE article_id=?", (article_id,)).fetchone()
        return dict(row) if row else None

def get_article_graphics(article_id):
    with db() as conn:
        return [dict(r) for r in conn.execute(
            "SELECT * FROM article_graphics WHERE article_id=? ORDER BY page_num", (article_id,)
        ).fetchall()]

def get_all_articles():
    with db() as conn:
        return [dict(r) for r in conn.execute(
            "SELECT article_id,title,url,search_query,account_login,downloaded_at,exported_to_wp,wp_post_id,needs_manual_review "
            "FROM articles ORDER BY downloaded_at DESC"
        ).fetchall()]


def get_articles_not_exported_to_wp():
    """Статьи, которые ещё не выгружены в WordPress."""
    with db() as conn:
        return [dict(r) for r in conn.execute(
            "SELECT article_id FROM articles WHERE exported_to_wp=0 OR exported_to_wp IS NULL"
        ).fetchall()]

def log_parse(account_id, article_id, status, message=""):
    with db() as conn:
        conn.execute(
            "INSERT INTO parse_log(account_id,article_id,status,message) VALUES(?,?,?,?)",
            (account_id, article_id, status, message)
        )

def get_daily_count(account_id: int) -> int:
    from datetime import date
    today = date.today().isoformat()
    with db() as conn:
        row = conn.execute(
            "SELECT count FROM daily_counts WHERE account_id=? AND date=?", (account_id, today)
        ).fetchone()
        return row["count"] if row else 0

def increment_daily_count(account_id: int):
    from datetime import date
    today = date.today().isoformat()
    with db() as conn:
        conn.execute("""
            INSERT INTO daily_counts(account_id,date,count) VALUES(?,?,1)
            ON CONFLICT(account_id,date) DO UPDATE SET count=count+1
        """, (account_id, today))

def get_articles_not_downloaded():
    """Возвращает все статьи из search_results которые ещё не скачаны."""
    with db() as conn:
        return [dict(r) for r in conn.execute("""
            SELECT sr.*, sq.query
            FROM search_results sr
            JOIN search_queries sq ON sq.id = sr.query_id
            WHERE sr.downloaded = 0 AND sq.active = 1
            ORDER BY
                CASE WHEN sq.last_collected_at IS NULL THEN 0 ELSE 1 END,
                datetime(sq.last_collected_at) ASC,
                sq.id ASC,
                sr.id ASC
        """).fetchall()]


def get_articles_not_downloaded_for_query(query_id: int):
    """Возвращает очередь только для выбранного активного запроса."""
    with db() as conn:
        return [dict(r) for r in conn.execute("""
            SELECT sr.*, sq.query
            FROM search_results sr
            JOIN search_queries sq ON sq.id = sr.query_id
            WHERE sr.downloaded = 0 AND sq.active = 1 AND sq.id = ?
            ORDER BY sr.id ASC
        """, (query_id,)).fetchall()]
