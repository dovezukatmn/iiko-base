# Часто задаваемые вопросы (FAQ)

## Общие вопросы

### Что такое iiko-base?

iiko-base - это готовое рабочее пространство для разработки веб-приложений на стеке Python (FastAPI) + Laravel + PostgreSQL. Проект включает все необходимое для быстрого развертывания на VPS сервере.

### Для чего это нужно?

- Быстро запустить backend API на Python
- Иметь готовую админ-панель на Laravel
- Развернуть приложение на сервере одной командой
- Использовать современные технологии (FastAPI, Laravel 10, PostgreSQL)

### Нужно ли мне знать программирование?

Для базовой установки достаточно уметь:
- Подключаться к серверу по SSH
- Копировать и вставлять команды
- Редактировать текстовые файлы

Для разработки нужны знания Python и/или PHP.

## Установка и настройка

### Какой VPS выбрать?

Рекомендуем:
- **Jino.ru** - хорошо подходит для новичков, русская поддержка
- **DigitalOcean** - популярный, простой интерфейс
- **Hetzner** - недорогой, хорошая производительность
- **Timeweb** - русский хостинг

Минимум: 1 GB RAM, 10 GB диск, Ubuntu 20.04/22.04

### Сколько времени займет установка?

- Автоматическая установка: 10-15 минут
- Ручная настройка: +5-10 минут
- Настройка домена и SSL: +10-20 минут

Итого: **25-45 минут** для полной установки.

### Можно ли установить на Windows?

Нет, скрипты написаны для Linux (Ubuntu). Варианты:
1. Используйте VPS с Ubuntu (рекомендуется)
2. Используйте WSL2 на Windows для локальной разработки
3. Используйте Docker (работает на Windows/Mac/Linux)

### Нужен ли мне домен?

Не обязательно. Можно использовать IP адрес сервера. Но домен:
- Удобнее запоминать (mysite.com vs 185.123.45.67)
- Необходим для SSL сертификата
- Выглядит профессиональнее

## Технические вопросы

### Какие порты используются?

- **8000** - Python Backend (FastAPI)
- **80** - HTTP (Nginx)
- **443** - HTTPS (Nginx, после настройки SSL)
- **5432** - PostgreSQL (только localhost)

### Как изменить порт Python backend?

1. Измените в `backend/.env`:
   ```
   PORT=8001
   ```

2. Измените в `/etc/systemd/system/iiko-backend.service`:
   ```
   ExecStart=.../uvicorn app.main:app --host 0.0.0.0 --port 8001
   ```

3. Измените в `nginx/iiko-base.conf`:
   ```
   upstream python_backend {
       server 127.0.0.1:8001;
   }
   ```

4. Перезапустите сервисы:
   ```bash
   systemctl daemon-reload
   systemctl restart iiko-backend
   systemctl restart nginx
   ```

### Как добавить новый API endpoint?

Отредактируйте `backend/app/routes.py`:

```python
@api_router.get("/my-endpoint", tags=["custom"])
async def my_endpoint():
    return {"message": "Hello World"}
```

Перезапустите backend:
```bash
systemctl restart iiko-backend
```

### Как добавить новую таблицу в базу данных?

1. Добавьте модель в `backend/database/models.py`:
   ```python
   class MyTable(Base):
       __tablename__ = "my_table"
       id = Column(Integer, primary_key=True)
       name = Column(String(255))
   ```

2. Создайте SQL миграцию в `database/`:
   ```sql
   CREATE TABLE my_table (
       id SERIAL PRIMARY KEY,
       name VARCHAR(255)
   );
   ```

3. Примените миграцию:
   ```bash
   psql -U iiko_user -d iiko_db -f database/my_migration.sql
   ```

## Проблемы и их решение

### Python backend не запускается

**Симптомы:** `systemctl status iiko-backend` показывает failed

**Решения:**
1. Проверьте логи:
   ```bash
   journalctl -u iiko-backend -n 50
   ```

2. Проверьте .env файл:
   ```bash
   cat backend/.env
   ```
   Убедитесь, что DATABASE_URL корректен.

3. Проверьте подключение к БД:
   ```bash
   psql -U iiko_user -d iiko_db -h localhost
   ```

4. Запустите вручную для отладки:
   ```bash
   cd /var/www/iiko-base/backend
   source venv/bin/activate
   uvicorn app.main:app --host 0.0.0.0 --port 8000
   ```

### Nginx показывает 502 Bad Gateway

**Причина:** Backend не запущен или недоступен

**Решения:**
1. Проверьте, запущен ли backend:
   ```bash
   systemctl status iiko-backend
   ```

