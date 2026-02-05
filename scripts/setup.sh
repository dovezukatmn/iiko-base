#!/bin/bash

###############################################################################
# Скрипт настройки окружения iiko-base
# 
# Создает виртуальное окружение, устанавливает зависимости
###############################################################################

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

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

print_info "Настройка окружения в директории: $PROJECT_DIR"

# Настройка git safe.directory для избежания ошибки dubious ownership
print_info "Настройка git safe.directory..."
git config --global --add safe.directory "$PROJECT_DIR" 2>/dev/null || true

# Настройка Python backend
print_info "Настройка Python backend..."
cd backend

if [ ! -d "venv" ]; then
    print_info "Создание виртуального окружения Python..."
    python3 -m venv venv
fi

print_info "Активация виртуального окружения и установка зависимостей..."
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt

# Создание .env файла для backend
if [ ! -f ".env" ]; then
    print_info "Создание .env файла для backend..."
    cp .env.example .env
    print_warning "Не забудьте настроить .env файл!"
fi

deactivate
cd ..

# Настройка Laravel frontend
print_info "Настройка Laravel frontend..."
cd frontend

# Установка зависимостей Laravel
if [ -f "composer.json" ]; then
    print_info "Установка зависимостей Composer..."
    # Разрешаем запуск composer от root и игнорируем ошибки post-install скриптов
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-scripts || print_warning "Composer install завершился с предупреждениями"

    # Запускаем autoload-dump отдельно
    COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize --no-scripts
fi

# Создание .env файла для frontend
if [ ! -f ".env" ]; then
    print_info "Создание .env файла для frontend..."
    cp .env.example .env

    # Генерация ключа приложения (только если artisan существует)
    if [ -f "artisan" ]; then
        php artisan key:generate || print_warning "Не удалось сгенерировать ключ приложения"
    else
        print_warning "Файл artisan не найден, пропуск генерации ключа"
    fi
fi

cd ..

# Настройка прав доступа
print_info "Настройка прав доступа..."
if [ -d "frontend/storage" ]; then
    chmod -R 775 frontend/storage
fi
if [ -d "frontend/bootstrap/cache" ]; then
    chmod -R 775 frontend/bootstrap/cache
fi

# Инициализация базы данных
print_info "Инициализация базы данных..."
read -p "Хотите инициализировать базу данных? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Чтение параметров подключения
    read -p "Введите имя пользователя PostgreSQL [iiko_user]: " DB_USER
    DB_USER=${DB_USER:-iiko_user}
    
    read -sp "Введите пароль PostgreSQL: " DB_PASSWORD
    echo
    
    read -p "Введите имя базы данных [iiko_db]: " DB_NAME
    DB_NAME=${DB_NAME:-iiko_db}
    
    print_info "Инициализация таблиц..."
    PGPASSWORD=$DB_PASSWORD psql -U $DB_USER -d $DB_NAME -f database/schema.sql || print_warning "Не удалось выполнить инициализацию БД"
fi

print_info "Настройка окружения завершена!"
echo ""
print_info "Следующие шаги:"
echo "1. Настройте .env файлы в backend/ и frontend/"
echo "2. Запустите скрипт deploy.sh для деплоя"
