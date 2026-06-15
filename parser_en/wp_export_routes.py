from flask import Blueprint, redirect, url_for
import re

import database as db_module
from wp_exporter import export_disease_article
from clear_article import build_uptodate_article
from wp_exporter import get_wp_settings


wp_export_bp = Blueprint("wp_export", __name__)

_WP_STATUSES_TO_PRESERVE = {"publish", "private", "draft", "pending", "future"}


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


def _clean_service_labels(html: str) -> str:
    """Удаляет служебные строки вида 'Topic 3347 Version 123.0' и 'Graphic 57431 Version 170.0'."""
    if not html:
        return ""
    html = re.sub(r"Topic\s*\d+\s*Version\s*\d+\.\d+", "", html, flags=re.I)
    html = re.sub(r"Graphic\s*\d+\s*Version\s*\d+\.\d+", "", html, flags=re.I)
    return html


def _clean_inline_styles(html: str) -> str:
    """Очищает инлайновые стили style="..." и style='...'."""
    if not html:
        return ""
    # Удаляем style="..."/style='...'
    html = re.sub(r'\sstyle=("|\')[^"\']*\1', "", html, flags=re.I)
    return html


def _clean_html(html: str) -> str:
    """Комплексная очистка HTML: служебные строки + инлайновые стили."""
    return _clean_inline_styles(_clean_service_labels(html or ""))


BASE_STYLE = """
<style>
body{
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  background:#f5f7fb;
  margin:0;
  padding:30px;
}

main{
  max-width:950px;
  margin:auto;
  background:white;
  padding:40px;
  border-radius:12px;
  box-shadow:0 10px 30px rgba(0,0,0,0.08);
}

h1{
  border-bottom:2px solid #e5e7eb;
  padding-bottom:10px;
}

h2,h3,h4,h5,h6{
  margin-top:1.5em;
  margin-bottom:0.6em;
}

table{
  width:100%;
  border-collapse:collapse;
  margin:20px 0;
  font-size:14px;
}

th,td{
  border:1px solid #d1d5db;
  padding:8px 10px;
}

th{
  background:#f3f4f6;
}

ul,ol{
  margin:0.75em 0 0.75em 1.5em;
}

li{
  margin:0.25em 0;
}

.tabs{
  margin-top:20px;
}

.tab-buttons{
  display:flex;
  gap:10px;
  margin-bottom:15px;
  flex-wrap:wrap;
}

.tab-btn{
  padding:8px 14px;
  border:none;
  background:#e5e7eb;
  border-radius:6px;
  cursor:pointer;
  font-size:14px;
}

.tab-btn.active{
  background:#2563eb;
  color:white;
}

.tab-content{
  display:none;
}

.tab-content.active{
  display:block;
}

.graphics-container{
  position:relative;
  margin-top:10px;
}

.slides{
  overflow:hidden;
}

.slide{
  display:none;
}

.slide.active{
  display:block;
}

.slide-title{
  font-weight:600;
  margin-bottom:8px;
}

.slide-content{
  background:#f9fafb;
  border-radius:8px;
  padding:10px;
  border:1px solid #e5e7eb;
}

.nav{
  position:absolute;
  top:40%;
  background:#2563eb;
  color:white;
  border:none;
  font-size:20px;
  padding:8px 12px;
  cursor:pointer;
  border-radius:6px;
  transform:translateY(-50%);
}

.prev{left:-40px;}
.next{right:-40px;}

.no-graphics{
  color:#6b7280;
  font-size:14px;
  margin-top:10px;
}

/* Модальное окно для полноэкранного просмотра слайдов */
.modal-backdrop{
  position:fixed;
  inset:0;
  background:rgba(15,23,42,0.65);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:9999;
}

.modal-backdrop.active{
  display:flex;
}

.modal{
  width:95%;
  max-width:1100px;
  max-height:90vh;
  background:#111827;
  color:#e5e7eb;
  border-radius:12px;
  box-shadow:0 20px 50px rgba(0,0,0,0.45);
  display:flex;
  flex-direction:column;
}

.modal-header{
  padding:12px 16px;
  border-bottom:1px solid #1f2937;
  display:flex;
  align-items:center;
  justify-content:space-between;
}

.modal-title{
  font-size:14px;
  font-weight:500;
}

.modal-close{
  border:none;
  background:transparent;
  color:#9ca3af;
  cursor:pointer;
  font-size:18px;
  line-height:1;
}

.modal-body{
  padding:0;
  background:#111827;
  flex:1;
  overflow:auto;
}

.modal-body-inner{
  padding:18px 22px;
}

.modal-footer{
  padding:8px 14px;
  border-top:1px solid #1f2937;
  display:flex;
  justify-content:space-between;
  align-items:center;
  font-size:12px;
  color:#9ca3af;
}

.modal-nav-btn{
  border-radius:999px;
  border:1px solid #4b5563;
  background:#1f2937;
  color:#e5e7eb;
  padding:4px 10px;
  font-size:12px;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:4px;
}

.modal-nav-btn:hover{
  background:#374151;
}

a{
  color:#2563eb;
  text-decoration:none;
}

a:hover{
  text-decoration:underline;
}

/* Адаптив под мобильные устройства */
@media (max-width: 768px){
  body{
    padding:16px;
  }

  main{
    padding:20px 16px;
    border-radius:10px;
    box-shadow:0 6px 20px rgba(0,0,0,0.06);
  }

  .tab-buttons{
    gap:8px;
  }

  .tab-btn{
    flex:1 1 120px;
    text-align:center;
    font-size:13px;
    padding:7px 10px;
  }

  .graphics-container{
    margin:0;
  }

  .nav{
    top:auto;
    bottom:10px;
    transform:none;
  }

  .prev{
    left:10px;
  }

  .next{
    right:10px;
  }
}
</style>
"""

