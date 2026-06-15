import os
import re
import json
import time
import hashlib
import mimetypes
import ast
import requests
from bs4 import BeautifulSoup
from html import escape
from pypdf import PdfReader
from requests.auth import HTTPBasicAuth
from storage import init_db, save_result

try:
    import fitz  # PyMuPDF
except ImportError:  # pragma: no cover - production image installs PyMuPDF.
    fitz = None

# ----------------------------------------------------------------------
# Настройки (переменные окружения / значения по умолчанию)
# ----------------------------------------------------------------------
ANTICAPTCHA_API_KEY = os.getenv("ANTICAPTCHA_API_KEY", "")
DELAY_BETWEEN_PAGES = float(os.getenv("DELAY_BETWEEN_PAGES", "1"))  # секунд между запросами страниц
DELAY_BETWEEN_PDF = float(os.getenv("DELAY_BETWEEN_PDF", "1"))  # секунд между скачиванием PDF
CAPTCHA_IMAGES_DIR = os.getenv("CAPTCHA_IMAGES_DIR", "captcha_images")
PDF_DIR = os.getenv("PDF_DIR", "pdf_files")
LOCAL_DB_PATH = os.getenv("LOCAL_DB_PATH", os.path.join("data", "parser_lk.db"))
HTTP_TIMEOUT = float(os.getenv("HTTP_TIMEOUT", "60"))
ANTICAPTCHA_REQUEST_TIMEOUT = float(os.getenv("ANTICAPTCHA_REQUEST_TIMEOUT", "30"))
PAGE_RETRY_DELAY = float(os.getenv("PAGE_RETRY_DELAY", str(DELAY_BETWEEN_PAGES)))
PAGE_RETRY_REFRESH_AFTER = int(os.getenv("PAGE_RETRY_REFRESH_AFTER", "3") or "3")

WP_SYNC_ENABLED = os.getenv("WP_SYNC_ENABLED", "true").strip().lower() in {"1", "true", "yes", "on"}
WP_BASE_URL = os.getenv("WP_BASE_URL", "https://host.docker.internal:8444").rstrip("/")
WP_USERNAME = os.getenv("WP_USERNAME", "admin")
WP_PASSWORD = os.getenv("WP_PASSWORD", "")
WP_VERIFY_SSL = os.getenv("WP_VERIFY_SSL", "false").strip().lower() in {"1", "true", "yes", "on"}
WP_AUTHOR_ID = int(os.getenv("WP_AUTHOR_ID", "1"))
WP_TIMEOUT = float(os.getenv("WP_TIMEOUT", "120"))
WP_POST_TYPE = os.getenv("WP_POST_TYPE", "substance").strip().lower() or "substance"
if WP_POST_TYPE not in {"disease", "substance"}:
    WP_POST_TYPE = "substance"
WP_ARTICLE_TYPE_SLUG = os.getenv("WP_ARTICLE_TYPE_SLUG", "ohlp")
WP_ARTICLE_TYPE_NAME = os.getenv("WP_ARTICLE_TYPE_NAME", "ОХЛП")
WP_POST_STATUS = os.getenv("WP_POST_STATUS", "draft")
MAX_ITEMS = int(os.getenv("MAX_ITEMS", "0") or "0")
SKIP_DOCUMENTS = os.getenv("SKIP_DOCUMENTS", "false").strip().lower() in {"1", "true", "yes", "on"}
PDF_TEXT_MAX_CHARS = int(os.getenv("PDF_TEXT_MAX_CHARS", "0") or "0")
PDF_IMAGE_MIN_WIDTH = int(os.getenv("PDF_IMAGE_MIN_WIDTH", "120") or "120")
PDF_IMAGE_MIN_HEIGHT = int(os.getenv("PDF_IMAGE_MIN_HEIGHT", "80") or "80")
PDF_IMAGE_MIN_AREA = int(os.getenv("PDF_IMAGE_MIN_AREA", "12000") or "12000")
PDF_IMAGE_MAX_PAGE_AREA_RATIO = float(os.getenv("PDF_IMAGE_MAX_PAGE_AREA_RATIO", "0.72") or "0.72")
PDF_PAGE_PREVIEW_ZOOM = float(os.getenv("PDF_PAGE_PREVIEW_ZOOM", "1.55") or "1.55")
PDF_IMAGE_PLACEHOLDER_PREFIX = "__MEDVISE_ATTACHMENT__:"
MISSING_SECTION_HTML = "<p><em>Такого раздела в данной ОХЛП нет.</em></p>"
INSTRUCTION_SECTION_DEFS = [
    {"key": "1", "summary": "1. Наименование лекарственного препарата", "pattern": r"1\.?\s*(?:Наименование|Торговое\s+наименование)\s+лекарственного\s+препарата"},
    {"key": "2", "summary": "2. Качественный и количественный состав", "pattern": r"2\.?\s*Качественный\s+и\s+количественный\s+состав"},
    {"key": "3", "summary": "3. Лекарственная форма", "pattern": r"3\.?\s*Лекарственная\s+форма"},
    {"key": "4.1", "summary": "4.1. Показания к применению", "pattern": r"4\.1\.?\s*Показания\s+к\s+применению"},
    {"key": "4.2", "summary": "4.2. Режим дозирования и способ применения", "pattern": r"4\.2\.?\s*(?:Режим\s+дозирования\s+и\s+способ\s+применения|Способ\s+применения\s+и\s+режим\s+дозирования|Способ\s+применения|Режим\s+дозирования)"},
    {"key": "4.3", "summary": "4.3. Противопоказания", "pattern": r"4\.3\.?\s*Противопоказания"},
    {"key": "4.4", "summary": "4.4. Особые указания и меры предосторожности при применении", "pattern": r"4\.4\.?\s*(?:Особые\s+указания\s+и\s+меры\s+предосторожности\s+при\s+применении|Особые\s+указания)"},
    {"key": "4.5", "summary": "4.5. Взаимодействие с другими лекарственными препаратами и другие виды взаимодействия", "pattern": r"4\.5\.?\s*Взаимодействие(?:\s+с\s+другими\s+лекарственными\s+(?:препаратами|средствами))?"},
    {"key": "4.6", "summary": "4.6. Фертильность, беременность и лактация", "pattern": r"4\.6\.?\s*(?:Фертильность,\s*)?беременность\s+и\s+лактация|Применение\s+при\s+беременности\s+и\s+в\s+период\s+лактации"},
    {"key": "4.7", "summary": "4.7. Влияние на способность управлять транспортными средствами и работать с механизмами", "pattern": r"4\.7\.?\s*Влияние\s+на\s+способность\s+управлять\s+транспортными\s+средствами\s+и\s+(?:работать|механизмами)"},
    {"key": "4.8", "summary": "4.8. Нежелательные реакции", "pattern": r"4\.8\.?\s*Нежелательные\s+реакции"},
    {"key": "4.9", "summary": "4.9. Передозировка", "pattern": r"4\.9\.?\s*Передозировка"},
    {"key": "5.1", "summary": "5.1. Фармакодинамические свойства", "pattern": r"5\.1\.?\s*(?:Фармакодинамические\s+свойства|Механизм\s+действия)"},
    {"key": "5.2", "summary": "5.2. Фармакокинетические свойства", "pattern": r"5\.2\.?\s*Фармакокинетические\s+свойства"},
    {"key": "5.3", "summary": "5.3. Данные доклинической безопасности", "pattern": r"5\.3\.?\s*Данные\s+доклинической\s+безопасности"},
]
PHARMACEUTICAL_PROPERTY_DEFS = [
    {"key": "6.1", "summary": "6.1. Перечень вспомогательных веществ", "pattern": r"6\.1\.?\s*Перечень\s+вспомогательных\s+веществ"},
    {"key": "6.2", "summary": "6.2. Несовместимость", "pattern": r"6\.2\.?\s*Несовместимость"},
    {"key": "6.3", "summary": "6.3. Срок годности", "pattern": r"6\.3\.?\s*Срок\s+годности"},
    {"key": "6.4", "summary": "6.4. Особые меры предосторожности при хранении", "pattern": r"6\.4\.?\s*Особые\s+меры\s+предосторожности\s+при\s+хранении"},
    {"key": "6.5", "summary": "6.5. Характер и содержание первичной упаковки", "pattern": r"6\.5\.?\s*Характер\s+и\s+содержание\s+первичной\s+упаковки"},
    {"key": "6.6", "summary": "6.6. Особые меры предосторожности при уничтожении и другие манипуляции с препаратом", "pattern": r"6\.6\.?\s*Особые\s+меры\s+предосторожности\s+при\s+уничтожении"},
]
ALL_PDF_SECTION_DEFS = INSTRUCTION_SECTION_DEFS + PHARMACEUTICAL_PROPERTY_DEFS
PDF_SECTION_LOOKAHEAD_RE = re.compile(r"^\s*(?:[1-9]|1[0-2])(?:\.\d+)?\.?\s+[А-ЯA-ZЁ]", re.IGNORECASE)
PDF_STOP_AFTER_SIX_RE = re.compile(r"^\s*(?:7|8|9|10|11|12)\.?\s+[А-ЯA-ZЁ]", re.IGNORECASE)
PDF_PREVIEW_VERSION = "pdf-preview-original-until-section-7-v2"
SECTION_PATTERN_BY_KEY = {
    item["key"]: re.compile(r"^\s*" + item["pattern"], re.IGNORECASE)
    for item in ALL_PDF_SECTION_DEFS
}

# Заголовки для POST запросов (из примера)
POST_HEADERS = {
    'Accept': 'text/html, */*; q=0.01',
    'Accept-Encoding': 'gzip, deflate, br, zstd',
    'Accept-Language': 'ru,en;q=0.9',
    'DXCss': '0_2059,1_68,1_69,0_2062,1_74,1_210,0_1927,1_209,0_1930,0_1944,0_1947,1_84',
    'DXScript': '1_11,1_64,1_12,1_252,1_13,1_14,1_15,1_16,1_20,1_66,1_48,1_17,1_9,17_0,17_8,1_27,1_39,1_31,17_36,1_23,1_55,17_35,1_41,1_54,1_53,17_34,1_183,1_184,1_24,1_33,1_46,1_213,1_211,1_240,1_47,1_52,17_6,1_51,17_15,1_21,1_22,1_40,1_34,1_19,1_224,1_225,1_212,1_218,1_216,1_219,1_220,1_217,1_221,1_214,1_222,1_223,1_227,1_236,1_238,1_239,1_226,1_231,1_232,1_233,1_215,1_228,1_229,1_230,1_234,1_235,1_237,17_49,17_50,17_2,1_59,1_57,17_39,1_56,17_40,1_58,17_41,17_42,1_60,17_3,1_49,17_9,17_10,1_35,17_11,1_63,1_62,17_12,1_50,1_38,17_44,1_43,17_13,17_14,1_67,1_185,1_182,17_24,1_205,17_25,1_194,17_18,1_203,17_20,1_188,1_190,1_198,1_199,1_200,1_204,1_186,1_193,17_17,17_22,1_192,17_19,1_61,1_195,1_189,17_16,1_197,1_191,17_43,1_202,1_196,17_21,1_250,17_1',
    'Origin': 'https://lk.regmed.ru',
    'Referer': 'https://lk.regmed.ru/Register/EAEU_SmPC',
    'Sec-Fetch-Dest': 'empty',
    'Sec-Fetch-Mode': 'cors',
    'Sec-Fetch-Site': 'same-origin',
    'X-Requested-With': 'XMLHttpRequest',
    'sec-ch-ua': '"Not(A:Brand";v="8", "Chromium";v="144", "YaBrowser";v="26.3", "Yowser";v="2.5"',
    'sec-ch-ua-mobile': '?0',
    'sec-ch-ua-platform': '"Windows"',
    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
}

USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 YaBrowser/26.3.0.0 Safari/537.36"

