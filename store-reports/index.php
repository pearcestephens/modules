<?php
function sr_base_prefix(): string {
	// 1) Env override if set
	$p = getenv('APP_URL_PREFIX');
	if (function_exists('env')) { $p = $p ?: env('APP_URL_PREFIX',''); }
	$p = trim((string)$p);
	if ($p !== '') {
		if ($p[0] !== '/') $p = '/'.$p; return rtrim($p,'/');
	}
	// 2) Auto-detect from URL, support patterns:
	//    /applications/{app}/... or /{app}/modules/...
	$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
	if ($uri !== '') {
		if (preg_match('#^(/applications/[^/]+)#i', $uri, $m)) { return rtrim($m[1],'/'); }
		if (preg_match('#^/([a-z0-9_-]+)/modules/#i', $uri, $m)) {
			$seg = strtolower($m[1]);
			if ($seg !== 'modules') { return '/'.$seg; }
		}
	}
	// 3) Default: no prefix
	return '';
}
// Store Reports Module Entry Point / Lightweight Router
require_once __DIR__ . '/bootstrap.php';

// Map actions to view scripts or API handlers
// Support central router: path => action
if (!isset($_GET['action']) && isset($_GET['path'])) { $_GET['action'] = $_GET['path']; }
$action = $_GET['action'] ?? 'dashboard';

// URL builder: preserve central modules/router.php format when used
function sr_url(string $action, array $params=[]): string {
	$isCentral = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'router.php') || (isset($_GET['m']) && $_GET['m'] === 'store-reports');
	if ($isCentral) {
		$q = http_build_query(array_merge(['m'=>'store-reports','path'=>$action], $params));
		return '/modules/router.php?'.$q;
	}
	$q = http_build_query(array_merge(['action'=>$action], $params));
	return './?'.$q;
}
$isApi = str_starts_with($action, 'api:');

// API routing prefix api:upload-image -> api/upload-image.php
if ($isApi) {
	sr_require_auth();
	sr_rate_limit('api', 60);
	if (!verify_csrf()) { sr_json(['success'=>false,'error'=>'CSRF failed'], 403); }
	$apiName = substr($action, 4);
	$apiFile = __DIR__ . '/api/' . $apiName . '.php';
	if (!preg_match('/^[a-z0-9\-_]+$/i', $apiName)) { sr_json(['success'=>false,'error'=>'Invalid API name'], 400); }
	if (!file_exists($apiFile)) { sr_json(['success'=>false,'error'=>'Endpoint not found'], 404); }
	require $apiFile;
	exit; // API scripts must output JSON and exit
}

// View routing
$viewMap = [
	'dashboard' => 'views/dashboard.php',
	'create' => 'views/create.php',
	'upload' => 'views/upload-photos.php',
	'list' => 'views/list.php',
	'view' => 'views/view.php',
	'edit' => 'views/edit.php',
	'analytics' => 'views/analytics.php',
	'history' => 'views/history.php'
];

if (!isset($viewMap[$action])) {
	http_response_code(404);
	echo '<h3>404 - Page Not Found</h3>';
	exit;
}

// For views, prefer core auth gate; fallback to friendly redirect
// Enforce auth via module delegator to avoid core redirect loops
	sr_require_auth(false);

// Shared layout wrapper
include __DIR__ . '/views/layouts/main.php';
?>
