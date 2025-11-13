<?php
/**
 * AI Assistant API Endpoint
 *
 * Provides AI-powered features for consignments via REST API
 *
 * Endpoints:
 * - POST /api/ai/recommend-carrier - Get carrier recommendation
 * - POST /api/ai/analyze-transfer - Analyze transfer and get suggestions
 * - POST /api/ai/ask - Ask AI a question
 * - POST /api/ai/predict-cost - Predict transfer cost
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Services/AIConsignmentAssistant.php';

use Consignments\Services\AIConsignmentAssistant;

header('Content-Type: application/json');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = $_SERVER['PATH_INFO'] ?? $_GET['action'] ?? '';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Initialize AI Assistant
try {
    $ai = new AIConsignmentAssistant();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to initialize AI: ' . $e->getMessage()]);
    exit;
}

// Route to appropriate handler
switch ($path) {
    case '/recommend-carrier':
    case 'recommend-carrier':
        handleRecommendCarrier($ai, $input);
        break;

    case '/analyze-transfer':
    case 'analyze-transfer':
        handleAnalyzeTransfer($ai, $input);
        break;

    case '/ask':
    case 'ask':
        handleAsk($ai, $input);
        break;

    case '/predict-cost':
    case 'predict-cost':
        handlePredictCost($ai, $input);
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Unknown action',
            'available_actions' => [
                'recommend-carrier',
                'analyze-transfer',
                'ask',
                'predict-cost',
            ],
        ]);
        break;
}

/**
 * Handle carrier recommendation request
 */
function handleRecommendCarrier(AIConsignmentAssistant $ai, array $input): void
{
    if (empty($input['transfer'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing transfer data']);
        return;
    }

    $result = $ai->recommendCarrier($input['transfer']);
    echo json_encode($result);
}

/**
 * Handle transfer analysis request
 */
function handleAnalyzeTransfer(AIConsignmentAssistant $ai, array $input): void
{
    if (empty($input['consignment_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing consignment_id']);
        return;
    }

    $result = $ai->analyzeTransfer((int) $input['consignment_id']);
    echo json_encode($result);
}

/**
 * Handle ask question request
 */
function handleAsk(AIConsignmentAssistant $ai, array $input): void
{
    if (empty($input['question'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing question']);
        return;
    }

    $result = $ai->ask($input['question'], $input['context'] ?? []);
    echo json_encode($result);
}

/**
 * Handle cost prediction request
 */
function handlePredictCost(AIConsignmentAssistant $ai, array $input): void
{
    if (empty($input['transfer'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing transfer data']);
        return;
    }

    $result = $ai->predictCost($input['transfer']);
    echo json_encode($result);
}
