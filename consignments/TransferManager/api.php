<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Load core CIS application for authentication
$included = false;
$appCandidates = [
    (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : '') . '/app.php',
    '/home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/app.php',
    '/home/master/applications/jcepnzzkmj/public_html/app.php'
];

// Enhanced output buffering to capture bootstrap constant warnings
ob_start();
foreach ($appCandidates as $candidate) {
    if (!$candidate) continue;
    if (is_readable($candidate)) {
        try {
            require_once $candidate;
            $included = true;
            break;
        } catch (Throwable $e) {
            // try next candidate
        }
    }
}

// Capture and discard any unwanted output (constant warnings, etc.)
$unwantedOutput = ob_get_contents();
ob_clean();

// Authentication guard - always return JSON for API endpoints
if (function_exists('isLoggedIn')) {
    if (!isLoggedIn()) {
        header('Content-Type: application/json; charset=utf-8', true, 401);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Authentication required. Please log in at staff.vapeshed.co.nz'
            ],
            'meta' => ['timestamp' => date('c')]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

/**
 * api.php — Vend Lightspeed Gateway (independent)
 * POST JSON: { pin:"...", action:"create_consignment", data:{...} }
 * Set LS_API_TOKEN and PIN_CODE in env for safety.
 */

const API_VERSION = '2.0.0';
const REQUEST_TIMEOUT = 30;
const MAX_RETRY_ATTEMPTS = 3;

// SECURITY: Load PIN from environment
function getPinCode(): string {
    $pin = $_ENV['TRANSFER_MANAGER_PIN'] ?? getenv('TRANSFER_MANAGER_PIN');
    if (!$pin) {
        error_log('[TransferManager] CRITICAL: TRANSFER_MANAGER_PIN not set in environment');
        http_response_code(500);
        die(json_encode(['ok' => false, 'error' => 'Server configuration error']));
    }
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

  // SECURITY: Require LS_API_TOKEN from environment
  $token = $_ENV['LS_API_TOKEN'] ?? getenv('LS_API_TOKEN');
  if (!$token) {
    error_log('[TransferManager] CRITICAL: LS_API_TOKEN not set in environment');
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

// SECURITY: Verify PIN from request against environment variable
if (($req['pin'] ?? '') !== getPinCode()) sendError('AUTH','Invalid PIN or missing',[],401);

$act = $req['action'] ?? '';
switch ($act) {
  case 'api_info':
    sendSuccess([
      'api' => 'CIS Vend Lightspeed Gateway',
      'version' => API_VERSION,
      'endpoints' => ['get_consignment','create_consignment','update_consignment','delete_consignment','get_consignment_totals','list_consignment_products','add_consignment_product','update_consignment_product','remove_consignment_product','list_products','get_product','list_outlets','list_suppliers']
    ]);

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

  default: sendError('UNKNOWN_ACTION',"Unknown action: $act", ['try'=>['api_info','get_consignment','create_consignment', 'list_consignment_products']]);
}
