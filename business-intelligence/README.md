# 📊 Business Intelligence Module - Complete Documentation

## Overview

The **Business Intelligence Module** provides comprehensive financial analytics, P&L tracking, profitability analysis, and forecasting for all 19 retail locations. It delivers executive insights, store comparisons, cost breakdowns, and predictive analytics.

---

## 🎯 Features

### Financial Analytics
- **Complete P&L**: Revenue, COGS, gross profit, operating expenses, net profit per store
- **Profitability Rankings**: Top/bottom performers by profit, revenue, margin
- **Revenue Trends**: Daily/weekly/monthly trend analysis with YoY comparisons
- **Cost Breakdown**: Detailed expense categorization and allocation
- **Margin Analysis**: Gross and net margins by store and category

### Visualization
- **Revenue Trend Chart**: Line chart showing revenue and profit over time
- **Revenue Mix Chart**: Doughnut chart of product category breakdown
- **Cost Breakdown Chart**: Bar chart of expense categories
- **Forecast Chart**: Historical + predictive profit forecasting
- **Performance Heatmap**: Color-coded squares showing profit margin per store

### Key Metrics
- **Total Revenue**: Aggregated sales across all stores
- **Net Profit**: Bottom-line profitability
- **Average Margin**: Gross and net margins
- **Transaction Count**: Total sales transactions
- **Store Rankings**: Best/worst performers across multiple dimensions

---

## 🗃️ Database Schema

### Tables (8 total)

#### 1. `financial_snapshots` (Complete P&L)
**Primary financial record per store per period**

**Revenue Section:**
- `gross_sales`, `returns`, `discounts`, `net_sales`

**COGS Section:**
- `opening_stock_value`, `purchases`, `closing_stock_value`, `cogs`

**Gross Profit:**
- `gross_profit` = net_sales - cogs
- `gross_margin_pct` = (gross_profit / net_sales) × 100

**Operating Expenses:**
- Staff: `staff_wages`, `staff_super`, `staff_other`, `total_staff_costs`
- Occupancy: `rent`, `utilities`, `insurance`, `maintenance`
- Marketing: `marketing`
- Technology: `pos_fees`, `bank_fees`
- Other: `other_expenses`
- `total_operating_expenses`

**Net Profit:**
- `net_profit` = gross_profit - total_operating_expenses
- `net_margin_pct` = (net_profit / net_sales) × 100

**Comparisons:**
- `vs_last_period_pct`, `vs_last_year_pct`

**Period Types**: daily, weekly, monthly, quarterly, yearly

#### 2. `revenue_by_category`
Product mix breakdown
- **Fields**: `outlet_id`, `snapshot_date`, `category_name`, `quantity_sold`, `revenue`, `cogs`, `gross_profit`, `margin_pct`, `revenue_pct_of_total`
- **Categories**: Nicotine, Hardware, Accessories, etc.

#### 3. `staff_costs_detail`
Labor cost breakdown
- **Hours**: `total_hours_worked`, `overtime_hours`
- **Costs**: `base_wages`, `overtime_pay`, `superannuation`, `kiwisaver`, `leave_accrual`, `other_costs`
- **Efficiency**: `cost_per_hour`, `staff_count`
- **Integration**: `deputy_sync_at`, `xero_sync_at`

#### 4. `overhead_allocation`
Monthly expense allocation per store
- **Fixed Costs**: `rent`, `insurance`, `rates_taxes`
- **Variable Costs**: `utilities`, `phone_internet`, `cleaning`, `maintenance`
- **Marketing**: `local_marketing`, `allocated_corporate_marketing`
- **Technology**: `pos_subscription`, `payment_processing_fees`, `software_licenses`
- **Admin**: `allocated_admin_costs`, `legal_compliance`, `bank_fees`
- **Totals**: `total_fixed_costs`, `total_variable_costs`, `total_overhead`

