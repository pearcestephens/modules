<?php
/**
 * Performance Monitor
 * 
 * Tracks performance metrics and provides optimization insights
 */

namespace CIS\Base;

class PerformanceMonitor {
    private static $startTime;
    private static $startMemory;
    private static $checkpoints = [];
    private static $queries = [];
    
    /**
     * Start monitoring
     */
    public static function start(): void {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage();
    }
    
    /**
     * Add checkpoint
     */
    public static function checkpoint(string $name): void {
        self::$checkpoints[] = [
            'name' => $name,
            'time' => microtime(true),
            'memory' => memory_get_usage()
        ];
    }
    
    /**
     * Log query
     */
    public static function logQuery(string $query, float $duration): void {
        self::$queries[] = [
            'query' => $query,
            'duration' => $duration,
            'time' => microtime(true)
        ];
    }
    
    /**
     * Get performance report
     */
    public static function getReport(): array {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $report = [
            'total_time' => $endTime - self::$startTime,
            'peak_memory' => memory_get_peak_usage(true),
            'memory_used' => $endMemory - self::$startMemory,
            'query_count' => count(self::$queries),
            'checkpoints' => [],
            'slow_queries' => []
        ];
        
        // Process checkpoints
        $lastTime = self::$startTime;
        foreach (self::$checkpoints as $checkpoint) {
            $report['checkpoints'][] = [
                'name' => $checkpoint['name'],
                'duration' => $checkpoint['time'] - $lastTime,
                'memory' => $checkpoint['memory']
            ];
            $lastTime = $checkpoint['time'];
        }
        
        // Find slow queries (>100ms)
        foreach (self::$queries as $query) {
            if ($query['duration'] > 0.1) {
                $report['slow_queries'][] = $query;
            }
        }
        
        return $report;
    }
    
    /**
     * Output performance footer (for debug mode)
     */
    public static function outputFooter(): void {
        $report = self::getReport();
        
        echo "\n<!-- Performance Report\n";
        echo sprintf("Total Time: %.3fs\n", $report['total_time']);
        echo sprintf("Peak Memory: %s\n", self::formatBytes($report['peak_memory']));
        echo sprintf("Queries: %d\n", $report['query_count']);
        
        if (!empty($report['slow_queries'])) {
            echo "\nSlow Queries:\n";
            foreach ($report['slow_queries'] as $query) {
                echo sprintf("  - %.3fs: %s\n", $query['duration'], substr($query['query'], 0, 100));
            }
        }
        
        echo "-->\n";
    }
    
    /**
     * Format bytes to human readable
     */
    private static function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
