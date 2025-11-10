# Product Intelligence System - Quick Start Guide

## 🚀 Installation

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/shared/services/product-intelligence
composer install
```

## 📋 Prerequisites

1. **Puppeteer HTTP Server** (for Chrome automation)
2. **OpenAI API Key** (for GPT Vision - Phase 1F)
3. **Redis Server** (for caching)
4. **Product Catalog Database**

## 💻 Basic Usage Examples

### 1. Match Product (SKU-less)

```php
use CIS\SharedServices\ProductIntelligence\Matching\ProductMatcher;

$config = require 'config/product-intelligence.php';
$pdo = new PDO(...); // Your database connection

$matcher = new ProductMatcher($pdo, $config['matching']);

$competitorProduct = [
    'name' => 'SMOK RPM80 Pod Kit Blue 18mg',
    'brand' => 'SMOK',
    'price' => 49.99,
    'nicotine' => '18mg',
    'color' => 'blue',
];

$match = $matcher->matchProduct($competitorProduct);

if ($match['matched']) {
    echo "Found match!\n";
    echo "Confidence: " . ($match['confidence'] * 100) . "%\n";
    echo "Match Level: {$match['match_level']}\n";
    echo "Our Product ID: {$match['our_product_id']}\n";
    echo "Our SKU: {$match['our_sku']}\n";
} else {
    echo "No match found above threshold\n";
}
```

### 2. Extract Product Data

```php
use CIS\SharedServices\ProductIntelligence\Extraction\MultiStrategyExtractor;

$config = require 'config/product-intelligence.php';

$extractor = new MultiStrategyExtractor($config['extraction']);

$result = $extractor->extract('https://competitor.com/product/12345');

echo "Strategies Used: " . count($result['strategies_used']) . "\n";
echo "Overall Confidence: " . ($result['confidence'] * 100) . "%\n";
echo "Extraction Time: {$result['extraction_time']}s\n";

if (!empty($result['data'])) {
    echo "\nExtracted Data:\n";
    echo "Name: " . ($result['data']['name'] ?? 'N/A') . "\n";
    echo "Price: " . ($result['data']['price'] ?? 'N/A') . "\n";
    echo "Brand: " . ($result['data']['brand'] ?? 'N/A') . "\n";
    echo "Stock: " . ($result['data']['stock'] ?? 'N/A') . "\n";
}
```

### 3. Extract Prices with GST Detection

```php
use CIS\SharedServices\ProductIntelligence\Intelligence\PriceExtractor;

$config = require 'config/product-intelligence.php';

$extractor = new PriceExtractor($config['price']);

$html = file_get_contents('https://competitor.com/product/12345');
$prices = $extractor->extractPrices($html);

if ($prices['primary_price']) {
    $price = $prices['primary_price']['value'];
    echo "Primary Price: $" . number_format($price, 2) . "\n";

    echo "GST Status: {$prices['gst_status']}\n";
    echo "GST Included: " . ($prices['gst_included'] ? 'Yes' : 'No') . "\n";
    echo "Confidence: " . ($prices['confidence'] * 100) . "%\n";

    if ($prices['sale_price']) {
        echo "Sale Price: $" . number_format($prices['sale_price']['value'], 2) . "\n";
        echo "Regular Price: $" . number_format($prices['regular_price']['value'], 2) . "\n";
        $savings = $prices['regular_price']['value'] - $prices['sale_price']['value'];
        echo "You Save: $" . number_format($savings, 2) . "\n";
    }
}

// Convert GST
if ($prices['gst_included']) {
    $exclPrice = $extractor->convertGST($price, true, false);
    echo "\nPrice excl GST: $" . number_format($exclPrice, 2) . "\n";
    $gstAmount = $extractor->calculateGSTAmount($price);
    echo "GST Amount: $" . number_format($gstAmount, 2) . "\n";
}
```

### 4. Automate Chrome Browser

```php
use CIS\SharedServices\ProductIntelligence\Chrome\ChromeManager;

$config = require 'config/product-intelligence.php';

$chrome = new ChromeManager($config['chrome']);

// Create session with persistent profile
$session = $chrome->createSession('competitor_site_profile');
$sessionId = $session['session_id'];

// Navigate (auto-bypasses age gates, simulates Google referrer)
$chrome->navigate($sessionId, 'https://competitor.com/product/12345');

// Get HTML
$htmlResult = $chrome->getHTML($sessionId);
$html = $htmlResult['html'];

// Take screenshot
$screenshot = $chrome->screenshot($sessionId, [
    'fullPage' => true,
]);
file_put_contents('screenshot.png', base64_decode($screenshot['image']));

// Enable request interception
$chrome->enableRequestInterception($sessionId);

// Navigate again (to capture API calls)
$chrome->navigate($sessionId, 'https://competitor.com/api/products');

// Get intercepted requests
$requests = $chrome->getInterceptedRequests($sessionId);
foreach ($requests as $req) {
    if (stripos($req['url'], 'api') !== false) {
        echo "Captured API: {$req['url']}\n";
        $data = json_decode($req['response'], true);
        print_r($data);
    }
}

// Close session
$chrome->closeSession($sessionId);
```

### 5. Bypass Cloudflare

```php
use CIS\SharedServices\ProductIntelligence\Bypass\CloudflareBypass;

$config = require 'config/product-intelligence.php';

$bypass = new CloudflareBypass($config['cloudflare']);

$result = $bypass->bypass('https://protected-site.com/product/12345');

