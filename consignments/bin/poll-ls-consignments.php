#!/usr/bin/env php
<?php
/**
 * Lightspeed Consignment Poller - Cursor-based sync
 * 
 * Polls Lightspeed for new/updated consignments using cursor pagination.
 * Never misses events, even during downtime.
 * 
 * Usage:
 *   php bin/poll-ls-consignments.php [--full] [--limit=100]
 * 
 * Options:
 *   --full       Full sync from beginning (resets cursor)
 *   --limit=N    Page size (default: 100)
 * 
 * @package Consignments
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Consignments\Infra\Lightspeed\LightspeedClient;
use Psr\Log\LoggerInterface;

class ConsignmentPoller
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private LightspeedClient $client;
    private int $pageSize = 100;
    private bool $fullSync = false;

    public function __construct(PDO $pdo, LoggerInterface $logger, LightspeedClient $client)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->client = $client;
    }

    public function setPageSize(int $size): void
    {
        $this->pageSize = $size;
    }

    public function setFullSync(bool $full): void
    {
        $this->fullSync = $full;
    }

    public function poll(): void
    {
        $startTime = microtime(true);
        $totalFetched = 0;
        $totalUpserted = 0;

        try {
            if ($this->fullSync) {
                $this->logger->info('Starting FULL sync (cursor reset)');
                $this->resetCursor();
            }

            $cursor = $this->getCursor();
            $this->logger->info('Starting poll', [
                'cursor' => $cursor,
                'page_size' => $this->pageSize,
                'full_sync' => $this->fullSync
            ]);

            $hasMore = true;
            $currentCursor = $cursor;

            while ($hasMore) {
                $page = $this->fetchPage($currentCursor);
                $consignments = $page['data'] ?? [];
                $hasMore = $page['has_more'] ?? false;

                $this->logger->debug('Fetched page', [
                    'count' => count($consignments),
                    'has_more' => $hasMore
                ]);

                if (empty($consignments)) {
                    break;
                }

                foreach ($consignments as $consignment) {
                    $upserted = $this->upsertConsignment($consignment);
                    if ($upserted) {
                        $totalUpserted++;
                    }
                    $totalFetched++;
                }

                // Update cursor to highest ID in this page
                $maxId = max(array_column($consignments, 'id'));
                $currentCursor = (string)$maxId;
                $this->updateCursor($currentCursor);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Poll completed', [
                'fetched' => $totalFetched,
                'upserted' => $totalUpserted,
                'final_cursor' => $currentCursor,
                'duration_ms' => $duration
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Poll failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function fetchPage(string $cursor): array
    {
        $params = [
            'page_size' => $this->pageSize,
            'order' => 'id',
            'direction' => 'asc'
        ];

        if ($cursor !== '0') {
            $params['after'] = $cursor;
        }

        $response = $this->client->get('/api/2.0/consignments.json', $params);

        return [
            'data' => $response['consignments'] ?? [],
            'has_more' => !empty($response['consignments']) && count($response['consignments']) === $this->pageSize
        ];
    }

    private function upsertConsignment(array $consignment): bool
    {
        $consignmentId = $consignment['id'];
        $status = $consignment['status'] ?? 'UNKNOWN';
        $outletId = $consignment['outlet_id'] ?? null;
        $rawJson = json_encode($consignment);

        // Check if exists
        $stmt = $this->pdo->prepare("SELECT id FROM queue_consignments WHERE consignment_id = ?");
        $stmt->execute([$consignmentId]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            // Update
            $updateStmt = $this->pdo->prepare("
                UPDATE queue_consignments
                SET status = ?,
                    outlet_id = ?,
                    raw_json = ?,
                    last_synced_at = NOW()
                WHERE consignment_id = ?
            ");
            $updateStmt->execute([$status, $outletId, $rawJson, $consignmentId]);

            $this->logger->debug('Consignment updated', [
                'consignment_id' => $consignmentId,
                'status' => $status
            ]);

            return false; // Not a new record

        } else {
            // Insert
            $insertStmt = $this->pdo->prepare("
                INSERT INTO queue_consignments (consignment_id, status, outlet_id, raw_json, first_seen_at, last_synced_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $insertStmt->execute([$consignmentId, $status, $outletId, $rawJson]);

            $this->logger->debug('Consignment inserted', [
                'consignment_id' => $consignmentId,
                'status' => $status
            ]);

            return true; // New record
        }
    }

    private function getCursor(): string
    {
        $stmt = $this->pdo->prepare("SELECT last_processed_id FROM sync_cursors WHERE cursor_type = 'consignments'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['last_processed_id'] ?? '0';
    }

    private function updateCursor(string $cursorValue): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO sync_cursors (cursor_type, last_processed_id, last_processed_at)
            VALUES ('consignments', ?, NOW())
            ON DUPLICATE KEY UPDATE
                last_processed_id = VALUES(last_processed_id),
                last_processed_at = VALUES(last_processed_at)
        ");
        $stmt->execute([$cursorValue]);

        $this->logger->debug('Cursor updated', ['cursor' => $cursorValue]);
    }

    private function resetCursor(): void
    {
        $stmt = $this->pdo->prepare("UPDATE sync_cursors SET last_processed_id = '0' WHERE cursor_type = 'consignments'");
        $stmt->execute();
    }
}

// ============================================================================
// CLI Entry Point
// ============================================================================

$options = getopt('', ['full', 'limit:']);
$fullSync = isset($options['full']);
$pageSize = isset($options['limit']) ? (int)$options['limit'] : 100;

try {
    // Load dependencies
    $pdo = require __DIR__ . '/../config/database.php';
    $logger = require __DIR__ . '/../config/logger.php';
    $client = new LightspeedClient($logger);

    $poller = new ConsignmentPoller($pdo, $logger, $client);
    $poller->setPageSize($pageSize);
    $poller->setFullSync($fullSync);
    $poller->poll();

    exit(0);

} catch (\Throwable $e) {
    fwrite(STDERR, "Fatal error: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
