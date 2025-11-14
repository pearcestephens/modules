<?php
/**
 * Universal Search Controller
 *
 * API endpoints for the search system that makes Gmail look like trash.
 *
 * @package StaffEmailHub\Controllers
 */

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\UniversalSearchEngine;

class UniversalSearchController
{
    private $db;
    private $logger;
    private $redis;
    private $mcpClient;
    private $searchEngine;

    public function __construct($db, $logger, $redis, $mcpClient)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->redis = $redis;
        $this->mcpClient = $mcpClient;

        $staffId = $_SESSION['staff_id'] ?? null;
        if (!$staffId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $this->searchEngine = new UniversalSearchEngine($db, $logger, $redis, $mcpClient, $staffId);
    }

    /**
     * POST /api/search
     * Main search endpoint
     */
    public function search()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $query = $data['query'] ?? $_GET['q'] ?? '';
            $context = $data['context'] ?? $_GET['context'] ?? 'all';

            $options = [
                'context' => $context,
                'limit' => $data['limit'] ?? 50,
                'offset' => $data['offset'] ?? 0,
                'has_attachments' => $data['has_attachments'] ?? false,
                'unread_only' => $data['unread_only'] ?? false,
                'folder' => $data['folder'] ?? null,
            ];

            $results = $this->searchEngine->search($query, $options);

            header('Content-Type: application/json');
            echo json_encode($results);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/search/ai
     * AI-powered "Bot Find It" mode
     */
    public function aiSearch()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $query = $data['query'] ?? $_GET['q'] ?? '';

            $results = $this->searchEngine->aiSearch($query);

            header('Content-Type: application/json');
            echo json_encode($results);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/search/suggestions
     * Get instant suggestions as user types
     */
    public function suggestions()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $query = $data['query'] ?? $_GET['q'] ?? '';

            $results = $this->searchEngine->getSuggestions($query);

            header('Content-Type: application/json');
            echo json_encode($results);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/search/all
     * Search across all contexts simultaneously
     */
    public function searchAll()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $query = $data['query'] ?? $_GET['q'] ?? '';
            $contexts = $data['contexts'] ?? [];

            $results = $this->searchEngine->searchAll($query, $contexts);

            header('Content-Type: application/json');
            echo json_encode($results);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/search/analytics
     * Get search analytics and statistics
     */
    public function analytics()
    {
        try {
            $timeframe = $_GET['timeframe'] ?? '7days';

            // Top queries
            $topQueries = $this->getTopQueries($timeframe);

            // Failed searches
            $failedSearches = $this->getFailedSearches($timeframe);

            // Performance by context
            $performance = $this->getPerformanceByContext($timeframe);

            // AI accuracy
            $aiAccuracy = $this->getAIAccuracy($timeframe);

            $analytics = [
                'success' => true,
                'timeframe' => $timeframe,
                'top_queries' => $topQueries,
                'failed_searches' => $failedSearches,
                'performance_by_context' => $performance,
                'ai_accuracy' => $aiAccuracy,
            ];

            header('Content-Type: application/json');
            echo json_encode($analytics);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/search/feedback
     * Record user feedback on search results
     */
    public function feedback()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $searchId = $data['search_id'] ?? null;
            $helpful = $data['helpful'] ?? null;
            $clickedResultId = $data['clicked_result_id'] ?? null;
            $clickedResultType = $data['clicked_result_type'] ?? null;

            if ($searchId) {
                // Update search analytics with feedback
                $stmt = $this->db->prepare("
                    UPDATE search_analytics
                    SET clicked_result_id = ?, clicked_result_type = ?
                    WHERE id = ?
                ");
                $stmt->execute([$clickedResultId, $clickedResultType, $searchId]);
            }

            // For AI searches
            if (isset($data['ai_search_id'])) {
                $stmt = $this->db->prepare("
                    UPDATE ai_search_analytics
                    SET user_feedback = ?
                    WHERE id = ?
                ");
                $feedback = $helpful ? 'helpful' : 'not_helpful';
                $stmt->execute([$feedback, $data['ai_search_id']]);
            }

            echo json_encode(['success' => true]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/search/save
     * Save a search query for quick access
     */
    public function saveQuery()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $data['name'] ?? '';
            $query = $data['query'] ?? '';
            $context = $data['context'] ?? 'all';
            $filters = $data['filters'] ?? [];

            $staffId = $_SESSION['staff_id'];

            $stmt = $this->db->prepare("
                INSERT INTO search_saved_queries
                (staff_id, name, query, context, filters)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $staffId,
                $name,
                $query,
                $context,
                json_encode($filters)
            ]);

            echo json_encode([
                'success' => true,
                'id' => $this->db->lastInsertId()
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/search/saved
     * Get user's saved searches
     */
    public function getSavedQueries()
    {
        try {
            $staffId = $_SESSION['staff_id'];

            $stmt = $this->db->prepare("
                SELECT * FROM search_saved_queries
                WHERE staff_id = ?
                ORDER BY is_favorite DESC, use_count DESC, created_at DESC
            ");
            $stmt->execute([$staffId]);
            $saved = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'saved_queries' => $saved
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    private function getTopQueries(string $timeframe): array
    {
        $days = $this->parseTimeframe($timeframe);

        $stmt = $this->db->prepare("
            SELECT query, COUNT(*) as count, AVG(response_time_ms) as avg_time
            FROM search_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY query
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getFailedSearches(string $timeframe): array
    {
        $days = $this->parseTimeframe($timeframe);

        $stmt = $this->db->prepare("
            SELECT query, context, COUNT(*) as fail_count
            FROM search_analytics
            WHERE total_results = 0
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY query, context
            ORDER BY fail_count DESC
            LIMIT 10
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPerformanceByContext(string $timeframe): array
    {
        $days = $this->parseTimeframe($timeframe);

        $stmt = $this->db->prepare("
            SELECT
                context,
                COUNT(*) as total_searches,
                AVG(response_time_ms) as avg_response_time,
                AVG(total_results) as avg_results
            FROM search_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY context
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getAIAccuracy(string $timeframe): array
    {
        $days = $this->parseTimeframe($timeframe);

        $stmt = $this->db->prepare("
            SELECT
                AVG(confidence) as avg_confidence,
                COUNT(*) as total_ai_searches,
                SUM(CASE WHEN user_feedback = 'helpful' THEN 1 ELSE 0 END) as helpful_count,
                SUM(CASE WHEN user_feedback = 'not_helpful' THEN 1 ELSE 0 END) as not_helpful_count
            FROM ai_search_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $total = $result['helpful_count'] + $result['not_helpful_count'];
        $accuracy = $total > 0 ? ($result['helpful_count'] / $total) * 100 : 0;

        return [
            'avg_confidence' => round($result['avg_confidence'], 2),
            'total_searches' => $result['total_ai_searches'],
            'accuracy_percentage' => round($accuracy, 1),
            'helpful_count' => $result['helpful_count'],
            'not_helpful_count' => $result['not_helpful_count'],
        ];
    }

    private function parseTimeframe(string $timeframe): int
    {
        switch ($timeframe) {
            case '24hours': return 1;
            case '7days': return 7;
            case '30days': return 30;
            case '90days': return 90;
            default: return 7;
        }
    }
}
