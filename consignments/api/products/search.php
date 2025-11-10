<?php
/**
 * Products API - Search
 *
 * Fuzzy product search for purchase order creation
 * Uses 3-tier validation with confidence scoring
 *
 * Method: GET
 * Query params:
 * - q: Search query (required)
 * - limit: Max results (default: 20)
 *
 * @package CIS\Consignments\Products\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../../lib/Helpers/ValidationHelper.php';

use CIS\Consignments\Helpers\ValidationHelper;

// Check authentication
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only GET allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get search query
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (empty($query) || strlen($query) < 2) {
        throw new InvalidArgumentException('Search query must be at least 2 characters');
    }

    $limit = (int)($_GET['limit'] ?? 20);
    $limit = max(1, min(100, $limit)); // Between 1 and 100

    // Initialize database
    $db = get_db();

    // Use fuzzy matching from ValidationHelper
    $results = ValidationHelper::fuzzyMatchProduct($db, $query, $limit);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'data' => $results,
        'count' => count($results)
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Product Search API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
