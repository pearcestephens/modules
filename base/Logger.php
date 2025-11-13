<?php
/**
 * Universal CIS Logger
 * 
 * System-wide logging service for humans and bots
 * Records all actions with full context for AI analysis
 * 
 * ============================================================================
 * ✅ WHEN TO USE THIS LOGGER
 * ============================================================================
 * 
 * USE THIS FOR:
 * 
 * 1. USER ACTIONS (action method)
 *    - Any meaningful user interaction (create, update, delete, approve, reject)
 *    - State changes in the system (order placed, product flagged, transfer completed)
 *    - Important user decisions (approval given, settings changed)
 *    Example: CISLogger::action('transfers', 'pack_transfer', 'success', 'transfer', $transferId);
 * 
 * 2. SECURITY EVENTS (security method)
 *    - Failed login attempts
 *    - Unauthorized access attempts
 *    - Suspicious behavior detected (DevTools open, rapid requests, unusual patterns)
 *    - Permission violations
 *    Example: CISLogger::security('failed_login', 'warning', $userId, ['attempts' => 3]);
 * 
 * 3. AI/BOT INTERACTIONS (ai method)
 *    - AI model decisions and reasoning
 *    - Training data capture for machine learning
 *    - Bot pipeline execution for analysis
 *    - Pattern detection and recommendations
 *    Example: CISLogger::ai('product_flagged', 'flagged_products', $prompt, $response, $reasoning);
 * 
 * 4. PERFORMANCE METRICS (performance method)
 *    - Page load times
 *    - API response times
 *    - Slow queries
 *    - Resource usage spikes
 *    Example: CISLogger::performance('page_load', 'dashboard', 345.2, 'ms');
 * 
 * 5. BUSINESS-CRITICAL OPERATIONS
 *    - Financial transactions
 *    - Inventory changes
 *    - Data exports
 *    - Bulk operations
 *    - Integration with external systems (Vend, Xero, etc.)
 * 
 * 6. AUDIT TRAIL REQUIREMENTS
 *    - Who did what, when, and why
 *    - Changes to important records
 *    - Compliance and regulatory needs
 *    - Investigation of issues after the fact
 * 
 * ============================================================================
 * ❌ WHEN NOT TO USE THIS LOGGER
 * ============================================================================
 * 
 * DON'T USE THIS FOR:
 * 
 * 1. DEBUGGING / DEVELOPMENT
 *    ❌ Don't: CISLogger::action('debug', 'testing_value', 'success', null, null, ['value' => $x]);
 *    ✅ Do: error_log("Debug: value is $x");
 *    ✅ Do: var_dump($x); // During development only
 *    
 * 2. ERROR LOGGING (use ErrorHandler instead)
 *    ❌ Don't: CISLogger::action('error', 'exception_caught', 'failure', null, null, ['error' => $e->getMessage()]);
 *    ✅ Do: throw $e; // Let ErrorHandler catch it and log to logs_errors table
 *    ✅ Do: error_log($e->getMessage()); // For non-critical errors
 *    
 * 3. TRIVIAL/FREQUENT ACTIONS
 *    ❌ Don't log: Page views, form field focus, mouse movements (unless for security)
 *    ❌ Don't log: Every single database query
 *    ❌ Don't log: Auto-save operations that happen every few seconds
 *    ❌ Don't log: CSS/JS file loads, image loads
 *    
 * 4. HIGH-FREQUENCY POLLING
 *    ❌ Don't log: AJAX polling requests (every 5 seconds)
 *    ❌ Don't log: WebSocket heartbeats
 *    ❌ Don't log: Real-time data updates
 *    
 * 5. SENSITIVE DATA (unless encrypted/redacted)
 *    ❌ Don't log: Raw passwords (NEVER!)
 *    ❌ Don't log: Credit card numbers
 *    ❌ Don't log: Full API keys or tokens
 *    ✅ Do: Log hashed/masked versions if needed for audit
 *    
 * 6. TEMP/EXPERIMENTAL CODE
 *    ❌ Don't clutter logs with test data during development
 *    ✅ Do: Use error_log() for temporary debug output
 *    ✅ Do: Remove debug logging before committing
 * 
 * ============================================================================
 * 📏 GENERAL GUIDELINES
 * ============================================================================
 * 
 * ASK YOURSELF:
 * - Will I want to know this happened when investigating an issue? → YES = Log it
 * - Does this represent a meaningful user action or system event? → YES = Log it
 * - Is this happening more than 100 times per minute per user? → YES = Don't log it
 * - Is this just for debugging during development? → YES = Use error_log() instead
 * - Could this data help train AI or detect patterns? → YES = Log it with ai()
 * - Is this a security concern or suspicious behavior? → YES = Log it with security()
 * - Does this affect performance or resource usage? → YES = Log it with performance()
 * 
 * PERFORMANCE IMPACT:
 * - Each log = 1 database INSERT (fast, but adds up)
 * - Log tables grow quickly (millions of rows)
 * - Excessive logging can slow down the application
 * - Balance between "useful for analysis" and "too much noise"
 * 
 * BEST PRACTICES:
 * - Log the START and END of important processes
 * - Include context that helps understand what happened
 * - Use consistent category and action_type names
 * - Don't log inside tight loops
 * - Log failures AND successes (success tells us it worked!)
 * - Include entity_type and entity_id for traceability
 * 
 * ============================================================================
 * 🎯 QUICK DECISION TREE
 * ============================================================================
 * 
 * Is it an ERROR or EXCEPTION?
 *   → Use ErrorHandler (throw exception) or error_log()
 * 
 * Is it for DEBUGGING during development?
 *   → Use error_log() or var_dump() (remove before commit)
 * 
 * Is it a MEANINGFUL USER ACTION?
 *   → Use CISLogger::action()
 * 
 * Is it a SECURITY EVENT?
 *   → Use CISLogger::security()
 * 
 * Is it an AI/BOT DECISION?
 *   → Use CISLogger::ai()
 * 
 * Is it a PERFORMANCE METRIC?
 *   → Use CISLogger::performance()
 * 
 * Is it TRIVIAL or happens 100+ times/min?
 *   → Don't log it
 * 
 * Still unsure?
 *   → Ask: "Will this help me investigate an issue in production?"
 *      YES = Log it | NO = Skip it
 * 
 * ============================================================================
 * 
 * Usage Examples:
 *   CISLogger::action('flagged_products', 'complete_product', 'success', 'product', $productId, $context);
 *   CISLogger::ai('pattern_detected', 'flagged_products', $prompt, $response, $reasoning);
 *   CISLogger::security('devtools_detected', 'critical', $userId, $threatData);
 *   CISLogger::performance('page_load', 'flagged_products_page', 234.5, 'ms');
 * 
 * @package CIS\Services
 * @version 2.0.0
 */

