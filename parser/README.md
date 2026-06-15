# Руководство по развертыванию

## Текущая схема работы

Парсер:

- сохраняет локальные JSON/PDF/HTML/TXT в папку `parser/data`
- использует таблицу `clinical_recommendations` как технический кэш для отслеживания изменений
- синхронизирует рекомендации в WordPress как записи типа `disease`

В WordPress создаются записи:

- `post_type = disease`
- `post_status = draft` для новых записей
- `article-type = clinical-guidelines`
- `post_author = admin` / `author_id = 1`

На фронте раздел `/clinical-recommendations/` берёт данные уже из опубликованных `disease`-записей этого типа.

## Запуск в проекте Medvisement

Из корня проекта:

```bash
docker compose --profile clinical-parser run --rm clinical-parser
```

Тест одной рекомендации:

```bash
docker compose --profile clinical-parser run --rm clinical-parser python test_single.py 232_2
```

## Системные требования

### Минимальные
- Python 3.8+
- 500 MB свободного места на диске
- 512 MB RAM
- Интернет соединение

### Рекомендуемые
- Python 3.10+
- 5 GB свободного места (для хранения PDF и изображений)
- 2 GB RAM
- Стабильное интернет соединение

---

## Быстрая установка (SQLite)

### Шаг 1: Клонирование/скачивание

```bash
# Если у вас Git
git clone <ваш-репозиторий>
cd clinical-recommendations-parser

# Или просто скопируйте файлы в папку проекта
```

### Шаг 2: Установка зависимостей

```bash
pip install -r requirements.txt
```

**requirements.txt:**
```
requests>=2.28.0
beautifulsoup4>=4.11.0
lxml>=4.9.0
```

### Шаг 3: Проверка конфигурации

Откройте `config.py` и убедитесь:

```python
DB_TYPE: str = 'sqlite'  # ← Должно быть 'sqlite'
```

### Шаг 4: Запуск

```bash
# Первый запуск (создаст БД и скачает все рекомендации)
python main.py
```

**Ожидаемый результат:**
```
2025-01-17 10:00:00 - INFO - Используется БД: sqlite
2025-01-17 10:00:00 - INFO - Путь к SQLite: data/clinical_recommendations.db
2025-01-17 10:00:01 - INFO - Запуск парсера клинических рекомендаций
2025-01-17 10:00:02 - INFO - Получение списка клинических рекомендаций...
2025-01-17 10:00:05 - INFO - Получено 450 рекомендаций
...
```

---

## Установка с MySQL

### Шаг 1: Установка MySQL сервера

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install mysql-server
sudo mysql_secure_installation
```

#### CentOS/RHEL
```bash
sudo yum install mysql-server
sudo systemctl start mysqld
sudo mysql_secure_installation
```

#### macOS
```bash
brew install mysql
brew services start mysql
mysql_secure_installation
```

#### Windows
Скачайте установщик с https://dev.mysql.com/downloads/installer/

### Шаг 2: Создание пользователя БД

```bash
mysql -u root -p
```

```sql
-- Создаем БД (опционально, парсер создаст автоматически)
CREATE DATABASE clinical_recommendations 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

-- Создаем пользователя
CREATE USER 'cr_parser'@'localhost' IDENTIFIED BY 'secure_password';

-- Даем права
GRANT ALL PRIVILEGES ON clinical_recommendations.* TO 'cr_parser'@'localhost';
FLUSH PRIVILEGES;

-- Проверяем
SHOW GRANTS FOR 'cr_parser'@'localhost';

EXIT;
```

### Шаг 3: Установка Python библиотеки

```bash
pip install mysql-connector-python
```

### Шаг 4: Настройка config.py

```python
# config.py

@dataclass
class Config:
    # ==================== НАСТРОЙКИ БД ====================
    DB_TYPE: str = 'mysql'  # ← Изменили на mysql!
    
    # Настройки MySQL
    DB_HOST: str = 'localhost'
    DB_PORT: int = 3306
    DB_USER: str = 'cr_parser'       # ← Ваш пользователь
    DB_PASSWORD: str = 'secure_password'  # ← Ваш пароль
    DB_NAME: str = 'clinical_recommendations'
    
    # ... остальные настройки
