<?php

/**
 * Behavioral Fraud Detection API
 *
 * Orchestrates the complete behavioral fraud detection workflow:
 * 1. Analyzes staff behavioral patterns from all data sources
 * 2. Calculates risk scores and identifies anomalies
 * 3. Activates dynamic camera targeting for high-risk individuals
 * 4. Provides real-time dashboards and management alerts
 *
 * Endpoints:
 * - POST /api/fraud-detection/analyze (Run behavioral analysis)
 * - GET /api/fraud-detection/dashboard (Get dashboard data)
 * - GET /api/fraud-detection/staff/{id}/profile (Get staff profile)
 * - POST /api/fraud-detection/targeting/{id}/activate (Manual targeting)
 * - POST /api/fraud-detection/targeting/{id}/deactivate (Stop targeting)
 * - GET /api/fraud-detection/alerts (Get current alerts)
 *
 * @package FraudDetection
 * @version 1.0.0
 */

require_once __DIR__ . '/../fraud-detection/BehavioralAnalyticsEngine.php';
require_once __DIR__ . '/../fraud-detection/DynamicCameraTargetingSystem.php';
require_once __DIR__ . '/../fraud-detection/RealTimeAlertDashboard.php';

use FraudDetection\BehavioralAnalyticsEngine;
use FraudDetection\DynamicCameraTargetingSystem;
use FraudDetection\RealTimeAlertDashboard;
use PDO;

