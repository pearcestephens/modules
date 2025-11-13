<?php
/**
 * Reports List API
 * GET /api/reports-list
 *
 * Lists reports with filtering, sorting, and pagination
 *
 * @endpoint GET /api/reports-list?outlet_id=X&status=draft&page=1&limit=20
 * @response JSON with reports array and pagination metadata
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

// Get authenticated user
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Get query parameters
$outletId = $_GET['outlet_id'] ?? null;
$status = $_GET['status'] ?? null;
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;
$createdBy = $_GET['created_by'] ?? null;
$search = $_GET['search'] ?? null;
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// Validate sort column (prevent SQL injection)
$allowedSortColumns = [
    'created_at', 'updated_at', 'report_date', 'status',
    'completion_percentage', 'grade_score', 'outlet_id'
];

if (!in_array($sortBy, $allowedSortColumns)) {
    $sortBy = 'created_at';
}

try {
    $pdo = sr_pdo();

    // Build WHERE clause
    $where = ['1=1'];
    $params = [];

    // Filter by outlet
    if ($outletId) {
        $where[] = 'r.outlet_id = ?';
        $params[] = $outletId;
    }

    // Filter by status
    if ($status && in_array($status, ['draft', 'in_progress', 'completed', 'archived'])) {
        $where[] = 'r.status = ?';
        $params[] = $status;
    }

    // Filter by date range
    if ($dateFrom) {
        $where[] = 'r.report_date >= ?';
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $where[] = 'r.report_date <= ?';
        $params[] = $dateTo;
    }

    // Filter by creator
    if ($createdBy) {
        $where[] = 'r.created_by = ?';
        $params[] = $createdBy;
    }

    // Search in notes
    if ($search) {
        $where[] = '(r.staff_notes LIKE ? OR r.manager_notes LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    // Check user permissions - regular users only see their own reports
    // TODO: Managers should see all reports for their outlets
    $isManager = false; // TODO: Check role from database

    if (!$isManager) {
        $where[] = 'r.created_by = ?';
        $params[] = $userId;
    }

    $whereClause = implode(' AND ', $where);

    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM store_reports r
        WHERE {$whereClause}
    ";

    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = (int)$stmt->fetchColumn();

    // Calculate pagination
    $totalPages = ceil($totalRecords / $limit);
    $offset = ($page - 1) * $limit;

    // Get reports
    $sql = "
        SELECT
            r.report_id,
            r.outlet_id,
            r.report_date,
            r.status,
            r.completion_percentage,
            r.grade_score,
            r.grade_letter,
            r.staff_notes,
            r.manager_notes,
            r.created_at,
            r.updated_at,
            r.created_by,
            o.outlet_name,
            o.outlet_code,
            u.first_name,
            u.last_name,
            u.email,
            COUNT(DISTINCT i.image_id) as image_count,
            COUNT(DISTINCT vm.memo_id) as voice_memo_count,
            COUNT(DISTINCT ri.checklist_id) as completed_items
        FROM store_reports r
        LEFT JOIN vend_outlets o ON r.outlet_id = o.outlet_id
        LEFT JOIN users u ON r.created_by = u.user_id
        LEFT JOIN store_report_images i ON r.report_id = i.report_id
        LEFT JOIN store_report_voice_memos vm ON r.report_id = vm.report_id
        LEFT JOIN store_report_items ri ON r.report_id = ri.report_id
        WHERE {$whereClause}
        GROUP BY r.report_id
        ORDER BY r.{$sortBy} {$sortDir}
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format reports
    foreach ($reports as &$report) {
        $report['report_id'] = (int)$report['report_id'];
        $report['completion_percentage'] = (int)$report['completion_percentage'];
        $report['grade_score'] = (float)$report['grade_score'];
        $report['image_count'] = (int)$report['image_count'];
        $report['voice_memo_count'] = (int)$report['voice_memo_count'];
        $report['completed_items'] = (int)$report['completed_items'];

        $report['creator'] = [
            'user_id' => (int)$report['created_by'],
            'name' => trim($report['first_name'] . ' ' . $report['last_name']),
            'email' => $report['email']
        ];

        $report['outlet'] = [
            'outlet_id' => $report['outlet_id'],
            'name' => $report['outlet_name'],
            'code' => $report['outlet_code']
        ];

        // Remove redundant fields
        unset($report['created_by'], $report['first_name'], $report['last_name'],
              $report['email'], $report['outlet_name'], $report['outlet_code']);
    }

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $limit,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ],
        'filters' => [
            'outlet_id' => $outletId,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => $search
        ],
        'message' => 'Reports list retrieved'
    ]);

} catch (Exception $e) {
    sr_log_error('reports_list_error', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve reports list',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