```

### Шаг 5: Запуск

```bash
python main.py
```

**Проверка подключения:**
```
2025-01-17 10:00:00 - INFO - Используется БД: mysql
2025-01-17 10:00:00 - INFO - MySQL подключение: cr_parser@localhost:3306/clinical_recommendations
2025-01-17 10:00:01 - INFO - База данных успешно инициализирована
```

---

## Структура файлов после установки

```
clinical-recommendations-parser/
├── config.py              # Конфигурация
├── main.py                # Главный файл
├── parser.py              # Парсер контента
├── api_client.py          # API клиент
├── database.py            # SQLite БД
├── database_mysql.py      # MySQL БД
├── file_manager.py        # Управление файлами
├── test_single.py         # Тестовый скрипт
├── requirements.txt       # Зависимости
├── parser.log             # Логи (создается автоматически)
└── data/                  # Данные (создается автоматически)
    ├── clinical_recommendations.db  # SQLite БД
    ├── json/              # JSON и отформатированные файлы
    │   ├── 232_2.json
    │   ├── 232_2_formatted.txt
    │   ├── 232_2_formatted.html
    │   └── 232_2_original.html
    └── files/             # PDF, изображения, таблицы
        └── 232_2/
            ├── 232_2.pdf
            ├── image_abc123.jpg
            └── table_1.html
```

---

## Первый запуск

### Тест одной рекомендации

```bash
# Перед полным запуском, протестируйте на одной рекомендации
python test_single.py 232_2
```

**Что проверить:**
- ✅ PDF скачался
- ✅ Таблицы сохранились
- ✅ Изображения извлечены
- ✅ HTML корректный
- ✅ Спойлеры работают (открыть .html в браузере)

### Полный запуск

```bash
python main.py
```

**Первый запуск может занять 1-3 часа** (зависит от количества рекомендаций и скорости интернета).

---

## Конфигурация

### Основные параметры в config.py

```python
@dataclass
class Config:
    # === БД ===
    DB_TYPE: str = 'sqlite'           # 'sqlite' или 'mysql'
    
    # === Задержки ===
    REQUEST_TIMEOUT: int = 30         # Таймаут запроса (сек)
    MAX_RETRIES: int = 3              # Количество повторных попыток
    DELAY_BETWEEN_REQUESTS: float = 1.0  # Задержка между запросами (сек)
```

### Изменение задержки между запросами

Если сервер Минздрава возвращает ошибки 429 (Too Many Requests):

```python
DELAY_BETWEEN_REQUESTS: float = 2.0  # Увеличьте до 2 секунд
```

### Изменение таймаутов

Для медленного интернета:

```python
REQUEST_TIMEOUT: int = 60  # Увеличьте до 60 секунд
```

---

## Переключение между SQLite и MySQL

### Из SQLite в MySQL

1. Измените `config.py`:
```python
DB_TYPE: str = 'mysql'
```

2. Укажите параметры подключения:
```python
DB_HOST: str = 'localhost'
DB_PORT: int = 3306
DB_USER: str = 'cr_parser'
DB_PASSWORD: str = 'your_password'
DB_NAME: str = 'clinical_recommendations'
```

3. Запустите парсер - он создаст таблицы автоматически:
```bash
python main.py
```

### Миграция данных из SQLite в MySQL

**Скрипт миграции** (`migrate_sqlite_to_mysql.py`):

```python
import sqlite3
import mysql.connector
from config import config
from database import ClinicalRecommendation
import json

# Подключение к SQLite
sqlite_conn = sqlite3.connect('data/clinical_recommendations.db')
sqlite_cursor = sqlite_conn.cursor()

# Подключение к MySQL
mysql_conn = mysql.connector.connect(
    host=config.DB_HOST,
    port=config.DB_PORT,
    user=config.DB_USER,
    password=config.DB_PASSWORD,
    database=config.DB_NAME
)
mysql_cursor = mysql_conn.cursor()

# Получаем все записи из SQLite
sqlite_cursor.execute("SELECT * FROM clinical_recommendations")
rows = sqlite_cursor.fetchall()

print(f"Найдено записей: {len(rows)}")

# Вставляем в MySQL
for row in rows:
    mysql_cursor.execute('''
        INSERT INTO clinical_recommendations 
        (id, code_version, name, mkb, version, publish_date, age_category,
         status, raw_json, formatted_text, formatted_html, last_updated, 
         is_deleted, file_hash)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ''', row)
    
mysql_conn.commit()
print("Миграция завершена!")

sqlite_conn.close()
mysql_conn.close()
```

Запуск:
```bash
python migrate_sqlite_to_mysql.py
```

---

## Автоматизация

### Cron (Linux/macOS)

Запуск каждый день в 3:00:

```bash
crontab -e
```

Добавьте:
```
0 3 * * * cd /path/to/parser && /usr/bin/python3 main.py >> /path/to/parser/cron.log 2>&1
```

### Task Scheduler (Windows)

1. Открыть Task Scheduler
2. Create Basic Task
3. Trigger: Daily, 3:00 AM
4. Action: Start a program
5. Program: `C:\Python310\python.exe`
6. Arguments: `main.py`
7. Start in: `C:\path\to\parser`

### systemd service (Linux)

`/etc/systemd/system/cr-parser.service`:

```ini
[Unit]
Description=Clinical Recommendations Parser
After=network.target

