# 🚀 Руководство по запуску iiko-base

## ✅ Проверка готовности

Система **готова к запуску** после обновления PHP версии с 8.1 на 8.3.

### Проверенные компоненты

- ✅ **PHP 8.3** - установлен и настроен
- ✅ **Python Backend** - работает, API доступен
- ✅ **Laravel Frontend** - настроен
- ✅ **Nginx** - конфигурация обновлена для PHP 8.3
- ✅ **Docker Compose** - конфигурация валидна
- ✅ **Все скрипты** - синтаксис проверен

### Что было исправлено

Обновлены все ссылки с `php8.1-fpm` на `php8.3-fpm` в следующих файлах:
- `scripts/deploy.sh`
- `nginx/iiko-base.conf`
- `nginx/iiko-base-ssl.conf`
- Документация (README.md, QUICK_FIX_502.md, и др.)

## 🔧 Варианты запуска

### Вариант 1: Docker Compose (рекомендуется для разработки)

```bash
# Запустить все сервисы
docker compose up -d

# Проверить статус
docker compose ps

# Просмотр логов
docker compose logs -f

# Остановить
docker compose down
```

После запуска приложение будет доступно:
- **Frontend/API**: http://localhost
- **Backend API**: http://localhost:8000
- **API Docs**: http://localhost:8000/api/v1/docs
- **Health Check**: http://localhost:8000/health

### Вариант 2: Системный деплой (для production)

#### Предварительная установка

```bash
# Запустить проверку готовности
bash scripts/verify-deployment.sh

# Установить зависимости
sudo bash scripts/install.sh

# Настроить окружение
bash scripts/setup.sh
```

#### Деплой

```bash
# Выполнить деплой
sudo bash scripts/deploy.sh
```

Скрипт деплоя автоматически:
1. Обновит код из git
2. Установит Python зависимости
3. Установит Laravel зависимости
4. Создаст .env файл (если не существует)
5. Применит миграции БД
6. Настроит Nginx
7. Создаст systemd сервис
8. **Запустит PHP 8.3 FPM** ✓
9. Запустит все сервисы

## 🌐 Доступ к приложению

### Production URLs (после деплоя на сервер)

- **Основной сайт**: https://vezuroll.ru
- **Панель администратора**: https://vezuroll.ru/admin
- **Backend API**: https://api.vezuroll.ru
- **API документация**: https://api.vezuroll.ru/api/v1/docs

### Локальная разработка

- **Frontend**: http://localhost
- **Backend API**: http://localhost:8000
- **API Docs**: http://localhost:8000/api/v1/docs

## 🔍 Проверка работоспособности

### Быстрая проверка

```bash
# Backend health check
curl http://localhost:8000/health
# Ответ: {"status":"healthy"}

# Backend info
curl http://localhost:8000/
# Ответ: {"message":"iiko-base API","version":"1.0.0","docs":"/api/v1/docs"}

# API documentation
curl http://localhost:8000/api/v1/docs
# Должен вернуть HTML страницу Swagger UI
```

### Проверка сервисов (production)

```bash
# Все сервисы
sudo systemctl status iiko-backend
sudo systemctl status php8.3-fpm
sudo systemctl status nginx
sudo systemctl status postgresql

# Или кратко
sudo systemctl status iiko-backend php8.3-fpm nginx postgresql --no-pager
```

### Просмотр логов

```bash
# Backend
sudo journalctl -u iiko-backend -f

# PHP-FPM
sudo journalctl -u php8.3-fpm -f

# Nginx
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/nginx/iiko-base-error.log
```

## 🐛 Устранение проблем

### Ошибка 502 Bad Gateway

**Причина**: PHP-FPM не запущен

**Решение**:
```bash
sudo systemctl start php8.3-fpm
sudo systemctl enable php8.3-fpm
sudo systemctl restart nginx
```

Подробнее: [QUICK_FIX_502.md](../QUICK_FIX_502.md)

### Backend не отвечает

**Проверка**:
```bash
sudo systemctl status iiko-backend
sudo journalctl -u iiko-backend -n 50
```

**Перезапуск**:
```bash
sudo systemctl restart iiko-backend
```

### Проблемы с БД

**Проверка подключения**:
```bash
# Из директории frontend
cd /var/www/iiko-base/frontend
php artisan migrate:status
```

**Создание БД** (если не существует):
```bash
sudo -u postgres createdb -O iiko_user iiko_db
```

## 📋 Контрольный список для первого запуска

- [ ] Проверить версию PHP: `php -v` (должна быть 8.3.x)
- [ ] Запустить проверку: `bash scripts/verify-deployment.sh`
- [ ] Установить зависимости: `sudo bash scripts/install.sh`
- [ ] Настроить окружение: `bash scripts/setup.sh`
- [ ] Выполнить деплой: `sudo bash scripts/deploy.sh`
- [ ] Проверить сервисы: `systemctl status iiko-backend php8.3-fpm nginx`
- [ ] Открыть в браузере: http://vezuroll.ru
- [ ] Проверить API: http://api.vezuroll.ru/health
- [ ] Проверить документацию: http://api.vezuroll.ru/api/v1/docs

## 🔐 Безопасность

### Важные настройки

1. **Изменить пароли** в `.env` файлах:
   - `frontend/.env` - DB_PASSWORD
   - `backend/.env` - DATABASE_URL, SECRET_KEY

2. **Настроить firewall**:
```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

3. **Получить SSL сертификат** (для production):
```bash
sudo certbot --nginx -d vezuroll.ru -d api.vezuroll.ru
```

## 📊 Мониторинг

### Статус всех сервисов

```bash
# Создать скрипт для быстрой проверки
echo '#!/bin/bash
echo "=== Backend ==="
systemctl status iiko-backend --no-pager | head -3
echo ""
echo "=== PHP-FPM ==="
systemctl status php8.3-fpm --no-pager | head -3
echo ""
echo "=== Nginx ==="
systemctl status nginx --no-pager | head -3
echo ""
echo "=== PostgreSQL ==="
systemctl status postgresql --no-pager | head -3
' > /tmp/check-services.sh
chmod +x /tmp/check-services.sh
sudo /tmp/check-services.sh
```

### Проверка портов

```bash
# Проверить, что все порты слушают
sudo netstat -tuln | grep -E '(:80|:443|:8000|:5432)'
```

## 💡 Полезные команды

### Перезапуск всех сервисов

```bash
sudo systemctl restart php8.3-fpm iiko-backend nginx
```

### Обновление приложения

```bash
cd /var/www/iiko-base
git pull origin main
sudo bash scripts/deploy.sh
```

### Просмотр активных подключений

```bash
# Nginx
sudo tail -f /var/log/nginx/iiko-base-access.log

# Backend
sudo journalctl -u iiko-backend -f
```

## 📞 Дополнительная помощь

- [Исправление 502 ошибки](../QUICK_FIX_502.md)
- [Подробное руководство по 502](../docs/502_ERROR_FIX.md)
- [История изменений](../ИСПРАВЛЕНИЕ_502.md)
- [README](../README.md)

---

**Статус**: ✅ Готово к запуску  
**Дата обновления**: 8 февраля 2026  
**Версия PHP**: 8.3  
**Версия Laravel**: 10.50.0  
**Версия Python**: 3.12.3
