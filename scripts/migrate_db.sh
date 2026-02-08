#!/bin/bash

###############################################################################
# Скрипт миграции базы данных iiko-base
#
# Безопасно добавляет новые таблицы и колонки в существующую базу данных.
# Не удаляет существующие данные. Можно запускать повторно.
#
# Использование:
#   ./scripts/migrate_db.sh                    # Интерактивный режим
#   ./scripts/migrate_db.sh --auto             # С параметрами из .env
#   ./scripts/migrate_db.sh --host localhost \
#       --port 5432 --user iiko_user \
#       --db iiko_db --password mypass         # С явными параметрами
###############################################################################

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_info()    { echo -e "${GREEN}[INFO]${NC} $1"; }
print_error()   { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_step()    { echo -e "${BLUE}[STEP]${NC} $1"; }

# Определение директории проекта
PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
MIGRATE_SQL="$PROJECT_DIR/database/migrate.sql"

# Константы
PSQL_TIMEOUT=30
PGCONNECT_TIMEOUT=5

# ─── Разбор аргументов ──────────────────────────────────────────────────
AUTO_MODE=false
DB_HOST=""
DB_PORT=""
DB_USER=""
DB_PASSWORD=""
DB_NAME=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --auto)      AUTO_MODE=true; shift ;;
        --host)      DB_HOST="$2"; shift 2 ;;
        --port)      DB_PORT="$2"; shift 2 ;;
        --user)      DB_USER="$2"; shift 2 ;;
        --db)        DB_NAME="$2"; shift 2 ;;
        --password)  DB_PASSWORD="$2"; shift 2 ;;
        --help|-h)
            echo "Использование: $0 [ОПЦИИ]"
            echo ""
            echo "Опции:"
            echo "  --auto             Использовать параметры из backend/.env"
            echo "  --host HOST        Хост PostgreSQL (по умолчанию: localhost)"
            echo "  --port PORT        Порт PostgreSQL (по умолчанию: 5432)"
            echo "  --user USER        Имя пользователя PostgreSQL (по умолчанию: iiko_user)"
            echo "  --db DATABASE      Имя базы данных (по умолчанию: iiko_db)"
            echo "  --password PASS    Пароль PostgreSQL"
            echo "  -h, --help         Показать эту справку"
            exit 0
            ;;
        *)
            print_error "Неизвестный аргумент: $1"
            echo "Используйте --help для справки"
            exit 1
            ;;
    esac
done

# ─── Проверка наличия файла миграции ─────────────────────────────────────
if [ ! -f "$MIGRATE_SQL" ]; then
    print_error "Файл миграции не найден: $MIGRATE_SQL"
    exit 1
fi

# ─── Проверка наличия psql ───────────────────────────────────────────────
if ! command -v psql &> /dev/null; then
    print_error "Клиент PostgreSQL (psql) не установлен"
    print_info "Установите его командой: sudo apt-get install postgresql-client"
    exit 1
fi

echo ""
echo "=============================================="
echo "  Миграция базы данных iiko-base"
echo "=============================================="
echo ""

# ─── Получение параметров подключения ────────────────────────────────────
if [ "$AUTO_MODE" = true ]; then
    # Чтение из backend/.env
    ENV_FILE="$PROJECT_DIR/backend/.env"
    if [ ! -f "$ENV_FILE" ]; then
        print_error "Файл $ENV_FILE не найден"
        print_info "Создайте его из .env.example: cp backend/.env.example backend/.env"
        exit 1
    fi

    # Парсинг DATABASE_URL из .env
    DATABASE_URL=$(grep "^DATABASE_URL=" "$ENV_FILE" | cut -d'=' -f2-)
    if [ -n "$DATABASE_URL" ]; then
        # Парсинг postgresql://user:password@host:port/dbname
        DB_USER=${DB_USER:-$(echo "$DATABASE_URL" | sed -n 's|.*://\([^:]*\):.*|\1|p')}
        DB_PASSWORD=${DB_PASSWORD:-$(echo "$DATABASE_URL" | sed -n 's|.*://[^:]*:\([^@]*\)@.*|\1|p')}
        DB_HOST=${DB_HOST:-$(echo "$DATABASE_URL" | sed -n 's|.*@\([^:]*\):.*|\1|p')}
        DB_PORT=${DB_PORT:-$(echo "$DATABASE_URL" | sed -n 's|.*:\([0-9]*\)/.*|\1|p')}
        DB_NAME=${DB_NAME:-$(echo "$DATABASE_URL" | sed -n 's|.*/\([^?]*\).*|\1|p')}
        print_info "Параметры загружены из $ENV_FILE"
    else
        print_error "DATABASE_URL не найден в $ENV_FILE"
        exit 1
    fi
