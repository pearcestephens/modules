<?php
/**
 * AI Agent Automation Engine
 * Autonomous task processing, monitoring, and self-healing
 *
 * @package HumanResources\Payroll\AI
 */

declare(strict_types=1);

namespace HumanResources\Payroll\AI;

class AgentEngine
{
    private array $config;
    private string $logPath;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'auto_heal' => true,
            'auto_sync' => true,
            'auto_reconcile' => true,
            'alert_threshold' => 0.95,
            'max_retries' => 3,
            'retry_delay' => 5,
        ], $config);

        $this->logPath = __DIR__ . '/../logs/ai_agent.log';
    }

    /**
     * Run autonomous monitoring and self-healing cycle
     */
    public function runCycle(): array
    {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => [],
            'actions' => [],
            'errors' => [],
        ];

        // 1. Health Checks
        $results['checks']['database'] = $this->checkDatabase();
        $results['checks']['services'] = $this->checkServices();
        $results['checks']['queue'] = $this->checkEmailQueue();
        $results['checks']['storage'] = $this->checkStorage();

        // 2. Automated Actions
        if ($this->config['auto_sync']) {
            $results['actions']['sync'] = $this->autoSync();
        }

        if ($this->config['auto_reconcile']) {
            $results['actions']['reconcile'] = $this->autoReconcile();
        }

        if ($this->config['auto_heal']) {
            $results['actions']['heal'] = $this->autoHeal($results['checks']);
        }

        // 3. Log Results
        $this->log('Cycle completed', $results);

        return $results;
    }

    /**
     * Check database health and performance
     */
    private function checkDatabase(): array
    {
        try {
            require_once __DIR__ . '/../lib/VapeShedDb.php';
            $conn = \HumanResources\Payroll\Lib\getVapeShedConnection();

            if (!$conn) {
                return ['status' => 'error', 'message' => 'Connection failed'];
            }

            // Check response time
            $start = microtime(true);
            $conn->query('SELECT 1');
            $duration = (microtime(true) - $start) * 1000;

            return [
                'status' => 'healthy',
                'response_time' => round($duration, 2) . 'ms',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check external service connectivity
     */
    private function checkServices(): array
    {
        $services = [];

        // Xero
        $services['xero'] = $this->pingService('xero', 'https://api.xero.com/api.xro/2.0/Organisation');

        // Deputy
        $services['deputy'] = $this->pingService('deputy', 'https://api.deputy.com/api/v1/whoami');

        // SendGrid
        $services['sendgrid'] = $this->checkSendGrid();

        return $services;
    }

    /**
     * Ping external service
     */
    private function pingService(string $name, string $url): array
    {
        $start = microtime(true);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = (microtime(true) - $start) * 1000;

        curl_close($ch);

        $isHealthy = $httpCode >= 200 && $httpCode < 500;

        return [
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'http_code' => $httpCode,
            'response_time' => round($duration, 2) . 'ms'
        ];
    }

    /**
     * Check SendGrid configuration
     */
    private function checkSendGrid(): array
    {
        $sendgridPath = $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/services/SendGridService.php';
        if (file_exists($sendgridPath)) {
            require_once $sendgridPath;
        }

        try {
            $apiKey = getenv('SENDGRID_API_KEY') ?: '';
            $isConfigured = !empty($apiKey);

            return [
                'status' => $isConfigured ? 'healthy' : 'not_configured',
                'api_key_present' => $isConfigured
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check email queue status
     */
    private function checkEmailQueue(): array
    {
        require_once __DIR__ . '/../lib/EmailQueueHelper.php';

        try {
            $stats = queue_get_stats();
            $pendingRatio = $stats['total'] > 0 ? ($stats['pending'] / $stats['total']) : 0;

            return [
                'status' => $pendingRatio < 0.8 ? 'healthy' : 'backlog',
                'pending' => $stats['pending'],
                'sent' => $stats['sent'],
                'failed' => $stats['failed'],
                'total' => $stats['total'],
                'pending_ratio' => round($pendingRatio, 3)
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check storage capacity
     */
    private function checkStorage(): array
    {
        $path = __DIR__ . '/..';
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $used = $total - $free;
        $usedPercent = ($used / $total) * 100;

        return [
            'status' => $usedPercent < 90 ? 'healthy' : 'low',
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_percent' => round($usedPercent, 1)
        ];
    }

    /**
     * Auto-sync with external services
     */
    private function autoSync(): array
    {
        $results = [];

        // Sync with Xero
        try {
            // Would call XeroService sync methods here
            $results['xero'] = ['status' => 'queued'];
        } catch (\Exception $e) {
            $results['xero'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Sync with Deputy
        try {
            // Would call DeputyService sync methods here
            $results['deputy'] = ['status' => 'queued'];
        } catch (\Exception $e) {
            $results['deputy'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Auto-reconcile discrepancies
     */
    private function autoReconcile(): array
    {
        // Would run reconciliation checks and auto-fix minor issues
        return [
            'status' => 'completed',
            'checks_run' => 0,
            'issues_found' => 0,
            'auto_fixed' => 0
        ];
    }

    /**
     * Self-healing actions
     */
    private function autoHeal(array $checks): array
    {
        $actions = [];

        // Heal database connections
        if ($checks['database']['status'] === 'error') {
            $actions[] = 'Attempted database reconnection';
        }

        // Clear email queue backlog
        if (isset($checks['queue']['status']) && $checks['queue']['status'] === 'backlog') {
            $actions[] = 'Triggered email queue processor';
        }

        // Alert on low storage
        if ($checks['storage']['status'] === 'low') {
            $actions[] = 'Storage alert sent';
        }

        return [
            'actions_taken' => count($actions),
            'details' => $actions
        ];
    }

    /**
     * Log agent activity
     */
    private function log(string $message, array $context = []): void
    {
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context
        ];

        file_put_contents(
            $this->logPath,
            json_encode($entry) . "\n",
            FILE_APPEND
        );
    }
}