BASE_SCRIPT = """
<script>
function openTab(id){
  document.querySelectorAll(".tab-content")
    .forEach(el => el.classList.remove("active"));

  document.querySelectorAll(".tab-btn")
    .forEach(el => el.classList.remove("active"));

  var tab = document.getElementById(id);
  if (tab){
    tab.classList.add("active");
  }
  if (event && event.target){
    event.target.classList.add("active");
  }
}

let currentSlide = 0;

function showSlide(i){
  const slides = document.querySelectorAll(".slide");
  if (!slides.length){
    return;
  }

  if (i >= slides.length) currentSlide = 0;
  else if (i < 0) currentSlide = slides.length - 1;
  else currentSlide = i;

  slides.forEach(s => s.classList.remove("active"));
  slides[currentSlide].classList.add("active");

  updateModalFromSlide();
}

function slide(step){
  showSlide(currentSlide + step);
}

function openModal(index){
  const slides = document.querySelectorAll(".slide");
  if (!slides.length){
    return;
  }
  if (typeof index === "number"){
    currentSlide = index;
  }
  const backdrop = document.getElementById("graphics-modal");
  if (!backdrop){
    return;
  }
  backdrop.classList.add("active");
  updateModalFromSlide();
}

function closeModal(){
  const backdrop = document.getElementById("graphics-modal");
  if (backdrop){
    backdrop.classList.remove("active");
  }
}

function updateModalFromSlide(){
  const slides = document.querySelectorAll(".slide");
  if (!slides.length){
    return;
  }
  const slide = slides[currentSlide];
  const titleEl = slide.querySelector(".slide-title");
  const bodyEl = slide.querySelector(".slide-content");

  const modalTitle = document.getElementById("modal-title");
  const modalBody = document.getElementById("modal-body");
  const modalCounter = document.getElementById("modal-counter");

  if (modalTitle && titleEl){
    modalTitle.textContent = titleEl.textContent || "";
  }
  if (modalBody && bodyEl){
    modalBody.innerHTML = bodyEl.innerHTML;
  }
  if (modalCounter){
    modalCounter.textContent = (currentSlide + 1) + " / " + slides.length;
  }
}

function modalPrev(){
  slide(-1);
}

function modalNext(){
  slide(1);
}

window.onload = function(){
  showSlide(0);
};
</script>
"""


def _build_graphics_slider(graphics: list[dict]) -> str:
    if not graphics:
        return '<div class="no-graphics">Графики отсутствуют.</div>'

    slides = ""
    for i, g in enumerate(graphics):
        label = g.get("indicator") or f"Graphic {i + 1}"
        html = _clean_html(g.get("html") or "")
        slides += f"""
        <div class="slide" onclick="openModal({i})">
            <div class="slide-title">{label}</div>
            <div class="slide-content">
                {html}
            </div>
        </div>
        """

    return f"""
<div class="graphics-container">
    <button class="nav prev" onclick="slide(-1)">❮</button>
    <div class="slides">
        {slides}
    </div>
    <button class="nav next" onclick="slide(1)">❯</button>
</div>

<div id="graphics-modal" class="modal-backdrop" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-title"></div>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <div class="modal-body-inner" id="modal-body"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-nav-btn" onclick="modalPrev()">❮ Предыдущий</button>
      <span id="modal-counter"></span>
      <button class="modal-nav-btn" onclick="modalNext()">Следующий ❯</button>
    </div>
  </div>
</div>
"""


def _build_tabs(article_html: str, graphics_html: str) -> str:
    return f"""
<div class="tabs">

  <div class="tab-buttons">
      <button class="tab-btn active" onclick="openTab('article')">Article</button>
      <button class="tab-btn" onclick="openTab('graphics')">Graphics</button>
  </div>

  <div id="article" class="tab-content active">
      {article_html}
  </div>

  <div id="graphics" class="tab-content">
      {graphics_html}
  </div>

</div>
"""


