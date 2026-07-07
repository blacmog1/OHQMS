-- Functions and triggers for ticket generation, queue ordering, and auto-tracking

-- ---------------------------------------------------------------------------
-- Utility: log tracking events
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION log_tracking_event(
    p_entity_type tracking_entity,
    p_entity_id   INT,
    p_event_type  tracking_event,
    p_doctor_id   INT DEFAULT NULL,
    p_metadata    JSONB DEFAULT NULL
)
RETURNS BIGINT
LANGUAGE plpgsql
AS $$
DECLARE
    v_tracking_id BIGINT;
BEGIN
    INSERT INTO real_time_tracking (entity_type, entity_id, event_type, doctor_id, metadata)
    VALUES (p_entity_type, p_entity_id, p_event_type, p_doctor_id, p_metadata)
    RETURNING tracking_id INTO v_tracking_id;

    RETURN v_tracking_id;
END;
$$;

-- ---------------------------------------------------------------------------
-- Generate next ticket code (e.g. GEN-014) for a department
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION generate_ticket_code(
    p_department_id INT,
    p_date          DATE DEFAULT CURRENT_DATE
)
RETURNS TABLE (sequence_number INT, ticket_code VARCHAR)
LANGUAGE plpgsql
AS $$
DECLARE
    v_prefix        VARCHAR(10);
    v_next_sequence INT;
BEGIN
    SELECT d.prefix_code INTO v_prefix
    FROM department d
    WHERE d.department_id = p_department_id;

    IF v_prefix IS NULL THEN
        RAISE EXCEPTION 'Department % not found', p_department_id;
    END IF;

    INSERT INTO department_daily_sequence (department_id, sequence_date, last_sequence)
    VALUES (p_department_id, p_date, 1)
    ON CONFLICT (department_id, sequence_date)
    DO UPDATE SET last_sequence = department_daily_sequence.last_sequence + 1
    RETURNING department_daily_sequence.last_sequence INTO v_next_sequence;

    RETURN QUERY
    SELECT
        v_next_sequence,
        v_prefix || '-' || LPAD(v_next_sequence::TEXT, 3, '0');
END;
$$;

-- ---------------------------------------------------------------------------
-- Book a queue ticket (walk-in kiosk or online remote spot)
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION book_queue_ticket(
    p_patient_id        INT,
    p_department_id     INT,
    p_entry_channel     entry_channel,
    p_scheduled_slot_at TIMESTAMPTZ DEFAULT NULL
)
RETURNS queue_ticket
LANGUAGE plpgsql
AS $$
DECLARE
    v_seq           INT;
    v_code          VARCHAR(20);
    v_effective_at  TIMESTAMPTZ;
    v_ticket        queue_ticket;
BEGIN
    SELECT gt.sequence_number, gt.ticket_code
    INTO v_seq, v_code
    FROM generate_ticket_code(p_department_id) AS gt;

    v_effective_at := COALESCE(p_scheduled_slot_at, NOW());

    INSERT INTO queue_ticket (
        patient_id,
        department_id,
        entry_channel,
        sequence_number,
        ticket_code,
        scheduled_slot_at,
        effective_queue_at,
        booked_at
    )
    VALUES (
        p_patient_id,
        p_department_id,
        p_entry_channel,
        v_seq,
        v_code,
        p_scheduled_slot_at,
        v_effective_at,
        NOW()
    )
    RETURNING * INTO v_ticket;

    PERFORM log_tracking_event(
        'queue_ticket',
        v_ticket.ticket_id,
        CASE WHEN p_entry_channel = 'online' THEN 'appointment_booked' ELSE 'ticket_created' END,
        NULL,
        jsonb_build_object(
            'ticket_code', v_code,
            'entry_channel', p_entry_channel,
            'department_id', p_department_id
        )
    );

    RETURN v_ticket;
END;
$$;

-- ---------------------------------------------------------------------------
-- Register emergency patient (bypasses standard queue)
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION register_emergency_patient(
    p_patient_id      INT,
    p_department_id   INT,
    p_acuity_level    SMALLINT,
    p_primary_symptom TEXT,
    p_check_in_loc    VARCHAR DEFAULT NULL,
    p_last_vitals     JSONB DEFAULT NULL
)
RETURNS emergency_patient
LANGUAGE plpgsql
AS $$
DECLARE
    v_emergency emergency_patient;
    v_doctor    RECORD;
