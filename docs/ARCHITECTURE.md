# Архитектура проекта iiko-base

## Обзор

iiko-base - это полностью настроенная система для работы с рестораном на базе стека Python + Laravel + PostgreSQL.

## Компоненты системы

### 1. Python Backend (FastAPI)

**Назначение:** RESTful API для основной бизнес-логики

**Технологии:**
- FastAPI - современный веб-фреймворк
- Uvicorn - ASGI сервер
- SQLAlchemy - ORM для работы с базой данных
- Pydantic - валидация данных
- Alembic - миграции базы данных

**Порт:** 8000

**Основные функции:**
- Управление меню ресторана
- API для мобильных приложений
- Интеграция с iiko (опционально)
- Обработка заказов

**Структура:**
```
backend/
├── app/
│   ├── main.py           # Главный файл приложения
│   └── routes.py         # API endpoints
├── config/
│   └── settings.py       # Настройки приложения
├── database/
│   ├── connection.py     # Подключение к БД
│   └── models.py         # Модели SQLAlchemy
├── venv/                 # Виртуальное окружение
├── requirements.txt      # Зависимости Python
└── .env                  # Переменные окружения
```

**Endpoints:**
- `GET /` - Информация об API
- `GET /health` - Проверка здоровья
- `GET /api/v1/menu` - Список элементов меню
- `POST /api/v1/menu` - Создание элемента меню
- `GET /api/v1/menu/{id}` - Получение элемента меню
- `GET /api/v1/users` - Список пользователей

### 2. Laravel Frontend (Admin Panel)

**Назначение:** Административная панель для управления системой

**Технологии:**
- Laravel 10 - PHP фреймворк
- Blade - шаблонизатор
- PHP 8.1+

**Основные функции:**
- Управление пользователями
- Управление меню через веб-интерфейс
- Просмотр статистики
- Настройки системы

**Структура:**
```
frontend/
├── app/                  # Логика приложения
├── config/               # Конфигурация
│   └── database.php      # Настройки БД
├── database/             # Миграции и сиды
├── public/               # Публичные файлы
├── resources/            # Views, CSS, JS
├── routes/               # Маршруты
├── composer.json         # Зависимости PHP
└── .env                  # Переменные окружения
```

### 3. PostgreSQL Database

**Назначение:** Хранение данных

**Версия:** PostgreSQL 12+

**Основные таблицы:**
- `users` - Пользователи системы
- `menu_items` - Элементы меню
- `migrations` - История миграций

**Схема базы данных:**

#### Таблица users
```sql
- id: SERIAL PRIMARY KEY
- email: VARCHAR(255) UNIQUE
- username: VARCHAR(100) UNIQUE
- hashed_password: VARCHAR(255)
- is_active: BOOLEAN
- is_superuser: BOOLEAN
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
```

#### Таблица menu_items
```sql
- id: SERIAL PRIMARY KEY
- name: VARCHAR(255)
- description: TEXT
- price: INTEGER (цена в копейках)
- category: VARCHAR(100)
- is_available: BOOLEAN
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
```

### 4. Nginx Web Server

**Назначение:** Реверс-прокси и веб-сервер

**Функции:**
- Обслуживание Laravel frontend
- Проксирование запросов к Python backend
- SSL терминация
- Кэширование статических файлов
- Load balancing (при необходимости)

**Конфигурация:**
```
Запросы к vezuroll.ru или b1d8d8270d0f.vps.myjino.ru → Laravel (PHP-FPM)
Запросы к api.vezuroll.ru или api.b1d8d8270d0f.vps.myjino.ru → Python Backend (port 8000)
```

## Поток данных

### 1. Запрос к админ-панели (Laravel)

```
Пользователь → Nginx (port 80/443) → PHP-FPM → Laravel → PostgreSQL
                                                     ↓
                                              HTML Response
                                                     ↓
                                               Пользователь
```

### 2. API запрос (Python Backend)

```
Клиент → Nginx (api.domain.com) → Python Backend (port 8000) → PostgreSQL
                                                                      ↓
                                                               JSON Response
                                                                      ↓
                                                                   Клиент
```

### 3. Полный цикл (Laravel + Python)

```
Админ в браузере → Laravel → HTTP запрос к Python API → PostgreSQL
                      ↓                                      ↓
                   Обработка ответа                    Возврат данных
                      ↓                                      ↓
                  Отображение данных ← JSON Response ← Python Backend
```

## Безопасность

### 1. Аутентификация

**Python Backend:**
- JWT токены (можно добавить)
- API ключи
- Rate limiting

**Laravel:**
- Laravel Sanctum для API
- Session-based auth для веб
- CSRF защита

### 2. База данных

- Параметризованные запросы (SQL injection защита)
- Отдельный пользователь с ограниченными правами
- Шифрование паролей (bcrypt)

### 3. Сетевая безопасность

- SSL/TLS шифрование (HTTPS)
- Firewall настройки
- Ограничение доступа к PostgreSQL (только localhost)

## Масштабирование

### Вертикальное масштабирование

1. **Увеличение ресурсов сервера:**
   - Больше RAM
   - Больше CPU cores
   - Faster SSD

