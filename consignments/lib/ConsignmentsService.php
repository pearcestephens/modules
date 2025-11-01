<?php
declare(strict_types=1);

/**
 * Legacy ConsignmentsService Shim (BC Compatibility)
 *
 * Provides backwards compatibility for legacy code using:
 *   require_once 'lib/ConsignmentsService.php';
 *   $service = new ConsignmentsService(...);
 *
 * This simply aliases to the canonical namespaced version.
 */

require_once __DIR__ . '/../src/Services/ConsignmentService.php';
class_alias(\Consignments\Services\ConsignmentService::class, 'ConsignmentsService');

{
    public function __construct(
        private \PDO $pdo,
        private \LightspeedClient $ls
    ) {}

    /**
     * Save counted lines, put transfer into PACKING, and return an upload contract.
     * @return array{upload_session_id:string,upload_url:string,progress_url:string}
     */
    public function submitTransferAndPrepareUpload(int $transferId, array $items, string $notes = ''): array
    {
        $this->pdo->beginTransaction();

        // Lock transfer in OPEN/PACKING
        $q = $this->pdo->prepare("SELECT id, public_id, state, outlet_from, outlet_to, COALESCE(reference, public_id) AS ref
                                  FROM transfers
                                  WHERE id = ? AND state IN ('OPEN','PACKING')
                                  FOR UPDATE");
        $q->execute([$transferId]);
        $t = $q->fetch(\PDO::FETCH_ASSOC);
        if (!$t) {
            $this->pdo->rollBack();
            throw new \RuntimeException('Transfer not found or not editable (OPEN/PACKING)');
        }

        // Validate lines belong to this transfer; set qty_sent_total deterministically
        $sel = $this->pdo->prepare("SELECT id, qty_requested FROM transfer_items WHERE transfer_id = ? AND product_id = ?");
        $upd = $this->pdo->prepare("UPDATE transfer_items SET qty_sent_total = ?, updated_at = NOW() WHERE id = ?");

        $processed = 0;
        foreach ($items as $row) {
            if (!is_array($row)) continue;
            $productId  = (string)($row['product_id'] ?? '');
            $countedQty = (int)($row['counted_qty'] ?? 0);
            if ($productId === '' || $countedQty < 0) continue;

            $sel->execute([$transferId, $productId]);
            $line = $sel->fetch(\PDO::FETCH_ASSOC);
            if (!$line) {
                $this->pdo->rollBack();
                throw new \RuntimeException("Product {$productId} not in transfer");
            }
            $toSend = min($countedQty, (int)$line['qty_requested']); // clamp
            $upd->execute([$toSend, (int)$line['id']]);
            $processed++;
        }
        if ($processed === 0) {
            $this->pdo->rollBack();
            throw new \RuntimeException('No valid items processed');
        }

        // Move to PACKING + append note
        $u = $this->pdo->prepare("UPDATE transfers
                                  SET state='PACKING',
                                      notes = CONCAT(COALESCE(notes,''), :n),
                                      updated_at = NOW()
                                  WHERE id = :id");
        $note = $notes !== '' ? ("\n[" . date('c') . "] " . $notes) : '';
        $u->execute([':id' => $transferId, ':n' => $note]);

        // Ensure progress table + upsert first status
        self::ensureProgressSchema($this->pdo);
        $sessionId = 'upload_' . bin2hex(random_bytes(8));
        $this->progressUpsert($transferId, $sessionId, 'ready', 'Ready to upload');

        $this->pdo->commit();

        return [
            'upload_session_id' => $sessionId,
            'upload_url' => "/modules/consignments/api/upload_to_lightspeed.php",
            'progress_url' => "/modules/consignments/api/progress_sse.php?transfer_id={$transferId}&session_id={$sessionId}"
        ];
    }

    /**
     * Perform the Lightspeed upload for this transfer + session.
     * - Creates consignment
     * - Adds all products (qty_sent_total)
     * - Marks SENT on success
     */
    public function uploadNow(int $transferId, string $sessionId): array
    {
        // Basic lock & fetch
        $this->pdo->beginTransaction();
        $tq = $this->pdo->prepare("SELECT id, outlet_from, outlet_to, COALESCE(reference, public_id) AS ref, vend_transfer_id, state
                                   FROM transfers WHERE id = ? FOR UPDATE");
        $tq->execute([$transferId]);
        $t = $tq->fetch(\PDO::FETCH_ASSOC);
        if (!$t) {
            $this->pdo->rollBack();
            throw new \RuntimeException('Transfer not found');
        }

        // Start progress
        $this->progressUpsert($transferId, $sessionId, 'connecting', 'Connecting to Lightspeed…');

        // Create consignment if not present
        $vendId = (string)($t['vend_transfer_id'] ?? '');
        if ($vendId === '') {
            $this->progressUpsert($transferId, $sessionId, 'creating', 'Creating consignment…', ['ref' => $t['ref']]);
            $res = $this->ls->createConsignment((string)$t['outlet_from'], (string)$t['outlet_to'], (string)$t['ref']);
            if (!$res['ok'] || empty($res['json']['id'])) {
                $this->progressUpsert($transferId, $sessionId, 'error', 'Consignment create failed', ['response' => $res]);
                $this->pdo->rollBack();
                throw new \RuntimeException('Lightspeed consignment create failed: ' . ($res['raw'] ?? ''));
            }
            $vendId = (string)$res['json']['id'];
            $uu = $this->pdo->prepare("UPDATE transfers SET vend_transfer_id=?, updated_at=NOW() WHERE id=?");
            $uu->execute([$vendId, $transferId]);
            $this->progressUpsert($transferId, $sessionId, 'created', 'Consignment created', ['vend_consignment_id' => $vendId]);
        } else {
            $this->progressUpsert($transferId, $sessionId, 'resuming', 'Resuming existing consignment', ['vend_consignment_id' => $vendId]);
        }
        $this->pdo->commit();

        // Fetch lines with vend_product_id
        $li = $this->pdo->prepare("
            SELECT ti.product_id, ti.qty_sent_total AS count, vp.product_id AS vend_product_id
            FROM transfer_items ti
            LEFT JOIN vend_products vp ON vp.id = ti.product_id
            WHERE ti.transfer_id = ? AND ti.qty_sent_total > 0
        ");
        $li->execute([$transferId]);
        $lines = $li->fetchAll(\PDO::FETCH_ASSOC);

        $added = 0; $failed = 0;
        foreach ($lines as $line) {
            $vendProductId = (string)($line['vend_product_id'] ?? '');
            $cnt = (int)($line['count'] ?? 0);
            if ($vendProductId === '' || $cnt <= 0) {
                $failed++;
                $this->progressUpsert($transferId, $sessionId, 'error', 'Missing product mapping or qty', ['line' => $line]);
                continue;
            }
            $this->progressUpsert($transferId, $sessionId, 'adding', 'Adding product…', ['product' => $vendProductId, 'count' => $cnt]);

            $r = $this->ls->addConsignmentProduct($vendId, $vendProductId, $cnt);
            if ($r['ok']) { $added++; }
            else {
                $failed++;
                $this->progressUpsert($transferId, $sessionId, 'error', 'Product add failed', ['product' => $vendProductId, 'response' => $r]);
            }
        }

        if ($failed === 0) {
            $this->progressUpsert($transferId, $sessionId, 'finalizing', 'Marking consignment SENT');
            $s = $this->ls->updateConsignmentStatus($vendId, 'SENT');
            if (!$s['ok']) {
                $this->progressUpsert($transferId, $sessionId, 'error', 'Failed to mark SENT', ['response' => $s]);
                throw new \RuntimeException('Lightspeed SENT update failed: ' . ($s['raw'] ?? ''));
            }
            $this->pdo->prepare("UPDATE transfers SET state='SENT', updated_at=NOW() WHERE id=?")->execute([$transferId]);
            $this->progressUpsert($transferId, $sessionId, 'completed', 'Upload complete', ['added' => $added]);
            return ['success' => true, 'consignment_id' => $vendId, 'added' => $added];
        }

        $this->progressUpsert($transferId, $sessionId, 'failed', 'Upload completed with errors', ['added' => $added, 'failed' => $failed]);
        return ['success' => false, 'consignment_id' => $vendId, 'added' => $added, 'failed' => $failed];
    }

    // ---------- Helpers ----------

    public function progressUpsert(int $transferId, string $sessionId, string $status, string $message, array $meta = []): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO consignment_upload_progress
                (transfer_id, session_id, status, message, meta_json, updated_at, created_at)
            VALUES (:tid, :sid, :st, :msg, :meta, NOW(), NOW())
            ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                meta_json=VALUES(meta_json), updated_at=NOW()
        ");
        $stmt->execute([
            ':tid' => $transferId,
            ':sid' => $sessionId,
            ':st'  => $status,
            ':msg' => $message,
            ':meta'=> json_encode($meta, JSON_UNESCAPED_UNICODE)
        ]);
    }

    public static function resolveLightspeedToken(\PDO $pdo): string
    {
        // ENV first
        $envs = ['VEND_API_TOKEN', 'LIGHTSPEED_API_TOKEN'];
        foreach ($envs as $k) {
            $v = getenv($k);
            if (is_string($v) && $v !== '') return $v;
        }
        // CIS config table fallback (key variants)
        $keys = ['LIGHTSPEED_API_TOKEN','VEND_API_TOKEN','vend_api_token','lightspeed_api_token'];
        $in = implode(',', array_fill(0, count($keys), '?'));
        $q = $pdo->prepare("SELECT config_value FROM cis_config WHERE config_key IN ($in) ORDER BY updated_at DESC LIMIT 1");
        $q->execute($keys);
        $row = $q->fetch(\PDO::FETCH_ASSOC);
        $val = (string)($row['config_value'] ?? '');
        if ($val === '') throw new \RuntimeException('Lightspeed API token not configured');
        return $val;
    }

    public static function resolveVendBaseUrl(\PDO $pdo): string
    {
        $envs = ['VEND_BASE_URL','LIGHTSPEED_BASE_URL'];
        foreach ($envs as $k) {
            $v = getenv($k);
            if (is_string($v) && $v !== '') return rtrim($v, '/');
        }
        // Optional CIS config override
        try {
            $q = $pdo->query("SELECT config_value FROM cis_config WHERE config_key='VEND_BASE_URL' ORDER BY updated_at DESC LIMIT 1");
            $val = (string)($q?->fetchColumn() ?: '');
            if ($val !== '') return rtrim($val, '/');
        } catch (\Throwable) {}
        return 'https://vapeshed.retail.lightspeed.app/api/2.0';
    }

    public static function ensureProgressSchema(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS consignment_upload_progress (
              transfer_id BIGINT NOT NULL,
              session_id  VARCHAR(64) NOT NULL,
              status      VARCHAR(32) NOT NULL,
              message     VARCHAR(255) NOT NULL,
              meta_json   JSON NULL,
              updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (transfer_id, session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