# Заголовки для скачивания PDF (копия из браузера)
PDF_HEADERS = {
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'Accept-Encoding': 'gzip, deflate, br, zstd',
    'Accept-Language': 'ru,en;q=0.9',
    'Referer': 'https://lk.regmed.ru/Register/EAEU_SmPC',
    'Sec-Fetch-Dest': 'iframe',
    'Sec-Fetch-Mode': 'navigate',
    'Sec-Fetch-Site': 'same-origin',
    'Sec-Fetch-User': '?1',
    'Upgrade-Insecure-Requests': '1',
    'sec-ch-ua': '"Not(A:Brand";v="8", "Chromium";v="144", "YaBrowser";v="26.3", "Yowser";v="2.5"',
    'sec-ch-ua-mobile': '?0',
    'sec-ch-ua-platform': '"Windows"',
    'User-Agent': USER_AGENT,
}



class BlockingParseError(RuntimeError):
    pass


def calculate_file_hash(file_path):
    digest = hashlib.md5()
    with open(file_path, "rb") as handle:
        for chunk in iter(lambda: handle.read(8192), b""):
            digest.update(chunk)
    return digest.hexdigest()


class WordPressClient:
    """Минимальный REST-клиент для выгрузки ОХЛП в WordPress."""

    def __init__(self):
        self.enabled = WP_SYNC_ENABLED and bool(WP_BASE_URL and WP_USERNAME and WP_PASSWORD)
        self.session = requests.Session()
        self.session.auth = HTTPBasicAuth(WP_USERNAME, WP_PASSWORD)

        if not WP_VERIFY_SSL:
            requests.packages.urllib3.disable_warnings()  # type: ignore[attr-defined]

        if WP_SYNC_ENABLED and not self.enabled:
            print("WordPress sync включён, но WP_BASE_URL/WP_USERNAME/WP_PASSWORD заполнены не полностью.")

    def request(self, method, path, **kwargs):
        kwargs.setdefault("timeout", WP_TIMEOUT)
        kwargs.setdefault("verify", WP_VERIFY_SSL)
        headers = kwargs.pop("headers", {})
        headers.setdefault("Accept", "application/json")
        response = self.session.request(method, f"{WP_BASE_URL}{path}", headers=headers, **kwargs)
        response.raise_for_status()
        payload = response.json()
        if not isinstance(payload, dict):
            raise ValueError("WordPress REST вернул неожиданный ответ")
        return payload

    def upsert_disease(self, payload):
        return self.upsert_post(payload)

    def upsert_post(self, payload):
        return self.request(
            "POST",
            f"/wp-json/medvise/v1/{WP_POST_TYPE}/upsert",
            json=payload,
            headers={"Content-Type": "application/json"},
        )

    def disease_status(self, external_id):
        return self.post_status(external_id)

    def post_status(self, external_id):
        return self.request(
            "GET",
            f"/wp-json/medvise/v1/{WP_POST_TYPE}/status",
            params={"external_id": external_id},
        )

    def upload_attachment(self, post_id, external_id, file_path, file_key, attachment_role="file"):
        mime_type = mimetypes.guess_type(file_path)[0] or "application/octet-stream"
        with open(file_path, "rb") as handle:
            return self.request(
                "POST",
                f"/wp-json/medvise/v1/{WP_POST_TYPE}/attachment",
                data={
                    "post_id": str(post_id),
                    "external_id": external_id,
                    "file_key": file_key,
                    "file_hash": calculate_file_hash(file_path),
                    "attachment_role": attachment_role,
                },
                files={"file": (os.path.basename(file_path), handle, mime_type)},
            )

    def finalize_attachments(self, post_id, external_id, expected_keys):
        return self.request(
            "POST",
            f"/wp-json/medvise/v1/{WP_POST_TYPE}/attachments/finalize",
            json={
                "post_id": post_id,
                "external_id": external_id,
                "expected_keys": expected_keys,
            },
            headers={"Content-Type": "application/json"},
        )


def list_downloaded_documents(med_id):
    """Возвращает PDF, уже скачанные для препарата и готовые к загрузке в WP."""
    if not os.path.isdir(PDF_DIR):
        return []

    prefix = f"{med_id}_"
    documents = []
    for file_name in sorted(os.listdir(PDF_DIR)):
        if not file_name.startswith(prefix) or not file_name.lower().endswith(".pdf"):
            continue

        documents.append(
            {
                "file_name": file_name,
                "file_path": os.path.join(PDF_DIR, file_name),
            }
        )

    return documents


def get_next_captcha_image_number(captcha_dir):
    if not os.path.isdir(captcha_dir):
        return 1

    return len([name for name in os.listdir(captcha_dir) if name.lower().endswith(".jpg")]) + 1


def wordpress_attachments_synced(wp_client, med_id):
    if not wp_client or not wp_client.enabled:
        return False

    external_id = f"lk_{med_id}"
    try:
        status = wp_client.disease_status(external_id)
    except Exception as exc:
        print(f"Не удалось проверить статус WordPress для {external_id}: {exc}")
        return False

    return bool(status.get("exists") and status.get("attachments_synced"))


def reached_items_limit(processed_count):
    return MAX_ITEMS > 0 and processed_count >= MAX_ITEMS


def normalize_id_folder(value):
    return str(value).strip()


def normalize_mnn_name(value):
    name = re.sub(r"\s+", " ", str(value or "")).strip()
    name = re.sub(r"\s*\+\s*", "+", name)
    return name


def mnn_group_key(value):
    return normalize_mnn_name(value).casefold()


def mnn_title(value):
    name = normalize_mnn_name(value)
    if not name:
        return ""
    return name[0].upper() + name[1:]


def mnn_external_id(value):
    key = mnn_group_key(value)
    digest = hashlib.md5(key.encode("utf-8")).hexdigest()
    return f"lk_mnn_{digest}"


def build_medicine_title(data):
    inn = (data.get("inn") or "").strip()
    reg_number = (data.get("reg_number") or "").strip()

    if inn:
        return mnn_title(inn)
    return f"ОХЛП {reg_number}".strip()


def resolve_document_path(doc):
    file_name = os.path.basename(doc.get("file_name") or doc.get("file_path") or "")
    if not file_name:
        return "", ""

    file_path = doc.get("file_path") or ""
    if not file_path:
        file_path = os.path.join(PDF_DIR, file_name)
    elif not os.path.isabs(file_path):
        candidates = [
            file_path,
            os.path.join(PDF_DIR, file_name),
        ]
        file_path = next((candidate for candidate in candidates if os.path.exists(candidate)), file_path)

    return file_name, file_path


def normalize_pdf_text_line(line):
    return re.sub(r"\s+", " ", line or "").strip()


def list_marker_info(text):
    stripped = text.strip()
    ordered_match = re.match(r"^\s*(?:\d+|[a-zа-я])[\.)]\s+(.+)$", stripped, flags=re.IGNORECASE)
    if ordered_match:
        return "ol", ordered_match.group(1)

    unordered_match = re.match(r"^\s*(?:[•·●▪‒–—-])\s+(.+)$", stripped)
    if unordered_match:
        return "ul", unordered_match.group(1)

    return None, stripped


def render_list_html(list_type, list_items):
    if not list_type or not list_items:
        return ""
    return f"<{list_type}>" + "".join(f"<li>{item}</li>" for item in list_items) + f"</{list_type}>"


def append_to_last_list_item(list_items, html):
    html = str(html or "").strip()
    if not list_items or not html:
        return
    separator = "<br>" if list_items[-1] else ""
    list_items[-1] = f"{list_items[-1]}{separator}{html}"


def is_plain_text_list_break(line):
    stripped = normalize_pdf_text_line(line)
    if not stripped:
        return True
    if stripped.endswith(":"):
        return True
    if len(stripped) <= 90 and stripped.upper() == stripped and re.search(r"[A-ZА-ЯЁ]", stripped):
        return True
    return False


def pdf_text_lines_to_html(text):
    parts = []
    list_type = None
    list_items = []

    def flush_list():
        nonlocal list_type, list_items
        list_html = render_list_html(list_type, list_items)
        if list_html:
            parts.append(list_html)
        list_type = None
        list_items = []

    for line in (text or "").splitlines():
        line = normalize_pdf_text_line(line)
        if not line:
            flush_list()
            continue

        current_list_type, item_text = list_marker_info(line)
        if current_list_type:
            if list_type and list_type != current_list_type:
                flush_list()
            list_type = current_list_type
            list_items.append(escape(item_text))
            continue

        if list_type and list_items and not is_plain_text_list_break(line):
            append_to_last_list_item(list_items, escape(line))
            continue

        flush_list()
        parts.append(f"<p>{escape(line)}</p>")

    flush_list()
    return "\n".join(parts)


def is_pdf_list_continuation(item, previous_item):
    if not previous_item or item.get("type") != "line":
        return False
    if is_pdf_subheading(item):
        return False
    if item.get("page") != previous_item.get("page"):
        return False

    current_y = float(item.get("y") or 0)
    previous_y = float(previous_item.get("y") or 0)
    y_gap = current_y - previous_y
    previous_size = float(previous_item.get("size") or item.get("size") or 12)
    if y_gap <= 0 or y_gap > max(22, previous_size * 2.4):
        return False

    current_x = float(item.get("x") or 0)
    previous_x = float(previous_item.get("x") or 0)
    if previous_item.get("_list_marker") and current_x <= previous_x + 4:
        return False
    return current_x >= previous_x - 8 and current_x <= previous_x + 120


def text_to_html_paragraphs(text, max_paragraph_chars=1200):
    text = re.sub(r"\s+", " ", str(text or "")).strip()
    if not text:
        return ""

    sentences = re.split(r"(?<=[.!?;:])\s+(?=[А-ЯA-ZЁ0-9])", text)
    paragraphs = []
    current = ""
    for sentence in sentences:
        sentence = sentence.strip()
        if not sentence:
            continue
        if current and len(current) + len(sentence) + 1 > max_paragraph_chars:
            paragraphs.append(current)
            current = sentence
        else:
            current = f"{current} {sentence}".strip()

    if current:
        paragraphs.append(current)

    return "\n".join(f"<p>{escape(paragraph)}</p>" for paragraph in paragraphs)


def extract_pdf_text(file_path):
    reader = PdfReader(file_path)
    if reader.is_encrypted:
        decrypt_result = reader.decrypt("")
        if not decrypt_result:
            raise RuntimeError("PDF зашифрован и не открывается пустым паролем")

    parts = []
    extracted_chars = 0
    for page in reader.pages:
        text = (page.extract_text() or "").strip()
        if not text:
            continue
        if PDF_TEXT_MAX_CHARS > 0:
            remaining_chars = PDF_TEXT_MAX_CHARS - extracted_chars
            if remaining_chars <= 0:
                break
            text = text[:remaining_chars]
            extracted_chars += len(text)
        parts.append(text)
        if PDF_TEXT_MAX_CHARS > 0 and extracted_chars >= PDF_TEXT_MAX_CHARS:
            break

    return "\n".join(parts)


def normalize_pdf_text_for_sections(text):
    text = str(text or "").replace("\u00a0", " ")
    text = re.sub(r"\s+", " ", text)
    return text.strip()


def pdf_span_to_html(span):
    text = escape(span.get("text") or "")
    if not text:
        return ""

    font = (span.get("font") or "").lower()
    flags = int(span.get("flags") or 0)
    is_bold = bool(flags & 16) or "bold" in font or "black" in font or "semibold" in font
    is_italic = bool(flags & 2) or "italic" in font or "oblique" in font

    if is_italic:
        text = f"<em>{text}</em>"
    if is_bold:
        text = f"<strong>{text}</strong>"
    return text


def bbox_is_inside(inner, outer, tolerance=2):
    return (
        inner[0] >= outer[0] - tolerance
        and inner[1] >= outer[1] - tolerance
        and inner[2] <= outer[2] + tolerance
        and inner[3] <= outer[3] + tolerance
    )


def table_rows_to_html(rows):
    body_rows = []
    for row in rows or []:
        cells = []
        for cell in row or []:
            cell_text = normalize_pdf_text_line(str(cell or ""))
            cells.append(f"<td>{escape(cell_text)}</td>")
        if cells:
            body_rows.append("<tr>" + "".join(cells) + "</tr>")

    if not body_rows:
        return ""
    return "<table><tbody>" + "".join(body_rows) + "</tbody></table>"


