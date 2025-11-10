<?php
declare(strict_types=1);

namespace CIS\Consignments\Services;

use Exception;

/**
 * Intelligence Hub Adapter
 *
 * Connects the Consignments module to the Intelligence Hub AI Agent Platform
 * at gpt.ecigdis.co.nz.
 *
 * Features:
 * - Chat interface with AI Agent
 * - MCP Server tool integration (semantic search, file analysis, etc.)
 * - Workflow orchestration
 * - Frontend automation tools
 * - Approval system integration
 *
 * Intelligence Hub Components:
 * - AI Agent Platform: ToolChainOrchestrator, AIOrchestrator (RAG)
 * - MCP Server: 13 tools, 22,185 files indexed, semantic search
 * - Frontend Tools: Playwright, Puppeteer, GPT Vision
 * - Visual Workflow Builder
 * - Approval System for code changes
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author CIS Development Team
 */
class IntelligenceHubAdapter
{
    private array $config;
    private int $timeout;

    /**
     * Intelligence Hub endpoints
     */
    private const ENDPOINTS = [
        'chat' => 'https://gpt.ecigdis.co.nz/ai-agent/api/chat.php',
        'mcp' => 'https://gpt.ecigdis.co.nz/mcp/server_v4.php',
        'workflow' => 'https://gpt.ecigdis.co.nz/ai-agent/api/execute-workflow.php',
        'approval' => 'https://gpt.ecigdis.co.nz/assets/services/ai-agent/api/approve-fix.php',
        'frontend_audit' => 'https://gpt.ecigdis.co.nz/assets/services/ai-agent/api/frontend-audit.php',
    ];

    /**
     * API Key for authentication
     */
    private const API_KEY = '31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35';

