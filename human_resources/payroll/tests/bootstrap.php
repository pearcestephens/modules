<?php
/**
 * Test Bootstrap - Initialize database connection for testing
 */

// Load main app
require_once '/home/master/applications/jcepnzzkmj/public_html/app.php';

// Load payroll service classes
require_once __DIR__ . '/../services/PayslipCalculationEngine.php';
require_once __DIR__ . '/../services/BonusService.php';
require_once __DIR__ . '/../services/BankExportService.php';
require_once __DIR__ . '/../services/NZEmploymentLaw.php';

// Ensure PDO is available
if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo']) {
    // Manual PDO initialization if not loaded
    $host = 'localhost';
    $db   = 'jcepnzzkmj';
    $user = 'jcepnzzkmj';
    $pass = 'wprKh9Jq63';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $GLOBALS['pdo'] = $pdo;
        echo "✅ Database connection established\n\n";
    } catch (PDOException $e) {
        die("❌ Database connection failed: " . $e->getMessage() . "\n");
    }
}
