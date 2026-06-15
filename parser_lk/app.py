import importlib
import os
import re
import signal
import subprocess
import sys
import threading
import time
from pathlib import Path

from flask import Flask, abort, redirect, request, send_from_directory, url_for
from markupsafe import escape

from storage import clear_results, delete_result, init_db, list_results

BASE_DIR = Path(__file__).resolve().parent
ENV_PATH = BASE_DIR / ".env"
DATA_DIR = BASE_DIR / "data"
LOCAL_DB_PATH = os.getenv("LOCAL_DB_PATH", str(DATA_DIR / "parser_lk.db"))
LOG_PATH = os.getenv("LOG_PATH", str(DATA_DIR / "parser_lk.log"))
PDF_DIR = Path(os.getenv("PDF_DIR", "pdf_files"))
if not PDF_DIR.is_absolute():
    PDF_DIR = BASE_DIR / PDF_DIR
CAPTCHA_IMAGES_DIR = Path(os.getenv("CAPTCHA_IMAGES_DIR", "captcha_images"))
if not CAPTCHA_IMAGES_DIR.is_absolute():
    CAPTCHA_IMAGES_DIR = BASE_DIR / CAPTCHA_IMAGES_DIR

SETTINGS_KEYS = [
    ("ANTICAPTCHA_API_KEY", "Ключ AntiCaptcha", "text"),
    ("DELAY_BETWEEN_PAGES", "Задержка между страницами", "text"),
    ("DELAY_BETWEEN_PDF", "Задержка между PDF", "text"),
    ("WP_SYNC_ENABLED", "Синхронизация с WordPress", "text"),
    ("WP_BASE_URL", "Адрес WordPress", "text"),
    ("WP_USERNAME", "Пользователь WordPress", "text"),
    ("WP_PASSWORD", "Пароль WordPress", "password"),
    ("WP_VERIFY_SSL", "Проверять SSL WordPress", "text"),
    ("WP_POST_TYPE", "Тип записи WordPress", "text"),
]
SETTINGS_GROUPS = [
    ("Параметры парсера", {"ANTICAPTCHA_API_KEY", "DELAY_BETWEEN_PAGES", "DELAY_BETWEEN_PDF"}),
    ("Подключение WordPress", {"WP_SYNC_ENABLED", "WP_BASE_URL", "WP_USERNAME", "WP_PASSWORD", "WP_VERIFY_SSL", "WP_POST_TYPE"}),
]
SECRET_KEYS = {"WP_PASSWORD"}
RESULTS_PAGE_SIZE = 25

app = Flask(__name__)
parser_process = None
process_lock = threading.Lock()


class PrefixMiddleware:
    def __init__(self, app):
        self.app = app

    def __call__(self, environ, start_response):
        script_name = environ.get("HTTP_X_SCRIPT_NAME", "")
        if script_name:
            environ["SCRIPT_NAME"] = script_name.rstrip("/")
        return self.app(environ, start_response)


app.wsgi_app = PrefixMiddleware(app.wsgi_app)


def ensure_paths():
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    PDF_DIR.mkdir(parents=True, exist_ok=True)
    CAPTCHA_IMAGES_DIR.mkdir(parents=True, exist_ok=True)
    init_db(LOCAL_DB_PATH)


def read_env_lines():
    if not ENV_PATH.exists():
        return []
    return ENV_PATH.read_text(encoding="utf-8").splitlines(keepends=True)


def parse_env_file():
    values = {}
    for line in read_env_lines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#") or "=" not in stripped:
            continue
        key, value = stripped.split("=", 1)
        key = key.strip()
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {'"', "'"}:
            value = value[1:-1]
        values[key] = value
    return values


def sanitize_env_value(value):
    return str(value or "").replace("\r", "").replace("\n", "").strip()


