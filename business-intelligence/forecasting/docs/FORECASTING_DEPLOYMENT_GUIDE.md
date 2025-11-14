# QUICK START DEPLOYMENT GUIDE
## Advanced Forecasting & Intelligent Ordering System

**Setup Time:** 30 minutes
**Required Skills:** PHP, MySQL, Web server access
**Difficulty:** Intermediate

---

## 5-MINUTE CHECKLIST

- [ ] Review database schema file
- [ ] Copy all files to server
- [ ] Create database tables
- [ ] Update routes configuration
- [ ] Access dashboard and verify

---

## STEP-BY-STEP INSTALLATION

### 1. DATABASE SETUP (5 minutes)

```bash
# Connect to MySQL
mysql -u your_user -p your_database

# Paste entire content of CREATE_ADVANCED_FORECASTING_SCHEMA.sql
source /path/to/CREATE_ADVANCED_FORECASTING_SCHEMA.sql;

# Verify tables created
SHOW TABLES LIKE 'forecast%';
SHOW TABLES LIKE 'demand%';
SHOW TABLES LIKE 'supplier%';
SHOW TABLES LIKE 'lead_time%';
SHOW TABLES LIKE 'shipment%';
SHOW TABLES LIKE 'conversion%';
SHOW TABLES LIKE 'inventory%';
SHOW TABLES LIKE 'intelligent%';
```

### 2. FILE DEPLOYMENT (5 minutes)

```bash
# Copy forecasting engine
mkdir -p /var/www/cis-admin/app/Forecasting
cp ForecastingEngine.php /var/www/cis-admin/app/Forecasting/

# Copy ordering system
mkdir -p /var/www/cis-admin/app/Ordering
cp IntelligentOrderingController.php /var/www/cis-admin/app/Ordering/

# Copy tracking system
mkdir -p /var/www/cis-admin/app/Tracking
cp ShipmentTracker.php /var/www/cis-admin/app/Tracking/

# Copy main controller
cp AdvancedForecastingController.php /var/www/cis-admin/app/Controllers/

# Copy dashboard UI
mkdir -p /var/www/cis-admin/resources/views/forecasting
cp dashboard.html /var/www/cis-admin/resources/views/forecasting/

# Set permissions
chmod 755 /var/www/cis-admin/app/Forecasting
chmod 755 /var/www/cis-admin/app/Ordering
chmod 755 /var/www/cis-admin/app/Tracking
```

### 3. ROUTE CONFIGURATION (3 minutes)

Create file: `/var/www/cis-admin/config/routes-forecasting.php`

```php
<?php
/**
 * Advanced Forecasting & Ordering Routes
 */

return [
    // Forecasting Routes
    ['GET', '/forecasting/dashboard', 'ForecastingController@dashboard'],
    ['GET', '/forecasting/forecast/{product_id}', 'ForecastingController@getProductForecast'],

    // API Routes
    ['GET', '/api/forecasting/dashboard', 'AdvancedForecastingController@getForecastingDashboard'],
    ['GET', '/api/forecasting/forecast/{product_id}', 'AdvancedForecastingController@getProductForecast'],
    ['POST', '/api/forecasting/recommendations', 'AdvancedForecastingController@getOrderingRecommendations'],
    ['POST', '/api/ordering/create-po', 'AdvancedForecastingController@createPurchaseOrder'],
    ['POST', '/api/ordering/consolidate', 'AdvancedForecastingController@consolidateShipments'],
    ['GET', '/api/tracking/shipments', 'AdvancedForecastingController@getShipmentsDashboard'],
    ['GET', '/api/tracking/shipment/{shipment_id}', 'AdvancedForecastingController@getShipmentDetails'],
    ['POST', '/api/tracking/update-status', 'AdvancedForecastingController@updateShipmentStatus'],
    ['GET', '/api/reporting/forecast-accuracy', 'AdvancedForecastingController@getForecastAccuracyReport'],
    ['GET', '/api/reporting/roi-analysis', 'AdvancedForecastingController@getROIAnalysis'],
    ['GET', '/api/reporting/supplier-performance', 'AdvancedForecastingController@getSupplierPerformanceReport'],
];
```