if ($result['success']) {
    echo "Bypass successful after {$result['attempts']} attempts\n";
    $html = $result['response']['body'];

    // Use the HTML
    echo "Page length: " . strlen($html) . " bytes\n";
} else {
    echo "Bypass failed: {$result['error']}\n";
    echo "Attempts: {$result['attempts']}\n";
}

// View bypass log
foreach ($result['log'] as $entry) {
    echo "[" . date('H:i:s', $entry['timestamp']) . "] {$entry['message']}\n";
}
```

## 🔧 Configuration

### Environment Variables (.env)

```bash
# Chrome Automation
PUPPETEER_URL=http://localhost:3000

# GPT Vision
OPENAI_API_KEY=sk-...

# CAPTCHA Solving
CAPTCHA_SOLVER=2captcha
CAPTCHA_API_KEY=...

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cis
DB_USERNAME=...
DB_PASSWORD=...

# Logging
LOG_LEVEL=info
```

### Config File Customization

Edit `config/product-intelligence.php` to adjust:
- Matching thresholds and weights
- Extraction strategy priorities
- Price detection patterns
- Chrome automation settings
- Bypass retry limits

## 📊 Database Setup

### Products Table

```sql
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sku VARCHAR(100) UNIQUE,
    name VARCHAR(255) NOT NULL,
    brand VARCHAR(100),
    model VARCHAR(100),
    flavor VARCHAR(100),
    nicotine VARCHAR(50),
    variant VARCHAR(100),
    color VARCHAR(50),
    size VARCHAR(50),
    capacity VARCHAR(50),
    image_url TEXT,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_brand (brand),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Competitive Intelligence Tables (recommended)

```sql
-- Extracted products from competitors
CREATE TABLE IF NOT EXISTS competitor_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    competitor VARCHAR(100) NOT NULL,
    url TEXT NOT NULL,
    name VARCHAR(255),
    brand VARCHAR(100),
    sku VARCHAR(100),
    price DECIMAL(10,2),
    gst_status ENUM('incl', 'excl', 'unknown'),
    stock_status VARCHAR(50),
    extracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    our_product_id INT NULL,
    match_confidence DECIMAL(5,4),
    INDEX idx_competitor (competitor),
    INDEX idx_extracted (extracted_at),
    FOREIGN KEY (our_product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product matching history
CREATE TABLE IF NOT EXISTS product_matches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    competitor_product_id INT NOT NULL,
    our_product_id INT NOT NULL,
    confidence DECIMAL(5,4),
    match_level ENUM('exact', 'strong', 'medium', 'weak'),
    signals JSON,
    matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competitor_product_id) REFERENCES competitor_products(id),
    FOREIGN KEY (our_product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extraction logs
CREATE TABLE IF NOT EXISTS extraction_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    url TEXT NOT NULL,
    strategies_used JSON,
    confidence DECIMAL(5,4),
    extraction_time DECIMAL(6,3),
    data JSON,
    log JSON,
    extracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_extracted (extracted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 🧪 Testing

### Run PHPUnit Tests

```bash
vendor/bin/phpunit
```

### Run Static Analysis

```bash
vendor/bin/phpstan analyse src/ --level=7
```

### Run Code Style Checks

```bash
vendor/bin/phpcs --standard=PSR12 src/
```

## 🐛 Troubleshooting

### Chrome Connection Issues

**Problem**: ChromeManager can't connect to Puppeteer

**Solution**:
1. Ensure Puppeteer HTTP server is running on port 3000
2. Check `PUPPETEER_URL` in config
3. Test with `curl http://localhost:3000`

### Product Matching Low Confidence

**Problem**: Products not matching even when similar

**Solution**:
1. Lower `min_confidence` threshold in config
2. Ensure product catalog database is populated
3. Check brand names match exactly (case-insensitive)
4. Add more attributes to competitor product data

### Price Extraction Failing

**Problem**: Prices not being detected

**Solution**:
1. Check HTML contains visible price text
2. Verify currency format matches patterns (NZ$, $, NZD)
3. Try with more specific extraction (pass HTML section, not full page)
4. Add custom price patterns to PriceExtractor

### Cloudflare Bypass Not Working

**Problem**: Still getting challenge page after bypass

**Solution**:
1. Enable browser mode: `'use_browser' => true`
2. Configure CAPTCHA solver service
3. Check IP reputation (may be blocked)
4. Increase retry count and delay
5. Use different Chrome profile

## 📞 Support

For issues or questions:
1. Check logs in `/private_html/logs/product-intelligence/`
2. Review extraction logs for detailed strategy attempts
3. Enable debug logging: `LOG_LEVEL=debug`
4. Contact development team

## 🚀 Performance Tips

1. **Use Redis caching** for repeated extractions
2. **Reuse Chrome sessions** across multiple pages
3. **Stop on first success** for faster extraction when high confidence
4. **Limit strategies** to only those needed for specific sites
5. **Batch product matching** for multiple competitors

## 📚 Further Reading

- [PHASE_1E_COMPLETE.md](./PHASE_1E_COMPLETE.md) - Full technical documentation
- [config/product-intelligence.php](./config/product-intelligence.php) - Configuration reference
- [Puppeteer Documentation](https://pptr.dev/) - Chrome automation
- [OpenAI Vision API](https://platform.openai.com/docs/guides/vision) - GPT Vision integration

---

**Version**: 3.0.0
**Status**: Phase 1E Complete ✅
**Last Updated**: 2025-01-04