def write_env_values(new_values):
    lines = read_env_lines()
    seen = set()
    result = []
    key_re = re.compile(r"^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=")
    for line in lines:
        match = key_re.match(line)
        if match and match.group(1) in new_values:
            key = match.group(1)
            value = sanitize_env_value(new_values[key])
            result.append(f"{key}={value}\n")
            seen.add(key)
        else:
            result.append(line if line.endswith("\n") else f"{line}\n")
    if result and result[-1].strip():
        result.append("\n")
    for key, _, _ in SETTINGS_KEYS:
        if key not in seen:
            result.append(f"{key}={sanitize_env_value(new_values.get(key, ''))}\n")
    ENV_PATH.write_text("".join(result), encoding="utf-8")


def build_runtime_env():
    env = os.environ.copy()
    env.update(parse_env_file())
    env["LOCAL_DB_PATH"] = LOCAL_DB_PATH
    env["LOG_PATH"] = LOG_PATH
    env["PDF_DIR"] = str(PDF_DIR)
    env["CAPTCHA_IMAGES_DIR"] = str(CAPTCHA_IMAGES_DIR)
    env["PYTHONUNBUFFERED"] = "1"
    return env


def load_parser_main_module():
    runtime_env = build_runtime_env()
    runtime_env["WP_SYNC_ENABLED"] = "true"
    runtime_env["WP_POST_STATUS"] = "draft"
    runtime_env["WP_POST_TYPE"] = runtime_env.get("WP_POST_TYPE") or "substance"
    runtime_env["WP_ARTICLE_TYPE_NAME"] = "ОХЛП"
    if not runtime_env.get("WP_ARTICLE_TYPE_SLUG"):
        runtime_env["WP_ARTICLE_TYPE_SLUG"] = "ohlp"
    os.environ.update(runtime_env)
    parser_main = importlib.import_module("main")
    return importlib.reload(parser_main)


def result_medicine_data(row):
    return {
        "inn": row.get("inn", ""),
        "trade_name": row.get("trade_name", ""),
        "form": row.get("form", ""),
        "reg_number": row.get("reg_number", ""),
        "reg_date": row.get("reg_date", ""),
        "owner": row.get("owner", ""),
        "country": row.get("country", ""),
    }


def result_documents(row):
    documents = []
    for doc in row.get("documents") or []:
        file_name = os.path.basename(doc.get("file_name") or "")
        if not file_name:
            continue

        file_path = doc.get("file_path") or ""
        if not file_path:
            file_path = str(PDF_DIR / file_name)
        elif not os.path.isabs(file_path):
            candidate = BASE_DIR / file_path
            file_path = str(candidate if candidate.exists() else PDF_DIR / file_name)

        documents.append({"file_name": file_name, "file_path": file_path})
    return documents


def export_results_to_wordpress():
    ensure_paths()
    rows = list_results(LOCAL_DB_PATH)
    if not rows:
        return {"exported": 0, "errors": []}

    parser_main = load_parser_main_module()
    wp_client = parser_main.WordPressClient()
    if not wp_client.enabled:
        raise RuntimeError("WordPress не настроен: заполните WP_BASE_URL, WP_USERNAME и WP_PASSWORD.")

    grouped_rows = {}
    for row in rows:
        inn = parser_main.normalize_mnn_name(row.get("inn", ""))
        group_key = parser_main.mnn_group_key(inn or row.get("id_folder") or row.get("id"))
        grouped_rows.setdefault(group_key, {"inn": inn, "rows": []})["rows"].append(row)

    exported = 0
    errors = []
    for group in grouped_rows.values():
        group_rows = group["rows"]
        try:
            medicines = []
            for row in group_rows:
                med_id = str(row.get("id_folder") or "").strip()
                if not med_id:
                    raise RuntimeError("у записи не заполнен ID_FOLDER")
                medicines.append(
                    {
                        "med_id": med_id,
                        "data": result_medicine_data(row),
                        "documents": result_documents(row),
                    }
                )

            post_id = parser_main.sync_mnn_group_to_wordpress(
                wp_client,
                group["inn"] or medicines[0]["data"].get("inn") or medicines[0]["med_id"],
                medicines,
                sync_attachments=True,
            )
            if not post_id:
                raise RuntimeError("WordPress не вернул ID записи")

            for row in group_rows:
                deleted = delete_result(LOCAL_DB_PATH, row["id"])
                cleanup_files(deleted)
            exported += len(group_rows)
        except Exception as exc:
            title = group["inn"] or group_rows[0].get("trade_name") or group_rows[0].get("id_folder") or group_rows[0].get("id")
            errors.append(f"{title}: {exc}")

    return {"exported": exported, "errors": errors}


