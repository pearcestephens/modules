<?php
/**
 * Predictive Search Intelligence Engine
 *
 * "SEARCH JUST KNOWS WHAT I'M THINKING BEFORE I SEARCH IT"
 *
 * This is the AI brain that makes search feel psychic. It:
 * - Learns user behavior patterns
 * - Predicts what you're looking for based on context
 * - Surfaces results BEFORE you even finish typing
 * - Knows what you need based on your current workflow
 * - Adapts to time of day, day of week, role, department
 * - Gets smarter the more you use it
 *
 * Features:
 * - Context-aware predictions (knows what page you're on)
 * - Workflow pattern recognition (morning routine, end-of-day tasks)
 * - Proactive suggestions ("You might be looking for...")
 * - Intent prediction from first 2-3 characters
 * - Personalized ranking based on your history
 * - Team behavior learning (what do similar users search for?)
 *
 * @package StaffEmailHub\Services\Search
 */

namespace StaffEmailHub\Services\Search;

use Exception;

class PredictiveSearchEngine
{
    private $db;
    private $logger;
    private $staffId;
    private $mcpClient;

    // Prediction confidence thresholds
    const CONFIDENCE_HIGH = 0.8;     // 80%+ confidence - show proactively
    const CONFIDENCE_MEDIUM = 0.6;   // 60-80% - show as suggestions
    const CONFIDENCE_LOW = 0.4;      // 40-60% - background preload

    // Context detection
    const CONTEXT_EMAIL = 'email';
    const CONTEXT_PRODUCT = 'product';
    const CONTEXT_ORDER = 'order';
    const CONTEXT_CUSTOMER = 'customer';
    const CONTEXT_REPORT = 'report';

    // Time-based patterns
    const TIME_MORNING = 'morning';      // 6am-11am
    const TIME_MIDDAY = 'midday';        // 11am-2pm
    const TIME_AFTERNOON = 'afternoon';  // 2pm-5pm
    const TIME_EVENING = 'evening';      // 5pm-9pm

    public function __construct($db, $logger, $staffId, $mcpClient = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $this->mcpClient = $mcpClient;
    }

