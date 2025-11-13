<?php
// CLI helper: php tools/cli_call.php action=api:get-trends report_id=1
parse_str(implode('&', array_slice($argv,1)), $_GET);
if (!isset($_GET['action'])) { fwrite(STDERR, "Missing action parameter. Usage: php tools/cli_call.php action=api:get-report report_id=1 [dev_no_auth=1] [method=POST]\n"); exit(1); }

// Determine method (GET default); if POST we'll move non-control params into $_POST
$method = strtoupper($_GET['method'] ?? 'GET');
if (php_sapi_name() === 'cli' && empty($_SERVER['REQUEST_METHOD'])) {
	$_SERVER['REQUEST_METHOD'] = $method;
}
if ($method === 'POST') {
	foreach ($_GET as $k=>$v) {
		if (in_array($k, ['action','method','dev_no_auth'])) continue;
		$_POST[$k] = $v;
	}
}

// Dev no-auth bypass: php tools/cli_call.php action=dashboard dev_no_auth=1
if (!session_id()) { session_start(); }
if (!empty($_GET['dev_no_auth'])) {
	$_SESSION['user_id'] = 1;
	$_SESSION['is_admin'] = true;
	if (!function_exists('is_logged_in')) { function is_logged_in(){ return true; } }
	if (!function_exists('is_admin')) { function is_admin(){ return !empty($_SESSION['is_admin']); } }
	if (!function_exists('current_user_id')) { function current_user_id(){ return $_SESSION['user_id'] ?? 0; } }
	// Auto-inject CSRF token for POST requests in dev bypass mode
	if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
	if ($method === 'POST' && empty($_POST['csrf_token'])) { $_POST['csrf_token'] = $_SESSION['csrf_token']; }
}

require __DIR__ . '/../index.php';
?>