def pdf_image_assets_dir(file_path):
    stem = os.path.splitext(os.path.basename(file_path))[0]
    return os.path.join(os.path.dirname(file_path), f"{stem}_images")


def is_supported_pdf_image_extension(ext):
    return str(ext or "").lower().lstrip(".") in {"png", "jpg", "jpeg", "jpe", "webp", "gif", "bmp", "tif", "tiff"}


def should_keep_pdf_image(block, page_rect):
    width_px = int(block.get("width") or 0)
    height_px = int(block.get("height") or 0)
    bbox = tuple(block.get("bbox") or (0, 0, 0, 0))
    bbox_width = max(0, float(bbox[2]) - float(bbox[0]))
    bbox_height = max(0, float(bbox[3]) - float(bbox[1]))
    pixel_area = width_px * height_px
    bbox_area = bbox_width * bbox_height
    page_area = float(page_rect.width) * float(page_rect.height)

    if width_px < PDF_IMAGE_MIN_WIDTH or height_px < PDF_IMAGE_MIN_HEIGHT:
        return False
    if pixel_area < PDF_IMAGE_MIN_AREA and bbox_area < PDF_IMAGE_MIN_AREA / 3:
        return False
    if page_area > 0 and bbox_area / page_area > PDF_IMAGE_MAX_PAGE_AREA_RATIO:
        return False

    near_top = float(bbox[1]) < 90 and bbox_height < 90
    near_bottom = float(page_rect.height) - float(bbox[3]) < 90 and bbox_height < 90
    if near_top or near_bottom:
        return False

    return True


def save_pdf_image_block(file_path, page_index, image_index, block):
    image_bytes = block.get("image")
    ext = str(block.get("ext") or "png").lower().lstrip(".")
    if ext == "jpeg":
        ext = "jpg"
    if not image_bytes or not is_supported_pdf_image_extension(ext):
        return None

    assets_dir = pdf_image_assets_dir(file_path)
    os.makedirs(assets_dir, exist_ok=True)
    stem = os.path.splitext(os.path.basename(file_path))[0]
    image_hash = hashlib.md5(image_bytes).hexdigest()[:10]
    file_name = f"{stem}_p{page_index + 1}_{image_index + 1}_{image_hash}.{ext}"
    image_path = os.path.join(assets_dir, file_name)

    if not os.path.exists(image_path):
        with open(image_path, "wb") as handle:
            handle.write(image_bytes)

    return image_path, file_name


def render_image_item_html(item):
    file_key = item.get("file_key") or item.get("file_name") or ""
    if not file_key:
        return ""

    src = PDF_IMAGE_PLACEHOLDER_PREFIX + file_key
    alt = item.get("alt") or "Изображение из PDF"
    return (
        f'<img class="medvise-pdf-image" src="{escape(src, quote=True)}" '
        f'alt="{escape(alt, quote=True)}" '
        f'data-medvise-attachment-key="{escape(file_key, quote=True)}">'
    )


def extract_pdf_items_with_pymupdf(file_path):
    if fitz is None:
        return []

    doc = fitz.open(file_path)
    items = []
    extracted_chars = 0
    for page_index, page in enumerate(doc):
        table_bboxes = []
        if hasattr(page, "find_tables"):
            try:
                tables = page.find_tables()
                for table in getattr(tables, "tables", []) or []:
                    rows = table.extract()
                    table_html = table_rows_to_html(rows)
                    if not table_html:
                        continue
                    bbox = tuple(table.bbox)
                    table_bboxes.append(bbox)
                    table_text = " ".join(
                        normalize_pdf_text_line(str(cell or ""))
                        for row in rows or []
                        for cell in row or []
                    )
                    items.append(
                        {
                            "type": "table",
                            "page": page_index,
                            "x": bbox[0],
                            "y": bbox[1],
                            "page_height": float(page.rect.height),
                            "bbox": bbox,
                            "text": normalize_pdf_text_line(table_text),
                            "html": table_html,
                            "size": 0,
                        }
                    )
            except Exception as exc:
                print(f"Не удалось извлечь таблицы из PDF {os.path.basename(file_path)}: {exc}")

        text_dict = page.get_text("dict")
        image_index = 0
        for block in text_dict.get("blocks", []):
            if block.get("type") == 1:
                if not should_keep_pdf_image(block, page.rect):
                    continue
                saved_image = save_pdf_image_block(file_path, page_index, image_index, block)
                image_index += 1
                if not saved_image:
                    continue
                image_path, image_file_name = saved_image
                bbox = tuple(block.get("bbox") or (0, 0, 0, 0))
                items.append(
                    {
                        "type": "image",
                        "page": page_index,
                        "x": bbox[0],
                        "y": bbox[1],
                        "page_height": float(page.rect.height),
                        "bbox": bbox,
                        "text": "",
                        "html": "",
                        "size": 0,
                        "file_name": image_file_name,
                        "file_path": image_path,
                        "file_key": image_file_name,
                        "role": "pdf_image",
                        "alt": f"Изображение из PDF {os.path.basename(file_path)}, страница {page_index + 1}",
                    }
                )
                continue

            if block.get("type") != 0:
                continue
            for line in block.get("lines", []):
                bbox = tuple(line.get("bbox") or (0, 0, 0, 0))
                if any(bbox_is_inside(bbox, table_bbox) for table_bbox in table_bboxes):
                    continue

                spans = line.get("spans", [])
                text = normalize_pdf_text_line("".join(span.get("text") or "" for span in spans))
                if not text:
                    continue
                if PDF_TEXT_MAX_CHARS > 0 and extracted_chars >= PDF_TEXT_MAX_CHARS:
                    break

                span_html = "".join(pdf_span_to_html(span) for span in spans)
                sizes = [float(span.get("size") or 0) for span in spans]
                fonts = [(span.get("font") or "").lower() for span in spans]
                flags = [int(span.get("flags") or 0) for span in spans]
                is_bold = any(flag & 16 for flag in flags) or any(
                    "bold" in font or "black" in font or "semibold" in font for font in fonts
                )
                is_italic = any(flag & 2 for flag in flags) or any(
                    "italic" in font or "oblique" in font for font in fonts
                )
                items.append(
                    {
                        "type": "line",
                        "page": page_index,
                        "x": bbox[0],
                        "y": bbox[1],
                        "page_height": float(page.rect.height),
                        "bbox": bbox,
                        "text": text,
                        "html": span_html or escape(text),
                        "size": max(sizes) if sizes else 0,
                        "bold": is_bold,
                        "italic": is_italic,
                    }
                )
                extracted_chars += len(text)
            if PDF_TEXT_MAX_CHARS > 0 and extracted_chars >= PDF_TEXT_MAX_CHARS:
                break
        if PDF_TEXT_MAX_CHARS > 0 and extracted_chars >= PDF_TEXT_MAX_CHARS:
            break

    return sorted(items, key=lambda item: (item["page"], item["y"], item["x"]))


def normalized_section_heading_candidates(items, index):
    current = normalize_pdf_text_line(items[index].get("text", ""))
    candidates = [(current, index + 1)]
    if index + 1 >= len(items) or items[index].get("type") != "line":
        return candidates

    next_item = items[index + 1]
    same_page = next_item.get("page") == items[index].get("page")
    close_line = same_page and abs(float(next_item.get("y", 0)) - float(items[index].get("y", 0))) < 42
    if close_line and next_item.get("type") == "line":
        combined = normalize_pdf_text_line(f"{current} {next_item.get('text', '')}")
        candidates.append((combined, index + 2))
    return candidates


def find_pdf_section_positions(items):
    positions = []
    seen_section_six = False
    for index, item in enumerate(items):
        if item.get("type") != "line":
            continue

        for text, content_start in normalized_section_heading_candidates(items, index):
            if seen_section_six and PDF_STOP_AFTER_SIX_RE.search(text):
                positions.append({"key": "__stop__", "start": index, "content_start": content_start})
                break

            matched = False
            for key, pattern in SECTION_PATTERN_BY_KEY.items():
                if pattern.search(text):
                    positions.append({"key": key, "start": index, "content_start": content_start})
                    if str(key).startswith("6"):
                        seen_section_six = True
                    matched = True
                    break
            if matched:
                break

    return positions


def find_first_pdf_stop_position(items, positions):
    for position in positions or []:
        if position.get("key") != "__stop__":
            continue
        start = position.get("start")
        if isinstance(start, int) and 0 <= start < len(items):
            item = items[start]
            return {
                "page": int(item.get("page") or 0),
                "y": max(0.0, float(item.get("y") or 0)),
            }
    return None


def collect_pdf_anchor_positions(items, positions):
    anchors = {}
    for position in positions or []:
        key = position.get("key")
        if not key or key == "__stop__" or key in anchors:
            continue
        start = position.get("start")
        if not isinstance(start, int) or start < 0 or start >= len(items):
            continue
        item = items[start]
        bbox = item.get("bbox") or ()
        page_height = float(item.get("page_height") or 0)
        anchors[key] = {
            "page": int(item.get("page") or 0),
            "y": max(0.0, float(item.get("y") or 0)),
            "bottom_y": max(0.0, float(bbox[3] if len(bbox) > 3 else item.get("y") or 0)),
            "page_height": page_height,
        }

    grouped_sections = {
        "4": [item["key"] for item in INSTRUCTION_SECTION_DEFS if str(item["key"]).startswith("4.")],
        "5": [item["key"] for item in INSTRUCTION_SECTION_DEFS if str(item["key"]).startswith("5.")],
        "6": [item["key"] for item in PHARMACEUTICAL_PROPERTY_DEFS],
    }
    for group_key, child_keys in grouped_sections.items():
        for child_key in child_keys:
            if child_key in anchors:
                anchors[group_key] = dict(anchors[child_key])
                break

    return anchors


def create_pdf_preview_until_section_six(file_path, items, positions):
    if fitz is None or not file_path or not os.path.exists(file_path):
        return None, {}

    anchors = collect_pdf_anchor_positions(items, positions)
    stop_position = find_first_pdf_stop_position(items, positions)
    source_stat = os.stat(file_path)
    source_signature = hashlib.md5(
        (
            f"{PDF_PREVIEW_VERSION}:{os.path.basename(file_path)}:"
            f"{source_stat.st_size}:{source_stat.st_mtime_ns}:{stop_position}"
        ).encode("utf-8")
    ).hexdigest()[:10]
    assets_dir = pdf_image_assets_dir(file_path)
    os.makedirs(assets_dir, exist_ok=True)
    stem = os.path.splitext(os.path.basename(file_path))[0]
    file_name = f"{stem}_until_section_6_{source_signature}.pdf"
    preview_path = os.path.join(assets_dir, file_name)

    if os.path.exists(preview_path):
        return (
            {
                "file_name": file_name,
                "file_path": preview_path,
                "file_key": file_name,
                "role": "pdf_preview",
            },
            anchors,
        )

    doc = fitz.open(file_path)
    preview_doc = fitz.open()
    try:
        last_page = len(doc) - 1
        if stop_position:
            last_page = min(last_page, int(stop_position["page"]))

        for page_index in range(0, last_page + 1):
            page = doc[page_index]
            clip = page.rect
            if stop_position and page_index == int(stop_position["page"]):
                clip_bottom = max(0.0, float(stop_position["y"]) - 6.0)
                if clip_bottom < 40:
                    continue
                clip = fitz.Rect(0, 0, page.rect.width, min(page.rect.height, clip_bottom))

            if clip == page.rect:
                preview_doc.insert_pdf(doc, from_page=page_index, to_page=page_index)
                continue

            new_page = preview_doc.new_page(width=clip.width, height=clip.height)
            new_page.show_pdf_page(new_page.rect, doc, page_index, clip=clip)

        if len(preview_doc) == 0:
            return None, anchors

        preview_doc.save(preview_path, deflate=True, garbage=4)
    finally:
        preview_doc.close()
        doc.close()

    return (
        {
            "file_name": file_name,
            "file_path": preview_path,
            "file_key": file_name,
            "role": "pdf_preview",
        },
        anchors,
    )


