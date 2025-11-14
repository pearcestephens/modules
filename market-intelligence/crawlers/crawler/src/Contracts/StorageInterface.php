<?php

declare(strict_types=1);
/**
 * Storage Interface.
 *
 * Contract for data persistence with Repository pattern
 * Defines methods for storing, retrieving, and querying crawler data
 *
 * @version 2.0.0 - Ultra-Sophisticated ML/AI Enhanced
 */

namespace CIS\SharedServices\Crawler\Contracts;

interface StorageInterface
{
    /**
     * Store crawl result
     * Enhanced with: Version control, change tracking, data compression.
     *
     * @param array $result Crawl result (url, html, extracted_data, metadata)
     *
     * @return int Result ID
     */
    public function storeCrawlResult(array $result): int;

    /**
     * Get crawl result by ID
     * Enhanced with: Lazy loading, caching layer (Redis).
     *
     * @param int $id Result ID
     *
     * @return array|null Crawl result or null if not found
     */
    public function getCrawlResult(int $id): ?array;

    /**
     * Get latest crawl result for URL
     * Enhanced with: Cache-first strategy.
     *
     * @param string $url Target URL
     *
     * @return array|null Latest result or null if not found
     */
    public function getLatestCrawlResult(string $url): ?array;

    /**
     * Query crawl results with filters
     * Enhanced with: Full-text search, faceted filtering, pagination.
     *
     * @param array $filters Filters (url_pattern, date_range, status, domain)
     * @param array $options Query options (limit, offset, order_by)
     *
     * @return array Query results with pagination metadata
     */
    public function queryCrawlResults(array $filters = [], array $options = []): array;

    /**
     * Store session state (for resumable crawls)
     * NEW: Distributed state management with Redis.
     *
     * @param string $sessionId Session identifier
     * @param array  $state     Session state (urls_visited, queue, checkpoints)
     */
    public function storeSessionState(string $sessionId, array $state): void;

    /**
     * Get session state
     * NEW: Atomic read operations with distributed locks.
     *
     * @param string $sessionId Session identifier
     *
     * @return array|null Session state or null if not found
     */
    public function getSessionState(string $sessionId): ?array;

    /**
     * Delete session state.
     *
     * @param string $sessionId Session identifier
     */
    public function deleteSessionState(string $sessionId): void;

    /**
     * Store performance metrics (time-series data)
     * NEW: InfluxDB integration for high-performance time-series storage.
     *
     * @param array $metrics Metrics (timestamp, operation, duration, success, metadata)
     */
    public function storeMetrics(array $metrics): void;

    /**
     * Query performance metrics
     * NEW: Time-series queries with aggregation (avg, p95, p99).
     *
     * @param array $query Query parameters (time_range, operation, aggregation)
     *
     * @return array Metrics data with aggregations
     */
    public function queryMetrics(array $query): array;

    /**
     * Store fingerprint data
     * NEW: Fingerprint version history tracking.
     *
     * @param int   $profileId   Profile ID
     * @param array $fingerprint Fingerprint data (browser, device, network)
     *
     * @return int Fingerprint ID
     */
    public function storeFingerprint(int $profileId, array $fingerprint): int;

    /**
     * Get active fingerprint for profile
     * Enhanced with: Caching layer.
     *
     * @param int $profileId Profile ID
     *
     * @return array|null Active fingerprint or null if not found
     */
    public function getActiveFingerprint(int $profileId): ?array;

    /**
     * Store detected bot protection system
     * NEW: Bot protection intelligence database.
     *
     * @param string $domain     Target domain
     * @param array  $protection Protection details (system, version, features)
     */
    public function storeBotProtection(string $domain, array $protection): void;

    /**
     * Get known bot protection for domain
     * Enhanced with: Cache with TTL (24 hour expiry).
     *
     * @param string $domain Target domain
     *
     * @return array|null Protection details or null if not found
     */
    public function getBotProtection(string $domain): ?array;

    /**
     * Acquire distributed lock (for concurrent operations)
     * NEW: Redis-based distributed locking with auto-expiry.
     *
     * @param string $resource Resource identifier
     * @param int    $ttl      Lock TTL in seconds
     *
     * @return bool True if lock acquired, false otherwise
     */
    public function acquireLock(string $resource, int $ttl = 30): bool;

    /**
     * Release distributed lock.
     *
     * @param string $resource Resource identifier
     */
    public function releaseLock(string $resource): void;

    /**
     * Cache data with TTL
     * NEW: Multi-layer caching (memory → Redis → database).
     *
     * @param string $key   Cache key
     * @param mixed  $value Data to cache
     * @param int    $ttl   TTL in seconds (0 = forever)
     */
    public function cache(string $key, $value, int $ttl = 3600): void;

    /**
     * Get cached data.
     *
     * @param string $key Cache key
     *
     * @return mixed|null Cached data or null if not found/expired
     */
    public function getCached(string $key);

    /**
     * Clear cache.
     *
     * @param string|null $pattern Key pattern (null = clear all)
     */
    public function clearCache(?string $pattern = null): void;

    /**
     * Batch insert for performance
     * NEW: Bulk operations with transaction support.
     *
     * @param string $table   Table name
     * @param array  $records Array of records to insert
     *
     * @return int Number of records inserted
     */
    public function batchInsert(string $table, array $records): int;

    /**
     * Get storage statistics
     * NEW: Storage usage analytics.
     *
     * @return array Statistics (total_results, size_bytes, cache_hit_rate)
     */
    public function getStatistics(): array;
}
