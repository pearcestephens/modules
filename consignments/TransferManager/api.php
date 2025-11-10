<?php
declare(strict_types=1);

// Always respond in JSON and trap errors BEFORE any other includes that could emit HTML
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
}
// Start buffering early so fatal errors don't leak partial HTML
ob_start();

// JSON-mode error/exception handling registered FIRST
set_exception_handler(function(Throwable $e){
  http_response_code(500);
  if (ob_get_level() > 0) { @ob_clean(); }
  echo json_encode([
    'success' => false,
    'error' => [
      'code' => 'SERVER_ERROR',
      'message' => $e->getMessage(),
    ],
    'meta' => ['timestamp' => date('c')]
  ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  exit;
});

set_error_handler(function($severity, $message, $file, $line){
  http_response_code(500);
  if (ob_get_level() > 0) { @ob_clean(); }
  echo json_encode([
    'success' => false,
    'error' => [
      'code' => 'PHP_ERROR',
      'message' => $message,
      'file' => $file,
      'line' => $line
    ],
    'meta' => ['timestamp' => date('c')]
  ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  exit;
});

register_shutdown_function(function(){
  $err = error_get_last();
  if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    http_response_code(500);
    if (ob_get_level() > 0) { @ob_clean(); }
    echo json_encode([
      'success' => false,
      'error' => [
        'code' => 'FATAL',
        'message' => $err['message'],
        'file' => $err['file'] ?? null,
        'line' => $err['line'] ?? null
      ],
      'meta' => ['timestamp' => date('c')]
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  }
});

// Load core CIS application for authentication (modern bootstrap only)
$included = false;
$baseBootstrap = __DIR__ . '/../../base/bootstrap.php';
if (is_readable($baseBootstrap)) {
  require_once $baseBootstrap; // ensures CIS\Base\Database and services are available
  $included = true;
}

// IMPORTANT: Do NOT load legacy app.php here. It pulls in assets/functions/config.php
// which auto-loads every file in assets/functions/ including permissions.php, causing
// function name collisions (e.g., getUserRole()). The TransferManager API must operate
// solely on the modern module bootstrap above. If, in rare cases, the base bootstrap is
// unavailable, we still avoid pulling in legacy config to prevent redeclarations.

// Capture and discard any unwanted output from bootstrap (if any)
$unwantedOutput = ob_get_contents();
ob_clean();
// Resume a fresh buffer for normal JSON output
ob_start();

// (handlers already registered above)

// Early diagnostic endpoint (no auth) to investigate session/cookie issues
// Call with: POST { action: 'whoami' }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $raw = file_get_contents('php://input');
  $json = json_decode($raw ?: 'null', true);
  if (is_array($json) && isset($json['action']) && $json['action'] === 'whoami') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'success' => true,
      'data' => [
        'session_name' => session_name(),
        'session_id' => session_id(),
        'has_userID' => isset($_SESSION['userID']),
        'userID' => $_SESSION['userID'] ?? null,
        'cookies' => array_keys($_COOKIE ?? []),
        'cookie_session_id' => $_COOKIE[session_name()] ?? null,
        'headers' => [
          'accept' => $_SERVER['HTTP_ACCEPT'] ?? null,
          'xhr' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null,
        ]
      ],
      'meta' => ['timestamp' => date('c')]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
  }
}

// If global bootstrap attempted an auth redirect (Location header), neutralize it for AJAX API calls
// so the frontend doesn't see a 302/opaqueredirect. We'll return JSON 401 instead when unauthenticated.
if (!headers_sent()) {
  $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
            || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
  if ($isAjax) {
    foreach (headers_list() as $h) {
      if (stripos($h, 'Location:') === 0) {
        header_remove('Location');
        // Reset any accidental 3xx
        http_response_code(200);
        break;
      }
    }
  }
}

// Authentication guard - always return JSON for API endpoints (no HTML redirects)
// Consider user authenticated if either isLoggedIn() is true OR a valid session userID exists.
// This aligns with the rest of CIS and avoids false negatives during transitional templates.
$uid = $_SESSION['userID'] ?? null;
$authed = false;
if (function_exists('isLoggedIn')) {
  try { $authed = (bool)isLoggedIn(); } catch (Throwable $e) { $authed = false; }
}
if ($uid && !$authed) { $authed = true; }

if (!$authed) {
  header('Content-Type: application/json; charset=utf-8', true, 401);
  echo json_encode([
    'success' => false,
    'error' => [
      'code' => 'UNAUTHORIZED',
      'message' => 'Authentication required. Please log in.'
    ],
    'meta' => ['timestamp' => date('c')]
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

/**
 * api.php — Vend Lightspeed Gateway (independent)
 * POST JSON: { pin:"...", action:"create_consignment", data:{...} }
 * Set LS_API_TOKEN and PIN_CODE in env for safety.
 */

const API_VERSION = '2.0.0';
const REQUEST_TIMEOUT = 30;
const MAX_RETRY_ATTEMPTS = 3;

// Load centralized Config service to read secrets from public_html/.env
if (!class_exists('Services\\Config')) {
  $cfgPath = __DIR__ . '/../../../assets/services/Config.php';
  if (is_readable($cfgPath)) {
    require_once $cfgPath;
  }
}

// Helper to get config with fallbacks (namespaced to avoid collisions)
if (!function_exists('tm_cfg_get')) {
function tm_cfg_get(string $key, $default = null) {
  // Prefer centralized Config
  if (class_exists('Services\\Config')) {
    try {
      $cfg = \Services\Config::getInstance();
      return $cfg->get($key, $default);
    } catch (\Throwable $e) {
      // fall through
    }
  }
  // Fallback to environment
  $val = $_ENV[$key] ?? getenv($key);
  if ($val !== false && $val !== null && $val !== '') return $val;
  // Optional local override for dev: .env.local.json alongside this API
  static $local = null;
  if ($local === null) {
    $localFile = __DIR__ . '/.env.local.json';
    $local = (is_readable($localFile) ? (json_decode(file_get_contents($localFile), true) ?: []) : []);
  }
  if (is_array($local) && array_key_exists($key, $local)) return $local[$key];
  return $default;
}
}

// Temporary Override PIN (manager-issued, time-limited)
function tm_pin_file(): string { return __DIR__ . '/.override_pin.json'; }
function tm_load_pin(): ?array {
  $f = tm_pin_file();
  if (!is_readable($f)) return null;
  $j = json_decode(file_get_contents($f) ?: 'null', true);
  if (!is_array($j)) return null;
  $exp = (int)($j['expires_at'] ?? 0);
  if ($exp && $exp < time()) { @unlink($f); return null; }
  return $j;
}
function tm_save_pin(array $data): bool {
  $payload = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  return (bool)@file_put_contents(tm_pin_file(), $payload);
}
function tm_revoke_pin(): void { @unlink(tm_pin_file()); }
function tm_generate_pin(int $len = 6): string {
  $digits = '23456789'; $pin='';
  for ($i=0;$i<$len;$i++) { $pin .= $digits[random_int(0, strlen($digits)-1)]; }
  return $pin;
}

function getRequestId(): string { static $r=null; return $r ??= substr(md5(uniqid('req_', true)),0,12); }

/* ---------- File-based sync state (shared with backend.php) ---------- */
function get_sync_flag_file(): string {
  return __DIR__ . '/.sync_enabled';
}

function get_sync_enabled(): bool {
  $file = get_sync_flag_file();

  // If file doesn't exist, create it with default: enabled (1)
  if (!file_exists($file)) {
    file_put_contents($file, '1');
    return true;
  }

  // Always read fresh from file (no static cache)
  $content = trim(file_get_contents($file));
  return ($content === '1');
}

function set_sync_enabled(bool $enabled): void {
  $file = get_sync_flag_file();
  file_put_contents($file, $enabled ? '1' : '0');
}
function sendSuccess(array $data=[], array $meta=[]): void {
  $resp=['success'=>true,'data'=>$data,'meta'=>array_merge(['timestamp'=>date('c'),'request_id'=>getRequestId(),'api_version'=>API_VERSION],$meta)];
  header('Content-Type: application/json; charset=utf-8'); header('X-Request-ID: '.getRequestId());
  echo json_encode($resp, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit;
}
function sendError(string $code, string $msg, array $details=[], int $http=400): void {
  $resp=['success'=>false,'error'=>['code'=>$code,'message'=>$msg,'details'=>$details],'meta'=>['timestamp'=>date('c'),'request_id'=>getRequestId(),'api_version'=>API_VERSION]];
  header('Content-Type: application/json; charset=utf-8'); header('X-Request-ID: '.getRequestId()); http_response_code($http);
  echo json_encode($resp, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit;
}
function parseHeaders(string $raw): array { $h=[]; foreach (explode("\r\n",$raw) as $line){ if(strpos($line,':')!==false){[$k,$v]=explode(':',$line,2); $h[strtolower(trim($k))]=trim($v);} } return $h; }

function ls_base(): string { return 'https://vapeshed.retail.lightspeed.app/api/2.0'; }
function ls_ui(): string   { return 'https://vapeshed.retail.lightspeed.app/app/2.0'; }
function ls_req(string $method, string $endpoint, ?array $data=null): array {
  // Check if sync is enabled before making any Lightspeed API calls
  if (!get_sync_enabled()) {
    return ['success'=>false, 'status_code'=>0, 'error'=>'SYNC_DISABLED', 'message'=>'Lightspeed sync is disabled'];
  }

  $url = rtrim(ls_base(),'/').'/'.ltrim($endpoint,'/');
  $attempts=0; $max=MAX_RETRY_ATTEMPTS;

  // SECURITY: Require LS_API_TOKEN from configuration
  $token = tm_cfg_get('LS_API_TOKEN');
  if (!$token) {
    // Fallback to existing Vend access token if provided in central .env
    $token = tm_cfg_get('VEND_ACCESS_TOKEN');
  }
  if (!$token) {
    error_log('[TransferManager] CRITICAL: LS_API_TOKEN not set');
    return ['success'=>false, 'status_code'=>0, 'error'=>'CONFIG_ERROR', 'message'=>'Lightspeed API token not configured'];
  }

  do {
    $attempts++;
    $ch = curl_init($url);
    $hdr = [
      'Authorization: Bearer '.$token,
      'Accept: application/json',
      'Content-Type: application/json',
      'User-Agent: CIS-LS-Gateway/'.API_VERSION,
      'X-Request-ID: '.getRequestId().'_'.$attempts
    ];
    $opts = [
      CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>REQUEST_TIMEOUT,
      CURLOPT_CUSTOMREQUEST=>strtoupper($method), CURLOPT_HTTPHEADER=>$hdr, CURLOPT_HEADER=>true
    ];
    if ($data!==null) $opts[CURLOPT_POSTFIELDS]=json_encode($data, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch,$opts);
    $resp = curl_exec($ch);
    if ($resp === false) { $err=curl_error($ch); curl_close($ch); if ($attempts<$max){ usleep(200000*$attempts); continue; } return ['success'=>false,'status_code'=>0,'error'=>'CURL','message'=>$err]; }
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headers = parseHeaders(substr($resp,0,$hs)); $body = substr($resp,$hs);
    curl_close($ch);
    $json = json_decode($body, true); $data = $json ?? $body;
    if ($http==429 && $attempts<$max) { sleep((int)($headers['retry-after'] ?? 1)); continue; }
    return ['success'=>$http>=200 && $http<300, 'status_code'=>$http, 'headers'=>$headers, 'data'=>$data];
  } while ($attempts<$max);
  return ['success'=>false,'status_code'=>0,'error'=>'UNKNOWN'];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sendError('METHOD','POST required',[],405);
$raw = file_get_contents('php://input') ?: ''; $req = json_decode($raw,true);
if (!$req) sendError('INVALID_JSON','Body must be JSON');

// SECURITY: Verify credentials: prefer PIN; fallback to authenticated session + CSRF
$providedPin  = trim((string)($req['pin'] ?? ''));
$providedCsrf = (string)($req['csrf'] ?? '');

function tm_valid_csrf(string $token): bool {
  if ($token === '') return false;
  $candidates = [
    $_SESSION['tt_csrf'] ?? null,
    $_SESSION['csrf_token'] ?? null,
    $_SESSION['csrf'] ?? null
  ];
  foreach ($candidates as $t) {
    if (is_string($t) && $t !== '' && hash_equals($t, $token)) return true;
  }
  return false;
}

$activePin = tm_load_pin();
$pinOk  = ($providedPin !== '' && $activePin && hash_equals((string)$activePin['pin'], $providedPin));
$sessOk = ($authed && tm_valid_csrf($providedCsrf));
if (!($pinOk || $sessOk)) sendError('AUTH','Invalid credentials',[],401);

$act = $req['action'] ?? '';
switch ($act) {
  case 'pin_issue': {
    // Managers/Admins only
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array(strtolower((string)$role), ['manager','admin'], true)) sendError('FORBIDDEN','Not allowed',[],403);
    $ttl = max(60, min(3600, (int)($req['ttl'] ?? 900))); // 1–60 minutes
    $pin = tm_generate_pin(6);
    $payload = [
      'pin' => $pin,
      'issued_by' => (int)($_SESSION['userID'] ?? 0),
      'created_at' => time(),
      'expires_at' => time() + $ttl
    ];
    if (!tm_save_pin($payload)) sendError('PIN_SAVE_FAILED','Could not persist PIN');
    sendSuccess(['pin'=>$pin,'expires_at'=>$payload['expires_at']]);
  }
  case 'pin_status': {
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array(strtolower((string)$role), ['manager','admin'], true)) sendError('FORBIDDEN','Not allowed',[],403);
    $p = tm_load_pin();
    if (!$p) sendSuccess(['active'=>false]);
    $safe = $p; unset($safe['pin']);
    $safe['active']=true;
    sendSuccess($safe);
  }
  case 'pin_revoke': {
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array(strtolower((string)$role), ['manager','admin'], true)) sendError('FORBIDDEN','Not allowed',[],403);
    tm_revoke_pin();
    sendSuccess(['revoked'=>true]);
  }
  case 'api_info':
    sendSuccess([
      'api' => 'CIS Vend Lightspeed Gateway',
      'version' => API_VERSION,
      'endpoints' => ['list_transfers','get_transfer_detail','toggle_sync','verify_sync','get_consignment','create_consignment','update_consignment','delete_consignment','get_consignment_totals','list_consignment_products','add_consignment_product','update_consignment_product','remove_consignment_product','list_products','get_product','list_outlets','list_suppliers']
    ]);
  // local DB helpers used by Transfer Manager UI
  case 'list_transfers': {
    $page    = max(1, (int)($req['page'] ?? 1));
    $perPage = max(1, min(100, (int)($req['perPage'] ?? 25)));
    $type    = trim((string)($req['type'] ?? ''));
    $state   = trim((string)($req['state'] ?? ''));
    $outlet  = trim((string)($req['outlet'] ?? ''));
    $q       = trim((string)($req['q'] ?? ''));

    $where = [];$params=[];
    if ($type  !== '') { $where[]='c.transfer_category = :type'; $params[':type']=strtoupper($type); }
    if ($state !== '') { $where[]='c.state = :state'; $params[':state']=strtoupper($state); }
    if ($outlet!=='') { $where[]='(c.outlet_from = :outlet OR c.outlet_to = :outlet)'; $params[':outlet']=$outlet; }
    if ($q     !== '') { $where[]='(c.vend_number LIKE :q OR c.public_id LIKE :q OR c.outlet_from LIKE :q OR c.outlet_to LIKE :q)'; $params[':q']='%'.$q.'%'; }
    $whereSql = $where?('WHERE '.implode(' AND ',$where)) : '';

    $pdo = \CIS\Base\Database::pdo();
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM vend_consignments c $whereSql"); $stmt->execute($params); $total=(int)$stmt->fetchColumn();
    $offset=($page-1)*$perPage;
    $sql="SELECT c.id, c.transfer_category, c.state, c.vend_number, c.vend_transfer_id, c.outlet_from, c.outlet_to, c.total_count, c.total_boxes, c.updated_at
          FROM vend_consignments c $whereSql ORDER BY c.updated_at DESC LIMIT :limit OFFSET :offset";
    $stmt=$pdo->prepare($sql);
    foreach($params as $k=>$v){ $stmt->bindValue($k,$v); }
    $stmt->bindValue(':limit',$perPage, PDO::PARAM_INT); $stmt->bindValue(':offset',$offset, PDO::PARAM_INT); $stmt->execute();
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach($rows as &$r){ $r['status']=strtolower($r['state']??'open'); $r['total_boxes']=(int)($r['total_boxes']??0); }
    sendSuccess(['rows'=>$rows,'total'=>$total,'page'=>$page,'perPage'=>$perPage]);
  }

  case 'get_transfer_detail': {
    $id=(int)($req['id'] ?? 0); if ($id<=0) sendError('INVALID','id required');
    $pdo=\CIS\Base\Database::pdo();
    $t=$pdo->prepare("SELECT * FROM vend_consignments WHERE id=? LIMIT 1"); $t->execute([$id]); $transfer=$t->fetch(PDO::FETCH_ASSOC);
    if (!$transfer) sendError('NOT_FOUND','Transfer not found');
    $i=$pdo->prepare("SELECT * FROM vend_consignment_products WHERE consignment_id=? ORDER BY id ASC"); $i->execute([$id]); $items=$i->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $totals=['items'=>count($items),'qty'=>array_sum(array_map(function($x){ return (int)($x['qty'] ?? $x['qty_requested'] ?? 0); }, $items))];
    sendSuccess(['transfer'=>$transfer,'items'=>$items,'totals'=>$totals]);
  }

  case 'toggle_sync': { $enabled=(bool)($req['enabled'] ?? false); set_sync_enabled($enabled); sendSuccess(['sync_enabled'=>get_sync_enabled()]); }
  case 'verify_sync': {
    $pdo=\CIS\Base\Database::pdo(); $okDb=true; $queue=['total'=>0,'failed'=>0];
    try{ $q=$pdo->query("SELECT COUNT(*) total, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) failed FROM vend_consignment_queue WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"); $row=$q->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'failed'=>0]; $queue=['total'=>(int)$row['total'],'failed'=>(int)$row['failed']]; }catch(Throwable $e){ $okDb=false; }
    sendSuccess(['db'=>$okDb?'ok':'error','sync_enabled'=>get_sync_enabled(),'queue'=>$queue]);
  }

  // consignments
  case 'get_consignment': {
    $id = (string)($req['id'] ?? '');
    if (!$id) sendError('INVALID','id required');
    $r = ls_req('GET', "consignments/$id");
    $r['success'] ? sendSuccess($r['data'], ['ui_url'=>rtrim(ls_ui(),'/').'/consignments/'.rawurlencode($id)]) :
                    sendError('NOT_FOUND','Consignment not found',['status'=>$r['status_code']]);
  }

  case 'create_consignment': {
    $d = (array)($req['data'] ?? []);
    foreach (['outlet_id','type'] as $k) if (empty($d[$k])) sendError('MISSING',"Field '$k' is required");
    $payload = ['outlet_id'=>$d['outlet_id'],'type'=>strtoupper($d['type']),'status'=>$d['status'] ?? 'OPEN'];
    foreach (['name','reference','source_outlet_id','supplier_id'] as $k) if (isset($d[$k])) $payload[$k]=$d[$k];
    $r = ls_req('POST','consignments',$payload);
    $r['success'] ? sendSuccess($r['data'], ['ui_url'=>isset($r['data']['id'])? rtrim(ls_ui(),'/').'/consignments/'.$r['data']['id']:null]) :
                    sendError('CREATE_FAILED','Failed to create',$r, 502);
  }

  case 'update_consignment': {
    $id = (string)($req['id'] ?? '');
    $d  = (array)($req['data'] ?? []);
    if (!$id) sendError('INVALID','id required');
    // fetch current to preserve required fields
    $cur = ls_req('GET',"consignments/$id");
    if (!$cur['success']) sendError('NOT_FOUND','Consignment not found');
    $c = $cur['data'];
    $payload = [
      'type'      => $d['type'] ?? $c['type'],
      'outlet_id' => $d['outlet_id'] ?? $c['outlet_id'],
      'status'    => $d['status'] ?? $c['status'],
      'name'      => $d['name'] ?? $c['name'],
      'reference' => $d['reference'] ?? $c['reference']
    ];
    if (isset($c['source_outlet_id'])) $payload['source_outlet_id']=$d['source_outlet_id'] ?? $c['source_outlet_id'];
    if (isset($c['supplier_id']))      $payload['supplier_id']     =$d['supplier_id']      ?? $c['supplier_id'];
    $r = ls_req('PUT',"consignments/$id",$payload);
    $r['success'] ? sendSuccess($r['data']) : sendError('UPDATE_FAILED','Failed to update',$r, 502);
  }

  case 'delete_consignment': {
    $id = (string)($req['id'] ?? '');
    if (!$id) sendError('INVALID','id required');
    $r = ls_req('DELETE',"consignments/$id");
    $r['success'] ? sendSuccess(['deleted'=>true]) : sendError('DELETE_FAILED','Failed to delete',$r, 502);
  }

  case 'get_consignment_totals': {
    $id = (string)($req['id'] ?? '');
    if (!$id) sendError('INVALID','id required');
    $r = ls_req('GET',"consignments/$id/totals");
    $r['success'] ? sendSuccess($r['data']) : sendError('TOTALS_FAILED','Failed to get totals',$r, 502);
  }

  // products on consignment
  case 'list_consignment_products': {
    $id = (string)($req['id'] ?? ''); if (!$id) sendError('INVALID','id required');
    $endpoint = "consignments/$id/products";
    $r = ls_req('GET', $endpoint);
    $r['success'] ? sendSuccess($r['data']) : sendError('LIST_FAILED','Failed to list',$r, 502);
  }
  case 'add_consignment_product': {
    $id = (string)($req['id'] ?? ''); $d=(array)($req['data'] ?? []);
    if (!$id) sendError('INVALID','id required');
    foreach (['product_id','count'] as $k) if (!isset($d[$k])) sendError('MISSING',"'$k' required");
    $payload=['product_id'=>$d['product_id'],'count'=>(int)$d['count']];
    if (isset($d['received'])) $payload['received']=(int)$d['received'];
    if (isset($d['cost']))     $payload['cost']=(float)$d['cost'];
    $r=ls_req('POST',"consignments/$id/products",$payload);
    $r['success'] ? sendSuccess($r['data']) : sendError('ADD_FAILED','Failed to add',$r, 502);
  }
  case 'update_consignment_product': {
    $id=(string)($req['id'] ?? ''); $pid=(string)($req['product_id'] ?? ''); $d=(array)($req['data'] ?? []);
    if (!$id || !$pid) sendError('INVALID','id and product_id required');
    $payload=[]; if(isset($d['count']))$payload['count']=(int)$d['count']; if(isset($d['received']))$payload['received']=(int)$d['received']; if(isset($d['cost']))$payload['cost']=(float)$d['cost'];
    if(!$payload) sendError('NO_UPDATE','No fields');
    $r=ls_req('PUT',"consignments/$id/products/$pid",$payload);
    $r['success'] ? sendSuccess($r['data']) : sendError('UPDATE_FAILED','Failed to update',$r, 502);
  }
  case 'remove_consignment_product': {
    $id=(string)($req['id'] ?? ''); $pid=(string)($req['product_id'] ?? '');
    if (!$id || !$pid) sendError('INVALID','id and product_id required');
    $r=ls_req('DELETE',"consignments/$id/products/$pid");
    $r['success'] ? sendSuccess(['removed'=>true]) : sendError('REMOVE_FAILED','Failed to remove',$r, 502);
  }

  // general resources
  case 'list_products': { $r=ls_req('GET','products'); $r['success'] ? sendSuccess($r['data']) : sendError('LIST_FAILED','Failed',$r, 502); }
  case 'get_product':   { $id=(string)($req['id'] ?? ''); if(!$id) sendError('INVALID','id'); $r=ls_req('GET',"products/$id"); $r['success'] ? sendSuccess($r['data']) : sendError('NOT_FOUND','Product not found',$r,404); }
  case 'list_outlets':  { $r=ls_req('GET','outlets'); $r['success'] ? sendSuccess($r['data']) : sendError('LIST_FAILED','Failed',$r, 502); }
  case 'list_suppliers':{ $r=ls_req('GET','suppliers'); $r['success'] ? sendSuccess($r['data']) : sendError('LIST_FAILED','Failed',$r, 502); }

  default: sendError('UNKNOWN_ACTION',"Unknown action: $act", ['try'=>['list_transfers','get_transfer_detail','toggle_sync','verify_sync','api_info','get_consignment','create_consignment','list_consignment_products']]);
}
