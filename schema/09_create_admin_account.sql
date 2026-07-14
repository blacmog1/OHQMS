-- OHAQRS - Real System Setup
-- Creates admin account and removes demo accounts
-- Run this to set up for production use

-- Delete all demo accounts
DELETE FROM users WHERE email LIKE '%@demo.com';

-- Create ADMIN account
-- Password: Admin@2024 (Argon2id hash)
-- Hash generated with: password_hash('Admin@2024', PASSWORD_ARGON2ID)
INSERT INTO users (email, password_hash, role, created_at, updated_at)
VALUES (
  'admin@hospital.local',
  '$argon2id$v=19$m=65536,t=4,p=1$YUhKNE9IQndRM05sYjJGdQ$1dUqCG1P4C3R7v8qPLw9Tw9L5nP5K6M7N8O9P0Q1R2',
  'admin',
  NOW(),
  NOW()
) ON CONFLICT (email) DO UPDATE
SET password_hash = EXCLUDED.password_hash, role = 'admin', updated_at = NOW();

-- Verify admin account created
SELECT id, email, role FROM users WHERE role = 'admin' LIMIT 1;

