<?php
/**
 * Flagged Products API Functions
 * 
 * Handles all API requests for the flagged products module
 * Routes: ?action=complete, ?action=report_violation, ?action=get_stats
 * 
 * @package CIS\FlaggedProducts
 * @version 2.0.0
 * @security MAXIMUM
 */

declare(strict_types=1);

// Load module dependencies
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/vend-queue.php';
require_once __DIR__ . '/../lib/AntiCheat.php';
require_once __DIR__ . '/../models/FlaggedProductsRepository.php';

// Set JSON header
header('Content-Type: application/json');

// Validate session
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing action parameter']);
    exit;
}

// Route to appropriate handler
try {
    switch ($action) {
        case 'complete':
            handleCompleteProduct();
            break;
            
        case 'report_violation':
            handleReportViolation();
            break;
            
        case 'get_stats':
            handleGetStats();
            break;
            
        case 'get_leaderboard':
            handleGetLeaderboard();
            break;
            
        case 'get_completion_summary':
            handleGetCompletionSummary();
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("[Flagged Products API] Error in action '$action': " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle product completion
 */
function handleCompleteProduct(): void {
    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        return;
    }
    
    // Validate required fields
    $required = ['product_id', 'quantity', 'time_taken', 'security_context'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
            return;
        }
    }
    
    $productId = (int)$input['product_id'];
    $quantity = (int)$input['quantity'];
    $timeTaken = (int)$input['time_taken'];
    $securityContext = $input['security_context'];
    $userId = $_SESSION['userID'];
    $outletId = $_SESSION['outlet_id'];
    
    // Validate quantity
    if ($quantity < 0 || $quantity > 9999) {
        echo json_encode(['success' => false, 'error' => 'Invalid quantity']);
        return;
    }
    
    // Validate time taken (2-120 seconds for human behavior)
    if ($timeTaken < 2 || $timeTaken > 120) {
        CISLogger::security('suspicious_timing', 'high', $userId, [
            'product_id' => $productId,
            'time_taken' => $timeTaken,
            'expected_range' => '2-120 seconds'
        ]);
    }
    
    // Complete product through repository
    $result = FlaggedProductsRepository::completeProduct(
        $productId,
        $userId,
        $outletId,
        $quantity,
        $securityContext,
        $timeTaken
    );
    
    if ($result['success']) {
        // Update Lightspeed via queue
        updateLightspeedInventory($productId, $outletId, $quantity);
        
        // Log success
        CISLogger::action(
            'flagged_products',
            'complete_product',
            'success',
            'product',
            (string)$productId,
            [
                'quantity' => $quantity,
                'time_taken' => $timeTaken,
                'points_awarded' => $result['points_awarded'],
                'accuracy' => $result['accuracy'],
                'security_score' => $securityContext['securityScore'] ?? null
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'points_awarded' => $result['points_awarded'],
            'new_points' => $result['new_total_points'],
            'accuracy' => $result['accuracy'],
            'streak' => $result['streak'] ?? 0,
            'achievements' => $result['achievements'] ?? []
        ]);
        
    } else {
        // Log failure
        CISLogger::action(
            'flagged_products',
            'complete_product',
            'failure',
            'product',
            (string)$productId,
            [
                'error' => $result['error'],
                'quantity' => $quantity
            ]
        );
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
}

/**
 * Handle security violation report
 */
function handleReportViolation(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['type'], $input['severity'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        return;
    }
    
    $userId = $_SESSION['userID'];
    $type = $input['type'];
    $severity = $input['severity'];
    $data = $input['data'] ?? [];
    
    // Log via AntiCheat system
    AntiCheat::logSuspiciousActivity(
        $userId,
        $type,
        $severity,
        $data
    );
    
    // Also log via CISLogger
    CISLogger::security(
        $type,
        $severity,
        $userId,
        $data
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Violation logged'
    ]);
}

/**
 * Handle get user stats
 */
function handleGetStats(): void {
    $userId = $_SESSION['userID'];
    $stats = FlaggedProductsRepository::getUserStats($userId);
    
    if ($stats) {
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Stats not found'
        ]);
    }
}

/**
 * Handle get leaderboard
 */
function handleGetLeaderboard(): void {
    $period = $_GET['period'] ?? 'weekly';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $leaderboard = FlaggedProductsRepository::getLeaderboard($period, $limit);
    
    echo json_encode([
        'success' => true,
        'period' => $period,
        'leaderboard' => $leaderboard
    ]);
}

/**
 * Handle get completion summary (for post-completion display)
 */
function handleGetCompletionSummary(): void {
    $userId = $_SESSION['userID'];
    $outletId = $_SESSION['outlet_id'];
    
    // Get user stats
    $userStats = FlaggedProductsRepository::getUserStats($userId);
    
    // Get store stats
    $storeStats = FlaggedProductsRepository::getStoreStats($outletId);
    
    // Get recent achievements
    $sql = "SELECT achievement_type, awarded_at 
            FROM flagged_products_achievements 
            WHERE user_id = ? 
            ORDER BY awarded_at DESC 
            LIMIT 3";
    $achievements = sql_query_collection_safe($sql, [$userId]);
    
    // Get leaderboard position
    $leaderboard = FlaggedProductsRepository::getLeaderboard('weekly', 100);
    $position = 0;
    foreach ($leaderboard as $index => $entry) {
        if ($entry->user_id == $userId) {
            $position = $index + 1;
            break;
        }
    }
    
    // Generate ChatGPT insight
    $aiInsight = generateCompletionInsight($userStats, $storeStats, $achievements);
    
    echo json_encode([
        'success' => true,
        'user_stats' => $userStats,
        'store_stats' => $storeStats,
        'achievements' => $achievements,
        'leaderboard_position' => $position,
        'ai_insight' => $aiInsight,
        'motivational_message' => getMotivationalMessage($userStats)
    ]);
}

