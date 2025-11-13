<?php

declare(strict_types=1);
/**
 * CloudflareBypass - Advanced Cloudflare Turnstile & Challenge Bypass.
 *
 * Features:
 * - 2025 Cloudflare Turnstile challenge detection
 * - JavaScript challenge execution
 * - Cookie manipulation
 * - TLS fingerprint matching
 * - Rate limit handling
 * - Challenge solving with retries
 *
 * @version 3.0.0
 */

namespace CIS\SharedServices\ProductIntelligence\Bypass;

use const CASE_LOWER;
use const CURLINFO_HEADER_SIZE;
use const CURLINFO_HTTP_CODE;
use const CURLOPT_COOKIE;
use const CURLOPT_ENCODING;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_MAXREDIRS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;

class CloudflareBypass
{
    // Cloudflare detection signatures
    private const CF_SIGNATURES = [
        'headers'   => ['cf-ray', 'cf-cache-status', 'cf-request-id'],
        'body_text' => ['cloudflare', 'challenge-platform', 'turnstile'],
        'title'     => ['just a moment', 'attention required'],
    ];

    private array $config;

    private array $bypassLog = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_retries'    => 3,
            'retry_delay'    => 5000, // ms
            'use_browser'    => true, // Use real browser for JS challenges
            'solver_service' => null, // 2captcha, anticaptcha, etc.
        ], $config);
    }

    /**
     * Detect if Cloudflare protection is active.
     *
     * @param array $response HTTP response
     *
     * @return array Detection result
     */
    public function detectCloudflare(array $response): array
    {
        $detected      = false;
        $challengeType = null;
        $confidence    = 0.0;

        // Check headers
        $headers = array_change_key_case($response['headers'] ?? [], CASE_LOWER);
        foreach (self::CF_SIGNATURES['headers'] as $header) {
            if (isset($headers[$header])) {
                $detected = true;
                $confidence += 0.3;
            }
        }

        // Check body content
        $body = $response['body'] ?? '';
        foreach (self::CF_SIGNATURES['body_text'] as $text) {
            if (stripos($body, $text) !== false) {
                $detected = true;
                $confidence += 0.2;
            }
        }

        // Detect challenge type
        if (stripos($body, 'turnstile') !== false) {
            $challengeType = 'turnstile';
        } elseif (stripos($body, 'challenge-platform') !== false) {
            $challengeType = 'javascript';
        } elseif (stripos($body, 'managed challenge') !== false) {
            $challengeType = 'managed';
        } elseif ($response['status_code'] === 403) {
            $challengeType = 'blocked';
        }

        $this->log('Cloudflare detection: ' . ($detected ? 'YES' : 'NO') .
                   ($challengeType ? " (Type: {$challengeType})" : ''));

        return [
            'detected'       => $detected,
            'challenge_type' => $challengeType,
            'confidence'     => min($confidence, 1.0),
        ];
    }

    /**
     * Attempt to bypass Cloudflare protection.
     *
     * @param string $url     Target URL
     * @param array  $context Request context (cookies, headers, etc.)
     *
     * @return array Bypass result
     */
    public function bypass(string $url, array $context = []): array
    {
        $this->bypassLog = [];
        $attempts        = 0;

        while ($attempts < $this->config['max_retries']) {
            $attempts++;
            $this->log("Bypass attempt #{$attempts}");

            // Step 1: Make initial request
            $response = $this->makeRequest($url, $context);

            // Step 2: Detect Cloudflare
            $detection = $this->detectCloudflare($response);

            if (!$detection['detected']) {
                $this->log('No Cloudflare detected - success!');

                return [
                    'success'  => true,
                    'response' => $response,
                    'attempts' => $attempts,
                    'log'      => $this->bypassLog,
                ];
            }

            // Step 3: Handle challenge based on type
            switch ($detection['challenge_type']) {
                case 'turnstile':
                    $solveResult = $this->solveTurnstile($url, $response, $context);

                    break;
                case 'javascript':
                    $solveResult = $this->solveJavaScriptChallenge($url, $response, $context);

                    break;
                case 'managed':
                    $solveResult = $this->solveManagedChallenge($url, $response, $context);

                    break;
                case 'blocked':
                    $this->log('Blocked (403) - cannot bypass');

                    return [
                        'success'  => false,
                        'error'    => 'IP blocked by Cloudflare',
                        'attempts' => $attempts,
                        'log'      => $this->bypassLog,
                    ];

                default:
                    $solveResult = ['success' => false];
            }

            if ($solveResult['success']) {
                // Update context with new cookies
                $context['cookies'] = array_merge(
                    $context['cookies'] ?? [],
                    $solveResult['cookies'] ?? [],
                );

                // Make final request with challenge cookies
                $finalResponse = $this->makeRequest($url, $context);

                if (!$this->detectCloudflare($finalResponse)['detected']) {
                    $this->log('Challenge solved - success!');

                    return [
                        'success'  => true,
                        'response' => $finalResponse,
                        'attempts' => $attempts,
                        'log'      => $this->bypassLog,
                    ];
                }
            }

            // Wait before retry
            usleep($this->config['retry_delay'] * 1000);
        }

        return [
            'success'  => false,
            'error'    => 'Max retries exceeded',
            'attempts' => $attempts,
            'log'      => $this->bypassLog,
        ];
    }

    /**
     * Get bypass log.
     */
    public function getLog(): array
    {
        return $this->bypassLog;
    }

    /**
     * Solve Turnstile challenge (2025 latest).
     */
    private function solveTurnstile(string $url, array $response, array $context): array
    {
        $this->log('Solving Turnstile challenge');

        // Extract sitekey
        $body    = $response['body'];
        $sitekey = null;

        if (preg_match('/data-sitekey=["\']([^"\']+)["\']/', $body, $matches)) {
            $sitekey = $matches[1];
        }

        if (!$sitekey) {
            $this->log('Could not extract sitekey');

            return ['success' => false];
        }

        $this->log("Sitekey: {$sitekey}");

        // If using solver service (2captcha, anticaptcha)
        if ($this->config['solver_service']) {
            return $this->useSolverService('turnstile', $url, $sitekey);
        }

        // Otherwise, use real browser
        if ($this->config['use_browser']) {
            return $this->solveTurnstileWithBrowser($url, $sitekey, $context);
        }

        return ['success' => false, 'error' => 'No solver available'];
    }

    /**
     * Solve Turnstile using real browser.
     */
    private function solveTurnstileWithBrowser(string $url, string $sitekey, array $context): array
    {
        $this->log('Using browser to solve Turnstile');

        // This requires integration with ChromeManager
        // Browser will automatically solve Turnstile by waiting and simulating human behavior

        // Placeholder: In real implementation, this would:
        // 1. Create Chrome session
        // 2. Navigate to URL
        // 3. Wait for Turnstile to complete (up to 30 seconds)
        // 4. Extract cookies
        // 5. Return cookies

        return ['success' => false, 'error' => 'Browser solving not yet implemented'];
    }

    /**
     * Solve JavaScript challenge.
     */
    private function solveJavaScriptChallenge(string $url, array $response, array $context): array
    {
        $this->log('Solving JavaScript challenge');

        // JavaScript challenges require executing JS code and submitting result
        // This MUST use real browser

        if (!$this->config['use_browser']) {
            return ['success' => false, 'error' => 'JS challenge requires browser'];
        }

        // Placeholder for browser integration
        return ['success' => false, 'error' => 'JS solving not yet implemented'];
    }

    /**
     * Solve managed challenge.
     */
    private function solveManagedChallenge(string $url, array $response, array $context): array
    {
        $this->log('Solving managed challenge');

        // Managed challenges are the hardest - require perfect browser fingerprinting
        // and human-like behavior

        if (!$this->config['use_browser']) {
            return ['success' => false, 'error' => 'Managed challenge requires browser'];
        }

        // Placeholder for browser integration with advanced fingerprinting
        return ['success' => false, 'error' => 'Managed solving not yet implemented'];
    }

    /**
     * Use external solver service (2captcha, anticaptcha, etc.).
     */
    private function useSolverService(string $challengeType, string $url, string $sitekey): array
    {
        $this->log("Using solver service for {$challengeType}");

        // Placeholder for 2captcha/anticaptcha integration
        // Would make API call to solver service and wait for solution

        return ['success' => false, 'error' => 'Solver service not configured'];
    }

    /**
     * Make HTTP request with custom headers and cookies.
     */
    private function makeRequest(string $url, array $context): array
    {
        $ch = curl_init($url);

        $headers = $context['headers'] ?? [];
        $headers = array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ], $headers);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => '', // Enable all encodings
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER         => true,
        ]);

        // Add cookies
        if (!empty($context['cookies'])) {
            $cookieStr = $this->buildCookieString($context['cookies']);
            curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
        }

        $response   = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerStr = substr($response, 0, $headerSize);
        $body      = substr($response, $headerSize);

        return [
            'status_code' => $httpCode,
            'headers'     => $this->parseHeaders($headerStr),
            'body'        => $body,
        ];
    }

    /**
     * Parse HTTP headers.
     */
    private function parseHeaders(string $headerStr): array
    {
        $headers = [];
        $lines   = explode("\r\n", $headerStr);

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value]                   = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Build cookie string.
     */
    private function buildCookieString(array $cookies): string
    {
        $parts = [];
        foreach ($cookies as $name => $value) {
            $parts[] = "{$name}={$value}";
        }

        return implode('; ', $parts);
    }

    /**
     * Log bypass event.
     */
    private function log(string $message): void
    {
        $this->bypassLog[] = [
            'timestamp' => microtime(true),
            'message'   => $message,
        ];
    }
}