def is_pdf_subheading(item):
    text = item.get("text", "").strip()
    if item.get("type") != "line" or not text:
        return False
    if PDF_SECTION_LOOKAHEAD_RE.search(text):
        return False
    if len(text) > 140:
        return False
    if text.endswith(".") and len(text) > 45:
        return False
    return bool(item.get("bold")) or text.endswith(":")


def render_pdf_items_to_html(items):
    parts = []
    paragraph_lines = []
    list_type = None
    list_items = []
    previous_line = None
    previous_list_line = None

    def flush_paragraph():
        nonlocal paragraph_lines, previous_line
        if paragraph_lines:
            parts.append("<p>" + "<br>".join(paragraph_lines) + "</p>")
            paragraph_lines = []
        previous_line = None

    def flush_list():
        nonlocal list_type, list_items, previous_list_line
        list_html = render_list_html(list_type, list_items)
        if list_html:
            parts.append(list_html)
        list_type = None
        list_items = []
        previous_list_line = None

    for item in items:
        if item.get("type") == "table":
            flush_paragraph()
            flush_list()
            parts.append(item.get("html", ""))
            continue
        if item.get("type") == "image":
            flush_paragraph()
            flush_list()
            image_html = render_image_item_html(item)
            if image_html:
                parts.append(image_html)
            continue

        text = item.get("text", "")
        if not text:
            continue

        current_list_type, item_text = list_marker_info(text)
        if current_list_type:
            flush_paragraph()
            if list_type and list_type != current_list_type:
                flush_list()
            list_type = current_list_type
            list_items.append(escape(item_text))
            previous_list_line = {**item, "_list_marker": True}
            continue

        if is_pdf_subheading(item):
            flush_paragraph()
            flush_list()
            parts.append(f"<h4>{item.get('html') or escape(text.strip(':'))}</h4>")
            continue

        if list_type and list_items and is_pdf_list_continuation(item, previous_list_line):
            append_to_last_list_item(list_items, item.get("html") or escape(text))
            previous_list_line = {**item, "_list_marker": False}
            continue

        flush_list()
        if previous_line:
            previous_size = float(previous_line.get("size") or 12)
            y_gap = float(item.get("y") or 0) - float(previous_line.get("y") or 0)
            different_page = item.get("page") != previous_line.get("page")
            if different_page or y_gap > max(18, previous_size * 1.9):
                flush_paragraph()

        paragraph_lines.append(item.get("html") or escape(text))
        previous_line = item

    flush_paragraph()
    flush_list()
    return "\n".join(part for part in parts if str(part).strip())


def render_pdf_items_with_subdetails(items, subheadings):
    positions = []
    for index, item in enumerate(items):
        if item.get("type") != "line":
            continue
        text = normalize_pdf_text_line(item.get("text", "")).strip(":")
        for heading in subheadings:
            if text.casefold() == heading.casefold():
                positions.append((index, heading))
                break

    if not positions:
        return render_pdf_items_to_html(items)

    parts = []
    if positions[0][0] > 0:
        lead_html = render_pdf_items_to_html(items[: positions[0][0]])
        if lead_html:
            parts.append(lead_html)

    for pos_index, (start, heading) in enumerate(positions):
        end = positions[pos_index + 1][0] if pos_index + 1 < len(positions) else len(items)
        content_html = render_pdf_items_to_html(items[start + 1 : end]) or MISSING_SECTION_HTML
        parts.append(details_html(heading, content_html))

    return "\n".join(parts)


def render_section_items_to_html(section_key, items):
    if section_key == "4.2":
        return render_pdf_items_with_subdetails(items, ["Способ применения", "Режим дозирования"])
    if section_key == "4.4":
        return render_pdf_items_with_subdetails(items, ["С осторожностью", "Особые указания"])
    return render_pdf_items_to_html(items)


def extract_instruction_sections_from_items(items):
    positions = find_pdf_section_positions(items)
    sections = {}
    seen_keys = set()

    for position_index, position in enumerate(positions):
        key = position["key"]
        if key == "__stop__":
            continue
        if key in seen_keys:
            continue

        start = position["content_start"]
        end = len(items)
        for next_position in positions[position_index + 1 :]:
            if next_position["start"] > position["start"]:
                end = next_position["start"]
                break

        section_items = items[start:end]
        section_html = render_section_items_to_html(key, section_items)
        if section_html.strip():
            sections[key] = section_html
            seen_keys.add(key)

    return sections


def collect_image_attachments_from_items(items):
    images = []
    seen = set()
    for item in items or []:
        if item.get("type") != "image":
            continue
        file_key = item.get("file_key") or item.get("file_name")
        file_path = item.get("file_path")
        if not file_key or not file_path or file_key in seen:
            continue
        seen.add(file_key)
        images.append(
            {
                "file_name": item.get("file_name") or file_key,
                "file_path": file_path,
                "file_key": file_key,
                "role": item.get("role") or "pdf_image",
            }
        )
    return images


def extract_instruction_section_text(pdf_text, section_key):
    section_def = next((item for item in ALL_PDF_SECTION_DEFS if item["key"] == section_key), None)
    if not section_def:
        return ""

    text = normalize_pdf_text_for_sections(pdf_text)
    match = re.search(section_def["pattern"], text, flags=re.IGNORECASE)
    if not match:
        return ""

    start = match.end()
    next_match = re.search(r"\s(?:[1-9]|1[0-2])(?:\.\d+)?\.?\s+[А-ЯA-ZЁ]", text[start:], flags=re.IGNORECASE)
    end = start + next_match.start() if next_match else len(text)
    section_text = text[start:end].strip(" .:-")
    return section_text


def extract_instruction_document_from_pdf(file_path):
    items = extract_pdf_items_with_pymupdf(file_path)
    images = collect_image_attachments_from_items(items)
    if items:
        positions = find_pdf_section_positions(items)
        preview_pdf, pdf_anchor_map = create_pdf_preview_until_section_six(file_path, items, positions)
        sections = extract_instruction_sections_from_items(items)
        preview_images = images + ([preview_pdf] if preview_pdf else [])
        if sections or pdf_anchor_map or preview_pdf:
            return {
                "sections": sections,
                "images": preview_images,
                "preview_pdf": preview_pdf,
                "pdf_anchor_map": pdf_anchor_map,
            }

    pdf_text = extract_pdf_text(file_path)
    sections = {}
    for section_def in ALL_PDF_SECTION_DEFS:
        section_text = extract_instruction_section_text(pdf_text, section_def["key"])
        if section_text:
            sections[section_def["key"]] = text_to_html_paragraphs(section_text)
    return {
        "sections": sections,
        "images": images,
        "preview_pdf": None,
        "pdf_anchor_map": {},
    }


def extract_instruction_sections_from_pdf(file_path):
    return extract_instruction_document_from_pdf(file_path)["sections"]


def extract_pdf_content_html(file_path):
    parts = []
    for page_number, text in enumerate(extract_pdf_text(file_path).split("\n"), 1):
        if not text:
            continue
        page_html = pdf_text_lines_to_html(text)
        if page_html:
            parts.append(f'<section class="medvise-pdf-page"><h4>Страница {page_number}</h4>\n{page_html}</section>')

    return "\n".join(parts)


def build_pdf_documents_content_html(documents):
    sections = []
    for doc in documents or []:
        file_name, file_path = resolve_document_path(doc)
        if not file_name:
            continue
        if not file_path or not os.path.exists(file_path):
            print(f"PDF {file_name} не найден для извлечения текста: {file_path}")
            continue

        try:
            content_html = extract_pdf_content_html(file_path)
        except Exception as exc:
            print(f"Не удалось извлечь текст из PDF {file_name}: {exc}")
            content_html = (
                "<p><em>Не удалось извлечь текст из PDF-документа. "
                f"Файл: {escape(file_name)}.</em></p>"
            )

        if not content_html.strip():
            content_html = f"<p><em>В PDF-документе не найден текстовый слой. Файл: {escape(file_name)}.</em></p>"

        sections.append(f'<section class="medvise-pdf-document">\n{content_html}</section>')

    if not sections:
        return ""

    return "\n".join(sections)


def medicine_field_rows(med_id, data, include_mnn=True):
    fields = [
        ("ID_FOLDER", str(med_id)),
    ]
    if include_mnn:
        fields.append(("МНН", data.get("inn", "")))
    fields.extend(
        [
            ("Торговое наименование", data.get("trade_name", "")),
            ("Форма выпуска", data.get("form", "")),
            ("Номер РУ", data.get("reg_number", "")),
            ("Дата РУ", data.get("reg_date", "")),
            ("Владелец РУ", data.get("owner", "")),
            ("Страна", data.get("country", "")),
        ]
    )
    return "\n".join(
        f"<tr><th>{escape(label)}</th><td>{escape(value or '—')}</td></tr>"
        for label, value in fields
    )


def trade_summary_rows(medicines):
    rows = []
    for item in medicines:
        data = item["data"]
        docs = item.get("documents") or []
        pdf_names = ", ".join(escape(doc.get("file_name", "")) for doc in docs if doc.get("file_name")) or "—"
        rows.append(
            "<tr>"
            f"<td>{escape(data.get('trade_name') or '—')}</td>"
            f"<td>{escape(data.get('form') or '—')}</td>"
            f"<td>{escape(data.get('reg_number') or '—')}</td>"
            f"<td>{escape(data.get('reg_date') or '—')}</td>"
            f"<td>{escape(data.get('owner') or '—')}</td>"
            f"<td>{escape(data.get('country') or '—')}</td>"
            f"<td>{pdf_names}</td>"
            "</tr>"
        )
    return "\n".join(rows)


def collect_instruction_documents(documents):
    parsed_documents = []
    for doc in documents or []:
        file_name, file_path = resolve_document_path(doc)
        if not file_name:
            continue

        parsed = {"file_name": file_name, "sections": {}}
        if not file_path or not os.path.exists(file_path):
            parsed["error_html"] = f"<p><em>PDF-документ не найден: {escape(file_name)}.</em></p>"
            parsed_documents.append(parsed)
            continue

        parsed["source_pdf"] = {
            "file_name": file_name,
            "file_path": file_path,
            "file_key": file_name,
            "role": "pdf",
        }

        try:
            extracted_document = extract_instruction_document_from_pdf(file_path)
            parsed["sections"] = extracted_document.get("sections") or {}
            parsed["images"] = extracted_document.get("images") or []
            parsed["preview_pdf"] = extracted_document.get("preview_pdf")
            parsed["pdf_anchor_map"] = extracted_document.get("pdf_anchor_map") or {}
        except Exception as exc:
            print(f"Не удалось разобрать разделы PDF {file_name}: {exc}")
            parsed["error_html"] = (
                "<p><em>Не удалось извлечь текст из PDF-документа. "
                f"Файл: {escape(file_name)}.</em></p>"
            )

        parsed_documents.append(parsed)

    return parsed_documents


def details_html(summary, content):
    content = str(content or "").strip() or "<p></p>"
    return f"<details><summary>{escape(summary)}</summary>\n{content}\n</details>"


def trade_summary_details(medicines):
    rows = trade_summary_rows(medicines)
    if not rows:
        return details_html("Торговые наименования и формы выпуска", "<p></p>")

    table = (
        "<table><thead><tr>"
        "<th>Торговое наименование</th>"
        "<th>Форма выпуска</th>"
        "<th>Номер РУ</th>"
        "<th>Дата РУ</th>"
        "<th>Владелец РУ</th>"
        "<th>Страна</th>"
        "<th>PDF</th>"
        "</tr></thead>"
        f"<tbody>{rows}</tbody></table>"
    )
    return details_html("Торговые наименования и формы выпуска", table)


def section_html_or_missing(sections, key):
    html = (sections or {}).get(key, "")
    return html if str(html).strip() else MISSING_SECTION_HTML


def pharmaceutical_properties_html(sections):
    parts = []
    for section_def in PHARMACEUTICAL_PROPERTY_DEFS:
        parts.append(f'<h2 class="has-text-align-center">{escape(section_def["summary"])}</h2>')
        parts.append(section_html_or_missing(sections, section_def["key"]))
    return "\n".join(parts)