#### 5. `benchmark_metrics`
Performance KPIs and benchmarks
- **Revenue Benchmarks**: `revenue_per_sqm`, `revenue_per_staff_hour`, `revenue_per_transaction`
- **Profitability**: `gross_margin_pct`, `net_margin_pct`, `ebitda_pct`
- **Efficiency**: `labor_cost_pct`, `overhead_cost_pct`, `break_even_revenue`
- **Traffic**: `customer_conversion_rate`, `repeat_customer_rate`, `basket_size`
- **Inventory**: `stock_turn_days`, `stockout_rate`, `shrinkage_pct`
- **Customer Satisfaction**: `nps_score`, `google_rating`, `complaint_rate`
- **Rankings**: `rank_revenue`, `rank_profit`, `rank_growth`, `rank_efficiency`

#### 6. `forecasts`
Predictive analytics
- **Forecast Period**: `forecast_date`, `forecast_period`, `generated_at`
- **Model**: `forecast_model` (e.g., linear_regression), `confidence_level`
- **Revenue Forecast**: `forecasted_revenue`, `forecasted_revenue_low`, `forecasted_revenue_high`
- **Profit Forecast**: `forecasted_gross_profit`, `forecasted_net_profit`
- **Actuals**: `actual_revenue`, `actual_net_profit`, `variance_pct`

#### 7. `target_settings`
Goals and targets per store
- **Period**: `target_year`, `target_month`
- **Targets**: `revenue_target`, `gross_profit_target`, `net_profit_target`, `transaction_count_target`, `avg_transaction_value_target`, `labor_cost_pct_target`
- **Stretch Goals**: `stretch_revenue_target`, `stretch_profit_target`
- **Actuals**: `actual_revenue`, `actual_net_profit`, `pct_to_target`

#### 8. `variance_analysis`
Budget vs Actual comparison
- **Revenue Variance**: `budgeted_revenue`, `actual_revenue`, `revenue_variance`, `revenue_variance_pct`
- **Profit Variance**: `budgeted_net_profit`, `actual_net_profit`, `profit_variance`, `profit_variance_pct`
- **Expense Variance**: `budgeted_expenses`, `actual_expenses`, `expense_variance`, `expense_variance_pct`
- **Analysis**: `variance_reason`, `corrective_action`, `is_favorable`

### Views

#### `vw_current_month_pnl`
Current month P&L for all stores
```sql
SELECT outlet_name, net_sales, cogs, gross_profit, gross_margin_pct,
       total_operating_expenses, net_profit, net_margin_pct
FROM financial_snapshots f
JOIN outlets o ON f.outlet_id = o.id
WHERE period_type = 'monthly' AND snapshot_date = CURRENT_MONTH
```

#### `vw_store_profitability_rankings`
Rankings by revenue and profit (last 12 months)
```sql
SELECT outlet_name, total_revenue, total_profit, avg_margin_pct,
       RANK() OVER (ORDER BY total_profit DESC) as profit_rank
FROM financial_snapshots
GROUP BY outlet_id
```

#### `vw_monthly_trends`
Monthly aggregated trends
```sql
SELECT month, total_revenue, total_gross_profit, total_net_profit,
       avg_gross_margin, avg_net_margin, total_transactions
FROM financial_snapshots
GROUP BY month
```

#### `vw_performance_outliers`
Top 5 and Bottom 5 performers
```sql
(SELECT 'Top 5', outlet_name, net_profit, net_margin_pct ... LIMIT 5)
UNION ALL
(SELECT 'Bottom 5', outlet_name, net_profit, net_margin_pct ... LIMIT 5)
```

### Stored Procedures

#### `sp_calculate_financial_snapshot`
Auto-calculate P&L from raw data
```sql
CALL sp_calculate_financial_snapshot(outlet_id, start_date, end_date, period_type);
```
- Pulls Lightspeed sales data
- Calculates COGS (placeholder: 35% of net sales)
- Computes gross and net profit
- Inserts/updates `financial_snapshots` table

---

## 🎨 User Interface

### Dashboard (`dashboard.php`)

#### Executive Summary Cards
- **Total Revenue**: $1.52M (+15.3% vs last period)
- **Net Profit**: $345K (+8.2% vs last period)
- **Avg Margin**: 22.7% (gross margin)
- **Transactions**: 28,450 (+12.5% vs last period)

#### Charts

**Revenue Trend Chart** (Line Chart)
- Dual-axis: Revenue (green) and Net Profit (blue)
- Time series with date labels
- Hover tooltips show exact values

