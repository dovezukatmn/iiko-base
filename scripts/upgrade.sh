#!/bin/bash

###############################################################################
# Скрипт обновления существующей системы iiko-base
#
# Интегрирует новый функционал админ-панели (авторизация, RBAC, iiko API,
# вебхуки, мониторинг заказов) в уже работающую систему на сервере.
#
# Что делает:
#   1. Копирует новые файлы бэкенда (auth, iiko_service, schemas, routes)
#   2. Обновляет зависимости Python (добавляет httpx)
#   3. Обновляет конфигурацию (.env)
#   4. Применяет миграцию базы данных (новые таблицы, без потери данных)
#   5. Перезапускает сервисы
#
# Использование:
#   ./scripts/upgrade.sh                          # Интерактивный режим
#   ./scripts/upgrade.sh --target /var/www/iiko-base  # Указать путь установки
#   ./scripts/upgrade.sh --skip-db                # Пропустить миграцию БД
#   ./scripts/upgrade.sh --dry-run                # Показать что будет сделано
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

# Определение директории с исходниками (где лежит этот скрипт)
SOURCE_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

# ─── Значения по умолчанию ───────────────────────────────────────────────
TARGET_DIR=""
SKIP_DB=false
DRY_RUN=false
RESTART_SERVICES=true

# ─── Разбор аргументов ──────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case $1 in
        --target)         TARGET_DIR="$2"; shift 2 ;;
        --skip-db)        SKIP_DB=true; shift ;;
        --dry-run)        DRY_RUN=true; shift ;;
        --no-restart)     RESTART_SERVICES=false; shift ;;
        --help|-h)
            echo "Использование: $0 [ОПЦИИ]"
            echo ""
            echo "Опции:"
            echo "  --target DIR       Путь к существующей установке iiko-base"
            echo "                     (по умолчанию: текущая директория проекта)"
            echo "  --skip-db          Пропустить миграцию базы данных"
            echo "  --no-restart       Не перезапускать сервисы"
            echo "  --dry-run          Показать что будет сделано, без изменений"
            echo "  -h, --help         Показать эту справку"
            echo ""
            echo "Примеры:"
            echo "  $0                                    # Обновить текущую установку"
            echo "  $0 --target /var/www/iiko-base        # Обновить установку в /var/www/"
            echo "  $0 --dry-run                          # Предпросмотр изменений"
            echo "  $0 --skip-db --no-restart             # Только файлы, без БД и рестарта"
            exit 0
            ;;
        *)
            print_error "Неизвестный аргумент: $1"
            echo "Используйте --help для справки"
            exit 1
            ;;
    esac
done

# Если целевая директория не указана, используем исходную
TARGET_DIR=${TARGET_DIR:-$SOURCE_DIR}

echo ""
echo "══════════════════════════════════════════════════════"
echo "  Обновление iiko-base: Интеграция админ-панели"
echo "══════════════════════════════════════════════════════"
echo ""
print_info "Источник:  $SOURCE_DIR"
print_info "Целевая:   $TARGET_DIR"
if [ "$DRY_RUN" = true ]; then
    print_warning "РЕЖИМ ПРЕДПРОСМОТРА — изменения не будут применены"
fi
echo ""

# ─── Проверка исходной директории ────────────────────────────────────────
if [ ! -f "$SOURCE_DIR/backend/app/auth.py" ]; then
    print_error "Файлы обновления не найдены в $SOURCE_DIR/backend/app/"
    print_info "Убедитесь, что вы запускаете скрипт из корня репозитория iiko-base"
    exit 1
fi

# ─── Проверка целевой директории ─────────────────────────────────────────
if [ ! -d "$TARGET_DIR/backend" ]; then
    print_error "Директория backend не найдена в $TARGET_DIR"
    print_info "Убедитесь, что --target указывает на корень проекта iiko-base"
    exit 1
fi

# ─── Шаг 1: Резервное копирование ───────────────────────────────────────
print_step "1/6  Создание резервной копии текущих файлов..."

BACKUP_DIR="$TARGET_DIR/backups/upgrade_$(date +%Y%m%d_%H%M%S)"

if [ "$DRY_RUN" = false ]; then
    mkdir -p "$BACKUP_DIR"

    # Бэкап файлов, которые будут заменены
    for file in app/routes.py app/main.py config/settings.py database/models.py; do
        if [ -f "$TARGET_DIR/backend/$file" ]; then
            mkdir -p "$BACKUP_DIR/$(dirname "$file")"
            cp "$TARGET_DIR/backend/$file" "$BACKUP_DIR/$file"
        fi
    done

    if [ -f "$TARGET_DIR/backend/.env" ]; then
        cp "$TARGET_DIR/backend/.env" "$BACKUP_DIR/.env.backup"
    fi

    print_info "✓ Резервная копия создана в $BACKUP_DIR"
