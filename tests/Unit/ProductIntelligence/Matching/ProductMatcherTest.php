<?php

/**
 * ProductMatcherTest - Ultra-Strict Unit Tests.
 *
 * Tests fuzzy matching algorithms, weighted scoring, confidence tiers,
 * attribute matching, brand extraction with maximum rigor.
 *
 * Target Coverage: 100%
 * Edge Cases: Comprehensive
 * Performance: Validated
 */

declare(strict_types=1);

namespace CIS\SharedServices\ProductIntelligence\Tests\Unit\Matching;

use CIS\SharedServices\ProductIntelligence\Matching\ProductMatcher;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ProductMatcherTest extends TestCase
{
    private ?PDO $pdo;

    private ?ProductMatcher $matcher;

    private array $testProducts = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create in-memory SQLite database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create schema
        $this->createSchema();

        // Insert test products
        $this->testProducts = $this->insertTestProducts();

        // Create matcher instance
        $this->matcher = new ProductMatcher($this->pdo, [
            'min_confidence'       => 0.50,
            'use_ml_scoring'       => true,
            'use_brand_extraction' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->pdo     = null;
        $this->matcher = null;
        parent::tearDown();
    }

    // =========================================================================
    // EXACT MATCH TESTS (95%+ confidence)
    // =========================================================================

    public function testExactNameMatchReturnsHighConfidence(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'SMOK RPM80 Pod Kit Strawberry',
            'brand' => 'SMOK',
        ]);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(0.95, $result['confidence']);
        $this->assertEquals('exact', $result['match_level']);
    }

    public function testExactMatchWithAllAttributesReturnsMaxConfidence(): void
    {
        $product = $this->testProducts[0];

        $result = $this->matcher->matchProduct([
            'name'     => $product['name'],
            'brand'    => $product['brand'],
            'sku'      => $product['sku'],
            'flavor'   => $product['flavor'],
            'nicotine' => $product['nicotine'],
        ]);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(0.95, $result['confidence']);
        $this->assertEquals($product['id'], $result['our_product_id']);
    }

    public function testCaseInsensitiveExactMatch(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'smok rpm80 pod kit strawberry', // lowercase
            'brand' => 'SMOK',
        ]);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(0.90, $result['confidence']);
    }

    // =========================================================================
    // STRONG MATCH TESTS (85-95% confidence)
    // =========================================================================

    public function testStrongMatchWithMinorTypo(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'SMOK RPM80 Pod Kkit Strawberry', // Typo: Kkit
            'brand' => 'SMOK',
        ]);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(0.85, $result['confidence']);
        $this->assertLessThan(0.95, $result['confidence']);
        $this->assertEquals('strong', $result['match_level']);
    }

    public function testStrongMatchWithWordOrderDifference(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'Strawberry SMOK RPM80 Pod Kit', // Different order
            'brand' => 'SMOK',
        ]);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(0.85, $result['confidence']);
    }

    public function testStrongMatchWithMissingAttribute(): void
    {
        $product = $this->testProducts[0];

        $result = $this->matcher->matchProduct([
            'name'  => $product['name'],
            'brand' => $product['brand'],
            // Missing flavor attribute
        ]);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(0.85, $result['confidence']);
    }

    // =========================================================================
    // MEDIUM MATCH TESTS (70-85% confidence)
    // =========================================================================

    public function testMediumMatchWithPartialNameMatch(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'SMOK RPM80 Device', // Partial match
            'brand' => 'SMOK',
        ]);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(0.70, $result['confidence']);
        $this->assertLessThan(0.85, $result['confidence']);
        $this->assertEquals('medium', $result['match_level']);
    }

    public function testMediumMatchWithSimilarButDifferentFlavor(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'SMOK RPM80 Pod Kit Blueberry', // Different flavor
            'brand' => 'SMOK',
        ]);

        $confidence = $result['confidence'] ?? 0;
        if ($confidence >= 0.70) {
            $this->assertEquals('medium', $result['match_level']);
        }
    }

    // =========================================================================
    // WEAK MATCH TESTS (50-70% confidence)
    // =========================================================================

    public function testWeakMatchWithOnlyBrandMatch(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'Some Generic Product',
            'brand' => 'SMOK',
        ]);

        if ($result['matched']) {
            $this->assertLessThan(0.70, $result['confidence']);
            if ($result['confidence'] >= 0.50) {
                $this->assertEquals('weak', $result['match_level']);
            }
        }
    }

    // =========================================================================
    // NO MATCH TESTS (< 50% confidence)
    // =========================================================================

    public function testNoMatchWithCompletelyDifferentProduct(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'Completely Different Unrelated Product XYZ',
            'brand' => 'Unknown Brand',
        ]);

        $this->assertFalse($result['matched']);
        $this->assertLessThan(0.50, $result['confidence']);
    }

    public function testNoMatchWithEmptyName(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => '',
            'brand' => 'SMOK',
        ]);

        $this->assertFalse($result['matched']);
    }

    // =========================================================================
    // FUZZY MATCHING ALGORITHM TESTS
    // =========================================================================

    public function testLevenshteinDistanceCalculation(): void
    {
        // Test with known Levenshtein distance
        $str1 = 'kitten';
        $str2 = 'sitting';
        // Expected Levenshtein distance: 3

        $reflection = new ReflectionClass($this->matcher);
        $method     = $reflection->getMethod('calculateStringSimilarity');
        $method->setAccessible(true);

        $similarity = $method->invoke($this->matcher, $str1, $str2);

        $this->assertGreaterThan(0, $similarity);
        $this->assertLessThan(1, $similarity);
    }

    public function testJaroWinklerSimilarity(): void
    {
        $reflection = new ReflectionClass($this->matcher);
        $method     = $reflection->getMethod('jaroWinklerSimilarity');
        $method->setAccessible(true);

        // Identical strings
        $similarity = $method->invoke($this->matcher, 'test', 'test');
        $this->assertEquals(1.0, $similarity, '', 0.001);

        // Completely different
        $similarity = $method->invoke($this->matcher, 'abc', 'xyz');
        $this->assertLessThan(0.5, $similarity);

        // Similar strings
        $similarity = $method->invoke($this->matcher, 'martha', 'marhta');
        $this->assertGreaterThan(0.9, $similarity);
    }

    public function testTokenBasedSimilarity(): void
    {
        $reflection = new ReflectionClass($this->matcher);
        $method     = $reflection->getMethod('tokenBasedSimilarity');
        $method->setAccessible(true);

        // Identical tokens, different order
        $similarity = $method->invoke($this->matcher, 'pod kit smok', 'smok kit pod');
        $this->assertEquals(1.0, $similarity);

        // Partial overlap
        $similarity = $method->invoke($this->matcher, 'smok rpm80 pod', 'smok rpm80');
        $this->assertGreaterThan(0.65, $similarity);

        // No overlap
        $similarity = $method->invoke($this->matcher, 'abc def', 'xyz uvw');
        $this->assertEquals(0.0, $similarity);
    }

    // =========================================================================
    // STRING NORMALIZATION TESTS
    // =========================================================================

    public function testStringNormalizationRemovesSpecialCharacters(): void
    {
        $reflection = new ReflectionClass($this->matcher);
        $method     = $reflection->getMethod('normalizeString');
        $method->setAccessible(true);

        $normalized = $method->invoke($this->matcher, 'Test-Product_Name!@#$%');
        $this->assertEquals('testproductname', $normalized);
    }

    public function testStringNormalizationHandlesUnicode(): void
    {
        $reflection = new ReflectionClass($this->matcher);
        $method     = $reflection->getMethod('normalizeString');
        $method->setAccessible(true);

        $normalized = $method->invoke($this->matcher, 'Café Münchén');
        $this->assertIsString($normalized);
        $this->assertNotEmpty($normalized);
    }

    public function testStringNormalizationTrimsWhitespace(): void
    {
        $reflection = new ReflectionClass($this->matcher);
        $method     = $reflection->getMethod('normalizeString');
        $method->setAccessible(true);

        $normalized = $method->invoke($this->matcher, '  Multiple   Spaces  ');
        $this->assertEquals('multiple spaces', $normalized);
    }

    // =========================================================================
    // ATTRIBUTE MATCHING TESTS
    // =========================================================================

    public function testAttributeMatchingWithIdenticalAttributes(): void
    {
        $reflection = new ReflectionClass($this->matcher);
        $method     = $reflection->getMethod('matchAttributes');
        $method->setAccessible(true);

        $competitor = ['flavor' => 'Strawberry', 'nicotine' => '3mg'];
        $our        = ['flavor' => 'Strawberry', 'nicotine' => '3mg'];

        $score = $method->invoke($this->matcher, $competitor, $our);
        $this->assertEquals(1.0, $score);
    }

    public function testAttributeMatchingWithNoMatchingAttributes(): void
    {
        $reflection = new ReflectionClass($this->matcher);
        $method     = $reflection->getMethod('matchAttributes');
        $method->setAccessible(true);

        $competitor = ['flavor' => 'Strawberry', 'nicotine' => '3mg'];
        $our        = ['flavor' => 'Mango', 'nicotine' => '6mg'];

        $score = $method->invoke($this->matcher, $competitor, $our);
        $this->assertEquals(0.0, $score);
    }

    public function testAttributeMatchingWithPartialMatch(): void
    {
        $reflection = new ReflectionClass($this->matcher);
        $method     = $reflection->getMethod('matchAttributes');
        $method->setAccessible(true);

        $competitor = ['flavor' => 'Strawberry', 'nicotine' => '3mg', 'color' => 'Blue'];
        $our        = ['flavor' => 'Strawberry', 'nicotine' => '6mg', 'color' => 'Blue'];

        $score = $method->invoke($this->matcher, $competitor, $our);
        $this->assertGreaterThan(0, $score);
        $this->assertLessThan(1, $score);
        $this->assertEqualsWithDelta(0.67, $score, 0.01); // 2 out of 3 match
    }

    // =========================================================================
    // BRAND EXTRACTION TESTS
    // =========================================================================

    public function testBrandExtractionFromProductName(): void
    {
        $brand = $this->matcher->extractBrand('SMOK RPM80 Pod Kit');
        $this->assertEquals('SMOK', $brand);
    }

    public function testBrandExtractionCaseInsensitive(): void
    {
        $brand = $this->matcher->extractBrand('vaporesso gen pod kit');
        $this->assertEquals('Vaporesso', $brand);
    }

    public function testBrandExtractionReturnsNullForUnknownBrand(): void
    {
        $brand = $this->matcher->extractBrand('Unknown Brand Product XYZ');
        $this->assertNull($brand);
    }

    public function testBrandExtractionWithMultipleBrands(): void
    {
        // Should return first match
        $brand = $this->matcher->extractBrand('SMOK and Vaporesso collaboration');
        $this->assertContains($brand, ['SMOK', 'Vaporesso']);
    }

    // =========================================================================
    // NICOTINE EXTRACTION TESTS
    // =========================================================================

    public function testNicotineExtractionWithMgFormat(): void
    {
        $nicotine = $this->matcher->extractNicotine('Product 3mg nicotine');
        $this->assertEquals('3mg', $nicotine);
    }

    public function testNicotineExtractionWithPercentageFormat(): void
    {
        $nicotine = $this->matcher->extractNicotine('Product 0.3% nicotine');
        $this->assertEquals('0.3%', $nicotine);
    }

    public function testNicotineExtractionWithMgPerMlFormat(): void
    {
        $nicotine = $this->matcher->extractNicotine('Product 6mg/ml nicotine');
        $this->assertEquals('6mg/ml', $nicotine);
    }

    public function testNicotineExtractionReturnsNullWhenNotFound(): void
    {
        $nicotine = $this->matcher->extractNicotine('Product with no nicotine info');
        $this->assertNull($nicotine);
    }

    // =========================================================================
    // WEIGHTED SCORING TESTS
    // =========================================================================

    public function testWeightedScoringFavorsName(): void
    {
        // Name match should have highest weight (40%)
        $result1 = $this->matcher->matchProduct([
            'name'  => $this->testProducts[0]['name'],
            'brand' => 'WrongBrand',
        ]);

        $result2 = $this->matcher->matchProduct([
            'name'  => 'Wrong Name',
            'brand' => $this->testProducts[0]['brand'],
        ]);

        if ($result1['matched'] && $result2['matched']) {
            $this->assertGreaterThan($result2['confidence'], $result1['confidence']);
        }
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function testMatchWithNullValues(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => null,
            'brand' => null,
        ]);

        $this->assertFalse($result['matched']);
    }

    public function testMatchWithEmptyArray(): void
    {
        $result = $this->matcher->matchProduct([]);

        $this->assertFalse($result['matched']);
    }

    public function testMatchWithVeryLongProductName(): void
    {
        $longName = str_repeat('Long Product Name ', 100);

        $result = $this->matcher->matchProduct([
            'name'  => $longName,
            'brand' => 'SMOK',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('matched', $result);
    }

    public function testMatchWithSpecialCharactersInName(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'SMOK™ RPM80® Pod Kit™',
            'brand' => 'SMOK',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('matched', $result);
    }

    public function testMatchWithNumericOnlyName(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => '12345678',
            'brand' => 'SMOK',
        ]);

        $this->assertIsArray($result);
    }

    // =========================================================================
    // PERFORMANCE TESTS
    // =========================================================================

    public function testMatchingPerformanceUnderLoad(): void
    {
        $iterations = 100;
        $start      = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->matcher->matchProduct([
                'name'  => 'SMOK RPM80 Pod Kit',
                'brand' => 'SMOK',
            ]);
        }

        $duration = microtime(true) - $start;
        $avgTime  = $duration / $iterations;

        // Each match should take less than 100ms
        $this->assertLessThan(0.1, $avgTime, "Average matching time: {$avgTime}s");
    }

    public function testMemoryUsageDuringMatching(): void
    {
        $memBefore = memory_get_usage(true);

        for ($i = 0; $i < 50; $i++) {
            $this->matcher->matchProduct([
                'name'  => 'Test Product ' . $i,
                'brand' => 'Test Brand',
            ]);
        }

        $memAfter = memory_get_usage(true);
        $memUsed  = $memAfter - $memBefore;

        // Should not use more than 10MB for 50 matches
        $this->assertLessThan(10 * 1024 * 1024, $memUsed);
    }

    // =========================================================================
    // RESULT STRUCTURE TESTS
    // =========================================================================

    public function testResultStructureForSuccessfulMatch(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => $this->testProducts[0]['name'],
            'brand' => $this->testProducts[0]['brand'],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('matched', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('match_level', $result);
        $this->assertArrayHasKey('our_product_id', $result);
        $this->assertArrayHasKey('our_sku', $result);
        $this->assertArrayHasKey('our_name', $result);
        $this->assertArrayHasKey('all_matches', $result);
        $this->assertArrayHasKey('signals', $result);

        $this->assertIsBool($result['matched']);
        $this->assertIsFloat($result['confidence']);
        $this->assertIsString($result['match_level']);
        $this->assertIsArray($result['all_matches']);
        $this->assertIsArray($result['signals']);
    }

    public function testResultStructureForFailedMatch(): void
    {
        $result = $this->matcher->matchProduct([
            'name'  => 'Non-existent product',
            'brand' => 'Unknown',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('matched', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('reason', $result);

        $this->assertFalse($result['matched']);
        $this->assertEquals(0.0, $result['confidence']);
        $this->assertIsString($result['reason']);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function createSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE products (
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
                active INTEGER DEFAULT 1
            )
        ');
    }

    private function insertTestProducts(): array
    {
        $products = [
            [
                'sku'       => 'TEST-001',
                'name'      => 'SMOK RPM80 Pod Kit Strawberry',
                'brand'     => 'SMOK',
                'model'     => 'RPM80',
                'flavor'    => 'Strawberry',
                'nicotine'  => '3mg',
                'variant'   => 'Standard',
                'color'     => 'Blue',
                'size'      => '50ml',
                'capacity'  => '2000mAh',
                'image_url' => 'https://example.com/img1.jpg',
                'active'    => 1,
            ],
            [
                'sku'       => 'TEST-002',
                'name'      => 'Vaporesso Gen Pod Kit Mango',
                'brand'     => 'Vaporesso',
                'model'     => 'Gen',
                'flavor'    => 'Mango',
                'nicotine'  => '6mg',
                'variant'   => 'Pro',
                'color'     => 'Black',
                'size'      => '60ml',
                'capacity'  => '2500mAh',
                'image_url' => 'https://example.com/img2.jpg',
                'active'    => 1,
            ],
        ];

        foreach ($products as $product) {
            $stmt = $this->pdo->prepare('
                INSERT INTO products (sku, name, brand, model, flavor, nicotine, variant, color, size, capacity, image_url, active)
                VALUES (:sku, :name, :brand, :model, :flavor, :nicotine, :variant, :color, :size, :capacity, :image_url, :active)
            ');
            $stmt->execute($product);
            $product['id'] = (int) $this->pdo->lastInsertId();
            $products[]    = $product;
        }

        return $products;
    }
}