**Revenue Mix Chart** (Doughnut Chart)
- Product categories as segments
- Percentage and dollar value on hover
- Legend at bottom

**Cost Breakdown Chart** (Bar Chart)
- Expense categories: Staff, Rent, Utilities, Marketing, Insurance, Other
- Color-coded bars
- Dollar values on Y-axis

**Profit Forecast Chart** (Line Chart)
- Historical profit (solid blue line)
- Forecasted profit (dashed yellow line)
- Last 30 days historical + next 7 days forecast

#### Store Performance Table
Sortable table with columns:
- **Rank**: #1, #2, etc.
- **Store**: Name and code
- **Revenue**: Total sales
- **COGS**: Cost of goods
- **Gross Profit**: Revenue - COGS
- **Operating Expenses**: Total overhead
- **Net Profit**: Bottom line (color-coded green/red)
- **Margin %**: Net margin percentage
- **vs Last Period**: % change with arrow (↑/↓)

**Sort Options**: Revenue / Profit / Margin %

#### Performance Heatmap
Grid of color-coded squares (one per store):
- **Green**: ≥25% margin (excellent)
- **Teal**: 20-25% margin (good)
- **Yellow**: 15-20% margin (acceptable)
- **Orange**: 10-15% margin (warning)
- **Red**: <10% margin (critical)

Each square shows:
- Outlet code
- Margin percentage
- Net profit

Click square → View detailed P&L for that store

---

## 📡 API Endpoints

### GET `/api/get-financial-data.php`
**Returns**: Complete financial analytics data

**Query Parameters:**
- `period` (required): today / 7days / 30days / this_month / last_month / this_year

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_revenue": 1520000,
      "net_profit": 345000,
      "avg_margin": 22.7,
      "total_transactions": 28450
    },
    "revenue_trend": [
      {"date": "2025-11-01", "revenue": 52000, "profit": 11500},
      {"date": "2025-11-02", "revenue": 48000, "profit": 10200}
    ],
    "revenue_mix": {
      "Nicotine": 850000,
      "Hardware": 420000,
      "Accessories": 250000
    },
    "store_performance": [
      {
        "outlet_id": 1,
        "outlet_name": "The Vape Shed - Queen Street",
        "outlet_code": "VS-QST",
        "revenue": 95000,
        "cogs": 33250,
        "gross_profit": 61750,
        "operating_expenses": 42000,
        "net_profit": 19750,
        "margin_pct": 20.8,
        "vs_last_period": 12.5
      }
    ],
    "cost_breakdown": {
      "Staff Costs": 180000,
      "Rent": 120000,
      "Utilities": 25000,
      "Marketing": 18000,
      "Insurance": 12000,
      "Other": 15000
    },
    "forecast": {
      "historical_dates": ["2025-10-01", "2025-10-02"],
      "historical_values": [11500, 10200],
      "forecast_dates": ["2025-11-06", "2025-11-07"],
      "forecast_values": [10850, 10850]
    }
  },
  "period": "30days",
  "date_range": {
    "start": "2025-10-06",
    "end": "2025-11-05"
  }
}
```

### GET `/api/export-report.php`
**Exports financial report to Excel/CSV**

**Query Parameters:**
- `period` (required): same as get-financial-data.php
- `format` (optional): excel / csv (default: excel)

**Response**: Downloads file

---

## 💻 Installation

### Step 1: Install Database Schema
```bash
mysql -u root -p your_database < modules/business-intelligence/database/schema.sql
```

### Step 2: Verify Tables and Views
```sql
SHOW TABLES LIKE 'financial%';
SHOW TABLES LIKE 'revenue%';
SHOW TABLES LIKE 'benchmark%';
SHOW FULL TABLES WHERE Table_type = 'VIEW';
```

### Step 3: Verify Stored Procedure
```sql
SHOW PROCEDURE STATUS WHERE Db = 'your_database';
```

### Step 4: Load Initial Data
```sql
-- Call procedure to generate snapshots for all outlets
CALL sp_calculate_financial_snapshot(1, '2025-10-01', '2025-10-31', 'monthly');
```

### Step 5: Access Dashboard
Navigate to: `http://staff.vapeshed.co.nz/modules/business-intelligence/dashboard.php`

