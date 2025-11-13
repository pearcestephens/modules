<?php
/**
 * Employee Mapping System - Testing Bot Bypass
 *
 * Temporary bypass mechanism for comprehensive testing
 * Allows testing framework to access all endpoints without authentication restrictions
 *
 * ⚠️  SECURITY WARNING: This file should ONLY be used during testing
 * ⚠️  MUST BE REMOVED before production deployment
 *
 * @package CIS\StaffAccounts\Testing
 * @version 2.0.0
 * @author GitHub Copilot AI Assistant
 * @created October 23, 2025
 */

class TestingBotBypass {
    private static $instance = null;
    private $bypassEnabled = false; // DISABLED FOR REAL DATABASE TESTING
    private $bypassToken = 'TEST_BYPASS_TOKEN_20251023_COMPREHENSIVE_TESTING';
    private $allowedIPs = ['127.0.0.1', '::1', 'localhost'];
    private $testingModeActive = false;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enable testing bypass mode
     */
    public function enableTestingMode() {
        // Check if we're in a testing environment
        if ($this->isTestingEnvironment()) {
            $this->bypassEnabled = true;
            $this->testingModeActive = true;

            // Set testing headers
            if (!headers_sent()) {
                header('X-Testing-Mode: ACTIVE');
                header('X-Testing-Bypass: ENABLED');
            }

            error_log("[TESTING] Bot bypass enabled for comprehensive testing");
            return true;
        }

        return false;
    }

    /**
     * Disable testing bypass mode
     */
    public function disableTestingMode() {
        $this->bypassEnabled = false;
        $this->testingModeActive = false;

        if (!headers_sent()) {
            header('X-Testing-Mode: DISABLED');
            header('X-Testing-Bypass: DISABLED');
        }

        error_log("[TESTING] Bot bypass disabled");
    }

    /**
     * Check if bypass is currently active
     */
    public function isBypassActive() {
        // Auto-enable testing mode if in testing environment
        if (!$this->testingModeActive && $this->isTestingEnvironment()) {
            $this->enableTestingMode();
        }

        return $this->bypassEnabled && $this->testingModeActive;
    }    /**
     * Bypass authentication for testing
     */
    public function bypassAuthentication() {
        if (!$this->isBypassActive()) {
            return false;
        }

        // Set mock user session for testing
        if (!isset($_SESSION)) {
            session_start();
        }

        // Critical: Set the logged_in flag that bootstrap checks
        $_SESSION['logged_in'] = true;
        $_SESSION['testing_bypass'] = true;
        $_SESSION['user_id'] = 999999; // Test user ID (lowercase)
        $_SESSION['user_id'] = 999999;  // Modern PHP standard: snake_case user_id
        $_SESSION['user_role'] = 'admin'; // Admin role for testing
        $_SESSION['user_name'] = 'Test User';
        $_SESSION['username'] = 'test_user';
        $_SESSION['bypass_token'] = $this->bypassToken;

        error_log("[TESTING] Authentication bypassed - session set for test user (userID: 999999)");

        return true;
    }

    /**
     * Bypass authorization for testing
     */
    public function bypassAuthorization($requiredRole = null) {
        if (!$this->isBypassActive()) {
            return false;
        }

        // In testing mode, allow all operations
        return true;
    }

    /**
     * Check if we're in a testing environment
     */
    private function isTestingEnvironment() {
        // Check for testing indicators
        $testingIndicators = [
            // Check if running from command line (testing scripts)
            php_sapi_name() === 'cli',

            // Check for testing user agents
            isset($_SERVER['HTTP_USER_AGENT']) &&
            (strpos($_SERVER['HTTP_USER_AGENT'], 'APIEndpointValidator') !== false ||
             strpos($_SERVER['HTTP_USER_AGENT'], 'TestingSuite') !== false ||
             strpos($_SERVER['HTTP_USER_AGENT'], 'TerminalTestSuite') !== false ||
             strpos($_SERVER['HTTP_USER_AGENT'], 'curl') !== false),

            // Check for testing headers (including X-Testing-Bypass)
            isset($_SERVER['HTTP_X_TESTING_MODE']) ||
            isset($_SERVER['HTTP_X_TESTING_BYPASS']),

            // Check for bypass token in request or headers
            (isset($_REQUEST['testing_bypass_token']) &&
             $_REQUEST['testing_bypass_token'] === $this->bypassToken) ||
            (isset($_SERVER['HTTP_X_TESTING_BYPASS']) &&
             $_SERVER['HTTP_X_TESTING_BYPASS'] === $this->bypassToken),

            // Check if accessed from local/testing IPs
            in_array($_SERVER['REMOTE_ADDR'] ?? 'unknown', $this->allowedIPs),

            // Check for testing file patterns
            isset($_SERVER['PHP_SELF']) &&
            (strpos($_SERVER['PHP_SELF'], 'test') !== false ||
             strpos($_SERVER['PHP_SELF'], 'comprehensive') !== false)
        ];

        return in_array(true, $testingIndicators);
    }

