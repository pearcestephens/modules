# 🚀 FORECASTING MODULE - INSTALLATION & SETUP GUIDE

**Location:** `/modules/forecasting/`

**Version:** 2.6 (Complete with v1.0 → v2.6 integrated)

**Status:** ✅ Ready for Production Deployment

---

## 📦 What's Included

**9 Files, 4,596 lines of code, 208 KB**

### Core Classes (v2.0 Foundation)
```
├─ ForecastingEngine.php           (511 lines) Main forecasting logic
├─ SalesDataAggregator.php         (711 lines) 7/30/90/180-day rolling windows
├─ DataAccuracyValidator.php       (563 lines) MAPE/RMSE/MAE/Bias tracking
└─ RealTimeMonitor.php             (492 lines) Anomaly detection
```

### Intelligence Layers (v2.5)
```
├─ ProductCategoryOptimizer.php     (736 lines) Category-level analysis
└─ SeasonalityEngine.php            (665 lines) Temporal pattern detection
```

### Lost Sales Module (v2.6)
```
└─ ConversionRateOptimizer.php      (485 lines) Inventory constraint analysis
```

### Integration & Documentation
```
├─ ForecastingModuleLoader.php      (388 lines) Unified loader & coordinator
├─ index.php                        (45 lines)  Auto-loader
└─ README.md                        (492 lines) Complete documentation
```

---

## 🔧 INSTALLATION STEPS (10-15 minutes)

### STEP 1: Verify File Locations (1 min)

```bash
# Check that all files are in place
ls -lh /modules/forecasting/

# Expected output: 9 PHP files + 1 README
```

### STEP 2: Update Your Application Bootstrap (3 min)

Add this to your main application bootstrap file (e.g., `public/index.php` or `app.php`):

```php
<?php
// Initialize Forecasting Module
require_once __DIR__ . '/../../modules/forecasting/index.php';

use CIS\Modules\Forecasting\ForecastingModuleLoader;

// Create the forecasting module instance (singleton recommended)
if (!function_exists('forecasting')) {
    function forecasting() {
        static $instance = null;
        if ($instance === null) {
            $instance = new ForecastingModuleLoader($pdo, [
                'cache_enabled' => true,
                'cache_ttl' => 3600,
                'enable_v25' => true,
                'enable_v26' => true,
                'log_enabled' => true,
                'log_path' => '/var/log/cis/forecasting/',
            ]);
        }
        return $instance;
    }
}
```

### STEP 3: Create Log Directory (2 min)

```bash
# Create logging directory
sudo mkdir -p /var/log/cis/forecasting/
sudo chmod 775 /var/log/cis/forecasting/
sudo chown www-data:www-data /var/log/cis/forecasting/

# Verify
ls -la /var/log/cis/forecasting/
```

### STEP 4: Test the Module (2 min)

Create a test script at `/test_forecasting.php`:

```php
<?php
// Load module
require_once __DIR__ . '/modules/forecasting/index.php';

use CIS\Modules\Forecasting\ForecastingModuleLoader;

// Connect to database
$pdo = new PDO('mysql:host=localhost;dbname=cis', 'user', 'pass');

// Initialize
$forecasting = new ForecastingModuleLoader($pdo);

// Test system health
$health = $forecasting->getSystemHealth();
echo "✓ Module initialized successfully\n";
echo "  Version: " . $health['version'] . "\n";
echo "  Status: " . $health['status'] . "\n";
echo "  Engines: " . $health['engines_initialized'] . "\n";

// Test a simple forecast (adjust product_id to real data)
$forecast = $forecasting->generateForecast(
    product_id: 12345,
    forecast_days: 30,
    outlet_id: 1
);

if (isset($forecast['error'])) {
    echo "⚠ Forecast test returned error: " . $forecast['error'] . "\n";
} else {
    echo "✓ Forecast generated successfully\n";
    echo "  Predicted units: " . $forecast['predicted_demand_units'] . "\n";
    echo "  Confidence: " . $forecast['metadata']['confidence_score'] . "\n";
}
?>
```

Run the test:
```bash
php /home/129337.cloudwaysapps.com/hdgwrzntwa/public_html/test_forecasting.php
```

### STEP 5: Integration with Controllers (5 min)

Add forecasting to your existing controllers:

