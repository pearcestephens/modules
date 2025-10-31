<?php
/**
 * Sync Vend Sales Payments
 *
 * Extracts payment data from vend_sales.payments JSON field
 * and populates vend_sales_payments table for faster queries
 *
 * This syncs ALL sales (customers and staff)
 *
 * Run via: php sync-vend-sales-payments.php
 * Or schedule via cron: 0 * * * * php sync-vend-sales-payments.php
 */

declare(strict_types=1);

// Load module bootstrap
require_once __DIR__ . '/../bootstrap.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting vend_sales_payments sync...\n";

/**
 * Convert Vend ISO 8601 datetime to MySQL format
 *
 * @param string|null $date Date string in format like '2025-10-28T06:03:53+00:00'
 * @return string|null MySQL datetime format 'Y-m-d H:i:s' or null
 */
function convertVendDate(?string $date): ?string
{
    if (empty($date)) {
        return null;
    }

    try {
        $dt = new DateTime($date);
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

try {
    $pdo = cis_resolve_pdo();
    $pdo->beginTransaction();

    // Get all vend_sales with payments in last 365 days (1 year)
    $stmt = $pdo->query("
        SELECT
            vs.id as vend_sale_id,
            vs.customer_id as vend_customer_id,
            vs.total_price as sale_total,
            vs.sale_date as payment_date,
            vs.payments,
            vs.status as sale_status,
            vs.outlet_id,
            vs.register_id,
            vo.name as outlet_name
        FROM vend_sales vs
        LEFT JOIN vend_outlets vo ON vs.outlet_id = vo.id
        WHERE vs.sale_date >= DATE_SUB(NOW(), INTERVAL 365 DAY)
            AND vs.payments IS NOT NULL
            AND vs.payments != '[]'
            AND vs.payments != ''
        ORDER BY vs.sale_date DESC
    ");

    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $processed = 0;
    $inserted = 0;
    $skipped = 0;
    $total_payments_extracted = 0;

    echo "Found " . count($sales) . " sales to process...\n";

    // Prepare insert statement for vend_sales_payments
    $insert_stmt = $pdo->prepare("
        INSERT INTO vend_sales_payments
        (vend_sale_id, vend_customer_id, payment_id, payment_type_id, retailer_payment_type_id,
         amount, name, payment_date, register_id, register_open_sequence_id, outlet_id,
         surcharge, source_id, deleted_at, sale_date, outlet_name, sale_status, sale_total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            name = VALUES(name),
            payment_date = VALUES(payment_date),
            register_id = VALUES(register_id),
            register_open_sequence_id = VALUES(register_open_sequence_id),
            outlet_id = VALUES(outlet_id),
            surcharge = VALUES(surcharge),
            source_id = VALUES(source_id),
            deleted_at = VALUES(deleted_at),
            sale_status = VALUES(sale_status),
            sale_total = VALUES(sale_total),
            updated_at = NOW()
    ");

    // Clear existing records for these sales first to avoid duplicates
    $delete_stmt = $pdo->prepare("DELETE FROM vend_sales_payments WHERE vend_sale_id = ?");

    foreach ($sales as $sale) {
        $processed++;

        // Parse payments JSON
        $payments_json = json_decode($sale['payments'], true);
        if (empty($payments_json) || !is_array($payments_json)) {
            $skipped++;
            continue;
        }

        // Delete existing records for this sale
        $delete_stmt->execute([$sale['vend_sale_id']]);

        // Process each payment in the JSON array
        foreach ($payments_json as $payment) {
            // Extract payment details from Vend JSON
            $payment_id = $payment['id'] ?? null;
            $payment_type_id = $payment['payment_type_id'] ?? null;
            $retailer_payment_type_id = $payment['retailer_payment_type_id'] ?? null;
            $amount = isset($payment['amount']) ? (float)$payment['amount'] : 0.0;
            $name = $payment['name'] ?? 'Unknown';
            $payment_date = convertVendDate($payment['payment_date'] ?? $sale['sale_date']);

            // Additional Vend fields
            $register_id = $payment['register_id'] ?? $sale['register_id'];
            $register_open_sequence_id = $payment['register_open_sequence_id'] ?? null;
            $outlet_id = $payment['outlet_id'] ?? $sale['outlet_id'];
            $surcharge = isset($payment['surcharge']) ? (float)$payment['surcharge'] : null;
            $source_id = $payment['source_id'] ?? null;
            $deleted_at = convertVendDate($payment['deleted_at'] ?? null);

            // Insert payment record
            $insert_stmt->execute([
                $sale['vend_sale_id'],         // vend_sale_id
                $sale['vend_customer_id'],     // vend_customer_id
                $payment_id,                   // payment_id
                $payment_type_id,              // payment_type_id
                $retailer_payment_type_id,     // retailer_payment_type_id
                $amount,                       // amount
                $name,                         // name
                $payment_date,                 // payment_date (converted)
                $register_id,                  // register_id
                $register_open_sequence_id,    // register_open_sequence_id
                $outlet_id,                    // outlet_id
                $surcharge,                    // surcharge
                $source_id,                    // source_id
                $deleted_at,                   // deleted_at (converted)
                convertVendDate($sale['sale_date']), // sale_date (converted)
                $sale['outlet_name'],          // outlet_name
                $sale['sale_status'],          // sale_status
                $sale['sale_total']            // sale_total
            ]);

            if ($insert_stmt->rowCount() > 0) {
                $inserted++;
            }

            $total_payments_extracted++;
        }

        // Progress indicator every 100 sales
        if ($processed % 100 == 0) {
            echo "\rProcessed: $processed sales | Extracted: $total_payments_extracted payments | Inserted: $inserted | Skipped: $skipped";
        }
    }

    echo "\n";

    // Clean up old records (>1 year)
    $cleanup_stmt = $pdo->prepare("
        DELETE FROM vend_sales_payments
        WHERE sale_date < DATE_SUB(NOW(), INTERVAL 365 DAY)
    ");
    $cleanup_stmt->execute();
    $deleted_old = $cleanup_stmt->rowCount();

    $pdo->commit();

    echo "[" . date('Y-m-d H:i:s') . "] Sync complete!\n";
    echo "  Total sales processed: " . number_format($processed) . "\n";
    echo "  Total payments extracted: " . number_format($total_payments_extracted) . "\n";
    echo "  Inserted: " . number_format($inserted) . "\n";
    echo "  Skipped: " . number_format($skipped) . "\n";
    echo "  Cleaned up $deleted_old old records (>1 year)\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "[ERROR] Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Done!\n";
