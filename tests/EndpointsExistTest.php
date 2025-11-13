<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class EndpointsExistTest extends TestCase
{
    private const ENDPOINTS = [
        'base/public/behavior_stats.php',
        'base/public/performance_fingerprints.php',
        'base/public/performance_summary.php',
        'base/public/behavior.php',
        'base/public/behavior_inspect.php'
    ];

    public function testEndpointsExist(): void
    {
        foreach (self::ENDPOINTS as $path) {
            $full = __DIR__ . '/../' . $path;
            $this->assertFileExists($full, "Missing endpoint: {$path}");
        }
    }
}
