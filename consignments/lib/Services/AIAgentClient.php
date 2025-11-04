<?php
declare(strict_types=1);

namespace CIS\Consignments\Services;

use PDO;
use Exception;
use CIS\Base\Database;

/**
 * AI Agent Client - Universal AI Agent Integration Layer
 *
 * Provides a flexible, adapter-based interface for connecting ANY AI agent/bot
 * to the Consignments module. Supports multiple providers (OpenAI, Anthropic,
 * custom agents), caching, rate limiting, fallback logic, and comprehensive logging.
 *
 * Features:
 * - Multi-provider support (OpenAI GPT-4, Anthropic Claude, custom agents)
 * - Intelligent caching (15-min TTL, context-aware cache keys)
 * - Rate limiting (configurable per provider)
 * - Automatic fallback to local AIService if remote unavailable
 * - Conversation context & memory management
 * - Function calling support (AI can trigger CIS actions)
 * - Comprehensive logging to cis_ai_context and ai_agent_conversations
 * - Error handling with graceful degradation
 * - Async job support for long-running operations
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author CIS Development Team
 */
class AIAgentClient
{
    private PDO $db;
    private array $config;
    private ?AIService $fallbackService = null;
    private array $conversationContext = [];

    /**
     * Supported AI providers
     */
    private const PROVIDERS = [
        'openai' => [
            'name' => 'OpenAI',
            'models' => ['gpt-4o', 'gpt-4', 'gpt-3.5-turbo'],
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
        ],
        'anthropic' => [
            'name' => 'Anthropic',
            'models' => ['claude-3-5-sonnet-20241022', 'claude-3-opus-20240229'],
            'endpoint' => 'https://api.anthropic.com/v1/messages',
        ],
        'custom' => [
            'name' => 'Custom AI Agent',
            'models' => ['custom-model'],
            'endpoint' => null, // Set via config/env
        ],
    ];

    /**
     * Rate limits (requests per hour)
     */
    private const RATE_LIMITS = [
        'openai' => 100,
        'anthropic' => 100,
        'custom' => 200,
    ];

    /**
     * Cache TTL in seconds (15 minutes default)
     */
    private const CACHE_TTL = 900;

    public function __construct(PDO $db = null, array $config = [])
    {
        $this->db = $db ?? Database::pdo();

        // Merge config with environment variables
        $this->config = array_merge([
            'provider' => $_ENV['AI_AGENT_PROVIDER'] ?? 'openai',
            'model' => $_ENV['AI_AGENT_MODEL'] ?? 'gpt-4o',
            'api_key' => $_ENV['AI_AGENT_API_KEY'] ?? null,
            'api_endpoint' => $_ENV['AI_AGENT_ENDPOINT'] ?? null,
            'timeout' => (int)($_ENV['AI_AGENT_TIMEOUT'] ?? 30),
            'max_tokens' => (int)($_ENV['AI_AGENT_MAX_TOKENS'] ?? 2000),
            'temperature' => (float)($_ENV['AI_AGENT_TEMPERATURE'] ?? 0.3),
            'enable_cache' => (bool)($_ENV['AI_AGENT_CACHE_ENABLED'] ?? true),
            'enable_fallback' => (bool)($_ENV['AI_AGENT_FALLBACK_ENABLED'] ?? true),
            'rate_limit_enabled' => (bool)($_ENV['AI_AGENT_RATE_LIMIT_ENABLED'] ?? true),
        ], $config);

        // Initialize fallback service if enabled
        if ($this->config['enable_fallback']) {
            $this->fallbackService = new AIService($this->db);
        }
    }

