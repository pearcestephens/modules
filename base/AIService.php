<?php
/**
 * ============================================================================
 * CIS Base - AI Integration Service
 * ============================================================================
 *
 * Provides seamless integration with CIS AI Intelligent Hub
 * Connects base module to Claude and GPT-based AI services
 *
 * **Features:**
 * - Natural language queries to AI hub
 * - Semantic search across entire codebase
 * - AI-powered logging and analysis
 * - Code pattern detection
 * - Business intelligence queries
 * - Real-time AI assistance
 *
 * **AI Hub Endpoint:** https://gpt.ecigdis.co.nz/mcp/server_v2_complete.php
 *
 * **Available Tools:** 13 AI tools (semantic_search, find_code, analyze_file,
 *   get_file_content, list_satellites, sync_satellite, find_similar,
 *   explore_by_tags, get_stats, top_keywords, search_by_category,
 *   list_categories, get_analytics)
 *
 * @package CIS\Base
 * @version 1.0.0
 * @author Pearce Stephens
 * @created 2025-10-28
 */

declare(strict_types=1);

namespace CIS\Base;

class AIService
{
    /** @var string AI Intelligent Hub endpoint */
    private const HUB_URL = 'https://gpt.ecigdis.co.nz/mcp/server_v4.php';

    /** @var string API Key for authentication */
    private const API_KEY = '31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35';

    /** @var int Request timeout in seconds */
    private const TIMEOUT = 30;

    /** @var int Satellite unit ID for CIS */
    private const CIS_UNIT_ID = 2;

    /** @var array Cache for AI responses */
    private static array $cache = [];

    /** @var bool Enable caching */
    private static bool $cacheEnabled = true;

    /** @var Logger Logger instance for AI interactions */
    private static ?Logger $logger = null;

    /**
     * Initialize AI Service
     */
    public static function init(): void
    {
        if (self::$logger === null) {
            self::$logger = new Logger();
        }
    }

