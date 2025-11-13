<?php
/**
 * Quick test page to list available transfers
 */
session_start();

// Load database connection
require_once __DIR__ . '/../../base/DatabasePDO.php';
use CIS\Base\DatabasePDO;

// Configure database
DatabasePDO::configure([
    'host' => 'localhost',
    'database' => 'jcepnzzkmj',
    'username' => 'jcepnzzkmj',
    'password' => 'wprKh9Jq63',
]);

$db = DatabasePDO::connection();
$stmt = $db->query("SELECT id, public_id, state, status, outlet_from, outlet_to, created_at, transfer_category
                    FROM vend_consignments
                    WHERE deleted_at IS NULL
                    AND transfer_category = 'STOCK'
                    ORDER BY id DESC
                    LIMIT 20");
$transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test: Available Transfers</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        a { color: #0969da; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Available Transfers (Test Page)</h1>
    <p>Click a transfer ID to open it in the flagship packing page:</p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Public ID</th>
                <th>State</th>
                <th>Status</th>
                <th>From</th>
                <th>To</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transfers as $t): ?>
            <tr>
                <td><?php echo $t['id']; ?></td>
                <td><?php echo htmlspecialchars($t['public_id']); ?></td>
                <td><?php echo htmlspecialchars($t['state']); ?></td>
                <td><?php echo htmlspecialchars($t['status']); ?></td>
                <td><?php echo $t['outlet_from']; ?></td>
                <td><?php echo $t['outlet_to']; ?></td>
                <td><?php echo $t['created_at']; ?></td>
                <td>
                    <a href="pack-enterprise-flagship.php?id=<?php echo $t['id']; ?>" target="_blank">
                        Open Flagship Page
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <hr>
    <h3>Direct API Test</h3>
    <p>Test the API endpoint directly:</p>
    <button onclick="testAPI(<?php echo $transfers[0]['id']; ?>)">Test API with Transfer #<?php echo $transfers[0]['id']; ?></button>
    <pre id="apiResult" style="background:#f5f5f5; padding:10px; margin-top:10px;"></pre>

    <script>
    function testAPI(id) {
        document.getElementById('apiResult').textContent = 'Loading...';
        fetch('/modules/consignments/stock-transfers/api/get-transfer-data.php?transfer_id=' + id)
            .then(r => r.json())
            .then(data => {
                document.getElementById('apiResult').textContent = JSON.stringify(data, null, 2);
            })
            .catch(err => {
                document.getElementById('apiResult').textContent = 'Error: ' + err.message;
            });
    }
    </script>
</body>
</html>
