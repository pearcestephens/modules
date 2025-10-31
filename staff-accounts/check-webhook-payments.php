<?php
/**
 * Check Webhook Payments Structure
 * Queries webhooks_log to see actual payment data from Vend
 */

require_once __DIR__ . '/bootstrap.php';
require_once ROOT_PATH . '/assets/functions/config.php';

$pdo = cis_resolve_pdo();

echo "=======================================================\n";
echo "CHECKING WEBHOOKS_LOG FOR PAYMENT DATA STRUCTURE\n";
echo "=======================================================\n\n";

// Get a recent sale.update webhook with payment data
$stmt = $pdo->query("
    SELECT 
        id,
        event_type,
        payload,
        created_at
    FROM webhooks_log 
    WHERE event_type = 'sale.update' 
    AND payload LIKE '%payments%'
    ORDER BY created_at DESC 
    LIMIT 3
");

$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "❌ No sale.update webhooks found with payment data.\n";
    echo "Checking if webhooks_log table exists...\n\n";
    
    $tables = $pdo->query("SHOW TABLES LIKE 'webhooks_log'")->fetchAll();
    if (empty($tables)) {
        echo "❌ webhooks_log table does not exist!\n";
    } else {
        echo "✅ webhooks_log table exists\n";
        $count = $pdo->query("SELECT COUNT(*) FROM webhooks_log")->fetchColumn();
        echo "   Total webhooks: $count\n";
    }
    exit;
}

echo "✅ Found " . count($webhooks) . " webhooks with payment data\n\n";

foreach ($webhooks as $index => $webhook) {
    echo "--- WEBHOOK #" . ($index + 1) . " ---\n";
    echo "ID: " . $webhook['id'] . "\n";
    echo "Event: " . $webhook['event_type'] . "\n";
    echo "Date: " . $webhook['created_at'] . "\n\n";
    
    $payload = json_decode($webhook['payload'], true);
    
    if (isset($payload['payload']['payments'])) {
        $payments = $payload['payload']['payments'];
        
        echo "PAYMENTS DATA:\n";
        echo json_encode($payments, JSON_PRETTY_PRINT) . "\n\n";
        
        if (!empty($payments) && is_array($payments)) {
            $firstPayment = reset($payments);
            echo "AVAILABLE FIELDS IN PAYMENT JSON:\n";
            foreach (array_keys($firstPayment) as $field) {
                $value = $firstPayment[$field];
                $type = gettype($value);
                echo "  - $field ($type): " . json_encode($value) . "\n";
            }
        }
    } else {
        echo "⚠️  No payments field in this webhook\n";
    }
    
    echo "\n";
    echo str_repeat("=", 55) . "\n\n";
}

echo "\n✅ DONE! Schema fields match above structure.\n";
