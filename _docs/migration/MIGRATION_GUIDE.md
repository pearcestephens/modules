# CIS Modules - Migration Guide
**Target:** Move from Intelligence Hub (hdgwrzntwa) to CIS Staff Portal (jcepnzzkmj)
**Date:** November 6, 2025

---

## Quick Migration Steps

### 1. Prepare Target Application (CIS Staff Portal)
```bash
# SSH into CIS application
cd /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html

# Create modules directory
mkdir -p modules/stock_transfer_engine
mkdir -p modules/human_behavior
mkdir -p modules/crawlers
mkdir -p modules/dynamic_pricing
mkdir -p modules/ai_intelligence
```

### 2. Transfer Files
```bash
# Option A: Using rsync (recommended)
rsync -avz /home/129337.cloudwaysapps.com/hdgwrzntwa/public_html/CIS_MODULES/ \
           /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules/

# Option B: Using cp
cp -r /home/129337.cloudwaysapps.com/hdgwrzntwa/public_html/CIS_MODULES/* \
      /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules/
```

### 3. Import Database Tables
```bash
# Connect to CIS database
mysql -u jcepnzzkmj -p jcepnzzkmj

# Import stock transfer schema
SOURCE /path/to/CIS_MODULES/stock_transfer_engine/database/stock_transfer_engine_schema.sql;

# Verify tables created
SHOW TABLES LIKE '%transfer%';
SHOW TABLES LIKE '%excess%';
SHOW TABLES LIKE '%freight%';
```

### 4. Update Configuration Files

#### A. Update Database Connections
**File:** `modules/stock_transfer_engine/services/VendTransferAPI.php`
```php
// OLD (Intelligence Hub):
$this->db = new PDO('mysql:host=localhost;dbname=hdgwrzntwa', 'hdgwrzntwa', 'password');

// NEW (CIS Portal):
$this->db = new PDO('mysql:host=localhost;dbname=jcepnzzkmj', 'jcepnzzkmj', 'password');
```

#### B. Update File Paths
**File:** `modules/stock_transfer_engine/config/warehouses.php`
```php
// Update any absolute paths from:
// /home/129337.cloudwaysapps.com/hdgwrzntwa/public_html/
// TO:
// /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/
```

#### C. Update Warehouse Configuration
**File:** `modules/stock_transfer_engine/config/warehouses.php`
```php
return [
    'mode' => getenv('WAREHOUSE_MODE') ?: 'single',
    'primary_warehouse_id' => getenv('PRIMARY_WAREHOUSE_ID') ?: 'frankton_001',
    'secondary_warehouse_id' => getenv('SECONDARY_WAREHOUSE_ID') ?: null,
    'juice_manufacturing_outlet_id' => 'frankton_001',
    'fallback_enabled' => true,
    'stock_source_priority' => ['warehouse', 'hub_store', 'flagship', 'any']
];
```

### 5. Update Service Autoloading