BEGIN
    INSERT INTO emergency_patient (
        patient_id,
        department_id,
        acuity_level,
        primary_symptom,
        check_in_loc,
        last_vitals,
        status
    )
    VALUES (
        p_patient_id,
        p_department_id,
        p_acuity_level,
        p_primary_symptom,
        p_check_in_loc,
        p_last_vitals,
        'triaged'
    )
    RETURNING * INTO v_emergency;

    PERFORM log_tracking_event(
        'emergency_patient',
        v_emergency.triage_id,
        'ticket_created',
        NULL,
        jsonb_build_object(
            'acuity_level', p_acuity_level,
            'department_id', p_department_id
        )
    );

    -- Critical acuity (1-2): instant notification to available doctors in department
    IF p_acuity_level <= 2 THEN
        FOR v_doctor IN
            SELECT d.doctor_id
            FROM doctor d
            WHERE d.department_id = p_department_id
              AND d.status = 'available'
        LOOP
            INSERT INTO doctor_notification (doctor_id, emergency_triage_id, message)
            VALUES (
                v_doctor.doctor_id,
                v_emergency.triage_id,
                'EMERGENCY: Acuity Level ' || p_acuity_level || ' - ' || p_primary_symptom
            );

            PERFORM log_tracking_event(
                'doctor',
                v_doctor.doctor_id,
                'notification_sent',
                v_doctor.doctor_id,
                jsonb_build_object('emergency_triage_id', v_emergency.triage_id)
            );
        END LOOP;

        UPDATE emergency_patient
        SET notification_sent = TRUE, status = 'routed'
        WHERE triage_id = v_emergency.triage_id;

        v_emergency.notification_sent := TRUE;
        v_emergency.status := 'routed';
    END IF;

    RETURN v_emergency;
END;
$$;

-- ---------------------------------------------------------------------------
-- Doctor dashboard: fetch next patient from unified queue
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION get_next_queue_patient(p_doctor_id INT)
RETURNS SETOF queue_ticket
LANGUAGE plpgsql
AS $$
DECLARE
    v_department_id INT;
    v_ticket        queue_ticket;
BEGIN
    SELECT d.department_id INTO v_department_id
    FROM doctor d
    WHERE d.doctor_id = p_doctor_id;

    IF v_department_id IS NULL THEN
        RAISE EXCEPTION 'Doctor % not found', p_doctor_id;
    END IF;

    SELECT *
    INTO v_ticket
    FROM queue_ticket qt
    WHERE qt.department_id = v_department_id
      AND qt.status IN ('waiting', 'checked_in')
    ORDER BY qt.effective_queue_at ASC, qt.ticket_id ASC
    LIMIT 1
    FOR UPDATE SKIP LOCKED;

    IF NOT FOUND THEN
        RETURN;
    END IF;

    UPDATE queue_ticket
    SET
        status = 'called',
        doctor_id = p_doctor_id,
        called_at = NOW(),
        updated_at = NOW()
    WHERE ticket_id = v_ticket.ticket_id
    RETURNING * INTO v_ticket;

    UPDATE doctor
    SET status = 'busy'
    WHERE doctor_id = p_doctor_id;

    PERFORM log_tracking_event('queue_ticket', v_ticket.ticket_id, 'called', p_doctor_id,
        jsonb_build_object('ticket_code', v_ticket.ticket_code));
    PERFORM log_tracking_event('doctor', p_doctor_id, 'doctor_status_change', p_doctor_id,
        jsonb_build_object('status', 'busy'));

    RETURN NEXT v_ticket;
    RETURN;
END;
$$;

