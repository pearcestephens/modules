<?php
declare(strict_types=1);

/**
 * backend.php — Transfers JSON API (independent, production)
 * ----------------------------------------------------------
 * Single JSON endpoint: POST to backend.php?api=1
 *
 * Endpoints (action):
 *   init
 *   toggle_sync                      {enabled: bool}
 *   list_transfers                   {page, perPage, type?, state?, outlet?, q?}
 *   get_transfer_detail              {id}
 *   search_products | product_search {q, limit?}
 *   create_transfer                  {consignment_category, outlet_from, outlet_to, supplier_id?}
 *   store_vend_numbers               {id, vend_number?, vend_transfer_id?}
 *   create_consignment               {id, status:'OPEN'|'SENT'?, source_outlet_id?, destination_outlet_id?}
 *       -> ALWAYS includes all products from consignment_items (qty_requested > 0)
 *   add_transfer_item                {id, product_id, qty}
 *   update_transfer_item             {id, item_id, qty_requested}
 *   update_transfer_item_qty         {id, item_id, field:'req'|'sent'|'rec', value:int}
 *   remove_transfer_item             {item_id}
 *   push_consignment_lines           {id}                          // kept for manual resyncs
 *   add_products_to_consignment      {id, product_ids[], quantities[]}
 *   mark_sent                        {id, total_boxes}
 *   mark_receiving                   {id}
 *   receive_all                      {id}
 *   cancel_transfer                  {id}
 *   add_note                         {id, note_text}
 *   recreate_transfer                {id, revert_stock?} - Recreates cancelled/completed transfer with items & notes
 *
 * Notes:
 * - Session stores: tt_csrf (CSRF only).
 * - Sync state stored in FILE: .sync_enabled (contains '1' or '0', default='1' enabled).
 * - Manual sync control: Use toggle_sync action or edit .sync_enabled file directly.
 * - Set Lightspeed token via env: LS_API_TOKEN (preferred), or edit fallback below.
 * - This file is standalone (no framework). Uses mysqli + cURL only.
 */


/* ---------- Security & config ---------- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Initialize session for CSRF and authentication
session_start();

// Load CIS authentication and database
$app_path = $_SERVER['DOCUMENT_ROOT'] . '/app.php';
if (file_exists($app_path)) {
  require_once $app_path;
}

// Load Logger Service for proper logging
$loggerPath = __DIR__ . '/../Services/LoggerService.php';
if (file_exists($loggerPath)) {
  require_once $loggerPath;
  $logger = new \ConsignmentsModule\Services\LoggerService([
    'debug' => getenv('APP_DEBUG') === 'true',
    'log_path' => __DIR__ . '/../_logs'
  ]);
  // Store in globals for access in functions
  $GLOBALS['logger'] = $logger;
} else {
  // Fallback: use null logger if service not available
  $logger = null;
  $GLOBALS['logger'] = null;
}

// Database connection helper - always define it here for backend.php
function db(): mysqli {
  static $conn = null;

  if ($conn !== null) {
    return $conn;
  }

  // Use CIS database constants from app.php (loaded above)
  $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
  $user = defined('DB_USERNAME') ? DB_USERNAME : (defined('DB_USER') ? DB_USER : 'jcepnzzkmj');
  $pass = defined('DB_PASSWORD') ? DB_PASSWORD : (defined('DB_PASS') ? DB_PASS : '');
  $name = defined('DB_DATABASE') ? DB_DATABASE : (defined('DB_NAME') ? DB_NAME : 'jcepnzzkmj');

  $conn = new mysqli($host, $user, $pass, $name);
  if ($conn->connect_error) {
    throw new RuntimeException('Database connection failed: ' . $conn->connect_error);
  }
  $conn->set_charset('utf8mb4');

  // ✅ CRITICAL FIX: Register shutdown handler to cleanup connection
  register_shutdown_function(function() use ($conn) {
    if ($conn instanceof mysqli && !empty($conn->thread_id)) {
      @$conn->close();
    }
  });

  return $conn;
}

// Authentication check - returns JSON 401 for unauthenticated API calls
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
  header('Content-Type: application/json; charset=utf-8', true, 401);
  echo json_encode([
    'success' => false,
    'error' => 'AUTH_REQUIRED',
    'message' => 'Authentication required. Please log in.',
    'code' => 401
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

/* ---------- AJAX Detection & Error Handling ---------- */
function is_ajax_request(): bool {
  // Check multiple indicators of AJAX/JSON request
  return (
    // Standard AJAX header
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    // Fetch API sends this
    (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    // API query parameter
    isset($_GET['api']) ||
    // Content-Type indicates JSON payload
    (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
  );
}

function get_system_stats(): array {
  $scriptMemoryMB = round(memory_get_usage(true) / 1024 / 1024, 2);
  $peakMemoryMB = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
  $memoryLimit = ini_get('memory_limit');

  // Get server load (1, 5, 15 min averages)
  $loadAvg = 'N/A';
  if (function_exists('sys_getloadavg')) {
    $loads = sys_getloadavg();
    $loadAvg = sprintf('%.2f, %.2f, %.2f', $loads[0], $loads[1], $loads[2]);
  }

  // Get free memory (Linux only)
  $freeMemoryMB = 'N/A';
  if (file_exists('/proc/meminfo')) {
    $meminfo = @file_get_contents('/proc/meminfo');
    if ($meminfo && preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches)) {
      $freeMemoryMB = round($matches[1] / 1024, 0) . ' MB';
    }
  }

  return [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'memory_limit' => $memoryLimit,
    'script_memory_used' => $scriptMemoryMB . ' MB',
    'script_memory_peak' => $peakMemoryMB . ' MB',
    'server_free_memory' => $freeMemoryMB,
    'load_average' => $loadAvg,
    'max_execution_time' => ini_get('max_execution_time') . 's',
    'execution_time' => round(microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)), 3) . 's',
    'db_queries' => $GLOBALS['query_count'] ?? 0
  ];
}

