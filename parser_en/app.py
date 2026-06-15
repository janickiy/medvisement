"""
app.py — Flask веб-интерфейс для управления парсером libook.
"""

from flask import Flask, render_template_string, request, redirect, url_for, jsonify
from markupsafe import escape
import database as db_module
import scheduler as sched
from wp_export_routes import wp_export_bp, _build_full_html
from retry_routes import retry_bp

db_module.init_db()
app = Flask(__name__)


class PrefixMiddleware:
    """Позволяет открывать Flask-интерфейс за nginx-префиксом /parser-en."""

    def __init__(self, app):
        self.app = app

    def __call__(self, environ, start_response):
        script_name = environ.get("HTTP_X_SCRIPT_NAME", "").rstrip("/")
        if script_name:
            environ["SCRIPT_NAME"] = script_name
        return self.app(environ, start_response)


app.wsgi_app = PrefixMiddleware(app.wsgi_app)
app.register_blueprint(wp_export_bp)
app.register_blueprint(retry_bp)

# Флаг фонового сбора результатов поиска
COLLECT_RUNNING = False

# ══════════════════════════════════════════════════════════════════
#  HTML ШАБЛОНЫ
# ══════════════════════════════════════════════════════════════════

BASE_HTML = """
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Libook Scraper</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:#0f1117;color:#e2e8f0;min-height:100vh}
  a{color:#7c9ef8;text-decoration:none}
  a:hover{text-decoration:underline}

  .sidebar{position:fixed;top:0;left:0;height:100vh;width:220px;background:#1a1d27;
    border-right:1px solid #2d3148;display:flex;flex-direction:column;padding:20px 0}
  .sidebar h2{color:#7c9ef8;font-size:15px;padding:0 20px 20px;letter-spacing:.5px;border-bottom:1px solid #2d3148}
  .sidebar nav{padding-top:12px}
  .sidebar nav a{display:block;padding:10px 20px;color:#a0aec0;font-size:14px;transition:.2s}
  .sidebar nav a:hover,.sidebar nav a.active{background:#252840;color:#fff;text-decoration:none}
  .sidebar nav a .icon{margin-right:8px}

  .main{margin-left:220px;padding:30px}
  .page-title{font-size:22px;font-weight:600;margin-bottom:24px;color:#fff}

  .card{background:#1a1d27;border:1px solid #2d3148;border-radius:10px;padding:24px;margin-bottom:20px}
  .card h3{font-size:15px;color:#a0aec0;margin-bottom:16px;text-transform:uppercase;letter-spacing:.5px}

  table{width:100%;border-collapse:collapse;font-size:14px}
  th{background:#252840;color:#7c9ef8;padding:10px 14px;text-align:left;font-weight:500}
  td{padding:10px 14px;border-bottom:1px solid #252840;vertical-align:top;word-break:break-word}
  tr:last-child td{border-bottom:none}
  tr:hover td{background:#1e2235}

  .btn{display:inline-block;padding:7px 16px;border-radius:6px;font-size:13px;cursor:pointer;border:none;transition:.2s}
  .btn-primary{background:#4361ee;color:#fff}.btn-primary:hover{background:#3451d1}
  .btn-success{background:#2eb872;color:#fff}.btn-success:hover{background:#28a464}
  .btn-danger{background:#e53e3e;color:#fff}.btn-danger:hover{background:#c53030}
  .btn-warn{background:#d97706;color:#fff}.btn-warn:hover{background:#b45309}
  .btn-sm{padding:4px 10px;font-size:12px}

  .badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600}
  .badge-green{background:#1a472a;color:#68d391}
  .badge-red{background:#4a1515;color:#fc8181}
  .badge-blue{background:#1a2e6b;color:#90cdf4}
  .badge-gray{background:#2d3748;color:#a0aec0}

  form.inline{display:inline}
  .form-group{margin-bottom:14px}
  .form-group label{display:block;font-size:13px;color:#a0aec0;margin-bottom:5px}
  .form-group input,.form-group select{width:100%;padding:8px 12px;background:#252840;
    border:1px solid #3d4266;border-radius:6px;color:#e2e8f0;font-size:14px}
  .form-group input:focus,.form-group select:focus{outline:none;border-color:#7c9ef8}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
  .control-card{display:block}
  .control-top{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:14px}
  .control-status{min-width:118px}
  .control-status strong{display:inline-block}
  .control-selects{margin-bottom:0}
  .control-selects .form-group{margin-bottom:0}

  .status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px}
  .dot-green{background:#48bb78;box-shadow:0 0 6px #48bb78}
  .dot-red{background:#fc8181}
  .dot-gray{background:#a0aec0}

  .worker-row{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #252840;font-size:14px}
  .worker-row:last-child{border-bottom:none}
  .worker-login{color:#7c9ef8;font-weight:500;min-width:200px}
  .worker-status{color:#a0aec0;flex:1}

  .tabs{display:flex;border-bottom:2px solid #2d3148;margin-bottom:20px}
  .tab-btn{padding:10px 20px;background:none;border:none;color:#a0aec0;cursor:pointer;
    font-size:14px;border-bottom:2px solid transparent;margin-bottom:-2px;transition:.2s}
  .tab-btn.active{color:#7c9ef8;border-bottom-color:#7c9ef8}
  .tab-content{display:none}.tab-content.active{display:block}

  .article-frame{width:100%;height:70vh;border:none;border-radius:6px;background:#fff}
  .search-info{color:#a0aec0;font-size:13px;margin-bottom:16px}
  .progress-bar{background:#252840;border-radius:4px;height:6px;margin-top:4px}
  .progress-fill{height:100%;border-radius:4px;background:#4361ee;transition:.3s}

  .notice{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:14px}
  .notice-error{background:#4a1515;color:#fc8181;border:1px solid #742a2a}
  .notice-success{background:#1a472a;color:#68d391;border:1px solid #276749}

  #toast{position:fixed;bottom:24px;right:24px;background:#2eb872;color:#fff;
    padding:12px 20px;border-radius:8px;font-size:14px;display:none;z-index:999}
</style>
</head>
<body>
<div class="sidebar">
  <h2>⚡ Libook Scraper</h2>
  <nav>
    <a href="/accounts" class="{{ 'active' if active=='accounts' else '' }}"><span class="icon">👤</span>Аккаунты</a>
    <a href="/queries" class="{{ 'active' if active=='queries' else '' }}"><span class="icon">🔍</span>Запросы</a>
    <a href="/results" class="{{ 'active' if active=='results' else '' }}"><span class="icon">📋</span>Результаты поиска</a>
    <a href="/settings" class="{{ 'active' if active=='settings' else '' }}"><span class="icon">🔐</span>Настройки</a>
    <a href="/control" class="{{ 'active' if active=='control' else '' }}"><span class="icon">⚙️</span>Управление</a>
  </nav>
</div>
<div class="main">
  {% block content %}{% endblock %}
</div>
<div id="toast"></div>
<script>
function showToast(msg, ok=true){
  const t=document.getElementById('toast');
  t.textContent=msg;
  t.style.background=ok?'#2eb872':'#e53e3e';
  t.style.display='block';
  setTimeout(()=>t.style.display='none',3000);
}
</script>
</body>
</html>
"""

