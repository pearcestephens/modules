<?php
/**
 * BANK TRANSACTIONS MODULE - COMPREHENSIVE DIAGNOSTIC
 *
 * Checks everything: files, tables, endpoints, permissions
 */

// Bootstrap
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Transactions - Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; line-height: 1.6; }
        .section { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .pass { color: #28a745; font-weight: bold; }
        .fail { color: #dc3545; font-weight: bold; }
        .warn { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; border-bottom: 2px solid #6c757d; padding-bottom: 8px; margin-top: 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; border-left: 4px solid #007bff; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #e9ecef; font-weight: bold; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <h1>🏦 Bank Transactions Module - Comprehensive Diagnostic</h1>
    <p class="info">Generated: <?= date('Y-m-d H:i:s') ?></p>

    <?php
    $passCount = 0;
    $failCount = 0;
    $warnCount = 0;

    function testPass($msg) {
        global $passCount;
        $passCount++;
        return "<span class='pass'>✓</span> $msg<br>";
    }

    function testFail($msg) {
        global $failCount;
        $failCount++;
        return "<span class='fail'>✗</span> $msg<br>";
    }

    function testWarn($msg) {
        global $warnCount;
        $warnCount++;
        return "<span class='warn'>⚠</span> $msg<br>";
    }

    // Get database connection
    try {
        $db = \CIS\Base\Database::getConnection();
        echo testPass("Database connection established");
    } catch (Exception $e) {
        echo testFail("Database connection failed: " . $e->getMessage());
        $db = null;
    }
    ?>

    <div class="section">
        <h2>1. FILE STRUCTURE CHECK</h2>
        <?php
        $requiredFiles = [
            'bootstrap.php' => 'Module initialization',
            'index.php' => 'Main entry point/router',
            'controllers/BaseController.php' => 'Base controller',
            'controllers/DashboardController.php' => 'Dashboard controller',
            'controllers/TransactionController.php' => 'Transaction controller',
            'controllers/MatchingController.php' => 'Matching controller',
            'models/BaseModel.php' => 'Base model',
            'models/TransactionModel.php' => 'Transaction data access',
            'models/OrderModel.php' => 'Order data access',
            'models/PaymentModel.php' => 'Payment data access',
            'models/AuditLogModel.php' => 'Audit log access',
            'views/layout.php' => 'Master layout',
            'views/dashboard.php' => 'Dashboard view',
            'views/transaction-list.php' => 'Transaction list view',
            'api/dashboard-metrics.php' => 'Dashboard API',
            'api/match-suggestions.php' => 'Match suggestions API',
            'api/auto-match-single.php' => 'Auto-match single API',
            'api/auto-match-all.php' => 'Auto-match all API',
            'api/bulk-auto-match.php' => 'Bulk auto-match API',
            'api/bulk-send-review.php' => 'Bulk send review API',
            'api/reassign-payment.php' => 'Reassign payment API',
            'api/export.php' => 'Export API',
            'api/settings.php' => 'Settings API',
            'assets/css/transactions.css' => 'Stylesheet',
            'assets/js/dashboard.js' => 'Dashboard JavaScript',
            'assets/js/transaction-list.js' => 'Transaction list JavaScript',
        ];

        foreach ($requiredFiles as $file => $description) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                echo testPass("$file - $description");
            } else {
                echo testFail("$file MISSING - $description");
            }
        }
        ?>
    </div>

    <?php if ($db): ?>
    <div class="section">
        <h2>2. DATABASE TABLES CHECK</h2>
        <?php
        $requiredTables = [
            'bank_transactions_current' => 'Current/active bank transactions',
            'bank_transactions_archive' => 'Archived bank transactions',
            'bank_audit_trail' => 'Audit trail for changes',
            'bank_manual_reviews' => 'Manual review queue',
            'orders' => 'Orders from Vend',
            'orders_invoices' => 'Order invoices/payments',
        ];

        foreach ($requiredTables as $table => $description) {
            try {
                $stmt = $db->query("SHOW TABLES LIKE '$table'");
                $exists = $stmt->fetch();

                if ($exists) {
                    $countStmt = $db->query("SELECT COUNT(*) as cnt FROM `$table`");
                    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
                    echo testPass("$table ($count rows) - $description");
                } else {
                    echo testFail("$table MISSING - $description");
                }
            } catch (Exception $e) {
                echo testFail("$table ERROR: " . $e->getMessage());
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>3. TABLE STRUCTURE CHECK - bank_transactions_current</h2>
        <?php
        try {
            $stmt = $db->query("DESCRIBE bank_transactions_current");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $requiredColumns = [
                'id', 'transaction_date', 'transaction_reference', 'transaction_name',
                'transaction_type', 'amount', 'status', 'confidence_score',
                'matched_at', 'matched_by', 'order_id', 'payment_id'
            ];

            $actualColumns = array_column($columns, 'Field');
            $missing = array_diff($requiredColumns, $actualColumns);

            if (empty($missing)) {
                echo testPass("All required columns present (" . count($actualColumns) . " total)");
                echo "<details><summary>Show columns</summary><pre>";
                foreach ($columns as $col) {
                    echo sprintf("%-30s %-20s %s\n", $col['Field'], $col['Type'], $col['Null']);
                }
                echo "</pre></details>";
            } else {
                echo testFail("Missing columns: " . implode(', ', $missing));
            }
        } catch (Exception $e) {
            echo testFail("Cannot check table structure: " . $e->getMessage());
        }
        ?>
    </div>

    <div class="section">
        <h2>4. SAMPLE DATA CHECK</h2>
        <?php
        try {
            $stmt = $db->query("SELECT * FROM bank_transactions_current ORDER BY transaction_date DESC LIMIT 5");
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($samples) > 0) {
                echo testPass(count($samples) . " sample transactions found");
                echo "<table>";
                echo "<tr><th>ID</th><th>Date</th><th>Reference</th><th>Amount</th><th>Status</th><th>Type</th></tr>";
                foreach ($samples as $row) {
                    $statusClass = $row['status'] === 'matched' ? 'badge-success' :
                                  ($row['status'] === 'unmatched' ? 'badge-danger' : 'badge-warning');
                    echo "<tr>";
                    echo "<td>{$row['id']}</td>";
                    echo "<td>{$row['transaction_date']}</td>";
                    echo "<td>" . htmlspecialchars($row['transaction_reference'] ?? 'N/A') . "</td>";
                    echo "<td>\$" . number_format($row['amount'], 2) . "</td>";
                    echo "<td><span class='status-badge {$statusClass}'>{$row['status']}</span></td>";
                    echo "<td>{$row['transaction_type']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo testWarn("No transactions found in table (empty table)");
            }
        } catch (Exception $e) {
            echo testFail("Cannot query sample data: " . $e->getMessage());
        }
        ?>
    </div>

    <div class="section">
        <h2>5. API ENDPOINT ACCESSIBILITY</h2>
        <?php
        $endpoints = [
            'dashboard-metrics.php',
            'match-suggestions.php',
            'auto-match-single.php',
            'auto-match-all.php',
            'bulk-auto-match.php',
            'bulk-send-review.php',
            'reassign-payment.php',
            'export.php',
            'settings.php'
        ];

        foreach ($endpoints as $endpoint) {
            $path = __DIR__ . '/api/' . $endpoint;
            if (file_exists($path)) {
                // Check if file is readable and has PHP opening tag
                $content = file_get_contents($path);
                if (strpos($content, '<?php') !== false) {
                    echo testPass("$endpoint - Valid PHP file");
                } else {
                    echo testWarn("$endpoint - File exists but may not be valid PHP");
                }
            } else {
                echo testFail("$endpoint - File missing");
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>6. MODEL METHODS CHECK</h2>
        <?php
        require_once __DIR__ . '/models/TransactionModel.php';

        $model = new \CIS\BankTransactions\Models\TransactionModel($db);
        $requiredMethods = [
            'findUnmatched', 'findById', 'getDashboardMetrics',
            'getTypeBreakdown', 'getRecentMatches', 'getAutoMatchRate'
        ];

        foreach ($requiredMethods as $method) {
            if (method_exists($model, $method)) {
                echo testPass("TransactionModel::$method() exists");
            } else {
                echo testFail("TransactionModel::$method() MISSING");
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>7. MODULE CONSTANTS CHECK</h2>
        <?php
        $constants = [
            'BANK_TRANSACTIONS_MODULE_PATH',
            'BANK_TRANSACTIONS_MODULE_URL',
            'BANK_TRANSACTIONS_VERSION',
            'BANK_TRANSACTIONS_CONFIDENCE_THRESHOLD',
            'BANK_TRANSACTIONS_CONFIDENCE_MARGIN'
        ];

        foreach ($constants as $const) {
            if (defined($const)) {
                echo testPass("$const = " . constant($const));
            } else {
                echo testFail("$const NOT DEFINED");
            }
        }
        ?>
    </div>
    <?php endif; ?>

    <div class="section">
        <h2>8. ROUTING TEST</h2>
        <?php
        $routes = ['dashboard', 'list', 'detail', 'auto-match', 'manual-match'];

        echo "<p>Testing route switches in index.php:</p>";
        foreach ($routes as $route) {
            // Check if route is handled in index.php
            $indexContent = file_get_contents(__DIR__ . '/index.php');
            if (strpos($indexContent, "case '$route':") !== false) {
                echo testPass("Route '$route' defined");
            } else {
                echo testWarn("Route '$route' not explicitly defined (may be handled by default)");
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>📊 DIAGNOSTIC SUMMARY</h2>
        <table>
            <tr>
                <th>Status</th>
                <th>Count</th>
                <th>Percentage</th>
            </tr>
            <tr>
                <td><span class="pass">✓ Pass</span></td>
                <td><?= $passCount ?></td>
                <td><?= $passCount + $failCount + $warnCount > 0 ? round(($passCount / ($passCount + $failCount + $warnCount)) * 100, 1) : 0 ?>%</td>
            </tr>
            <tr>
                <td><span class="fail">✗ Fail</span></td>
                <td><?= $failCount ?></td>
                <td><?= $passCount + $failCount + $warnCount > 0 ? round(($failCount / ($passCount + $failCount + $warnCount)) * 100, 1) : 0 ?>%</td>
            </tr>
            <tr>
                <td><span class="warn">⚠ Warning</span></td>
                <td><?= $warnCount ?></td>
                <td><?= $passCount + $failCount + $warnCount > 0 ? round(($warnCount / ($passCount + $failCount + $warnCount)) * 100, 1) : 0 ?>%</td>
            </tr>
            <tr style="font-weight: bold; background: #e9ecef;">
                <td>TOTAL</td>
                <td><?= $passCount + $failCount + $warnCount ?></td>
                <td>100%</td>
            </tr>
        </table>

        <?php if ($failCount === 0 && $warnCount === 0): ?>
            <p style="color: #28a745; font-size: 18px; font-weight: bold;">🎉 ALL CHECKS PASSED! Module is ready for testing.</p>
        <?php elseif ($failCount === 0): ?>
            <p style="color: #ffc107; font-size: 18px; font-weight: bold;">⚠️ Module functional but has warnings. Review warnings above.</p>
        <?php else: ?>
            <p style="color: #dc3545; font-size: 18px; font-weight: bold;">❌ CRITICAL ISSUES FOUND. Fix failures above before proceeding.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🔗 QUICK LINKS</h2>
        <ul>
            <li><a href="index.php?route=dashboard">Dashboard</a></li>
            <li><a href="index.php?route=list">Transaction List</a></li>
            <li><a href="api/dashboard-metrics.php">Dashboard Metrics API</a></li>
            <li><a href="debug-dashboard.php">Debug Dashboard</a></li>
        </ul>
    </div>

    <p style="text-align: center; color: #6c757d; margin-top: 40px;">
        Bank Transactions Module v<?= BANK_TRANSACTIONS_VERSION ?? '1.0.0' ?> |
        Diagnostic Tool v1.0 |
        <?= date('Y-m-d H:i:s') ?>
    </p>
</body>
</html>
