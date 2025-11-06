<?php
/**
 * Quick Register Deposit Recording Script
 *
 * Usage: Update the deposits in the $deposits array below, then run this script
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// ============================================================================
// CONFIGURE YOUR DEPOSITS HERE
// ============================================================================

$deposits = [
    // [user_id, amount, description]
    // Example:
    // [45, 150.00, 'Register deposit - Week ending Nov 5'],

    // ADD YOUR DEPOSITS BELOW:

];

// ============================================================================
// DO NOT EDIT BELOW THIS LINE
// ============================================================================

if (empty($deposits)) {
    die("\n❌ ERROR: No deposits configured!\n\nEdit this file and add deposits to the \$deposits array.\n\n");
}

echo "\n╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║              REGISTER DEPOSIT RECORDING SCRIPT                               ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

$db = get_db();
$success = 0;
$errors = 0;

foreach ($deposits as $deposit) {
    if (count($deposit) !== 3) {
        echo "⚠️  SKIPPED: Invalid deposit format (needs [user_id, amount, description])\n";
        $errors++;
        continue;
    }

    [$userId, $amount, $description] = $deposit;

    // Validate
    if (!is_numeric($userId) || $userId <= 0) {
        echo "⚠️  SKIPPED: Invalid user_id: $userId\n";
        $errors++;
        continue;
    }

    if (!is_numeric($amount) || $amount <= 0) {
        echo "⚠️  SKIPPED: Invalid amount for user $userId: $amount\n";
        $errors++;
        continue;
    }

    // Check user exists
    $stmt = $db->prepare("SELECT id, username, full_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "⚠️  SKIPPED: User $userId not found\n";
        $errors++;
        continue;
    }

    // Get current balance
    $stmt = $db->prepare("SELECT balance FROM staff_accounts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    $oldBalance = $account ? (float)$account['balance'] : 0.00;
    $newBalance = $oldBalance + $amount;

    // Begin transaction
    $db->beginTransaction();

    try {
        // Update or create staff account
        if ($account) {
            $stmt = $db->prepare("
                UPDATE staff_accounts
                SET balance = balance + ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$amount, $userId]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO staff_accounts (user_id, balance, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");
            $stmt->execute([$userId, $amount]);
        }

        // Record transaction
        $stmt = $db->prepare("
            INSERT INTO staff_payment_transactions
            (user_id, type, amount, description, status, created_at)
            VALUES (?, 'deposit', ?, ?, 'completed', NOW())
        ");
        $stmt->execute([$userId, $amount, $description]);

        $db->commit();

        echo "✅ RECORDED: {$user['full_name']} (#{$userId}) - \${$amount} - $description\n";
        echo "   Old Balance: \$" . number_format($oldBalance, 2) . " → New Balance: \$" . number_format($newBalance, 2) . "\n\n";

        $success++;

    } catch (Exception $e) {
        $db->rollBack();
        echo "❌ ERROR: Failed to record deposit for user $userId: " . $e->getMessage() . "\n\n";
        $errors++;
    }
}

echo "\n╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                            SUMMARY                                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";
echo "✅ Successfully recorded: $success deposits\n";
if ($errors > 0) {
    echo "❌ Errors/Skipped: $errors\n";
}
echo "\n";

if ($success > 0) {
    echo "💡 View updated accounts at:\n";
    echo "   https://staff.vapeshed.co.nz/modules/staff-accounts/\n\n";
}
