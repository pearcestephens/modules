<?php
declare(strict_types=1);

// Simulate GET requests
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

// forgot-password
ob_start();
include $modulesRoot . '/core/forgot-password.php';
$forgot = ob_get_clean();

assert_contains($forgot, 'Forgot Password', 'Forgot password page renders');
assert_contains($forgot, 'name="csrf_token"', 'CSRF token field present (forgot)');
assert_contains($forgot, 'name="email"', 'Email input present (forgot)');

// reset-password with no token (should show invalid link UI)
$_GET['token'] = '';
ob_start();
include $modulesRoot . '/core/reset-password.php';
$reset = ob_get_clean();

assert_contains($reset, 'Invalid Reset Link', 'Reset password invalid token message renders');
