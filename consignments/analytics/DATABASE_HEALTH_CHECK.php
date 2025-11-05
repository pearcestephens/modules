<?php
/**
 * DATABASE HEALTH CHECKER
 *
 * Comprehensive database analysis tool
 * Tests all tables, indexes, relationships, and performance
 */

require_once __DIR__ . '/../../../config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Health Check - Analytics System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
            font-family: 'Segoe UI', sans-serif;
        }

        .health-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-section {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .health-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .health-card h5 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .status-indicator {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-healthy { background: #28a745; }
        .status-warning { background: #ffc107; }
        .status-critical { background: #dc3545; }

        .metric-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .metric-row:last-child {
            border-bottom: none;
        }

        .metric-label {
            color: #6c757d;
            font-size: 14px;
        }

        .metric-value {
            font-weight: 600;
            color: #333;
        }

        .table-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .index-list {
            font-size: 12px;
            color: #495057;
            margin-top: 10px;
        }

        .badge-custom {
            font-size: 11px;
            padding: 4px 8px;
        }

        .query-performance {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            font-size: 13px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="health-container">
        <div class="header-section">
            <h1><i class="bi bi-heart-pulse"></i> Database Health Check</h1>
            <p class="text-muted mb-0">Comprehensive analysis of analytics database structure and performance</p>
        </div>

        <?php

        // Get database name
        $dbResult = $conn->query("SELECT DATABASE() as db_name");
        $dbName = $dbResult->fetch_assoc()['db_name'];

        echo "<div class='alert alert-info'>";
        echo "<i class='bi bi-database'></i> <strong>Database:</strong> $dbName";
        echo " | <strong>Server:</strong> " . $conn->host_info;
        echo "</div>";

        // Connection Health
        echo "<div class='health-card'>";
        echo "<h5><i class='bi bi-plug'></i> Connection Health</h5>";

        $connectionHealthy = $conn->ping();
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>Connection Status</span>";
        echo "<span class='metric-value'>";
        echo "<span class='status-indicator " . ($connectionHealthy ? "status-healthy" : "status-critical") . "'></span> ";
        echo $connectionHealthy ? "Connected" : "Disconnected";
        echo "</span></div>";

        $charsetResult = $conn->query("SHOW VARIABLES LIKE 'character_set_connection'");
        $charset = $charsetResult->fetch_assoc()['Value'];
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>Character Set</span>";
        echo "<span class='metric-value'>$charset</span>";
        echo "</div>";

        $versionResult = $conn->query("SELECT VERSION() as version");
        $version = $versionResult->fetch_assoc()['version'];
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>MySQL Version</span>";
        echo "<span class='metric-value'>$version</span>";
        echo "</div>";

        echo "</div>";

        // Table Analysis
        echo "<div class='health-grid'>";

        $tables = [
            'BARCODE_SCAN_EVENTS',
            'RECEIVING_SESSIONS',
            'FRAUD_DETECTION_RULES',
            'FRAUD_ALERTS',
            'USER_ACHIEVEMENTS',
            'DAILY_PERFORMANCE_STATS',
            'LEADERBOARD_CACHE',
            'ANALYTICS_SETTINGS',
            'OUTLET_ANALYTICS_SETTINGS',
            'USER_ANALYTICS_SETTINGS',
            'TRANSFER_ANALYTICS_SETTINGS'
        ];

        foreach ($tables as $table) {
            echo "<div class='health-card'>";

            // Check if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
            $tableExists = $tableCheck->num_rows > 0;

            echo "<h5>";
            echo "<span class='status-indicator " . ($tableExists ? "status-healthy" : "status-critical") . "'></span>";
            echo "$table";
            echo "</h5>";

            if ($tableExists) {
                // Get row count
                $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
                $rowCount = $countResult->fetch_assoc()['count'];

                // Get table size
                $sizeResult = $conn->query("
                    SELECT
                        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = '$dbName'
                    AND TABLE_NAME = '$table'
                ");
                $size = $sizeResult->fetch_assoc()['size_mb'];

                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>Row Count</span>";
                echo "<span class='metric-value'>" . number_format($rowCount) . "</span>";
                echo "</div>";

                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>Table Size</span>";
                echo "<span class='metric-value'>{$size} MB</span>";
                echo "</div>";

                // Get column count
                $columnsResult = $conn->query("
                    SELECT COUNT(*) as col_count
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = '$dbName'
                    AND TABLE_NAME = '$table'
                ");
                $columnCount = $columnsResult->fetch_assoc()['col_count'];

                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>Columns</span>";
                echo "<span class='metric-value'>$columnCount</span>";
                echo "</div>";

                // Get indexes
                $indexResult = $conn->query("SHOW INDEX FROM $table");
                $indexes = [];
                while ($row = $indexResult->fetch_assoc()) {
                    $indexes[$row['Key_name']][] = $row['Column_name'];
                }

                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>Indexes</span>";
                echo "<span class='metric-value'>" . count($indexes) . "</span>";
                echo "</div>";

                if (count($indexes) > 0) {
                    echo "<div class='index-list'>";
                    echo "<strong>Index Details:</strong><br>";
                    foreach ($indexes as $indexName => $columns) {
                        $isPrimary = $indexName === 'PRIMARY';
                        $badgeClass = $isPrimary ? 'bg-primary' : 'bg-secondary';
                        echo "<span class='badge $badgeClass badge-custom me-1'>$indexName</span>";
                    }
                    echo "</div>";
                }

            } else {
                echo "<div class='alert alert-danger mb-0'>";
                echo "<i class='bi bi-exclamation-triangle'></i> Table does not exist!";
                echo "</div>";
            }

            echo "</div>";
        }

        echo "</div>";

        // View Analysis
        echo "<h4 class='mt-4'><i class='bi bi-eye'></i> Views</h4>";
        echo "<div class='health-grid'>";

        $views = ['CURRENT_RANKINGS', 'SUSPICIOUS_SCANS', 'PERFORMANCE_SUMMARY'];

        foreach ($views as $view) {
            echo "<div class='health-card'>";

            $viewCheck = $conn->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_$dbName = '$view'");
            $viewExists = $viewCheck->num_rows > 0;

            echo "<h5>";
            echo "<span class='status-indicator " . ($viewExists ? "status-healthy" : "status-critical") . "'></span>";
            echo "$view";
            echo "</h5>";

            if ($viewExists) {
                // Try to query view
                try {
                    $testResult = $conn->query("SELECT COUNT(*) as count FROM $view");
                    if ($testResult) {
                        $count = $testResult->fetch_assoc()['count'];
                        echo "<div class='metric-row'>";
                        echo "<span class='metric-label'>Rows Returned</span>";
                        echo "<span class='metric-value'>" . number_format($count) . "</span>";
                        echo "</div>";

                        echo "<div class='alert alert-success mb-0 mt-2'>";
                        echo "<i class='bi bi-check-circle'></i> View is queryable";
                        echo "</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger mb-0'>";
                    echo "<i class='bi bi-exclamation-triangle'></i> View error: " . $e->getMessage();
                    echo "</div>";
                }
            } else {
                echo "<div class='alert alert-danger mb-0'>";
                echo "<i class='bi bi-exclamation-triangle'></i> View does not exist!";
                echo "</div>";
            }

            echo "</div>";
        }

        echo "</div>";

        // Performance Tests
        echo "<h4 class='mt-4'><i class='bi bi-speedometer'></i> Performance Tests</h4>";
        echo "<div class='health-grid'>";

        // Test 1: Simple SELECT
        echo "<div class='health-card'>";
        echo "<h5>Simple SELECT Query</h5>";
        $start = microtime(true);
        $result = $conn->query("SELECT COUNT(*) as count FROM BARCODE_SCAN_EVENTS");
        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>Execution Time</span>";
        echo "<span class='metric-value'>{$duration}ms</span>";
        echo "</div>";
        $status = $duration < 100 ? 'status-healthy' : ($duration < 500 ? 'status-warning' : 'status-critical');
        echo "<div class='mt-2'><span class='status-indicator $status'></span> " . ($duration < 100 ? 'Excellent' : ($duration < 500 ? 'Acceptable' : 'Slow')) . "</div>";
        echo "</div>";

        // Test 2: JOIN Query
        echo "<div class='health-card'>";
        echo "<h5>JOIN Query</h5>";
        $start = microtime(true);
        $result = $conn->query("
            SELECT COUNT(*) as count
            FROM BARCODE_SCAN_EVENTS e
            LEFT JOIN RECEIVING_SESSIONS s ON e.transfer_id = s.transfer_id
            LIMIT 1000
        ");
        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>Execution Time</span>";
        echo "<span class='metric-value'>{$duration}ms</span>";
        echo "</div>";
        $status = $duration < 200 ? 'status-healthy' : ($duration < 1000 ? 'status-warning' : 'status-critical');
        echo "<div class='mt-2'><span class='status-indicator $status'></span> " . ($duration < 200 ? 'Excellent' : ($duration < 1000 ? 'Acceptable' : 'Slow')) . "</div>";
        echo "</div>";

        // Test 3: View Query
        echo "<div class='health-card'>";
        echo "<h5>View Query (CURRENT_RANKINGS)</h5>";
        $start = microtime(true);
        $result = $conn->query("SELECT * FROM CURRENT_RANKINGS LIMIT 10");
        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>Execution Time</span>";
        echo "<span class='metric-value'>{$duration}ms</span>";
        echo "</div>";
        $status = $duration < 300 ? 'status-healthy' : ($duration < 1000 ? 'status-warning' : 'status-critical');
        echo "<div class='mt-2'><span class='status-indicator $status'></span> " . ($duration < 300 ? 'Excellent' : ($duration < 1000 ? 'Acceptable' : 'Slow')) . "</div>";
        echo "</div>";

        // Test 4: Aggregation Query
        echo "<div class='health-card'>";
        echo "<h5>Aggregation Query</h5>";
        $start = microtime(true);
        $result = $conn->query("
            SELECT
                user_id,
                COUNT(*) as scan_count,
                AVG(time_since_last_scan_ms) as avg_speed
            FROM BARCODE_SCAN_EVENTS
            WHERE scanned_at >= CURDATE() - INTERVAL 7 DAY
            GROUP BY user_id
            LIMIT 100
        ");
        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>Execution Time</span>";
        echo "<span class='metric-value'>{$duration}ms</span>";
        echo "</div>";
        $status = $duration < 500 ? 'status-healthy' : ($duration < 2000 ? 'status-warning' : 'status-critical');
        echo "<div class='mt-2'><span class='status-indicator $status'></span> " . ($duration < 500 ? 'Excellent' : ($duration < 2000 ? 'Acceptable' : 'Slow')) . "</div>";
        echo "</div>";

        echo "</div>";

        // Data Integrity Checks
        echo "<h4 class='mt-4'><i class='bi bi-shield-check'></i> Data Integrity Checks</h4>";
        echo "<div class='health-grid'>";

        // Check 1: Orphaned scan events
        echo "<div class='health-card'>";
        echo "<h5>Orphaned Scan Events</h5>";
        $result = $conn->query("
            SELECT COUNT(*) as orphaned
            FROM BARCODE_SCAN_EVENTS e
            LEFT JOIN RECEIVING_SESSIONS s ON e.transfer_id = s.transfer_id
            WHERE e.transfer_id IS NOT NULL AND s.transfer_id IS NULL
        ");
        $orphaned = $result->fetch_assoc()['orphaned'];
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>Orphaned Records</span>";
        echo "<span class='metric-value'>$orphaned</span>";
        echo "</div>";
        $status = $orphaned == 0 ? 'status-healthy' : 'status-warning';
        echo "<div class='mt-2'><span class='status-indicator $status'></span> " . ($orphaned == 0 ? 'No issues' : "$orphaned records need attention") . "</div>";
        echo "</div>";

        // Check 2: Invalid timestamps
        echo "<div class='health-card'>";
        echo "<h5>Invalid Timestamps</h5>";
        $result = $conn->query("
            SELECT COUNT(*) as invalid
            FROM RECEIVING_SESSIONS
            WHERE completed_at IS NOT NULL AND completed_at < started_at
        ");
        $invalid = $result->fetch_assoc()['invalid'];
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>Invalid Records</span>";
        echo "<span class='metric-value'>$invalid</span>";
        echo "</div>";
        $status = $invalid == 0 ? 'status-healthy' : 'status-critical';
        echo "<div class='mt-2'><span class='status-indicator $status'></span> " . ($invalid == 0 ? 'All valid' : "$invalid records have errors") . "</div>";
        echo "</div>";

        // Check 3: Duplicate achievements
        echo "<div class='health-card'>";
        echo "<h5>Duplicate Achievements</h5>";
        $result = $conn->query("
            SELECT COUNT(*) as duplicates
            FROM (
                SELECT user_id, achievement_type, COUNT(*) as cnt
                FROM USER_ACHIEVEMENTS
                GROUP BY user_id, achievement_type
                HAVING cnt > 1
            ) as dups
        ");
        $duplicates = $result->fetch_assoc()['duplicates'];
        echo "<div class='metric-row'>";
        echo "<span class='metric-label'>Duplicate Records</span>";
        echo "<span class='metric-value'>$duplicates</span>";
        echo "</div>";
        $status = $duplicates == 0 ? 'status-healthy' : 'status-warning';
        echo "<div class='mt-2'><span class='status-indicator $status'></span> " . ($duplicates == 0 ? 'No duplicates' : "$duplicates need review") . "</div>";
        echo "</div>";

        echo "</div>";

        // Summary
        $totalIssues = $orphaned + $invalid + $duplicates;
        echo "<div class='alert " . ($totalIssues == 0 ? 'alert-success' : 'alert-warning') . " mt-4'>";
        echo "<h5><i class='bi bi-clipboard-check'></i> Health Check Summary</h5>";
        if ($totalIssues == 0) {
            echo "<p><strong>✅ All checks passed!</strong> The database is healthy and performing well.</p>";
        } else {
            echo "<p><strong>⚠️ $totalIssues issue(s) detected.</strong> Please review the checks above for details.</p>";
        }
        echo "<small>Last checked: " . date('Y-m-d H:i:s') . "</small>";
        echo "</div>";

        ?>
    </div>
</body>
</html>
