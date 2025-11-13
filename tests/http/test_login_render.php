<?php
declare(strict_types=1);

// Simulate GET request
$_SERVER['REQUEST_METHOD'] = 'GET';

$modulesRoot = realpath(__DIR__ . '/../../');
require $modulesRoot . '/base/bootstrap.php';
require $modulesRoot . '/core/bootstrap.php';

function assert_contains(string $haystack, string $needle, string $msg): void {
    static $pass = 0, $fail = 0;
    if (strpos($haystack, $needle) !== false) {
        echo "✅  $msg\n";
        $pass++;
    } else {
        echo "❌  $msg\n";
        $fail++;
    }
}

ob_start();
include $modulesRoot . '/core/login.php';
$output = ob_get_clean();

assert_contains($output, '<form method="POST" action="" id="loginForm"', 'Login form renders');
assert_contains($output, 'name="csrf_token"', 'CSRF token field present');
assert_contains($output, 'name="email"', 'Email input present');
assert_contains($output, 'name="password"', 'Password input present');