---

## 🔧 Configuration

### Chart.js
Already included via CDN in `dashboard.php`:
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
```

### Period Selector
Default periods available:
- Today
- Last 7 Days
- Last 30 Days (default)
- This Month
- Last Month
- This Year

Add custom periods in `get-financial-data.php`:
```php
case 'custom':
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
    break;
```

---

## 🚀 Usage Examples

### Manual P&L Entry
```sql
INSERT INTO financial_snapshots (
    outlet_id, snapshot_date, period_type,
    period_start_date, period_end_date,
    gross_sales, returns, discounts, net_sales,
    cogs, gross_profit, gross_margin_pct,
    total_staff_costs, rent, utilities, marketing,
    total_operating_expenses, net_profit, net_margin_pct
) VALUES (
    1, '2025-11-01', 'monthly',
    '2025-11-01', '2025-11-30',
    98000, 2000, 1000, 95000,
    33250, 61750, 65.0,
    28000, 8000, 2500, 1500,
    42000, 19750, 20.8
);
```

### Set Targets
```sql
INSERT INTO target_settings (
    outlet_id, target_year, target_month,
    revenue_target, net_profit_target
) VALUES (
    1, 2025, 11, 100000, 22000
);
```

### Generate Forecast
```sql
INSERT INTO forecasts (
    outlet_id, forecast_date, forecast_period,
    forecast_model, confidence_level,
    forecasted_revenue, forecasted_net_profit
) VALUES (
    1, '2025-12-01', 'monthly',
    'linear_regression', 85.5,
    105000, 23500
);
```

---

## 📊 Integration Points

### Lightspeed/Vend POS
- Daily sales sync → `financial_snapshots.net_sales`
- Transaction count → `financial_snapshots.transaction_count`
- Product category sales → `revenue_by_category`

**Sync Script Example:**
```php
// Pseudo-code for daily sync
$sales = $lightspeedAPI->getSales($outletId, $date);
$pdo->prepare("
    INSERT INTO financial_snapshots (outlet_id, snapshot_date, gross_sales, ...)
    VALUES (?, ?, ?, ...)
    ON DUPLICATE KEY UPDATE gross_sales = VALUES(gross_sales)
")->execute([$outletId, $date, $sales['total']]);
```

### Xero Accounting
- Weekly expense sync → `overhead_allocation`
- Rent, utilities, insurance → respective fields
- Bank fees → `overhead_allocation.bank_fees`

### Deputy Timesheets
- Weekly labor cost sync → `staff_costs_detail`
- Hours worked → `staff_costs_detail.total_hours_worked`
- Wages → `staff_costs_detail.base_wages`

---

## 🎯 Roadmap

- [ ] Automated daily Lightspeed sync (cron job)
- [ ] Weekly Xero expense sync
- [ ] Weekly Deputy labor cost sync
- [ ] Email alerts for underperforming stores
- [ ] Custom date range selector
- [ ] Export to Excel with formatting
- [ ] Drill-down P&L detail page per store
- [ ] Budget vs Actual variance alerts
- [ ] Predictive analytics with ML models
- [ ] Mobile-responsive dashboard
- [ ] Real-time data refresh (WebSocket)

---

## 🧪 Testing

### Verify Data Flow
1. Check `financial_snapshots` has data for current month:
```sql
SELECT * FROM financial_snapshots WHERE snapshot_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01');
```

2. Test stored procedure:
```sql
CALL sp_calculate_financial_snapshot(1, CURDATE(), CURDATE(), 'daily');
SELECT * FROM financial_snapshots WHERE outlet_id = 1 AND snapshot_date = CURDATE();
```

3. Test API:
```bash
curl "http://staff.vapeshed.co.nz/modules/business-intelligence/api/get-financial-data.php?period=30days"
```

4. Test charts render:
   - Open dashboard
   - Inspect browser console for JS errors
   - Verify Chart.js initializes

---

## 📞 Support

For issues or questions:
- **Email**: pearce.stephens@ecigdis.co.nz
- **Internal**: Submit ticket at helpdesk.vapeshed.co.nz

---

## 📜 License

Internal use only - Ecigdis Limited / The Vape Shed
