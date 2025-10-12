<?php
declare(strict_types=1);

/**
 * Smoke Test - Quick validation that base module works
 */

$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/modules/consignments/';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'staff.vapeshed.co.nz';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';

$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '0';

if (session_status() !== PHP_SESSION_ACTIVE) {
	@session_start();
}

if (empty($_SESSION['userID'])) {
	$_SESSION['userID'] = 1;
}

if (!defined('HTTPS_URL')) {
	define('HTTPS_URL', 'https://staff.vapeshed.co.nz/');
}

register_shutdown_function(static function (): void {
	$error = error_get_last();
	if ($error === null) {
		echo "✅ Smoke test passed - Module bootstrap successful\n";
	}
});

ob_start();
require_once dirname(__DIR__, 2) . '/consignments/index.php';
ob_end_clean();
