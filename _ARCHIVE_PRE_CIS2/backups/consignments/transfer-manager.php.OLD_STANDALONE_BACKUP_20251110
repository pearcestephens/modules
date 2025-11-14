<?php
/**
 * Transfer Manager - Standalone Version with Modern Assets
 *
 * Uses CIS app.php for auth/database and loads modern v2.0 assets
 *
 * @package CIS\Consignments
 * @version 2.0.0
 */

declare(strict_types=1);

// Load CIS core
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['tt_csrf'])) {
    $_SESSION['tt_csrf'] = bin2hex(random_bytes(32));
}

// Connect to database
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$user = defined('DB_USERNAME') ? DB_USERNAME : (defined('DB_USER') ? DB_USER : 'jcepnzzkmj');
$pass = defined('DB_PASSWORD') ? DB_PASSWORD : (defined('DB_PASS') ? DB_PASS : '');
$name = defined('DB_DATABASE') ? DB_DATABASE : (defined('DB_NAME') ? DB_NAME : 'jcepnzzkmj');

$db = new mysqli($host, $user, $pass, $name);
if ($db->connect_error) {
    die('Database connection failed: ' . $db->connect_error);
}
$db->set_charset('utf8mb4');

// Load outlets and suppliers for APP_CONFIG
$outlets = [];
$result = $db->query("SELECT outletID, outletName FROM outlets WHERE status = 'active' ORDER BY outletName");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $outlets[$row['outletID']] = $row['outletName'];
    }
}

$suppliers = [];
$result = $db->query("SELECT supplierID, supplierName FROM suppliers WHERE status = 'active' ORDER BY supplierName");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[$row['supplierID']] = $row['supplierName'];
    }
}

// Page config
$pageTitle = 'Transfer Manager';
$pageName = 'Transfer Manager';
$pageParent = 'Consignments';

// Include CIS header
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/template/html-header.php';
?>

<!-- Modern v2.0 CSS (scoped, no conflicts) -->
<link rel="stylesheet" href="/modules/consignments/assets/css/transfer-manager-v2.css">

<!-- Inject APP_CONFIG before JS loads -->
<script>
window.APP_CONFIG = {
    CSRF: <?= json_encode($_SESSION['tt_csrf']) ?>,
    LS_CONSIGNMENT_BASE: '/modules/consignments/TransferManager/',
    OUTLET_MAP: <?= json_encode($outlets, JSON_UNESCAPED_SLASHES) ?>,
    SUPPLIER_MAP: <?= json_encode($suppliers, JSON_UNESCAPED_SLASHES) ?>,
    SYNC_ENABLED: true
};
console.log('✅ APP_CONFIG injected:', window.APP_CONFIG);
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/template/header.php'; ?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
    <div class="app-body">
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/template/sidemenu.php'; ?>

        <main class="main">

            <?php
            // Include the Transfer Manager frontend content
            require_once __DIR__ . '/TransferManager/frontend-content.php';
            ?>

        </main>
    </div>
</body>

<!-- Modern v2.0 auto-loading JS -->
<script src="/modules/consignments/assets/js/app-loader.js"></script>

</html>
