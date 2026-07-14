<?php
declare(strict_types=1);

/**
 * OHAQRS - Logger Utility
 *
 * Writes structured audit events to the `real_time_tracking` table
 * and optionally to the PHP error_log for emergency fallback.
 *
 * Usage:
 *   require_once __DIR__ . '/logger.php';
 *   logEvent($pdo, 'queue_ticket', 42, 'status_change', ['from' => 'waiting', 'to' => 'called']);
 *   logEvent($pdo, 'doctor',       3,  'doctor_status_change', ['status' => 'busy'], doctorId: 3);
 */



/**
 * Writes a structured event row to real_time_tracking.
 *
 * @param PDO         $pdo        Active database connection
 * @param string      $entityType One of: 'queue_ticket' | 'emergency_patient' | 'doctor'
 * @param int         $entityId   Primary key of the entity being tracked
 * @param string      $eventType  One of the tracking_event ENUM values
 * @param array       $metadata   Arbitrary key-value context (stored as JSONB)
 * @param int|null    $doctorId   Optional — FK to doctor table
 * @return bool                   True on success, false if the insert fails (non-fatal)
 */
function logEvent(
    PDO    $pdo,
    string $entityType,
    int    $entityId,
    string $eventType,
    array  $metadata  = [],
    ?int   $doctorId  = null
): bool {
    // Validate ENUM values to avoid SQLSTATE 22P02 on bad input
    $validEntityTypes = ['queue_ticket', 'emergency_patient', 'doctor'];
    $validEventTypes  = [
        'ticket_created', 'appointment_booked', 'checked_in', 'called',
        'service_start', 'service_end', 'status_change',
        'notification_sent', 'doctor_status_change',
    ];

    if (!in_array($entityType, $validEntityTypes, true)) {
        error_log("OHAQRS logEvent: invalid entity_type '$entityType'");
        return false;
    }
    if (!in_array($eventType, $validEventTypes, true)) {
        error_log("OHAQRS logEvent: invalid event_type '$eventType'");
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO real_time_tracking
                 (entity_type, entity_id, event_type, event_time, doctor_id, metadata)
             VALUES
                 (:entity_type, :entity_id, :event_type, NOW(), :doctor_id, :metadata)"
        );
        $stmt->execute([
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':event_type'  => $eventType,
            ':doctor_id'   => $doctorId,
            ':metadata'    => !empty($metadata) ? json_encode($metadata) : null,
        ]);
        return true;
    } catch (PDOException $e) {
        // Logging must never crash the calling script
        error_log('OHAQRS logEvent PDOException: ' . $e->getMessage());
        return false;
    }
}