def instruction_sections_html(sections):
    parts = []
    for section_def in INSTRUCTION_SECTION_DEFS:
        parts.append(details_html(section_def["summary"], section_html_or_missing(sections, section_def["key"])))
    parts.append(details_html("6. Фармацевтические свойства", pharmaceutical_properties_html(sections)))
    return "\n".join(parts)


def build_trade_instruction_content(item):
    med_id = item["med_id"]
    data = item["data"]
    documents = item.get("instruction_documents") or []
    registry_html = details_html(
        "Сведения из реестра",
        f"<table><tbody>{medicine_field_rows(med_id, data, include_mnn=False)}</tbody></table>",
    )

    if not documents:
        return registry_html + "\n" + instruction_sections_html({})

    if len(documents) == 1:
        document = documents[0]
        error_html = document.get("error_html", "")
        sections_html = instruction_sections_html(document.get("sections") or {})
        return "\n".join(part for part in [registry_html, error_html, sections_html] if str(part).strip())

    document_details = []
    for document in documents:
        content = "\n".join(
            part
            for part in [
                document.get("error_html", ""),
                instruction_sections_html(document.get("sections") or {}),
            ]
            if str(part).strip()
        )
        document_details.append(details_html(document.get("file_name") or "PDF", content))

    return registry_html + "\n" + "\n".join(document_details)


def build_trade_instruction_section(item):
    med_id = item["med_id"]
    data = item["data"]
    trade_name = (data.get("trade_name") or "").strip() or f"ID {med_id}"
    form = (data.get("form") or "").strip()
    summary = trade_name if not form else f"{trade_name} — {form}"
    return details_html(summary, build_trade_instruction_content(item))


OHLPPDF_NAV_TREE = [
    {"key": "1", "title": "1. Наименование лекарственного препарата", "children": []},
    {"key": "2", "title": "2. Качественный и количественный состав", "children": []},
    {"key": "3", "title": "3. Лекарственная форма", "children": []},
    {
        "key": "4",
        "title": "4. Клинические данные",
        "children": [item for item in INSTRUCTION_SECTION_DEFS if str(item["key"]).startswith("4.")],
    },
    {
        "key": "5",
        "title": "5. Фармакологические свойства",
        "children": [item for item in INSTRUCTION_SECTION_DEFS if str(item["key"]).startswith("5.")],
    },
    {
        "key": "6",
        "title": "6. Фармацевтические свойства",
        "children": PHARMACEUTICAL_PROPERTY_DEFS,
    },
]


def html_fragment_id(*parts):
    raw = "|".join(str(part or "") for part in parts)
    digest = hashlib.md5(raw.encode("utf-8")).hexdigest()[:10]
    slug = re.sub(r"[^a-z0-9_-]+", "-", raw.lower()).strip("-")
    slug = re.sub(r"-{2,}", "-", slug)[:56].strip("-")
    return f"ohlp-{slug}-{digest}" if slug else f"ohlp-{digest}"


def ohlp_document_title(item, document, fallback_index=1):
    data = item.get("data") or {}
    trade_name = (data.get("trade_name") or "").strip()
    form = (data.get("form") or "").strip()
    file_name = (document.get("file_name") or "").strip()

    if trade_name and form:
        return f"{trade_name} — {form}"
    if trade_name:
        return trade_name
    if file_name:
        return file_name
    return f"ОХЛП {fallback_index}"


def build_ohlp_documents(prepared_medicines):
    documents = []
    for item in prepared_medicines or []:
        instruction_documents = item.get("instruction_documents") or []
        if not instruction_documents:
            instruction_documents = [{"file_name": "ОХЛП", "sections": {}}]

        for document in instruction_documents:
            documents.append(
                {
                    "item": item,
                    "document": document,
                    "title": ohlp_document_title(item, document, len(documents) + 1),
                }
            )
    return documents


def ohlp_nav_keys_in_order():
    keys = []
    for node in OHLPPDF_NAV_TREE:
        keys.append(node["key"])
        keys.extend(child["key"] for child in node.get("children") or [])
    return keys


def positions_are_same(first, second):
    if not first or not second:
        return False
    return (
        int(first.get("page") or 0) == int(second.get("page") or 0)
        and abs(float(first.get("y") or 0) - float(second.get("y") or 0)) < 4
    )


def next_ohlp_anchor_position(section_key, anchor_map=None):
    anchor_map = anchor_map or {}
    keys = ohlp_nav_keys_in_order()
    if section_key not in keys:
        return None

    current_position = anchor_map.get(section_key)
    if not current_position:
        return None

    start_index = keys.index(section_key)
    if str(section_key) in {"4", "5", "6"}:
        next_root = str(int(section_key) + 1)
        if next_root in anchor_map and not positions_are_same(current_position, anchor_map.get(next_root)):
            return anchor_map[next_root]
        if section_key == "6":
            return None

    for next_key in keys[start_index + 1 :]:
        position = anchor_map.get(next_key)
        if position and not positions_are_same(current_position, position):
            return position
    return None


def build_ohlp_nav_attrs(document_id, section_key, anchor_map=None):
    target_id = html_fragment_id(document_id, section_key)
    attrs = [
        f'href="#{target_id}"',
        f'data-target="{target_id}"',
        f'data-document-id="{escape(document_id, quote=True)}"',
    ]
    position = (anchor_map or {}).get(section_key)
    if position:
        attrs.append(f'data-page="{int(position.get("page") or 0) + 1}"')
        attrs.append(f'data-y="{float(position.get("y") or 0):.2f}"')
        attrs.append(f'data-bottom-y="{float(position.get("bottom_y") or 0):.2f}"')
        attrs.append(f'data-page-height="{float(position.get("page_height") or 0):.2f}"')
        end_position = next_ohlp_anchor_position(section_key, anchor_map)
        if end_position:
            attrs.append(f'data-end-page="{int(end_position.get("page") or 0) + 1}"')
            attrs.append(f'data-end-y="{float(end_position.get("y") or 0):.2f}"')
            attrs.append(f'data-end-bottom-y="{float(end_position.get("bottom_y") or 0):.2f}"')
            attrs.append(f'data-end-page-height="{float(end_position.get("page_height") or 0):.2f}"')
    return " ".join(attrs)


def build_ohlp_nav_tree_html(document_id, include_document_title=False, document_title="", anchor_map=None):
    parts = []
    if include_document_title:
        parts.append(
            f'<div class="medvise-ohlp-nav-document">{escape(document_title or "ОХЛП")}</div>'
        )

    for node in OHLPPDF_NAV_TREE:
        parts.append(
            '<a class="medvise-ohlp-nav-link medvise-ohlp-nav-link-root" '
            f'{build_ohlp_nav_attrs(document_id, node["key"], anchor_map)}>{escape(node["title"])}</a>'
        )
        for child in node.get("children") or []:
            parts.append(
                '<a class="medvise-ohlp-nav-link medvise-ohlp-nav-link-child" '
                f'{build_ohlp_nav_attrs(document_id, child["key"], anchor_map)}>{escape(child["summary"])}</a>'
            )

    return "\n".join(parts)


def build_ohlp_section_html(document_id, section_key, title, content_html="", heading_level=2):
    tag = "h2" if heading_level <= 2 else "h3"
    section_id = html_fragment_id(document_id, section_key)
    content = str(content_html or "").strip()
    return (
        f'<section id="{section_id}" class="medvise-ohlp-section medvise-ohlp-section-{escape(str(section_key), quote=True)}">'
        f"<{tag}>{escape(title)}</{tag}>"
        f"{content}"
        "</section>"
    )


def build_ohlp_pdf_viewer_html(document_id, document, document_title):
    pdf_file = document.get("preview_pdf") or document.get("source_pdf") or {}
    file_key = pdf_file.get("file_key") or pdf_file.get("file_name") or ""
    if not file_key:
        return ""

    src = PDF_IMAGE_PLACEHOLDER_PREFIX + file_key
    initial_src = f"{src}#toolbar=0&navpanes=0&page=1&zoom=page-width"
    return (
        '<iframe class="medvise-ohlp-pdf-frame" '
        f'data-document-id="{escape(document_id, quote=True)}" '
        f'data-pdf-src="{escape(src, quote=True)}" '
        f'src="{escape(initial_src, quote=True)}" '
        f'title="{escape(document_title or "ОХЛП PDF", quote=True)}"></iframe>'
    )


def build_ohlp_full_pdf_link_html(document_id, document):
    pdf_file = document.get("source_pdf") or {}
    file_key = pdf_file.get("file_key") or pdf_file.get("file_name") or ""
    if not file_key:
        return ""

    src = PDF_IMAGE_PLACEHOLDER_PREFIX + file_key
    icon = (
        '<span class="medvise-ohlp-full-file-icon" aria-hidden="true">'
        '<svg viewBox="0 0 24 24" focusable="false">'
        '<path d="M6 2h8l4 4v16H6z"></path>'
        '<path d="M14 2v5h5"></path>'
        '<text x="12" y="17" text-anchor="middle">PDF</text>'
        '</svg>'
        '</span>'
    )
    return (
        '<a class="medvise-ohlp-full-file-link" '
        f'href="{escape(src, quote=True)}" '
        f'data-document-id="{escape(document_id, quote=True)}" '
        'target="_blank" rel="noopener">'
        f'{icon}<span>Смотреть весь файл</span></a>'
    )


def build_ohlp_document_html(document_id, document_title, document):
    pdf_viewer_html = build_ohlp_pdf_viewer_html(document_id, document, document_title)
    if pdf_viewer_html:
        return f'<article class="medvise-ohlp-pdf-document">{pdf_viewer_html}</article>'

    error_html = document.get("error_html") or "<p><em>PDF-документ не найден.</em></p>"
    return f'<article class="medvise-ohlp-pdf-document medvise-ohlp-pdf-missing"><div class="medvise-ohlp-error">{error_html}</div></article>'


