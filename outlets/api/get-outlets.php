<?php
/**
 * Outlets API - Get Outlets Data
 * Returns all outlets with revenue and status information
 */

header('Content-Type: application/json');
require_once '../../../config/database.php';

try {
    // Get filters from query params
    $status = $_GET['status'] ?? null;
    $city = $_GET['city'] ?? null;
    $search = $_GET['search'] ?? null;

    // Build query
    $sql = "SELECT * FROM vw_outlets_overview WHERE 1=1";
    $params = [];

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    if ($city) {
        $sql .= " AND city = ?";
        $params[] = $city;
    }

    if ($search) {
        $sql .= " AND (outlet_name LIKE ? OR outlet_code LIKE ? OR city LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= " ORDER BY outlet_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary stats
    $summarySQL = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN lease_end_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) as expiring_leases,
            AVG(revenue_last_30_days) as avg_revenue
        FROM vw_outlets_overview
    ";
    $summaryStmt = $pdo->query($summarySQL);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $outlets,
        'summary' => $summary,
        'count' => count($outlets)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
