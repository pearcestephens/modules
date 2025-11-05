<?php
/**
 * Security Dashboard - ANALYTICS STYLE
 * Matches pack-advanced-layout-a.php design scheme
 * Fraud detection, suspicious scans, investigation tools
 */

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Security Dashboard');
$theme->setPageSubtitle('Monitor suspicious activity and fraud detection');
$theme->showTimestamps = true;

// Breadcrumbs
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Analytics', null);
$theme->addBreadcrumb('Security', null);

// Header buttons
$theme->addHeaderButton('Fraud Rules', 'btn-outline-warning', 'javascript:manageFraudRules()', 'fa-shield');
$theme->addHeaderButton('Investigation Report', 'btn-outline-danger', 'javascript:generateReport()', 'fa-file-alt');
$theme->addHeaderButton('Export Data', 'btn-outline-secondary', 'javascript:exportData()', 'fa-download');
?>

<?php $theme->render('html-head'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<?php $theme->render('header'); ?>
<?php $theme->render('sidebar'); ?>
<?php $theme->render('main-start'); ?>

<style>
/* Match pack-advanced-layout-a.php styling */
.security-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
    min-height: calc(100vh - 200px);
}

/* Alert Cards (matches stats-grid but with alert colors) */
.alert-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.alert-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-left: 4px solid #dc3545;
    transition: transform 0.2s;
}

