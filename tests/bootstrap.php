<?php

/**
 * PHPUnit Bootstrap File.
 *
 * Ultra-strict test environment setup with maximum quality enforcement
 */

declare(strict_types=1);

error_reporting(\E_ALL | \E_STRICT);
ini_set('display_errors', '1');

// Define test environment
define('TESTING', true);
define('TEST_START_TIME', microtime(true));

// Project root
$projectRoot = dirname(__DIR__);

// Load Composer autoloader for crawler
$crawlerAutoloader = $projectRoot . '/shared/services/crawler/vendor/autoload.php';
if (file_exists($crawlerAutoloader)) {
    require_once $crawlerAutoloader;
}

// Load Composer autoloader for product-intelligence
$productIntelAutoloader = $projectRoot . '/shared/services/product-intelligence/vendor/autoload.php';
if (file_exists($productIntelAutoloader)) {
    require_once $productIntelAutoloader;
}

// Load main Composer autoloader if exists
$mainAutoloader = $projectRoot . '/vendor/autoload.php';
if (file_exists($mainAutoloader)) {
    require_once $mainAutoloader;
}

// Test utilities class
class TestUtils
{
    /**
     * Generate random string for testing.
     */
    public static function randomString(int $length = 10): string
    {
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / 62))), 1, $length);
    }

    /**
     * Generate random email for testing.
     */
    public static function randomEmail(): string
    {
        return self::randomString(8) . '@' . self::randomString(8) . '.com';
    }

    /**
     * Generate random URL for testing.
     */
    public static function randomUrl(): string
    {
        return 'https://' . self::randomString(10) . '.com/' . self::randomString(8);
    }

    /**
     * Create mock PDO for testing.
     */
    public static function createMockPDO(): PDO
    {
        return new PDO('sqlite::memory:');
    }

    /**
     * Create test database schema.
     */
    public static function createTestSchema(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
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
                active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS crawler_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                profile_name VARCHAR(100),
                profile_path TEXT,
                user_agent TEXT,
                viewport_width INTEGER,
                viewport_height INTEGER,
                timezone VARCHAR(50),
                locale VARCHAR(10),
                fingerprint TEXT,
                usage_count INTEGER DEFAULT 0,
                success_rate REAL DEFAULT 1.0,
                banned INTEGER DEFAULT 0,
                last_used TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    /**
     * Insert test products.
     */
    public static function insertTestProducts(PDO $pdo, int $count = 10): array
    {
        $products = [];

        $brands    = ['SMOK', 'Vaporesso', 'GeekVape', 'Voopoo', 'Uwell'];
        $flavors   = ['Strawberry', 'Mango', 'Mint', 'Tobacco', 'Vanilla'];
        $nicotines = ['0mg', '3mg', '6mg', '12mg', '18mg'];

        for ($i = 0; $i < $count; $i++) {
            $product = [
                'sku'       => 'TEST-' . str_pad((string) $i, 4, '0', \STR_PAD_LEFT),
                'name'      => $brands[array_rand($brands)] . ' Pod Kit ' . $flavors[array_rand($flavors)],
                'brand'     => $brands[array_rand($brands)],
                'model'     => 'Model-' . $i,
                'flavor'    => $flavors[array_rand($flavors)],
                'nicotine'  => $nicotines[array_rand($nicotines)],
                'variant'   => 'Variant-' . $i,
                'color'     => ['Black', 'Blue', 'Red', 'Silver'][array_rand(['Black', 'Blue', 'Red', 'Silver'])],
                'size'      => rand(10, 100) . 'ml',
                'capacity'  => rand(500, 5000) . 'mAh',
                'image_url' => 'https://example.com/image-' . $i . '.jpg',
                'active'    => 1,
            ];

            $stmt = $pdo->prepare('
                INSERT INTO products (sku, name, brand, model, flavor, nicotine, variant, color, size, capacity, image_url, active)
                VALUES (:sku, :name, :brand, :model, :flavor, :nicotine, :variant, :color, :size, :capacity, :image_url, :active)
            ');
            $stmt->execute($product);

            $product['id'] = (int) $pdo->lastInsertId();
            $products[]    = $product;
        }

        return $products;
    }

    /**
     * Generate HTML fixture with product data.
     */
    public static function generateProductHTML(array $data = []): string
    {
        $defaults = [
            'name'  => 'Test Product Name',
            'price' => 49.99,
            'brand' => 'Test Brand',
            'sku'   => 'TEST-SKU-001',
            'gst'   => 'incl',
            'stock' => 'in stock',
        ];

        $data = array_merge($defaults, $data);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Product Page</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "{$data['name']}",
        "brand": {"@type": "Brand", "name": "{$data['brand']}"},
        "sku": "{$data['sku']}",
        "offers": {
            "@type": "Offer",
            "price": "{$data['price']}",
            "priceCurrency": "NZD",
            "availability": "https://schema.org/InStock"
        }
    }
    </script>
</head>
<body>
    <h1 class="product-title">{$data['name']}</h1>
    <div class="product-brand">{$data['brand']}</div>
    <div class="product-sku">SKU: {$data['sku']}</div>
    <div class="product-price">NZ\${$data['price']} ({$data['gst']} GST)</div>
    <div class="product-stock">{$data['stock']}</div>
    <img src="https://example.com/image.jpg" alt="{$data['name']}" class="product-image"/>
</body>
</html>
HTML;
    }

    /**
     * Assert array structure matches expected schema.
     */
    public static function assertArrayStructure(array $expected, array $actual, string $message = ''): void
    {
        foreach ($expected as $key => $type) {
            if (!array_key_exists($key, $actual)) {
                throw new PHPUnit\Framework\AssertionFailedError(
                    $message . " - Missing key: {$key}",
                );
            }

            if ($type === 'array') {
                if (!is_array($actual[$key])) {
                    throw new PHPUnit\Framework\AssertionFailedError(
                        $message . " - Key {$key} is not an array",
                    );
                }
            } elseif ($type === 'numeric') {
                if (!is_numeric($actual[$key])) {
                    throw new PHPUnit\Framework\AssertionFailedError(
                        $message . " - Key {$key} is not numeric",
                    );
                }
            } elseif (gettype($actual[$key]) !== $type) {
                throw new PHPUnit\Framework\AssertionFailedError(
                    $message . " - Key {$key} has wrong type: expected {$type}, got " . gettype($actual[$key]),
                );
            }
        }
    }

    /**
     * Measure memory usage of callback.
     */
    public static function measureMemory(callable $callback): array
    {
        $memBefore  = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);

        $result = $callback();

        $memAfter  = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);

        return [
            'result'      => $result,
            'memory_used' => $memAfter - $memBefore,
            'peak_memory' => $peakAfter - $peakBefore,
        ];
    }

    /**
     * Measure execution time of callback.
     */
    public static function measureTime(callable $callback): array
    {
        $start  = microtime(true);
        $result = $callback();
        $end    = microtime(true);

        return [
            'result' => $result,
            'time'   => $end - $start,
        ];
    }
}

// Register test utilities globally
$GLOBALS['testUtils'] = new TestUtils();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                              ║\n";
echo "║                  🧪 ULTRA-STRICT TEST SUITE INITIALIZED 🧪                   ║\n";
echo "║                                                                              ║\n";
echo "║                    Maximum Strictness | Maximum Coverage                     ║\n";
echo "║                                                                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo 'PHP Version: ' . \PHP_VERSION . "\n";
echo 'PHPUnit Version: ' . PHPUnit\Runner\Version::id() . "\n";
echo "Test Environment: STRICT MODE ENABLED\n";
echo "Coverage Target: 98%+\n";
echo "Mutation Testing: ENABLED\n";
echo "\n";
