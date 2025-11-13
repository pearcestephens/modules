<?php
// Store Reports Module Bootstrap
// Initializes environment, DB connection, session, CSRF token helper, and autoloads module classes.

// Load centralized env loader (ensures DB_PASSWORD etc.)
$envLoader = dirname(__DIR__,2) . '/modules/config/env-loader.php';
if (is_file($envLoader)) {
	require_once $envLoader; // defines env(), requireEnv(), lazy loads .env from project root
} else {
	error_log('store-reports bootstrap: env-loader.php not found at '.$envLoader);
}

// Guard direct access path traversal
if (basename(__FILE__) !== 'bootstrap.php') {
	http_response_code(403);
	exit('Forbidden');
}

// Do not start session here; root bootstrap will configure and start sessions (DB-backed)

// Ensure legacy-compatible view 'staff_accounts' exists before loading root bootstrap
try {
	$dbHost = env('DB_HOST','127.0.0.1');
	$dbName = env('DB_NAME','');
	$dbUser = env('DB_USER','');
	$dbPass = env('DB_PASS', env('DB_PASSWORD','')) ?: env('DB_PASSWORD','');
	if ($dbName) {
		$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
		$__tmpPdo = new PDO($dsn, $dbUser, $dbPass, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false
		]);
		$check = $__tmpPdo->prepare("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'staff_accounts'");
		$check->execute();
		$exists = (bool)$check->fetch();
		if (!$exists) {
			$__tmpPdo->exec(
				"CREATE OR REPLACE VIEW staff_accounts AS\n".
				"SELECT\n".
				"  u.id AS id,\n".
				"  u.id AS staff_id,\n".
				"  u.first_name AS first_name,\n".
				"  u.last_name AS last_name,\n".
				"  u.email AS email,\n".
				"  u.email AS username,\n".
				"  u.password AS password_hash,\n".
				"  COALESCE(u.staff_active,1) AS is_active,\n".
				"  NULL AS deleted_at,\n".
				"  NULL AS last_login_at,\n".
				"  u.vend_id AS vend_user_id,\n".
				"  u.deputy_id AS deputy_user_id,\n".
				"  0.00 AS balance,\n".
				"  0.00 AS vend_balance,\n".
				"  u.default_outlet AS outlet_id,\n".
				"  u.role AS role,\n".
				"  u.phone AS phone,\n".
				"  u.image AS avatar\n".
				"FROM users u"
			);
		}
	}
} catch (Throwable $e) {
	// Non-fatal; root bootstrap may handle auth differently. We continue.
}

// Load global app bootstrap if available (shared DB connection, autoload, Auth::init())
$rootBootstrap = dirname(__DIR__, 2) . '/bootstrap.php';
if (file_exists($rootBootstrap)) {
	require_once $rootBootstrap; // defines isAuthenticated(), Auth::init(), etc.
}
// Do not load modules/base/bootstrap.php to avoid function re-declare; root bootstrap already defines auth

// Basic autoload for module-specific classes (models, controllers, services)
spl_autoload_register(function ($class) {
	$base = __DIR__;
	$paths = [
		$base . '/models/' . $class . '.php',
		$base . '/controllers/' . $class . '.php',
		$base . '/services/' . $class . '.php',
		$base . '/services/gpt/' . $class . '.php',
		$base . '/lib/' . $class . '.php'
	];
	foreach ($paths as $p) {
		if (file_exists($p)) {
			require_once $p;
			return;
		}
	}
});