def render_page(block_content, active=""):
    notice_html = ""
    if request.args.get("error"):
        notice_html = f'<div class="notice notice-error">{escape(request.args["error"])}</div>'
    elif request.args.get("notice"):
        notice_html = f'<div class="notice notice-success">{escape(request.args["notice"])}</div>'

    tpl = BASE_HTML.replace("{% block content %}{% endblock %}", notice_html + block_content)
    prefix = request.script_root.rstrip("/")
    if prefix:
        tpl = tpl.replace('href="/', f'href="{prefix}/')
        tpl = tpl.replace("href='/", f"href='{prefix}/")
        tpl = tpl.replace('action="/', f'action="{prefix}/')
        tpl = tpl.replace("action='/", f"action='{prefix}/")
        tpl = tpl.replace("fetch('/", f"fetch('{prefix}/")
        tpl = tpl.replace('fetch("/', f'fetch("{prefix}/')
    return render_template_string(tpl, active=active)


# ══════════════════════════════════════════════════════════════════
#  ACCOUNTS
# ══════════════════════════════════════════════════════════════════

@app.route("/accounts", methods=["GET"])
def accounts_page():
    accounts = db_module.get_all_accounts()
    rows = ""
    for a in accounts:
        rows += f"""
        <tr>
          <td>{a['id']}</td>
          <td>{a['login']}</td>
          <td>{'●'*len(a['password'])}</td>
          <td><code style="font-size:12px">{a['proxy'] or '—'}</code></td>
          <td>{'<span class="badge badge-green">Активен</span>' if a['active'] else '<span class="badge badge-red">Отключён</span>'}</td>
          <td>{a['max_per_day']} / день</td>
          <td>Раз в {a['interval_min']} мин</td>
          <td>
            <a class="btn btn-primary btn-sm" href="/accounts/edit/{a['id']}">Изменить</a>
            <form class="inline" method="post" action="/accounts/delete/{a['id']}"
                  onsubmit="return confirm('Удалить аккаунт?')">
              <button class="btn btn-danger btn-sm">Удалить</button>
            </form>
          </td>
        </tr>"""
    content = f"""
    <div class="page-title">👤 Аккаунты</div>
    <div class="card">
      <a class="btn btn-success" href="/accounts/add">+ Добавить аккаунт</a>
    </div>
    <div class="card">
      <table>
        <tr><th>#</th><th>Логин</th><th>Пароль</th><th>Прокси</th>
            <th>Статус</th><th>Лимит</th><th>Интервал</th><th>Действия</th></tr>
        {rows if rows else '<tr><td colspan="8" style="text-align:center;color:#a0aec0">Нет аккаунтов</td></tr>'}
      </table>
    </div>"""
    return render_page(content, "accounts")


@app.route("/accounts/add", methods=["GET", "POST"])
@app.route("/accounts/edit/<int:acc_id>", methods=["GET", "POST"])
def accounts_edit(acc_id=None):
    acc = None
    if acc_id:
        accounts = db_module.get_all_accounts()
        acc = next((a for a in accounts if a["id"] == acc_id), None)

    if request.method == "POST":
        db_module.upsert_account(
            login=request.form["login"],
            password=request.form["password"],
            proxy=request.form.get("proxy", ""),
            active=1 if request.form.get("active") else 0,
            max_per_day=int(request.form.get("max_per_day", 20)),
            interval_min=int(request.form.get("interval_min", 10)),
            acc_id=acc_id,
        )
        return redirect(url_for("accounts_page"))

    v = acc or {"login":"","password":"","proxy":"","active":1,"max_per_day":20,"interval_min":10}
    content = f"""
    <div class="page-title">{'Изменить' if acc_id else 'Добавить'} аккаунт</div>
    <div class="card" style="max-width:600px">
      <form method="post">
        <div class="form-group">
          <label>Логин (email)</label>
          <input name="login" value="{v['login']}" required>
        </div>
        <div class="form-group">
          <label>Пароль</label>
          <input name="password" type="password" value="{v['password']}" required>
        </div>
        <div class="form-group">
          <label>Прокси (например: socks5://host:port, оставить пустым — прямое подключение)</label>
          <input name="proxy" value="{v['proxy']}">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Макс. статей в день</label>
            <input name="max_per_day" type="number" min="1" value="{v['max_per_day']}">
          </div>
          <div class="form-group">
            <label>Интервал между статьями (мин)</label>
            <input name="interval_min" type="number" min="1" value="{v['interval_min']}">
          </div>
        </div>
        <div class="form-group">
          <label><input type="checkbox" name="active" {'checked' if v['active'] else ''}> Активен</label>
        </div>
        <button class="btn btn-primary" type="submit">Сохранить</button>
        <a class="btn btn-warn" href="/accounts">Отмена</a>
      </form>
    </div>"""
    return render_page(content, "accounts")


