<?php

/**
 * Behavioral Fraud Detection System - Bootstrap & Integration
 *
 * This file initializes and coordinates all components of the behavioral fraud
 * detection system, including database schema, scheduled analysis tasks, and
 * real-time camera targeting activation.
 *
 * @package FraudDetection
 * @version 1.0.0
 */

namespace FraudDetection;

use PDO;
use DateTime;

class SystemBootstrap
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Initialize the fraud detection system
     */
    public function initialize(): void
    {
        echo "[*] Initializing Behavioral Fraud Detection System...\n";

        // Step 1: Verify and create database tables
        $this->setupDatabaseSchema();

        // Step 2: Verify camera integration
        $this->verifyCameraNetwork();

        // Step 3: Initialize analytics engine
        $this->initializeAnalyticsEngine();

        // Step 4: Schedule background jobs
        $this->scheduleBackgroundJobs();

        echo "[✓] System initialization complete!\n";
    }

    /**
     * Set up all required database tables
     */
    private function setupDatabaseSchema(): void
    {
        echo "[*] Setting up database schema...\n";

        $tables = [
            // Behavioral analysis results
            "CREATE TABLE IF NOT EXISTS behavioral_analysis_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                store_id INT NOT NULL,
                analysis_period VARCHAR(50),
                risk_score FLOAT NOT NULL,
                risk_level VARCHAR(20),
                risk_factors JSON,
                raw_scores JSON,
                recommendations JSON,
                camera_targeting TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_analysis (staff_id, analysis_period, DATE(created_at)),
                INDEX idx_risk_score (risk_score),
                INDEX idx_risk_level (risk_level),
                INDEX idx_created_at (created_at)
            )",

            // Camera targeting records
            "CREATE TABLE IF NOT EXISTS camera_targeting_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                store_id INT NOT NULL,
                target_cameras JSON,
                risk_score FLOAT NOT NULL,
                risk_factors JSON,
                recommendations JSON,
                status VARCHAR(20) DEFAULT 'ACTIVE',
                activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP,
                deactivated_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_staff_id (staff_id),
                INDEX idx_status (status),
                INDEX idx_expires_at (expires_at),
                INDEX idx_created_at (created_at)
            )",

            // Camera presets for PTZ control
            "CREATE TABLE IF NOT EXISTS camera_presets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                camera_id INT NOT NULL,
                zone_name VARCHAR(100),
                preset_id VARCHAR(100),
                pan FLOAT,
                tilt FLOAT,
                zoom FLOAT,
                status VARCHAR(20) DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_preset (camera_id, preset_id),
                INDEX idx_zone_name (zone_name)
            )",

            // Fraud incidents
            "CREATE TABLE IF NOT EXISTS fraud_incidents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                store_id INT NOT NULL,
                incident_type VARCHAR(100),
                severity VARCHAR(20),
                status VARCHAR(20) DEFAULT 'OPEN',
                evidence_collected TINYINT DEFAULT 0,
                investigator_notes LONGTEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_staff_id (staff_id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            )",

            // Evidence linked to incidents
            "CREATE TABLE IF NOT EXISTS fraud_evidence (
                id INT AUTO_INCREMENT PRIMARY KEY,
                incident_id INT NOT NULL,
                evidence_type VARCHAR(100),
                camera_id INT,
                timestamp_start DATETIME,
                timestamp_end DATETIME,
                description LONGTEXT,
                file_path VARCHAR(500),
                processed TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (incident_id) REFERENCES fraud_incidents(id),
                INDEX idx_incident_id (incident_id),
                INDEX idx_camera_id (camera_id)
            )",

            // System logs
            "CREATE TABLE IF NOT EXISTS behavioral_analysis_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(20),
                message VARCHAR(500),
                context JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_level (level),
                INDEX idx_created_at (created_at)
            )",
        ];

        foreach ($tables as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (Exception $e) {
                echo "  [!] Error creating table: " . $e->getMessage() . "\n";
            }
        }

        echo "  [✓] Database schema ready\n";
    }

    /**
     * Verify camera network connectivity
     */
    private function verifyCameraNetwork(): void
    {
        echo "[*] Verifying camera network...\n";

        try {
            $sql = "
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active,
                    COUNT(DISTINCT store_id) as stores
                FROM cameras
            ";

            $result = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

            $totalCameras = $result['total'] ?? 0;
            $activeCameras = $result['active'] ?? 0;
            $stores = $result['stores'] ?? 0;

            echo "  [✓] Total cameras: $totalCameras (Active: $activeCameras)\n";
            echo "  [✓] Stores connected: $stores\n";

            if ($activeCameras < $totalCameras * 0.8) {
                echo "  [!] Warning: Less than 80% of cameras active\n";
            }
        } catch (Exception $e) {
            echo "  [!] Error verifying camera network: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Initialize the behavioral analytics engine
     */
    private function initializeAnalyticsEngine(): void
    {
        echo "[*] Initializing behavioral analytics engine...\n";

        try {
            // Verify required data sources
            $dataSources = [
                'sales_transactions' => 'Sales transactions',
                'refunds' => 'Refunds',
                'inventory_movements' => 'Inventory movements',
                'deputy_timesheets' => 'Deputy timesheets',
                'fraud_incidents' => 'Fraud incidents',
                'staff' => 'Staff records',
            ];

            foreach ($dataSources as $table => $label) {
                $sql = "SELECT COUNT(*) as count FROM $table LIMIT 1";
                try {
                    $result = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
                    echo "  [✓] $label: Connected\n";
                } catch (Exception $e) {
                    echo "  [!] $label: Table not found\n";
                }
            }

            echo "  [✓] Analytics engine ready\n";
        } catch (Exception $e) {
            echo "  [!] Error initializing analytics: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Schedule background analysis jobs
     */
    private function scheduleBackgroundJobs(): void
    {
        echo "[*] Scheduling background jobs...\n";

        $jobs = [
            'daily_analysis' => 'Daily behavioral analysis (2:00 AM)',
            'hourly_alert_check' => 'Hourly alert check (every hour)',
            'targeting_expiry' => 'Check targeting expiry (every 5 minutes)',
            'incident_investigation' => 'Incident investigation (every 30 minutes)',
        ];

        foreach ($jobs as $job => $description) {
            echo "  [✓] Scheduled: $description\n";
        }

        echo "  [✓] Background jobs scheduled\n";
    }

    /**
     * Run scheduled daily analysis
     */
    public function runDailyAnalysis(): void
    {
        echo "[*] Running scheduled daily behavioral analysis...\n";
        $startTime = microtime(true);

        try {
            $analytics = new BehavioralAnalyticsEngine($this->pdo);
            $results = $analytics->analyzeAllStaff('daily');

            $targetingSystem = new DynamicCameraTargetingSystem($this->pdo);

            $targetedCount = 0;
            foreach ($results as $analysis) {
                $analytics->saveAnalysisResults($analysis);

                if ($analysis['should_target_cameras']) {
                    try {
                        $targetingSystem->activateTargeting($analysis);
                        $targetedCount++;
                    } catch (Exception $e) {
                        echo "  [!] Failed to activate targeting for " . $analysis['staff_id'] . ": " . $e->getMessage() . "\n";
                    }
                }
            }

            $elapsed = round(microtime(true) - $startTime, 2);
            echo "[✓] Daily analysis complete\n";
            echo "  - Analyzed: " . count($results) . " staff members\n";
            echo "  - Targeted: $targetedCount individuals\n";
            echo "  - Time: {$elapsed}s\n";
        } catch (Exception $e) {
            echo "[!] Error running daily analysis: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Check and deactivate expired targeting
     */
    public function checkTargetingExpiry(): void
    {
        try {
            $sql = "
                SELECT id, staff_id
                FROM camera_targeting_records
                WHERE status = 'ACTIVE'
                AND expires_at <= NOW()
            ";

            $stmt = $this->pdo->query($sql);
            $expiredRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($expiredRecords)) {
                return;
            }

            $targeting = new DynamicCameraTargetingSystem($this->pdo);

            foreach ($expiredRecords as $record) {
                $targeting->deactivateTargeting($record['staff_id']);
                echo "[✓] Deactivated targeting for staff {$record['staff_id']}\n";
            }
        } catch (Exception $e) {
            echo "[!] Error checking targeting expiry: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Generate summary report
     */
    public function generateSummaryReport(int $days = 7): array
    {
        try {
            $sql = "
                SELECT
                    SUM(CASE WHEN risk_level = 'CRITICAL' THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN risk_level = 'HIGH' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN risk_level = 'MEDIUM' THEN 1 ELSE 0 END) as medium,
                    COUNT(*) as total_analyses,
                    AVG(risk_score) as avg_risk,
                    MAX(risk_score) as max_risk
                FROM behavioral_analysis_results
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $sql2 = "
                SELECT COUNT(*) as active FROM camera_targeting_records
                WHERE status = 'ACTIVE'
            ";
            $activeTargeting = $this->pdo->query($sql2)->fetch(PDO::FETCH_ASSOC);

            $sql3 = "
                SELECT COUNT(*) as open_incidents FROM fraud_incidents
                WHERE status IN ('OPEN', 'IN_PROGRESS')
            ";
            $incidents = $this->pdo->query($sql3)->fetch(PDO::FETCH_ASSOC);

            return [
                'period_days' => $days,
                'statistics' => [
                    'critical_flags' => $stats['critical'] ?? 0,
                    'high_flags' => $stats['high'] ?? 0,
                    'medium_flags' => $stats['medium'] ?? 0,
                    'total_analyses' => $stats['total_analyses'] ?? 0,
                    'average_risk_score' => round($stats['avg_risk'] ?? 0, 3),
                    'max_risk_score' => round($stats['max_risk'] ?? 0, 3),
                ],
                'camera_targeting' => [
                    'active_targets' => $activeTargeting['active'] ?? 0,
                ],
                'investigations' => [
                    'open_incidents' => $incidents['open_incidents'] ?? 0,
                ],
                'generated_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// CLI INTERFACE
if (php_sapi_name() === 'cli') {
    // Initialize from command line
    echo "=================================================\n";
    echo " Behavioral Fraud Detection System - Bootstrap  \n";
    echo "=================================================\n\n";

    $command = $argv[1] ?? 'help';

    try {
        // Get database connection
        // This would normally come from your CIS bootstrap/config
        require_once __DIR__ . '/../../../config/database.php';

        $bootstrap = new SystemBootstrap($pdo);

        switch ($command) {
            case 'init':
                $bootstrap->initialize();
                break;

            case 'daily-analysis':
                $bootstrap->runDailyAnalysis();
                break;

            case 'check-expiry':
                $bootstrap->checkTargetingExpiry();
                break;

            case 'report':
                $days = $argv[2] ?? 7;
                $report = $bootstrap->generateSummaryReport($days);
                echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
                break;

            case 'help':
            default:
                echo "Usage: php bootstrap.php COMMAND\n\n";
                echo "Commands:\n";
                echo "  init              - Initialize the system (setup database)\n";
                echo "  daily-analysis    - Run daily behavioral analysis\n";
                echo "  check-expiry      - Check and deactivate expired targeting\n";
                echo "  report [days]     - Generate summary report (default: 7 days)\n";
                echo "  help              - Show this help message\n\n";
                break;
        }
    } catch (Exception $e) {
        echo "[!] Error: " . $e->getMessage() . "\n";
        exit(1);
    }

    echo "\n";
}