/**
 * Update Lightspeed inventory via queue
 */
function updateLightspeedInventory(int $productId, string $outletId, int $quantity): void {
    global $con;
    
    // Get product details
    $sql = "SELECT vend_product_id, sku FROM flagged_products WHERE id = ?";
    $product = sql_query_single_row_safe($sql, [$productId]);
    
    if (!$product || !$product->vend_product_id) {
        error_log("[Flagged Products] Cannot update Lightspeed: product not found or no vend_product_id");
        return;
    }
    
    // Get outlet Vend ID
    $sql = "SELECT id FROM vend_outlets WHERE outlet_id = ?";
    $outlet = sql_query_single_row_safe($sql, [$outletId]);
    
    if (!$outlet) {
        error_log("[Flagged Products] Cannot update Lightspeed: outlet not found");
        return;
    }
    
    // Prepare Lightspeed API update
    $url = "https://vapeshed.vendhq.com/api/2.0/products/{$product->vend_product_id}/inventory";
    $payload = json_encode([
        'outlet_id' => $outlet->id,
        'count' => $quantity,
        'reason' => 'Flagged product count updated by staff'
    ]);
    
    // Add to Vend queue
    if (function_exists('enqueueVendTask')) {
        enqueueVendTask($url, $payload, 'PUT', 'flagged_products', 'inventory_update');
    }
    
    // Update CIS inventory immediately
    $sql = "UPDATE vend_inventory 
            SET inventory_level = ?,
                updated_at = NOW()
            WHERE product_id = ? 
            AND outlet_id = ?";
    
    sql_query_update_or_insert_safe($sql, [$quantity, $product->vend_product_id, $outlet->id]);
    
    // Log the update
    CISLogger::action(
        'flagged_products',
        'inventory_update',
        'success',
        'inventory',
        $product->vend_product_id,
        [
            'outlet_id' => $outletId,
            'quantity' => $quantity,
            'sku' => $product->sku,
            'queued_to_lightspeed' => true
        ]
    );
}

/**
 * Generate AI insight using ChatGPT-style analysis
 */
function generateCompletionInsight(object $userStats, object $storeStats, array $achievements): string {
    $accuracy = $userStats->accuracy_rate ?? 0;
    $securityScore = $userStats->security_score ?? 100;
    $streak = $userStats->current_streak ?? 0;
    
    $insights = [];
    
    // Accuracy insights
    if ($accuracy >= 95) {
        $insights[] = "🎯 Outstanding accuracy! Your {$accuracy}% precision shows exceptional attention to detail.";
    } elseif ($accuracy >= 85) {
        $insights[] = "👍 Good accuracy at {$accuracy}%. Consider double-checking products with complex packaging.";
    } else {
        $insights[] = "📊 Your {$accuracy}% accuracy has room for improvement. Take your time counting each product carefully.";
    }
    
    // Security insights
    if ($securityScore >= 95) {
        $insights[] = "🛡️ Perfect security score! You're following all best practices.";
    } elseif ($securityScore < 80) {
        $insights[] = "⚠️ Security score needs attention. Avoid switching tabs and keep focus on the task.";
    }
    
    // Streak insights
    if ($streak >= 7) {
        $insights[] = "🔥 Amazing {$streak}-day streak! Your consistency is driving team success.";
    } elseif ($streak >= 3) {
        $insights[] = "📈 {$streak} days in a row! Keep building that momentum.";
    }
    
    // Store comparison
    $storeAccuracy = $storeStats->avg_accuracy ?? 0;
    if ($accuracy > $storeAccuracy) {
        $insights[] = "⭐ You're above the store average! Your team relies on your thoroughness.";
    }
    
    // Recent achievements
    if (!empty($achievements)) {
        $insights[] = "🏆 Recent achievements unlocked! You're setting the standard for excellence.";
    }
    
    return implode(" ", $insights);
}

/**
 * Get motivational message based on performance
 */
function getMotivationalMessage(object $stats): string {
    $messages = [
        'excellent' => [
            "You're crushing it! 🚀",
            "Absolute legend! 💪",
            "On fire today! 🔥",
            "Setting the bar high! ⭐"
        ],
        'good' => [
            "Great work! Keep it up! 👍",
            "You're doing really well! 💪",
            "Solid performance! 📈",
            "Nice progress! ⭐"
        ],
        'average' => [
            "You've got this! 💪",
            "Keep pushing forward! 📈",
            "Every day is progress! 🎯",
            "You're on the right track! 👍"
        ]
    ];
    
    $accuracy = $stats->accuracy_rate ?? 0;
    $category = $accuracy >= 90 ? 'excellent' : ($accuracy >= 75 ? 'good' : 'average');
    
    return $messages[$category][array_rand($messages[$category])];
}