@app.route("/accounts/delete/<int:acc_id>", methods=["POST"])
def accounts_delete(acc_id):
    db_module.delete_account(acc_id)
    return redirect(url_for("accounts_page"))


# ══════════════════════════════════════════════════════════════════
#  QUERIES
# ══════════════════════════════════════════════════════════════════

@app.route("/queries", methods=["GET"])
def queries_page():
    queries = db_module.get_all_queries_with_stats()
    rows = ""
    for q in queries:
        total = int(q.get("total_found") or 0)
        downloaded = int(q.get("downloaded_count") or 0)
        remaining = int(q.get("remaining_count") or 0)
        parse_button = (
            f"""
            <form class="inline" method="post" action="/control/start">
              <input type="hidden" name="query_id" value="{q['id']}">
              <button class="btn btn-success btn-sm">Парсить запрос</button>
            </form>
            """
            if q["active"]
            else '<span class="badge badge-gray">Запрос отключён</span>'
        )
        rows += f"""
        <tr>
          <td>{q['id']}</td>
          <td><strong>{q['query']}</strong></td>
          <td>{'<span class="badge badge-green">Активен</span>' if q['active'] else '<span class="badge badge-gray">Отключён</span>'}</td>
          <td><span class="badge badge-blue">{total}</span></td>
          <td><span class="badge badge-green">{downloaded}</span></td>
          <td><span class="badge badge-gray">{remaining}</span></td>
          <td style="font-size:12px;color:#a0aec0">{q.get('last_collected_at') or '—'}</td>
          <td>{q['created_at']}</td>
          <td>
            {parse_button}
            <a class="btn btn-primary btn-sm" href="/queries/edit/{q['id']}">Изменить</a>
            <form class="inline" method="post" action="/queries/delete/{q['id']}"
                  onsubmit="return confirm('Удалить?')">
              <button class="btn btn-danger btn-sm">Удалить</button>
            </form>
          </td>
        </tr>"""
    content = f"""
    <div class="page-title">🔍 Поисковые запросы</div>
    <div class="card">
      <a class="btn btn-success" href="/queries/add">+ Добавить запрос</a>
    </div>
    <div class="card">
      <table>
        <tr><th>#</th><th>Запрос</th><th>Статус</th><th>Найдено</th><th>Скачано</th><th>Осталось</th><th>Последний обход</th><th>Добавлен</th><th>Действия</th></tr>
        {rows if rows else '<tr><td colspan="9" style="text-align:center;color:#a0aec0">Нет запросов</td></tr>'}
      </table>
    </div>"""
    return render_page(content, "queries")


@app.route("/queries/add", methods=["GET", "POST"])
@app.route("/queries/edit/<int:q_id>", methods=["GET", "POST"])
def queries_edit(q_id=None):
    q = None
    if q_id:
        qs = db_module.get_all_queries()
        q = next((x for x in qs if x["id"] == q_id), None)

    if request.method == "POST":
        db_module.upsert_query(
            query=request.form["query"],
            active=1 if request.form.get("active") else 0,
            q_id=q_id,
        )
        return redirect(url_for("queries_page"))

    v = q or {"query": "", "active": 1}
    content = f"""
    <div class="page-title">{'Изменить' if q_id else 'Добавить'} запрос</div>
    <div class="card" style="max-width:500px">
      <form method="post">
        <div class="form-group">
          <label>Поисковый запрос</label>
          <input name="query" value="{v['query']}" required>
        </div>
        <div class="form-group">
          <label><input type="checkbox" name="active" {'checked' if v['active'] else ''}> Активен</label>
        </div>
        <button class="btn btn-primary" type="submit">Сохранить</button>
        <a class="btn btn-warn" href="/queries">Отмена</a>
      </form>
    </div>"""
    return render_page(content, "queries")


@app.route("/queries/delete/<int:q_id>", methods=["POST"])
def queries_delete(q_id):
    db_module.delete_query(q_id)
    return redirect(url_for("queries_page"))


# ══════════════════════════════════════════════════════════════════
#  RESULTS
# ══════════════════════════════════════════════════════════════════

