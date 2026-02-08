#!/bin/bash

###############################################################################
# Скрипт деплоя iiko-base
# 
# Запускает приложение и настраивает автозапуск
###############################################################################

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Константы для таймаутов
DB_CHECK_TIMEOUT=10  # Таймаут для проверки подключения к БД (секунды)

print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Определение директории проекта
PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$PROJECT_DIR"

print_info "Деплой приложения из директории: $PROJECT_DIR"

# Остановка существующих процессов
print_info "Остановка существующих процессов..."
pkill -f "uvicorn" || true
systemctl stop nginx || true

# Обновление кода (если используется git)
if [ -d ".git" ]; then
    print_info "Обновление кода из репозитория..."
    git pull origin main || git pull origin master || print_warning "Не удалось обновить код"
fi

# Установка/обновление зависимостей Python
print_info "Обновление зависимостей Python..."
cd backend
source venv/bin/activate
pip install -r requirements.txt
deactivate
cd ..

# Установка/обновление зависимостей Laravel
print_info "Обновление зависимостей Laravel..."
cd frontend

# Убедимся, что необходимые директории Laravel существуют
mkdir -p resources/views storage/framework/{sessions,views,cache} storage/logs bootstrap/cache

# Создание .env файла, если он не существует
if [ ! -f .env ]; then
    print_info "Создание файла .env из .env.example..."
    cp .env.example .env
    print_warning "Не забудьте настроить параметры в .env (DB, URL и др.)"
fi

composer install --no-dev --optimize-autoloader

# Генерация ключа приложения, если он не задан
if ! grep -qE "^APP_KEY=.+" .env || grep -qE "^APP_KEY=$" .env; then
    print_info "Генерация ключа приложения..."
    php artisan key:generate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
cd ..

# Применение миграций базы данных (Laravel)
print_info "Проверка подключения к базе данных..."
cd frontend

# Загрузка настроек БД из .env
if [ -f .env ]; then
    DB_HOST=$(grep "^DB_HOST=" .env | cut -d'=' -f2 | tr -d ' ')
    DB_PORT=$(grep "^DB_PORT=" .env | cut -d'=' -f2 | tr -d ' ')
    DB_USER=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2 | tr -d ' ')
    DB_PASSWORD=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2 | tr -d ' ')
    DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2 | tr -d ' ')
    
    # Проверка подключения к БД перед миграциями
    # Используем прямое подключение к PostgreSQL без зависимостей Laravel
    export PGPASSWORD="$DB_PASSWORD"
    export PGCONNECT_TIMEOUT=5
    
    if timeout "$DB_CHECK_TIMEOUT" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c '\q' 2>/dev/null; then
        print_info "✓ Подключение к БД успешно"
        print_info "Применение миграций..."
        php artisan migrate --force || print_warning "Миграции не выполнены"
    else
        print_warning "Не удалось подключиться к базе данных"
        print_info "Проверьте настройки БД в файле .env:"
        print_info "  - DB_HOST, DB_PORT, DB_DATABASE"
        print_info "  - DB_USERNAME, DB_PASSWORD"
        print_info ""
        print_info "Убедитесь, что PostgreSQL запущен:"
        print_info "  sudo systemctl status postgresql"
        print_info ""
        print_info "Создайте пользователя БД, если он не существует:"
        print_info "  sudo -u postgres psql -c \"CREATE USER $DB_USER WITH PASSWORD 'ваш_пароль';\""
        print_info "  sudo -u postgres psql -c \"ALTER USER $DB_USER CREATEDB;\""
        print_info ""
        print_info "Создайте базу данных, если она не существует:"
        print_info "  sudo -u postgres createdb -O $DB_USER $DB_NAME"
        print_info ""
        print_info "Подробная информация в docs/DATABASE_ERRORS.md"
        print_warning "Пропуск миграций"
    fi
    
    # Очистка переменных окружения с паролем
    unset PGPASSWORD
    unset PGCONNECT_TIMEOUT
else
    print_error "Файл .env не найден"
    print_warning "Пропуск миграций"
fi

cd ..

# Настройка Nginx
print_info "Настройка Nginx..."
if [ ! -f "/etc/nginx/sites-available/iiko-base" ]; then
    cp nginx/iiko-base.conf /etc/nginx/sites-available/iiko-base
    ln -s /etc/nginx/sites-available/iiko-base /etc/nginx/sites-enabled/
    print_info "Конфигурация Nginx создана"
else
    print_info "Обновление конфигурации Nginx..."
    cp nginx/iiko-base.conf /etc/nginx/sites-available/iiko-base
fi

# Проверка конфигурации Nginx
nginx -t

# Создание systemd сервиса для Python backend
print_info "Создание systemd сервиса..."
cat > /etc/systemd/system/iiko-backend.service << EOF
[Unit]
Description=iiko-base Python Backend
After=network.target

[Service]
Type=simple
User=iiko
WorkingDirectory=$PROJECT_DIR/backend
Environment="PATH=$PROJECT_DIR/backend/venv/bin"
Environment="PYTHONPATH=$PROJECT_DIR/backend"
ExecStart=$PROJECT_DIR/backend/venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000
Restart=always

[Install]
WantedBy=multi-user.target
EOF

# Перезагрузка systemd
systemctl daemon-reload

# Запуск сервисов
print_info "Запуск сервисов..."
systemctl enable iiko-backend
systemctl restart iiko-backend

# Запуск PHP-FPM (требуется для Laravel frontend)
print_info "Запуск PHP-FPM..."
systemctl enable php8.3-fpm || systemctl enable php-fpm || print_warning "PHP-FPM service не найден"
systemctl restart php8.3-fpm || systemctl restart php-fpm || print_warning "Не удалось перезапустить PHP-FPM"

systemctl restart nginx

# Проверка статуса
print_info "Проверка статуса сервисов..."
systemctl status iiko-backend --no-pager || true
if ! systemctl status php8.3-fpm --no-pager 2>/dev/null && ! systemctl status php-fpm --no-pager 2>/dev/null; then
    print_warning "PHP-FPM не запущен"
fi
systemctl status nginx --no-pager || true

print_info "Деплой завершен успешно!"
echo ""
print_info "Сервисы:"
echo "  - Python Backend: http://localhost:8000"
echo "  - Laravel Admin: через Nginx"
echo ""
print_info "Проверьте логи:"
echo "  - Backend: journalctl -u iiko-backend -f"
echo "  - Nginx: tail -f /var/log/nginx/error.log"