fi

# Значения по умолчанию
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-5432}
DB_USER=${DB_USER:-iiko_user}
DB_NAME=${DB_NAME:-iiko_db}

# Запрос пароля, если не указан
if [ -z "$DB_PASSWORD" ]; then
    if [ "$AUTO_MODE" = false ]; then
        read -p "Хост PostgreSQL [$DB_HOST]: " INPUT
        DB_HOST=${INPUT:-$DB_HOST}

        read -p "Порт PostgreSQL [$DB_PORT]: " INPUT
        DB_PORT=${INPUT:-$DB_PORT}

        read -p "Имя пользователя [$DB_USER]: " INPUT
        DB_USER=${INPUT:-$DB_USER}

        read -p "Имя базы данных [$DB_NAME]: " INPUT
        DB_NAME=${INPUT:-$DB_NAME}

        read -sp "Пароль PostgreSQL: " DB_PASSWORD
        echo
    else
        print_error "Пароль не указан. Используйте --password или настройте DATABASE_URL в backend/.env"
        exit 1
    fi
fi

# ─── Проверка подключения ────────────────────────────────────────────────
print_step "Проверка подключения к PostgreSQL..."
export PGPASSWORD="$DB_PASSWORD"
export PGCONNECT_TIMEOUT=$PGCONNECT_TIMEOUT

CONNECTION_ERROR=$(timeout "$PSQL_TIMEOUT" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c '\q' 2>&1)
CONNECTION_STATUS=$?

if [ $CONNECTION_STATUS -ne 0 ]; then
    print_error "Не удалось подключиться к базе данных"
    if [ -n "$CONNECTION_ERROR" ]; then
        # Фильтруем чувствительные данные (пароли, токены) из вывода ошибки
        echo "$CONNECTION_ERROR" | grep -v -i 'password' | head -n 5
    fi
    echo ""
    print_info "Проверьте параметры подключения:"
    print_info "  Хост: $DB_HOST"
    print_info "  Порт: $DB_PORT"
    print_info "  Пользователь: $DB_USER"
    print_info "  База данных: $DB_NAME"
    unset PGPASSWORD
    unset PGCONNECT_TIMEOUT
    exit 1
fi

print_info "✓ Подключение к базе данных успешно"

# ─── Показать текущее состояние ──────────────────────────────────────────
print_step "Текущие таблицы в базе данных:"
TABLES=$(timeout "$PSQL_TIMEOUT" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -tAc \
    "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name;" 2>/dev/null)

if [ -z "$TABLES" ]; then
    print_info "  (пусто — таблицы будут созданы)"
else
    echo "$TABLES" | while IFS= read -r table; do
        print_info "  • $table"
    done
fi

# ─── Подтверждение ───────────────────────────────────────────────────────
if [ "$AUTO_MODE" = false ]; then
    echo ""
    print_warning "Будут добавлены таблицы: users, menu_items, iiko_settings, orders, webhook_events, api_logs"
    print_info "Существующие данные НЕ будут удалены"
    read -p "Продолжить миграцию? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Миграция отменена"
        unset PGPASSWORD
        unset PGCONNECT_TIMEOUT
        exit 0
    fi
fi

# ─── Выполнение миграции ─────────────────────────────────────────────────
print_step "Применение миграции..."
MIGRATION_OUTPUT=$(timeout "$PSQL_TIMEOUT" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$MIGRATE_SQL" 2>&1)
MIGRATION_STATUS=$?

if [ $MIGRATION_STATUS -ne 0 ]; then
    print_error "Ошибка при выполнении миграции:"
    # Показываем только строки с ошибками, без чувствительных данных
    echo "$MIGRATION_OUTPUT" | grep -i -E '(ERROR|FATAL|error|fatal)' | head -n 10
    unset PGPASSWORD
    unset PGCONNECT_TIMEOUT
    exit 1
fi

# ─── Показать результат ──────────────────────────────────────────────────
print_info "✓ Миграция выполнена успешно!"
echo ""
print_step "Таблицы после миграции:"
TABLES_AFTER=$(timeout "$PSQL_TIMEOUT" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -tAc \
    "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name;" 2>/dev/null)
echo "$TABLES_AFTER" | while IFS= read -r table; do
    print_info "  ✓ $table"
done

# ─── Очистка ─────────────────────────────────────────────────────────────
unset PGPASSWORD
unset PGCONNECT_TIMEOUT

echo ""
print_info "Миграция базы данных завершена!"
print_info "Теперь можно запустить приложение: ./scripts/deploy.sh"
