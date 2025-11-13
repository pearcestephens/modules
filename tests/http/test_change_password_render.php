<?php
declare(strict_types=1);

// Simulate GET request
$_SERVER['REQUEST_METHOD'] = 'GET';

$modulesRoot = realpath(__DIR__ . '/../../');
require $modulesRoot . '/base/bootstrap.php';
require $modulesRoot . '/core/bootstrap.php';

// Fake auth for rendering
$_SESSION['user_id'] = 1;
$_SESSION['userID'] = 1;
$_SESSION['user'] = ['id' => 1, 'email' => 'test@example.com'];
// Populate session cache to avoid DB access in getCurrentUser()
$_SESSION['user_data'] = [
    'id' => 1,
    'email' => 'test@example.com',
    'username' => 'tester',
    'role' => 'admin',
    'outlet_id' => 0,
    'status' => 'active'
];

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
include $modulesRoot . '/core/change-password.php';
$output = ob_get_clean();

assert_contains($output, 'Change Password', 'Change password page renders');
assert_contains($output, 'name="csrf_token"', 'CSRF token field present');
assert_contains($output, 'name="current_password"', 'Current password input present');
assert_contains($output, 'name="new_password"', 'New password input present');
assert_contains($output, 'name="confirm_password"', 'Confirm password input present');
