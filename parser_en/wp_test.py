import os
import sys
from wp_utils import upsert_disease_post

# Добавляем текущий каталог в путь (если wp_utils рядом)
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

if __name__ == "__main__":
    title = "Тест: прямая запись в БД (EN)"
    content = """
    <details>
        <summary>Прямая запись</summary>
        <p>Этот контент записан напрямую в MySQL, минуя WP-фильтры.</p>
        <ul><li>Работает?</li></ul>
    </details>
    """

    print("🚀 Запуск теста с прямой записью в БД...")

    post_id = upsert_disease_post(
        title=title,
        content=content,
        external_id="en_test_direct_db",
        status="draft",
        article_type_slug="eng-articles",
        age_slugs=["adult"],
        meta_extra={"method": "direct_db_update"},
        is_english=True,
    )

    if post_id:
        print(f"✅ Готово. Проверьте запись ID={post_id} в админке.")
    else:
        print("❌ Не удалось создать запись.")