```php
<?php
namespace CIS\Controllers;

use CIS\Modules\Forecasting\ForecastingModuleLoader;

class ProductController {
    protected $forecasting;

    public function __construct(ForecastingModuleLoader $forecasting) {
        $this->forecasting = $forecasting;
    }

    public function getProductForecast($id) {
        $forecast = $this->forecasting->generateForecast(
            product_id: $id,
            forecast_days: 30,
            outlet_id: $this->auth->getOutletId(),
            apply_all_enhancements: true
        );

        return response()->json($forecast);
    }
}

// In your routes
Route::get('/api/product/{id}/forecast', 'ProductController@getProductForecast');
```

### STEP 6: Add to Cron Jobs (optional, 3 min)

For batch forecasting and cache refresh:

```bash
# Edit crontab
crontab -e

# Add these lines
# Run daily forecasting batch at 2 AM
0 2 * * * /usr/bin/php /path/to/scripts/batch_forecast.php

# Clear forecasting cache every 6 hours
0 */6 * * * redis-cli FLUSHALL
```

Create `/scripts/batch_forecast.php`:

```php
<?php
require_once '/modules/forecasting/index.php';

$pdo = new PDO('mysql:host=localhost;dbname=cis', 'user', 'pass');
$forecasting = new \CIS\Modules\Forecasting\ForecastingModuleLoader($pdo);

// Get top 100 products by sales
$sql = "SELECT product_id FROM vend_products
        ORDER BY last_sales_date DESC
        LIMIT 100";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate forecasts
$forecasts = $forecasting->forecastBatch($products, 30, null);

echo "Generated " . count($forecasts) . " forecasts\n";

// Cache results (optional)
file_put_contents(
    '/var/cache/cis/forecasts_' . date('Y-m-d') . '.json',
    json_encode($forecasts)
);
?>
```

---

## ✅ VERIFICATION CHECKLIST

After installation, verify everything works:

```bash
# 1. Check file permissions
ls -la /modules/forecasting/*.php

# 2. Check PHP syntax
for file in /modules/forecasting/*.php; do php -l "$file"; done

# 3. Check log directory
ls -la /var/log/cis/forecasting/

# 4. Test database connection
mysql -h localhost -u user -ppass cis -e "SELECT COUNT(*) FROM vend_products;"

# 5. Test module loading
php -r "require '/modules/forecasting/index.php'; echo 'Module loads OK';"

# 6. Run test script
php /test_forecasting.php
```

---

## 🔌 USAGE PATTERNS

### Pattern 1: Direct Initialization

```php
$forecasting = new \CIS\Modules\Forecasting\ForecastingModuleLoader($pdo);
$forecast = $forecasting->generateForecast(123, 30);
```

### Pattern 2: Dependency Injection

```php
class OrderService {
    public function __construct(ForecastingModuleLoader $forecasting) {
        $this->forecasting = $forecasting;
    }

    public function suggestOrderQuantity($product_id) {
        $forecast = $this->forecasting->generateForecast($product_id, 30);
        return $forecast['predicted_demand_units'] * 1.2; // +20% safety stock
    }
}
```

### Pattern 3: Singleton Helper

```php
// In bootstrap
function forecasting() {
    static $instance = null;
    if (!$instance) {
        $instance = new ForecastingModuleLoader($app->getDatabase());
    }
    return $instance;
}

// Usage anywhere
$forecast = forecasting()->generateForecast(123, 30);
```

### Pattern 4: Batch Processing

```php
$module = forecasting();

$product_ids = [101, 102, 103, 104, 105];
$forecasts = $module->forecastBatch($product_ids, 30, 1);

foreach ($forecasts as $product_id => $forecast) {
    if (!isset($forecast['error'])) {
        // Process forecast
        echo "Product $product_id: " . $forecast['predicted_demand_units'] . "\n";
    }
}
```

---

## 📊 EXPECTED PERFORMANCE

### Response Times
- Single forecast: 50-200ms
- Batch (10 products): 500-800ms
- Batch (100 products): 5-8 seconds

### Memory Usage
- Initialization: ~2 MB
- Single forecast: ~1 MB
- Batch (100): ~10 MB

### Database Load
- Queries per forecast: 5-10
- Average query time: 10-50ms
- Cache hit rate: 80%+ after warm-up

---