.alert-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.alert-card.critical { border-left-color: #dc3545; }
.alert-card.high { border-left-color: #fd7e14; }
.alert-card.medium { border-left-color: #ffc107; }
.alert-card.low { border-left-color: #17a2b8; }

.alert-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 12px;
}

.alert-card.critical .alert-icon { background: #f8d7da; color: #dc3545; }
.alert-card.high .alert-icon { background: #fff3cd; color: #fd7e14; }
.alert-card.medium .alert-icon { background: #fff8e1; color: #ffc107; }
.alert-card.low .alert-icon { background: #d1ecf1; color: #17a2b8; }

.alert-value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin: 8px 0;
}

.alert-label {
    font-size: 13px;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
}

.alert-status {
    font-size: 12px;
    margin-top: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
}

.alert-status.needs-review {
    background: #fff3cd;
    color: #856404;
}

/* Filter Bar (matches period-selector) */
.filter-bar {
    background: #fff;
    border: 1px solid #dee2e6;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-select {
    padding: 6px 12px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.filter-btn {
    padding: 6px 16px;
    border: 2px solid #dee2e6;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.filter-btn.active {
    border-color: #dc3545;
    background: #dc3545;
    color: #fff;
}

/* Suspicious Scans Table (matches product-table-a) */
.suspicious-table {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 20px;
}

.suspicious-table table {
    width: 100%;
    margin-bottom: 0;
}

.suspicious-table thead {
    background: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
}

.suspicious-table th {
    padding: 12px 15px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
}

.suspicious-table td {
    padding: 12px 15px;
    vertical-align: middle;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
}

.suspicious-table tbody tr:hover {
    background: #fff8e1;
    cursor: pointer;
}

.suspicious-table tbody tr.critical {
    background: #f8d7da;
}

.suspicious-table tbody tr.high {
    background: #fff3cd;
}

.fraud-score-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
}

.fraud-score-critical {
    background: #dc3545;
    color: #fff;
}

.fraud-score-high {
    background: #fd7e14;
    color: #fff;
}

.fraud-score-medium {
    background: #ffc107;
    color: #000;
}

.fraud-score-low {
    background: #17a2b8;
    color: #fff;
}

/* Investigation Panel (matches freight-console-a) */
.investigation-panel {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    overflow: hidden;
}

.panel-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 15px 20px;
}

.panel-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.panel-body {
    padding: 20px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #495057;
}

.detail-value {
    color: #333;
    font-family: 'Courier New', monospace;
}

/* Fraud Reasons List */
.fraud-reasons {
    list-style: none;
    padding: 0;
    margin: 10px 0;
}

.fraud-reasons li {
    padding: 8px 12px;
    background: #fff3cd;
    border-left: 3px solid #ffc107;
    margin-bottom: 8px;
    border-radius: 4px;
    font-size: 13px;
}

.fraud-reasons li.critical {
    background: #f8d7da;
    border-left-color: #dc3545;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.action-btn {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.action-btn-approve {
    background: #28a745;
    color: #fff;
}

.action-btn-approve:hover {
    background: #218838;
}

.action-btn-flag {
    background: #dc3545;
    color: #fff;
}

.action-btn-flag:hover {
    background: #c82333;
}

.action-btn-review {
    background: #ffc107;
    color: #000;
}

.action-btn-review:hover {
    background: #e0a800;
}

/* Pattern Analysis Chart */
.pattern-analysis {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.pattern-chart {
    min-height: 300px;
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 60px 20px;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid #f0f0f0;
    border-top-color: #dc3545;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Modal Overlay */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.modal-overlay.active {
    display: flex;
}

.modal-content {
    background: #fff;
    border-radius: 8px;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .alert-cards {
        grid-template-columns: 1fr;
    }

    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="security-container">

    <!-- Alert Summary Cards -->
    <div class="alert-cards">
        <div class="alert-card critical">
            <div class="alert-icon">
                <i class="bi bi-exclamation-octagon-fill"></i>
            </div>
            <div class="alert-value" id="critical-count">0</div>
            <div class="alert-label">Critical Alerts</div>
            <div class="alert-status needs-review">Requires immediate review</div>
        </div>

        <div class="alert-card high">
            <div class="alert-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="alert-value" id="high-count">0</div>
            <div class="alert-label">High Risk</div>
            <div class="alert-status needs-review">Needs investigation</div>
        </div>

        <div class="alert-card medium">
            <div class="alert-icon">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <div class="alert-value" id="medium-count">0</div>
            <div class="alert-label">Medium Risk</div>
            <div class="alert-status">Under review</div>
        </div>

        <div class="alert-card low">
            <div class="alert-icon">
                <i class="bi bi-info-circle-fill"></i>
            </div>
            <div class="alert-value" id="low-count">0</div>
            <div class="alert-label">Low Risk</div>
            <div class="alert-status">Monitor</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-group">
            <strong>Severity:</strong>
            <button class="filter-btn active" data-severity="all" onclick="filterSeverity('all')">All</button>
            <button class="filter-btn" data-severity="critical" onclick="filterSeverity('critical')">Critical</button>
            <button class="filter-btn" data-severity="high" onclick="filterSeverity('high')">High</button>
            <button class="filter-btn" data-severity="medium" onclick="filterSeverity('medium')">Medium</button>
        </div>

        <div class="filter-group">
            <strong>Period:</strong>
            <select class="filter-select" id="periodFilter" onchange="loadSuspiciousScans()">
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="all">All Time</option>
            </select>
        </div>

        <div class="filter-group">
            <strong>Status:</strong>
            <select class="filter-select" id="statusFilter" onchange="loadSuspiciousScans()">
                <option value="all">All</option>
                <option value="pending">Pending Review</option>
                <option value="reviewed">Reviewed</option>
                <option value="flagged">Flagged</option>
            </select>
        </div>

        <div class="filter-group ms-auto">
            <button class="btn btn-outline-primary btn-sm" onclick="loadSuspiciousScans()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div id="loadingState" class="loading-state">
        <div class="loading-spinner"></div>
        <p class="text-muted">Loading security data...</p>
    </div>

    <!-- Main Content -->
    <div id="mainContent" style="display: none;">

        <!-- Suspicious Scans Table -->
        <div class="suspicious-table">
            <div class="panel-header">
                <h2 class="panel-title">
                    <i class="bi bi-shield-fill-exclamation"></i> Suspicious Scan Activity
                    <span class="badge bg-danger ms-2" id="suspicious-count">0</span>
                </h2>
            </div>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 120px;">Timestamp</th>
                        <th>User</th>
                        <th>Transfer</th>
                        <th>Barcode</th>
                        <th style="width: 100px;">Fraud Score</th>
                        <th>Reasons</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody id="suspiciousTable">
                    <!-- Loaded dynamically -->
                </tbody>
            </table>
        </div>

        <!-- Pattern Analysis -->
        <div class="pattern-analysis">
            <div class="panel-header">
                <h2 class="panel-title">
                    <i class="bi bi-graph-up"></i> Fraud Pattern Analysis
                </h2>
            </div>
            <div class="pattern-chart">
                <canvas id="patternChart"></canvas>
            </div>
        </div>

    </div>
</div>

<!-- Investigation Modal -->
<div class="modal-overlay" id="investigationModal">
    <div class="modal-content">
        <div class="investigation-panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="panel-title">
                    <i class="bi bi-search"></i> Investigate Suspicious Scan
                </h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div class="panel-body" id="investigationContent">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let currentSeverity = 'all';
let patternChart = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadSuspiciousScans();
    loadPatternAnalysis();
});

// Load Suspicious Scans
async function loadSuspiciousScans() {
    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('mainContent').style.display = 'none';

    try {
        const period = document.getElementById('periodFilter').value;
        const status = document.getElementById('statusFilter').value;

        const response = await fetch(`../api/barcode_analytics.php?action=get_suspicious_scans&period=${period}&status=${status}&severity=${currentSeverity}`);
        const data = await response.json();

        if (data.success) {
            renderSuspiciousScans(data.scans);
            updateAlertCounts(data.counts);
        } else {
            alert('Error loading security data: ' + data.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load security data');
    }

    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('mainContent').style.display = 'block';
}

// Update Alert Counts
function updateAlertCounts(counts) {
    document.getElementById('critical-count').textContent = counts.critical || 0;
    document.getElementById('high-count').textContent = counts.high || 0;
    document.getElementById('medium-count').textContent = counts.medium || 0;
    document.getElementById('low-count').textContent = counts.low || 0;
}

// Render Suspicious Scans
function renderSuspiciousScans(scans) {
    const tbody = document.getElementById('suspiciousTable');
    tbody.innerHTML = '';

    document.getElementById('suspicious-count').textContent = scans.length;

    if (scans.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted">No suspicious activity found</td></tr>';
        return;
    }

    scans.forEach(scan => {
        const score = scan.fraud_score || 0;
        const severity = score >= 70 ? 'critical' : score >= 50 ? 'high' : score >= 30 ? 'medium' : 'low';
        const scoreClass = `fraud-score-${severity}`;

        const tr = document.createElement('tr');
        tr.className = severity;
        tr.innerHTML = `
            <td>${scan.scanned_at}</td>
            <td>${scan.user_name || scan.user_id}</td>
            <td>#${scan.transfer_id}</td>
            <td><code>${scan.barcode}</code></td>
            <td>
                <span class="fraud-score-badge ${scoreClass}">${score}</span>
            </td>
            <td>
                <small>${(JSON.parse(scan.fraud_reasons || '[]')).slice(0, 2).join(', ')}</small>
            </td>
            <td>
                <span class="badge bg-warning">Pending</span>
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="investigateScan(${scan.event_id})">
                    <i class="bi bi-search"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// Filter Severity
function filterSeverity(severity) {
    currentSeverity = severity;

    // Update active button
    document.querySelectorAll('.filter-btn[data-severity]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-severity="${severity}"]`).classList.add('active');

    loadSuspiciousScans();
}

// Investigate Scan
async function investigateScan(eventId) {
    try {
        const response = await fetch(`../api/barcode_analytics.php?action=get_scan_details&event_id=${eventId}`);
        const data = await response.json();

        if (data.success) {
            showInvestigationModal(data.scan);
        } else {
            alert('Error loading scan details');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load scan details');
    }
}

// Show Investigation Modal
function showInvestigationModal(scan) {
    const reasons = JSON.parse(scan.fraud_reasons || '[]');
    const score = scan.fraud_score || 0;
    const severity = score >= 70 ? 'critical' : score >= 50 ? 'high' : score >= 30 ? 'medium' : 'low';

    document.getElementById('investigationContent').innerHTML = `
        <div class="detail-row">
            <span class="detail-label">Event ID:</span>
            <span class="detail-value">${scan.event_id}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Timestamp:</span>
            <span class="detail-value">${scan.scanned_at}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">User:</span>
            <span class="detail-value">${scan.user_name || scan.user_id}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Transfer ID:</span>
            <span class="detail-value">#${scan.transfer_id}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Barcode:</span>
            <span class="detail-value"><code>${scan.barcode}</code></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Product ID:</span>
            <span class="detail-value">${scan.product_id || 'N/A'}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Scan Result:</span>
            <span class="detail-value">${scan.scan_result}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Fraud Score:</span>
            <span class="detail-value">
                <span class="fraud-score-badge fraud-score-${severity}">${score}</span>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Time Since Last Scan:</span>
            <span class="detail-value">${scan.time_since_last_scan_ms}ms</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Device:</span>
            <span class="detail-value">${scan.device_type}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">IP Address:</span>
            <span class="detail-value">${scan.ip_address}</span>
        </div>

        <div style="margin-top: 20px;">
            <strong>Fraud Detection Reasons:</strong>
            <ul class="fraud-reasons">
                ${reasons.map(r => `<li class="${severity}">${r}</li>`).join('')}
            </ul>
        </div>

        <div class="action-buttons">
            <button class="action-btn action-btn-approve" onclick="approveScsan(${scan.event_id})">
                <i class="bi bi-check-circle"></i> Approve
            </button>
            <button class="action-btn action-btn-review" onclick="markForReview(${scan.event_id})">
                <i class="bi bi-eye"></i> Review Later
            </button>
            <button class="action-btn action-btn-flag" onclick="flagScan(${scan.event_id})">
                <i class="bi bi-flag-fill"></i> Flag as Fraud
            </button>
        </div>
    `;

    document.getElementById('investigationModal').classList.add('active');
}

// Close Modal
function closeModal() {
    document.getElementById('investigationModal').classList.remove('active');
}

// Load Pattern Analysis
async function loadPatternAnalysis() {
    try {
        const response = await fetch(`../api/barcode_analytics.php?action=get_fraud_patterns`);
        const data = await response.json();

        if (data.success) {
            renderPatternChart(data.patterns);
        }
    } catch (error) {
        console.error('Error loading patterns:', error);
    }
}

// Render Pattern Chart
function renderPatternChart(patterns) {
    const ctx = document.getElementById('patternChart').getContext('2d');

    if (patternChart) {
        patternChart.destroy();
    }

    patternChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: patterns.labels || ['Invalid Barcode', 'Too Fast', 'Duplicate', 'Sequential', 'Excessive Qty'],
            datasets: [{
                label: 'Fraud Detections',
                data: patterns.counts || [0, 0, 0, 0, 0],
                backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#17a2b8', '#6c757d']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Action Functions
function approveScan(eventId) {
    if (confirm('Mark this scan as approved and not fraudulent?')) {
        // API call to approve
        alert('Scan approved');
        closeModal();
        loadSuspiciousScans();
    }
}

function markForReview(eventId) {
    alert('Marked for later review');
    closeModal();
}

function flagScan(eventId) {
    if (confirm('Flag this scan as confirmed fraud? This may require further action.')) {
        // API call to flag
        alert('Scan flagged');
        closeModal();
        loadSuspiciousScans();
    }
}

// Management Functions
function manageFraudRules() {
    window.location.href = '../admin/analytics-settings.php?category=fraud_detection';
}

function generateReport() {
    window.location.href = `../api/barcode_analytics.php?action=generate_fraud_report`;
}

function exportData() {
    window.location.href = `../api/barcode_analytics.php?action=export_suspicious_scans`;
}
</script>

<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
