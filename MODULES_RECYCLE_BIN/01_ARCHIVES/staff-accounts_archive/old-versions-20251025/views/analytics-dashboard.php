/**
 * Analytics Dashboard for Employee Mapping System
 * 
 * Displays comprehensive analytics including:
 * - Mapping success rates and trends
 * - Performance metrics and benchmarks
 * - Historical data analysis
 * - Interactive charts and visualizations
 * 
 * @package CIS\StaffAccounts\Views
 * @version 1.0.0
 */

// Prevent direct access
if (!isset($bootstrap) || $bootstrap !== true) {
    include '../bootstrap.php';
}
?>

<!-- Analytics Dashboard Container -->
<div id="analytics-dashboard" class="mt-3">
    
    <!-- Analytics Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">
                        <i class="fas fa-chart-line text-info"></i>
                        Analytics Dashboard
                    </h4>
                    <p class="text-muted mb-0">Comprehensive mapping performance and trend analysis</p>
                </div>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="refreshAnalytics">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="exportAnalytics">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="showAnalyticsHelp()">
                        <i class="fas fa-question-circle"></i> Help
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Time Range Selector -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-light">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="btn-group btn-group-sm" role="group" id="timeRangeSelector">
                                <button type="button" class="btn btn-outline-primary active" data-range="7">7 Days</button>
                                <button type="button" class="btn btn-outline-primary" data-range="30">30 Days</button>
                                <button type="button" class="btn btn-outline-primary" data-range="90">90 Days</button>
                                <button type="button" class="btn btn-outline-primary" data-range="365">1 Year</button>
                                <button type="button" class="btn btn-outline-primary" data-range="all">All Time</button>
                            </div>
                        </div>
                        <div class="col-md-4 text-right">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i>
                                Last updated: <span id="lastUpdated">Loading...</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Performance Indicators -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-success h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Mapping Success Rate
                            </div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800" id="successRate">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="progress progress-sm mr-2">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             id="successRateBar" style="width: 0%" aria-valuenow="0" 
                                             aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-primary h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Auto-Match Accuracy
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="autoMatchAccuracy">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-magic fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-warning h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Avg. Processing Time
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="avgProcessingTime">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-info h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Amount Processed
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="amountProcessed">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <!-- Mapping Trends Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-area"></i> Mapping Trends Over Time
                    </h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" data-toggle="dropdown">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                            <div class="dropdown-header">Chart Options:</div>
                            <a class="dropdown-item" href="#" onclick="downloadChart('trendsChart')">Download Chart</a>
                            <a class="dropdown-item" href="#" onclick="toggleChartType('trendsChart')">Toggle View</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="trendsChart" style="height: 320px;"></canvas>
                    </div>
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Shows daily mapping activity and success rates over the selected time period
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mapping Methods Pie Chart -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie"></i> Mapping Methods
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="methodsChart" style="height: 245px;"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-primary"></i> Auto-Match
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-success"></i> Manual
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-info"></i> Bulk
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row mb-4">
        <!-- Performance Metrics -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-tachometer-alt"></i> Performance Metrics
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="performanceChart" style="height: 250px;"></canvas>
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Processing time, accuracy, and success rates comparison
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Analysis -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-exclamation-triangle"></i> Error Analysis
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="errorChart" style="height: 250px;"></canvas>
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Common mapping errors and resolution patterns
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables Row -->
    <div class="row mb-4">
        <!-- Top Performers Table -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-trophy"></i> Top Performing Stores
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="topStoresTable">
                            <thead>
                                <tr>
                                    <th>Store</th>
                                    <th>Mappings</th>
                                    <th>Success Rate</th>
                                    <th>Avg. Time</th>
                                </tr>
                            </thead>
                            <tbody id="topStoresTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        <i class="fas fa-spinner fa-spin"></i> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i> Recent Mapping Activity
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="recentActivityTable">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Employee</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="recentActivityTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        <i class="fas fa-spinner fa-spin"></i> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Analytics Row -->
    <div class="row mb-4">
        <!-- Trend Analysis -->
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Advanced Trend Analysis
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <canvas id="advancedTrendsChart" style="height: 300px;"></canvas>
                        </div>
                        <div class="col-md-4">
                            <div class="analytics-insights">
                                <h6 class="text-info mb-3">
                                    <i class="fas fa-lightbulb"></i> Key Insights
                                </h6>
                                <div id="analyticsInsights">
                                    <div class="insight-item">
                                        <i class="fas fa-spinner fa-spin text-muted"></i>
                                        <span class="text-muted">Analyzing trends...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health Indicators -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-heartbeat"></i>
                        System Health Indicators
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="health-indicator">
                                <div class="health-circle bg-success" id="apiHealth">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div class="mt-2">
                                    <strong>API Health</strong>
                                    <br><small class="text-muted" id="apiHealthStatus">Operational</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="health-indicator">
                                <div class="health-circle bg-success" id="dbHealth">
                                    <i class="fas fa-database text-white"></i>
                                </div>
                                <div class="mt-2">
                                    <strong>Database</strong>
                                    <br><small class="text-muted" id="dbHealthStatus">Connected</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="health-indicator">
                                <div class="health-circle bg-warning" id="queueHealth">
                                    <i class="fas fa-tasks text-white"></i>
                                </div>
                                <div class="mt-2">
                                    <strong>Queue</strong>
                                    <br><small class="text-muted" id="queueHealthStatus">Busy</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="health-indicator">
                                <div class="health-circle bg-success" id="mappingHealth">
                                    <i class="fas fa-users text-white"></i>
                                </div>
                                <div class="mt-2">
                                    <strong>Mapping Service</strong>
                                    <br><small class="text-muted" id="mappingHealthStatus">Active</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Help Modal -->
