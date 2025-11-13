<?php
/**
 * Simple Authentication Test
 * Access: https://staff.vapeshed.co.nz/modules/human_resources/payroll/test-auth-simple.php
 */

session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payroll Auth Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #3498db;
            color: white;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔐 Payroll Authentication Test</h1>

        <?php
        $isLoggedIn = !empty($_SESSION['user_id']) && !empty($_SESSION['authenticated']);

        if ($isLoggedIn):
        ?>
            <div class="status success">
                ✅ <strong>AUTHENTICATED</strong> - You are logged in!
            </div>

            <h2>Session Information</h2>
            <table>
                <tr>
                    <th>Variable</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td><code>$_SESSION['user_id']</code></td>
                    <td><?= htmlspecialchars($_SESSION['user_id'] ?? 'NOT SET') ?></td>
                </tr>
                <tr>
                    <td><code>$_SESSION['username']</code></td>
                    <td><?= htmlspecialchars($_SESSION['username'] ?? 'NOT SET') ?></td>
                </tr>
                <tr>
                    <td><code>$_SESSION['authenticated']</code></td>
                    <td><?= $_SESSION['authenticated'] ? 'TRUE' : 'FALSE' ?></td>
                </tr>
                <tr>
                    <td><code>$_SESSION['first_name']</code></td>
                    <td><?= htmlspecialchars($_SESSION['first_name'] ?? 'NOT SET') ?></td>
                </tr>
                <tr>
                    <td><code>$_SESSION['last_name']</code></td>
                    <td><?= htmlspecialchars($_SESSION['last_name'] ?? 'NOT SET') ?></td>
                </tr>
                <tr>
                    <td><strong>Session ID</strong></td>
                    <td><?= htmlspecialchars(session_id()) ?></td>
                </tr>
            </table>

            <h2>Next Steps</h2>
            <div class="info">
                ℹ️ Your session is valid! You can now access the payroll module.
            </div>

            <a href="/modules/human_resources/payroll/" class="btn btn-success">
                🚀 Access Payroll Module
            </a>

            <a href="/index.php" class="btn">
                🏠 Main Dashboard
            </a>

        <?php else: ?>
            <div class="status error">
                ❌ <strong>NOT AUTHENTICATED</strong> - You are not logged in
            </div>

            <h2>Session Status</h2>
            <table>
                <tr>
                    <th>Check</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>Session Started</td>
                    <td><?= session_status() === PHP_SESSION_ACTIVE ? '✅ Yes' : '❌ No' ?></td>
                </tr>
                <tr>
                    <td>Session ID</td>
                    <td><?= htmlspecialchars(session_id() ?: 'NONE') ?></td>
                </tr>
                <tr>
                    <td><code>$_SESSION['user_id']</code></td>
                    <td><?= isset($_SESSION['user_id']) ? '✅ Set' : '❌ Not Set' ?></td>
                </tr>
                <tr>
                    <td><code>$_SESSION['authenticated']</code></td>
                    <td><?= isset($_SESSION['authenticated']) && $_SESSION['authenticated'] ? '✅ True' : '❌ False/Not Set' ?></td>
                </tr>
            </table>

            <h2>Solution</h2>
            <div class="info">
                ℹ️ <strong>You need to log in first!</strong>
                <br><br>
                The payroll module requires you to be logged in to the main CIS system at
                <strong>staff.vapeshed.co.nz</strong>.
            </div>

            <a href="/login.php?redirect=/modules/human_resources/payroll/" class="btn btn-success">
                🔑 Log In to CIS
            </a>

            <h2>Debug Info</h2>
            <details>
                <summary>View Full Session Data</summary>
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;"><?php
                    print_r($_SESSION);
                ?></pre>
            </details>

        <?php endif; ?>

        <hr style="margin: 30px 0;">

        <h2>System Information</h2>
        <table>
            <tr>
                <td><strong>Current URL</strong></td>
                <td><?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></td>
            </tr>
            <tr>
                <td><strong>Server</strong></td>
                <td><?= htmlspecialchars($_SERVER['HTTP_HOST']) ?></td>
            </tr>
            <tr>
                <td><strong>PHP Session Name</strong></td>
                <td><code><?= htmlspecialchars(session_name()) ?></code></td>
            </tr>
            <tr>
                <td><strong>Session Cookie</strong></td>
                <td><?= isset($_COOKIE[session_name()]) ? '✅ Present' : '❌ Not Found' ?></td>
            </tr>
        </table>

    </div>
</body>
</html>