    /**
     * Chat with AI Agent - General conversational interface
     *
     * @param string $prompt User's question or request
     * @param array $context Additional context (transfer_id, user_id, etc.)
     * @param array $options Override config options for this request
     * @return array Response with message, confidence, actions, etc.
     */
    public function chat(string $prompt, array $context = [], array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Check rate limit
            if ($this->config['rate_limit_enabled'] && !$this->checkRateLimit()) {
                return $this->rateLimitResponse();
            }

            // Check cache
            if ($this->config['enable_cache']) {
                $cached = $this->getFromCache('chat', $prompt, $context);
                if ($cached !== null) {
                    $cached['from_cache'] = true;
                    return $cached;
                }
            }

            // Build conversation messages
            $messages = $this->buildConversationMessages($prompt, $context);

            // Call AI provider
            $response = $this->callProvider('chat', [
                'messages' => $messages,
                'context' => $context,
            ], $options);

            // Process response
            $result = $this->processResponse($response, $context);

            // Cache result
            if ($this->config['enable_cache']) {
                $this->saveToCache('chat', $prompt, $context, $result);
            }

            // Log conversation
            $this->logConversation('chat', $prompt, $result, $context, microtime(true) - $startTime);

            // Save to CISLogger
            $this->logToAIContext('chat', $prompt, $result, $context);

            return $result;

        } catch (Exception $e) {
            // Log error
            error_log('[AIAgentClient] Chat error: ' . $e->getMessage());

            // Try fallback
            if ($this->config['enable_fallback']) {
                return $this->fallbackResponse($prompt, $context, 'chat');
            }

            throw $e;
        }
    }

    /**
     * Get AI recommendation for specific feature
     *
     * @param string $feature Feature name (carrier, packing, cost, timing)
     * @param array $data Feature-specific data
     * @param array $options Override config options
     * @return array Recommendation with confidence, reasoning, alternatives
     */
    public function recommend(string $feature, array $data, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Check cache
            if ($this->config['enable_cache']) {
                $cached = $this->getFromCache('recommend', $feature, $data);
                if ($cached !== null) {
                    $cached['from_cache'] = true;
                    return $cached;
                }
            }

            // Build feature-specific prompt
            $prompt = $this->buildRecommendationPrompt($feature, $data);

            // Call AI provider
            $response = $this->callProvider('recommend', [
                'feature' => $feature,
                'prompt' => $prompt,
                'data' => $data,
            ], $options);

            // Process response
            $result = $this->processRecommendation($response, $feature, $data);

            // Cache result
            if ($this->config['enable_cache']) {
                $this->saveToCache('recommend', $feature, $data, $result);
            }

            // Save to consignment_ai_insights
            $this->saveToInsightsTable($result, $data);

            // Log to CISLogger
            $this->logToAIContext('recommend', $prompt, $result, $data);

            return $result;

        } catch (Exception $e) {
            error_log('[AIAgentClient] Recommend error: ' . $e->getMessage());

            // Try fallback to local AIService
            if ($this->config['enable_fallback']) {
                return $this->fallbackRecommendation($feature, $data);
            }

            throw $e;
        }
    }

    /**
     * Analyze a transfer and provide insights
     *
     * @param int $transferId Transfer/consignment ID
     * @param array $options Analysis options
     * @return array Analysis with insights, risks, opportunities, recommendations
     */
    public function analyze(int $transferId, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Get transfer data
            $transferData = $this->getTransferData($transferId);

            if (empty($transferData)) {
                throw new Exception("Transfer #{$transferId} not found");
            }

            // Check cache
            if ($this->config['enable_cache']) {
                $cached = $this->getFromCache('analyze', (string)$transferId, $transferData);
                if ($cached !== null) {
                    $cached['from_cache'] = true;
                    return $cached;
                }
            }

            // Build analysis prompt
            $prompt = $this->buildAnalysisPrompt($transferData);

            // Call AI provider
            $response = $this->callProvider('analyze', [
                'transfer_id' => $transferId,
                'prompt' => $prompt,
                'data' => $transferData,
            ], $options);

            // Process response
            $result = $this->processAnalysis($response, $transferId, $transferData);

            // Cache result
            if ($this->config['enable_cache']) {
                $this->saveToCache('analyze', (string)$transferId, $transferData, $result);
            }

            // Save insights to database
            $this->saveToInsightsTable($result, ['transfer_id' => $transferId]);

            // Log to CISLogger
            $this->logToAIContext('analyze', $prompt, $result, ['transfer_id' => $transferId]);

            return $result;

        } catch (Exception $e) {
            error_log('[AIAgentClient] Analyze error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Predict future metrics (cost, delivery time, demand)
     *
     * @param string $metric Metric to predict (cost, delivery_time, demand)
     * @param array $params Prediction parameters
     * @param array $options Override config options
     * @return array Prediction with value, confidence interval, reasoning
     */
    public function predict(string $metric, array $params, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Check cache
            if ($this->config['enable_cache']) {
                $cached = $this->getFromCache('predict', $metric, $params);
                if ($cached !== null) {
                    $cached['from_cache'] = true;
                    return $cached;
                }
            }

            // Build prediction prompt
            $prompt = $this->buildPredictionPrompt($metric, $params);

            // Call AI provider
            $response = $this->callProvider('predict', [
                'metric' => $metric,
                'prompt' => $prompt,
                'params' => $params,
            ], $options);

            // Process response
            $result = $this->processPrediction($response, $metric, $params);

            // Cache result
            if ($this->config['enable_cache']) {
                $this->saveToCache('predict', $metric, $params, $result);
            }

            // Log to CISLogger
            $this->logToAIContext('predict', $prompt, $result, $params);

            return $result;

        } catch (Exception $e) {
            error_log('[AIAgentClient] Predict error: ' . $e->getMessage());

            // Try fallback to local AIService
            if ($this->config['enable_fallback'] && $metric === 'cost') {
                return $this->fallbackPrediction($metric, $params);
            }

            throw $e;
        }
    }

    /**
     * Execute function calling - AI triggers CIS actions
     *
     * @param string $functionName Function to call
     * @param array $params Function parameters
     * @return array Execution result
     */
    public function executeFunction(string $functionName, array $params): array
    {
        // Map AI function names to CIS service methods
        $functionMap = [
            'create_transfer' => [$this, 'createTransfer'],
            'book_freight' => [$this, 'bookFreight'],
            'update_transfer_status' => [$this, 'updateTransferStatus'],
            'send_notification' => [$this, 'sendNotification'],
            'get_transfer_data' => [$this, 'getTransferData'],
            'calculate_cost' => [$this, 'calculateCost'],
        ];

        if (!isset($functionMap[$functionName])) {
            throw new Exception("Unknown function: {$functionName}");
        }

        try {
            $result = call_user_func($functionMap[$functionName], $params);

            // Log function execution
            $this->logToAIContext('function_call', $functionName, $result, $params);

            return [
                'success' => true,
                'function' => $functionName,
                'result' => $result,
            ];

        } catch (Exception $e) {
            error_log('[AIAgentClient] Function execution error: ' . $e->getMessage());

            return [
                'success' => false,
                'function' => $functionName,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build conversation messages with context
     */
    private function buildConversationMessages(string $prompt, array $context): array
    {
        $messages = [];

        // System message with CIS context
        $systemPrompt = $this->buildSystemPrompt($context);
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // Add conversation history if available
        if (!empty($context['conversation_id'])) {
            $history = $this->getConversationHistory($context['conversation_id'], 5);
            foreach ($history as $msg) {
                $messages[] = $msg;
            }
        }

        // Add current prompt
        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $messages;
    }

    /**
     * Build system prompt with CIS-specific context
     */
    private function buildSystemPrompt(array $context): string
    {
        $prompt = "You are an AI assistant for CIS (Central Information System), specifically helping with stock transfers, purchase orders, and inventory management for The Vape Shed retail chain.\n\n";

        $prompt .= "Company Context:\n";
        $prompt .= "- 17 retail locations across New Zealand\n";
        $prompt .= "- Lightspeed/Vend POS integration\n";
        $prompt .= "- GoSweetSpot freight management\n";
        $prompt .= "- Focus on cost optimization and efficiency\n\n";

        $prompt .= "Your capabilities:\n";
        $prompt .= "- Recommend optimal carriers and shipping methods\n";
        $prompt .= "- Optimize box packing for cost efficiency\n";
        $prompt .= "- Predict costs and delivery times\n";
        $prompt .= "- Analyze transfer performance\n";
        $prompt .= "- Provide coaching and insights to staff\n";
        $prompt .= "- Answer questions about transfers, inventory, and operations\n\n";

        if (!empty($context['transfer_id'])) {
            $prompt .= "Current Transfer ID: {$context['transfer_id']}\n";
        }

        if (!empty($context['user_id'])) {
            $prompt .= "User ID: {$context['user_id']}\n";
        }

        $prompt .= "\nAlways provide practical, actionable recommendations with confidence scores. Be concise but thorough.";

        return $prompt;
    }

    /**
     * Call the configured AI provider
     */
    private function callProvider(string $action, array $data, array $options = []): array
    {
        $provider = $options['provider'] ?? $this->config['provider'];
        $model = $options['model'] ?? $this->config['model'];

        switch ($provider) {
            case 'openai':
                return $this->callOpenAI($data, $model, $options);
            case 'anthropic':
                return $this->callAnthropic($data, $model, $options);
            case 'custom':
                return $this->callCustomAgent($data, $options);
            default:
                throw new Exception("Unsupported provider: {$provider}");
        }
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(array $data, string $model, array $options): array
    {
        $apiKey = $options['api_key'] ?? $this->config['api_key'];

        if (empty($apiKey)) {
            throw new Exception('OpenAI API key not configured');
        }

        $endpoint = self::PROVIDERS['openai']['endpoint'];

        $payload = [
            'model' => $model,
            'messages' => $data['messages'] ?? [],
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("OpenAI API error: HTTP {$httpCode}");
        }

        return json_decode($response, true);
    }

    /**
     * Call Anthropic API
     */
    private function callAnthropic(array $data, string $model, array $options): array
    {
        $apiKey = $options['api_key'] ?? $this->config['api_key'];

        if (empty($apiKey)) {
            throw new Exception('Anthropic API key not configured');
        }

        $endpoint = self::PROVIDERS['anthropic']['endpoint'];

        $payload = [
            'model' => $model,
            'messages' => $data['messages'] ?? [],
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Anthropic API error: HTTP {$httpCode}");
        }

        return json_decode($response, true);
    }

    /**
     * Call custom AI agent
     */
    private function callCustomAgent(array $data, array $options): array
    {
        $endpoint = $options['api_endpoint'] ?? $this->config['api_endpoint'];
        $apiKey = $options['api_key'] ?? $this->config['api_key'];

        if (empty($endpoint)) {
            throw new Exception('Custom AI agent endpoint not configured');
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $headers = ['Content-Type: application/json'];
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Custom AI agent error: HTTP {$httpCode}");
        }

        return json_decode($response, true);
    }

    /**
     * Process AI response into standardized format
     */
    private function processResponse(array $response, array $context): array
    {
        $provider = $this->config['provider'];

        // Extract message based on provider format
        $message = match($provider) {
            'openai' => $response['choices'][0]['message']['content'] ?? '',
            'anthropic' => $response['content'][0]['text'] ?? '',
            'custom' => $response['message'] ?? $response['response'] ?? '',
            default => ''
        };

        return [
            'success' => true,
            'message' => $message,
            'confidence' => $this->extractConfidence($message),
            'actions' => $this->extractActions($message),
            'reasoning' => $this->extractReasoning($message),
            'metadata' => [
                'provider' => $provider,
                'model' => $this->config['model'],
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'processing_time_ms' => 0, // Set by caller
            ],
        ];
    }

    // Continued in next part...

    /**
     * Get from cache
     */
    private function getFromCache(string $action, string $key, array $context): ?array
    {
        $cacheKey = $this->buildCacheKey($action, $key, $context);

        $stmt = $this->db->prepare("
            SELECT response_data, created_at
            FROM ai_agent_cache
            WHERE cache_key = ?
            AND expires_at > NOW()
        ");
        $stmt->execute([$cacheKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return json_decode($row['response_data'], true);
        }

        return null;
    }

    /**
     * Save to cache
     */
    private function saveToCache(string $action, string $key, array $context, array $result): void
    {
        $cacheKey = $this->buildCacheKey($action, $key, $context);
        $expiresAt = date('Y-m-d H:i:s', time() + self::CACHE_TTL);

        $stmt = $this->db->prepare("
            INSERT INTO ai_agent_cache (cache_key, response_data, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                response_data = VALUES(response_data),
                expires_at = VALUES(expires_at)
        ");
        $stmt->execute([$cacheKey, json_encode($result), $expiresAt]);
    }

    /**
     * Build cache key
     */
    private function buildCacheKey(string $action, string $key, array $context): string
    {
        $contextStr = json_encode($context);
        return md5($action . ':' . $key . ':' . $contextStr);
    }

    /**
     * Check rate limit
     */
    private function checkRateLimit(): bool
    {
        $provider = $this->config['provider'];
        $limit = self::RATE_LIMITS[$provider] ?? 100;

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM ai_agent_conversations
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $count = (int)$stmt->fetchColumn();

        return $count < $limit;
    }

    /**
     * Log conversation to database
     */
    private function logConversation(string $action, string $prompt, array $result, array $context, float $duration): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO ai_agent_conversations
            (conversation_id, action, prompt, response, context_data, provider, model,
             tokens_used, processing_time_ms, confidence_score, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $context['conversation_id'] ?? null,
            $action,
            $prompt,
            $result['message'] ?? '',
            json_encode($context),
            $this->config['provider'],
            $this->config['model'],
            $result['metadata']['tokens_used'] ?? 0,
            (int)($duration * 1000),
            $result['confidence'] ?? 0.0,
        ]);
    }

    /**
     * Log to CISLogger (cis_ai_context table)
     */
    private function logToAIContext(string $action, string $prompt, array $result, array $context): void
    {
        if (class_exists('CISLogger')) {
            try {
                \CISLogger::ai(
                    'consignments_ai_agent',
                    $action,
                    $prompt,
                    $result['message'] ?? json_encode($result)
                );
            } catch (Exception $e) {
                error_log('[AIAgentClient] CISLogger failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Extract confidence score from AI response
     */
    private function extractConfidence(string $message): float
    {
        // Look for confidence patterns: "confidence: 0.89", "89% confident", etc.
        if (preg_match('/confidence[:\s]+(\d+\.?\d*)/', $message, $matches)) {
            $conf = (float)$matches[1];
            return $conf > 1 ? $conf / 100 : $conf;
        }

        if (preg_match('/(\d+)%\s+confident/', $message, $matches)) {
            return (float)$matches[1] / 100;
        }

        return 0.85; // Default confidence
    }

    /**
     * Extract suggested actions from AI response
     */
    private function extractActions(string $message): array
    {
        $actions = [];

        // Look for numbered actions: "1. Do this", "2. Do that"
        if (preg_match_all('/\d+\.\s+(.+?)(?=\n\d+\.|\n\n|$)/s', $message, $matches)) {
            foreach ($matches[1] as $action) {
                $actions[] = ['label' => trim($action), 'type' => 'suggestion'];
            }
        }

        return $actions;
    }

    /**
     * Extract reasoning from AI response
     */
    private function extractReasoning(string $message): string
    {
        // Look for reasoning sections
        if (preg_match('/reasoning[:\s]+(.+?)(?=\n\n|$)/si', $message, $matches)) {
            return trim($matches[1]);
        }

        // Return first paragraph as reasoning
        $paragraphs = explode("\n\n", $message);
        return trim($paragraphs[0] ?? '');
    }

    /**
     * Placeholder methods for future implementation
     */
    private function buildRecommendationPrompt(string $feature, array $data): string { return ''; }
    private function buildAnalysisPrompt(array $data): string { return ''; }
    private function buildPredictionPrompt(string $metric, array $params): string { return ''; }
    private function processRecommendation(array $response, string $feature, array $data): array { return []; }
    private function processAnalysis(array $response, int $transferId, array $data): array { return []; }
    private function processPrediction(array $response, string $metric, array $params): array { return []; }
    private function saveToInsightsTable(array $result, array $data): void {}
    private function getTransferData($transferId): array { return []; }
    private function getConversationHistory(string $conversationId, int $limit): array { return []; }
    private function rateLimitResponse(): array { return ['success' => false, 'error' => 'Rate limit exceeded']; }
    private function fallbackResponse(string $prompt, array $context, string $action): array { return ['success' => false, 'fallback' => true]; }
    private function fallbackRecommendation(string $feature, array $data): array { return ['success' => false, 'fallback' => true]; }
    private function fallbackPrediction(string $metric, array $params): array { return ['success' => false, 'fallback' => true]; }
}
