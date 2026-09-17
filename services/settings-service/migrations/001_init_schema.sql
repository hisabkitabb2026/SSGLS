-- Create settings table
CREATE TABLE IF NOT EXISTS settings (
    id VARCHAR(36) PRIMARY KEY,
    company_id INTEGER NOT NULL,
    key VARCHAR(255) NOT NULL,
    value JSONB,
    module VARCHAR(100),
    type VARCHAR(50),
    is_active BOOLEAN DEFAULT true,
    created_by INTEGER,
    updated_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    UNIQUE(company_id, key)
);

-- Create indexes for settings table
CREATE INDEX IF NOT EXISTS idx_settings_company_id ON settings(company_id);
CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(key);
CREATE INDEX IF NOT EXISTS idx_settings_module ON settings(module);
CREATE INDEX IF NOT EXISTS idx_settings_deleted_at ON settings(deleted_at);
CREATE INDEX IF NOT EXISTS idx_settings_company_key ON settings(company_id, key);

-- Create audit_logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    id VARCHAR(36) PRIMARY KEY,
    company_id INTEGER NOT NULL,
    setting_id VARCHAR(36),
    action VARCHAR(50) NOT NULL,
    old_value JSONB,
    new_value JSONB,
    changed_by INTEGER,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Create indexes for audit_logs table
CREATE INDEX IF NOT EXISTS idx_audit_logs_company_id ON audit_logs(company_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_setting_id ON audit_logs(setting_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_deleted_at ON audit_logs(deleted_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_changed_by ON audit_logs(changed_by);

-- Create sample seed data (optional)
-- INSERT INTO settings (id, company_id, key, value, module, type, is_active, created_by, created_at)
-- VALUES
--   ('setting-001', 1, 'app_name', '"My Application"', 'general', 'string', true, 1, CURRENT_TIMESTAMP),
--   ('setting-002', 1, 'app_version', '"1.0.0"', 'general', 'string', true, 1, CURRENT_TIMESTAMP),
--   ('setting-003', 1, 'max_upload_size', '10485760', 'uploads', 'integer', true, 1, CURRENT_TIMESTAMP);
