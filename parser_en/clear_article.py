import re
from typing import Optional, Tuple
from urllib.parse import urlparse

from bs4 import BeautifulSoup


SOURCE_LINK_HOSTS_TO_UNWRAP = {
    "uptodate.com",
    "www.uptodate.com",
    "utd.libook.xyz",
}

INTERNAL_SOURCE_ARTICLE_PATH = "/eng-articles/source"
SOURCE_TOPIC_RE = re.compile(r"^/contents/topics/(\d+)/?$")


def _should_unwrap_source_link(href: str) -> bool:
    parsed = urlparse((href or "").strip())
    host = (parsed.netloc or "").lower().split("@")[-1].split(":")[0]
    return host in SOURCE_LINK_HOSTS_TO_UNWRAP


def _extract_source_topic_id(href: str) -> Optional[Tuple[str, str]]:
    parsed = urlparse((href or "").strip())
    host = (parsed.netloc or "").lower().split("@")[-1].split(":")[0]

    if host and host not in SOURCE_LINK_HOSTS_TO_UNWRAP:
        return None

    match = SOURCE_TOPIC_RE.match(parsed.path.rstrip("/"))
    if not match:
        return None

    return match.group(1), parsed.fragment


def _build_internal_source_article_href(topic_id: str, fragment: str = "") -> str:
    href = f"{INTERNAL_SOURCE_ARTICLE_PATH}/{topic_id}/"
    if fragment:
        href += f"#{fragment}"
    return href


def _add_class(tag, class_name: str) -> None:
    classes = tag.get("class") or []
    if isinstance(classes, str):
        classes = classes.split()
    if class_name not in classes:
        classes.append(class_name)
    tag["class"] = classes