    /**
     * MCP tools available (13 total)
     */
    private const MCP_TOOLS = [
        'semantic_search',
        'search_by_category',
        'find_code',
        'find_similar',
        'explore_by_tags',
        'analyze_file',
        'get_file_content',
        'health_check',
        'get_stats',
        'top_keywords',
        'list_categories',
        'get_analytics',
        'list_satellites',
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_key' => $_ENV['INTELLIGENCE_HUB_API_KEY'] ?? null,
            'timeout' => (int)($_ENV['AI_AGENT_TIMEOUT'] ?? 30),
            'chat_endpoint' => $_ENV['INTELLIGENCE_HUB_CHAT_ENDPOINT'] ?? self::ENDPOINTS['chat'],
            'mcp_endpoint' => self::ENDPOINTS['mcp'],
            'enable_mcp' => true,
            'enable_workflows' => true,
            'enable_frontend_tools' => true,
        ], $config);

        $this->timeout = $this->config['timeout'];
    }

    /**
     * Chat with Intelligence Hub AI Agent
     *
     * @param string $message User's question or request
     * @param array $context Additional context (transfer_id, user_id, etc.)
     * @return array Response with message, confidence, metadata
     * @throws Exception If request fails
     */
    public function chat(string $message, array $context = []): array
    {
        $endpoint = $this->config['chat_endpoint'];

        // TODO: Adjust request format based on your actual Intelligence Hub API
        $payload = [
            'message' => $message,
            'context' => $context,
            'model' => 'gpt-4o', // Intelligence Hub will route to appropriate model
        ];

        // Add API key if configured
        if (!empty($this->config['api_key'])) {
            $payload['api_key'] = $this->config['api_key'];
        }

        try {
            $response = $this->makeRequest($endpoint, $payload);

            // TODO: Adjust response parsing based on your actual Intelligence Hub response format
            return $this->parseResponse($response);

        } catch (Exception $e) {
            error_log('[IntelligenceHubAdapter] Chat error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Use MCP Server tools for semantic search and file analysis
     *
     * @param string $tool Tool name (semantic_search, analyze_file, etc.)
     * @param array $arguments Tool-specific arguments
     * @return array Tool result
     * @throws Exception If tool not found or request fails
     */
    public function useMCPTool(string $tool, array $arguments): array
    {
        if (!in_array($tool, self::MCP_TOOLS)) {
            throw new Exception("Unknown MCP tool: {$tool}");
        }

        if (!$this->config['enable_mcp']) {
            throw new Exception('MCP integration is disabled');
        }

        $endpoint = $this->config['mcp_endpoint'];

        // MCP Server request format (from MASTER_SYSTEM_GUIDE.md)
        $payload = [
            'method' => 'tools/call',
            'params' => [
                'name' => $tool,
                'arguments' => $arguments,
            ],
        ];

        try {
            $response = $this->makeRequest($endpoint, $payload);
            return $response;

        } catch (Exception $e) {
            error_log('[IntelligenceHubAdapter] MCP tool error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Semantic search across 22,185 files in Intelligence Hub
     *
     * @param string $query Natural language search query
     * @param int $limit Number of results (default 10)
     * @return array Search results with file paths and relevance scores
     */
    public function semanticSearch(string $query, int $limit = 10): array
    {
        return $this->useMCPTool('semantic_search', [
            'query' => $query,
            'limit' => $limit,
        ]);
    }

    /**
     * Search by business category
     *
     * Available categories (31 total): Inventory Management, Transfers,
     * Purchase Orders, Financial Systems, HR & Payroll, etc.
     *
     * @param string $query Search query
     * @param string $category Category name
     * @param int $limit Number of results
     * @return array Search results
     */
    public function searchByCategory(string $query, string $category, int $limit = 20): array
    {
        return $this->useMCPTool('search_by_category', [
            'query' => $query,
            'category_name' => $category,
            'limit' => $limit,
        ]);
    }

    /**
     * Find code patterns (functions, classes, methods)
     *
     * @param string $pattern Code pattern to find
     * @param string $type Type: 'function', 'class', 'method', 'pattern'
     * @return array Matching code locations
     */
    public function findCode(string $pattern, string $type = 'pattern'): array
    {
        return $this->useMCPTool('find_code', [
            'pattern' => $pattern,
            'type' => $type,
        ]);
    }

    /**
     * Analyze a file with Intelligence Hub
     *
     * @param string $filePath Relative path from root
     * @return array File analysis with metrics, dependencies, etc.
     */
    public function analyzeFile(string $filePath): array
    {
        return $this->useMCPTool('analyze_file', [
            'file_path' => $filePath,
        ]);
    }

    /**
     * Get Intelligence Hub statistics
     *
     * @return array Stats about indexed files, categories, etc.
     */
    public function getStats(): array
    {
        return $this->useMCPTool('get_stats', []);
    }

    /**
     * Execute a workflow in Intelligence Hub
     *
     * Workflows can include:
     * - Frontend page audit
     * - Auto-fix pipeline with approvals
     * - 24/7 monitoring
     * - Custom multi-step workflows
     *
     * @param string $workflowName Workflow name or ID
     * @param array $params Workflow parameters
     * @return array Workflow execution result
     */
    public function executeWorkflow(string $workflowName, array $params = []): array
    {
        if (!$this->config['enable_workflows']) {
            throw new Exception('Workflow integration is disabled');
        }

        $endpoint = self::ENDPOINTS['workflow'];

        $payload = [
            'workflow' => $workflowName,
            'params' => $params,
        ];

        if (!empty($this->config['api_key'])) {
            $payload['api_key'] = $this->config['api_key'];
        }

        return $this->makeRequest($endpoint, $payload);
    }

    /**
     * Request frontend audit from Intelligence Hub
     *
     * Uses Playwright/Puppeteer to audit a page for:
     * - Errors (JS, PHP, console)
     * - Performance issues
     * - Accessibility problems
     * - SEO issues
     *
     * @param string $url URL to audit
     * @param array $checks Types of checks: errors, performance, accessibility, seo
     * @param bool $autoFix Whether to generate fixes (requires approval)
     * @return array Audit results with errors, suggestions, screenshots
     */
    public function auditPage(string $url, array $checks = ['errors'], bool $autoFix = false): array
    {
        if (!$this->config['enable_frontend_tools']) {
            throw new Exception('Frontend tools integration is disabled');
        }

        // Call Intelligence Hub frontend audit tool
        return $this->executeWorkflow('frontend_audit', [
            'url' => $url,
            'checks' => $checks,
            'auto_fix' => $autoFix,
        ]);
    }

    /**
     * Make HTTP request to Intelligence Hub
     *
     * @param string $endpoint API endpoint URL
     * @param array $payload Request payload
     * @return array Decoded response
     * @throws Exception If request fails
     */
    private function makeRequest(string $endpoint, array $payload): array
    {
        $ch = curl_init($endpoint);

        $headers = ['Content-Type: application/json'];

        // Add API key in header if configured and not in payload
        if (!empty($this->config['api_key']) && !isset($payload['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['api_key'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("HTTP {$httpCode} error from Intelligence Hub");
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Intelligence Hub');
        }

        return $decoded;
    }

    /**
     * Parse Intelligence Hub response into standardized format
     *
     * TODO: Adjust based on actual response structure
     *
     * @param array $response Raw API response
     * @return array Standardized response
     */
    private function parseResponse(array $response): array
    {
        // Default parsing - adjust based on your actual response format
        return [
            'success' => true,
            'message' => $response['response'] ?? $response['message'] ?? '',
            'confidence' => $response['confidence'] ?? 0.85,
            'actions' => $response['actions'] ?? [],
            'reasoning' => $response['reasoning'] ?? '',
            'metadata' => [
                'provider' => 'intelligence-hub',
                'model' => $response['model'] ?? 'gpt-4o',
                'tokens_used' => $response['tokens_used'] ?? $response['usage']['total_tokens'] ?? 0,
                'processing_time_ms' => $response['processing_time_ms'] ?? 0,
            ],
            'raw_response' => $response, // Keep original for debugging
        ];
    }

    /**
     * Get available MCP tools
     *
     * @return array List of available MCP tool names
     */
    public function getAvailableMCPTools(): array
    {
        return self::MCP_TOOLS;
    }

    /**
     * Check Intelligence Hub health
     *
     * @return array Health status
     */
    public function healthCheck(): array
    {
        try {
            return $this->useMCPTool('health_check', []);
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}
