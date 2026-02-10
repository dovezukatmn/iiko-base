-- ============================================================================
-- Миграция базы данных iiko-base
-- 
-- Безопасный скрипт для обновления существующей базы данных.
-- Добавляет новые таблицы и колонки БЕЗ удаления существующих данных.
-- Можно запускать повторно — все операции идемпотентны (IF NOT EXISTS).
--
-- Использование:
--   psql -h localhost -U iiko_user -d iiko_db -f database/migrate.sql
--   или через скрипт: ./scripts/migrate_db.sh
-- ============================================================================

BEGIN;

-- Расширения (если ещё не установлены)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- ─── Триггер-функция для updated_at ─────────────────────────────────────
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- ─── Таблица users (если не существует) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    hashed_password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_superuser BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active);

-- Добавление колонки role (RBAC) — безопасно для существующей таблицы
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'viewer';
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Триггер (пересоздаётся без ошибок)
DROP TRIGGER IF EXISTS update_users_updated_at ON users;
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Таблица menu_items (если не существует) ────────────────────────────
CREATE TABLE IF NOT EXISTS menu_items (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price INTEGER CHECK (price >= 0),
    category VARCHAR(100),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_menu_items_category ON menu_items(category);
CREATE INDEX IF NOT EXISTS idx_menu_items_is_available ON menu_items(is_available);

DROP TRIGGER IF EXISTS update_menu_items_updated_at ON menu_items;
CREATE TRIGGER update_menu_items_updated_at BEFORE UPDATE ON menu_items
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Таблица настроек интеграции iiko ───────────────────────────────────
CREATE TABLE IF NOT EXISTS iiko_settings (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255),
    api_key VARCHAR(500) NOT NULL,
    api_url VARCHAR(500) DEFAULT 'https://api-ru.iiko.services/api/1',
    webhook_url VARCHAR(500),
    webhook_secret VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_token_refresh TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Add organization_name column if it doesn't exist (for existing databases)
ALTER TABLE iiko_settings ADD COLUMN IF NOT EXISTS organization_name VARCHAR(255);

DROP TRIGGER IF EXISTS update_iiko_settings_updated_at ON iiko_settings;
CREATE TRIGGER update_iiko_settings_updated_at BEFORE UPDATE ON iiko_settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Таблица заказов ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    iiko_order_id VARCHAR(255) UNIQUE,
    organization_id VARCHAR(255),
    status VARCHAR(50) DEFAULT 'new',
    customer_name VARCHAR(255),
    customer_phone VARCHAR(50),
    delivery_address TEXT,
    total_amount INTEGER DEFAULT 0,
    order_data TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_orders_iiko_order_id ON orders(iiko_order_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);

DROP TRIGGER IF EXISTS update_orders_updated_at ON orders;
CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Таблица вебхук-событий ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS webhook_events (
    id SERIAL PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    payload TEXT,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_webhook_events_event_type ON webhook_events(event_type);

-- ─── Таблица логов API запросов ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS api_logs (
    id SERIAL PRIMARY KEY,
    method VARCHAR(10) NOT NULL,
    url VARCHAR(500) NOT NULL,
    request_body TEXT,
    response_status INTEGER,
    response_body TEXT,
    duration_ms INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_api_logs_created_at ON api_logs(created_at);

-- ─── Таблица операций с бонусами ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bonus_transactions (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255) NOT NULL,
    customer_id VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255),
    customer_phone VARCHAR(50),
    wallet_id VARCHAR(255) NOT NULL,
    wallet_name VARCHAR(255),
    operation_type VARCHAR(50) NOT NULL,
    amount DOUBLE PRECISION NOT NULL,
    comment TEXT,
    order_id VARCHAR(255),
    performed_by VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_bonus_transactions_org ON bonus_transactions(organization_id);
CREATE INDEX IF NOT EXISTS idx_bonus_transactions_customer ON bonus_transactions(customer_id);
CREATE INDEX IF NOT EXISTS idx_bonus_transactions_type ON bonus_transactions(operation_type);
CREATE INDEX IF NOT EXISTS idx_bonus_transactions_order ON bonus_transactions(order_id);
CREATE INDEX IF NOT EXISTS idx_bonus_transactions_created_at ON bonus_transactions(created_at);

COMMIT;

-- ─── Результат (информационный запрос, не часть транзакции миграции) ────
-- Выводим список таблиц для подтверждения успешности миграции
SELECT table_name FROM information_schema.tables 
WHERE table_schema = 'public' ORDER BY table_name;
