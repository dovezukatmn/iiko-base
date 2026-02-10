#!/bin/bash

###############################################################################
# Скрипт проверки готовности к деплою iiko-base
# 
# Проверяет конфигурацию, зависимости и совместимость
###############################################################################

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Счетчики
PASSED=0
FAILED=0
WARNINGS=0

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[✓]${NC} $1"
    ((PASSED++))
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
    ((FAILED++))
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
    ((WARNINGS++))
}

# Определение директории проекта
PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$PROJECT_DIR"

print_info "Проверка готовности к деплою: $PROJECT_DIR"
echo ""

# 1. Проверка версий ПО
print_info "=== Проверка версий ПО ==="

if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
    if [[ "$PHP_VERSION" == "8.3" ]]; then
        print_success "PHP 8.3 установлен"
    else
        print_warning "PHP версия: $PHP_VERSION (ожидается 8.3)"
    fi
else
    print_error "PHP не установлен"
fi

if command -v python3 &> /dev/null; then
    PYTHON_VERSION=$(python3 --version | cut -d " " -f 2)
    print_success "Python установлен: $PYTHON_VERSION"
else
    print_error "Python3 не установлен"
fi

if command -v nginx &> /dev/null; then
    NGINX_VERSION=$(nginx -v 2>&1 | cut -d "/" -f 2)
    print_success "Nginx установлен: $NGINX_VERSION"
else
    print_warning "Nginx не установлен"
fi

if command -v docker &> /dev/null; then
    DOCKER_VERSION=$(docker --version | cut -d " " -f 3 | tr -d ",")
    print_success "Docker установлен: $DOCKER_VERSION"
else
    print_warning "Docker не установлен (опционально)"
fi

echo ""

# 2. Проверка синтаксиса скриптов
print_info "=== Проверка синтаксиса скриптов ==="

for script in scripts/*.sh; do
    if [[ -f "$script" ]]; then
        if bash -n "$script" 2>/dev/null; then
            print_success "$(basename $script) - синтаксис OK"
        else
            print_error "$(basename $script) - ошибка синтаксиса"
        fi
    fi
done

echo ""

# 3. Проверка конфигурации Nginx
print_info "=== Проверка конфигурации Nginx ==="

# Проверка на использование php8.3-fpm
if grep -q "php8.3-fpm" nginx/*.conf 2>/dev/null; then
    print_success "Nginx настроен на PHP 8.3"
else
    print_error "Nginx не настроен на PHP 8.3"
fi

# Проверка на старые версии PHP
if grep -q "php8.1-fpm" nginx/*.conf 2>/dev/null; then
    print_error "Найдены ссылки на старую версию PHP 8.1"
fi

echo ""

# 4. Проверка backend (Python)
print_info "=== Проверка Python Backend ==="

if [[ -f "backend/requirements.txt" ]]; then
    print_success "requirements.txt найден"
    
    # Проверка синтаксиса Python файлов
    if python3 -m py_compile backend/app/main.py 2>/dev/null; then
        print_success "main.py - синтаксис OK"
    else
        print_error "main.py - ошибка синтаксиса"
    fi
else
    print_error "requirements.txt не найден"
fi

if [[ -d "backend/venv" ]]; then
    print_success "Python virtual environment существует"
else
    print_warning "Python virtual environment не создан"
fi

echo ""

# 5. Проверка frontend (Laravel)
print_info "=== Проверка Laravel Frontend ==="

if [[ -f "frontend/composer.json" ]]; then
    print_success "composer.json найден"
else
    print_error "composer.json не найден"
fi

if [[ -f "frontend/.env.example" ]]; then
    print_success ".env.example найден"
else
    print_error ".env.example не найден"
fi

if [[ -f "frontend/.env" ]]; then
    print_success ".env файл существует"
else
    print_warning ".env файл не создан (будет создан при деплое)"
fi

echo ""

# 6. Проверка Docker конфигурации
print_info "=== Проверка Docker конфигурации ==="

if [[ -f "docker-compose.yml" ]]; then
    print_success "docker-compose.yml найден"
    
    if command -v docker &> /dev/null; then
        if docker compose config &> /dev/null; then
            print_success "docker-compose.yml - синтаксис OK"
        else
            print_error "docker-compose.yml - ошибка конфигурации"
        fi
    fi
else
    print_error "docker-compose.yml не найден"
fi

echo ""

# 7. Проверка структуры директорий
print_info "=== Проверка структуры директорий ==="

required_dirs=(
    "backend"
    "backend/app"
    "frontend"
    "frontend/public"
    "nginx"
    "scripts"
    "database"
)

for dir in "${required_dirs[@]}"; do
    if [[ -d "$dir" ]]; then
        print_success "Директория $dir существует"
    else
        print_error "Директория $dir не найдена"
    fi
done

echo ""

# 8. Проверка портов
print_info "=== Проверка доступности портов ==="

check_port() {
    local port=$1
    local service=$2
    
    if command -v nc &> /dev/null; then
        if nc -z localhost $port 2>/dev/null; then
            print_warning "Порт $port ($service) уже используется"
        else
            print_success "Порт $port ($service) свободен"
        fi
    else
        if netstat -tuln 2>/dev/null | grep -q ":$port "; then
            print_warning "Порт $port ($service) уже используется"
        else
            print_success "Порт $port ($service) свободен"
        fi
    fi
}

check_port 8000 "Backend API"
check_port 80 "Nginx HTTP"
check_port 5432 "PostgreSQL"

echo ""

# 9. Проверка совместимости версий
print_info "=== Проверка совместимости ==="

# Проверка соответствия PHP в скриптах и конфигах
SCRIPT_PHP=$(grep -o "php8\.[0-9]" scripts/deploy.sh | head -1)
NGINX_PHP=$(grep -o "php8\.[0-9]" nginx/iiko-base.conf | head -1)

if [[ "$SCRIPT_PHP" == "$NGINX_PHP" ]]; then
    print_success "Версии PHP согласованы: $SCRIPT_PHP"
else
    print_error "Несоответствие версий PHP: скрипт=$SCRIPT_PHP, nginx=$NGINX_PHP"
fi

echo ""

# Итоговая статистика
print_info "=== Итоги проверки ==="
echo -e "${GREEN}Успешно: $PASSED${NC}"
echo -e "${YELLOW}Предупреждения: $WARNINGS${NC}"
echo -e "${RED}Ошибки: $FAILED${NC}"
echo ""

if [[ $FAILED -eq 0 ]]; then
    print_success "✓ Все критические проверки пройдены успешно!"
    print_info "Система готова к деплою"
    exit 0
else
    print_error "✗ Обнаружены критические ошибки"
    print_info "Исправьте ошибки перед деплоем"
    exit 1
fi