def parser_status():
    global parser_process
    with process_lock:
        if parser_process is None:
            return {"running": False, "returncode": None}
        code = parser_process.poll()
        return {"running": code is None, "returncode": code}


def start_parser():
    global parser_process
    ensure_paths()
    with process_lock:
        if parser_process is not None and parser_process.poll() is None:
            return False
        log_handle = open(LOG_PATH, "ab", buffering=0)
        header = f"\n--- Запуск parser_lk {time.strftime('%Y-%m-%d %H:%M:%S')} ---\n".encode("utf-8")
        log_handle.write(header)
        parser_process = subprocess.Popen(
            [sys.executable, "main.py"],
            cwd=str(BASE_DIR),
            env=build_runtime_env(),
            stdout=log_handle,
            stderr=subprocess.STDOUT,
            start_new_session=True,
        )
        return True


def stop_parser():
    global parser_process
    with process_lock:
        if parser_process is None or parser_process.poll() is not None:
            return False
        try:
            os.killpg(os.getpgid(parser_process.pid), signal.SIGTERM)
        except Exception:
            parser_process.terminate()
        return True


def log_tail(limit=12000):
    path = Path(LOG_PATH)
    if not path.exists():
        return ""
    with path.open("rb") as handle:
        handle.seek(0, os.SEEK_END)
        size = handle.tell()
        handle.seek(max(0, size - limit))
        return handle.read().decode("utf-8", errors="replace")


def cleanup_files(row):
    if not row:
        return
    for doc in row.get("documents") or []:
        name = os.path.basename(doc.get("file_name") or "")
        if not name:
            continue
        path = PDF_DIR / name
        try:
            if path.exists():
                path.unlink()
        except OSError:
            pass


def status_badge():
    status = parser_status()
    if status["running"]:
        return '<span class="badge badge-run">Работает</span>'
    code = status["returncode"]
    if code is None:
        return '<span class="badge">Остановлен</span>'
    if code == 0:
        return '<span class="badge badge-ok">Завершен</span>'
    return f'<span class="badge badge-error">Ошибка {code}</span>'


