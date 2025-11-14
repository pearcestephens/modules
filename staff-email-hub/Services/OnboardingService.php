<?php

declare(strict_types=1);

namespace StaffEmailHub\Services;

use Exception;
use PDO;

/**
 * OnboardingService - Handles initial setup and configuration for the Staff Email Hub
 *
 * Features:
 * - Setup wizard workflow management
 * - Configuration validation
 * - Environment setup verification
 * - Permission validation
 * - Feature enablement
 */
class OnboardingService
{
    private PDO $db;
    private string $basePath;
    private array $config = [];

    public function __construct(PDO $db, string $basePath)
    {
        $this->db = $db;
        $this->basePath = $basePath;
        $this->loadConfig();
    }

    /**
     * Load current configuration
     */
    private function loadConfig(): void
    {
        try {
            $stmt = $this->db->query("SELECT * FROM module_config WHERE module = 'staff-email-hub'");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $this->config[$row['config_key']] = json_decode($row['config_value'], true);
            }
        } catch (Exception $e) {
            error_log("[OnboardingService] Failed to load config: " . $e->getMessage());
        }
    }

    /**
     * Get onboarding status
     *
     * @return array Status of each onboarding step
     */
    public function getOnboardingStatus(): array
    {
        return [
            'step1_environment' => $this->checkEnvironment(),
            'step2_database' => $this->checkDatabase(),
            'step3_email_config' => $this->checkEmailConfig(),
            'step4_file_storage' => $this->checkFileStorage(),
            'step5_feature_flags' => $this->checkFeatureFlags(),
            'step6_sample_data' => $this->checkSampleData(),
            'completion_percentage' => $this->calculateCompletion(),
            'is_complete' => $this->isOnboardingComplete(),
        ];
    }

    /**
     * Check environment prerequisites
     */
    private function checkEnvironment(): array
    {
        $checks = [
            'php_version' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'pdo_available' => extension_loaded('PDO'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'curl_available' => extension_loaded('curl'),
            'fileinfo_available' => extension_loaded('fileinfo'),
            'openssl_available' => extension_loaded('openssl'),
            'gd_available' => extension_loaded('gd'),
            'env_file_exists' => file_exists($this->basePath . '/.env'),
            'config_writable' => is_writable($this->basePath . '/config'),
            'storage_writable' => is_writable($this->basePath . '/storage'),
        ];

        return [
            'passed' => array_reduce($checks, fn($carry, $item) => $carry && $item, true),
            'checks' => $checks,
        ];
    }

    /**
     * Check database setup
     */
    private function checkDatabase(): array
    {
        $tables = [
            'staff_emails',
            'staff_email_templates',
            'customer_hub_profile',
            'customer_id_uploads',
            'customer_purchase_history',
            'customer_communication_log',
            'customer_search_index',
            'email_search_index',
            'module_config',
            'id_verification_audit_log',
            'email_access_log',
        ];

        $exists = [];
        try {
            foreach ($tables as $table) {
                $stmt = $this->db->query("SHOW TABLES LIKE '$table'");
                $exists[$table] = $stmt->rowCount() > 0;
            }
        } catch (Exception $e) {
            error_log("[OnboardingService] Database check failed: " . $e->getMessage());
        }

        return [
            'passed' => array_reduce($exists, fn($carry, $item) => $carry && $item, true),
            'tables' => $exists,
            'missing_count' => count(array_filter($exists, fn($item) => !$item)),
        ];
    }

    /**
     * Check email configuration
     */
    private function checkEmailConfig(): array
    {
        $config = [];

        // Rackspace IMAP Configuration
        $config['rackspace_imap'] = [
            'enabled' => !empty($this->config['RACKSPACE_IMAP_HOST']),
            'host' => !empty($_ENV['RACKSPACE_IMAP_HOST'] ?? $this->config['RACKSPACE_IMAP_HOST'] ?? ''),
            'port' => !empty($_ENV['RACKSPACE_IMAP_PORT'] ?? $this->config['RACKSPACE_IMAP_PORT'] ?? ''),
            'username' => !empty($_ENV['RACKSPACE_IMAP_USERNAME'] ?? $this->config['RACKSPACE_IMAP_USERNAME'] ?? ''),
            'password' => !empty($_ENV['RACKSPACE_IMAP_PASSWORD'] ?? ''),
        ];

        // Rackspace SMTP Configuration
        $config['rackspace_smtp'] = [
            'enabled' => !empty($this->config['RACKSPACE_SMTP_HOST']),
            'host' => !empty($_ENV['RACKSPACE_SMTP_HOST'] ?? $this->config['RACKSPACE_SMTP_HOST'] ?? ''),
            'port' => !empty($_ENV['RACKSPACE_SMTP_PORT'] ?? $this->config['RACKSPACE_SMTP_PORT'] ?? ''),
            'username' => !empty($_ENV['RACKSPACE_SMTP_USERNAME'] ?? $this->config['RACKSPACE_SMTP_USERNAME'] ?? ''),
            'password' => !empty($_ENV['RACKSPACE_SMTP_PASSWORD'] ?? ''),
        ];

        // SendGrid Configuration
        $config['sendgrid'] = [
            'enabled' => !empty($_ENV['SENDGRID_API_KEY'] ?? $this->config['SENDGRID_API_KEY'] ?? ''),
            'api_key' => !empty($_ENV['SENDGRID_API_KEY'] ?? $this->config['SENDGRID_API_KEY'] ?? ''),
        ];

        // At least one email provider must be configured
        $hasProvider = $config['rackspace_smtp']['enabled'] ||
                      $config['rackspace_imap']['enabled'] ||
                      $config['sendgrid']['enabled'];

        return [
            'passed' => $hasProvider,
            'rackspace_imap' => $config['rackspace_imap'],
            'rackspace_smtp' => $config['rackspace_smtp'],
            'sendgrid' => $config['sendgrid'],
            'has_email_provider' => $hasProvider,
        ];
    }

    /**
     * Check file storage configuration
     */
    private function checkFileStorage(): array
    {
        $paths = [
            'id_uploads' => $this->basePath . '/storage/id_uploads',
            'email_attachments' => $this->basePath . '/storage/email_attachments',
            'temp_files' => $this->basePath . '/storage/temp',
            'logs' => $this->basePath . '/storage/logs',
        ];

        $checks = [];
        foreach ($paths as $name => $path) {
            $exists = file_exists($path);
            $writable = $exists && is_writable($path);

            $checks[$name] = [
                'exists' => $exists,
                'writable' => $writable,
                'path' => $path,
            ];
        }

        return [
            'passed' => array_reduce($checks, fn($carry, $item) => $carry && $item['writable'], true),
            'paths' => $checks,
        ];
    }

    /**
     * Check feature flags
     */
    private function checkFeatureFlags(): array
    {
        return [
            'email_client_enabled' => $this->config['FEATURE_EMAIL_CLIENT'] ?? true,
            'customer_hub_enabled' => $this->config['FEATURE_CUSTOMER_HUB'] ?? true,
            'id_verification_enabled' => $this->config['FEATURE_ID_VERIFICATION'] ?? true,
            'advanced_search_enabled' => $this->config['FEATURE_ADVANCED_SEARCH'] ?? true,
            'audit_logging_enabled' => $this->config['FEATURE_AUDIT_LOGGING'] ?? true,
            'webhook_notifications_enabled' => $this->config['FEATURE_WEBHOOK_NOTIFICATIONS'] ?? false,
        ];
    }

    /**
     * Check if sample/demo data is loaded
     */
    private function checkSampleData(): array
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM customer_hub_profile WHERE is_demo_data = true");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $demoCount = $result['count'] ?? 0;

            $stmt = $this->db->query("SELECT COUNT(*) as count FROM staff_emails WHERE is_demo_data = true");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $emailCount = $result['count'] ?? 0;

            return [
                'loaded' => $demoCount > 0 || $emailCount > 0,
                'demo_customers' => $demoCount,
                'demo_emails' => $emailCount,
            ];
        } catch (Exception $e) {
            error_log("[OnboardingService] Sample data check failed: " . $e->getMessage());
            return [
                'loaded' => false,
                'demo_customers' => 0,
                'demo_emails' => 0,
            ];
        }
    }

    /**
     * Calculate onboarding completion percentage
     */
    private function calculateCompletion(): int
    {
        $status = $this->getOnboardingStatus();
        $steps = [
            $status['step1_environment']['passed'] ? 1 : 0,
            $status['step2_database']['passed'] ? 1 : 0,
            $status['step3_email_config']['passed'] ? 1 : 0,
            $status['step4_file_storage']['passed'] ? 1 : 0,
            !empty($status['step5_feature_flags']) ? 1 : 0,
            $status['step6_sample_data']['loaded'] ? 1 : 0,
        ];

        return (int) round((array_sum($steps) / count($steps)) * 100);
    }

    /**
     * Check if onboarding is complete
     */
    private function isOnboardingComplete(): bool
    {
        $status = $this->getOnboardingStatus();
        return $status['step1_environment']['passed'] &&
               $status['step2_database']['passed'] &&
               $status['step3_email_config']['passed'] &&
               $status['step4_file_storage']['passed'];
    }

    /**
     * Create required directories
     */
    public function createDirectories(): array
    {
        $paths = [
            'storage/id_uploads',
            'storage/email_attachments',
            'storage/temp',
            'storage/logs',
            'cache',
        ];

        $results = [];
        foreach ($paths as $path) {
            $fullPath = $this->basePath . '/' . $path;

            try {
                if (!file_exists($fullPath)) {
                    mkdir($fullPath, 0755, true);
                }
                chmod($fullPath, 0755);
                $results[$path] = ['success' => true, 'message' => 'Directory created/verified'];
            } catch (Exception $e) {
                $results[$path] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Save email configuration
     */
    public function saveEmailConfig(array $emailConfig): array
    {
        try {
            // Validate configuration
            if (empty($emailConfig['provider'])) {
                return $this->error('Email provider must be specified');
            }

            $provider = $emailConfig['provider']; // 'rackspace' or 'sendgrid'

            if ($provider === 'rackspace') {
                // Validate Rackspace IMAP
                if (!empty($emailConfig['rackspace_imap'])) {
                    $required = ['host', 'port', 'username', 'password'];
                    foreach ($required as $field) {
                        if (empty($emailConfig['rackspace_imap'][$field])) {
                            return $this->error("Rackspace IMAP: $field is required");
                        }
                    }
                }

                // Validate Rackspace SMTP
                if (!empty($emailConfig['rackspace_smtp'])) {
                    $required = ['host', 'port', 'username', 'password'];
                    foreach ($required as $field) {
                        if (empty($emailConfig['rackspace_smtp'][$field])) {
                            return $this->error("Rackspace SMTP: $field is required");
                        }
                    }
                }
            } elseif ($provider === 'sendgrid') {
                // Validate SendGrid
                if (empty($emailConfig['sendgrid']['api_key'])) {
                    return $this->error('SendGrid API key is required');
                }
            }

            // Save to database
            $stmt = $this->db->prepare("
                INSERT INTO module_config (module, config_key, config_value, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
            ");

            // Save each configuration value
            foreach ($emailConfig as $key => $value) {
                $stmt->execute([
                    'staff-email-hub',
                    strtoupper($key),
                    is_array($value) ? json_encode($value) : $value,
                ]);
            }

            // Reload configuration
            $this->loadConfig();

            return [
                'success' => true,
                'message' => 'Email configuration saved successfully',
                'provider' => $provider,
            ];
        } catch (Exception $e) {
            return $this->error('Failed to save email configuration: ' . $e->getMessage());
        }
    }

    /**
     * Test email configuration
     */
    public function testEmailConfig(string $provider, string $testEmail): array
    {
        try {
            if ($provider === 'rackspace') {
                return $this->testRackspaceConfig($testEmail);
            } elseif ($provider === 'sendgrid') {
                return $this->testSendGridConfig($testEmail);
            }

            return $this->error('Unknown email provider');
        } catch (Exception $e) {
            return $this->error('Email config test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test Rackspace IMAP connection
     */
    private function testRackspaceConfig(string $testEmail): array
    {
        try {
            $host = $this->config['RACKSPACE_IMAP_HOST'] ?? $_ENV['RACKSPACE_IMAP_HOST'] ?? '';
            $port = $this->config['RACKSPACE_IMAP_PORT'] ?? $_ENV['RACKSPACE_IMAP_PORT'] ?? 993;
            $username = $this->config['RACKSPACE_IMAP_USERNAME'] ?? $_ENV['RACKSPACE_IMAP_USERNAME'] ?? '';
            $password = $this->config['RACKSPACE_IMAP_PASSWORD'] ?? $_ENV['RACKSPACE_IMAP_PASSWORD'] ?? '';

            if (empty($host) || empty($username) || empty($password)) {
                return $this->error('Rackspace IMAP credentials not configured');
            }

            // Test IMAP connection
            $imapPath = "{" . $host . ":" . $port . "/imap/ssl}INBOX";
            $mailbox = @imap_open($imapPath, $username, $password, OP_READONLY, 1);

            if (!$mailbox) {
                return $this->error('Failed to connect to Rackspace IMAP: ' . imap_last_error());
            }

            imap_close($mailbox);

            return [
                'success' => true,
                'message' => 'Rackspace IMAP connection successful',
                'provider' => 'rackspace',
                'type' => 'imap',
            ];
        } catch (Exception $e) {
            return $this->error('Rackspace test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test SendGrid configuration
     */
    private function testSendGridConfig(string $testEmail): array
    {
        try {
            $apiKey = $this->config['SENDGRID_API_KEY'] ?? $_ENV['SENDGRID_API_KEY'] ?? '';

            if (empty($apiKey)) {
                return $this->error('SendGrid API key not configured');
            }

            // Test SendGrid API
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.sendgrid.com/v3/mail/send',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    'personalizations' => [[
                        'to' => [['email' => $testEmail]],
                    ]],
                    'from' => ['email' => 'test@vapeshed.co.nz'],
                    'subject' => 'Staff Email Hub - Configuration Test',
                    'content' => [['type' => 'text/plain', 'value' => 'Test email from Staff Email Hub']],
                ]),
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 202) {
                return [
                    'success' => true,
                    'message' => 'SendGrid test email sent successfully',
                    'provider' => 'sendgrid',
                ];
            }

            return $this->error('SendGrid test failed: HTTP ' . $httpCode);
        } catch (Exception $e) {
            return $this->error('SendGrid test failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark onboarding as complete
     */
    public function completeOnboarding(string $completedBy): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO module_config (module, config_key, config_value, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
            ");

            $stmt->execute([
                'staff-email-hub',
                'ONBOARDING_COMPLETED',
                json_encode([
                    'timestamp' => date('Y-m-d H:i:s'),
                    'completed_by' => $completedBy,
                    'version' => '1.0.0',
                ]),
            ]);

            return [
                'success' => true,
                'message' => 'Onboarding marked as complete',
            ];
        } catch (Exception $e) {
            return $this->error('Failed to mark onboarding complete: ' . $e->getMessage());
        }
    }

    /**
     * Generic error response
     */
    private function error(string $message): array
    {
        error_log("[OnboardingService] $message");
        return [
            'success' => false,
            'error' => $message,
        ];
    }
}
