<?php
declare(strict_types=1);

// Ensures high-value indexes exist for consignment module queries
// Safe to run multiple times; checks INFORMATION_SCHEMA first

require_once __DIR__ . '/../bootstrap.php';

use PDO;

function idxExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1");
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

function ensureIndex(PDO $pdo, string $table, string $index, string $cols): void {
    if (idxExists($pdo, $table, $index)) { echo "[SKIP] $table.$index exists\n"; return; }
    $sql = "CREATE INDEX `$index` ON `$table` ($cols)";
    $pdo->exec($sql);
    echo "[OK]   Created index $table.$index on ($cols)\n";
}

$pdo = \CIS\Base\Database::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// queue_consignments
ensureIndex($pdo, 'queue_consignments', 'qc_tcat_created', 'transfer_category, created_at');
ensureIndex($pdo, 'queue_consignments', 'qc_tcat_state_created', 'transfer_category, state, created_at');
ensureIndex($pdo, 'queue_consignments', 'qc_state_created', 'state, created_at');
ensureIndex($pdo, 'queue_consignments', 'qc_user_created', 'cis_user_id, created_at');

// queue_consignment_products
ensureIndex($pdo, 'queue_consignment_products', 'qcp_consignment', 'consignment_id');

// consignment_shipments
ensureIndex($pdo, 'consignment_shipments', 'cs_transfer_created', 'transfer_id, created_at');
ensureIndex($pdo, 'consignment_shipments', 'cs_consignment_created', 'consignment_id, created_at');

// consignment_parcels
ensureIndex($pdo, 'consignment_parcels', 'cp_shipment', 'shipment_id');

// consignment_notes
ensureIndex($pdo, 'consignment_notes', 'cn_transfer_created', 'transfer_id, created_at');

echo "Done.\n";
