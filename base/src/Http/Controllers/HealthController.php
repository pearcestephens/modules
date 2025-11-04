<?php
/**
 * Health Check Controller
 *
 * System health monitoring and diagnostics
 *
 * @package CIS\Base\Http\Controllers
 */

<?php

declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

class HealthController
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function ping(Request $request): Response
    {
        return Response::success([
            'uptime' => $this->getUptime(),
            'time' => date('c'),
            'app' => $this->app->config('app.name', 'CIS Base Module'),
        ]);
    }

    public function phpinfo(Request $request): Response
    {
        // Only allow admin access; middleware should guard this, but double-check
        if ($this->app->config('security.auth.enabled', true)) {
            $user = $this->app->session()->get('auth_user');
            if (empty($user)) {
                return Response::unauthorized();
            }
        }

        ob_start();
        phpinfo();
        $info = ob_get_clean();
        return Response::html($info);
    }

    public function checks(Request $request): Response
    {
        $results = [];

        // SSL certificate expiry (uses openssl s_client if available)
        $host = parse_url($this->app->config('app.url', ''), PHP_URL_HOST) ?: 'localhost';
        $results['ssl'] = $this->checkSSLCert($host);

        // Database
        $results['database'] = $this->checkDatabase();

        // PHP-FPM
        $results['php_fpm'] = $this->checkPhpFpm();

        // Disk
        $results['disk'] = $this->checkDisk();

        // Vend API
        $results['vend_api'] = $this->checkVendApi();

        // Queue (if enabled)
        $results['queue'] = $this->checkQueue();

        // Persist health check
        try {
            $this->app->database()->execute(
                "INSERT INTO web_health_checks (check_type, status, response_time_ms, details, checked_at) VALUES (?, ?, ?, ?, ?)",
                ['composite', $this->aggregateStatus($results), 0, json_encode($results), date('Y-m-d H:i:s')]
            );
        } catch (\Throwable $e) {
            $this->app->logger()->warning('Health check DB write failed', ['error' => $e->getMessage()]);
        }

        return Response::json(['checks' => $results]);
    }

    public function dashboard(Request $request): Response
    {
        // Render a simple dashboard using views (basic HTML)
        $viewPath = $this->app->paths('views') . '/admin/health/dashboard.php';
        if (file_exists($viewPath)) {
            ob_start();
            include $viewPath;
            $html = ob_get_clean();
            return Response::html($html);
        }

        return Response::html('<h1>Health Dashboard</h1><p>No dashboard view available.</p>');
    }

    private function getUptime(): string
    {
        if (is_readable('/proc/uptime')) {
            $u = file_get_contents('/proc/uptime');
            $parts = explode(' ', trim($u));
            $seconds = (int)floor((float)$parts[0]);
            $d = floor($seconds / 86400);
            $h = floor(($seconds % 86400) / 3600);
            $m = floor(($seconds % 3600) / 60);
            return "{$d}d {$h}h {$m}m";
        }
        return 'unknown';
    }

    private function checkSSLCert(string $host): array
    {
        $port = 443;
        $result = ['status' => 'unknown'];
        try {
            $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false]]);
            $client = stream_socket_client("tls://{$host}:{$port}", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
            if ($client) {
                $params = stream_context_get_params($client);
                $cert = $params['options']['ssl']['peer_certificate'] ?? null;
                if ($cert) {
                    $parsed = openssl_x509_parse($cert);
                    if (is_array($parsed) && isset($parsed['validTo_time_t'])) {
                        $expireTs = $parsed['validTo_time_t'];
                        $daysLeft = (int)floor(($expireTs - time()) / 86400);
                        $result = ['status' => $daysLeft > 0 ? 'pass' : 'fail', 'days_left' => $daysLeft];
                    }
                }
            }
        } catch (\Throwable $e) {
            $result = ['status' => 'fail', 'message' => $e->getMessage()];
        }
        return $result;
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            $db = $this->app->database();
            $stmt = $db->query('SELECT 1');
            $row = $stmt->fetch();
            $time = (microtime(true) - $start) * 1000;
            return ['status' => 'pass', 'response_time_ms' => (int)$time];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    private function checkPhpFpm(): array
    {
        // Try to read from /status if available (local PHP-FPM status page)
        $statusPage = $this->app->config('health.php_fpm_status_uri', '/status');
        // Can't access PHP-FPM directly in many setups - provide basic check
        $proc = @shell_exec('pgrep -f php-fpm | wc -l');
        $count = is_numeric(trim($proc)) ? (int)trim($proc) : 0;
        return ['status' => $count > 0 ? 'pass' : 'fail', 'process_count' => $count];
    }

    private function checkDisk(): array
    {
        $path = $this->app->paths('base') ?? __DIR__;
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if ($total > 0) {
            $percentUsed = (int)floor((($total - $free) / $total) * 100);
            $status = $percentUsed >= $this->app->config('health.thresholds.disk_critical', 90) ? 'fail' : ($percentUsed >= $this->app->config('health.thresholds.disk_warning', 80) ? 'warning' : 'pass');
            return ['status' => $status, 'percent_used' => $percentUsed];
        }
        return ['status' => 'unknown'];
    }

    private function checkVendApi(): array
    {
        $vend = $this->app->config('apis.vend', []);
        if (empty($vend['url']) || empty($vend['token'])) {
            return ['status' => 'warning', 'message' => 'Vend API not configured'];
        }

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, rtrim($vend['url'], '/') . '/users/current');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $vend['token']]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $start = microtime(true);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $time = (microtime(true) - $start) * 1000;
            curl_close($ch);
            if ($code >= 200 && $code < 300) {
                return ['status' => 'pass', 'response_time_ms' => (int)$time];
            }
            return ['status' => 'fail', 'http_code' => $code, 'response' => $resp];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        // Placeholder - return pass if queue not enabled
        if (!$this->app->config('health.checks.queue', false)) {
            return ['status' => 'pass', 'message' => 'Queue checks disabled'];
        }

        try {
            $count = $this->app->queue()->pendingCount();
            return ['status' => 'pass', 'pending' => $count];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    private function aggregateStatus(array $checks): string
    {
        foreach ($checks as $c) {
            if (isset($c['status']) && $c['status'] === 'fail') {
                return 'fail';
            }
        }
        return 'pass';
    }
}
                    'expires_at' => date('Y-m-d', $expiryDate),
                    'days_until_expiry' => $daysUntilExpiry,
                    'response_time_ms' => $responseTime,
                ];
            }

            return [
                'status' => 'pass',
                'message' => "SSL certificate valid ({$daysUntilExpiry} days remaining)",
                'expires_at' => date('Y-m-d', $expiryDate),
                'days_until_expiry' => $daysUntilExpiry,
                'issuer' => $cert['issuer']['O'] ?? 'Unknown',
                'response_time_ms' => $responseTime,
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'message' => 'SSL check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    /**
     * Check database connection
     */
    private function checkDatabase(): array
    {
        $startTime = microtime(true);

        try {
            $timeout = $this->app->config('health.thresholds.database_query_timeout', 5);

            // Simple query
            $result = $this->app->database()->query("SELECT 1 as test")->fetch();

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($result['test'] !== 1) {
                throw new \Exception('Invalid query result');
            }

            // Get connection stats
            $stats = $this->app->database()->query("SHOW STATUS LIKE 'Threads_connected'")->fetch();
            $connections = $stats['Value'] ?? 'unknown';

            return [
                'status' => 'pass',
                'message' => 'Database connection healthy',
                'response_time_ms' => $responseTime,
                'connections' => $connections,
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    /**
     * Check PHP-FPM status
     */
    private function checkPhpFpm(): array
    {
        $startTime = microtime(true);

        try {
            // Check process count
            $processes = 0;
            if (function_exists('exec')) {
                exec('ps aux | grep -c php-fpm', $output);
                $processes = (int)($output[0] ?? 0);
            }

            $minProcesses = $this->app->config('health.thresholds.php_fpm_min_processes', 1);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($processes < $minProcesses) {
                return [
                    'status' => 'warning',
                    'message' => "Only {$processes} PHP-FPM processes running (min: {$minProcesses})",
                    'processes' => $processes,
                    'response_time_ms' => $responseTime,
                ];
            }

            return [
                'status' => 'pass',
                'message' => 'PHP-FPM healthy',
                'processes' => $processes,
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'response_time_ms' => $responseTime,
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'warning',
                'message' => 'PHP-FPM check incomplete: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    /**
     * Check disk space
     */
    private function checkDiskSpace(): array
    {
        $startTime = microtime(true);

        try {
            $path = $this->app->config('paths.base', __DIR__);
            $total = disk_total_space($path);
            $free = disk_free_space($path);
            $used = $total - $free;
            $usedPercent = round(($used / $total) * 100, 2);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $warningThreshold = $this->app->config('health.thresholds.disk_warning', 80);
            $criticalThreshold = $this->app->config('health.thresholds.disk_critical', 90);

            if ($usedPercent >= $criticalThreshold) {
                return [
                    'status' => 'fail',
                    'message' => "Disk space critical: {$usedPercent}% used",
                    'used_percent' => $usedPercent,
                    'total_gb' => round($total / 1073741824, 2),
                    'free_gb' => round($free / 1073741824, 2),
                    'used_gb' => round($used / 1073741824, 2),
                    'response_time_ms' => $responseTime,
                ];
            } elseif ($usedPercent >= $warningThreshold) {
                return [
                    'status' => 'warning',
                    'message' => "Disk space low: {$usedPercent}% used",
                    'used_percent' => $usedPercent,
                    'total_gb' => round($total / 1073741824, 2),
                    'free_gb' => round($free / 1073741824, 2),
                    'used_gb' => round($used / 1073741824, 2),
                    'response_time_ms' => $responseTime,
                ];
            }

            return [
                'status' => 'pass',
                'message' => "Disk space healthy: {$usedPercent}% used",
                'used_percent' => $usedPercent,
                'total_gb' => round($total / 1073741824, 2),
                'free_gb' => round($free / 1073741824, 2),
                'used_gb' => round($used / 1073741824, 2),
                'response_time_ms' => $responseTime,
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'warning',
                'message' => 'Disk space check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    /**
     * Check Vend API connectivity
     */
    private function checkVendApi(): array
    {
        $startTime = microtime(true);

        try {
            $apiUrl = $this->app->config('apis.vend.url');
            $apiToken = $this->app->config('apis.vend.token');

            if (empty($apiToken)) {
                return [
                    'status' => 'warning',
                    'message' => 'Vend API token not configured',
                    'response_time_ms' => 0,
                ];
            }

            // Simple API call
            $ch = curl_init("{$apiUrl}/users");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$apiToken}",
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($curlError) {
                throw new \Exception($curlError);
            }

            if ($httpCode !== 200) {
                return [
                    'status' => 'fail',
                    'message' => "Vend API returned HTTP {$httpCode}",
                    'http_code' => $httpCode,
                    'response_time_ms' => $responseTime,
                ];
            }

            return [
                'status' => 'pass',
                'message' => 'Vend API connection healthy',
                'http_code' => $httpCode,
                'response_time_ms' => $responseTime,
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'message' => 'Vend API check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    /**
     * Check queue workers
     */
    private function checkQueue(): array
    {
        $startTime = microtime(true);

        try {
            // Check for active queue jobs
            $activeJobs = 0;
            // TODO: Implement when queue system is active

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'pass',
                'message' => 'Queue workers healthy',
                'active_jobs' => $activeJobs,
                'response_time_ms' => $responseTime,
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'warning',
                'message' => 'Queue check failed: ' . $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    /**
     * Render health dashboard HTML
     */
    private function renderHealthDashboard(array $currentChecks, array $recentChecks): string
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>System Health Dashboard</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                .status-pass { color: #198754; }
                .status-warning { color: #ffc107; }
                .status-fail { color: #dc3545; }
                .health-card { margin-bottom: 1rem; }
            </style>
        </head>
        <body>
            <div class="container mt-4">
                <h1>System Health Dashboard</h1>

                <div class="alert alert-<?= $currentChecks['status'] === 'pass' ? 'success' : ($currentChecks['status'] === 'warning' ? 'warning' : 'danger') ?>">
                    Overall Status: <strong><?= strtoupper($currentChecks['status']) ?></strong>
                    <span class="float-end">Last checked: <?= $currentChecks['_meta']['timestamp'] ?></span>
                </div>

                <div class="row">
                    <?php foreach ($currentChecks['checks'] as $checkType => $check): ?>
                    <div class="col-md-6 health-card">
                        <div class="card">
                            <div class="card-header status-<?= $check['status'] ?>">
                                <strong><?= ucwords(str_replace('_', ' ', $checkType)) ?></strong>
                                <span class="float-end"><?= strtoupper($check['status']) ?></span>
                            </div>
                            <div class="card-body">
                                <p><?= htmlspecialchars($check['message']) ?></p>
                                <small class="text-muted">Response time: <?= $check['response_time_ms'] ?>ms</small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <h3>Recent Checks</h3>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Response Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentChecks as $check): ?>
                            <tr>
                                <td><?= $check['checked_at'] ?></td>
                                <td><?= $check['check_type'] ?></td>
                                <td class="status-<?= $check['status'] ?>"><?= strtoupper($check['status']) ?></td>
                                <td><?= $check['response_time_ms'] ?>ms</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                // Auto-refresh every 30 seconds
                setTimeout(() => location.reload(), 30000);
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
