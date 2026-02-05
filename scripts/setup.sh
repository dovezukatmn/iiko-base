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

# Создание необходимых директорий Laravel
print_info "Создание необходимых директорий..."
mkdir -p bootstrap/cache
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs

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
    # Проверяем, запущен ли PostgreSQL
    print_info "Проверка статуса PostgreSQL..."
    if ! systemctl is-active --quiet postgresql 2>/dev/null; then
        print_warning "PostgreSQL не запущен"
        if [ "$EUID" -eq 0 ]; then
            print_info "Попытка запуска PostgreSQL..."
            if ! systemctl start postgresql 2>/dev/null; then
                print_error "Не удалось запустить PostgreSQL"
                print_warning "Пропуск инициализации БД"
                print_info "Запустите PostgreSQL вручную: sudo systemctl start postgresql"
            fi
        else
            print_error "PostgreSQL не запущен. Запустите его командой: sudo systemctl start postgresql"
            print_warning "Пропуск инициализации БД"
        fi
    else
        print_info "PostgreSQL запущен"
    fi
    
    # Проверяем, запущен ли PostgreSQL после попытки запуска
    if ! systemctl is-active --quiet postgresql 2>/dev/null; then
        # PostgreSQL не запущен, пропускаем инициализацию
        print_info "Продолжаем настройку окружения без инициализации БД"
    else
        # Проверяем, установлен ли клиент psql
        if ! command -v psql &> /dev/null; then
            print_error "Клиент PostgreSQL (psql) не установлен"
            print_info "Установите его командой: sudo apt-get install postgresql-client"
            print_warning "Пропуск инициализации БД"
        else
            # Чтение параметров подключения
            read -p "Введите имя пользователя PostgreSQL [iiko_user]: " DB_USER
            DB_USER=${DB_USER:-iiko_user}
            
            read -sp "Введите пароль PostgreSQL: " DB_PASSWORD
            echo
            
            read -p "Введите имя базы данных [iiko_db]: " DB_NAME
            DB_NAME=${DB_NAME:-iiko_db}
            
            read -p "Введите хост PostgreSQL [localhost]: " DB_HOST
            DB_HOST=${DB_HOST:-localhost}
            
            read -p "Введите порт PostgreSQL [5432]: " DB_PORT
            DB_PORT=${DB_PORT:-5432}
            
            print_info "Проверка подключения к базе данных..."
            
            # Проверяем подключение к PostgreSQL (ОБЯЗАТЕЛЬНО с флагом -h для password auth)
            if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d postgres -c '\q' 2>/dev/null; then
                print_info "✓ Подключение к PostgreSQL успешно"
                
                # Проверяем существование базы данных (используем безопасное сравнение)
                DB_EXISTS=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname=\$\$${DB_NAME}\$\$" 2>/dev/null)
                
                if [ "$DB_EXISTS" != "1" ]; then
                    print_info "База данных $DB_NAME не существует, создание..."
                    # Используем идентификаторы вместо прямой интерполяции для безопасности
                    if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d postgres -c "CREATE DATABASE \"${DB_NAME}\";" 2>/dev/null; then
                        print_info "✓ База данных $DB_NAME создана"
                    else
                        print_error "Не удалось создать базу данных $DB_NAME"
                        print_info "Возможные причины:"
                        print_info "  - У пользователя $DB_USER нет прав на создание БД"
                        print_info "  - База данных уже существует"
                        print_warning "Попробуйте создать БД вручную: sudo -u postgres createdb -O $DB_USER $DB_NAME"
                    fi
                else
                    print_info "✓ База данных $DB_NAME уже существует"
                fi
                
                print_info "Инициализация таблиц..."
                if [ -f "database/schema.sql" ]; then
                    if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f database/schema.sql 2>/dev/null; then
                        print_info "✓ Таблицы успешно созданы"
                    else
                        print_warning "Не удалось выполнить инициализацию БД (возможно, таблицы уже существуют)"
                    fi
                else
                    print_warning "Файл database/schema.sql не найден"
                fi
                
                print_info "✓ База данных настроена успешно!"
            else
                print_error "Не удалось подключиться к PostgreSQL"
                echo ""
                print_info "═══════════════════════════════════════════════════════════"
                print_info "ДИАГНОСТИКА ПРОБЛЕМЫ:"
                print_info "═══════════════════════════════════════════════════════════"
                
                # Проверка 1: PostgreSQL запущен?
                if systemctl is-active --quiet postgresql 2>/dev/null; then
                    print_info "✓ PostgreSQL запущен"
                else
                    print_error "✗ PostgreSQL не запущен"
                    print_info "  Решение: sudo systemctl start postgresql"
                fi
                
                # Проверка 2: Пользователь существует?
                print_info ""
                print_info "Проверьте, существует ли пользователь $DB_USER:"
                print_info "  sudo -u postgres psql -c \"\\du $DB_USER\""
                print_info ""
                print_info "Если пользователь не существует, создайте его:"
                print_info "  sudo -u postgres psql -c \"CREATE USER $DB_USER WITH PASSWORD 'ваш_пароль';\""
                print_info "  sudo -u postgres psql -c \"ALTER USER $DB_USER CREATEDB;\""
                
                # Проверка 3: Аутентификация
                print_info ""
                print_info "Проверьте настройки аутентификации в pg_hba.conf:"
                print_info "  sudo nano /etc/postgresql/*/main/pg_hba.conf"
                print_info ""
                print_info "Убедитесь, что есть строка (добавьте перед строкой 'local all all peer'):"
                print_info "  host    all             all             127.0.0.1/32            md5"
                print_info "  host    all             all             ::1/128                 md5"
                print_info ""
                print_info "После изменений перезапустите PostgreSQL:"
                print_info "  sudo systemctl restart postgresql"
                
                # Проверка 4: Тест подключения
                print_info ""
                print_info "Проверьте подключение вручную:"
                print_info "  psql -h localhost -U $DB_USER -d postgres"
                print_info "  (введите пароль при запросе)"
                
                print_info "═══════════════════════════════════════════════════════════"
                print_info ""
                print_info "Подробную информацию смотрите в docs/DATABASE_ERRORS.md"
                print_warning "Пропуск инициализации БД"
            fi
        fi
    fi
fi

print_info "Настройка окружения завершена!"
echo ""
print_info "Следующие шаги:"
echo "1. Настройте .env файлы в backend/ и frontend/"
echo "2. Запустите скрипт deploy.sh для деплоя"
