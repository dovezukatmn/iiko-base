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

-- ============================================================================
-- COMPREHENSIVE IIKO SYNCHRONIZATION TABLES
-- Added as part of comprehensive iiko integration refactoring
-- ============================================================================

-- ─── Categories (Menu Groups from iiko) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    parent_id VARCHAR(255),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    is_visible BOOLEAN DEFAULT TRUE,
    image_url TEXT,
    seo_title VARCHAR(255),
    seo_description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_categories_parent_id ON categories(parent_id);
CREATE INDEX IF NOT EXISTS idx_categories_is_active ON categories(is_active);
CREATE INDEX IF NOT EXISTS idx_categories_is_visible ON categories(is_visible);
CREATE INDEX IF NOT EXISTS idx_categories_iiko_id ON categories(iiko_id);

DROP TRIGGER IF EXISTS update_categories_updated_at ON categories;
CREATE TRIGGER update_categories_updated_at BEFORE UPDATE ON categories
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Products (Items from iiko) ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    category_id VARCHAR(255),
    parent_group VARCHAR(255),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    code VARCHAR(100),
    sku VARCHAR(100),
    weight FLOAT,
    measure_unit VARCHAR(50),
    price INTEGER DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    is_visible BOOLEAN DEFAULT TRUE,
    image_urls TEXT[],
    tags TEXT[],
    seo_title VARCHAR(255),
    seo_description TEXT,
    energy_value FLOAT,
    fats FLOAT,
    proteins FLOAT,
    carbs FLOAT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_products_category_id ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_products_is_available ON products(is_available);
CREATE INDEX IF NOT EXISTS idx_products_is_visible ON products(is_visible);
CREATE INDEX IF NOT EXISTS idx_products_iiko_id ON products(iiko_id);
CREATE INDEX IF NOT EXISTS idx_products_code ON products(code);

