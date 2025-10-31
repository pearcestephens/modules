<?php
/**
 * Flagged Products Repository
 * 
 * Database access layer with prepared statements
 * Records AI context for every operation
 * 
 * @package CIS\FlaggedProducts
 */

declare(strict_types=1);

class FlaggedProductsRepository {
    
    /**
     * Get flagged products for outlet with AI context
     */
    public static function getFlaggedForOutlet(string $outletId, array $aiContext = []): array {
        $sql = "SELECT 
                    fp.id as fp_id,
                    fp.product_id,
                    fp.reason,
                    fp.qty_before,
                    fp.dummy_product,
                    fp.date_flagged,
                    vp.name as product_name,
                    vp.handle,
                    vp.image_url,
                    vi.inventory_level,
                    vi.reorder_point,
                    vi.reorder_amount
                FROM flagged_products fp
                INNER JOIN vend_products vp ON vp.id = fp.product_id
                INNER JOIN vend_inventory vi ON vi.product_id = vp.id AND vi.outlet_id = ?
                WHERE fp.outlet = ?
                AND fp.date_completed_stocktake IS NULL
                ORDER BY fp.date_flagged ASC, vp.name ASC";
        
        $results = sql_query_collection_safe($sql, [$outletId, $outletId]);
        
        // Record context for AI pipeline
        self::recordAIContext('get_flagged_products', [
            'outlet_id' => $outletId,
            'count' => count($results),
            'ai_context' => $aiContext,
            'timestamp' => time()
        ]);
        
        return $results;
    }
    
    /**
     * Complete flagged product with full security context
     */
    public static function completeProduct(
        string $productId,
        string $outletId,
        int $userId,
        int $qtyAfter,
        ?int $qtyBefore,
        array $securityContext
    ): bool {
        global $con;
        
        // Calculate security score
        require_once __DIR__ . '/../lib/AntiCheat.php';
        $securityScore = AntiCheat::calculateSecurityScore($securityContext);
        
        // Record attempt first (even if it fails)
        $timeSpent = $securityContext['time_spent'] ?? 0;
        AntiCheat::recordCompletionAttempt(
            $userId,
            (int)$outletId,
            $productId,
            $qtyBefore ?? 0,
            $qtyAfter,
            $timeSpent,
            $securityContext
        );
        
        // Check if user should be blocked
        $blockCheck = AntiCheat::shouldBlockUser($userId);
        if ($blockCheck['blocked']) {
            self::recordAIContext('completion_blocked', [
                'user_id' => $userId,
                'product_id' => $productId,
                'reason' => $blockCheck['reason'],
                'cheat_score' => $blockCheck['cheat_score']
            ]);
            
            throw new Exception($blockCheck['reason']);
        }
        
        // Update flagged product
        if ($qtyBefore !== null) {
            $sql = "UPDATE flagged_products 
                    SET date_completed_stocktake = NOW(),
                        qty_before = ?,
                        qty_after = ?,
                        completed_by_staff = ?
                    WHERE product_id = ?
                    AND outlet = ?
                    AND date_completed_stocktake IS NULL";
            $params = [$qtyBefore, $qtyAfter, $userId, $productId, $outletId];
        } else {
            $sql = "UPDATE flagged_products 
                    SET date_completed_stocktake = NOW(),
                        qty_after = ?,
                        completed_by_staff = ?
                    WHERE product_id = ?
                    AND outlet = ?
                    AND date_completed_stocktake IS NULL";
            $params = [$qtyAfter, $userId, $productId, $outletId];
        }
        
        sql_query_update_or_insert_safe($sql, $params);
        
        // Calculate accuracy and award points
        $wasAccurate = ($qtyBefore === $qtyAfter);
        self::awardPoints($userId, $outletId, $wasAccurate, $securityScore);
        
        // Record for AI pipeline
        self::recordAIContext('product_completed', [
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'product_id' => $productId,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'was_accurate' => $wasAccurate,
            'security_score' => $securityScore,
            'security_context' => $securityContext,
            'time_spent' => $timeSpent
        ]);
        
        // Trigger AI insight generation if needed
        self::queueAIInsight($userId, $outletId, $securityScore);
        
        return true;
    }
    