@app.route("/results", methods=["GET"])
def results_page():
    queries = db_module.get_all_queries_with_stats()
    sel_id = request.args.get("q", type=int)

    query_tabs = ""
    for q in queries:
        active_cls = "active" if q["id"] == sel_id else ""
        total = int(q.get("total_found") or 0)
        downloaded = int(q.get("downloaded_count") or 0)
        query_tabs += f'<a href="/results?q={q["id"]}" class="btn btn-primary btn-sm {active_cls}" style="margin:2px">{q["query"]} ({downloaded}/{total})</a> '

    results_html = ""
    if sel_id:
        results = db_module.get_search_results(sel_id)
        total = len(results)
        downloaded = sum(1 for r in results if r["downloaded"])
        pct = int(downloaded / total * 100) if total else 0
        results_html = f"""
        <div class="search-info">
          Найдено: <strong>{total}</strong> &nbsp;|&nbsp;
          Скачано: <strong>{downloaded}</strong> &nbsp;|&nbsp;
          Осталось: <strong>{total - downloaded}</strong>
          <div class="progress-bar"><div class="progress-fill" style="width:{pct}%"></div></div>
        </div>
        <table>
          <tr><th>ID</th><th>Заголовок</th><th>URL</th><th>Статус</th><th>Действие</th></tr>"""
        for r in results:
            badge = '<span class="badge badge-green">Скачана</span>' if r["downloaded"] else '<span class="badge badge-gray">Не скачана</span>'
            parse_button = f"""
            <form class="inline" method="post" action="/control/start">
              <input type="hidden" name="article_id" value="{r['article_id']}">
              <button class="btn btn-success btn-sm">Парсить статью</button>
            </form>"""
            results_html += f"""
          <tr>
            <td>{r['article_id']}</td>
            <td>{r['title']}</td>
            <td><a href="{r['url']}" target="_blank" style="font-size:12px">{r['url'][:60]}…</a></td>
            <td>{badge}</td>
            <td>{parse_button}</td>
          </tr>"""
        results_html += "</table>"

    content = f"""
    <div class="page-title">📋 Результаты поиска</div>
    <div class="card">
      <h3>Выберите запрос</h3>
      {query_tabs if query_tabs else '<span style="color:#a0aec0">Нет запросов</span>'}
    </div>
    {'<div class="card">' + results_html + '</div>' if results_html else ''}"""
    return render_page(content, "results")


# ══════════════════════════════════════════════════════════════════
#  ARTICLES LIST
# ══════════════════════════════════════════════════════════════════

@app.route("/articles")
def articles_list():
    return redirect(url_for("results_page"))
    articles = db_module.get_all_articles()
    rows = ""
    for a in articles:
        wp_badge = (
            '<span class="badge badge-green">WP</span>'
            if a.get("exported_to_wp")
            else '<span class="badge badge-gray">—</span>'
        )
        review_badge = (
            '<span class="badge badge-red">REVIEW</span>'
            if a.get("needs_manual_review")
            else '<span class="badge badge-gray">—</span>'
        )
        rows += f"""
        <tr>
          <td><a href="/articles/{a['article_id']}">{a['article_id']}</a></td>
          <td>{a['title']}</td>
          <td><code style="font-size:11px">{a['search_query']}</code></td>
          <td>{a['account_login']}</td>
          <td style="font-size:12px;color:#a0aec0">{a['downloaded_at'][:16]}</td>
          <td>{wp_badge}</td>
          <td>{review_badge}</td>
          <td><a class="btn btn-primary btn-sm" href="/articles/{a['article_id']}">Открыть</a></td>
        </tr>"""
    content = f"""
    <div class="page-title">📄 Скачанные статьи</div>
    <div class="card">
      <table>
        <tr><th>ID</th><th>Название</th><th>Запрос</th><th>Аккаунт</th><th>Скачана</th><th>WP</th><th>Проверка</th><th></th></tr>
        {rows if rows else '<tr><td colspan="8" style="text-align:center;color:#a0aec0">Нет скачанных статей</td></tr>'}
      </table>
    </div>"""
    return render_page(content, "articles")


# ══════════════════════════════════════════════════════════════════
#  ARTICLE DETAIL
# ══════════════════════════════════════════════════════════════════

def _extract_body(html: str) -> str:
    if not html:
        return ""
    lower = html.lower()
    body_start = lower.find("<body")
    if body_start == -1:
        return html
    body_start = lower.find(">", body_start)
    body_end = lower.rfind("</body>")
    if body_start == -1 or body_end == -1:
        return html
    return html[body_start + 1 : body_end]


def _build_full_wp_html(art: dict, graphics: list[dict]) -> str:
    article_body = _extract_body(art.get("article_html") or "")
    graphics_html = ""
    for g in graphics:
        label = g["indicator"] or f"Page {g['page_num']}"
        graphics_html += f'<h3>{label}</h3>\n{g.get("html") or ""}\n'
    return article_body + ("\n<hr/>\n" + graphics_html if graphics_html else "")