def render_page(title, active, body):
    links = [
        ("control_page", "⚙️", "Управление парсером", "control"),
        ("settings_page", "🔐", "Настройки", "settings"),
        ("results_page", "📋", "Результаты", "results"),
    ]
    nav = "".join(
        f'<a class="{ "active" if key == active else "" }" href="{url_for(endpoint)}"><span class="icon" aria-hidden="true">{icon}</span>{label}</a>'
        for endpoint, icon, label, key in links
    )
    html = """
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>__TITLE__</title>
  <style>
    :root{color-scheme:dark;--bg:#141724;--panel:#1b2030;--panel2:#22283a;--line:#30384e;--text:#edf2ff;--muted:#aab5ce;--green:#21a86b;--blue:#3568e8;--red:#d84f4f;--amber:#d6a637}
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font:14px/1.45 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    header{position:fixed;left:0;top:0;bottom:0;width:260px;display:flex;align-items:stretch;flex-direction:column;gap:18px;padding:22px 18px;background:#232842;border-right:1px solid var(--line)}
    h1{font-size:22px;line-height:1.2;margin:0;font-weight:700;letter-spacing:0} h2{font-size:17px;margin:0 0 16px}
    nav{display:flex;flex-direction:column;gap:8px} nav a{display:flex;align-items:center;gap:8px;color:var(--muted);text-decoration:none;padding:10px 12px;border-radius:6px;background:transparent;border:1px solid transparent} nav a.active,nav a:hover{color:var(--text);background:var(--panel2);border-color:var(--line)} nav a .icon{width:18px;flex:0 0 18px;text-align:center}
    main{max-width:1240px;margin:0 auto 0 260px;padding:22px 18px 36px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:18px;margin-bottom:16px}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.results-toolbar,.log-toolbar{margin-bottom:20px}.actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .btn{border:0;border-radius:6px;padding:9px 13px;color:white;background:var(--blue);font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:36px}.btn:hover{filter:brightness(1.08)}.btn.green{background:var(--green)}.btn.red{background:var(--red)}.btn.ghost{background:var(--panel2);color:var(--text);border:1px solid var(--line)}.btn .icon{line-height:1}
    .badge{display:inline-flex;align-items:center;min-height:28px;padding:5px 10px;border-radius:999px;background:#394157;color:var(--text);font-weight:700}.badge-run{background:#166b49}.badge-ok{background:#204f7a}.badge-error{background:#793138}.muted{color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.settings-groups{display:flex;flex-direction:column;gap:18px}.settings-group{padding-top:18px;border-top:1px solid var(--line)}.settings-group:first-child{padding-top:0;border-top:0}.settings-group h3{font-size:14px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin:0 0 12px}.field{display:flex;flex-direction:column;gap:7px}.field label{color:var(--muted);font-weight:700}.field input{width:100%;border:1px solid var(--line);border-radius:6px;background:#111522;color:var(--text);padding:10px 11px;font:inherit}
    table{width:100%;border-collapse:collapse;background:var(--panel)}th,td{padding:12px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}th{color:var(--muted);font-size:13px;background:#242a3d}td a{color:#8fb3ff}.nowrap{white-space:nowrap}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:8px}.inn-link{color:#8fb3ff;text-decoration:underline;text-underline-offset:3px;font-weight:700}
    .pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:14px}.pagination-info{color:var(--muted)}.pagination-links{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.page-link,.page-gap{min-width:34px;min-height:34px;padding:7px 10px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center}.page-link{color:var(--text);text-decoration:none;background:var(--panel2);border:1px solid var(--line);font-weight:700}.page-link:hover,.page-link.active{background:var(--blue);border-color:var(--blue);text-decoration:none}.page-gap{color:var(--muted)}
    .modal{display:none;position:fixed;inset:0;z-index:20;align-items:center;justify-content:center;padding:24px;background:rgba(7,10,18,.72)}.modal:target{display:flex}.modal-panel{position:relative;width:min(720px,100%);max-height:calc(100vh - 48px);overflow:auto;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:20px;box-shadow:0 24px 70px rgba(0,0,0,.45)}.modal-close{position:absolute;right:14px;top:12px;width:34px;height:34px;border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--text);text-decoration:none;background:var(--panel2);border:1px solid var(--line);font-size:22px;line-height:1}.modal-title{margin:0 44px 6px 0}.modal-subtitle{margin:0;color:var(--muted)}.detail-grid{display:grid;grid-template-columns:180px minmax(0,1fr);gap:10px 14px;margin-top:18px}.detail-label{color:var(--muted);font-weight:700}.detail-value{min-width:0}.doc-list{margin:0;padding-left:18px}.doc-list li{margin:4px 0}
    pre{white-space:pre-wrap;word-break:break-word;margin:0;background:#101522;border:1px solid var(--line);border-radius:8px;padding:14px;max-height:430px;overflow:auto;color:#dce5ff}.notice{border-color:#2f6d52;background:#183628}.empty{padding:22px;color:var(--muted);text-align:center}
    form{margin:0}.inline{display:inline}@media(max-width:760px){header{position:static;width:auto;border-right:0;border-bottom:1px solid var(--line);padding:18px 14px}nav{flex-direction:row;flex-wrap:wrap}main{margin:0;padding:16px 12px}.grid,.detail-grid{grid-template-columns:1fr}th,td{padding:10px 8px}.btn{width:auto}.modal{padding:14px}.modal-panel{max-height:calc(100vh - 28px)}}
  </style>
</head>
<body>
  <header><h1>⚡ Парсер ОХЛП</h1><nav>__NAV__</nav></header>
  <main>__BODY__</main>
</body>
</html>
"""
    return (
        html.replace("__TITLE__", str(escape(title)))
        .replace("__NAV__", nav)
        .replace("__BODY__", body)
    )


