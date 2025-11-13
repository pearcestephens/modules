<?php
/**
 * AI Image Analysis API
 * POST /api/ai-analyze-image
 *
 * Triggers AI vision analysis for uploaded image
 * Uses OpenAI GPT-4 Vision API
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../assets/services/mcp/StoreReportsAdapter.php';

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate limiting
sr_rate_limit('ai_analyze_image', 60, 10); // 10 analyses per minute

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$imageId = $input['image_id'] ?? null;
$analysisType = $input['analysis_type'] ?? 'general';

if (!$imageId) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required field: image_id'
    ]);
    exit;
}

// Get authenticated user
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    $pdo = sr_pdo();

    // Get image details
    $stmt = $pdo->prepare("
        SELECT
            i.image_id,
            i.report_id,
            i.file_path,
            i.caption,
            r.outlet_id,
            r.created_by
        FROM store_report_images i
        JOIN store_reports r ON i.report_id = r.report_id
        WHERE i.image_id = ?
    ");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        exit;
    }

    // Check permissions
    $isOwner = ($image['created_by'] == $userId);
    $isManager = false; // TODO: Check user role

    if (!$isOwner && !$isManager) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }

    // Verify image file exists
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $image['file_path'];

    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Image file not found on disk']);
        exit;
    }

    // Get AI analysis prompt based on type
    $prompts = [
        'general' => 'Analyze this retail store image. Describe what you see, identify any issues with cleanliness, organization, product display, or safety concerns. Be specific and detailed.',
        'cleanliness' => 'Focus on cleanliness: Are surfaces clean? Any dust, dirt, or spills? How would you rate the overall cleanliness on a scale of 1-5?',
        'organization' => 'Evaluate organization: Are products properly arranged? Any clutter? Is signage clear? Rate organization 1-5.',
        'safety' => 'Identify safety concerns: Fire hazards, blocked exits, tripping hazards, electrical issues, damaged fixtures. Be thorough.',
        'product_display' => 'Analyze product display: Are products facing forward? Proper pricing visible? Attractive merchandising? Stock levels adequate?',
        'compliance' => 'Check regulatory compliance: Age restriction signage visible? Product warnings displayed? Any violations of retail regulations?'
    ];

    $prompt = $prompts[$analysisType] ?? $prompts['general'];

    // Add custom prompt if provided
    if (!empty($input['custom_prompt'])) {
        $prompt = $input['custom_prompt'];
    }

    // =========================================================================
    // 🚀 MCP HUB INTEGRATION - Bypass GitHub Copilot Coding Agent!
    // =========================================================================
    // Use our centralized Intelligence Hub for AI operations
    // Hub handles: logging, caching, rate limiting, cost tracking
    // =========================================================================

    $adapter = new Services\MCP\Adapters\StoreReportsAdapter();
    $adapter->setUser($userId)
            ->setReport($image['report_id'])
            ->getMCPClient()
            ->setBotId('store-reports-vision-analyzer')
            ->setUnitId($image['outlet_id'] ?? 0);

    // Call MCP Hub for AI analysis (replaces direct OpenAI call)
    $analysisResult = $adapter->analyzeImage(
        $imageId,
        $analysisType,
        ['custom_prompt' => $input['custom_prompt'] ?? null]
    );

    if (!$analysisResult['success']) {
        throw new Exception('MCP Hub analysis failed: ' . ($analysisResult['error'] ?? 'Unknown error'));
    }

    $aiResponse = $analysisResult['summary'];
    $tokensUsed = $analysisResult['raw_response']['metadata']['tokens'] ?? 0;
    $confidenceScore = $analysisResult['confidence'];

    // Store AI analysis request and response
    $stmt = $pdo->prepare("
        INSERT INTO store_report_ai_requests (
            report_id,
            image_id,
            request_type,
            prompt,
            response,
            confidence_score,
            tokens_used,
            requested_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Calculate confidence score (simplified - parse from response if available)
    $confidenceScore = 0.85; // Default

    $stmt->execute([
        $image['report_id'],
        $imageId,
        $analysisType,
        $prompt,
        $aiResponse,
        $confidenceScore,
        $tokensUsed,
        $userId
    ]);

    $aiRequestId = $pdo->lastInsertId();

    // Extract issues from analysis
    $issues = $analysisResult['issues'] ?? [];
    $rating = $analysisResult['rating'] ?? null;

    // Update image with AI analysis results
    $stmt = $pdo->prepare("
        UPDATE store_report_images
        SET ai_analysis_status = 'completed',
            ai_last_analyzed_at = NOW()
        WHERE image_id = ?
    ");
    $stmt->execute([$imageId]);

    // Log the analysis
    $stmt = $pdo->prepare("
        INSERT INTO store_report_history (
            report_id, user_id, action, details
        ) VALUES (?, ?, 'ai_analysis_completed', ?)
    ");
    $stmt->execute([
        $image['report_id'],
        $userId,
        json_encode([
            'image_id' => $imageId,
            'analysis_type' => $analysisType,
            'ai_request_id' => $aiRequestId,
            'tokens_used' => $tokensUsed
        ])
    ]);

    // Success response (enhanced with MCP Hub results)
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'ai_request_id' => $aiRequestId,
        'analysis' => $aiResponse,
        'analysis_type' => $analysisType,
        'confidence_score' => $confidenceScore,
        'tokens_used' => $tokensUsed,
        'image_id' => $imageId,
        'issues_found' => count($issues),
        'issues' => $issues,
        'rating' => $rating,
        'message' => 'AI analysis completed via MCP Hub',
        'powered_by' => 'MCP Intelligence Hub'
    ]);

} catch (Exception $e) {
    sr_log_error('ai_analyze_image_error', [
        'image_id' => $imageId,
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'AI analysis failed',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