<div class="modal fade" id="analyticsHelpModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-question-circle text-info"></i>
                    Analytics Dashboard Help
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-chart-line text-primary"></i> Understanding Charts</h6>
                        <ul class="list-unstyled">
                            <li><strong>Mapping Trends:</strong> Shows daily mapping activity over time</li>
                            <li><strong>Methods Distribution:</strong> Breakdown of auto vs manual mappings</li>
                            <li><strong>Performance Metrics:</strong> Processing time and accuracy trends</li>
                            <li><strong>Error Analysis:</strong> Common issues and resolution patterns</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-tachometer-alt text-success"></i> Key Metrics</h6>
                        <ul class="list-unstyled">
                            <li><strong>Success Rate:</strong> Percentage of successful mappings</li>
                            <li><strong>Auto-Match Accuracy:</strong> AI matching precision</li>
                            <li><strong>Processing Time:</strong> Average time per mapping</li>
                            <li><strong>Amount Processed:</strong> Total dollar value mapped</li>
                        </ul>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6><i class="fas fa-lightbulb text-warning"></i> Tips for Better Performance</h6>
                        <div class="alert alert-info">
                            <ul class="mb-0">
                                <li>Use auto-match when confidence score is above 85%</li>
                                <li>Manual review recommended for complex cases</li>
                                <li>Monitor error patterns to improve accuracy</li>
                                <li>Regular system health checks ensure optimal performance</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.analytics-insights .insight-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid #eee;
}

.analytics-insights .insight-item:last-child {
    border-bottom: none;
}

.health-indicator {
    padding: 1rem 0;
}

.health-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 1.5rem;
}

.progress-sm {
    height: 0.5rem;
}

.chart-area, .chart-pie {
    position: relative;
    height: 320px;
    width: 100%;
}

.border-left-primary {
    border-left: 4px solid #4e73df!important;
}

.border-left-success {
    border-left: 4px solid #1cc88a!important;
}

.border-left-info {
    border-left: 4px solid #36b9cc!important;
}

.border-left-warning {
    border-left: 4px solid #f6c23e!important;
}

.text-xs {
    font-size: 0.7rem;
}
</style>