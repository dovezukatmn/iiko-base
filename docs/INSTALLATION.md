# Руководство по установке iiko-base на Jino VPS

## Содержание
1. [Введение](#введение)
2. [Требования](#требования)
3. [Быстрый старт](#быстрый-старт)
4. [Подробная установка](#подробная-установка)
5. [Настройка домена](#настройка-домена)
6. [SSL сертификаты](#ssl-сертификаты)
7. [Автодеплой](#автодеплой)
8. [Решение проблем](#решение-проблем)

## Введение

**iiko-base** - это полностью настроенное рабочее пространство для разработки на стеке:
- **Python (FastAPI)** - backend API
- **Laravel** - административная панель
- **PostgreSQL** - база данных
- **Nginx** - веб-сервер

Этот проект создан специально для **новичков** и предоставляет все необходимое для быстрого развертывания на VPS хостинге Jino.

## Требования

### Минимальные требования к серверу:
- **VPS**: Jino или любой другой с Ubuntu 20.04/22.04
- **RAM**: минимум 1 GB (рекомендуется 2 GB)
- **Диск**: минимум 10 GB свободного места
- **Процессор**: 1 ядро

### Необходимые знания:
- Базовые навыки работы с терминалом Linux
- Понимание SSH подключения
- Базовые знания Git (опционально)

## Быстрый старт

### Шаг 1: Подключение к серверу

```bash
# Подключитесь к вашему VPS через SSH
ssh username@your-server-ip

# Или если у вас есть ключ
ssh -i /path/to/key username@your-server-ip
```

### Шаг 2: Скачивание и установка

```bash
# Переключитесь на root пользователя
sudo su

# Скачайте проект
cd /var/www
git clone https://github.com/dovezukatmn/iiko-base.git
cd iiko-base

# Запустите автоматическую установку
chmod +x scripts/*.sh
./scripts/install.sh
```

Скрипт установит все необходимые компоненты:
- Python 3 и зависимости
- PHP 8.1 и расширения
- PostgreSQL
- Nginx
- Composer

### Шаг 3: Настройка окружения

```bash
# Настройте проект
./scripts/setup.sh
```

Вам будет предложено:
1. Инициализировать базу данных (введите `y`)
2. Ввести данные для подключения к PostgreSQL
3. Подтвердить создание таблиц

### Шаг 4: Настройка конфигурации

#### Backend (.env)
```bash
nano backend/.env
```

Измените следующие параметры:
```ini
DATABASE_URL=postgresql://iiko_user:12101991Qq!@localhost:5432/iiko_db
SECRET_KEY=your-secret-key-here
BACKEND_CORS_ORIGINS=["https://vezuroll.ru", "https://b1d8d8270d0f.vps.myjino.ru"]
```

#### Frontend (.env)
```bash
nano frontend/.env
```

Измените следующие параметры:
```ini
APP_URL=https://vezuroll.ru
DB_PASSWORD=12101991Qq!
BACKEND_API_URL=https://api.vezuroll.ru/api/v1
```

### Шаг 5: Настройка Nginx

```bash
# Откройте конфигурацию Nginx
nano nginx/iiko-base.conf
```

Замените `yourdomain.com` на ваш реальный домен (vezuroll.ru или b1d8d8270d0f.vps.myjino.ru) во всех местах.

### Шаг 6: Деплой

```bash
# Запустите деплой
./scripts/deploy.sh
```

Скрипт автоматически:
1. Установит все зависимости
2. Настроит Nginx
3. Создаст systemd сервис для Python backend
4. Запустит все сервисы

### Шаг 7: Проверка

Проверьте, что все работает:

```bash
# Проверка Python backend
curl http://localhost:8000/health

# Проверка статуса сервисов
systemctl status iiko-backend
systemctl status nginx
```

## Подробная установка

### 1. Установка зависимостей вручную

Если автоматический скрипт не сработал, выполните установку вручную:

#### PostgreSQL
```bash
# Установка
sudo apt-get update
sudo apt-get install postgresql postgresql-contrib -y

# Создание пользователя и базы данных
sudo -u postgres psql << EOF
CREATE USER iiko_user WITH PASSWORD 'your_secure_password';
CREATE DATABASE iiko_db OWNER iiko_user;
GRANT ALL PRIVILEGES ON DATABASE iiko_db TO iiko_user;
\c iiko_db
GRANT ALL PRIVILEGES ON SCHEMA public TO iiko_user;
EOF
```

#### Python и виртуальное окружение
```bash
# Установка Python
sudo apt-get install python3 python3-pip python3-venv -y

# Создание виртуального окружения
cd /var/www/iiko-base/backend
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

#### PHP и Composer
```bash
# Установка PHP
sudo apt-get install php php-fpm php-pgsql php-mbstring php-xml php-curl -y

# Установка Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Установка зависимостей Laravel
cd /var/www/iiko-base/frontend
composer install --no-dev
```

#### Nginx
```bash
# Установка
sudo apt-get install nginx -y

# Копирование конфигурации
sudo cp /var/www/iiko-base/nginx/iiko-base.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/iiko-base.conf /etc/nginx/sites-enabled/

# Проверка конфигурации
sudo nginx -t

# Перезапуск
sudo systemctl restart nginx
```

### 2. Инициализация базы данных

```bash
# Подключитесь к PostgreSQL
sudo -u postgres psql -d iiko_db

# Выполните SQL скрипты
\i /var/www/iiko-base/database/schema.sql
\q
```

### 3. Запуск Python backend

#### Вариант 1: Через systemd (рекомендуется)
```bash
# Создайте systemd сервис
sudo nano /etc/systemd/system/iiko-backend.service
```

Вставьте:
```ini
[Unit]
Description=iiko-base Python Backend
After=network.target

[Service]
Type=simple
User=iiko
WorkingDirectory=/var/www/iiko-base/backend
Environment="PATH=/var/www/iiko-base/backend/venv/bin"
ExecStart=/var/www/iiko-base/backend/venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000
Restart=always

[Install]
WantedBy=multi-user.target
```

Запустите:
```bash
sudo systemctl daemon-reload
sudo systemctl enable iiko-backend
sudo systemctl start iiko-backend
```

#### Вариант 2: Вручную (для тестирования)
```bash
cd /var/www/iiko-base/backend
source venv/bin/activate
uvicorn app.main:app --host 0.0.0.0 --port 8000
```

## Настройка домена

### 1. Добавление DNS записей

В панели управления вашего регистратора доменов (например, на Jino) добавьте:

**A записи:**
```
vezuroll.ru                  → IP_ADDRESS_OF_YOUR_VPS
www.vezuroll.ru              → IP_ADDRESS_OF_YOUR_VPS
api.vezuroll.ru              → IP_ADDRESS_OF_YOUR_VPS
b1d8d8270d0f.vps.myjino.ru   → IP_ADDRESS_OF_YOUR_VPS
api.b1d8d8270d0f.vps.myjino.ru → IP_ADDRESS_OF_YOUR_VPS
```

### 2. Обновление конфигурации Nginx

```bash
# Откройте конфигурацию
sudo nano /etc/nginx/sites-available/iiko-base.conf

# Замените yourdomain.com на ваш домен (vezuroll.ru или b1d8d8270d0f.vps.myjino.ru)
# Сохраните (Ctrl+O) и выйдите (Ctrl+X)

# Перезапустите Nginx
sudo systemctl restart nginx
```

## SSL сертификаты

### Установка Certbot (Let's Encrypt)

```bash
# Установка Certbot
sudo apt-get install certbot python3-certbot-nginx -y

# Получение SSL сертификата
sudo certbot --nginx -d vezuroll.ru -d www.vezuroll.ru -d api.vezuroll.ru

# Следуйте инструкциям на экране
```

### Использование SSL конфигурации

```bash
# Скопируйте SSL конфигурацию
sudo cp /var/www/iiko-base/nginx/iiko-base-ssl.conf /etc/nginx/sites-available/iiko-base.conf

# Отредактируйте пути к сертификатам и домены
sudo nano /etc/nginx/sites-available/iiko-base.conf

# Проверьте конфигурацию
sudo nginx -t

# Перезапустите Nginx
sudo systemctl restart nginx
```

### Автоматическое обновление сертификатов

Certbot автоматически настраивает cron для обновления. Проверьте:

```bash
sudo certbot renew --dry-run
```

## Автодеплой

### Вариант 1: Git hooks (простой)

Создайте post-receive hook на сервере:

```bash
# На сервере
cd /var/www/iiko-base
git init --bare ~/iiko-base-repo.git

# Создайте hook
nano ~/iiko-base-repo.git/hooks/post-receive
```

Добавьте:
```bash
#!/bin/bash
GIT_WORK_TREE=/var/www/iiko-base git checkout -f
cd /var/www/iiko-base
./scripts/deploy.sh
```

Сделайте его исполняемым:
```bash
chmod +x ~/iiko-base-repo.git/hooks/post-receive
```

На вашем локальном компьютере:
```bash
git remote add production ssh://username@your-server-ip/~/iiko-base-repo.git
git push production main
```

### Вариант 2: GitHub Actions (продвинутый)

Создайте `.github/workflows/deploy.yml` в репозитории:

```yaml
name: Deploy to Jino VPS

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.HOST }}
          username: ${{ secrets.USERNAME }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd /var/www/iiko-base
            git pull origin main
            ./scripts/deploy.sh
```

Добавьте секреты в GitHub:
- `HOST`: IP адрес вашего сервера
- `USERNAME`: имя пользователя SSH
- `SSH_KEY`: приватный SSH ключ

### Вариант 3: Webhook (альтернатива)

Установите webhook сервис:

```bash
# На сервере
cd /var/www/iiko-base/scripts
nano webhook.sh
```

Добавьте:
```bash
#!/bin/bash
cd /var/www/iiko-base
git pull origin main
./scripts/deploy.sh
```

Настройте webhook в вашем репозитории GitHub/GitLab для вызова этого скрипта.

## Решение проблем

### Python backend не запускается

```bash
# Проверьте логи
sudo journalctl -u iiko-backend -f

# Проверьте виртуальное окружение
cd /var/www/iiko-base/backend
source venv/bin/activate
python -m uvicorn app.main:app --host 0.0.0.0 --port 8000
```

### Nginx показывает ошибку 502

```bash
# Проверьте, запущен ли Python backend
systemctl status iiko-backend

# Проверьте логи Nginx
tail -f /var/log/nginx/error.log

# Проверьте права доступа
sudo chown -R www-data:www-data /var/www/iiko-base
```

### База данных недоступна

```bash
# Проверьте статус PostgreSQL
systemctl status postgresql

# Проверьте подключение
psql -U iiko_user -d iiko_db -h localhost

# Проверьте пароль в .env файлах
```

### Laravel показывает ошибки

```bash
# Очистите кэш
cd /var/www/iiko-base/frontend
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Проверьте права на директории
chmod -R 775 storage bootstrap/cache
```

### Проблемы с правами доступа

```bash
# Установите правильные права
sudo chown -R www-data:www-data /var/www/iiko-base/frontend
sudo chown -R iiko:iiko /var/www/iiko-base/backend

# Для storage и cache
cd /var/www/iiko-base/frontend
sudo chmod -R 775 storage bootstrap/cache
```

## Дополнительная информация

### Структура проекта
```
iiko-base/
├── backend/           # Python FastAPI приложение
├── frontend/          # Laravel административная панель
├── database/          # SQL скрипты
├── nginx/             # Конфигурация Nginx
├── scripts/           # Скрипты установки и деплоя
└── docs/              # Документация
```

### Полезные команды

```bash
# Просмотр логов backend
sudo journalctl -u iiko-backend -f

# Просмотр логов Nginx
sudo tail -f /var/log/nginx/error.log

# Перезапуск сервисов
sudo systemctl restart iiko-backend
sudo systemctl restart nginx
sudo systemctl restart postgresql

# Проверка статуса
sudo systemctl status iiko-backend
sudo systemctl status nginx

# Мониторинг процессов
htop
```

### Контакты и поддержка

Если у вас возникли вопросы:
1. Проверьте раздел "Решение проблем"
2. Создайте issue в GitHub репозитории
3. Проверьте логи для детальной информации об ошибках

## Заключение

Поздравляем! Вы успешно развернули iiko-base на своем VPS сервере.

**Следующие шаги:**
1. Настройте SSL сертификаты для безопасности
2. Настройте автоматическое резервное копирование базы данных
3. Настройте мониторинг сервера
4. Начните разработку своего приложения!

**Важно:**
- Регулярно обновляйте систему: `sudo apt-get update && sudo apt-get upgrade`
- Делайте резервные копии базы данных
- Используйте сильные пароли
- Следите за логами на предмет ошибок
