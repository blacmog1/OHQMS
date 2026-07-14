-- Audit Logging and Enhanced Security Tables
-- Run this migration to add audit logging capabilities

-- =====================================================
-- Audit Log Table for security and compliance
-- =====================================================

CREATE TABLE IF NOT EXISTS audit_log (
    log_id          BIGSERIAL PRIMARY KEY,
    user_id         INT REFERENCES users(id) ON DELETE SET NULL,
    action          VARCHAR(255) NOT NULL,
    entity_type     VARCHAR(100) NOT NULL,
    entity_id       INT,
    details         JSONB,
    ip_address      INET,
    user_agent      TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_log_user_id ON audit_log(user_id);
CREATE INDEX idx_audit_log_entity ON audit_log(entity_type, entity_id);
CREATE INDEX idx_audit_log_created_at ON audit_log(created_at DESC);
CREATE INDEX idx_audit_log_action ON audit_log(action);

-- =====================================================
-- Failed Login Attempts (for security monitoring)
-- =====================================================

CREATE TABLE IF NOT EXISTS failed_login_attempt (
    attempt_id      BIGSERIAL PRIMARY KEY,
    email           VARCHAR(255),
    ip_address      INET NOT NULL,
    user_agent      TEXT,
    attempted_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_failed_login_email ON failed_login_attempt(email);
CREATE INDEX idx_failed_login_ip ON failed_login_attempt(ip_address);
CREATE INDEX idx_failed_login_attempted_at ON failed_login_attempt(attempted_at DESC);

-- =====================================================
-- API Access Tokens (for future JWT support)
-- =====================================================

CREATE TABLE IF NOT EXISTS api_token (
    token_id        SERIAL PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash      VARCHAR(255) NOT NULL UNIQUE,
    token_name      VARCHAR(100),
    last_used_at    TIMESTAMPTZ,
    expires_at      TIMESTAMPTZ,
    is_revoked      BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_api_token_user_id ON api_token(user_id);
CREATE INDEX idx_api_token_hash ON api_token(token_hash);
CREATE INDEX idx_api_token_expires_at ON api_token(expires_at) WHERE NOT is_revoked;

-- =====================================================
-- Session Management
-- =====================================================

CREATE TABLE IF NOT EXISTS user_session (
    session_id      VARCHAR(255) PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ip_address      INET NOT NULL,
    user_agent      TEXT,
    expires_at      TIMESTAMPTZ NOT NULL,
    last_activity   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_user_session_user_id ON user_session(user_id);
CREATE INDEX idx_user_session_expires_at ON user_session(expires_at);

-- =====================================================
-- Two-Factor Authentication Settings
-- =====================================================

CREATE TABLE IF NOT EXISTS two_factor_setting (
    setting_id      SERIAL PRIMARY KEY,
    user_id         INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    two_factor_type VARCHAR(50) NOT NULL, -- 'totp', 'sms', 'email'
    secret_key      VARCHAR(255) NOT NULL,
    backup_codes    TEXT[], -- Array of backup codes
    is_enabled      BOOLEAN NOT NULL DEFAULT FALSE,
    verified_at     TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_two_factor_user_id ON two_factor_setting(user_id);

-- =====================================================
-- Password Reset Tokens
-- =====================================================

CREATE TABLE IF NOT EXISTS password_reset_token (
    token_id        SERIAL PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash      VARCHAR(255) NOT NULL UNIQUE,
    expires_at      TIMESTAMPTZ NOT NULL,
    used_at         TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_password_reset_token_user_id ON password_reset_token(user_id);
CREATE INDEX idx_password_reset_token_expires_at ON password_reset_token(expires_at);

-- =====================================================
-- Soft Deletes Support (for data recovery)
-- =====================================================

ALTER TABLE patient ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ;
ALTER TABLE doctor ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ;
ALTER TABLE queue_ticket ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ;
ALTER TABLE appointment ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ;

CREATE INDEX idx_patient_deleted_at ON patient(deleted_at) WHERE deleted_at IS NULL;
CREATE INDEX idx_doctor_deleted_at ON doctor(deleted_at) WHERE deleted_at IS NULL;
CREATE INDEX idx_queue_ticket_deleted_at ON queue_ticket(deleted_at) WHERE deleted_at IS NULL;

-- =====================================================
-- System Configuration
-- =====================================================

CREATE TABLE IF NOT EXISTS system_config (
    config_id       SERIAL PRIMARY KEY,
    config_key      VARCHAR(255) NOT NULL UNIQUE,
    config_value    TEXT,
    config_type     VARCHAR(50) DEFAULT 'string', -- 'string', 'int', 'bool', 'json'
    description     TEXT,
    updated_by      INT REFERENCES users(id) ON DELETE SET NULL,
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_system_config_key ON system_config(config_key);

-- =====================================================
-- Add enhanced constraints to existing tables
-- =====================================================

-- Ensure email uniqueness in users table (may already exist)
ALTER TABLE users ADD CONSTRAINT IF NOT EXISTS uq_users_email UNIQUE (email);

-- Add check constraint for password length
ALTER TABLE users ADD CONSTRAINT IF NOT EXISTS chk_password_length CHECK (LENGTH(password_hash) > 0);

COMMIT;
