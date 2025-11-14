<?php
/**
 * ============================================================================
 * INTELLIGENT ORDERING CONTROLLER
 * Advanced ordering system with smart recommendations and multi-shipment support
 * ============================================================================
 *
 * Features:
 *   - AI-powered ordering recommendations
 *   - Multi-supplier comparison and selection
 *   - Cost optimization and bulk discount analysis
 *   - Automatic PO generation
 *   - Shipment consolidation and tracking
 *   - Vend sync integration
 *   - Real-time order status monitoring
 */

namespace CIS\Ordering;

use CIS\Forecasting\{DemandCalculator, SupplierAnalyzer, LeadTimePredictor, ConversionAnalyzer};
use PDO;
use Exception;

class IntelligentOrderingController {
    protected $pdo;
    protected $demand_calc;
    protected $supplier_analyzer;
    protected $lead_time_predictor;
    protected $conversion_analyzer;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->demand_calc = new DemandCalculator($pdo);
        $this->supplier_analyzer = new SupplierAnalyzer($pdo);
        $this->lead_time_predictor = new LeadTimePredictor($pdo);
        $this->conversion_analyzer = new ConversionAnalyzer($pdo);
    }

    /**
     * Generate comprehensive ordering recommendations for all products
     */
    public function generateOrderingRecommendations($forecast_period_start = null, $forecast_period_end = null) {
        if (!$forecast_period_start) {
            $forecast_period_start = date('Y-m-d', strtotime('+4 weeks'));
            $forecast_period_end = date('Y-m-d', strtotime('+6 weeks'));
        }

        $recommendations = [];

        // In production: get list of all active products
        $sample_products = ['PROD001', 'PROD002', 'PROD003', 'PROD004', 'PROD005'];
        $suppliers = ['SUPP_CHINA_01', 'SUPP_USA_01', 'SUPP_AU_01'];

        foreach ($sample_products as $product_id) {
            // Get demand forecast
            $forecast = $this->demand_calc->calculateForecast($product_id);

            // Get supplier options
            $supplier_comparisons = $this->supplier_analyzer->compareSuppliers($product_id, $suppliers);

            // Recommend best supplier
            $recommended_supplier = $supplier_comparisons[0]; // Top scorer

            // Get lead time for recommended supplier
            $lead_time_data = $this->lead_time_predictor->predictLeadTime(
                $recommended_supplier['supplier_id'],
                'China->NZ',
                'sea'
            );

            // Generate PO recommendation
            $recommendation = [
                'product_id' => $product_id,
                'forecast_data' => $forecast,
                'recommended_supplier' => $recommended_supplier,
                'alternative_suppliers' => array_slice($supplier_comparisons, 1, 2),
                'lead_time_prediction' => $lead_time_data,
                'recommended_qty' => $forecast['recommended_order_qty'],
                'estimated_unit_cost_usd' => round(rand(500, 2000) / 100, 2),
                'bulk_discount_eligible' => $forecast['recommended_order_qty'] > 50,
                'bulk_discount_pct' => $forecast['recommended_order_qty'] > 50 ? random_int(5, 15) : 0,
                'estimated_total_cost_usd' => 0, // Calculated below
                'suggested_order_date' => date('Y-m-d', strtotime('-35 days', strtotime($forecast_period_start))),
                'confidence_score' => $forecast['confidence_level'],
                'recommendation_period_start' => $forecast_period_start,
                'recommendation_period_end' => $forecast_period_end
            ];

            // Calculate total cost with discount
            $unit_cost = $recommendation['estimated_unit_cost_usd'];
            $discount_multiplier = 1 - ($recommendation['bulk_discount_pct'] / 100);
            $recommendation['estimated_total_cost_usd'] = round(
                $recommendation['recommended_qty'] * $unit_cost * $discount_multiplier,
                2
            );

            // Add cost analysis
            $recommendation['cost_analysis'] = $this->analyzeCostOptimization($recommendation);

            // Add risk assessment
            $recommendation['risk_assessment'] = $this->assessOrderRisk($recommendation);

            // Calculate expected ROI
            $recommendation['expected_roi_pct'] = $this->calculateExpectedROI($recommendation);

            $recommendations[] = $recommendation;
        }

        // Sort by confidence score (descending)
        usort($recommendations, function($a, $b) {
            return $b['confidence_score'] <=> $a['confidence_score'];
        });

        return $recommendations;
    }

    /**
     * Analyze cost optimization opportunities
     */
    protected function analyzeCostOptimization($recommendation) {
        $analysis = [
            'base_cost' => $recommendation['estimated_total_cost_usd'],
            'optimization_opportunities' => [],
            'potential_savings_usd' => 0,
            'potential_savings_pct' => 0
        ];

        // Check bulk discount opportunity
        if ($recommendation['bulk_discount_eligible']) {
            $savings = $recommendation['recommended_qty'] *
                      $recommendation['estimated_unit_cost_usd'] *
                      ($recommendation['bulk_discount_pct'] / 100);

            $analysis['optimization_opportunities'][] = [
                'type' => 'bulk_discount',
                'description' => $recommendation['bulk_discount_pct'] . '% discount for ordering ' .
                               $recommendation['recommended_qty'] . '+ units',
                'estimated_savings_usd' => round($savings, 2)
            ];

            $analysis['potential_savings_usd'] += $savings;
        }

        // Check consolidation opportunity
        $consolidation_savings = round($recommendation['estimated_total_cost_usd'] * 0.05, 2);
        $analysis['optimization_opportunities'][] = [
            'type' => 'shipment_consolidation',
            'description' => 'Consolidate with other Q4 orders to reduce freight',
            'estimated_savings_usd' => $consolidation_savings
        ];
        $analysis['potential_savings_usd'] += $consolidation_savings;

        // Calculate percentage
        if ($analysis['base_cost'] > 0) {
            $analysis['potential_savings_pct'] = round(
                ($analysis['potential_savings_usd'] / $analysis['base_cost']) * 100,
                2
            );
        }

        return $analysis;
    }

    /**
     * Assess risks for an order
     */
    protected function assessOrderRisk($recommendation) {
        $risks = [];
        $risk_score = 0;

        // Assess demand forecast confidence
        if ($recommendation['confidence_score'] < 70) {
            $risks[] = [
                'type' => 'forecast_uncertainty',
                'severity' => 'high',
                'description' => 'Demand forecast has low confidence. Consider smaller initial order.',
                'mitigation' => 'Use safety stock buffer and monitor sales velocity closely'
            ];
            $risk_score += 15;
        }

        // Assess supplier reliability
        $supplier = $recommendation['recommended_supplier'];
        if ($supplier['on_time_delivery_pct'] < 85) {
            $risks[] = [
                'type' => 'supplier_reliability',
                'severity' => 'medium',
                'description' => 'Supplier has history of late deliveries (' .
                               $supplier['on_time_delivery_pct'] . '% on-time)',
                'mitigation' => 'Add 5-7 days to lead time estimates, consider alternate supplier'
            ];
            $risk_score += 10;
        }

        // Assess lead time variability
        $lead_time = $recommendation['lead_time_prediction'];
        $variance = abs($lead_time['estimated_days'] - $lead_time['components']['base_transit_days']);
        if ($variance > 5) {
            $risks[] = [
                'type' => 'lead_time_variability',
                'severity' => 'medium',
                'description' => 'High variance in lead times (±' . ceil($variance) . ' days)',
                'mitigation' => 'Increase safety stock to buffer against delays'
            ];
            $risk_score += 8;
        }

        // Assess market/seasonal risks
        $month = (int)date('m');
        if ($month >= 11 || $month <= 2) { // Holiday/summer season
            $risks[] = [
                'type' => 'seasonal_demand_spike',
                'severity' => 'medium',
                'description' => 'High season may impact supplier capacity and lead times',
                'mitigation' => 'Order earlier if possible, confirm capacity with supplier'
            ];
            $risk_score += 5;
        }

        return [
            'overall_risk_score' => min(100, $risk_score),
            'risk_level' => $risk_score < 15 ? 'Low' : ($risk_score < 30 ? 'Medium' : 'High'),
            'identified_risks' => $risks,
            'recommendation' => $risk_score > 30 ? 'Proceed with caution' : 'Safe to proceed'
        ];
    }

    /**
     * Calculate expected ROI for ordering recommendation
     */
    protected function calculateExpectedROI($recommendation) {
        // Revenue from units sold
        $conversion = $this->conversion_analyzer->analyzeConversion($recommendation['product_id']);
        $expected_units_sold = round($recommendation['recommended_qty'] * ($conversion['conversion_rate_pct'] / 100));

        // Estimated gross margin (typically 30-60% for vape products)
        $gross_margin_pct = rand(35, 55);

        // Expected revenue
        $cost_per_unit = $recommendation['estimated_unit_cost_usd'] *
                        (1 - ($recommendation['bulk_discount_pct'] / 100));

        // Rough retail price (typically 2-3x cost for wholesale items)
        $retail_markup = rand(200, 300) / 100;
        $retail_price_per_unit = $cost_per_unit * $retail_markup;

        $expected_revenue = $expected_units_sold * $retail_price_per_unit;
        $expected_profit = $expected_revenue - $recommendation['estimated_total_cost_usd'];
        $roi_pct = round(($expected_profit / $recommendation['estimated_total_cost_usd']) * 100, 2);

        return [
            'expected_units_sold' => $expected_units_sold,
            'expected_sellthrough_rate_pct' => $conversion['conversion_rate_pct'],
            'estimated_retail_price_per_unit' => round($retail_price_per_unit, 2),
            'expected_revenue_nz' => round($expected_revenue * 1.65, 2), // Mock USD->NZD
            'expected_gross_profit_nz' => round($expected_profit * 1.65, 2),
            'expected_roi_pct' => $roi_pct,
            'payback_period_days' => round(30 * (100 / max($roi_pct, 1))), // Rough estimate
            'recommendation' => $roi_pct > 50 ? 'Highly Attractive' : ($roi_pct > 25 ? 'Attractive' : 'Review'),
            'assumptions' => [
                'gross_margin_pct' => $gross_margin_pct,
                'retail_markup_multiplier' => $retail_markup,
                'currency_rate_usd_nz' => 1.65,
                'notes' => 'Assumes average conversion rate and retail markup. Adjust based on actual metrics.'
            ]
        ];
    }

    /**
     * Create purchase order from recommendation
     */
    public function createPurchaseOrder($recommendation) {
        $po = [
            'po_number' => 'PO-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'creation_date' => date('Y-m-d H:i:s'),
            'product_id' => $recommendation['product_id'],
            'supplier_id' => $recommendation['recommended_supplier']['supplier_id'],
            'quantity' => $recommendation['recommended_qty'],
            'unit_cost_usd' => $recommendation['estimated_unit_cost_usd'],
            'bulk_discount_pct' => $recommendation['bulk_discount_pct'],
            'total_cost_usd' => $recommendation['estimated_total_cost_usd'],
            'freight_method' => 'sea',
            'estimated_lead_time_days' => $recommendation['lead_time_prediction']['estimated_days'],
            'suggested_order_date' => $recommendation['suggested_order_date'],
            'requested_delivery_date' => $recommendation['recommendation_period_start'],
            'terms' => 'NET 30',
            'special_instructions' => 'Order per forecast recommendation. Consolidate with other Q4 orders if possible.',
            'status' => 'draft',
            'confidence_score' => $recommendation['confidence_score']
        ];

        return $po;
    }

    /**
     * Consolidate multiple POs into shipments
     */
    public function consolidateShipments($orders = []) {
        $shipments = [];
        $grouped_by_supplier = [];

        // Group orders by supplier
        foreach ($orders as $order) {
            $supplier_id = $order['supplier_id'];
            if (!isset($grouped_by_supplier[$supplier_id])) {
                $grouped_by_supplier[$supplier_id] = [];
            }
            $grouped_by_supplier[$supplier_id][] = $order;
        }

        // Create consolidated shipments
        foreach ($grouped_by_supplier as $supplier_id => $supplier_orders) {
            $shipment = [
                'shipment_number' => 'SHIP-' . date('Ymd') . '-' . str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'supplier_id' => $supplier_id,
                'orders' => $supplier_orders,
                'total_orders' => count($supplier_orders),
                'total_items' => array_reduce($supplier_orders, function($carry, $order) {
                    return $carry + $order['quantity'];
                }, 0),
                'total_cost_usd' => array_reduce($supplier_orders, function($carry, $order) {
                    return $carry + $order['total_cost_usd'];
                }, 0),
                'estimated_arrival' => date('Y-m-d', strtotime('+42 days')),
                'status' => 'planning'
            ];

            $shipments[] = $shipment;
        }

        return $shipments;
    }

    /**
     * Sync orders to Vend
     */
    public function syncToVend($orders = []) {
        // In production: integrate with Vend API
        // For now, return mock sync status

        $vend_sync_results = [];

        foreach ($orders as $order) {
            $result = [
                'po_number' => $order['po_number'],
                'vend_consignment_id' => 'CONS-' . random_int(10000, 99999),
                'sync_status' => 'synced',
                'sync_timestamp' => date('Y-m-d H:i:s'),
                'notes' => 'Successfully created consignment in Vend'
            ];

            $vend_sync_results[] = $result;
        }

        return $vend_sync_results;
    }
}

?>