def build_ohlp_viewer_html(mnn_name, prepared_medicines):
    documents = build_ohlp_documents(prepared_medicines)
    include_document_titles = len(documents) > 1
    nav_parts = ['<div class="medvise-ohlp-nav-title">Оглавление</div>']
    scroll_parts = []

    for index, entry in enumerate(documents, 1):
        item = entry["item"]
        document = entry["document"]
        document_id = html_fragment_id(mnn_name, item.get("med_id"), document.get("file_name"), index)
        nav_parts.append(
            build_ohlp_full_pdf_link_html(document_id, document)
        )
        nav_parts.append(
            build_ohlp_nav_tree_html(
                document_id,
                include_document_title=include_document_titles,
                document_title=entry["title"],
                anchor_map=document.get("pdf_anchor_map") or {},
            )
        )
        scroll_parts.append(
            '<section class="medvise-ohlp-document">'
            + build_ohlp_document_html(document_id, entry["title"], document)
            + "</section>"
        )

    return f"""<!-- wp:html -->
<style>
.medvise-ohlp-viewer {{
    --medvise-ohlp-blue: #2f79a8;
    --medvise-ohlp-border: #d8dde7;
    --medvise-ohlp-muted: #637083;
    --medvise-ohlp-nav-width: 280px;
    --medvise-ohlp-nav-gap: 30px;
    position: relative;
    display: block;
    width: 100%;
    max-width: none;
    margin: 42px 0 28px;
    padding: 0;
    overflow: visible;
    box-sizing: border-box;
}}
body.medvise-ohlp-layout-active #primary.content-area.medvise-ohlp-content-area,
body.medvise-ohlp-layout-active .content-area.medvise-ohlp-content-area {{
    width: 100%;
    max-width: 800px;
    margin-left: auto !important;
    margin-right: auto !important;
}}
body.medvise-ohlp-layout-active #themesflat-content > .container.medvise-ohlp-container,
body.medvise-ohlp-layout-active .page-wrap > .container.medvise-ohlp-container {{
    width: calc(100% - 40px);
    max-width: 800px;
    margin-left: auto !important;
    margin-right: auto !important;
}}
.editor-styles-wrapper .medvise-ohlp-viewer,
.block-editor-block-list__layout .medvise-ohlp-viewer {{
    margin-top: 96px;
}}
.medvise-ohlp-nav,
.medvise-ohlp-scroll {{
    background: #fff;
    border: 1px solid var(--medvise-ohlp-border);
    box-shadow: 0 14px 34px rgba(23, 46, 77, 0.08);
}}
.medvise-ohlp-nav {{
    position: absolute;
    z-index: 2;
    top: 0;
    left: calc((var(--medvise-ohlp-nav-width) + var(--medvise-ohlp-nav-gap)) * -1);
    width: var(--medvise-ohlp-nav-width);
    height: 400px;
    max-height: 400px;
    overflow: auto;
    padding: 22px 20px;
    box-sizing: border-box;
}}
.medvise-ohlp-nav-title {{
    margin: 0 0 16px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--medvise-ohlp-muted);
}}
.medvise-ohlp-full-file-link {{
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #eef6fb;
    color: #174d70;
    font-weight: 700;
    line-height: 1.25;
    text-align: center;
    text-decoration: none;
}}
.medvise-ohlp-full-file-link:hover,
.medvise-ohlp-full-file-link.is-active {{
    background: #dfeff8;
    color: #0f3d59;
}}
.medvise-ohlp-full-file-icon {{
    display: inline-flex;
    width: 22px;
    height: 22px;
    flex: 0 0 auto;
    color: #d93a3a;
}}
.medvise-ohlp-full-file-icon svg {{
    width: 100%;
    height: 100%;
    display: block;
}}
.medvise-ohlp-full-file-icon path {{
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linejoin: round;
}}
.medvise-ohlp-full-file-icon text {{
    fill: currentColor;
    font-size: 5px;
    font-weight: 700;
    font-family: Arial, sans-serif;
}}
.medvise-ohlp-nav-document {{
    margin: 18px 0 10px;
    font-weight: 700;
    color: #10233d;
}}
.medvise-ohlp-nav-link {{
    display: block;
    color: var(--medvise-ohlp-blue);
    text-decoration: underline;
    line-height: 1.28;
    border-radius: 8px;
    padding: 4px 8px;
}}
.medvise-ohlp-nav-link:hover,
.medvise-ohlp-nav-link.is-active {{
    background: #eef6fb;
    color: #174d70;
}}
.medvise-ohlp-nav-link-child {{
    margin-left: 18px;
    font-size: 0.94em;
}}
.medvise-ohlp-scroll {{
    width: 100%;
    height: 400px;
    min-height: 0;
    max-height: 400px;
    overflow: auto;
    padding: 0;
    background: #fff;
    box-sizing: border-box;
}}
.medvise-ohlp-document + .medvise-ohlp-document {{
    margin-top: 40px;
    padding-top: 32px;
    border-top: 1px solid var(--medvise-ohlp-border);
}}
.medvise-ohlp-pdf-document {{
    width: 100%;
    height: 100%;
    margin: 0 auto;
}}
.medvise-ohlp-pdf-frame {{
    width: 100%;
    min-height: 0;
    height: 400px;
    display: block;
    border: 0;
    background: #fff;
}}
.medvise-ohlp-paper {{
    max-width: 820px;
    margin: 0 auto;
    color: #101820;
    font-family: "Times New Roman", Times, serif;
    font-size: 16px;
    line-height: 1.55;
}}
.medvise-ohlp-paper h1 {{
    margin: 0 0 28px;
    text-align: center;
    font-size: 23px;
    line-height: 1.25;
    font-family: Arial, sans-serif;
    font-weight: 500;
}}
.medvise-ohlp-paper h2,
.medvise-ohlp-paper h3 {{
    margin: 30px 0 14px;
    text-align: center;
    text-transform: uppercase;
    font-weight: 700;
    line-height: 1.25;
}}
.medvise-ohlp-paper h2 {{
    font-size: 19px;
}}
.medvise-ohlp-paper h3 {{
    font-size: 16px;
}}
.medvise-ohlp-paper p,
.medvise-ohlp-paper ul,
.medvise-ohlp-paper ol,
.medvise-ohlp-paper table {{
    margin-bottom: 14px;
}}
.medvise-ohlp-paper table {{
    width: 100%;
    border-collapse: collapse;
}}
.medvise-ohlp-paper th,
.medvise-ohlp-paper td {{
    border: 1px solid #d6d6d6;
    padding: 8px;
    vertical-align: top;
}}
.medvise-ohlp-section {{
    scroll-margin-top: 18px;
}}
.medvise-ohlp-error {{
    margin-bottom: 16px;
    color: #8a1f1f;
}}
@media (max-width: 900px) {{
    .medvise-ohlp-viewer {{
        margin-top: 36px;
    }}
    body.medvise-ohlp-layout-active #primary.content-area.medvise-ohlp-content-area,
    body.medvise-ohlp-layout-active .content-area.medvise-ohlp-content-area {{
        max-width: none;
    }}
    body.medvise-ohlp-layout-active #themesflat-content > .container.medvise-ohlp-container,
    body.medvise-ohlp-layout-active .page-wrap > .container.medvise-ohlp-container {{
        width: 100%;
        max-width: none;
    }}
    .medvise-ohlp-nav {{
        position: static;
        width: 100%;
        margin-bottom: 18px;
        height: 400px;
        max-height: 400px;
    }}
    .medvise-ohlp-scroll {{
        height: 400px;
        max-height: 400px;
        overflow: auto;
    }}
    .medvise-ohlp-pdf-frame {{
        min-height: 0;
        height: 400px;
    }}
}}
</style>
<div class="medvise-ohlp-viewer">
    <nav class="medvise-ohlp-nav" aria-label="Оглавление ОХЛП">
        {"".join(nav_parts)}
    </nav>
    <div class="medvise-ohlp-scroll">
        {"".join(scroll_parts)}
    </div>
</div>
<!-- /wp:html -->"""


def build_mnn_group_content(mnn_name, medicines):
    prepared_medicines = []
    for item in medicines:
        prepared = dict(item)
        if "instruction_documents" not in prepared:
            prepared["instruction_documents"] = collect_instruction_documents(prepared.get("documents") or [])
        prepared_medicines.append(prepared)

    return build_ohlp_viewer_html(mnn_name, prepared_medicines)


def prepare_medicines_for_content(medicines):
    prepared_medicines = []
    for item in medicines or []:
        prepared = dict(item)
        prepared["instruction_documents"] = collect_instruction_documents(prepared.get("documents") or [])
        prepared_medicines.append(prepared)
    return prepared_medicines


def collect_group_attachments(prepared_medicines):
    attachments = []
    for item in prepared_medicines or []:
        for doc in item.get("documents") or []:
            file_name, file_path = resolve_document_path(doc)
            if not file_name or not file_path:
                continue
            attachments.append(
                {
                    "file_name": file_name,
                    "file_path": file_path,
                    "file_key": file_name,
                    "role": "pdf",
                }
            )

        for parsed_document in item.get("instruction_documents") or []:
            attachments.extend(parsed_document.get("images") or [])
    return attachments


def replace_attachment_placeholders(content, attachment_urls):
    html = str(content or "")
    for file_key, attachment_url in (attachment_urls or {}).items():
        if not attachment_url:
            continue
        html = html.replace(
            PDF_IMAGE_PLACEHOLDER_PREFIX + file_key,
            escape(str(attachment_url), quote=True),
        )

    html = re.sub(
        r'<img[^>]+src="' + re.escape(PDF_IMAGE_PLACEHOLDER_PREFIX) + r'[^"]+"[^>]*>',
        "",
        html,
        flags=re.IGNORECASE,
    )
    return html


def upload_group_attachments(wp_client, post_id, external_id, attachments):
    expected_keys = []
    seen_keys = set()
    attachment_urls = {}
    for attachment in attachments:
        file_key = attachment.get("file_key") or attachment.get("file_name")
        file_name = attachment.get("file_name") or file_key
        file_path = attachment.get("file_path")
        role = attachment.get("role") or "file"

        if not file_key or file_key in seen_keys:
            continue
        seen_keys.add(file_key)

        if not file_path or not os.path.exists(file_path):
            continue

        expected_keys.append(file_key)
        upload_result = wp_client.upload_attachment(post_id, external_id, file_path, file_key, role)
        if not upload_result.get("ok"):
            raise RuntimeError(f"Не удалось загрузить вложение {file_name} в WordPress: {upload_result}")

        attachment_url = upload_result.get("attachment_url")
        if attachment_url:
            attachment_urls[file_key] = attachment_url

    return expected_keys, attachment_urls


def upload_group_documents(wp_client, post_id, external_id, documents):
    attachments = []
    for doc in documents:
        file_name, file_path = resolve_document_path(doc)
        if file_name and file_path:
            attachments.append({"file_name": file_name, "file_path": file_path, "file_key": file_name, "role": "pdf"})
    expected_keys, _ = upload_group_attachments(wp_client, post_id, external_id, attachments)
    if expected_keys:
        wp_client.finalize_attachments(post_id, external_id, expected_keys)


def sync_mnn_group_to_wordpress(wp_client, mnn_name, medicines, sync_attachments=False):
    if not wp_client or not wp_client.enabled:
        return None

    medicines = list(medicines or [])
    if not medicines:
        return None

    title = mnn_title(mnn_name)
    external_id = mnn_external_id(mnn_name)
    trade_names = []
    med_ids = []
    for item in medicines:
        data = item["data"]
        med_ids.append(str(item["med_id"]))
        trade_name = (data.get("trade_name") or "").strip()
        if trade_name:
            trade_names.append(trade_name)

    prepared_medicines = prepare_medicines_for_content(medicines)
    content = build_mnn_group_content(mnn_name, prepared_medicines)
    payload_content = replace_attachment_placeholders(content, {})

    payload = {
        "title": title,
        "content": payload_content,
        "external_id": external_id,
        "status": WP_POST_STATUS,
        "article_type_slug": WP_ARTICLE_TYPE_SLUG,
        "article_type_name": WP_ARTICLE_TYPE_NAME,
        "mnn_names_or_slugs": [title],
        "author_id": WP_AUTHOR_ID,
        "post_excerpt": f"ОХЛП: {title}",
        "meta_extra": {
            "medvise_ohlp_mnn": title,
            "medvise_ohlp_id_folders": ",".join(med_ids),
            "medvise_ohlp_trade_names": "\n".join(sorted(set(trade_names))),
            "medvise_ohlp_instruction_count": str(len(medicines)),
            "medvise_ohlp_synced_at": time.strftime("%Y-%m-%dT%H:%M:%S"),
        },
    }

    try:
        result = wp_client.upsert_post(payload)
        if not result.get("ok"):
            raise RuntimeError(f"WordPress upsert вернул ошибку: {result}")

        post_id = int(result.get("post_id") or 0)
        print(f"WordPress draft: {external_id} ({title}) -> post_id={post_id}, инструкций={len(medicines)}")

        if sync_attachments and post_id > 0:
            attachments = collect_group_attachments(prepared_medicines)
            expected_keys, attachment_urls = upload_group_attachments(wp_client, post_id, external_id, attachments)
            content_with_urls = replace_attachment_placeholders(content, attachment_urls)
            if content_with_urls != payload_content:
                payload["content"] = content_with_urls
                result = wp_client.upsert_post(payload)
                if not result.get("ok"):
                    raise RuntimeError(f"WordPress upsert после загрузки медиа вернул ошибку: {result}")
            if expected_keys:
                wp_client.finalize_attachments(post_id, external_id, expected_keys)
        return post_id
    except Exception as exc:
        raise RuntimeError(f"Ошибка WordPress sync для {external_id}: {exc}") from exc


def build_medicine_content(med_id, data, documents):
    pdf_content_html = build_pdf_documents_content_html(documents)

    return (
        "<h2>Общая характеристика лекарственного препарата</h2>\n"
        f"<table><tbody>{medicine_field_rows(med_id, data)}</tbody></table>\n"
        f"{pdf_content_html}\n"
        '<p>Источник: <a href="https://lk.regmed.ru/Register/EAEU_SmPC">Реестр лекарственных средств ЕАЭС</a></p>'
    )


