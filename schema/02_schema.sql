-- Connect to: hospital_queue
-- Core schema for dual workflow: standard walk-in/online queue + emergency triage bypass

-- ---------------------------------------------------------------------------
-- Custom types
-- ---------------------------------------------------------------------------

CREATE TYPE entry_channel AS ENUM ('online', 'walk_in');

CREATE TYPE queue_status AS ENUM (
    'waiting',
    'checked_in',
    'called',
    'in_service',
    'completed',
    'cancelled',
    'no_show'
);

CREATE TYPE doctor_status AS ENUM ('available', 'busy', 'on_break');

CREATE TYPE emergency_status AS ENUM (
    'triaged',
    'routed',
    'in_service',
    'completed',
    'transferred'
);

CREATE TYPE tracking_entity AS ENUM ('queue_ticket', 'emergency_patient', 'doctor');

CREATE TYPE tracking_event AS ENUM (
    'ticket_created',
    'appointment_booked',
    'checked_in',
    'called',
    'service_start',
    'service_end',
    'status_change',
    'notification_sent',
    'doctor_status_change'
);

-- ---------------------------------------------------------------------------
-- Core entities (from ERD)
-- ---------------------------------------------------------------------------

CREATE TABLE department (
    department_id   SERIAL PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    prefix_code     VARCHAR(10)  NOT NULL UNIQUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE patient (
    patient_id    SERIAL PRIMARY KEY,
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    phone_number  VARCHAR(20),
    email         VARCHAR(255),
    date_of_birth DATE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE doctor (
    doctor_id     SERIAL PRIMARY KEY,
    department_id INT NOT NULL REFERENCES department (department_id) ON DELETE RESTRICT,
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    room_number   VARCHAR(20),
    status        doctor_status NOT NULL DEFAULT 'available',
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- Queue management (Phase 1 & 2: online + walk-in interleaving)
-- ---------------------------------------------------------------------------

-- Tracks daily sequence per department for ticket codes like GEN-014
CREATE TABLE department_daily_sequence (
    department_id  INT  NOT NULL REFERENCES department (department_id) ON DELETE CASCADE,
    sequence_date  DATE NOT NULL DEFAULT CURRENT_DATE,
    last_sequence  INT  NOT NULL DEFAULT 0,
    PRIMARY KEY (department_id, sequence_date)
);

CREATE TABLE queue_ticket (
    ticket_id          SERIAL PRIMARY KEY,
    patient_id         INT NOT NULL REFERENCES patient (patient_id) ON DELETE RESTRICT,
    department_id      INT NOT NULL REFERENCES department (department_id) ON DELETE RESTRICT,
    entry_channel      entry_channel NOT NULL,
    sequence_number    INT NOT NULL,
    ticket_code        VARCHAR(20) NOT NULL,
    status             queue_status NOT NULL DEFAULT 'waiting',
    doctor_id          INT REFERENCES doctor (doctor_id) ON DELETE SET NULL,

    -- Timestamps for central queue alignment and analytics
    booked_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    scheduled_slot_at  TIMESTAMPTZ,
    check_in_at        TIMESTAMPTZ,
    called_at          TIMESTAMPTZ,
    service_start_at   TIMESTAMPTZ,
    service_end_at     TIMESTAMPTZ,

    -- Unified queue ordering: online slots and walk-ins merged by effective time
    effective_queue_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_ticket_code UNIQUE (ticket_code),
    CONSTRAINT chk_online_slot CHECK (
        entry_channel <> 'online' OR scheduled_slot_at IS NOT NULL
    )
);

-- ---------------------------------------------------------------------------
-- Emergency bypass (Phase 2 & 3: preemption + doctor override)
-- ---------------------------------------------------------------------------

CREATE TABLE emergency_patient (
    triage_id        SERIAL PRIMARY KEY,
    patient_id       INT NOT NULL REFERENCES patient (patient_id) ON DELETE RESTRICT,
    department_id    INT NOT NULL REFERENCES department (department_id) ON DELETE RESTRICT,
    doctor_id        INT REFERENCES doctor (doctor_id) ON DELETE SET NULL,
    acuity_level     SMALLINT NOT NULL CHECK (acuity_level BETWEEN 1 AND 5),
    primary_symptom  TEXT NOT NULL,
    triage_time      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    check_in_loc     VARCHAR(100),
    last_vitals      JSONB,
    status           emergency_status NOT NULL DEFAULT 'triaged',
    service_start_at TIMESTAMPTZ,
    service_end_at   TIMESTAMPTZ,
    notification_sent BOOLEAN NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Instant notification bell for doctor dashboard (emergency interruption)
CREATE TABLE doctor_notification (
    notification_id   SERIAL PRIMARY KEY,
    doctor_id         INT NOT NULL REFERENCES doctor (doctor_id) ON DELETE CASCADE,
    emergency_triage_id INT NOT NULL REFERENCES emergency_patient (triage_id) ON DELETE CASCADE,
    message           TEXT NOT NULL,
    is_read           BOOLEAN NOT NULL DEFAULT FALSE,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- Clinical records & closure (Phase 4)
-- ---------------------------------------------------------------------------

CREATE TABLE patient_record (
    record_id           SERIAL PRIMARY KEY,
    patient_id          INT NOT NULL REFERENCES patient (patient_id) ON DELETE RESTRICT,
    doctor_id           INT NOT NULL REFERENCES doctor (doctor_id) ON DELETE RESTRICT,
    queue_ticket_id     INT REFERENCES queue_ticket (ticket_id) ON DELETE SET NULL,
    emergency_triage_id INT REFERENCES emergency_patient (triage_id) ON DELETE SET NULL,
    visit_date          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    symptoms            TEXT,
    treatment_notes     TEXT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_visit_source CHECK (
        queue_ticket_id IS NOT NULL OR emergency_triage_id IS NOT NULL
    )
);

-- ---------------------------------------------------------------------------
-- Real-time tracking telemetry (Phase 4)
-- ---------------------------------------------------------------------------

CREATE TABLE real_time_tracking (
    tracking_id  BIGSERIAL PRIMARY KEY,
    entity_type  tracking_entity NOT NULL,
    entity_id    INT NOT NULL,
    event_type   tracking_event NOT NULL,
    event_time   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    doctor_id    INT REFERENCES doctor (doctor_id) ON DELETE SET NULL,
    metadata     JSONB
);

-- ---------------------------------------------------------------------------
-- Indexes for queue engine, dashboard, and reporting
-- ---------------------------------------------------------------------------

CREATE INDEX idx_queue_ticket_department_status
    ON queue_ticket (department_id, status, effective_queue_at);

CREATE INDEX idx_queue_ticket_patient
    ON queue_ticket (patient_id);

CREATE INDEX idx_queue_ticket_doctor
    ON queue_ticket (doctor_id)
    WHERE doctor_id IS NOT NULL;

CREATE INDEX idx_emergency_patient_acuity
    ON emergency_patient (acuity_level, status, triage_time);

CREATE INDEX idx_emergency_patient_department
    ON emergency_patient (department_id, status);

CREATE INDEX idx_emergency_patient_doctor
    ON emergency_patient (doctor_id)
    WHERE doctor_id IS NOT NULL;

CREATE INDEX idx_doctor_department_status
    ON doctor (department_id, status);

CREATE INDEX idx_patient_record_visit_date
    ON patient_record (visit_date);

CREATE INDEX idx_patient_record_patient
    ON patient_record (patient_id);

CREATE INDEX idx_real_time_tracking_entity
    ON real_time_tracking (entity_type, entity_id, event_time DESC);

CREATE INDEX idx_real_time_tracking_event_time
    ON real_time_tracking (event_time DESC);

CREATE INDEX idx_doctor_notification_unread
    ON doctor_notification (doctor_id, is_read)
    WHERE is_read = FALSE;

-- ---------------------------------------------------------------------------
-- Seed reference data
-- ---------------------------------------------------------------------------

INSERT INTO department (department_name, prefix_code) VALUES
    ('General Medicine', 'GEN'),
    ('Cardiology',       'CAR'),
    ('Pediatrics',       'PED'),
    ('Emergency',        'EMR');
