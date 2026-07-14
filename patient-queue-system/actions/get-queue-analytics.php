<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../config/db.php';

/**
 * OHAQRS - Advanced Queue Analytics
 *
 * Route:  GET /actions/get-queue-analytics.php
 * Access: admin only
 * Query params: period (day|week|month), department_id, start_date, end_date
 */



require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use GET.']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$sessionRole = $_SESSION['role'] ?? '';

if ($sessionRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

try {
    $period = trim((string)($_GET['period'] ?? 'day'));
    $departmentId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

    // Default date range
    if (!$startDate) {
        $startDate = match($period) {
            'week' => date('Y-m-d', strtotime('-7 days')),
            'month' => date('Y-m-d', strtotime('-30 days')),
            default => date('Y-m-d'),
        };
    }

    if (!$endDate) {
        $endDate = date('Y-m-d');
    }

    // Get overall metrics
    $overallMetrics = getOverallMetrics($pdo, $startDate, $endDate, $departmentId);

    // Get queue performance by department
    $departmentMetrics = getDepartmentMetrics($pdo, $startDate, $endDate, $departmentId);

    // Get doctor performance
    $doctorMetrics = getDoctorMetrics($pdo, $startDate, $endDate, $departmentId);

    // Get hourly distribution
    $hourlyDistribution = getHourlyDistribution($pdo, $startDate, $endDate, $departmentId);

    // Get no-show analysis
    $noShowAnalysis = getNoShowAnalysis($pdo, $startDate, $endDate, $departmentId);

    echo json_encode([
        'success' => true,
        'period' => $period,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'overall_metrics' => $overallMetrics,
        'department_metrics' => $departmentMetrics,
        'doctor_metrics' => $doctorMetrics,
        'hourly_distribution' => $hourlyDistribution,
        'no_show_analysis' => $noShowAnalysis,
    ]);

} catch (Exception $e) {
    error_log('Queue analytics error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}

function getOverallMetrics($pdo, $startDate, $endDate, $departmentId = null) {
    $where = 'qt.created_at::date BETWEEN :start_date AND :end_date';
    $params = [':start_date' => $startDate, ':end_date' => $endDate];

    if ($departmentId) {
        $where .= ' AND qt.department_id = :dept_id';
        $params[':dept_id'] = $departmentId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) as total_tickets,
            SUM(CASE WHEN qt.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN qt.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN qt.status = 'no_show' THEN 1 ELSE 0 END) as no_show,
            SUM(CASE WHEN qt.status IN ('waiting', 'checked_in', 'called') THEN 1 ELSE 0 END) as active,
            ROUND(AVG(EXTRACT(EPOCH FROM (qt.called_at - qt.booked_at)) / 60)::numeric, 2) as avg_wait_time_minutes,
            ROUND(AVG(EXTRACT(EPOCH FROM (qt.service_end_at - qt.service_start_at)) / 60)::numeric, 2) as avg_service_time_minutes,
            MIN(qt.booked_at) as earliest_ticket,
            MAX(qt.booked_at) as latest_ticket
         FROM queue_ticket qt
         WHERE $where"
    );

    $stmt->execute($params);
    return $stmt->fetch();
}

function getDepartmentMetrics($pdo, $startDate, $endDate, $departmentId = null) {
    $where = 'qt.created_at::date BETWEEN :start_date AND :end_date';
    $params = [':start_date' => $startDate, ':end_date' => $endDate];

    if ($departmentId) {
        $where .= ' AND qt.department_id = :dept_id';
        $params[':dept_id'] = $departmentId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            d.department_id,
            d.department_name,
            COUNT(qt.ticket_id) as total_tickets,
            SUM(CASE WHEN qt.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN qt.status = 'no_show' THEN 1 ELSE 0 END) as no_show,
            ROUND(AVG(EXTRACT(EPOCH FROM (qt.called_at - qt.booked_at)) / 60)::numeric, 2) as avg_wait_time,
            ROUND(AVG(EXTRACT(EPOCH FROM (qt.service_end_at - qt.service_start_at)) / 60)::numeric, 2) as avg_service_time
         FROM department d
         LEFT JOIN queue_ticket qt ON d.department_id = qt.department_id AND $where
         GROUP BY d.department_id, d.department_name
         ORDER BY total_tickets DESC"
    );

    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getDoctorMetrics($pdo, $startDate, $endDate, $departmentId = null) {
    $where = 'qt.created_at::date BETWEEN :start_date AND :end_date';
    $params = [':start_date' => $startDate, ':end_date' => $endDate];

    if ($departmentId) {
        $where .= ' AND qt.department_id = :dept_id';
        $params[':dept_id'] = $departmentId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            doc.doctor_id,
            doc.first_name,
            doc.last_name,
            d.department_name,
            COUNT(qt.ticket_id) as total_patients,
            SUM(CASE WHEN qt.status = 'completed' THEN 1 ELSE 0 END) as completed,
            ROUND(AVG(EXTRACT(EPOCH FROM (qt.service_end_at - qt.service_start_at)) / 60)::numeric, 2) as avg_service_time,
            ROUND(COUNT(qt.ticket_id)::numeric / 
                NULLIF(EXTRACT(EPOCH FROM (MAX(qt.service_end_at) - MIN(qt.service_start_at))) / 3600, 0), 2) as patients_per_hour
         FROM doctor doc
         LEFT JOIN queue_ticket qt ON doc.doctor_id = qt.doctor_id AND $where
         LEFT JOIN department d ON doc.department_id = d.department_id
         WHERE qt.ticket_id IS NOT NULL
         GROUP BY doc.doctor_id, doc.first_name, doc.last_name, d.department_name
         ORDER BY completed DESC"
    );

    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getHourlyDistribution($pdo, $startDate, $endDate, $departmentId = null) {
    $where = 'qt.created_at::date BETWEEN :start_date AND :end_date';
    $params = [':start_date' => $startDate, ':end_date' => $endDate];

    if ($departmentId) {
        $where .= ' AND qt.department_id = :dept_id';
        $params[':dept_id'] = $departmentId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            EXTRACT(HOUR FROM qt.booked_at) as hour,
            COUNT(*) as ticket_count,
            ROUND(AVG(EXTRACT(EPOCH FROM (qt.called_at - qt.booked_at)) / 60)::numeric, 2) as avg_wait_time
         FROM queue_ticket qt
         WHERE $where AND qt.booked_at IS NOT NULL
         GROUP BY hour
         ORDER BY hour"
    );

    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getNoShowAnalysis($pdo, $startDate, $endDate, $departmentId = null) {
    $where = 'qt.created_at::date BETWEEN :start_date AND :end_date';
    $params = [':start_date' => $startDate, ':end_date' => $endDate];

    if ($departmentId) {
        $where .= ' AND qt.department_id = :dept_id';
        $params[':dept_id'] = $departmentId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) as total_appointments,
            SUM(CASE WHEN qt.status = 'no_show' THEN 1 ELSE 0 END) as no_show_count,
            ROUND((SUM(CASE WHEN qt.status = 'no_show' THEN 1 ELSE 0 END)::numeric / 
                    COUNT(*) * 100)::numeric, 2) as no_show_percentage,
            d.department_name,
            COUNT(DISTINCT p.patient_id) as unique_patients
         FROM queue_ticket qt
         LEFT JOIN department d ON qt.department_id = d.department_id
         LEFT JOIN patient p ON qt.patient_id = p.patient_id
         WHERE $where
         GROUP BY d.department_name"
    );

    $stmt->execute($params);
    return $stmt->fetchAll();
}
