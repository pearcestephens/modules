<?php
/**
 * Business Intelligence Module - CONVERTED TO BASE TEMPLATE
 *
 * Now uses VapeUltra base template system with Chart.js integration
 *
 * @package CIS\Modules\BusinessIntelligence
 * @version 2.0.0 - ULTRA EDITION
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../base/Template/Renderer.php';
require_once __DIR__ . '/../base/middleware/MiddlewarePipeline.php';

use App\Template\Renderer;
use App\Middleware\MiddlewarePipeline;

// Create authenticated middleware pipeline
$pipeline = MiddlewarePipeline::createAuthenticated();

// Execute pipeline
$pipeline->handle($_REQUEST, function($request) {

    ob_start();
    ?>

    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Business Intelligence</h1>
                <p class="text-muted">Real-time analytics and insights</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline-primary active" data-period="today">Today</button>
                <button class="btn btn-outline-primary" data-period="week">Week</button>
                <button class="btn btn-outline-primary" data-period="month">Month</button>
                <button class="btn btn-outline-primary" data-period="year">Year</button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Total Revenue</p>
                                <h3 class="mb-0">$45,230</h3>
                                <small class="text-success">↑ 12.5% vs last period</small>
                            </div>
                            <div class="text-success fs-2">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Orders</p>
                                <h3 class="mb-0">1,234</h3>
                                <small class="text-success">↑ 8.2% vs last period</small>
                            </div>
                            <div class="text-primary fs-2">
                                <i class="bi bi-cart-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Avg Order Value</p>
                                <h3 class="mb-0">$36.67</h3>
                                <small class="text-danger">↓ 2.1% vs last period</small>
                            </div>
                            <div class="text-warning fs-2">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Top Store</p>
                                <h3 class="mb-0">Auckland</h3>
                                <small class="text-muted">$12,450 revenue</small>
                            </div>
                            <div class="text-info fs-2">
                                <i class="bi bi-trophy"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Revenue Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Product Mix</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="productMixChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Top Products</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="topProductsChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Store Performance</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="storePerformanceChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $moduleContent = ob_get_clean();

    // Render with VapeUltra base template
    $renderer = new Renderer();
    $renderer->render($moduleContent, [
        'title' => 'Business Intelligence - Vape Shed CIS Ultra',
        'class' => 'page-business-intelligence',
        'layout' => 'main',
        'scripts' => [
            '/modules/business-intelligence/assets/js/bi-charts.js',
        ],
        'styles' => [
            '/modules/business-intelligence/assets/css/bi-dashboard.css',
        ],
        'inline_scripts' => "
            VapeUltra.Core.registerModule('BI', {
                init: function() {
                    console.log('✅ Business Intelligence module initialized');
                    this.initCharts();
                },

                initCharts: function() {
                    // Revenue Trend Chart
                    VapeUltra.Charts.createLineChart('revenueChart', {
                        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        datasets: [{
                            label: 'Revenue',
                            data: [4200, 5300, 4800, 6100, 5700, 7200, 6800],
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    });

                    // Product Mix Chart
                    VapeUltra.Charts.createDoughnutChart('productMixChart', {
                        labels: ['Devices', 'Pods', 'Liquids', 'Accessories'],
                        datasets: [{
                            data: [35, 45, 15, 5],
                            backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444']
                        }]
                    });

                    // Top Products Chart
                    VapeUltra.Charts.createBarChart('topProductsChart', {
                        labels: ['JUUL Device', 'JUUL Pods', 'IQOS', 'Vuse', 'Logic'],
                        datasets: [{
                            label: 'Units Sold',
                            data: [450, 890, 320, 280, 150],
                            backgroundColor: '#6366f1'
                        }]
                    });

                    // Store Performance Chart
                    VapeUltra.Charts.createBarChart('storePerformanceChart', {
                        labels: ['Auckland', 'Wellington', 'Christchurch', 'Hamilton', 'Tauranga'],
                        datasets: [{
                            label: 'Revenue',
                            data: [12450, 9800, 8600, 7200, 6900],
                            backgroundColor: '#10b981'
                        }]
                    });
                }
            });
        ",
        'nav_items' => [
            'bi' => [
                'title' => 'Business Intelligence',
                'items' => [
                    ['icon' => 'graph-up', 'label' => 'Dashboard', 'href' => '/modules/business-intelligence/', 'badge' => null],
                    ['icon' => 'bar-chart', 'label' => 'Sales Analytics', 'href' => '/modules/business-intelligence/sales.php', 'badge' => null],
                    ['icon' => 'box-seam', 'label' => 'Inventory Analytics', 'href' => '/modules/business-intelligence/inventory.php', 'badge' => null],
                    ['icon' => 'people', 'label' => 'Customer Analytics', 'href' => '/modules/business-intelligence/customers.php', 'badge' => null],
                ]
            ]
        ]
    ]);

});
