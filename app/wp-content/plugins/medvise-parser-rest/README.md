## Medvise Parser REST (WordPress plugin)

Плагин добавляет кастомные REST-эндпоинты для **upsert** записей типа `disease` и проверки их существования по `source_id`.

### Установка

- Скопируйте папку `medvise-parser-rest` в `wp-content/plugins/`
- Активируйте плагин в админке WordPress
- Создайте **Application Password** для пользователя:
  - **Пользователи → Профиль → Application Passwords**

### Авторизация

Все запросы требуют Basic Auth:

- `username`: логин пользователя WP
- `password`: Application Password

Права: пользователь должен иметь возможность `edit_posts`.

### Эндпоинты

#### 1) Upsert записи disease

- **POST** `/wp-json/medvise/v1/disease/upsert`
- **Body (JSON)**:
  - `title` (string, required)
  - `content` (string, HTML)
  - `external_id` (string, required) — сохраняется в мета как `source_id=pi_<external_id>`
  - `status` (string, optional, default `draft`)
  - `article_type_slug` (string, optional) — таксономия `article-type`
  - `article_type_name` (string, optional) — название термина, если `article_type_slug` нужно создать или обновить
  - `is_english` (bool, optional) — ставит `medvise_is_english_article=1`
  - `age_slugs` (array[string], optional) — таксономия `age`
  - `symptom_names_or_slugs` (array[string], optional) — таксономия `symptoms` (по имени или slug, создаётся при необходимости)
  - `specialty_slugs` (array[string], optional) — таксономия `specialty`
  - `mnn_names_or_slugs` (array[string], optional) — таксономия `mnn` / `МНН` (создаётся плагином)
  - `meta_extra` (object, optional) — произвольные дополнительные meta-поля (`key => value`)

- **Response (JSON)**:
  - `{ ok: true, post_id: <int> }` или `{ ok: false, error: <string> }`

#### 2) Проверка существования записи disease (точный статус)

- **GET** `/wp-json/medvise/v1/disease/status`
- **Query params**:
  - `external_id` (string, optional)
  - `source_id` (string, optional)

Если передан `external_id`, то поиск идёт по `source_id = pi_<external_id>`.

- **Response (JSON)**:
  - `ok` (bool)
  - `exists` (bool)
  - `post_id` (int)
  - `post_status` (string)
  - `source_id` (string)

### Что делает upsert

- Ищет запись `disease` по meta `source_id`
- Если существует — обновляет, иначе создаёт
- Устанавливает таксономии (`article-type`, `age`, `symptoms`, `specialty`, `mnn`)
- Устанавливает мета (`source_id`, `medvise_is_english_article`, `meta_extra`)