@app.route("/")
def index():
    return redirect(url_for("control_page"))


@app.route("/control")
def control_page():
    ensure_paths()
    notice = request.args.get("notice", "")
    status = parser_status()
    if status["running"]:
        action = f'<form method="post" action="{url_for("control_stop")}"><button class="btn red"><span class="icon" aria-hidden="true">⏹</span>Остановить</button></form>'
    else:
        action = f'<form method="post" action="{url_for("control_start")}"><button class="btn green"><span class="icon" aria-hidden="true">▶</span>Запустить парсер</button></form>'
    notice_html = f'<div class="panel notice">{escape(notice)}</div>' if notice else ""
    body = f"""
    {notice_html}
    <section class="panel">
      <div class="topbar">
        <div><h2>Управление парсером</h2><div class="muted">Статус: {status_badge()}</div></div>
        <div class="actions">{action}</div>
      </div>
    </section>
    <section class="panel">
      <div class="topbar log-toolbar"><h2>Лог</h2><a class="btn ghost" href="{url_for('control_page')}"><span class="icon" aria-hidden="true">🔄</span>Обновить</a></div>
      <pre>{escape(log_tail())}</pre>
    </section>
    <section class="panel">
      <div class="topbar">
        <h2>Быстрые действия</h2>
        <form method="post" action="{url_for('export_wordpress_route')}" onsubmit="return confirm('Выгрузить все результаты в WordPress и удалить их из списка?')"><button class="btn green"><span class="icon" aria-hidden="true">⬆️</span>Выгрузить в WordPress</button></form>
      </div>
    </section>
    """
    return render_page("Парсер ОХЛП", "control", body)


@app.route("/control/start", methods=["POST"])
def control_start():
    started = start_parser()
    notice = "Парсер запущен." if started else "Парсер уже работает."
    return redirect(url_for("control_page", notice=notice))


@app.route("/control/stop", methods=["POST"])
def control_stop():
    stopped = stop_parser()
    notice = "Остановка отправлена." if stopped else "Парсер не был запущен из веб-интерфейса."
    return redirect(url_for("control_page", notice=notice))


@app.route("/control/export-wordpress", methods=["POST"])
def export_wordpress_route():
    try:
        result = export_results_to_wordpress()
        exported = result["exported"]
        errors = result["errors"]
        if exported == 0 and not errors:
            notice = "Нет результатов для выгрузки."
        elif errors:
            error_text = "; ".join(errors[:3])
            if len(errors) > 3:
                error_text += f"; еще ошибок: {len(errors) - 3}"
            notice = f"Выгружено в WordPress: {exported}. Ошибки: {error_text}"
        else:
            notice = f"Выгружено в WordPress: {exported}. Успешно выгруженные результаты удалены."
    except Exception as exc:
        notice = f"Ошибка выгрузки в WordPress: {exc}"
    return redirect(url_for("control_page", notice=notice))


