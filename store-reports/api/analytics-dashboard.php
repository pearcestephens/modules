<?php
/**
 * Analytics Dashboard API
 * GET /api/analytics-dashboard
 *
 * Retrieves dashboard metrics and KPIs
 * Aggregated data for charts and summary widgets
 *
 * @endpoint GET /api/analytics-dashboard?outlet_id=X&period=30d
 * @response JSON with metrics, trends, and chart data
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get query parameters
$outletId = $_GET['outlet_id'] ?? null;
$period = $_GET['period'] ?? '30d'; // 7d, 30d, 90d, 1y

// Get authenticated user
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Parse period to date range
$periodMap = [
    '7d' => 7,
    '30d' => 30,
    '90d' => 90,
    '1y' => 365
];

$days = $periodMap[$period] ?? 30;
$dateFrom = date('Y-m-d', strtotime("-{$days} days"));
$dateTo = date('Y-m-d');

try {
    $pdo = sr_pdo();

    // Build WHERE clause for outlet filtering
    $outletWhere = $outletId ? "AND r.outlet_id = ?" : "";
    $outletParams = $outletId ? [$outletId] : [];

    // Check user permissions
    $isManager = false; // TODO: Check role

    if (!$isManager) {
        // Regular users only see their own reports
        $outletWhere .= " AND r.created_by = ?";
        $outletParams[] = $userId;
    }

    // 1. Summary Statistics
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_reports,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_reports,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_reports,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_reports,
            AVG(grade_score) as avg_grade_score,
            AVG(completion_percentage) as avg_completion,
            COUNT(DISTINCT outlet_id) as outlets_covered
        FROM store_reports r
        WHERE report_date >= ? AND report_date <= ? {$outletWhere}
    ");
    $stmt->execute(array_merge([$dateFrom, $dateTo], $outletParams));
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Grade Distribution
    $stmt = $pdo->prepare("
        SELECT
            grade_letter,
            COUNT(*) as count
        FROM store_reports r
        WHERE report_date >= ? AND report_date <= ?
            AND status = 'completed' {$outletWhere}
        GROUP BY grade_letter
        ORDER BY
            FIELD(grade_letter, 'A', 'B', 'C', 'D', 'F')
    ");
    $stmt->execute(array_merge([$dateFrom, $dateTo], $outletParams));
    $gradeDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Trend Over Time (daily averages)
    $stmt = $pdo->prepare("
        SELECT
            DATE(report_date) as date,
            COUNT(*) as report_count,
            AVG(grade_score) as avg_score,
            AVG(completion_percentage) as avg_completion
        FROM store_reports r
        WHERE report_date >= ? AND report_date <= ? {$outletWhere}
        GROUP BY DATE(report_date)
        ORDER BY date ASC
    ");
    $stmt->execute(array_merge([$dateFrom, $dateTo], $outletParams));
    $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Top Issues (most flagged checklist items)
    $stmt = $pdo->prepare("
        SELECT
            c.question_text,
            c.category,
            COUNT(*) as flagged_count,
            AVG(ri.response_value) as avg_score
        FROM store_report_items ri
        JOIN store_report_checklist c ON ri.checklist_id = c.checklist_id
        JOIN store_reports r ON ri.report_id = r.report_id
        WHERE ri.is_flagged = 1
            AND r.report_date >= ? AND r.report_date <= ? {$outletWhere}
        GROUP BY ri.checklist_id
        ORDER BY flagged_count DESC
        LIMIT 10
    ");
    $stmt->execute(array_merge([$dateFrom, $dateTo], $outletParams));
    $topIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Outlet Performance (if not filtered to single outlet)
    $outletPerformance = [];

    if (!$outletId) {
        $stmt = $pdo->prepare("
            SELECT
                o.outlet_id,
                o.outlet_name,
                o.outlet_code,
                COUNT(r.report_id) as report_count,
                AVG(r.grade_score) as avg_score,
                MAX(r.report_date) as last_report_date
            FROM vend_outlets o
            LEFT JOIN store_reports r ON o.outlet_id = r.outlet_id
                AND r.report_date >= ? AND r.report_date <= ?
                AND r.status = 'completed'
            WHERE o.is_active = 1
            GROUP BY o.outlet_id
            ORDER BY avg_score DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $outletPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 6. AI Usage Statistics
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT r.request_id) as total_ai_requests,
            SUM(r.tokens_used) as total_tokens,
            COUNT(DISTINCT r.image_id) as images_analyzed,
            AVG(r.confidence_score) as avg_confidence
        FROM store_report_ai_requests r
        JOIN store_reports sr ON r.report_id = sr.report_id
        WHERE sr.report_date >= ? AND sr.report_date <= ? {$outletWhere}
    ");
    $stmt->execute(array_merge([$dateFrom, $dateTo], $outletParams));
    $aiStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 7. Recent Activity
    $stmt = $pdo->prepare("
        SELECT
            r.report_id,
            r.outlet_id,
            r.report_date,
            r.status,
            r.grade_letter,
            r.created_at,
            o.outlet_name,
            u.first_name,
            u.last_name
        FROM store_reports r
        JOIN vend_outlets o ON r.outlet_id = o.outlet_id
        JOIN users u ON r.created_by = u.user_id
        WHERE r.report_date >= ? AND r.report_date <= ? {$outletWhere}
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute(array_merge([$dateFrom, $dateTo], $outletParams));
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recentActivity as &$activity) {
        $activity['report_id'] = (int)$activity['report_id'];
        $activity['user_name'] = trim($activity['first_name'] . ' ' . $activity['last_name']);
        unset($activity['first_name'], $activity['last_name']);
    }

    // Format numeric values
    $summary['total_reports'] = (int)$summary['total_reports'];
    $summary['completed_reports'] = (int)$summary['completed_reports'];
    $summary['draft_reports'] = (int)$summary['draft_reports'];
    $summary['in_progress_reports'] = (int)$summary['in_progress_reports'];
    $summary['avg_grade_score'] = round((float)$summary['avg_grade_score'], 2);
    $summary['avg_completion'] = round((float)$summary['avg_completion'], 2);

    $aiStats['total_ai_requests'] = (int)$aiStats['total_ai_requests'];
    $aiStats['total_tokens'] = (int)$aiStats['total_tokens'];
    $aiStats['images_analyzed'] = (int)$aiStats['images_analyzed'];
    $aiStats['avg_confidence'] = round((float)$aiStats['avg_confidence'], 2);

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'period' => [
            'days' => $days,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ],
        'summary' => $summary,
        'grade_distribution' => $gradeDistribution,
        'trends' => $trends,
        'top_issues' => $topIssues,
        'outlet_performance' => $outletPerformance,
        'ai_statistics' => $aiStats,
        'recent_activity' => $recentActivity,
        'message' => 'Analytics dashboard data retrieved'
    ]);

} catch (Exception $e) {
    sr_log_error('analytics_dashboard_error', [
        'outlet_id' => $outletId,
        'period' => $period,
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve analytics dashboard',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
