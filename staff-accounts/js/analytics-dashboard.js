/**
 * Analytics Dashboard JavaScript
 * 
 * Handles chart rendering, data visualization, and interactive analytics
 * for the Employee Mapping System
 * 
 * @package CIS\StaffAccounts\JS
 * @version 1.0.0
 */

// Global variables
let analyticsData = {};
let charts = {};
let currentTimeRange = 7;
let refreshInterval = null;

/**
 * Initialize analytics dashboard
 */
function initAnalyticsDashboard() {
    console.log('Initializing Analytics Dashboard...');
    
    // Set up event listeners
    setupAnalyticsEventListeners();
    
    // Load initial data
    loadAnalyticsData();
    
    // Set up auto-refresh
    setupAutoRefresh();
    
    // Initialize charts
    initializeCharts();
}

/**
 * Set up event listeners
 */
function setupAnalyticsEventListeners() {
    // Time range selector
    $('#timeRangeSelector button').on('click', function() {
        const range = $(this).data('range');
        selectTimeRange(range);
    });
    
    // Refresh button
    $('#refreshAnalytics').on('click', function() {
        loadAnalyticsData(true);
    });
    
    // Export button
    $('#exportAnalytics').on('click', function() {
        exportAnalyticsData();
    });
}

/**
 * Select time range and refresh data
 */
function selectTimeRange(range) {
    currentTimeRange = range;
    
    // Update button states
    $('#timeRangeSelector button').removeClass('active');
    $(`#timeRangeSelector button[data-range="${range}"]`).addClass('active');
    
    // Reload data
    loadAnalyticsData();
}

/**
 * Load analytics data from API
 */
