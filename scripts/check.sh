#!/bin/bash

###############################################################################
# Скрипт проверки корректности установки iiko-base
# 
# Проверяет наличие всех необходимых файлов и директорий
###############################################################################

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}!${NC} $1"
}

# Определение директории проекта
PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$PROJECT_DIR"

echo "Проверка установки iiko-base..."
echo "Директория проекта: $PROJECT_DIR"
echo ""

errors=0
warnings=0

# Проверка основных директорий
echo "=== Проверка структуры проекта ==="
for dir in backend frontend database scripts nginx; do
    if [ -d "$dir" ]; then
        print_success "Директория $dir существует"
    else
        print_error "Директория $dir отсутствует"
        ((errors++))
    fi
done
echo ""

# Проверка Laravel директорий
echo "=== Проверка структуры Laravel ==="
laravel_dirs=(
    "frontend/bootstrap/cache"
    "frontend/storage"
    "frontend/storage/app"
    "frontend/storage/app/public"
    "frontend/storage/framework"
    "frontend/storage/framework/cache"
    "frontend/storage/framework/cache/data"
    "frontend/storage/framework/sessions"
    "frontend/storage/framework/testing"
    "frontend/storage/framework/views"
    "frontend/storage/logs"
)

for dir in "${laravel_dirs[@]}"; do
    if [ -d "$dir" ]; then
        print_success "$dir существует"
        # Проверка прав доступа
        if [ -w "$dir" ]; then
            : # директория доступна для записи
        else
            print_warning "$dir не доступна для записи"
            ((warnings++))
        fi
    else
        print_error "$dir отсутствует"
        ((errors++))
    fi
done
echo ""

# Проверка файлов базы данных
echo "=== Проверка файлов базы данных ==="
for file in database/init.sql database/schema.sql; do
    if [ -f "$file" ]; then
        print_success "$file существует"
    else
        print_error "$file отсутствует"
        ((errors++))
    fi
done
echo ""

# Проверка .env файлов
echo "=== Проверка конфигурации ==="
for env_file in backend/.env frontend/.env; do
    if [ -f "$env_file" ]; then
        print_success "$env_file существует"
    else
        print_warning "$env_file отсутствует (будет создан при запуске setup.sh)"
        ((warnings++))
    fi
done
echo ""

# Проверка зависимостей
echo "=== Проверка зависимостей ==="
commands=("python3" "pip" "php" "composer" "git")
for cmd in "${commands[@]}"; do
    if command -v "$cmd" &> /dev/null; then
        version=$($cmd --version 2>&1 | head -n1)
        print_success "$cmd установлен ($version)"
    else
        print_warning "$cmd не установлен"
        ((warnings++))
    fi
done
echo ""

# Проверка PostgreSQL
if command -v psql &> /dev/null; then
    psql_version=$(psql --version)
    print_success "PostgreSQL установлен ($psql_version)"
    
    # Проверка подключения (только если PostgreSQL запущен локально)
    if systemctl is-active --quiet postgresql 2>/dev/null || pgrep -x postgres > /dev/null 2>&1; then
        print_success "PostgreSQL запущен"
    else
        print_warning "PostgreSQL не запущен или используется Docker"
    fi
else
    print_warning "PostgreSQL не установлен (можно использовать Docker)"
    ((warnings++))
fi
echo ""

# Итоги
echo "=== Результаты проверки ==="
if [ $errors -eq 0 ] && [ $warnings -eq 0 ]; then
    echo -e "${GREEN}✓ Все проверки пройдены успешно!${NC}"
    exit 0
elif [ $errors -eq 0 ]; then
    echo -e "${YELLOW}Проверка завершена с предупреждениями: $warnings${NC}"
    echo "Запустите ./scripts/setup.sh для завершения настройки"
    exit 0
else
    echo -e "${RED}Проверка завершена с ошибками: $errors${NC}"
    echo -e "${YELLOW}Предупреждений: $warnings${NC}"
    echo ""
    echo "Рекомендации:"
    echo "1. Убедитесь, что репозиторий склонирован полностью"
    echo "2. Запустите ./scripts/setup.sh для создания недостающих директорий"
    echo "3. Если проблема сохраняется, проверьте FAQ: docs/FAQ.md"
    exit 1
fi
