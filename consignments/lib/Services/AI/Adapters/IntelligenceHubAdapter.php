<?php
declare(strict_types=1);

namespace CIS\Consignments\Services\AI\Adapters;

use Exception;

/**
 * Intelligence Hub Adapter
 *
 * Connects to internal AI Agent Platform at gpt.ecigdis.co.nz
 *
 * Features:
 * - Chat with GPT-4o/Claude via Intelligence Hub
 * - MCP Server tools (semantic search, code analysis, 22K+ indexed files)
 * - RAG (Retrieval-Augmented Generation) with company knowledge
 * - Workflow orchestration
 * - Frontend automation (Playwright, Puppeteer)
 * - Cost-effective (internal caching + batch processing)
 *
 * @package CIS\Consignments\Services\AI\Adapters
 */
class IntelligenceHubAdapter
{
    private array $config;
    private string $chatEndpoint;
    private string $mcpEndpoint;
    private string $workflowEndpoint;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->chatEndpoint = $config['chat_endpoint'] ?? 'https://gpt.ecigdis.co.nz/ai-agent/api/chat.php';
        $this->mcpEndpoint = $config['mcp_endpoint'] ?? 'https://gpt.ecigdis.co.nz/mcp/server_v4.php';
        $this->workflowEndpoint = $config['workflow_endpoint'] ?? 'https://gpt.ecigdis.co.nz/ai-agent/api/execute-workflow.php';
    }

    /**
     * Chat with Intelligence Hub AI via MCP Server v3
     *
     * Uses ai_agent.query tool which:
     * - Automatically records ALL conversations to database
     * - Searches 8,645 indexed files with RAG
     * - Maintains conversation memory (10 turns)
     * - Executes MCP tools on-demand
     * - Supports streaming responses
     *
     * @param string $prompt User prompt
     * @param array $context Transfer/system context
     * @param array $options Additional options
     * @return array Standardized response
     */
    public function chat(string $prompt, array $context = [], array $options = []): array
    {
        // Use MCP JSON-RPC format for ai_agent.query tool
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => uniqid('consignments_', true),
            'params' => [
                'name' => 'ai_agent.query',
                'arguments' => [
                    'query' => $prompt,
                    'mode' => $options['mode'] ?? 'standard', // quick|standard|deep|raw
                    'conversation_id' => $context['conversation_id'] ?? $this->generateSessionId(),
                    'top_k' => $options['top_k'] ?? 5,
                    'snippet_bytes' => $options['snippet_bytes'] ?? 240,
                    'summary_only' => $options['summary_only'] ?? true,
                    'include_debug' => $options['include_debug'] ?? false,
                    'stream' => $options['stream'] ?? false,
                    'format' => $options['format'] ?? 'list',
                ],
            ],
        ];

        // Add transfer context as metadata
        if (!empty($context['transfer_id'])) {
            $payload['params']['arguments']['metadata'] = $this->enrichTransferContext($context);
        }

        $response = $this->callMCPAPI($payload);

        return $this->parseMCPResponse($response);
    }    /**
     * Use MCP Server tool directly
     *
     * Available tools:
     * - semantic_search: Search codebase with natural language
     * - analyze_file: Deep analysis of specific file
     * - find_code: Locate functions, classes, patterns
     * - get_stats: Module/system statistics
     * - analyze_dependencies: Trace code dependencies
     * - suggest_refactor: AI-powered refactoring suggestions
     *
     * @param string $tool Tool name
     * @param array $args Tool arguments
     * @return array Tool response
     */
    public function useMCPTool(string $tool, array $args = []): array
    {
        $payload = [
            'tool' => $tool,
            'arguments' => $args,
            'source' => 'consignments_module',
        ];

        $response = $this->callMCPAPI($payload);

        return [
            'success' => true,
            'message' => $response['result'] ?? '',
            'data' => $response['data'] ?? [],
            'metadata' => $response['metadata'] ?? [],
        ];
    }

    /**
     * Semantic search across entire codebase (22,185 files indexed)
     *
     * Example: "Find code that validates transfer quantities"
     *
     * @param string $query Natural language query
     * @param array $filters Optional filters (file types, modules, etc.)
     * @return array Search results
     */
    public function semanticSearch(string $query, array $filters = []): array
    {
        return $this->useMCPTool('semantic_search', [
            'query' => $query,
            'filters' => $filters,
            'limit' => $filters['limit'] ?? 10,
        ]);
    }

    /**
     * Execute Intelligence Hub workflow
     *
     * Workflows: Multi-step AI-powered automations with approval gates
     *
     * Example workflows:
     * - "audit_transfer" - Full transfer validation with recommendations
     * - "optimize_carrier" - Analyze carrier performance, suggest alternatives
     * - "monitor_frontend" - Continuous UI monitoring with auto-fix
     *
     * @param string $workflowName Workflow identifier
     * @param array $params Workflow parameters
     * @return array Workflow result
     */
    public function executeWorkflow(string $workflowName, array $params = []): array
    {
        $payload = [
            'workflow' => $workflowName,
            'params' => $params,
            'source' => 'consignments_module',
            'require_approval' => $params['require_approval'] ?? true,
        ];

        $response = $this->callAPI($this->workflowEndpoint, $payload);

        return [
            'success' => $response['success'] ?? false,
            'workflow_id' => $response['workflow_id'] ?? null,
            'status' => $response['status'] ?? 'unknown',
            'result' => $response['result'] ?? [],
            'approval_required' => $response['approval_required'] ?? false,
            'approval_url' => $response['approval_url'] ?? null,
        ];
    }

    /**
     * Request frontend automation (Playwright/Puppeteer audit)
     *
     * Intelligence Hub will:
     * - Load page in headless browser
     * - Run accessibility audit
     * - Check for JS errors
     * - Validate forms
     * - Take screenshots
     * - Generate fix recommendations
     *
     * @param string $url Page URL to audit
     * @param array $options Audit options
     * @return array Audit results
     */
    public function auditPage(string $url, array $options = []): array
    {
        return $this->executeWorkflow('frontend_audit', [
            'url' => $url,
            'checks' => $options['checks'] ?? ['accessibility', 'errors', 'forms', 'performance'],
            'generate_fixes' => $options['generate_fixes'] ?? true,
            'require_approval' => $options['require_approval'] ?? true,
        ]);
    }

    /**
     * Enrich transfer context with additional data for better AI responses
     *
     * @param array $context Base context
     * @return array Enriched context
     */
    private function enrichTransferContext(array $context): array
    {
        // Add transfer metadata, history, related records
        // Intelligence Hub's RAG will augment this with KB data

        return [
            'transfer_id' => $context['transfer_id'],
            'type' => $context['type'] ?? 'unknown',
            'status' => $context['status'] ?? 'unknown',
            'from_outlet' => $context['from_outlet'] ?? null,
            'to_outlet' => $context['to_outlet'] ?? null,
            'item_count' => $context['item_count'] ?? 0,
            'total_weight' => $context['total_weight'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Call MCP Server v3 JSON-RPC API
     *
     * @param array $payload JSON-RPC request payload
     * @return array API response
     */
    private function callMCPAPI(array $payload): array
    {
        $ch = curl_init($this->mcpEndpoint);

        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . ($this->config['api_key'] ?? ''),
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("MCP Server connection error: {$curlError}");
        }

        if ($httpCode !== 200) {
            throw new Exception("MCP Server API error: HTTP {$httpCode} - {$response}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from MCP Server');
        }

        // Check for JSON-RPC error
        if (isset($data['error'])) {
            throw new Exception("MCP Server error: {$data['error']['message']} (code: {$data['error']['code']})");
        }

        return $data;
    }

    /**
     * Parse MCP JSON-RPC response into standardized format
     *
     * @param array $response Raw JSON-RPC response
     * @return array Standardized response
     */
    private function parseMCPResponse(array $response): array
    {
        $result = $response['result'] ?? [];

        // Extract content from MCP response format
        $content = '';
        if (isset($result['content']) && is_array($result['content'])) {
            foreach ($result['content'] as $item) {
                $content .= $item['text'] ?? '';
            }
        }

        return [
            'message' => $content ?: ($result['summary'] ?? $result['response'] ?? ''),
            'confidence' => 0.9, // MCP server responses are high confidence
            'reasoning' => $result['reasoning'] ?? '',
            'actions' => $result['suggested_actions'] ?? [],
            'model' => 'intelligence-hub-mcp-v3',
            'tokens_used' => $result['tokens_used'] ?? 0,
            'cost_usd' => 0, // Internal service, no direct cost
            'processing_time_ms' => $result['execution_time'] ?? 0,
            'used_rag' => true, // MCP always uses RAG
            'used_mcp' => true,
            'sources' => $result['sources'] ?? $result['files_found'] ?? [],
            'metadata' => [
                'files_searched' => $result['files_searched'] ?? 0,
                'total_indexed' => 8645, // Current index size
                'conversation_recorded' => true, // MCP auto-records
            ],
        ];
    }

    /**
     * Parse Intelligence Hub response into standardized format
     *
     * @param array $response Raw API response
     * @return array Standardized response
     */
    private function parseResponse(array $response): array
    {
        return [
            'message' => $response['response'] ?? $response['message'] ?? '',
            'confidence' => $response['confidence'] ?? 0.9,
            'reasoning' => $response['reasoning'] ?? '',
            'actions' => $response['suggested_actions'] ?? [],
            'model' => $response['model_used'] ?? 'intelligence-hub',
            'tokens_used' => $response['tokens_used'] ?? 0,
            'cost_usd' => $response['cost_usd'] ?? 0,
            'processing_time_ms' => $response['processing_time_ms'] ?? 0,
            'used_rag' => $response['used_rag'] ?? false,
            'used_mcp' => $response['used_mcp'] ?? false,
            'sources' => $response['sources'] ?? [],
        ];
    }

    /**
     * Generate session ID for conversation tracking
     *
     * @return string Session ID
     */
    private function generateSessionId(): string
    {
        return 'consignments_' . uniqid('', true);
    }
}
