<?php
/**
 * Migration: Create payroll_auth_audit_log table
 *
 * Tracks all auth flag toggles for compliance and audit trail.
 *
 * @package HumanResources\Payroll\Migrations
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config/env-loader.php';

$host = env('DB_HOST', '127.0.0.1');
$port = env('DB_PORT', '3306');
$name = env('DB_DATABASE', 'jcepnzzkmj');
$user = env('DB_USERNAME', 'jcepnzzkmj');
$pass = env('DB_PASSWORD', '');

if (empty($pass)) {
    $pass = 'wprKh9Jq63';
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "Creating payroll_auth_audit_log table...\n";

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payroll_auth_audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            actor VARCHAR(64) NOT NULL COMMENT 'User or system that toggled the flag',
            action VARCHAR(32) NOT NULL COMMENT 'enable or disable',
            flag_before TINYINT(1) NOT NULL COMMENT 'Previous state',
            flag_after TINYINT(1) NOT NULL COMMENT 'New state',
            ip_address VARCHAR(64) DEFAULT NULL COMMENT 'IP address of actor',
            INDEX idx_timestamp (timestamp),
            INDEX idx_actor (actor)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Audit trail for payroll auth flag changes';
    ");

    echo "✅ Table payroll_auth_audit_log created successfully\n";
    exit(0);

} catch (PDOException $e) {
    fwrite(STDERR, "❌ Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
