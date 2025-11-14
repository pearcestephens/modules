<?php

declare(strict_types=1);
/**
 * Crawler Interface.
 *
 * Contract for web crawling engines with anti-detection
 * Defines methods for URL fetching, content extraction, and stealth operations
 *
 * @version 2.0.0 - Ultra-Sophisticated ML/AI Enhanced
 */

namespace CIS\SharedServices\Crawler\Contracts;

interface CrawlerInterface
{
    /**
     * Crawl a URL with full anti-detection measures
     * Enhanced with: Circuit Breaker pattern, adaptive rate limiting.
     *
     * @param string $url     Target URL
     * @param array  $options Crawl options (method, headers, stealth_level, etc.)
     *
     * @return array Response (html, status, headers, metrics)
     */
    public function crawl(string $url, array $options = []): array;

    /**
     * Crawl multiple URLs in batch with intelligent scheduling
     * NEW: Priority queue, load balancing, resource optimization.
     *
     * @param array $urls    Array of URLs to crawl
     * @param array $options Batch options (concurrency, priority, retry_strategy)
     *
     * @return array Batch results with success/failure tracking
     */
    public function crawlBatch(array $urls, array $options = []): array;

    /**
     * Extract structured data from HTML
     * Enhanced with: ML-based content extraction, semantic parsing.
     *
     * @param string $html      HTML content
     * @param array  $selectors CSS/XPath selectors for target data
     *
     * @return array Extracted data (structured)
     */
    public function extract(string $html, array $selectors = []): array;

    /**
     * Set stealth level for anti-detection
     * Enhanced with: Dynamic stealth adaptation based on target behavior.
     *
     * @param string $level Stealth level (low, medium, high, extreme)
     */
    public function setStealthLevel(string $level): void;

    /**
     * Get current crawl metrics
     * Enhanced with: Real-time analytics, anomaly detection.
     *
     * @return array Metrics (requests, success_rate, avg_response_time, detection_events)
     */
    public function getMetrics(): array;

    /**
     * Check if target URL has bot detection enabled
     * NEW: ML-based detection system identification (Cloudflare, reCAPTCHA, etc.).
     *
     * @param string $url Target URL
     *
     * @return array Detection analysis (system, confidence, bypass_strategy)
     */
    public function detectBotProtection(string $url): array;

    /**
     * Bypass bot detection system
     * NEW: Cloudflare bypass, reCAPTCHA v3 solving, JavaScript challenge solving.
     *
     * @param string $url           Target URL
     * @param array  $detectionInfo Detection analysis from detectBotProtection()
     *
     * @return array Bypass result (success, cookies, tokens)
     */
    public function bypassProtection(string $url, array $detectionInfo): array;

    /**
     * Render JavaScript-heavy page (SPA support)
     * NEW: Headless browser simulation without actual Chrome binary.
     *
     * @param string $url     Target URL
     * @param array  $options Render options (wait_for, timeout, screenshots)
     *
     * @return array Rendered content (html, dom_state, screenshots)
     */
    public function renderJavaScript(string $url, array $options = []): array;

    /**
     * Set rate limit strategy
     * NEW: ML-based adaptive rate limiting with Token Bucket + Leaky Bucket.
     *
     * @param array $strategy Strategy config (requests_per_second, burst_size, algorithm)
     */
    public function setRateLimitStrategy(array $strategy): void;

    /**
     * Get recommended wait time before next request
     * NEW: ML prediction based on target behavior patterns.
     *
     * @param string $targetDomain Target domain for rate limit calculation
     *
     * @return float Seconds to wait
     */
    public function getRecommendedWaitTime(string $targetDomain): float;

    /**
     * Handle crawl failure with retry strategy
     * Enhanced with: Exponential backoff, Circuit Breaker, fallback strategies.
     *
     * @param string $url   Failed URL
     * @param array  $error Error details
     *
     * @return array Retry decision (should_retry, wait_time, strategy)
     */
    public function handleFailure(string $url, array $error): array;

    /**
     * Clear crawler cache and reset state.
     */
    public function reset(): void;
}
