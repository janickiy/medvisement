"""
Скрипт для тестирования парсинга одной клинической рекомендации
Полезен для отладки форматирования без полного запуска парсера

Использование:
    python test_single.py 232_2
    python test_single.py 925_1
"""

import sys
import os
from config import config, logger
from api_client import APIClient
from parser import ContentParser
from file_manager import FileManager


def test_single_recommendation(code_version: str):
    """Тестирование парсинга одной рекомендации"""

    logger.info(f"=" * 60)
    logger.info(f"Тестирование рекомендации: {code_version}")
    logger.info(f"=" * 60)

    api_client = APIClient()
    parser = ContentParser()
    file_manager = FileManager()

    # 1. Получаем детальную информацию
    logger.info("Шаг 1: Загрузка данных из API...")
    detail_data = api_client.get_recommendation_detail(code_version)

    if not detail_data:
        logger.error(f"Не удалось загрузить данные для {code_version}")
        return False

    logger.info(f"✓ Данные загружены")

    # 2. Сохраняем JSON
    logger.info("Шаг 2: Сохранение JSON...")
    json_hash = file_manager.save_json(code_version, detail_data)
    logger.info(f"✓ JSON сохранен, hash: {json_hash}")

    # 3. Создаем директорию для файлов
    logger.info("Шаг 3: Создание директории для файлов...")
    files_dir = file_manager.create_recommendation_directory(code_version)
    logger.info(f"✓ Директория создана: {files_dir}")

    # 4. Скачиваем PDF
    logger.info("Шаг 4: Скачивание PDF...")
    pdf_filename = os.path.join(files_dir, f"{code_version}.pdf")
    pdf_downloaded = api_client.download_pdf(code_version, pdf_filename)
    if pdf_downloaded:
        logger.info(f"✓ PDF скачан: {pdf_filename}")
    else:
        logger.warning(f"⚠ PDF не скачан (возможно, недоступен)")

    # 5. Извлекаем HTML контент
    logger.info("Шаг 5: Извлечение HTML контента...")
    sections = detail_data.get('obj', {}).get('sections', [])

    if not sections:
        logger.error("Контент не найден в данных")
        return False

    html_content = ""
    for section in sections:
        if section.get('content'):
            html_content += section.get('content', '')

    if not html_content:
        logger.error("HTML контент пуст")
        return False

    logger.info(f"✓ HTML контент извлечен, длина: {len(html_content)} символов")

    # 6. Сохраняем оригинальный HTML
    logger.info("Шаг 6: Сохранение оригинального HTML...")
    file_manager.save_original_html(code_version, html_content)
    logger.info(f"✓ Оригинальный HTML сохранен")

    # 7. Извлекаем изображения и таблицы
    logger.info("Шаг 7: Извлечение изображений и таблиц...")
    html_content, image_files, table_files = parser.extract_images_and_tables(
        html_content,
        files_dir
    )
    logger.info(f"✓ Извлечено изображений: {len(image_files)}")
    logger.info(f"✓ Сохранено таблиц: {len(table_files)}")

    if image_files:
        for img in image_files[:5]:  # Показываем первые 5
            logger.info(f"  - {img}")
        if len(image_files) > 5:
            logger.info(f"  ... и еще {len(image_files) - 5}")

    if table_files:
        for tbl in table_files:
            logger.info(f"  - {tbl}")

    # 8. Парсим и форматируем
    logger.info("Шаг 8: Парсинг и форматирование...")

    # НОВОЕ: Парсим напрямую из JSON структуры sections
    sections_data = detail_data.get('obj', {}).get('sections', [])
    logger.info(f"Найдено разделов в JSON: {len(sections_data)}")

    # Показываем ID разделов для отладки
    logger.info("ID разделов:")
    for section in sections_data[:10]:  # Первые 10
        section_id = section.get('id', 'unknown')
        title = section.get('title', 'без заголовка')
        logger.info(f"  - {section_id}: {title}")
    if len(sections_data) > 10:
        logger.info(f"  ... и еще {len(sections_data) - 10} разделов")

    formatted_text, formatted_html = parser.parse_from_json_sections(sections_data)
    logger.info(f"✓ Текст отформатирован, длина: {len(formatted_text)} символов")
    logger.info(f"✓ HTML отформатирован, длина: {len(formatted_html)} символов")

    # 9. Сохраняем отформатированные версии
    logger.info("Шаг 9: Сохранение отформатированных версий...")
    file_manager.save_formatted_text(code_version, formatted_text)
    file_manager.save_formatted_html(code_version, formatted_html)
    logger.info(f"✓ Отформатированные версии сохранены")

    # 10. Показываем структуру разделов
    logger.info("\n" + "=" * 60)
    logger.info("ПРОВЕРКА ИЕРАРХИИ:")
    logger.info("=" * 60)

    # Парсим получившийся HTML для проверки
    from bs4 import BeautifulSoup
    html_soup = BeautifulSoup(formatted_html, 'html.parser')

    # Функция для вывода дерева details
    def print_details_tree(element, level=0):
        if element.name == 'details':
            summary = element.find('summary', recursive=False)
            if summary:
                indent = "  " * level
                logger.info(f"{indent}▼ {summary.get_text().strip()}")

                # Ищем вложенные details
                for child_details in element.find_all('details', recursive=False):
                    print_details_tree(child_details, level + 1)

    # Выводим дерево
    for details in html_soup.find_all('details', recursive=False):
        print_details_tree(details)

    # Проверяем потенциальные проблемы
    logger.info("\n" + "=" * 60)
    logger.info("ПРОВЕРКА ПРОБЛЕМ:")
    logger.info("=" * 60)

    # 1. Проверка дубликатов заголовков
    all_summaries = [s.get_text().strip() for s in html_soup.find_all('summary')]
    duplicates = [s for s in set(all_summaries) if all_summaries.count(s) > 1]
    if duplicates:
        logger.warning(f"⚠ Найдены дубликаты заголовков: {duplicates}")
    else:
        logger.info("✓ Дубликатов заголовков нет")

    # 2. Проверка пустых details
    for details in html_soup.find_all('details'):
        content = details.get_text().replace(details.find('summary').get_text(), '').strip()
        if not content or content == '':
            summary_text = details.find('summary').get_text().strip()
            logger.warning(f"⚠ Пустой спойлер: {summary_text}")

    # 3. Проверка А1 и А2
    a1_a2_found = False
    for summary in html_soup.find_all('summary'):
        text = summary.get_text().lower()
        if 'приложение а1' in text or 'приложение а2' in text:
            logger.warning(f"⚠ Найдено исключенное приложение: {summary.get_text()}")
            a1_a2_found = True

    if not a1_a2_found:
        logger.info("✓ Приложения А1 и А2 отсутствуют")

    # 4. Проверка вложенности 1.1 в 1
    doc1_found = False
    doc1_has_children = False

    for details in html_soup.find_all('details', recursive=False):
        summary = details.find('summary', recursive=False)
        if summary and '1. Краткая информация' in summary.get_text():
            doc1_found = True
            # Проверяем есть ли вложенные details
            child_details = details.find_all('details', recursive=False)
            if child_details:
                doc1_has_children = True
                child_titles = [cd.find('summary').get_text().strip() for cd in child_details if cd.find('summary')]
                logger.info(f"✓ Раздел '1. Краткая информация' имеет {len(child_details)} подразделов:")
                for title in child_titles:
                    logger.info(f"  - {title}")
            else:
                logger.warning("⚠ Раздел '1. Краткая информация' НЕ имеет подразделов!")
            break

    if not doc1_found:
        logger.warning("⚠ Раздел '1. Краткая информация' не найден")

    # 11. Показываем начало отформатированного текста
    logger.info("\n" + "=" * 60)
    logger.info("НАЧАЛО ОТФОРМАТИРОВАННОГО ТЕКСТА (первые 1000 символов):")
    logger.info("=" * 60)
    print(formatted_text[:1000])
    if len(formatted_text) > 1000:
        print(f"\n... (еще {len(formatted_text) - 1000} символов)")

    # 12. Итоговая информация
    logger.info("\n" + "=" * 60)
    logger.info("РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ:")
    logger.info("=" * 60)
    logger.info(f"✓ Рекомендация: {code_version}")
    logger.info(f"✓ Файлы сохранены в:")
    logger.info(f"  - JSON: data/json/{code_version}.json")
    logger.info(f"  - Текст: data/json/{code_version}_formatted.txt")
    logger.info(f"  - HTML форм.: data/json/{code_version}_formatted.html")
    logger.info(f"  - HTML ориг.: data/json/{code_version}_original.html")
    logger.info(f"  - Файлы: data/files/{code_version}/")
    logger.info(f"    - PDF: {code_version}.pdf {'✓' if pdf_downloaded else '✗'}")
    logger.info(f"    - Изображений: {len(image_files)}")
    logger.info(f"    - Таблиц: {len(table_files)}")
    logger.info("=" * 60)

    return True


def main():
    if len(sys.argv) < 2:
        print("Использование: python test_single.py <code_version>")
        print("Примеры:")
        print("  python test_single.py 232_2")
        print("  python test_single.py 925_1")
        sys.exit(1)

    code_version = sys.argv[1]

    try:
        success = test_single_recommendation(code_version)
        if success:
            print("\n✓ Тестирование завершено успешно!")
            print(f"\nПроверьте файлы в:")
            print(f"  - data/json/{code_version}_formatted.txt")
            print(f"  - data/json/{code_version}_formatted.html")
        else:
            print("\n✗ Тестирование завершилось с ошибками")
            sys.exit(1)
    except Exception as e:
        logger.error(f"Критическая ошибка: {e}", exc_info=True)
        sys.exit(1)


if __name__ == "__main__":
    main()
