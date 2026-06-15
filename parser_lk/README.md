# Парсер данных с портала Росздравнадзора

Этот проект автоматизирует сбор данных со страницы `https://lk.regmed.ru/Register/EAEU_SmPC` (реестр лекарственных средств ЕАЭС).
Парсер собирает информацию о препаратах, скачивает документы (PDF) и отправляет статьи напрямую в WordPress.

## Функциональность

- Обход всех страниц таблицы с препаратами.
- Извлечение данных: МНН, торговое наименование, форма выпуска, номер РУ, дата РУ, владелец РУ, страна.
- Получение и решение капчи через сервис Anti-Captcha (ImageToText).
- Скачивание документов (PDF) для каждого препарата.
- Создание/обновление черновиков WordPress с типом статьи `ОХЛП` в выбранном разделе: `Препараты` по умолчанию или `Заболевания` через `WP_POST_TYPE=disease`.
- Сохранение изображений капч в папку `captcha_images`; PDF используются как временные файлы для загрузки вложений в WordPress.
- Возможность настройки задержек между запросами.

## Требования

- Docker (опционально) или Python 3.9+.
- Ключ Anti-Captcha (задаётся через переменные окружения).
- Доступ в интернет.
- Запущенный WordPress-проект Medvisement, если нужна автоматическая выгрузка статей в админку.

## Разворачивание проекта `parser-lk`

### 1. Получить код

```bash
git clone https://gitlab.lovetirami.su/medvisement/parser-lk.git parser_lk
cd parser_lk
```

Если парсер разворачивается внутри общего проекта Medvisement, папка должна лежать рядом с остальными сервисами проекта:

```text
/path/to/medvisement/parser_lk
```

### 2. Подготовить папки данных

```bash
mkdir -p data/captcha_images data/pdf_files
```

В эти папки сохраняются изображения капч, временные PDF-файлы и служебные данные парсера. Эти файлы не нужно коммитить в Git.

### 3. Создать `.env`

Создайте файл `.env` в корне `parser_lk`:

```text
ANTICAPTCHA_API_KEY=change_me
DELAY_BETWEEN_PAGES=2
DELAY_BETWEEN_PDF=1
MAX_ITEMS=0
SKIP_DOCUMENTS=false

WP_SYNC_ENABLED=true
WP_BASE_URL=https://nginx
WP_USERNAME=admin
WP_PASSWORD=your_wordpress_application_password
WP_VERIFY_SSL=false
WP_AUTHOR_ID=1
WP_POST_TYPE=substance
WP_ARTICLE_TYPE_SLUG=ohlp
WP_ARTICLE_TYPE_NAME=ОХЛП
WP_POST_STATUS=draft

WORDPRESS_DOCKER_NETWORK=medvisement1_webnet
```

`WP_PASSWORD` — это не обычный пароль пользователя WordPress, а Application Password из профиля пользователя в админке WordPress.

`WP_POST_TYPE` определяет, куда выгружать записи:
- `substance` — раздел `Препараты`;
- `disease` — раздел `Заболевания`.

### 4. Запустить через Docker

Для локального окружения:

```bash
docker compose up -d --build
```

Для тестового удалённого хоста, где используется Docker-сеть `medvisement2_webnet`:

```bash
docker compose -f docker-compose.yml -f docker-compose.remote.yml up -d --build
```

Контейнер запускает web-панель на порту `5000`. В составе общего проекта Medvisement она доступна через nginx:
- локально: `https://localhost/parser-lk/`;
- тестовый хост: `https://test2.medvisement.com:8444/parser-lk/`.

### 5. Запустить парсинг

Основной сценарий — запуск из web-панели кнопкой запуска парсера.

Если нужно запустить парсер вручную из командной строки:

```bash
docker compose run --rm parser python main.py
```

Для тестового удалённого хоста:

```bash
docker compose -f docker-compose.yml -f docker-compose.remote.yml run --rm parser python main.py
```

После успешной обработки PDF парсер создаёт или обновляет черновики в WordPress. Скачанные PDF также прикрепляются к статье.

### 6. Проверка и обслуживание

Проверить статус контейнера:

```bash
docker compose ps
```

Посмотреть логи:

```bash
docker compose logs -f parser
```

Остановить парсер:

```bash
docker compose down
```

Если WordPress не принимает записи, проверьте:
- запущен ли основной WordPress/docker-проект;
- совпадает ли `WORDPRESS_DOCKER_NETWORK` с сетью WordPress;
- корректны ли `WP_USERNAME` и `WP_PASSWORD`;
- активен ли REST-плагин `medvise-parser-rest` в WordPress.

