<?php
/**
 * DATABASE DIAGNOSTIC TEST
 * Tests database connectivity and staff_account_reconciliation table structure
 */

// Load bootstrap
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostic Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .test { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .pass { background: #d4edda; border: 1px solid #c3e6cb; }
        .fail { background: #f8d7da; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; }
        h2 { color: #333; }
        pre { background: white; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .sql { background: #fff3cd; padding: 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Staff Accounts Database Diagnostic</h1>

    <?php
    // Test 1: Database connection
    echo '<div class="test info"><strong>TEST 1:</strong> Database Connection</div>';
    try {
        $db = get_db();
        echo '<div class="test pass">✓ Database connection successful</div>';
        echo '<pre>Database: hdgwrzntwa</pre>';
    } catch (Exception $e) {
        echo '<div class="test fail">✗ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }

    // Test 2: Table existence
    echo '<div class="test info"><strong>TEST 2:</strong> Check staff_account_reconciliation table</div>';
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'staff_account_reconciliation'");
        $table_exists = $stmt->fetch();

        if ($table_exists) {
            echo '<div class="test pass">✓ Table staff_account_reconciliation exists</div>';
        } else {
            echo '<div class="test fail">✗ Table staff_account_reconciliation does NOT exist</div>';
            echo '<div class="test info">Available tables:</div>';
            $stmt = $db->query("SHOW TABLES");
            echo '<pre>';
            while ($table = $stmt->fetch()) {
                echo htmlspecialchars($table[0]) . "\n";
            }
            echo '</pre>';
            exit;
        }
    } catch (Exception $e) {
        echo '<div class="test fail">✗ Error checking table: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }

    // Test 3: Table structure
    echo '<div class="test info"><strong>TEST 3:</strong> Table Structure</div>';
    try {
        $stmt = $db->query("DESCRIBE staff_account_reconciliation");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo '<div class="test pass">✓ Table structure retrieved</div>';
        echo '<h3>Columns in staff_account_reconciliation:</h3>';
        echo '<pre>';
        foreach ($columns as $col) {
            echo sprintf("%-30s %-15s %-10s %-10s\n",
                $col['Field'],
                $col['Type'],
                $col['Null'],
                $col['Key']
            );
        }
        echo '</pre>';

        // Check for critical columns
        $column_names = array_column($columns, 'Field');
        $required = ['account_id', 'vend_user_id', 'employee_name', 'vend_balance'];
        $missing = array_diff($required, $column_names);

        if (empty($missing)) {
            echo '<div class="test pass">✓ All required columns present</div>';
        } else {
            echo '<div class="test fail">✗ Missing columns: ' . implode(', ', $missing) . '</div>';
        }

    } catch (Exception $e) {
        echo '<div class="test fail">✗ Error reading structure: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    // Test 4: Row count
    echo '<div class="test info"><strong>TEST 4:</strong> Data Check</div>';
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM staff_account_reconciliation");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $row['count'];

        echo '<div class="test pass">✓ Query successful</div>';
        echo '<pre>Total rows: ' . $count . '</pre>';

        if ($count > 0) {
            echo '<h3>Sample Record:</h3>';
            $stmt = $db->query("SELECT * FROM staff_account_reconciliation LIMIT 1");
            $sample = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<pre>' . print_r($sample, true) . '</pre>';
        } else {
            echo '<div class="test info">ℹ Table is empty (no records)</div>';
        }

    } catch (Exception $e) {
        echo '<div class="test fail">✗ Error counting rows: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    // Test 5: User session check
    echo '<div class="test info"><strong>TEST 5:</strong> Session Check</div>';
    if (isset($_SESSION['userID'])) {
        echo '<div class="test pass">✓ User logged in</div>';
        echo '<pre>User ID: ' . $_SESSION['userID'] . '</pre>';

        // Try to fetch this user's account
        try {
            $stmt = $db->prepare("
                SELECT * FROM staff_account_reconciliation
                WHERE vend_user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['userID']]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($account) {
                echo '<div class="test pass">✓ Account record found for logged-in user</div>';
                echo '<h3>Your Account Data:</h3>';
                echo '<pre>' . print_r($account, true) . '</pre>';
            } else {
                echo '<div class="test fail">✗ No account record for user ID: ' . $_SESSION['userID'] . '</div>';
                echo '<div class="test info">This user needs an account record created</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test fail">✗ Error fetching user account: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

    } else {
        echo '<div class="test fail">✗ No user logged in (SESSION userID not set)</div>';
    }

    // Test 6: Sample query test
    echo '<div class="test info"><strong>TEST 6:</strong> Test My Account Query</div>';
    echo '<div class="sql">SQL: SELECT * FROM staff_account_reconciliation WHERE vend_user_id = ? LIMIT 1</div>';

    if (isset($_SESSION['userID'])) {
        try {
            $stmt = $db->prepare("
                SELECT
                    account_id,
                    vend_user_id,
                    employee_name,
                    vend_balance,
                    total_allocated,
                    total_payments_ytd,
                    last_reconciled_at,
                    last_payment_date,
                    vend_balance_updated_at
                FROM staff_account_reconciliation
                WHERE vend_user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['userID']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                echo '<div class="test pass">✓ Query executed successfully</div>';
                echo '<pre>' . print_r($result, true) . '</pre>';
            } else {
                echo '<div class="test fail">✗ Query returned no results for user ' . $_SESSION['userID'] . '</div>';
            }

        } catch (Exception $e) {
            echo '<div class="test fail">✗ Query failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        echo '<div class="test info">ℹ Skipped (no user logged in)</div>';
    }
    ?>

    <hr>
    <p><a href="index.php">← Back to Staff Accounts</a> | <a href="views/my-account.php">Test My Account Page</a></p>
</body>
</html>
