<?php

/**
 * Real-Time Alert & Dashboard System
 *
 * Web-based management dashboard for monitoring behavioral analysis results,
 * viewing real-time camera feeds, and managing fraud investigation workflow.
 *
 * Features:
 * - Real-time risk scoring and alerts
 * - Live camera feed viewer with multi-camera support
 * - Staff risk profiles and historical trending
 * - Investigation and incident management
 * - Advanced filtering and search capabilities
 * - Export and reporting functions
 *
 * @package FraudDetection
 * @version 1.0.0
 */

namespace FraudDetection;

use PDO;
use DateTime;
use Exception;

class RealTimeAlertDashboard
{
    private PDO $pdo;
    private array $config;
    private $logger;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge($this->defaultConfig(), $config);
        $this->initializeLogger();
    }

    private function defaultConfig(): array
    {
        return [
            'dashboard_refresh_interval' => 5, // seconds
            'critical_alert_sound' => true,
            'alert_retention_days' => 30,
            'show_historical_data' => true,
            'enable_export' => true,
            'enable_email_export' => true,
        ];
    }

    private function initializeLogger(): void
    {
        $logPath = __DIR__ . '/../../logs/dashboard.log';
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
     * Get dashboard data - high-risk alerts and current status
     */
    public function getDashboardData(int $storeId = null): array
    {
        try {
            return [
                'summary' => $this->getSummaryMetrics($storeId),
                'critical_alerts' => $this->getCriticalAlerts($storeId),
                'targeted_individuals' => $this->getTargetedIndividuals($storeId),
                'active_investigations' => $this->getActiveInvestigations($storeId),
                'system_health' => $this->getSystemHealth(),
                'recent_incidents' => $this->getRecentIncidents($storeId, 10),
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to get dashboard data: " . $e->getMessage());
            return ['error' => 'Failed to load dashboard data'];
        }
    }

    /**
     * Get summary metrics for dashboard
     */
    private function getSummaryMetrics(int $storeId = null): array
    {
        try {
            $whereClause = $storeId ? "WHERE bar.store_id = ?" : "";
            $params = $storeId ? [$storeId] : [];

            // Get risk level counts
            $sql = "
                SELECT
                    SUM(CASE WHEN risk_level = 'CRITICAL' THEN 1 ELSE 0 END) as critical_count,
                    SUM(CASE WHEN risk_level = 'HIGH' THEN 1 ELSE 0 END) as high_count,
                    SUM(CASE WHEN risk_level = 'MEDIUM' THEN 1 ELSE 0 END) as medium_count,
                    SUM(CASE WHEN risk_level = 'LOW' THEN 1 ELSE 0 END) as low_count,
                    COUNT(*) as total_flagged,
                    AVG(risk_score) as average_risk,
                    MAX(risk_score) as highest_risk
                FROM behavioral_analysis_results bar
                {$whereClause}
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND camera_targeting = 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'critical' => (int)$metrics['critical_count'],
                'high' => (int)$metrics['high_count'],
                'medium' => (int)$metrics['medium_count'],
                'low' => (int)$metrics['low_count'],
                'total_flagged' => (int)$metrics['total_flagged'],
                'average_risk' => round($metrics['average_risk'], 3),
                'highest_risk' => round($metrics['highest_risk'], 3),
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to get summary metrics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get critical alerts requiring immediate attention
     */
    private function getCriticalAlerts(int $storeId = null): array
    {
        try {
            $whereClause = $storeId ? "AND bar.store_id = ?" : "";
            $params = $storeId ? [$storeId] : [];

            $sql = "
                SELECT
                    bar.staff_id,
                    s.name,
                    s.email,
                    st.name as store_name,
                    bar.risk_level,
                    bar.risk_score,
                    bar.risk_factors,
                    bar.recommendations,
                    bar.created_at,
                    ctr.id as targeting_id,
                    ctr.status as targeting_status,
                    ctr.expires_at
                FROM behavioral_analysis_results bar
                JOIN staff s ON bar.staff_id = s.id
                JOIN stores st ON bar.store_id = st.id
                LEFT JOIN camera_targeting_records ctr ON bar.staff_id = ctr.staff_id
                    AND ctr.status = 'ACTIVE'
                WHERE bar.risk_level IN ('CRITICAL', 'HIGH')
                {$whereClause}
                AND bar.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
                ORDER BY bar.risk_score DESC, bar.created_at DESC
                LIMIT 20
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format risk factors and recommendations
            foreach ($alerts as &$alert) {
                $alert['risk_factors'] = json_decode($alert['risk_factors'], true);
                $alert['recommendations'] = json_decode($alert['recommendations'], true);
                $alert['risk_score'] = round($alert['risk_score'], 3);
                $alert['hours_since_alert'] = $this->getHoursSince($alert['created_at']);
            }

            return $alerts;
        } catch (Exception $e) {
            $this->logger->error("Failed to get critical alerts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get currently targeted individuals
     */
    private function getTargetedIndividuals(int $storeId = null): array
    {
        try {
            $whereClause = $storeId ? "AND ctr.store_id = ?" : "";
            $params = $storeId ? [$storeId] : [];

            $sql = "
                SELECT
                    ctr.id as targeting_id,
                    ctr.staff_id,
                    s.name,
                    s.email,
                    st.name as store_name,
                    ctr.risk_score,
                    ctr.risk_factors,
                    ctr.activated_at,
                    ctr.expires_at,
                    TIMESTAMPDIFF(MINUTE, ctr.activated_at, ctr.expires_at) as remaining_minutes,
                    ctr.target_cameras,
                    COUNT(DISTINCT c.id) as active_camera_count
                FROM camera_targeting_records ctr
                JOIN staff s ON ctr.staff_id = s.id
                JOIN stores st ON ctr.store_id = st.id
                LEFT JOIN cameras c ON c.store_id = st.id AND c.status = 'ACTIVE'
                WHERE ctr.status = 'ACTIVE'
                {$whereClause}
                AND ctr.expires_at > NOW()
                GROUP BY ctr.id
                ORDER BY ctr.risk_score DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $individuals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($individuals as &$individual) {
                $individual['risk_factors'] = json_decode($individual['risk_factors'], true);
                $individual['target_cameras'] = json_decode($individual['target_cameras'], true);
                $individual['risk_score'] = round($individual['risk_score'], 3);
            }

            return $individuals;
        } catch (Exception $e) {
            $this->logger->error("Failed to get targeted individuals: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get active investigations
     */
    private function getActiveInvestigations(int $storeId = null): array
    {
        try {
            $whereClause = $storeId ? "AND fi.store_id = ?" : "";
            $params = $storeId ? [$storeId] : [];

            $sql = "
                SELECT
                    fi.id as investigation_id,
                    fi.staff_id,
                    s.name,
                    s.email,
                    st.name as store_name,
                    fi.incident_type,
                    fi.severity,
                    fi.status,
                    fi.evidence_collected,
                    fi.investigator_notes,
                    fi.created_at,
                    fi.updated_at,
                    COUNT(DISTINCT fe.id) as evidence_count
                FROM fraud_incidents fi
                JOIN staff s ON fi.staff_id = s.id
                JOIN stores st ON fi.store_id = st.id
                LEFT JOIN fraud_evidence fe ON fi.id = fe.incident_id
                WHERE fi.status IN ('OPEN', 'IN_PROGRESS', 'ESCALATED')
                {$whereClause}
                GROUP BY fi.id
                ORDER BY fi.severity DESC, fi.created_at DESC
                LIMIT 15
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get active investigations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent incidents
     */
    private function getRecentIncidents(int $storeId = null, int $limit = 10): array
    {
        try {
            $whereClause = $storeId ? "WHERE fi.store_id = ?" : "";
            $params = $storeId ? [$storeId] : [];

            $sql = "
                SELECT
                    fi.id,
                    fi.staff_id,
                    s.name as staff_name,
                    st.name as store_name,
                    fi.incident_type,
                    fi.severity,
                    fi.status,
                    fi.created_at,
                    DATE_FORMAT(fi.created_at, '%M %d, %Y %h:%i %p') as formatted_date
                FROM fraud_incidents fi
                JOIN staff s ON fi.staff_id = s.id
                JOIN stores st ON fi.store_id = st.id
                {$whereClause}
                ORDER BY fi.created_at DESC
                LIMIT {$limit}
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Failed to get recent incidents: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get system health status
     */
    private function getSystemHealth(): array
    {
        try {
            return [
                'database' => $this->checkDatabaseHealth(),
                'camera_network' => $this->checkCameraNetworkHealth(),
                'ai_processing' => $this->checkAIProcessingHealth(),
                'storage' => $this->checkStorageHealth(),
                'overall_status' => 'OPERATIONAL',
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to get system health: " . $e->getMessage());
            return ['overall_status' => 'ERROR'];
        }
    }

    private function checkDatabaseHealth(): array
    {
        try {
            $result = $this->pdo->query("SELECT 1")->fetch();
            return ['status' => $result ? 'HEALTHY' : 'ERROR', 'timestamp' => now()];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private function checkCameraNetworkHealth(): array
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'INACTIVE' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN status = 'ERROR' THEN 1 ELSE 0 END) as error
                FROM cameras
            ";
            $result = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

            $healthPercentage = ($result['active'] / $result['total']) * 100;
            $status = $healthPercentage >= 95 ? 'HEALTHY' : ($healthPercentage >= 80 ? 'DEGRADED' : 'ERROR');

            return [
                'status' => $status,
                'active_cameras' => $result['active'],
                'total_cameras' => $result['total'],
                'health_percentage' => round($healthPercentage, 1),
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private function checkAIProcessingHealth(): array
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as total_analyses,
                    AVG(risk_score) as avg_risk,
                    MAX(TIMESTAMPDIFF(MINUTE, created_at, NOW())) as oldest_analysis_minutes
                FROM behavioral_analysis_results
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ";
            $result = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

            $status = $result['oldest_analysis_minutes'] < 60 ? 'HEALTHY' : 'DEGRADED';

            return [
                'status' => $status,
                'analyses_last_24h' => $result['total_analyses'],
                'average_risk' => round($result['avg_risk'], 3),
                'last_analysis_minutes_ago' => $result['oldest_analysis_minutes'],
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private function checkStorageHealth(): array
    {
        try {
            // Check database disk space
            $sql = "SELECT
                        ROUND(sum(data_length + index_length) / 1024 / 1024, 1) as database_size_mb
                     FROM information_schema.TABLES
                     WHERE table_schema NOT IN ('mysql', 'information_schema', 'performance_schema')";
            $result = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

            return [
                'status' => 'HEALTHY',
                'database_size_mb' => $result['database_size_mb'],
                'video_storage_gb' => 'N/A', // Would query file system
            ];
        } catch (Exception $e) {
            return ['status' => 'DEGRADED', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get detailed staff profile with historical data
     */
    public function getStaffProfile(int $staffId): array
    {
        try {
            $staff = $this->getStaffData($staffId);
            if (!$staff) {
                throw new Exception("Staff member not found");
            }

            return [
                'profile' => $staff,
                'current_analysis' => $this->getCurrentAnalysis($staffId),
                'historical_trends' => $this->getHistoricalTrends($staffId),
                'incident_history' => $this->getIncidentHistory($staffId),
                'targeting_history' => $this->getTargetingHistory($staffId),
                'peer_comparison' => $this->getPeerComparison($staffId),
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to get staff profile: " . $e->getMessage());
            return ['error' => 'Failed to load staff profile'];
        }
    }

    private function getStaffData(int $staffId): ?array
    {
        $sql = "
            SELECT
                s.id, s.name, s.email, s.phone,
                s.hire_date, s.department, s.role,
                st.name as store_name,
                COUNT(DISTINCT fi.id) as total_incidents
            FROM staff s
            JOIN stores st ON s.store_id = st.id
            LEFT JOIN fraud_incidents fi ON s.id = fi.staff_id
            WHERE s.id = ?
            GROUP BY s.id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getCurrentAnalysis(int $staffId): ?array
    {
        $sql = "
            SELECT * FROM behavioral_analysis_results
            WHERE staff_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['risk_factors'] = json_decode($result['risk_factors'], true);
            $result['recommendations'] = json_decode($result['recommendations'], true);
            $result['risk_score'] = round($result['risk_score'], 3);
        }

        return $result;
    }

    private function getHistoricalTrends(int $staffId, int $days = 30): array
    {
        $sql = "
            SELECT
                DATE(created_at) as date,
                AVG(risk_score) as avg_risk,
                MAX(risk_score) as peak_risk,
                COUNT(*) as analysis_count
            FROM behavioral_analysis_results
            WHERE staff_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getIncidentHistory(int $staffId): array
    {
        $sql = "
            SELECT
                id, incident_type, severity, status, created_at
            FROM fraud_incidents
            WHERE staff_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTargetingHistory(int $staffId): array
    {
        $sql = "
            SELECT
                id, status, risk_score, activated_at, expires_at, deactivated_at
            FROM camera_targeting_records
            WHERE staff_id = ?
            ORDER BY activated_at DESC
            LIMIT 10
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPeerComparison(int $staffId): array
    {
        // Get this staff's metrics vs store peers
        $sql = "
            SELECT
                'This Employee' as type,
                AVG(risk_score) as avg_risk,
                COUNT(*) as analysis_count
            FROM behavioral_analysis_results
            WHERE staff_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION ALL
            SELECT
                'Store Average' as type,
                AVG(bar.risk_score) as avg_risk,
                COUNT(*) as analysis_count
            FROM behavioral_analysis_results bar
            WHERE bar.staff_id IN (
                SELECT id FROM staff WHERE store_id = (
                    SELECT store_id FROM staff WHERE id = ?
                )
            )
            AND bar.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffId, $staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Helper: Get hours since timestamp
     */
    private function getHoursSince(string $timestamp): int
    {
        $dt = new DateTime($timestamp);
        $now = new DateTime();
        $diff = $now->diff($dt);
        return $diff->days * 24 + $diff->h;
    }
}
