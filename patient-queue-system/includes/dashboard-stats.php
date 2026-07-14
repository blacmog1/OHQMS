<?php
declare(strict_types=1);

/**
 * OHAQRS - Dashboard Statistics
 *
 * Provides aggregated stats for the admin dashboard.
 * Called by actions/get-dashboard-stats.php — not accessed directly.
 *
 * Usage:
 *   require_once __DIR__ . '/dashboard-stats.php';
 *   $stats = getDashboardStats($pdo);
 */



/**
 * Returns an associative array of real-time dashboard statistics.
 *
 * @param PDO $pdo Active database connection
 * @return array
 */
function getDashboardStats(PDO $pdo): array
{
    $stats = [];

    // ------------------------------------------------------------------
    // 1. Total patients registered (all time)
    // ------------------------------------------------------------------
    $stats['total_patients'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM patient")
        ->fetchColumn();

    // ------------------------------------------------------------------
    // 2. Tickets created today
    // ------------------------------------------------------------------
    $stats['tickets_today'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM queue_ticket WHERE created_at >= CURRENT_DATE")
        ->fetchColumn();

    // ------------------------------------------------------------------
    // 3. Currently waiting (status = waiting or checked_in)
    // ------------------------------------------------------------------
    $stats['currently_waiting'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM queue_ticket
                 WHERE status IN ('waiting','checked_in')
                   AND created_at >= CURRENT_DATE")
        ->fetchColumn();

    // ------------------------------------------------------------------
    // 4. Currently in service (status = in_service or called)
    // ------------------------------------------------------------------
    $stats['currently_in_service'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM queue_ticket
                 WHERE status IN ('called','in_service')
                   AND created_at >= CURRENT_DATE")
        ->fetchColumn();

    // ------------------------------------------------------------------
    // 5. Completed visits today
    // ------------------------------------------------------------------
    $stats['completed_today'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM queue_ticket
                 WHERE status = 'completed' AND created_at >= CURRENT_DATE")
        ->fetchColumn();

    // ------------------------------------------------------------------
    // 6. Cancelled today
    // ------------------------------------------------------------------
    $stats['cancelled_today'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM queue_ticket
                 WHERE status = 'cancelled' AND created_at >= CURRENT_DATE")
        ->fetchColumn();

    // ------------------------------------------------------------------
    // 7. Average service duration today (minutes)
    // ------------------------------------------------------------------
    $avgRow = $pdo->query(
        "SELECT ROUND(AVG(EXTRACT(EPOCH FROM (service_end_at - service_start_at)) / 60), 1) AS avg_mins
         FROM queue_ticket
         WHERE status = 'completed'
           AND service_end_at   IS NOT NULL
           AND service_start_at IS NOT NULL
           AND created_at >= CURRENT_DATE"
    )->fetchColumn();
    $stats['avg_service_minutes_today'] = $avgRow !== false ? (float)$avgRow : null;

    // ------------------------------------------------------------------
    // 8. Per-department breakdown (today)
    // ------------------------------------------------------------------
    $stmtDept = $pdo->query(
        "SELECT d.department_name,
                COUNT(qt.ticket_id)                                         AS total,
                SUM(CASE WHEN qt.status IN ('waiting','checked_in') THEN 1 ELSE 0 END) AS waiting,
                SUM(CASE WHEN qt.status IN ('called','in_service')  THEN 1 ELSE 0 END) AS in_service,
                SUM(CASE WHEN qt.status = 'completed'               THEN 1 ELSE 0 END) AS completed
         FROM   department d
         LEFT JOIN queue_ticket qt
                ON qt.department_id = d.department_id
               AND qt.created_at >= CURRENT_DATE
         GROUP BY d.department_id, d.department_name
         ORDER BY d.department_name"
    );
    $stats['by_department'] = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

    // ------------------------------------------------------------------
    // 9. Doctor availability summary
    // ------------------------------------------------------------------
    $stmtDocs = $pdo->query(
        "SELECT status, COUNT(*) AS cnt FROM doctor GROUP BY status"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats['doctors_available'] = (int)($stmtDocs['available'] ?? 0);
    $stats['doctors_busy']      = (int)($stmtDocs['busy']      ?? 0);
    $stats['doctors_on_break']  = (int)($stmtDocs['on_break']  ?? 0);

    // ------------------------------------------------------------------
    // 10. Total doctors in system
    // ------------------------------------------------------------------
    $stats['total_doctors'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM doctor")
        ->fetchColumn();

    // Frontend-friendly aliases
    $stats['active_doctors']  = $stats['doctors_available'];
    $stats['today_tickets']    = $stats['tickets_today'];
    $completed = $stats['completed_today'];
    $today     = max($stats['tickets_today'], 1);
    $stats['completion_rate'] = round(($completed / $today) * 100, 1);

    return $stats;
}
