<?php
/**
 * INTEGRATIONS PAGE
 *
 * Displays integration status for external systems.
 */

require_once __DIR__ . '/../shared/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Fetch integration statuses
try {
    $stmt = $pdo->query("SELECT system_name, status, last_sync FROM integrations ORDER BY system_name");
    $integrationData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Failed to fetch integration statuses: " . $e->getMessage());
    http_response_code(500);
    echo 'Internal server error';
    exit;
}

$pageTitle = 'System Integrations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - CIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            padding: 40px 0;
        }

        .integrations-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .table {
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="integrations-container">
        <h1 class="text-center mb-4">System Integrations</h1>
        <table class="table table-striped">
            <thead>
            <tr>
                <th>System</th>
                <th>Status</th>
                <th>Last Sync</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($integrationData as $integration): ?>
                <tr>
                    <td><?php echo htmlspecialchars($integration['system_name']); ?></td>
                    <td><?php echo htmlspecialchars($integration['status']); ?></td>
                    <td><?php echo htmlspecialchars($integration['last_sync']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
