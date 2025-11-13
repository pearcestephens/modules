<?php
/**
 * Quick Setup: Add Pearce Stephens as Director
 *
 * Run this once (CLI recommended) to bootstrap the system with the Director account.
 * Safe to re-run; will no-op if the user already exists.
 */

require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

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

try {
    $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'director' LIMIT 1");
    $directorRole = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fwrite(STDERR, "WARN: Failed querying roles table: " . $e->getMessage() . "\n");
    $directorRole = null;
}

if (!$directorRole) {
    try {
        $ins = $pdo->prepare(
            "INSERT INTO roles (name, display_name, description, level, is_system_role, approval_limit)
             VALUES ('director', 'Director', 'Company Director - Full System Access', 100, TRUE, 999999.99)"
        );
        $ins->execute();
        $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'director' LIMIT 1");
    } catch (Throwable $e) {
        fwrite(STDERR, "ERROR: Failed to create Director role: " . $e->getMessage() . "\n");
        exit(1);
    }
}

$directorRoleId = $directorRole['id'] ?? null;
if (!$directorRoleId) {
    fwrite(STDERR, "ERROR: Director role ID could not be resolved.\n");
    exit(1);
}

$onboarding->createUserIfNotExists('pearce.stephens@company.com', 'Pearce', 'Stephens', $directorRoleId);

fwrite(STDOUT, "SUCCESS: Pearce Stephens added as Director.\n");
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/footer.php';
?>
<?php
/**
 * Quick Setup: Add Pearce Stephens as Director
 *
 * Run this once (CLI recommended) to bootstrap the system with the Director account.
 * Safe to re-run; will no-op if the user already exists.
 */

require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

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

try {
    $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'director' LIMIT 1");
    $directorRole = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fwrite(STDERR, "WARN: Failed querying roles table: " . $e->getMessage() . "\n");
    $directorRole = null;
}

if (!$directorRole) {
    try {
        $ins = $pdo->prepare(
            "INSERT INTO roles (name, display_name, description, level, is_system_role, approval_limit)
             VALUES ('director', 'Director', 'Company Director - Full System Access', 100, TRUE, 999999.99)"
        );
        $ins->execute();
        $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'director' LIMIT 1");
    } catch (Throwable $e) {
        fwrite(STDERR, "ERROR: Failed to create Director role: " . $e->getMessage() . "\n");
        exit(1);
    }
}

$directorRoleId = $directorRole['id'] ?? null;
if (!$directorRoleId) {
    fwrite(STDERR, "ERROR: Director role ID could not be resolved.\n");
    exit(1);
}

$onboarding->createUserIfNotExists('pearce.stephens@company.com', 'Pearce', 'Stephens', $directorRoleId);

fwrite(STDOUT, "SUCCESS: Pearce Stephens added as Director.\n");
