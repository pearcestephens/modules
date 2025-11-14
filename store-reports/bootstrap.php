<?php
/**
 * Store Reports Module Bootstrap
 * Minimal bootstrap that delegates to core, then adds module-specific functionality.
 */

// ============================================================================
// 1. LOAD CORE BOOTSTRAP
// ============================================================================
// Core handles: environment, database, sessions, auth, config
$coreConfig = dirname(__DIR__, 2) . '/assets/functions/config.php';
if (file_exists($coreConfig)) {
	require_once $coreConfig;
} else {
	die('Core config not found. Expected: ' . $coreConfig);
}

// Guard against direct access
if (basename(__FILE__) !== 'bootstrap.php') {
	http_response_code(403);
	exit('Forbidden');
}

// ============================================================================
// 2. MODULE-SPECIFIC AUTOLOADER
// ============================================================================
spl_autoload_register(function ($class) {
	$paths = [
		__DIR__ . '/models/' . $class . '.php',
		__DIR__ . '/controllers/' . $class . '.php',
		__DIR__ . '/services/' . $class . '.php',
		__DIR__ . '/services/gpt/' . $class . '.php',
		__DIR__ . '/lib/' . $class . '.php'
	];
	foreach ($paths as $p) {
		if (file_exists($p)) {
			require_once $p;
			return;
		}
	}
});

// ============================================================================
// 3. MODULE HELPERS (Minimal - Delegate to Core Where Possible)
// ============================================================================