def sync_medicine_to_wordpress(wp_client, med_id, data, documents=None, sync_attachments=False):
    if not wp_client or not wp_client.enabled:
        return None

    external_id = f"lk_{med_id}"
    documents = documents or []
    title = build_medicine_title(data)
    payload = {
        "title": title,
        "content": build_medicine_content(med_id, data, documents),
        "external_id": external_id,
        "status": WP_POST_STATUS,
        "article_type_slug": WP_ARTICLE_TYPE_SLUG,
        "article_type_name": WP_ARTICLE_TYPE_NAME,
        "mnn_names_or_slugs": [title] if title else [],
        "author_id": WP_AUTHOR_ID,
        "post_excerpt": "Общая характеристика лекарственного препарата",
        "meta_extra": {
            "medvise_ohlp_id_folder": str(med_id),
            "medvise_ohlp_mnn": title,
            "medvise_ohlp_inn": title,
            "medvise_ohlp_trade_name": data.get("trade_name", ""),
            "medvise_ohlp_form": data.get("form", ""),
            "medvise_ohlp_reg_number": data.get("reg_number", ""),
            "medvise_ohlp_reg_date": data.get("reg_date", ""),
            "medvise_ohlp_owner": data.get("owner", ""),
            "medvise_ohlp_country": data.get("country", ""),
            "medvise_ohlp_synced_at": time.strftime("%Y-%m-%dT%H:%M:%S"),
        },
    }

    try:
        result = wp_client.upsert_post(payload)
        if not result.get("ok"):
            raise RuntimeError(f"WordPress upsert вернул ошибку: {result}")

        post_id = int(result.get("post_id") or 0)
        print(f"WordPress draft: {external_id} -> post_id={post_id}")

        if not sync_attachments or not documents or post_id <= 0:
            return post_id

        upload_group_documents(wp_client, post_id, external_id, documents)
        return post_id
    except Exception as exc:
        raise RuntimeError(f"Ошибка WordPress sync для {external_id}: {exc}") from exc


# ----------------------------------------------------------------------
# Вспомогательные функции парсинга
# ----------------------------------------------------------------------
def extract_control_state(html, control_name):
    """
    Извлекает из скрипта с ASPx.createControl объект state для указанного контрола.
    Возвращает словарь с ключами: keys, callbackState, customOperationState,
    groupLevelState, selection, lastMultiSelectIndex, pageCount.
    Если контрол не найден, возвращает None.
    """
    soup = BeautifulSoup(html, 'html.parser')
    script_text = None
    # Ищем скрипт, где создаётся именно control_name
    for script in soup.find_all('script'):
        if script.string and f"ASPx.createControl(MVCxClientGridView,'{control_name}'" in script.string:
            script_text = script.string
            break
    if not script_text:
        return None

    # Извлекаем keys (массив)
    keys_match = re.search(r"'keys'\s*:\s*(\[[^\]]*\])", script_text)
    if not keys_match:
        raise ValueError(f"Не найден ключ 'keys' в скрипте для {control_name}")
    keys = json.loads(keys_match.group(1).replace("'", '"'))

    # callbackState
    callback_match = re.search(r"'callbackState'\s*:\s*'([^']*)'", script_text)
    callback_state = callback_match.group(1) if callback_match else ''

    # customOperationState
    custom_match = re.search(r"'customOperationState'\s*:\s*'([^']*)'", script_text)
    custom_state = custom_match.group(1) if custom_match else ''

    # groupLevelState
    group_match = re.search(r"'groupLevelState'\s*:\s*({[^}]*})", script_text)
    if group_match:
        group_state = json.loads(group_match.group(1).replace("'", '"'))
    else:
        group_state = {}

    # selection
    selection_match = re.search(r"'selection'\s*:\s*'([^']*)'", script_text)
    selection = selection_match.group(1) if selection_match else ''

    # lastMultiSelectIndex
    last_multi_match = re.search(r"'lastMultiSelectIndex'\s*:\s*(-?\d+)", script_text)
    last_multi = int(last_multi_match.group(1)) if last_multi_match else -1

    # pageCount (может отсутствовать для некоторых контролов)
    page_count_match = re.search(r"'pageCount'\s*:\s*(\d+)", script_text)
    page_count = int(page_count_match.group(1)) if page_count_match else None

    return {
        'keys': keys,
        'callbackState': callback_state,
        'customOperationState': custom_state,
        'groupLevelState': group_state,
        'selection': selection,
        'lastMultiSelectIndex': last_multi,
        'pageCount': page_count,
    }


def parse_table_html(html):
    """
    Извлекает из HTML таблицы (из ответа POST) данные о препаратах
    и список доступных ссылок (ID_FOLDER, ID_CARD_TYPE).
    Возвращает:
        medicines: list of dicts
        doc_links: list of (id_folder, card_type)
    """
    soup = BeautifulSoup(html, 'html.parser')
    rows = soup.find_all('tr', class_=re.compile(r'dxgvDataRow_MaterialCompact'))
    medicines = []
    doc_links = []

    for row in rows:
        cells = row.find_all('td')
        if len(cells) < 7:
            continue

        inn = cells[0].get_text(strip=True) if cells[0] else ''
        trade_name = cells[1].get_text(strip=True) if cells[1] else ''
        form = cells[2].get_text(strip=True) if cells[2] else ''
        reg_number = cells[3].get_text(strip=True) if cells[3] else ''
        reg_date = cells[4].get_text(strip=True) if cells[4] else ''
        owner = cells[5].get_text(strip=True) if cells[5] else ''
        country = cells[6].get_text(strip=True) if cells[6] else ''

        medicines.append({
            'inn': inn,
            'trade_name': trade_name,
            'form': form,
            'reg_number': reg_number,
            'reg_date': reg_date,
            'owner': owner,
            'country': country,
        })

        # Извлекаем ссылки из колонок 7 и 8 (если есть)
        if len(cells) > 7:
            col7_span = cells[7].find('span', onclick=re.compile(r'ShowDocList'))
            if col7_span:
                onclick = col7_span.get('onclick', '')
                match = re.search(r'ShowDocList\((\d+),\s*(\d+)\)', onclick)
                if match:
                    id_folder = normalize_id_folder(match.group(1))
                    card_type = int(match.group(2))
                    doc_links.append((id_folder, card_type))

    return medicines, doc_links


def extract_json_from_dx_response(text):
    """Извлекает JSON из ответа вида /*DX*/({...})/*DXHTML*/"""
    match = re.search(r'/\*DX\*/\((.*)\)/\*DXHTML\*/', text, re.DOTALL)
    if not match:
        raise ValueError("Не найден DX JSON в ответе")
    json_str = match.group(1)
    try:
        return json.loads(json_str)
    except json.JSONDecodeError as exc:
        try:
            return ast.literal_eval(json_str)
        except (ValueError, SyntaxError) as literal_exc:
            snippet = re.sub(r"\s+", " ", text[:500]).strip()
            raise ValueError(
                f"Не удалось разобрать DX JSON/JS: {exc}; {literal_exc}. "
                f"Фрагмент ответа: {snippet}"
            ) from literal_exc


def extract_html_from_dx_response(text):
    marker = ")/*DXHTML*/"
    marker_pos = text.find(marker)
    if marker_pos == -1:
        return ""
    return text[marker_pos + len(marker):].strip()


def get_captcha_key(session):
    """Получает ключ капчи."""
    resp = session.get("https://lk.regmed.ru/Register/GetCaptchaKey", timeout=HTTP_TIMEOUT)
    resp.raise_for_status()
    data = resp.json()
    return data.get("captchaKey")


def download_captcha_image(session, key, save_dir, counter):
    """Загружает картинку капчи и сохраняет в папку."""
    url = f"https://lk.regmed.ru/Register/GenerateCaptcha?key={key}"
    resp = session.get(url, timeout=HTTP_TIMEOUT)
    resp.raise_for_status()
    os.makedirs(save_dir, exist_ok=True)
    filename = f"{counter}.jpg"
    path = os.path.join(save_dir, filename)
    with open(path, 'wb') as f:
        f.write(resp.content)
    return path


def solve_captcha_with_anticaptcha(image_path, api_key):
    """
    Отправляет изображение в anti-captcha.com и возвращает распознанный текст.
    Используется API ImageToTextTask.
    """
    import base64
    import time

    with open(image_path, 'rb') as f:
        img_data = base64.b64encode(f.read()).decode('utf-8')

    # Создаём задачу
    create_task_url = "https://api.anti-captcha.com/createTask"
    payload = {
        "clientKey": api_key,
        "task": {
            "type": "ImageToTextTask",
            "body": img_data
        }
    }
    resp = requests.post(create_task_url, json=payload, timeout=ANTICAPTCHA_REQUEST_TIMEOUT)
    resp.raise_for_status()
    task_data = resp.json()
    if task_data.get('errorId'):
        raise Exception(f"Anti-captcha error: {task_data.get('errorDescription')}")
    task_id = task_data['taskId']

    # Ждём решения
    get_result_url = "https://api.anti-captcha.com/getTaskResult"
    for _ in range(30):  # максимум 30 попыток
        time.sleep(2)
        resp = requests.post(
            get_result_url,
            json={"clientKey": api_key, "taskId": task_id},
            timeout=ANTICAPTCHA_REQUEST_TIMEOUT,
        )
        result = resp.json()
        if result['status'] == 'ready':
            return result['solution']['text']
    raise Exception("Anti-captcha не вернул результат за отведённое время")


def get_documents_for_medicine(session, id_folder, card_type, captcha_key, captcha_text, callback_state):
    """
    Отправляет запрос на получение списка документов.
    Возвращает HTML (строку) или None при ошибке капчи.
    """
    # Статическая часть __DXCallbackArgument (как в примере)
    dx_callback_arg = "c0:KV|2;[];CT|2;{};GB|35;14|CUSTOMCALLBACK15|[object Object];"
    # Формируем JSON для gvEAEU_SmPCListDoc
    gv_json = {
        "lastMultiSelectIndex": -1,
        "keys": [],
        "callbackState": callback_state,
        "groupLevelState": {},
        "selection": "",
        "toolbar": "{}"
    }
    data = {
        "DXCallbackName": "gvEAEU_SmPCListDoc",
        "__DXCallbackArgument": dx_callback_arg,
        "gvEAEU_SmPCListDoc": json.dumps(gv_json, separators=(',', ':')),
        "DXMVCEditorsValues": '{"txtSearch":null}',
        "ID_FOLDER": str(id_folder),
        "ID_CARD_TYPE": str(card_type),
        "captchaValue": captcha_text,
        "KeyCaptcha": captcha_key,
    }
    resp = session.post(
        "https://lk.regmed.ru/Register/gvEAEU_SmPCListDoc",
        data=data,
        headers=POST_HEADERS,
        timeout=HTTP_TIMEOUT,
    )
    resp.raise_for_status()
    # Проверяем, не вернулась ли ошибка капчи
    if "Неверная капча!" in resp.text:
        return None
    # Если вернулся редирект на страницу ошибки, тоже считаем ошибкой
    if "redirect" in resp.text and "/Home/ErrorPage" in resp.text:
        return None
    # Извлекаем JSON и HTML из ответа
    try:
        # Ищем позицию "/*DX*/({" и "})/*DXHTML*/"
        start = resp.text.find("/*DX*/({")
        if start == -1:
            return None
        # Ищем конец JSON: "})/*DXHTML*/"
        end = resp.text.find("})/*DXHTML*/", start)
        if end == -1:
            return None
        json_str = resp.text[start + len("/*DX*/({") - 1: end + 1]  # включая фигурные скобки
        # Убираем возможные лишние пробелы
        json_str = json_str.strip().replace("'", '"')
        json_data = json.loads(json_str)
        # HTML находится после "})/*DXHTML*/"
        html_start = end + len("})/*DXHTML*/")
        html_content = resp.text[html_start:].strip()
        # Если в JSON есть html, но он пустой, используем извлеченный
        if json_data.get('result', {}).get('html') == '<%html%>' and html_content:
            return html_content
        # Иначе возвращаем то, что в JSON
        return json_data['result'].get('html')
    except Exception as e:
        print(f"Ошибка при парсинге ответа: {e}")
        return None


