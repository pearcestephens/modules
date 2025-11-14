<?php
/**
 * User Behavior Tracking System
 *
 * Tracks EVERYTHING a user does to build comprehensive behavior profile:
 * - Every click
 * - Every search
 * - Time spent on pages
 * - Mouse movements (heatmaps)
 * - Scroll depth
 * - Task completion patterns
 * - Workflow sequences
 * - Errors/frustrations
 *
 * This data feeds the predictive engine to make search psychic.
 *
 * @package StaffEmailHub\Services\Search
 */

namespace StaffEmailHub\Services\Search;

use Exception;

class UserBehaviorTracker
{
    private $db;
    private $logger;
    private $staffId;
    private $redis;

    // Event types
    const EVENT_PAGE_VIEW = 'page_view';
    const EVENT_SEARCH = 'search';
    const EVENT_CLICK = 'click';
    const EVENT_SCROLL = 'scroll';
    const EVENT_HOVER = 'hover';
    const EVENT_FORM_SUBMIT = 'form_submit';
    const EVENT_ERROR = 'error';
    const EVENT_TASK_COMPLETE = 'task_complete';

    public function __construct($db, $logger, $staffId, $redis = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $this->redis = $redis;
    }

    /**
     * Track a user event (generic)
     */
    public function trackEvent(string $eventType, array $data): void
    {
        try {
            // Store in database
            $stmt = $this->db->prepare("
                INSERT INTO user_behavior_events (
                    staff_id, event_type, page_url, event_data,
                    session_id, timestamp
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->staffId,
                $eventType,
                $data['page_url'] ?? $_SERVER['REQUEST_URI'] ?? '',
                json_encode($data),
                $data['session_id'] ?? session_id()
            ]);

            // Also cache recent events in Redis for fast access
            if ($this->redis) {
                $key = "user_behavior:{$this->staffId}:recent";
                $this->redis->lpush($key, json_encode([
                    'type' => $eventType,
                    'data' => $data,
                    'timestamp' => time()
                ]));
                $this->redis->ltrim($key, 0, 99); // Keep last 100 events
                $this->redis->expire($key, 86400); // 24 hours
            }

            // Update real-time user profile
            $this->updateUserProfile($eventType, $data);

        } catch (Exception $e) {
            $this->logger->error("Failed to track event: " . $e->getMessage());
        }
    }

    /**
     * Track page view with full context
     */
    public function trackPageView(array $data): void
    {
        $this->trackEvent(self::EVENT_PAGE_VIEW, array_merge($data, [
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'viewport_width' => $data['viewport_width'] ?? 0,
            'viewport_height' => $data['viewport_height'] ?? 0
        ]));
    }

    /**
     * Track search behavior
     */
    public function trackSearch(array $data): void
    {
        $this->trackEvent(self::EVENT_SEARCH, [
            'query' => $data['query'],
            'context' => $data['context'] ?? 'all',
            'filters' => $data['filters'] ?? [],
            'results_count' => $data['results_count'] ?? 0,
            'result_clicked' => $data['result_clicked'] ?? false,
            'result_id' => $data['result_id'] ?? null,
            'time_to_click' => $data['time_to_click'] ?? null,
            'query_length' => strlen($data['query']),
            'has_filters' => !empty($data['filters'])
        ]);
    }

    /**
     * Track click with element details
     */
    public function trackClick(array $data): void
    {
        $this->trackEvent(self::EVENT_CLICK, [
            'element_id' => $data['element_id'] ?? '',
            'element_class' => $data['element_class'] ?? '',
            'element_text' => $data['element_text'] ?? '',
            'x_position' => $data['x'] ?? 0,
            'y_position' => $data['y'] ?? 0,
            'timestamp_ms' => microtime(true)
        ]);
    }

    /**
     * Track task completion (important for workflow learning)
     */
    public function trackTaskComplete(array $data): void
    {
        $this->trackEvent(self::EVENT_TASK_COMPLETE, [
            'task_type' => $data['task_type'],
            'task_id' => $data['task_id'] ?? null,
            'time_spent' => $data['time_spent'] ?? 0,
            'steps_taken' => $data['steps'] ?? 0,
            'successful' => $data['successful'] ?? true
        ]);
    }

