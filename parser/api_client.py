import requests
import json
import time
from typing import List, Dict, Any, Optional
from config import config
import logging

logger = logging.getLogger(__name__)


class APIClient:
    def __init__(self):
        self.session = requests.Session()
        self.session.headers.update(config.HEADERS)

    def get_all_recommendations(self) -> List[Dict[str, Any]]:
        """Получить все клинические рекомендации одним запросом"""
        payload = {
            "filters": [
                {
                    "fieldName": "status",
                    "filterType": 1,
                    "filterValueType": 2,
                    "value1": 0,
                    "value2": "",
                    "values": []
                }
            ],
            "sortOption": {
                "fieldName": "publishdate",
                "sortType": 2
            },
            "pageSize": 999999,
            "currentPage": 1,
            "useANDoperator": True,
            "columns": []
        }

        try:
            response = self.session.post(
                config.API_LIST_URL,
                json=payload,
                timeout=config.REQUEST_TIMEOUT
            )
            response.raise_for_status()

            data = response.json()
            return data.get("Data", [])

        except Exception as e:
            logger.error(f"Ошибка при получении списка рекомендаций: {e}")
            return []

    def get_recommendation_detail(self, code_version: str) -> Optional[Dict[str, Any]]:
        """Получить детальную информацию по рекомендации"""
        url = f"{config.API_DETAIL_URL}&id={code_version}&ssid=null"

        for attempt in range(config.MAX_RETRIES):
            try:
                response = self.session.get(
                    url,
                    timeout=config.REQUEST_TIMEOUT
                )
                response.raise_for_status()

                return response.json()

            except requests.exceptions.RequestException as e:
                logger.warning(f"Попытка {attempt + 1} не удалась для {code_version}: {e}")
                if attempt < config.MAX_RETRIES - 1:
                    time.sleep(config.DELAY_BETWEEN_REQUESTS * (attempt + 1))
                else:
                    logger.error(f"Не удалось получить данные для {code_version} после {config.MAX_RETRIES} попыток")
                    return None
            except json.JSONDecodeError as e:
                logger.error(f"Ошибка парсинга JSON для {code_version}: {e}")
                return None

    def download_pdf(self, code_version: str, filepath: str) -> bool:
        """
        Скачать PDF файл клинической рекомендации
        Исправление проблемы №1
        """
        url = f"https://apicr.minzdrav.gov.ru/api.ashx?op=GetClinrecPdf&id={code_version}"

        for attempt in range(config.MAX_RETRIES):
            try:
                logger.info(f"Попытка скачивания PDF для {code_version}...")
                response = self.session.get(
                    url,
                    timeout=config.REQUEST_TIMEOUT,
                    stream=True
                )
                response.raise_for_status()

                # Проверяем, что получили PDF
                content_type = response.headers.get('Content-Type', '')
                if 'pdf' not in content_type.lower() and len(response.content) < 1000:
                    logger.warning(f"Получен не PDF файл для {code_version}, Content-Type: {content_type}")
                    return False

                with open(filepath, 'wb') as f:
                    for chunk in response.iter_content(chunk_size=8192):
                        if chunk:
                            f.write(chunk)

                logger.info(f"PDF успешно скачан: {filepath}")
                return True

            except requests.exceptions.RequestException as e:
                logger.warning(f"Попытка {attempt + 1} скачивания PDF для {code_version} не удалась: {e}")
                if attempt < config.MAX_RETRIES - 1:
                    time.sleep(config.DELAY_BETWEEN_REQUESTS * (attempt + 1))
                else:
                    logger.error(f"Не удалось скачать PDF для {code_version} после {config.MAX_RETRIES} попыток")
                    return False
            except Exception as e:
                logger.error(f"Ошибка при скачивании PDF {code_version}: {e}")
                return False

        return False

    def download_file(self, url: str, filename: str) -> bool:
        """Скачать файл (картинку и т.д.)"""
        try:
            response = self.session.get(url, timeout=config.REQUEST_TIMEOUT, stream=True)
            response.raise_for_status()

            with open(filename, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    f.write(chunk)

            return True

        except Exception as e:
            logger.error(f"Ошибка при скачивании файла {url}: {e}")
            return False