def _build_preview_html_page(inner_html: str, title: str) -> str:
    """Формирует красивую HTML-страницу предпросмотра, похожую на отображение в WordPress."""
    return f"""<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{title}</title>
  <style>
    body{{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;margin:0;padding:24px;background:#f5f5f7;color:#111827;line-height:1.6}}
    main{{max-width:900px;margin:0 auto;background:#ffffff;border-radius:12px;box-shadow:0 10px 30px rgba(15,23,42,0.08);padding:32px}}
    h1,h2,h3,h4,h5,h6{{margin:1.5em 0 0.6em;font-weight:600;color:#111827}}
    h1{{font-size:28px;border-bottom:2px solid #e5e7eb;padding-bottom:0.4em}}
    h2{{font-size:22px;border-left:4px solid #2563eb;padding-left:10px;background:#f3f4ff;padding-top:4px;padding-bottom:4px;border-radius:4px}}
    h3{{font-size:18px;color:#1f2937;margin-top:1.4em}}
    p{{margin:0.75em 0}}
    ul,ol{{margin:0.75em 0 0.75em 1.5em}}
    li{{margin:0.3em 0}}

    table{{width:100%;border-collapse:collapse;margin:1.5em 0;font-size:14px;}}
    thead tr{{background:#f3f4f6}}
    th,td{{border:1px solid #e5e7eb;padding:8px 10px;vertical-align:top}}
    tr:nth-child(even) td{{background:#fafafa}}

    blockquote{{margin:1.2em 0;padding:0.8em 1em;border-left:4px solid #2563eb;background:#f3f4ff;border-radius:4px;color:#1f2937}}
    code{{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono','Courier New', monospace;background:#f3f4f6;border-radius:3px;padding:2px 4px;font-size:90%}}
    pre code{{display:block;padding:12px;overflow-x:auto}}

    .ref-block h2, .ref-block h3{{border:none;background:transparent;border-left:4px solid #10b981;padding-left:10px}}
    .ref-block ol{{padding-left:1.5em}}
    .ref-block li{{margin-bottom:0.4em}}

    details{{border-radius:6px;border:1px solid #e5e7eb;margin:0.8em 0;background:#f9fafb}}
    summary{{cursor:pointer;padding:8px 12px;font-weight:500;list-style:none}}
    summary::-webkit-details-marker{{display:none}}
    summary::before{{content:'▸';display:inline-block;margin-right:6px;color:#6b7280;transition:transform .15s ease-out}}
    details[open] summary::before{{transform:rotate(90deg)}}
    details > *:not(summary){{padding:0 12px 10px 12px}}

    .graphics-gallery{{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}}
    .graphics-thumb{{flex:1 1 200px;border-radius:8px;border:1px solid #e5e7eb;overflow:hidden;background:#f9fafb;cursor:pointer;transition:box-shadow .15s,border-color .15s}}
    .graphics-thumb:hover{{box-shadow:0 6px 18px rgba(15,23,42,0.12);border-color:#2563eb}}
    .graphics-thumb-title{{padding:8px 10px;font-size:13px;font-weight:500;border-bottom:1px solid #e5e7eb;background:#f3f4f6}}
    .graphics-thumb-body{{max-height:180px;overflow:hidden}}

    .modal-backdrop{{position:fixed;inset:0;background:rgba(15,23,42,0.55);display:none;align-items:center;justify-content:center;z-index:9999}}
    .modal-backdrop.active{{display:flex}}
    .modal{{width:90%;max-width:1100px;max-height:90vh;background:#111827;color:#e5e7eb;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,0.45);display:flex;flex-direction:column}}
    .modal-header{{padding:12px 16px;border-bottom:1px solid #1f2937;display:flex;align-items:center;justify-content:space-between}}
    .modal-title{{font-size:14px;font-weight:500}}
    .modal-close{{border:none;background:transparent;color:#9ca3af;cursor:pointer;font-size:18px;line-height:1}}
    .modal-body{{padding:0;background:#111827;flex:1;overflow:auto}}
    .modal-body-inner{{padding:18px 22px}}
    .modal-footer{{padding:8px 14px;border-top:1px solid #1f2937;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#9ca3af}}
    .modal-nav-btn{{border-radius:999px;border:1px solid #4b5563;background:#1f2937;color:#e5e7eb;padding:4px 10px;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px}}
    .modal-nav-btn:hover{{background:#374151}}

    a{{color:#2563eb;text-decoration:none}}
    a:hover{{text-decoration:underline}}
  </style>
</head>
<body>
  <main>
  {inner_html}
  </main>
</body>
</html>"""


