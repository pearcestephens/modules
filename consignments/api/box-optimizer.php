<?php
declare(strict_types=1);

/**
 * Box Optimization API Endpoint
 *
 * Analyzes boxes in real-time as they're entered and provides:
 * - Dimensional validation
 * - Utilization warnings
 * - Consolidation suggestions
 * - Carrier comparisons
 * - Weight tier analysis
 *
 * Endpoint: POST /modules/consignments/api/box-optimizer.php
 *
 * Request payload:
 * {
 *   "action": "analyze_box" | "analyze_multiple",
 *   "boxes": [{length, width, height, weight}, ...],
 *   "carrier": "nz_courier" | "nz_post" | "gss",
 *   "transfer_id": 123
 * }
 *
 * @package CIS\Consignments\API
 * @version 1.0.0
 */

header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: 1.0');

// Allow CORS for consignments UI
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Load dependencies
    require_once __DIR__ . '/../bootstrap.php';
    require_once __DIR__ . '/../lib/Services/BoxOptimizerService.php';

    use CIS\Consignments\Services\BoxOptimizerService;

    // Get DB connection
    $db = get_db() ?: (function() {
        // Fallback if get_db() not available
        global $pdo;
        return $pdo ?? null;
    })();

    if (!$db) {
        throw new RuntimeException('Database connection failed');
    }

    // Parse request
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }

    $action = $input['action'] ?? 'analyze_box';
    $boxes = $input['boxes'] ?? [];
    $carrier = $input['carrier'] ?? 'nz_courier';
    $transferId = $input['transfer_id'] ?? null;

    // Validate input
    if (empty($boxes)) {
        http_response_code(400);
        echo json_encode(['error' => 'No boxes provided']);
        exit;
    }

    // Initialize service
    $optimizer = new BoxOptimizerService($db);

    // Route to action
    if ($action === 'analyze_multiple') {
        // Analyze all boxes together
        $result = $optimizer->analyzeMultipleBoxes($boxes, $carrier);
    } else {
        // Analyze single box (first one)
        $result = $optimizer->analyzeBox($boxes[0], $carrier, $transferId);
    }

    // Return result
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    error_log('Box optimizer API error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);
    exit;
}