@app.route("/settings", methods=["GET", "POST"])
def settings_page():
    ensure_paths()
    notice = ""
    values = parse_env_file()
    if request.method == "POST":
        posted = {key: request.form.get(key, "") for key, _, _ in SETTINGS_KEYS}
        for key in SECRET_KEYS:
            if not posted.get(key):
                posted[key] = values.get(key, "")
        write_env_values(posted)
        values = parse_env_file()
        notice = "Настройки сохранены."
    field_by_key = {}
    for key, label, input_type in SETTINGS_KEYS:
        value = values.get(key, "")
        display_value = "" if key in SECRET_KEYS else value
        placeholder = ' placeholder="Сохранено"' if key in SECRET_KEYS and value else ""
        field_by_key[key] = (
            f'<div class="field"><label for="{key}">{escape(label)}</label>'
            f'<input id="{key}" name="{key}" type="{input_type}" value="{escape(display_value)}"{placeholder} autocomplete="off"></div>'
        )
    groups = []
    grouped_keys = set()
    for title, keys in SETTINGS_GROUPS:
        group_fields = [field_by_key[key] for key, _, _ in SETTINGS_KEYS if key in keys]
        grouped_keys.update(keys)
        groups.append(
            f'<div class="settings-group"><h3>{escape(title)}</h3><div class="grid">{"".join(group_fields)}</div></div>'
        )
    extra_fields = [field for key, field in field_by_key.items() if key not in grouped_keys]
    if extra_fields:
        groups.append(
            f'<div class="settings-group"><h3>Прочее</h3><div class="grid">{"".join(extra_fields)}</div></div>'
        )
    notice_html = f'<div class="panel notice">{escape(notice)}</div>' if notice else ""
    body = f"""
    {notice_html}
    <section class="panel">
      <form method="post" action="{url_for('settings_page')}">
        <div class="topbar"><h2>Настройки</h2><button class="btn green"><span class="icon" aria-hidden="true">💾</span>Сохранить</button></div>
        <div class="settings-groups">{''.join(groups)}</div>
      </form>
    </section>
    """
    return render_page("Настройки", "settings", body)