#### A. Add to CIS Autoloader
**File:** `/modules/autoload.php` (create if doesn't exist)
```php
<?php
// Stock Transfer Engine
require_once __DIR__ . '/stock_transfer_engine/services/VendTransferAPI.php';
require_once __DIR__ . '/stock_transfer_engine/services/WarehouseManager.php';
require_once __DIR__ . '/stock_transfer_engine/services/ExcessDetectionEngine.php';

// Crawlers
require_once __DIR__ . '/crawlers/CompetitiveIntelCrawler.php';
require_once __DIR__ . '/crawlers/ChromeSessionManager.php';

// Dynamic Pricing
require_once __DIR__ . '/dynamic_pricing/DynamicPricingEngine.php';

// Human Behavior
require_once __DIR__ . '/human_behavior/HumanBehaviorEngine.php';

// AI Intelligence
require_once __DIR__ . '/ai_intelligence/AdvancedIntelligenceEngine.php';
```

### 6. Test Each Module

#### A. Test Stock Transfer Engine
```bash
cd /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html
php -f modules/stock_transfer_engine/test_integration.php
```

#### B. Test Human Behavior Engine
```bash
php -f modules/human_behavior/test_chaotic_boundaries.php
```

#### C. Test Crawlers
```bash
php -f modules/crawlers/test_crawler_tool.php
```

### 7. Update Environment Variables

**File:** `/home/129337.cloudwaysapps.com/jcepnzzkmj/.env`
```bash
# Warehouse Configuration
WAREHOUSE_MODE=single
PRIMARY_WAREHOUSE_ID=frankton_001
JUICE_MANUFACTURING_OUTLET=frankton_001

# Vend API (copy from existing CIS config)
VEND_DOMAIN_PREFIX=your_domain
VEND_PERSONAL_ACCESS_TOKEN=your_token

# Courier APIs (when ready)
NZ_POST_API_KEY=your_key
NZ_COURIERS_API_KEY=your_key
```

---

## Module-Specific Migration Instructions

### Stock Transfer Engine

**Dependencies:**
- Vend API credentials (already in CIS)
- Database: 10 tables
- Config: warehouses.php

**Integration Points:**
1. Connect to existing Vend service in CIS
2. Use existing vend_* synced tables
3. Add to CIS navigation menu
4. Create dashboard page

**Critical Files:**
- `VendTransferAPI.php` - Update DB connection
- `WarehouseManager.php` - Update config path
- `ExcessDetectionEngine.php` - Update DB connection

**Testing:**
```php
// Test VendTransferAPI
$api = new VendTransferAPI();
$stockLevels = $api->pullStockLevels('frankton_001');
print_r($stockLevels);

// Test WarehouseManager
$manager = new WarehouseManager();
$source = $manager->getStockSource('5L-STRAWBERRY-JUICE', 1000);
print_r($source);

// Test ExcessDetectionEngine
$engine = new ExcessDetectionEngine($db, $api, $manager);
$alerts = $engine->detectOverstock();
print_r($alerts);
```

---

### Human Behavior Engine

**Dependencies:**
- None (standalone)

**Integration Points:**
- Use for automated testing
- Bot detection evasion
- QA automation

**Testing:**
```php
$engine = new HumanBehaviorEngine();
$session = $engine->startSession();
$engine->simulateTyping("Test input");
$engine->simulateMouseMovement(100, 200);
```

---

### Crawler System

**Dependencies:**
- Chrome/Chromium installed
- ChromeSessionManager
- Database: 3 tables

**Integration Points:**
- Competitive intelligence dashboard
- Dynamic pricing engine
- Product monitoring

**Testing:**
```php
$crawler = new CompetitiveIntelCrawler();
$results = $crawler->crawlCompetitor('https://competitor.com');
print_r($results);
```

---

### Dynamic Pricing Engine

**Dependencies:**
- Crawler system (price data)
- Product database
- Database: 1 table

**Integration Points:**
- CIS product management
- Pricing dashboard
- Automated price updates

**Testing:**
```php
$engine = new DynamicPricingEngine($db);
$recommendations = $engine->generateRecommendations();
print_r($recommendations);
```

---

## Post-Migration Verification

### Database Checks
```sql
-- Verify all tables exist
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'jcepnzzkmj'
AND TABLE_NAME LIKE '%transfer%'
OR TABLE_NAME LIKE '%excess%'
OR TABLE_NAME LIKE '%crawler%'
OR TABLE_NAME LIKE '%pricing%';

-- Check table structures
DESCRIBE stock_transfers;
DESCRIBE excess_stock_alerts;
DESCRIBE crawler_logs;
DESCRIBE dynamic_pricing_recommendations;
```

### File Permission Checks
```bash
# Ensure proper permissions
find /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules -type f -exec chmod 644 {} \;
find /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules -type d -exec chmod 755 {} \;

# Set ownership
chown -R jcepnzzkmj:www-data /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules
```

### API Connectivity Tests
```bash
# Test Vend API
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://yourdomain.vendhq.com/api/2.0/products | head -50

# Test database connectivity
php -r "new PDO('mysql:host=localhost;dbname=jcepnzzkmj', 'jcepnzzkmj', 'password');"
```

---

## Common Issues & Solutions

### Issue 1: Database Connection Errors
**Symptom:** "Access denied for user" or "Unknown database"
**Solution:**
```php
// Verify credentials in each service file
$this->db = new PDO(
    'mysql:host=localhost;dbname=jcepnzzkmj',
    'jcepnzzkmj',
    'YOUR_PASSWORD_HERE'
);
```

### Issue 2: Missing Tables
**Symptom:** "Table doesn't exist" errors
**Solution:**
```bash
# Re-import schema
mysql -u jcepnzzkmj -p jcepnzzkmj < stock_transfer_engine_schema.sql
```

### Issue 3: File Not Found
**Symptom:** "require_once: No such file or directory"
**Solution:**
```php
// Use absolute paths
require_once __DIR__ . '/relative/path/to/file.php';
// OR
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/path/to/file.php';
```

### Issue 4: Vend API Not Working
**Symptom:** "Unauthorized" or empty responses
**Solution:**
1. Verify token in .env file
2. Check token hasn't expired
3. Verify domain prefix is correct
4. Test with curl first

---

## Integration with Existing CIS Features

### 1. Add to CIS Navigation
**File:** `includes/navigation.php`
```php
<li><a href="/modules/stock_transfer_engine/dashboard.php">Stock Transfers</a></li>
<li><a href="/modules/crawlers/crawler-monitor.php">Crawler Monitor</a></li>
<li><a href="/modules/dynamic_pricing/dashboard.php">Dynamic Pricing</a></li>
```

### 2. Add to CIS Dashboard Widgets
**File:** `dashboard/index.php`
```php
// Excess Stock Alerts Widget
$engine = new ExcessDetectionEngine($db, $vendAPI, $warehouseManager);
$criticalAlerts = $engine->detectOverstock(['severity' => 'critical']);
include 'widgets/excess_stock_alerts.php';

// Pricing Recommendations Widget
$pricingEngine = new DynamicPricingEngine($db);
$recommendations = $pricingEngine->getTopRecommendations(10);
include 'widgets/pricing_recommendations.php';
```

### 3. Add Cron Jobs
**File:** `/etc/crontab` or CIS cron system
```bash
# Run excess detection daily at 6am
0 6 * * * php /path/to/modules/stock_transfer_engine/cron/detect_excess.php

# Update crawler data every 6 hours
0 */6 * * * php /path/to/modules/crawlers/cron-competitive.php

# Generate pricing recommendations daily at 7am
0 7 * * * php /path/to/modules/dynamic_pricing/cron/generate_recommendations.php
```

---

## Rollback Plan

If migration fails, revert with:

```bash
# Backup current state first
mysqldump -u jcepnzzkmj -p jcepnzzkmj > cis_backup_pre_migration.sql

# Remove modules
rm -rf /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules

# Drop tables if needed
mysql -u jcepnzzkmj -p jcepnzzkmj -e "DROP TABLE IF EXISTS stock_transfers, stock_transfer_items, excess_stock_alerts, ..."

# Restore from backup
mysql -u jcepnzzkmj -p jcepnzzkmj < cis_backup_pre_migration.sql
```

---

## Success Criteria

Migration is successful when:

- ✅ All 14 database tables exist in jcepnzzkmj database
- ✅ All PHP files execute without errors
- ✅ Stock Transfer Engine can pull data from Vend
- ✅ Excess Detection Engine generates alerts
- ✅ Crawler system can access targets
- ✅ Dynamic Pricing Engine generates recommendations
- ✅ All tests pass (see test files in each module)
- ✅ Dashboard pages load without errors
- ✅ Cron jobs execute successfully

---

## Support & Documentation

**Internal Documentation:**
- INDEX.md - Complete file inventory
- Each module has inline documentation
- Database schemas include comments

**External Resources:**
- Vend API Docs: https://docs.vendhq.com/
- NZ Post API: https://www.nzpost.co.nz/business/sending-within-nz/api
- NZ Couriers API: Contact for documentation

**Contacts:**
- Owner: Pearce Stephens (pearce.stephens@ecigdis.co.nz)
- CIS Portal: staff.vapeshed.co.nz
- Intelligence Hub: gpt.ecigdis.co.nz

---

**END OF MIGRATION GUIDE**