-- ---------------------------------------------------------------------------
-- Start / end consultation
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION start_consultation(
    p_ticket_id INT DEFAULT NULL,
    p_triage_id INT DEFAULT NULL,
    p_doctor_id INT DEFAULT NULL
)
RETURNS VOID
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_ticket_id IS NOT NULL THEN
        UPDATE queue_ticket
        SET status = 'in_service', service_start_at = NOW(), updated_at = NOW(),
            doctor_id = COALESCE(p_doctor_id, doctor_id)
        WHERE ticket_id = p_ticket_id;

        PERFORM log_tracking_event('queue_ticket', p_ticket_id, 'service_start', p_doctor_id);
    ELSIF p_triage_id IS NOT NULL THEN
        UPDATE emergency_patient
        SET status = 'in_service', service_start_at = NOW(), updated_at = NOW(),
            doctor_id = COALESCE(p_doctor_id, doctor_id)
        WHERE triage_id = p_triage_id;

        PERFORM log_tracking_event('emergency_patient', p_triage_id, 'service_start', p_doctor_id);
    ELSE
        RAISE EXCEPTION 'Either ticket_id or triage_id must be provided';
    END IF;
END;
$$;

CREATE OR REPLACE FUNCTION end_consultation(
    p_ticket_id INT DEFAULT NULL,
    p_triage_id INT DEFAULT NULL,
    p_doctor_id INT DEFAULT NULL
)
RETURNS VOID
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_ticket_id IS NOT NULL THEN
        UPDATE queue_ticket
        SET status = 'completed', service_end_at = NOW(), updated_at = NOW()
        WHERE ticket_id = p_ticket_id;

        PERFORM log_tracking_event('queue_ticket', p_ticket_id, 'service_end', p_doctor_id);
    ELSIF p_triage_id IS NOT NULL THEN
        UPDATE emergency_patient
        SET status = 'completed', service_end_at = NOW(), updated_at = NOW()
        WHERE triage_id = p_triage_id;

        PERFORM log_tracking_event('emergency_patient', p_triage_id, 'service_end', p_doctor_id);
    ELSE
        RAISE EXCEPTION 'Either ticket_id or triage_id must be provided';
    END IF;

    IF p_doctor_id IS NOT NULL THEN
        UPDATE doctor SET status = 'available' WHERE doctor_id = p_doctor_id;
        PERFORM log_tracking_event('doctor', p_doctor_id, 'doctor_status_change', p_doctor_id,
            jsonb_build_object('status', 'available'));
    END IF;
END;
$$;

-- ---------------------------------------------------------------------------
-- Live waiting room display view
-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW live_waiting_room_display AS
SELECT
    qt.ticket_code,
    d.department_name,
    d.prefix_code,
    qt.status,
    qt.entry_channel,
    qt.effective_queue_at,
    qt.called_at,
    doc.room_number AS doctor_room,
    ROW_NUMBER() OVER (
        PARTITION BY qt.department_id
        ORDER BY qt.effective_queue_at, qt.ticket_id
    ) AS queue_position
FROM queue_ticket qt
JOIN department d ON d.department_id = qt.department_id
LEFT JOIN doctor doc ON doc.doctor_id = qt.doctor_id
WHERE qt.status IN ('waiting', 'checked_in', 'called', 'in_service')
ORDER BY qt.department_id, queue_position;

-- ---------------------------------------------------------------------------
-- Analytics view: wait times per department
-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW department_wait_analytics AS
SELECT
    d.department_name,
    qt.entry_channel,
    COUNT(*) AS total_visits,
    AVG(EXTRACT(EPOCH FROM (qt.service_start_at - qt.booked_at)) / 60) AS avg_wait_minutes,
    AVG(EXTRACT(EPOCH FROM (qt.service_end_at - qt.service_start_at)) / 60) AS avg_service_minutes,
    MIN(qt.booked_at) AS period_start,
    MAX(qt.booked_at) AS period_end
FROM queue_ticket qt
JOIN department d ON d.department_id = qt.department_id
WHERE qt.status = 'completed'
  AND qt.service_start_at IS NOT NULL
GROUP BY d.department_name, qt.entry_channel;

-- ---------------------------------------------------------------------------
-- Auto-update updated_at timestamps
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at := NOW();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_queue_ticket_updated_at
    BEFORE UPDATE ON queue_ticket
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TRIGGER trg_emergency_patient_updated_at
    BEFORE UPDATE ON emergency_patient
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