// Simple CSRF token utilities (delegate to core if present)
if (!function_exists('csrf_token')) {
	if (!isset($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	function csrf_token(): string { return $_SESSION['csrf_token']; }
}
if (!function_exists('verify_csrf')) {
	function verify_csrf(): bool {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
			return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
		}
		return true;
	}
}

// Determine if the current request expects JSON (API/AJAX) vs HTML
function sr_wants_json(): bool {
	$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
	$xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
	$uri    = $_SERVER['REQUEST_URI'] ?? '';
	$action = $_GET['action'] ?? '';
	if (stripos($accept, 'application/json') !== false) return true;
	if (stripos($xhr, 'XMLHttpRequest') !== false) return true;
	if (strpos($uri, '/api/') !== false) return true;
	if (is_string($action) && str_starts_with($action, 'api:')) return true;
	return false;
}

// Build absolute URL for redirects
function sr_current_url(): string {
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'staff.vapeshed.co.nz';
	$uri = $_SERVER['REQUEST_URI'] ?? '/';
	return $scheme.'://'.$host.$uri;
}

// Basic auth gate - prefers BASE isAuthenticated(), falls back to legacy/session
if (!function_exists('sr_require_auth')) {
	function sr_require_auth(bool $adminOnly = false): void {
			// Unified detection instead of delegating to core require_login to avoid loops
		// Fallback minimal checks
			// Prefer core isAuthenticated() (supports CIS_SESSION), then fall back to helpers/keys
			$authed = false;
			if (function_exists('isAuthenticated')) {
				// Try default, and explicit key names
				$authed = (bool) isAuthenticated();
				if (!$authed) { $authed = (bool) isAuthenticated('user_id'); }
				if (!$authed) { $authed = (bool) isAuthenticated('userID'); }
			}
			if (!$authed && class_exists('Auth') && method_exists('Auth','check')) {
				// Check with default and key variants
				$authed = (bool) Auth::check('user_id');
				if (!$authed) { $authed = (bool) Auth::check('userID'); }
				if (!$authed) { $authed = (bool) Auth::check(); }
			}
			if (!$authed && function_exists('current_user_id')) { $authed = (bool) current_user_id(); }
			if (!$authed) { $authed = (!empty($_SESSION['user_id'])) || (!empty($_SESSION['userID'])); }
			if (!$authed) {
				// Last-chance: if CIS_SESSION cookie present, attempt restore from DB Session table
				if (!empty($_COOKIE['CIS_SESSION'] ?? null) && function_exists('env')) {
					try {
						$dsn='mysql:host='.(string)env('DB_HOST','127.0.0.1').';dbname='.(string)env('DB_NAME','').';charset=utf8mb4';
						$pdo=new PDO($dsn,(string)env('DB_USER',''),(string)env('DB_PASS',env('DB_PASSWORD','')),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
						// If Session table stores serialized PHP session data, load and populate superglobal
						$st=$pdo->prepare('SELECT Session_Data FROM `Session` WHERE Session_Id = ? AND Session_Expires > NOW() LIMIT 1');
						$st->execute([$_COOKIE['CIS_SESSION']]);
						$row=$st->fetch(PDO::FETCH_ASSOC);
						if ($row && !empty($row['Session_Data'])) {
							// Parse session data if PHP serialized (assume handler writes raw string)
							// We cannot safely unserialize into $_SESSION; just detect presence of user_id markers
							$blob=(string)$row['Session_Data'];
							if (str_contains($blob,'user_id') || str_contains($blob,'userID')) {
								// Consider authenticated if markers found
								$authed=true;
							}
						}
					} catch (Throwable $e) { /* ignore */ }
				}
				if (sr_wants_json()) { sr_json(['success'=>false,'error'=>'Unauthorized'], 401); }
				$uri = $_SERVER['REQUEST_URI'] ?? '';
				if (stripos($uri,'/login.php') !== false || stripos($uri,'/admin-ui-access.php') !== false) {
					// Prevent redirect loop: show minimal login-needed page
					http_response_code(401);
					echo '<h3>Login required</h3>';
					exit;
				}
				if (!$authed) {
					header('Location: /login.php?redirect='.rawurlencode(sr_current_url()), true, 302);
					exit;
				}
			}
		if ($adminOnly) {
			$isAdmin = false;
			if (function_exists('is_admin')) { $isAdmin = (bool) is_admin(); }
			elseif (function_exists('hasPermission')) { $isAdmin = (bool) hasPermission('admin'); }
			elseif (function_exists('getUserRole')) { $isAdmin = (getUserRole() === 'admin'); }
			elseif (isset($_SESSION['role'])) { $isAdmin = ($_SESSION['role'] === 'admin'); }
			if (!$isAdmin) {
				if (sr_wants_json()) { sr_json(['success'=>false,'error'=>'Forbidden'], 403); }
				http_response_code(403);
				echo '<h1>403 Forbidden</h1>';
				exit;
			}
		}
	}
}

// Correlation ID generation
if (!isset($_SESSION['sr_correlation_seed'])) {
	$_SESSION['sr_correlation_seed'] = bin2hex(random_bytes(8));
}
$SR_CORRELATION_ID = $_SESSION['sr_correlation_seed'] . '-' . substr(bin2hex(random_bytes(8)),0,8);
header('X-Correlation-ID: '.$SR_CORRELATION_ID);

// Basic response helper with correlation id injection
function sr_json($data, int $status = 200): void {
	global $SR_CORRELATION_ID;
	if (is_array($data)) {
		$data['correlation_id'] = $SR_CORRELATION_ID;
	}
	http_response_code($status);
	header('Content-Type: application/json');
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

// Rate limiter (very simple in-memory per session)
function sr_rate_limit(string $key, int $maxPerMinute = 30): void {
	$bucket =& $_SESSION['rate_'.$key];
	$now = time();
	if (!is_array($bucket)) { $bucket = ['window' => $now, 'count' => 0]; }
	if ($now - $bucket['window'] > 60) { $bucket = ['window' => $now, 'count' => 0]; }
	if (++$bucket['count'] > $maxPerMinute) {
		http_response_code(429);
		exit('Rate limit exceeded');
	}
}

// Minimal logger fallback if CISLogger absent
if (!class_exists('CISLogger')) {
	class CISLogger {
		private $channel;
		public function __construct($channel) { $this->channel = $channel; }
		public function log($level, $msg) {
			error_log('[store-reports]['.$this->channel.']['.$level.'] '.$msg);
		}
		public function info($msg){ $this->log('INFO',$msg); }
		public function error($msg){ $this->log('ERROR',$msg); }
	}
}

// Export global module logger
$StoreReportsLogger = new CISLogger('store-reports');

// Safe logger helpers (handle different CISLogger interfaces)
function sr_log_info($msg): void {
	global $StoreReportsLogger;
	if (isset($StoreReportsLogger)) {
		if (method_exists($StoreReportsLogger,'info')) { $StoreReportsLogger->info($msg); return; }
		if (method_exists($StoreReportsLogger,'log')) { $StoreReportsLogger->log('INFO',$msg); return; }
	}
	error_log('[store-reports][INFO] '.$msg);
}
function sr_log_error($msg): void {
	global $StoreReportsLogger;
	if (isset($StoreReportsLogger)) {
		if (method_exists($StoreReportsLogger,'error')) { $StoreReportsLogger->error($msg); return; }
		if (method_exists($StoreReportsLogger,'log')) { $StoreReportsLogger->log('ERROR',$msg); return; }
	}
	error_log('[store-reports][ERROR] '.$msg);
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