def _build_full_html(art: dict, graphics: list[dict], include_title: bool = True) -> str:
    """
    Генерирует финальный HTML для WordPress/скачивания.

    Важно: сначала собираем "сырой" HTML (article + graphics) и прогоняем через
    clear_article.build_uptodate_article(), который:
    - удаляет мусор/служебные блоки
    - чистит атрибуты/inline стили
    - строит вкладки Article/Graphics/Authors/References
    - добавляет оглавление, слайдер, модалки, адаптив и т.п.
    """
    article_body = _extract_body(art.get("article_html") or "")
    article_body = _clean_html(article_body)

    # Формируем дополнительный список графиков из БД:
    # (заголовок, HTML), чтобы clear_article собрал по ним вкладку Graphics.
    extra_graphics: list[tuple[str, str]] = []
    for g in graphics or []:
        title = g.get("indicator") or f"Graphic {g.get('page_num')}"
        html = _clean_html(g.get("html") or "")
        article_body += html
        extra_graphics.append((title, html))

    return build_uptodate_article(article_body, include_title=include_title)


def _as_gutenberg_html_block(html: str) -> str:
    """Сохраняет HTML как Custom HTML block, без конвертации в параграфы Gutenberg."""
    html = html or ""
    return f"<!-- wp:html -->\n{html}\n<!-- /wp:html -->"


def _get_existing_wp_status(external_id: str) -> str | None:
    """Возвращает текущий статус уже выгруженной статьи, чтобы не снимать публикацию при обновлении."""
    settings = get_wp_settings()
    if settings.method != "rest":
        return None

    try:
        from wp_rest_client import get_disease_status_rest

        st = get_disease_status_rest(
            base_url=settings.rest_base_url,
            username=settings.rest_username,
            app_password=settings.rest_app_password,
            verify_ssl=settings.rest_verify_ssl,
            external_id=external_id,
        )
        status = str(st.get("post_status") or "")
        if st.get("exists") and status in _WP_STATUSES_TO_PRESERVE:
            return status
    except Exception as exc:
        print(f"[wp-export] Не удалось получить текущий статус {external_id}: {exc}")

    return None


def _export_single_article_to_wp(article_id: str) -> bool:
    art = db_module.get_article(article_id)
    if not art:
        return False

    external_id = f"en_{art['article_id']}"
    target_status = "draft"

    graphics = db_module.get_article_graphics(article_id)
    # Это тот же HTML, который скачивается кнопкой "Скачать в HTML".
    # Оборачиваем его в core/html, чтобы WP-плагин не разбирал страницу на
    # параграфы и не ломал исходное оформление.
    full_html = _as_gutenberg_html_block(_build_full_html(art, graphics, include_title=False))

    # TODO: при появлении в БД полей для возраста/симптомов/специализаций
    # можно прокинуть их сюда и дальше в export_disease_article.
    post_id = export_disease_article(
        title=art["title"],
        content_html=full_html,
        external_id=external_id,
        status=target_status,
        article_type_slug="eng-articles",
        is_english=True,
        age_slugs=["adult"],
        symptom_names_or_slugs=None,
        specialty_slugs=None,
        meta_extra=None,
    )

    if post_id:
        # Для REST-режима дополнительно уточняем статус существования через /disease/status.
        # Это точнее, чем просто верить сохранённому post_id.
        settings = get_wp_settings()
        if settings.method == "rest":
            try:
                from wp_rest_client import get_disease_status_rest

                st = get_disease_status_rest(
                    base_url=settings.rest_base_url,
                    username=settings.rest_username,
                    app_password=settings.rest_app_password,
                    verify_ssl=settings.rest_verify_ssl,
                    external_id=external_id,
                )
                exists = bool(st.get("exists"))
                st_post_id = st.get("post_id")
                db_module.set_article_wp_status(
                    article_id,
                    exported_to_wp=exists,
                    wp_post_id=str(st_post_id) if st_post_id else str(post_id),
                )
            except Exception:
                db_module.mark_article_exported(article_id, str(post_id))
        else:
            db_module.mark_article_exported(article_id, str(post_id))
        return True
    return False


def _collect_articles_for_export() -> list[dict]:
    """Возвращает все скачанные статьи для массовой выгрузки/обновления."""
    return [{"article_id": article["article_id"]} for article in db_module.get_all_articles()]


@wp_export_bp.route("/articles/<article_id>/export_wp", methods=["POST"])
def export_article_to_wp(article_id):
    _export_single_article_to_wp(article_id)
    return redirect(url_for("article_detail", article_id=article_id))


@wp_export_bp.route("/control/export_wp_all", methods=["POST"])
def export_all_not_exported_to_wp():
    """Выгружает/обновляет в WordPress все скачанные статьи."""
    articles = _collect_articles_for_export()
    if not articles:
        return redirect(url_for("control_page", notice="Нет статей для выгрузки в WordPress."))

    success_count = 0
    fail_count = 0
    for row in articles:
        try:
            if _export_single_article_to_wp(row["article_id"]):
                success_count += 1
            else:
                fail_count += 1
        except Exception as exc:
            fail_count += 1
            print(f"[wp-export] Ошибка выгрузки статьи {row['article_id']}: {exc}")

    notice = f"Выгрузка завершена: успешно {success_count}, ошибок {fail_count}."
    return redirect(url_for("control_page", notice=notice))