declare(strict_types=1);

// Load Database class
require_once __DIR__ . '/Database.php';

use CIS\Base\Database;

class CISLogger {
    
    private static $sessionId = null;
    private static $traceId = null;
    private static $startTime = null;
    
    /**
     * Initialize logger (call at app start)
     */
    public static function init(): void {
        if (self::$sessionId === null) {
            self::$sessionId = session_id() ?: uniqid('session_', true);
            self::$traceId = uniqid('trace_', true);
            self::$startTime = microtime(true);
            
            // Start session tracking
            self::startSession();
        }
    }
    
    /**
     * Log an action (human or bot)
     * 
     * @param string $category Module or feature (e.g., 'flagged_products', 'transfers')
     * @param string $actionType Specific action (e.g., 'complete_product', 'create_transfer')
     * @param string $result 'success', 'failure', 'partial', 'pending'
     * @param string|null $entityType What was acted on (e.g., 'product', 'transfer')
     * @param string|null $entityId ID of entity
     * @param array $context Additional context data
     * @param string $actorType 'user', 'bot', 'system', 'cron', 'api'
     * @return int|null Log ID
     */
    public static function action(
        string $category,
        string $actionType,
        string $result = 'success',
        ?string $entityType = null,
        ?string $entityId = null,
        array $context = [],
        string $actorType = 'user'
    ): ?int {
        self::init();
        
        global $con;
        
        $userId = $_SESSION['user_id'] ?? null;
        $userName = isset($_SESSION['first_name'], $_SESSION['last_name']) 
            ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name']
            : null;
        
        // Add automatic context
        $context['page'] = $_SERVER['REQUEST_URI'] ?? null;
        $context['referer'] = $_SERVER['HTTP_REFERER'] ?? null;
        $context['timestamp'] = date('c');
        
        // Calculate execution time if available
        $executionTime = self::$startTime ? (int)((microtime(true) - self::$startTime) * 1000) : null;
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        
        $sql = "INSERT INTO cis_action_log 
                (actor_type, actor_id, actor_name, action_category, action_type, action_result,
                 entity_type, entity_id, context_json, metadata_json, ip_address, user_agent,
                 request_method, request_url, session_id, execution_time_ms, memory_usage_mb, trace_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
            $metadataJson = json_encode([
                'php_version' => PHP_VERSION,
                'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'
            ]);
            
            $logId = Database::insert('cis_action_log', [
                'actor_type' => $actorType,
                'actor_id' => $userId,
                'actor_name' => $userName,
                'action_category' => $category,
                'action_type' => $actionType,
                'action_result' => $result,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'context_json' => $contextJson,
                'metadata_json' => $metadataJson,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'request_url' => $_SERVER['REQUEST_URI'] ?? null,
                'session_id' => self::$sessionId,
                'execution_time_ms' => $executionTime,
                'memory_usage_mb' => $memoryUsage,
                'trace_id' => self::$traceId
            ]);
            
            // Update session activity
            self::updateSessionActivity();
            
            return $logId;
            
        } catch (Exception $e) {
            error_log("[CISLogger::action] Failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log AI context for training/analysis
     */
    public static function ai(
        string $contextType,
        string $sourceSystem,
        ?string $prompt = null,
        ?string $response = null,
        ?string $reasoning = null,
        array $inputData = [],
        array $outputData = [],
        ?float $confidenceScore = null,
        array $tags = []
    ): ?int {
        self::init();
        
        $userId = $_SESSION['user_id'] ?? null;
        $outletId = $_SESSION['outlet_id'] ?? null;
        
        try {
            return Database::insert('cis_ai_context', [
                'context_type' => $contextType,
                'source_system' => $sourceSystem,
                'user_id' => $userId,
                'outlet_id' => $outletId,
                'prompt' => $prompt,
                'response' => $response,
                'reasoning' => $reasoning,
                'input_data' => json_encode($inputData),
                'output_data' => json_encode($outputData),
                'confidence_score' => $confidenceScore,
                'tags' => json_encode($tags)
            ]);
        } catch (Exception $e) {
            error_log("[CISLogger::ai] Failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log security event
     */
    public static function security(
        string $eventType,
        string $severity,
        ?int $userId = null,
        array $threatIndicators = [],
        ?string $actionTaken = null,
        ?int $relatedActionId = null
    ): ?int {
        self::init();
        
        try {
            return Database::insert('cis_security_log', [
                'event_type' => $eventType,
                'severity' => $severity,
                'user_id' => $userId ?: ($_SESSION['user_id'] ?? null),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'threat_indicators' => json_encode($threatIndicators),
                'action_taken' => $actionTaken,
                'related_action_id' => $relatedActionId
            ]);
        } catch (Exception $e) {
            error_log("[CISLogger::security] Failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log performance metric
     */
    public static function performance(
        string $metricType,
        string $metricName,
        float $value,
        string $unit = 'ms',
        array $context = []
    ): ?int {
        self::init();
        
        try {
            return Database::insert('cis_performance_metrics', [
                'metric_type' => $metricType,
                'metric_name' => $metricName,
                'value' => $value,
                'unit' => $unit,
                'page_url' => $_SERVER['REQUEST_URI'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null,
                'outlet_id' => $_SESSION['outlet_id'] ?? null,
                'context_json' => json_encode($context)
            ]);
        } catch (Exception $e) {
            error_log("[CISLogger::performance] Failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log bot pipeline execution
     */
    public static function botPipeline(
        string $botName,
        string $pipelineStage,
        string $status,
        array $inputData = [],
        array $outputData = [],
        ?string $errorMessage = null,
        ?int $executionTimeMs = null,
        ?int $tokensUsed = null
    ): ?int {
        self::init();
        
        try {
            return Database::insert('cis_bot_pipeline_log', [
                'bot_name' => $botName,
                'pipeline_stage' => $pipelineStage,
                'status' => $status,
                'input_data' => json_encode($inputData),
                'output_data' => json_encode($outputData),
                'error_message' => $errorMessage,
                'execution_time_ms' => $executionTimeMs,
                'tokens_used' => $tokensUsed,
                'trace_id' => self::$traceId,
                'completed_at' => ($status === 'completed' ? 'NOW()' : null)
            ]);
        } catch (Exception $e) {
            error_log("[CISLogger::botPipeline] Failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Start user session tracking
     */
    private static function startSession(): void {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return;
        
        try {
            $deviceType = self::detectDeviceType();
            
            // Use REPLACE to handle ON DUPLICATE KEY UPDATE logic
            Database::execute("
                INSERT INTO cis_user_sessions 
                (session_id, user_id, outlet_id, ip_address, user_agent, device_type)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE last_activity_at = NOW()
            ", [
                self::$sessionId,
                $userId,
                $_SESSION['outlet_id'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $deviceType
            ]);
        } catch (Exception $e) {
            error_log("[CISLogger::startSession] Failed: " . $e->getMessage());
        }
    }
    
    /**
     * Update session activity
     */
    private static function updateSessionActivity(): void {
        try {
            Database::execute("
                UPDATE cis_user_sessions 
                SET last_activity_at = NOW(), 
                    total_actions = total_actions + 1
                WHERE session_id = ?
            ", [self::$sessionId]);
        } catch (Exception $e) {
            // Silent fail
        }
    }
    
    /**
     * End session
     */
    public static function endSession(string $reason = 'logout'): void {
        try {
            Database::execute("
                UPDATE cis_user_sessions 
                SET ended_at = NOW(), ended_reason = ?
                WHERE session_id = ? AND ended_at IS NULL
            ", [$reason, self::$sessionId]);
        } catch (Exception $e) {
            error_log("[CISLogger::endSession] Failed: " . $e->getMessage());
        }
    }
    
    /**
     * Detect device type from user agent
     */
    private static function detectDeviceType(): string {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/mobile|android|iphone|ipad|tablet/i', $userAgent)) {
            if (preg_match('/tablet|ipad/i', $userAgent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        
        return 'desktop';
    }
    
    /**
     * Get session statistics
     */
    public static function getSessionStats(?string $sessionId = null): ?object {
        $sessionId = $sessionId ?: self::$sessionId;
        
        $result = Database::queryOne("SELECT * FROM cis_user_sessions WHERE session_id = ?", [$sessionId]);
        return $result ? (object)$result : null;
    }
    
    /**
     * Query action log
     */
    public static function getActions(array $filters = [], int $limit = 100): array {
        $where = [];
        $params = [];
        
        if (isset($filters['user_id'])) {
            $where[] = "actor_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['category'])) {
            $where[] = "action_category = ?";
            $params[] = $filters['category'];
        }
        
        if (isset($filters['from_date'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['from_date'];
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM cis_action_log $whereClause ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return Database::query($sql, $params);
    }
}

// Auto-initialize on first use
CISLogger::init();
