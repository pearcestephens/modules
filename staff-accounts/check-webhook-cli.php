<?php
/**
 * CLI Script - Check Webhook Payments Structure
 */

// Database credentials
$host = '127.0.0.1';
$dbname = 'jcepnzzkmj';
$username = 'jcepnzzkmj';
$password = 'wprKh9Jq63';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=======================================================\n";
    echo "CHECKING WEBHOOKS_LOG FOR PAYMENT DATA STRUCTURE\n";
    echo "=======================================================\n\n";

    // Get recent sale.update webhooks with payment data
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

        echo "\n" . str_repeat("=", 55) . "\n\n";
    }

    echo "\n✅ DONE! Check if schema fields match above structure.\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // ✅ CRITICAL FIX: Always cleanup PDO connection to prevent connection leaks
    $pdo = null;
}
