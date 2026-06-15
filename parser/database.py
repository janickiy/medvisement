import sqlite3
import json
from datetime import datetime
from typing import List, Dict, Any, Optional
from dataclasses import dataclass


@dataclass
class ClinicalRecommendation:
    id: int
    code_version: str
    name: str
    mkb: str
    version: int
    publish_date: str
    age_category: str
    status: int
    raw_json: dict
    formatted_text: str = ""
    formatted_html: str = ""  # НОВОЕ ПОЛЕ!
    last_updated: Optional[str] = None
    is_deleted: bool = False
    file_hash: str = ""

    def to_dict(self):
        return {
            'id': self.id,
            'code_version': self.code_version,
            'name': self.name,
            'mkb': self.mkb,
            'version': self.version,
            'publish_date': self.publish_date,
            'age_category': self.age_category,
            'status': self.status,
            'formatted_text': self.formatted_text,
            'formatted_html': self.formatted_html,  # НОВОЕ!
            'last_updated': self.last_updated or datetime.now().isoformat(),
            'is_deleted': self.is_deleted,
            'file_hash': self.file_hash
        }


class Database:
    def __init__(self, db_path: str):
        self.db_path = db_path
        self.init_db()

    def init_db(self):
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()

            # Таблица клинических рекомендаций
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS clinical_recommendations (
                    id INTEGER PRIMARY KEY,
                    code_version TEXT UNIQUE,
                    name TEXT,
                    mkb TEXT,
                    version INTEGER,
                    publish_date TEXT,
                    age_category TEXT,
                    status INTEGER,
                    raw_json TEXT,
                    formatted_text TEXT,
                    formatted_html TEXT,
                    last_updated TEXT,
                    is_deleted BOOLEAN DEFAULT 0,
                    file_hash TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ''')

            # Индексы для быстрого поиска
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_code_version ON clinical_recommendations(code_version)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_name ON clinical_recommendations(name)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_mkb ON clinical_recommendations(mkb)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_status ON clinical_recommendations(status)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_deleted ON clinical_recommendations(is_deleted)')

            conn.commit()

    def save_recommendation(self, cr: ClinicalRecommendation):
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()

            # Проверяем, существует ли уже запись
            cursor.execute(
                "SELECT id, file_hash FROM clinical_recommendations WHERE code_version = ?",
                (cr.code_version,)
            )
            existing = cursor.fetchone()

            if existing:
                # Если хэш изменился, обновляем
                if existing[1] != cr.file_hash:
                    cursor.execute('''
                        UPDATE clinical_recommendations 
                        SET name = ?, mkb = ?, version = ?, publish_date = ?, age_category = ?,
                            status = ?, raw_json = ?, formatted_text = ?, formatted_html = ?, last_updated = ?,
                            is_deleted = 0, file_hash = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE code_version = ?
                    ''', (
                        cr.name, cr.mkb, cr.version, cr.publish_date, cr.age_category,
                        cr.status, json.dumps(cr.raw_json, ensure_ascii=False),
                        cr.formatted_text, cr.formatted_html,
                        datetime.now().isoformat(), cr.file_hash, cr.code_version
                    ))
                    return True  # Данные обновлены
                return False  # Данные не изменились
            else:
                # Новая запись
                cursor.execute('''
                    INSERT INTO clinical_recommendations 
                    (id, code_version, name, mkb, version, publish_date, age_category, 
                     status, raw_json, formatted_text, formatted_html, last_updated, file_hash)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ''', (
                    cr.id, cr.code_version, cr.name, cr.mkb, cr.version,
                    cr.publish_date, cr.age_category, cr.status,
                    json.dumps(cr.raw_json, ensure_ascii=False),
                    cr.formatted_text, cr.formatted_html,
                    datetime.now().isoformat(), cr.file_hash
                ))
                return True  # Новая запись добавлена

    def mark_as_deleted(self, code_version: str):
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()
            cursor.execute(
                "UPDATE clinical_recommendations SET is_deleted = 1 WHERE code_version = ?",
                (code_version,)
            )
            conn.commit()

    def get_all_code_versions(self) -> List[str]:
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()
            cursor.execute("SELECT code_version FROM clinical_recommendations")
            return [row[0] for row in cursor.fetchall()]

    def get_recommendation(self, code_version: str) -> Optional[ClinicalRecommendation]:
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()
            cursor.execute(
                "SELECT * FROM clinical_recommendations WHERE code_version = ?",
                (code_version,)
            )
            row = cursor.fetchone()

            if row:
                return ClinicalRecommendation(
                    id=row[0],
                    code_version=row[1],
                    name=row[2],
                    mkb=row[3],
                    version=row[4],
                    publish_date=row[5],
                    age_category=row[6],
                    status=row[7],
                    raw_json=json.loads(row[8]),
                    formatted_text=row[9],
                    formatted_html=row[10],
                    last_updated=row[11],
                    is_deleted=bool(row[12]),
                    file_hash=row[13]
                )
            return None

    def get_statistics(self) -> Dict[str, Any]:
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.cursor()

            cursor.execute("SELECT COUNT(*) FROM clinical_recommendations")
            total = cursor.fetchone()[0]

            cursor.execute("SELECT COUNT(*) FROM clinical_recommendations WHERE is_deleted = 1")
            deleted = cursor.fetchone()[0]

            cursor.execute("SELECT COUNT(*) FROM clinical_recommendations WHERE is_deleted = 0")
            active = cursor.fetchone()[0]

            cursor.execute("SELECT COUNT(DISTINCT mkb) FROM clinical_recommendations WHERE is_deleted = 0")
            unique_mkb = cursor.fetchone()[0]

            return {
                'total': total,
                'active': active,
                'deleted': deleted,
                'unique_mkb': unique_mkb
            }
