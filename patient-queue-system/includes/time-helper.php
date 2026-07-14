<?php
declare(strict_types=1);

/**
 * Patient Queue Management System - Time Helper Utilities
 * 
 * Reusable functions to assist with patient queue estimated time calculations.
 */

/**
 * Calculates the Estimated Time of Arrival (ETA) for a patient in the queue.
 *
 * @param int $queuePosition The position of the patient in the queue.
 * @param int $avgDurationMinutes The average duration of a visit in minutes.
 * @return string The user-friendly formatted ETA time string (e.g., '09:15 AM').
 */
function calculateETA($queuePosition, $avgDurationMinutes) {
    // =========================================================================
    // PLACEHOLDER: FUTURE DYNAMIC DOCTOR SHIFT START TIME QUERY
    // =========================================================================
    // Currently, we assume the clinic opens at a baseline of '08:00 AM'.
    // In a production environment with dynamic doctor shifts, a PostgreSQL query 
    // should be executed to fetch the specific doctor's dynamic shift start time.
    //
    // Example:
    // try {
    //     $stmt = $pdo->prepare("
    //         SELECT TO_CHAR(shift_start, 'HH:MI AM') AS shift_start_formatted 
    //         FROM doctor_shifts 
    //         WHERE doctor_id = :doctor_id 
    //           AND shift_date = CURRENT_DATE 
    //           AND status = 'active'
    //     ");
    //     $stmt->execute([':doctor_id' => $doctorId]);
    //     $baselineTime = $stmt->fetchColumn() ?: '08:00 AM';
    // } catch (PDOException $e) {
    //     $baselineTime = '08:00 AM'; // Fallback baseline
    // }
    
    $baselineTime = '08:00 AM';

    // Multiply the $queuePosition integer parameter by the $avgDurationMinutes to find the total wait offset
    $totalOffsetMinutes = $queuePosition * $avgDurationMinutes;

    // Use PHP's built-in date functions to add that total minute offset to the baseline
    $baselineDateTime = DateTime::createFromFormat('h:i A', $baselineTime);
    if ($baselineDateTime === false) {
        $baselineDateTime = new DateTime('08:00:00');
    }

    $baselineDateTime->modify("+$totalOffsetMinutes minutes");

    // Return the formatted user-friendly time string (e.g., '09:15 AM' or '11:30 AM')
    return $baselineDateTime->format('h:i A');
}
