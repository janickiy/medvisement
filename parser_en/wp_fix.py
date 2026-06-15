import os
import sys
import sqlite3
import mysql.connector
from mysql.connector import Error

# === Настройки ===
SQLITE_DB_PATH = "/home/dev2/parser/data/clinical_recommendations.db"
WP_MYSQL_CONFIG = {
    'host': 'localhost',
    'database': 'dev2_medvisement',
    'user': 'dev2_medvisement',
    'password': '1[-6Fas!@OUK[dpw'
}


def get_post_id_by_source_id(source_id):
    """Ищет ID записи в WordPress по meta_key='source_id' и meta_value=source_id."""
    try:
        conn = mysql.connector.connect(**WP_MYSQL_CONFIG)
        cursor = conn.cursor()
        cursor.execute("""
            SELECT p.ID 
            FROM wp_posts p
            INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
            WHERE pm.meta_key = 'source_id' AND pm.meta_value = %s
            LIMIT 1
        """, (source_id,))
        result = cursor.fetchone()
        return result[0] if result else None
    except Error as e:
        print(f"❌ Ошибка поиска записи в WP: {e}")
        return None
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()


def update_post_content(post_id, content):
    """Обновляет post_content напрямую в wp_posts."""
    try:
        conn = mysql.connector.connect(**WP_MYSQL_CONFIG)
        cursor = conn.cursor()
        cursor.execute("UPDATE wp_posts SET post_content = %s WHERE ID = %s", (content, post_id))
        conn.commit()
        return True
    except Error as e:
        print(f"❌ Ошибка обновления контента для ID={post_id}: {e}")
        return False
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()


def main():
    print("🔧 Запуск исправления post_content для всех записей...")

    # Подключаемся к SQLite
    if not os.path.exists(SQLITE_DB_PATH):
        print(f"❌ Файл БД не найден: {SQLITE_DB_PATH}")
        return

    sqlite_conn = sqlite3.connect(SQLITE_DB_PATH)
    sqlite_cursor = sqlite_conn.cursor()

    try:
        sqlite_cursor.execute('''
            SELECT id, formatted_html 
            FROM clinical_recommendations 
            WHERE is_deleted = 0 AND formatted_html IS NOT NULL
        ''')
        records = sqlite_cursor.fetchall()
        print(f"Найдено {len(records)} записей с контентом.")

        updated = 0
        for i, (rec_id, html_content) in enumerate(records, 1):
            source_id = f"pi_{rec_id}"
            wp_post_id = get_post_id_by_source_id(source_id)

            if wp_post_id:
                safe_html = html_content if html_content.strip() else "<p>Контент отсутствует</p>"
                if update_post_content(wp_post_id, safe_html):
                    updated += 1
                    print(f"[{i}/{len(records)}] ✅ Обновлён ID={wp_post_id} (source_id={source_id})")
                else:
                    print(f"[{i}/{len(records)}] ❌ Не удалось обновить ID={wp_post_id}")
            else:
                print(f"[{i}/{len(records)}] ⚠️ Запись не найдена в WP: {source_id}")

        print(f"\n✅ Готово! Обновлено {updated} записей из {len(records)}.")

    except Exception as e:
        print(f"💥 Критическая ошибка: {e}")
    finally:
        sqlite_conn.close()


if __name__ == "__main__":
    main()