2. Проверьте, слушает ли backend на порту 8000:
   ```bash
   netstat -tlnp | grep 8000
   ```

3. Проверьте конфигурацию Nginx:
   ```bash
   nginx -t
   tail -f /var/log/nginx/error.log
   ```

### Не могу подключиться к PostgreSQL

**Ошибка:** `FATAL: Peer authentication failed for user "iiko_user"`

**Решение:**
Эта ошибка возникает, когда PostgreSQL пытается использовать peer-аутентификацию вместо парольной. 

1. Всегда используйте `-h localhost` при подключении:
   ```bash
   psql -h localhost -U iiko_user -d iiko_db
   ```

2. Или настройте pg_hba.conf для использования md5/scram-sha-256:
   ```bash
   sudo nano /etc/postgresql/14/main/pg_hba.conf
   ```
   Измените строку `local all all peer` на `local all all md5`
   
3. Перезапустите PostgreSQL:
   ```bash
   sudo systemctl restart postgresql
   ```

**Ошибка:** `FATAL: password authentication failed`

**Решения:**
1. Проверьте пароль в .env файлах
2. Сбросьте пароль:
   ```bash
   sudo -u postgres psql
   ALTER USER iiko_user WITH PASSWORD 'new_password';
   \q
   ```
3. Обновите пароль в backend/.env и frontend/.env

### Ошибка "Permission denied"

**Решения:**
1. Проверьте права на файлы:
   ```bash
   ls -la /var/www/iiko-base
   ```

2. Установите правильные права:
   ```bash
   sudo chown -R iiko:iiko /var/www/iiko-base/backend
   sudo chown -R www-data:www-data /var/www/iiko-base/frontend
   ```

3. Для storage Laravel:
   ```bash
   sudo chmod -R 775 /var/www/iiko-base/frontend/storage
   ```

### Laravel ошибка "directory must be present and writable"

**Ошибка:** `The /var/www/iiko-base/frontend/bootstrap/cache directory must be present and writable`

**Решение:**
Эта ошибка возникает, когда отсутствуют необходимые директории Laravel.

1. Запустите скрипт setup.sh, который создаст все необходимые директории:
   ```bash
   ./scripts/setup.sh
   ```

2. Или создайте директории вручную:
   ```bash
   mkdir -p frontend/bootstrap/cache
   mkdir -p frontend/storage/{app,framework,logs}
   mkdir -p frontend/storage/framework/{sessions,views,cache}
   ```

3. Установите правильные права:
   ```bash
   chmod -R 775 frontend/storage
   chmod -R 775 frontend/bootstrap/cache
   ```

### SSL сертификат не работает

**Проблема:** ERR_SSL_PROTOCOL_ERROR в браузере

**Решения:**
1. Убедитесь, что certbot установлен:
   ```bash
   certbot --version
   ```

2. Получите сертификат заново:
   ```bash
   certbot --nginx -d yourdomain.com
   ```

3. Проверьте конфигурацию:
   ```bash
   nginx -t
   systemctl restart nginx
   ```

4. Проверьте, что DNS записи настроены правильно:
   ```bash
   dig yourdomain.com
   ```

## Docker

### Как запустить проект в Docker локально?

```bash
git clone https://github.com/dovezukatmn/iiko-base.git
cd iiko-base
docker-compose up -d
```

### Как просмотреть логи Docker контейнеров?

```bash
docker-compose logs -f          # Все логи
docker-compose logs -f backend  # Только backend
docker-compose logs -f postgres # Только БД
```

### Как остановить Docker контейнеры?

```bash
docker-compose stop           # Остановить
docker-compose down           # Остановить и удалить
docker-compose down -v        # + удалить volumes (БД очистится!)
```

### Можно ли использовать Docker для production?

Можно, но рекомендуется прямая установка на VPS:
- Проще мониторинг
- Меньше overhead
- Проще настройка firewall
- Легче отладка

## Разработка

### Как добавить новую зависимость Python?

1. Активируйте venv:
   ```bash
   cd backend
   source venv/bin/activate
   ```

2. Установите пакет:
   ```bash
   pip install package-name
   ```

3. Обновите requirements.txt:
   ```bash
   pip freeze > requirements.txt
   ```

4. Перезапустите backend:
   ```bash
   systemctl restart iiko-backend
   ```

### Как добавить новую зависимость PHP/Laravel?

```bash
cd frontend
composer require package/name
```

### Как включить debug режим?

**Backend (.env):**
```ini
DEBUG=True
```

