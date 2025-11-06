/**
 * Business Intelligence Dashboard - JavaScript
 * Handles data visualization, charts, and analytics
 */

$(document).ready(function() {
    let financialData = {};
    let currentPeriod = '30days';
    let charts = {};

    // Load data on page load
    loadFinancialData();

    /**
     * Load financial data from API
     */
    function loadFinancialData() {
        showLoading();

        $.ajax({
            url: '../api/get-financial-data.php',
            method: 'GET',
            data: { period: currentPeriod },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    financialData = response.data;
                    renderDashboard();
                    hideLoading();
                } else {
                    showAlert('Error loading data: ' + response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Failed to load financial data', 'danger');
                hideLoading();
            }
        });
    }

    /**
     * Render complete dashboard
     */
    function renderDashboard() {
        updateSummaryCards();
        renderRevenueTrendChart();
        renderRevenueMixChart();
        renderStorePerformanceTable();
        renderCostBreakdownChart();
        renderForecastChart();
        renderPerformanceHeatmap();
    }

    /**
     * Update summary cards
     */
    function updateSummaryCards() {
        const summary = financialData.summary || {};

        $('#totalRevenue').text(formatCurrency(summary.total_revenue || 0));
        $('#netProfit').text(formatCurrency(summary.net_profit || 0));
        $('#avgMargin').text((summary.avg_margin || 0).toFixed(1) + '%');
        $('#totalTransactions').text((summary.total_transactions || 0).toLocaleString());
    }

    /**
     * Render Revenue Trend Chart (Line Chart)
     */
    function renderRevenueTrendChart() {
        const ctx = document.getElementById('revenueTrendChart').getContext('2d');
        const trendData = financialData.revenue_trend || [];

        if (charts.revenueTrend) {
            charts.revenueTrend.destroy();
        }

        charts.revenueTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendData.map(d => d.date),
                datasets: [
                    {
                        label: 'Revenue',
                        data: trendData.map(d => d.revenue),
                        borderColor: 'rgb(25, 135, 84)',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Net Profit',
                        data: trendData.map(d => d.profit),
                        borderColor: 'rgb(13, 110, 253)',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + (value / 1000) + 'K';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Render Revenue Mix Chart (Doughnut Chart)
     */
    function renderRevenueMixChart() {
        const ctx = document.getElementById('revenueMixChart').getContext('2d');
        const mixData = financialData.revenue_mix || {};

        if (charts.revenueMix) {
            charts.revenueMix.destroy();
        }

        charts.revenueMix = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(mixData),
                datasets: [{
                    data: Object.values(mixData),
                    backgroundColor: [
                        'rgb(25, 135, 84)',
                        'rgb(13, 110, 253)',
                        'rgb(255, 193, 7)',
                        'rgb(220, 53, 69)',
                        'rgb(13, 202, 240)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + formatCurrency(value) + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Render Store Performance Table
     */
    function renderStorePerformanceTable() {
        const $tbody = $('#storePerformanceTable');
        $tbody.empty();

        const storeData = financialData.store_performance || [];

        storeData.forEach((store, index) => {
            const profitClass = store.net_profit >= 0 ? 'text-success' : 'text-danger';
            const changeClass = store.vs_last_period >= 0 ? 'text-success' : 'text-danger';
            const changeIcon = store.vs_last_period >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';

            const row = `
                <tr>
                    <td><strong>#${index + 1}</strong></td>
                    <td>
                        <strong>${store.outlet_name}</strong><br>
                        <small class="text-muted">${store.outlet_code}</small>
                    </td>
                    <td>${formatCurrency(store.revenue)}</td>
                    <td>${formatCurrency(store.cogs)}</td>
                    <td class="text-success">${formatCurrency(store.gross_profit)}</td>
                    <td>${formatCurrency(store.operating_expenses)}</td>
                    <td class="${profitClass}"><strong>${formatCurrency(store.net_profit)}</strong></td>
                    <td>${store.margin_pct.toFixed(1)}%</td>
                    <td class="${changeClass}">
                        <i class="bi ${changeIcon}"></i> ${Math.abs(store.vs_last_period).toFixed(1)}%
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    /**
     * Render Cost Breakdown Chart (Bar Chart)
     */
    function renderCostBreakdownChart() {
        const ctx = document.getElementById('costBreakdownChart').getContext('2d');
        const costData = financialData.cost_breakdown || {};

        if (charts.costBreakdown) {
            charts.costBreakdown.destroy();
        }

        charts.costBreakdown = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(costData),
                datasets: [{
                    label: 'Cost',
                    data: Object.values(costData),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return formatCurrency(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + (value / 1000) + 'K';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Render Forecast Chart (Line Chart with Prediction)
     */
    function renderForecastChart() {
        const ctx = document.getElementById('forecastChart').getContext('2d');
        const forecastData = financialData.forecast || {};

        if (charts.forecast) {
            charts.forecast.destroy();
        }

        charts.forecast = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [...(forecastData.historical_dates || []), ...(forecastData.forecast_dates || [])],
                datasets: [
                    {
                        label: 'Historical Profit',
                        data: forecastData.historical_values || [],
                        borderColor: 'rgb(13, 110, 253)',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Forecasted Profit',
                        data: [...Array(forecastData.historical_values?.length || 0).fill(null), ...(forecastData.forecast_values || [])],
                        borderColor: 'rgb(255, 193, 7)',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        borderDash: [5, 5],
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatCurrency(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + (value / 1000) + 'K';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Render Performance Heatmap
     */
    function renderPerformanceHeatmap() {
        const $heatmap = $('#performanceHeatmap');
        $heatmap.empty();

        const storeData = financialData.store_performance || [];

        storeData.forEach(store => {
            const margin = store.margin_pct;
            let color = '';

            if (margin >= 25) color = '#198754'; // Green
            else if (margin >= 20) color = '#20c997'; // Teal
            else if (margin >= 15) color = '#ffc107'; // Yellow
            else if (margin >= 10) color = '#fd7e14'; // Orange
            else color = '#dc3545'; // Red

            const square = `
                <div class="heatmap-square"
                     style="background-color: ${color}; width: 100px; height: 100px; border-radius: 8px; padding: 10px; color: white; display: flex; flex-direction: column; justify-content: center; align-items: center; cursor: pointer;"
                     title="${store.outlet_name}: ${margin.toFixed(1)}% margin"
                     onclick="viewStoreDetail(${store.outlet_id})">
                    <small style="font-size: 0.7rem;">${store.outlet_code}</small>
                    <strong style="font-size: 1.2rem;">${margin.toFixed(1)}%</strong>
                    <small style="font-size: 0.7rem;">${formatCurrency(store.net_profit)}</small>
                </div>
            `;
            $heatmap.append(square);
        });
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        return '$' + parseFloat(amount).toLocaleString('en-NZ', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    /**
     * Show loading indicator
     */
    function showLoading() {
        $('main').prepend('<div class="alert alert-info" id="loadingAlert"><i class="bi bi-hourglass-split"></i> Loading data...</div>');
    }

    /**
     * Hide loading indicator
     */
    function hideLoading() {
        $('#loadingAlert').remove();
    }

    /**
     * Show alert message
     */
    function showAlert(message, type = 'info') {
        const alert = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('main').prepend(alert);

        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
    }

    // Event Listeners
    $('#periodSelector').change(function() {
        currentPeriod = $(this).val();
        loadFinancialData();
    });

    $('#refreshData').click(function() {
        loadFinancialData();
        showAlert('Data refreshed successfully!', 'success');
    });

    $('#exportReport').click(function() {
        window.location.href = '../api/export-report.php?period=' + currentPeriod;
    });

    // Table sorting
    $('#sortByRevenue').click(function() {
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        sortStoreTable('revenue');
    });

    $('#sortByProfit').click(function() {
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        sortStoreTable('profit');
    });

    $('#sortByMargin').click(function() {
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        sortStoreTable('margin');
    });

    /**
     * Sort store performance table
     */
    function sortStoreTable(sortBy) {
        const storeData = financialData.store_performance || [];

        storeData.sort((a, b) => {
            if (sortBy === 'revenue') return b.revenue - a.revenue;
            if (sortBy === 'profit') return b.net_profit - a.net_profit;
            if (sortBy === 'margin') return b.margin_pct - a.margin_pct;
            return 0;
        });

        financialData.store_performance = storeData;
        renderStorePerformanceTable();
    }
});

/**
 * View store detail (global function)
 */
function viewStoreDetail(outletId) {
    window.location.href = `store-pnl.php?id=${outletId}`;
}
