<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Intelligence - The Vape Shed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/bi-dashboard.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <h5 class="text-white px-3">CIS Portal</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-secondary" href="../outlets/dashboard.php">
                                <i class="bi bi-shop"></i> Outlets
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="dashboard.php">
                                <i class="bi bi-graph-up"></i> Business Intelligence
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-graph-up-arrow text-success"></i> Business Intelligence Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <select class="form-select form-select-sm" id="periodSelector">
                                <option value="today">Today</option>
                                <option value="7days">Last 7 Days</option>
                                <option value="30days" selected>Last 30 Days</option>
                                <option value="this_month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="this_year">This Year</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="refreshData">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="exportReport">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Executive Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-success shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Revenue</h6>
                                        <h2 class="card-title text-success" id="totalRevenue">$1.52M</h2>
                                        <small class="text-success"><i class="bi bi-arrow-up"></i> +15.3% vs last period</small>
                                    </div>
                                    <div class="text-success">
                                        <i class="bi bi-currency-dollar" style="font-size: 3rem; opacity: 0.3;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-primary shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Net Profit</h6>
                                        <h2 class="card-title text-primary" id="netProfit">$345K</h2>
                                        <small class="text-success"><i class="bi bi-arrow-up"></i> +8.2% vs last period</small>
                                    </div>
                                    <div class="text-primary">
                                        <i class="bi bi-graph-up" style="font-size: 3rem; opacity: 0.3;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Avg Margin</h6>
                                        <h2 class="card-title text-info" id="avgMargin">22.7%</h2>
                                        <small class="text-muted">Gross margin across all stores</small>
                                    </div>
                                    <div class="text-info">
                                        <i class="bi bi-percent" style="font-size: 3rem; opacity: 0.3;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Transactions</h6>
                                        <h2 class="card-title text-warning" id="totalTransactions">28,450</h2>
                                        <small class="text-success"><i class="bi bi-arrow-up"></i> +12.5% vs last period</small>
                                    </div>
                                    <div class="text-warning">
                                        <i class="bi bi-cart" style="font-size: 3rem; opacity: 0.3;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Revenue Trend Chart -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Revenue Trend</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueTrendChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Revenue Mix</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueMixChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Store Performance Table -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Store Profitability</h5>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" id="sortByRevenue">Revenue</button>
                                    <button class="btn btn-outline-primary active" id="sortByProfit">Profit</button>
                                    <button class="btn btn-outline-primary" id="sortByMargin">Margin %</button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Rank</th>
                                                <th>Store</th>
                                                <th>Revenue</th>
                                                <th>COGS</th>
                                                <th>Gross Profit</th>
                                                <th>Operating Expenses</th>
                                                <th>Net Profit</th>
                                                <th>Margin %</th>
                                                <th>vs Last Period</th>
                                            </tr>
                                        </thead>
                                        <tbody id="storePerformanceTable">
                                            <!-- Dynamic content -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cost Breakdown & Forecasts -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-wallet2"></i> Cost Breakdown</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="costBreakdownChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-activity"></i> Profit Forecast</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="forecastChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Heatmap -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-grid-3x3"></i> Store Performance Heatmap</h5>
                            </div>
                            <div class="card-body">
                                <div id="performanceHeatmap" class="d-flex flex-wrap gap-2">
                                    <!-- Dynamic heatmap squares -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="../assets/js/bi-dashboard.js"></script>
</body>
</html>
