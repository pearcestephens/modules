<?php
/**
 * Sync Sales Payments from Vend Sales
 * 
 * Extracts payment data from vend_sales.payments JSON field
 * and populates sales_payments table for faster queries
 * 
 * This syncs ALL sales (customers and staff), not just staff
 * 
 * Run via: php sync-payments.php
 * Or schedule via cron: 0 * * * * php sync-payments.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../app.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting sales payments sync...\n";

try {
    $pdo = get_pdo();
    $pdo->beginTransaction();
    
    // Get all vend_sales with payments in last 90 days
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
        WHERE vs.sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
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
    
    // Prepare insert statement with ALL fields from actual Vend JSON structure
    $insert_stmt = $pdo->prepare("
        INSERT INTO sales_payments 
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
            updated_at = NOW()
    ");
    
    // Clear existing records for these sales first to avoid duplicates
    $delete_stmt = $pdo->prepare("DELETE FROM sales_payments WHERE vend_sale_id = ?");
    
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
        
        // Process each payment in the JSON array - extract ALL fields from actual Vend structure
        foreach ($payments_json as $payment) {
            // Extract ALL payment details from Vend JSON (verified structure from database)
            $payment_id = $payment['id'] ?? null;
            $payment_type_id = $payment['payment_type_id'] ?? null;
            $retailer_payment_type_id = $payment['retailer_payment_type_id'] ?? null;
            $amount = isset($payment['amount']) ? (float)$payment['amount'] : 0.0;
            $name = $payment['name'] ?? 'Unknown';
            $payment_date = $payment['payment_date'] ?? $sale['payment_date']; // Use sale_date as fallback
            
            // Additional Vend fields (verified from actual database query)
            $register_id = $payment['register_id'] ?? $sale['register_id']; // Fallback to sale register
            $register_open_sequence_id = $payment['register_open_sequence_id'] ?? null;
            $outlet_id = $payment['outlet_id'] ?? $sale['outlet_id']; // Fallback to sale outlet
            $surcharge = isset($payment['surcharge']) ? (float)$payment['surcharge'] : null;
            $source_id = $payment['source_id'] ?? null;
            $deleted_at = $payment['deleted_at'] ?? null;
            
            // Insert payment record with ALL fields
            $insert_stmt->execute([
                $sale['vend_sale_id'],         // vend_sale_id
                $sale['vend_customer_id'],     // vend_customer_id
                $payment_id,                   // payment_id (Vend's payment UUID)
                $payment_type_id,              // payment_type_id
                $retailer_payment_type_id,     // retailer_payment_type_id
                $amount,                       // amount
                $name,                         // name (e.g., "Cash", "Internet Banking")
                $payment_date,                 // payment_date from JSON
                $register_id,                  // register_id from JSON
                $register_open_sequence_id,    // register_open_sequence_id from JSON
                $outlet_id,                    // outlet_id from JSON (fallback to sale outlet)
                $surcharge,                    // surcharge from JSON
                $source_id,                    // source_id from JSON
                $deleted_at,                   // deleted_at from JSON
                $sale['payment_date'],         // sale_date from vend_sales
                $sale['outlet_name'],          // outlet_name
                $sale['sale_status'],          // sale_status
                $sale['sale_total']            // sale_total
            ]);
            
            if ($insert_stmt->rowCount() > 0) {
                $inserted++;
                $total_payments_extracted++;
            }
        }
        
        // Progress indicator
        if ($processed % 100 == 0) {
            echo "  Processed: $processed sales | Extracted: $total_payments_extracted payments | Inserted: $inserted | Skipped: $skipped\n";
        }
    }
    
    $pdo->commit();
    
    echo "[" . date('Y-m-d H:i:s') . "] Sync complete!\n";
    echo "  Total sales processed: $processed\n";
    echo "  Total payments extracted: $total_payments_extracted\n";
    echo "  Inserted: $inserted\n";
    echo "  Skipped: $skipped\n";
    
    // Clean up old records (older than 90 days)
    $cleanup_stmt = $pdo->query("
        DELETE FROM sales_payments 
        WHERE payment_date < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $deleted = $cleanup_stmt->rowCount();
    
    if ($deleted > 0) {
        echo "  Cleaned up $deleted old records (>90 days)\n";
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Done!\n";
