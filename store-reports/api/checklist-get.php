<?php
/**
 * Checklist Get API
 * GET /api/checklist-get
 *
 * Retrieves current active checklist version
 * Used when creating new reports or viewing checklist structure
 *
 * @endpoint GET /api/checklist-get?version_id=123 (optional)
 * @response JSON with checklist items grouped by category
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
$versionId = $_GET['version_id'] ?? null;
$includeInactive = filter_var($_GET['include_inactive'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Get authenticated user
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    $pdo = sr_pdo();

    // Get version info
    if ($versionId) {
        // Get specific version
        $stmt = $pdo->prepare("
            SELECT version_id, version_number, change_description, created_at, created_by
            FROM store_report_checklist_versions
            WHERE version_id = ?
        ");
        $stmt->execute([$versionId]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$version) {
            http_response_code(404);
            echo json_encode(['error' => 'Checklist version not found']);
            exit;
        }
    } else {
        // Get current active version
        $stmt = $pdo->query("
            SELECT version_id, version_number, change_description, created_at, created_by
            FROM store_report_checklist_versions
            ORDER BY version_number DESC
            LIMIT 1
        ");
        $version = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$version) {
            // No versions exist, use default checklist
            $version = [
                'version_id' => null,
                'version_number' => 1,
                'change_description' => 'Initial checklist',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        $versionId = $version['version_id'];
    }

    // Get checklist items
    $whereClause = $includeInactive ? '1=1' : 'is_active = 1';

    if ($versionId) {
        $sql = "
            SELECT
                checklist_id,
                version_id,
                question_text,
                response_type,
                response_options,
                weight,
                category,
                requires_photo,
                requires_note,
                help_text,
                sort_order,
                is_active
            FROM store_report_checklist
            WHERE version_id = ? AND {$whereClause}
            ORDER BY category ASC, sort_order ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$versionId]);
    } else {
        // Legacy items without version_id
        $sql = "
            SELECT
                checklist_id,
                version_id,
                question_text,
                response_type,
                response_options,
                weight,
                category,
                requires_photo,
                requires_note,
                help_text,
                sort_order,
                is_active
            FROM store_report_checklist
            WHERE version_id IS NULL AND {$whereClause}
            ORDER BY category ASC, sort_order ASC
        ";
        $stmt = $pdo->query($sql);
    }

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format items and group by category
    $categories = [];
    $totalItems = 0;
    $totalWeight = 0;

    foreach ($items as $item) {
        $item['checklist_id'] = (int)$item['checklist_id'];
        $item['weight'] = (float)$item['weight'];
        $item['requires_photo'] = (bool)$item['requires_photo'];
        $item['requires_note'] = (bool)$item['requires_note'];
        $item['is_active'] = (bool)$item['is_active'];

        // Parse response options if JSON
        if ($item['response_options'] && is_string($item['response_options'])) {
            $item['response_options'] = json_decode($item['response_options'], true);
        }

        $category = $item['category'] ?: 'General';

        if (!isset($categories[$category])) {
            $categories[$category] = [
                'category_name' => $category,
                'items' => [],
                'item_count' => 0,
                'total_weight' => 0
            ];
        }

        $categories[$category]['items'][] = $item;
        $categories[$category]['item_count']++;
        $categories[$category]['total_weight'] += $item['weight'];

        $totalItems++;
        $totalWeight += $item['weight'];
    }

    // Convert categories to indexed array
    $categoriesArray = array_values($categories);

    // Get version history
    $stmt = $pdo->query("
        SELECT
            v.version_id,
            v.version_number,
            v.change_description,
            v.created_at,
            u.first_name,
            u.last_name
        FROM store_report_checklist_versions v
        LEFT JOIN users u ON v.created_by = u.user_id
        ORDER BY v.version_number DESC
        LIMIT 10
    ");
    $versionHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($versionHistory as &$v) {
        $v['version_id'] = (int)$v['version_id'];
        $v['version_number'] = (int)$v['version_number'];
        $v['created_by_name'] = trim($v['first_name'] . ' ' . $v['last_name']);
        unset($v['first_name'], $v['last_name']);
    }

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'version' => [
            'version_id' => $version['version_id'] ? (int)$version['version_id'] : null,
            'version_number' => (int)$version['version_number'],
            'change_description' => $version['change_description'],
            'created_at' => $version['created_at']
        ],
        'categories' => $categoriesArray,
        'statistics' => [
            'total_items' => $totalItems,
            'total_weight' => round($totalWeight, 2),
            'category_count' => count($categoriesArray)
        ],
        'version_history' => $versionHistory,
        'message' => 'Checklist retrieved'
    ]);

} catch (Exception $e) {
    sr_log_error('checklist_get_error', [
        'version_id' => $versionId,
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve checklist',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
