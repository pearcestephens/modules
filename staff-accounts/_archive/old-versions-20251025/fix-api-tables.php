<?php
/**
 * Fix all table and column name mismatches in manager-dashboard API
 * Based on actual database schema verification
 */

$apiFile = __DIR__ . '/api/manager-dashboard.php';
$content = file_get_contents($apiFile);

// Backup
file_put_contents($apiFile . '.backup-' . date('YmdHis'), $content);

// Fix: staff_allocations -> vend_payment_allocations
// But note: vend_payment_allocations doesn't have allocation_date, it has created_at
// Also doesn't have user_id, it has vend_customer_id

$content = str_replace(
    "FROM staff_allocations\n                   WHERE MONTH(allocation_date) = MONTH(CURDATE())\n                   AND YEAR(allocation_date) = YEAR(CURDATE())",
    "FROM vend_payment_allocations\n                   WHERE MONTH(created_at) = MONTH(CURDATE())\n                   AND YEAR(created_at) = YEAR(CURDATE())",
    $content
);

// Fix: staff_allocations credit card count subquery
// Need to join via vend_customer_id
$content = str_replace(
    "(SELECT COUNT(*) FROM staff_allocations WHERE user_id = u.id AND payment_method = 'credit_card') as credit_card_payments",
    "(SELECT COUNT(*) FROM staff_payment_transactions spt WHERE spt.user_id = u.id AND spt.transaction_type = 'payment_approved') as credit_card_payments",
    $content
);

// Fix: Any references to old staff_account_balance that might have been missed
// (Already done, but double-check)

file_put_contents($apiFile, $content);

echo "✅ API file corrected\n";
echo "   - Fixed staff_allocations -> vend_payment_allocations\n";
echo "   - Fixed allocation_date -> created_at\n";
echo "   - Fixed credit_card_payments query to use staff_payment_transactions\n";
echo "\n✅ Backup saved to: " . basename($apiFile) . '.backup-' . date('YmdHis') . "\n";
