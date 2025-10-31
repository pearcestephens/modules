<?php
/**
 * ============================================================================
 * AI Chat Service - Enterprise ERP AI Assistant Integration
 * ============================================================================
 * 
 * Provides AI-powered chat and automation for CIS ERP system
 * Integrates with Claude/GPT via AI Intelligence Hub
 * 
 * Key Features:
 * - Natural language queries for business data
 * - Automated report generation
 * - Decision support and recommendations
 * - Task automation and workflow assistance
 * - Real-time business intelligence
 * - Predictive analytics
 * 
 * @package CIS\Base\Services
 * @version 1.0.0
 * @author Pearce Stephens
 * @date 2025-10-28
 */

declare(strict_types=1);

namespace CIS\Base\Services;

use CIS\Base\Database;
use CIS\Base\Logger;

class AIChatService
{
    private const AI_HUB_URL = 'https://gpt.ecigdis.co.nz/api/';
    private const MAX_CONTEXT_LENGTH = 8000; // tokens
    
    private static ?self $instance = null;
    private array $conversationHistory = [];
    private string $sessionId;
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        $this->sessionId = $this->generateSessionId();
    }
    
    // ========================================================================
    // BUSINESS INTELLIGENCE & DATA QUERIES
    // ========================================================================
    
    /**
     * Natural Language Query - Ask questions about business data
     * 
     * Examples:
     * - "What were our top 5 selling products last month?"
     * - "Show me all pending purchase orders over $10,000"
     * - "Which stores have low stock of product X?"
     */
    public function queryBusinessData(string $question, array $context = []): array
    {
        // Build context from database schema and current filters
        $systemContext = $this->buildBusinessContext($context);
        
        // Send to AI with database schema knowledge
        $response = $this->sendToAI([
            'action' => 'query_data',
            'question' => $question,
            'context' => $systemContext,
            'available_tables' => $this->getAvailableTables(),
            'user_role' => $context['user_role'] ?? 'user',
        ]);
        
        // If AI generated SQL, execute it safely
        if (isset($response['sql_query'])) {
            $results = $this->executeSafeQuery($response['sql_query'], $response['parameters'] ?? []);
            $response['data'] = $results;
            $response['row_count'] = count($results);
        }
        
        // Log the interaction
        Logger::info('AI Business Query', [
            'question' => $question,
            'sql_generated' => $response['sql_query'] ?? null,
            'results_count' => $response['row_count'] ?? 0,
            'execution_time' => $response['execution_time'] ?? 0
        ]);
        
        return $response;
    }
    
    /**
     * Generate Reports - AI-powered report creation
     * 
     * Examples:
     * - "Create a sales performance report for Q4"
     * - "Generate inventory turnover analysis"
     * - "Build a staff performance dashboard"
     */
    public function generateReport(string $reportRequest, array $options = []): array
    {
        $response = $this->sendToAI([
            'action' => 'generate_report',
            'request' => $reportRequest,
            'format' => $options['format'] ?? 'table', // table, chart, pdf, excel
            'date_range' => $options['date_range'] ?? 'last_30_days',
            'filters' => $options['filters'] ?? [],
            'available_metrics' => $this->getAvailableMetrics()
        ]);
        
        // Execute queries to build report
        if (isset($response['queries'])) {
            $reportData = [];
            foreach ($response['queries'] as $query) {
                $reportData[$query['section']] = $this->executeSafeQuery(
                    $query['sql'],
                    $query['parameters'] ?? []
                );
            }
            $response['report_data'] = $reportData;
        }
        
        // Log report generation
        Logger::info('AI Report Generated', [
            'report_type' => $reportRequest,
            'sections' => count($response['queries'] ?? []),
            'format' => $options['format'] ?? 'table'
        ]);
        
        return $response;
    }
    
    /**
     * Analyze Trends - Identify patterns and trends in data
     * 
     * Examples:
     * - "Analyze sales trends for the past 6 months"
     * - "Find correlations between marketing spend and revenue"
     * - "Predict inventory needs for next quarter"
     */
    public function analyzeTrends(string $analysisRequest, array $dataSource = []): array
    {
        // Fetch relevant historical data
        $historicalData = $this->fetchHistoricalData($dataSource);
        
        $response = $this->sendToAI([
            'action' => 'analyze_trends',
            'request' => $analysisRequest,
            'data' => $historicalData,
            'time_period' => $dataSource['time_period'] ?? 'last_6_months',
            'metrics' => $dataSource['metrics'] ?? []
        ]);
        
        // Add visualizations if requested
        if ($response['requires_visualization'] ?? false) {
            $response['chart_config'] = $this->generateChartConfig($response);
        }
        
        Logger::info('AI Trend Analysis', [
            'analysis_type' => $analysisRequest,
            'data_points' => count($historicalData),
            'insights_found' => count($response['insights'] ?? [])
        ]);
        
        return $response;
    }
    
    // ========================================================================
    // AUTOMATION & TASK ASSISTANCE
    // ========================================================================
    
    /**
     * Automate Workflow - Create or execute automated workflows
     * 
     * Examples:
     * - "Create a workflow to auto-reorder stock when below threshold"
     * - "Set up automated invoice reminders"
     * - "Build approval workflow for purchase orders over $5,000"
     */
    public function automateWorkflow(string $workflowRequest, array $config = []): array
    {
        $response = $this->sendToAI([
            'action' => 'create_workflow',
            'request' => $workflowRequest,
            'triggers' => $config['triggers'] ?? [],
            'conditions' => $config['conditions'] ?? [],
            'actions' => $config['actions'] ?? [],
            'available_actions' => $this->getAvailableWorkflowActions()
        ]);
        
        // If workflow is approved, save it
        if ($response['workflow_ready'] ?? false) {
            $workflowId = $this->saveWorkflow($response['workflow_definition']);
            $response['workflow_id'] = $workflowId;
        }
        
        Logger::info('AI Workflow Created', [
            'workflow_name' => $response['workflow_name'] ?? 'Unnamed',
            'triggers' => count($response['workflow_definition']['triggers'] ?? []),
            'actions' => count($response['workflow_definition']['actions'] ?? [])
        ]);
        
        return $response;
    }
    
    /**
     * Suggest Actions - AI recommendations for next steps
     * 
     * Examples:
     * - "What should I do about low stock levels?"
     * - "How can I improve cash flow?"
     * - "Suggest actions for underperforming products"
     */
    public function suggestActions(string $situation, array $context = []): array
    {
        // Gather relevant business metrics
        $businessMetrics = $this->getCurrentBusinessMetrics($context);
        
        $response = $this->sendToAI([
            'action' => 'suggest_actions',
            'situation' => $situation,
            'current_metrics' => $businessMetrics,
            'constraints' => $context['constraints'] ?? [],
            'priorities' => $context['priorities'] ?? []
        ]);
        
        // Rank suggestions by impact and feasibility
        if (isset($response['suggestions'])) {
            $response['suggestions'] = $this->rankSuggestions($response['suggestions']);
        }
        
        Logger::info('AI Action Suggestions', [
            'situation' => $situation,
            'suggestions_count' => count($response['suggestions'] ?? []),
            'top_priority' => $response['suggestions'][0]['action'] ?? 'none'
        ]);
        
        return $response;
    }
    
    /**
     * Validate Transaction - Check if transaction makes business sense
     * 
     * Examples:
     * - "Should I approve this $50,000 purchase order?"
     * - "Is this customer credit limit increase justified?"
     * - "Validate this supplier pricing change"
     */
    public function validateTransaction(array $transactionData, string $transactionType): array
    {
        // Get historical context for similar transactions
        $historicalContext = $this->getTransactionHistory($transactionType);
        
        $response = $this->sendToAI([
            'action' => 'validate_transaction',
            'transaction' => $transactionData,
            'type' => $transactionType,
            'historical_data' => $historicalContext,
            'business_rules' => $this->getBusinessRules($transactionType),
            'risk_factors' => $this->identifyRiskFactors($transactionData)
        ]);
        
        // Add risk score and recommendation
        $response['risk_score'] = $this->calculateRiskScore($response);
        $response['approval_recommendation'] = $response['risk_score'] < 0.3 ? 'approve' : 
                                               ($response['risk_score'] < 0.7 ? 'review' : 'reject');
        
        Logger::info('AI Transaction Validation', [
            'transaction_type' => $transactionType,
            'transaction_value' => $transactionData['amount'] ?? 0,
            'risk_score' => $response['risk_score'],
            'recommendation' => $response['approval_recommendation']
        ]);
        
        return $response;
    }
    
    // ========================================================================
    // DECISION SUPPORT & RECOMMENDATIONS
    // ========================================================================
    
    /**
     * Compare Options - Help choose between alternatives
     * 
     * Examples:
     * - "Which supplier should I choose for this order?"
     * - "Compare these two marketing strategies"
     * - "Should I hire more staff or outsource?"
     */
    public function compareOptions(array $options, string $decisionContext): array
    {
        // Gather pros/cons data for each option
        $enrichedOptions = array_map(function($option) {
            return array_merge($option, [
                'historical_performance' => $this->getHistoricalPerformance($option),
                'cost_analysis' => $this->analyzeCosts($option),
                'risk_assessment' => $this->assessRisks($option)
            ]);
        }, $options);
        
        $response = $this->sendToAI([
            'action' => 'compare_options',
            'options' => $enrichedOptions,
            'context' => $decisionContext,
            'decision_criteria' => ['cost', 'time', 'quality', 'risk'],
            'business_priorities' => $this->getBusinessPriorities()
        ]);
        
        // Add scoring matrix
        $response['comparison_matrix'] = $this->buildComparisonMatrix($enrichedOptions, $response);
        
        Logger::info('AI Option Comparison', [
            'decision_type' => $decisionContext,
            'options_compared' => count($options),
            'recommended_option' => $response['recommendation']['option_id'] ?? null
        ]);
        
        return $response;
    }
    
    /**
     * Forecast Metrics - Predict future business metrics
     * 
     * Examples:
     * - "Forecast sales for next quarter"
     * - "Predict inventory needs for Christmas season"
     * - "Estimate cash flow for next 6 months"
     */
    public function forecastMetrics(string $metric, array $parameters = []): array
    {
        // Fetch historical data for the metric
        $historicalData = $this->fetchMetricHistory($metric, $parameters['lookback_period'] ?? '2_years');
        
        $response = $this->sendToAI([
            'action' => 'forecast_metrics',
            'metric' => $metric,
            'historical_data' => $historicalData,
            'forecast_period' => $parameters['forecast_period'] ?? '3_months',
            'confidence_level' => $parameters['confidence_level'] ?? 0.95,
            'external_factors' => $parameters['external_factors'] ?? []
        ]);
        
        // Add statistical validation
        $response['forecast_accuracy'] = $this->calculateForecastAccuracy($metric, $historicalData);
        $response['confidence_intervals'] = $this->calculateConfidenceIntervals($response);
        
        Logger::info('AI Metric Forecast', [
            'metric' => $metric,
            'forecast_period' => $parameters['forecast_period'] ?? '3_months',
            'data_points_used' => count($historicalData),
            'confidence' => $response['confidence'] ?? 0
        ]);
        
        return $response;
    }
    
    /**
     * Optimize Resource - Find optimal allocation of resources
     * 
     * Examples:
     * - "How should I allocate budget across departments?"
     * - "Optimize staff scheduling for next week"
     * - "Best way to distribute inventory across stores"
     */
    public function optimizeResource(string $resourceType, array $constraints = []): array
    {
        // Get current resource allocation
        $currentAllocation = $this->getCurrentResourceAllocation($resourceType);
        
        $response = $this->sendToAI([
            'action' => 'optimize_resource',
            'resource_type' => $resourceType,
            'current_allocation' => $currentAllocation,
            'constraints' => $constraints,
            'optimization_goals' => $constraints['goals'] ?? ['maximize_efficiency', 'minimize_cost'],
            'historical_performance' => $this->getResourcePerformanceHistory($resourceType)
        ]);
        
        // Calculate impact of proposed changes
        if (isset($response['proposed_allocation'])) {
            $response['impact_analysis'] = $this->analyzeOptimizationImpact(
                $currentAllocation,
                $response['proposed_allocation']
            );
        }
        
        Logger::info('AI Resource Optimization', [
            'resource_type' => $resourceType,
            'constraints' => count($constraints),
            'improvement_percentage' => $response['expected_improvement'] ?? 0
        ]);
        
        return $response;
    }
    
    // ========================================================================
    // ANOMALY DETECTION & ALERTS
    // ========================================================================
    
    /**
     * Detect Anomalies - Find unusual patterns in data
     * 
     * Examples:
     * - "Are there any unusual transactions today?"
     * - "Detect anomalies in inventory movements"
     * - "Find suspicious customer behavior patterns"
     */
    public function detectAnomalies(string $dataSource, array $parameters = []): array
    {
        // Fetch data to analyze
        $data = $this->fetchDataForAnalysis($dataSource, $parameters);
        
        // Get baseline metrics
        $baseline = $this->calculateBaseline($dataSource, $parameters['baseline_period'] ?? '30_days');
        
        $response = $this->sendToAI([
            'action' => 'detect_anomalies',
            'data_source' => $dataSource,
            'current_data' => $data,
            'baseline_metrics' => $baseline,
            'sensitivity' => $parameters['sensitivity'] ?? 'medium',
            'anomaly_types' => $parameters['types'] ?? ['outliers', 'trends', 'patterns']
        ]);
        
        // Calculate severity scores for each anomaly
        if (isset($response['anomalies'])) {
            foreach ($response['anomalies'] as &$anomaly) {
                $anomaly['severity_score'] = $this->calculateAnomalySeverity($anomaly, $baseline);
                $anomaly['recommended_action'] = $this->recommendAnomalyAction($anomaly);
            }
        }
        
        Logger::warning('AI Anomaly Detection', [
            'data_source' => $dataSource,
            'anomalies_found' => count($response['anomalies'] ?? []),
            'high_severity_count' => count(array_filter($response['anomalies'] ?? [], 
                fn($a) => $a['severity_score'] > 0.7))
        ]);
        
        return $response;
    }
    
    /**
     * Monitor KPIs - Real-time KPI monitoring with alerts
     * 
     * Examples:
     * - "Monitor gross profit margin"
     * - "Track daily sales against target"
     * - "Watch inventory turnover ratio"
     */
    public function monitorKPI(string $kpiName, array $thresholds = []): array
    {
        // Get current KPI value
        $currentValue = $this->calculateKPI($kpiName);
        
        // Get historical trend
        $trend = $this->getKPITrend($kpiName, '30_days');
        
        $response = $this->sendToAI([
            'action' => 'monitor_kpi',
            'kpi_name' => $kpiName,
            'current_value' => $currentValue,
            'trend' => $trend,
            'thresholds' => $thresholds,
            'target_value' => $thresholds['target'] ?? null,
            'warning_level' => $thresholds['warning'] ?? null,
            'critical_level' => $thresholds['critical'] ?? null
        ]);
        
        // Determine alert level
        $response['alert_level'] = $this->determineAlertLevel($currentValue, $thresholds);
        
        // If alert, create notification
        if ($response['alert_level'] !== 'normal') {
            $this->createKPIAlert($kpiName, $currentValue, $response);
        }
        
        Logger::info('AI KPI Monitor', [
            'kpi' => $kpiName,
            'current_value' => $currentValue,
            'alert_level' => $response['alert_level'],
            'trend' => $trend['direction'] ?? 'stable'
        ]);
        
        return $response;
    }
    
    // ========================================================================
    // CONVERSATIONAL AI & CONTEXT MANAGEMENT
    // ========================================================================
    
    /**
     * Chat - General conversational AI interface
     */
    public function chat(string $message, array $context = []): array
    {
        // Add message to conversation history
        $this->conversationHistory[] = [
            'role' => 'user',
            'content' => $message,
            'timestamp' => time()
        ];
        
        // Trim context if too long
        $this->trimConversationHistory();
        
        $response = $this->sendToAI([
            'action' => 'chat',
            'message' => $message,
            'conversation_history' => $this->conversationHistory,
            'user_context' => $context,
            'available_capabilities' => $this->getAvailableCapabilities()
        ]);
        
        // Add response to history
        $this->conversationHistory[] = [
            'role' => 'assistant',
            'content' => $response['message'] ?? '',
            'timestamp' => time()
        ];
        
        // If AI suggests using a specific function, add it to response
        if (isset($response['suggested_function'])) {
            $response['function_help'] = $this->getFunctionHelp($response['suggested_function']);
        }
        
        Logger::info('AI Chat Interaction', [
            'message_length' => strlen($message),
            'response_length' => strlen($response['message'] ?? ''),
            'function_suggested' => $response['suggested_function'] ?? null
        ]);
        
        return $response;
    }
    
    /**
     * Explain Result - Get AI explanation of data or decision
     */
    public function explainResult(mixed $data, string $context): array
    {
        $response = $this->sendToAI([
            'action' => 'explain_result',
            'data' => $data,
            'context' => $context,
            'explanation_level' => 'detailed' // simple, detailed, technical
        ]);
        
        return $response;
    }
    
    /**
     * Learn Pattern - Train AI on business-specific patterns
     */
    public function learnPattern(string $patternType, array $examples): array
    {
        $response = $this->sendToAI([
            'action' => 'learn_pattern',
            'pattern_type' => $patternType,
            'examples' => $examples,
            'save_learning' => true
        ]);
        
        Logger::info('AI Pattern Learning', [
            'pattern_type' => $patternType,
            'examples_provided' => count($examples)
        ]);
        
        return $response;
    }
    
    // ========================================================================
    // HELPER METHODS
    // ========================================================================
    
    private function sendToAI(array $payload): array
    {
        $startTime = microtime(true);
        
        try {
            $ch = curl_init(self::AI_HUB_URL . 'chat');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Session-ID: ' . $this->sessionId
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new \Exception("AI Hub returned HTTP $httpCode");
            }
            
            $result = json_decode($response, true);
            $result['execution_time'] = round((microtime(true) - $startTime) * 1000, 2);
            
            return $result;
            
        } catch (\Exception $e) {
            Logger::error('AI Hub Communication Failed', [
                'error' => $e->getMessage(),
                'action' => $payload['action'] ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback_response' => $this->getFallbackResponse($payload)
            ];
        }
    }
    
    private function executeSafeQuery(string $sql, array $params = []): array
    {
        // Validate SQL for safety (read-only operations)
        if (!$this->isSafeQuery($sql)) {
            throw new \Exception("Query contains unsafe operations");
        }
        
        return Database::query($sql, $params);
    }
    
    private function isSafeQuery(string $sql): bool
    {
        $sql = strtoupper(trim($sql));
        
        // Only allow SELECT, WITH (CTEs)
        $allowedStarts = ['SELECT', 'WITH'];
        $startsWithAllowed = false;
        foreach ($allowedStarts as $start) {
            if (str_starts_with($sql, $start)) {
                $startsWithAllowed = true;
                break;
            }
        }
        
        if (!$startsWithAllowed) {
            return false;
        }
        
        // Disallow dangerous keywords
        $dangerous = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE'];
        foreach ($dangerous as $keyword) {
            if (strpos($sql, $keyword) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    private function getAvailableTables(): array
    {
        $tables = Database::query("SHOW TABLES");
        return array_column($tables, array_keys($tables[0])[0]);
    }
    
    private function getAvailableMetrics(): array
    {
        return [
            'sales' => ['total_sales', 'average_order_value', 'sales_growth'],
            'inventory' => ['turnover_rate', 'stock_levels', 'shrinkage'],
            'financial' => ['gross_profit', 'net_profit', 'cash_flow'],
            'customer' => ['acquisition_cost', 'lifetime_value', 'retention_rate'],
            'staff' => ['productivity', 'attendance', 'performance_scores']
        ];
    }
    
    private function generateSessionId(): string
    {
        return 'ai_' . uniqid() . '_' . substr(md5((string)time()), 0, 8);
    }
    
    private function trimConversationHistory(): void
    {
        // Keep only last 20 messages to stay under token limit
        if (count($this->conversationHistory) > 20) {
            $this->conversationHistory = array_slice($this->conversationHistory, -20);
        }
    }
    
    private function buildBusinessContext(array $userContext): array
    {
        return [
            'business_type' => 'retail',
            'industry' => 'vaping products',
            'stores_count' => 17,
            'system_name' => 'CIS',
            'current_date' => date('Y-m-d'),
            'user_role' => $userContext['user_role'] ?? 'user',
            'user_permissions' => $userContext['permissions'] ?? []
        ];
    }
    
    private function getAvailableCapabilities(): array
    {
        return [
            'query_business_data',
            'generate_report',
            'analyze_trends',
            'automate_workflow',
            'suggest_actions',
            'validate_transaction',
            'compare_options',
            'forecast_metrics',
            'optimize_resource',
            'detect_anomalies',
            'monitor_kpi'
        ];
    }
    
    private function getFallbackResponse(array $payload): string
    {
        return "I'm currently unable to process your request. Please try again later or contact support.";
    }
    
    // Placeholder methods for full implementation
    private function fetchHistoricalData(array $dataSource): array { return []; }
    private function generateChartConfig(array $response): array { return []; }
    private function getAvailableWorkflowActions(): array { return []; }
    private function saveWorkflow(array $definition): int { return 0; }
    private function getCurrentBusinessMetrics(array $context): array { return []; }
    private function rankSuggestions(array $suggestions): array { return $suggestions; }
    private function getTransactionHistory(string $type): array { return []; }
    private function getBusinessRules(string $type): array { return []; }
    private function identifyRiskFactors(array $data): array { return []; }
    private function calculateRiskScore(array $response): float { return 0.0; }
    private function getHistoricalPerformance(array $option): array { return []; }
    private function analyzeCosts(array $option): array { return []; }
    private function assessRisks(array $option): array { return []; }
    private function getBusinessPriorities(): array { return []; }
    private function buildComparisonMatrix(array $options, array $response): array { return []; }
    private function fetchMetricHistory(string $metric, string $period): array { return []; }
    private function calculateForecastAccuracy(string $metric, array $data): float { return 0.0; }
    private function calculateConfidenceIntervals(array $response): array { return []; }
    private function getCurrentResourceAllocation(string $type): array { return []; }
    private function getResourcePerformanceHistory(string $type): array { return []; }
    private function analyzeOptimizationImpact(array $current, array $proposed): array { return []; }
    private function fetchDataForAnalysis(string $source, array $params): array { return []; }
    private function calculateBaseline(string $source, string $period): array { return []; }
    private function calculateAnomalySeverity(array $anomaly, array $baseline): float { return 0.0; }
    private function recommendAnomalyAction(array $anomaly): string { return 'review'; }
    private function calculateKPI(string $name): float { return 0.0; }
    private function getKPITrend(string $name, string $period): array { return []; }
    private function determineAlertLevel(float $value, array $thresholds): string { return 'normal'; }
    private function createKPIAlert(string $name, float $value, array $response): void {}
    private function getFunctionHelp(string $function): array { return []; }
}
