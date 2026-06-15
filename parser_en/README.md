# Libook Scraper

## Структура проекта

```
libook_project/
├── app.py              # Flask веб-интерфейс
├── libook_scraper.py   # Playwright-скрапер
├── scheduler.py        # Планировщик параллельного парсинга
├── database.py         # SQLite схема и хелперы
├── requirements.txt
├── libook.db           # SQLite БД (создаётся автоматически)
├── session_data/       # Сессии браузера (по одной папке на аккаунт)
│   └── {login}/
│       └── storage_state.json
└── downloaded_articles/  # (опционально) локальные копии HTML
```

## Установка

```bash
pip install -r requirements.txt
playwright install chromium
```

## Docker

```bash
docker compose up --build
```

Откройте `http://localhost:5050`.

Контейнер подключается к Docker-сети основного проекта `medvisement1_webnet`, поэтому при запуске из Docker REST URL WordPress должен быть `https://nginx`.

На тестовом удалённом хосте используется сеть `medvisement2_webnet`, поэтому запускайте так:

```bash
docker compose -f docker-compose.yml -f docker-compose.remote.yml up -d --build
```

После nginx-прокси интерфейс доступен по домену:
`https://test2.medvisement.com:8444/parser-en/`.

## Запуск веб-интерфейса

```bash
python app.py
```

Откройте http://localhost:5000

## Что делает интерфейс

### 👤 Аккаунты
- Добавление/редактирование/удаление аккаунтов
- Для каждого аккаунта: логин, пароль, прокси (socks5://host:port), макс. статей в день, интервал между статьями

### 🔍 Запросы
- Список поисковых запросов для обработки
- Можно включать/выключать каждый запрос

### 📋 Результаты поиска
- Для каждого поискового запроса — все найденные статьи
- Видно: какие уже скачаны, какие ещё нет
- Прогресс-бар скачивания

### 📄 Статьи
- Список всех скачанных статей
- Просмотр статьи: две вкладки
  - **Основной контент** — HTML из #topicContent
  - **Графики** — страницы из модального окна (по одной вкладке на каждую страницу)

### ⚙️ Управление
- Запуск/остановка парсинга
- При запуске все активные аккаунты работают **параллельно**
- Кнопка "Собрать результаты поиска сейчас" — срочный сбор без ожидания
- Кнопка "⬆ Выгрузить все в WordPress" — выгрузка всех ещё не выгруженных

### 🔐 Настройки
- Выбор способа выгрузки в WordPress: **WP-CLI** или **REST**
- Для REST нужно указать: **base URL**, **username**, **Application Password**
- Для локального Docker-окружения уже настроено: `REST`, `https://nginx`, `admin`, пароль `1234567`, SSL-проверка выключена.
- Английские статьи выгружаются в `Заболевания` как `draft` с типом статьи `eng-articles`.

## WordPress: REST выгрузка (плагин)

В репозитории есть плагин: `wp_plugin/medvise-parser-rest/medvise-parser-rest.php`.

Установка:
- Скопируйте папку `medvise-parser-rest` в `wp-content/plugins/`
- Активируйте плагин в админке WordPress
- Создайте **Application Password** для пользователя (Пользователи → Профиль → Application Passwords)

Endpoint:
- `POST /wp-json/medvise/v1/disease/upsert`
- Auth: Basic (username + application password)
 - Проверка статуса/существования: `GET /wp-json/medvise/v1/disease/status?external_id=...` (точнее, чем хранить флаг локально)

Поля запроса (JSON):
- `title` (string)
- `content` (string, HTML)
- `external_id` (string) — будет сохранён как `source_id=pi_<external_id>`
- `status` (string, например `draft`)
- `article_type_slug` (string, по умолчанию для этого парсера `eng-articles`)
- `is_english` (bool) — выставит `medvise_is_english_article=yes`
 - `age_slugs` (array[string]) — таксономия `age`
 - `symptom_names_or_slugs` (array[string]) — таксономия `symptoms` (по имени или slug; создаётся при необходимости)
 - `specialty_slugs` (array[string]) — таксономия `specialty`
 - `meta_extra` (object) — дополнительные meta-поля

### Важно про HTML статьи

Перед выгрузкой в WordPress и перед скачиванием HTML применяется финальная обработка `clear_article.build_uptodate_article()`:
- чистка служебных блоков/строк,
- удаление inline-стилей и лишних атрибутов,
- вкладки `Article / Graphics / Authors / References`,
- оглавление, слайдер графиков и модальные окна,
- адаптивная мобильная версия.


## Алгоритм парсинга

1. Планировщик запускает по одному воркеру на каждый активный аккаунт
2. Каждый воркер:
   - Проверяет дневной лимит
   - Берёт первую незагруженную статью из search_results
   - Открывает страницу статьи напрямую по URL
   - Ждёт JS-рендера #topicContent (до 60 сек)
   - Переходит на вкладку с графиками
   - Кликает по элементу коллекции → открывает модальное окно
   - Собирает ВСЕ страницы по кругу (нажимает "предыдущая" N раз)
   - Сохраняет всё в SQLite
   - Ждёт interval_min минут и переходит к следующей статье
   - Если незагруженных нет — собирает новые результаты поиска

## Об алгоритме обхода графиков

Открыв модальное окно мы можем оказаться на любой странице, например "3 Of 5".
Нажимая "предыдущая" ровно N раз (N = total_pages - 1 = 4 раза):
  3 → 2 → 1 → 5 → 4
За N нажатий мы обойдём все уникальные страницы кругового списка.
Уже собранные страницы пропускаются (словарь collected по page_num).