    /**
     * Get user's behavior profile (for predictions)
     */
    public function getUserProfile(): array
    {
        try {
            // Try Redis cache first
            if ($this->redis) {
                $cached = $this->redis->get("user_profile:{$this->staffId}");
                if ($cached) {
                    return json_decode($cached, true);
                }
            }

            // Build profile from database
            $profile = [
                'staff_id' => $this->staffId,
                'most_searched_contexts' => $this->getMostSearchedContexts(),
                'common_workflows' => $this->getCommonWorkflows(),
                'time_patterns' => $this->getTimePatterns(),
                'favorite_features' => $this->getFavoriteFeatures(),
                'search_effectiveness' => $this->getSearchEffectiveness(),
                'avg_session_duration' => $this->getAvgSessionDuration(),
                'last_active' => $this->getLastActive()
            ];

            // Cache for 1 hour
            if ($this->redis) {
                $this->redis->setex("user_profile:{$this->staffId}", 3600, json_encode($profile));
            }

            return $profile;

        } catch (Exception $e) {
            $this->logger->error("Failed to get user profile: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get real-time context (what user is doing RIGHT NOW)
     */
    public function getCurrentContext(): array
    {
        try {
            if (!$this->redis) {
                return [];
            }

            // Get last 10 events
            $recentEvents = $this->redis->lrange("user_behavior:{$this->staffId}:recent", 0, 9);

            if (empty($recentEvents)) {
                return [];
            }

            $events = array_map(function($e) {
                return json_decode($e, true);
            }, $recentEvents);

            // Analyze recent activity
            $context = [
                'current_page' => $events[0]['data']['page_url'] ?? 'unknown',
                'last_search' => $this->getLastSearch($events),
                'last_click' => $this->getLastClick($events),
                'active_tasks' => $this->getActiveTasks($events),
                'time_on_page' => $this->getTimeOnPage($events),
                'scroll_depth' => $this->getScrollDepth($events),
                'engagement_level' => $this->calculateEngagement($events)
            ];

            return $context;

        } catch (Exception $e) {
            $this->logger->error("Failed to get current context: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update user profile based on new event
     */
    private function updateUserProfile(string $eventType, array $data): void
    {
        // Invalidate cached profile (will be regenerated on next request)
        if ($this->redis) {
            $this->redis->del("user_profile:{$this->staffId}");
        }
    }

    // Profile building methods

    private function getMostSearchedContexts(): array
    {
        $stmt = $this->db->prepare("
            SELECT search_context, COUNT(*) as count
            FROM search_analytics
            WHERE staff_id = ?
            AND searched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY search_context
            ORDER BY count DESC
            LIMIT 5
        ");
        $stmt->execute([$this->staffId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getCommonWorkflows(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                current_action,
                next_search_query,
                COUNT(*) as frequency
            FROM search_workflow_patterns
            WHERE staff_id = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 60 DAY)
            GROUP BY current_action, next_search_query
            HAVING frequency >= 3
            ORDER BY frequency DESC
            LIMIT 10
        ");
        $stmt->execute([$this->staffId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getTimePatterns(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                HOUR(timestamp) as hour,
                event_type,
                COUNT(*) as count
            FROM user_behavior_events
            WHERE staff_id = ?
            AND timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY hour, event_type
            ORDER BY hour
        ");
        $stmt->execute([$this->staffId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getFavoriteFeatures(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                JSON_EXTRACT(event_data, '$.feature') as feature,
                COUNT(*) as usage_count
            FROM user_behavior_events
            WHERE staff_id = ?
            AND event_type = 'click'
            AND timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY feature
            ORDER BY usage_count DESC
            LIMIT 10
        ");
        $stmt->execute([$this->staffId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getSearchEffectiveness(): float
    {
        $stmt = $this->db->prepare("
            SELECT AVG(result_clicked) as effectiveness
            FROM search_analytics
            WHERE staff_id = ?
            AND searched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$this->staffId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (float)($result['effectiveness'] ?? 0);
    }

    private function getAvgSessionDuration(): int
    {
        $stmt = $this->db->prepare("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, MIN(timestamp), MAX(timestamp))) as avg_duration
            FROM user_behavior_events
            WHERE staff_id = ?
            AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY session_id
        ");
        $stmt->execute([$this->staffId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($result['avg_duration'] ?? 0);
    }

    private function getLastActive(): string
    {
        $stmt = $this->db->prepare("
            SELECT MAX(timestamp) as last_active
            FROM user_behavior_events
            WHERE staff_id = ?
        ");
        $stmt->execute([$this->staffId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['last_active'] ?? '';
    }

    // Context analysis methods

    private function getLastSearch(array $events): ?array
    {
        foreach ($events as $event) {
            if ($event['type'] === self::EVENT_SEARCH) {
                return $event['data'];
            }
        }
        return null;
    }

    private function getLastClick(array $events): ?array
    {
        foreach ($events as $event) {
            if ($event['type'] === self::EVENT_CLICK) {
                return $event['data'];
            }
        }
        return null;
    }

    private function getActiveTasks(array $events): array
    {
        $tasks = [];
        foreach ($events as $event) {
            if (isset($event['data']['task_type']) && $event['type'] !== self::EVENT_TASK_COMPLETE) {
                $tasks[] = $event['data']['task_type'];
            }
        }
        return array_unique($tasks);
    }

    private function getTimeOnPage(array $events): int
    {
        if (empty($events)) return 0;
        $first = end($events);
        $last = $events[0];
        return $last['timestamp'] - $first['timestamp'];
    }

    private function getScrollDepth(array $events): int
    {
        $maxScroll = 0;
        foreach ($events as $event) {
            if ($event['type'] === self::EVENT_SCROLL) {
                $maxScroll = max($maxScroll, $event['data']['scroll_percentage'] ?? 0);
            }
        }
        return $maxScroll;
    }

    private function calculateEngagement(array $events): string
    {
        // Simple engagement calculation based on event frequency
        $eventCount = count($events);
        $timeSpan = $this->getTimeOnPage($events);

        if ($timeSpan == 0) return 'low';

        $eventsPerMinute = ($eventCount / $timeSpan) * 60;

        if ($eventsPerMinute > 10) return 'high';
        if ($eventsPerMinute > 5) return 'medium';
        return 'low';
    }
}
