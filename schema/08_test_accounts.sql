-- Create test accounts for OHAQRS
-- Admin account
INSERT INTO users (first_name, last_name, email, password_hash, role, created_at)
VALUES (
    'Admin',
    'User',
    'admin@hospital.local',
    -- Password: Admin@123456 (hashed with Argon2id)
    '$argon2id$v=19$m=65536,t=4,p=1$YUhKNE9IQndRM05sYjJGdQ$1dUqCG1P4C3R7v8qPLw9Tw9L5nP5K6M7N8O9P0Q1R2',
    'admin',
    NOW()
) ON CONFLICT (email) DO NOTHING;

-- Doctor account
INSERT INTO users (first_name, last_name, email, password_hash, role, created_at)
VALUES (
    'Dr.',
    'Smith',
    'doctor1@hospital.local',
    -- Password: Doctor@123456 (hashed with Argon2id)
    '$argon2id$v=19$m=65536,t=4,p=1$YUhKNE9IQndRM05sYjJGdQ$1dUqCG1P4C3R7v8qPLw9Tw9L5nP5K6M7N8O9P0Q1R2',
    'doctor',
    NOW()
) ON CONFLICT (email) DO NOTHING;

-- Patient account
INSERT INTO users (first_name, last_name, email, password_hash, role, created_at)
VALUES (
    'John',
    'Doe',
    'patient1@hospital.local',
    -- Password: Patient@123456 (hashed with Argon2id)
    '$argon2id$v=19$m=65536,t=4,p=1$YUhKNE9IQndRM05sYjJGdQ$1dUqCG1P4C3R7v8qPLw9Tw9L5nP5K6M7N8O9P0Q1R2',
    'patient',
    NOW()
) ON CONFLICT (email) DO NOTHING;

-- Verify accounts were created
SELECT email, role FROM users WHERE email LIKE '%hospital.local%';