def extract_tokens_from_doc_html(html):
    """
    Из HTML ответа извлекает все токены из span с title='Загрузить документ'.
    Возвращает список токенов.
    """
    soup = BeautifulSoup(html, 'html.parser')
    spans = soup.find_all('span', title='Загрузить документ')
    tokens = []
    for span in spans:
        onclick = span.get('onclick', '')
        match = re.search(r"fnLoadDocFilefromDA\(`([^`]+)`\)", onclick)
        if match:
            tokens.append(match.group(1))
    return tokens


def download_pdf(session, token, save_dir, file_name):
    """Скачивает PDF по токену и сохраняет."""
    url = f"https://lk.regmed.ru/Register/GetPDFfromDAToken?Token={token}"
    # url = f'https://lk.regmed.ru/Register/ViewPDFfromDAToken?Token={token}'
    resp = session.get(url, headers=PDF_HEADERS, timeout=HTTP_TIMEOUT)
    resp.raise_for_status()
    content_type = resp.headers.get("Content-Type", "")
    if "pdf" not in content_type.lower() and not resp.content.startswith(b"%PDF"):
        raise RuntimeError("Ответ на скачивание документа не похож на PDF.")
    os.makedirs(save_dir, exist_ok=True)
    path = os.path.join(save_dir, file_name)
    with open(path, 'wb') as f:
        f.write(resp.content)
    return path


def process_documents_for_medicine(session, wp_client, id_folder, card_type, medicine_data, list_doc_state):
    _ = wp_client
    downloaded_documents = list_downloaded_documents(id_folder)
    if downloaded_documents:
        save_result(LOCAL_DB_PATH, id_folder, card_type, medicine_data, downloaded_documents)
        print(f"PDF для {id_folder} уже есть локально, запись обновлена в локальной базе.")
        return

    last_error = None
    attempt = 0
    while True:
        attempt += 1
        img_path = None
        try:
            captcha_key = get_captcha_key(session)
            captcha_dir = CAPTCHA_IMAGES_DIR
            os.makedirs(captcha_dir, exist_ok=True)
            next_num = get_next_captcha_image_number(captcha_dir)
            img_path = download_captcha_image(session, captcha_key, captcha_dir, next_num)

            print(f"Решение капчи для {id_folder} (тип {card_type}), попытка {attempt}...")
            captcha_text = solve_captcha_with_anticaptcha(img_path, ANTICAPTCHA_API_KEY)
            print(f"Распознанный текст: {captcha_text}")

            doc_html = get_documents_for_medicine(
                session,
                id_folder,
                card_type,
                captcha_key,
                captcha_text,
                list_doc_state['callbackState'],
            )
        except Exception as exc:
            last_error = exc
            print(f"Ошибка при получении документов для {id_folder}: {exc}")
            doc_html = None

        if doc_html is None:
            try:
                if img_path:
                    os.remove(img_path)
            except OSError:
                pass
            detail = f": {last_error}" if last_error else ""
            print(
                f"Капча неверна или документы для {id_folder} не получены{detail}. "
                "Повторяем ту же запись."
            )
            time.sleep(DELAY_BETWEEN_PAGES)
            continue

        tokens = extract_tokens_from_doc_html(doc_html)
        if not tokens:
            print(
                f"Не удалось получить ссылку на скачивание PDF для {id_folder} "
                f"(тип {card_type}), запись пропущена."
            )
            return

        print(f"Найдено {len(tokens)} документов для {id_folder} (тип {card_type})")
        for i, token in enumerate(tokens):
            file_name = f"{id_folder}_{card_type}_{i + 1}.pdf"
            file_path = os.path.join(PDF_DIR, file_name)
            if os.path.exists(file_path):
                print(f"PDF {file_name} уже загружен локально, пропускаем скачивание.")
                continue
            try:
                pdf_path = download_pdf(session, token, PDF_DIR, file_name)
            except Exception as exc:
                print(
                    f"Не удалось скачать PDF для {id_folder} (тип {card_type}), "
                    f"файл {file_name} пропущен: {exc}"
                )
                continue
            print(f"Скачан {pdf_path}")
            time.sleep(DELAY_BETWEEN_PDF)

        documents = list_downloaded_documents(id_folder)
        if not documents:
            print(f"PDF для {id_folder} не сохранен локально, запись пропущена.")
            return
        save_result(LOCAL_DB_PATH, id_folder, card_type, medicine_data, documents)
        print(f"Сохранено в локальную базу: {id_folder} ({len(documents)} PDF).")
        return


def create_parser_session():
    session = requests.Session()
    session.headers.update({'User-Agent': USER_AGENT})
    return session


def load_initial_context(session, announce=True):
    if announce:
        print("Загрузка начальной страницы...")
    resp = session.get("https://lk.regmed.ru/Register/EAEU_SmPC", timeout=HTTP_TIMEOUT)
    resp.raise_for_status()
    initial_html = resp.text

    main_state = extract_control_state(initial_html, 'gvEAEU_SmPC')
    if main_state is None:
        raise ValueError("Не удалось извлечь состояние для gvEAEU_SmPC")

    list_doc_state = extract_control_state(initial_html, 'gvEAEU_SmPCListDoc')
    if list_doc_state is None:
        raise ValueError("Не удалось извлечь состояние для gvEAEU_SmPCListDoc")

    page_count = main_state['pageCount']
    return initial_html, main_state, list_doc_state, page_count


def build_grid_page_payload(current_state, page_num):
    dx_callback_arg = (
        f"c0:KV|251;{json.dumps(current_state['keys'], separators=(',', ':'))};"
        f"CT|2;{{}};GB|20;12|PAGERONCLICK3|PN{page_num};"
    )
    gv_state = {
        "keys": current_state['keys'],
        "lastMultiSelectIndex": current_state.get('lastMultiSelectIndex', -1),
        "callbackState": current_state.get('callbackState', ''),
        "groupLevelState": current_state.get('groupLevelState', {}),
        "customOperationState": current_state.get('customOperationState', ''),
        "selection": current_state.get('selection', ''),
        "toolbar": "{}",
        "contextMenu": None,
    }
    return {
        "DXCallbackName": "gvEAEU_SmPC",
        "__DXCallbackArgument": dx_callback_arg,
        "gvEAEU_SmPC": json.dumps(gv_state, separators=(',', ':')),
        "DXMVCEditorsValues": '{"txtSearch":null}',
    }


def request_grid_page(session, current_state, page_num):
    payload = build_grid_page_payload(current_state, page_num)
    resp = session.post(
        "https://lk.regmed.ru/Register/gvEAEU_SmPC",
        data=payload,
        headers=POST_HEADERS,
        timeout=HTTP_TIMEOUT,
    )
    resp.raise_for_status()
    json_data = extract_json_from_dx_response(resp.text)
    result = json_data.get('result') or {}
    new_state = result.get('stateObject')
    table_html = result.get('html')
    if table_html == '<%html%>':
        table_html = extract_html_from_dx_response(resp.text) or table_html
    if not isinstance(new_state, dict) or table_html is None:
        raise ValueError("DX JSON не содержит stateObject/html для таблицы препаратов")
    return new_state, table_html


def restore_context_before_page(page_num):
    session = create_parser_session()
    initial_html, current_state, list_doc_state, page_count = load_initial_context(session, announce=False)
    _ = initial_html
    for replay_page in range(2, page_num):
        print(f"Восстановление состояния: переход к странице {replay_page}...")
        current_state, _ = request_grid_page(session, current_state, replay_page)
        time.sleep(DELAY_BETWEEN_PAGES)
    return session, current_state, list_doc_state, page_count


def load_grid_page_with_recovery(session, current_state, list_doc_state, page_count, page_num):
    attempt = 0
    refresh_after = max(1, PAGE_RETRY_REFRESH_AFTER)
    while True:
        attempt += 1
        try:
            new_state, table_html = request_grid_page(session, current_state, page_num)
            return session, new_state, table_html, list_doc_state, page_count
        except Exception as exc:
            print(f"Не удалось загрузить страницу {page_num}, попытка {attempt}: {exc}")
            if attempt % refresh_after == 0:
                try:
                    print("Обновляем сессию и восстанавливаем состояние таблицы...")
                    session, current_state, list_doc_state, page_count = restore_context_before_page(page_num)
                except Exception as restore_exc:
                    print(f"Не удалось восстановить состояние таблицы: {restore_exc}")
            time.sleep(PAGE_RETRY_DELAY)


# ----------------------------------------------------------------------
# Основная логика парсера
# ----------------------------------------------------------------------
def main():
    # Инициализация
    if not ANTICAPTCHA_API_KEY:
        raise ValueError("Не задан ANTICAPTCHA_API_KEY. Укажите переменную окружения ANTICAPTCHA_API_KEY.")
    init_db(LOCAL_DB_PATH)
    session = create_parser_session()
    wp_client = None

    # 1. Загружаем начальную страницу
    initial_html, main_state, list_doc_state, page_count = load_initial_context(session)
    print(f"Всего страниц: {page_count}")
    if MAX_ITEMS > 0:
        print(f"Лимит запуска: {MAX_ITEMS} записей.")
    if SKIP_DOCUMENTS:
        print("Обработка PDF-документов отключена для этого запуска.")

    print(f"Получен callbackState для списка документов: {list_doc_state['callbackState'][:50]}...")

    processed_count = 0

    # Для первой страницы извлекаем таблицу из HTML начальной страницы
    soup = BeautifulSoup(initial_html, 'html.parser')
    table = soup.find('table', id='gvEAEU_SmPC_DXMainTable')
    if table:
        medicines_data, doc_links = parse_table_html(str(table))
        # Сохраняем препараты, используя keys из main_state (они идут в том же порядке)
        medicines_by_id = {}
        for idx, med_data in enumerate(medicines_data):
            if reached_items_limit(processed_count):
                break
            id_folder = normalize_id_folder(main_state['keys'][idx])
            medicines_by_id[id_folder] = med_data
            processed_count += 1
        # Обрабатываем ссылки на документы (если есть)
        if SKIP_DOCUMENTS:
            print("PDF-документы пропущены.")
        else:
            for id_folder, card_type in doc_links:
                if id_folder not in medicines_by_id:
                    continue
                process_documents_for_medicine(
                    session,
                    wp_client,
                    id_folder,
                    card_type,
                    medicines_by_id[id_folder],
                    list_doc_state,
                )
    else:
        print("Не найдена таблица на начальной странице")

    if reached_items_limit(processed_count):
        print(f"\nДостигнут лимит {MAX_ITEMS} записей, парсер остановлен.")
        print("\nПарсинг завершён.")
        return

    # 2. Цикл по оставшимся страницам
    current_state = main_state
    for page_num in range(2, page_count + 1):
        print(f"\n--- Загрузка страницы {page_num} ---")
        session, new_state, table_html, list_doc_state, page_count = load_grid_page_with_recovery(
            session,
            current_state,
            list_doc_state,
            page_count,
            page_num,
        )
        # Парсим таблицу
        medicines_data, doc_links = parse_table_html(table_html)
        # Сохраняем препараты (keys в new_state соответствуют строкам)
        keys = new_state['keys']
        medicines_by_id = {}
        for idx, med_data in enumerate(medicines_data):
            if reached_items_limit(processed_count):
                break
            id_folder = normalize_id_folder(keys[idx])
            medicines_by_id[id_folder] = med_data
            processed_count += 1
        # Обрабатываем документы (аналогично первой странице)
        if SKIP_DOCUMENTS:
            print("PDF-документы пропущены.")
        else:
            for id_folder, card_type in doc_links:
                if id_folder not in medicines_by_id:
                    continue
                process_documents_for_medicine(
                    session,
                    wp_client,
                    id_folder,
                    card_type,
                    medicines_by_id[id_folder],
                    list_doc_state,
                )

        # Обновляем current_state для следующей итерации
        current_state = new_state
        if reached_items_limit(processed_count):
            print(f"\nДостигнут лимит {MAX_ITEMS} записей, парсер остановлен.")
            break
        time.sleep(DELAY_BETWEEN_PAGES)

    print("\nПарсинг завершён.")


if __name__ == "__main__":
    main()
