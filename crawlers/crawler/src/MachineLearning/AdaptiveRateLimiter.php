<?php

declare(strict_types=1);
/**
 * AdaptiveRateLimiter - ML-Based Dynamic Rate Limiting.
 *
 * Features:
 * - Token Bucket algorithm
 * - Leaky Bucket algorithm
 * - ML-based rate prediction
 * - Domain-specific limits
 * - Burst handling
 *
 * @version 2.0.0
 */

namespace CIS\SharedServices\Crawler\MachineLearning;

use Psr\Log\LoggerInterface;

use function array_slice;
use function count;

class AdaptiveRateLimiter
{
    private LoggerInterface $logger;

    private array $config;

    private array $buckets = [];

    private array $history = [];

    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'algorithm'           => 'token_bucket',
            'requests_per_second' => 2.0,
            'burst_size'          => 10,
            'learning_enabled'    => true,
            'learning_window'     => 100,
        ], $config);
    }

    /**
     * Set rate limit for specific domain.
     *
     * @param string $domain Domain to limit
     * @param float $requestsPerSecond Rate limit
     * @return void
     */
    public function setLimit(string $domain, float $requestsPerSecond): void
    {
        if (!isset($this->buckets[$domain])) {
            $this->initializeBucket($domain);
        }

        $this->buckets[$domain]['rate'] = $requestsPerSecond;
        $this->config['requests_per_second'] = $requestsPerSecond;

        $this->logger->debug("Rate limit updated for {$domain}: {$requestsPerSecond} req/s");
    }

    /**
     * Record response time for adaptive learning.
     */
    public function recordResponse(string $domain, int $statusCode, float $responseTime = 0.0): void
    {
        if (!isset($this->buckets[$domain])) {
            $this->initializeBucket($domain);
        }

        $this->buckets[$domain]['avg_response_time'] = $responseTime;
        $this->buckets[$domain]['last_status'] = $statusCode;

        $this->logger->debug("Response recorded for {$domain}", [
            'status' => $statusCode,
            'response_time' => $responseTime
        ]);
    }

    /**
     * Check if request is allowed.
     */
    public function allowRequest(string $domain): bool
    {
        if (!isset($this->buckets[$domain])) {
            $this->initializeBucket($domain);
        }

        $allowed = false;

        switch ($this->config['algorithm']) {
            case 'token_bucket':
                $allowed = $this->tokenBucketAllow($domain);

                break;
            case 'leaky_bucket':
                $allowed = $this->leakyBucketAllow($domain);

                break;
            case 'sliding_window':
                $allowed = $this->slidingWindowAllow($domain);

                break;
            default:
                $allowed = true;
        }

        $this->recordRequest($domain, $allowed);

        if ($this->config['learning_enabled']) {
            $this->learnFromHistory($domain);
        }

        return $allowed;
    }

    /**
     * Get recommended wait time.
     */
    public function getWaitTime(string $domain): float
    {
        if (!isset($this->buckets[$domain])) {
            return 0;
        }

        $bucket = $this->buckets[$domain];

        switch ($this->config['algorithm']) {
            case 'token_bucket':
                if ($bucket['tokens'] >= 1) {
                    return 0;
                }

                return (1 - $bucket['tokens']) / $bucket['rate'];
            case 'leaky_bucket':
                if (count($bucket['queue']) < $bucket['capacity']) {
                    return 0;
                }

                return 1 / $bucket['rate'];
            default:
                return 1 / $bucket['rate'];
        }
    }

    /**
     * Predict optimal rate for domain based on history.
     */
    public function predictOptimalRate(string $domain): float
    {
        if (!isset($this->history[$domain]) || count($this->history[$domain]) < 10) {
            return $this->config['requests_per_second'];
        }

        $recentHistory = array_slice($this->history[$domain], -$this->config['learning_window']);

        $successRate     = count(array_filter($recentHistory, fn ($h) => $h['allowed'])) / count($recentHistory);
        $avgResponseTime = array_sum(array_column($recentHistory, 'response_time')) / count($recentHistory);

        // Adjust rate based on success rate
        $currentRate = $this->buckets[$domain]['rate'];

        if ($successRate > 0.95 && $avgResponseTime < 1.0) {
            $newRate = $currentRate * 1.1; // Increase by 10%
        } elseif ($successRate < 0.7 || $avgResponseTime > 3.0) {
            $newRate = $currentRate * 0.8; // Decrease by 20%
        } else {
            $newRate = $currentRate;
        }

        $newRate = max(0.5, min(5.0, $newRate)); // Clamp between 0.5 and 5 RPS

        $this->logger->debug('Rate prediction', [
            'domain'            => $domain,
            'current_rate'      => round($currentRate, 2),
            'predicted_rate'    => round($newRate, 2),
            'success_rate'      => round($successRate, 2),
            'avg_response_time' => round($avgResponseTime, 2),
        ]);

        return $newRate;
    }

    /**
     * Update rate limit for domain.
     */
    public function updateRate(string $domain, float $rate): void
    {
        if (!isset($this->buckets[$domain])) {
            $this->initializeBucket($domain);
        }

        $this->buckets[$domain]['rate'] = $rate;

        $this->logger->info('Rate limit updated', [
            'domain'   => $domain,
            'new_rate' => round($rate, 2),
        ]);
    }

    /**
     * Get statistics for domain.
     */
    public function getStats(string $domain): array
    {
        if (!isset($this->history[$domain])) {
            return [];
        }

        $history = $this->history[$domain];
        $total   = count($history);
        $allowed = count(array_filter($history, fn ($h) => $h['allowed']));

        return [
            'total_requests'   => $total,
            'allowed_requests' => $allowed,
            'blocked_requests' => $total - $allowed,
            'success_rate'     => $total > 0 ? round(($allowed / $total) * 100, 2) : 0,
            'current_rate'     => $this->buckets[$domain]['rate'] ?? 0,
        ];
    }

    // ============================================================================
    // PRIVATE METHODS - ALGORITHMS
    // ============================================================================

    private function tokenBucketAllow(string $domain): bool
    {
        $bucket = &$this->buckets[$domain];

        // Refill tokens
        $now         = microtime(true);
        $timePassed  = $now - $bucket['last_refill'];
        $tokensToAdd = $timePassed * $bucket['rate'];

        $bucket['tokens']      = min($bucket['capacity'], $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;

        // Check if we have a token
        if ($bucket['tokens'] >= 1) {
            $bucket['tokens']--;

            return true;
        }

        return false;
    }

    private function leakyBucketAllow(string $domain): bool
    {
        $bucket = &$this->buckets[$domain];

        // Leak requests
        $now            = microtime(true);
        $timePassed     = $now - $bucket['last_leak'];
        $requestsToLeak = floor($timePassed * $bucket['rate']);

        for ($i = 0; $i < $requestsToLeak && !empty($bucket['queue']); $i++) {
            array_shift($bucket['queue']);
        }

        $bucket['last_leak'] = $now;

        // Check if bucket has capacity
        if (count($bucket['queue']) < $bucket['capacity']) {
            $bucket['queue'][] = $now;

            return true;
        }

        return false;
    }

    private function slidingWindowAllow(string $domain): bool
    {
        $bucket      = &$this->buckets[$domain];
        $now         = microtime(true);
        $windowStart = $now - 1.0; // 1 second window

        // Remove old requests
        $bucket['requests'] = array_filter(
            $bucket['requests'],
            fn ($timestamp) => $timestamp > $windowStart,
        );

        // Check if under limit
        $requestsInWindow = count($bucket['requests']);
        $limit            = $bucket['rate'];

        if ($requestsInWindow < $limit) {
            $bucket['requests'][] = $now;

            return true;
        }

        return false;
    }

    private function initializeBucket(string $domain): void
    {
        $rate     = $this->config['requests_per_second'];
        $capacity = $this->config['burst_size'];

        switch ($this->config['algorithm']) {
            case 'token_bucket':
                $this->buckets[$domain] = [
                    'tokens'      => $capacity,
                    'capacity'    => $capacity,
                    'rate'        => $rate,
                    'last_refill' => microtime(true),
                ];

                break;
            case 'leaky_bucket':
                $this->buckets[$domain] = [
                    'queue'     => [],
                    'capacity'  => $capacity,
                    'rate'      => $rate,
                    'last_leak' => microtime(true),
                ];

                break;
            case 'sliding_window':
                $this->buckets[$domain] = [
                    'requests' => [],
                    'rate'     => $rate,
                ];

                break;
        }

        $this->history[$domain] = [];
    }

    private function recordRequest(string $domain, bool $allowed): void
    {
        $this->history[$domain][] = [
            'timestamp'     => microtime(true),
            'allowed'       => $allowed,
            'response_time' => 0, // To be filled by caller
        ];

        // Limit history size
        if (count($this->history[$domain]) > $this->config['learning_window'] * 2) {
            $this->history[$domain] = array_slice($this->history[$domain], -$this->config['learning_window']);
        }
    }

    private function learnFromHistory(string $domain): void
    {
        if (count($this->history[$domain]) < $this->config['learning_window']) {
            return;
        }

        $optimalRate = $this->predictOptimalRate($domain);

        if (abs($optimalRate - $this->buckets[$domain]['rate']) > 0.1) {
            $this->updateRate($domain, $optimalRate);
        }
    }

    /**
     * Initialize token bucket for domain.
     */
    private function initTokenBucket(string $domain, float $rate, int $capacity): array
    {
        return [
            'type'      => 'token_bucket',
            'tokens'    => $capacity,
            'capacity'  => $capacity,
            'rate'      => $rate,
            'last_refill' => microtime(true),
        ];
    }

    /**
     * Initialize leaky bucket for domain.
     */
    private function initLeakyBucket(string $domain, float $rate): array
    {
        return [
            'type'      => 'leaky_bucket',
            'queue'     => [],
            'rate'      => $rate,
            'last_leak' => microtime(true),
        ];
    }

    /**
     * Refill tokens for token bucket.
     */
    private function refillTokens(string $domain): void
    {
        if (!isset($this->buckets[$domain]) || $this->buckets[$domain]['type'] !== 'token_bucket') {
            return;
        }

        $now = microtime(true);
        $elapsed = $now - $this->buckets[$domain]['last_refill'];
        $tokensToAdd = $elapsed * $this->buckets[$domain]['rate'];

        $this->buckets[$domain]['tokens'] = min(
            $this->buckets[$domain]['capacity'],
            $this->buckets[$domain]['tokens'] + $tokensToAdd
        );
        $this->buckets[$domain]['last_refill'] = $now;
    }

    private function getCurrentTokens(string $domain): float
    {
        $this->refillTokens($domain);
        return $this->buckets[$domain]['tokens'] ?? 0.0;
    }

    private function getQueueSize(string $domain): int
    {
        return count($this->buckets[$domain]['queue'] ?? []);
    }

    private function processLeakyBucket(string $domain): void
    {
        if (!isset($this->buckets[$domain]) || $this->buckets[$domain]['type'] !== 'leaky_bucket') {
            return;
        }

        $now = microtime(true);
        $elapsed = $now - $this->buckets[$domain]['last_leak'];
        $requestsToLeak = (int)($elapsed * $this->buckets[$domain]['rate']);

        for ($i = 0; $i < $requestsToLeak && !empty($this->buckets[$domain]['queue']); $i++) {
            array_shift($this->buckets[$domain]['queue']);
        }

        $this->buckets[$domain]['last_leak'] = $now;
    }

    private function getAlgorithm(string $domain): string
    {
        return $this->buckets[$domain]['type'] ?? $this->config['algorithm'];
    }

    private function getEffectiveRate(string $domain): float
    {
        return $this->buckets[$domain]['rate'] ?? $this->config['requests_per_second'];
    }

    private function getAdjustmentFactor(string $domain): float
    {
        if (!isset($this->history[$domain]) || count($this->history[$domain]) < 5) {
            return 1.0;
        }

        $recent = array_slice($this->history[$domain], -5);
        $successRate = count(array_filter($recent, fn($r) => $r['status'] < 400)) / count($recent);

        return $successRate > 0.8 ? 1.1 : ($successRate < 0.5 ? 0.8 : 1.0);
    }

    private function calculateBackoff(string $domain, int $attemptCount): float
    {
        return min(60.0, pow(2, $attemptCount - 1));
    }

    private function cleanupOldRequests(string $domain): void
    {
        if (!isset($this->history[$domain])) {
            return;
        }

        $cutoff = microtime(true) - 3600; // Keep last hour
        $this->history[$domain] = array_filter(
            $this->history[$domain],
            fn($r) => $r['timestamp'] > $cutoff
        );
    }
}