async function loadAnalyticsData(forceRefresh = false) {
    try {
        showAnalyticsLoading();
        
        const params = new URLSearchParams({
            action: 'analytics',
            range: currentTimeRange,
            force_refresh: forceRefresh ? 'true' : 'false'
        });
        
        const response = await fetch(`api/employee-mapping.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            analyticsData = data.data;
            updateAnalyticsDashboard(analyticsData);
            updateLastRefreshTime();
        } else {
            throw new Error(data.error || 'Failed to load analytics data');
        }
        
    } catch (error) {
        console.error('Error loading analytics:', error);
        showAnalyticsError('Failed to load analytics: ' + error.message);
    } finally {
        hideAnalyticsLoading();
    }
}

/**
 * Update the entire analytics dashboard
 */
function updateAnalyticsDashboard(data) {
    // Update KPI cards
    updateKPICards(data.kpis);
    
    // Update charts
    updateTrendsChart(data.trends);
    updateMethodsChart(data.methods);
    updatePerformanceChart(data.performance);
    updateErrorChart(data.errors);
    updateAdvancedTrendsChart(data.advanced_trends);
    
    // Update tables
    updateTopStoresTable(data.top_stores);
    updateRecentActivityTable(data.recent_activity);
    
    // Update insights
    updateAnalyticsInsights(data.insights);
    
    // Update system health
    updateSystemHealth(data.health);
}

/**
 * Update KPI cards
 */
function updateKPICards(kpis) {
    // Success Rate
    const successRate = parseFloat(kpis.success_rate || 0);
    $('#successRate').html(`${successRate.toFixed(1)}%`);
    $('#successRateBar').css('width', `${successRate}%`).attr('aria-valuenow', successRate);
    
    // Auto-Match Accuracy
    const autoAccuracy = parseFloat(kpis.auto_match_accuracy || 0);
    $('#autoMatchAccuracy').html(`${autoAccuracy.toFixed(1)}%`);
    
    // Average Processing Time
    const avgTime = parseFloat(kpis.avg_processing_time || 0);
    $('#avgProcessingTime').html(`${avgTime.toFixed(1)}s`);
    
    // Amount Processed
    const amountProcessed = parseFloat(kpis.amount_processed || 0);
    $('#amountProcessed').html(`$${amountProcessed.toLocaleString()}`);
}

/**
 * Initialize all charts
 */
function initializeCharts() {
    // Initialize empty charts with proper context
    const chartConfig = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        }
    };
    
    // Trends Chart
    const trendsCtx = document.getElementById('trendsChart');
    if (trendsCtx) {
        charts.trends = new Chart(trendsCtx, {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                ...chartConfig,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Mappings'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
    }
    
    // Methods Pie Chart
    const methodsCtx = document.getElementById('methodsChart');
    if (methodsCtx) {
        charts.methods = new Chart(methodsCtx, {
            type: 'doughnut',
            data: { labels: [], datasets: [] },
            options: {
                ...chartConfig,
                plugins: {
                    ...chartConfig.plugins,
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    // Performance Chart
    const performanceCtx = document.getElementById('performanceChart');
    if (performanceCtx) {
        charts.performance = new Chart(performanceCtx, {
            type: 'radar',
            data: { labels: [], datasets: [] },
            options: {
                ...chartConfig,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
    
    // Error Chart
    const errorCtx = document.getElementById('errorChart');
    if (errorCtx) {
        charts.error = new Chart(errorCtx, {
            type: 'bar',
            data: { labels: [], datasets: [] },
            options: {
                ...chartConfig,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Error Count'
                        }
                    }
                }
            }
        });
    }
    
    // Advanced Trends Chart
    const advancedCtx = document.getElementById('advancedTrendsChart');
    if (advancedCtx) {
        charts.advancedTrends = new Chart(advancedCtx, {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                ...chartConfig,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

/**
 * Update trends chart
 */
function updateTrendsChart(trendsData) {
    if (!charts.trends || !trendsData) return;
    
    const labels = trendsData.dates || [];
    const mappingsData = trendsData.mappings || [];
    const successData = trendsData.success_rates || [];
    
    charts.trends.data = {
        labels: labels,
        datasets: [
            {
                label: 'Total Mappings',
                data: mappingsData,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                fill: true,
                tension: 0.4
            },
            {
                label: 'Success Rate (%)',
                data: successData,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                fill: false,
                yAxisID: 'y1'
            }
        ]
    };
    
    // Add second Y axis for success rate
    charts.trends.options.scales.y1 = {
        type: 'linear',
        display: true,
        position: 'right',
        max: 100,
        title: {
            display: true,
            text: 'Success Rate (%)'
        },
        grid: {
            drawOnChartArea: false,
        },
    };
    
    charts.trends.update();
}

/**
 * Update methods pie chart
 */
function updateMethodsChart(methodsData) {
    if (!charts.methods || !methodsData) return;
    
    const labels = Object.keys(methodsData);
    const data = Object.values(methodsData);
    const colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];
    
    charts.methods.data = {
        labels: labels,
        datasets: [{
            data: data,
            backgroundColor: colors.slice(0, labels.length),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };
    
    charts.methods.update();
}

/**
 * Update performance radar chart
 */
function updatePerformanceChart(performanceData) {
    if (!charts.performance || !performanceData) return;
    
    charts.performance.data = {
        labels: ['Speed', 'Accuracy', 'Efficiency', 'User Satisfaction', 'Error Rate', 'Completion Rate'],
        datasets: [{
            label: 'Current Performance',
            data: [
                performanceData.speed || 0,
                performanceData.accuracy || 0,
                performanceData.efficiency || 0,
                performanceData.user_satisfaction || 0,
                100 - (performanceData.error_rate || 0), // Invert error rate
                performanceData.completion_rate || 0
            ],
            backgroundColor: 'rgba(78, 115, 223, 0.2)',
            borderColor: '#4e73df',
            borderWidth: 2,
            pointBackgroundColor: '#4e73df'
        }]
    };
    
    charts.performance.update();
}

/**
 * Update error analysis chart
 */
function updateErrorChart(errorData) {
    if (!charts.error || !errorData) return;
    
    const labels = Object.keys(errorData);
    const data = Object.values(errorData);
    
    charts.error.data = {
        labels: labels,
        datasets: [{
            label: 'Error Count',
            data: data,
            backgroundColor: [
                'rgba(231, 74, 59, 0.8)',
                'rgba(246, 194, 62, 0.8)',
                'rgba(54, 185, 204, 0.8)',
                'rgba(28, 200, 138, 0.8)',
                'rgba(78, 115, 223, 0.8)'
            ],
            borderColor: [
                '#e74a3b',
                '#f6c23e',
                '#36b9cc',
                '#1cc88a',
                '#4e73df'
            ],
            borderWidth: 1
        }]
    };
    
    charts.error.update();
}

/**
 * Update advanced trends chart
 */
function updateAdvancedTrendsChart(advancedData) {
    if (!charts.advancedTrends || !advancedData) return;
    
    const labels = advancedData.dates || [];
    
    charts.advancedTrends.data = {
        labels: labels,
        datasets: [
            {
                label: 'Processing Volume',
                data: advancedData.volume || [],
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                fill: false
            },
            {
                label: 'Success Trend',
                data: advancedData.success_trend || [],
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                fill: false
            },
            {
                label: 'Error Trend',
                data: advancedData.error_trend || [],
                borderColor: '#e74a3b',
                backgroundColor: 'rgba(231, 74, 59, 0.1)',
                fill: false
            }
        ]
    };
    
    charts.advancedTrends.update();
}

/**
 * Update top stores table
 */
function updateTopStoresTable(storesData) {
    const tbody = $('#topStoresTableBody');
    tbody.empty();
    
    if (!storesData || storesData.length === 0) {
        tbody.html('<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>');
        return;
    }
    
    storesData.forEach(store => {
        const successRate = parseFloat(store.success_rate || 0);
        const avgTime = parseFloat(store.avg_time || 0);
        
        const badgeClass = successRate >= 90 ? 'success' : successRate >= 70 ? 'warning' : 'danger';
        
        const row = `
            <tr>
                <td><strong>${store.name}</strong></td>
                <td><span class="badge badge-primary">${store.mappings}</span></td>
                <td><span class="badge badge-${badgeClass}">${successRate.toFixed(1)}%</span></td>
                <td>${avgTime.toFixed(1)}s</td>
            </tr>
        `;
        
        tbody.append(row);
    });
}

/**
 * Update recent activity table
 */
function updateRecentActivityTable(activityData) {
    const tbody = $('#recentActivityTableBody');
    tbody.empty();
    
    if (!activityData || activityData.length === 0) {
        tbody.html('<tr><td colspan="4" class="text-center text-muted">No recent activity</td></tr>');
        return;
    }
    
    activityData.forEach(activity => {
        const time = new Date(activity.created_at);
        const timeStr = time.toLocaleTimeString();
        
        const methodBadge = getMethodBadge(activity.method);
        const statusBadge = getStatusBadge(activity.status);
        
        const row = `
            <tr>
                <td><small>${timeStr}</small></td>
                <td>${activity.employee_name}</td>
                <td>${methodBadge}</td>
                <td>${statusBadge}</td>
            </tr>
        `;
        
        tbody.append(row);
    });
}

/**
 * Get method badge HTML
 */
function getMethodBadge(method) {
    const badges = {
        'auto': '<span class="badge badge-primary">Auto</span>',
        'manual': '<span class="badge badge-success">Manual</span>',
        'bulk': '<span class="badge badge-info">Bulk</span>'
    };
    
    return badges[method] || '<span class="badge badge-secondary">Unknown</span>';
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const badges = {
        'success': '<span class="badge badge-success">Success</span>',
        'pending': '<span class="badge badge-warning">Pending</span>',
        'failed': '<span class="badge badge-danger">Failed</span>',
        'reviewing': '<span class="badge badge-info">Review</span>'
    };
    
    return badges[status] || '<span class="badge badge-secondary">Unknown</span>';
}

/**
 * Update analytics insights
 */
function updateAnalyticsInsights(insights) {
    const container = $('#analyticsInsights');
    container.empty();
    
    if (!insights || insights.length === 0) {
        container.html('<div class="insight-item"><i class="fas fa-info-circle text-muted"></i> <span class="text-muted">No insights available</span></div>');
        return;
    }
    
    insights.forEach(insight => {
        const iconClass = getInsightIcon(insight.type);
        const textClass = getInsightTextClass(insight.priority);
        
        const item = `
            <div class="insight-item">
                <i class="${iconClass}"></i>
                <span class="${textClass}">${insight.message}</span>
            </div>
        `;
        
        container.append(item);
    });
}

/**
 * Get insight icon based on type
 */
function getInsightIcon(type) {
    const icons = {
        'improvement': 'fas fa-arrow-up text-success',
        'warning': 'fas fa-exclamation-triangle text-warning',
        'error': 'fas fa-times-circle text-danger',
        'info': 'fas fa-info-circle text-info',
        'success': 'fas fa-check-circle text-success'
    };
    
    return icons[type] || 'fas fa-lightbulb text-info';
}

/**
 * Get insight text class based on priority
 */
function getInsightTextClass(priority) {
    const classes = {
        'high': 'text-danger font-weight-bold',
        'medium': 'text-warning',
        'low': 'text-muted'
    };
    
    return classes[priority] || 'text-dark';
}

/**
 * Update system health indicators
 */
function updateSystemHealth(healthData) {
    if (!healthData) return;
    
    updateHealthIndicator('api', healthData.api);
    updateHealthIndicator('db', healthData.database);
    updateHealthIndicator('queue', healthData.queue);
    updateHealthIndicator('mapping', healthData.mapping_service);
}

/**
 * Update individual health indicator
 */
function updateHealthIndicator(service, health) {
    const circle = $(`#${service}Health`);
    const status = $(`#${service}HealthStatus`);
    
    if (!health) return;
    
    // Remove all background classes
    circle.removeClass('bg-success bg-warning bg-danger');
    
    // Set status based on health
    if (health.status === 'healthy') {
        circle.addClass('bg-success');
        circle.html('<i class="fas fa-check text-white"></i>');
    } else if (health.status === 'warning') {
        circle.addClass('bg-warning');
        circle.html('<i class="fas fa-exclamation text-white"></i>');
    } else {
        circle.addClass('bg-danger');
        circle.html('<i class="fas fa-times text-white"></i>');
    }
    
    status.text(health.message || health.status);
}

/**
 * Set up auto-refresh
 */
function setupAutoRefresh() {
    // Refresh every 5 minutes
    refreshInterval = setInterval(() => {
        loadAnalyticsData();
    }, 5 * 60 * 1000);
}

/**
 * Update last refresh time
 */
function updateLastRefreshTime() {
    const now = new Date();
    $('#lastUpdated').text(now.toLocaleTimeString());
}

/**
 * Show analytics loading state
 */
function showAnalyticsLoading() {
    // Add loading class to main container
    $('#analytics-dashboard').addClass('analytics-loading');
    
    // Show spinners in KPI cards
    $('#successRate, #autoMatchAccuracy, #avgProcessingTime, #amountProcessed').html('<i class="fas fa-spinner fa-spin"></i>');
}

/**
 * Hide analytics loading state
 */
function hideAnalyticsLoading() {
    $('#analytics-dashboard').removeClass('analytics-loading');
}

/**
 * Show analytics error
 */
function showAnalyticsError(message) {
    const alertHtml = `
        <div class="alert alert-danger alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Analytics Error:</strong> ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    setTimeout(function() {
        $('.alert').alert('close');
    }, 8000);
}

/**
 * Export analytics data
 */
function exportAnalyticsData() {
    try {
        const exportData = {
            timestamp: new Date().toISOString(),
            time_range: currentTimeRange,
            data: analyticsData
        };
        
        const dataStr = JSON.stringify(exportData, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        
        const link = document.createElement('a');
        link.href = URL.createObjectURL(dataBlob);
        link.download = `employee-mapping-analytics-${new Date().toISOString().split('T')[0]}.json`;
        link.click();
        
    } catch (error) {
        console.error('Export failed:', error);
        showAnalyticsError('Failed to export analytics data');
    }
}

/**
 * Show analytics help modal
 */
function showAnalyticsHelp() {
    $('#analyticsHelpModal').modal('show');
}

/**
 * Download chart as image
 */
function downloadChart(chartId) {
    try {
        const chart = charts[chartId.replace('Chart', '')];
        if (!chart) {
            throw new Error('Chart not found');
        }
        
        const link = document.createElement('a');
        link.href = chart.toBase64Image();
        link.download = `${chartId}-${new Date().toISOString().split('T')[0]}.png`;
        link.click();
        
    } catch (error) {
        console.error('Chart download failed:', error);
        showAnalyticsError('Failed to download chart');
    }
}

/**
 * Toggle chart type (line/bar)
 */
function toggleChartType(chartId) {
    try {
        const chartKey = chartId.replace('Chart', '');
        const chart = charts[chartKey];
        
        if (!chart) return;
        
        // Toggle between line and bar for applicable charts
        if (chart.config.type === 'line') {
            chart.config.type = 'bar';
        } else if (chart.config.type === 'bar') {
            chart.config.type = 'line';
        }
        
        chart.update();
        
    } catch (error) {
        console.error('Chart toggle failed:', error);
    }
}

// Initialize when document is ready
$(document).ready(function() {
    if ($('#analytics-dashboard').length > 0) {
        initAnalyticsDashboard();
    }
});

// Clean up intervals when page unloads
$(window).on('beforeunload', function() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});