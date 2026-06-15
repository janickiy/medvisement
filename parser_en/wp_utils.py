import subprocess
import json
import re
import os
import tempfile
import time

import mysql.connector
from mysql.connector import Error

# === Флаг прямой записи в БД (временно)
DIRECT_DB_UPDATE = True

# === Настройки MySQL WordPress
WP_DB_CONFIG = {
    'host': 'localhost',
    'database': 'dev2_medvisement',
    'user': 'dev2_medvisement',
    'password': '1[-6Fas!@OUK[dpw'
}


WP_PATH = "/home/dev2/web/dev2.medvisement.com/public_html"
WP_USER = "dev2"
META_KEY = "source_id"


def _update_post_content_directly(post_id, content):
    """Прямая запись post_content в MySQL (обход WP-фильтров)."""
    if not DIRECT_DB_UPDATE:
        return

    try:
        connection = mysql.connector.connect(**WP_DB_CONFIG)
        if connection.is_connected():
            cursor = connection.cursor()
            cursor.execute(
                "UPDATE wp_posts SET post_content = %s WHERE ID = %s",
                (content, post_id)
            )
            connection.commit()
            print(f"🔧 Прямая запись контента в БД для записи ID={post_id}")
    except Error as e:
        print(f"❌ Ошибка прямой записи в БД: {e}")
    finally:
        if connection.is_connected():
            cursor.close()
            connection.close()


def _ensure_data_html_dir():
    """Создаёт каталог data/html, если не существует."""
    html_dir = os.path.join(os.path.dirname(__file__), "data", "html")
    os.makedirs(html_dir, exist_ok=True)
    return html_dir


def _map_age_to_slugs(age_str):
    if not age_str:
        return ["adult"]
    age_lower = age_str.lower()
    if "дет" in age_lower:
        return ["child"]
    elif "взр" in age_lower:
        return ["adult"]
    else:
        return ["adult", "child"]


def _map_age_category(age_str):
    """Преобразует строку категории возраста в слаги."""
    if not age_str:
        return ["adult"]  # по умолчанию
    age_str = age_str.lower()
    if "дет" in age_str:
        return ["child"]
    elif "взр" in age_str:
        return ["adult"]
    else:
        return ["adult", "child"]  # если неясно


def run_wp_cli(args):
    env = os.environ.copy()
    env["SERVER_NAME"] = "dev2.medvisement.com"
    env["HTTP_HOST"] = "dev2.medvisement.com"
    cmd = ["wp"] + args + [f"--path={WP_PATH}", f"--user={WP_USER}", "--allow-root"]
    result = subprocess.run(cmd, env=env, capture_output=True, text=True)
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip())
    return result.stdout.strip()


def run_wp_cli_with_stdin(args, stdin_input=None):
    """Выполняет WP-CLI с передачей данных через stdin."""
    env = os.environ.copy()
    env["SERVER_NAME"] = "dev2.medvisement.com"
    env["HTTP_HOST"] = "dev2.medvisement.com"
    cmd = ["wp"] + args + [f"--path={WP_PATH}", f"--user={WP_USER}", "--allow-root"]
    result = subprocess.run(
        cmd,
        env=env,
        input=stdin_input,
        capture_output=True,
        text=True,
        encoding='utf-8'
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip())
    return result.stdout.strip()


def find_post_by_meta(meta_value):
    try:
        output = run_wp_cli([
            "post", "list",
            "--post_type=disease",
            f"--meta_key={META_KEY}",
            f"--meta_value={meta_value}",
            "--field=ID",
            "--format=ids"
        ])
        if output.strip():
            return output.strip().split(",")[0]
    except Exception:
        pass
    return None


def get_or_create_term_slug(taxonomy, name):
    """Возвращает СЛАГ термина по названию. Создаёт, если нужно."""
    name = name.strip()
    if not name:
        return None

    # 1. Ищем существующий термин по имени → получаем его СЛАГ
    try:
        output = run_wp_cli([
            "term", "list", taxonomy,
            "--name=" + name,
            "--field=slug",      # ← КЛЮЧЕВОЕ: именно slug!
            "--format=csv"       # или ids — но главное field=slug
        ])
        lines = [line.strip() for line in output.splitlines() if line.strip()]
        if len(lines) >= 2:  # первая строка — заголовок, если format=csv
            slug = lines[1]
        elif len(lines) == 1:
            slug = lines[0]
        else:
            slug = None

        if slug and slug != "slug":  # избегаем заголовка
            print(f"✅ Найден термин '{name}' → слаг: {slug}")
            return slug
    except Exception as e:
        print(f"🔍 Ошибка поиска термина '{name}': {e}")

    # 2. Создаём новый термин
    print(f"🆕 Создаём термин: '{name}' в '{taxonomy}'")
    run_wp_cli(["term", "create", taxonomy, name])

    # 3. Сразу получаем его слаг
    try:
        output = run_wp_cli([
            "term", "list", taxonomy,
            "--name=" + name,
            "--field=slug",
            "--format=csv"
        ])
        lines = [line.strip() for line in output.splitlines() if line.strip()]
        if len(lines) >= 2:
            slug = lines[1]
        elif len(lines) == 1:
            slug = lines[0]
        else:
            slug = None

        if slug and slug != "slug":
            print(f"✅ После создания: слаг = {slug}")
            return slug
    except Exception as e:
        print(f"⚠️ Не удалось получить слаг после создания: {e}")

    # Fallback: генерируем вручную (редко)
    fallback = re.sub(r'[^a-z0-9\-]+', '-', name.lower()).strip('-')
    print(f"⚠️ Используем fallback слаг: {fallback}")
    return fallback


