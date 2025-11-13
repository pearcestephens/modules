<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use CIS\Base\PerformanceMetrics;

final class PerformanceEnrichmentTest extends TestCase
{
    public function testMetricMappingContainsCoreKeys(): void
    {
        $required = ['lcp','cls','inp','fcp','ttfb','dcl'];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, PerformanceMetrics::MAP, "Missing metric: {$key}");
            $this->assertNotEmpty(PerformanceMetrics::MAP[$key], "Empty name for metric: {$key}");
        }
    }

    public function testResolveReturnsCanonicalName(): void
    {
        $this->assertSame('LargestContentfulPaint', PerformanceMetrics::resolve('lcp'));
        $this->assertSame('TimeToFirstByte', PerformanceMetrics::resolve('ttfb'));
        $this->assertNull(PerformanceMetrics::resolve('unknown_metric'));
    }
}