## Установка и запуск

### Запуск через Docker (рекомендуется)

1) Создайте папки для данных (они будут смонтированы в контейнер):

```bash
mkdir -p data/captcha_images data/pdf_files
```

2) Задайте переменную окружения с ключом anti-captcha и запустите:

#### Windows (PowerShell)

```powershell
$env:ANTICAPTCHA_API_KEY="ВАШ_КЛЮЧ"
docker compose up --build
```

#### Linux/macOS (bash)

```bash
export ANTICAPTCHA_API_KEY="ВАШ_КЛЮЧ"
docker compose up --build
```

Для фонового режима:

```bash
docker compose up -d --build
```

На тестовом удалённом хосте используется сеть `medvisement2_webnet`, поэтому запускайте так:

```bash
docker compose -f docker-compose.yml -f docker-compose.remote.yml up --build
```

Результаты будут сохранены в папке `data/`:
- `data/captcha_images/` — изображения капч
- `data/pdf_files/` — скачанные PDF для загрузки в WordPress

### Запуск без Docker

```bash
python -m venv .venv
python -m pip install -r requirements.txt
python main.py
```

При необходимости задайте переменные окружения (см. ниже).

## Конфигурация

Переменные окружения:

- **`ANTICAPTCHA_API_KEY`**: ключ API для Anti-Captcha (обязательно)
- **`DELAY_BETWEEN_PAGES`**: задержка между запросами страниц (сек), по умолчанию `1`
- **`DELAY_BETWEEN_PDF`**: задержка между скачиванием PDF (сек), по умолчанию `1`
- **`MAX_ITEMS`**: лимит записей за запуск, `0` — без лимита
- **`SKIP_DOCUMENTS`**: пропустить скачивание PDF-документов, по умолчанию `false`
- **`CAPTCHA_IMAGES_DIR`**: папка для изображений капч, по умолчанию `captcha_images`
- **`PDF_DIR`**: папка для PDF, по умолчанию `pdf_files`
- **`WP_SYNC_ENABLED`**: выгружать препараты в WordPress, по умолчанию `true`
- **`WP_BASE_URL`**: базовый URL WordPress REST, для локального Docker по умолчанию `https://nginx`
- **`WP_USERNAME`** и **`WP_PASSWORD`**: пользователь WordPress и Application Password
- **`WP_VERIFY_SSL`**: проверять SSL-сертификат WordPress, по умолчанию `false`
- **`WP_POST_TYPE`**: тип записи WordPress, по умолчанию `substance`
- **`WP_ARTICLE_TYPE_SLUG`**: slug типа статьи, по умолчанию `ohlp`
- **`WP_ARTICLE_TYPE_NAME`**: название типа статьи в админке, по умолчанию `ОХЛП`
- **`WP_POST_STATUS`**: статус создаваемых записей, по умолчанию `draft`

Пример `.env`:

```text
ANTICAPTCHA_API_KEY=your_api_key_here
DELAY_BETWEEN_PAGES=2
DELAY_BETWEEN_PDF=1
WP_BASE_URL=https://nginx
WP_USERNAME=admin
WP_PASSWORD=your_wordpress_application_password
WP_VERIFY_SSL=false
WP_POST_TYPE=substance
WORDPRESS_DOCKER_NETWORK=medvisement1_webnet
```

При включённой WordPress-синхронизации препараты создаются/обновляются как черновики с типом статьи `ОХЛП`.
По умолчанию используется раздел `Препараты` (`post_type=substance`). Если нужно выгружать в `Заболевания`, задайте `WP_POST_TYPE=disease`.

## Структура проекта

```text
.
├── main.py
├── requirements.txt
├── Dockerfile
├── docker-compose.yml
├── README.md
└── data/
    ├── captcha_images/
    └── pdf_files/
```

## Примечания

- Тестовый/публичный ключ в репозиторий не добавляйте — задавайте ключ через `.env` или переменные окружения.
- Если сайт начнёт ограничивать частоту запросов — увеличьте задержки `DELAY_BETWEEN_PAGES`/`DELAY_BETWEEN_PDF`.

## Web-интерфейс

Сервис запускает Flask-панель управления из `app.py` и хранит служебные результаты в локальной SQLite-базе `data/parser_lk.db`.
При необходимости парсер можно запустить вручную:

```bash
docker compose -f docker-compose.yml -f docker-compose.remote.yml run --rm parser python main.py
```

Во время парсинга запись сохраняется локально после скачивания PDF. Если распознавание капчи, получение токена или скачивание PDF завершилось ошибкой, текущий запуск останавливается, чтобы не пропускать проблемную запись.
