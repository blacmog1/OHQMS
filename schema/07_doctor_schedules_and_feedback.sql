-- Doctor Schedule Management Table
-- Allows doctors to manage their available time slots

CREATE TABLE IF NOT EXISTS doctor_schedule (
    schedule_id     SERIAL PRIMARY KEY,
    doctor_id       INT NOT NULL REFERENCES doctor(doctor_id) ON DELETE CASCADE,
    schedule_date   DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    max_patients    INT NOT NULL DEFAULT 10,
    is_available    BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_doctor_schedule_unique 
    ON doctor_schedule(doctor_id, schedule_date, start_time);

CREATE INDEX idx_doctor_schedule_date 
    ON doctor_schedule(schedule_date, is_available)
    WHERE is_available = TRUE;

CREATE INDEX idx_doctor_schedule_doctor 
    ON doctor_schedule(doctor_id);

-- =====================================================
-- Appointment/Queue Ticket Enhancements
-- =====================================================

-- Add rescheduling tracking
ALTER TABLE queue_ticket ADD COLUMN IF NOT EXISTS rescheduled_from INT 
    REFERENCES queue_ticket(ticket_id) ON DELETE SET NULL;

ALTER TABLE queue_ticket ADD COLUMN IF NOT EXISTS reschedule_count INT DEFAULT 0;

ALTER TABLE queue_ticket ADD COLUMN IF NOT EXISTS cancellation_reason VARCHAR(255);

-- Add performance metrics
ALTER TABLE queue_ticket ADD COLUMN IF NOT EXISTS wait_time_minutes INT;

ALTER TABLE queue_ticket ADD COLUMN IF NOT EXISTS service_time_minutes INT;

-- =====================================================
-- Doctor Availability Tracking
-- =====================================================

CREATE TABLE IF NOT EXISTS doctor_availability (
    availability_id SERIAL PRIMARY KEY,
    doctor_id       INT NOT NULL REFERENCES doctor(doctor_id) ON DELETE CASCADE,
    start_date      DATE NOT NULL,
    end_date        DATE,
    status          VARCHAR(50) NOT NULL, -- 'available', 'on_leave', 'vacation', 'off'
    notes           TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_doctor_availability_doctor_date 
    ON doctor_availability(doctor_id, start_date, end_date);

-- =====================================================
-- Appointment Feedback/Rating
-- =====================================================

CREATE TABLE IF NOT EXISTS appointment_feedback (
    feedback_id     SERIAL PRIMARY KEY,
    ticket_id       INT NOT NULL REFERENCES queue_ticket(ticket_id) ON DELETE CASCADE,
    patient_id      INT NOT NULL REFERENCES patient(patient_id) ON DELETE CASCADE,
    doctor_id       INT NOT NULL REFERENCES doctor(doctor_id) ON DELETE CASCADE,
    rating          SMALLINT CHECK (rating BETWEEN 1 AND 5),
    comments        TEXT,
    would_recommend BOOLEAN,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_appointment_feedback_patient 
    ON appointment_feedback(patient_id);

CREATE INDEX idx_appointment_feedback_doctor 
    ON appointment_feedback(doctor_id);

-- =====================================================
-- Queue Performance Metrics
-- =====================================================

CREATE TABLE IF NOT EXISTS queue_metrics (
    metric_id       SERIAL PRIMARY KEY,
    department_id   INT NOT NULL REFERENCES department(department_id) ON DELETE CASCADE,
    metric_date     DATE NOT NULL,
    total_patients  INT DEFAULT 0,
    avg_wait_time   INT DEFAULT 0, -- in minutes
    avg_service_time INT DEFAULT 0, -- in minutes
    no_show_count   INT DEFAULT 0,
    completed_count INT DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_queue_metrics_unique 
    ON queue_metrics(department_id, metric_date);

COMMIT;