Then include in your main router:

```php
// In your main routing file (e.g., Router.php)
$forecasting_routes = require 'config/routes-forecasting.php';
foreach ($forecasting_routes as $route) {
    $this->registerRoute($route[0], $route[1], $route[2]);
}
```

### 4. ENVIRONMENT CONFIGURATION (2 minutes)

Add to `.env`:

```bash
# Forecasting System
FORECASTING_ENABLED=true
FORECASTING_LOOKBACK_DAYS=180
FORECASTING_FORECAST_DAYS=42
FORECASTING_CONFIDENCE_THRESHOLD=75

# Vend Integration (when implemented)
VEND_API_KEY=your_vend_key_here
VEND_API_SECRET=your_vend_secret_here
VEND_SYNC_ENABLED=false

# Database (if different from main CIS)
FORECASTING_DB_HOST=localhost
FORECASTING_DB_USER=cis_user
FORECASTING_DB_PASS=your_password
FORECASTING_DB_NAME=cis_forecasting

# Notifications
FORECASTING_ALERTS_EMAIL=manager@vapeshed.co.nz
FORECASTING_ALERTS_ENABLED=true
```

### 5. VERIFICATION (5 minutes)

```bash
# Test file permissions
php -l /var/www/cis-admin/app/Forecasting/ForecastingEngine.php
php -l /var/www/cis-admin/app/Ordering/IntelligentOrderingController.php
php -l /var/www/cis-admin/app/Tracking/ShipmentTracker.php
php -l /var/www/cis-admin/app/Controllers/AdvancedForecastingController.php

# Test database connection
mysql -u your_user -p your_database -e "SELECT COUNT(*) as forecast_records FROM forecast_predictions;"

# Access dashboard
open http://your-cis-instance/cis-admin/resources/views/forecasting/dashboard.html
```

---

## QUICK TEST (5 minutes)

Create test file: `/var/www/test-forecasting.php`

```php
<?php
require 'vendor/autoload.php';

// Database connection
$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASS')
);

// Test 1: Create controller
$controller = new \CIS\Controllers\AdvancedForecastingController($pdo);
echo "✓ Controller instantiated\n";

// Test 2: Get dashboard
$dashboard = $controller->getForecastingDashboard();
echo "✓ Dashboard loaded\n";
echo "  - Forecast accuracy: " . $dashboard['key_metrics']['average_forecast_accuracy'] . "\n";
echo "  - Active recommendations: " . count($dashboard['active_recommendations']) . "\n";

// Test 3: Get forecast
$forecast = $controller->getProductForecast('PROD001');
echo "✓ Product forecast generated\n";
echo "  - Predicted demand: " . $forecast['predicted_demand_units'] . " units\n";
echo "  - Confidence: " . $forecast['confidence_level'] . "%\n";

// Test 4: Get recommendations
$recommendations = $controller->getOrderingRecommendations();
echo "✓ Ordering recommendations generated\n";
echo "  - Total recommendations: " . $recommendations['total_recommendations'] . "\n";
echo "  - Total spend: $" . number_format($recommendations['total_recommended_spend_usd'], 2) . "\n";

// Test 5: Track shipments
$shipments = $controller->getShipmentsDashboard();
echo "✓ Shipment dashboard loaded\n";
echo "  - Active shipments: " . $shipments['total_active_shipments'] . "\n";
echo "  - Total value: NZ$" . number_format($shipments['total_value_nz'], 2) . "\n";

echo "\n✅ All tests passed!\n";
?>
```

Run test:
```bash
php /var/www/test-forecasting.php
```

---

## PRODUCTION DEPLOYMENT CHECKLIST

### Pre-Deployment
- [ ] Database backed up
- [ ] All files copied and permissions set
- [ ] Routes configured and tested
- [ ] Environment variables set
- [ ] SSL certificate valid
- [ ] Server has 2GB+ RAM available
- [ ] PHP 7.4+ with PDO MySQL support

### Deployment
- [ ] Deploy code to production
- [ ] Run database migrations
- [ ] Test all routes in production
- [ ] Monitor error logs for issues
- [ ] Enable analytics/monitoring

