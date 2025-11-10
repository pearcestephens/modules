<?php

/**
 * AI Business Insights Service.
 *
 * Generates actionable business intelligence using AI analysis
 * - Sales performance insights
 * - Inventory intelligence
 * - Operational efficiency analysis
 * - Proactive issue detection
 *
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Base\Services;

use CIS\Base\AIService;
use CIS\Base\Core\Application;
use CIS\Base\Core\Database;
use CIS\Base\Core\Logger;
use Exception;

use function array_slice;
use function count;

class AIBusinessInsightsService
{
    private Application $app;

    private Database $db;

    private Logger $logger;

    /**
     * Constructor.
     *
     * @param Application $app Application instance
     */
    public function __construct(Application $app)
    {
        $this->app    = $app;
        $this->db     = $app->make(Database::class);
        $this->logger = $app->make(Logger::class);
    }

    /**
     * Generate daily business insights.
     *
     * Analyzes recent data and generates actionable insights
     *
     * @return array Generated insights
     */
    public function generateDailyInsights(): array
    {
        $this->logger->info('Generating daily AI business insights');

        $insights = [];

        try {
            // Sales performance insights
            $salesInsights = $this->analyzeSalesPerformance();
            $insights      = array_merge($insights, $salesInsights);

            // Inventory intelligence
            $inventoryInsights = $this->analyzeInventoryIntelligence();
            $insights          = array_merge($insights, $inventoryInsights);

            // Operational efficiency
            $operationalInsights = $this->analyzeOperationalEfficiency();
            $insights            = array_merge($insights, $operationalInsights);

            // Store insights in database
            foreach ($insights as $insight) {
                $this->saveInsight($insight);
            }

            $this->logger->info('Generated ' . count($insights) . ' business insights');

            return $insights;
        } catch (Exception $e) {
            $this->logger->error('Failed to generate insights: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Get critical insights requiring immediate attention.
     *
     * @return array Critical insights
     */
    public function getCriticalInsights(): array
    {
        return $this->db->query("
            SELECT *
            FROM ai_business_insights
            WHERE priority IN ('critical', 'high')
                AND status = 'new'
                AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY
                FIELD(priority, 'critical', 'high'),
                created_at DESC
            LIMIT 20
        ")->fetchAll();
    }

    /**
     * Get all active insights.
     *
     * @param string|null $type     Filter by type
     * @param string|null $priority Filter by priority
     *
     * @return array Insights
     */
    public function getInsights(?string $type = null, ?string $priority = null): array
    {
        $sql = "
            SELECT *
            FROM ai_business_insights
            WHERE status IN ('new', 'reviewed')
                AND (expires_at IS NULL OR expires_at > NOW())
        ";

        $params = [];

        if ($type) {
            $sql .= ' AND insight_type = ?';
            $params[] = $type;
        }

        if ($priority) {
            $sql .= ' AND priority = ?';
            $params[] = $priority;
        }

        $sql .= " ORDER BY
                    FIELD(priority, 'critical', 'high', 'medium', 'low', 'info'),
                    created_at DESC";

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Ask AI a business question.
     *
     * @param string $question Natural language question
     * @param array  $context  Additional context
     *
     * @return array AI response with insights
     */
    public function ask(string $question, array $context = []): array
    {
        $this->logger->info('AI business question: ' . $question);

        // Use AIService to search relevant information
        $results = AIService::search($question, 20);

        // Log the interaction
        $this->logger->ai(
            'business_question',
            'business_intelligence',
            $question,
            ['results_count' => count($results)],
            $context,
        );

        return [
            'success'   => true,
            'question'  => $question,
            'results'   => $results,
            'context'   => $context,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Mark insight as reviewed.
     *
     * @param int         $insightId Insight ID
     * @param int         $userId    User who reviewed
     * @param string|null $action    Action taken
     */
    public function reviewInsight(int $insightId, int $userId, ?string $action = null): void
    {
        $this->db->update('ai_business_insights', [
            'status'       => 'reviewed',
            'reviewed_by'  => $userId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'action_taken' => $action,
        ], ['insight_id' => $insightId]);

        $this->logger->info("Insight #{$insightId} reviewed by user #{$userId}");
    }

    /**
     * Dismiss insight as not relevant.
     *
     * @param int    $insightId Insight ID
     * @param int    $userId    User who dismissed
     * @param string $reason    Reason for dismissal
     */
    public function dismissInsight(int $insightId, int $userId, string $reason): void
    {
        $this->db->update('ai_business_insights', [
            'status'       => 'dismissed',
            'reviewed_by'  => $userId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'action_taken' => 'Dismissed: ' . $reason,
        ], ['insight_id' => $insightId]);

        $this->logger->info("Insight #{$insightId} dismissed by user #{$userId}: {$reason}");
    }

    /**
     * Analyze sales performance and identify trends.
     *
     * @return array Sales insights
     */
    private function analyzeSalesPerformance(): array
    {
        $insights = [];

        // Get sales data for last 30 days vs previous 30 days
        $currentPeriod = $this->db->query("
            SELECT
                outlet_id,
                outlet_name,
                COUNT(*) as sale_count,
                SUM(total_price) as total_sales,
                AVG(total_price) as avg_sale_value
            FROM vend_sales
            WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND sale_date < CURDATE()
                AND status = 'CLOSED'
            GROUP BY outlet_id, outlet_name
        ")->fetchAll();

        $previousPeriod = $this->db->query("
            SELECT
                outlet_id,
                COUNT(*) as sale_count,
                SUM(total_price) as total_sales
            FROM vend_sales
            WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                AND sale_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND status = 'CLOSED'
            GROUP BY outlet_id
        ")->fetchAll();

        // Create lookup map for previous period
        $previousMap = [];
        foreach ($previousPeriod as $prev) {
            $previousMap[$prev['outlet_id']] = $prev;
        }

        // Analyze each store
        foreach ($currentPeriod as $current) {
            $outletId = $current['outlet_id'];
            $previous = $previousMap[$outletId] ?? null;

            if (!$previous) {
                continue;
            }

            // Calculate change percentage
            $salesChange = (($current['total_sales'] - $previous['total_sales']) / $previous['total_sales']) * 100;

            // Significant decline detected
            if ($salesChange < -15) {
                // Use AI to analyze potential reasons
                $aiAnalysis = AIService::ask(
                    "Analyze potential reasons for {$current['outlet_name']} sales declining by " .
                    round(abs($salesChange), 1) . '% in the last 30 days. Consider: staff changes, ' .
                    'competitor activity, seasonal patterns, inventory issues, local events.',
                );

                $insights[] = [
                    'type'        => 'sales_performance',
                    'category'    => 'store_decline',
                    'priority'    => $salesChange < -25 ? 'critical' : 'high',
                    'title'       => "{$current['outlet_name']} Sales Down " . round(abs($salesChange), 1) . '%',
                    'description' => 'Sales have decreased significantly from $' .
                                   number_format($previous['total_sales'], 2) . ' to $' .
                                   number_format($current['total_sales'], 2) . ' over the last 30 days.',
                    'data' => [
                        'outlet_id'          => $outletId,
                        'outlet_name'        => $current['outlet_name'],
                        'current_sales'      => $current['total_sales'],
                        'previous_sales'     => $previous['total_sales'],
                        'change_percent'     => round($salesChange, 2),
                        'current_sale_count' => $current['sale_count'],
                        'avg_sale_value'     => $current['avg_sale_value'],
                    ],
                    'ai_analysis'       => $aiAnalysis,
                    'recommendations'   => $this->generateSalesRecoveryRecommendations($salesChange, $current),
                    'time_period_start' => date('Y-m-d', strtotime('-30 days')),
                    'time_period_end'   => date('Y-m-d'),
                    'confidence'        => 0.85,
                ];
            }

            // Significant improvement detected
            if ($salesChange > 15) {
                $insights[] = [
                    'type'        => 'sales_performance',
                    'category'    => 'store_success',
                    'priority'    => 'medium',
                    'title'       => "{$current['outlet_name']} Sales Up " . round($salesChange, 1) . '%',
                    'description' => "Excellent performance! Analyze what's working here.",
                    'data'        => [
                        'outlet_id'      => $outletId,
                        'outlet_name'    => $current['outlet_name'],
                        'current_sales'  => $current['total_sales'],
                        'previous_sales' => $previous['total_sales'],
                        'change_percent' => round($salesChange, 2),
                    ],
                    'recommendations' => [
                        [
                            'action'      => 'Identify success factors',
                            'description' => 'Interview store manager to understand what drove improvement',
                            'impact'      => 'Can replicate success across other stores',
                        ],
                        [
                            'action'      => 'Document best practices',
                            'description' => 'Capture and share successful strategies',
                            'impact'      => 'Company-wide performance improvement',
                        ],
                    ],
                    'time_period_start' => date('Y-m-d', strtotime('-30 days')),
                    'time_period_end'   => date('Y-m-d'),
                    'confidence'        => 0.90,
                ];
            }
        }

        return $insights;
    }

    /**
     * Analyze inventory intelligence.
     *
     * @return array Inventory insights
     */
    private function analyzeInventoryIntelligence(): array
    {
        $insights = [];

        // Find slow-moving products (high stock, low sales)
        $slowMovers = $this->db->query('
            SELECT
                vp.product_id,
                vp.name as product_name,
                vp.variant_name,
                SUM(vi.inventory_level) as total_stock,
                COUNT(DISTINCT vi.outlet_id) as stores_with_stock,
                COALESCE(recent_sales.sale_count, 0) as sales_last_30_days,
                COALESCE(recent_sales.total_sold, 0) as units_sold_30_days
            FROM vend_products vp
            LEFT JOIN vend_inventory vi ON vp.product_id = vi.product_id
            LEFT JOIN (
                SELECT
                    product_id,
                    COUNT(*) as sale_count,
                    SUM(quantity) as total_sold
                FROM vend_sale_products
                WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY product_id
            ) recent_sales ON vp.product_id = recent_sales.product_id
            WHERE vp.active = 1
            GROUP BY vp.product_id, vp.name, vp.variant_name
            HAVING total_stock > 50 AND sales_last_30_days < 5
            ORDER BY total_stock DESC
            LIMIT 10
        ')->fetchAll();

        if (!empty($slowMovers)) {
            $productList = array_map(function ($p) {
                return $p['product_name'] . ($p['variant_name'] ? ' - ' . $p['variant_name'] : '');
            }, $slowMovers);

            // Ask AI for recommendations
            $aiRecommendations = AIService::ask(
                'We have ' . count($slowMovers) . ' slow-moving products with high stock levels: ' .
                implode(', ', array_slice($productList, 0, 5)) .
                '. Suggest strategies to move this inventory (promotions, bundling, redistribution, markdown timing).',
            );

            $insights[] = [
                'type'        => 'inventory_intelligence',
                'category'    => 'slow_movers',
                'priority'    => 'high',
                'title'       => count($slowMovers) . ' Products with Excess Slow-Moving Stock',
                'description' => 'These products have high inventory but very low sales velocity, tying up capital.',
                'data'        => [
                    'products'             => $slowMovers,
                    'total_units'          => array_sum(array_column($slowMovers, 'total_stock')),
                    'estimated_value_tied' => array_sum(array_map(function ($p) {
                        return $p['total_stock'] * 25; // Rough estimate
                    }, $slowMovers)),
                ],
                'ai_analysis'     => $aiRecommendations,
                'recommendations' => [
                    [
                        'action'      => 'Run targeted promotion',
                        'description' => '20% off slow-movers for 2 weeks',
                        'impact'      => 'Estimated 40-60% inventory reduction',
                    ],
                    [
                        'action'      => 'Redistribute stock',
                        'description' => 'Move excess inventory to higher-performing stores',
                        'impact'      => 'Better sales velocity, reduced waste',
                    ],
                    [
                        'action'      => 'Bundle with fast movers',
                        'description' => 'Create bundle deals pairing slow with popular items',
                        'impact'      => 'Increase perceived value, clear inventory',
                    ],
                ],
                'confidence' => 0.88,
            ];
        }

        // Find transfer bottlenecks
        $transferDelays = $this->db->query("
            SELECT
                st.from_outlet_id,
                from_outlet.name as from_outlet_name,
                st.to_outlet_id,
                to_outlet.name as to_outlet_name,
                COUNT(*) as transfer_count,
                AVG(TIMESTAMPDIFF(HOUR, st.created_at, st.received_at)) as avg_hours,
                MAX(TIMESTAMPDIFF(HOUR, st.created_at, st.received_at)) as max_hours
            FROM stock_transfers st
            JOIN vend_outlets from_outlet ON st.from_outlet_id = from_outlet.outlet_id
            JOIN vend_outlets to_outlet ON st.to_outlet_id = to_outlet.outlet_id
            WHERE st.created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                AND st.status = 'received'
                AND st.received_at IS NOT NULL
            GROUP BY st.from_outlet_id, from_outlet.name, st.to_outlet_id, to_outlet.name
            HAVING avg_hours > 48
            ORDER BY avg_hours DESC
            LIMIT 5
        ")->fetchAll();

        if (!empty($transferDelays)) {
            foreach ($transferDelays as $delay) {
                $insights[] = [
                    'type'        => 'operational_efficiency',
                    'category'    => 'transfer_delays',
                    'priority'    => 'medium',
                    'title'       => "Transfer Delays: {$delay['from_outlet_name']} → {$delay['to_outlet_name']}",
                    'description' => 'Average transfer time of ' . round($delay['avg_hours'], 1) .
                                   ' hours is significantly above target.',
                    'data'            => $delay,
                    'recommendations' => [
                        [
                            'action'      => 'Investigate root cause',
                            'description' => 'Check courier reliability, packing efficiency, receiving process',
                            'impact'      => 'Reduce delays by identifying bottleneck',
                        ],
                        [
                            'action'      => 'Set up alerts',
                            'description' => 'Notify both stores when transfer exceeds 36 hours',
                            'impact'      => 'Proactive issue resolution',
                        ],
                    ],
                    'confidence' => 0.82,
                ];
            }
        }

        return $insights;
    }

    /**
     * Analyze operational efficiency.
     *
     * @return array Operational insights
     */
    private function analyzeOperationalEfficiency(): array
    {
        $insights = [];

        // Analyze consignment receiving times
        $consignmentEfficiency = $this->db->query("
            SELECT
                AVG(TIMESTAMPDIFF(MINUTE, received_at, completed_at)) as avg_minutes,
                COUNT(*) as count,
                outlet_id,
                vo.name as outlet_name
            FROM vend_consignments vc
            JOIN vend_outlets vo ON vc.outlet_id = vo.outlet_id
            WHERE received_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND completed_at IS NOT NULL
                AND status = 'RECEIVED'
            GROUP BY outlet_id, vo.name
            HAVING avg_minutes > 60
            ORDER BY avg_minutes DESC
            LIMIT 5
        ")->fetchAll();

        if (!empty($consignmentEfficiency)) {
            $slowestStore = $consignmentEfficiency[0];

            // Use AI to suggest improvements
            $aiSuggestions = AIService::ask(
                "Our consignment receiving process at {$slowestStore['outlet_name']} takes an average of " .
                round($slowestStore['avg_minutes']) . ' minutes, which is slow. ' .
                'Suggest specific process improvements to reduce this time (automation, workflow changes, training).',
            );

            $insights[] = [
                'type'        => 'operational_efficiency',
                'category'    => 'consignment_processing',
                'priority'    => 'medium',
                'title'       => 'Slow Consignment Processing Detected',
                'description' => 'Several stores taking > 60 minutes average to receive consignments',
                'data'        => [
                    'slow_stores'     => $consignmentEfficiency,
                    'company_average' => round($slowestStore['avg_minutes'] * 0.65), // Assume best practice is 35% faster
                ],
                'ai_analysis'     => $aiSuggestions,
                'recommendations' => [
                    [
                        'action'      => 'Implement barcode scanning',
                        'description' => 'Auto-populate product data instead of manual entry',
                        'impact'      => 'Save 15-20 minutes per consignment',
                        'effort'      => 'medium',
                        'timeframe'   => '2 weeks',
                    ],
                    [
                        'action'      => 'Pre-fill from PO data',
                        'description' => 'Use purchase order data to speed up receiving',
                        'impact'      => 'Save 10-15 minutes per consignment',
                        'effort'      => 'low',
                        'timeframe'   => '1 week',
                    ],
                    [
                        'action'      => 'Staff training',
                        'description' => 'Share best practices from fastest stores',
                        'impact'      => 'Save 5-10 minutes per consignment',
                        'effort'      => 'low',
                        'timeframe'   => '3 days',
                    ],
                ],
                'expected_impact' => [
                    'time_savings_per_consignment' => 30,
                    'time_savings_per_week'        => 150, // 5 consignments/week avg
                    'roi_months'                   => 0.5,
                ],
                'confidence' => 0.87,
            ];
        }

        return $insights;
    }

    /**
     * Generate recommendations for sales recovery.
     *
     * @param float $changePercent How much sales changed
     * @param array $storeData     Store information
     *
     * @return array Recommendations
     */
    private function generateSalesRecoveryRecommendations(float $changePercent, array $storeData): array
    {
        $recommendations = [];

        // Severe decline
        if ($changePercent < -25) {
            $recommendations[] = [
                'action'      => 'Emergency intervention',
                'description' => 'Schedule immediate manager meeting to diagnose issues',
                'impact'      => 'Prevent further decline',
                'urgency'     => 'immediate',
            ];

            $recommendations[] = [
                'action'      => 'Deploy backup staff',
                'description' => 'Temporarily assign experienced staff from nearby stores',
                'impact'      => 'Stabilize operations, recover 60-70% of lost sales',
                'urgency'     => 'immediate',
            ];
        }

        // Moderate decline
        if ($changePercent < -15) {
            $recommendations[] = [
                'action'      => 'Review local competition',
                'description' => 'Check if new competitors opened or existing ones have promotions',
                'impact'      => 'Inform counter-strategy',
                'urgency'     => 'high',
            ];

            $recommendations[] = [
                'action'      => 'Analyze staff changes',
                'description' => 'Check for recent turnover, extended leave, or performance issues',
                'impact'      => 'Identify and address staffing gaps',
                'urgency'     => 'high',
            ];

            $recommendations[] = [
                'action'      => 'Run promotional campaign',
                'description' => 'Targeted local marketing to drive traffic',
                'impact'      => 'Increase store visibility and traffic',
                'urgency'     => 'medium',
            ];
        }

        return $recommendations;
    }

    /**
     * Save insight to database.
     *
     * @param array $insight Insight data
     *
     * @return int Insight ID
     */
    private function saveInsight(array $insight): int
    {
        return $this->db->insert('ai_business_insights', [
            'insight_type'      => $insight['type'],
            'category'          => $insight['category'],
            'priority'          => $insight['priority'],
            'title'             => $insight['title'],
            'description'       => $insight['description'],
            'insight_data'      => json_encode($insight['data']),
            'model_name'        => 'AIService v1.0',
            'confidence_score'  => $insight['confidence'] ?? 0.80,
            'reasoning'         => isset($insight['ai_analysis']) ? json_encode($insight['ai_analysis']) : null,
            'data_sources'      => json_encode(['vend_sales', 'vend_inventory', 'stock_transfers']),
            'time_period_start' => $insight['time_period_start'] ?? null,
            'time_period_end'   => $insight['time_period_end'] ?? null,
            'recommendations'   => json_encode($insight['recommendations'] ?? []),
            'expected_impact'   => json_encode($insight['expected_impact'] ?? []),
            'status'            => 'new',
            'expires_at'        => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
    }
}