else
    print_info "  Будет создана резервная копия в $TARGET_DIR/backups/"
fi

# ─── Шаг 2: Копирование новых файлов бэкенда ────────────────────────────
print_step "2/6  Копирование файлов бэкенда..."

# Проверяем, являются ли директории одной и той же
SAME_DIR=false
if [ "$(realpath "$SOURCE_DIR")" = "$(realpath "$TARGET_DIR")" ]; then
    SAME_DIR=true
fi

# Список новых файлов (не существуют в базовой версии)
NEW_FILES=(
    "app/auth.py"
    "app/schemas.py"
    "app/iiko_service.py"
)

# Список обновляемых файлов (заменяют существующие)
UPDATE_FILES=(
    "app/routes.py"
    "app/main.py"
    "config/settings.py"
    "database/models.py"
    "requirements.txt"
    ".env.example"
)

if [ "$DRY_RUN" = false ]; then
    if [ "$SAME_DIR" = true ]; then
        # Источник и цель совпадают — файлы уже на месте
        for file in "${NEW_FILES[@]}"; do
            if [ -f "$TARGET_DIR/backend/$file" ]; then
                print_info "  ✓ $file (уже на месте)"
            else
                print_warning "  ✗ $file (не найден)"
            fi
        done
        for file in "${UPDATE_FILES[@]}"; do
            if [ -f "$TARGET_DIR/backend/$file" ]; then
                print_info "  ✓ $file (уже на месте)"
            else
                print_warning "  ✗ $file (не найден)"
            fi
        done
    else
        # Копирование новых файлов
        for file in "${NEW_FILES[@]}"; do
            if [ -f "$SOURCE_DIR/backend/$file" ]; then
                mkdir -p "$TARGET_DIR/backend/$(dirname "$file")"
                cp "$SOURCE_DIR/backend/$file" "$TARGET_DIR/backend/$file"
                print_info "  + $file (новый)"
            fi
        done

        # Обновление существующих файлов
        for file in "${UPDATE_FILES[@]}"; do
            if [ -f "$SOURCE_DIR/backend/$file" ]; then
                cp "$SOURCE_DIR/backend/$file" "$TARGET_DIR/backend/$file"
                print_info "  ↻ $file (обновлён)"
            fi
        done
    fi
else
    for file in "${NEW_FILES[@]}"; do
        print_info "  + $file (будет добавлен)"
    done
    for file in "${UPDATE_FILES[@]}"; do
        print_info "  ↻ $file (будет обновлён)"
    done
fi

# Копирование файлов базы данных
if [ "$DRY_RUN" = false ]; then
    if [ "$SAME_DIR" = false ]; then
        cp "$SOURCE_DIR/database/migrate.sql" "$TARGET_DIR/database/migrate.sql"
        cp "$SOURCE_DIR/database/schema.sql" "$TARGET_DIR/database/schema.sql"
        print_info "  ↻ database/migrate.sql (обновлён)"
        print_info "  ↻ database/schema.sql (обновлён)"
    else
        print_info "  ✓ database/migrate.sql (уже на месте)"
        print_info "  ✓ database/schema.sql (уже на месте)"
    fi
else
    print_info "  ↻ database/migrate.sql (будет обновлён)"
    print_info "  ↻ database/schema.sql (будет обновлён)"
fi

# Копирование скриптов
if [ "$DRY_RUN" = false ]; then
    if [ "$SAME_DIR" = false ]; then
        cp "$SOURCE_DIR/scripts/migrate_db.sh" "$TARGET_DIR/scripts/migrate_db.sh"
    fi
    chmod +x "$TARGET_DIR/scripts/migrate_db.sh"
    print_info "  ✓ scripts/migrate_db.sh"
else
    print_info "  + scripts/migrate_db.sh (будет добавлен)"
fi

# ─── Шаг 3: Обновление .env ─────────────────────────────────────────────
print_step "3/6  Обновление конфигурации .env..."

ENV_FILE="$TARGET_DIR/backend/.env"

if [ "$DRY_RUN" = false ]; then
    if [ -f "$ENV_FILE" ]; then
        # Добавляем новые переменные, если их ещё нет
        VARS_TO_ADD=(
            "IIKO_API_URL=https://api-ru.iiko.services/api/1"
            "IIKO_API_KEY=your-iiko-api-key"
            "ACCESS_TOKEN_EXPIRE_MINUTES=30"
            "ALGORITHM=HS256"
            "WEBHOOK_BASE_URL="
        )

        ADDED=0
        for var_line in "${VARS_TO_ADD[@]}"; do
            VAR_NAME=$(echo "$var_line" | cut -d'=' -f1)
            if ! grep -q "^${VAR_NAME}=" "$ENV_FILE"; then
                echo "$var_line" >> "$ENV_FILE"
                ADDED=$((ADDED + 1))
            fi
        done

        if [ $ADDED -gt 0 ]; then
            print_info "✓ Добавлено $ADDED новых переменных в .env"
        else
            print_info "✓ Все переменные уже присутствуют в .env"
        fi
    else
        # Создаём .env из .env.example
        cp "$TARGET_DIR/backend/.env.example" "$ENV_FILE"
        print_info "✓ Создан .env из .env.example"
        print_warning "Настройте параметры в $ENV_FILE"
    fi
