<?php
/**
 * Flagged Products Module Logger
 * 
 * Wraps CISLogger with module-specific conveniences
 * Provides consistent logging patterns across the module
 * 
 * Usage:
 *   use FlaggedProducts\Lib\Logger;
 *   
 *   Logger::productCompleted($productId, $reason, $quality);
 *   Logger::productGenerated($productId, $strategy, $aiData);
 *   Logger::achievementEarned($userId, $achievementId);
 * 
 * @package CIS\FlaggedProducts\Lib
 * @version 1.0.0
 */

declare(strict_types=1);

namespace FlaggedProducts\Lib;

class Logger {
    
    private const CATEGORY = 'flagged_products';
    
    /**
     * Initialize logger (called automatically by bootstrap)
     */
    public static function init(): void {
        if (class_exists('CISLogger', false)) {
            \CISLogger::init();
        }
    }
    
    // ========================================================================
    // PRODUCT ACTIONS
    // ========================================================================
    
    /**
     * Log product completion
     */
    public static function productCompleted(
        int $productId,
        string $reason,
        ?int $qualityScore = null,
        ?float $timeSpent = null,
        array $additionalContext = []
    ): void {
        $context = array_merge([
            'completion_reason' => $reason,
            'quality_score' => $qualityScore,
            'time_spent_seconds' => $timeSpent,
        ], $additionalContext);
        
        self::log('product_completed', 'success', 'product', $productId, $context);
    }
    
    /**
     * Log product generation (AI/bot)
     */
    public static function productGenerated(
        int $productId,
        string $strategy,
        array $aiData = [],
        ?string $outletId = null
    ): void {
        $context = [
            'strategy' => $strategy,
            'outlet_id' => $outletId,
            'ai_confidence' => $aiData['confidence'] ?? null,
            'ai_reasoning' => $aiData['reasoning'] ?? null,
        ];
        
        self::log('product_generated', 'success', 'product', $productId, $context, 'bot');
        
        // Also log AI context if available
        if (!empty($aiData)) {
            self::logAI(
                'product_generation',
                $aiData['prompt'] ?? null,
                $aiData['response'] ?? null,
                $aiData['reasoning'] ?? null,
                ['strategy' => $strategy, 'outlet_id' => $outletId],
                ['product_id' => $productId],
                $aiData['confidence'] ?? null,
                ['product_generation', 'strategy_' . $strategy]
            );
        }
    }
    
    /**
     * Log product quality update
     */
    public static function productQualityUpdated(
        int $productId,
        int $oldQuality,
        int $newQuality,
        string $reason
    ): void {
        self::log('product_quality_updated', 'success', 'product', $productId, [
            'old_quality' => $oldQuality,
            'new_quality' => $newQuality,
            'reason' => $reason,
            'delta' => $newQuality - $oldQuality
        ]);
    }
    
    /**
     * Log product deletion
     */
    public static function productDeleted(
        int $productId,
        string $reason,
        array $productData = []
    ): void {
        self::log('product_deleted', 'success', 'product', $productId, [
            'reason' => $reason,
            'product_name' => $productData['name'] ?? null,
            'created_at' => $productData['created_at'] ?? null
        ]);
    }
    
    /**
     * Log product flagged (added to system)
     */
    public static function productFlagged(
        int $productId,
        string $reason,
        ?string $outletId = null,
        array $metadata = []
    ): void {
        $context = array_merge([
            'flag_reason' => $reason,
            'outlet_id' => $outletId,
        ], $metadata);
        
        self::log('product_flagged', 'success', 'product', $productId, $context);
    }
    
    // ========================================================================
    // LEADERBOARD & ACHIEVEMENTS
    // ========================================================================
    