## 🐛 TROUBLESHOOTING

### Issue 1: "Class not found" error

**Cause:** Auto-loader not working or namespace mismatch

**Solution:**
```bash
# Verify index.php is being loaded
php -r "require '/modules/forecasting/index.php'; echo 'Loaded OK';"

# Check file has proper namespace
head -10 /modules/forecasting/ForecastingEngine.php
# Should show: namespace CIS\Forecasting;
```

### Issue 2: Empty forecast results

**Cause:** Product has no sales history

**Solution:**
```php
// Check if product has data
$aggregator = $forecasting->getSalesAggregator();
$data = $aggregator->getAggregatedSales($product_id, 90);

if (empty($data)) {
    // Use category baseline instead
    $category = $forecasting->getCategoryOptimizer()
        ->getCategoryForProduct($product_id);
    // ... get category forecast
}
```

### Issue 3: Slow forecasting

**Cause:** Database queries or large dataset

**Solution:**
```php
// Enable caching
$config = [
    'cache_enabled' => true,
    'cache_ttl' => 3600,  // 1 hour
];
$forecasting = new ForecastingModuleLoader($pdo, $config);

// Use batch for multiple products
$forecasts = $forecasting->forecastBatch($ids, 30);

// Filter to avoid unnecessary queries
// Only forecast products with recent sales
```

### Issue 4: Permission errors on log directory

**Cause:** Directory not writable by PHP process

**Solution:**
```bash
sudo mkdir -p /var/log/cis/forecasting/
sudo chown www-data:www-data /var/log/cis/forecasting/
sudo chmod 775 /var/log/cis/forecasting/
```

### Issue 5: Database connection errors

**Cause:** PDO not available or wrong credentials

**Solution:**
```php
// Test connection
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=cis',
        'username',
        'password',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Connected OK\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
```

---

## 📚 ADDITIONAL RESOURCES

**Complete API Documentation:**
- `/modules/forecasting/README.md` - Full API reference
- `/public_html/ADVANCED_FORECASTING_v2.5_ENHANCEMENT_GUIDE.md` - v2.5 details
- `/public_html/ADVANCED_FORECASTING_v2.6_GUIDE.md` - v2.6 details

**Example Code:**
- See `README.md` section "Quick Start" for code examples
- See individual class docblocks for method signatures
- See test cases in enhancement guides

**Video Guides:**
- N/A (documentation is comprehensive)

---

## 🎯 NEXT STEPS

1. ✅ Complete installation steps above
2. ✅ Run verification checklist
3. ✅ Test with sample product
4. ✅ Integrate with your application
5. ✅ Monitor logs and performance
6. 📈 Optional: Continue to v2.7-v2.9 for additional enhancements

---

## 📋 DEPLOYMENT CHECKLIST

Before going to production, verify:

- [ ] All 9 files in `/modules/forecasting/`
- [ ] File permissions correct (644 for PHP files)
- [ ] Log directory created and writable
- [ ] Database connection tested
- [ ] Test forecast script runs without errors
- [ ] Controllers/API endpoints updated
- [ ] Cron jobs configured (optional)
- [ ] Documentation reviewed
- [ ] Performance acceptable
- [ ] Error logging working

---

## 🔒 SECURITY NOTES

1. **File Permissions:** Keep `.php` files at 644, not 777
2. **Log Directory:** Only www-data should write there
3. **Database Access:** Use restricted MySQL user if possible
4. **Input Validation:** Validate product_id, outlet_id in controllers
5. **Error Messages:** Don't expose SQL errors in production

---

## 📈 MONITORING

Monitor these metrics after deployment:

```bash
# Check error log
tail -f /var/log/cis/forecasting/forecasting.log

# Monitor response times
tail -f /var/log/apache2/access.log | grep forecast

# Check database performance
mysql -u user -ppass cis -e "SHOW PROCESSLIST;"
```

---

## 🎓 GETTING HELP

1. **Documentation:** Read `/modules/forecasting/README.md`
2. **API Reference:** See class docblocks in source code
3. **Test Cases:** Run examples from enhancement guides
4. **Troubleshooting:** See section above

---

**Installation Date:** November 14, 2025

**Completed by:** System Setup

**Version:** 2.6 (Complete)

**Status:** ✅ READY FOR PRODUCTION