class BehavioralFraudDetectionAPI
{
    private PDO $pdo;
    private BehavioralAnalyticsEngine $analytics;
    private DynamicCameraTargetingSystem $targeting;
    private RealTimeAlertDashboard $dashboard;
    private array $response = [];
    private int $httpCode = 200;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->analytics = new BehavioralAnalyticsEngine($pdo);
        $this->targeting = new DynamicCameraTargetingSystem($pdo);
        $this->dashboard = new RealTimeAlertDashboard($pdo);
    }

    /**
     * Route API request
     */
    public function route(string $method, string $endpoint, array $params = []): void
    {
        try {
            match ($endpoint) {
                'analyze' => $this->analyze($params),
                'dashboard' => $this->getDashboard($params),
                'alerts' => $this->getAlerts($params),
                'staff-profile' => $this->getStaffProfile($params),
                'targeting-activate' => $this->activateTargeting($params),
                'targeting-deactivate' => $this->deactivateTargeting($params),
                'targeting-history' => $this->getTargetingHistory($params),
                'export-report' => $this->exportReport($params),
                default => $this->error("Unknown endpoint: $endpoint", 404),
            };
        } catch (Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Run behavioral analysis for all staff or specific staff member
     */
    private function analyze(array $params): void
    {
        $staffId = $params['staff_id'] ?? null;
        $storeId = $params['store_id'] ?? null;
        $timeWindow = $params['time_window'] ?? 'daily';

        try {
            if ($staffId) {
                // Analyze single staff member
                $analysis = $this->analytics->analyzeStaffMember($staffId, $timeWindow);

                // Save results
                $this->analytics->saveAnalysisResults($analysis);

                // Check if should activate camera targeting
                if ($analysis['should_target_cameras']) {
                    $this->targeting->activateTargeting($analysis);
                    $analysis['camera_targeting_activated'] = true;
                }

                $this->response = [
                    'success' => true,
                    'message' => 'Analysis complete',
                    'analysis' => $analysis,
                ];
            } else {
                // Analyze all staff
                $results = $this->analytics->analyzeAllStaff($timeWindow);

                // Save and target
                $targetedCount = 0;
                foreach ($results as $analysis) {
                    $this->analytics->saveAnalysisResults($analysis);
                    if ($analysis['should_target_cameras']) {
                        $this->targeting->activateTargeting($analysis);
                        $targetedCount++;
                    }
                }

                $this->response = [
                    'success' => true,
                    'message' => 'Analysis complete for all staff',
                    'time_window' => $timeWindow,
                    'total_analyzed' => count($results),
                    'targeted_count' => $targetedCount,
                    'flagged_staff' => array_slice($results, 0, 10), // Top 10 most risky
                ];
            }
        } catch (Exception $e) {
            $this->error("Analysis failed: " . $e->getMessage(), 400);
        }
    }

    /**
     * Get real-time dashboard data
     */
    private function getDashboard(array $params): void
    {
        try {
            $storeId = $params['store_id'] ?? null;
            $data = $this->dashboard->getDashboardData($storeId);

            $this->response = [
                'success' => true,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => $data,
            ];
        } catch (Exception $e) {
            $this->error("Dashboard load failed: " . $e->getMessage(), 400);
        }
    }

    /**
     * Get current high-risk alerts
     */
    private function getAlerts(array $params): void
    {
        try {
            $storeId = $params['store_id'] ?? null;
            $severity = $params['severity'] ?? null; // 'CRITICAL' | 'HIGH' | etc.

            // Get critical and high alerts from dashboard
            $dashboardData = $this->dashboard->getDashboardData($storeId);
            $alerts = $dashboardData['critical_alerts'];

            // Filter by severity if specified
            if ($severity) {
                $alerts = array_filter($alerts, fn($a) => $a['risk_level'] === $severity);
            }

            $this->response = [
                'success' => true,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'alert_count' => count($alerts),
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->error("Failed to get alerts: " . $e->getMessage(), 400);
        }
    }

    /**
     * Get detailed staff profile
     */
    private function getStaffProfile(array $params): void
    {
        $staffId = $params['staff_id'] ?? null;

        if (!$staffId) {
            $this->error("Missing staff_id parameter", 400);
            return;
        }

        try {
            $profile = $this->dashboard->getStaffProfile($staffId);

            $this->response = [
                'success' => true,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'staff_profile' => $profile,
            ];
        } catch (Exception $e) {
            $this->error("Failed to get staff profile: " . $e->getMessage(), 400);
        }
    }

    /**
     * Manually activate camera targeting for staff member
     */
    private function activateTargeting(array $params): void
    {
        $staffId = $params['staff_id'] ?? null;

        if (!$staffId) {
            $this->error("Missing staff_id parameter", 400);
            return;
        }

        try {
            // Get current analysis or create manual targeting
            $analysis = $this->analytics->analyzeStaffMember($staffId, 'daily');

            // Override risk check for manual activation
            $analysis['should_target_cameras'] = true;
            $analysis['manual_activation'] = true;

            $this->targeting->activateTargeting($analysis);

            $this->response = [
                'success' => true,
                'message' => 'Camera targeting activated',
                'staff_id' => $staffId,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            $this->error("Failed to activate targeting: " . $e->getMessage(), 400);
        }
    }

    /**
     * Deactivate camera targeting for staff member
     */
    private function deactivateTargeting(array $params): void
    {
        $staffId = $params['staff_id'] ?? null;

        if (!$staffId) {
            $this->error("Missing staff_id parameter", 400);
            return;
        }

        try {
            $this->targeting->deactivateTargeting($staffId);

            $this->response = [
                'success' => true,
                'message' => 'Camera targeting deactivated',
                'staff_id' => $staffId,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            $this->error("Failed to deactivate targeting: " . $e->getMessage(), 400);
        }
    }

    /**
     * Get targeting history for staff member
     */
    private function getTargetingHistory(array $params): void
    {
        $staffId = $params['staff_id'] ?? null;
        $days = $params['days'] ?? 30;

        if (!$staffId) {
            $this->error("Missing staff_id parameter", 400);
            return;
        }

        try {
            $history = $this->targeting->getTargetingHistory($staffId, $days);

            $this->response = [
                'success' => true,
                'staff_id' => $staffId,
                'period_days' => $days,
                'total_targeting_events' => count($history),
                'history' => $history,
            ];
        } catch (Exception $e) {
            $this->error("Failed to get targeting history: " . $e->getMessage(), 400);
        }
    }

    /**
     * Export incident report
     */
    private function exportReport(array $params): void
    {
        $staffId = $params['staff_id'] ?? null;
        $format = $params['format'] ?? 'pdf'; // pdf, csv, json

        if (!$staffId) {
            $this->error("Missing staff_id parameter", 400);
            return;
        }

        try {
            // Get comprehensive profile
            $profile = $this->dashboard->getStaffProfile($staffId);

            // Format based on request
            switch ($format) {
                case 'json':
                    $this->response = [
                        'success' => true,
                        'format' => 'json',
                        'data' => $profile,
                    ];
                    break;

                case 'csv':
                    $this->response = [
                        'success' => true,
                        'format' => 'csv',
                        'filename' => "fraud_report_staff_{$staffId}_" . date('Y-m-d') . ".csv",
                        'download_url' => "/fraud-detection/download/report_{$staffId}.csv",
                    ];
                    break;

                case 'pdf':
                default:
                    $this->response = [
                        'success' => true,
                        'format' => 'pdf',
                        'filename' => "fraud_report_staff_{$staffId}_" . date('Y-m-d') . ".pdf",
                        'download_url' => "/fraud-detection/download/report_{$staffId}.pdf",
                    ];
                    break;
            }
        } catch (Exception $e) {
            $this->error("Failed to export report: " . $e->getMessage(), 400);
        }
    }

    /**
     * Send JSON response
     */
    public function sendResponse(): void
    {
        header('Content-Type: application/json');
        header('HTTP/1.1 ' . $this->httpCode . ' ' . $this->getHttpCodeMessage($this->httpCode));
        echo json_encode($this->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Record error response
     */
    private function error(string $message, int $code = 500): void
    {
        $this->httpCode = $code;
        $this->response = [
            'success' => false,
            'error' => $message,
            'code' => $code,
        ];
    }

    /**
     * Helper: Get HTTP message
     */
    private function getHttpCodeMessage(int $code): string
    {
        $messages = [
            200 => 'OK',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
        ];
        return $messages[$code] ?? 'Unknown';
    }
}

// API ENDPOINT HANDLER
if (php_sapi_name() === 'cli') {
    die("This is an HTTP endpoint, not a CLI script.\n");
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Extract endpoint from path
$pathParts = explode('/', trim($path, '/'));
$endpoint = end($pathParts);

// Get parameters
$params = match ($method) {
    'GET' => $_GET,
    'POST' => json_decode(file_get_contents('php://input'), true) ?? $_POST,
    default => [],
};

// Initialize and route
try {
    // Get database connection (assumes shared CIS DB connection)
    // This would come from your bootstrap/config
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_NAME', 'cis'),
        env('DB_USER', 'root'),
        env('DB_PASS', '')
    );

    $api = new BehavioralFraudDetectionAPI($pdo);
    $api->route($method, $endpoint, $params);
    $api->sendResponse();
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'System error: ' . $e->getMessage(),
    ]);
}

function env($key, $default = null)
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}