def upsert_disease_post(
    title,
    content,
    external_id,
    status="draft",
    article_type_slug=None,
    age_slugs=None,
    symptom_names_or_slugs=None,
    specialty_slugs=None,
    meta_extra=None,
    is_english=False,
):
    meta_value = f"pi_{external_id}"
    existing_post_id = find_post_by_meta(meta_value)

    # === Сохраняем HTML в data/html/{external_id}.html ===
    html_dir = _ensure_data_html_dir()
    html_file_path = os.path.join(html_dir, f"{external_id}.html")
    safe_content = content if content and content.strip() else "<p>Контент отсутствует</p>"

    with open(html_file_path, 'w', encoding='utf-8') as f:
        f.write(safe_content)

    # === Метаполя ===
    meta_full = {META_KEY: meta_value}

    # Флаг английской статьи (carbon field, checkbox → '1')
    if is_english:
        meta_full["medvise_is_english_article"] = "1"
    if meta_extra:
        meta_full.update(meta_extra)

    # === Команда без передачи контента в аргументах ===
    base_cmd = [
        f"--post_title={title}",
        f"--post_content_file={html_file_path}",
        f"--post_status={status}",
        f"--meta_input={json.dumps(meta_full)}"
    ]

    try:
        if existing_post_id:
            run_wp_cli(["post", "update", existing_post_id] + base_cmd)
            post_id = existing_post_id
            print(f"🔄 Запись обновлена. ID: {post_id}")
        else:
            post_id = run_wp_cli(["post", "create", "--post_type=disease", "--porcelain"] + base_cmd)
            print(f"✅ Запись создана. ID: {post_id}")
    except Exception as e:
        print(f"❌ Ошибка при работе с записью: {e}")
        return None

    def set_terms_by_slug_only(taxonomy, items):
        if not items:
            return
        slugs = []
        for item in items:
            item = str(item).strip()
            if not item:
                continue
            # Если это уже слаг (латиница/дефисы)
            if re.match(r'^[a-z0-9\-]+$', item):
                # Проверим, существует ли
                try:
                    out = run_wp_cli(["term", "list", taxonomy, f"--slug={item}", "--format=ids"])
                    if out.strip():
                        slugs.append(item)
                    else:
                        print(f"🆕 Создаём термин со слагом '{item}' в '{taxonomy}'")
                        run_wp_cli(["term", "create", taxonomy, item, f"--slug={item}"])
                        slugs.append(item)
                except:
                    pass
            else:
                # Это название — получаем слаг
                slug = get_or_create_term_slug(taxonomy, item)
                if slug:
                    slugs.append(slug)
        if slugs:
            run_wp_cli(["post", "term", "set", post_id, taxonomy] + slugs)
            print(f"📌 Установлены термины для '{taxonomy}': {slugs}")

    if article_type_slug:
        set_terms_by_slug_only("article-type", [article_type_slug])
    if age_slugs:
        set_terms_by_slug_only("age", age_slugs)
    if symptom_names_or_slugs:
        set_terms_by_slug_only("symptoms", symptom_names_or_slugs)
    if specialty_slugs:
        set_terms_by_slug_only("specialty", specialty_slugs)

    # Прямая запись контента в БД (если включено)
    _update_post_content_directly(post_id, safe_content)

    return post_id


def create_post_with_file(title, content, post_type="disease", status="draft", meta_input=None):
    # Подготовка контента
    safe_content = content if content and content.strip() else "<p>Контент отсутствует</p>"

    # Создаём временный файл
    with tempfile.NamedTemporaryFile(mode='w', suffix='.html', delete=False, encoding='utf-8') as tmp:
        tmp.write(safe_content)
        tmp_path = tmp.name

    try:
        env = os.environ.copy()
        env["SERVER_NAME"] = "dev2.medvisement.com"
        env["HTTP_HOST"] = "dev2.medvisement.com"

        cmd = [
            "wp", "post", "create",
            f"--post_type={post_type}",
            f"--post_title={title}",
            f"--post_status={status}",
            f"--post_content_file={tmp_path}",
            "--porcelain"
        ]
        if meta_input:
            cmd.append(f"--meta_input={json.dumps(meta_input)}")

        cmd.extend([f"--path=/home/dev2/web/dev2.medvisement.com/public_html", "--user=dev2"])

        result = subprocess.run(cmd, env=env, capture_output=True, text=True)

        if result.returncode != 0:
            print("❌ Ошибка:", result.stderr)
            return None
        else:
            post_id = result.stdout.strip()
            print(f"✅ Запись создана. ID: {post_id}")
            return post_id

    finally:
        # Ждём немного и удаляем
        time.sleep(5)
        try:
            print('Del file') # os.unlink(tmp_path)
        except Exception as e:
            print(f"⚠️ Не удалось удалить временный файл: {e}")


# === Тест ===
'''if __name__ == "__main__":
    html_content = "<details><summary>Тест</summary><p>Контент</p></details>"
    upsert_disease_post(
        title="Финальный тест: только слаги",
        content=html_content,
        external_id="1005",
        status="draft",
        article_type_slug="clinical-guidelines",
        age_slugs=["adult"],
        symptom_names_or_slugs=["Головная боль", "Новый симптом FINAL"]
    )
    print("✅ Готово")'''
