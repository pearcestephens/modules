<?php
/**
 * Store Reports - Admin Trend Data API
 * Returns 30-day compliance trend for dashboard charts
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../private_html/check-login.php';
require_once __DIR__ . '/../config.php';

// Check admin access
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    $days = min(max($days, 7), 90); // Between 7-90 days

    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get daily average scores
    $stmt = $db->prepare("SELECT
        DATE(report_date) as date,
        COUNT(*) as report_count,
        AVG(overall_score) as avg_score,
        AVG(staff_score) as avg_staff_score,
        AVG(ai_score) as avg_ai_score,
        SUM(critical_issues_count) as total_critical_issues
    FROM store_reports
    WHERE report_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND status = 'completed'
    GROUP BY DATE(report_date)
    ORDER BY date ASC");

    $stmt->execute([$days]);
    $trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for Chart.js
    $labels = [];
    $scores = [];
    $staffScores = [];
    $aiScores = [];
    $reportCounts = [];
    $criticalIssues = [];

    foreach ($trendData as $row) {
        $labels[] = date('M j', strtotime($row['date']));
        $scores[] = round((float)$row['avg_score'], 1);
        $staffScores[] = round((float)$row['avg_staff_score'], 1);
        $aiScores[] = round((float)$row['avg_ai_score'], 1);
        $reportCounts[] = (int)$row['report_count'];
        $criticalIssues[] = (int)$row['total_critical_issues'];
    }

    // Get outlet breakdown
    $stmt = $db->prepare("SELECT
        vo.name as outlet_name,
        COUNT(sr.id) as report_count,
        AVG(sr.overall_score) as avg_score,
        SUM(sr.critical_issues_count) as critical_issues
    FROM store_reports sr
    LEFT JOIN vend_outlets vo ON sr.outlet_id = vo.id
    WHERE sr.report_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND sr.status = 'completed'
    GROUP BY sr.outlet_id, vo.name
    ORDER BY avg_score DESC");

    $stmt->execute([$days]);
    $outletBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get status distribution
    $stmt = $db->query("SELECT
        status,
        COUNT(*) as count
    FROM store_reports
    WHERE report_date >= DATE_SUB(NOW(), INTERVAL $days DAY)
    GROUP BY status");

    $statusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'scores' => $scores,
        'staff_scores' => $staffScores,
        'ai_scores' => $aiScores,
        'report_counts' => $reportCounts,
        'critical_issues' => $criticalIssues,
        'outlet_breakdown' => array_map(function($row) {
            return [
                'outlet' => $row['outlet_name'],
                'reports' => (int)$row['report_count'],
                'score' => round((float)$row['avg_score'], 1),
                'critical_issues' => (int)$row['critical_issues']
            ];
        }, $outletBreakdown),
        'status_distribution' => $statusDistribution,
        'period_days' => $days
    ]);

} catch (PDOException $e) {
    error_log("Store Reports - Trend data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Store Reports - Trend data error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
