-- Migration: Add comprehensive iiko synchronization tables
-- This migration adds tables for storing synchronized data from iiko Cloud API

-- ========================================
-- Categories (Menu Groups from iiko)
-- ========================================
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

-- ========================================
-- Products (Items from iiko)
-- ========================================
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
    image_urls TEXT[], -- Array of image URLs
    tags TEXT[], -- Array of tags (vegan, spicy, new, etc.)
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

-- ========================================
-- Product Sizes (from iiko nomenclature)
-- ========================================
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

-- ========================================
-- Modifier Groups
-- ========================================
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

-- ========================================
-- Modifiers
-- ========================================
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

-- ========================================
-- Product Modifiers (Link table)
-- ========================================
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

-- ========================================
-- Combos
-- ========================================
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

-- ========================================
-- Combo Items
-- ========================================
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

-- ========================================
-- Stop Lists
-- ========================================
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

-- ========================================
-- Price Categories
-- ========================================
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

-- ========================================
-- Product Prices (for different price categories)
-- ========================================
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

-- ========================================
-- Terminal Groups
-- ========================================
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

-- ========================================
-- Payment Types
-- ========================================
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

-- ========================================
-- External Menus
-- ========================================
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

-- ========================================
-- Webhook Configurations
-- ========================================
CREATE TABLE IF NOT EXISTS webhook_configs (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255) NOT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    auth_token VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    -- Event filters
    enable_delivery_orders BOOLEAN DEFAULT TRUE,
    enable_table_orders BOOLEAN DEFAULT FALSE,
    enable_reservations BOOLEAN DEFAULT FALSE,
    enable_stoplist_updates BOOLEAN DEFAULT TRUE,
    enable_personal_shifts BOOLEAN DEFAULT FALSE,
    -- Order statuses to track
    track_order_statuses TEXT[], -- Array of statuses
    track_item_statuses TEXT[], -- Array of item statuses
    track_errors BOOLEAN DEFAULT TRUE,
    -- Metadata
    last_test_at TIMESTAMP WITH TIME ZONE,
    last_test_status VARCHAR(50),
    last_test_response TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(organization_id)
);

CREATE INDEX IF NOT EXISTS idx_webhook_configs_organization_id ON webhook_configs(organization_id);
CREATE INDEX IF NOT EXISTS idx_webhook_configs_is_active ON webhook_configs(is_active);

-- ========================================
-- Sync History (track sync operations)
-- ========================================
CREATE TABLE IF NOT EXISTS sync_history (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255) NOT NULL,
    sync_type VARCHAR(100) NOT NULL, -- menu, stoplist, payments, terminals, etc.
    status VARCHAR(50) NOT NULL, -- success, failed, partial
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

-- ========================================
-- Triggers for updated_at
-- ========================================
CREATE TRIGGER update_categories_updated_at BEFORE UPDATE ON categories
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_products_updated_at BEFORE UPDATE ON products
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_product_sizes_updated_at BEFORE UPDATE ON product_sizes
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_modifier_groups_updated_at BEFORE UPDATE ON modifier_groups
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_modifiers_updated_at BEFORE UPDATE ON modifiers
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_combos_updated_at BEFORE UPDATE ON combos
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_stop_lists_updated_at BEFORE UPDATE ON stop_lists
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_price_categories_updated_at BEFORE UPDATE ON price_categories
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_product_prices_updated_at BEFORE UPDATE ON product_prices
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_terminal_groups_updated_at BEFORE UPDATE ON terminal_groups
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_payment_types_updated_at BEFORE UPDATE ON payment_types
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_external_menus_updated_at BEFORE UPDATE ON external_menus
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_webhook_configs_updated_at BEFORE UPDATE ON webhook_configs
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
