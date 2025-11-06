<?php
/**
 * Business Intelligence API - Get Financial Data
 * Returns P&L data, trends, forecasts for all stores
 */

header('Content-Type: application/json');
require_once '../../../config/database.php';

try {
    $period = $_GET['period'] ?? '30days';

    // Calculate date range based on period
    switch ($period) {
        case 'today':
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
            break;
        case '7days':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $endDate = date('Y-m-d');
            break;
        case '30days':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = date('Y-m-d');
            break;
        case 'this_month':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            break;
        case 'last_month':
            $startDate = date('Y-m-01', strtotime('first day of last month'));
            $endDate = date('Y-m-t', strtotime('last day of last month'));
            break;
        case 'this_year':
            $startDate = date('Y-01-01');
            $endDate = date('Y-12-31');
            break;
        default:
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = date('Y-m-d');
    }

    // Summary metrics
    $summarySQL = "
        SELECT
            SUM(net_sales) as total_revenue,
            SUM(net_profit) as net_profit,
            AVG(gross_margin_pct) as avg_margin,
            SUM(transaction_count) as total_transactions
        FROM financial_snapshots
        WHERE snapshot_date BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($summarySQL);
    $stmt->execute([$startDate, $endDate]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Revenue trend (daily)
    $trendSQL = "
        SELECT
            snapshot_date as date,
            SUM(net_sales) as revenue,
            SUM(net_profit) as profit
        FROM financial_snapshots
        WHERE snapshot_date BETWEEN ? AND ?
        GROUP BY snapshot_date
        ORDER BY snapshot_date
    ";
    $stmt = $pdo->prepare($trendSQL);
    $stmt->execute([$startDate, $endDate]);
    $revenueTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue mix by category
    $mixSQL = "
        SELECT
            category_name,
            SUM(revenue) as revenue
        FROM revenue_by_category
        WHERE snapshot_date BETWEEN ? AND ?
        GROUP BY category_name
    ";
    $stmt = $pdo->prepare($mixSQL);
    $stmt->execute([$startDate, $endDate]);
    $mixData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $revenueMix = [];
    foreach ($mixData as $row) {
        $revenueMix[$row['category_name']] = floatval($row['revenue']);
    }

    // Store performance
    $storeSQL = "
        SELECT
            f.outlet_id,
            o.outlet_name,
            o.outlet_code,
            SUM(f.net_sales) as revenue,
            SUM(f.cogs) as cogs,
            SUM(f.gross_profit) as gross_profit,
            SUM(f.total_operating_expenses) as operating_expenses,
            SUM(f.net_profit) as net_profit,
            AVG(f.net_margin_pct) as margin_pct,
            AVG(f.vs_last_period_pct) as vs_last_period
        FROM financial_snapshots f
        JOIN outlets o ON f.outlet_id = o.id
        WHERE f.snapshot_date BETWEEN ? AND ?
        GROUP BY f.outlet_id
        ORDER BY net_profit DESC
    ";
    $stmt = $pdo->prepare($storeSQL);
    $stmt->execute([$startDate, $endDate]);
    $storePerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cost breakdown
    $costSQL = "
        SELECT
            SUM(total_staff_costs) as 'Staff Costs',
            SUM(rent) as 'Rent',
            SUM(utilities) as 'Utilities',
            SUM(marketing) as 'Marketing',
            SUM(insurance) as 'Insurance',
            SUM(other_expenses) as 'Other'
        FROM financial_snapshots
        WHERE snapshot_date BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($costSQL);
    $stmt->execute([$startDate, $endDate]);
    $costData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Forecast (simple example - last 30 days historical + next 7 days forecast)
    $forecastSQL = "
        SELECT
            snapshot_date,
            SUM(net_profit) as profit
        FROM financial_snapshots
        WHERE snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY snapshot_date
        ORDER BY snapshot_date
    ";
    $stmt = $pdo->query($forecastSQL);
    $forecastHistorical = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate simple forecast (average of last 7 days)
    $recentAvg = 0;
    if (count($forecastHistorical) >= 7) {
        $lastWeek = array_slice($forecastHistorical, -7);
        $recentAvg = array_sum(array_column($lastWeek, 'profit')) / 7;
    }

    $forecastDates = [];
    $forecastValues = [];
    for ($i = 1; $i <= 7; $i++) {
        $forecastDates[] = date('Y-m-d', strtotime("+$i days"));
        $forecastValues[] = $recentAvg;
    }

    // Response
    echo json_encode([
        'success' => true,
        'data' => [
            'summary' => $summary,
            'revenue_trend' => $revenueTrend,
            'revenue_mix' => $revenueMix,
            'store_performance' => $storePerformance,
            'cost_breakdown' => $costData,
            'forecast' => [
                'historical_dates' => array_column($forecastHistorical, 'snapshot_date'),
                'historical_values' => array_map('floatval', array_column($forecastHistorical, 'profit')),
                'forecast_dates' => $forecastDates,
                'forecast_values' => $forecastValues
            ]
        ],
        'period' => $period,
        'date_range' => [
            'start' => $startDate,
            'end' => $endDate
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