@app.route("/articles/<article_id>")
def article_detail(article_id):
    art = db_module.get_article(article_id)
    if not art:
        return render_page(f'<div class="page-title">Статья {article_id} не найдена</div>', "articles")

    graphics = db_module.get_article_graphics(article_id)

    # Полный HTML, который отправляем/будем отправлять в WordPress.
    full_wp_html = _build_full_wp_html(art, graphics)
    # Красивая страница предпросмотра (то, что пользователь видит в iframe и может скачать).
    preview_page_html = _build_preview_html_page(full_wp_html, art["title"])

    # Вкладки с графиками
    graphics_tabs_btns = ""
    graphics_tabs_content = ""
    for g in graphics:
        tab_id = f"graphic_{g['page_num']}"
        graphics_tabs_btns += f"""<button class="tab-btn" onclick="switchTab('{tab_id}')">{g['indicator'] or f"Стр. {g['page_num']}"}</button>"""
        graphics_tabs_content += f"""
        <div class="tab-content" id="{tab_id}">
          <iframe srcdoc="{{}}" class="article-frame" id="frame_{tab_id}"></iframe>
        </div>"""

    # JS-данные для графиков: заворачиваем каждую страницу тоже в наш шаблон предпросмотра,
    # чтобы внутри модального окна/вкладки вид был таким же аккуратным.
    graphic_js = ""
    for g in graphics:
        key = f"graphic_{g['page_num']}"
        inner = g.get("html") or ""
        page_html = _build_preview_html_page(inner, g.get("indicator") or f"Стр. {g['page_num']}")
        graphic_js += f'GRAPHICS_DATA["{key}"] = document.createElement("div");'
        graphic_js += f'GRAPHICS_DATA["{key}"].setAttribute("data-html", {repr(page_html)});'

    # Используем data-атрибуты чтобы не ломать шаблон кавычками
    content = f"""
    <div class="page-title">📄 {art['title']}</div>
    <div class="card" style="margin-bottom:12px;padding:14px">
      <div style="font-size:13px;color:#a0aec0;margin-bottom:8px">
        <strong>ID:</strong> {art['article_id']} &nbsp;|&nbsp;
        <strong>Запрос:</strong> {art['search_query']} &nbsp;|&nbsp;
        <strong>Аккаунт:</strong> {art['account_login']} &nbsp;|&nbsp;
        <strong>URL:</strong> <a href="{art['url']}" target="_blank">{art['url']}</a>
      </div>
      <div style="font-size:13px;color:#a0aec0">
        <strong>WordPress:</strong>
        {"<span class='badge badge-green'>Выкачано</span> (ID=" + str(art.get('wp_post_id') or '') + ")" if art.get("exported_to_wp") else "<span class='badge badge-gray'>Ещё не выкачано</span>"}
        &nbsp;|&nbsp;
        <strong>Проверка:</strong>
        {"<span class='badge badge-red'>Нужна</span>" if art.get("needs_manual_review") else "<span class='badge badge-gray'>—</span>"}
      </div>
      <div style="margin-top:10px">
        <form class="inline" method="post" action="/articles/{art['article_id']}/retry"
              onsubmit="return confirm('Снять флаг ручной проверки и перескачать/перевыгрузить статью?')">
          <button class="btn btn-warn btn-sm">🔁 Retry (re-scrape + re-export)</button>
        </form>
        <a class="btn btn-primary btn-sm" href="/articles/{art['article_id']}/download_html" style="margin-left:6px" target="_blank">
          ⬇ Скачать в HTML
        </a>
      </div>
    </div>

    <div class="tabs" id="main-tabs">
      <button class="tab-btn active" onclick="switchMainTab('tab-article')">📝 Основной контент</button>
      <button class="tab-btn" onclick="switchMainTab('tab-graphics')">🖼 Графики ({len(graphics)} стр.)</button>
    </div>

    <div class="tab-content active" id="tab-article">
      <iframe class="article-frame" id="frame-article"></iframe>
    </div>

    <div class="tab-content" id="tab-graphics">
      {'<div style="color:#a0aec0;padding:20px">Графики не найдены</div>' if not graphics else f'''
      <div class="tabs" id="graphic-tabs">
        {graphics_tabs_btns}
      </div>
      {graphics_tabs_content}
      '''}
    </div>

    <script>
    // Данные статьи (полная, стилизованная HTML-страница предпросмотра)
    const ARTICLE_HTML = document.createElement('div');
    ARTICLE_HTML.id = 'article_data';
    ARTICLE_HTML.setAttribute('data-html', {repr(preview_page_html)});
    document.body.appendChild(ARTICLE_HTML);

    const GRAPHICS_DATA = {{}};
    {graphic_js}

    function loadFrame(iframe, html) {{
      const doc = iframe.contentDocument || iframe.contentWindow.document;
      doc.open(); doc.write(html); doc.close();
    }}

    // Загружаем основной фрейм при старте
    window.addEventListener('load', function() {{
      const htmlData = document.getElementById('article_data').getAttribute('data-html');
      loadFrame(document.getElementById('frame-article'), htmlData);
      // Загружаем первый график если есть
      const firstKey = Object.keys(GRAPHICS_DATA)[0];
      if (firstKey) {{
        const html = GRAPHICS_DATA[firstKey].getAttribute('data-html');
        const frame = document.getElementById('frame_' + firstKey);
        if (frame) loadFrame(frame, html);
        document.getElementById(firstKey) && (document.getElementById(firstKey).classList.add('active'));
        document.querySelectorAll('#graphic-tabs .tab-btn')[0] && document.querySelectorAll('#graphic-tabs .tab-btn')[0].classList.add('active');
      }}
    }});

    function switchMainTab(id) {{
      document.querySelectorAll('#main-tabs .tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('#tab-article, #tab-graphics').forEach(t => t.classList.remove('active'));
      event.target.classList.add('active');
      document.getElementById(id).classList.add('active');
    }}

    function switchTab(id) {{
      // Кнопки
      document.querySelectorAll('#graphic-tabs .tab-btn').forEach(b => b.classList.remove('active'));
      event.target.classList.add('active');
      // Контент
      Object.keys(GRAPHICS_DATA).forEach(key => {{
        const el = document.getElementById(key);
        if (el) el.classList.remove('active');
      }});
      const target = document.getElementById(id);
      if (target) {{
        target.classList.add('active');
        // Ленивая загрузка фрейма
        const frame = document.getElementById('frame_' + id);
        if (frame && !frame.dataset.loaded) {{
          const html = GRAPHICS_DATA[id] ? GRAPHICS_DATA[id].getAttribute('data-html') : '';
          loadFrame(frame, html);
          frame.dataset.loaded = '1';
        }}
      }}
    }}
    </script>"""
    return render_page(content, "articles")


@app.route("/articles/<article_id>/download_html")
def article_download_html(article_id):
    art = db_module.get_article(article_id)
    if not art:
        return render_page(f'<div class="page-title">Статья {article_id} не найдена</div>', "articles")

    graphics = db_module.get_article_graphics(article_id)
    preview_html = _build_full_html(art, graphics)

    from flask import make_response

    resp = make_response(preview_html)
    resp.headers["Content-Type"] = "text/html; charset=utf-8"
    resp.headers["Content-Disposition"] = f'attachment; filename="article_{article_id}.html"'
    return resp


# ══════════════════════════════════════════════════════════════════
#  SETTINGS
# ══════════════════════════════════════════════════════════════════

