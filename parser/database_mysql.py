"""
ОПЦИОНАЛЬНЫЙ ФАЙЛ для миграции на MySQL
В задании указана MySQL, но текущий код использует SQLite.
Этот файл показывает, как перейти на MySQL.

Установка: pip install mysql-connector-python
"""

import mysql.connector
from mysql.connector import Error
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


class MySQLDatabase:
    def __init__(self, host: str = 'localhost', port: int = 3306, user: str = 'root',
                 password: str = '', database: str = 'clinical_recommendations'):
        self.host = host
        self.port = port
        self.user = user
        self.password = password
        self.database = database
        self.connection = None
        self.init_db()

    def _get_connection(self):
        """Получить соединение с БД"""
        try:
            if self.connection is None or not self.connection.is_connected():
                self.connection = mysql.connector.connect(
                    host=self.host,
                    port=self.port,
                    user=self.user,
                    password=self.password,
                    database=self.database,
                    charset='utf8mb4',
                    collation='utf8mb4_unicode_ci'
                )
            return self.connection
        except Error as e:
            print(f"Ошибка подключения к MySQL: {e}")
            return None

    def init_db(self):
        """Инициализация БД и создание таблиц"""
        try:
            # Сначала подключаемся без указания БД
            connection = mysql.connector.connect(
                host=self.host,
                port=self.port,
                user=self.user,
                password=self.password
            )
            cursor = connection.cursor()

            # Создаем БД если не существует
            cursor.execute(f"CREATE DATABASE IF NOT EXISTS {self.database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
            cursor.close()
            connection.close()

            # Подключаемся к созданной БД
            conn = self._get_connection()
            if conn is None:
                raise Exception("Не удалось подключиться к БД")

            cursor = conn.cursor()

            # Создаем таблицу клинических рекомендаций
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS clinical_recommendations (
                    id INT PRIMARY KEY,
                    code_version VARCHAR(50) UNIQUE,
                    name TEXT,
                    mkb VARCHAR(50),
                    version INT,
                    publish_date VARCHAR(50),
                    age_category VARCHAR(100),
                    status INT,
                    raw_json LONGTEXT,
                    formatted_text LONGTEXT,
                    formatted_html LONGTEXT,
                    last_updated DATETIME,
                    is_deleted BOOLEAN DEFAULT 0,
                    file_hash VARCHAR(32),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_code_version (code_version),
                    INDEX idx_mkb (mkb),
                    INDEX idx_status (status),
                    INDEX idx_deleted (is_deleted),
                    FULLTEXT INDEX idx_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ''')

            conn.commit()
            cursor.close()
            print("База данных успешно инициализирована")

        except Error as e:
            print(f"Ошибка инициализации БД: {e}")

    def save_recommendation(self, cr: ClinicalRecommendation):
        """Сохранить или обновить рекомендацию"""
        conn = self._get_connection()
        if conn is None:
            return False

        cursor = conn.cursor()

        try:
            # Проверяем, существует ли уже запись
            cursor.execute(
                "SELECT id, file_hash FROM clinical_recommendations WHERE code_version = %s",
                (cr.code_version,)
            )
            existing = cursor.fetchone()

            if existing:
                # Если хэш изменился, обновляем
                if existing[1] != cr.file_hash:
                    cursor.execute('''
                        UPDATE clinical_recommendations 
                        SET name = %s, mkb = %s, version = %s, publish_date = %s, age_category = %s,
                            status = %s, raw_json = %s, formatted_text = %s, formatted_html = %s, last_updated = %s,
                            is_deleted = 0, file_hash = %s
                        WHERE code_version = %s
                    ''', (
                        cr.name, cr.mkb, cr.version, cr.publish_date, cr.age_category,
                        cr.status, json.dumps(cr.raw_json, ensure_ascii=False),
                        cr.formatted_text, cr.formatted_html, datetime.now(),
                        cr.file_hash, cr.code_version
                    ))
                    conn.commit()
                    return True
                return False
            else:
                # Новая запись
                cursor.execute('''
                    INSERT INTO clinical_recommendations 
                    (id, code_version, name, mkb, version, publish_date, age_category, 
                     status, raw_json, formatted_text, formatted_html, last_updated, file_hash)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ''', (
                    cr.id, cr.code_version, cr.name, cr.mkb, cr.version,
                    cr.publish_date, cr.age_category, cr.status,
                    json.dumps(cr.raw_json, ensure_ascii=False),
                    cr.formatted_text, cr.formatted_html, datetime.now(),
                    cr.file_hash
                ))
                conn.commit()
                return True

        except Error as e:
            print(f"Ошибка сохранения рекомендации: {e}")
            conn.rollback()
            return False
        finally:
            cursor.close()

    def mark_as_deleted(self, code_version: str):
        """Пометить рекомендацию как удаленную"""
        conn = self._get_connection()
        if conn is None:
            return

        cursor = conn.cursor()
        try:
            cursor.execute(
                "UPDATE clinical_recommendations SET is_deleted = 1 WHERE code_version = %s",
                (code_version,)
            )
            conn.commit()
        except Error as e:
            print(f"Ошибка пометки как удаленной: {e}")
            conn.rollback()
        finally:
            cursor.close()

    def get_all_code_versions(self) -> List[str]:
        """Получить все code_version из БД"""
        conn = self._get_connection()
        if conn is None:
            return []

        cursor = conn.cursor()
        try:
            cursor.execute("SELECT code_version FROM clinical_recommendations")
            return [row[0] for row in cursor.fetchall()]
        except Error as e:
            print(f"Ошибка получения code_versions: {e}")
            return []
        finally:
            cursor.close()

    def get_recommendation(self, code_version: str) -> Optional[ClinicalRecommendation]:
        """Получить рекомендацию по code_version"""
        conn = self._get_connection()
        if conn is None:
            return None

        cursor = conn.cursor()
        try:
            cursor.execute(
                "SELECT * FROM clinical_recommendations WHERE code_version = %s",
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
                    raw_json=json.loads(row[8]) if row[8] else {},
                    formatted_text=row[9],
                    formatted_html=row[10],  # НОВОЕ!
                    last_updated=str(row[11]) if row[11] else None,
                    is_deleted=bool(row[12]),
                    file_hash=row[13]
                )
            return None
        except Error as e:
            print(f"Ошибка получения рекомендации: {e}")
            return None
        finally:
            cursor.close()

    def get_statistics(self) -> Dict[str, Any]:
        """Получить статистику по БД"""
        conn = self._get_connection()
        if conn is None:
            return {}

        cursor = conn.cursor()
        try:
            stats = {}

            cursor.execute("SELECT COUNT(*) FROM clinical_recommendations")
            stats['total'] = cursor.fetchone()[0]

            cursor.execute("SELECT COUNT(*) FROM clinical_recommendations WHERE is_deleted = 1")
            stats['deleted'] = cursor.fetchone()[0]

            cursor.execute("SELECT COUNT(*) FROM clinical_recommendations WHERE is_deleted = 0")
            stats['active'] = cursor.fetchone()[0]

            cursor.execute("SELECT COUNT(DISTINCT mkb) FROM clinical_recommendations WHERE is_deleted = 0")
            stats['unique_mkb'] = cursor.fetchone()[0]

            return stats
        except Error as e:
            print(f"Ошибка получения статистики: {e}")
            return {}
        finally:
            cursor.close()

    def close(self):
        """Закрыть соединение с БД"""
        if self.connection and self.connection.is_connected():
            self.connection.close()
            print("Соединение с MySQL закрыто")


# Для использования MySQL вместо SQLite в main.py:
# 1. Установите: pip install mysql-connector-python
# 2. В config.py замените:
#    from database_mysql import MySQLDatabase as Database
# 3. Настройте параметры подключения в config.py:
#    DB_HOST = 'localhost'
#    DB_USER = 'root'
#    DB_PASSWORD = 'your_password'
#    DB_NAME = 'clinical_recommendations'
