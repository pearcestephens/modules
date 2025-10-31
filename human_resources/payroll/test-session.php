<?php
/**
 * Test Session and Authentication
 * Simple diagnostic to check if sessions work and if user is authenticated
 */

// Start session
session_start();

// Get user info from MAIN CIS SESSION STRUCTURE
// CIS uses: $_SESSION['userID'], $_SESSION['username'], $_SESSION['authenticated']
$isAuthenticated = !empty($_SESSION['userID']) && !empty($_SESSION['authenticated']);
$user = null;

if ($isAuthenticated) {
    $user = [
        'id' => $_SESSION['userID'] ?? null,
        'name' => $_SESSION['username'] ?? 'Unknown',
        'email' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? 'staff'
    ];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 4px; border: 1px solid #ddd; }
        .success { border-left: 4px solid #22c55e; }
        .error { border-left: 4px solid #ef4444; }
        .info { border-left: 4px solid #3b82f6; }
        h2 { margin-top: 0; }
        pre { background: #f9fafb; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
        .status.ok { background: #22c55e; color: white; }
        .status.fail { background: #ef4444; color: white; }
    </style>
</head>
<body>
    <h1>🔍 Payroll Session Diagnostic</h1>

    <div class="box <?php echo $user ? 'success' : 'error'; ?>">
        <h2>Authentication Status</h2>
        <?php if ($user): ?>
            <p><span class="status ok">✓ AUTHENTICATED</span></p>
            <p><strong>User:</strong> <?php echo htmlspecialchars($user['name'] ?? $user['email'] ?? 'Unknown'); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role'] ?? 'N/A'); ?></p>
            <p><strong>User ID:</strong> <?php echo htmlspecialchars((string)($user['id'] ?? 'N/A')); ?></p>
        <?php else: ?>
            <p><span class="status fail">✗ NOT AUTHENTICATED</span></p>
            <p>No user session found. Please <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">login</a>.</p>
        <?php endif; ?>
    </div>

    <div class="box info">
        <h2>Session Information</h2>
        <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
        <p><strong>Session Status:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?></p>
        <p><strong>Cookie Name:</strong> <?php echo session_name(); ?></p>
        <p><strong>Session Save Path:</strong> <?php echo session_save_path(); ?></p>
    </div>

    <div class="box info">
        <h2>Request Information</h2>
        <p><strong>Request URI:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''); ?></p>
        <p><strong>Request Method:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? ''); ?></p>
        <p><strong>HTTPS:</strong> <?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'Yes' : 'No'; ?></p>
        <p><strong>Server Protocol:</strong> <?php echo htmlspecialchars($_SERVER['SERVER_PROTOCOL'] ?? ''); ?></p>
    </div>

    <div class="box info">
        <h2>Cookies</h2>
        <?php if (empty($_COOKIE)): ?>
            <p><em>No cookies set</em></p>
        <?php else: ?>
            <pre><?php print_r($_COOKIE); ?></pre>
        <?php endif; ?>
    </div>

    <div class="box info">
        <h2>Full Session Data</h2>
        <?php if (empty($_SESSION)): ?>
            <p><em>Session is empty</em></p>
        <?php else: ?>
            <pre><?php print_r($_SESSION); ?></pre>
        <?php endif; ?>
    </div>

    <div class="box info">
        <h2>Test Actions</h2>
        <p><a href="/modules/human_resources/payroll/?view=dashboard">→ Go to Payroll Dashboard</a></p>
        <p><a href="/modules/human_resources/payroll/?view=payruns">→ Go to Pay Runs</a></p>
        <p><a href="/login.php">→ Go to Login Page</a></p>
        <p><a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">→ Refresh This Page</a></p>
    </div>

    <div class="box info">
        <h2>Troubleshooting Steps</h2>
        <ol>
            <li>If "NOT AUTHENTICATED": Clear your browser cookies and try logging in again</li>
            <li>If session ID keeps changing: Check browser cookie settings (should allow cookies from this domain)</li>
            <li>If redirect loop: Clear browser cache and cookies, then try again</li>
            <li>If still issues: Try accessing in an incognito/private browser window</li>
        </ol>
    </div>

</body>
</html>