    /**
     * Log achievement earned
     */
    public static function achievementEarned(
        int $userId,
        int $achievementId,
        string $achievementName,
        int $points,
        array $conditions = []
    ): void {
        self::log('achievement_earned', 'success', 'achievement', $achievementId, [
            'user_id' => $userId,
            'achievement_name' => $achievementName,
            'points_earned' => $points,
            'conditions_met' => $conditions
        ], 'system');
    }
    
    /**
     * Log leaderboard update
     */
    public static function leaderboardUpdated(
        ?int $userId = null,
        ?string $outletId = null,
        array $stats = []
    ): void {
        self::log('leaderboard_updated', 'success', 'leaderboard', null, [
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'stats' => $stats
        ], 'system');
    }
    
    /**
     * Log store stats refresh
     */
    public static function storeStatsRefreshed(
        string $outletId,
        array $stats = []
    ): void {
        self::log('store_stats_refreshed', 'success', 'store_stats', $outletId, [
            'metrics' => $stats
        ], 'system');
    }
    
    // ========================================================================
    // AI & INSIGHTS
    // ========================================================================
    
    /**
     * Log AI insight generation
     */
    public static function insightGenerated(
        string $insightType,
        array $data = [],
        ?float $confidence = null
    ): void {
        self::log('insight_generated', 'success', 'insight', null, [
            'insight_type' => $insightType,
            'data' => $data,
            'confidence' => $confidence
        ], 'bot');
    }
    
    /**
     * Log pattern detection
     */
    public static function patternDetected(
        string $pattern,
        array $affectedProducts = [],
        ?string $recommendation = null
    ): void {
        self::log('pattern_detected', 'success', 'pattern', null, [
            'pattern' => $pattern,
            'affected_count' => count($affectedProducts),
            'recommendation' => $recommendation
        ], 'bot');
    }
    
    // ========================================================================
    // CRON TASKS
    // ========================================================================
    
    /**
     * Log cron task start
     */
    public static function cronTaskStarted(
        string $taskName,
        array $config = []
    ): void {
        self::log('cron_task_started', 'success', 'cron_task', null, [
            'task_name' => $taskName,
            'config' => $config
        ], 'cron');
    }
    
    /**
     * Log cron task completion
     */
    public static function cronTaskCompleted(
        string $taskName,
        bool $success,
        array $metrics = [],
        ?string $errorMessage = null
    ): void {
        $result = $success ? 'success' : 'failure';
        
        self::log('cron_task_completed', $result, 'cron_task', null, [
            'task_name' => $taskName,
            'metrics' => $metrics,
            'error' => $errorMessage
        ], 'cron');
    }
    
    // ========================================================================
    // SECURITY
    // ========================================================================
    
    /**
     * Log anti-cheat detection
     */
    public static function cheatDetected(
        string $cheatType,
        int $userId,
        array $evidence = [],
        string $actionTaken = 'flagged'
    ): void {
        self::logSecurity(
            'cheat_detected',
            'critical',
            $userId,
            [
                'cheat_type' => $cheatType,
                'evidence' => $evidence,
                'module' => 'flagged_products'
            ],
            $actionTaken
        );
    }
    
    /**
     * Log DevTools detection
     */
    public static function devToolsDetected(
        int $userId,
        string $page
    ): void {
        self::logSecurity(
            'devtools_detected',
            'warning',
            $userId,
            ['page' => $page, 'module' => 'flagged_products'],
            'user_flagged'
        );
    }
    
    /**
     * Log suspicious activity
     */
    public static function suspiciousActivity(
        string $activityType,
        int $userId,
        array $details = []
    ): void {
        self::logSecurity(
            'suspicious_activity',
            'warning',
            $userId,
            array_merge(['activity_type' => $activityType, 'module' => 'flagged_products'], $details),
            'logged'
        );
    }
    
    // ========================================================================
    // PERFORMANCE
    // ========================================================================
    
    /**
     * Log page load time
     */
    public static function pageLoad(
        string $pageName,
        float $loadTimeMs,
        array $context = []
    ): void {
        self::logPerformance('page_load', $pageName, $loadTimeMs, 'ms', $context);
    }
    
