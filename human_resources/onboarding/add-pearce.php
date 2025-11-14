<?php
/**
 * Quick Setup: Add Pearce Stephens as Director
 *
 * Run this once (CLI recommended) to bootstrap the system with the Director account.
 * Safe to re-run; will no-op if the user already exists.
 */

// Use module shared bootstrap for consistent env + DB + helpers
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

// Resolve PDO defensively (pattern aligned with other onboarding pages)
if (!isset($pdo) || !$pdo instanceof PDO) {
    if (function_exists('cis_resolve_pdo')) {
        $pdo = cis_resolve_pdo();
    } elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }
}

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "ERROR: Database connection unavailable. Ensure shared/bootstrap.php initialized correctly.\n");
    exit(1);
}

$onboarding = new UniversalOnboardingService($pdo);

// Get Director role ID
try {
    $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'director' LIMIT 1");
    $directorRole = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fwrite(STDERR, "WARN: Failed querying roles table: " . $e->getMessage() . "\n");
    $directorRole = null;
}

// If Director role is missing, attempt to create it (no-op if extra NOT NULL columns block it)
if (!$directorRole) {
    try {
        // Insert full role definition to satisfy NOT NULL columns
        $ins = $pdo->prepare(
            "INSERT INTO roles (name, display_name, description, level, is_system_role, approval_limit)
             VALUES ('director', 'Director', 'Company Director - Full System Access', 100, TRUE, 999999.99)"
        );
        $ins->execute();
        // Re-fetch
        $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'director' LIMIT 1");
        $directorRole = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$directorRole) {
            fwrite(STDERR, "WARN: Director role still not available; continuing without role assignment.\n");
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "WARN: Could not create Director role automatically: " . $e->getMessage() . "\nProceeding without role assignment.\n");
        $directorRole = null;
    }
}

// Check if you already exist
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute(['pearce.stephens@ecigdis.co.nz']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: Failed checking existing user: " . $e->getMessage() . "\n");
    exit(1);
}

if ($existing) {
    echo "✅ You already exist in the system (User ID: {$existing['id']})\n";
    echo "Access the dashboard at: https://staff.vapeshed.co.nz/modules/employee-onboarding/dashboard.php\n";
    exit;
}

// Your details
$employeeData = [
    'first_name' => 'Pearce',
    'last_name' => 'Stephens',
    'email' => 'pearce.stephens@ecigdis.co.nz',
    'mobile' => '', // Add your mobile if you want
    'employee_number' => 'EMP00001',
    'job_title' => 'Director / Owner',
    'department' => 'Executive',
    'start_date' => '2015-01-01', // When The Vape Shed started
    'employment_type' => 'full_time',
    'location_id' => 1, // Adjust if needed
    'username' => 'pearce',
    'password' => 'changeme123', // CHANGE THIS IMMEDIATELY
    'status' => 'active',
    'is_admin' => true, // Super admin access
    'roles' => isset($directorRole['id']) ? [$directorRole['id']] : [], // Assign Director if available
    'notes' => 'Company Director and Owner - Bootstrap account'
];

// Sync options - you can disable these if you want to add manually to external systems
$options = [
    'sync_xero' => false, // Set to true to auto-create in Xero
    'sync_deputy' => false, // Set to true to auto-create in Deputy
    'sync_lightspeed' => false // Set to true to auto-create in Lightspeed
];

echo "🚀 Creating your Director account...\n\n";

try {
    $result = $onboarding->onboardEmployee($employeeData, $options);
} catch (Throwable $e) {
    fwrite(STDERR, "❌ FATAL during onboarding: " . $e->getMessage() . "\n");
    exit(1);
}

if ($result['success']) {
    echo "✅ SUCCESS! Your account has been created!\n\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "📋 YOUR ACCOUNT DETAILS\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "User ID:        {$result['user_id']}\n";
    echo "Name:           Pearce Stephens\n";
    echo "Email:          pearce.stephens@ecigdis.co.nz\n";
    echo "Username:       pearce\n";
    echo "Password:       changeme123 (CHANGE THIS!)\n";
    echo "Role:           Director (Full System Access)\n";
    echo "Admin Access:   YES (Super Admin)\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    echo "🔐 SYNC STATUS:\n";
    foreach ($result['sync_results'] as $system => $sync) {
        $icon = $sync['status'] === 'success' ? '✅' :
                ($sync['status'] === 'disabled' ? '⊖' : '❌');
        echo "$icon " . ucfirst($system) . ": " . ($sync['message'] ?? $sync['error'] ?? 'Disabled') . "\n";
    }

    echo "\n🎯 NEXT STEPS:\n";
    echo "1. Login at: https://staff.vapeshed.co.nz/login.php\n";
    echo "2. Change your password immediately!\n";
    echo "3. Access the onboarding wizard: https://staff.vapeshed.co.nz/modules/employee-onboarding/onboarding-wizard.php\n";
    echo "4. View all employees: https://staff.vapeshed.co.nz/modules/employee-onboarding/dashboard.php\n";
    echo "\n🔥 YOU NOW HAVE FULL DIRECTOR ACCESS TO THE UNIVERSAL ONBOARDING SYSTEM!\n\n";

} else {
    echo "❌ ERROR: {$result['error']}\n";
    echo "\nPlease check:\n";
    echo "1. Database schema is installed (run: mysql -u jcepnzzkmj -p jcepnzzkmj < database/schema.sql)\n";
    echo "2. Database connection is working\n";
    echo "3. Required tables exist\n";
}
