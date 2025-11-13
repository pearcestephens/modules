<?php declare(strict_types=1);
namespace CIS\Base;

/**
 * PerformanceMetrics mapping helper.
 * Centralizes metric names for consistency across enrichment & logging.
 */
final class PerformanceMetrics
{
    /** @var array<string,string> canonical map */
    public const MAP = [
        'lcp' => 'LargestContentfulPaint',
        'cls' => 'CumulativeLayoutShift',
        'inp' => 'InteractionToNextPaint',
        'fcp' => 'FirstContentfulPaint',
        'ttfb' => 'TimeToFirstByte',
        'dcl' => 'DomContentLoaded'
    ];

    public static function resolve(string $key): ?string
    {
        return self::MAP[$key] ?? null;
    }
}