@app.route("/settings", methods=["GET", "POST"])
def settings_page():
    if request.method == "POST":
        db_module.set_setting("wp_export_method", request.form.get("wp_export_method", "cli"))
        db_module.set_setting("wp_rest_base_url", request.form.get("wp_rest_base_url", "").strip())
        db_module.set_setting("wp_rest_username", request.form.get("wp_rest_username", "").strip())
        db_module.set_setting("wp_rest_app_password", request.form.get("wp_rest_app_password", "").strip())
        db_module.set_setting("wp_rest_verify_ssl", "true" if request.form.get("wp_rest_verify_ssl") else "false")
        return redirect(url_for("settings_page"))

    v = {
        "wp_export_method": db_module.get_setting("wp_export_method", "cli"),
        "wp_rest_base_url": db_module.get_setting("wp_rest_base_url", ""),
        "wp_rest_username": db_module.get_setting("wp_rest_username", ""),
        "wp_rest_app_password": db_module.get_setting("wp_rest_app_password", ""),
        "wp_rest_verify_ssl": db_module.get_setting("wp_rest_verify_ssl", "false"),
    }

    content = f"""
    <div class="page-title">🔐 Настройки</div>
    <div class="card" style="max-width:720px">
      <h3>WordPress</h3>
      <form method="post">
        <div class="form-group">
          <label>Способ выгрузки</label>
          <select name="wp_export_method">
            <option value="cli" {'selected' if v['wp_export_method']=='cli' else ''}>WP-CLI (как сейчас)</option>
            <option value="rest" {'selected' if v['wp_export_method']=='rest' else ''}>REST (через плагин)</option>
          </select>
        </div>

        <div class="form-group">
          <label>REST base URL (например: https://site.com)</label>
          <input name="wp_rest_base_url" value="{v['wp_rest_base_url']}">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>REST username</label>
            <input name="wp_rest_username" value="{v['wp_rest_username']}">
          </div>
          <div class="form-group">
            <label>Application Password</label>
            <input name="wp_rest_app_password" type="password" value="{v['wp_rest_app_password']}">
          </div>
        </div>
        <div class="form-group">
          <label>
            <input type="checkbox" name="wp_rest_verify_ssl" {'checked' if v['wp_rest_verify_ssl'].lower() in ['1', 'true', 'yes', 'on'] else ''}>
            Проверять SSL-сертификат REST URL
          </label>
        </div>

        <button class="btn btn-primary" type="submit">Сохранить</button>
      </form>
      <p style="font-size:12px;color:#a0aec0;margin-top:10px">
        Для режима REST нужен установленный плагин (см. README). Для локального https://localhost обычно SSL-проверку нужно отключить.
      </p>
    </div>
    """
    return render_page(content, "settings")


# ══════════════════════════════════════════════════════════════════
#  CONTROL PANEL
# ══════════════════════════════════════════════════════════════════

@app.route("/control")
def control_page():
    status = sched.get_status()
    is_running = status["running"]

    workers_html = ""
    for login, info in status["workers"].items():
        dot_cls = "dot-green" if info["status"] not in ("stopped", "idle", "") else "dot-gray"
        daily = info.get("count_today", 0)
        workers_html += f"""
        <div class="worker-row">
          <span class="worker-login"><span class="status-dot {dot_cls}"></span>{login}</span>
          <span class="worker-status">{info.get('status','—')}</span>
          <span class="badge badge-blue">{daily} сегодня</span>
          <span style="font-size:12px;color:#a0aec0">{('Посл: ' + info.get('last_article','')) if info.get('last_article') else ''}</span>
        </div>"""

    active_queries = [q for q in db_module.get_all_queries_with_stats() if q["active"]]
    query_options = '<option value="">Все активные запросы по очереди</option>'
    for q in active_queries:
        total = int(q.get("total_found") or 0)
        remaining = int(q.get("remaining_count") or 0)
        label = f"{q['query']} — осталось {remaining} из {total}"
        query_options += f'<option value="{q["id"]}">{escape(label)}</option>'

    article_options = '<option value="">Не выбрана</option>'
    for r in db_module.get_search_results_for_selector(active_only=True):
        status_label = "скачана" if r.get("downloaded") else "не скачана"
        label = f"{r['query']} / {r['title']} ({status_label})"
        article_options += f'<option value="{escape(r["article_id"])}">{escape(label)}</option>'

    status_block = f"""
      <div class="control-status">
        <span class="status-dot {'dot-green' if is_running else 'dot-red'}"></span>
        <strong>{'Работает' if is_running else 'Остановлен'}</strong>
        {('<br><span style="font-size:12px;color:#a0aec0">Режим: ' + escape(status.get('mode','')) + '</span>') if status.get('mode') else ''}
      </div>
    """

    control_inner = ""
    if is_running:
        control_inner = f"""
        <div class="control-top">
          {status_block}
          <form method="post" action="/control/stop">
            <button class="btn btn-danger">⏹ Остановить парсинг</button>
          </form>
          <button class="btn btn-primary" onclick="refreshStatus()">🔄 Обновить статус</button>
        </div>"""
    else:
        control_inner = f"""
        <form method="post" action="/control/start">
          <div class="control-top">
            {status_block}
            <button class="btn btn-success">▶ Запустить парсинг</button>
            <button class="btn btn-primary" type="button" onclick="refreshStatus()">🔄 Обновить статус</button>
          </div>
          <div class="form-row control-selects">
            <div class="form-group" style="margin-bottom:0">
              <label>Запрос для парсинга</label>
              <select name="query_id">{query_options}</select>
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label>Конкретная статья (если выбрана, запрос игнорируется)</label>
              <select name="article_id">{article_options}</select>
            </div>
          </div>
        </form>"""

    content = f"""
    <div class="page-title">⚙️ Управление парсером</div>
    <div class="card control-card">
      {control_inner}
    </div>

    <div class="card" id="workers-card">
      <h3>Воркеры аккаунтов</h3>
      <div id="workers-list">
        {workers_html if workers_html else '<span style="color:#a0aec0">Нет воркеров</span>'}
      </div>
    </div>

    <div class="card">
      <h3>Быстрые действия</h3>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <form method="post" action="/control/collect">
          <button class="btn btn-primary" {'disabled' if globals().get('COLLECT_RUNNING') else ''}>
            📥 Собрать результаты поиска сейчас
          </button>
        </form>
      </div>
      <p style="font-size:12px;color:#a0aec0;margin-top:10px">
        «Собрать результаты» — откроет браузер первого активного аккаунта
        и соберёт статьи только по активным поисковым запросам в БД.
        Новые или изменённые статьи после парсинга выгружаются в WordPress автоматически.
        { ' <br><strong>Сбор сейчас выполняется…</strong>' if globals().get('COLLECT_RUNNING') else '' }
      </p>
    </div>

    <script>
    function refreshStatus() {{
      fetch('/api/status').then(r=>r.json()).then(data=>{{
        let html = '';
        for (const [login, info] of Object.entries(data.workers)) {{
          const dot = (info.status && info.status !== 'stopped' && info.status !== 'idle')
            ? 'dot-green' : 'dot-gray';
          html += `<div class="worker-row">
            <span class="worker-login"><span class="status-dot ${{dot}}"></span>${{login}}</span>
            <span class="worker-status">${{info.status || '—'}}</span>
            <span class="badge badge-blue">${{info.count_today}} сегодня</span>
            <span style="font-size:12px;color:#a0aec0">${{info.last_article ? 'Посл: '+info.last_article : ''}}</span>
          </div>`;
        }}
        document.getElementById('workers-list').innerHTML = html || '<span style="color:#a0aec0">Нет воркеров</span>';
        showToast('Статус обновлён');
      }});
    }}
    setInterval(refreshStatus, 10000);
    </script>"""
    return render_page(content, "control")