2. **Оптимизация базы данных:**
   - Индексы
   - Connection pooling
   - Query optimization

### Горизонтальное масштабирование

1. **Python Backend:**
   - Несколько экземпляров Uvicorn
   - Load balancer (Nginx)
   - Separate API servers

2. **Laravel Frontend:**
   - Несколько PHP-FPM workers
   - Session storage в Redis
   - Asset CDN

3. **База данных:**
   - Master-Slave репликация
   - Read replicas
   - Connection pooling (PgBouncer)

## Мониторинг

### 1. Логирование

**Nginx:**
```
/var/log/nginx/iiko-base-access.log
/var/log/nginx/iiko-base-error.log
/var/log/nginx/iiko-api-access.log
/var/log/nginx/iiko-api-error.log
```

**Python Backend:**
```bash
journalctl -u iiko-backend -f
```

**Laravel:**
```
frontend/storage/logs/laravel.log
```

**PostgreSQL:**
```
/var/log/postgresql/postgresql-{version}-main.log
```

### 2. Метрики

Рекомендуемые инструменты:
- **Prometheus** - сбор метрик
- **Grafana** - визуализация
- **New Relic / Datadog** - APM (опционально)

### 3. Health Checks

**Python Backend:**
```bash
curl http://localhost:8000/health
```

**Nginx:**
```bash
systemctl status nginx
```

**PostgreSQL:**
```bash
systemctl status postgresql
```

## Backup и восстановление

### 1. База данных

**Создание backup:**
```bash
# Ежедневный backup
pg_dump -U iiko_user iiko_db > backup_$(date +%Y%m%d).sql

# Backup с компрессией
pg_dump -U iiko_user iiko_db | gzip > backup_$(date +%Y%m%d).sql.gz
```

**Восстановление:**
```bash
psql -U iiko_user -d iiko_db < backup_20240101.sql
```

**Автоматический backup (cron):**
```bash
# Добавить в crontab
0 2 * * * pg_dump -U iiko_user iiko_db | gzip > /backups/db_$(date +\%Y\%m\%d).sql.gz
```

### 2. Файлы приложения

**Git:**
```bash
git commit -am "Backup"
git push origin main
```

**rsync (для uploaded files):**
```bash
rsync -avz /var/www/iiko-base/frontend/storage/app/ backup@server:/backups/storage/
```

## Развертывание (Deployment)

### 1. Процесс деплоя

```
1. Pull код из репозитория
   ↓
2. Установка зависимостей (pip, composer)
   ↓
3. Миграции базы данных
   ↓
4. Сборка ассетов (если есть)
   ↓
5. Очистка кэша
   ↓
6. Перезапуск сервисов
```

### 2. Zero-downtime deployment

Для production с высокой нагрузкой:

1. **Blue-Green deployment:**
   - Два идентичных окружения
   - Деплой в неактивное
   - Переключение Nginx

2. **Rolling deployment:**
   - Несколько backend серверов
   - Обновление по одному
   - Load balancer переключает трафик

### 3. Rollback стратегия

```bash
# Git rollback
git revert HEAD
./scripts/deploy.sh

# Database rollback
php artisan migrate:rollback

# Восстановление из backup
psql -U iiko_user -d iiko_db < backup_previous.sql
```

## Окружения

### Development (Разработка)

```
- DEBUG = True
- Подробное логирование
- Hot reload
- Local database
```

### Staging (Тестирование)

```
- DEBUG = False
- Production-like настройки
- Тестовая БД
- SSL certificates
```

### Production (Продакшн)

```
- DEBUG = False
- Оптимизированные настройки
- Мониторинг
- Backups
- SSL certificates
```

## Интеграции

### 1. iiko Integration (опционально)

Для интеграции с системой iiko:
- API ключи iiko
- Webhook endpoints
- Синхронизация меню
- Обработка заказов

### 2. Payment systems

Можно добавить:
- Stripe
- PayPal
- Yandex.Kassa
- CloudPayments

### 3. Notification systems

- Email (SMTP)
- SMS (Twilio, etc.)
- Push notifications
- Telegram bot

## Производительность

### 1. Кэширование

**Laravel:**
```php
// Cache driver: redis/memcached
Cache::remember('menu', 60, function() {
    return MenuItem::all();
});
```

**Python:**
```python
# Можно добавить Redis caching
from functools import lru_cache

@lru_cache(maxsize=128)
def get_menu_items():
    return db.query(MenuItem).all()
```

### 2. Database optimization

- Индексы на часто запрашиваемых полях
- Connection pooling
- Query optimization
- Denormalization для read-heavy операций

### 3. Asset optimization

- Minification CSS/JS
- Image optimization
- CDN для статических файлов
- Gzip compression

## Заключение

Эта архитектура обеспечивает:
- ✅ Масштабируемость
- ✅ Безопасность
- ✅ Простоту развертывания
- ✅ Легкость поддержки
- ✅ Гибкость для расширения

Система готова для:
- Малого бизнеса (одиночный сервер)
- Средних проектов (несколько серверов)
- Масштабирования до высоких нагрузок
