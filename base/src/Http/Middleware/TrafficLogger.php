<?php

/**
 * Traffic Logger Middleware.
 *
 * Logs all HTTP requests to database for traffic monitoring
 */

declare(strict_types=1);

namespace CIS\Base\Http\Middleware;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;
use Throwable;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

class TrafficLogger
{
    private Application $app;

    private float $startTime;

    public function __construct(Application $app)
    {
        $this->app       = $app;
        $this->startTime = microtime(true);
    }

    /**
     * Handle incoming request.
     */
    public function handle(Request $request): ?Response
    {
        // Check if traffic logging is enabled
        if (!$this->app->config('traffic.enabled', true)) {
            return null;
        }

        // Check sample rate (performance optimization)
        $sampleRate = $this->app->config('traffic.sample_rate', 1.0);
        if ($sampleRate < 1.0 && (mt_rand() / mt_getrandmax()) > $sampleRate) {
            return null; // Skip logging based on sample rate
        }

        // Check if path should be ignored
        $ignorePaths = $this->app->config('traffic.ignore_paths', []);
        $path        = $request->path();

        foreach ($ignorePaths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return null; // Skip logging for this path
            }
        }

        // Register shutdown function to log after response is sent
        register_shutdown_function(function () use ($request) {
            $this->logRequest($request);
        });

        return null; // Continue to next middleware
    }

    /**
     * Log request to database.
     */
    private function logRequest(Request $request): void
    {
        try {
            $responseTime = (int) ((microtime(true) - $this->startTime) * 1000);
            $statusCode   = http_response_code();
            $memoryMb     = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

            // Detect bot
            $botInfo = $this->detectBot($request->userAgent());

            // Get geo location (cached)
            $geo = $this->getGeoLocation($request->ip());

            // Insert traffic record
            $this->app->database()->execute(
                'INSERT INTO web_traffic_requests
                (request_id, timestamp, method, endpoint, query_string, status_code,
                 response_time_ms, memory_mb, ip_address, user_agent, referer, user_id,
                 is_bot, bot_type, country_code, country_name, city)
                VALUES (?, NOW(3), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $request->id(),
                    $request->method(),
                    $request->query('endpoint', ''),
                    $request->query('endpoint') ? '' : http_build_query($request->queryAll()),
                    $statusCode,
                    $responseTime,
                    $memoryMb,
                    $request->ip(),
                    substr($request->userAgent(), 0, 500),
                    $request->referer(),
                    $_SESSION['user_id'] ?? null,
                    $botInfo['is_bot'] ? 1 : 0,
                    $botInfo['type'],
                    $geo['country_code'] ?? null,
                    $geo['country_name'] ?? null,
                    $geo['city'] ?? null,
                ],
            );

            // Check for alert conditions
            $this->checkAlerts($statusCode, $responseTime);
        } catch (Throwable $e) {
            // Silently fail - don't break the application if logging fails
            error_log('TrafficLogger error: ' . $e->getMessage());
        }
    }

    /**
     * Detect if user agent is a bot.
     */
    private function detectBot(string $userAgent): array
    {
        $bots = [
            'googlebot'           => 'Googlebot',
            'bingbot'             => 'Bingbot',
            'slurp'               => 'Yahoo',
            'duckduckbot'         => 'DuckDuckGo',
            'baiduspider'         => 'Baidu',
            'yandexbot'           => 'Yandex',
            'facebookexternalhit' => 'Facebook',
            'twitterbot'          => 'Twitter',
            'linkedinbot'         => 'LinkedIn',
            'whatsapp'            => 'WhatsApp',
            'curl'                => 'cURL',
            'wget'                => 'Wget',
            'python-requests'     => 'Python',
            'java'                => 'Java',
            'bot'                 => 'Generic Bot',
            'crawler'             => 'Crawler',
            'spider'              => 'Spider',
        ];

        $userAgentLower = strtolower($userAgent);

        foreach ($bots as $pattern => $type) {
            if (str_contains($userAgentLower, $pattern)) {
                return ['is_bot' => true, 'type' => $type];
            }
        }

        return ['is_bot' => false, 'type' => null];
    }

    /**
     * Get geo location from IP (cached).
     */
    private function getGeoLocation(string $ip): ?array
    {
        // Skip private IPs
        if ($this->isPrivateIp($ip)) {
            return null;
        }

        // Check cache
        $cacheKey = 'geo:' . $ip;
        $cached   = $this->app->cache()->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        // Call geo API
        try {
            $provider = $this->app->config('apis.geo.provider', 'ip-api');
            $url      = $this->app->config('apis.geo.url', 'http://ip-api.com/json');

            $ch = curl_init("{$url}/{$ip}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);

                if ($data && $data['status'] === 'success') {
                    $result = [
                        'country_code' => $data['countryCode'] ?? null,
                        'country_name' => $data['country'] ?? null,
                        'city'         => $data['city'] ?? null,
                    ];

                    // Cache for 24 hours
                    $ttl = $this->app->config('apis.geo.cache_ttl', 86400);
                    $this->app->cache()->set($cacheKey, $result, $ttl);

                    return $result;
                }
            }
        } catch (Throwable $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Check if IP is private.
     */
    private function isPrivateIp(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /**
     * Check for alert conditions.
     */
    private function checkAlerts(int $statusCode, int $responseTime): void
    {
        // Error spike detection
        if ($statusCode >= 500) {
            $threshold    = $this->app->config('traffic.alerts.error_spike_threshold', 10);
            $recentErrors = $this->app->database()->query(
                'SELECT COUNT(*) as count FROM web_traffic_requests
                 WHERE status_code >= 500 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)',
            )->fetch();

            if ($recentErrors['count'] >= $threshold) {
                $this->triggerAlert('error_spike', [
                    'count'     => $recentErrors['count'],
                    'threshold' => $threshold,
                ]);
            }
        }

        // Slow endpoint detection
        $slowThreshold = (int) ($this->app->config('traffic.alerts.slow_endpoint_threshold', 5.0) * 1000);
        if ($responseTime > $slowThreshold) {
            $this->triggerAlert('slow_endpoint', [
                'response_time_ms' => $responseTime,
                'threshold_ms'     => $slowThreshold,
            ]);
        }
    }

    /**
     * Trigger alert (log for now, can extend to email/Slack later).
     */
    private function triggerAlert(string $type, array $data): void
    {
        $this->app->logger()->warning("Traffic alert: {$type}", $data);

        // TODO: Send email/Slack notification
        // TODO: Store in alerts table
    }
}