    /**
     * Award points based on accuracy and security
     */
    private static function awardPoints(int $userId, string $outletId, bool $wasAccurate, int $securityScore): void {
        $basePoints = 10;
        
        // Bonus for accuracy
        if ($wasAccurate) {
            $basePoints += 20;
        }
        
        // Security bonus (perfect score = +15 points)
        if ($securityScore >= 95) {
            $basePoints += 15;
        } elseif ($securityScore >= 85) {
            $basePoints += 10;
        } elseif ($securityScore >= 75) {
            $basePoints += 5;
        } else {
            // Penalty for low security score
            $basePoints = max(1, $basePoints - 10);
        }
        
        // Check streak
        $streak = self::getUserStreak($userId);
        if ($streak > 0) {
            $basePoints += min(50, $streak * 2); // Max 50 bonus for streak
        }
        
        // Record points
        $sql = "INSERT INTO flagged_products_points 
                (user_id, outlet_id, points_earned, reason, accuracy_percentage, streak_days) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $reason = $wasAccurate ? 'Accurate stocktake' : 'Completed stocktake';
        $accuracy = $wasAccurate ? 100 : 0;
        
        sql_query_update_or_insert_safe($sql, [
            $userId,
            $outletId,
            $basePoints,
            $reason,
            $accuracy,
            $streak
        ]);
        
        // Check for achievements
        self::checkAchievements($userId, $outletId);
    }
    
    /**
     * Get user's current streak
     */
    private static function getUserStreak(int $userId): int {
        $sql = "SELECT MAX(streak_days) as streak 
                FROM flagged_products_points 
                WHERE user_id = ? 
                AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        
        $result = sql_query_single_row_safe($sql, [$userId]);
        return $result ? (int)$result->streak : 0;
    }
    
    /**
     * Check and award achievements
     */
    private static function checkAchievements(int $userId, string $outletId): void {
        // Get user stats
        $stats = self::getUserStats($userId, 30);
        
        $achievements = [];
        
        // Perfect Week (7 days in a row, 100% accuracy)
        if ($stats['streak_days'] >= 7 && $stats['accuracy_30d'] >= 100) {
            $achievements[] = [
                'code' => 'perfect_week',
                'name' => '🏆 Perfect Week',
                'description' => '7 days in a row with 100% accuracy',
                'points' => 100
            ];
        }
        
        // Speed Demon (100 products completed)
        if ($stats['total_completed'] >= 100) {
            $achievements[] = [
                'code' => 'speed_demon_100',
                'name' => '⚡ Speed Demon',
                'description' => '100 products completed',
                'points' => 50
            ];
        }
        
        // Security Champion (average security score > 95)
        if ($stats['avg_security_score'] >= 95) {
            $achievements[] = [
                'code' => 'security_champion',
                'name' => '🛡️ Security Champion',
                'description' => 'Maintained 95+ security score',
                'points' => 75
            ];
        }
        
        // Accuracy Master (95%+ accuracy over 30 days)
        if ($stats['accuracy_30d'] >= 95) {
            $achievements[] = [
                'code' => 'accuracy_master',
                'name' => '🎯 Accuracy Master',
                'description' => '95%+ accuracy over 30 days',
                'points' => 150
            ];
        }
        
        // Award new achievements
        foreach ($achievements as $achievement) {
            $sql = "INSERT IGNORE INTO flagged_products_achievements 
                    (user_id, achievement_code, achievement_name, achievement_description, points_awarded) 
                    VALUES (?, ?, ?, ?, ?)";
            
            sql_query_update_or_insert_safe($sql, [
                $userId,
                $achievement['code'],
                $achievement['name'],
                $achievement['description'],
                $achievement['points']
            ]);
        }
    }
    
