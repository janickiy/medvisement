import os
import sys
import logging
from dataclasses import dataclass

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PARSER_LOG_PATH = os.path.join(BASE_DIR, "parser.log")


def _get_env_int(name: str, default: int) -> int:
    value = os.getenv(name)
    if value is None or value == "":
        return default
    return int(value)


def _get_env_float(name: str, default: float) -> float:
    value = os.getenv(name)
    if value is None or value == "":
        return default
    return float(value)


def _get_env_bool(name: str, default: bool) -> bool:
    value = os.getenv(name)
    if value is None or value == "":
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}

# Настройка логирования
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(PARSER_LOG_PATH, encoding='utf-8'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)


@dataclass
class Config:
    # ==================== НАСТРОЙКИ БД ====================
    # Тип БД: 'sqlite' или 'mysql'
    DB_TYPE: str = os.getenv('CLINREC_DB_TYPE', 'sqlite').lower()

    # Настройки SQLite (используются если DB_TYPE = 'sqlite')
    DB_PATH: str = os.getenv('CLINREC_DB_PATH') or None

    # Настройки MySQL (используются если DB_TYPE = 'mysql')
    DB_HOST: str = os.getenv('CLINREC_DB_HOST', 'localhost')
    DB_PORT: int = _get_env_int('CLINREC_DB_PORT', 3306)
    DB_USER: str = os.getenv('CLINREC_DB_USER', 'root')
    DB_PASSWORD: str = os.getenv('CLINREC_DB_PASSWORD', 'your_password')
    DB_NAME: str = os.getenv('CLINREC_DB_NAME', 'clinical_recommendations')

    # ==================== НАСТРОЙКИ API ====================
    API_LIST_URL: str = "https://apicr.minzdrav.gov.ru/api.ashx?op=GetJsonClinrecsFilterV2"
    API_DETAIL_URL: str = "https://apicr.minzdrav.gov.ru/api.ashx?op=GetClinrec2"

    # ==================== ПУТИ ====================
    BASE_DIR: str = BASE_DIR
    DATA_DIR: str = os.getenv('CLINREC_DATA_DIR', os.path.join(BASE_DIR, "data"))
    FILES_DIR: str = os.getenv('CLINREC_FILES_DIR', os.path.join(DATA_DIR, "files"))
    JSON_DIR: str = os.getenv('CLINREC_JSON_DIR', os.path.join(DATA_DIR, "json"))

    # ==================== НАСТРОЙКИ ПАРСЕРА ====================
    REQUEST_TIMEOUT: int = _get_env_int('CLINREC_REQUEST_TIMEOUT', 30)
    MAX_RETRIES: int = _get_env_int('CLINREC_MAX_RETRIES', 3)
    DELAY_BETWEEN_REQUESTS: float = _get_env_float('CLINREC_DELAY_BETWEEN_REQUESTS', 1.0)

    # ==================== WORDPRESS SYNC ====================
    WP_SYNC_ENABLED: bool = _get_env_bool('CLINREC_WP_SYNC_ENABLED', False)
    WP_BASE_URL: str = os.getenv('CLINREC_WP_BASE_URL', 'https://nginx').rstrip('/')
    WP_USERNAME: str = os.getenv('CLINREC_WP_USERNAME', '')
    WP_PASSWORD: str = os.getenv('CLINREC_WP_PASSWORD', '')
    WP_AUTHOR_ID: int = _get_env_int('CLINREC_WP_AUTHOR_ID', 1)
    WP_VERIFY_SSL: bool = _get_env_bool('CLINREC_WP_VERIFY_SSL', False)
    WP_ARTICLE_TYPE_SLUG: str = os.getenv('CLINREC_WP_ARTICLE_TYPE_SLUG', 'clinical-guidelines')

    # Headers для запросов
    HEADERS: dict = None

    def __post_init__(self):
        # Создаем директории если их нет
        os.makedirs(self.DATA_DIR, exist_ok=True)
        os.makedirs(self.FILES_DIR, exist_ok=True)
        os.makedirs(self.JSON_DIR, exist_ok=True)

        # Устанавливаем путь к SQLite БД
        if self.DB_PATH is None:
            self.DB_PATH = os.path.join(self.DATA_DIR, "clinical_recommendations.db")

        # Инициализируем заголовки
        if self.HEADERS is None:
            self.HEADERS = {
                "authority": "apicr.minzdrav.gov.ru",
                "accept": "application/json, text/plain, */*",
                "accept-language": "ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7",
                "authorization": "Bearer null",
                "origin": "https://cr.minzdrav.gov.ru",
                "referer": "https://cr.minzdrav.gov.ru/",
                "sec-ch-ua": '"Google Chrome";v="143", "Chromium";v="143", "Not=A?Brand";v="24"',
                "sec-ch-ua-mobile": "?0",
                "sec-ch-ua-platform": '"Windows"',
                "sec-fetch-dest": "empty",
                "sec-fetch-mode": "cors",
                "sec-fetch-site": "same-site",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) "
                              "Chrome/143.0.0.0 Safari/537.36",
                "content-type": "application/json"
            }

        # Логируем используемый тип БД
        logger.info(f"Используется БД: {self.DB_TYPE}")
        if self.DB_TYPE == 'sqlite':
            logger.info(f"Путь к SQLite: {self.DB_PATH}")
        elif self.DB_TYPE == 'mysql':
            logger.info(f"MySQL подключение: {self.DB_USER}@{self.DB_HOST}:{self.DB_PORT}/{self.DB_NAME}")

        if self.WP_SYNC_ENABLED:
            logger.info(
                "WordPress sync enabled: %s as %s (author_id=%s)",
                self.WP_BASE_URL,
                self.WP_USERNAME,
                self.WP_AUTHOR_ID,
            )
        else:
            logger.info("WordPress sync disabled")


config = Config()