else
    print_info "  Будут добавлены переменные: IIKO_API_URL, IIKO_API_KEY, ACCESS_TOKEN_EXPIRE_MINUTES, ALGORITHM, WEBHOOK_BASE_URL"
fi

# ─── Шаг 4: Установка зависимостей Python ───────────────────────────────
print_step "4/6  Обновление зависимостей Python..."

if [ "$DRY_RUN" = false ]; then
    VENV_DIR="$TARGET_DIR/backend/venv"
    if [ -d "$VENV_DIR" ]; then
        source "$VENV_DIR/bin/activate"
        pip install -r "$TARGET_DIR/backend/requirements.txt" --quiet
        deactivate
        print_info "✓ Зависимости Python обновлены"
    else
        print_warning "Виртуальное окружение не найдено в $VENV_DIR"
        print_info "Создайте его: python3 -m venv $VENV_DIR && source $VENV_DIR/bin/activate && pip install -r $TARGET_DIR/backend/requirements.txt"
    fi
else
    print_info "  Будет выполнено: pip install -r requirements.txt (добавлен httpx)"
fi

# ─── Шаг 5: Миграция базы данных ────────────────────────────────────────
print_step "5/6  Миграция базы данных..."

if [ "$SKIP_DB" = true ]; then
    print_info "Миграция БД пропущена (--skip-db)"
    print_info "Запустите вручную: ./scripts/migrate_db.sh --auto"
elif [ "$DRY_RUN" = false ]; then
    if [ -f "$TARGET_DIR/scripts/migrate_db.sh" ]; then
        chmod +x "$TARGET_DIR/scripts/migrate_db.sh"
        # Запускаем миграцию в автоматическом режиме
        if bash "$TARGET_DIR/scripts/migrate_db.sh" --auto 2>/dev/null; then
            print_info "✓ Миграция базы данных выполнена"
        else
            print_warning "Автоматическая миграция не удалась"
            print_info "Запустите миграцию вручную: ./scripts/migrate_db.sh"
        fi
    fi
else
    print_info "  Будет выполнена миграция: ./scripts/migrate_db.sh --auto"
fi

# ─── Шаг 6: Перезапуск сервисов ─────────────────────────────────────────
print_step "6/6  Перезапуск сервисов..."

if [ "$RESTART_SERVICES" = false ]; then
    print_info "Перезапуск пропущен (--no-restart)"
elif [ "$DRY_RUN" = false ]; then
    if systemctl is-active --quiet iiko-backend 2>/dev/null; then
        systemctl restart iiko-backend
        print_info "✓ iiko-backend перезапущен"
    else
        print_warning "Сервис iiko-backend не найден или не запущен"
        print_info "Запустите вручную: cd $TARGET_DIR/backend && source venv/bin/activate && uvicorn app.main:app --host 0.0.0.0 --port 8000"
    fi

    if systemctl is-active --quiet nginx 2>/dev/null; then
        systemctl reload nginx 2>/dev/null || systemctl restart nginx 2>/dev/null || true
        print_info "✓ Nginx перезагружен"
    fi
else
    print_info "  Будут перезапущены: iiko-backend, nginx"
fi

# ─── Итог ────────────────────────────────────────────────────────────────
echo ""
echo "══════════════════════════════════════════════════════"

if [ "$DRY_RUN" = true ]; then
    print_warning "Это был предпросмотр. Для применения изменений запустите без --dry-run"
else
    print_info "✓ Обновление завершено успешно!"
    echo ""
    print_info "Что нового:"
    echo "  • Авторизация и регистрация (JWT + RBAC)"
    echo "  • Интеграция с iiko Cloud API"
    echo "  • Управление заказами"
    echo "  • Прием вебхуков от iiko"
    echo "  • Журнал API-запросов"
    echo ""
    print_info "Следующие шаги:"
    echo "  1. Настройте IIKO_API_KEY в backend/.env"
    echo "  2. Откройте документацию API: http://localhost:8000/api/v1/docs"
    echo "  3. Зарегистрируйте администратора: POST /api/v1/auth/register"
    echo ""
    if [ "$TARGET_DIR" != "$SOURCE_DIR" ]; then
        print_info "Резервная копия: $BACKUP_DIR"
    fi
fi

echo "══════════════════════════════════════════════════════"
