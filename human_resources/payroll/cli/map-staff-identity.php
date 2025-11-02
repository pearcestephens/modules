#!/usr/bin/env php
<?php
/**
 * CLI Wizard: Map Staff Identity (Xero ↔ Vend)
 *
 * Allows ops to view, add, and fix staff mappings before payroll apply.
 * Usage: php cli/map-staff-identity.php [--list|--add|--fix]
 *
 * @author GitHub Copilot
 * @created 2025-11-02
 */

require_once __DIR__ . '/../dao/StaffIdentityDao.php';

$options = getopt('', ['list', 'add', 'fix', 'help']);

function printHelp() {
    echo "\nStaff Identity Mapping CLI\n";
    echo "Usage: php cli/map-staff-identity.php [--list|--add|--fix]\n";
    echo "  --list   List all current mappings\n";
    echo "  --add    Add a new mapping (interactive)\n";
    echo "  --fix    Fix unmapped staff (interactive)\n";
    echo "  --help   Show this help\n\n";
}

// DB connection (replace with your actual connection logic)
function getDb() {
    $dsn = getenv('PAYROLL_DB_DSN') ?: 'mysql:host=localhost;dbname=jcepnzzkmj;charset=utf8mb4';
    $user = getenv('PAYROLL_DB_USER') ?: 'jcepnzzkmj';
    $pass = getenv('PAYROLL_DB_PASS') ?: 'wprKh9Jq63';
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

$db = getDb();
$dao = new StaffIdentityDao($db);

if (isset($options['help']) || count($options) === 0) {
    printHelp();
    exit(0);
}

if (isset($options['list'])) {
    $rows = $dao->getAll();
    echo "\nCurrent Staff Mappings:\n";
    foreach ($rows as $row) {
        printf("- Xero: %s | Vend: %s | Name: %s | Active: %s\n",
            $row['xero_employee_id'], $row['vend_customer_id'], $row['display_name'], $row['active'] ? 'Yes' : 'No');
    }
    exit(0);
}

if (isset($options['add'])) {
    echo "\nAdd New Staff Mapping\n";
    echo "Xero Employee ID: ";
    $xeroId = trim(fgets(STDIN));
    echo "Vend Customer ID: ";
    $vendId = trim(fgets(STDIN));
    echo "Display Name: ";
    $name = trim(fgets(STDIN));
    $id = $dao->create($xeroId, $vendId, $name);
    echo "Mapping created with ID: $id\n";
    exit(0);
}

if (isset($options['fix'])) {
    echo "\nFix Unmapped Staff\n";
    echo "Enter comma-separated Xero Employee IDs to check: ";
    $input = trim(fgets(STDIN));
    $ids = array_map('trim', explode(',', $input));
    $unmapped = $dao->getUnmapped($ids);
    if (empty($unmapped)) {
        echo "All provided Xero IDs are mapped.\n";
        exit(0);
    }
    echo "Unmapped Xero IDs:\n";
    foreach ($unmapped as $id) {
        echo "- $id\n";
    }
    foreach ($unmapped as $id) {
        echo "Add mapping for $id? (y/n): ";
        $ans = strtolower(trim(fgets(STDIN)));
        if ($ans === 'y') {
            echo "Vend Customer ID: ";
            $vendId = trim(fgets(STDIN));
            echo "Display Name: ";
            $name = trim(fgets(STDIN));
            $dao->create($id, $vendId, $name);
            echo "Mapping created for $id.\n";
        }
    }
    exit(0);
}

printHelp();
exit(1);