    /**
     * Predict what user is looking for based on current context
     * This runs BEFORE they even start typing
     *
     * @param array $context Current page, recent actions, time, etc.
     * @return array Predicted searches with confidence scores
     */
    public function predictIntent(array $context = []): array
    {
        try {
            $this->logger->info("Predicting search intent for staff {$this->staffId}", $context);

            $predictions = [];

            // 1. Context-based predictions (what page are they on?)
            $contextPredictions = $this->predictFromContext($context);
            $predictions = array_merge($predictions, $contextPredictions);

            // 2. Workflow pattern predictions (what do they usually do next?)
            $workflowPredictions = $this->predictFromWorkflowPattern($context);
            $predictions = array_merge($predictions, $workflowPredictions);

            // 3. Time-based predictions (morning routine vs end-of-day)
            $timePredictions = $this->predictFromTimePattern();
            $predictions = array_merge($predictions, $timePredictions);

            // 4. Recency predictions (recently viewed/searched items)
            $recencyPredictions = $this->predictFromRecency();
            $predictions = array_merge($predictions, $recencyPredictions);

            // 5. Team behavior predictions (what do similar users search?)
            $teamPredictions = $this->predictFromTeamBehavior($context);
            $predictions = array_merge($predictions, $teamPredictions);

            // 6. Unfinished task predictions (things they started but didn't complete)
            $unfinishedPredictions = $this->predictUnfinishedTasks();
            $predictions = array_merge($predictions, $unfinishedPredictions);

            // Aggregate and rank predictions
            $rankedPredictions = $this->aggregateAndRankPredictions($predictions);

            // Only return high-confidence predictions
            return array_filter($rankedPredictions, function($pred) {
                return $pred['confidence'] >= self::CONFIDENCE_MEDIUM;
            });

        } catch (Exception $e) {
            $this->logger->error("Prediction error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Predict intent from the first few characters typed
     * After 2-3 characters, we already know what they want
     *
     * @param string $partialQuery First 2-5 characters typed
     * @param array $context Current context
     * @return array Predictions with confidence scores
     */
    public function predictFromPartialQuery(string $partialQuery, array $context = []): array
    {
        try {
            if (strlen($partialQuery) < 2) {
                return []; // Need at least 2 characters
            }

            $predictions = [];

            // 1. Check user's search history for patterns
            $historicalPredictions = $this->predictFromSearchHistory($partialQuery);

            // 2. Check global popular searches starting with these characters
            $popularPredictions = $this->predictFromPopularSearches($partialQuery);

            // 3. Smart pattern detection (email addresses, order IDs, SKUs)
            $patternPredictions = $this->detectAndPredictPattern($partialQuery);

            // 4. MCP AI prediction (if available)
            if ($this->mcpClient) {
                $aiPredictions = $this->aiPredictIntent($partialQuery, $context);
                $predictions = array_merge($predictions, $aiPredictions);
            }

            // Combine and rank
            $allPredictions = array_merge(
                $historicalPredictions,
                $popularPredictions,
                $patternPredictions,
                $predictions
            );

            return $this->aggregateAndRankPredictions($allPredictions);

        } catch (Exception $e) {
            $this->logger->error("Partial query prediction error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Predict based on what page user is currently on
     * Email page → predict email searches
     * Product page → predict product searches
     */
    private function predictFromContext(array $context): array
    {
        $predictions = [];
        $currentPage = $context['page'] ?? 'unknown';
        $currentModule = $context['module'] ?? 'unknown';

        try {
            // Query: What do users typically search for on this page?
            $stmt = $this->db->prepare("
                SELECT
                    search_query,
                    search_context,
                    COUNT(*) as frequency,
                    AVG(result_clicked) as click_rate
                FROM search_analytics
                WHERE staff_id = ?
                AND page_context = ?
                AND searched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY search_query, search_context
                HAVING frequency >= 2
                ORDER BY frequency DESC, click_rate DESC
                LIMIT 5
            ");
            $stmt->execute([$this->staffId, $currentPage]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $predictions[] = [
                    'type' => 'context',
                    'query' => $row['search_query'],
                    'context' => $row['search_context'],
                    'confidence' => min(0.9, 0.5 + ($row['frequency'] * 0.1)),
                    'reason' => "You often search this on the {$currentPage} page",
                    'action' => 'preload_results'
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("Context prediction error: " . $e->getMessage());
        }

        return $predictions;
    }

    /**
     * Predict based on user's typical workflow pattern
     * E.g., "Every morning, they check emails, then orders, then inventory"
     */
    private function predictFromWorkflowPattern(array $context): array
    {
        $predictions = [];
        $currentAction = $context['last_action'] ?? null;

        try {
            // Find common action sequences (Markov chain)
            $stmt = $this->db->prepare("
                SELECT
                    next_search_query,
                    next_search_context,
                    COUNT(*) as frequency,
                    AVG(time_to_next_search) as avg_time
                FROM search_workflow_patterns
                WHERE staff_id = ?
                AND current_action = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 60 DAY)
                GROUP BY next_search_query, next_search_context
                HAVING frequency >= 3
                ORDER BY frequency DESC
                LIMIT 3
            ");
            $stmt->execute([$this->staffId, $currentAction]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $predictions[] = [
                    'type' => 'workflow',
                    'query' => $row['next_search_query'],
                    'context' => $row['next_search_context'],
                    'confidence' => min(0.85, 0.4 + ($row['frequency'] * 0.15)),
                    'reason' => "You typically do this after {$currentAction}",
                    'action' => 'suggest_proactively'
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("Workflow prediction error: " . $e->getMessage());
        }

        return $predictions;
    }

    /**
     * Predict based on time of day and day of week
     * Morning: check emails, afternoon: process orders, etc.
     */
    private function predictFromTimePattern(): array
    {
        $predictions = [];
        $hour = (int)date('H');
        $dayOfWeek = date('w'); // 0=Sunday, 6=Saturday

        $timeOfDay = $this->getTimeOfDay($hour);

        try {
            // What do they usually search at this time?
            $stmt = $this->db->prepare("
                SELECT
                    search_query,
                    search_context,
                    COUNT(*) as frequency,
                    AVG(result_clicked) as effectiveness
                FROM search_analytics
                WHERE staff_id = ?
                AND HOUR(searched_at) BETWEEN ? AND ?
                AND DAYOFWEEK(searched_at) = ?
                AND searched_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY search_query, search_context
                HAVING frequency >= 2
                ORDER BY frequency DESC, effectiveness DESC
                LIMIT 3
            ");

            $hourRange = $this->getHourRange($hour);
            $stmt->execute([
                $this->staffId,
                $hourRange['start'],
                $hourRange['end'],
                $dayOfWeek
            ]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $predictions[] = [
                    'type' => 'time_pattern',
                    'query' => $row['search_query'],
                    'context' => $row['search_context'],
                    'confidence' => min(0.75, 0.3 + ($row['frequency'] * 0.15)),
                    'reason' => "You usually search this {$timeOfDay}",
                    'action' => 'background_preload'
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("Time pattern prediction error: " . $e->getMessage());
        }

        return $predictions;
    }

    /**
     * Predict based on recently viewed/searched items
     * Recency bias - things you looked at recently are likely to be searched again
     */
    private function predictFromRecency(): array
    {
        $predictions = [];

        try {
            // Recent searches (last 24 hours)
            $stmt = $this->db->prepare("
                SELECT
                    search_query,
                    search_context,
                    searched_at,
                    result_clicked
                FROM search_analytics
                WHERE staff_id = ?
                AND searched_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND result_clicked = 1
                ORDER BY searched_at DESC
                LIMIT 5
            ");
            $stmt->execute([$this->staffId]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $hoursAgo = (time() - strtotime($row['searched_at'])) / 3600;
                $recencyScore = max(0.3, 1 - ($hoursAgo / 24)); // Decay over 24 hours

                $predictions[] = [
                    'type' => 'recency',
                    'query' => $row['search_query'],
                    'context' => $row['search_context'],
                    'confidence' => $recencyScore * 0.7, // Max 70% confidence
                    'reason' => "You searched this " . round($hoursAgo, 1) . " hours ago",
                    'action' => 'suggest_in_dropdown'
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("Recency prediction error: " . $e->getMessage());
        }

        return $predictions;
    }

    /**
     * Predict based on what similar team members search for
     * "People like you often search for..."
     */
    private function predictFromTeamBehavior(array $context): array
    {
        $predictions = [];

        try {
            // Find users with similar role/department
            $stmt = $this->db->prepare("
                SELECT
                    sa.search_query,
                    sa.search_context,
                    COUNT(DISTINCT sa.staff_id) as user_count,
                    AVG(sa.result_clicked) as effectiveness
                FROM search_analytics sa
                INNER JOIN staff s ON sa.staff_id = s.id
                INNER JOIN staff my_profile ON my_profile.id = ?
                WHERE s.role = my_profile.role
                AND s.department = my_profile.department
                AND s.id != ?
                AND sa.searched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY sa.search_query, sa.search_context
                HAVING user_count >= 3 AND effectiveness > 0.5
                ORDER BY user_count DESC, effectiveness DESC
                LIMIT 3
            ");
            $stmt->execute([$this->staffId, $this->staffId]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $predictions[] = [
                    'type' => 'team_behavior',
                    'query' => $row['search_query'],
                    'context' => $row['search_context'],
                    'confidence' => min(0.65, 0.3 + ($row['user_count'] * 0.1)),
                    'reason' => "{$row['user_count']} team members searched this recently",
                    'action' => 'suggest_in_dropdown'
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("Team behavior prediction error: " . $e->getMessage());
        }

        return $predictions;
    }

    /**
     * Predict unfinished tasks
     * E.g., searched for an order but didn't update it
     */
    private function predictUnfinishedTasks(): array
    {
        $predictions = [];

        try {
            // Find searches that didn't result in meaningful action
            $stmt = $this->db->prepare("
                SELECT
                    sa.search_query,
                    sa.search_context,
                    sa.result_id,
                    sa.searched_at
                FROM search_analytics sa
                LEFT JOIN user_actions ua ON ua.staff_id = sa.staff_id
                    AND ua.entity_type = sa.search_context
                    AND ua.entity_id = sa.result_id
                    AND ua.created_at > sa.searched_at
                WHERE sa.staff_id = ?
                AND sa.result_clicked = 1
                AND ua.id IS NULL  -- No follow-up action
                AND sa.searched_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
                ORDER BY sa.searched_at DESC
                LIMIT 3
            ");
            $stmt->execute([$this->staffId]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $predictions[] = [
                    'type' => 'unfinished_task',
                    'query' => $row['search_query'],
                    'context' => $row['search_context'],
                    'confidence' => 0.75,
                    'reason' => "You viewed this but didn't complete an action",
                    'action' => 'suggest_proactively',
                    'highlight' => true
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("Unfinished task prediction error: " . $e->getMessage());
        }

        return $predictions;
    }

    /**
     * Predict from user's search history with partial match
     */
    private function predictFromSearchHistory(string $partialQuery): array
    {
        $predictions = [];

        try {
            $stmt = $this->db->prepare("
                SELECT
                    search_query,
                    search_context,
                    COUNT(*) as frequency,
                    MAX(searched_at) as last_searched,
                    AVG(result_clicked) as effectiveness
                FROM search_analytics
                WHERE staff_id = ?
                AND search_query LIKE ?
                AND searched_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY search_query, search_context
                ORDER BY frequency DESC, last_searched DESC
                LIMIT 5
            ");
            $stmt->execute([$this->staffId, $partialQuery . '%']);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $predictions[] = [
                    'type' => 'history',
                    'query' => $row['search_query'],
                    'context' => $row['search_context'],
                    'confidence' => min(0.95, 0.6 + ($row['frequency'] * 0.1)),
                    'reason' => "You've searched this {$row['frequency']} times before",
                    'action' => 'autocomplete'
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("History prediction error: " . $e->getMessage());
        }

        return $predictions;
    }

    /**
     * Predict from popular searches across all users
     */
    private function predictFromPopularSearches(string $partialQuery): array
    {
        $predictions = [];

        try {
            $stmt = $this->db->prepare("
                SELECT
                    search_query,
                    search_context,
                    COUNT(DISTINCT staff_id) as user_count,
                    AVG(result_clicked) as effectiveness
                FROM search_analytics
                WHERE search_query LIKE ?
                AND searched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY search_query, search_context
                HAVING user_count >= 5
                ORDER BY user_count DESC, effectiveness DESC
                LIMIT 3
            ");
            $stmt->execute([$partialQuery . '%']);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $predictions[] = [
                    'type' => 'popular',
                    'query' => $row['search_query'],
                    'context' => $row['search_context'],
                    'confidence' => min(0.7, 0.4 + ($row['user_count'] * 0.05)),
                    'reason' => "{$row['user_count']} people have searched this",
                    'action' => 'suggest_in_dropdown'
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("Popular search prediction error: " . $e->getMessage());
        }

        return $predictions;
    }

    /**
     * Detect patterns in partial query (email, order ID, SKU, etc.)
     */
    private function detectAndPredictPattern(string $partialQuery): array
    {
        $predictions = [];

        // Email address pattern
        if (strpos($partialQuery, '@') !== false || filter_var($partialQuery . '@test.com', FILTER_VALIDATE_EMAIL)) {
            $predictions[] = [
                'type' => 'pattern',
                'query' => $partialQuery,
                'context' => 'email',
                'confidence' => 0.9,
                'reason' => "Looks like an email address",
                'action' => 'switch_to_email_search'
            ];
        }

        // Order ID pattern (#12345 or ORD-12345)
        if (preg_match('/^(#|ORD-?)\d+/i', $partialQuery)) {
            $predictions[] = [
                'type' => 'pattern',
                'query' => $partialQuery,
                'context' => 'order',
                'confidence' => 0.95,
                'reason' => "Looks like an order ID",
                'action' => 'switch_to_order_search'
            ];
        }

        // SKU pattern (letters + numbers)
        if (preg_match('/^[A-Z]{2,4}-?\d{3,}/i', $partialQuery)) {
            $predictions[] = [
                'type' => 'pattern',
                'query' => $partialQuery,
                'context' => 'product',
                'confidence' => 0.85,
                'reason' => "Looks like a product SKU",
                'action' => 'switch_to_product_search'
            ];
        }

        // Phone number pattern
        if (preg_match('/^[\d\s\-\(\)]{7,}/', $partialQuery)) {
            $predictions[] = [
                'type' => 'pattern',
                'query' => $partialQuery,
                'context' => 'customer',
                'confidence' => 0.8,
                'reason' => "Looks like a phone number",
                'action' => 'switch_to_customer_search'
            ];
        }

        return $predictions;
    }

    /**
     * Use MCP AI to predict intent from partial query
     */
    private function aiPredictIntent(string $partialQuery, array $context): array
    {
        try {
            $prompt = "Given a user typing '{$partialQuery}' in our search, predict what they're looking for. Context: " . json_encode($context);

            // Call MCP AI service
            $aiResponse = $this->mcpClient->call('ai-generate', [
                'prompt' => $prompt,
                'max_tokens' => 100,
                'temperature' => 0.3
            ]);

            // Parse AI response and return predictions
            // (Implementation depends on MCP response format)

            return []; // Placeholder

        } catch (Exception $e) {
            $this->logger->error("AI prediction error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Aggregate and rank predictions from multiple sources
     */
    private function aggregateAndRankPredictions(array $predictions): array
    {
        // Group by query
        $grouped = [];
        foreach ($predictions as $pred) {
            $key = $pred['query'] . '|' . $pred['context'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = $pred;
                $grouped[$key]['sources'] = [$pred['type']];
            } else {
                // Boost confidence if multiple sources predict same thing
                $grouped[$key]['confidence'] = min(0.99, $grouped[$key]['confidence'] + 0.1);
                $grouped[$key]['sources'][] = $pred['type'];
            }
        }

        // Sort by confidence
        usort($grouped, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return array_values($grouped);
    }

    /**
     * Record user's actual search behavior for learning
     */
    public function recordSearchBehavior(array $data): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO search_analytics (
                    staff_id, search_query, search_context, page_context,
                    result_clicked, result_id, searched_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->staffId,
                $data['query'] ?? '',
                $data['context'] ?? 'all',
                $data['page'] ?? '',
                $data['clicked'] ?? 0,
                $data['result_id'] ?? null
            ]);

            // Update workflow patterns
            $this->updateWorkflowPattern($data);

        } catch (Exception $e) {
            $this->logger->error("Failed to record search behavior: " . $e->getMessage());
        }
    }

    /**
     * Update workflow pattern (what action follows what)
     */
    private function updateWorkflowPattern(array $data): void
    {
        try {
            // Get last action
            $lastAction = $data['last_action'] ?? null;
            if (!$lastAction) return;

            $stmt = $this->db->prepare("
                INSERT INTO search_workflow_patterns (
                    staff_id, current_action, next_search_query,
                    next_search_context, time_to_next_search, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->staffId,
                $lastAction,
                $data['query'] ?? '',
                $data['context'] ?? 'all',
                $data['time_since_last'] ?? 0
            ]);

        } catch (Exception $e) {
            $this->logger->error("Failed to update workflow pattern: " . $e->getMessage());
        }
    }

    // Helper methods

    private function getTimeOfDay(int $hour): string
    {
        if ($hour >= 6 && $hour < 11) return self::TIME_MORNING;
        if ($hour >= 11 && $hour < 14) return self::TIME_MIDDAY;
        if ($hour >= 14 && $hour < 17) return self::TIME_AFTERNOON;
        return self::TIME_EVENING;
    }

    private function getHourRange(int $hour): array
    {
        $timeOfDay = $this->getTimeOfDay($hour);
        switch ($timeOfDay) {
            case self::TIME_MORNING:   return ['start' => 6, 'end' => 11];
            case self::TIME_MIDDAY:    return ['start' => 11, 'end' => 14];
            case self::TIME_AFTERNOON: return ['start' => 14, 'end' => 17];
            default:                   return ['start' => 17, 'end' => 21];
        }
    }
}
