<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Reconciliation - CIS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .variance-card { border-left: 4px solid #ffc107; }
        .variance-high { border-left-color: #dc3545; }
        .variance-medium { border-left-color: #fd7e14; }
        .variance-low { border-left-color: #28a745; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1><i class="fas fa-balance-scale"></i> Payroll Reconciliation</h1>
                <p class="text-muted">Compare CIS calculations vs Xero/Deputy data</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4" id="statsCards">
            <div class="col-md-3">
                <div class="card stat-card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-users"></i> Total Employees</h5>
                        <h2 id="statEmployees">-</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-exclamation-triangle"></i> Variances</h5>
                        <h2 id="statVariances">-</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-check-circle"></i> Matched</h5>
                        <h2 id="statMatched">-</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-dollar-sign"></i> Total Variance</h5>
                        <h2 id="statTotalVariance">-</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-md-3">
                                <label>Period</label>
                                <select class="form-control" id="periodFilter">
                                    <option value="current">Current Period</option>
                                    <option value="last">Last Period</option>
                                    <option value="all">All Periods</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Variance Threshold</label>
                                <select class="form-control" id="thresholdFilter">
                                    <option value="0.01">$0.01+</option>
                                    <option value="1.00">$1.00+</option>
                                    <option value="10.00">$10.00+</option>
                                    <option value="50.00" selected>$50.00+</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Type</label>
                                <select class="form-control" id="typeFilter">
                                    <option value="all">All Types</option>
                                    <option value="hours">Hours</option>
                                    <option value="pay">Pay Amount</option>
                                    <option value="deductions">Deductions</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>&nbsp;</label><br>
                                <button class="btn btn-primary btn-block" onclick="loadVariances()">
                                    <i class="fas fa-sync"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Variances Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Detected Variances</h5>
                    </div>
                    <div class="card-body">
                        <div id="variancesLoading" class="text-center py-5">
                            <i class="fas fa-spinner fa-spin fa-3x text-muted"></i>
                            <p class="mt-3">Loading variances...</p>
                        </div>
                        <div id="variancesContainer" style="display: none;">
                            <table class="table table-hover" id="variancesTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Type</th>
                                        <th>CIS Value</th>
                                        <th>Xero Value</th>
                                        <th>Variance</th>
                                        <th>%</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="variancesBody">
                                </tbody>
                            </table>
                        </div>
                        <div id="variancesEmpty" style="display: none;" class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success"></i>
                            <p class="mt-3"><strong>No variances detected!</strong></p>
                            <p class="text-muted">All values match within threshold.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load dashboard data on page load
        $(document).ready(function() {
            loadDashboard();
            loadVariances();
        });

        function loadDashboard() {
            $.get('/modules/human_resources/payroll/?api=reconciliation/dashboard', function(response) {
                if (response.success) {
                    $('#statEmployees').text(response.data.total_employees || 0);
                    $('#statVariances').text(response.data.variances_count || 0);
                    $('#statMatched').text(response.data.matched_count || 0);
                    $('#statTotalVariance').text('$' + (response.data.total_variance || 0).toFixed(2));
                }
            }).fail(function() {
                console.error('Failed to load dashboard data');
            });
        }

        function loadVariances() {
            const period = $('#periodFilter').val();
            const threshold = $('#thresholdFilter').val();

            $('#variancesLoading').show();
            $('#variancesContainer').hide();
            $('#variancesEmpty').hide();

            $.get('/modules/human_resources/payroll/?api=reconciliation/variances', {
                period: period,
                threshold: threshold
            }, function(response) {
                $('#variancesLoading').hide();

                if (response.success && response.variances.length > 0) {
                    renderVariances(response.variances);
                    $('#variancesContainer').show();
                } else {
                    $('#variancesEmpty').show();
                }
            }).fail(function() {
                $('#variancesLoading').hide();
                alert('Failed to load variances');
            });
        }

        function renderVariances(variances) {
            const tbody = $('#variancesBody');
            tbody.empty();

            variances.forEach(function(v) {
                const varianceAmount = v.variance.toFixed(2);
                const variancePct = v.variance_pct.toFixed(1);
                const severity = Math.abs(v.variance) > 100 ? 'danger' : (Math.abs(v.variance) > 50 ? 'warning' : 'info');

                const row = `
                    <tr class="table-${severity}">
                        <td>${v.employee_name}</td>
                        <td><span class="badge badge-secondary">${v.type}</span></td>
                        <td>$${v.cis_value.toFixed(2)}</td>
                        <td>$${v.xero_value.toFixed(2)}</td>
                        <td><strong>$${varianceAmount}</strong></td>
                        <td>${variancePct}%</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="investigateVariance(${v.id})">
                                <i class="fas fa-search"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function investigateVariance(id) {
            alert('Investigation feature coming soon! Variance ID: ' + id);
            // TODO: Open modal with detailed comparison
        }
    </script>
</body>
</html>
