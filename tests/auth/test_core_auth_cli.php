<?php
/**
 * CLI Test: Core Auth Helpers (no DB, no HTTP)
 *
 * Run: php modules/tests/auth/test_core_auth_cli.php
 */
declare(strict_types=1);

$modulesRoot = realpath(__DIR__ . '/../../'); // points to /modules
require $modulesRoot . '/base/bootstrap.php';
require $modulesRoot . '/core/bootstrap.php';

$tests = 0;
$fails = 0;

function ok(bool $cond, string $msg): void {
    global $tests, $fails;
    $tests++;
    if ($cond) {
        echo "✅  $msg\n";
    } else {
        $fails++;
        echo "❌  $msg\n";
    }
}

echo "\n=== CORE AUTH CLI TESTS ===\n\n";

// Ensure clean session
logoutUser(false);

// 1) CSRF token generation/validation
try {
    $token1 = generate_csrf_token();
    ok(is_string($token1) && strlen($token1) >= 32, 'CSRF token generated');
    ok(validate_csrf_token($token1) === true, 'CSRF token validates (correct)');
    ok(validate_csrf_token($token1 . 'X') === false, 'CSRF token rejects (incorrect)');
} catch (Throwable $e) {
    ok(false, 'CSRF functions threw exception: ' . $e->getMessage());
}

// 2) isAuthenticated, getUserId (pre-login)
ok(isAuthenticated() === false, 'Not authenticated before login');
ok(getUserId() === null, 'No user ID before login');

// 3) loginUser basic flow (no DB dependency)
try {
    $user = [
        'id' => 999999,
        'email' => 'unit-test@example.com',
        'username' => 'unit_test'
    ];
    loginUser($user);
    ok(isAuthenticated() === true, 'Authenticated after loginUser');
    ok(getUserId() === 999999, 'getUserId returns logged-in ID');
    ok(isset($_SESSION['user']) && ($_SESSION['user']['id'] ?? 0) === 999999, 'Session user populated');
} catch (Throwable $e) {
    ok(false, 'loginUser threw exception: ' . $e->getMessage());
}

// 4) logoutUser clears session
try {
    logoutUser(false);
    ok(isAuthenticated() === false, 'Not authenticated after logoutUser');
    ok(getUserId() === null, 'User ID cleared after logout');
} catch (Throwable $e) {
    ok(false, 'logoutUser threw exception: ' . $e->getMessage());
}

echo "\n=== RESULT: $tests tests, $fails failures ===\n";
exit($fails > 0 ? 1 : 0);
