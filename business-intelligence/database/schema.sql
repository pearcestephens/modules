-- ============================================================================
-- BUSINESS INTELLIGENCE MODULE - DATABASE SCHEMA
-- ============================================================================
-- Purpose: Financial P&L analysis, profitability tracking, forecasting
-- Tracks: Revenue, costs, margins, benchmarks, forecasts per store
-- Version: 1.0.0
-- Date: 2025-11-05
-- ============================================================================

-- ============================================================================
-- TABLE 1: financial_snapshots (COMPLETE P&L BY STORE)
-- ============================================================================
CREATE TABLE IF NOT EXISTS financial_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Period
    snapshot_date DATE NOT NULL,
    period_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') DEFAULT 'daily',
    period_start_date DATE NOT NULL,
    period_end_date DATE NOT NULL,

    -- REVENUE
    gross_sales DECIMAL(12,2) DEFAULT 0.00,
    returns DECIMAL(12,2) DEFAULT 0.00,
    discounts DECIMAL(12,2) DEFAULT 0.00,
    net_sales DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Gross - Returns - Discounts',

    -- COST OF GOODS SOLD
    opening_stock_value DECIMAL(12,2) DEFAULT 0.00,
    purchases DECIMAL(12,2) DEFAULT 0.00,
    closing_stock_value DECIMAL(12,2) DEFAULT 0.00,
    cogs DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Opening + Purchases - Closing',

    -- GROSS PROFIT
    gross_profit DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Net Sales - COGS',
    gross_margin_pct DECIMAL(5,2) DEFAULT 0.00,

    -- OPERATING EXPENSES
    staff_wages DECIMAL(10,2) DEFAULT 0.00,
    staff_super DECIMAL(10,2) DEFAULT 0.00,
    staff_other DECIMAL(10,2) DEFAULT 0.00,
    total_staff_costs DECIMAL(10,2) DEFAULT 0.00,

    rent DECIMAL(10,2) DEFAULT 0.00,
    utilities DECIMAL(10,2) DEFAULT 0.00,
    insurance DECIMAL(10,2) DEFAULT 0.00,
    maintenance DECIMAL(10,2) DEFAULT 0.00,
    marketing DECIMAL(10,2) DEFAULT 0.00,
    pos_fees DECIMAL(10,2) DEFAULT 0.00,
    bank_fees DECIMAL(10,2) DEFAULT 0.00,
    other_expenses DECIMAL(10,2) DEFAULT 0.00,

    total_operating_expenses DECIMAL(10,2) DEFAULT 0.00,

    -- NET PROFIT
    net_profit DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Gross Profit - Operating Expenses',
    net_margin_pct DECIMAL(5,2) DEFAULT 0.00,

    -- METRICS
    transaction_count INT DEFAULT 0,
    customer_count INT DEFAULT 0,
    avg_transaction_value DECIMAL(10,2) DEFAULT 0.00,

    -- COMPARISON
    vs_last_period_pct DECIMAL(6,2) DEFAULT NULL,
    vs_last_year_pct DECIMAL(6,2) DEFAULT NULL,

    -- SOURCE
    data_source ENUM('auto', 'manual', 'import') DEFAULT 'auto',
    sync_status ENUM('pending', 'synced', 'error') DEFAULT 'pending',
    synced_at DATETIME DEFAULT NULL,

    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_outlet_period (outlet_id, snapshot_date, period_type),
    INDEX idx_outlet (outlet_id),
    INDEX idx_date (snapshot_date),
    INDEX idx_period (period_type),
    INDEX idx_net_profit (net_profit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 2: revenue_by_category (PRODUCT MIX)
-- ============================================================================
CREATE TABLE IF NOT EXISTS revenue_by_category (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Period
    snapshot_date DATE NOT NULL,
    period_type ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily',

    -- Category
    category_name VARCHAR(100) NOT NULL COMMENT 'Nicotine, Hardware, Accessories, etc.',

    -- Metrics
    quantity_sold INT DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0.00,
    cogs DECIMAL(10,2) DEFAULT 0.00,
    gross_profit DECIMAL(10,2) DEFAULT 0.00,
    margin_pct DECIMAL(5,2) DEFAULT 0.00,

    -- Mix
    revenue_pct_of_total DECIMAL(5,2) DEFAULT 0.00,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_outlet_date_category (outlet_id, snapshot_date, period_type, category_name),
    INDEX idx_outlet (outlet_id),
    INDEX idx_date (snapshot_date),
    INDEX idx_category (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 3: staff_costs_detail (LABOR BREAKDOWN)
-- ============================================================================
CREATE TABLE IF NOT EXISTS staff_costs_detail (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Period
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    -- Hours
    total_hours_worked DECIMAL(8,2) DEFAULT 0.00,
    overtime_hours DECIMAL(8,2) DEFAULT 0.00,

    -- Costs
    base_wages DECIMAL(10,2) DEFAULT 0.00,
    overtime_pay DECIMAL(10,2) DEFAULT 0.00,
    superannuation DECIMAL(10,2) DEFAULT 0.00,
    kiwisaver DECIMAL(10,2) DEFAULT 0.00,
    leave_accrual DECIMAL(10,2) DEFAULT 0.00,
    other_costs DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(10,2) DEFAULT 0.00,

    -- Efficiency
    cost_per_hour DECIMAL(8,2) DEFAULT 0.00,
    staff_count INT DEFAULT 0,

    -- Source
    deputy_sync_at DATETIME DEFAULT NULL,
    xero_sync_at DATETIME DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_outlet_period (outlet_id, period_start, period_end),
    INDEX idx_outlet (outlet_id),
    INDEX idx_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 4: overhead_allocation (EXPENSE ALLOCATION)
-- ============================================================================
CREATE TABLE IF NOT EXISTS overhead_allocation (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Period
    period_month DATE NOT NULL COMMENT 'First day of month',

    -- Fixed Costs
    rent DECIMAL(10,2) DEFAULT 0.00,
    insurance DECIMAL(10,2) DEFAULT 0.00,
    rates_taxes DECIMAL(10,2) DEFAULT 0.00,

    -- Variable Costs
    utilities DECIMAL(10,2) DEFAULT 0.00,
    phone_internet DECIMAL(10,2) DEFAULT 0.00,
    cleaning DECIMAL(10,2) DEFAULT 0.00,
    maintenance DECIMAL(10,2) DEFAULT 0.00,

    -- Marketing
    local_marketing DECIMAL(10,2) DEFAULT 0.00,
    allocated_corporate_marketing DECIMAL(10,2) DEFAULT 0.00,

    -- Technology
    pos_subscription DECIMAL(10,2) DEFAULT 0.00,
    payment_processing_fees DECIMAL(10,2) DEFAULT 0.00,
    software_licenses DECIMAL(10,2) DEFAULT 0.00,

    -- Administrative
    allocated_admin_costs DECIMAL(10,2) DEFAULT 0.00,
    legal_compliance DECIMAL(10,2) DEFAULT 0.00,
    bank_fees DECIMAL(10,2) DEFAULT 0.00,

    -- Miscellaneous
    other_expenses DECIMAL(10,2) DEFAULT 0.00,

    -- Totals
    total_fixed_costs DECIMAL(10,2) DEFAULT 0.00,
    total_variable_costs DECIMAL(10,2) DEFAULT 0.00,
    total_overhead DECIMAL(10,2) DEFAULT 0.00,

    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_outlet_month (outlet_id, period_month),
    INDEX idx_outlet (outlet_id),
    INDEX idx_month (period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 5: benchmark_metrics (PERFORMANCE BENCHMARKS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS benchmark_metrics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Period
    metric_date DATE NOT NULL,
    metric_period ENUM('daily', 'weekly', 'monthly') DEFAULT 'monthly',

    -- Revenue Benchmarks
    revenue_per_sqm DECIMAL(10,2) DEFAULT NULL COMMENT 'Revenue per square meter',
    revenue_per_staff_hour DECIMAL(10,2) DEFAULT NULL,
    revenue_per_transaction DECIMAL(10,2) DEFAULT NULL,

    -- Profitability
    gross_margin_pct DECIMAL(5,2) DEFAULT NULL,
    net_margin_pct DECIMAL(5,2) DEFAULT NULL,
    ebitda_pct DECIMAL(5,2) DEFAULT NULL,

    -- Efficiency
    labor_cost_pct DECIMAL(5,2) DEFAULT NULL COMMENT '% of revenue',
    overhead_cost_pct DECIMAL(5,2) DEFAULT NULL,
    break_even_revenue DECIMAL(12,2) DEFAULT NULL,

    -- Traffic & Conversion
    customer_conversion_rate DECIMAL(5,2) DEFAULT NULL COMMENT 'Transactions / Visitors',
    repeat_customer_rate DECIMAL(5,2) DEFAULT NULL,
    basket_size DECIMAL(10,2) DEFAULT NULL,

    -- Inventory
    stock_turn_days DECIMAL(6,2) DEFAULT NULL,
    stockout_rate DECIMAL(5,2) DEFAULT NULL,
    shrinkage_pct DECIMAL(5,2) DEFAULT NULL,

    -- Customer Satisfaction
    nps_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Net Promoter Score',
    google_rating DECIMAL(3,2) DEFAULT NULL,
    complaint_rate DECIMAL(5,2) DEFAULT NULL,

    -- Rankings (1 = best)
    rank_revenue INT DEFAULT NULL,
    rank_profit INT DEFAULT NULL,
    rank_growth INT DEFAULT NULL,
    rank_efficiency INT DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_outlet_metric_date (outlet_id, metric_date, metric_period),
    INDEX idx_outlet (outlet_id),
    INDEX idx_date (metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 6: forecasts (PREDICTIVE ANALYTICS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS forecasts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Forecast Period
    forecast_date DATE NOT NULL COMMENT 'Date being forecasted',
    forecast_period ENUM('daily', 'weekly', 'monthly', 'quarterly') DEFAULT 'monthly',

    -- Generated
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    forecast_model VARCHAR(100) DEFAULT 'linear_regression' COMMENT 'Model used',
    confidence_level DECIMAL(5,2) DEFAULT NULL COMMENT 'Confidence %',

    -- Revenue Forecast
    forecasted_revenue DECIMAL(12,2) DEFAULT NULL,
    forecasted_revenue_low DECIMAL(12,2) DEFAULT NULL COMMENT 'Low estimate',
    forecasted_revenue_high DECIMAL(12,2) DEFAULT NULL COMMENT 'High estimate',

    -- Profit Forecast
    forecasted_gross_profit DECIMAL(12,2) DEFAULT NULL,
    forecasted_net_profit DECIMAL(12,2) DEFAULT NULL,

    -- Actuals (filled in after period)
    actual_revenue DECIMAL(12,2) DEFAULT NULL,
    actual_net_profit DECIMAL(12,2) DEFAULT NULL,
    variance_pct DECIMAL(6,2) DEFAULT NULL COMMENT 'Forecast vs Actual %',

    -- Assumptions
    assumptions TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_outlet_forecast_date (outlet_id, forecast_date, forecast_period),
    INDEX idx_outlet (outlet_id),
    INDEX idx_forecast_date (forecast_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 7: target_settings (GOALS & TARGETS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS target_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Period
    target_year INT NOT NULL,
    target_month INT DEFAULT NULL COMMENT '1-12, NULL = annual',

    -- Revenue Targets
    revenue_target DECIMAL(12,2) DEFAULT NULL,
    gross_profit_target DECIMAL(12,2) DEFAULT NULL,
    net_profit_target DECIMAL(12,2) DEFAULT NULL,

    -- Operational Targets
    transaction_count_target INT DEFAULT NULL,
    avg_transaction_value_target DECIMAL(10,2) DEFAULT NULL,
    labor_cost_pct_target DECIMAL(5,2) DEFAULT NULL,

    -- Stretch Goals
    stretch_revenue_target DECIMAL(12,2) DEFAULT NULL,
    stretch_profit_target DECIMAL(12,2) DEFAULT NULL,

    -- Actuals (updated as period progresses)
    actual_revenue DECIMAL(12,2) DEFAULT NULL,
    actual_net_profit DECIMAL(12,2) DEFAULT NULL,
    pct_to_target DECIMAL(6,2) DEFAULT NULL,

    set_by INT UNSIGNED DEFAULT NULL COMMENT 'User ID',
    set_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,

    UNIQUE KEY uk_outlet_period (outlet_id, target_year, target_month),
    INDEX idx_outlet (outlet_id),
    INDEX idx_year (target_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 8: variance_analysis (BUDGET VS ACTUAL)
-- ============================================================================
CREATE TABLE IF NOT EXISTS variance_analysis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT UNSIGNED NOT NULL,

    -- Period
    analysis_date DATE NOT NULL,
    period_type ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',

    -- Revenue Variance
    budgeted_revenue DECIMAL(12,2) DEFAULT 0.00,
    actual_revenue DECIMAL(12,2) DEFAULT 0.00,
    revenue_variance DECIMAL(12,2) DEFAULT 0.00,
    revenue_variance_pct DECIMAL(6,2) DEFAULT 0.00,

    -- Profit Variance
    budgeted_net_profit DECIMAL(12,2) DEFAULT 0.00,
    actual_net_profit DECIMAL(12,2) DEFAULT 0.00,
    profit_variance DECIMAL(12,2) DEFAULT 0.00,
    profit_variance_pct DECIMAL(6,2) DEFAULT 0.00,

    -- Expense Variance
    budgeted_expenses DECIMAL(12,2) DEFAULT 0.00,
    actual_expenses DECIMAL(12,2) DEFAULT 0.00,
    expense_variance DECIMAL(12,2) DEFAULT 0.00,
    expense_variance_pct DECIMAL(6,2) DEFAULT 0.00,

    -- Analysis
    variance_reason TEXT DEFAULT NULL,
    corrective_action TEXT DEFAULT NULL,
    is_favorable BOOLEAN DEFAULT NULL,

    analyzed_by INT UNSIGNED DEFAULT NULL,
    analyzed_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_outlet_analysis_date (outlet_id, analysis_date, period_type),
    INDEX idx_outlet (outlet_id),
    INDEX idx_date (analysis_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- VIEWS: ANALYTICS
-- ============================================================================

-- View: Current Month P&L Summary
CREATE OR REPLACE VIEW vw_current_month_pnl AS
SELECT
    f.outlet_id,
    o.outlet_name,
    o.outlet_code,
    f.snapshot_date,
    f.net_sales,
    f.cogs,
    f.gross_profit,
    f.gross_margin_pct,
    f.total_operating_expenses,
    f.net_profit,
    f.net_margin_pct,
    f.transaction_count,
    f.avg_transaction_value
FROM financial_snapshots f
JOIN outlets o ON f.outlet_id = o.id
WHERE f.period_type = 'monthly'
  AND f.snapshot_date = DATE_FORMAT(CURDATE(), '%Y-%m-01')
ORDER BY f.net_profit DESC;

-- View: Store Profitability Rankings
CREATE OR REPLACE VIEW vw_store_profitability_rankings AS
SELECT
    f.outlet_id,
    o.outlet_name,
    o.outlet_code,
    o.city,
    SUM(f.net_sales) as total_revenue,
    SUM(f.net_profit) as total_profit,
    AVG(f.net_margin_pct) as avg_margin_pct,
    RANK() OVER (ORDER BY SUM(f.net_profit) DESC) as profit_rank,
    RANK() OVER (ORDER BY SUM(f.net_sales) DESC) as revenue_rank
FROM financial_snapshots f
JOIN outlets o ON f.outlet_id = o.id
WHERE f.snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY f.outlet_id
ORDER BY total_profit DESC;

-- View: Monthly Trends
CREATE OR REPLACE VIEW vw_monthly_trends AS
SELECT
    DATE_FORMAT(snapshot_date, '%Y-%m') as month,
    SUM(net_sales) as total_revenue,
    SUM(gross_profit) as total_gross_profit,
    SUM(net_profit) as total_net_profit,
    AVG(gross_margin_pct) as avg_gross_margin,
    AVG(net_margin_pct) as avg_net_margin,
    SUM(transaction_count) as total_transactions
FROM financial_snapshots
WHERE period_type = 'monthly'
GROUP BY DATE_FORMAT(snapshot_date, '%Y-%m')
ORDER BY month DESC;

-- View: Top & Bottom Performers
CREATE OR REPLACE VIEW vw_performance_outliers AS
(
    SELECT
        'Top 5' as category,
        o.outlet_name,
        o.outlet_code,
        f.net_profit as profit,
        f.net_margin_pct as margin_pct,
        f.snapshot_date
    FROM financial_snapshots f
    JOIN outlets o ON f.outlet_id = o.id
    WHERE f.period_type = 'monthly'
      AND f.snapshot_date = DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ORDER BY f.net_profit DESC
    LIMIT 5
)
UNION ALL
(
    SELECT
        'Bottom 5' as category,
        o.outlet_name,
        o.outlet_code,
        f.net_profit as profit,
        f.net_margin_pct as margin_pct,
        f.snapshot_date
    FROM financial_snapshots f
    JOIN outlets o ON f.outlet_id = o.id
    WHERE f.period_type = 'monthly'
      AND f.snapshot_date = DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ORDER BY f.net_profit ASC
    LIMIT 5
);

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER $$

-- Calculate Financial Snapshot from Raw Data
CREATE PROCEDURE IF NOT EXISTS sp_calculate_financial_snapshot(
    IN p_outlet_id INT,
    IN p_start_date DATE,
    IN p_end_date DATE,
    IN p_period_type VARCHAR(20)
)
BEGIN
    DECLARE v_gross_sales DECIMAL(12,2);
    DECLARE v_returns DECIMAL(12,2);
    DECLARE v_discounts DECIMAL(12,2);
    DECLARE v_net_sales DECIMAL(12,2);
    DECLARE v_cogs DECIMAL(12,2);
    DECLARE v_gross_profit DECIMAL(12,2);
    DECLARE v_gross_margin_pct DECIMAL(5,2);

    -- Calculate from Lightspeed data (placeholder - replace with actual logic)
    SELECT
        COALESCE(SUM(total_sales), 0),
        COALESCE(SUM(returns), 0),
        COALESCE(SUM(discounts), 0)
    INTO v_gross_sales, v_returns, v_discounts
    FROM outlet_revenue_snapshots
    WHERE outlet_id = p_outlet_id
      AND snapshot_date BETWEEN p_start_date AND p_end_date;

    SET v_net_sales = v_gross_sales - v_returns - v_discounts;
    SET v_cogs = v_net_sales * 0.35; -- Placeholder: 35% COGS
    SET v_gross_profit = v_net_sales - v_cogs;
    SET v_gross_margin_pct = IF(v_net_sales > 0, (v_gross_profit / v_net_sales) * 100, 0);

    -- Insert or update snapshot
    INSERT INTO financial_snapshots (
        outlet_id, snapshot_date, period_type,
        period_start_date, period_end_date,
        gross_sales, returns, discounts, net_sales,
        cogs, gross_profit, gross_margin_pct,
        data_source, sync_status, synced_at
    ) VALUES (
        p_outlet_id, p_end_date, p_period_type,
        p_start_date, p_end_date,
        v_gross_sales, v_returns, v_discounts, v_net_sales,
        v_cogs, v_gross_profit, v_gross_margin_pct,
        'auto', 'synced', NOW()
    )
    ON DUPLICATE KEY UPDATE
        gross_sales = v_gross_sales,
        returns = v_returns,
        discounts = v_discounts,
        net_sales = v_net_sales,
        cogs = v_cogs,
        gross_profit = v_gross_profit,
        gross_margin_pct = v_gross_margin_pct,
        synced_at = NOW();

END$$

DELIMITER ;

-- ============================================================================
-- COMPLETE
-- ============================================================================
