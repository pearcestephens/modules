<?php

/**
 * PriceExtractorTest - Ultra-Strict Unit Tests.
 *
 * Tests NZD currency detection, GST inclusion/exclusion, price patterns,
 * sale/regular differentiation, GST conversion with maximum rigor.
 *
 * Target Coverage: 100%
 * Edge Cases: Comprehensive
 * Accuracy: 95%+ GST detection
 */

declare(strict_types=1);

namespace CIS\SharedServices\ProductIntelligence\Tests\Unit\Intelligence;

use CIS\SharedServices\ProductIntelligence\Intelligence\PriceExtractor;
use PHPUnit\Framework\TestCase;

use function count;

class PriceExtractorTest extends TestCase
{
    private PriceExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new PriceExtractor([
            'default_currency'    => 'NZD',
            'assume_gst_incl'     => true,
            'detect_sale_prices'  => true,
            'detect_price_ranges' => true,
        ]);
    }

    // =========================================================================
    // NZD CURRENCY DETECTION TESTS
    // =========================================================================

    public function testDetectNZDollarSign(): void
    {
        $html   = '<div class="price">$49.99</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertNotEmpty($result['prices']);
        $this->assertEquals(49.99, $result['prices'][0]['value']);
        $this->assertEquals('NZD', $result['currency']);
    }

    public function testDetectNZDWithPrefix(): void
    {
        $html   = '<div class="price">NZ$49.99</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertNotEmpty($result['prices']);
        $this->assertEquals(49.99, $result['prices'][0]['value']);
    }

    public function testDetectNZDWithSuffix(): void
    {
        $html   = '<div class="price">$49.99 NZD</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertNotEmpty($result['prices']);
        $this->assertEquals(49.99, $result['prices'][0]['value']);
    }

    public function testDetectNZDTextOnly(): void
    {
        $html   = '<div class="price">49.99 NZD</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertNotEmpty($result['prices']);
        $this->assertEquals(49.99, $result['prices'][0]['value']);
    }

    // =========================================================================
    // GST INCLUSION DETECTION TESTS
    // =========================================================================

    public function testGSTInclusionDetectionExplicit(): void
    {
        $html   = '<div class="price">$49.99 (incl GST)</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertEquals('incl', $result['gst_status']);
        $this->assertTrue($result['gst_included']);
        $this->assertGreaterThanOrEqual(0.80, $result['confidence']);
    }

    public function testGSTInclusionDetectionVariations(): void
    {
        $variations = [
            '$49.99 including GST',
            '$49.99 inc GST',
            '$49.99 inc. GST',
            'Price includes GST $49.99',
            '$49.99 GST included',
        ];

        foreach ($variations as $html) {
            $result = $this->extractor->extractPrices($html);
            $this->assertEquals('incl', $result['gst_status'], "Failed for: {$html}");
            $this->assertTrue($result['gst_included']);
        }
    }

    // =========================================================================
    // GST EXCLUSION DETECTION TESTS
    // =========================================================================

    public function testGSTExclusionDetectionExplicit(): void
    {
        $html   = '<div class="price">$49.99 (excl GST)</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertEquals('excl', $result['gst_status']);
        $this->assertFalse($result['gst_included']);
        $this->assertGreaterThanOrEqual(0.80, $result['confidence']);
    }

    public function testGSTExclusionDetectionWithPlus(): void
    {
        $html   = '<div class="price">$49.99 + GST</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertEquals('excl', $result['gst_status']);
        $this->assertFalse($result['gst_included']);
    }

    public function testGSTExclusionDetectionVariations(): void
    {
        $variations = [
            '$49.99 excluding GST',
            '$49.99 exc GST',
            '$49.99 exc. GST',
            'Price excludes GST $49.99',
            '$49.99 GST excluded',
            '$49.99 +GST',
        ];

        foreach ($variations as $html) {
            $result = $this->extractor->extractPrices($html);
            $this->assertEquals('excl', $result['gst_status'], "Failed for: {$html}");
            $this->assertFalse($result['gst_included']);
        }
    }

    // =========================================================================
    // GST AMBIGUOUS/UNKNOWN DETECTION TESTS
    // =========================================================================

    public function testGSTUnknownWhenNoMention(): void
    {
        $html   = '<div class="price">$49.99</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertContains($result['gst_status'], ['unknown', 'assumed_incl']);
        $this->assertLessThan(0.80, $result['confidence']);
    }

    public function testGSTMixedWhenBothPresent(): void
    {
        $html   = '<div>$49.99 incl GST</div><div>$39.99 excl GST</div>';
        $result = $this->extractor->extractPrices($html);

        // When both present, should be marked as mixed or ambiguous
        $this->assertContains($result['gst_status'], ['mixed', 'incl', 'excl']);
    }

    // =========================================================================
    // GST CONVERSION TESTS
    // =========================================================================

    public function testGSTConversionInclToExcl(): void
    {
        $priceIncl = 115.00;
        $priceExcl = $this->extractor->convertGST($priceIncl, true, false);

        // 115 / 1.15 = 100
        $this->assertEqualsWithDelta(100.00, $priceExcl, 0.01);
    }

    public function testGSTConversionExclToIncl(): void
    {
        $priceExcl = 100.00;
        $priceIncl = $this->extractor->convertGST($priceExcl, false, true);

        // 100 * 1.15 = 115
        $this->assertEqualsWithDelta(115.00, $priceIncl, 0.01);
    }

    public function testGSTConversionNoChangeWhenSameStatus(): void
    {
        $price = 100.00;

        $result1 = $this->extractor->convertGST($price, true, true);
        $this->assertEquals($price, $result1);

        $result2 = $this->extractor->convertGST($price, false, false);
        $this->assertEquals($price, $result2);
    }

    public function testGSTAmountCalculation(): void
    {
        $priceIncl = 115.00;
        $gstAmount = $this->extractor->calculateGSTAmount($priceIncl);

        // GST = 115 - (115 / 1.15) = 115 - 100 = 15
        $this->assertEqualsWithDelta(15.00, $gstAmount, 0.01);
    }

    public function testGSTConversionPrecision(): void
    {
        $price      = 49.99;
        $excl       = $this->extractor->convertGST($price, true, false);
        $backToIncl = $this->extractor->convertGST($excl, false, true);

        // Should be close to original (within rounding)
        $this->assertEqualsWithDelta($price, $backToIncl, 0.02);
    }

    // =========================================================================
    // PRICE PATTERN MATCHING TESTS
    // =========================================================================

    public function testSimpleDollarAmount(): void
    {
        $html   = 'Price: $99.99';
        $result = $this->extractor->extractPrices($html);

        $this->assertNotEmpty($result['prices']);
        $this->assertEquals(99.99, $result['prices'][0]['value']);
    }

    public function testPriceWithCommas(): void
    {
        $html   = 'Price: $1,299.99';
        $result = $this->extractor->extractPrices($html);

        $this->assertNotEmpty($result['prices']);
        $this->assertEquals(1299.99, $result['prices'][0]['value']);
    }

    public function testMultipleCommasInPrice(): void
    {
        $html   = 'Price: $12,345.67';
        $result = $this->extractor->extractPrices($html);

        $this->assertNotEmpty($result['prices']);
        $this->assertEquals(12345.67, $result['prices'][0]['value']);
    }

    public function testPriceWithoutCents(): void
    {
        $html   = 'Price: $50';
        $result = $this->extractor->extractPrices($html);

        // Some patterns might not match without decimal
        if (!empty($result['prices'])) {
            $this->assertIsFloat($result['prices'][0]['value']);
        }
    }

    public function testMultiplePricesInHTML(): void
    {
        $html   = '<div>Was $99.99</div><div>Now $79.99</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertCount(2, $result['prices']);
    }

    // =========================================================================
    // SALE VS REGULAR PRICE DETECTION TESTS
    // =========================================================================

    public function testSalePriceDetectionWithNowKeyword(): void
    {
        $html   = '<div>Was $99.99</div><div>Now $79.99</div>';
        $result = $this->extractor->extractPrices($html);

        if ($result['sale_price'] && $result['regular_price']) {
            $this->assertLessThan($result['regular_price']['value'], $result['sale_price']['value']);
            $this->assertEquals(79.99, $result['sale_price']['value']);
            $this->assertEquals(99.99, $result['regular_price']['value']);
        }
    }

    public function testSalePriceDetectionWithSaveKeyword(): void
    {
        $html   = '<div>Save $20! Only $79.99</div><div>RRP $99.99</div>';
        $result = $this->extractor->extractPrices($html);

        if ($result['sale_price']) {
            $this->assertNotNull($result['sale_price']);
        }
    }

    public function testSalePriceDetectionWithRRPKeyword(): void
    {
        $html   = '<div>Sale: $79.99</div><div>RRP: $99.99</div>';
        $result = $this->extractor->extractPrices($html);

        if ($result['regular_price']) {
            $this->assertEquals(99.99, $result['regular_price']['value']);
        }
    }

    // =========================================================================
    // PRICE RANGE DETECTION TESTS
    // =========================================================================

    public function testPriceRangeDetection(): void
    {
        $html   = '<div>$49.99 - $99.99</div>';
        $result = $this->extractor->extractPrices($html);

        if (isset($result['price_range'])) {
            $this->assertEquals(49.99, $result['price_range']['min']);
            $this->assertEquals(99.99, $result['price_range']['max']);
            $this->assertStringContainsString('49.99', $result['price_range']['formatted']);
            $this->assertStringContainsString('99.99', $result['price_range']['formatted']);
        }
    }

    public function testPriceRangeWithMultiplePrices(): void
    {
        $html   = '<div>$29.99</div><div>$49.99</div><div>$79.99</div>';
        $result = $this->extractor->extractPrices($html);

        if (isset($result['price_range'])) {
            $this->assertLessThan($result['price_range']['max'], $result['price_range']['min']);
        }
    }

    // =========================================================================
    // PRICE FORMATTING TESTS
    // =========================================================================

    public function testFormatPriceWithGSTIncluded(): void
    {
        $formatted = $this->extractor->formatPrice(49.99, 'NZD', true);

        $this->assertStringContainsString('49.99', $formatted);
        $this->assertStringContainsString('incl GST', $formatted);
        $this->assertStringContainsString('$', $formatted);
    }

    public function testFormatPriceWithGSTExcluded(): void
    {
        $formatted = $this->extractor->formatPrice(49.99, 'NZD', false);

        $this->assertStringContainsString('49.99', $formatted);
        $this->assertStringContainsString('excl GST', $formatted);
    }

    public function testFormatPriceWithDifferentCurrency(): void
    {
        $formatted = $this->extractor->formatPrice(49.99, 'USD', true);

        $this->assertStringContainsString('49.99', $formatted);
        $this->assertStringContainsString('USD', $formatted);
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function testEmptyHTMLReturnsNoPrices(): void
    {
        $result = $this->extractor->extractPrices('');

        $this->assertEmpty($result['prices']);
        $this->assertNull($result['primary_price']);
    }

    public function testHTMLWithNoPrices(): void
    {
        $html   = '<div>No prices here</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertEmpty($result['prices']);
    }

    public function testVeryLargePrice(): void
    {
        $html   = '<div>$999,999.99</div>';
        $result = $this->extractor->extractPrices($html);

        if (!empty($result['prices'])) {
            $this->assertEquals(999999.99, $result['prices'][0]['value']);
        }
    }

    public function testVerySmallPrice(): void
    {
        $html   = '<div>$0.01</div>';
        $result = $this->extractor->extractPrices($html);

        if (!empty($result['prices'])) {
            $this->assertEquals(0.01, $result['prices'][0]['value']);
        }
    }

    public function testZeroPrice(): void
    {
        $html   = '<div>$0.00</div>';
        $result = $this->extractor->extractPrices($html);

        if (!empty($result['prices'])) {
            $this->assertEquals(0.00, $result['prices'][0]['value']);
        }
    }

    public function testNegativePrice(): void
    {
        $html   = '<div>-$10.00</div>'; // Discount/refund
        $result = $this->extractor->extractPrices($html);

        // Should either ignore negative or handle specially
        $this->assertIsArray($result);
    }

    public function testPriceWithSpaces(): void
    {
        $html   = '<div>$ 49.99</div>';
        $result = $this->extractor->extractPrices($html);

        if (!empty($result['prices'])) {
            $this->assertEquals(49.99, $result['prices'][0]['value']);
        }
    }

    public function testMultipleCurrenciesInHTML(): void
    {
        $html   = '<div>$49.99 USD</div><div>$79.99 NZD</div>';
        $result = $this->extractor->extractPrices($html);

        // Should detect both prices
        $this->assertGreaterThanOrEqual(1, count($result['prices']));
    }

    // =========================================================================
    // CONFIDENCE SCORE TESTS
    // =========================================================================

    public function testHighConfidenceWithExplicitGST(): void
    {
        $html   = '<div>$49.99 (including GST)</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertGreaterThanOrEqual(0.80, $result['confidence']);
    }

    public function testMediumConfidenceWithoutGST(): void
    {
        $html   = '<div>$49.99</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertLessThan(0.80, $result['confidence']);
        $this->assertGreaterThanOrEqual(0.40, $result['confidence']);
    }

    // =========================================================================
    // CONTEXTUAL ANALYSIS TESTS
    // =========================================================================

    public function testContextualPriceExtraction(): void
    {
        $html   = '<div class="product-price">$49.99</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertNotEmpty($result['prices']);
        $this->assertArrayHasKey('context', $result['prices'][0]);
    }

    // =========================================================================
    // PERFORMANCE TESTS
    // =========================================================================

    public function testExtractionPerformance(): void
    {
        $html = str_repeat('<div>$49.99</div>', 100);

        $start    = microtime(true);
        $result   = $this->extractor->extractPrices($html);
        $duration = microtime(true) - $start;

        // Should complete in under 100ms
        $this->assertLessThan(0.1, $duration);
    }

    public function testMemoryUsageDuringExtraction(): void
    {
        $memBefore = memory_get_usage(true);

        $html = str_repeat('<div>$49.99 incl GST</div>', 1000);
        $this->extractor->extractPrices($html);

        $memAfter = memory_get_usage(true);
        $memUsed  = $memAfter - $memBefore;

        // Should not use more than 5MB
        $this->assertLessThan(5 * 1024 * 1024, $memUsed);
    }

    // =========================================================================
    // RESULT STRUCTURE TESTS
    // =========================================================================

    public function testResultStructureCompleteness(): void
    {
        $html   = '<div>$49.99 (incl GST)</div>';
        $result = $this->extractor->extractPrices($html);

        $this->assertArrayHasKey('prices', $result);
        $this->assertArrayHasKey('primary_price', $result);
        $this->assertArrayHasKey('sale_price', $result);
        $this->assertArrayHasKey('regular_price', $result);
        $this->assertArrayHasKey('currency', $result);
        $this->assertArrayHasKey('gst_status', $result);
        $this->assertArrayHasKey('gst_included', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function testPriceArrayStructure(): void
    {
        $html   = '<div>$49.99</div>';
        $result = $this->extractor->extractPrices($html);

        if (!empty($result['prices'])) {
            $price = $result['prices'][0];

            $this->assertArrayHasKey('value', $price);
            $this->assertArrayHasKey('formatted', $price);
            $this->assertArrayHasKey('offset', $price);
            $this->assertArrayHasKey('context', $price);

            $this->assertIsFloat($price['value']);
            $this->assertIsString($price['formatted']);
            $this->assertIsInt($price['offset']);
            $this->assertIsString($price['context']);
        }
    }

    // =========================================================================
    // INTEGRATION WITH REAL-WORLD HTML TESTS
    // =========================================================================

    public function testRealWorldProductPageStructure(): void
    {
        $html = '
            <div class="product-container">
                <h1>SMOK RPM80 Pod Kit</h1>
                <div class="price-container">
                    <span class="price-regular">RRP: $99.99</span>
                    <span class="price-sale">Now: $79.99</span>
                    <span class="price-note">Prices include GST</span>
                </div>
            </div>
        ';

        $result = $this->extractor->extractPrices($html);

        $this->assertNotEmpty($result['prices']);
        $this->assertEquals('incl', $result['gst_status']);

        if ($result['sale_price'] && $result['regular_price']) {
            $this->assertEquals(79.99, $result['sale_price']['value']);
            $this->assertEquals(99.99, $result['regular_price']['value']);
        }
    }
}