@app.route("/results")
def results_page():
    ensure_paths()
    rows = list_results(LOCAL_DB_PATH)
    if rows:
        total_count = len(rows)
        total_pages = max(1, (total_count + RESULTS_PAGE_SIZE - 1) // RESULTS_PAGE_SIZE)
        page = request.args.get("page", 1, type=int) or 1
        page = min(max(page, 1), total_pages)
        start = (page - 1) * RESULTS_PAGE_SIZE
        end = start + RESULTS_PAGE_SIZE
        page_rows = rows[start:end]
        table_rows = []
        modals = []
        for row in page_rows:
            docs = row.get("documents") or []
            pdf_file = row.get("pdf_file") or (docs[0].get("file_name") if docs else "")
            pdf_link = '<span class="muted">Нет PDF</span>'
            if pdf_file:
                pdf_link = f'<a href="{url_for("serve_pdf", file_name=pdf_file)}" target="_blank" rel="noopener">{escape(pdf_file)}</a>'
            result_id = row["id"]
            modal_id = f"result-modal-{result_id}"
            inn_text = row.get("inn") or "Без МНН"
            inn_link = f'<a class="inn-link" href="#{modal_id}">{escape(inn_text)}</a>'
            detail_items = [
                ("МНН", row.get("inn") or "—"),
                ("Торговое наименование", row.get("trade_name") or "—"),
                ("Форма выпуска", row.get("form") or "—"),
                ("Номер РУ", row.get("reg_number") or "—"),
                ("Дата РУ", row.get("reg_date") or "—"),
                ("Владелец РУ", row.get("owner") or "—"),
                ("Время", row.get("created_at") or "—"),
            ]
            detail_html = "".join(
                f'<div class="detail-label">{escape(label)}</div><div class="detail-value">{escape(value)}</div>'
                for label, value in detail_items
            )
            document_items = []
            for doc in docs:
                file_name = os.path.basename(doc.get("file_name") or "")
                if file_name:
                    document_items.append(
                        f'<li><a href="{url_for("serve_pdf", file_name=file_name)}" target="_blank" rel="noopener">{escape(file_name)}</a></li>'
                    )
            documents_html = (
                f'<ul class="doc-list">{"".join(document_items)}</ul>'
                if document_items
                else '<span class="muted">Нет PDF</span>'
            )
            modals.append(
                f"""
                <div id="{modal_id}" class="modal" role="dialog" aria-modal="true" aria-labelledby="{modal_id}-title">
                  <div class="modal-panel">
                    <a class="modal-close" href="#" aria-label="Закрыть">×</a>
                    <h2 id="{modal_id}-title" class="modal-title">{escape(inn_text)}</h2>
                    <p class="modal-subtitle">{escape(row.get("trade_name") or "")}</p>
                    <div class="detail-grid">
                      {detail_html}
                      <div class="detail-label">Документы</div><div class="detail-value">{documents_html}</div>
                    </div>
                  </div>
                </div>
                """
            )
            table_rows.append(
                "<tr>"
                f"<td>{inn_link}</td>"
                f"<td>{escape(row.get('trade_name') or '')}</td>"
                f"<td class='nowrap'>{escape(row.get('created_at') or '')}</td>"
                f"<td>{pdf_link}</td>"
                f"<td class='nowrap'><form class='inline' method='post' action='{url_for('delete_result_route', result_id=result_id, page=page)}' onsubmit=\"return confirm('Удалить запись?')\"><button class='btn red'><span class='icon' aria-hidden='true'>🗑</span>Удалить</button></form></td>"
                "</tr>"
            )
        pagination = ""
        if total_pages > 1:
            page_numbers = {1, total_pages, page - 2, page - 1, page, page + 1, page + 2}
            page_numbers = sorted(number for number in page_numbers if 1 <= number <= total_pages)
            links = []
            previous_number = None
            if page > 1:
                links.append(f'<a class="page-link" href="{url_for("results_page", page=page - 1)}">‹</a>')
            for number in page_numbers:
                if previous_number is not None and number - previous_number > 1:
                    links.append('<span class="page-gap">…</span>')
                active_class = " active" if number == page else ""
                links.append(
                    f'<a class="page-link{active_class}" href="{url_for("results_page", page=number)}">{number}</a>'
                )
                previous_number = number
            if page < total_pages:
                links.append(f'<a class="page-link" href="{url_for("results_page", page=page + 1)}">›</a>')
            pagination = (
                '<div class="pagination">'
                f'<div class="pagination-info">Показаны {start + 1}–{min(end, total_count)} из {total_count}</div>'
                f'<div class="pagination-links">{"".join(links)}</div>'
                "</div>"
            )
        table = f"""
        <div class="table-wrap"><table>
          <thead><tr><th>МНН</th><th>Торговое наименование</th><th>Время</th><th>ОХЛП</th><th>Действия</th></tr></thead>
          <tbody>{''.join(table_rows)}</tbody>
        </table></div>
        {pagination}
        {''.join(modals)}
        """
    else:
        table = '<div class="empty">Нет данных</div>'
    body = f"""
    <section class="panel">
      <div class="topbar results-toolbar">
        <h2>Результаты</h2>
        <form method="post" action="{url_for('clear_results_route')}" onsubmit="return confirm('Очистить все результаты?')"><button class="btn red"><span class="icon" aria-hidden="true">🧹</span>Очистить</button></form>
      </div>
      {table}
    </section>
    """
    return render_page("Результаты", "results", body)


@app.route("/results/delete/<int:result_id>", methods=["POST"])
def delete_result_route(result_id):
    row = delete_result(LOCAL_DB_PATH, result_id)
    cleanup_files(row)
    page = request.args.get("page", type=int)
    if page and page > 1:
        return redirect(url_for("results_page", page=page))
    return redirect(url_for("results_page"))


@app.route("/results/clear", methods=["POST"])
def clear_results_route():
    rows = clear_results(LOCAL_DB_PATH)
    for row in rows:
        cleanup_files(row)
    return redirect(url_for("results_page"))


@app.route("/pdf/<path:file_name>")
def serve_pdf(file_name):
    safe_name = os.path.basename(file_name)
    path = PDF_DIR / safe_name
    if not path.exists():
        abort(404)
    return send_from_directory(PDF_DIR, safe_name, mimetype="application/pdf", as_attachment=False)


if __name__ == "__main__":
    ensure_paths()
    app.run(host="0.0.0.0", port=5000, debug=False, use_reloader=False)
