<?php
/**
 * AI Business Insights API Endpoint
 *
 * Provides dashboard access to AI-generated business insights
 *
 * Endpoints:
 *   GET  /api/ai-insights              - Get all active insights
 *   GET  /api/ai-insights/critical     - Get critical insights only
 *   GET  /api/ai-insights/{id}         - Get specific insight
 *   POST /api/ai-insights/{id}/review  - Mark insight as reviewed
 *   POST /api/ai-insights/{id}/dismiss - Dismiss insight
 *   POST /api/ai-insights/ask          - Ask AI a business question
 *   POST /api/ai-insights/generate     - Generate fresh insights (manual trigger)
 *
 * @package CIS\Base\API
 */

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap/app.php';

use CIS\Base\Core\Application;
use CIS\Base\Services\AIBusinessInsightsService;
use CIS\Base\Core\Logger;

// Initialize
$app = Application::getInstance();
$logger = $app->make(Logger::class);

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS request (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$pathParts = explode('/', trim($path, '/'));

try {
    // Create insights service
    $insightsService = new AIBusinessInsightsService($app);

    // TODO: Add authentication check here
    // $user = authenticate();
    $userId = 1; // Placeholder - replace with actual user ID

    // Route the request
    if ($method === 'GET' && empty($path)) {
        // GET /api/ai-insights - Get all active insights
        $type = $_GET['type'] ?? null;
        $priority = $_GET['priority'] ?? null;
        $insights = $insightsService->getInsights($type, $priority);

        echo json_encode([
            'success' => true,
            'data' => $insights,
            'count' => count($insights),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);

    } elseif ($method === 'GET' && $path === 'critical') {
        // GET /api/ai-insights/critical - Get critical insights
        $insights = $insightsService->getCriticalInsights();

        echo json_encode([
            'success' => true,
            'data' => $insights,
            'count' => count($insights),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);

    } elseif ($method === 'GET' && is_numeric($pathParts[0])) {
        // GET /api/ai-insights/{id} - Get specific insight
        $insightId = (int)$pathParts[0];
        $insight = $insightsService->getInsights();
        $insight = array_filter($insight, fn($i) => $i['insight_id'] == $insightId);
        $insight = reset($insight);

        if ($insight) {
            echo json_encode([
                'success' => true,
                'data' => $insight,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Insight not found',
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
        }

    } elseif ($method === 'POST' && isset($pathParts[0]) && $pathParts[1] === 'review') {
        // POST /api/ai-insights/{id}/review - Review insight
        $insightId = (int)$pathParts[0];
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? null;

        $insightsService->reviewInsight($insightId, $userId, $action);

        echo json_encode([
            'success' => true,
            'message' => 'Insight reviewed successfully',
            'insight_id' => $insightId,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);

    } elseif ($method === 'POST' && isset($pathParts[0]) && $pathParts[1] === 'dismiss') {
        // POST /api/ai-insights/{id}/dismiss - Dismiss insight
        $insightId = (int)$pathParts[0];
        $input = json_decode(file_get_contents('php://input'), true);
        $reason = $input['reason'] ?? 'Not relevant';

        $insightsService->dismissInsight($insightId, $userId, $reason);

        echo json_encode([
            'success' => true,
            'message' => 'Insight dismissed',
            'insight_id' => $insightId,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);

    } elseif ($method === 'POST' && $path === 'ask') {
        // POST /api/ai-insights/ask - Ask AI a question
        $input = json_decode(file_get_contents('php://input'), true);
        $question = $input['question'] ?? '';

        if (empty($question)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Question is required',
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            exit;
        }

        $answer = $insightsService->ask($question, $input['context'] ?? []);

        echo json_encode([
            'success' => true,
            'data' => $answer,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);

    } elseif ($method === 'POST' && $path === 'generate') {
        // POST /api/ai-insights/generate - Generate fresh insights (manual trigger)
        $insights = $insightsService->generateDailyInsights();

        echo json_encode([
            'success' => true,
            'message' => 'Insights generated successfully',
            'count' => count($insights),
            'data' => $insights,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);

    } else {
        // Unknown endpoint
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint not found',
            'method' => $method,
            'path' => $path,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

    $logger->error('AI Insights API Error', [
        'error' => $e->getMessage(),
        'method' => $method,
        'path' => $path,
        'trace' => $e->getTraceAsString()
    ]);
}