@app.route("/control/start", methods=["POST"])
def control_start():
    query_id = request.form.get("query_id", type=int)
    article_id = (request.form.get("article_id") or "").strip() or None

    active_accounts = [a for a in db_module.get_all_accounts() if a["active"]]
    if not active_accounts:
        return redirect(url_for(
            "accounts_page",
            error="Добавьте и активируйте аккаунт Libook перед запуском парсера.",
        ))

    active_queries = [q for q in db_module.get_all_queries() if q["active"]]
    if not active_queries:
        return redirect(url_for(
            "queries_page",
            error="Добавьте и активируйте поисковый запрос перед запуском парсера.",
        ))

    if article_id:
        selected_article = db_module.get_search_result(article_id)
        if not selected_article:
            return redirect(url_for("results_page", error="Выбранная статья не найдена в результатах поиска."))
        if not selected_article.get("query_active"):
            return redirect(url_for("results_page", error="Запрос выбранной статьи отключён. Включите запрос или выберите другую статью."))
        query_id = None
    elif query_id and not db_module.get_active_query(query_id):
        return redirect(url_for("queries_page", error="Выбранный запрос не найден или отключён."))

    sched.start(query_id=query_id, article_id=article_id)
    if article_id:
        notice = f"Парсер запущен для статьи {article_id}."
    elif query_id:
        notice = f"Парсер запущен для запроса #{query_id}."
    else:
        notice = "Парсер запущен по всем активным запросам."
    return redirect(url_for("control_page", notice=notice))


@app.route("/control/stop", methods=["POST"])
def control_stop():
    sched.stop()
    return redirect(url_for("control_page"))


@app.route("/control/collect", methods=["POST"])
def control_collect():
    """Запускает разовый сбор результатов поиска в фоновом потоке."""
    import threading
    import asyncio

    global COLLECT_RUNNING
    if COLLECT_RUNNING:
        # Уже идёт сбор — просто возвращаемся на страницу
        return redirect(url_for("control_page"))

    accounts = [a for a in db_module.get_all_accounts() if a["active"]]
    if not accounts:
        return redirect(url_for(
            "accounts_page",
            error="Добавьте и активируйте аккаунт Libook перед сбором результатов.",
        ))

    queries = [q for q in db_module.get_all_queries() if q["active"]]
    if not queries:
        return redirect(url_for(
            "queries_page",
            error="Добавьте и активируйте поисковый запрос перед сбором результатов.",
        ))

    def _collect():
        global COLLECT_RUNNING
        COLLECT_RUNNING = True
        try:
            acc = accounts[0]

            async def _run():
                from libook_scraper import LibookScraper
                from playwright.async_api import async_playwright
                async with async_playwright() as pw:
                    for q in queries:
                        scraper = LibookScraper(
                            login=acc["login"],
                            password=acc["password"],
                            proxy=acc["proxy"],
                            search_query=q["query"],
                            account_id=acc["id"],
                            headless=True,
                        )
                        browser, context = await scraper._make_context(pw)
                        page = await context.new_page()
                        try:
                            await scraper._login(page)
                            await scraper._save_session(context)
                            await scraper.collect_search_results(page, query_id=q["id"])
                        finally:
                            await scraper._save_session(context)
                            await context.close()
                            await browser.close()

            loop = asyncio.new_event_loop()
            loop.run_until_complete(_run())
            loop.close()
        finally:
            COLLECT_RUNNING = False

    threading.Thread(target=_collect, daemon=True).start()
    return redirect(url_for("control_page"))


# ── API ──────────────────────────────────────────────────────────

@app.route("/api/status")
def api_status():
    return jsonify(sched.get_status())


# ── Root redirect ────────────────────────────────────────────────

@app.route("/")
def index():
    return redirect(url_for("control_page"))


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