    /**
     * Get user statistics
     */
    public static function getUserStats(int $userId, int $days = 30): array {
        $sql = "SELECT 
                    COUNT(*) as total_completed,
                    AVG(CASE WHEN fpa.security_score >= 0 THEN fpa.security_score ELSE 100 END) as avg_security_score,
                    SUM(CASE WHEN fp.qty_before = fp.qty_after THEN 1 ELSE 0 END) as accurate_count,
                    COUNT(*) as total_count
                FROM flagged_products fp
                LEFT JOIN flagged_products_completion_attempts fpa ON fpa.product_id = fp.product_id AND fpa.user_id = ?
                WHERE fp.completed_by_staff = ?
                AND fp.date_completed_stocktake >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $result = sql_query_single_row_safe($sql, [$userId, $userId, $days]);
        
        if (!$result) {
            return [
                'total_completed' => 0,
                'accuracy_30d' => 0,
                'avg_security_score' => 100,
                'streak_days' => 0
            ];
        }
        
        $accuracy = $result->total_count > 0 
            ? ($result->accurate_count / $result->total_count) * 100 
            : 0;
        
        return [
            'total_completed' => (int)$result->total_completed,
            'accuracy_30d' => round($accuracy, 2),
            'avg_security_score' => round((float)$result->avg_security_score, 2),
            'streak_days' => self::getUserStreak($userId)
        ];
    }
    