    /**
     * Log slow query
     */
    public static function slowQuery(
        string $queryName,
        float $durationMs,
        string $sql,
        array $params = []
    ): void {
        self::logPerformance('slow_query', $queryName, $durationMs, 'ms', [
            'sql' => substr($sql, 0, 200),
            'params' => $params
        ]);
    }
    
    /**
     * Log API response time
     */
    public static function apiResponse(
        string $endpoint,
        float $responseTimeMs,
        bool $success,
        ?string $error = null
    ): void {
        self::logPerformance('api_response', $endpoint, $responseTimeMs, 'ms', [
            'success' => $success,
            'error' => $error
        ]);
    }
    
    // ========================================================================
    // ERRORS & WARNINGS
    // ========================================================================
    
    /**
     * Log error
     */
    public static function error(
        string $errorType,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        array $context = []
    ): void {
        $context['error_message'] = $message;
        self::log($errorType, 'failure', $entityType, $entityId, $context);
    }
    
    /**
     * Log warning
     */
    public static function warning(
        string $warningType,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        array $context = []
    ): void {
        $context['warning_message'] = $message;
        self::log($warningType, 'partial', $entityType, $entityId, $context);
    }
    
    // ========================================================================
    // INTERNAL HELPERS
    // ========================================================================
    
    /**
     * Core logging wrapper
     */
    private static function log(
        string $actionType,
        string $result,
        ?string $entityType = null,
        ?int $entityId = null,
        array $context = [],
        string $actorType = 'user'
    ): void {
        if (!class_exists('CISLogger', false)) {
            error_log("[FlaggedProducts\Logger] CISLogger not available");
            return;
        }
        
        try {
            \CISLogger::action(
                self::CATEGORY,
                $actionType,
                $result,
                $entityType,
                $entityId ? (string)$entityId : null,
                $context,
                $actorType
            );
        } catch (\Exception $e) {
            error_log("[FlaggedProducts\Logger] Failed to log: " . $e->getMessage());
        }
    }
    
    /**
     * AI logging wrapper
     */
    private static function logAI(
        string $contextType,
        ?string $prompt = null,
        ?string $response = null,
        ?string $reasoning = null,
        array $inputData = [],
        array $outputData = [],
        ?float $confidence = null,
        array $tags = []
    ): void {
        if (!class_exists('CISLogger', false)) {
            return;
        }
        
        try {
            \CISLogger::ai(
                $contextType,
                self::CATEGORY,
                $prompt,
                $response,
                $reasoning,
                $inputData,
                $outputData,
                $confidence,
                $tags
            );
        } catch (\Exception $e) {
            error_log("[FlaggedProducts\Logger] Failed to log AI: " . $e->getMessage());
        }
    }
    
    /**
     * Security logging wrapper
     */
    private static function logSecurity(
        string $eventType,
        string $severity,
        int $userId,
        array $threatIndicators = [],
        ?string $actionTaken = null
    ): void {
        if (!class_exists('CISLogger', false)) {
            return;
        }
        
        try {
            \CISLogger::security(
                $eventType,
                $severity,
                $userId,
                $threatIndicators,
                $actionTaken
            );
        } catch (\Exception $e) {
            error_log("[FlaggedProducts\Logger] Failed to log security: " . $e->getMessage());
        }
    }
    
    /**
     * Performance logging wrapper
     */
    private static function logPerformance(
        string $metricType,
        string $metricName,
        float $value,
        string $unit = 'ms',
        array $context = []
    ): void {
        if (!class_exists('CISLogger', false)) {
            return;
        }
        
        try {
            \CISLogger::performance(
                $metricType,
                $metricName,
                $value,
                $unit,
                $context
            );
        } catch (\Exception $e) {
            error_log("[FlaggedProducts\Logger] Failed to log performance: " . $e->getMessage());
        }
    }
}
