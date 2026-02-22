-- Migration: Add outgoing webhooks for external services integration
-- Created: 2026-02-11
-- Description: Adds tables for configuring and logging outgoing webhooks to third-party services like Senler

-- ============================================================
-- 1. Create outgoing_webhooks table
-- ============================================================

CREATE TABLE IF NOT EXISTS outgoing_webhooks (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    webhook_url VARCHAR(500) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    
    -- Authentication
    auth_type VARCHAR(50) DEFAULT 'none',  -- none, bearer, basic, custom
    auth_token VARCHAR(500),
    auth_username VARCHAR(255),
    auth_password VARCHAR(255),
    custom_headers TEXT,  -- JSON string of custom headers
    
    -- Event configuration
    send_on_order_created BOOLEAN DEFAULT true,
    send_on_order_updated BOOLEAN DEFAULT true,
    send_on_order_status_changed BOOLEAN DEFAULT true,
    send_on_order_cancelled BOOLEAN DEFAULT false,
    
    -- Filter configuration
    filter_organization_ids TEXT,  -- JSON array of organization IDs to filter
    filter_order_types TEXT,  -- JSON array: DELIVERY, PICKUP, DINE_IN
    filter_statuses TEXT,  -- JSON array of statuses to trigger webhook
    
    -- Payload configuration
    payload_format VARCHAR(50) DEFAULT 'iiko_soi',  -- iiko_soi, iiko_cloud, custom
    include_fields TEXT,  -- JSON array of fields to include
    custom_payload_template TEXT,  -- Custom JSON template with variables
    
    -- Retry configuration
    retry_count INTEGER DEFAULT 3,
    retry_delay_seconds INTEGER DEFAULT 5,
    timeout_seconds INTEGER DEFAULT 30,
    
    -- Statistics
    total_sent INTEGER DEFAULT 0,
    total_success INTEGER DEFAULT 0,
    total_failed INTEGER DEFAULT 0,
    last_sent_at TIMESTAMP WITH TIME ZONE,
    last_success_at TIMESTAMP WITH TIME ZONE,
    last_error TEXT,
    
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_outgoing_webhooks_active ON outgoing_webhooks(is_active);
CREATE INDEX IF NOT EXISTS idx_outgoing_webhooks_name ON outgoing_webhooks(name);

-- ============================================================
-- 2. Create outgoing_webhook_logs table
-- ============================================================

CREATE TABLE IF NOT EXISTS outgoing_webhook_logs (
    id SERIAL PRIMARY KEY,
    webhook_id INTEGER NOT NULL REFERENCES outgoing_webhooks(id) ON DELETE CASCADE,
    webhook_name VARCHAR(255),
    
    -- Order information
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    order_external_id VARCHAR(255),
    event_type VARCHAR(100),  -- order.created, order.updated, etc.
    
    -- Request details
    request_url VARCHAR(500),
    request_method VARCHAR(10) DEFAULT 'POST',
    request_headers TEXT,  -- JSON
    request_body TEXT,  -- JSON payload sent
    
    -- Response details
    response_status INTEGER,
    response_headers TEXT,  -- JSON
    response_body TEXT,
    
    -- Execution details
    attempt_number INTEGER DEFAULT 1,
    duration_ms INTEGER,
    success BOOLEAN DEFAULT false,
    error_message TEXT,
    
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_outgoing_webhook_logs_webhook_id ON outgoing_webhook_logs(webhook_id);
CREATE INDEX IF NOT EXISTS idx_outgoing_webhook_logs_order_id ON outgoing_webhook_logs(order_id);
CREATE INDEX IF NOT EXISTS idx_outgoing_webhook_logs_success ON outgoing_webhook_logs(success);
CREATE INDEX IF NOT EXISTS idx_outgoing_webhook_logs_created_at ON outgoing_webhook_logs(created_at);

-- ============================================================
-- 3. Add trigger for updated_at
-- ============================================================

CREATE OR REPLACE FUNCTION update_outgoing_webhooks_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_update_outgoing_webhooks_updated_at ON outgoing_webhooks;
CREATE TRIGGER trigger_update_outgoing_webhooks_updated_at
    BEFORE UPDATE ON outgoing_webhooks
    FOR EACH ROW
    EXECUTE FUNCTION update_outgoing_webhooks_updated_at();

-- ============================================================
-- 4. Comments for documentation
-- ============================================================

COMMENT ON TABLE outgoing_webhooks IS 'Configuration for webhooks sent to external services (e.g., Senler, VK, etc.)';
COMMENT ON COLUMN outgoing_webhooks.payload_format IS 'Format of webhook payload: iiko_soi (SOI API format), iiko_cloud (Cloud API format), custom';
COMMENT ON COLUMN outgoing_webhooks.custom_payload_template IS 'Custom JSON template with variables like {{order_id}}, {{status}}, etc.';
COMMENT ON COLUMN outgoing_webhooks.include_fields IS 'JSON array of field names to include in payload (null = all fields)';

COMMENT ON TABLE outgoing_webhook_logs IS 'Log of all outgoing webhook deliveries';
COMMENT ON COLUMN outgoing_webhook_logs.attempt_number IS 'Retry attempt number (1 = first attempt)';