def build_uptodate_article(raw_html: str, include_title: bool = True):
    soup = BeautifulSoup(raw_html, "html.parser")

    # ------------------------------------------------
    # TITLE — извлекаем ДО чистки атрибутов,
    # чтобы сохранить все теги и текст как есть
    # ------------------------------------------------

    title = ""
    title_el = soup.find(id="topicTitle")

    if title_el:
        # Удаляем служебные теги (кнопки, иконки, svg)
        REMOVE_TAGS = {"button", "svg", "path", "use", "img"}
        for sub in title_el.find_all(list(REMOVE_TAGS), recursive=True):
            sub.decompose()
        # Span и прочие контейнеры — разворачиваем, сохраняя текст
        KEEP_TAGS = {"em", "strong", "sup", "sub", "b", "i"}
        for sub in title_el.find_all(True, recursive=True):
            if sub.name not in KEEP_TAGS:
                sub.unwrap()
        title = title_el.decode_contents().strip()
        if not title:
            title = title_el.get_text(strip=True)
        title_el.decompose()

    # ------------------------------------------------
    # CLEAN ATTRIBUTES
    # ------------------------------------------------

    for tag in soup.find_all(True):

        if tag.has_attr("class"):
            tag["class"] = [c for c in tag["class"] if not c.startswith("Mui")]

            if not tag["class"]:
                del tag["class"]

        for attr in list(tag.attrs):

            if attr.startswith("data-") or attr.startswith("aria-"):
                del tag.attrs[attr]

        if "style" in tag.attrs:
            del tag.attrs["style"]

    # ------------------------------------------------
    # FIX IMG PREVIEW STYLE
    # ------------------------------------------------

    for div in soup.find_all("div", class_="img_preview"):
        for img in div.find_all("img"):
            img["style"] = "max-width:100%;"

    # ------------------------------------------------
    # REMOVE USELESS BLOCKS
    # ------------------------------------------------

    for sel in [
        "#graphicVersion",
        "#topicVersionRevision",
        "#reviewProcess",
        "#literatureReviewDate",
        ".disclosureLink",
        ".img_zoom_btn"
    ]:
        for el in soup.select(sel):
            el.decompose()

    for el in soup.select(".headingEndMark"):
        el.decompose()

    for el in soup.find_all("hr"):
        el.decompose()

    # ------------------------------------------------
    # REMOVE GRAPHIC COUNTERS FROM ARTICLE
    # ------------------------------------------------

    for h in soup.find_all("h3"):
        if re.match(r"\d+\s*Of\s*\d+", h.get_text(strip=True)):
            h.decompose()

    # ------------------------------------------------
    # FIX href="#"
    # ------------------------------------------------

    for a in soup.find_all("a", href="#"):
        a.string = "see in materials"
        a["href"] = "javascript:void(0)"
        a["onclick"] = "openTab('graphics'); return false;"

    # ------------------------------------------------
    # REWRITE INTERNAL SOURCE ARTICLE LINKS
    # ------------------------------------------------

    for a in soup.find_all("a", href=True):
        href = a.get("href")
        source_topic = _extract_source_topic_id(href)

        if source_topic:
            topic_id, fragment = source_topic
            a["href"] = _build_internal_source_article_href(topic_id, fragment)
            a["data-source-topic-id"] = topic_id
            _add_class(a, "medvise-internal-source-link")
            a.attrs.pop("target", None)
            a.attrs.pop("rel", None)
        elif _should_unwrap_source_link(href):
            a.unwrap()

    # ------------------------------------------------
    # AUTHORS
    # ------------------------------------------------

    authors_html = ""
    a = soup.find(id="topicContributors")

    if a:
        names = [x.get_text(strip=True) for x in a.find_all("a")]

        authors_html = "<ul class='authors'>" + "".join(
            f"<li>{n}</li>" for n in names
        ) + "</ul>"

        a.decompose()

    # ------------------------------------------------
    # WRAP INLINE TABLES with scroll container
    # ------------------------------------------------

    for tbl in soup.find_all("table"):
        container = soup.new_tag("div")
        container["class"] = "table-scroll"
        tbl.insert_before(container)
        tbl.extract()
        container.append(tbl)

    # ------------------------------------------------
    # GRAPHICS
    # ------------------------------------------------

    graphics = []

    for g in soup.select(".graphic"):

        ttl = g.select_one(".ttl")

        g_title = ""

        if ttl:
            g_title = ttl.get_text(strip=True)
            ttl.decompose()

        graphics.append((g_title, str(g)))

        g.decompose()

    graphics_html = ""

    if graphics:

        slides = ""

        for i, (g_title, g) in enumerate(graphics, 1):
            slides += f"""
    <div class="slide" id="graphic{i}">
    <h3 class="graphicTitle">{g_title}</h3>
    {g}
    </div>
    """

        graphics_html = f"""
    <div class="graphicsHeader">
    <button onclick="slide(-1)">&#10094;</button>
    <span id="slideIndex"></span>
    <button onclick="slide(1)">&#10095;</button>
    </div>

    <div class="slides">
    {slides}
    </div>
    """

    # ------------------------------------------------
    # REFERENCES
    # ------------------------------------------------

    references_html = ""

    ref = soup.find(id="references")

    if ref:
        references_html = str(ref)
        ref.decompose()

    # ------------------------------------------------
    # BULLET FIX
    # ------------------------------------------------

    for p in soup.find_all("p", class_=re.compile(r"bulletIndent")):

        glyph = p.find("span", class_="glyph")

        if glyph:

            glyph.extract()

            li = soup.new_tag("li")

            for c in list(p.children):
                li.append(c)

            ul = soup.new_tag("ul")
            ul["class"] = "bullet"

            ul.append(li)

            p.replace_with(ul)

        else:
            del p["class"]

    # ------------------------------------------------
    # HEADINGS
    # ------------------------------------------------

    for el in soup.select('[role="heading"]'):

        heading_level = 2

        if el.has_attr("class"):
            for cls in el["class"]:
                if cls.startswith("h") and cls[1:].isdigit():
                    level = int(cls[1:])
                    if 1 <= level <= 6:
                        heading_level = level
                        break

        h = soup.new_tag(f"h{heading_level}")
        h.string = el.get_text(strip=True)

        el.replace_with(h)

    # ------------------------------------------------
    # CONTENTS (TOC)
    # ------------------------------------------------

    toc = "<ul class='toc'>"
    section_counter = 0

    for h in soup.find_all(["h1", "h2", "h3", "h4", "h5", "h6"]):
        section_counter += 1
        hid = f"section{section_counter}"
        h["id"] = hid

        level = int(h.name[1])
        indent_class = f"toc-level-{level}"

        toc += f"<li class='{indent_class}'><a href='#{hid}'>{h.get_text()}</a></li>"

    toc += "</ul>"

    sidebar_html = f"""
    <div class="sidebar-wrapper" id="sidebarWrapper">
        <div class="sidebar" id="articleSidebar">
            <h3>Contents</h3>
            {toc}
        </div>
        <button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle sidebar">
            <span class="toggle-icon">&#8249;</span>
        </button>
    </div>
    <div class="sidebar-placeholder" id="sidebarPlaceholder"></div>
    """

    article_html = str(soup)

    # ------------------------------------------------
    # CSS
    # ------------------------------------------------

    css = """
<style>

*{
box-sizing:border-box;
}

html{
overflow-x:clip; /* clip не создаёт scroll container — sticky работает */
}

body{
font-family:Georgia,'Times New Roman',serif;
margin:0;
background:#f0f2f5;
font-size:16px;
line-height:1.7;
color:#2d2d2d;
}

.libook-parser-article{
width:100%;
max-width:1280px;
margin:0 auto;
font-family:Georgia,'Times New Roman',serif;
font-size:16px;
line-height:1.7;
color:#2d2d2d;
}

.libook-article-container{
max-width:1280px;
width:100%;
margin:0 auto;
background:#fff;
padding:32px 24px;
overflow-x:hidden;
}

#main.has-libook-parser-article,
#main:has(.libook-parser-article){
max-width:1280px !important;
width:100% !important;
}

#main.has-libook-parser-article .entry-content,
.entry-content.has-libook-parser-article,
article.has-libook-parser-article,
article.has-libook-parser-article .main-post{
max-width:none !important;
width:100% !important;
}

/* ── TABS ─────────────────────────────────────── */

.tabs{
display:flex;
border-bottom:2px solid #e0e4ea;
margin-bottom:28px;
flex-wrap:wrap;
gap:2px;
}

.tabs button{
background:none;
border:none;
border-bottom:3px solid transparent;
margin-bottom:-2px;
padding:11px 20px;
cursor:pointer;
font-size:14px;
font-family:Arial,Helvetica,sans-serif;
font-weight:600;
color:#666;
letter-spacing:0.03em;
transition:color 0.2s,border-color 0.2s;
}

.tabs button:hover{color:#1565c0;}

.tabs button.active{
border-bottom-color:#1565c0;
color:#1565c0;
}

.tab{display:none;}
.tab.active{display:block;}

/* ── ARTICLE LAYOUT ───────────────────────────── */

.article-layout{
display:flex;
gap:0;
position:relative;
align-items:flex-start;
width:100%;
}

/* ── SIDEBAR WRAPPER ──────────────────────────── */

.sidebar-wrapper{
/* JS делает его fixed — из потока выходит.
   Место держит .sidebar-placeholder рядом */
flex-shrink:0;
width:260px;
transition:width 0.3s ease;
position:relative;
}

.sidebar-wrapper.collapsed{
width:16px;
}

/* Невидимый резервный блок той же ширины что wrapper */
.sidebar-placeholder{
flex-shrink:0;
width:260px;
transition:width 0.3s ease;
pointer-events:none;
}

.sidebar-wrapper.collapsed ~ .sidebar-placeholder,
.sidebar-placeholder.collapsed{
width:16px;
}

/* ── SIDEBAR ──────────────────────────────────── */

.sidebar{
width:260px;
/* Позиция управляется враппером (fixed через JS).
   max-height выставляется динамически в positionSidebar() */
position:relative;
overflow-y:auto;
overflow-x:hidden;
background:#f8fafc;
border:1px solid #dde3ed;
border-right:none;
border-radius:8px 0 0 8px;
padding:18px 16px;
transition:opacity 0.25s ease,visibility 0.25s ease;
scrollbar-width:thin;
scrollbar-color:#c5cfe0 transparent;
}

.sidebar::-webkit-scrollbar{width:4px;}
.sidebar::-webkit-scrollbar-track{background:transparent;}
.sidebar::-webkit-scrollbar-thumb{background:#c5cfe0;border-radius:2px;}

.sidebar-wrapper.collapsed .sidebar{
opacity:0;
visibility:hidden;
pointer-events:none;
}

.sidebar h3{
margin:0 0 14px 0;
font-size:11px;
font-family:Arial,Helvetica,sans-serif;
font-weight:700;
text-transform:uppercase;
letter-spacing:0.1em;
color:#8a95a8;
padding-bottom:10px;
border-bottom:1px solid #e0e6f0;
}

/* ── SIDEBAR TOGGLE — полоса по всей высоте ──── */

.sidebar-toggle{
position:absolute;
right:-16px;
top:0;
bottom:0;
width:16px;
background:#e8edf5;
border:1px solid #dde3ed;
border-left:none;
border-radius:0 6px 6px 0;
cursor:pointer;
display:flex;
align-items:center;
justify-content:center;
padding:0;
margin:0;
transition:background 0.2s;
}

.sidebar-toggle:hover{background:#d0d8e8;}

.sidebar-toggle .toggle-icon{
font-size:18px;
color:#5a6882;
line-height:1;
display:block;
transition:transform 0.3s ease;
user-select:none;
}

.sidebar-wrapper.collapsed .sidebar-toggle .toggle-icon{
transform:rotate(180deg);
}

/* ── ARTICLE CONTENT ──────────────────────────── */

.article-content{
flex:1;
min-width:0;
max-width:none;
width:100%;
padding-left:28px;
border-left:1px solid #e8edf5;
transition:padding 0.3s ease;
}

.sidebar-wrapper.collapsed ~ .article-content{
padding-left:20px;
}

/* ── TOC ──────────────────────────────────────── */

.toc{list-style:none;padding:0;margin:0;}
.toc li{margin:0;}

.toc li a{
text-decoration:none;
color:#3d6498;
font-size:13px;
font-family:Arial,Helvetica,sans-serif;
line-height:1.45;
display:block;
padding:4px 6px;
border-radius:4px;
transition:background 0.15s,color 0.15s;
}

.toc li a:hover{background:#e8f0fe;color:#1a3a6b;}
.toc-level-1 > a{font-weight:700;font-size:13px;color:#1e3050;}
.toc-level-2{padding-left:12px;}
.toc-level-3{padding-left:24px;}
.toc-level-3 > a{font-size:12px;color:#5a7299;}
.toc-level-4{padding-left:36px;}
.toc-level-4 > a{font-size:12px;color:#7a8eaa;}
.toc-level-5,.toc-level-6{padding-left:48px;}
.toc-level-5 > a,.toc-level-6 > a{font-size:11px;color:#8a99b0;}

/* ── TYPOGRAPHY ───────────────────────────────── */

.authors{
list-style:none;padding:0;text-align:center;
margin-bottom:28px;color:#666;
font-family:Arial,Helvetica,sans-serif;font-size:14px;
}
.authors li{display:inline-block;margin:4px 10px;}

h1{
text-align:center;margin-bottom:24px;
font-size:26px;line-height:1.3;color:#1a2540;
font-family:Georgia,'Times New Roman',serif;
}
h2{
margin-top:36px;margin-bottom:12px;
border-bottom:2px solid #1565c0;padding-bottom:6px;
font-size:20px;color:#1a2540;
font-family:Georgia,'Times New Roman',serif;
}
h3{
margin-top:28px;margin-bottom:10px;
border-bottom:1px solid #e0e4ea;padding-bottom:4px;
font-size:17px;color:#2a3a5a;
font-family:Georgia,'Times New Roman',serif;
}
h4{margin-top:22px;margin-bottom:8px;font-size:15px;color:#3a4e6a;font-weight:700;font-family:Arial,Helvetica,sans-serif;}
h5{margin-top:18px;margin-bottom:6px;font-size:14px;color:#4a5e7a;font-weight:700;font-family:Arial,Helvetica,sans-serif;}
h6{margin-top:16px;margin-bottom:6px;font-size:13px;color:#5a6e8a;font-weight:700;font-family:Arial,Helvetica,sans-serif;}

p{margin:0 0 14px 0;}
.bullet{margin:8px 0 8px 20px;padding:0;}
.bullet li{margin-bottom:5px;}

/* ── IMAGES IN ARTICLE ────────────────────────── */

/* Ограниченный размер по умолчанию; клик → lightbox */
.article-content img,
.img_preview img{
max-width:460px;
width:100%;
height:auto;
display:block;
margin:12px auto;
border-radius:4px;
cursor:zoom-in;
border:1px solid #e0e4ea;
transition:box-shadow 0.2s;
}

.article-content img:hover,
.img_preview img:hover{
box-shadow:0 2px 12px rgba(0,0,0,0.15);
}

/* Контейнер картинки — тоже центрируем */
.img_preview{
display:block;
max-width:460px;
width:100%;
margin:0 auto;
text-align:center;
}

/* ── TABLES IN ARTICLE ────────────────────────── */

.table-scroll{
overflow-x:auto;
-webkit-overflow-scrolling:touch;
margin:16px 0;
border:1px solid #dde3ed;
border-radius:6px;
max-width:100%;
width:100%;
transition:box-shadow 0.2s;
position:relative;
}

.table-scroll:hover{
box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

/* Кнопка открытия в лайтбоксе */
.table-expand-btn{
display:inline-flex;
align-items:center;
gap:5px;
margin-bottom:6px;
padding:4px 10px;
background:#f0f4fb;
border:1px solid #c5d0e8;
border-radius:4px;
font-size:11px;
font-family:Arial,Helvetica,sans-serif;
color:#3d6498;
cursor:pointer;
transition:background 0.15s;
}

.table-expand-btn:hover{
background:#dce6f8;
}

.table-scroll table{
border-collapse:collapse;
font-size:6px;
font-family:Arial,Helvetica,sans-serif;
width:100%;
line-height:1.1;
}

.article-content table th,
.article-content table td{
border:1px solid #e0e4ec;
padding:1px 4px;
vertical-align:top;
background:#fff;
}

/* subtitle1 — основной заголовок столбца (тёмный фон) */
.article-content table td.subtitle1,
.article-content table th.subtitle1{
background-color:#2c3e6b;
color:#fff;
font-weight:700;
font-family:Arial,Helvetica,sans-serif;
}

/* subtitle2_left — заголовок группы (светлый фон, жирный) */
.article-content table td.subtitle2_left,
.article-content table th.subtitle2_left{
background-color:#e8edf5;
color:#1e3050;
font-weight:700;
font-family:Arial,Helvetica,sans-serif;
}

/* subtitle3_left — подзаголовок (очень светлый, курсив) */
.article-content table td.subtitle3_left,
.article-content table th.subtitle3_left{
background-color:#f4f6fb;
color:#3a4e6a;
font-weight:600;
font-style:italic;
font-family:Arial,Helvetica,sans-serif;
}

/* indent1 / indent2 — отступы для вложенных строк */
.article-content table td.indent1{
padding-left:18px;
}

.article-content table td.indent2{
padding-left:30px;
}

/* border_bottom_white — убираем нижнюю границу (объединённые ячейки) */
.article-content table td.border_bottom_white{
border-bottom-color:#fff;
}

/* ── GRAPHICS TAB ─────────────────────────────── */

.graphicsHeader{
display:flex;align-items:center;justify-content:center;
gap:16px;margin-bottom:16px;
}

.graphicsHeader button{
background:#1565c0;color:#fff;border:none;
width:36px;height:36px;border-radius:50%;
font-size:16px;cursor:pointer;
transition:background 0.2s;
display:flex;align-items:center;justify-content:center;
}

.graphicsHeader button:hover{background:#0d47a1;}

#slideIndex{
font-family:Arial,Helvetica,sans-serif;
font-size:14px;color:#555;
min-width:60px;text-align:center;
}

.slide{display:none;}
.slide.active{display:block;}

.graphicTitle{
font-weight:bold;margin-bottom:12px;border:none !important;
}

/* Tables in graphics tab */
#graphics .table-scroll{
max-width:100%;
}

#graphics .table-scroll table{
width:100%;
font-size:14px;
}

#graphics table th,
#graphics table td{
border:1px solid #dde3ed;
padding:10px 14px;
font-family:Arial,Helvetica,sans-serif;
font-size:7px;
vertical-align:top;
background:#fff;
}

/* subtitle-классы в graphics */
#graphics table td.subtitle1,
#graphics table th.subtitle1{
background-color:#2c3e6b;color:#fff;font-weight:700;
}
#graphics table td.subtitle2_left,
#graphics table th.subtitle2_left{
background-color:#e8edf5;color:#1e3050;font-weight:700;
}
#graphics table td.subtitle3_left,
#graphics table th.subtitle3_left{
background-color:#f4f6fb;color:#3a4e6a;font-weight:600;font-style:italic;
}
#graphics table td.indent1{padding-left:18px;}
#graphics table td.indent2{padding-left:30px;}
#graphics table td.border_bottom_white{border-bottom-color:#fff;}

#graphics img{
max-width:100%;height:auto;
display:block;margin:12px auto;
cursor:zoom-in;
border-radius:4px;border:1px solid #e0e4ea;
}

/* ── TABLE LIGHTBOX ───────────────────────────── */

.table-lightbox-overlay{
display:none;
position:fixed;inset:0;
background:rgba(0,0,0,0.82);
z-index:9998;
align-items:center;
justify-content:center;
padding:20px;
}

.table-lightbox-overlay.open{
display:flex;
}

.table-lightbox-inner{
background:#fff;
border-radius:8px;
max-width:96vw;
max-height:92vh;
overflow:auto;
-webkit-overflow-scrolling:touch;
padding:20px;
position:relative;
box-shadow:0 8px 40px rgba(0,0,0,0.5);
}

.table-lightbox-inner table{
border-collapse:collapse;
font-size:14px;
font-family:Arial,Helvetica,sans-serif;
min-width:600px;
}

.table-lightbox-inner table th,
.table-lightbox-inner table td{
border:1px solid #dde3ed;
padding:8px 12px;
vertical-align:top;
background:#fff;
}

/* Те же классы в лайтбоксе */
.table-lightbox-inner table td.subtitle1,
.table-lightbox-inner table th.subtitle1{
background-color:#2c3e6b;color:#fff;font-weight:700;
}
.table-lightbox-inner table td.subtitle2_left,
.table-lightbox-inner table th.subtitle2_left{
background-color:#e8edf5;color:#1e3050;font-weight:700;
}
.table-lightbox-inner table td.subtitle3_left,
.table-lightbox-inner table th.subtitle3_left{
background-color:#f4f6fb;color:#3a4e6a;font-weight:600;font-style:italic;
}
.table-lightbox-inner table td.indent1{padding-left:18px;}
.table-lightbox-inner table td.indent2{padding-left:30px;}
.table-lightbox-inner table td.border_bottom_white{border-bottom-color:#fff;}

.table-lightbox-close{
position:sticky;
top:0;
float:right;
margin:-4px -8px 8px 0;
background:none;border:none;
color:#555;font-size:26px;
cursor:pointer;
line-height:1;
transition:color 0.2s;
z-index:1;
}

.table-lightbox-close:hover{color:#1a2540;}

/* ── LIGHTBOX ─────────────────────────────────── */

.lightbox-overlay{
display:none;
position:fixed;inset:0;
background:rgba(0,0,0,0.88);
z-index:9999;
align-items:center;
justify-content:center;
cursor:zoom-out;
}

.lightbox-overlay.open{display:flex;}

.lightbox-overlay img{
max-width:92vw;
max-height:92vh;
border-radius:6px;
box-shadow:0 8px 40px rgba(0,0,0,0.6);
object-fit:contain;
cursor:default;
}

.lightbox-close{
position:absolute;
top:18px;right:24px;
background:none;border:none;
color:#fff;font-size:32px;
cursor:pointer;
line-height:1;opacity:0.8;
transition:opacity 0.2s;
}

.lightbox-close:hover{opacity:1;}

/* ── MOBILE TOC ───────────────────────────────── */

.mobile-toc-toggle{
display:none;
width:100%;
background:#f0f4fb;
border:1px solid #dde3ed;
border-radius:6px;
padding:10px 16px;
margin-bottom:16px;
font-family:Arial,Helvetica,sans-serif;
font-size:14px;font-weight:600;color:#1a3a6b;
cursor:pointer;text-align:left;
align-items:center;justify-content:space-between;
}

.mobile-toc-toggle .toc-toggle-icon{
font-size:12px;
transition:transform 0.25s;
}

.mobile-toc-toggle.open .toc-toggle-icon{
transform:rotate(180deg);
}

.mobile-toc-panel{
display:none;
background:#f8fafc;
border:1px solid #dde3ed;
border-top:none;
border-radius:0 0 6px 6px;
padding:14px 16px;
margin-top:-16px;
margin-bottom:20px;
}

.mobile-toc-panel.open{display:block;}

/* ── RESPONSIVE ───────────────────────────────── */

@media (max-width:900px){

.libook-article-container{padding:20px 16px;}

/* Скрываем десктопный сайдбар */
.sidebar-wrapper{display:none;}
.sidebar-placeholder{display:none;}

.article-content{
padding-left:0;
border-left:none;
}

/* Показываем мобильное меню оглавления */
.mobile-toc-toggle{display:flex;}

/* Изображения не вылезают за экран */
.article-content img,
.img_preview img,
.img_preview{max-width:100%;}

h1{font-size:22px;}
h2{font-size:18px;}
h3{font-size:16px;}
h4,h5,h6{font-size:14px;}

}

@media (max-width:600px){

.libook-article-container{padding:14px 12px;}
body{font-size:15px;}
h1{font-size:19px;}
h2{font-size:16px;margin-top:26px;}
h3{font-size:15px;}

.tabs button{padding:9px 12px;font-size:13px;}

/* Таблицы на мобиле — горизонтальный скролл внутри обёртки */
.article-content .table-scroll,
#graphics .table-scroll{
max-width:calc(100vw - 28px);
}

.article-content table,
#graphics table{
min-width:360px;
}

.article-content table th,
.article-content table td,
#graphics table th,
#graphics table td{
padding:6px 8px;
font-size:12px;
white-space:nowrap;
}

.graphicsHeader button{width:32px;height:32px;font-size:14px;}

/* table-lightbox на мобиле — полный экран */
.table-lightbox-inner{
max-width:100vw;
max-height:100vh;
border-radius:0;
padding:12px;
}

.table-lightbox-inner table{
min-width:400px;
font-size:13px;
}

}

@media (max-width:400px){

.libook-article-container{padding:10px 8px;}
h1{font-size:17px;}
h2{font-size:15px;}

.tabs button{padding:8px 10px;font-size:12px;}

.article-content .table-scroll,
#graphics .table-scroll{
max-width:calc(100vw - 20px);
}

}

</style>
"""

    # ------------------------------------------------
    # JS
    # ------------------------------------------------

    js = """
<script>

/* TABS */
function openTab(id){
    document.querySelectorAll(".tab").forEach(t=>t.classList.remove("active"));
    document.querySelectorAll(".tabs button").forEach(t=>t.classList.remove("active"));
    document.getElementById(id).classList.add("active");
    document.querySelector('[data-tab="'+id+'"]').classList.add("active");
    if(id === 'article'){
        refreshArticleSidebar();
    }else{
        const wrapper = document.getElementById('sidebarWrapper');
        if(wrapper) wrapper.style.display = 'none';
    }
}

/* GRAPHICS SLIDESHOW */
let cur = 0;

function showSlide(i){
    let s = document.querySelectorAll(".slide:not(.debug-bar-wp-query-list .slide)");
    if(!s.length) return;
    if(i >= s.length) cur = 0;
    if(i < 0) cur = s.length - 1;
    s.forEach(x=>x.classList.remove("active"));
    s[cur].classList.add("active");
    document.getElementById("slideIndex").innerText = (cur+1) + " of " + s.length;
}

function slide(n){ cur += n; showSlide(cur); }

/* DESKTOP SIDEBAR — fixed с вычисленными координатами */
function positionSidebar(){
    const wrapper     = document.getElementById('sidebarWrapper');
    const placeholder = document.getElementById('sidebarPlaceholder');
    if(!wrapper) return;

    const articleTab = document.getElementById('article');
    if(articleTab && !articleTab.classList.contains('active')){
        wrapper.style.display = 'none';
        return;
    }

    /* Мобиле — всё сбрасываем */
    if(window.innerWidth <= 900){
        wrapper.style.cssText = '';
        if(placeholder) placeholder.style.cssText = 'display:none';
        return;
    }

    /* Кешируем ПОЛНУЮ ширину и координаты один раз */
    if(!wrapper._origLeft){
        const wasCollapsed = wrapper.classList.contains('collapsed');
        wrapper.classList.remove('collapsed');
        wrapper.style.position = 'static';
        wrapper.style.width    = '';
        if(placeholder) placeholder.style.display = 'none';

        const r  = wrapper.getBoundingClientRect();
        const sy = window.scrollY || window.pageYOffset;
        wrapper._origLeft  = r.left + (window.scrollX || window.pageXOffset);
        wrapper._origTop   = r.top  + sy;
        wrapper._fullWidth = wrapper.offsetWidth;

        if(wasCollapsed) wrapper.classList.add('collapsed');
        if(placeholder) placeholder.style.display = '';
    }

    const collapsed = wrapper.classList.contains('collapsed');
    const visWidth  = collapsed ? 16 : wrapper._fullWidth;

    /* Placeholder держит место в flex-потоке */
    if(placeholder){
        placeholder.style.display    = '';
        placeholder.style.width      = visWidth + 'px';
        placeholder.style.flexShrink = '0';
    }

    /* Если sentinel вышел из вьюпорта (конец статьи) — скрываем wrapper */
    if(wrapper._sentinelGone){
        wrapper.style.display = 'none';
        return;
    }
    wrapper.style.display = '';

    const scrollY = window.scrollY || window.pageYOffset;
    const topPx   = Math.max(wrapper._origTop - scrollY, 20);

    /* Ограничиваем высоту sidebar видимой частью article-layout.
       layout.getBoundingClientRect().bottom — низ layout в координатах вьюпорта.
       Если он ниже вьюпорта — берём innerHeight.
       max-height = расстояние от topPx до низа видимой части layout. */
    const layout   = document.querySelector('.article-layout');
    const innerSb  = document.getElementById('articleSidebar');
    if(layout && innerSb){
        const layoutBottom = Math.min(layout.getBoundingClientRect().bottom, window.innerHeight);
        const availH = Math.max(layoutBottom - topPx, 0);
        innerSb.style.maxHeight = availH + 'px';
    }

    wrapper.style.position = 'fixed';
    wrapper.style.left     = wrapper._origLeft + 'px';
    wrapper.style.top      = topPx + 'px';
    wrapper.style.width    = visWidth + 'px';
    wrapper.style.zIndex   = '200';
}

function resetSidebarMeasurements(){
    const wrapper = document.getElementById('sidebarWrapper');
    const placeholder = document.getElementById('sidebarPlaceholder');
    const innerSb = document.getElementById('articleSidebar');
    if(!wrapper) return;

    delete wrapper._origLeft;
    delete wrapper._origTop;
    delete wrapper._fullWidth;
    wrapper._sentinelGone = false;
    wrapper.style.cssText = '';

    if(placeholder) placeholder.style.cssText = '';
    if(innerSb) innerSb.style.maxHeight = '';
}

function refreshArticleSidebar(){
    resetSidebarMeasurements();
    requestAnimationFrame(function(){
        positionSidebar();
        setTimeout(positionSidebar, 80);
    });
}

function toggleSidebar(){
    const w = document.getElementById('sidebarWrapper');
    if(!w) return;
    w.classList.toggle('collapsed');
    /* Обновляем placeholder сразу и ещё раз после анимации CSS */
    positionSidebar();
    setTimeout(positionSidebar, 320);
    try{ localStorage.setItem('sidebarCollapsed', w.classList.contains('collapsed')?'true':'false'); }catch(e){}
}

function restoreSidebarState(){
    const w = document.getElementById('sidebarWrapper');
    if(!w) return;
    try{ if(localStorage.getItem('sidebarCollapsed')==='true') w.classList.add('collapsed'); }catch(e){}
}

function markEmbeddedArticleLayout(){
    const root = document.querySelector('.libook-parser-article');
    if(!root) return;
    const main = root.closest('#main');
    const entry = root.closest('.entry-content');
    const article = root.closest('article.blog-single');
    if(main) main.classList.add('has-libook-parser-article');
    if(entry) entry.classList.add('has-libook-parser-article');
    if(article) article.classList.add('has-libook-parser-article');
}

/* MOBILE TOC */
function toggleMobileToc(){
    const btn   = document.getElementById('mobileTocBtn');
    const panel = document.getElementById('mobileTocPanel');
    if(!btn||!panel) return;
    const open = panel.classList.toggle('open');
    btn.classList.toggle('open', open);
}

/* LIGHTBOX */
function openLightbox(src){
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxOverlay').classList.add('open');
}

function closeLightbox(){
    document.getElementById('lightboxOverlay').classList.remove('open');
}

/* TABLE LIGHTBOX */
function openTableLightbox(html){
    document.getElementById('tableLightboxContent').innerHTML = html;
    document.getElementById('tableLightboxOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeTableLightbox(){
    document.getElementById('tableLightboxOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

/* Вешаем кнопку «открыть» над каждой таблицей */
function initTableZoom(){
    document.querySelectorAll('.table-scroll').forEach(function(wrap){
        var tbl = wrap.querySelector('table');
        if(!tbl) return;
        var btn = document.createElement('button');
        btn.className = 'table-expand-btn';
        btn.innerHTML = '&#x26F6; Expand table';
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            openTableLightbox(tbl.outerHTML);
        });
        wrap.parentNode.insertBefore(btn, wrap);
    });
}

/* IMAGE CLICK → lightbox */
function initImageZoom(){
    document.querySelectorAll('.article-content img, #graphics img').forEach(function(img){
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', function(){ openLightbox(img.src); });
    });
}

window.onload = function(){
    openTab("article");
    showSlide(0);
    restoreSidebarState();
    markEmbeddedArticleLayout();
    positionSidebar();
    initTableZoom();
    initImageZoom();

    /* IntersectionObserver на sentinel в конце article-layout.
       Как только sentinel уходит за нижний край вьюпорта —
       sidebar скрывается. Как возвращается — показывается снова. */
    const sentinel = document.getElementById('sidebarSentinel');
    const wrapper  = document.getElementById('sidebarWrapper');
    if(sentinel && wrapper && window.IntersectionObserver){
        const obs = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                /* sentinel ушёл ВВЕРХ за вьюпорт — значит пользователь
                   проскроллил ниже конца статьи → скрываем sidebar.
                   top > 0 когда sentinel ещё ниже вьюпорта (не доскроллили) */
                const scrolledPastArticle = !entry.isIntersecting
                    && entry.boundingClientRect.top <= 0;
                wrapper._sentinelGone = scrolledPastArticle;
                positionSidebar();
            });
        }, { threshold: 0 });
        obs.observe(sentinel);
    }

    window.addEventListener('resize', function(){
        const w = document.getElementById('sidebarWrapper');
        if(w){ delete w._origLeft; delete w._fullWidth; }
        positionSidebar();
    });
    window.addEventListener('scroll', positionSidebar, {passive:true});

    /* Закрытие image lightbox кликом по фону или Escape */
    document.getElementById('lightboxOverlay').addEventListener('click', function(e){
        if(e.target===this) closeLightbox();
    });

    /* Закрытие table lightbox */
    document.getElementById('tableLightboxOverlay').addEventListener('click', function(e){
        if(e.target===this) closeTableLightbox();
    });

    document.addEventListener('keydown', function(e){
        if(e.key==='Escape'){ closeLightbox(); closeTableLightbox(); }
    });
};

</script>
"""

    # ------------------------------------------------
    # MOBILE TOC
    # ------------------------------------------------

    mobile_toc_html = f"""
<button class="mobile-toc-toggle" id="mobileTocBtn" onclick="toggleMobileToc()">
    Contents
    <span class="toc-toggle-icon">&#9660;</span>
</button>
<div class="mobile-toc-panel" id="mobileTocPanel">
    {toc}
</div>
"""

    # ------------------------------------------------
    # LIGHTBOX HTML
    # ------------------------------------------------

    lightbox_html = """
<div class="lightbox-overlay" id="lightboxOverlay">
    <button class="lightbox-close" onclick="closeLightbox()">&#x2715;</button>
    <img id="lightboxImg" src="" alt="">
</div>

<div class="table-lightbox-overlay" id="tableLightboxOverlay">
    <div class="table-lightbox-inner">
        <button class="table-lightbox-close" onclick="closeTableLightbox()">&#x2715;</button>
        <div id="tableLightboxContent"></div>
    </div>
</div>
"""

    # ------------------------------------------------
    # FINAL HTML
    # ------------------------------------------------

    title_html = f"<h1>{title}</h1>" if include_title and title else ""

    html = f"""
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
{css}
</head>

<body>

<div class="libook-parser-article">
<div class="libook-article-container">

{title_html}

<div class="tabs">
<button data-tab="article" onclick="openTab('article')">Article</button>
<button data-tab="graphics" onclick="openTab('graphics')">Graphics</button>
<button data-tab="authors" onclick="openTab('authors')">Authors</button>
<button data-tab="references" onclick="openTab('references')">References</button>
</div>

<div id="article" class="tab">

{mobile_toc_html}

<div class="article-layout">

{sidebar_html}

<div class="article-content">
{article_html}
</div>

</div>
<div id="sidebarSentinel"></div>

</div>

<div id="graphics" class="tab">
{graphics_html}
</div>

<div id="authors" class="tab">
{authors_html}
</div>

<div id="references" class="tab">
{references_html}
</div>

</div>
</div>

{lightbox_html}

{js}

</body>
</html>
"""

    return html


# Тестирование
'''if __name__ == "__main__":
    import os

    input_file = "created_for_download/article_3347 (4).html"
    output_file = "cleared.html"

    if os.path.exists(input_file):
        print(f"Чтение файла: {input_file}...")
        with open(input_file, "r", encoding="utf-8") as f:
            raw_html = f.read()

        print("Обработка HTML...")
        result_html = build_uptodate_article(raw_html)

        print(f"Сохранение результата в: {output_file}...")
        with open(output_file, "w", encoding="utf-8") as f:
            f.write(result_html)

        print("Готово! ✅")
    else:
        print(f"❌ Ошибка: Файл '{input_file}' не найден.")
        print("Убедитесь, что путь к файлу указан верно.")'''
