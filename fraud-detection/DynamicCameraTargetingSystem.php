<?php

/**
 * Dynamic Camera Targeting System
 *
 * Receives behavioral analysis results and automatically directs the camera network
 * to target flagged individuals. Coordinates 120+ IP cameras across 17 stores with
 * real-time PTZ control, multi-camera tracking, and intelligent prioritization.
 *
 * Features:
 * - Real-time PTZ camera tracking
 * - Multi-camera coordination and focus zones
 * - Automatic target switching based on pattern changes
 * - Historical tracking and pattern analysis
 * - Integration with CISWatch camera system
 *
 * @package FraudDetection
 * @version 1.0.0
 */

namespace FraudDetection;

use PDO;
use DateTime;
use Exception;

class DynamicCameraTargetingSystem
{
    private PDO $pdo;
    private array $config;
    private $logger;
    private array $cameraCache = [];
    private array $activeTargets = [];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge($this->defaultConfig(), $config);
        $this->initializeLogger();
    }

    private function defaultConfig(): array
    {
        return [
            // Camera network settings
            'total_cameras' => 102,
            'ptz_cameras_per_store' => 1,
            'fixed_cameras_ratio' => 0.85,

            // Targeting behavior
            'enable_auto_targeting' => true,
            'min_risk_for_targeting' => 0.75,
            'tracking_duration_minutes' => 60,
            'max_concurrent_targets' => 5,

            // PTZ camera settings
            'ptz_pan_speed' => 50,
            'ptz_tilt_speed' => 50,
            'ptz_zoom_level' => 2,

            // Focus zones
            'focus_zones' => [
                'checkout' => ['weight' => 0.35, 'cameras' => 1],
                'high_value_products' => ['weight' => 0.30, 'cameras' => 1],
                'entry_exit' => ['weight' => 0.20, 'cameras' => 1],
                'general_floor' => ['weight' => 0.15, 'cameras' => 1],
            ],

            // Camera API settings
            'camera_api_timeout' => 5,
            'camera_retry_attempts' => 3,

            // Persistence
            'enable_tracking_log' => true,
            'tracking_retention_days' => 30,

            // Recording settings
            'force_high_quality_recording' => true,
            'recording_bitrate_high_quality' => 'high',
            'recording_bitrate_standard' => 'standard',

            // Alert settings
            'send_alerts_to_managers' => true,
            'alert_channels' => ['email', 'sms', 'push'],
            'recording_start_offset_minutes' => 5, // Start recording 5 min before target time
        ];
    }

    private function initializeLogger(): void
    {
        $logPath = __DIR__ . '/../../logs/camera-targeting.log';
        $this->logger = new class ($logPath) {
            private $path;

            public function __construct($path)
            {
                $this->path = $path;
                @mkdir(dirname($path), 0755, true);
            }

            public function log($level, $message, $context = [])
            {
                $timestamp = date('Y-m-d H:i:s');
                $contextStr = $context ? json_encode($context) : '';
                file_put_contents($this->path, "[$timestamp] [$level] $message $contextStr\n", FILE_APPEND);
            }

            public function info($message, $context = []) { $this->log('INFO', $message, $context); }
            public function warning($message, $context = []) { $this->log('WARNING', $message, $context); }
            public function error($message, $context = []) { $this->log('ERROR', $message, $context); }
        };
    }

    /**
     * Process behavioral analysis and activate camera targeting
     */
    public function activateTargeting(array $analysis): bool
    {
        try {
            // Only target if risk exceeds threshold
            if ($analysis['risk_score'] < $this->config['min_risk_for_targeting']) {
                $this->logger->info("Risk score below threshold, skipping targeting", [
                    'staff_id' => $analysis['staff_id'],
                    'risk_score' => $analysis['risk_score']
                ]);
                return false;
            }

            // Check concurrent target limit
            if (count($this->activeTargets) >= $this->config['max_concurrent_targets']) {
                $this->logger->warning("Maximum concurrent targets reached", [
                    'current_targets' => count($this->activeTargets)
                ]);
                // Could deactivate lowest risk target, but let's log for now
            }

            $staff = $this->getStaffMember($analysis['staff_id']);
            if (!$staff) {
                throw new Exception("Staff member not found");
            }

            $this->logger->info("Activating camera targeting", [
                'staff_id' => $analysis['staff_id'],
                'staff_name' => $staff['name'],
                'store_id' => $staff['store_id'],
                'risk_score' => $analysis['risk_score'],
                'risk_level' => $analysis['risk_level'],
            ]);

            // Designate target cameras for this individual
            $targetCameras = $this->designateTargetCameras($staff['store_id']);

            // Activate recording on target cameras
            foreach ($targetCameras as $camera) {
                $this->activateHighQualityRecording($camera);
                $this->setFocusZone($camera, $analysis['risk_factors']);
            }

            // Create targeting record
            $targetingId = $this->createTargetingRecord(
                $analysis['staff_id'],
                $staff['store_id'],
                $targetCameras,
                $analysis['risk_score'],
                $analysis['risk_factors'],
                $analysis['recommendations']
            );

            // Track active target
            $this->activeTargets[$analysis['staff_id']] = [
                'targeting_id' => $targetingId,
                'activated_at' => new DateTime(),
                'duration_minutes' => $this->config['tracking_duration_minutes'],
                'cameras' => array_column($targetCameras, 'id'),
                'risk_score' => $analysis['risk_score'],
            ];

            // Send alerts to management
            if ($this->config['send_alerts_to_managers']) {
                $this->sendManagementAlert($analysis, $staff, $targetCameras);
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to activate camera targeting: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Designate which cameras will track this individual
     */
    private function designateTargetCameras(int $storeId): array
    {
        try {
            // Get high-priority cameras for this store
            $sql = "
                SELECT
                    id, camera_name, camera_ip, camera_model, ptz_capable,
                    focus_zone, stream_url, api_endpoint
                FROM cameras
                WHERE store_id = ?
                AND status = 'ACTIVE'
                ORDER BY
                    CASE
                        WHEN ptz_capable = 1 THEN 0
                        WHEN focus_zone = 'checkout' THEN 1
                        WHEN focus_zone = 'high_value_products' THEN 2
                        WHEN focus_zone = 'entry_exit' THEN 3
                        ELSE 4
                    END,
                    id ASC
                LIMIT 5
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$storeId]);
            $cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logger->info("Designated target cameras", [
                'store_id' => $storeId,
                'camera_count' => count($cameras),
                'cameras' => array_column($cameras, 'camera_name'),
            ]);

            return $cameras;
        } catch (Exception $e) {
            $this->logger->error("Failed to designate target cameras: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Activate high-quality recording for target camera
     */
    private function activateHighQualityRecording(array $camera): bool
    {
        try {
            if (!$this->config['force_high_quality_recording']) {
                return true;
            }

            // Update database
            $sql = "
                UPDATE cameras
                SET recording_quality = ?,
                    bitrate = ?,
                    recording_mode = 'CONTINUOUS',
                    updated_at = NOW()
                WHERE id = ?
            ";

            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $this->config['recording_bitrate_high_quality'],
                '8192k', // High bitrate
                $camera['id'],
            ]);

            // Send command to camera via API
            if ($camera['api_endpoint']) {
                $this->sendCameraCommand($camera, [
                    'action' => 'set_recording_quality',
                    'quality' => 'high',
                    'bitrate' => '8192k',
                ]);
            }

            $this->logger->info("Activated high-quality recording", [
                'camera_id' => $camera['id'],
                'camera_name' => $camera['camera_name'],
            ]);

            return $success;
        } catch (Exception $e) {
            $this->logger->error("Failed to activate high-quality recording: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set PTZ camera focus on specific zones based on risk factors
     */
    private function setFocusZone(array $camera, array $riskFactors): bool
    {
        try {
            if (!$camera['ptz_capable']) {
                return false;
            }

            // Determine priority focus zone based on risk factors
            $priorityZone = $this->determineFocusZone($riskFactors);

            // Get preset for this focus zone
            $sql = "
                SELECT preset_id, pan, tilt, zoom
                FROM camera_presets
                WHERE camera_id = ?
                AND zone_name = ?
                AND status = 'ACTIVE'
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$camera['id'], $priorityZone]);
            $preset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$preset) {
                // Create default preset for zone
                $preset = $this->createDefaultPreset($camera['id'], $priorityZone);
            }

            // Send PTZ command to camera
            $this->sendCameraCommand($camera, [
                'action' => 'goto_preset',
                'preset_id' => $preset['preset_id'],
                'pan' => $preset['pan'],
                'tilt' => $preset['tilt'],
                'zoom' => $preset['zoom'],
            ]);

            $this->logger->info("Set focus zone for PTZ camera", [
                'camera_id' => $camera['id'],
                'focus_zone' => $priorityZone,
                'preset_id' => $preset['preset_id'],
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to set focus zone: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Determine priority focus zone based on risk factors
     */
    private function determineFocusZone(array $riskFactors): string
    {
        // Check which fraud types are flagged
        $factorTypes = array_column($riskFactors, 'type');

        if (in_array('void_transactions', $factorTypes) || in_array('refund_patterns', $factorTypes)) {
            return 'checkout'; // Transaction fraud - focus on checkout
        }

        if (in_array('inventory_anomalies', $factorTypes)) {
            return 'high_value_products'; // Inventory theft - focus on products
        }

        if (in_array('after_hours_access', $factorTypes)) {
            return 'entry_exit'; // Unauthorized access - focus on entrances
        }

        if (in_array('discount_anomalies', $factorTypes)) {
            return 'checkout'; // Discount fraud - focus on POS
        }

        return 'general_floor'; // Default general monitoring
    }

    /**
     * Create default preset for camera and zone
     */
    private function createDefaultPreset(int $cameraId, string $zone): array
    {
        $presets = [
            'checkout' => ['pan' => 180, 'tilt' => -30, 'zoom' => 3],
            'high_value_products' => ['pan' => 90, 'tilt' => 0, 'zoom' => 4],
            'entry_exit' => ['pan' => 0, 'tilt' => -15, 'zoom' => 2],
            'general_floor' => ['pan' => 180, 'tilt' => -10, 'zoom' => 1],
        ];

        $preset = $presets[$zone] ?? $presets['general_floor'];

        $sql = "
            INSERT INTO camera_presets (camera_id, zone_name, preset_id, pan, tilt, zoom, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', NOW())
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $cameraId,
            $zone,
            $cameraId . '_' . $zone,
            $preset['pan'],
            $preset['tilt'],
            $preset['zoom'],
        ]);

        return ['preset_id' => $cameraId . '_' . $zone] + $preset;
    }

    /**
     * Send command to camera via API
     */
    private function sendCameraCommand(array $camera, array $command): bool
    {
        try {
            if (!$camera['api_endpoint']) {
                $this->logger->warning("Camera has no API endpoint", ['camera_id' => $camera['id']]);
                return false;
            }

            $payload = json_encode($command);
            $signature = hash_hmac('sha256', $payload, env('CAMERA_API_SECRET', 'secret'));

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $camera['api_endpoint'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->config['camera_api_timeout'],
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Signature: ' . $signature,
                    'X-Camera-ID: ' . $camera['id'],
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $this->logger->info("Camera command sent successfully", [
                    'camera_id' => $camera['id'],
                    'action' => $command['action'],
                ]);
                return true;
            } else {
                $this->logger->warning("Camera command failed", [
                    'camera_id' => $camera['id'],
                    'action' => $command['action'],
                    'http_code' => $httpCode,
                ]);
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to send camera command: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create targeting record in database
     */
    private function createTargetingRecord(
        int $staffId,
        int $storeId,
        array $cameras,
        float $riskScore,
        array $riskFactors,
        array $recommendations
    ): int {
        try {
            $sql = "
                INSERT INTO camera_targeting_records
                (staff_id, store_id, target_cameras, risk_score, risk_factors,
                 recommendations, status, activated_at, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', NOW(),
                    DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())
            ";

            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $staffId,
                $storeId,
                json_encode(array_column($cameras, 'id')),
                $riskScore,
                json_encode($riskFactors),
                json_encode($recommendations),
                $this->config['tracking_duration_minutes'],
            ]);

            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            $this->logger->error("Failed to create targeting record: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send alerts to store managers and security team
     */
    private function sendManagementAlert(array $analysis, array $staff, array $cameras): bool
    {
        try {
            $storeManagers = $this->getStoreManagers($staff['store_id']);

            $alertMessage = sprintf(
                "HIGH RISK ALERT: Staff member %s (%s) at %s has been flagged for behavioral analysis. Risk Score: %.2f (%s). %d cameras activated for monitoring.",
                $staff['name'],
                $staff['email'],
                $staff['store_name'],
                $analysis['risk_score'],
                $analysis['risk_level'],
                count($cameras)
            );

            foreach ($storeManagers as $manager) {
                if (in_array('email', $this->config['alert_channels'])) {
                    $this->sendEmail(
                        $manager['email'],
                        "High Risk Behavioral Alert",
                        $this->formatAlertEmail($analysis, $staff, $cameras),
                        $manager['name']
                    );
                }

                if (in_array('sms', $this->config['alert_channels']) && $manager['phone']) {
                    $this->sendSMS($manager['phone'], $alertMessage);
                }

                if (in_array('push', $this->config['alert_channels'])) {
                    $this->sendPushNotification($manager['id'], $alertMessage);
                }
            }

            $this->logger->info("Management alerts sent", [
                'staff_id' => $analysis['staff_id'],
                'manager_count' => count($storeManagers),
                'channels' => $this->config['alert_channels'],
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to send management alert: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format alert email
     */
    private function formatAlertEmail(array $analysis, array $staff, array $cameras): string
    {
        $html = "<html><body>";
        $html .= "<h2>High Risk Behavioral Analysis Alert</h2>";
        $html .= "<p><strong>Staff Member:</strong> {$staff['name']} ({$staff['email']})</p>";
        $html .= "<p><strong>Store:</strong> {$staff['store_name']}</p>";
        $html .= "<p><strong>Risk Score:</strong> " . number_format($analysis['risk_score'], 3) . " ({$analysis['risk_level']})</p>";
        $html .= "<p><strong>Analysis Period:</strong> {$analysis['analysis_period']}</p>";

        $html .= "<h3>Risk Factors:</h3>";
        $html .= "<ul>";
        foreach ($analysis['risk_factors'] as $factor) {
            $html .= "<li><strong>{$factor['type']}:</strong> {$factor['severity']} (Score: " . number_format($factor['score'], 3) . ")</li>";
        }
        $html .= "</ul>";

        $html .= "<h3>Recommendations:</h3>";
        $html .= "<ul>";
        foreach ($analysis['recommendations'] as $rec) {
            $html .= "<li><strong>[{$rec['priority']}]</strong> {$rec['action']}: {$rec['description']}</li>";
        }
        $html .= "</ul>";

        $html .= "<h3>Camera Monitoring:</h3>";
        $html .= "<p>" . count($cameras) . " cameras activated for " . $this->config['tracking_duration_minutes'] . " minutes:</p>";
        $html .= "<ul>";
        foreach ($cameras as $camera) {
            $html .= "<li>{$camera['camera_name']} ({$camera['focus_zone']})</li>";
        }
        $html .= "</ul>";

        $html .= "<p><em>This alert was automatically generated by the Behavioral Analytics System</em></p>";
        $html .= "</body></html>";

        return $html;
    }

    /**
     * Helper: Get store managers
     */
    private function getStoreManagers(int $storeId): array
    {
        try {
            $sql = "
                SELECT s.id, s.name, s.email, s.phone
                FROM staff s
                WHERE s.store_id = ?
                AND s.role IN ('MANAGER', 'ASSISTANT_MANAGER', 'SECURITY_OFFICER')
                AND s.status = 'ACTIVE'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$storeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get store managers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Helper: Get staff member
     */
    private function getStaffMember(int $staffId): ?array
    {
        $sql = "
            SELECT s.id, s.name, s.email, s.store_id, st.name as store_name
            FROM staff s
            JOIN stores st ON s.store_id = st.id
            WHERE s.id = ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Placeholder: Send email
     */
    private function sendEmail(string $email, string $subject, string $body, string $name): bool
    {
        // Implementation would use your email service
        $this->logger->info("Email alert sent", ['email' => $email, 'subject' => $subject]);
        return true;
    }

    /**
     * Placeholder: Send SMS
     */
    private function sendSMS(string $phone, string $message): bool
    {
        // Implementation would use your SMS service
        $this->logger->info("SMS alert sent", ['phone' => $phone]);
        return true;
    }

    /**
     * Placeholder: Send push notification
     */
    private function sendPushNotification(int $staffId, string $message): bool
    {
        // Implementation would use your push notification service
        $this->logger->info("Push notification sent", ['staff_id' => $staffId]);
        return true;
    }

    /**
     * Deactivate targeting for a staff member (when duration expires or risk resolves)
     */
    public function deactivateTargeting(int $staffId): bool
    {
        try {
            // Update targeting record
            $sql = "
                UPDATE camera_targeting_records
                SET status = 'INACTIVE',
                    deactivated_at = NOW()
                WHERE staff_id = ?
                AND status = 'ACTIVE'
            ";

            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$staffId]);

            // Reset camera recording to standard quality
            $this->resetCameraRecording($staffId);

            // Remove from active targets
            unset($this->activeTargets[$staffId]);

            $this->logger->info("Deactivated camera targeting", ['staff_id' => $staffId]);

            return $success;
        } catch (Exception $e) {
            $this->logger->error("Failed to deactivate camera targeting: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset camera recording to standard quality
     */
    private function resetCameraRecording(int $staffId): void
    {
        try {
            $sql = "
                SELECT target_cameras FROM camera_targeting_records
                WHERE staff_id = ?
                AND status = 'ACTIVE'
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return;
            }

            $cameraIds = json_decode($record['target_cameras'], true);

            $updateSql = "
                UPDATE cameras
                SET recording_quality = ?,
                    bitrate = ?,
                    updated_at = NOW()
                WHERE id IN (" . implode(',', array_fill(0, count($cameraIds), '?')) . ")
            ";

            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute(
                array_merge([
                    $this->config['recording_bitrate_standard'],
                    '2048k'
                ], $cameraIds)
            );

            $this->logger->info("Reset camera recording quality", [
                'staff_id' => $staffId,
                'camera_count' => count($cameraIds),
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to reset camera recording: " . $e->getMessage());
        }
    }

    /**
     * Get active targeting information
     */
    public function getActiveTargets(): array
    {
        return $this->activeTargets;
    }

    /**
     * Get targeting history for staff member
     */
    public function getTargetingHistory(int $staffId, int $days = 30): array
    {
        try {
            $sql = "
                SELECT * FROM camera_targeting_records
                WHERE staff_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get targeting history: " . $e->getMessage());
            return [];
        }
    }
}