// CSRF token (use core if available, otherwise simple implementation)
if (!function_exists('csrf_token')) {
	function csrf_token(): string {
		if (!isset($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['csrf_token'];
	}
}

if (!function_exists('verify_csrf')) {
	function verify_csrf(): bool {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
		$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
		return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
	}
}

// Check if request wants JSON response
function sr_wants_json(): bool {
	$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
	$xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
	$action = $_GET['action'] ?? '';
	return (stripos($accept, 'application/json') !== false)
		|| (stripos($xhr, 'XMLHttpRequest') !== false)
		|| (is_string($action) && str_starts_with($action, 'api:'));
}

// Get current full URL
function sr_current_url(): string {
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'staff.vapeshed.co.nz';
	$uri = $_SERVER['REQUEST_URI'] ?? '/';
	return $scheme . '://' . $host . $uri;
}

// Auth gate - delegates to core authentication
function sr_require_auth(bool $adminOnly = false): void {
	// Use core authentication if available
	if (function_exists('require_login')) {
		require_login();
	} elseif (function_exists('isAuthenticated') && !isAuthenticated()) {
		if (sr_wants_json()) {
			sr_json(['success' => false, 'error' => 'Unauthorized'], 401);
		}
		header('Location: /login.php?redirect=' . rawurlencode(sr_current_url()));
		exit;
	} elseif (empty($_SESSION['user_id']) && empty($_SESSION['userID'])) {
		if (sr_wants_json()) {
			sr_json(['success' => false, 'error' => 'Unauthorized'], 401);
		}
		header('Location: /login.php?redirect=' . rawurlencode(sr_current_url()));
		exit;
	}

	// Admin check if required
	if ($adminOnly) {
		$isAdmin = false;
		if (function_exists('is_admin')) {
			$isAdmin = is_admin();
		} elseif (isset($_SESSION['role'])) {
			$isAdmin = ($_SESSION['role'] === 'admin');
		}

		if (!$isAdmin) {
			if (sr_wants_json()) {
				sr_json(['success' => false, 'error' => 'Forbidden'], 403);
			}
			http_response_code(403);
			echo '<h1>403 Forbidden</h1>';
			exit;
		}
	}
}

// JSON response helper
function sr_json($data, int $status = 200): void {
	http_response_code($status);
	header('Content-Type: application/json');
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

// Simple rate limiter
function sr_rate_limit(string $key, int $maxPerMinute = 30): void {
	$bucket =& $_SESSION['rate_' . $key];
	$now = time();
	if (!is_array($bucket) || ($now - $bucket['window']) > 60) {
		$bucket = ['window' => $now, 'count' => 0];
	}
	if (++$bucket['count'] > $maxPerMinute) {
		http_response_code(429);
		exit('Rate limit exceeded');
	}
}

// Logging helpers
function sr_log_info($msg): void {
	error_log('[store-reports][INFO] ' . $msg);
}

function sr_log_error($msg): void {
	error_log('[store-reports][ERROR] ' . $msg);
}

// Use centralized DatabaseManager for robust PDO initialization
require_once __DIR__.'/DatabaseManager.php';
DatabaseManager::init();
$SR_PDO = DatabaseManager::pdo();
if (!$SR_PDO) {
	$StoreReportsLogger->error('DatabaseManager PDO unavailable: '.json_encode(DatabaseManager::lastError()));
	// Diagnostic mysqli attempt (no persistence) to differentiate driver vs credentials
	try {
		$dbHost = env('DB_HOST','127.0.0.1');
		$dbName = env('DB_NAME','jcepnzzkmj');
		$dbUser = env('DB_USER','jcepnzzkmj');
		$dbPass = env('DB_PASS', env('DB_PASSWORD',''));
		if ($dbPass === '') { $dbPass = env('DB_PASSWORD',''); }
		$__mysqli = @new mysqli($dbHost,$dbUser,(string)$dbPass,$dbName);
		if ($__mysqli && !$__mysqli->connect_error) {
			sr_log_info('Diagnostic mysqli succeeded while PDO failed (possible PDO auth plugin / driver issue).');
		} else {
			sr_log_error('Diagnostic mysqli failed: '.($__mysqli? $__mysqli->connect_error : 'init error'));
		}
	} catch (Throwable $me) {
		sr_log_error('Diagnostic mysqli exception: '.$me->getMessage());
	}
}
// Emit bootstrap diagnostics
sr_log_info('Bootstrap diagnostics php_version='.phpversion().' pdo_mysql='.(extension_loaded('pdo_mysql')?'yes':'no').' db_available='.(DatabaseManager::available()?'yes':'no'));

// Provide legacy $con only if still needed by untouched code paths
if (!isset($con) || !$con) {
	if ($SR_PDO) {
		// Skip legacy mysqli when PDO is present
		$con = null;
	} else {
		try {
			$dbHost = env('DB_HOST','127.0.0.1');
			$dbName = env('DB_NAME','jcepnzzkmj');
			$dbUser = env('DB_USER','jcepnzzkmj');
			$dbPass = env('DB_PASS', env('DB_PASSWORD',''));
			if ($dbPass === '') { $dbPass = env('DB_PASSWORD',''); }
			$con = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
			if ($con->connect_error) { sr_log_error('Legacy mysqli connect error: '.$con->connect_error); $con = null; }
		} catch (Throwable $e) { sr_log_error('Legacy mysqli fallback exception: '.$e->getMessage()); $con = null; }
	}
}

function sr_db_available(): bool { return class_exists('DatabaseManager') ? DatabaseManager::available() : false; }
function sr_pdo(): ?PDO { return class_exists('DatabaseManager') ? DatabaseManager::pdo() : null; }
function sr_query(string $sql, array $params = []): array {
	$pdo = sr_pdo();
	if (!$pdo) return [];
	try {
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll();
	} catch (Throwable $e) {
		// Structured error log if available
		if (is_file(__DIR__.'/JsonLogger.php')) { require_once __DIR__.'/JsonLogger.php'; (new JsonLogger('store-reports'))->error('sr_query_exception',[ 'sql'=>$sql, 'params'=>$params, 'error'=>$e->getMessage() ]); }
		if (isset($StoreReportsLogger)) { $StoreReportsLogger->error('sr_query exception: '.$e->getMessage()); }
		return [];
	}
}
function sr_query_one(string $sql, array $params = []): ?array {
	$rows = sr_query($sql,$params);
	return $rows[0] ?? null;
}
function sr_exec(string $sql, array $params = []): int {
	$pdo = sr_pdo();
	if (!$pdo) return 0;
	try {
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt->rowCount();
	} catch (Throwable $e) {
		if (is_file(__DIR__.'/JsonLogger.php')) { require_once __DIR__.'/JsonLogger.php'; (new JsonLogger('store-reports'))->error('sr_exec_exception',[ 'sql'=>$sql, 'params'=>$params, 'error'=>$e->getMessage() ]); }
		if (isset($StoreReportsLogger)) { $StoreReportsLogger->error('sr_exec exception: '.$e->getMessage()); }
		return 0;
	}
}

// Basic helper to safely fetch int
function sr_int($v, $default = 0): int { return filter_var($v, FILTER_VALIDATE_INT) !== false ? (int)$v : $default; }
function sr_str($v): string { return trim((string)$v); }

// Upload directory constant
define('SR_UPLOAD_DIR', dirname(__DIR__,2) . '/data/store_reports');
if (!is_dir(SR_UPLOAD_DIR)) { @mkdir(SR_UPLOAD_DIR, 0775, true); }

// Safety headers (basic)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// ✅ CRITICAL FIX: Register shutdown handler to cleanup any mysqli connections
register_shutdown_function(function() {
    global $con, $__mysqli;
    if (isset($con) && $con instanceof mysqli && !empty($con->thread_id)) {
        @$con->close();
    }
    if (isset($__mysqli) && $__mysqli instanceof mysqli && !empty($__mysqli->thread_id)) {
        @$__mysqli->close();
    }
});

// Done bootstrap.
?>