[Service]
Type=oneshot
User=your_user
WorkingDirectory=/path/to/parser
ExecStart=/usr/bin/python3 main.py
StandardOutput=append:/path/to/parser/service.log
StandardError=append:/path/to/parser/service.log

[Install]
WantedBy=multi-user.target
```

Запуск:
```bash
sudo systemctl enable cr-parser.service
sudo systemctl start cr-parser.service
```

---

## Мониторинг

### Проверка логов

```bash
# Последние 50 строк
tail -50 parser.log

# Отслеживание в реальном времени
tail -f parser.log

# Поиск ошибок
grep ERROR parser.log
```

### Проверка БД

**SQLite:**
```bash
sqlite3 data/clinical_recommendations.db "SELECT COUNT(*) FROM clinical_recommendations;"
```

**MySQL:**
```bash
mysql -u cr_parser -p -e "SELECT COUNT(*) FROM clinical_recommendations.clinical_recommendations;"
```

### Размер данных

```bash
# Общий размер
du -sh data/

# По категориям
du -sh data/json/
du -sh data/files/
du -sh data/*.db
```

---

## Troubleshooting

### Проблема: "No module named 'mysql'"

**Решение:**
```bash
pip install mysql-connector-python
```

### Проблема: "Access denied for user"

**Решение:**
```bash
mysql -u root -p
```
```sql
GRANT ALL PRIVILEGES ON clinical_recommendations.* TO 'cr_parser'@'localhost';
FLUSH PRIVILEGES;
```

### Проблема: PDF не скачиваются

**Проверка:**
```bash
curl "https://apicr.minzdrav.gov.ru/api.ashx?op=GetClinrecPdf&id=232_2" -o test.pdf
file test.pdf  # Должно быть: PDF document
```

**Решение:** Проверьте интернет соединение и увеличьте `REQUEST_TIMEOUT`

### Проблема: "Database is locked" (SQLite)

**Решение:**
```bash
# Закройте все подключения к БД
pkill -f main.py

# Или используйте MySQL вместо SQLite
```

---

## Обновление

### Обновление кода

```bash
git pull  # Если используете Git

# Или скопируйте новые файлы
```

### Обновление БД (миграция схемы)

Если добавилось новое поле в таблицу:

**SQLite:**
```bash
sqlite3 data/clinical_recommendations.db
```
```sql
ALTER TABLE clinical_recommendations ADD COLUMN new_field TEXT;
```

**MySQL:**
```bash
mysql -u cr_parser -p clinical_recommendations
```
```sql
ALTER TABLE clinical_recommendations ADD COLUMN new_field TEXT;
```

---

## Бэкап

### SQLite

```bash
# Бэкап
cp data/clinical_recommendations.db data/clinical_recommendations.db.backup

# Восстановление
cp data/clinical_recommendations.db.backup data/clinical_recommendations.db
```

### MySQL

```bash
# Бэкап
mysqldump -u cr_parser -p clinical_recommendations > backup.sql

# Восстановление
mysql -u cr_parser -p clinical_recommendations < backup.sql
```

### Бэкап файлов

```bash
# Полный бэкап
tar -czf backup_$(date +%Y%m%d).tar.gz data/

# Восстановление
tar -xzf backup_20250117.tar.gz
```

---

## Производительность

### Рекомендации

1. **Используйте MySQL для продакшена** - быстрее и надежнее
2. **Увеличьте DELAY_BETWEEN_REQUESTS** - чтобы не перегружать сервер
3. **Запускайте ночью** - меньше нагрузка на сервер
4. **Регулярные бэкапы** - раз в неделю

### Оптимизация MySQL

```sql
-- Создайте индексы если их нет
CREATE INDEX idx_code_version ON clinical_recommendations(code_version);
CREATE INDEX idx_mkb ON clinical_recommendations(mkb);
CREATE FULLTEXT INDEX idx_name ON clinical_recommendations(name);
```

---

## Поддержка

При возникновении проблем:

1. Проверьте логи: `tail -50 parser.log`
2. Запустите тест: `python test_single.py 232_2`
3. Проверьте подключение к БД
4. Проверьте интернет соединение
