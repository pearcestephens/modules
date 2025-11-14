<?php

/**
 * Staff Location Tracking System
 *
 * Integrates with Deputy API and badge systems to track staff locations
 * in real-time for camera selection and fraud detection targeting.
 *
 * @package FraudDetection
 * @version 1.0.0
 */

namespace FraudDetection;

use PDO;
use Exception;
use DateTime;

class StaffLocationTracker
{
    private PDO $pdo;
    private array $config;
    private ?string $deputyApiKey;
    private ?string $deputyApiUrl;
    private array $locationCache = [];
    private int $cacheTtl = 300; // 5 minutes

    /**
     * Location sources priority order
     */
    private const SOURCES = [
        'badge_system',    // Physical badge scans (most reliable)
        'deputy_api',      // Deputy shift schedules
        'last_known',      // Last confirmed location
        'default_outlet'   // Staff member's home outlet
    ];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->deputyApiKey = $config['deputy_api_key'] ?? getenv('DEPUTY_API_KEY');
        $this->deputyApiUrl = $config['deputy_api_url'] ?? getenv('DEPUTY_API_URL') ?? 'https://api.deputy.com/v1';
    }

    /**
     * Get current location of a staff member
     *
     * @param int $staffId Staff member ID
     * @return array{outlet_id: int, outlet_name: string, confidence: float, source: string, timestamp: string}|null
     */
    public function getCurrentLocation(int $staffId): ?array
    {
        // Check cache first
        $cacheKey = "staff_location_{$staffId}";
        if (isset($this->locationCache[$cacheKey])) {
            $cached = $this->locationCache[$cacheKey];
            if (time() - $cached['cached_at'] < $this->cacheTtl) {
                return $cached['data'];
            }
        }

        // Try each source in priority order
        foreach (self::SOURCES as $source) {
            $location = match ($source) {
                'badge_system' => $this->getLocationFromBadgeSystem($staffId),
                'deputy_api' => $this->getLocationFromDeputy($staffId),
                'last_known' => $this->getLastKnownLocation($staffId),
                'default_outlet' => $this->getDefaultOutlet($staffId),
                default => null
            };

            if ($location) {
                $location['source'] = $source;
                $this->cacheLocation($cacheKey, $location);
                $this->recordLocationHistory($staffId, $location);
                return $location;
            }
        }

        return null;
    }

    /**
     * Get locations for multiple staff members
     *
     * @param array $staffIds Array of staff IDs
     * @return array Keyed by staff_id
     */
    public function getMultipleLocations(array $staffIds): array
    {
        $locations = [];
        foreach ($staffIds as $staffId) {
            $location = $this->getCurrentLocation($staffId);
            if ($location) {
                $locations[$staffId] = $location;
            }
        }
        return $locations;
    }

    /**
     * Get all staff currently at a specific outlet
     *
     * @param int $outletId Outlet ID
     * @return array Array of staff members with their details
     */
    public function getStaffAtOutlet(int $outletId): array
    {
        // Get all active staff
        $stmt = $this->pdo->prepare("
            SELECT id, name, email, default_outlet_id
            FROM staff
            WHERE status = 'active'
        ");
        $stmt->execute();
        $allStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $staffAtOutlet = [];
        foreach ($allStaff as $staff) {
            $location = $this->getCurrentLocation($staff['id']);
            if ($location && $location['outlet_id'] === $outletId) {
                $staffAtOutlet[] = array_merge($staff, [
                    'current_location' => $location
                ]);
            }
        }

        return $staffAtOutlet;
    }

    /**
     * Get staff location from badge system (physical access)
     *
     * @param int $staffId
     * @return array|null
     */
    private function getLocationFromBadgeSystem(int $staffId): ?array
    {
        try {
            // Query most recent badge scan within last 4 hours
            $stmt = $this->pdo->prepare("
                SELECT
                    b.outlet_id,
                    o.name as outlet_name,
                    b.scan_time,
                    b.scan_type
                FROM badge_scans b
                JOIN outlets o ON b.outlet_id = o.id
                WHERE b.staff_id = :staff_id
                AND b.scan_time >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
                ORDER BY b.scan_time DESC
                LIMIT 1
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $scan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$scan) {
                return null;
            }

            // Calculate confidence based on recency
            $scanTime = new DateTime($scan['scan_time']);
            $now = new DateTime();
            $minutesAgo = ($now->getTimestamp() - $scanTime->getTimestamp()) / 60;

            // Confidence decreases over time
            // 100% in first 30 min, 80% at 2 hours, 60% at 4 hours
            $confidence = max(0.6, 1.0 - ($minutesAgo / 240 * 0.4));

            // Lower confidence for "out" scans
            if ($scan['scan_type'] === 'out') {
                $confidence *= 0.5;
            }

            return [
                'outlet_id' => (int)$scan['outlet_id'],
                'outlet_name' => $scan['outlet_name'],
                'confidence' => round($confidence, 2),
                'timestamp' => $scan['scan_time']
            ];
        } catch (Exception $e) {
            error_log("Badge system location lookup failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get staff location from Deputy API (shift schedules)
     *
     * @param int $staffId
     * @return array|null
     */
    private function getLocationFromDeputy(int $staffId): ?array
    {
        if (!$this->deputyApiKey) {
            return null;
        }

        try {
            // Get Deputy employee ID mapping
            $stmt = $this->pdo->prepare("
                SELECT deputy_employee_id
                FROM staff
                WHERE id = :staff_id
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $deputyId = $stmt->fetchColumn();

            if (!$deputyId) {
                return null;
            }

            // Query Deputy API for current roster
            $now = new DateTime();
            $url = $this->deputyApiUrl . '/resource/Roster/QUERY';

            $query = [
                'search' => [
                    'f1' => [
                        'field' => 'Employee',
                        'type' => 'eq',
                        'data' => $deputyId
                    ],
                    'f2' => [
                        'field' => 'Date',
                        'type' => 'eq',
                        'data' => $now->format('Y-m-d')
                    ]
                ]
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($query),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->deputyApiKey,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return null;
            }

            $data = json_decode($response, true);
            if (empty($data)) {
                return null;
            }

            // Find current active shift
            $currentTime = $now->getTimestamp();
            foreach ($data as $roster) {
                $startTime = strtotime($roster['StartTime']);
                $endTime = strtotime($roster['EndTime']);

                if ($currentTime >= $startTime && $currentTime <= $endTime) {
                    // Map Deputy location to outlet
                    $outletId = $this->mapDeputyLocationToOutlet($roster['OperationalUnit']);

                    if ($outletId) {
                        $stmt = $this->pdo->prepare("SELECT name FROM outlets WHERE id = :id");
                        $stmt->execute(['id' => $outletId]);
                        $outletName = $stmt->fetchColumn();

                        // Confidence based on shift timing
                        $shiftProgress = ($currentTime - $startTime) / ($endTime - $startTime);
                        $confidence = 0.85; // Deputy is reliable but not as certain as badge scan

                        // Lower confidence at start/end of shift
                        if ($shiftProgress < 0.1 || $shiftProgress > 0.9) {
                            $confidence = 0.7;
                        }

                        return [
                            'outlet_id' => $outletId,
                            'outlet_name' => $outletName,
                            'confidence' => $confidence,
                            'timestamp' => $roster['StartTime']
                        ];
                    }
                }
            }

            return null;
        } catch (Exception $e) {
            error_log("Deputy API location lookup failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get last known location from history
     *
     * @param int $staffId
     * @return array|null
     */
    private function getLastKnownLocation(int $staffId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    outlet_id,
                    outlet_name,
                    recorded_at
                FROM staff_location_history
                WHERE staff_id = :staff_id
                AND confidence >= 0.8
                ORDER BY recorded_at DESC
                LIMIT 1
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$location) {
                return null;
            }

            // Check how old the location is
            $recordedAt = new DateTime($location['recorded_at']);
            $now = new DateTime();
            $hoursAgo = ($now->getTimestamp() - $recordedAt->getTimestamp()) / 3600;

            // Only use if less than 8 hours old
            if ($hoursAgo > 8) {
                return null;
            }

            // Low confidence for old data
            $confidence = max(0.3, 0.6 - ($hoursAgo / 8 * 0.3));

            return [
                'outlet_id' => (int)$location['outlet_id'],
                'outlet_name' => $location['outlet_name'],
                'confidence' => round($confidence, 2),
                'timestamp' => $location['recorded_at']
            ];
        } catch (Exception $e) {
            error_log("Last known location lookup failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get staff member's default outlet (fallback)
     *
     * @param int $staffId
     * @return array|null
     */
    private function getDefaultOutlet(int $staffId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    s.default_outlet_id as outlet_id,
                    o.name as outlet_name
                FROM staff s
                JOIN outlets o ON s.default_outlet_id = o.id
                WHERE s.id = :staff_id
            ");
            $stmt->execute(['staff_id' => $staffId]);
            $outlet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$outlet) {
                return null;
            }

            return [
                'outlet_id' => (int)$outlet['outlet_id'],
                'outlet_name' => $outlet['outlet_name'],
                'confidence' => 0.4, // Very low confidence - just an assumption
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("Default outlet lookup failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Map Deputy location ID to outlet ID
     *
     * @param int $deputyLocationId
     * @return int|null
     */
    private function mapDeputyLocationToOutlet(int $deputyLocationId): ?int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT outlet_id
                FROM deputy_location_mapping
                WHERE deputy_location_id = :deputy_id
            ");
            $stmt->execute(['deputy_id' => $deputyLocationId]);
            return $stmt->fetchColumn() ?: null;
        } catch (Exception $e) {
            error_log("Deputy location mapping failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache location data
     *
     * @param string $key
     * @param array $data
     */
    private function cacheLocation(string $key, array $data): void
    {
        $this->locationCache[$key] = [
            'data' => $data,
            'cached_at' => time()
        ];
    }

    /**
     * Record location in history for analytics
     *
     * @param int $staffId
     * @param array $location
     */
    private function recordLocationHistory(int $staffId, array $location): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO staff_location_history
                (staff_id, outlet_id, outlet_name, confidence, source, recorded_at)
                VALUES (:staff_id, :outlet_id, :outlet_name, :confidence, :source, NOW())
            ");
            $stmt->execute([
                'staff_id' => $staffId,
                'outlet_id' => $location['outlet_id'],
                'outlet_name' => $location['outlet_name'],
                'confidence' => $location['confidence'],
                'source' => $location['source']
            ]);
        } catch (Exception $e) {
            // Don't fail if history recording fails
            error_log("Failed to record location history: " . $e->getMessage());
        }
    }

    /**
     * Get cameras for staff member's current location
     *
     * @param int $staffId
     * @return array Array of camera IDs
     */
    public function getCamerasForStaffLocation(int $staffId): array
    {
        $location = $this->getCurrentLocation($staffId);
        if (!$location) {
            return [];
        }

        // Only use high-confidence locations for camera selection
        if ($location['confidence'] < 0.7) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, rtsp_url, zone
                FROM camera_network
                WHERE outlet_id = :outlet_id
                AND status = 'active'
                ORDER BY priority DESC
            ");
            $stmt->execute(['outlet_id' => $location['outlet_id']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Camera lookup failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear location cache (useful for testing)
     */
    public function clearCache(): void
    {
        $this->locationCache = [];
    }

    /**
     * Get location history for analytics
     *
     * @param int $staffId
     * @param int $days Number of days to look back
     * @return array
     */
    public function getLocationHistory(int $staffId, int $days = 30): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    outlet_id,
                    outlet_name,
                    confidence,
                    source,
                    recorded_at,
                    DATE(recorded_at) as date,
                    COUNT(*) as visit_count
                FROM staff_location_history
                WHERE staff_id = :staff_id
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY outlet_id, DATE(recorded_at)
                ORDER BY recorded_at DESC
            ");
            $stmt->execute([
                'staff_id' => $staffId,
                'days' => $days
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Location history lookup failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update Deputy location mapping
     *
     * @param int $deputyLocationId
     * @param int $outletId
     * @return bool
     */
    public function updateDeputyMapping(int $deputyLocationId, int $outletId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO deputy_location_mapping (deputy_location_id, outlet_id, updated_at)
                VALUES (:deputy_id, :outlet_id, NOW())
                ON DUPLICATE KEY UPDATE
                    outlet_id = :outlet_id,
                    updated_at = NOW()
            ");
            return $stmt->execute([
                'deputy_id' => $deputyLocationId,
                'outlet_id' => $outletId
            ]);
        } catch (Exception $e) {
            error_log("Deputy mapping update failed: " . $e->getMessage());
            return false;
        }
    }
}
