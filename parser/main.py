from datetime import datetime
from typing import Dict, Any
import os
import re
from config import config
from api_client import APIClient
from parser import ContentParser
from file_manager import FileManager
from config import logger
from wordpress_client import WordPressClient

# Импортируем нужную БД в зависимости от config.DB_TYPE
if config.DB_TYPE == 'mysql':
    from database_mysql import MySQLDatabase as Database, ClinicalRecommendation

    logger.info("Используется MySQL")
elif config.DB_TYPE == 'sqlite':
    from database import Database, ClinicalRecommendation

    logger.info("Используется SQLite")
else:
    raise ValueError(f"Неизвестный тип БД: {config.DB_TYPE}. Используйте 'sqlite' или 'mysql'")


class ClinicalRecommendationsParser:
    def __init__(self):
        # Инициализируем БД в зависимости от типа
        if config.DB_TYPE == 'mysql':
            self.db = Database(
                host=config.DB_HOST,
                port=config.DB_PORT,
                user=config.DB_USER,
                password=config.DB_PASSWORD,
                database=config.DB_NAME
            )
        else:  # sqlite
            self.db = Database(config.DB_PATH)

        self.api_client = APIClient()
        self.parser = ContentParser()
        self.file_manager = FileManager()
        self.wp_client = WordPressClient() if config.WP_SYNC_ENABLED else None

    def run(self):
        """Основной метод запуска парсера"""
        logger.info("Запуск парсера клинических рекомендаций")

        try:
            # 1. Получаем актуальный список рекомендаций
            logger.info("Получение списка клинических рекомендаций...")
            recommendations_list = self.api_client.get_all_recommendations()

            if not recommendations_list:
                logger.error("Не удалось получить список рекомендаций")
                return

            logger.info(f"Получено {len(recommendations_list)} рекомендаций")

            # 2. Получаем существующие коды версий из БД
            existing_versions = set(self.db.get_all_code_versions())
            current_versions = set()

            # 3. Обрабатываем каждую рекомендацию
            for i, rec_data in enumerate(recommendations_list, 1):
                try:
                    code_version = rec_data.get('CodeVersion')
                    logger.info(f"[{i}/{len(recommendations_list)}] Обработка: {code_version}")

                    if not code_version:
                        logger.warning(f"Пропущена запись без CodeVersion")
                        continue

                    current_versions.add(code_version)

                    # Проверяем, нужно ли обновлять
                    existing_rec = self.db.get_recommendation(code_version)
                    wp_status = self.wp_client.get_disease_status(code_version) if self.wp_client else {'exists': True}

                    if existing_rec and not self._should_update(existing_rec, rec_data):
                        if self.wp_client and (not wp_status.get('exists') or not wp_status.get('attachments_synced')):
                            logger.info(
                                f"Рекомендация {code_version} есть в кеше, но в WordPress "
                                f"{'отсутствует' if not wp_status.get('exists') else 'ещё без вложений'}. "
                                f"Синхронизируем из локальных данных"
                            )
                            self._sync_recommendation_to_wordpress(
                                existing_rec.raw_json,
                                existing_rec.formatted_text,
                                existing_rec.formatted_html,
                                existing_rec.file_hash,
                                fallback_code_version=existing_rec.code_version,
                                fallback_name=existing_rec.name,
                                fallback_mkb=existing_rec.mkb,
                                fallback_publish_date=existing_rec.publish_date,
                                fallback_version=existing_rec.version,
                            )
                        else:
                            logger.info(f"Рекомендация {code_version} уже актуальна, пропускаем")
                        continue

                    # Получаем детальную информацию
                    logger.info(f"Загрузка детальной информации для {code_version}...")
                    detail_data = self.api_client.get_recommendation_detail(code_version)

                    if not detail_data:
                        logger.error(f"Не удалось загрузить детальную информацию для {code_version}")
                        continue

                    # Сохраняем JSON
                    json_hash = self.file_manager.save_json(code_version, detail_data)

                    if not json_hash:
                        logger.error(f"Не удалось сохранить JSON для {code_version}")
                        continue

                    # Создаем директорию для файлов
                    files_dir = self.file_manager.create_recommendation_directory(code_version)

                    # Скачиваем PDF (Исправление проблемы №1)
                    pdf_filename = os.path.join(files_dir, f"{code_version}.pdf")
                    pdf_downloaded = self.api_client.download_pdf(code_version, pdf_filename)
                    if pdf_downloaded:
                        logger.info(f"PDF скачан: {pdf_filename}")
                    else:
                        logger.warning(f"Не удалось скачать PDF для {code_version}")

                    # Парсим и форматируем контент
                    formatted_text, formatted_html, original_html = self._parse_and_format_content(
                        detail_data,
                        code_version,
                        files_dir
                    )

                    # Сохраняем форматированные версии
                    self.file_manager.save_formatted_text(code_version, formatted_text)
                    self.file_manager.save_formatted_html(code_version, formatted_html)
                    self.file_manager.save_original_html(code_version, original_html)

                    # Создаем объект рекомендации
                    clinical_rec = ClinicalRecommendation(
                        id=rec_data.get('Id'),
                        code_version=code_version,
                        name=rec_data.get('Name', ''),
                        mkb=rec_data.get('Mkbs', [{}])[0].get('MkbCode', '') if rec_data.get('Mkbs') else '',
                        version=rec_data.get('Version', 1),
                        publish_date=rec_data.get('PublishDateStr', ''),
                        age_category=rec_data.get('AgeCategoryStr', ''),
                        status=rec_data.get('Status', 0),
                        raw_json=detail_data,
                        formatted_text=formatted_text,
                        formatted_html=formatted_html,  # НОВОЕ!
                        file_hash=json_hash
                    )

                    # Сохраняем в БД
                    if self.db.save_recommendation(clinical_rec):
                        logger.info(f"Рекомендация {code_version} сохранена/обновлена")
                    else:
                        logger.info(f"Рекомендация {code_version} не изменилась")

                    self._sync_recommendation_to_wordpress(
                        detail_data,
                        formatted_text,
                        formatted_html,
                        json_hash,
                        fallback_code_version=code_version,
                        fallback_name=clinical_rec.name,
                        fallback_mkb=clinical_rec.mkb,
                        fallback_publish_date=clinical_rec.publish_date,
                        fallback_version=clinical_rec.version,
                    )

                    # Пауза между запросами
                    import time
                    time.sleep(config.DELAY_BETWEEN_REQUESTS)

                except Exception as e:
                    logger.error(f"Ошибка при обработке рекомендации: {e}", exc_info=True)
                    continue

            # 4. Помечаем удаленные рекомендации
            deleted_versions = existing_versions - current_versions
            for code_version in deleted_versions:
                logger.info(f"Рекомендация {code_version} удалена с сайта, помечаем как удаленную")
                self.db.mark_as_deleted(code_version)

            # 5. Очищаем старые файлы
            self.file_manager.cleanup_old_files(list(current_versions))

            # 6. Выводим статистику
            stats = self.db.get_statistics()
            logger.info("\n" + "=" * 50)
            logger.info("СТАТИСТИКА:")
            logger.info(f"Всего в БД: {stats['total']}")
            logger.info(f"Активных: {stats['active']}")
            logger.info(f"Удаленных: {stats['deleted']}")
            logger.info(f"Уникальных МКБ: {stats['unique_mkb']}")
            logger.info("=" * 50)

            logger.info("Парсинг завершен успешно!")

        except Exception as e:
            logger.error(f"Критическая ошибка в основном цикле: {e}", exc_info=True)

    def _should_update(self, existing_rec: ClinicalRecommendation, new_data: Dict[str, Any]) -> bool:
        """Проверка, нужно ли обновлять рекомендацию"""
        # Проверяем дату публикации
        try:
            existing_date = datetime.fromisoformat(existing_rec.publish_date.replace('Z', '+00:00'))
            new_date_str = new_data.get('PublishDateStr')

            if new_date_str:
                new_date = datetime.fromisoformat(new_date_str.replace('Z', '+00:00'))
                if new_date > existing_date:
                    return True
        except Exception as e:
            logger.warning(f"Ошибка сравнения дат: {e}")

        # Проверяем версию
        if new_data.get('Version', 1) > existing_rec.version:
            return True

        return False

    def _parse_and_format_content(
            self,
            detail_data: Dict[str, Any],
            code_version: str,
            files_dir: str
    ) -> tuple[str, str, str]:
        """
        Парсинг и форматирование контента
        Returns: (formatted_text, formatted_html, original_html)
        """
        try:
            # Извлекаем sections из JSON (НОВЫЙ ПОДХОД - используем структуру JSON)
            sections = detail_data.get('obj', {}).get('sections', [])

            if not sections:
                return "Контент не найден", "<p>Контент не найден</p>", ""

            # Объединяем контент из всех разделов для сохранения оригинала
            html_content = ""
            for section in sections:
                if section.get('content'):
                    html_content += section.get('content', '')

            if not html_content:
                return "Контент не найден", "<p>Контент не найден</p>", ""

            # Сохраняем оригинальный HTML
            original_html = html_content

            # Извлекаем изображения и таблицы (Исправление проблемы №2)
            html_content, image_files, table_files = self.parser.extract_images_and_tables(
                html_content,
                files_dir
            )

            if image_files:
                logger.info(f"Извлечено изображений: {len(image_files)}")
            if table_files:
                logger.info(f"Сохранено таблиц: {len(table_files)}")

            # НОВОЕ: Используем парсинг напрямую из JSON структуры
            # Это позволяет точно определить типы разделов по их ID
            logger.info("Парсинг из JSON структуры sections...")
            formatted_text, formatted_html = self.parser.parse_from_json_sections(sections)

            return formatted_text, formatted_html, original_html

        except Exception as e:
            logger.error(f"Ошибка парсинга контента для {code_version}: {e}", exc_info=True)
            return f"Ошибка при обработке контента: {str(e)}", "", ""

    def _sync_recommendation_to_wordpress(
            self,
            detail_data: Dict[str, Any],
            formatted_text: str,
            formatted_html: str,
            json_hash: str,
            fallback_code_version: str = '',
            fallback_name: str = '',
            fallback_mkb: str = '',
            fallback_publish_date: str = '',
            fallback_version: int = 1,
    ) -> bool:
        """Синхронизация рекомендации в WordPress disease"""
        if not self.wp_client:
            return True

        payload = self._build_wordpress_payload(
            detail_data=detail_data,
            formatted_text=formatted_text,
            formatted_html=formatted_html,
            json_hash=json_hash,
            fallback_code_version=fallback_code_version,
            fallback_name=fallback_name,
            fallback_mkb=fallback_mkb,
            fallback_publish_date=fallback_publish_date,
            fallback_version=fallback_version,
        )

        if not payload:
            logger.warning("Не удалось собрать payload для WordPress, пропускаем синхронизацию")
            return False

        result = self.wp_client.upsert_disease(payload)
        if result.get('ok'):
            post_id = int(result.get('post_id', 0))

            if post_id <= 0:
                logger.error("WordPress sync completed without post_id for %s", payload['external_id'])
                return False

            if not self._sync_wordpress_attachments(post_id, payload['external_id']):
                logger.error("Синхронизация вложений в WordPress не завершилась для %s", payload['external_id'])
                return False

            logger.info(
                "Синхронизация с WordPress завершена: %s -> post_id=%s",
                payload['external_id'],
                post_id,
            )
            return True

        logger.error("WordPress sync failed for %s: %s", payload['external_id'], result)
        return False

    def _build_wordpress_payload(
            self,
            detail_data: Dict[str, Any],
            formatted_text: str,
            formatted_html: str,
            json_hash: str,
            fallback_code_version: str = '',
            fallback_name: str = '',
            fallback_mkb: str = '',
            fallback_publish_date: str = '',
            fallback_version: int = 1,
    ) -> Dict[str, Any]:
        """Построение payload для WordPress REST upsert"""
        detail_data = detail_data or {}

        code_version = str(detail_data.get('id') or fallback_code_version or '').strip()
        title = str(detail_data.get('name') or fallback_name or '').strip()

        if not code_version or not title:
            return {}

        publish_date = str(detail_data.get('publish_date') or fallback_publish_date or '').strip()
        mkb = str(detail_data.get('mkb') or fallback_mkb or '').strip()
        version = detail_data.get('version') or detail_data.get('ver') or fallback_version or 1

        meta_extra = {
            'medvise_clinrec_code_version': code_version,
            'medvise_clinrec_publish_date': publish_date,
            'medvise_clinrec_version': str(version),
            'medvise_clinrec_mkb': mkb,
            'medvise_clinrec_json_hash': json_hash,
            'medvise_clinrec_synced_at': datetime.now().isoformat(),
        }

        if detail_data.get('apply_status'):
            meta_extra['medvise_clinrec_apply_status'] = str(detail_data['apply_status'])

        if detail_data.get('status') is not None:
            meta_extra['medvise_clinrec_source_status'] = str(detail_data['status'])

        payload = {
            'title': title,
            'content': formatted_html or '<p>Контент не найден</p>',
            'external_id': code_version,
            'article_type_slug': config.WP_ARTICLE_TYPE_SLUG,
            'author_id': config.WP_AUTHOR_ID,
            'post_excerpt': self._build_excerpt(formatted_text),
            'age_slugs': self._extract_age_slugs(detail_data),
            'specialty_names_or_slugs': self._extract_specialty_values(detail_data),
            'meta_extra': meta_extra,
        }

        if publish_date:
            payload['post_date'] = publish_date

        return payload

    def _sync_wordpress_attachments(self, post_id: int, code_version: str) -> bool:
        if not self.wp_client:
            return True

        files = self.file_manager.list_recommendation_files(code_version)
        expected_keys = [item['name'] for item in files]

        for item in files:
            result = self.wp_client.upload_disease_attachment(
                post_id=post_id,
                external_id=code_version,
                file_path=item['path'],
                file_key=item['name'],
                file_hash=item['hash'],
                attachment_role=item['role'],
            )

            if not result.get('ok'):
                logger.error(
                    "Не удалось синхронизировать вложение %s для %s: %s",
                    item['name'],
                    code_version,
                    result,
                )
                return False

        finalize_result = self.wp_client.finalize_disease_attachments(
            post_id=post_id,
            external_id=code_version,
            expected_keys=expected_keys,
        )

        if not finalize_result.get('ok'):
            logger.error("Не удалось завершить синхронизацию вложений для %s: %s", code_version, finalize_result)
            return False

        logger.info(
            "Вложения синхронизированы для %s: %d шт., pdf_attachment_id=%s",
            code_version,
            len(finalize_result.get('attachment_ids', [])),
            finalize_result.get('pdf_attachment_id', 0),
        )

        return True

    def _extract_age_slugs(self, detail_data: Dict[str, Any]) -> list[str]:
        age_slugs: list[str] = []

        if detail_data.get('adult'):
            age_slugs.append('adult')

        if detail_data.get('child'):
            age_slugs.append('child')

        return age_slugs

    def _extract_specialty_values(self, detail_data: Dict[str, Any]) -> list[str]:
        values: list[str] = []

        for specialty in detail_data.get('specialities') or []:
            if not isinstance(specialty, dict):
                continue

            name = str(specialty.get('Name') or specialty.get('name') or '').strip()
            if name:
                values.append(name)

        return list(dict.fromkeys(values))

    def _build_excerpt(self, formatted_text: str, limit: int = 280) -> str:
        text = re.sub(r'\s+', ' ', formatted_text.replace('%', ' ')).strip()

        if len(text) <= limit:
            return text

        return text[:limit].rstrip() + '...'

    def export_to_files(self, output_dir: str):
        """Экспорт всех отформатированных текстов в файлы"""
        os.makedirs(output_dir, exist_ok=True)

        import sqlite3
        with sqlite3.connect(self.db.db_path) as conn:
            cursor = conn.cursor()
            cursor.execute(
                "SELECT code_version, name, formatted_text FROM clinical_recommendations WHERE is_deleted = 0"
            )

            for code_version, name, text in cursor.fetchall():
                # Безопасное имя файла
                safe_name = "".join(c for c in name if c.isalnum() or c in (' ', '-', '_')).strip()
                filename = f"{code_version}_{safe_name[:50]}.txt"
                filepath = os.path.join(output_dir, filename)

                try:
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(f"Клиническая рекомендация: {name}\n")
                        f.write(f"Код версии: {code_version}\n")
                        f.write("=" * 50 + "\n\n")
                        f.write(text)

                    logger.info(f"Экспортировано: {filename}")
                except Exception as e:
                    logger.error(f"Ошибка экспорта {filename}: {e}")


def main():
    parser = ClinicalRecommendationsParser()

    # Запуск парсинга
    parser.run()

    # Экспорт в файлы (опционально)
    # parser.export_to_files("export")


if __name__ == "__main__":
    main()
