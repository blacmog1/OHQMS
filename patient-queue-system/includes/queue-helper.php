<?php
declare(strict_types=1);

/**
 * OHAQRS - Queue Helper Utilities (Production)
 *
 * Replaces the mock counter with an atomic PostgreSQL upsert on
 * the `department_daily_sequence` table.  The sequence resets to 1
 * automatically each calendar day because the upsert uses CURRENT_DATE.
 *
 * Usage:
 *   require_once __DIR__ . '/queue-helper.php';
 *   $code = generateQueueNumber($pdo, $departmentId, $prefixCode);
 *   // returns e.g. 'GEN-042'
 */



/**
 * Atomically increments today's department counter and returns a
 * formatted ticket code such as 'GEN-042'.
 *
 * Uses SELECT … FOR UPDATE inside a transaction to prevent race
 * conditions under concurrent requests (two patients booking at the
 * same instant will always get different sequence numbers).
 *
 * @param PDO    $pdo        Active database connection
 * @param int    $departmentId  The department's primary key
 * @param string $prefixCode    The department's prefix, e.g. 'GEN'
 * @return string              Formatted ticket code, e.g. 'GEN-042'
 * @throws RuntimeException    On transaction or DB failure
 */
function generateQueueNumber(PDO $pdo, int $departmentId, string $prefixCode): string
{
    // Sanitise prefix to letters only, uppercase
    $prefix = preg_replace('/[^A-Z]/', '', strtoupper($prefixCode));
    if ($prefix === '') {
        $prefix = 'Q';
    }

    $pdo->beginTransaction();

    try {
        // Lock the row for this department + today, preventing concurrent increments
        $lock = $pdo->prepare(
            "SELECT last_sequence
             FROM   department_daily_sequence
             WHERE  department_id  = :dept_id
               AND  sequence_date  = CURRENT_DATE
             FOR UPDATE"
        );
        $lock->execute([':dept_id' => $departmentId]);
        $row = $lock->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Row exists — increment
            $nextSeq = (int)$row['last_sequence'] + 1;
            $update = $pdo->prepare(
                "UPDATE department_daily_sequence
                 SET    last_sequence = :next
                 WHERE  department_id = :dept_id
                   AND  sequence_date = CURRENT_DATE"
            );
            $update->execute([':next' => $nextSeq, ':dept_id' => $departmentId]);
        } else {
            // First ticket of the day for this department
            $nextSeq = 1;
            $insert = $pdo->prepare(
                "INSERT INTO department_daily_sequence (department_id, sequence_date, last_sequence)
                 VALUES (:dept_id, CURRENT_DATE, 1)"
            );
            $insert->execute([':dept_id' => $departmentId]);
        }

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new RuntimeException(
            'generateQueueNumber failed: ' . $e->getMessage(),
            0,
            $e
        );
    }

    // Zero-pad to 3 digits: 1 → '001', 42 → '042'
    return $prefix . '-' . str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
}
