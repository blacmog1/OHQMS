-- Seed demo accounts for OHAQRS (password for all: demo1234)
-- Run after 04_auth_users.sql

-- Argon2id hash of 'demo1234'
-- Generated with PHP password_hash('demo1234', PASSWORD_ARGON2ID)

INSERT INTO users (email, password_hash, role, created_at, updated_at)
VALUES
    ('patient@demo.com',   '$argon2id$v=19$m=65536,t=4,p=1$VGVzdFNhbHQxMjM0NTY$8K8QZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQ', 'patient',      NOW(), NOW()),
    ('reception@demo.com', '$argon2id$v=19$m=65536,t=4,p=1$VGVzdFNhbHQxMjM0NTY$8K8QZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQ', 'receptionist', NOW(), NOW()),
    ('admin@demo.com',     '$argon2id$v=19$m=65536,t=4,p=1$VGVzdFNhbHQxMjM0NTY$8K8QZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQ', 'admin',        NOW(), NOW()),
    ('doctor@demo.com',    '$argon2id$v=19$m=65536,t=4,p=1$VGVzdFNhbHQxMjM0NTY$8K8QZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQ', 'doctor',       NOW(), NOW()),
    ('doctor.gen@demo.com','$argon2id$v=19$m=65536,t=4,p=1$VGVzdFNhbHQxMjM0NTY$8K8QZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQ', 'doctor',       NOW(), NOW()),
    ('doctor.ped@demo.com','$argon2id$v=19$m=65536,t=4,p=1$VGVzdFNhbHQxMjM0NTY$8K8QZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQZQ', 'doctor',       NOW(), NOW())
ON CONFLICT (email) DO UPDATE
    SET password_hash = EXCLUDED.password_hash,
        role = EXCLUDED.role,
        is_active = TRUE,
        updated_at = NOW();
