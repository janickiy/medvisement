import os
import json
import hashlib
from typing import Dict, Any, Optional, List
from config import config
import logging

logger = logging.getLogger(__name__)


class FileManager:
    ALLOWED_ATTACHMENT_EXTENSIONS = {
        '.pdf',
        '.png',
        '.jpg',
        '.jpeg',
        '.gif',
        '.webp',
        '.svg',
        '.html',
        '.htm',
        '.txt',
        '.csv',
        '.doc',
        '.docx',
        '.xls',
        '.xlsx',
        '.zip',
        '.rar',
        '.ppt',
        '.pptx',
    }

    def __init__(self):
        self.json_dir = config.JSON_DIR
        self.files_dir = config.FILES_DIR

    def save_json(self, code_version: str, data: Dict[str, Any]) -> str:
        """Сохранить JSON данные в файл"""
        filename = f"{code_version}.json"
        filepath = os.path.join(self.json_dir, filename)

        try:
            with open(filepath, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)

            # Вычисляем хэш файла
            file_hash = self._calculate_file_hash(filepath)

            logger.info(f"JSON сохранен: {filepath}")
            return file_hash

        except Exception as e:
            logger.error(f"Ошибка сохранения JSON {filename}: {e}")
            return ""

    def load_json(self, code_version: str) -> Optional[Dict[str, Any]]:
        """Загрузить JSON из файла"""
        filename = f"{code_version}.json"
        filepath = os.path.join(self.json_dir, filename)

        if not os.path.exists(filepath):
            return None

        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception as e:
            logger.error(f"Ошибка загрузки JSON {filename}: {e}")
            return None

    def _calculate_file_hash(self, filepath: str) -> str:
        """Вычислить хэш файла"""
        try:
            with open(filepath, 'rb') as f:
                file_hash = hashlib.md5()
                chunk = f.read(8192)
                while chunk:
                    file_hash.update(chunk)
                    chunk = f.read(8192)
            return file_hash.hexdigest()
        except Exception as e:
            logger.error(f"Ошибка вычисления хэша {filepath}: {e}")
            return ""

    def create_recommendation_directory(self, code_version: str) -> str:
        """Создать директорию для файлов рекомендации"""
        rec_dir = os.path.join(self.files_dir, code_version)
        os.makedirs(rec_dir, exist_ok=True)
        return rec_dir

    def list_recommendation_files(self, code_version: str) -> List[Dict[str, str]]:
        """Получить список файлов рекомендации для синхронизации в WordPress"""
        rec_dir = os.path.join(self.files_dir, code_version)
        if not os.path.isdir(rec_dir):
            return []

        files: List[Dict[str, str]] = []

        for filename in sorted(os.listdir(rec_dir)):
            filepath = os.path.join(rec_dir, filename)
            if not os.path.isfile(filepath):
                continue

            extension = os.path.splitext(filename)[1].lower()
            if extension not in self.ALLOWED_ATTACHMENT_EXTENSIONS:
                continue

            files.append(
                {
                    'name': filename,
                    'path': filepath,
                    'hash': self._calculate_file_hash(filepath),
                    'role': 'pdf' if filename.lower() == f'{code_version.lower()}.pdf' else 'file',
                }
            )

        files.sort(key=lambda item: (0 if item['role'] == 'pdf' else 1, item['name']))

        return files

    def save_formatted_text(self, code_version: str, text: str):
        """Сохранить отформатированный текст"""
        filename = f"{code_version}_formatted.txt"
        filepath = os.path.join(self.json_dir, filename)

        try:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(text)
            logger.info(f"Отформатированный текст сохранен: {filepath}")
        except Exception as e:
            logger.error(f"Ошибка сохранения отформатированного текста {filename}: {e}")

    def save_formatted_html(self, code_version: str, html: str):
        """Сохранить отформатированный HTML"""
        filename = f"{code_version}_formatted.html"
        filepath = os.path.join(self.json_dir, filename)

        try:
            with open(filepath, 'w', encoding='utf-8') as f:
                # Добавляем базовую HTML структуру
                full_html = f"""<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{code_version}</title>
    <style>
        body {{ font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }}
        details {{ margin: 10px 0; padding: 10px; border: 1px solid #ddd; }}
        summary {{ cursor: pointer; font-weight: bold; padding: 5px; }}
        table {{ border-collapse: collapse; width: 100%; margin: 10px 0; }}
        th, td {{ border: 1px solid #ddd; padding: 8px; text-align: left; }}
        th {{ background-color: #f2f2f2; }}
    </style>
</head>
<body>
{html}
</body>
</html>"""
                f.write(full_html)
            logger.info(f"Отформатированный HTML сохранен: {filepath}")
        except Exception as e:
            logger.error(f"Ошибка сохранения отформатированного HTML {filename}: {e}")

    def save_original_html(self, code_version: str, html: str):
        """Сохранить оригинальный HTML"""
        filename = f"{code_version}_original.html"
        filepath = os.path.join(self.json_dir, filename)

        try:
            with open(filepath, 'w', encoding='utf-8') as f:
                # Добавляем базовую HTML структуру
                full_html = f"""<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{code_version} - Оригинал</title>
    <style>
        body {{ font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }}
        table {{ border-collapse: collapse; width: 100%; margin: 10px 0; }}
        th, td {{ border: 1px solid #ddd; padding: 8px; text-align: left; }}
        th {{ background-color: #f2f2f2; }}
    </style>
</head>
<body>
{html}
</body>
</html>"""
                f.write(full_html)
            logger.info(f"Оригинальный HTML сохранен: {filepath}")
        except Exception as e:
            logger.error(f"Ошибка сохранения оригинального HTML {filename}: {e}")

    def cleanup_old_files(self, keep_code_versions: list):
        """Очистка старых файлов"""
        # Удаляем JSON файлы для удаленных рекомендаций
        for filename in os.listdir(self.json_dir):
            if filename.endswith('.json'):
                code_version = filename.replace('.json', '')
                if code_version not in keep_code_versions:
                    try:
                        os.remove(os.path.join(self.json_dir, filename))
                        logger.info(f"Удален старый JSON файл: {filename}")
                    except Exception as e:
                        logger.error(f"Ошибка удаления файла {filename}: {e}")

            # Удаляем связанные txt и html файлы
            elif filename.endswith('_formatted.txt') or filename.endswith('_formatted.html') or filename.endswith('_original.html'):
                code_version = filename.split('_')[0]
                if code_version not in keep_code_versions:
                    try:
                        os.remove(os.path.join(self.json_dir, filename))
                        logger.info(f"Удален старый файл: {filename}")
                    except Exception as e:
                        logger.error(f"Ошибка удаления файла {filename}: {e}")

        # Удаляем директории с файлами для удаленных рекомендаций
        for dirname in os.listdir(self.files_dir):
            dirpath = os.path.join(self.files_dir, dirname)
            if os.path.isdir(dirpath) and dirname not in keep_code_versions:
                try:
                    import shutil
                    shutil.rmtree(dirpath)
                    logger.info(f"Удалена директория с файлами: {dirname}")
                except Exception as e:
                    logger.error(f"Ошибка удаления директории {dirname}: {e}")
