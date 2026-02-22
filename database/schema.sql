-- Схема базы данных iiko-base
-- Таблицы для работы с пользователями и меню

-- Удаление существующих таблиц (для повторных запусков)
DROP TABLE IF EXISTS menu_items CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- Таблица пользователей
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    hashed_password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_superuser BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Индексы для таблицы users
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_is_active ON users(is_active);

-- Таблица элементов меню
CREATE TABLE menu_items (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price INTEGER CHECK (price >= 0),  -- Цена в копейках (1/100 рубля)
    category VARCHAR(100),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Индексы для таблицы menu_items
CREATE INDEX idx_menu_items_category ON menu_items(category);
CREATE INDEX idx_menu_items_is_available ON menu_items(is_available);

-- Триггер для автоматического обновления updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Применение триггера к таблицам
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_menu_items_updated_at BEFORE UPDATE ON menu_items
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- RBAC: Добавление поля role к таблице users
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'viewer';
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Вставка админа по умолчанию (только если не существует)
INSERT INTO users (email, username, hashed_password, role, is_active, is_superuser) 
VALUES ('admin@example.com', 'admin', '$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa', 'admin', TRUE, TRUE)
ON CONFLICT (username) DO NOTHING;

-- Таблица настроек интеграции iiko
CREATE TABLE IF NOT EXISTS iiko_settings (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255),
    organization_name VARCHAR(255),
    api_key VARCHAR(500) NOT NULL,
    api_url VARCHAR(500) DEFAULT 'https://api-ru.iiko.services/api/1',
    webhook_url VARCHAR(500),
    webhook_secret VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_token_refresh TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_iiko_settings_updated_at BEFORE UPDATE ON iiko_settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Таблица заказов
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

CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Таблица вебхук-событий
CREATE TABLE IF NOT EXISTS webhook_events (
    id SERIAL PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    payload TEXT,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_webhook_events_event_type ON webhook_events(event_type);

-- Таблица логов API запросов
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
CREATE INDEX IF NOT EXISTS idx_categories_iiko_id ON categories(iiko_id);

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
CREATE INDEX IF NOT EXISTS idx_products_iiko_id ON products(iiko_id);
CREATE INDEX IF NOT EXISTS idx_products_code ON products(code);

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
    UNIQUE(organization_id, terminal_group_id, product_id)
);

CREATE INDEX IF NOT EXISTS idx_stop_lists_organization_id ON stop_lists(organization_id);
CREATE INDEX IF NOT EXISTS idx_stop_lists_product_id ON stop_lists(product_id);
CREATE INDEX IF NOT EXISTS idx_stop_lists_is_stopped ON stop_lists(is_stopped);

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

-- ─── Webhook Configs ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS webhook_configs (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255) NOT NULL,
    webhook_url VARCHAR(500),
    auth_token VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    enable_delivery_orders BOOLEAN DEFAULT TRUE,
    enable_stoplist_updates BOOLEAN DEFAULT TRUE,
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

-- ─── Bonus Transactions ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bonus_transactions (
    id SERIAL PRIMARY KEY,
    transaction_type VARCHAR(50) NOT NULL,
    organization_id VARCHAR(255),
    customer_id VARCHAR(255),
    wallet_id VARCHAR(255),
    program_id VARCHAR(255),
    amount FLOAT,
    comment TEXT,
    operator_name VARCHAR(255),
    balance_before FLOAT,
    balance_after FLOAT,
    order_id VARCHAR(255),
    status VARCHAR(50) DEFAULT 'completed',
    error_message TEXT,
    iiko_transaction_id VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_bonus_transactions_customer_id ON bonus_transactions(customer_id);
CREATE INDEX IF NOT EXISTS idx_bonus_transactions_created_at ON bonus_transactions(created_at);