    /**
     * Record AI context for pipeline processing
     */
    private static function recordAIContext(string $action, array $context): void {
        $sql = "INSERT INTO ai_context_log 
                (action, context_json, user_id, outlet_id, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        try {
            sql_query_update_or_insert_safe($sql, [
                $action,
                json_encode($context),
                $context['user_id'] ?? null,
                $context['outlet_id'] ?? null
            ]);
        } catch (Exception $e) {
            // Silently fail - don't break user flow if AI logging fails
            error_log("[AI_CONTEXT] Failed to log: " . $e->getMessage());
        }
    }
    
    /**
     * Queue AI insight generation
     */
    private static function queueAIInsight(int $userId, string $outletId, int $securityScore): void {
        // Check if user needs an insight
        $recentInsights = sql_query_collection_safe(
            "SELECT id FROM flagged_products_ai_insights 
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            [$userId]
        );
        
        // Don't spam insights - max once per day
        if (count($recentInsights) > 0) {
            return;
        }
        
        // Get user pattern data for AI
        $stats = self::getUserStats($userId, 7);
        
        // Queue for AI processing via Smart-Cron
        $sql = "INSERT INTO smart_cron_queue 
                (task_name, task_data, priority, status, created_at) 
                VALUES (?, ?, ?, 'pending', NOW())";
        
        $taskData = json_encode([
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'stats' => $stats,
            'security_score' => $securityScore,
            'action' => 'generate_ai_insight'
        ]);
        
        sql_query_update_or_insert_safe($sql, [
            'generate_flagged_products_insight',
            $taskData,
            5 // Medium priority
        ]);
    }
    
    /**
     * Get store statistics with date range support
     */
    public static function getStoreStats(string $outletId, ?string $startDate = null, ?string $endDate = null): array {
        $days = 30;
        if ($startDate && $endDate) {
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $days = $start->diff($end)->days;
        }
        
        $sql = "SELECT 
                    COUNT(DISTINCT fp.id) as total_flagged,
                    SUM(CASE WHEN fp.date_completed_stocktake IS NOT NULL THEN 1 ELSE 0 END) as completed,
                    AVG(CASE WHEN fp.qty_before = fp.qty_after THEN 100 ELSE 0 END) as accuracy_pct,
                    AVG(fpa.time_spent_seconds) as avg_time_seconds,
                    AVG(fpa.security_score) as avg_security_score,
                    SUM(COALESCE(us.points_earned, 0)) as total_points
                FROM flagged_products fp
                LEFT JOIN flagged_products_completion_attempts fpa ON fpa.product_id = fp.product_id
                LEFT JOIN flagged_products_user_stats us ON us.user_id = fp.completed_by_staff
                WHERE fp.outlet = ?
                AND fp.date_flagged >= " . ($startDate ? "?" : "DATE_SUB(NOW(), INTERVAL ? DAY)");
        
        $params = $startDate 
            ? [$outletId, $startDate . ' 00:00:00']
            : [$outletId, $days];
        
        if ($endDate) {
            $sql .= " AND fp.date_flagged <= ?";
            $params[] = $endDate . ' 23:59:59';
        }
        
        $result = sql_query_single_row_safe($sql, $params);
        
        return $result ? [
            'total_flagged' => (int)$result->total_flagged,
            'completed' => (int)$result->completed,
            'products_completed' => (int)$result->completed,
            'accuracy' => round((float)$result->accuracy_pct, 2),
            'avg_time_per_product' => round((float)$result->avg_time_seconds, 2),
            'avg_security_score' => round((float)$result->avg_security_score, 2),
            'total_points' => (int)$result->total_points,
            'completion_rate' => $result->total_flagged > 0 
                ? round(($result->completed / $result->total_flagged) * 100, 2)
                : 0
        ] : [
            'total_flagged' => 0,
            'completed' => 0,
            'products_completed' => 0,
            'accuracy' => 0,
            'avg_time_per_product' => 0,
            'avg_security_score' => 0,
            'total_points' => 0,
            'completion_rate' => 0
        ];
    }
    
    /**
     * Get historical trend data for charts
     */
    public static function getHistoricalTrends(string $outletId, string $startDate, string $endDate): array {
        $sql = "SELECT 
                    DATE(fp.date_completed_stocktake) as date,
                    COUNT(*) as products_completed,
                    AVG(CASE WHEN fp.qty_before = fp.qty_after THEN 100 ELSE 0 END) as accuracy
                FROM flagged_products fp
                WHERE " . ($outletId !== 'all' ? "fp.outlet = ? AND " : "") . "
                    fp.date_completed_stocktake IS NOT NULL
                    AND fp.date_completed_stocktake >= ?
                    AND fp.date_completed_stocktake <= ?
                GROUP BY DATE(fp.date_completed_stocktake)
                ORDER BY date ASC";
        
        $params = $outletId !== 'all'
            ? [$outletId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
            : [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
        
        $results = sql_query_collection_safe($sql, $params);
        
        // Format for Chart.js
        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = [
                'date' => date('M j', strtotime($row->date)),
                'products_completed' => (int)$row->products_completed,
                'accuracy' => round((float)$row->accuracy, 1)
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Get leaderboard with enhanced data
     */
    public static function getLeaderboard(string $period = 'weekly', int $limit = 10, ?string $outletId = null): array {
        // Calculate date range based on period
        $dateCondition = "1=1";
        switch ($period) {
            case 'daily':
                $dateCondition = "DATE(fp.date_completed_stocktake) = CURDATE()";
                break;
            case 'weekly':
                $dateCondition = "YEARWEEK(fp.date_completed_stocktake) = YEARWEEK(NOW())";
                break;
            case 'monthly':
                $dateCondition = "YEAR(fp.date_completed_stocktake) = YEAR(NOW()) AND MONTH(fp.date_completed_stocktake) = MONTH(NOW())";
                break;
            case 'all_time':
                $dateCondition = "1=1";
                break;
        }
        
        $sql = "SELECT 
                    us.user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    u.outlet_id,
                    o.name as outlet_name,
                    SUM(us.points_earned) as total_points,
                    COUNT(DISTINCT fp.id) as products_completed,
                    AVG(CASE WHEN fp.qty_before = fp.qty_after THEN 100 ELSE 0 END) as accuracy,
                    MAX(us.current_streak) as current_streak,
                    AVG(fpa.time_spent_seconds) as avg_time_per_product
                FROM flagged_products_user_stats us
                INNER JOIN users u ON u.id = us.user_id
                INNER JOIN vend_outlets o ON o.id = u.outlet_id
                LEFT JOIN flagged_products fp ON fp.completed_by_staff = us.user_id AND $dateCondition
                LEFT JOIN flagged_products_completion_attempts fpa ON fpa.user_id = us.user_id
                WHERE 1=1 " . ($outletId ? "AND u.outlet_id = ?" : "") . "
                GROUP BY us.user_id, u.first_name, u.last_name, u.outlet_id, o.name
                HAVING products_completed > 0
                ORDER BY total_points DESC, accuracy DESC
                LIMIT ?";
        
        $params = $outletId ? [$outletId, $limit] : [$limit];
        
        return sql_query_collection_safe($sql, $params);
    }
}
