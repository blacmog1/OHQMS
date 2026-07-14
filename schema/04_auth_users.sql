-- Authentication layer: users table + profile links for patient/doctor accounts
-- Required by the OHAQRS PHP application

CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL CHECK (role IN ('patient', 'doctor', 'admin', 'receptionist')),
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users (role);

ALTER TABLE patient
    ADD COLUMN IF NOT EXISTS user_id INT UNIQUE REFERENCES users (id) ON DELETE SET NULL;

ALTER TABLE doctor
    ADD COLUMN IF NOT EXISTS user_id INT UNIQUE REFERENCES users (id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_patient_user_id ON patient (user_id);
CREATE INDEX IF NOT EXISTS idx_doctor_user_id ON doctor (user_id);

DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
