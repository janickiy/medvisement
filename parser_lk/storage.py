import json
import os
import sqlite3
import time

DEFAULT_DB_PATH = os.getenv("LOCAL_DB_PATH", os.path.join("data", "parser_lk.db"))


def resolve_db_path(db_path=None):
    db_path = db_path or DEFAULT_DB_PATH
    if not os.path.isabs(db_path):
        db_path = os.path.abspath(db_path)
    return db_path


def connect(db_path=None):
    db_path = resolve_db_path(db_path)
    parent = os.path.dirname(db_path)
    if parent:
        os.makedirs(parent, exist_ok=True)
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    return conn


def init_db(db_path=None):
    with connect(db_path) as conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS results (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_folder TEXT NOT NULL,
                card_type INTEGER NOT NULL,
                inn TEXT NOT NULL DEFAULT '',
                trade_name TEXT NOT NULL DEFAULT '',
                form TEXT NOT NULL DEFAULT '',
                reg_number TEXT NOT NULL DEFAULT '',
                reg_date TEXT NOT NULL DEFAULT '',
                owner TEXT NOT NULL DEFAULT '',
                country TEXT NOT NULL DEFAULT '',
                pdf_file TEXT NOT NULL DEFAULT '',
                documents_json TEXT NOT NULL DEFAULT '[]',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(id_folder, card_type)
            )
            """
        )
        conn.execute("CREATE INDEX IF NOT EXISTS idx_results_created_at ON results(created_at)")


def _now():
    return time.strftime("%Y-%m-%d %H:%M:%S")


def _document_payload(documents):
    payload = []
    for doc in documents or []:
        if isinstance(doc, str):
            file_name = os.path.basename(doc)
            file_path = doc
        else:
            file_name = doc.get("file_name") or os.path.basename(doc.get("file_path", ""))
            file_path = doc.get("file_path", "")
        if file_name:
            payload.append({"file_name": file_name, "file_path": file_path})
    return payload


def save_result(db_path, id_folder, card_type, medicine_data, documents):
    init_db(db_path)
    docs = _document_payload(documents)
    pdf_file = docs[0]["file_name"] if docs else ""
    now = _now()
    data = medicine_data or {}
    with connect(db_path) as conn:
        conn.execute(
            """
            INSERT INTO results (
                id_folder, card_type, inn, trade_name, form, reg_number,
                reg_date, owner, country, pdf_file, documents_json, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(id_folder, card_type) DO UPDATE SET
                inn=excluded.inn,
                trade_name=excluded.trade_name,
                form=excluded.form,
                reg_number=excluded.reg_number,
                reg_date=excluded.reg_date,
                owner=excluded.owner,
                country=excluded.country,
                pdf_file=excluded.pdf_file,
                documents_json=excluded.documents_json,
                updated_at=excluded.updated_at
            """,
            (
                str(id_folder), int(card_type), data.get("inn", ""), data.get("trade_name", ""),
                data.get("form", ""), data.get("reg_number", ""), data.get("reg_date", ""),
                data.get("owner", ""), data.get("country", ""), pdf_file,
                json.dumps(docs, ensure_ascii=False), now, now,
            ),
        )


def _row_to_dict(row):
    item = dict(row)
    try:
        item["documents"] = json.loads(item.get("documents_json") or "[]")
    except json.JSONDecodeError:
        item["documents"] = []
    return item


def list_results(db_path=None):
    init_db(db_path)
    with connect(db_path) as conn:
        rows = conn.execute("SELECT * FROM results ORDER BY datetime(created_at) DESC, id DESC").fetchall()
    return [_row_to_dict(row) for row in rows]


def get_result(db_path, result_id):
    init_db(db_path)
    with connect(db_path) as conn:
        row = conn.execute("SELECT * FROM results WHERE id = ?", (int(result_id),)).fetchone()
    return _row_to_dict(row) if row else None


def delete_result(db_path, result_id):
    row = get_result(db_path, result_id)
    if not row:
        return None
    with connect(db_path) as conn:
        conn.execute("DELETE FROM results WHERE id = ?", (int(result_id),))
    return row


def clear_results(db_path=None):
    rows = list_results(db_path)
    with connect(db_path) as conn:
        conn.execute("DELETE FROM results")
    return rows
