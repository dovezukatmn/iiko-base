-- Migration: Webhook and Order improvements for full iiko SOI API support
-- Created: 2026-02-11
-- Description: Adds fields to support complete webhook handling from iiko SOI API

-- ============================================================
-- 1. Extend orders table
-- ============================================================

-- Add SOI API specific fields
ALTER TABLE orders ADD COLUMN IF NOT EXISTS external_order_id VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS readable_number VARCHAR(100);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS promised_time TIMESTAMP WITH TIME ZONE;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS courier_id VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS courier_name VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS order_type VARCHAR(50);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS restaurant_name VARCHAR(255);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS problem TEXT;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS creation_status VARCHAR(50);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS error_info TEXT;

-- Create index for external_order_id for faster lookups
CREATE INDEX IF NOT EXISTS idx_orders_external_order_id ON orders(external_order_id);

-- ============================================================
-- 2. Extend webhook_events table
-- ============================================================

-- Add fields for better webhook tracking
ALTER TABLE webhook_events ADD COLUMN IF NOT EXISTS order_external_id VARCHAR(255);
ALTER TABLE webhook_events ADD COLUMN IF NOT EXISTS organization_id VARCHAR(255);
ALTER TABLE webhook_events ADD COLUMN IF NOT EXISTS processing_error TEXT;

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_webhook_events_order_external_id ON webhook_events(order_external_id);
CREATE INDEX IF NOT EXISTS idx_webhook_events_organization_id ON webhook_events(organization_id);
CREATE INDEX IF NOT EXISTS idx_webhook_events_processed ON webhook_events(processed);

-- ============================================================
-- 3. Add webhook configuration table (for multiple organizations)
-- ============================================================

CREATE TABLE IF NOT EXISTS webhook_configs (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(255) NOT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    auth_token VARCHAR(255),
    is_active BOOLEAN DEFAULT true,
    last_registration TIMESTAMP WITH TIME ZONE,
    registration_status VARCHAR(50),
    registration_error TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE
);

CREATE INDEX IF NOT EXISTS idx_webhook_configs_org_id ON webhook_configs(organization_id);
CREATE INDEX IF NOT EXISTS idx_webhook_configs_active ON webhook_configs(is_active);

-- ============================================================
-- Comments for documentation
-- ============================================================

COMMENT ON COLUMN orders.external_order_id IS 'External order ID from iiko SOI API (orderExternalId)';
COMMENT ON COLUMN orders.readable_number IS 'Human-readable order number from iiko (readableNumber)';
COMMENT ON COLUMN orders.promised_time IS 'Promised delivery/pickup time from iiko (promisedTime)';
COMMENT ON COLUMN orders.courier_id IS 'ID of assigned courier';
COMMENT ON COLUMN orders.courier_name IS 'Name of assigned courier';
COMMENT ON COLUMN orders.order_type IS 'Order type: DELIVERY, PICKUP, DINE_IN, etc.';
COMMENT ON COLUMN orders.restaurant_name IS 'Restaurant/terminal name from iiko';
COMMENT ON COLUMN orders.problem IS 'Order problem description if any';
COMMENT ON COLUMN orders.creation_status IS 'Creation status from SOI: OK, Error';
COMMENT ON COLUMN orders.error_info IS 'Error information from SOI webhook';

COMMENT ON COLUMN webhook_events.order_external_id IS 'External order ID for quick lookup';
COMMENT ON COLUMN webhook_events.organization_id IS 'Organization ID from webhook payload';
COMMENT ON COLUMN webhook_events.processing_error IS 'Error message if processing failed';

COMMENT ON TABLE webhook_configs IS 'Webhook registration configuration for multiple organizations';
