<?php
declare(strict_types=1);

/**
 * AI Insights Dashboard - View and manage AI-powered recommendations
 *
 * Displays AI-generated recommendations for freight optimization, carrier selection,
 * cost predictions, and delivery time estimates. Shows cost savings analysis,
 * confidence scoring, and allows accepting/dismissing recommendations.
 *
 * Features:
 * - Summary cards with total savings and optimization metrics
 * - Filterable recommendations list with DataTables
 * - Cost savings chart (Chart.js - last 12 months)
 * - Recommendation details modal with reasoning
 * - Accept/dismiss bulk actions
 * - Real-time updates via AJAX (30 sec interval)
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

use CIS\Services\Consignments\AI\AIService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pageTitle = 'AI Insights & Recommendations';

// Initialize AI Service
try {
    $aiService = new AIService($db);
} catch (Exception $e) {
    die("Failed to initialize AI Service: " . htmlspecialchars($e->getMessage()));
}

// Get summary statistics
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_recommendations,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_count,
        COUNT(CASE WHEN status = 'dismissed' THEN 1 END) as dismissed_count,
        AVG(confidence_score) as avg_confidence
    FROM consignment_ai_insights
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate cost savings
$stmt = $db->prepare("
    SELECT
        SUM(
            CASE
                WHEN insight_type = 'carrier_recommendation'
                AND status = 'accepted'
                THEN JSON_EXTRACT(data, '$.estimated_cost')
            END
        ) as total_ai_cost,
        SUM(
            CASE
                WHEN insight_type = 'carrier_recommendation'
                AND status = 'accepted'
                THEN JSON_EXTRACT(data, '$.alternatives[0].estimated_cost')
            END
        ) as total_baseline_cost
    FROM consignment_ai_insights
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND status = 'accepted'
");
$stmt->execute();
$costData = $stmt->fetch(PDO::FETCH_ASSOC);

$totalSavings = max(0, ($costData['total_baseline_cost'] ?? 0) - ($costData['total_ai_cost'] ?? 0));
$optimizationRate = $summary['total_recommendations'] > 0
    ? ($summary['accepted_count'] / $summary['total_recommendations']) * 100
    : 0;

// Get monthly savings for chart (last 12 months)
$stmt = $db->prepare("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(
            JSON_EXTRACT(data, '$.alternatives[0].estimated_cost') -
            JSON_EXTRACT(data, '$.estimated_cost')
        ) as monthly_savings
    FROM consignment_ai_insights
    WHERE insight_type = 'carrier_recommendation'
    AND status = 'accepted'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute();
$monthlySavings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format for JavaScript
$chartLabels = [];
$chartData = [];
foreach ($monthlySavings as $data) {
    $date = new DateTime($data['month'] . '-01');
    $chartLabels[] = $date->format('M Y');
    $chartData[] = round((float) $data['monthly_savings'], 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - CIS</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        .insights-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .insight-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .insight-card:hover {
            transform: translateY(-5px);
        }

        .insight-card.savings {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .insight-card.optimization {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .insight-card.confidence {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .insight-card.active {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .insight-card-icon {
            font-size: 2.5rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .insight-card-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }

        .insight-card-label {
            font-size: 0.875rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filters-panel {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            position: relative;
            height: 400px;
        }

        .recommendations-table {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .confidence-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .confidence-high {
            background: #d4edda;
            color: #155724;
        }

        .confidence-medium {
            background: #fff3cd;
            color: #856404;
        }

        .confidence-low {
            background: #f8d7da;
            color: #721c24;
        }

        .insight-type-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .type-carrier {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-box {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .type-cost {
            background: #e8f5e9;
            color: #388e3c;
        }

        .bulk-actions-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
            align-items: center;
            justify-content: space-between;
        }

        .bulk-actions-bar.active {
            display: flex;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .recommendation-detail {
            padding: 1.5rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .detail-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .detail-item-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .detail-item-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #212529;
        }

        .reasoning-box {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }

        .confidence-breakdown {
            margin: 1.5rem 0;
        }

        .confidence-bar {
            background: #e9ecef;
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .confidence-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            transition: width 0.5s ease-out;
        }

        .alternatives-list {
            margin-top: 1.5rem;
        }

        .alternative-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .auto-refresh-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #667eea;
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 25px;
            font-size: 0.875rem;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            display: none;
            align-items: center;
            gap: 0.5rem;
        }

        .auto-refresh-indicator.active {
            display: flex;
        }

        .pulse {
            width: 8px;
            height: 8px;
            background: #38ef7d;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/views/layouts/header.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">
                    <i class="fas fa-robot me-2"></i>
                    AI Insights & Recommendations
                </h1>
                <p class="text-muted mb-0">
                    AI-powered freight optimization and carrier recommendations
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-outline-primary" id="refresh-insights-btn">
                    <i class="fas fa-sync-alt me-2"></i>
                    Refresh
                </button>
                <button type="button" class="btn btn-primary" id="auto-refresh-toggle">
                    <i class="fas fa-clock me-2"></i>
                    Auto-Refresh: OFF
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="insights-summary">
            <div class="insight-card savings">
                <div class="insight-card-icon">
                    <i class="fas fa-piggy-bank"></i>
                </div>
                <div class="insight-card-value" id="total-savings">
                    $<?= number_format($totalSavings, 2) ?>
                </div>
                <div class="insight-card-label">Total Cost Savings (30 days)</div>
            </div>

            <div class="insight-card optimization">
                <div class="insight-card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="insight-card-value" id="optimization-rate">
                    <?= number_format($optimizationRate, 1) ?>%
                </div>
                <div class="insight-card-label">Optimization Rate</div>
            </div>

            <div class="insight-card confidence">
                <div class="insight-card-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <div class="insight-card-value" id="avg-confidence">
                    <?= number_format(($summary['avg_confidence'] ?? 0) * 100, 0) ?>%
                </div>
                <div class="insight-card-label">Avg Confidence Score</div>
            </div>

            <div class="insight-card active">
                <div class="insight-card-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="insight-card-value" id="active-recommendations">
                    <?= $summary['active_count'] ?>
                </div>
                <div class="insight-card-label">Active Recommendations</div>
            </div>
        </div>

        <!-- Cost Savings Chart -->
        <div class="chart-container">
            <h5 class="mb-3">
                <i class="fas fa-chart-area me-2"></i>
                Monthly Cost Savings (Last 12 Months)
            </h5>
            <canvas id="savings-chart"></canvas>
        </div>

        <!-- Filters Panel -->
        <div class="filters-panel">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Recommendation Type</label>
                    <select class="form-select" id="filter-type">
                        <option value="">All Types</option>
                        <option value="carrier_recommendation">Carrier Selection</option>
                        <option value="box_optimization">Box Optimization</option>
                        <option value="cost_prediction">Cost Prediction</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Confidence Level</label>
                    <select class="form-select" id="filter-confidence">
                        <option value="">All Confidence Levels</option>
                        <option value="high">High (>80%)</option>
                        <option value="medium">Medium (60-80%)</option>
                        <option value="low">Low (<60%)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filter-status">
                        <option value="">All Statuses</option>
                        <option value="active" selected>Active</option>
                        <option value="accepted">Accepted</option>
                        <option value="dismissed">Dismissed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="filter-search" placeholder="PO number...">
                </div>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar" id="bulk-actions-bar">
            <div>
                <strong id="selected-count">0</strong> recommendations selected
            </div>
            <div>
                <button type="button" class="btn btn-light btn-sm me-2" id="bulk-accept-btn">
                    <i class="fas fa-check me-1"></i>
                    Accept Selected
                </button>
                <button type="button" class="btn btn-outline-light btn-sm me-2" id="bulk-dismiss-btn">
                    <i class="fas fa-times me-1"></i>
                    Dismiss Selected
                </button>
                <button type="button" class="btn btn-outline-light btn-sm" id="clear-selection-btn">
                    Clear Selection
                </button>
            </div>
        </div>

        <!-- Recommendations Table -->
        <div class="recommendations-table">
            <h5 class="mb-3">
                <i class="fas fa-list me-2"></i>
                Recommendations
            </h5>
            <table class="table table-hover" id="recommendations-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="select-all">
                        </th>
                        <th>PO Number</th>
                        <th>Type</th>
                        <th>Recommendation</th>
                        <th>Confidence</th>
                        <th>Potential Savings</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated via AJAX -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recommendation Details Modal -->
    <div class="modal fade" id="recommendation-modal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>
                        Recommendation Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body recommendation-detail" id="recommendation-detail">
                    <!-- Populated via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                    <button type="button" class="btn btn-danger" id="modal-dismiss-btn">
                        <i class="fas fa-times me-2"></i>
                        Dismiss
                    </button>
                    <button type="button" class="btn btn-success" id="modal-accept-btn">
                        <i class="fas fa-check me-2"></i>
                        Accept Recommendation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Auto-Refresh Indicator -->
    <div class="auto-refresh-indicator" id="auto-refresh-indicator">
        <div class="pulse"></div>
        <span>Auto-refreshing...</span>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- AI Insights JavaScript -->
    <!-- Interaction logger (client-side) -->
    <script src="js/interaction-logger.js"></script>
    <!-- AI Insights JavaScript -->
    <script src="js/ai.js"></script>

    <script>
        // Initialize Chart.js
        const chartLabels = <?= json_encode($chartLabels) ?>;
        const chartData = <?= json_encode($chartData) ?>;

        const ctx = document.getElementById('savings-chart').getContext('2d');
        const savingsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Cost Savings ($)',
                    data: chartData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return 'Savings: $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });

        // Initialize AI module when DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof POAIInsights !== 'undefined') {
                POAIInsights.init();
            }
        });
    </script>
</body>
</html>