**Frontend (.env):**
```ini
APP_DEBUG=true
```

**⚠️ Не используйте debug в production!**

## Безопасность

### Безопасен ли проект по умолчанию?

Базовая безопасность есть, но нужно:
1. ✅ Изменить все пароли в .env
2. ✅ Изменить SECRET_KEY
3. ✅ Настроить SSL (HTTPS)
4. ✅ Настроить firewall
5. ✅ Регулярно обновлять систему

### Как настроить firewall?

```bash
# Установка UFW
sudo apt install ufw

# Разрешить необходимые порты
sudo ufw allow 22    # SSH
sudo ufw allow 80    # HTTP
sudo ufw allow 443   # HTTPS

# Включить firewall
sudo ufw enable

# Проверить статус
sudo ufw status
```

### Как изменить пароли?

**PostgreSQL:**
```bash
sudo -u postgres psql
ALTER USER iiko_user WITH PASSWORD 'new_secure_password';
\q
```

**Backend .env:**
```bash
nano backend/.env
# Измените DATABASE_URL
```

**Frontend .env:**
```bash
nano frontend/.env
# Измените DB_PASSWORD
```

**Перезапустите сервисы:**
```bash
systemctl restart iiko-backend
systemctl restart nginx
```

## Производительность

### Как увеличить производительность?

1. **PostgreSQL connection pooling:**
   - Установите PgBouncer
   - Настройте pool_size в SQLAlchemy

2. **Кэширование:**
   - Установите Redis
   - Настройте кэш в Laravel

3. **Nginx:**
   - Включите gzip сжатие
   - Настройте кэширование статики

4. **Сервер:**
   - Увеличьте RAM
   - Используйте SSD диски

### Сколько пользователей может выдержать?

Зависит от конфигурации сервера:
- **1 GB RAM:** 100-500 одновременных запросов
- **2 GB RAM:** 500-1000 одновременных запросов
- **4+ GB RAM:** 1000+ одновременных запросов

Для высоких нагрузок нужно горизонтальное масштабирование.

## Backup и восстановление

### Как сделать backup?

```bash
# Автоматический backup
./scripts/backup.sh

# Ручной backup
pg_dump -U iiko_user iiko_db > backup.sql
gzip backup.sql
```

### Как восстановить из backup?

```bash
./scripts/restore.sh /path/to/backup.sql.gz
```

### Как настроить автоматический backup?

```bash
# Добавить в crontab
crontab -e

# Добавить строку (backup каждый день в 2:00)
0 2 * * * /var/www/iiko-base/scripts/backup.sh
```

## Обновление

### Как обновить проект?

```bash
cd /var/www/iiko-base
git pull origin main
./scripts/deploy.sh
```

### Как обновить Python зависимости?

```bash
cd backend
source venv/bin/activate
pip install --upgrade -r requirements.txt
systemctl restart iiko-backend
```

### Как обновить систему Ubuntu?

```bash
sudo apt update
sudo apt upgrade -y
sudo reboot
```

## Дополнительно

### Где хранятся логи?

- **Backend:** `journalctl -u iiko-backend`
- **Nginx:** `/var/log/nginx/`
- **PostgreSQL:** `/var/log/postgresql/`
- **Laravel:** `frontend/storage/logs/`

### Как мониторить сервер?

Рекомендуемые инструменты:
- **UptimeRobot** - мониторинг доступности (бесплатно)
- **Prometheus + Grafana** - метрики
- **New Relic** - APM (платно)

### Можно ли использовать с другой БД (MySQL)?

Можно, но потребуется изменить:
1. Драйвер в Python (mysqlclient вместо psycopg2)
2. Конфигурацию Laravel
3. SQL скрипты (синтаксис отличается)

Рекомендуем PostgreSQL - он современнее и производительнее.

### Есть ли мобильное приложение?

Нет, но backend API готов для интеграции с мобильным приложением (iOS/Android).

### Можно ли использовать для коммерческих проектов?

Да, проект под лицензией MIT - можете использовать как угодно, в том числе коммерчески.

## Нужна помощь?

1. **Документация:**
   - [Руководство по установке](INSTALLATION.md)
   - [Руководство для новичков](BEGINNER_GUIDE.md)
   - [Архитектура](ARCHITECTURE.md)

2. **Поддержка:**
   - [GitHub Issues](https://github.com/dovezukatmn/iiko-base/issues)
   - Создайте issue с описанием проблемы

3. **Сообщество:**
   - Stack Overflow (тег: iiko-base)
   - Reddit: r/learnprogramming

**Не нашли ответ? Создайте issue!**
