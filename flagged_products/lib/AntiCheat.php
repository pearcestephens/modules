<?php
/**
 * Anti-Cheat System for Flagged Products
 * 
 * Prevents staff from cheating by:
 * - Detecting DevTools open
 * - Detecting browser extensions
 * - Monitoring tab switching
 * - Analyzing timing patterns
 * - Detecting screen recording
 * - Preventing screenshots
 * - Monitoring clipboard access
 * - Detecting multiple monitors
 * - Analyzing mouse movement patterns
 * 
 * @package CIS\FlaggedProducts
 * @version 1.0.0
 */

declare(strict_types=1);

class AntiCheat {
    
    /**
     * Log suspicious activity to database
     */
    public static function logSuspiciousActivity(
        int $userId,
        string $outletId,
        string $activityType,
        array $details,
        string $severity = 'warning'
    ): void {
        $sql = "INSERT INTO flagged_products_audit_log 
                (user_id, outlet_id, activity_type, details, severity, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $detailsJson = json_encode($details);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        sql_query_update_or_insert_safe($sql, [
            $userId,
            $outletId,
            $activityType,
            $detailsJson,
            $severity,
            $ipAddress,
            $userAgent
        ]);
    }
    
    /**
     * Record completion attempt with full context
     */
    public static function recordCompletionAttempt(
        int $userId,
        string $outletId,
        string $productId,
        int $qtyBefore,
        int $qtyAfter,
        float $timeSpent,
        array $securityContext
    ): int {
        $sql = "INSERT INTO flagged_products_completion_attempts 
                (user_id, outlet_id, product_id, qty_before, qty_after, time_spent_seconds, 
                 had_focus, tab_switches, devtools_detected, extensions_detected, 
                 suspicious_timing, mouse_movements, security_score, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        return sql_query_update_or_insert_safe($sql, [
            $userId,
            $outletId,
            $productId,
            $qtyBefore,
            $qtyAfter,
            $timeSpent,
            $securityContext['had_focus'] ?? 1,
            $securityContext['tab_switches'] ?? 0,
            $securityContext['devtools_detected'] ?? 0,
            $securityContext['extensions_detected'] ?? 0,
            $securityContext['suspicious_timing'] ?? 0,
            $securityContext['mouse_movements'] ?? 0,
            $securityContext['security_score'] ?? 100,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    /**
     * Calculate security score (0-100)
     * Lower score = more suspicious
     */
    public static function calculateSecurityScore(array $context): int {
        $score = 100;
        
        // DevTools detected = major red flag
        if ($context['devtools_detected'] ?? false) {
            $score -= 50;
        }
        
        // Browser extensions detected
        if (($context['extensions_detected'] ?? 0) > 0) {
            $score -= 20;
        }
        
        // Lost focus during task
        if (!($context['had_focus'] ?? true)) {
            $score -= 15;
        }
        
        // Too many tab switches
        $tabSwitches = $context['tab_switches'] ?? 0;
        if ($tabSwitches > 3) {
            $score -= min(20, $tabSwitches * 3);
        }
        
        // Suspicious timing (too fast or too slow)
        if ($context['suspicious_timing'] ?? false) {
            $score -= 25;
        }
        
        // Very few mouse movements (bot behavior)
        if (($context['mouse_movements'] ?? 100) < 10) {
            $score -= 15;
        }
        
        // Multiple monitors detected
        if ($context['multiple_monitors'] ?? false) {
            $score -= 10;
        }
        
        // Screen recording detected
        if ($context['screen_recording'] ?? false) {
            $score -= 30;
        }
        
        // Virtual machine detected
        if ($context['vm_detected'] ?? false) {
            $score -= 25;
        }
        
        return max(0, $score);
    }
    
    /**
     * Check if user has pattern of cheating
     */
    public static function getUserCheatScore(int $userId, int $days = 30): array {
        $sql = "SELECT 
                    COUNT(*) as total_attempts,
                    AVG(security_score) as avg_security_score,
                    SUM(devtools_detected) as devtools_count,
                    SUM(tab_switches) as total_tab_switches,
                    AVG(time_spent_seconds) as avg_time,
                    SUM(suspicious_timing) as suspicious_timing_count
                FROM flagged_products_completion_attempts 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $result = sql_query_single_row_safe($sql, [$userId, $days]);
        
        if (!$result) {
            return [
                'total_attempts' => 0,
                'avg_security_score' => 100,
                'risk_level' => 'none',
                'is_suspicious' => false
            ];
        }
        
        $avgScore = (float)$result->avg_security_score;
        $devtoolsCount = (int)$result->devtools_count;
        $suspiciousCount = (int)$result->suspicious_timing_count;
        
        // Determine risk level
        $riskLevel = 'low';
        $isSuspicious = false;
        
        if ($avgScore < 50 || $devtoolsCount > 5 || $suspiciousCount > 10) {
            $riskLevel = 'critical';
            $isSuspicious = true;
        } elseif ($avgScore < 70 || $devtoolsCount > 2 || $suspiciousCount > 5) {
            $riskLevel = 'high';
            $isSuspicious = true;
        } elseif ($avgScore < 85 || $devtoolsCount > 0 || $suspiciousCount > 2) {
            $riskLevel = 'medium';
        }
        
        return [
            'total_attempts' => (int)$result->total_attempts,
            'avg_security_score' => $avgScore,
            'devtools_count' => $devtoolsCount,
            'total_tab_switches' => (int)$result->total_tab_switches,
            'avg_time' => (float)$result->avg_time,
            'suspicious_timing_count' => $suspiciousCount,
            'risk_level' => $riskLevel,
            'is_suspicious' => $isSuspicious
        ];
    }
    
    /**
     * Get suspicious users report
     */
    public static function getSuspiciousUsers(?string $outletId = null, int $days = 7): array {
        $sql = "SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.outlet_id,
                    COUNT(DISTINCT fpa.id) as attempt_count,
                    AVG(fpa.security_score) as avg_security_score,
                    SUM(fpa.devtools_detected) as devtools_count,
                    SUM(fpa.tab_switches) as tab_switches,
                    SUM(fpa.suspicious_timing) as suspicious_count,
                    MAX(fpa.created_at) as last_attempt
                FROM users u
                INNER JOIN flagged_products_completion_attempts fpa ON fpa.user_id = u.id
                WHERE fpa.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                " . ($outletId ? "AND u.outlet_id = ?" : "") . "
                GROUP BY u.id
                HAVING avg_security_score < 75 OR devtools_count > 0
                ORDER BY avg_security_score ASC, devtools_count DESC
                LIMIT 50";
        
        $params = $outletId ? [$days, $outletId] : [$days];
        return sql_query_collection_safe($sql, $params);
    }
    
    /**
     * Block user from flagged products if too suspicious
     */
    public static function shouldBlockUser(int $userId): array {
        $cheatScore = self::getUserCheatScore($userId, 7);
        
        $blocked = false;
        $reason = '';
        
        if ($cheatScore['risk_level'] === 'critical') {
            $blocked = true;
            $reason = 'Critical security violations detected. Please contact your manager.';
        }
        
        return [
            'blocked' => $blocked,
            'reason' => $reason,
            'cheat_score' => $cheatScore
        ];
    }
}
