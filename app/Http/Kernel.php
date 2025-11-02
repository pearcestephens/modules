<?php
/**
 * Application HTTP kernel responsible for routing and security middleware.
 *
 * @package App\\Http
 */

declare(strict_types=1);

namespace App\Http;

use App\Support\Logger;
use App\Support\Response;

final class Kernel
{
    private array $appConfig;
    private array $urlConfig;
    private array $securityConfig;
    private Logger $logger;

    /**
     * @param array<string,mixed> $appConfig
     * @param array<string,mixed> $urlConfig
     * @param array<string,mixed> $securityConfig
     */
    public function __construct(array $appConfig, array $urlConfig, array $securityConfig)
    {
        $this->appConfig = $appConfig;
        $this->urlConfig = $urlConfig;
        $this->securityConfig = $securityConfig;
        $this->logger = new Logger($appConfig['log_channel'] ?? 'router', $appConfig['timezone'] ?? 'Pacific/Auckland');
    }

    /**
     * Handle the incoming request and route to the proper endpoint.
     */
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method Not Allowed', 405);
        }

        $clientIp = $this->getClientIp();
        $this->enforceIpRestrictions($clientIp);
        $this->enforceRateLimit($clientIp);

        $endpoint = $this->resolveEndpoint();
        $entry = $this->urlConfig['whitelist'][$endpoint] ?? null;

        if ($entry === null) {
            $this->logger->warning('Attempted access to unknown endpoint', ['endpoint' => $endpoint, 'ip' => $clientIp]);
            Response::error('Not Found', 404);
        }

        $scriptPath = is_array($entry) ? ($entry['script'] ?? null) : (string)$entry;

        if (!is_string($scriptPath) || !is_file($scriptPath)) {
            $this->logger->error('Endpoint script missing', ['endpoint' => $endpoint, 'path' => $scriptPath]);
            Response::error('Server configuration error', 500);
        }

        if (is_array($entry)) {
            if (!empty($entry['auth'])) {
                $this->enforceAuthentication();
            }

            $flags = $entry['flags'] ?? [];
            if (in_array('phpinfo', $flags, true)) {
                $this->guardPhpInfo();
            }
            if (in_array('quick_dial', $flags, true)) {
                $this->guardQuickDial($clientIp);
            }
        }

        $this->logger->debug('Dispatching endpoint', ['endpoint' => $endpoint, 'ip' => $clientIp]);

        require $scriptPath;
    }

    private function resolveEndpoint(): string
    {
        $requested = isset($_GET['endpoint']) ? (string)$_GET['endpoint'] : '';

        if ($requested === '') {
            return $this->urlConfig['default_endpoint'] ?? 'admin/health/ping';
        }

        if (!preg_match('#^[a-z0-9\-_/]+$#i', $requested)) {
            $this->logger->warning('Endpoint failed validation', ['endpoint' => $requested]);
            Response::error('Invalid endpoint supplied', 400);
        }

        return trim($requested, '/');
    }

    private function getClientIp(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!empty($forwarded)) {
            $parts = explode(',', $forwarded);
            $candidate = trim($parts[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    private function enforceIpRestrictions(string $clientIp): void
    {
        $allowed = $this->securityConfig['allowed_ips'] ?? [];

        if (!empty($allowed) && !in_array($clientIp, $allowed, true)) {
            $this->logger->warning('Blocked IP address', ['ip' => $clientIp]);
            Response::error('Forbidden', 403);
        }
    }

    private function enforceRateLimit(string $clientIp): void
    {
        $limitConfig = $this->securityConfig['rate_limit'] ?? ['requests_per_minute' => 60, 'burst' => 20];
        $requestsPerMinute = (int)($limitConfig['requests_per_minute'] ?? 60);
        $burst = (int)($limitConfig['burst'] ?? 20);

        if ($requestsPerMinute <= 0) {
            return;
        }

        $bucketKey = hash('sha256', $clientIp . '|' . date('Y-m-d H:i'));
        $bucketFile = sys_get_temp_dir() . '/cis_router_' . $bucketKey;

        $count = 0;

        $fp = fopen($bucketFile, 'c+');
        if ($fp !== false) {
            flock($fp, LOCK_EX);
            $data = stream_get_contents($fp);
            if (is_string($data) && $data !== '') {
                $decoded = json_decode($data, true);
                if (is_array($decoded) && isset($decoded['count'])) {
                    $count = (int)$decoded['count'];
                }
            }

            $count++;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(['count' => $count]));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        if ($count > $requestsPerMinute + $burst) {
            $this->logger->warning('Rate limit exceeded', ['ip' => $clientIp, 'count' => $count]);
            Response::error('Too Many Requests', 429, ['retry_after' => 60]);
        }
    }

    private function enforceAuthentication(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionKey = $this->securityConfig['admin_session_key'] ?? 'admin_session';
        $isAuthenticated = !empty($_SESSION[$sessionKey]);

        if (!$isAuthenticated) {
            $this->logger->warning('Unauthorised endpoint access blocked', ['session_key' => $sessionKey]);
            Response::error('Unauthorized', 401);
        }
    }

    private function guardPhpInfo(): void
    {
        $allowed = (bool)($this->securityConfig['phpinfo_enabled'] ?? false);

        if (!$allowed) {
            $this->logger->warning('phpinfo endpoint blocked by configuration');
            Response::error('Forbidden', 403);
        }

        if (!defined('PHPINFO_ALLOWED')) {
            define('PHPINFO_ALLOWED', true);
        }
    }

    private function guardQuickDial(string $clientIp): void
    {
        $config = $this->securityConfig['quick_dial'] ?? [];
        $requestsPerMinute = (int)($config['requests_per_minute'] ?? 0);

        if ($requestsPerMinute <= 0) {
            return;
        }

        $bucketKey = hash('sha256', 'quick_dial|' . $clientIp . '|' . date('Y-m-d H:i'));
        $bucketFile = sys_get_temp_dir() . '/cis_quick_dial_' . $bucketKey;

        $count = 0;
        $fp = fopen($bucketFile, 'c+');
        if ($fp !== false) {
            flock($fp, LOCK_EX);
            $data = stream_get_contents($fp);
            if (is_string($data) && $data !== '') {
                $decoded = json_decode($data, true);
                if (is_array($decoded) && isset($decoded['count'])) {
                    $count = (int)$decoded['count'];
                }
            }

            $count++;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(['count' => $count], JSON_THROW_ON_ERROR));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        if ($count > $requestsPerMinute) {
            $this->logger->warning('Quick dial rate limit exceeded', ['ip' => $clientIp, 'count' => $count]);
            Response::error('Too Many Requests', 429, ['retry_after' => 60]);
        }
    }
}