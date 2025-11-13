<?php
/**
 * ============================================================================
 * CIS Feed Functions Library
 * ============================================================================
 *
 * Purpose:
 *   Centralized feed data aggregation, formatting, and caching functions
 *   used across the CIS dashboard and feed system.
 *
 * Key Functions:
 *   - getRecentSystemActivity(): Fetch internal system activities
 *   - formatActivityCard(): Format activity object for display
 *   - getEngagementMetrics(): Calculate engagement scores
 *   - cacheFeedData(): Cache feed data with TTL
 *   - invalidateFeedCache(): Clear feed cache for user
 *
 * ============================================================================
 */

namespace CIS\Base;

use PDO;

class FeedFunctions {

    /**
     * Get recent system activities (staff actions, order updates, etc.)
     *
     * @param int $limit Maximum number of activities to return
     * @param int $offset Pagination offset
     * @param array $filters Optional: ['user_id', 'outlet_id', 'activity_type']
     * @return array
     */
    public static function getRecentSystemActivity($limit = 20, $offset = 0, $filters = []) {
        try {
            $db = \Services\Database::getInstance();
            $pdo = $db->getConnection();

            $query = "
                SELECT
                    a.id,
                    a.user_id,
                    a.activity_type,
                    a.entity_type,
                    a.entity_id,
                    a.title,
                    a.description,
                    a.metadata,
                    a.created_at as timestamp,
                    u.name as user_name,
                    u.profile_picture,
                    COUNT(DISTINCT al.id) as engagement
                FROM activity_log a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN activity_likes al ON a.id = al.activity_id
                WHERE a.deleted_at IS NULL
            ";

            $params = [];

            // Apply filters
            if (!empty($filters['outlet_id'])) {
                $query .= " AND a.outlet_id = ?";
                $params[] = $filters['outlet_id'];
            }

            if (!empty($filters['user_id'])) {
                $query .= " AND a.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['activity_type'])) {
                $query .= " AND a.activity_type IN (" . implode(',', array_fill(0, count($filters['activity_type']), '?')) . ")";
                $params = array_merge($params, $filters['activity_type']);
            }

            $query .= "
                GROUP BY a.id
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            $activities = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $activities[] = (object)[
                    'id' => $row['id'],
                    'feed_type' => 'internal',
                    'type' => $row['activity_type'],
                    'entity_type' => $row['entity_type'],
                    'entity_id' => $row['entity_id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'timestamp' => $row['timestamp'],
                    'user' => (object)[
                        'id' => $row['user_id'],
                        'name' => $row['user_name'],
                        'avatar' => $row['profile_picture']
                    ],
                    'engagement' => intval($row['engagement']),
                    'metadata' => json_decode($row['metadata'], true) ?? []
                ];
            }

            return $activities;

        } catch (\Exception $e) {
            Logger::error('Failed to fetch system activities', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Format activity object for display in feed card
     *
     * @param object $activity Activity object
     * @return array Formatted activity data
     */
    public static function formatActivityCard($activity) {
        $formatted = [
            'id' => $activity->id ?? uniqid(),
            'type' => $activity->type ?? 'unknown',
            'feed_type' => $activity->feed_type ?? 'internal',
            'title' => htmlspecialchars($activity->title ?? '', ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars(substr($activity->description ?? '', 0, 200), ENT_QUOTES, 'UTF-8'),
            'timestamp' => $activity->timestamp ?? date('c'),
            'time_ago' => self::timeAgo($activity->timestamp ?? 'now'),
            'engagement' => intval($activity->engagement ?? 0),
            'is_pinned' => (bool)($activity->is_pinned ?? false),
            'icon' => self::getActivityIcon($activity->type ?? 'unknown'),
            'color' => self::getActivityColor($activity->type ?? 'unknown'),
        ];

            if (isset($activity->user)) {
            $formatted['user'] = [
                'id' => $activity->user->id ?? null,
                'name' => $activity->user->name ?? 'System',
                'avatar' => $activity->user->avatar ?? null
            ];
        }

        if (isset($activity->image)) {
            $formatted['image'] = $activity->image;
        }

        if (isset($activity->url)) {
            $formatted['url'] = $activity->url;
        }

        return $formatted;
    }

    /**
     * Calculate engagement score for activity
     *
     * @param object $activity
     * @return int Engagement score (0-100)
     */
    public static function getEngagementMetrics($activity) {
        $likes = intval($activity->engagement ?? 0);
        $views = intval($activity->views ?? 0);
        $comments = intval($activity->comments ?? 0);
        $shares = intval($activity->shares ?? 0);

        // Weight formula: likes=10, views=1, comments=5, shares=15
        $score = min(100, ($likes * 10) + ($views * 1) + ($comments * 5) + ($shares * 15));

        return $score;
    }

    /**
     * Get time ago string (e.g., "5 minutes ago")
     *
     * @param string $timestamp ISO 8601 timestamp
     * @return string Human-readable time difference
     */
    public static function timeAgo($timestamp) {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;

        if ($diff < 60) return 'just now';
        if ($diff < 3600) return intval($diff / 60) . 'm ago';
        if ($diff < 86400) return intval($diff / 3600) . 'h ago';
        if ($diff < 604800) return intval($diff / 86400) . 'd ago';
        if ($diff < 2592000) return intval($diff / 604800) . 'w ago';

        return date('M d', $time);
    }

    /**
     * Get icon class for activity type
     *
     * @param string $type Activity type
     * @return string Bootstrap icon class
     */
    public static function getActivityIcon($type) {
        $icons = [
            'order_created' => 'bi bi-bag-check',
            'order_updated' => 'bi bi-arrow-repeat',
            'payment_received' => 'bi bi-cash-coin',
            'inventory_low' => 'bi bi-exclamation-triangle',
            'staff_joined' => 'bi bi-person-plus',
            'report_generated' => 'bi bi-file-text',
            'news' => 'bi bi-newspaper',
            'transfer' => 'bi bi-boxes',
            'consignment' => 'bi bi-truck',
        ];

        return $icons[$type] ?? 'bi bi-info-circle';
    }

    /**
     * Get color class for activity type
     *
     * @param string $type Activity type
     * @return string Bootstrap color class
     */
    public static function getActivityColor($type) {
        $colors = [
            'order_created' => 'success',
            'order_updated' => 'info',
            'payment_received' => 'success',
            'inventory_low' => 'warning',
            'staff_joined' => 'primary',
            'report_generated' => 'secondary',
            'news' => 'info',
            'transfer' => 'primary',
            'consignment' => 'primary',
            'error' => 'danger',
        ];

        return $colors[$type] ?? 'secondary';
    }

    /**
     * Cache feed data with TTL
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $ttl Time-to-live in seconds (default: 300)
     * @return bool Success
     */
    public static function cacheFeedData($key, $data, $ttl = 300) {
        if (function_exists('apcu_store')) {
            return apcu_store($key, $data, $ttl);
        }

        // Fallback: No caching
        return true;
    }

    /**
     * Get cached feed data
     *
     * @param string $key Cache key
     * @return mixed Cached data or false if not found
     */
    public static function getCachedFeed($key) {
        if (function_exists('apcu_fetch')) {
            return apcu_fetch($key);
        }

        return false;
    }

    /**
     * Invalidate feed cache for specific user or outlet
     *
     * @param int $userId User ID (optional)
     * @param int $outletId Outlet ID (optional)
     * @return void
     */
    public static function invalidateFeedCache($userId = null, $outletId = null) {
        if (function_exists('apcu_delete')) {
            $pattern = 'feed:';

            if ($userId) {
                $pattern .= "user:$userId:";
            }

            if ($outletId) {
                $pattern .= "outlet:$outletId:";
            }

            // Note: APCu doesn't support pattern deletion, so we need to track keys separately
            // For now, we'll just clear the user's feed
            if ($userId) {
                $keys = apcu_cache_info();
                foreach ($keys['cache_list'] as $entry) {
                    if (strpos($entry['key'], "feed:user:$userId:") === 0) {
                        apcu_delete($entry['key']);
                    }
                }
            }
        }
    }
}

// Convenience functions for backward compatibility
function getRecentSystemActivity($limit = 20, $offset = 0, $filters = []) {
    return FeedFunctions::getRecentSystemActivity($limit, $offset, $filters);
}

function formatActivityCard($activity) {
    return FeedFunctions::formatActivityCard($activity);
}

function getEngagementMetrics($activity) {
    return FeedFunctions::getEngagementMetrics($activity);
}

function invalidateFeedCache($userId = null, $outletId = null) {
    FeedFunctions::invalidateFeedCache($userId, $outletId);
}
?>
