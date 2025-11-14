<?php

declare(strict_types=1);

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\OnboardingService;
use StaffEmailHub\Database\DataSeeder;
use PDO;

/**
 * OnboardingController - Handles module setup wizard and initialization
 */
class OnboardingController
{
    private OnboardingService $onboardingService;
    private PDO $db;

    public function __construct(PDO $db, string $basePath)
    {
        $this->db = $db;
        $this->onboardingService = new OnboardingService($db, $basePath);
    }

    /**
     * GET /admin/onboarding/status
     * Get current onboarding status
     */
    public function getStatus(): array
    {
        try {
            $status = $this->onboardingService->getOnboardingStatus();

            return [
                'success' => true,
                'status' => $status,
            ];
        } catch (\Exception $e) {
            return $this->error('Failed to get onboarding status: ' . $e->getMessage());
        }
    }

    /**
     * POST /admin/onboarding/create-directories
     * Create required directories
     */
    public function createDirectories(): array
    {
        try {
            $results = $this->onboardingService->createDirectories();

            return [
                'success' => true,
                'message' => 'Directories created successfully',
                'results' => $results,
            ];
        } catch (\Exception $e) {
            return $this->error('Failed to create directories: ' . $e->getMessage());
        }
    }

    /**
     * POST /admin/onboarding/configure-email
     * Configure email settings (Rackspace or SendGrid)
     */
    public function configureEmail(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            if (empty($data['provider'])) {
                return $this->error('Email provider is required');
            }

            $result = $this->onboardingService->saveEmailConfig($data);

            if (!$result['success']) {
                return $result;
            }

            return [
                'success' => true,
                'message' => 'Email configuration saved',
                'provider' => $result['provider'],
            ];
        } catch (\Exception $e) {
            return $this->error('Failed to configure email: ' . $e->getMessage());
        }
    }

    /**
     * POST /admin/onboarding/test-email
     * Test email configuration
     */
    public function testEmail(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            if (empty($data['provider']) || empty($data['test_email'])) {
                return $this->error('Provider and test_email are required');
            }

            $result = $this->onboardingService->testEmailConfig(
                $data['provider'],
                $data['test_email']
            );

            return $result;
        } catch (\Exception $e) {
            return $this->error('Email test failed: ' . $e->getMessage());
        }
    }

    /**
     * POST /admin/onboarding/load-sample-data
     * Load sample customer, order, and email data
     */
    public function loadSampleData(): array
    {
        try {
            $seeder = new DataSeeder($this->db, true);
            $result = $seeder->seed();

            return $result;
        } catch (\Exception $e) {
            return $this->error('Failed to load sample data: ' . $e->getMessage());
        }
    }

    /**
     * POST /admin/onboarding/clear-sample-data
     * Clear all sample/demo data
     */
    public function clearSampleData(): array
    {
        try {
            $seeder = new DataSeeder($this->db, true);
            $result = $seeder->clearDemoData();

            return $result;
        } catch (\Exception $e) {
            return $this->error('Failed to clear sample data: ' . $e->getMessage());
        }
    }

    /**
     * POST /admin/onboarding/complete
     * Mark onboarding as complete
     */
    public function complete(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $completedBy = $data['completed_by'] ?? 'system';

            $result = $this->onboardingService->completeOnboarding($completedBy);

            return $result;
        } catch (\Exception $e) {
            return $this->error('Failed to complete onboarding: ' . $e->getMessage());
        }
    }

    /**
     * GET /admin/onboarding/wizard
     * Get full onboarding wizard UI data
     */
    public function getWizardData(): array
    {
        try {
            $status = $this->onboardingService->getOnboardingStatus();

            return [
                'success' => true,
                'current_step' => $this->determineCurrentStep($status),
                'completion_percentage' => $status['completion_percentage'],
                'steps' => [
                    [
                        'number' => 1,
                        'title' => 'Environment Check',
                        'description' => 'Verify PHP extensions and file permissions',
                        'completed' => $status['step1_environment']['passed'],
                        'issues' => $this->getIssues($status['step1_environment']),
                    ],
                    [
                        'number' => 2,
                        'title' => 'Database Setup',
                        'description' => 'Create required database tables',
                        'completed' => $status['step2_database']['passed'],
                        'issues' => $this->getIssues($status['step2_database']),
                    ],
                    [
                        'number' => 3,
                        'title' => 'Email Configuration',
                        'description' => 'Configure Rackspace IMAP/SMTP or SendGrid',
                        'completed' => $status['step3_email_config']['passed'],
                        'issues' => $this->getIssues($status['step3_email_config']),
                        'providers' => [
                            'rackspace_imap' => $status['step3_email_config']['rackspace_imap'],
                            'rackspace_smtp' => $status['step3_email_config']['rackspace_smtp'],
                            'sendgrid' => $status['step3_email_config']['sendgrid'],
                        ],
                    ],
                    [
                        'number' => 4,
                        'title' => 'File Storage',
                        'description' => 'Verify upload and storage directories',
                        'completed' => $status['step4_file_storage']['passed'],
                        'issues' => $this->getIssues($status['step4_file_storage']),
                    ],
                    [
                        'number' => 5,
                        'title' => 'Feature Configuration',
                        'description' => 'Enable/disable features',
                        'completed' => true,
                        'features' => $status['step5_feature_flags'],
                    ],
                    [
                        'number' => 6,
                        'title' => 'Sample Data',
                        'description' => 'Load demo data for testing',
                        'completed' => $status['step6_sample_data']['loaded'],
                        'data_count' => [
                            'demo_customers' => $status['step6_sample_data']['demo_customers'],
                            'demo_emails' => $status['step6_sample_data']['demo_emails'],
                        ],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            return $this->error('Failed to get wizard data: ' . $e->getMessage());
        }
    }

    /**
     * Determine current onboarding step
     */
    private function determineCurrentStep(array $status): int
    {
        if (!$status['step1_environment']['passed']) return 1;
        if (!$status['step2_database']['passed']) return 2;
        if (!$status['step3_email_config']['passed']) return 3;
        if (!$status['step4_file_storage']['passed']) return 4;
        if (!$status['step6_sample_data']['loaded']) return 5;
        return 6;
    }

    /**
     * Extract issues from status checks
     */
    private function getIssues(array $checks): array
    {
        $issues = [];

        foreach ($checks as $key => $value) {
            if (is_array($value)) {
                if ($key === 'checks' && isset($checks['checks'])) {
                    foreach ($checks['checks'] as $checkKey => $checkValue) {
                        if (!$checkValue) {
                            $issues[] = [
                                'type' => 'error',
                                'message' => 'Failed: ' . $this->humanizeKey($checkKey),
                            ];
                        }
                    }
                }
                if ($key === 'tables' && isset($checks['tables'])) {
                    foreach ($checks['tables'] as $tableKey => $tableValue) {
                        if (!$tableValue) {
                            $issues[] = [
                                'type' => 'error',
                                'message' => 'Missing table: ' . $tableKey,
                            ];
                        }
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Humanize configuration key names
     */
    private function humanizeKey(string $key): string
    {
        $map = [
            'php_version' => 'PHP 8.0+',
            'pdo_available' => 'PDO Extension',
            'pdo_mysql' => 'PDO MySQL',
            'curl_available' => 'CURL Extension',
            'fileinfo_available' => 'FileInfo Extension',
            'openssl_available' => 'OpenSSL Extension',
            'gd_available' => 'GD Extension',
            'env_file_exists' => '.env File',
            'config_writable' => 'Config Directory Writable',
            'storage_writable' => 'Storage Directory Writable',
        ];

        return $map[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Generic error response
     */
    private function error(string $message): array
    {
        error_log("[OnboardingController] $message");
        return [
            'success' => false,
            'error' => $message,
        ];
    }
}