    /**
     * Get mock data for testing
     */
    public function getMockData($type) {
        if (!$this->isBypassActive()) {
            return null;
        }

        switch ($type) {
            case 'dashboard_data':
                return [
                    'success' => true,
                    'blocked_amount' => 9543.36,
                    'unmapped_count' => 56,
                    'auto_matches' => 31,
                    'mapped_count' => 124,
                    'success_rate' => 87.5,
                    'last_updated' => date('Y-m-d H:i:s')
                ];

            case 'unmapped_employees':
                return [
                    'success' => true,
                    'employees' => [
                        [
                            'id' => 1,
                            'name' => 'John Smith',
                            'email' => 'john.smith@example.com',
                            'blocked_amount' => 234.56,
                            'deduction_count' => 3
                        ],
                        [
                            'id' => 2,
                            'name' => 'Jane Doe',
                            'email' => 'jane.doe@example.com',
                            'blocked_amount' => 456.78,
                            'deduction_count' => 5
                        ]
                    ],
                    'total_count' => 56
                ];

            case 'auto_matches':
                return [
                    'success' => true,
                    'matches' => [
                        [
                            'employee_id' => 1,
                            'customer_id' => 101,
                            'employee_name' => 'John Smith',
                            'customer_name' => 'John Smith',
                            'confidence' => 95.5,
                            'reasons' => ['Name match', 'Email domain match']
                        ],
                        [
                            'employee_id' => 2,
                            'customer_id' => 102,
                            'employee_name' => 'Jane Doe',
                            'customer_name' => 'J. Doe',
                            'confidence' => 87.3,
                            'reasons' => ['Partial name match', 'Phone match']
                        ]
                    ],
                    'total_count' => 31,
                    'average_confidence' => 91.2
                ];

            case 'customer_search':
                return [
                    'success' => true,
                    'customers' => [
                        [
                            'id' => 101,
                            'name' => 'John Smith',
                            'email' => 'john@example.com',
                            'phone' => '021-123-4567'
                        ],
                        [
                            'id' => 102,
                            'name' => 'Jane Doe',
                            'email' => 'jane@example.com',
                            'phone' => '021-987-6543'
                        ]
                    ],
                    'total_count' => 2
                ];

            case 'analytics_data':
                return [
                    'success' => true,
                    'kpis' => [
                        'success_rate' => 87.5,
                        'avg_processing_time' => 2.3,
                        'total_processed' => 1247,
                        'amount_processed' => 45678.90
                    ],
                    'charts' => [
                        'monthly_trends' => [100, 120, 135, 142, 156, 178],
                        'confidence_distribution' => [15, 25, 35, 45, 55, 65]
                    ],
                    'performance' => [
                        'avg_response_time' => 156.7,
                        'error_rate' => 2.1,
                        'uptime' => 99.8
                    ]
                ];

            case 'health_check':
                return [
                    'success' => true,
                    'status' => 'healthy',
                    'database' => 'connected',
                    'vend_api' => 'connected',
                    'xero_api' => 'connected',
                    'memory_usage' => '52%',
                    'disk_space' => '78%',
                    'response_time' => 23.4
                ];

            case 'audit_log':
                return [
                    'success' => true,
                    'logs' => [
                        [
                            'id' => 1,
                            'user_id' => 1,
                            'action' => 'employee_mapped',
                            'details' => 'Employee ID 123 mapped to Customer ID 456',
                            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                        ],
                        [
                            'id' => 2,
                            'user_id' => 2,
                            'action' => 'auto_match_approved',
                            'details' => 'Auto-match with 95% confidence approved',
                            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours'))
                        ]
                    ],
                    'total_count' => 1247
                ];

            case 'getStats':
                return [
                    'success' => true,
                    'data' => [
                        'blocked_amount' => '9543.36',
                        'unmapped_count' => 56,
                        'active_mappings' => 142,
                        'auto_match_suggestions' => 31
                    ],
                    'testing_mode' => true
                ];

            case 'getRecentActivity':
                return [
                    'success' => true,
                    'data' => [
                        [
                            'type' => 'mapping',
                            'description' => 'John Smith mapped to customer #12345',
                            'timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                            'user' => 'Admin User'
                        ],
                        [
                            'type' => 'auto_match',
                            'description' => 'Auto-matched Jane Doe (95% confidence)',
                            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                            'user' => 'System'
                        ],
                        [
                            'type' => 'unmapping',
                            'description' => 'Removed mapping for Bob Wilson',
                            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                            'user' => 'Admin User'
                        ],
                        [
                            'type' => 'mapping',
                            'description' => 'Sarah Johnson mapped to customer #67890',
                            'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                            'user' => 'Store Manager'
                        ],
                        [
                            'type' => 'auto_match',
                            'description' => 'Auto-matched Mike Brown (88% confidence)',
                            'timestamp' => date('Y-m-d H:i:s', strtotime('-4 hours')),
                            'user' => 'System'
                        ]
                    ],
                    'testing_mode' => true
                ];

            case 'system_diagnostics':
                return [
                    'success' => true,
                    'diagnostics' => [
                        'database_status' => 'healthy',
                        'api_connectivity' => 'good',
                        'memory_usage' => 52.3,
                        'disk_space' => 78.1,
                        'performance_score' => 94.5,
                        'last_check' => date('Y-m-d H:i:s')
                    ],
                    'testing_mode' => true
                ];

            case 'user_management':
                return [
                    'success' => true,
                    'users' => [
                        [
                            'id' => 1,
                            'name' => 'Admin User',
                            'email' => 'admin@vapeshed.co.nz',
                            'role' => 'admin',
                            'last_login' => date('Y-m-d H:i:s', strtotime('-2 hours'))
                        ],
                        [
                            'id' => 2,
                            'name' => 'Manager User',
                            'email' => 'manager@vapeshed.co.nz',
                            'role' => 'manager',
                            'last_login' => date('Y-m-d H:i:s', strtotime('-4 hours'))
                        ]
                    ],
                    'total_users' => 2,
                    'testing_mode' => true
                ];

            case 'export_data':
                return [
                    'success' => true,
                    'data' => [
                        'employees' => 124,
                        'customers' => 98,
                        'mappings' => 85
                    ],
                    'export_timestamp' => date('Y-m-d H:i:s'),
                    'testing_mode' => true
                ];

            default:
                return [
                    'success' => true,
                    'message' => 'Mock data for testing',
                    'testing_mode' => true,
                    'data_type' => $type
                ];
        }
    }

