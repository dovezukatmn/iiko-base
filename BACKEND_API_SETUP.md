# ⚙️ Конфигурация Backend API URL

## Быстрая настройка

### 1. Для Docker (рекомендуется)

Отредактируйте `/var/www/iiko-base/frontend/.env`:

```env
BACKEND_API_URL=http://backend:8000/api/v1
```

Используйте имя сервиса из `docker-compose.yml` (`backend`), а не `localhost`!

### 2. Для локальной разработки

```env
BACKEND_API_URL=http://localhost:8000/api/v1
```

### 3. Для production с разными доменами

Если frontend и backend на разных доменах:

```env
BACKEND_API_URL=https://api.vezuroll.ru/api/v1
```

**Важно:** Убедитесь что домен frontend добавлен в CORS backend!

Отредактируйте `/var/www/iiko-base/backend/.env`:

```env
BACKEND_CORS_ORIGINS='["https://vezuroll.ru", "https://api.vezuroll.ru"]'
```

### 4. Для production с Nginx reverse proxy

Если Nginx проксирует запросы на один домен:

```env
# Frontend .env
BACKEND_API_URL=https://vezuroll.ru/api/v1
```

И настройте Nginx для проксирования `/api/*`:

```nginx
# В конфиге Nginx для Laravel
location /api/ {
    proxy_pass http://backend:8000/api/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

## Проверка настройки

После изменения `.env`:

```bash
# 1. Очистите кеш Laravel
cd /var/www/iiko-base/frontend
php artisan config:clear
php artisan cache:clear

# 2. Перезапустите сервисы
docker-compose restart nginx
# или
sudo systemctl restart php8.3-fpm nginx

# 3. Проверьте подключение
curl http://localhost:8000/api/v1/health
# Должно вернуть: {"status":"healthy"}
```

## Типичные ошибки

### ❌ Использование localhost в Docker

```env
# НЕПРАВИЛЬНО для Docker:
BACKEND_API_URL=http://localhost:8000/api/v1

# ПРАВИЛЬНО для Docker:
BACKEND_API_URL=http://backend:8000/api/v1
```

### ❌ Забыли /api/v1 в конце

```env
# НЕПРАВИЛЬНО:
BACKEND_API_URL=http://backend:8000

# ПРАВИЛЬНО:
BACKEND_API_URL=http://backend:8000/api/v1
```

### ❌ CORS не настроен

Если frontend на `https://vezuroll.ru`, а backend на `https://api.vezuroll.ru`:

```env
# Backend .env ДОЛЖЕН содержать:
BACKEND_CORS_ORIGINS='["https://vezuroll.ru"]'
```

## Отладка

### Проверить откуда Laravel делает запросы

```bash
# В контейнере frontend (если Docker):
docker exec -it iiko-frontend php artisan tinker
>>> config('app.backend_api_url');
# Должно вывести правильный URL

# Или просто:
grep BACKEND_API_URL /var/www/iiko-base/frontend/.env
```

### Проверить доступность из Laravel

```bash
# Из контейнера/сервера Laravel:
curl -v http://backend:8000/api/v1/health

# Если недоступен, проверьте:
docker network ls
docker network inspect iiko-network
# Backend и frontend должны быть в одной сети!
```

### Включить debug логирование

В Laravel (`frontend/config/logging.php`):

```php
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'debug',
    ],
],
```

Затем проверьте логи:
```bash
tail -f /var/www/iiko-base/frontend/storage/logs/laravel.log
```

## Автоматическая настройка

Используйте скрипт для быстрой настройки:

```bash
#!/bin/bash
# setup-backend-url.sh

ENV_FILE="/var/www/iiko-base/frontend/.env"

# Определить тип окружения
if [ -f /.dockerenv ]; then
    BACKEND_URL="http://backend:8000/api/v1"
else
    BACKEND_URL="http://localhost:8000/api/v1"
fi

# Обновить .env
if grep -q "^BACKEND_API_URL=" "$ENV_FILE"; then
    sed -i "s|^BACKEND_API_URL=.*|BACKEND_API_URL=$BACKEND_URL|" "$ENV_FILE"
else
    echo "BACKEND_API_URL=$BACKEND_URL" >> "$ENV_FILE"
fi

echo "✓ BACKEND_API_URL установлен в: $BACKEND_URL"
php artisan config:clear
```

Запуск:
```bash
chmod +x setup-backend-url.sh
./setup-backend-url.sh
```

---

**См. также:**
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Устранение неполадок
- [README.md](README.md) - Общая документация
