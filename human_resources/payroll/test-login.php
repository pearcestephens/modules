<?php
/**
 * Simple Login Test - Check if CIS authentication works
 */

// Try PHPSESSID (default PHP session name)
if (session_status() === PHP_SESSION_NONE) {
    @session_name('CIS_SESSION');
}

// Session configuration
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_lifetime', '0');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Test - CIS Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 20px;
            background: #f8f9fa;
        }
        .test-box {
            background: white;
            padding: 30px;
            margin: 20px auto;
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 18px;
            margin: 20px 0;
        }
        .status-success { background: #22c55e; color: white; }
        .status-error { background: #ef4444; color: white; }
        .info-table { width: 100%; margin: 20px 0; }
        .info-table td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .info-table td:first-child { font-weight: 600; width: 200px; }
        pre {
            background: #f9fafb;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #3b82f6;
            overflow-x: auto;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            font-weight: 600;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #22c55e; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
    </style>
</head>
<body>
    <div class="test-box">
        <h1>🔐 CIS Login Test</h1>
        <p>This page checks if you're properly logged into CIS</p>
        <hr>

        <?php
        // Check if session is active
        $sessionActive = (session_status() === PHP_SESSION_ACTIVE);

        // Check authentication using main CIS session structure
        $isAuthenticated = !empty($_SESSION['user_id']) && !empty($_SESSION['authenticated']);

        if ($isAuthenticated):
            $userID = $_SESSION['user_id'];
            $username = $_SESSION['username'] ?? 'Unknown';
            $role = $_SESSION['role'] ?? 'N/A';
        ?>

            <div class="status-badge status-success">
                ✓ LOGGED IN SUCCESSFULLY
            </div>

            <h3>✅ Authentication Working!</h3>
            <p>You are logged into CIS and ready to use the Payroll module.</p>

            <table class="info-table">
                <tr>
                    <td>User ID:</td>
                    <td><strong><?php echo htmlspecialchars((string)$userID); ?></strong></td>
                </tr>
                <tr>
                    <td>Username:</td>
                    <td><strong><?php echo htmlspecialchars($username); ?></strong></td>
                </tr>
                <tr>
                    <td>Role:</td>
                    <td><strong><?php echo htmlspecialchars($role); ?></strong></td>
                </tr>
                <tr>
                    <td>Session ID:</td>
                    <td><?php echo session_id(); ?></td>
                </tr>
                <tr>
                    <td>Session Active:</td>
                    <td><?php echo $sessionActive ? 'Yes' : 'No'; ?></td>
                </tr>
            </table>

            <h3>📊 Session Data:</h3>
            <pre><?php
            $sessionCopy = $_SESSION;
            // Hide sensitive data
            if (isset($sessionCopy['password'])) $sessionCopy['password'] = '***HIDDEN***';
            print_r($sessionCopy);
            ?></pre>

            <h3>🎯 Next Steps:</h3>
            <div style="margin: 20px 0;">
                <a href="?view=dashboard" class="btn btn-primary">
                    → Try Payroll Dashboard
                </a>
                <a href="?view=payruns" class="btn btn-success">
                    → Try Pay Runs Page
                </a>
                <a href="/modules/human_resources/payroll/" class="btn btn-secondary">
                    → Go to Payroll Module
                </a>
            </div>

            <div class="alert alert-info" style="margin: 20px 0; padding: 15px; background: #dbeafe; border-left: 4px solid #3b82f6;">
                <strong>✓ Your login is working!</strong> If you still see errors on other pages,
                it's a permission or routing issue, NOT a login problem.
            </div>

        <?php else: ?>

            <div class="status-badge status-error">
                ✗ NOT LOGGED IN
            </div>

            <h3>❌ Not Authenticated</h3>
            <p>You need to log in to access the Payroll module.</p>

            <table class="info-table">
                <tr>
                    <td>Session Active:</td>
                    <td><?php echo $sessionActive ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td>Session ID:</td>
                    <td><?php echo session_id() ?: 'No session'; ?></td>
                </tr>
                <tr>
                    <td>$_SESSION['user_id']:</td>
                    <td><?php echo isset($_SESSION['user_id']) ? 'Set but empty/false' : 'Not set'; ?></td>
                </tr>
                <tr>
                    <td>$_SESSION['authenticated']:</td>
                    <td><?php echo isset($_SESSION['authenticated']) ? 'Set but empty/false' : 'Not set'; ?></td>
                </tr>
            </table>

            <h3>🔧 Debug Info:</h3>
            <pre><?php print_r($_SESSION); ?></pre>

            <h3>🎯 Action Required:</h3>
            <div style="margin: 20px 0;">
                <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">
                    → Go to Login Page
                </a>
                <a href="/login.php" class="btn btn-secondary">
                    → Login to CIS
                </a>
            </div>

            <div class="alert alert-warning" style="margin: 20px 0; padding: 15px; background: #fef3c7; border-left: 4px solid #f59e0b;">
                <strong>⚠️ You need to log in first!</strong> After logging in, come back to this page to verify.
            </div>

        <?php endif; ?>

        <hr style="margin: 30px 0;">

        <h3>🔍 Diagnostic Information:</h3>

        <h4>Cookies:</h4>
        <pre><?php
        if (!empty($_COOKIE)) {
            print_r($_COOKIE);
        } else {
            echo "No cookies set\n";
        }
        ?></pre>

        <h4>Server Info:</h4>
        <table class="info-table">
            <tr>
                <td>Request URI:</td>
                <td><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''); ?></td>
            </tr>
            <tr>
                <td>Request Method:</td>
                <td><?php echo htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? ''); ?></td>
            </tr>
            <tr>
                <td>HTTPS:</td>
                <td><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'Yes' : 'No'; ?></td>
            </tr>
            <tr>
                <td>Session Name:</td>
                <td><?php echo session_name(); ?></td>
            </tr>
            <tr>
                <td>Session Save Path:</td>
                <td><?php echo session_save_path(); ?></td>
            </tr>
        </table>

    </div>
</body>
</html>