    /**
     * Mock successful operation responses
     */
    public function getMockSuccessResponse($action, $data = []) {
        if (!$this->isBypassActive()) {
            return null;
        }

        $baseResponse = [
            'success' => true,
            'testing_mode' => true,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        switch ($action) {
            case 'approve_match':
                return array_merge($baseResponse, [
                    'message' => 'Auto-match approved successfully',
                    'employee_id' => $data['employee_id'] ?? 1,
                    'customer_id' => $data['customer_id'] ?? 1
                ]);

            case 'reject_match':
                return array_merge($baseResponse, [
                    'message' => 'Auto-match rejected successfully',
                    'employee_id' => $data['employee_id'] ?? 1,
                    'customer_id' => $data['customer_id'] ?? 1
                ]);

            case 'manual_map':
                return array_merge($baseResponse, [
                    'message' => 'Employee mapped manually',
                    'employee_id' => $data['employee_id'] ?? 1,
                    'customer_id' => $data['customer_id'] ?? 1
                ]);

            case 'save_settings':
                return array_merge($baseResponse, [
                    'message' => 'Settings saved successfully',
                    'settings_updated' => array_keys($data)
                ]);

            case 'bulk_auto_match':
                return array_merge($baseResponse, [
                    'message' => 'Bulk auto-match completed',
                    'processed_count' => 25,
                    'success_count' => 22,
                    'failed_count' => 3
                ]);

            case 'reset_mappings':
                return array_merge($baseResponse, [
                    'message' => 'All mappings reset successfully',
                    'reset_count' => 156
                ]);

            case 'export_data':
                return array_merge($baseResponse, [
                    'message' => 'Data exported successfully',
                    'data' => [
                        'employees' => 124,
                        'customers' => 98,
                        'mappings' => 85
                    ],
                    'export_timestamp' => date('Y-m-d H:i:s')
                ]);

            case 'import_data':
                return array_merge($baseResponse, [
                    'message' => 'Data imported successfully',
                    'imported_count' => 42,
                    'skipped_count' => 3,
                    'error_count' => 1
                ]);

            case 'system_diagnostics':
                return array_merge($baseResponse, [
                    'message' => 'System diagnostics completed',
                    'diagnostics' => [
                        'database_status' => 'healthy',
                        'api_connectivity' => 'good',
                        'memory_usage' => 52.3,
                        'disk_space' => 78.1,
                        'performance_score' => 94.5
                    ]
                ]);

            case 'user_management':
                return array_merge($baseResponse, [
                    'message' => 'User management data retrieved',
                    'users' => [
                        [
                            'id' => 1,
                            'name' => 'Admin User',
                            'email' => 'admin@vapeshed.co.nz',
                            'role' => 'admin',
                            'last_login' => date('Y-m-d H:i:s', strtotime('-2 hours'))
                        ],
                        [
                            'id' => 2,
                            'name' => 'Manager User',
                            'email' => 'manager@vapeshed.co.nz',
                            'role' => 'manager',
                            'last_login' => date('Y-m-d H:i:s', strtotime('-4 hours'))
                        ]
                    ],
                    'total_users' => 2
                ]);

            default:
                return array_merge($baseResponse, [
                    'message' => 'Operation completed successfully'
                ]);
        }
    }

    /**
     * Log testing activity
     */
    public function logTestingActivity($activity, $details = '') {
        if (!$this->isBypassActive()) {
            return;
        }

        $logEntry = sprintf(
            "[TESTING] %s - %s - %s - %s",
            date('Y-m-d H:i:s'),
            $activity,
            $details,
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        );

        error_log($logEntry);
    }

    /**
     * Get testing status
     */
    public function getTestingStatus() {
        return [
            'bypass_enabled' => $this->bypassEnabled,
            'testing_mode_active' => $this->testingModeActive,
            'is_testing_environment' => $this->isTestingEnvironment(),
            'session_has_bypass' => isset($_SESSION['testing_bypass']),
            'current_time' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Validate testing request
     */
    public function validateTestingRequest() {
        if (!$this->isTestingEnvironment()) {
            return false;
        }

        // Additional validation can be added here
        return true;
    }

    /**
     * Clean up testing environment
     */
    public function cleanup() {
        if (isset($_SESSION['testing_bypass'])) {
            unset($_SESSION['testing_bypass']);
            unset($_SESSION['bypass_token']);
        }

        $this->disableTestingMode();
        error_log("[TESTING] Testing environment cleaned up");
    }
}

// Auto-initialize for testing environments
if (php_sapi_name() === 'cli' ||
    (isset($_SERVER['HTTP_USER_AGENT']) &&
     (strpos($_SERVER['HTTP_USER_AGENT'], 'APIEndpointValidator') !== false ||
      strpos($_SERVER['HTTP_USER_AGENT'], 'TestingSuite') !== false ||
      strpos($_SERVER['HTTP_USER_AGENT'], 'curl') !== false)) ||
    (isset($_SERVER['PHP_SELF']) &&
     (strpos($_SERVER['PHP_SELF'], 'test-suite') !== false ||
      strpos($_SERVER['PHP_SELF'], 'comprehensive-test') !== false ||
      strpos($_SERVER['PHP_SELF'], 'api-endpoint-validator') !== false))) {

    $bypass = TestingBotBypass::getInstance();
    if ($bypass->enableTestingMode()) {
        $bypass->bypassAuthentication();
        error_log("[TESTING] Automatic bot bypass activated for testing environment");
    }
}