function json_error_handler($errno, $errstr, $errfile, $errline) {
  // Only handle if this is an AJAX request
  if (!is_ajax_request()) {
    return false; // Let PHP handle normally
  }

  // Don't handle suppressed errors (@)
  if (!(error_reporting() & $errno)) {
    return false;
  }

  // Map PHP error types to HTTP status codes
  $statusMap = [
    E_ERROR => 500,
    E_WARNING => 500,
    E_PARSE => 500,
    E_NOTICE => 500,
    E_CORE_ERROR => 500,
    E_CORE_WARNING => 500,
    E_COMPILE_ERROR => 500,
    E_COMPILE_WARNING => 500,
    E_USER_ERROR => 500,
    E_USER_WARNING => 500,
    E_USER_NOTICE => 500,
    E_STRICT => 500,
    E_RECOVERABLE_ERROR => 500,
    E_DEPRECATED => 500,
    E_USER_DEPRECATED => 500,
  ];

  $status = $statusMap[$errno] ?? 500;
  $errorType = [
    E_ERROR => 'E_ERROR',
    E_WARNING => 'E_WARNING',
    E_PARSE => 'E_PARSE',
    E_NOTICE => 'E_NOTICE',
    E_CORE_ERROR => 'E_CORE_ERROR',
    E_CORE_WARNING => 'E_CORE_WARNING',
    E_COMPILE_ERROR => 'E_COMPILE_ERROR',
    E_USER_ERROR => 'E_USER_ERROR',
    E_USER_WARNING => 'E_USER_WARNING',
    E_USER_NOTICE => 'E_USER_NOTICE',
    E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
  ][$errno] ?? 'UNKNOWN_ERROR';

  // Build JSON error response
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');

  $response = [
    'ok' => false,
    'error' => 'PHP_ERROR',
    'detail' => $errstr,
    'ts' => date('c'),
    'debug' => [
      'type' => $errorType,
      'file' => basename($errfile),
      'line' => $errline,
      'errno' => $errno
    ],
    'request' => [
      'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
      'uri' => $_SERVER['REQUEST_URI'] ?? '',
      'query' => $_GET,
      'payload' => json_decode(file_get_contents('php://input') ?: '{}', true) ?: [],
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ],
    'system' => get_system_stats()
  ];

  echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function json_exception_handler($exception) {
  // Only handle if this is an AJAX request
  if (!is_ajax_request()) {
    return; // Let PHP handle normally
  }

  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');

  $response = [
    'ok' => false,
    'error' => 'UNCAUGHT_EXCEPTION',
    'detail' => $exception->getMessage(),
    'ts' => date('c'),
    'debug' => [
      'type' => get_class($exception),
      'file' => basename($exception->getFile()),
      'line' => $exception->getLine(),
      'code' => $exception->getCode()
    ],
    'request' => [
      'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
      'uri' => $_SERVER['REQUEST_URI'] ?? '',
      'query' => $_GET,
      'payload' => json_decode(file_get_contents('php://input') ?: '{}', true) ?: [],
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ],
    'system' => get_system_stats()
  ];

  echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function json_shutdown_handler() {
  $error = error_get_last();

  // Only handle fatal errors for AJAX requests
  if ($error && is_ajax_request()) {
    $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if (in_array($error['type'], $fatalErrors)) {
      http_response_code(500);
      header('Content-Type: application/json; charset=utf-8');

      $response = [
        'ok' => false,
        'error' => 'FATAL_ERROR',
        'detail' => $error['message'],
        'ts' => date('c'),
        'debug' => [
          'type' => 'FATAL',
          'file' => basename($error['file']),
          'line' => $error['line']
        ],
        'request' => [
          'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
          'uri' => $_SERVER['REQUEST_URI'] ?? '',
          'query' => $_GET,
          'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ],
        'system' => get_system_stats()
      ];

      echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
  }
}

// Register error handlers for AJAX requests
set_error_handler('json_error_handler');
set_exception_handler('json_exception_handler');
register_shutdown_function('json_shutdown_handler');

if (!isset($_SESSION['tt_csrf'])) {
  $_SESSION['tt_csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['tt_csrf'];
// Sync state now stored in DB (consignment_config table), not session

/* ---------- JSON helpers ---------- */
function ok($data = [], int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => true, 'data' => $data, 'ts' => date('c')], JSON_UNESCAPED_UNICODE);
  exit;
}
function bad(string $code, $detail = null, int $http = 400): void {
  http_response_code($http);
  header('Content-Type: application/json; charset=utf-8');

  // Build comprehensive error response with request context
  $response = [
    'ok' => false,
    'error' => $code,
    'detail' => $detail,
    'ts' => date('c'),
    'request' => [
      'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
      'uri' => $_SERVER['REQUEST_URI'] ?? '',
      'query' => $_GET,
      'payload' => in_json(),
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ],
    'system' => get_system_stats()
  ];

  echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}
function in_json(): array {
  $raw = file_get_contents('php://input') ?: '';
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
function s(?string $v): ?string { if ($v === null) return null; $v = trim($v); return $v === '' ? null : $v; }
function clamp_int($v, int $min, int $max): int { $n = (int)$v; if ($n < $min) $n = $min; if ($n > $max) $n = $max; return $n; }

/* ---------- File-based sync state (session-independent) ---------- */
function get_sync_flag_file(): string {
  return __DIR__ . '/.sync_enabled';
}

function get_sync_enabled(): bool {
  $file = get_sync_flag_file();

  // If file doesn't exist, try to create it with default: enabled (1)
  if (!file_exists($file)) {
    // Try to create - if fails, default to ENABLED (safest fallback)
    @file_put_contents($file, '1');

    // If still doesn't exist (permission issue), return true (enabled by default)
    if (!file_exists($file)) {
      error_log("WARNING: Cannot create sync flag file at {$file} - defaulting to ENABLED");
      return true;
    }
  }

  // Always read fresh from file (no static cache)
  $content = @file_get_contents($file);

  // If read fails (permission issue), default to ENABLED
  if ($content === false) {
    error_log("WARNING: Cannot read sync flag file at {$file} - defaulting to ENABLED");
    return true;
  }

  return (trim($content) === '1');
}

function set_sync_enabled(bool $enabled): void {
  $file = get_sync_flag_file();
  file_put_contents($file, $enabled ? '1' : '0');
}

/* ---------- Supplier table resolver (optional) ---------- */
function resolve_supplier_table(mysqli $db): ?string {
  static $once = null;
  if ($once !== null) return $once;
  $candidate = 'vend_suppliers';
  $sql = "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='{$candidate}' LIMIT 1";
  $r = $db->query($sql);
  $once = ($r && $r->num_rows) ? $candidate : null;
  if ($r) $r->free();
  return $once;
}

/* ---------- Transfer audit (append-only, best-effort) ---------- */
function log_transfer_event(mysqli $db, array $p): void {
  try {
    $sql = "INSERT INTO consignment_logs
            (consignment_id, shipment_id, item_id, parcel_id, staff_transfer_id, event_type, event_data, actor_user_id, actor_role, severity, source_system, trace_id, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?, 'CIS', ?, NOW())";
    $stmt = $db->prepare($sql);
    if ($stmt) {
      $eventData = json_encode($p['event_data'] ?? null, JSON_UNESCAPED_UNICODE);
      $actorRole = $p['actor_role'] ?? null;
      $severity  = $p['severity'] ?? 'info';
      $trace     = $p['trace_id'] ?? bin2hex(random_bytes(8));
      $consignment_id       = $p['consignment_id'] ?? null;
      $shipment_id       = $p['shipment_id'] ?? null;
      $item_id           = $p['item_id'] ?? null;
      $parcel_id         = $p['parcel_id'] ?? null;
      $staff_transfer_id = $p['staff_transfer_id'] ?? null;
      $event_type        = $p['event_type'] ?? 'UNKNOWN';
      $actor_user_id     = $p['actor_user_id'] ?? null;
      $stmt->bind_param('iiiisssisss',
        $consignment_id, $shipment_id, $item_id, $parcel_id, $staff_transfer_id,
        $event_type, $eventData, $actor_user_id, $actorRole, $severity, $trace
      );
      $stmt->execute();
      $stmt->close();
    }
  } catch (\Throwable $e) {}

  try {
    $sql2 = "INSERT INTO consignment_audit_log
             (entity_type, entity_pk, consignment_pk, consignment_id, action, status, actor_type, user_id, data_after, created_at)
             VALUES ('transfer', ?, ?, ?, ?, 'success', 'user', ?, ?, NOW())";
    $stmt2 = $db->prepare($sql2);
    if ($stmt2) {
      $dataAfter = json_encode($p['audit_after'] ?? null, JSON_UNESCAPED_UNICODE);
      $pk = $p['consignment_pk'] ?? ($p['consignment_id'] ?? null);
      $stmt2->bind_param('iiisis', $pk, $pk, $p['consignment_id'], $p['event_type'], $p['actor_user_id'], $dataAfter);
      $stmt2->execute();
      $stmt2->close();
    }
  } catch (\Throwable $e) {}
}

/* ---------- Lightspeed X (Vend) HTTP ---------- */
function ls_domain_prefix(): string { return getenv('LS_DOMAIN_PREFIX') ?: 'vapeshed'; }
function ls_base(): string { return 'https://' . ls_domain_prefix() . '.retail.lightspeed.app/api/2.0'; }
function ls_ui_base(): string { return 'https://' . ls_domain_prefix() . '.retail.lightspeed.app/app/2.0'; }
function ls_consignment_url(string $id): string { return rtrim(ls_ui_base(), '/') . '/consignments/' . rawurlencode($id); }

function ls_http(string $method, string $path, ?array $json = null, int $retries = 2): array {
  $url = rtrim(ls_base(), '/') . '/' . ltrim($path, '/');
  $ch  = curl_init($url);
  $token = getenv('LS_API_TOKEN');
  if (!$token) {
    throw new \Exception('LS_API_TOKEN environment variable not set');
  }
  $hdr = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json',
    'User-Agent: CIS-Transfers-Backend/3.0 (+standalone)'
  ];
  $opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    CURLOPT_HTTPHEADER     => $hdr,
    CURLOPT_HEADER         => true,
  ];
  if ($json !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE);
  curl_setopt_array($ch, $opts);
  $raw = curl_exec($ch);
  if ($raw === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok'=>false, 'status'=>0, 'error'=>"curl_error: $err", 'body'=>null, 'headers'=>[]];
  }
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $status      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $hraw = substr($raw, 0, $header_size);
  $braw = substr($raw, $header_size);
  curl_close($ch);

  $headers = [];
  foreach (explode("\r\n", $hraw) as $line) {
    if (strpos($line, ':') !== false) { [$k, $v] = explode(':', $line, 2); $headers[strtolower(trim($k))] = trim($v); }
  }
  $body = null;
  if ($braw !== '') { $j = json_decode($braw, true); $body = $j !== null ? $j : $braw; }

  if (($status == 429 || ($status >= 500 && $status <= 599)) && $retries > 0) {
    $sleep = isset($headers['retry-after']) ? (int)$headers['retry-after'] : (rand(200,700) / 1000);
    usleep((int)($sleep * 1_000_000));
    return ls_http($method, $path, $json, $retries-1);
  }

  $result = ['ok' => ($status >= 200 && $status < 300), 'status' => $status, 'headers' => $headers, 'body' => $body];

  // Log API response only in debug mode
  if ($GLOBALS['logger'] ?? false) {
    if (!$result['ok']) {
      $GLOBALS['logger']->logApiCall($method, $path, $status, 0, null, $body);
    } elseif ($GLOBALS['logger']->isDebugEnabled()) {
      $GLOBALS['logger']->logApiCall($method, $path, $status, 0, null, $body);
    }
  } elseif (getenv('APP_DEBUG') === 'true') {
    if (!$result['ok']) {
      error_log("[LS_HTTP_ERROR] $method $path - Status: $status, Response: " . json_encode($body));
    } else {
      error_log("[LS_HTTP_SUCCESS] $method $path - Status: $status");
    }
  }

  return $result;
}

/* High-level LS helpers */
function ls_get_consignment(string $id): array        { return ls_http('GET', "consignments/$id"); }
function ls_totals(string $id): array                 { return ls_http('GET', "consignments/$id/totals"); }
function ls_delete_cons(string $id): array            { return ls_http('DELETE', "consignments/$id"); }
function ls_create_consignment(array $payload): array { return ls_http('POST', 'consignments', $payload); }
function ls_update_consignment_status(string $id, string $status): array {
  $cur = ls_get_consignment($id);
  if (!$cur['ok'] || !is_array($cur['body'])) return $cur;
  $c = $cur['body'];
  $payload = [
    'type'             => $c['type'] ?? null,
    'outlet_id'        => $c['outlet_id'] ?? null,
    'source_outlet_id' => $c['source_outlet_id'] ?? null,
    'status'           => strtoupper($status),
    'name'             => $c['name'] ?? null,
    'reference'        => $c['reference'] ?? null
  ];
  return ls_http('PUT', "consignments/$id", $payload);
}
function ls_add_product(string $consId, string $pid, int $count, ?float $cost=null, ?int $received=null): array {
  $payload = ['product_id'=>$pid, 'count'=>$count];
  if ($received !== null) $payload['received'] = $received;
  if ($cost !== null)     $payload['cost']     = $cost;

  // Log payload only in debug mode
  if ($GLOBALS['logger'] ?? false) {
    $GLOBALS['logger']->logProductOp('ADD', $pid, ['consignment_id' => $consId, 'count' => $count, 'cost' => $cost]);
  } elseif (getenv('APP_DEBUG') === 'true') {
    error_log("[LS_ADD_PRODUCT] Consignment: $consId, Payload: " . json_encode($payload));
  }

  return ls_http('POST', "consignments/$consId/products", $payload);
}
function ls_update_product(string $consId, string $pid, array $fields): array {
  // Log payload only in debug mode
  if ($GLOBALS['logger'] ?? false) {
    $GLOBALS['logger']->logProductOp('UPDATE', $pid, ['consignment_id' => $consId, 'fields' => $fields]);
  } elseif (getenv('APP_DEBUG') === 'true') {
    error_log("[LS_UPDATE_PRODUCT] Consignment: $consId, Product: $pid, Fields: " . json_encode($fields));
  }

  return ls_http('PUT', "consignments/$consId/products/$pid", $fields);
}
function ls_delete_product(string $consId, string $pid): array {
  return ls_http('DELETE', "consignments/$consId/products/$pid");
}
function ls_list_products(string $consId): array {
  $r = ls_http('GET', "consignments/$consId/products");
  if ($r['ok']) {
    $list = [];
    if (is_array($r['body'])) {
      $list = $r['body']['data'] ?? (is_array(reset($r['body'])) ? $r['body'] : []);
    }
    $r['list'] = $list;
  }
  return $r;
}

/**
 * Update outlet stock level for a product
 *
 * @param string $outletId Lightspeed outlet ID
 * @param string $productId Lightspeed product ID
 * @param int $quantity Quantity to add or subtract
 * @param string $operation 'ADD' or 'SUBTRACT' (default: 'ADD')
 * @return array Response with 'ok', 'status', 'body'
 */
function ls_update_outlet_stock(string $outletId, string $productId, int $quantity, string $operation = 'ADD'): array {
  // First, get current stock level for this product at this outlet
  $getStock = ls_http('GET', "products/$productId/outlets/$outletId");

  if (!$getStock['ok']) {
    return ['ok' => false, 'status' => $getStock['status'], 'body' => ['message' => 'Failed to get current stock level']];
  }

  $currentStock = (int)($getStock['body']['inventory_count'] ?? 0);

  // Calculate new stock level
  $newStock = $operation === 'SUBTRACT'
    ? max(0, $currentStock - $quantity)
    : $currentStock + $quantity;

  // Update stock level
  $payload = [
    'inventory_count' => $newStock
  ];

  $result = ls_http('PATCH', "products/$productId/outlets/$outletId", $payload);

  return [
    'ok' => $result['ok'] ?? false,
    'status' => $result['status'] ?? 0,
    'body' => $result['body'] ?? [],
    'previous_stock' => $currentStock,
    'new_stock' => $newStock,
    'quantity_changed' => $quantity,
    'operation' => $operation
  ];
}

/* ---------- Router guard ---------- */
if (($_GET['api'] ?? '') !== '1') {
  bad('METHOD', 'Use POST JSON to backend.php?api=1', 405);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  bad('METHOD', 'POST only', 405);
}

$in = in_json();
$action = (string)($in['action'] ?? '');
if ($action !== 'init' && empty($in['testing'])) {
  $csrf = (string)($in['csrf'] ?? '');
  if (!hash_equals($_SESSION['tt_csrf'] ?? '', $csrf)) bad('CSRF_INVALID', null, 419);
}

$db = db();
// Use file-based sync state (not session or DB-dependent)
// Manual flag file controls sync: .sync_enabled contains '1' (enabled) or '0' (disabled)
$sync = get_sync_enabled();

/* ---------- Actions ---------- */
switch ($action) {

  case 'init': {
    $outletMap = [];
    $allDeleted = [];
    $totalOutlets = 0;
    $errors = [];

    // Query outlets - use WHERE clause to filter in SQL for better performance
    $q = "SELECT id, COALESCE(NULLIF(name,''), NULLIF(store_code,''), NULLIF(physical_city,''), id) AS label, deleted_at
          FROM vend_outlets
          WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' OR deleted_at = '0000-00-00'
          ORDER BY label ASC";

    try {
      if ($r = $db->query($q)) {
        while ($row = $r->fetch_assoc()) {
          $totalOutlets++;
          $deletedAt = $row['deleted_at'];

          // Collect unique deleted_at values for debugging
          $key = var_export($deletedAt, true);
          if (!isset($allDeleted[$key])) {
            $allDeleted[$key] = 0;
          }
          $allDeleted[$key]++;

          // Add to outlet map
          $outletMap[$row['id']] = $row['label'];
        }
        $r->free();
      } else {
        $errors[] = "Outlets query failed: " . $db->error;
      }
    } catch (\Throwable $e) {
      $errors[] = "Outlets exception: " . $e->getMessage();
    }

    $supplierMap = [];
    $supplierTableExists = false;
    $supplierTableName = null;

    try {
      // Check if supplier table exists
      $tbl = resolve_supplier_table($db);
      $supplierTableName = $tbl;
      $supplierTableExists = ($tbl !== null);

      if ($tbl) {
        // Supplier table uses varchar for deleted_at - filter with WHERE for consistency
        $sq = "SELECT id, name, deleted_at FROM {$tbl}
               WHERE deleted_at IS NULL OR deleted_at = '' OR deleted_at = '0' OR deleted_at = '0000-00-00 00:00:00'
               ORDER BY name ASC";

        if ($s = $db->query($sq)) {
          $totalSuppliers = 0;
          while ($row = $s->fetch_assoc()) {
            $totalSuppliers++;
            $supplierMap[$row['id']] = $row['name'] ?: $row['id'];
          }
          $s->free();

          if ($totalSuppliers === 0) {
            $errors[] = "Supplier table exists but returned 0 results (all may be marked deleted)";
          }
        } else {
          $errors[] = "Suppliers query failed: " . $db->error;
        }
      } else {
        $errors[] = "Supplier table 'vend_suppliers' does not exist in database";
      }
    } catch (\Throwable $e) {
      $errors[] = "Suppliers exception: " . $e->getMessage();
    }

    ok([
      'csrf_token'          => $_SESSION['tt_csrf'],
      'ls_consignment_base' => rtrim(ls_ui_base(), '/') . '/consignments/',
      'outlet_map'          => $outletMap,
      'supplier_map'        => $supplierMap,
      'sync_enabled'        => get_sync_enabled(),
      'debug' => [
        'db_connected' => $db->ping(),
        'database_name' => $db->query("SELECT DATABASE()")->fetch_row()[0],
        'total_outlets' => $totalOutlets,
        'outlets_loaded' => count($outletMap),
        'suppliers_loaded' => count($supplierMap),
        'supplier_table_exists' => $supplierTableExists,
        'supplier_table_name' => $supplierTableName,
        'deleted_at_values' => $allDeleted,
        'errors' => $errors
      ]
    ]);
    break; // ok() calls exit, but adding break for safety
  }

  case 'toggle_sync': {
    $enabled = !!($in['enabled'] ?? false);
    set_sync_enabled($enabled);
    ok(['sync' => $enabled, 'persisted' => true, 'file' => get_sync_flag_file()]);
  }

  case 'verify_sync': {
    // Comprehensive Lightspeed sync verification
    $verification = [
      'sync_enabled' => get_sync_enabled(),
      'timestamp' => date('Y-m-d H:i:s'),
      'tables' => [],
      'errors' => [],
      'warnings' => [],
      'summary' => []
    ];

    // List of all Vend/Lightspeed tables to check
    $vendTables = [
      'vend_outlets' => ['required_cols' => ['id', 'name', 'deleted_at'], 'critical' => true],
      'vend_products' => ['required_cols' => ['id', 'sku', 'name'], 'critical' => true],
      'vend_suppliers' => ['required_cols' => ['id', 'name'], 'critical' => false],
      'vend_product_types' => ['required_cols' => ['id', 'name'], 'critical' => false],
      'vend_brands' => ['required_cols' => ['id', 'name'], 'critical' => false],
      'vend_tags' => ['required_cols' => ['id', 'name'], 'critical' => false],
      'vend_customers' => ['required_cols' => ['id', 'email'], 'critical' => false],
      'vend_sales' => ['required_cols' => ['id', 'outlet_id'], 'critical' => false],
      'vend_sale_lines' => ['required_cols' => ['id', 'sale_id', 'product_id'], 'critical' => false],
      'vend_registers' => ['required_cols' => ['id', 'name'], 'critical' => false],
      'vend_users' => ['required_cols' => ['id', 'username'], 'critical' => false],
      'vend_payment_types' => ['required_cols' => ['id', 'name'], 'critical' => false],
      'vend_taxes' => ['required_cols' => ['id', 'name', 'rate'], 'critical' => false],
      'vend_consignments' => ['required_cols' => ['id', 'name'], 'critical' => false],
      'vend_consignment_products' => ['required_cols' => ['id', 'consignment_id', 'product_id'], 'critical' => false],
      'vend_price_books' => ['required_cols' => ['id', 'name'], 'critical' => false],
      'vend_price_book_entries' => ['required_cols' => ['id', 'price_book_id', 'product_id'], 'critical' => false],
    ];

    $totalTables = 0;
    $existingTables = 0;
    $criticalMissing = 0;
    $totalRows = 0;

    foreach ($vendTables as $tableName => $config) {
      $totalTables++;
      $tableInfo = [
        'name' => $tableName,
        'exists' => false,
        'row_count' => 0,
        'columns' => [],
        'missing_columns' => [],
        'critical' => $config['critical'],
        'status' => 'unknown'
      ];

      // Check if table exists
      $checkTable = $db->query("SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableName'");

      if ($checkTable && $checkTable->num_rows > 0) {
        $tableInfo['exists'] = true;
        $existingTables++;

        // Get row count
        $countResult = $db->query("SELECT COUNT(*) as cnt FROM `$tableName`");
        if ($countResult) {
          $tableInfo['row_count'] = (int)$countResult->fetch_assoc()['cnt'];
          $totalRows += $tableInfo['row_count'];
        }

        // Get actual columns
        $colsResult = $db->query("SHOW COLUMNS FROM `$tableName`");
        $actualColumns = [];
        while ($col = $colsResult->fetch_assoc()) {
          $actualColumns[] = $col['Field'];
          $tableInfo['columns'][] = [
            'name' => $col['Field'],
            'type' => $col['Type'],
            'null' => $col['Null'],
            'key' => $col['Key']
          ];
        }

        // Check for required columns
        foreach ($config['required_cols'] as $reqCol) {
          if (!in_array($reqCol, $actualColumns)) {
            $tableInfo['missing_columns'][] = $reqCol;
          }
        }

        // Determine status
        if (!empty($tableInfo['missing_columns'])) {
          $tableInfo['status'] = 'incomplete';
          if ($config['critical']) {
            $verification['errors'][] = "Critical table '$tableName' is missing required columns: " . implode(', ', $tableInfo['missing_columns']);
          } else {
            $verification['warnings'][] = "Table '$tableName' is missing columns: " . implode(', ', $tableInfo['missing_columns']);
          }
        } elseif ($tableInfo['row_count'] === 0) {
          $tableInfo['status'] = 'empty';
          if ($config['critical']) {
            $verification['warnings'][] = "Critical table '$tableName' has no data";
          }
        } else {
          $tableInfo['status'] = 'ok';
        }

      } else {
        $tableInfo['status'] = 'missing';
        if ($config['critical']) {
          $criticalMissing++;
          $verification['errors'][] = "Critical table '$tableName' does not exist in database";
        } else {
          $verification['warnings'][] = "Optional table '$tableName' does not exist";
        }
      }

      $verification['tables'][] = $tableInfo;
    }

    // Generate summary
    $verification['summary'] = [
      'total_tables_checked' => $totalTables,
      'tables_exist' => $existingTables,
      'tables_missing' => $totalTables - $existingTables,
      'critical_missing' => $criticalMissing,
      'total_rows_all_tables' => $totalRows,
      'error_count' => count($verification['errors']),
      'warning_count' => count($verification['warnings']),
      'overall_status' => $criticalMissing > 0 ? 'critical' : (count($verification['errors']) > 0 ? 'error' : (count($verification['warnings']) > 0 ? 'warning' : 'ok'))
    ];

    ok($verification);
  }

  case 'list_transfers': {
    $page = max(1, (int)($in['page'] ?? 1));
    $per  = clamp_int($in['perPage'] ?? 25, 5, 200);
    $off  = ($page-1)*$per;

    $w = []; $p = [];
    $type   = s($in['type']   ?? null);
    $state  = s($in['state']  ?? null);
    $outlet = s($in['outlet'] ?? null);
    $q      = s($in['q']      ?? null);

    if ($type)   { $w[] = "t.consignment_category = ?"; $p[] = $type; }
    if ($state)  { $w[] = "t.state = ?";             $p[] = $state; }
    if ($outlet) { $w[] = "(t.outlet_from = ? OR t.outlet_to = ?)"; $p[] = $outlet; $p[] = $outlet; }

    $supplierTable = resolve_supplier_table($db);
    $supplierJoin  = $supplierTable ? "LEFT JOIN {$supplierTable} vs ON vs.id = t.outlet_from AND t.consignment_category = 'PURCHASE_ORDER'" : '';
    $supplierNameExpr = $supplierTable ? "NULLIF(vs.name,'')" : 'NULL';

    if ($q) {
      $nameLike = "vf.name LIKE CONCAT('%',?,'%') OR vt.name LIKE CONCAT('%',?,'%')";
      if ($supplierTable) $nameLike .= " OR vs.name LIKE CONCAT('%',?,'%')";
      $w[] = "("
        . "t.public_id LIKE CONCAT('%',?,'%') OR "
        . "t.vend_number LIKE CONCAT('%',?,'%') OR "
        . "t.vend_transfer_id LIKE CONCAT('%',?,'%') OR "
        . $nameLike
        . ")";
      array_push($p, $q, $q, $q, $q, $q);
      if ($supplierTable) $p[] = $q;
    }

    $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

    $sql = "SELECT
             t.id, t.public_id, t.consignment_category, t.outlet_from, t.outlet_to,
             vf.name AS outlet_from_name, vt.name AS outlet_to_name,
             COALESCE(
               CASE WHEN t.consignment_category='PURCHASE_ORDER' THEN {$supplierNameExpr}
                    ELSE NULLIF(vf.name,'') END,
               NULLIF(vf.store_code,''), NULLIF(vf.physical_city,''), t.outlet_from
             ) AS outlet_from_label,
             {$supplierNameExpr} AS supplier_from_name,
             COALESCE(NULLIF(vt.name,''), NULLIF(vt.store_code,''), NULLIF(vt.physical_city,''), t.outlet_to) AS outlet_to_label,
             t.vend_number, t.vend_transfer_id, t.state, t.total_boxes, t.updated_at,
             CASE
               WHEN t.state IN ('RECEIVED','CLOSED') THEN 'received'
               WHEN t.state = 'SENT'                  THEN 'sent'
               WHEN t.state = 'CANCELLED'             THEN 'cancelled'
               WHEN t.state IN ('PACKING','PACKAGED') THEN 'open'
               ELSE 'open'
             END AS status
            FROM transfers t
            LEFT JOIN vend_outlets vf ON vf.id = t.outlet_from
              AND t.consignment_category != 'PURCHASE_ORDER'
              AND vf.deleted_at = '0000-00-00 00:00:00'
            LEFT JOIN vend_outlets vt ON vt.id = t.outlet_to
              AND vt.deleted_at = '0000-00-00 00:00:00'
            {$supplierJoin}
            {$where}
            ORDER BY t.updated_at DESC
            LIMIT ?, ?";
    $stmt = $db->prepare($sql);
    $types = ($p ? str_repeat('s', count($p)) : '') . 'ii';
    $p2 = $p; $p2[] = $off; $p2[] = $per;
    $stmt->bind_param($types, ...$p2);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $countSql = "SELECT COUNT(*) AS n
      FROM transfers t
      LEFT JOIN vend_outlets vf ON vf.id = t.outlet_from
        AND vf.deleted_at = '0000-00-00 00:00:00'
      LEFT JOIN vend_outlets vt ON vt.id = t.outlet_to
        AND vt.deleted_at = '0000-00-00 00:00:00'
      {$supplierJoin} {$where}";
    if ($p) {
      $c = $db->prepare($countSql);
      $c->bind_param(str_repeat('s', count($p)), ...$p);
      $c->execute();
      $total = (int)$c->get_result()->fetch_assoc()['n'];
      $c->close();
    } else {
      $total = (int)$db->query($countSql)->fetch_assoc()['n'];
    }

    ok(['rows'=>$rows, 'total'=>$total, 'sync'=>$sync]);
  }

  case 'get_transfer_detail': {
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) bad('INVALID_ID');

    $supplierTable = resolve_supplier_table($db);
    $supplierJoin = $supplierTable ? "LEFT JOIN {$supplierTable} vs ON vs.id = t.outlet_from AND t.consignment_category = 'PURCHASE_ORDER'" : '';
    $supplierNameExpr = $supplierTable ? "NULLIF(vs.name,'')" : 'NULL';

    $detailSql = "SELECT t.*,
        vf.name AS outlet_from_name, vt.name AS outlet_to_name,
        COALESCE(
          CASE WHEN t.consignment_category='PURCHASE_ORDER' THEN {$supplierNameExpr}
               ELSE NULLIF(vf.name,'') END,
          NULLIF(vf.store_code,''), NULLIF(vf.physical_city,''), t.outlet_from
        ) AS outlet_from_label,
        {$supplierNameExpr} AS supplier_from_name,
        COALESCE(NULLIF(vt.name,''), NULLIF(vt.store_code,''), NULLIF(vt.physical_city,''), t.outlet_to) AS outlet_to_label
      FROM transfers t
      LEFT JOIN vend_outlets vf ON vf.id = t.outlet_from
        AND t.consignment_category != 'PURCHASE_ORDER'
        AND vf.deleted_at = '0000-00-00 00:00:00'
      LEFT JOIN vend_outlets vt ON vt.id = t.outlet_to
        AND vt.deleted_at = '0000-00-00 00:00:00'
      {$supplierJoin}
      WHERE t.id=?";
    $t = $db->prepare($detailSql);
    $t->bind_param('i', $id);
    $t->execute();
    $transfer = $t->get_result()->fetch_assoc();
    $t->close();
    if (!$transfer) bad('NOT_FOUND', null, 404);

    $qq = $db->prepare("SELECT ti.id, ti.product_id, vp.sku, vp.name AS product_name,
               vp.supply_price,
               COALESCE(vp.price_including_tax, vp.price_excluding_tax, 0) AS retail_price,
               ti.qty_requested, ti.qty_sent_total, ti.qty_received_total, ti.confirmation_status
            FROM consignment_items ti
            LEFT JOIN vend_products vp ON vp.id = ti.product_id
            WHERE ti.consignment_id=? ORDER BY ti.id ASC LIMIT 1000");
    $qq->bind_param('i',$id);
    $qq->execute();
    $items = $qq->get_result()->fetch_all(MYSQLI_ASSOC);
    $qq->close();

    $ss = $db->prepare("SELECT DISTINCT ts.id, ts.status, ts.delivery_mode, ts.tracking_number, ts.packed_at, ts.received_at, ts.packed_by,
         COALESCE(NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), NULLIF(u.email,''), CAST(ts.packed_by AS CHAR)) AS packed_by_name
         FROM consignment_shipments ts
         LEFT JOIN users u ON u.id = ts.packed_by
         WHERE ts.consignment_id=?
         ORDER BY ts.id DESC LIMIT 200");
    $ss->bind_param('i', $id);
    $ss->execute();
    $ships = $ss->get_result()->fetch_all(MYSQLI_ASSOC);
    $ss->close();

    $nn = $db->prepare("SELECT DISTINCT tn.id, tn.note_text, tn.created_by, tn.created_at,
         COALESCE(NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), NULLIF(u.email,''), CAST(tn.created_by AS CHAR)) AS created_by_name
         FROM consignment_notes tn
         LEFT JOIN users u ON u.id = tn.created_by
         WHERE tn.consignment_id=? AND tn.deleted_at IS NULL
         ORDER BY tn.id DESC LIMIT 100");
    $nn->bind_param('i',$id);
    $nn->execute();
    $notes = $nn->get_result()->fetch_all(MYSQLI_ASSOC);
    $nn->close();

    $remote = null; $totals = null;
    if ($sync && !empty($transfer['vend_transfer_id'])) {
      $r  = ls_get_consignment($transfer['vend_transfer_id']); $remote = $r['ok'] ? $r['body'] : ['error'=>$r['body']];
      $tr = ls_totals($transfer['vend_transfer_id']);          $totals = $tr['ok']? $tr['body'] : null;
    }

    // Build outlet objects for modal display
    $sourceOutlet = [
      'id' => $transfer['outlet_from'],
      'name' => $transfer['outlet_from_label'] ?: $transfer['outlet_from']
    ];
    $destOutlet = [
      'id' => $transfer['outlet_to'],
      'name' => $transfer['outlet_to_label'] ?: $transfer['outlet_to']
    ];

    ok([
      'transfer' => $transfer,
      'items' => $items,
      'shipments' => $ships,
      'notes' => $notes,
      'ls' => $remote,
      'totals' => $totals,
      'sync' => $sync,
      'source_outlet' => $sourceOutlet,
      'dest_outlet' => $destOutlet
    ]);
  }

  case 'search_products':
  case 'product_search': {
    $q = s($in['q'] ?? '');
    $limit = clamp_int($in['limit'] ?? 20, 5, 100);
    if (!$q || strlen($q) < 2) ok(['results'=>[]]);

    $sql = "SELECT id, name, sku FROM vend_products
            WHERE (name LIKE CONCAT('%',?,'%') OR sku LIKE CONCAT('%',?,'%'))
            ORDER BY name ASC LIMIT ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssi', $q, $q, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    ok(['results'=>$rows]);
  }

  case 'create_transfer': {
    $cat  = strtoupper(trim((string)($in['consignment_category'] ?? '')));
    $from = s($in['outlet_from'] ?? null);
    $to   = s($in['outlet_to'] ?? null);
    if (!$cat || !$from || !$to) bad('REQUIRED_FIELDS_MISSING');
    $public = 'TR-' . bin2hex(random_bytes(6));

    $stmt = $db->prepare("INSERT INTO transfers
       (public_id, consignment_category, creation_method, outlet_from, outlet_to, created_by, state, created_at, updated_at)
       VALUES (?, ?, 'MANUAL', ?, ?, 0, 'OPEN', NOW(), NOW())");
    $stmt->bind_param('ssss', $public, $cat, $from, $to);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    $vendId = null; $vendNum = null;
    if ($sync) {
      // ✅ FIXED: Use 'STOCK' for outlet transfers, 'SUPPLIER' for purchase orders
      $type = ($cat === 'PURCHASE_ORDER') ? 'SUPPLIER' : 'STOCK';
      $payload = [
        'name'             => "Transfer $public",
        'type'             => $type,
        'status'           => 'OPEN',
        'outlet_id'        => $to,
        'source_outlet_id' => $from
      ];
      $resp = ls_create_consignment($payload);
      if ($resp['ok'] && is_array($resp['body'])) {
        // Lightspeed returns data nested in body.data.id
        $vendId = $resp['body']['data']['id'] ?? $resp['body']['id'] ?? null;
        $vendNum= $resp['body']['data']['reference'] ?? $resp['body']['reference'] ?? null;
      }
    }
    if ($vendId) {
      $upd = $db->prepare("UPDATE transfers SET vend_transfer_id=?, vend_number=?, updated_at=NOW() WHERE id=?");
      $upd->bind_param('ssi',$vendId,$vendNum,$id);
      $upd->execute();
      $upd->close();
    }

    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'CREATE',
      'event_data'=>['category'=>$cat,'from'=>$from,'to'=>$to,'ls_id'=>$vendId],
      'consignment_pk'=>$id, 'audit_after'=>['state'=>'OPEN','vend_transfer_id'=>$vendId]
    ]);
    ok(['id'=>$id, 'public_id'=>$public, 'vend_transfer_id'=>$vendId, 'vend_number'=>$vendNum, 'sync'=>$sync]);
  }

  case 'store_vend_numbers': {
    $id = (int)($in['id'] ?? 0);
    if ($id<=0) bad('INVALID_ID');
    $vend_number = s($in['vend_number'] ?? null);
    $vend_uuid   = s($in['vend_transfer_id'] ?? null);
    $stmt = $db->prepare("UPDATE transfers SET vend_number=?, vend_transfer_id=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param('ssi',$vend_number,$vend_uuid,$id);
    $stmt->execute();
    $stmt->close();

    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'STORE_VEND_NUMBERS',
      'event_data'=>['vend_number'=>$vend_number,'vend_transfer_id'=>$vend_uuid],
      'consignment_pk'=>$id
    ]);
    ok(['id'=>$id]);
  }

  case 'create_consignment': {
    /**
     * ALWAYS include all products during consignment creation.
     * Steps:
     *  1) Fetch transfer + outlets; create LS consignment if missing.
     *  2) Build aggregated lines from consignment_items (qty_requested > 0).
     *  3) Add/update all products on the consignment.
     *  4) If requested status=SENT, set consignment to SENT.
     * Returns summary + consignment URL.
     */
    $id = (int)($in['id'] ?? 0);
    if ($id<=0) bad('INVALID_ID');

    // Optional status control
    $statusReq = strtoupper((string)($in['status'] ?? 'OPEN'));
    if (!in_array($statusReq, ['OPEN','SENT'], true)) $statusReq = 'OPEN';

    // Optional outlet overrides (rare)
    $sourceOverride = s($in['source_outlet_id'] ?? null);
    $destOverride   = s($in['destination_outlet_id'] ?? null);

    // Transfer
    $t = $db->prepare("SELECT * FROM transfers WHERE id=?");
    $t->bind_param('i',$id); $t->execute();
    $tr = $t->get_result()->fetch_assoc();
    $t->close();
    if (!$tr) bad('NOT_FOUND','transfer',404);

    // Determine endpoints and outlets
    // ✅ FIXED: Use 'STOCK' for outlet transfers, 'SUPPLIER' for purchase orders
    $type = ($tr['consignment_category']==='PURCHASE_ORDER') ? 'SUPPLIER' : 'STOCK';
    $source_outlet_id = $sourceOverride ?: (string)$tr['outlet_from'];
    $destination_outlet_id = $destOverride ?: (string)$tr['outlet_to'];
    if (!$source_outlet_id || !$destination_outlet_id) bad('MISSING_OUTLETS');

    // Build consignment creation payload
    $creationPayload = [
      'name'             => "Transfer ".$tr['public_id'],
      'type'             => $type,
      'status'           => 'OPEN',  // create OPEN, then optionally set to SENT after lines are in
      'outlet_id'        => $destination_outlet_id,
      'source_outlet_id' => $source_outlet_id
    ];

    // Sync guard
    if (!$sync) {
      // No external calls; still move local state to PACKING to show intent
      $stmt = $db->prepare("UPDATE transfers SET state='PACKING', updated_at=NOW() WHERE id=?");
      $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();

      log_transfer_event($db, [
        'consignment_id'=>$id, 'event_type'=>'CREATE_CONSIGNMENT_LOCAL',
        'event_data'=>['forced_include_all'=>true,'target_status'=>$statusReq,'sync'=>false],
        'consignment_pk'=>$id, 'audit_after'=>['state'=>'PACKING']
      ]);
      ok(['vend_transfer_id'=>null,'vend_number'=>null,'included_products'=>0,'sync'=>false]);
    }

    // Ensure consignment exists
    $vend_id = $tr['vend_transfer_id'] ?? null;
    $vend_number = $tr['vend_number'] ?? null;

    if (!$vend_id) {
      // Check sync is enabled before creating consignment
      if (!$sync) {
        bad('SYNC_DISABLED', [
          'message' => 'Lightspeed sync is disabled - cannot create consignment',
          'flag_file' => get_sync_flag_file(),
          'flag_exists' => file_exists(get_sync_flag_file()),
          'flag_content' => file_exists(get_sync_flag_file()) ? file_get_contents(get_sync_flag_file()) : null,
          'action' => 'Enable sync via toggle_sync or set .sync_enabled to "1"'
        ]);
      }

      $resp = ls_create_consignment($creationPayload);
      if (!$resp['ok'] || !is_array($resp['body'])) {
        bad('LS_CREATE_FAILED', [
          'message' => $resp['body']['message'] ?? $resp['error'] ?? 'Unknown LS error',
          'status' => $resp['status'] ?? null,
          'response' => $resp,
          'payload' => $creationPayload
        ], 502);
      }
      // Lightspeed returns data nested in body.data.id
      $vend_id = $resp['body']['data']['id'] ?? $resp['body']['id'] ?? null;
      $vend_number = $resp['body']['data']['reference'] ?? $resp['body']['reference'] ?? $vend_number;

      // Verify we got a valid ID back from Lightspeed
      if (!$vend_id) {
        bad('LS_CREATE_FAILED', [
          'message' => 'Lightspeed did not return a consignment ID',
          'response_body' => $resp['body'] ?? null,
          'full_response' => $resp,
          'payload' => $creationPayload
        ], 502);
      }

      $u = $db->prepare("UPDATE transfers SET vend_transfer_id=?, vend_number=?, state='PACKING', updated_at=NOW() WHERE id=?");
      $u->bind_param('ssi',$vend_id,$vend_number,$id); $u->execute(); $u->close();

      log_transfer_event($db, [
        'consignment_id'=>$id, 'event_type'=>'CREATE_CONSIGNMENT',
        'event_data'=>['vend_transfer_id'=>$vend_id,'vend_number'=>$vend_number],
        'consignment_pk'=>$id, 'audit_after'=>['state'=>'PACKING','vend_transfer_id'=>$vend_id]
      ]);
    }

    // ALWAYS include all products: aggregate qty_requested > 0
    $qq = $db->prepare("SELECT product_id, qty_requested FROM consignment_items WHERE consignment_id=? AND qty_requested>0 ORDER BY id");
    $qq->bind_param('i',$id);
    $qq->execute();
    $items = $qq->get_result()->fetch_all(MYSQLI_ASSOC);
    $qq->close();

    // Aggregate by product_id
    $agg = [];
    foreach ($items as $it) {
      $pid = (string)$it['product_id'];
      $qty = (int)$it['qty_requested'];
      if ($qty <= 0 || $pid === '') continue;
      $agg[$pid] = ($agg[$pid] ?? 0) + $qty;
    }

    // Read existing lines to decide add vs update
    $summary = ['added'=>0,'updated'=>0,'skipped'=>0,'errors'=>[]];
    $existing = [];

    // Safety check: vend_id must exist at this point
    if (!$vend_id || $vend_id === '') {
      bad('INVALID_STATE', 'No Lightspeed consignment ID available', 500);
    }

    $lsProducts = ls_list_products($vend_id);
    if ($lsProducts['ok']) {
      foreach (($lsProducts['list'] ?? []) as $lp) {
        $existing[(string)$lp['product_id']] = (int)($lp['count'] ?? 0);
      }
    }

    // Push all lines
    foreach ($agg as $pid => $qty) {
      if (isset($existing[$pid])) {
        if ($existing[$pid] !== $qty) {
          $updateFields = ['count' => $qty];
          if ($supplyPrice !== null) {
            $updateFields['cost'] = $supplyPrice;
          }
          $r = ls_update_product($vend_id, $pid, $updateFields);
          $r['ok'] ? $summary['updated']++ : $summary['errors'][] = [
            'product_id'=>$pid,'action'=>'update','status'=>$r['status'],'message'=>$r['body']['message'] ?? 'Update failed'
          ];
        } else {
          $summary['skipped']++;
        }
      } else {
        // ✅ Don't send cost - let Lightspeed use its own product pricing
        $r = ls_add_product($vend_id, $pid, $qty);
        $r['ok'] ? $summary['added']++ : $summary['errors'][] = [
          'product_id'=>$pid,'action'=>'add','status'=>$r['status'],'message'=>$r['body']['message'] ?? 'Add failed'
        ];
      }
    }

    // Optionally set SENT now that lines exist
    if ($statusReq === 'SENT') {
      $st = ls_update_consignment_status($vend_id, 'SENT');
      if (!$st['ok']) {
        $summary['errors'][] = ['action'=>'status','to'=>'SENT','status'=>$st['status'],'message'=>$st['body']['message'] ?? 'Status change failed'];
      } else {
        $uu = $db->prepare("UPDATE transfers SET state='SENT', updated_at=NOW() WHERE id=?");
        $uu->bind_param('i',$id); $uu->execute(); $uu->close();
      }
    }

    ok([
      'vend_transfer_id' => $vend_id,
      'vend_number'      => $vend_number,
      'included_products'=> count($agg),
      'summary'          => $summary,
      'consignment_url'  => $vend_id ? ls_consignment_url($vend_id) : null,
      'sync'             => true
    ]);
  }

  case 'add_transfer_item': {
    $tid = (int)($in['id'] ?? 0);
    $pid = s($in['product_id'] ?? null);
    $qty = clamp_int($in['qty'] ?? 0, 1, 100000);
    if ($tid<=0) bad('INVALID_TRANSFER_ID');
    if (!$pid)  bad('INVALID_PRODUCT_ID');
    if ($qty<=0)bad('INVALID_QUANTITY');

    $tx = $db->prepare("SELECT id, state FROM transfers WHERE id=?");
    $tx->bind_param('i',$tid); $tx->execute();
    $tr = $tx->get_result()->fetch_assoc();
    $tx->close();
    if (!$tr) bad('NOT_FOUND', 'transfer');
    if (in_array($tr['state'], ['CANCELLED','RECEIVED','CLOSED'])) bad('TRANSFER_CLOSED');

    $pc = $db->prepare("SELECT id,name,sku FROM vend_products WHERE id=? LIMIT 1");
    $pc->bind_param('s',$pid); $pc->execute();
    $product = $pc->get_result()->fetch_assoc(); $pc->close();
    if (!$product) bad('PRODUCT_NOT_FOUND', $pid);

    $sql = "INSERT INTO consignment_items
            (consignment_id, product_id, qty_requested, qty_sent_total, qty_received_total, confirmation_status, created_at)
            VALUES (?, ?, ?, 0, 0, 'pending', NOW())
            ON DUPLICATE KEY UPDATE qty_requested = qty_requested + VALUES(qty_requested)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('isi',$tid,$pid,$qty);
    $stmt->execute();
    $stmt->close();

    log_transfer_event($db, [
      'consignment_id'=>$tid, 'event_type'=>'ADD_ITEM',
      'event_data'=>['product_id'=>$pid,'qty'=>$qty,'product_name'=>$product['name']],
      'consignment_pk'=>$tid
    ]);
    ok(['added'=>true, 'product'=>$product, 'quantity'=>$qty]);
  }

  case 'update_transfer_item': {
    $tid = (int)($in['id'] ?? 0);
    $itemId = (int)($in['item_id'] ?? 0);
    $qty = clamp_int($in['qty_requested'] ?? 0, 0, 100000);
    if ($tid<=0 || $itemId<=0) bad('INVALID_INPUT');

    $q = $db->prepare("SELECT product_id FROM consignment_items WHERE id=? AND consignment_id=?");
    $q->bind_param('ii',$itemId,$tid); $q->execute();
    $pr = $q->get_result()->fetch_assoc(); $q->close();
    if (!$pr) bad('NOT_FOUND',null,404);
    $pid = $pr['product_id'];

    if ($qty === 0) {
      $stmt = $db->prepare("DELETE FROM consignment_items WHERE id=? AND consignment_id=?");
      $stmt->bind_param('ii',$itemId,$tid); $stmt->execute(); $stmt->close();
      log_transfer_event($db, [
        'consignment_id'=>$tid, 'item_id'=>$itemId, 'event_type'=>'REMOVE_ITEM',
        'event_data'=>['product_id'=>$pid], 'consignment_pk'=>$tid
      ]);
      ok(['removed'=>true]);
    } else {
      $stmt = $db->prepare("UPDATE consignment_items SET qty_requested=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND consignment_id=?");
      $stmt->bind_param('iii',$qty,$itemId,$tid);
      $stmt->execute(); $stmt->close();
      log_transfer_event($db, [
        'consignment_id'=>$tid, 'item_id'=>$itemId, 'event_type'=>'UPDATE_ITEM',
        'event_data'=>['product_id'=>$pid,'qty_requested'=>$qty], 'consignment_pk'=>$tid
      ]);
      ok(['updated'=>true]);
    }
  }

  case 'remove_transfer_item': {
    $itemId = (int)($in['item_id'] ?? 0);
    if ($itemId <= 0) bad('INVALID_ITEM_ID');

    $g = $db->prepare("SELECT consignment_id, product_id FROM consignment_items WHERE id=?");
    $g->bind_param('i',$itemId); $g->execute();
    $row = $g->get_result()->fetch_assoc(); $g->close();
    if (!$row) bad('NOT_FOUND', 'item');

    $tid = (int)$row['consignment_id'];
    $pid = (string)$row['product_id'];

    $d = $db->prepare("DELETE FROM consignment_items WHERE id=?");
    $d->bind_param('i',$itemId); $d->execute(); $d->close();

    log_transfer_event($db, [
      'consignment_id'=>$tid, 'item_id'=>$itemId, 'event_type'=>'REMOVE_ITEM',
      'event_data'=>['product_id'=>$pid], 'consignment_pk'=>$tid
    ]);
    ok(['removed'=>true]);
  }

  case 'update_transfer_item_qty': {
    $tid = (int)($in['id'] ?? 0);
    $itemId = (int)($in['item_id'] ?? 0);
    $field = (string)($in['field'] ?? '');
    $value = clamp_int($in['value'] ?? 0, 0, 100000);
    if ($tid<=0 || $itemId<=0) bad('INVALID_INPUT');
    if (!in_array($field, ['req','sent','rec'], true)) bad('INVALID_FIELD');

    $g = $db->prepare("SELECT product_id, qty_requested, qty_sent_total, qty_received_total FROM consignment_items WHERE id=? AND consignment_id=?");
    $g->bind_param('ii',$itemId,$tid); $g->execute();
    $row = $g->get_result()->fetch_assoc(); $g->close();
    if (!$row) bad('NOT_FOUND', 'item');
    $pid = $row['product_id'];

    $nReq = (int)$row['qty_requested'];
    $nSent = (int)$row['qty_sent_total'];
    $nRec = (int)$row['qty_received_total'];
    if ($field === 'req')  $nReq  = $value;
    if ($field === 'sent') $nSent = $value;
    if ($field === 'rec')  $nRec  = $value;

    if ($nRec > max($nSent, $nReq)) $nRec = max(min($nSent, $nReq), 0);
    if ($field === 'rec' && $nRec != $value) bad('CAP_EXCEEDED','Received cannot exceed Sent/Requested');

    if     ($field === 'req')  { $sql = "UPDATE consignment_items SET qty_requested=?      , updated_at=NOW() WHERE id=? AND consignment_id=?"; }
    elseif ($field === 'sent') { $sql = "UPDATE consignment_items SET qty_sent_total=?     , updated_at=NOW() WHERE id=? AND consignment_id=?"; }
    else                       { $sql = "UPDATE consignment_items SET qty_received_total=? , updated_at=NOW() WHERE id=? AND consignment_id=?"; }
    $u = $db->prepare($sql);
    $u->bind_param('iii',$value,$itemId,$tid);
    $u->execute(); $u->close();

    log_transfer_event($db, [
      'consignment_id'=>$tid, 'item_id'=>$itemId, 'event_type'=>'UPDATE_ITEM_QTY',
      'event_data'=>['field'=>$field,'value'=>$value], 'consignment_pk'=>$tid
    ]);
    ok(['updated'=>true]);
  }

  case 'push_consignment_lines': {
    // Manual resync helper (kept as-is)
    $id = (int)($in['id'] ?? 0);
    if ($id<=0) bad('INVALID_ID');

    $t = $db->prepare("SELECT * FROM transfers WHERE id=?");
    $t->bind_param('i',$id); $t->execute();
    $tr = $t->get_result()->fetch_assoc(); $t->close();
    if (!$tr) bad('NOT_FOUND','transfer',404);

    if (!$sync) bad('SYNC_DISABLED', [
      'message' => 'Lightspeed sync is disabled',
      'flag_file' => get_sync_flag_file(),
      'flag_exists' => file_exists(get_sync_flag_file()),
      'flag_content' => file_exists(get_sync_flag_file()) ? file_get_contents(get_sync_flag_file()) : null,
      'action' => 'Use toggle_sync action to enable or set .sync_enabled file to "1"'
    ]);

    $vend_id = $tr['vend_transfer_id'];
    if (!$vend_id) {
      // ✅ FIXED: Use 'STOCK' for outlet transfers, 'SUPPLIER' for purchase orders
      $type = ($tr['consignment_category']==='PURCHASE_ORDER') ? 'SUPPLIER' : 'STOCK';
      $resp = ls_create_consignment([
        'name'=>"Transfer ".$tr['public_id'],'type'=>$type,'status'=>'OPEN',
        'outlet_id'=>$tr['outlet_to'],'source_outlet_id'=>$tr['outlet_from']
      ]);
      if (!$resp['ok']) bad('LS_CREATE_FAILED', $resp['body']['message'] ?? 'Unknown');
      // Lightspeed returns data nested in body.data.id
      $vend_id = $resp['body']['data']['id'] ?? $resp['body']['id'] ?? null;
      if (!$vend_id) bad('LS_CREATE_FAILED', 'No consignment ID returned', 502);
      $vend_number = $resp['body']['data']['reference'] ?? $resp['body']['reference'] ?? $tr['vend_number'];
      $u = $db->prepare("UPDATE transfers SET vend_transfer_id=?, vend_number=?, updated_at=NOW() WHERE id=?");
      $u->bind_param('ssi',$vend_id,$vend_number,$id); $u->execute(); $u->close();
    }

    $qq = $db->prepare("SELECT ti.product_id, ti.qty_requested, vp.supply_price
            FROM consignment_items ti
            LEFT JOIN vend_products vp ON vp.id = ti.product_id
            WHERE ti.consignment_id=? AND ti.qty_requested>0 ORDER BY ti.id");
    $qq->bind_param('i',$id); $qq->execute();
    $items = $qq->get_result()->fetch_all(MYSQLI_ASSOC);
    $qq->close();

    $pushed=0; $updated=0; $skipped=0; $errors=[];
    $existing = [];
    if (!$vend_id) bad('INVALID_STATE', 'No Lightspeed consignment ID available', 500);
    $lsProducts = ls_list_products($vend_id);
    if ($lsProducts['ok']) foreach (($lsProducts['list'] ?? []) as $lp) $existing[$lp['product_id']] = (int)($lp['count'] ?? 0);

    foreach ($items as $line) {
      $pid = (string)$line['product_id'];
      $qty = (int)$line['qty_requested'];
      $cost = isset($line['supply_price']) ? (float)$line['supply_price'] : null;

      // Log cost information only in debug mode
      if ($GLOBALS['logger'] ?? false) {
        $GLOBALS['logger']->logProductOp('PUSH_COST', $pid, ['supply_price' => $line['supply_price'], 'cost' => $cost, 'qty' => $qty]);
      } elseif (getenv('APP_DEBUG') === 'true') {
        error_log("[CONSIGNMENT PUSH] Product $pid: supply_price={$line['supply_price']}, cost=$cost, qty=$qty");
      }

      if ($qty <= 0) { $skipped++; continue; }
      if (isset($existing[$pid])) {
        if ($existing[$pid] !== $qty) {
          $updateFields = ['count'=>$qty];
          if ($cost !== null) $updateFields['cost'] = $cost;
          if (getenv('APP_DEBUG') === 'true') {
            error_log("[CONSIGNMENT PUSH] Updating product $pid with fields: " . json_encode($updateFields));
          }
          $r = ls_update_product($vend_id, $pid, $updateFields);
          $r['ok'] ? $updated++ : $errors[] = ['product_id'=>$pid,'action'=>'update','status'=>$r['status'],'message'=>$r['body']['message'] ?? 'Update failed'];
        } else $skipped++;
      } else {
        if (getenv('APP_DEBUG') === 'true') {
          error_log("[CONSIGNMENT PUSH] Adding product $pid with cost=$cost, qty=$qty");
        }
        $r = ls_add_product($vend_id, $pid, $qty, $cost);
        $r['ok'] ? $pushed++ : $errors[] = ['product_id'=>$pid,'action'=>'add','status'=>$r['status'],'message'=>$r['body']['message'] ?? 'Add failed'];
      }
    }

    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'PUSH_LINES',
      'event_data'=>['lines'=>count($items),'pushed'=>$pushed,'updated'=>$updated,'skipped'=>$skipped,'errors'=>$errors],
      'consignment_pk'=>$id
    ]);

    ok(['pushed'=>$pushed,'updated'=>$updated,'skipped'=>$skipped,'errors'=>$errors,'vend_transfer_id'=>$vend_id,'consignment_url'=>ls_consignment_url($vend_id)]);
  }

  case 'add_products_to_consignment': {
    $id = (int)($in['id'] ?? 0);
    $pids = $in['product_ids'] ?? [];
    $qtys = $in['quantities'] ?? [];
    if ($id<=0) bad('INVALID_ID');
    if (!$pids || !is_array($pids)) bad('NO_PRODUCTS');

    $t = $db->prepare("SELECT * FROM transfers WHERE id=?");
    $t->bind_param('i',$id); $t->execute();
    $tr = $t->get_result()->fetch_assoc(); $t->close();
    if (!$tr) bad('NOT_FOUND','transfer',404);
    if (!$sync) bad('SYNC_DISABLED', [
      'message' => 'Lightspeed sync is disabled',
      'flag_file' => get_sync_flag_file(),
      'flag_exists' => file_exists(get_sync_flag_file()),
      'flag_content' => file_exists(get_sync_flag_file()) ? file_get_contents(get_sync_flag_file()) : null,
      'action' => 'Use toggle_sync action to enable or set .sync_enabled file to "1"'
    ]);

    $vend_id = $tr['vend_transfer_id'];
    if (!$vend_id) {
      // ✅ FIXED: Use 'STOCK' for outlet transfers, 'SUPPLIER' for purchase orders
      $type = ($tr['consignment_category']==='PURCHASE_ORDER') ? 'SUPPLIER' : 'STOCK';
      $resp = ls_create_consignment([
        'name'=>"Transfer ".$tr['public_id'],'type'=>$type,'status'=>'OPEN',
        'outlet_id'=>$tr['outlet_to'],'source_outlet_id'=>$tr['outlet_from']
      ]);
      if ($resp['ok']) {
        // Lightspeed returns data nested in body.data.id
        $vend_id = $resp['body']['data']['id'] ?? $resp['body']['id'] ?? null;
        if (!$vend_id) bad('LS_CREATE_FAILED', 'No consignment ID returned', 502);
        $vend_number = $resp['body']['data']['reference'] ?? $resp['body']['reference'] ?? $tr['vend_number'];
        $u = $db->prepare("UPDATE transfers SET vend_transfer_id=?, vend_number=?, updated_at=NOW() WHERE id=?");
        $u->bind_param('ssi',$vend_id,$vend_number,$id); $u->execute(); $u->close();
      } else bad('LS_CREATE_FAILED', $resp['body']['message'] ?? 'Unknown');
    }

    if (!$vend_id) bad('INVALID_STATE', 'No Lightspeed consignment ID available', 500);

    $existing = [];
    $lsProducts = ls_list_products($vend_id);
    if ($lsProducts['ok']) foreach (($lsProducts['list'] ?? []) as $lp) $existing[$lp['product_id']] = true;

    // Fetch supply prices for products
    $priceMap = [];
    if (!empty($pids)) {
      $placeholders = implode(',', array_fill(0, count($pids), '?'));
      $priceQuery = $db->prepare("SELECT id, supply_price FROM vend_products WHERE id IN ($placeholders)");
      $priceQuery->bind_param(str_repeat('s', count($pids)), ...$pids);
      $priceQuery->execute();
      $priceResults = $priceQuery->get_result()->fetch_all(MYSQLI_ASSOC);
      $priceQuery->close();
      foreach ($priceResults as $pr) {
        $priceMap[$pr['id']] = isset($pr['supply_price']) ? (float)$pr['supply_price'] : null;
      }

      // Log price lookup results only in debug mode
      if (getenv('APP_DEBUG') === 'true') {
        error_log("[ADD_PRODUCTS] Fetched prices for " . count($priceMap) . " products: " . json_encode($priceMap));
      }
    }

    $added = 0; $updated = 0; $errors=[];
    foreach ($pids as $i => $pid) {
      $qty = (int)($qtys[$i] ?? 1);
      $cost = $priceMap[$pid] ?? null;

      // Log product info only in debug mode
      if (getenv('APP_DEBUG') === 'true') {
        error_log("[ADD_PRODUCTS] Product $pid: qty=$qty, cost=" . ($cost !== null ? $cost : 'NULL'));
      }

      if ($qty <= 0) $qty = 1;
      if (isset($existing[$pid])) {
        $updateFields = ['count'=>$qty];
        if ($cost !== null) $updateFields['cost'] = $cost;
        $r = ls_update_product($vend_id, $pid, $updateFields);
        $r['ok'] ? $updated++ : $errors[] = ['product_id'=>$pid,'action'=>'update','status'=>$r['status'],'message'=>$r['body']['message'] ?? 'Update failed'];
      } else {
        $r = ls_add_product($vend_id, $pid, $qty, $cost);
        $r['ok'] ? $added++ : $errors[] = ['product_id'=>$pid,'action'=>'add','status'=>$r['status'],'message'=>$r['body']['message'] ?? 'Add failed'];
      }
    }
    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'ADD_PRODUCTS_TO_CONSIGNMENT',
      'event_data'=>['added'=>$added,'updated'=>$updated,'errors'=>$errors],'consignment_pk'=>$id
    ]);
    ok(['added'=>$added,'updated'=>$updated,'errors'=>$errors,'sync'=>$sync,'vend_transfer_id'=>$vend_id,'consignment_url'=>ls_consignment_url($vend_id)]);
  }

  case 'mark_sent': {
    $id = (int)($in['id'] ?? 0);
    $boxes = clamp_int($in['total_boxes'] ?? 1, 0, 1000);
    if ($id<=0) bad('INVALID_ID');

    $stmt = $db->prepare("INSERT INTO consignment_shipments (consignment_id, status, delivery_mode, packed_at, created_at) VALUES (?, 'packed', 'auto', NOW(), NOW())");
    $stmt->bind_param('i',$id); $stmt->execute(); $shipId = (int)$stmt->insert_id; $stmt->close();

    $stmt2 = $db->prepare("UPDATE transfers SET total_boxes=?, state='SENT', updated_at=NOW() WHERE id=?");
    $stmt2->bind_param('ii',$boxes,$id); $stmt2->execute(); $stmt2->close();

    $lsResult = null;
    if ($sync) {
      $t = $db->prepare("SELECT vend_transfer_id FROM transfers WHERE id=?");
      $t->bind_param('i',$id); $t->execute();
      $row = $t->get_result()->fetch_assoc(); $t->close();
      if (!empty($row['vend_transfer_id'])) $lsResult = ls_update_consignment_status($row['vend_transfer_id'], 'SENT');
    }

    log_transfer_event($db, [
      'consignment_id'=>$id, 'shipment_id'=>$shipId, 'event_type'=>'MARK_SENT',
      'event_data'=>['boxes'=>$boxes,'ls'=>$lsResult], 'consignment_pk'=>$id, 'audit_after'=>['state'=>'SENT','total_boxes'=>$boxes]
    ]);
    ok(['id'=>$id,'shipment_id'=>$shipId,'ls'=>$lsResult,'sync'=>$sync]);
  }

  case 'mark_receiving': {
    $id = (int)($in['id'] ?? 0);
    if ($id<=0) bad('INVALID_ID');

    $stmt = $db->prepare("UPDATE transfers SET state='RECEIVING', updated_at=NOW() WHERE id=?");
    $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();

    $ls = null; $totals = null;
    if ($sync) {
      $t = $db->prepare("SELECT vend_transfer_id FROM transfers WHERE id=?");
      $t->bind_param('i',$id); $t->execute();
      $row = $t->get_result()->fetch_assoc(); $t->close();
      if (!empty($row['vend_transfer_id'])) {
        $g = ls_get_consignment($row['vend_transfer_id']);
        if ($g['ok'] && is_array($g['body'])) {
          $ls = $g['body'];
          if (($ls['type'] ?? '') === 'SUPPLIER' && (($ls['status'] ?? 'OPEN') !== 'DISPATCHED')) {
            $upd = ls_update_consignment_status($row['vend_transfer_id'], 'DISPATCHED');
            $ls = $upd['ok'] ? ($upd['body'] ?? $ls) : $ls;
          }
          $to = ls_totals($row['vend_transfer_id']); $totals = $to['ok'] ? $to['body'] : null;
        }
      }
    }

    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'MARK_RECEIVING',
      'event_data'=>['ls'=>$ls,'totals'=>$totals], 'consignment_pk'=>$id, 'audit_after'=>['state'=>'RECEIVING']
    ]);
    ok(['id'=>$id,'ls'=>$ls,'totals'=>$totals,'sync'=>$sync]);
  }

  case 'receive_all': {
    $id = (int)($in['id'] ?? 0);
    if ($id<=0) bad('INVALID_ID');

    // 🆕 Support auto-fill mode: auto-set received quantities to sent quantities
    $autoFill = (bool)($in['auto_fill'] ?? true);

    // Update local consignment_items: set qty_received_total to qty_requested (sent quantity)
    $stmt = $db->prepare("UPDATE consignment_items SET qty_received_total = GREATEST(qty_received_total, qty_requested) WHERE consignment_id=? AND qty_received_total < qty_requested");
    $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();

    $stmt2 = $db->prepare("UPDATE transfers SET state='RECEIVED', updated_at=NOW() WHERE id=?");
    $stmt2->bind_param('i',$id); $stmt2->execute(); $stmt2->close();

    $rcpt = $db->prepare("INSERT INTO consignment_receipts (consignment_id, received_by, received_at, created_at) VALUES (?, 0, NOW(), NOW())");
    $rcpt->bind_param('i',$id); $rcpt->execute(); $receiptId = (int)$rcpt->insert_id; $rcpt->close();

    $lsSteps = [];
    $stockUpdates = [];
    if ($sync) {
      $t = $db->prepare("SELECT vend_transfer_id, outlet_to FROM transfers WHERE id=?");
      $t->bind_param('i',$id); $t->execute();
      $row = $t->get_result()->fetch_assoc(); $t->close();
      if (!empty($row['vend_transfer_id'])) {
        $lp = ls_list_products($row['vend_transfer_id']);
        $list = $lp['list'] ?? [];

        // 🆕 Auto-fill: Update each product's received quantity to match sent quantity
        foreach ($list as $p) {
          $count = (int)round((float)($p['count'] ?? 0));
          $pid   = (string)$p['product_id'];

          // Update Lightspeed consignment product with received = sent
          $r = ls_update_product($row['vend_transfer_id'], $pid, ['count'=>$count,'received'=>$count]);
          $lsSteps[] = ['product_id'=>$pid,'ok'=>$r['ok'],'status'=>$r['status'],'count'=>$count,'received'=>$count];

          // 🆕 Update destination outlet stock level (add received quantity to inventory)
          if ($r['ok'] && !empty($row['outlet_to'])) {
            $stockUpdate = ls_update_outlet_stock($row['outlet_to'], $pid, $count, 'ADD');
            $stockUpdates[] = [
              'outlet_id' => $row['outlet_to'],
              'product_id' => $pid,
              'quantity_added' => $count,
              'ok' => $stockUpdate['ok'] ?? false,
              'status' => $stockUpdate['status'] ?? 0
            ];
          }
        }

        // Mark consignment as RECEIVED in Lightspeed
        $final = ls_update_consignment_status($row['vend_transfer_id'], 'RECEIVED');
        $lsSteps[] = ['final'=>$final['status'] ?? 0, 'ok'=>$final['ok']];
      }
    }

    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'RECEIVE_ALL',
      'event_data'=>['receipt_id'=>$receiptId,'ls'=>$lsSteps,'stock_updates'=>$stockUpdates,'auto_fill'=>$autoFill],
      'consignment_pk'=>$id, 'audit_after'=>['state'=>'RECEIVED']
    ]);
    ok(['id'=>$id,'receipt_id'=>$receiptId,'ls'=>$lsSteps,'stock_updates'=>$stockUpdates,'sync'=>$sync]);
  }

  case 'cancel_transfer': {
    $id = (int)($in['id'] ?? 0);
    if ($id<=0) bad('INVALID_ID');

    $stmt = $db->prepare("UPDATE transfers SET state='CANCELLED', updated_at=NOW() WHERE id=?");
    $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();

    $lsRes = null;
    if ($sync) {
      $t = $db->prepare("SELECT vend_transfer_id FROM transfers WHERE id=?");
      $t->bind_param('i',$id); $t->execute();
      $row = $t->get_result()->fetch_assoc(); $t->close();
      if (!empty($row['vend_transfer_id'])) {
        $u = ls_update_consignment_status($row['vend_transfer_id'], 'CANCELLED');
        if (!$u['ok']) {
          $d = ls_delete_cons($row['vend_transfer_id']);
          $lsRes = ['update'=>$u['status'], 'delete'=>$d['status']];
        } else $lsRes = ['update'=>$u['status']];
      }
    }

    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'CANCEL',
      'event_data'=>['ls'=>$lsRes], 'consignment_pk'=>$id, 'audit_after'=>['state'=>'CANCELLED']
    ]);
    ok(['id'=>$id,'ls'=>$lsRes,'sync'=>$sync]);
  }

  case 'add_note': {
    $id = (int)($in['id'] ?? 0);
    $note = s($in['note_text'] ?? '');
    if ($id<=0 || !$note) bad('INVALID_INPUT');
    $stmt = $db->prepare("INSERT INTO consignment_notes (consignment_id, note_text, created_by, created_at) VALUES (?, ?, 0, NOW())");
    $stmt->bind_param('is',$id,$note); $stmt->execute(); $nid = (int)$stmt->insert_id; $stmt->close();

    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'ADD_NOTE', 'event_data'=>['note_id'=>$nid,'text'=>$note], 'consignment_pk'=>$id
    ]);
    ok(['id'=>$id,'note_id'=>$nid]);
  }

  case 'recreate_transfer': {
    // Recreates a cancelled/completed transfer with all its items and notes
    $id = (int)($in['id'] ?? 0);
    $revertStock = !!($in['revert_stock'] ?? false);
    if ($id <= 0) bad('INVALID_ID');

    // Start transaction for atomic operation
    $db->begin_transaction();

    try {
      // 1. Get original transfer details
      $stmt = $db->prepare("SELECT * FROM transfers WHERE id = ?");
      $stmt->bind_param('i', $id); $stmt->execute();
      $original = $stmt->get_result()->fetch_assoc(); $stmt->close();
      if (!$original) {
        throw new Exception('TRANSFER_NOT_FOUND');
      }

      // Allow recreation of cancelled, received, or closed transfers
      $allowedStates = ['CANCELLED', 'RECEIVED', 'CLOSED'];
      if (!in_array($original['state'], $allowedStates)) {
        throw new Exception('ONLY_COMPLETED_OR_CANCELLED_TRANSFERS_CAN_BE_RECREATED');
      }

      // 2. Create new transfer with same basic info
      // Generate new public_id
      $newPublicId = 'T-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
      $state = 'OPEN';
      $createdById = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
      $creationMethod = 'AUTOMATED'; // ENUM values: MANUAL, AUTOMATED (only 2 values in schema)
      $totalBoxes = (int)($original['total_boxes'] ?? 0);

      $stmt = $db->prepare("INSERT INTO transfers
        (public_id, consignment_category, creation_method, outlet_from, outlet_to, supplier_id, created_by, total_boxes, state, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OPEN', NOW(), NOW())");

      $stmt->bind_param('ssssssii',
        $newPublicId,
        $original['consignment_category'],
        $creationMethod,
        $original['outlet_from'],
        $original['outlet_to'],
        $original['supplier_id'],
        $createdById,
        $totalBoxes
      );
      $stmt->execute();
      $newId = (int)$stmt->insert_id;
      $stmt->close();

      if ($newId <= 0) {
        throw new Exception('FAILED_TO_CREATE_TRANSFER');
      }

      // 3. Copy transfer items with best available quantity data
      $itemsStmt = $db->prepare("SELECT * FROM consignment_items WHERE consignment_id = ?");
      $itemsStmt->bind_param('i', $id);
      $itemsStmt->execute();
      $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $itemsStmt->close();

      $insertItemStmt = $db->prepare("INSERT INTO consignment_items
        (consignment_id, product_id, qty_requested, qty_sent_total, qty_received_total, confirmation_status)
        VALUES (?, ?, ?, ?, ?, ?)");

      $copiedItems = 0;
      $seenProducts = []; // Track products to avoid duplicates

      foreach ($items as $item) {
        $productId = (int)$item['product_id'];

        // Skip if we've already processed this product (handles duplicate items in source transfer)
        if (isset($seenProducts[$productId])) {
          // Merge quantities with existing entry
          $seenProducts[$productId]['qty_requested'] += (int)$item['qty_requested'];
          $seenProducts[$productId]['qty_sent'] += (int)$item['qty_sent_total'];
          $seenProducts[$productId]['qty_received'] += (int)$item['qty_received_total'];
          continue;
        }

        // Use best available quantity data:
        // Priority: qty_received_total > qty_sent_total > qty_requested
        $qtyRequested = (int)$item['qty_requested'];
        $qtySent = (int)$item['qty_sent_total'];
        $qtyReceived = (int)$item['qty_received_total'];

        // If we have received data, use that for all fields
        if ($qtyReceived > 0) {
          $newQtyReq = $qtyReceived;
          $newQtySent = $qtyReceived;
          $newQtyRec = 0; // Ready to be received again
        }
        // Otherwise if we have sent data, use that
        elseif ($qtySent > 0) {
          $newQtyReq = $qtySent;
          $newQtySent = $qtySent;
          $newQtyRec = 0; // Ready to be received
        }
        // Otherwise use requested quantity
        else {
          $newQtyReq = $qtyRequested;
          $newQtySent = 0;
          $newQtyRec = 0;
        }

        $confirmStatus = 'pending'; // ENUM: pending, confirmed, discrepancy

        $insertItemStmt->bind_param('iiiiis',
          $newId,
          $productId,
          $newQtyReq,
          $newQtySent,
          $newQtyRec,
          $confirmStatus
        );
        $insertItemStmt->execute();

        // Track this product
        $seenProducts[$productId] = [
          'qty_requested' => $newQtyReq,
          'qty_sent' => $newQtySent,
          'qty_received' => $newQtyRec
        ];

        $copiedItems++;
      }
      $insertItemStmt->close();

      // 4. Copy notes with indication they're from the original transfer
      $notesStmt = $db->prepare("SELECT * FROM consignment_notes WHERE consignment_id = ? ORDER BY created_at ASC");
      $notesStmt->bind_param('i', $id);
      $notesStmt->execute();
      $notes = $notesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $notesStmt->close();

      $insertNoteStmt = $db->prepare("INSERT INTO consignment_notes
        (consignment_id, note_text, created_by, created_at)
        VALUES (?, ?, ?, NOW())");

      $copiedNotes = 0;
      foreach ($notes as $note) {
        $noteText = "[COPIED FROM {$original['public_id']}] " . $note['note_text'];
        $insertNoteStmt->bind_param('isi', $newId, $noteText, $createdById);
        $insertNoteStmt->execute();
        $copiedNotes++;
      }
      $insertNoteStmt->close();

      // 4.5. Add automatic note about recreation with user's name
      $userName = 'Unknown User';
      if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        $userStmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $userStmt->bind_param('i', $_SESSION['user_id']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userRow = $userResult->fetch_assoc()) {
          $firstName = trim($userRow['first_name'] ?? '');
          $lastName = trim($userRow['last_name'] ?? '');
          $userName = trim($firstName . ' ' . $lastName);
          if (empty($userName)) {
            $userName = 'User #' . $_SESSION['user_id'];
          }
        }
        $userStmt->close();
      }

      $recreationNote = "Transfer manually recreated from {$original['public_id']} by {$userName}";
      $autoNoteStmt = $db->prepare("INSERT INTO consignment_notes (consignment_id, note_text, created_by, created_at) VALUES (?, ?, ?, NOW())");
      $autoNoteStmt->bind_param('isi', $newId, $recreationNote, $createdById);
      $autoNoteStmt->execute();
      $autoNoteStmt->close();

      // 5. Revert stock if requested (adds inventory back to source outlet)
      $stockAdjustments = [];
      if ($revertStock && $sync) {
        foreach ($items as $item) {
          $productId = $item['product_id'];
          $qtySent = (int)($item['qty_sent_total'] ?? 0);

          if ($qtySent > 0) {
            // Add inventory back to source outlet via Lightspeed API
            $adjustResult = ls_update_outlet_stock(
              $original['outlet_from'],
              $productId,
              $qtySent,
              'ADD'
            );

            $stockAdjustments[] = [
              'product_id' => $productId,
              'outlet_id' => $original['outlet_from'],
              'quantity_restored' => $qtySent,
              'success' => $adjustResult['ok'] ?? false
            ];
          }
        }
      }

      // 6. Log the recreation event
      log_transfer_event($db, [
        'consignment_id' => $newId,
        'event_type' => 'RECREATED_FROM_' . $original['state'],
        'event_data' => [
          'original_transfer_id' => $id,
          'original_public_id' => $original['public_id'],
          'original_state' => $original['state'],
          'items_copied' => $copiedItems,
          'notes_copied' => $copiedNotes,
          'stock_reverted' => $revertStock,
          'stock_adjustments' => $stockAdjustments
        ],
        'consignment_pk' => $newId
      ]);

      // Commit transaction - all operations succeeded
      $db->commit();

      ok([
        'new_id' => $newId,
        'new_public_id' => $newPublicId,
        'original_id' => $id,
        'items_copied' => $copiedItems,
        'notes_copied' => $copiedNotes,
        'stock_reverted' => $revertStock,
        'stock_adjustments_count' => count($stockAdjustments),
        'message' => "Transfer recreated successfully from {$original['public_id']}"
      ]);

    } catch (Exception $e) {
      // Rollback transaction on any error
      $db->rollback();

      // Clean up any partially created transfer (in case rollback fails)
      if (isset($newId) && $newId > 0) {
        try {
          $cleanupStmt = $db->prepare("DELETE FROM consignment_items WHERE consignment_id = ?");
          $cleanupStmt->bind_param('i', $newId);
          $cleanupStmt->execute();
          $cleanupStmt->close();

          $cleanupStmt = $db->prepare("DELETE FROM consignment_notes WHERE consignment_id = ?");
          $cleanupStmt->bind_param('i', $newId);
          $cleanupStmt->execute();
          $cleanupStmt->close();

          $cleanupStmt = $db->prepare("DELETE FROM transfers WHERE id = ?");
          $cleanupStmt->bind_param('i', $newId);
          $cleanupStmt->execute();
          $cleanupStmt->close();
        } catch (Exception $cleanupErr) {
          // Log cleanup failure but don't throw (only in debug mode)
          if (getenv('APP_DEBUG') === 'true') {
            error_log("Failed to cleanup partial transfer {$newId}: " . $cleanupErr->getMessage());
          }
        }
      }

      // Return error to user
      bad('RECREATE_FAILED', $e->getMessage());
    }
    break; // CRITICAL: Prevent fall-through to next case
  }

  // ============================================================
  // REVERT ENDPOINTS (Undo transfer status changes)
  // ============================================================

  case 'revert_to_open': {
    // Revert SENT → OPEN (safe, adds inventory back to source)
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) bad('INVALID_ID');

    // 1. Get transfer details
    $stmt = $db->prepare("SELECT * FROM transfers WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$t) bad('TRANSFER_NOT_FOUND');

    // 2. Verify status and category
    if ($t['state'] !== 'SENT') bad('CAN_ONLY_REVERT_FROM_SENT');
    if ($t['consignment_category'] === 'PURCHASE_ORDER') bad('CANNOT_REVERT_PURCHASE_ORDERS');

    // 3. Get consignment ID
    $consId = $t['vend_transfer_id'];
    if (!$consId) bad('NO_LIGHTSPEED_CONSIGNMENT');

    // 4. Update Lightspeed consignment status to OPEN
    if ($sync) {
      $lsUpdate = ls_update_consignment_status($consId, 'OPEN');
      if (!$lsUpdate['ok']) bad('LIGHTSPEED_UPDATE_FAILED', $lsUpdate['status']);
    }

    // 5. Add inventory back to source outlet (for each item)
    $itemsStmt = $db->prepare("SELECT product_id, qty_sent FROM consignment_items WHERE consignment_id = ?");
    $itemsStmt->bind_param('i', $id); $itemsStmt->execute();
    $items = $itemsStmt->get_result();
    $adjustments = [];

    while ($item = $items->fetch_assoc()) {
      $productId = $item['product_id'];
      $sentQty = (int)($item['qty_sent'] ?? 0);

      if ($sentQty > 0 && $sync) {
        // Add back to source outlet
        ls_update_outlet_stock($t['outlet_from'], $productId, $sentQty, 'ADD');
        $adjustments[] = [
          'product_id' => $productId,
          'outlet_id' => $t['outlet_from'],
          'quantity_added' => $sentQty
        ];
      }
    }
    $itemsStmt->close();

    // 6. Update transfer status
    $updateStmt = $db->prepare("UPDATE transfers SET state = 'OPEN', updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('i', $id); $updateStmt->execute(); $updateStmt->close();

    // 7. Log revert event
    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'REVERTED_TO_OPEN',
      'event_data'=>['adjustments'=>$adjustments], 'consignment_pk'=>$id, 'audit_after'=>['state'=>'OPEN']
    ]);

    // 8. Return success
    ok([
      'id' => $id,
      'message' => 'Transfer reverted to OPEN. Inventory restored to source outlet.',
      'inventory_adjustments' => $adjustments,
      'new_status' => 'OPEN'
    ]);
  }

  case 'revert_to_sent': {
    // Revert RECEIVING → SENT (safe, removes partial inventory from destination)
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) bad('INVALID_ID');

    // 1. Get transfer details
    $stmt = $db->prepare("SELECT * FROM transfers WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$t) bad('TRANSFER_NOT_FOUND');

    // 2. Verify status
    if ($t['state'] !== 'RECEIVING') bad('CAN_ONLY_REVERT_FROM_RECEIVING');

    // 3. Get consignment ID
    $consId = $t['vend_transfer_id'];
    if (!$consId) bad('NO_LIGHTSPEED_CONSIGNMENT');

    // 4. Determine target status (SENT for STOCK, STOCK_ORDER for PURCHASE_ORDER)
    $targetStatus = ($t['consignment_category'] === 'PURCHASE_ORDER') ? 'STOCK_ORDER' : 'SENT';

    // 5. Update Lightspeed consignment status
    if ($sync) {
      $lsUpdate = ls_update_consignment_status($consId, $targetStatus);
      if (!$lsUpdate['ok']) bad('LIGHTSPEED_UPDATE_FAILED', $lsUpdate['status']);
    }

    // 6. Remove partial inventory from destination (if any items marked received)
    $itemsStmt = $db->prepare("SELECT product_id, qty_received_total FROM consignment_items WHERE consignment_id = ?");
    $itemsStmt->bind_param('i', $id); $itemsStmt->execute();
    $items = $itemsStmt->get_result();
    $adjustments = [];

    while ($item = $items->fetch_assoc()) {
      $productId = $item['product_id'];
      $receivedQty = (int)($item['qty_received_total'] ?? 0);

      if ($receivedQty > 0 && $sync) {
        // Remove from destination outlet
        ls_update_outlet_stock($t['outlet_to'], $productId, $receivedQty, 'SUBTRACT');
        $adjustments[] = [
          'product_id' => $productId,
          'outlet_id' => $t['outlet_to'],
          'quantity_removed' => $receivedQty
        ];
      }
    }
    $itemsStmt->close();

    // 7. Reset received quantities to 0
    $resetStmt = $db->prepare("UPDATE consignment_items SET qty_received_total = 0 WHERE consignment_id = ?");
    $resetStmt->bind_param('i', $id); $resetStmt->execute(); $resetStmt->close();

    // 8. Update transfer status
    $updateStmt = $db->prepare("UPDATE transfers SET state = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('si', $targetStatus, $id); $updateStmt->execute(); $updateStmt->close();

    // 9. Log revert event
    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'REVERTED_TO_SENT',
      'event_data'=>['adjustments'=>$adjustments], 'consignment_pk'=>$id, 'audit_after'=>['state'=>$targetStatus]
    ]);

    // 10. Return success
    ok([
      'id' => $id,
      'message' => 'Transfer reverted. Receiving cancelled.',
      'inventory_adjustments' => $adjustments,
      'new_status' => $targetStatus
    ]);
  }

  case 'revert_to_receiving': {
    // Revert PARTIAL → RECEIVING (RISKY - removes finalized inventory)
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) bad('INVALID_ID');

    // 1. Get transfer details
    $stmt = $db->prepare("SELECT * FROM transfers WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$t) bad('TRANSFER_NOT_FOUND');

    // 2. Verify status
    if ($t['state'] !== 'PARTIAL') bad('CAN_ONLY_REVERT_FROM_PARTIAL');

    // 3. Get consignment ID
    $consId = $t['vend_transfer_id'];
    if (!$consId) bad('NO_LIGHTSPEED_CONSIGNMENT');

    // 4. Update Lightspeed consignment status to RECEIVING
    if ($sync) {
      $lsUpdate = ls_update_consignment_status($consId, 'RECEIVING');
      if (!$lsUpdate['ok']) bad('LIGHTSPEED_UPDATE_FAILED', $lsUpdate['status']);
    }

    // 5. Remove inventory from destination (WARNING: removes finalized stock)
    $itemsStmt = $db->prepare("SELECT product_id, qty_received_total FROM consignment_items WHERE consignment_id = ?");
    $itemsStmt->bind_param('i', $id); $itemsStmt->execute();
    $items = $itemsStmt->get_result();
    $adjustments = [];

    while ($item = $items->fetch_assoc()) {
      $productId = $item['product_id'];
      $receivedQty = (int)($item['qty_received_total'] ?? 0);

      if ($receivedQty > 0 && $sync) {
        ls_update_outlet_stock($t['outlet_to'], $productId, $receivedQty, 'SUBTRACT');
        $adjustments[] = [
          'product_id' => $productId,
          'outlet_id' => $t['outlet_to'],
          'quantity_removed' => $receivedQty
        ];
      }
    }
    $itemsStmt->close();

    // 6. Reset received quantities to 0
    $resetStmt = $db->prepare("UPDATE consignment_items SET qty_received_total = 0 WHERE consignment_id = ?");
    $resetStmt->bind_param('i', $id); $resetStmt->execute(); $resetStmt->close();

    // 7. Update transfer status
    $updateStmt = $db->prepare("UPDATE transfers SET state = 'RECEIVING', updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('i', $id); $updateStmt->execute(); $updateStmt->close();

    // 8. Log revert event with WARNING flag
    log_transfer_event($db, [
      'consignment_id'=>$id, 'event_type'=>'REVERTED_TO_RECEIVING',
      'event_data'=>['warning'=>'INVENTORY_REMOVED','adjustments'=>$adjustments], 'consignment_pk'=>$id, 'audit_after'=>['state'=>'RECEIVING']
    ]);

    // 9. Return success
    ok([
      'id' => $id,
      'message' => 'Transfer reverted to RECEIVING. Inventory removed from destination.',
      'inventory_adjustments' => $adjustments,
      'new_status' => 'RECEIVING'
    ]);
  }

  default:
    bad('UNKNOWN_ACTION', $action);
}