    /**
     * Semantic search across entire codebase
     *
     * @param string $query Natural language query
     * @param int $limit Maximum results (default: 10)
     * @param int|null $unitId Filter by satellite unit (default: CIS)
     * @return array Search results with relevance scores
     *
     * @example
     * $results = AIService::search("how do we handle customer refunds");
     * foreach ($results as $result) {
     *     echo "{$result['file']}: {$result['relevance_score']}\n";
     * }
     */
    public static function search(string $query, int $limit = 10, ?int $unitId = null): array
    {
        self::init();

        $cacheKey = "search:" . md5($query . $limit . $unitId);
        if (self::$cacheEnabled && isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $params = [
            'query' => $query,
            'limit' => $limit
        ];

        if ($unitId !== null) {
            $params['unit_id'] = $unitId;
        }

        $result = self::callTool('semantic_search', $params);

        // Log AI search interaction
        self::$logger->ai(
            'semantic_search',
            'codebase_search',
            $query,
            $result,
            ['unit_id' => $unitId, 'limit' => $limit]
        );

        if (self::$cacheEnabled && $result['success']) {
            self::$cache[$cacheKey] = $result['data'] ?? [];
        }

        return $result['data'] ?? [];
    }

    /**
     * Find specific code patterns
     *
     * @param string $pattern Code pattern to find (class, function, variable)
     * @param string $searchIn Where to search (content, keywords, tags, entities, all)
     * @param int $limit Maximum results
     * @return array Matching files with context
     *
     * @example
     * $results = AIService::findCode("Database::query", "content");
     */
    public static function findCode(string $pattern, string $searchIn = 'all', int $limit = 20): array
    {
        self::init();

        $result = self::callTool('find_code', [
            'pattern' => $pattern,
            'search_in' => $searchIn,
            'limit' => $limit,
            'unit_id' => self::CIS_UNIT_ID
        ]);

        self::$logger->ai(
            'find_code',
            'code_search',
            $pattern,
            $result,
            ['search_in' => $searchIn]
        );

        return $result['data'] ?? [];
    }

    /**
     * Analyze a specific file
     *
     * @param string $filePath Full path to file
     * @param int|null $unitId Satellite unit ID
     * @return array File analysis with metrics, keywords, entities
     *
     * @example
     * $analysis = AIService::analyzeFile("modules/base/Database.php");
     * echo "Complexity: {$analysis['complexity_score']}\n";
     * echo "Keywords: " . implode(", ", $analysis['keywords']) . "\n";
     */
    public static function analyzeFile(string $filePath, ?int $unitId = null): array
    {
        self::init();

        $result = self::callTool('analyze_file', [
            'content_path' => $filePath,
            'unit_id' => $unitId ?? self::CIS_UNIT_ID
        ]);

        self::$logger->ai(
            'analyze_file',
            'file_analysis',
            $filePath,
            $result,
            ['unit_id' => $unitId]
        );

        return $result['data'] ?? [];
    }

    /**
     * Get business categories for categorization
     *
     * @param float|null $minPriority Minimum priority weight
     * @param string $orderBy Sort order (priority, file_count, name)
     * @return array List of business categories with statistics
     *
     * @example
     * $categories = AIService::getCategories(1.3); // High priority only
     */
    public static function getCategories(?float $minPriority = null, string $orderBy = 'priority'): array
    {
        self::init();

        $params = ['order_by' => $orderBy];
        if ($minPriority !== null) {
            $params['min_priority'] = $minPriority;
        }

        $result = self::callTool('list_categories', $params);

        return $result['data'] ?? [];
    }

    /**
     * Search within specific business category
     *
     * @param string $query Search query
     * @param string $categoryName Category name (e.g., "Inventory Management")
     * @param int $limit Maximum results
     * @return array Search results within category
     *
     * @example
     * $results = AIService::searchByCategory(
     *     "stock transfer validation",
     *     "Inventory Management"
     * );
     */
    public static function searchByCategory(string $query, string $categoryName, int $limit = 20): array
    {
        self::init();

        $result = self::callTool('search_by_category', [
            'query' => $query,
            'category_name' => $categoryName,
            'unit_id' => self::CIS_UNIT_ID,
            'limit' => $limit
        ]);

        self::$logger->ai(
            'search_by_category',
            'category_search',
            $query,
            $result,
            ['category' => $categoryName]
        );

        return $result['data'] ?? [];
    }

    /**
     * Get system-wide statistics
     *
     * @param string $breakdownBy Breakdown by (unit, type, tag, readability, sentiment)
     * @return array System statistics
     *
     * @example
     * $stats = AIService::getStats('unit');
     * echo "Total files: {$stats['total_files']}\n";
     */
    public static function getStats(string $breakdownBy = 'unit'): array
    {
        self::init();

        $result = self::callTool('get_stats', [
            'breakdown_by' => $breakdownBy
        ]);

        return $result['data'] ?? [];
    }

    /**
     * Get analytics data (tool usage, performance, popular queries)
     *
     * @param string $action Report type (overview, hourly, failed, slow, popular_queries, tool_usage, category_performance)
     * @param string $timeframe Time period (1h, 6h, 24h, 7d, 30d)
     * @param int $limit Maximum results for queries
     * @return array Analytics data
     *
     * @example
     * $analytics = AIService::getAnalytics('popular_queries', '24h');
     */
    public static function getAnalytics(string $action = 'overview', string $timeframe = '24h', int $limit = 50): array
    {
        self::init();

        $result = self::callTool('get_analytics', [
            'action' => $action,
            'timeframe' => $timeframe,
            'limit' => $limit
        ]);

        return $result['data'] ?? [];
    }

    /**
     * Find similar files based on keywords and semantic tags
     *
     * @param string $referencePath Path to reference file
     * @param int $limit Maximum similar files to return
     * @return array Similar files with similarity scores
     *
     * @example
     * $similar = AIService::findSimilar("modules/base/Database.php");
     */
    public static function findSimilar(string $referencePath, int $limit = 10): array
    {
        self::init();

        $result = self::callTool('find_similar', [
            'reference_path' => $referencePath,
            'unit_id' => self::CIS_UNIT_ID,
            'limit' => $limit
        ]);

        return $result['data'] ?? [];
    }

    /**
     * Get most common keywords across system
     *
     * @param int $limit Number of keywords to return
     * @param int|null $unitId Filter by satellite unit
     * @return array Top keywords with counts
     *
     * @example
     * $keywords = AIService::getTopKeywords(50);
     */
    public static function getTopKeywords(int $limit = 50, ?int $unitId = null): array
    {
        self::init();

        $params = ['limit' => $limit];
        if ($unitId !== null) {
            $params['unit_id'] = $unitId;
        }

        $result = self::callTool('top_keywords', $params);

        return $result['data'] ?? [];
    }

    /**
     * Ask AI a natural language question about the codebase
     *
     * @param string $question Natural language question
     * @param array $context Additional context for AI
     * @return array AI response with answer and sources
     *
     * @example
     * $answer = AIService::ask("How do we validate stock transfer items?");
     * echo "Answer: {$answer['response']}\n";
     * echo "Sources: " . implode(", ", $answer['sources']) . "\n";
     */
    public static function ask(string $question, array $context = []): array
    {
        self::init();

        // Use semantic search to find relevant information
        $searchResults = self::search($question, 10);

        // Compile sources
        $sources = array_map(function($result) {
            return $result['file'] ?? 'unknown';
        }, $searchResults);

        // Log the AI interaction
        self::$logger->ai(
            'ask_question',
            'ai_assistant',
            $question,
            ['sources' => $sources, 'results_count' => count($searchResults)],
            $context
        );

        return [
            'success' => true,
            'question' => $question,
            'results' => $searchResults,
            'sources' => $sources,
            'total_results' => count($searchResults)
        ];
    }

    /**
     * Enable or disable response caching
     *
     * @param bool $enabled
     */
    public static function setCacheEnabled(bool $enabled): void
    {
        self::$cacheEnabled = $enabled;
    }

    /**
     * Clear response cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Call AI Intelligent Hub tool
     *
     * @param string $tool Tool name
     * @param array $params Tool parameters
     * @return array Tool response
     */
    private static function callTool(string $tool, array $params): array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => $tool,
                'arguments' => $params
            ],
            'id' => uniqid()
        ];

        $startTime = microtime(true);

        $ch = curl_init(self::HUB_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        // Log performance
        if (self::$logger !== null) {
            self::$logger->performance(
                'ai_hub_call',
                $tool,
                $executionTime,
                'ms',
                ['http_code' => $httpCode, 'params' => $params]
            );
        }

        if ($curlError) {
            return [
                'success' => false,
                'error' => $curlError,
                'execution_time' => $executionTime
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => "HTTP {$httpCode}",
                'execution_time' => $executionTime
            ];
        }

        $decoded = json_decode($response, true);

        if (!$decoded || isset($decoded['error'])) {
            return [
                'success' => false,
                'error' => $decoded['error'] ?? 'Invalid response',
                'execution_time' => $executionTime
            ];
        }

        return [
            'success' => true,
            'data' => $decoded['result'] ?? [],
            'execution_time' => $executionTime
        ];
    }

    /**
     * Get AI Hub health status
     *
     * @return array Health status
     */
    public static function healthCheck(): array
    {
        self::init();

        $result = self::callTool('health_check', []);

        return [
            'hub_available' => $result['success'],
            'response_time' => $result['execution_time'] ?? null,
            'error' => $result['error'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