DROP TRIGGER IF EXISTS update_products_updated_at ON products;
CREATE TRIGGER update_products_updated_at BEFORE UPDATE ON products
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Product Sizes ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_sizes (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    price INTEGER DEFAULT 0,
    priority INTEGER DEFAULT 0,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_product_sizes_product_id ON product_sizes(product_id);
CREATE INDEX IF NOT EXISTS idx_product_sizes_iiko_id ON product_sizes(iiko_id);

DROP TRIGGER IF EXISTS update_product_sizes_updated_at ON product_sizes;
CREATE TRIGGER update_product_sizes_updated_at BEFORE UPDATE ON product_sizes
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Modifier Groups ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS modifier_groups (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    min_amount INTEGER DEFAULT 0,
    max_amount INTEGER DEFAULT 1,
    is_required BOOLEAN DEFAULT FALSE,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_modifier_groups_iiko_id ON modifier_groups(iiko_id);

DROP TRIGGER IF EXISTS update_modifier_groups_updated_at ON modifier_groups;
CREATE TRIGGER update_modifier_groups_updated_at BEFORE UPDATE ON modifier_groups
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Modifiers ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS modifiers (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    group_id VARCHAR(255),
    name VARCHAR(255) NOT NULL,
    price INTEGER DEFAULT 0,
    max_amount INTEGER DEFAULT 1,
    default_amount INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES modifier_groups(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_modifiers_group_id ON modifiers(group_id);
CREATE INDEX IF NOT EXISTS idx_modifiers_is_active ON modifiers(is_active);
CREATE INDEX IF NOT EXISTS idx_modifiers_iiko_id ON modifiers(iiko_id);

DROP TRIGGER IF EXISTS update_modifiers_updated_at ON modifiers;
CREATE TRIGGER update_modifiers_updated_at BEFORE UPDATE ON modifiers
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Product Modifiers (Link table) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_modifiers (
    id SERIAL PRIMARY KEY,
    product_id VARCHAR(255) NOT NULL,
    modifier_group_id VARCHAR(255) NOT NULL,
    min_amount INTEGER DEFAULT 0,
    max_amount INTEGER DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (modifier_group_id) REFERENCES modifier_groups(id) ON DELETE CASCADE,
    UNIQUE(product_id, modifier_group_id)
);

CREATE INDEX IF NOT EXISTS idx_product_modifiers_product_id ON product_modifiers(product_id);
CREATE INDEX IF NOT EXISTS idx_product_modifiers_modifier_group_id ON product_modifiers(modifier_group_id);

-- ─── Combos ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS combos (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    category_id VARCHAR(255),
    image_url TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_combos_is_active ON combos(is_active);
CREATE INDEX IF NOT EXISTS idx_combos_iiko_id ON combos(iiko_id);
CREATE INDEX IF NOT EXISTS idx_combos_category_id ON combos(category_id);

DROP TRIGGER IF EXISTS update_combos_updated_at ON combos;
CREATE TRIGGER update_combos_updated_at BEFORE UPDATE ON combos
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Combo Items ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS combo_items (
    id SERIAL PRIMARY KEY,
    combo_id VARCHAR(255) NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    amount INTEGER DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_combo_items_combo_id ON combo_items(combo_id);
CREATE INDEX IF NOT EXISTS idx_combo_items_product_id ON combo_items(product_id);

-- ─── Stop Lists ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS stop_lists (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255) NOT NULL,
    terminal_group_id VARCHAR(255),
    product_id VARCHAR(255) NOT NULL,
    balance FLOAT DEFAULT 0,
    is_stopped BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE(organization_id, terminal_group_id, product_id)
);

CREATE INDEX IF NOT EXISTS idx_stop_lists_organization_id ON stop_lists(organization_id);
CREATE INDEX IF NOT EXISTS idx_stop_lists_product_id ON stop_lists(product_id);
CREATE INDEX IF NOT EXISTS idx_stop_lists_is_stopped ON stop_lists(is_stopped);

DROP TRIGGER IF EXISTS update_stop_lists_updated_at ON stop_lists;
CREATE TRIGGER update_stop_lists_updated_at BEFORE UPDATE ON stop_lists
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Price Categories ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS price_categories (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    organization_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_price_categories_organization_id ON price_categories(organization_id);
CREATE INDEX IF NOT EXISTS idx_price_categories_iiko_id ON price_categories(iiko_id);

DROP TRIGGER IF EXISTS update_price_categories_updated_at ON price_categories;
CREATE TRIGGER update_price_categories_updated_at BEFORE UPDATE ON price_categories
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Product Prices ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_prices (
    id SERIAL PRIMARY KEY,
    product_id VARCHAR(255) NOT NULL,
    price_category_id VARCHAR(255) NOT NULL,
    price INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (price_category_id) REFERENCES price_categories(id) ON DELETE CASCADE,
    UNIQUE(product_id, price_category_id)
);

CREATE INDEX IF NOT EXISTS idx_product_prices_product_id ON product_prices(product_id);
CREATE INDEX IF NOT EXISTS idx_product_prices_price_category_id ON product_prices(price_category_id);

DROP TRIGGER IF EXISTS update_product_prices_updated_at ON product_prices;
CREATE TRIGGER update_product_prices_updated_at BEFORE UPDATE ON product_prices
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Terminal Groups ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS terminal_groups (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    organization_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_terminal_groups_organization_id ON terminal_groups(organization_id);
CREATE INDEX IF NOT EXISTS idx_terminal_groups_is_active ON terminal_groups(is_active);
CREATE INDEX IF NOT EXISTS idx_terminal_groups_iiko_id ON terminal_groups(iiko_id);

DROP TRIGGER IF EXISTS update_terminal_groups_updated_at ON terminal_groups;
CREATE TRIGGER update_terminal_groups_updated_at BEFORE UPDATE ON terminal_groups
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Payment Types ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payment_types (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    organization_id VARCHAR(255) NOT NULL,
    code VARCHAR(100),
    name VARCHAR(255) NOT NULL,
    comment TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    is_deleted BOOLEAN DEFAULT FALSE,
    print_cheque BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_payment_types_organization_id ON payment_types(organization_id);
CREATE INDEX IF NOT EXISTS idx_payment_types_is_active ON payment_types(is_active);
CREATE INDEX IF NOT EXISTS idx_payment_types_iiko_id ON payment_types(iiko_id);

DROP TRIGGER IF EXISTS update_payment_types_updated_at ON payment_types;
CREATE TRIGGER update_payment_types_updated_at BEFORE UPDATE ON payment_types
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── External Menus ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS external_menus (
    id VARCHAR(255) PRIMARY KEY,
    iiko_id VARCHAR(255) UNIQUE NOT NULL,
    organization_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_external_menus_organization_id ON external_menus(organization_id);
CREATE INDEX IF NOT EXISTS idx_external_menus_is_active ON external_menus(is_active);
CREATE INDEX IF NOT EXISTS idx_external_menus_iiko_id ON external_menus(iiko_id);

DROP TRIGGER IF EXISTS update_external_menus_updated_at ON external_menus;
CREATE TRIGGER update_external_menus_updated_at BEFORE UPDATE ON external_menus
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Webhook Configurations ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS webhook_configs (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255) NOT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    auth_token VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    enable_delivery_orders BOOLEAN DEFAULT TRUE,
    enable_table_orders BOOLEAN DEFAULT FALSE,
    enable_reservations BOOLEAN DEFAULT FALSE,
    enable_stoplist_updates BOOLEAN DEFAULT TRUE,
    enable_personal_shifts BOOLEAN DEFAULT FALSE,
    track_order_statuses TEXT[],
    track_item_statuses TEXT[],
    track_errors BOOLEAN DEFAULT TRUE,
    last_test_at TIMESTAMP WITH TIME ZONE,
    last_test_status VARCHAR(50),
    last_test_response TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(organization_id)
);

CREATE INDEX IF NOT EXISTS idx_webhook_configs_organization_id ON webhook_configs(organization_id);
CREATE INDEX IF NOT EXISTS idx_webhook_configs_is_active ON webhook_configs(is_active);

DROP TRIGGER IF EXISTS update_webhook_configs_updated_at ON webhook_configs;
CREATE TRIGGER update_webhook_configs_updated_at BEFORE UPDATE ON webhook_configs
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ─── Sync History ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sync_history (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255) NOT NULL,
    sync_type VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    items_synced INTEGER DEFAULT 0,
    error_message TEXT,
    duration_ms INTEGER,
    started_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP WITH TIME ZONE
);

CREATE INDEX IF NOT EXISTS idx_sync_history_organization_id ON sync_history(organization_id);
CREATE INDEX IF NOT EXISTS idx_sync_history_sync_type ON sync_history(sync_type);
CREATE INDEX IF NOT EXISTS idx_sync_history_status ON sync_history(status);
CREATE INDEX IF NOT EXISTS idx_sync_history_started_at ON sync_history(started_at);

COMMIT;

-- ─── Результат (информационный запрос, не часть транзакции миграции) ────
-- Выводим список таблиц для подтверждения успешности миграции
SELECT table_name FROM information_schema.tables 
WHERE table_schema = 'public' ORDER BY table_name;