### Post-Deployment
- [ ] Verify dashboard loads
- [ ] Test API endpoints
- [ ] Confirm alerts working
- [ ] Train staff on usage
- [ ] Monitor performance (first week)
- [ ] Collect feedback and iterate

---

## PERFORMANCE TUNING

### Database Optimization

```sql
-- Analyze table performance
ANALYZE TABLE forecast_predictions;
ANALYZE TABLE demand_signals;
ANALYZE TABLE shipment_tracking_advanced;

-- Check index usage
EXPLAIN SELECT * FROM forecast_predictions WHERE product_id = 'PROD001';

-- Optimize table
OPTIMIZE TABLE forecast_predictions;
OPTIMIZE TABLE supplier_performance_metrics;
```

### Caching Strategy

```php
// Add Redis caching (optional)
$cache_key = 'forecast_' . $product_id;
if ($cached = $redis->get($cache_key)) {
    return json_decode($cached);
}

$forecast = $demand_calc->calculateForecast($product_id);
$redis->setex($cache_key, 300, json_encode($forecast)); // 5 min cache
```

### Load Testing

```bash
# Test dashboard under load (100 concurrent requests)
ab -n 1000 -c 100 http://your-cis-instance/api/forecasting/dashboard

# Test API endpoints
ab -n 1000 -c 100 -m GET http://your-cis-instance/api/tracking/shipments

# Monitor query performance
SET GLOBAL slow_query_log='ON';
SET GLOBAL long_query_time=1; # Log queries >1 second
```

---

## MONITORING & ALERTS

### Health Checks

```php
// Add to main dashboard
$health = [
    'database' => $pdo->query('SELECT 1')->fetchAll() ? 'OK' : 'ERROR',
    'forecast_table' => $pdo->query("SELECT COUNT(*) FROM forecast_predictions")->fetchColumn(),
    'recent_forecasts' => $pdo->query("SELECT COUNT(*) FROM forecast_predictions WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn(),
    'pending_recommendations' => $pdo->query("SELECT COUNT(*) FROM intelligent_order_recommendations WHERE order_status = 'pending'")->fetchColumn(),
    'active_shipments' => $pdo->query("SELECT COUNT(*) FROM shipment_tracking_advanced WHERE status IN ('in_transit', 'in_customs')")->fetchColumn(),
];
```

### Alert Configuration

```php
// Set up notifications
$alerts = [
    'high_demand_variance' => ['email' => true, 'sms' => true, 'slack' => true],
    'supplier_delay' => ['email' => true, 'sms' => true],
    'potential_stockout' => ['email' => true, 'sms' => true, 'slack' => true],
    'forecast_inaccuracy' => ['email' => true],
    'system_error' => ['email' => true, 'sms' => true],
];
```

---

## TROUBLESHOOTING

### Issue: "Class not found" errors

```bash
# Verify autoloading
composer dump-autoload -o
php -r "require 'vendor/autoload.php'; echo 'Autoloader OK';"
```

### Issue: Database connection failed

```bash
# Check credentials
mysql -u user -p -h host -e "SELECT 1;"

# Verify environment variables loaded
php -r "echo getenv('DB_HOST');"
```

### Issue: Slow API responses

```bash
# Check slow query log
SHOW VARIABLES LIKE 'slow_query%';
TAIL -f /var/log/mysql/slow.log

# Add missing indexes
ALTER TABLE forecast_predictions ADD INDEX idx_product_outlet (product_id, outlet_id);
```

### Issue: Memory exhaustion

```bash
# Check PHP memory limit
php -r "echo ini_get('memory_limit');"

# Increase if needed (in php.ini or .htaccess)
php_value memory_limit 512M

# Optimize queries to reduce memory use
```

---

## SUPPORT & DOCUMENTATION

Full documentation available in:
- `ADVANCED_FORECASTING_SYSTEM.md` - Complete feature guide
- `API_REFERENCE.md` - Detailed endpoint documentation
- Inline code comments - Detailed implementation notes

---

**Version:** 1.0
**Status:** ✅ Ready for Production
**Support:** implementation@vapeshed.co.nz
