<?php
/**
 * Autosave Service - Enterprise Grade
 *
 * Features:
 * - Debounced autosave (configurable interval)
 * - IndexedDB browser-side backup
 * - Conflict detection & resolution
 * - Recovery from crashes
 * - Checkpoint management
 * - Version history
 * - Cross-device sync detection
 *
 * @author Enterprise Engineering Team
 * @date 2025-11-13
 */

declare(strict_types=1);

class AutosaveService
{
    private PDO $pdo;
    private array $config;

    private const DEFAULT_INTERVAL_SECONDS = 3;
    private const MAX_CHECKPOINTS_PER_REPORT = 50;
    private const CHECKPOINT_RETENTION_DAYS = 7;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;

        $this->config = array_merge([
            'interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
            'max_checkpoints' => self::MAX_CHECKPOINTS_PER_REPORT,
            'retention_days' => self::CHECKPOINT_RETENTION_DAYS,
            'enable_deduplication' => true,
            'compression' => true,
        ], $config);
    }

    /**
     * Create autosave checkpoint
     *
     * @param int $reportId
     * @param int $userId
     * @param array $data Complete report state
     * @param array $context Session/device info
     * @return array Result with checkpoint ID
     */
    public function createCheckpoint(int $reportId, int $userId, array $data, array $context = []): array
    {
        try {
            // Generate hash for deduplication
            $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
            $hash = hash('sha256', $dataJson);

            // Check if identical checkpoint already exists (deduplication)
            if ($this->config['enable_deduplication']) {
                $existing = $this->findCheckpointByHash($reportId, $hash);

                if ($existing) {
                    return [
                        'success' => true,
                        'checkpoint_id' => $existing['id'],
                        'deduplicated' => true,
                        'message' => 'No changes detected since last save'
                    ];
                }
            }

            // Calculate completion percentage
            $completionPercentage = $this->calculateCompletionPercentage($data);

            // Compress data if enabled
            if ($this->config['compression']) {
                $dataJson = gzcompress($dataJson, 6);
            }

            // Insert checkpoint
            $stmt = $this->pdo->prepare("
                INSERT INTO store_report_autosave_checkpoints
                (report_id, user_id, checkpoint_data, checkpoint_hash,
                 session_id, device_id, page_url, scroll_position,
                 items_completed, completion_percentage, has_unsaved_changes,
                 is_recovery_point, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $isRecoveryPoint = ($completionPercentage > 0 && $completionPercentage % 25 == 0); // Every 25%
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $this->config['retention_days'] . ' days'));

            $stmt->execute([
                $reportId,
                $userId,
                $dataJson,
                $hash,
                $context['session_id'] ?? null,
                $context['device_id'] ?? null,
                $context['page_url'] ?? null,
                $context['scroll_position'] ?? null,
                $data['items_completed'] ?? 0,
                $completionPercentage,
                1, // has_unsaved_changes = true
                $isRecoveryPoint ? 1 : 0,
                $expiresAt
            ]);

            $checkpointId = (int)$this->pdo->lastInsertId();

            // Update report's autosave reference
            $this->updateReportAutosave($reportId, $checkpointId);

            // Log autosave event
            $this->logAutosaveEvent($reportId, $userId, 'autosaved', $checkpointId);

            // Cleanup old checkpoints
            $this->cleanupOldCheckpoints($reportId);

            return [
                'success' => true,
                'checkpoint_id' => $checkpointId,
                'hash' => $hash,
                'completion_percentage' => $completionPercentage,
                'is_recovery_point' => $isRecoveryPoint,
                'deduplicated' => false
            ];

        } catch (Exception $e) {
            error_log("Autosave failed for report $reportId: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Recover report from latest checkpoint
     *
     * @param int $reportId
     * @param int $userId
     * @return array|null Recovered data or null
     */
    public function recoverLatestCheckpoint(int $reportId, int $userId): ?array
    {
        try {
            // Find latest recovery point or any checkpoint
            $stmt = $this->pdo->prepare("
                SELECT * FROM store_report_autosave_checkpoints
                WHERE report_id = ? AND user_id = ?
                ORDER BY is_recovery_point DESC, created_at DESC
                LIMIT 1
            ");

            $stmt->execute([$reportId, $userId]);
            $checkpoint = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$checkpoint) {
                return null;
            }

            // Decompress data if needed
            $data = $checkpoint['checkpoint_data'];

            if ($this->config['compression']) {
                $data = gzuncompress($data);
            }

            $recoveredData = json_decode($data, true);

            // Mark as recovered
            $this->pdo->prepare("
                UPDATE store_report_autosave_checkpoints
                SET recovered_from = 1, recovered_at = NOW()
                WHERE id = ?
            ")->execute([$checkpoint['id']]);

            // Log recovery event
            $this->logAutosaveEvent($reportId, $userId, 'recovered', $checkpoint['id']);

            return [
                'success' => true,
                'checkpoint_id' => $checkpoint['id'],
                'data' => $recoveredData,
                'metadata' => [
                    'created_at' => $checkpoint['created_at'],
                    'completion_percentage' => $checkpoint['completion_percentage'],
                    'is_recovery_point' => (bool)$checkpoint['is_recovery_point'],
                    'session_id' => $checkpoint['session_id'],
                    'device_id' => $checkpoint['device_id']
                ]
            ];

        } catch (Exception $e) {
            error_log("Recovery failed for report $reportId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all recovery points for a report
     *
     * @param int $reportId
     * @param int $limit
     * @return array List of checkpoints
     */
    public function getRecoveryPoints(int $reportId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id, user_id, created_at, completion_percentage,
                items_completed, is_recovery_point, session_id, device_id,
                recovered_from
            FROM store_report_autosave_checkpoints
            WHERE report_id = ?
            ORDER BY is_recovery_point DESC, created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$reportId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detect conflicts (multiple devices editing same report)
     *
     * @param int $reportId
     * @param string $currentDeviceId
     * @param string $currentSessionId
     * @return array Conflict info or null
     */
    public function detectConflicts(int $reportId, string $currentDeviceId, string $currentSessionId): ?array
    {
        // Check if another device/session has recent activity
        $stmt = $this->pdo->prepare("
            SELECT
                c.id, c.user_id, c.device_id, c.session_id, c.created_at,
                c.completion_percentage,
                u.first_name, u.last_name
            FROM store_report_autosave_checkpoints c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.report_id = ?
              AND c.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              AND (c.device_id != ? OR c.session_id != ?)
            ORDER BY c.created_at DESC
            LIMIT 1
        ");

        $stmt->execute([$reportId, $currentDeviceId, $currentSessionId]);
        $conflict = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conflict) {
            return null;
        }

        return [
            'has_conflict' => true,
            'other_user_id' => $conflict['user_id'],
            'other_user_name' => trim(($conflict['first_name'] ?? '') . ' ' . ($conflict['last_name'] ?? '')),
            'other_device_id' => $conflict['device_id'],
            'other_session_id' => $conflict['session_id'],
            'last_activity' => $conflict['created_at'],
            'other_completion' => $conflict['completion_percentage']
        ];
    }

    /**
     * Resolve conflict by merging changes
     *
     * @param int $reportId
     * @param array $localData Local device data
     * @param array $remoteData Remote/server data
     * @param string $strategy 'local_wins', 'remote_wins', 'merge'
     * @return array Resolved data
     */
    public function resolveConflict(int $reportId, array $localData, array $remoteData, string $strategy = 'merge'): array
    {
        switch ($strategy) {
            case 'local_wins':
                $resolved = $localData;
                break;

            case 'remote_wins':
                $resolved = $remoteData;
                break;

            case 'merge':
            default:
                $resolved = $this->mergeReportData($localData, $remoteData);
                break;
        }

        // Log conflict resolution
        $this->logConflictResolution($reportId, $strategy);

        return [
            'success' => true,
            'data' => $resolved,
            'strategy' => $strategy,
            'conflicts_detected' => $this->getConflictFields($localData, $remoteData)
        ];
    }

    /**
     * Merge two report states intelligently
     */
    private function mergeReportData(array $local, array $remote): array
    {
        $merged = $remote; // Start with remote as base

        // Items: merge by checklist_id
        if (isset($local['items']) && isset($remote['items'])) {
            $mergedItems = [];
            $itemsById = [];

            // Index remote items
            foreach ($remote['items'] as $item) {
                $key = $item['checklist_id'] ?? null;
                if ($key) {
                    $itemsById[$key] = $item;
                }
            }

            // Merge local items (prefer local if more recent)
            foreach ($local['items'] as $item) {
                $key = $item['checklist_id'] ?? null;
                if ($key) {
                    if (isset($itemsById[$key])) {
                        // Compare timestamps if available
                        $localTime = strtotime($item['answered_at'] ?? '1970-01-01');
                        $remoteTime = strtotime($itemsById[$key]['answered_at'] ?? '1970-01-01');

                        $itemsById[$key] = ($localTime > $remoteTime) ? $item : $itemsById[$key];
                    } else {
                        $itemsById[$key] = $item;
                    }
                }
            }

            $merged['items'] = array_values($itemsById);
        }

        // Images: combine both sets (no duplicates by path)
        if (isset($local['images']) && isset($remote['images'])) {
            $allImages = array_merge($remote['images'], $local['images']);
            $merged['images'] = array_values(array_unique($allImages, SORT_REGULAR));
        }

        // Voice memos: combine
        if (isset($local['voice_memos']) && isset($remote['voice_memos'])) {
            $allMemos = array_merge($remote['voice_memos'], $local['voice_memos']);
            $merged['voice_memos'] = array_values(array_unique($allMemos, SORT_REGULAR));
        }

        // Notes: append if different
        if (isset($local['staff_notes']) && $local['staff_notes'] !== ($remote['staff_notes'] ?? '')) {
            $merged['staff_notes'] = ($remote['staff_notes'] ?? '') . "\n\n[Merged from other device]\n" . $local['staff_notes'];
        }

        // Use highest completion percentage
        if (isset($local['completion_percentage']) && isset($remote['completion_percentage'])) {
            $merged['completion_percentage'] = max($local['completion_percentage'], $remote['completion_percentage']);
        }

        return $merged;
    }

    /**
     * Identify which fields have conflicts
     */
    private function getConflictFields(array $local, array $remote): array
    {
        $conflicts = [];

        // Compare top-level fields
        foreach ($local as $key => $value) {
            if (isset($remote[$key]) && $remote[$key] !== $value) {
                $conflicts[] = $key;
            }
        }

        return $conflicts;
    }

    /**
     * Calculate completion percentage
     */
    private function calculateCompletionPercentage(array $data): float
    {
        $totalItems = count($data['checklist'] ?? []);

        if ($totalItems === 0) {
            return 0.0;
        }

        $completedItems = 0;

        foreach (($data['items'] ?? []) as $item) {
            if (!empty($item['response_value']) || !empty($item['response_text']) || !empty($item['is_na'])) {
                $completedItems++;
            }
        }

        return round(($completedItems / $totalItems) * 100, 2);
    }

    /**
     * Find checkpoint by hash (deduplication)
     */
    private function findCheckpointByHash(int $reportId, string $hash): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, created_at
            FROM store_report_autosave_checkpoints
            WHERE report_id = ? AND checkpoint_hash = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([$reportId, $hash]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update report's last autosave reference
     */
    private function updateReportAutosave(int $reportId, int $checkpointId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE store_reports
            SET last_autosave_at = NOW(),
                autosave_checkpoint_id = ?,
                status = CASE
                    WHEN status = 'draft' THEN 'autosaved'
                    ELSE status
                END
            WHERE id = ?
        ");

        $stmt->execute([$checkpointId, $reportId]);
    }

    /**
     * Cleanup old checkpoints (keep only recent ones)
     */
    private function cleanupOldCheckpoints(int $reportId): void
    {
        // Keep only max_checkpoints most recent, plus all recovery points
        $stmt = $this->pdo->prepare("
            DELETE FROM store_report_autosave_checkpoints
            WHERE report_id = ?
              AND is_recovery_point = 0
              AND id NOT IN (
                  SELECT id FROM (
                      SELECT id FROM store_report_autosave_checkpoints
                      WHERE report_id = ?
                      ORDER BY created_at DESC
                      LIMIT ?
                  ) AS keep
              )
        ");

        $stmt->execute([$reportId, $reportId, $this->config['max_checkpoints']]);

        // Also delete expired checkpoints
        $this->pdo->prepare("
            DELETE FROM store_report_autosave_checkpoints
            WHERE expires_at < NOW()
        ")->execute();
    }

    /**
     * Log autosave event to history
     */
    private function logAutosaveEvent(int $reportId, int $userId, string $action, int $checkpointId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO store_report_history
                (report_id, user_id, action_type, entity_type, entity_id, description, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $description = match($action) {
                'autosaved' => 'Report autosaved',
                'recovered' => 'Report recovered from checkpoint',
                default => 'Autosave action: ' . $action
            };

            $stmt->execute([
                $reportId,
                $userId,
                $action,
                'checkpoint',
                $checkpointId,
                $description
            ]);
        } catch (Exception $e) {
            // Non-critical, just log
            error_log("Failed to log autosave event: " . $e->getMessage());
        }
    }

    /**
     * Log conflict resolution
     */
    private function logConflictResolution(int $reportId, string $strategy): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO store_report_history
                (report_id, user_id, action_type, description, created_at)
                VALUES (?, NULL, ?, ?, NOW())
            ");

            $stmt->execute([
                $reportId,
                'conflict_resolved',
                "Conflict resolved using strategy: $strategy"
            ]);
        } catch (Exception $e) {
            error_log("Failed to log conflict resolution: " . $e->getMessage());
        }
    }

    /**
     * Get autosave statistics for debugging
     */
    public function getStats(int $reportId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_checkpoints,
                SUM(is_recovery_point) as recovery_points,
                SUM(recovered_from) as recoveries_used,
                MAX(created_at) as last_autosave,
                MAX(completion_percentage) as max_completion,
                COUNT(DISTINCT session_id) as unique_sessions,
                COUNT(DISTINCT device_id) as unique_devices
            FROM store_report_autosave_checkpoints
            WHERE report_id = ?
        ");

        $stmt->execute([$reportId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
