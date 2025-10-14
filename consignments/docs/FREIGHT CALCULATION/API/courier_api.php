<?php
declare(strict_types=1);

/**
 * Courier API — V2 (Production-grade, mode-aware)
 * Compatible with regenerated Courier Console (Auto / Manual / Drop-off / Pickup).
 *
 * Success envelope:
 *   { ok: true, data: {...}, request_id: "hex", meta?: {...} }
 *
 * Error envelope:
 *   { ok: false, error: "CODE", human: "message", category: "INPUT|UPSTREAM|AUTHN|AUTHZ|DB|CONFLICT|INTERNAL",
 *     fields?: { k: msg }, request_id: "hex" }
 *
 * Actions:
 *   - rates
 *   - buy_label
 *   - cancel_label
 *   - address_validate
 *   - address_save
 *   - manual_dispatch
 *   - load_prefs
 *   - save_prefs
 */

///////////////////////////////////////////////////////////////////////////////////////////////////
// Bootstrap & Headers
///////////////////////////////////////////////////////////////////////////////////////////////////

const API_VERSION = '2.1.0';
$__REQ_ID = bin2hex(random_bytes(8));

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Request-Id: ' . $__REQ_ID);

error_reporting(E_ALL);
ini_set('display_errors', '0');

// CIS Bootstrap - Required for all modules
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?: '/home/master/applications/jcepnzzkmj/public_html';
require_once $docRoot . '/app.php';
require_once $docRoot . '/modules/transfers/_local_shims.php';

// Database helper - use cis_pdo() directly to avoid mysqli conflict
function pdo_db(): PDO {
    if (!function_exists('cis_pdo')) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'DB_BOOTSTRAP_MISSING','human'=>'Database connection unavailable.','category'=>'DB','request_id'=>$GLOBALS['__REQ_ID']]);
        exit;
    }
    return cis_pdo();
}


set_exception_handler(function(Throwable $e) use ($__REQ_ID){
    $msg = $e->getMessage();
    $code = 500;
    $err  = 'INTERNAL_ERROR';
    $cat  = 'INTERNAL';

    // Classify common sources
    if ($e instanceof PDOException) {
        $err='DB_UNAVAILABLE'; $cat='DB'; $code=503;
    } elseif ($e instanceof ErrorException) {
        $err='PHP_ERROR'; $cat='INTERNAL'; $code=500;
    } elseif (stripos($msg,'curl')!==false || stripos($msg,'http_')!==false) {
        $err='NETWORK'; $cat='UPSTREAM'; $code=502;
    }

    error_log("[CourierAPI][$__REQ_ID] {$err}/{$cat} :: ".$msg." @ ".$e->getFile().":".$e->getLine());

    // Minimal, safe message (don’t leak internals)
    R::err($err, [
        'human'    => $msg . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']',
        'category' => $cat,
    ], $code);
});

set_error_handler(function($sev,$msg,$file,$line){
    throw new ErrorException($msg, 0, $sev, $file, $line);
});

///////////////////////////////////////////////////////////////////////////////////////////////////
// Request parsing
///////////////////////////////////////////////////////////////////////////////////////////////////

$__JSON = (static function(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw !== '') {
            $j = json_decode($raw, true);
            if (is_array($j)) return $j;
        }
    }
    return [];
})();

function sval(string $k, $d=null) {
    global $__JSON;
    if (array_key_exists($k, $__JSON)) return $__JSON[$k];
    return $_POST[$k] ?? $d;
}
function jpost(string $k, $d=null) {
    global $__JSON;
    if (array_key_exists($k, $__JSON)) {
        return is_array($__JSON[$k]) ? $__JSON[$k] : $d;
    }
    if (isset($_POST[$k])) {
        $dec = json_decode((string)$_POST[$k], true);
        return is_array($dec) ? $dec : $d;
    }
    return $d;
}
function now(): string { return date('Y-m-d H:i:s'); }
function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function ua(): string { return $_SERVER['HTTP_USER_AGENT'] ?? ''; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED','human'=>'POST required','category'=>'INPUT','request_id'=>$__REQ_ID]);
    exit;
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// Response helpers
///////////////////////////////////////////////////////////////////////////////////////////////////

final class R {

     public static function _h(string $code, string $fallback='Unexpected server error.'): string {
        return self::defaultHuman($code) ?: $fallback;
    }

    public static function ok(array $d = [], int $c = 200, array $meta = []): void {
        global $__REQ_ID;
        http_response_code($c);
        echo json_encode(['ok'=>true,'data'=>$d,'request_id'=>$__REQ_ID,'meta'=>$meta ?: (object)[]],
            JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        exit;
    }
    public static function err(string $code, array $x = [], int $c = 400): void {
        global $__REQ_ID;
        $human    = (string)($x['human']    ?? self::defaultHuman($code));
        $category = (string)($x['category'] ?? self::defaultCategory($code));
        $fields   = isset($x['fields']) && is_array($x['fields']) ? $x['fields'] : null;
        http_response_code($c);
        $out = ['ok'=>false,'error'=>$code,'human'=>$human,'category'=>$category,'request_id'=>$__REQ_ID];
        if ($fields) $out['fields'] = $fields;
        echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        exit;
    }
    private static function defaultHuman(string $code): string {
        return [
            'NO_PARCELS'             => 'No parcel data was provided.',
            'INPUT_ZERO_WEIGHT'      => 'Weight is required.',
            'MISSING_TRANSFER'       => 'No transfer selected.',
            'UNAUTHENTICATED'        => 'Please sign in.',
            'GSS_SHIP_ERR'           => 'Carrier could not create a shipment.',
            'GSS_CANCEL_FAILED'      => 'Carrier could not cancel the label.',
            'NZP_LABEL_ERR'          => 'NZ Post could not issue a label.',
            'NETWORK'                => 'Network error.',
            'DB_UNAVAILABLE'         => 'Database not available.',
            'INTERNAL_ERROR'         => 'Unexpected server error.',
        ][$code] ?? $code;
    }
    private static function defaultCategory(string $code): string {
        if (strpos($code,'INPUT_')===0 || in_array($code,['NO_PARCELS','MISSING_TRANSFER'])) return 'INPUT';
        if (strpos($code,'AUTH')===0) return 'AUTHN';
        if (strpos($code,'GSS_')===0 || strpos($code,'NZP_')===0 || $code==='NETWORK') return 'UPSTREAM';
        if (strpos($code,'DB_')===0) return 'DB';
        if (strpos($code,'CONFLICT_')===0) return 'CONFLICT';
        return 'INTERNAL';
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// Utilities & domain helpers
///////////////////////////////////////////////////////////////////////////////////////////////////

function safe_write_file(string $absPath, string $binaryData): bool {
    $dir = dirname($absPath);
    if (!is_dir($dir)) { if (!@mkdir($dir, 0775, true) && !is_dir($dir)) return false; }
    $tmp = $absPath.'.tmp.'.bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $binaryData, LOCK_EX) === false) return false;
    return @rename($tmp, $absPath);
}

function require_user(): int {
    // For bot/API bypass, return a default user ID
    if (defined('BOT_BYPASS_AUTH') && BOT_BYPASS_AUTH) {
        return 18; // Default BOT user for API calls
    }
    
    $uid = (int)($_SESSION['user']['id'] ?? ($_SESSION['userID'] ?? 0));
    if ($uid <= 0) R::err('UNAUTHENTICATED', ['human'=>'You are not signed in.'], 401);
    return $uid;
}

function get_transfer(int $id): array {
    $s = pdo_db()->prepare("SELECT * FROM transfers WHERE id=? LIMIT 1");
    $s->execute([$id]);
    $t = $s->fetch(PDO::FETCH_ASSOC);
    if (!$t) R::err('MISSING_TRANSFER', [], 422);
    return $t;
}

function ensure_shipment(int $tid): array {
    $s = pdo_db()->prepare("SELECT * FROM transfer_shipments WHERE transfer_id=? ORDER BY id DESC LIMIT 1");
    $s->execute([$tid]);
    if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
        // Always refresh destination from outlet to ensure current address
        $row = populate_shipment_destination($row);
        return $row;
    }
    
    // Create new shipment
    pdo_db()->prepare("INSERT INTO transfer_shipments(transfer_id,delivery_mode,status,created_at) VALUES(?, 'auto','packed',NOW())")->execute([$tid]);
    $id = (int)pdo_db()->lastInsertId();
    $s = pdo_db()->prepare("SELECT * FROM transfer_shipments WHERE id=?");
    $s->execute([$id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    
    // Populate destination from outlet
    return populate_shipment_destination($row);
}

function populate_shipment_destination(array $shipment): array {
    try {
        // Get destination outlet info from the transfer
        $s = pdo_db()->prepare("
            SELECT 
                o.name,
                o.physical_address_1,
                o.physical_address_2,
                o.physical_suburb,
                o.physical_city,
                o.physical_postcode,
                o.email,
                o.physical_phone_number
            FROM transfers t
            JOIN vend_outlets o ON t.outlet_to = o.id
            WHERE t.id = ?
        ");
        $s->execute([$shipment['transfer_id']]);
        
        if ($outlet = $s->fetch(PDO::FETCH_ASSOC)) {
            // Update the shipment record with destination info
            $update = pdo_db()->prepare("
                UPDATE transfer_shipments 
                SET dest_name = ?,
                    dest_company = ?,
                    dest_addr1 = ?,
                    dest_addr2 = ?,
                    dest_suburb = ?,
                    dest_city = ?,
                    dest_postcode = ?,
                    dest_email = ?,
                    dest_phone = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $update->execute([
                $outlet['name'] ?: 'Store',
                $outlet['name'] ?: null,
                $outlet['physical_address_1'] ?: null,
                $outlet['physical_address_2'] ?: null,
                $outlet['physical_suburb'] ?: null,
                $outlet['physical_city'] ?: null,
                $outlet['physical_postcode'] ?: null,
                $outlet['email'] ?: null,
                $outlet['physical_phone_number'] ?: null,
                $shipment['id']
            ]);
            
            // Return updated shipment
            $s = pdo_db()->prepare("SELECT * FROM transfer_shipments WHERE id=?");
            $s->execute([$shipment['id']]);
            return $s->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log("[CourierAPI] populate_destination: ".$e->getMessage());
    }
    
    return $shipment;
}

/**
 * Merge custom destination fields with outlet defaults.
 * Custom fields override outlet data.
 */
function merge_destination_address(int $transferId, array $custom = []): array {
    $outlet = get_destination_address($transferId);
    return [
        'dest_name'     => $custom['dest_name'] ?? $custom['name'] ?? $outlet['dest_name'],
        'dest_company'  => $custom['dest_company'] ?? $custom['company'] ?? $outlet['dest_company'],
        'dest_addr1'    => $custom['dest_addr1'] ?? $custom['address1'] ?? $custom['street'] ?? $outlet['dest_addr1'],
        'dest_addr2'    => $custom['dest_addr2'] ?? $custom['address2'] ?? $outlet['dest_addr2'],
        'dest_suburb'   => $custom['dest_suburb'] ?? $custom['suburb'] ?? $outlet['dest_suburb'],
        'dest_city'     => $custom['dest_city'] ?? $custom['city'] ?? $outlet['dest_city'],
        'dest_postcode' => $custom['dest_postcode'] ?? $custom['postcode'] ?? $outlet['dest_postcode'],
        'dest_email'    => $custom['dest_email'] ?? $custom['email'] ?? $outlet['dest_email'],
        'dest_phone'    => $custom['dest_phone'] ?? $custom['phone'] ?? $outlet['dest_phone'],
        'dest_instructions' => $custom['dest_instructions'] ?? $custom['instructions'] ?? $custom['notes'] ?? null,
        'reference'     => $custom['reference'] ?? $custom['ref'] ?? null,
    ];
}

/**
 * Get destination address for a transfer WITHOUT creating/modifying shipment records.
 * Use this for read-only operations like getting quotes.
 */
function get_destination_address(int $transferId): array {
    try {
        $s = pdo_db()->prepare("
            SELECT 
                o.name,
                o.physical_address_1,
                o.physical_address_2,
                o.physical_suburb,
                o.physical_city,
                o.physical_postcode,
                o.email,
                o.physical_phone_number
            FROM transfers t
            JOIN vend_outlets o ON t.outlet_to = o.id
            WHERE t.id = ?
        ");
        $s->execute([$transferId]);
        
        if ($outlet = $s->fetch(PDO::FETCH_ASSOC)) {
            return [
                'dest_name'     => $outlet['name'] ?: 'Store',
                'dest_company'  => $outlet['name'] ?: null,
                'dest_addr1'    => $outlet['physical_address_1'] ?: null,
                'dest_addr2'    => $outlet['physical_address_2'] ?: null,
                'dest_suburb'   => $outlet['physical_suburb'] ?: null,
                'dest_city'     => $outlet['physical_city'] ?: null,
                'dest_postcode' => $outlet['physical_postcode'] ?: null,
                'dest_email'    => $outlet['email'] ?: null,
                'dest_phone'    => $outlet['physical_phone_number'] ?: null,
            ];
        }
    } catch (Throwable $e) {
        error_log("[CourierAPI] get_destination_address: ".$e->getMessage());
    }
    
    // Return empty/placeholder data if query fails
    return [
        'dest_name'     => 'Store',
        'dest_company'  => null,
        'dest_addr1'    => null,
        'dest_addr2'    => null,
        'dest_suburb'   => null,
        'dest_city'     => null,
        'dest_postcode' => null,
        'dest_email'    => null,
        'dest_phone'    => null,
    ];
}

/**
 * Merge custom destination data with outlet defaults.
 * Allows overriding specific fields while keeping outlet data as fallback.
 */
function build_destination(int $transferId, ?array $customDest = null): array {
    // Start with outlet address
    $dest = get_destination_address($transferId);
    
    // Override with custom fields if provided
    if (is_array($customDest)) {
        if (isset($customDest['name']))                 $dest['dest_name']     = $customDest['name'];
        if (isset($customDest['company']))              $dest['dest_company']  = $customDest['company'];
        if (isset($customDest['contact_person']))       $dest['dest_contact']  = $customDest['contact_person'];
        if (isset($customDest['phone']))                $dest['dest_phone']    = $customDest['phone'];
        if (isset($customDest['email']))                $dest['dest_email']    = $customDest['email'];
        if (isset($customDest['address_line1']))        $dest['dest_addr1']    = $customDest['address_line1'];
        if (isset($customDest['address_line2']))        $dest['dest_addr2']    = $customDest['address_line2'];
        if (isset($customDest['suburb']))               $dest['dest_suburb']   = $customDest['suburb'];
        if (isset($customDest['city']))                 $dest['dest_city']     = $customDest['city'];
        if (isset($customDest['postcode']))             $dest['dest_postcode'] = $customDest['postcode'];
        if (isset($customDest['country_code']))         $dest['dest_country']  = $customDest['country_code'];
        if (isset($customDest['delivery_instructions'])) $dest['dest_instructions'] = $customDest['delivery_instructions'];
        if (isset($customDest['is_business']))          $dest['is_business']   = (bool)$customDest['is_business'];
        if (isset($customDest['send_tracking_email']))  $dest['send_tracking'] = (bool)$customDest['send_tracking_email'];
    }
    
    return $dest;
}

/**
 * Parse cost breakdown from GSS rate response
 */
function parse_gss_costs(array $rate): array {
    return [
        'base_price'          => (float)($rate['BasePrice'] ?? $rate['Cost'] ?? 0),
        'fuel_surcharge'      => (float)($rate['FuelSurcharge'] ?? 0),
        'signature_surcharge' => (float)($rate['SignatureSurcharge'] ?? 0),
        'saturday_surcharge'  => (float)($rate['SaturdaySurcharge'] ?? 0),
        'rural_surcharge'     => (float)($rate['RuralSurcharge'] ?? 0),
        'residential_surcharge'=> (float)($rate['ResidentialSurcharge'] ?? 0),
        'oversize_surcharge'  => (float)($rate['OversizeSurcharge'] ?? 0),
        'total_cost'          => (float)($rate['Cost'] ?? 0),
    ];
}

/**
 * Parse cost breakdown from NZ Post response
 */
function parse_nzpost_costs(array $charges): array {
    if (!is_array($charges)) return ['total_cost' => 0];
    
    return [
        'base_price'          => (float)($charges['base_price'] ?? 0),
        'fuel_surcharge'      => (float)($charges['fuel_surcharge'] ?? 0),
        'signature_fee'       => (float)($charges['signature_fee'] ?? 0),
        'saturday_fee'        => (float)($charges['saturday_fee'] ?? 0),
        'adult_signature_fee' => (float)($charges['adult_signature_fee'] ?? 0),
        'rural_fee'           => (float)($charges['rural_fee'] ?? 0),
        'oversize_fee'        => (float)($charges['oversize_fee'] ?? 0),
        'residential_fee'     => (float)($charges['residential_fee'] ?? 0),
        'total_cost'          => (float)($charges['total'] ?? 0),
    ];
}


function audit_log(int $tid, string $action, string $status='success', array $meta=[]): void {
    try {
        $sql="INSERT INTO transfer_audit_log (entity_type,transfer_pk,transfer_id,action,status,actor_type,created_at,metadata,ip_address,user_agent)
              VALUES('transfer',?,?,?,?,'user',NOW(),JSON_OBJECT('meta', CAST(? AS CHAR)),?,?)";
        pdo_db()->prepare($sql)->execute([$tid,(string)$tid,$action,$status,json_encode($meta),ip(),ua()]);
    } catch (Throwable $e) { error_log("[CourierAPI] audit: ".$e->getMessage()); }
}
function event_log(int $tid, string $evt, array $data=[]): void {
    try {
        pdo_db()->prepare("INSERT INTO transfer_logs(transfer_id,event_type,event_data,source_system,created_at) VALUES(?,?,?,?,NOW())")
            ->execute([$tid,$evt,json_encode($data),'CIS']);
    } catch (Throwable $e) { error_log("[CourierAPI] event: ".$e->getMessage()); }
}

/** Idempotency store */
function idem_check(?string $key): void {
    if (!$key) return;
    try {
        $s = pdo_db()->prepare("SELECT response_json,status_code FROM transfer_idempotency WHERE idem_key=?");
        $s->execute([$key]);
        if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
            http_response_code((int)$row['status_code']);
            echo $row['response_json']; exit;
        }
    } catch (Throwable $e) { error_log("[CourierAPI] idem_check: ".$e->getMessage()); }
}
function idem_store(?string $key, array $responseBody, int $statusCode): void {
    if (!$key) return;
    try {
        pdo_db()->prepare("INSERT INTO transfer_idempotency(idem_key,idem_hash,response_json,status_code,created_at)
                       VALUES(?,SHA2(?,256),?, ?, NOW())
                       ON DUPLICATE KEY UPDATE response_json=VALUES(response_json), status_code=VALUES(status_code)")
          ->execute([$key,(string)$key,json_encode($responseBody,JSON_UNESCAPED_SLASHES),$statusCode]);
    } catch (Throwable $e) { error_log("[CourierAPI] idem_store: ".$e->getMessage()); }
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// Credentials per outlet (dynamic every call)
///////////////////////////////////////////////////////////////////////////////////////////////////

function carrier_creds(string $outletFrom): array {
    $gss = ['access_key'=>null,'site_id'=>getenv('GSS_SITE_ID') ?: '','supportemail'=>getenv('GSS_SUPPORT_EMAIL') ?: 'it@local'];
    $nzp = [
        'mode'             => getenv('NZPOST_MODE') ?: 'parcellabel',
        'api_key'          => null,
        'subscription_key' => null,
        'client_id'        => getenv('NZPOST_CLIENT_ID') ?: '',
        'client_secret'    => getenv('NZPOST_CLIENT_SECRET') ?: '',
        'site_code'        => getenv('NZPOST_SITE_CODE') ?: '',
        'account'          => getenv('NZPOST_ACCOUNT') ?: '',
    ];
    try {
        $s = pdo_db()->prepare("SELECT gss_token, nz_post_api_key, nz_post_subscription_key FROM vend_outlets WHERE id=? LIMIT 1");
        $s->execute([$outletFrom]);
        if ($row=$s->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['gss_token']))                $gss['access_key']       = $row['gss_token'];
            if (!empty($row['nz_post_api_key']))          $nzp['api_key']          = $row['nz_post_api_key'];
            if (!empty($row['nz_post_subscription_key'])) $nzp['subscription_key'] = $row['nz_post_subscription_key'];
        }
    } catch (Throwable $e) { error_log("[CourierAPI] creds: ".$e->getMessage()); }
    if (!$gss['access_key']) $gss['access_key'] = getenv('GSS_ACCESS_KEY') ?: null;
    
    // Determine primary carrier - GSS only needs access_key (site_id is optional)
    $primary = (!empty($gss['access_key'])) ? 'GSS' : (
        (!empty($nzp['api_key']) || !empty($nzp['subscription_key']) || ($nzp['client_id'] && $nzp['client_secret'])) ? 'NZ_POST' : 'NONE'
    );
    return ['carrier'=>$primary,'gss'=>$gss,'nzpost'=>$nzp];
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// Service & Container catalogs (minimal, DB-backed where available)
///////////////////////////////////////////////////////////////////////////////////////////////////

function sc_get_service_row_by_code(string $carrier_code, string $service_code): ?array {
    $sql="SELECT cs.* FROM carrier_services cs JOIN carriers c ON c.carrier_id=cs.carrier_id
          WHERE c.code=? AND cs.code=? LIMIT 1";
    $s=pdo_db()->prepare($sql); $s->execute([$carrier_code,$service_code]); $r=$s->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}
function sc_resolve_provider_code(int $service_id, string $provider): ?string {
    try {
        $s=pdo_db()->prepare("SELECT provider_code FROM carrier_service_mappings WHERE service_id=? AND provider=? LIMIT 1");
        $s->execute([$service_id,$provider]); $r=$s->fetch(PDO::FETCH_ASSOC);
        return $r['provider_code'] ?? null;
    } catch (Throwable $e){ error_log("[CourierAPI] svc map: ".$e->getMessage()); return null; }
}
function cc_carrier_id(string $code): ?int {
    $s=pdo_db()->prepare("SELECT carrier_id FROM carriers WHERE code=? LIMIT 1"); $s->execute([$code]);
    $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? (int)$r['carrier_id'] : null;
}
function cc_list_containers(string $carrier_code, ?string $service_code=null): array {
    $cid=cc_carrier_id($carrier_code); if(!$cid) return [];
    $sql="SELECT * FROM v_carrier_container_prices WHERE carrier_id=?"; $args=[$cid];
    if ($service_code) { $sql.=" AND (service_code=? OR service_code IS NULL OR service_code='')"; $args[]=$service_code; }
    $sql.=" ORDER BY (kind='bag') DESC, cost ASC, container_cap_g ASC";
    $s=pdo_db()->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function cc_container_fits(array $row, array $p): bool {
    $cap=(int)($row['rule_cap_g'] ?? $row['container_cap_g'] ?? 0);
    if ($cap>0 && (int)($p['weight_g'] ?? 0) > $cap) return false;
    foreach (['length_mm'=>'length_mm','width_mm'=>'width_mm','height_mm'=>'height_mm'] as $rk=>$pk) {
        $rv=(int)($row[$rk] ?? 0); $pv=(int)($p[$pk] ?? 0); if ($rv>0 && $pv>0 && $pv>$rv) return false;
    }
    return true;
}
function cc_best_container_for_parcel(string $carrier_code, ?string $service_code, array $parcel, bool $prefer_satchel=true): ?array {
    $rows=cc_list_containers($carrier_code,$service_code); if(!$rows) return null;
    $passes=$prefer_satchel ? [['bag'],['bag','box','document','unknown']] : [['bag','box','document','unknown']];
    foreach ($passes as $kinds) foreach ($rows as $r) {
        $kind=$r['kind'] ?? 'unknown'; if(!in_array($kind,$kinds,true)) continue; if (cc_container_fits($r,$parcel)) return $r;
    }
    return $rows[0] ?? null;
}
function cc_pick_containers(string $carrier_code, ?string $service_code, array $parcels, bool $prefer_satchel=true): array {
    $list=[]; $total=0.0;
    foreach ($parcels as $p) {
        $r=cc_best_container_for_parcel($carrier_code,$service_code,$p,$prefer_satchel);
        if($r){ $list[]=$r; $total+=(float)($r['cost'] ?? 0); }
    }
    return ['containers'=>$list, 'est_cost'=>$total];
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// GSS Address Normalization (comprehensive)
// Spec: https://api-docs.gosweetspot.com/docs/models/contact-address-model.html
///////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Normalize address data for GSS API
 * 
 * GSS Address Requirements:
 * - BuildingName: Optional, max 50 chars (Unit/Level/Building identifier)
 * - StreetAddress: REQUIRED, max 50 chars (street number and name)
 * - Suburb: Optional, max 50 chars (suburb/locality)
 * - City: REQUIRED, max 50 chars (city or state)
 * - PostCode: Optional, max 10 chars (strongly recommended for accurate rating)
 * - CountryCode: REQUIRED, 2 chars (ISO Alpha 2: NZ, AU, US, etc)
 * 
 * @param array $raw Raw address data (CIS format or custom override)
 * @param array &$warnings Output array for warnings (truncations, missing fields, etc)
 * @return array GSS-formatted address object
 */
function normalize_gss_address(array $raw, array &$warnings = []): array {
    $warnings = $warnings ?? [];
    
    // Extract raw fields
    $addr1      = trim((string)($raw['dest_addr1'] ?? $raw['address1'] ?? ''));
    $addr2      = trim((string)($raw['dest_addr2'] ?? $raw['address2'] ?? ''));
    $suburb     = trim((string)($raw['dest_suburb'] ?? $raw['suburb'] ?? ''));
    $city       = trim((string)($raw['dest_city'] ?? $raw['city'] ?? ''));
    $postcode   = trim((string)($raw['dest_postcode'] ?? $raw['postcode'] ?? ''));
    $country    = strtoupper(trim((string)($raw['dest_country'] ?? $raw['country'] ?? 'NZ')));
    
    // Detect if addr2 contains unit/level/building info (should go to BuildingName)
    $unit_keywords = ['unit', 'level', 'floor', 'fl', 'apt', 'apartment', 'suite', 'ste', 'building', 'bldg'];
    $addr2_is_building = false;
    if ($addr2 !== '') {
        $addr2_lower = strtolower($addr2);
        foreach ($unit_keywords as $kw) {
            if (strpos($addr2_lower, $kw) !== false) {
                $addr2_is_building = true;
                break;
            }
        }
    }
    
    // Determine BuildingName and StreetAddress
    $building_name = '';
    $street_address = $addr1;
    
    if ($addr2_is_building) {
        // addr2 has unit/level info → BuildingName
        $building_name = $addr2;
    } elseif ($addr2 !== '' && $addr1 !== '') {
        // addr2 doesn't look like building name → prepend to street address
        $street_address = $addr2 . ', ' . $addr1;
    } elseif ($addr2 !== '' && $addr1 === '') {
        // Only addr2 provided → use as street address
        $street_address = $addr2;
    }
    
    // GSS field length limits
    $max_lengths = [
        'BuildingName'   => 50,
        'StreetAddress'  => 50,
        'Suburb'         => 50,
        'City'           => 50,
        'PostCode'       => 10,
        'CountryCode'    => 2
    ];
    
    // Apply length limits with truncation warnings
    $truncate = function(string $val, string $field) use ($max_lengths, &$warnings): string {
        $max = $max_lengths[$field] ?? 255;
        if (mb_strlen($val) > $max) {
            $warnings[] = [
                'type'     => 'address_truncation',
                'field'    => $field,
                'original' => $val,
                'truncated'=> mb_substr($val, 0, $max)
            ];
            return mb_substr($val, 0, $max);
        }
        return $val;
    };
    
    $building_name  = $truncate($building_name, 'BuildingName');
    $street_address = $truncate($street_address, 'StreetAddress');
    $suburb         = $truncate($suburb, 'Suburb');
    $city           = $truncate($city, 'City');
    $postcode       = $truncate($postcode, 'PostCode');
    $country        = $truncate($country, 'CountryCode');
    
    // Validate required fields
    if ($street_address === '') {
        $warnings[] = [
            'type'    => 'missing_required_field',
            'field'   => 'StreetAddress',
            'action'  => 'using_placeholder'
        ];
        $street_address = 'Address Required';
    }
    
    if ($city === '') {
        $warnings[] = [
            'type'    => 'missing_required_field',
            'field'   => 'City',
            'action'  => 'using_placeholder'
        ];
        $city = 'City Required';
    }
    
    // Validate CountryCode (must be 2 chars ISO Alpha 2)
    if (strlen($country) !== 2) {
        $warnings[] = [
            'type'    => 'invalid_country_code',
            'value'   => $country,
            'action'  => 'defaulting_to_NZ'
        ];
        $country = 'NZ';
    }
    
    // Warn if PostCode missing (not required but strongly recommended)
    if ($postcode === '') {
        $warnings[] = [
            'type'    => 'missing_recommended_field',
            'field'   => 'PostCode',
            'impact'  => 'may_affect_rating_accuracy_and_rural_detection'
        ];
    }
    
    // Build GSS address object (only include non-empty optional fields)
    $gss_address = [
        'StreetAddress' => $street_address,
        'City'          => $city,
        'CountryCode'   => $country
    ];
    
    if ($building_name !== '') $gss_address['BuildingName'] = $building_name;
    if ($suburb !== '')        $gss_address['Suburb']       = $suburb;
    if ($postcode !== '')      $gss_address['PostCode']     = $postcode;
    
    return $gss_address;
}

/**
 * Build complete GSS Contact object (Destination or Origin)
 * 
 * @param array $raw Raw contact data
 * @param array &$warnings Output warnings array
 * @return array GSS Contact Model object
 */
function build_gss_contact(array $raw, array &$warnings = []): array {
    $gss_address = normalize_gss_address($raw, $warnings);
    
    // Extract contact fields
    $name           = trim((string)($raw['dest_name'] ?? $raw['name'] ?? 'Receiver'));
    $company        = trim((string)($raw['dest_company'] ?? $raw['company'] ?? ''));
    $contact_person = trim((string)($raw['dest_contact'] ?? $raw['contact_person'] ?? $name));
    $email          = trim((string)($raw['dest_email'] ?? $raw['email'] ?? 'noreply@example.com'));
    $phone          = trim((string)($raw['dest_phone'] ?? $raw['phone'] ?? ''));
    $instructions   = trim((string)($raw['dest_instructions'] ?? $raw['delivery_instructions'] ?? ''));
    
    // Apply max lengths for contact fields
    if (mb_strlen($name) > 50) {
        $warnings[] = ['type' => 'truncation', 'field' => 'Name', 'original' => $name];
        $name = mb_substr($name, 0, 50);
    }
    if (mb_strlen($company) > 50) {
        $warnings[] = ['type' => 'truncation', 'field' => 'CompanyName', 'original' => $company];
        $company = mb_substr($company, 0, 50);
    }
    if (mb_strlen($contact_person) > 50) {
        $warnings[] = ['type' => 'truncation', 'field' => 'ContactPerson', 'original' => $contact_person];
        $contact_person = mb_substr($contact_person, 0, 50);
    }
    if (mb_strlen($phone) > 50) {
        $warnings[] = ['type' => 'truncation', 'field' => 'PhoneNumber', 'original' => $phone];
        $phone = mb_substr($phone, 0, 50);
    }
    if (mb_strlen($instructions) > 120) {
        $warnings[] = ['type' => 'truncation', 'field' => 'DeliveryInstructions', 'original' => $instructions];
        $instructions = mb_substr($instructions, 0, 120);
    }
    
    // Build GSS contact object
    $contact = [
        'Name'          => $name !== '' ? $name : 'Receiver',
        'Address'       => $gss_address,
        'Email'         => $email !== '' ? $email : 'noreply@example.com',
        'ContactPerson' => $contact_person !== '' ? $contact_person : $name,
        'PhoneNumber'   => $phone !== '' ? $phone : ''
    ];
    
    if ($company !== '')      $contact['CompanyName'] = $company;
    if ($instructions !== '') $contact['DeliveryInstructions'] = $instructions;
    
    return $contact;
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// Clients
///////////////////////////////////////////////////////////////////////////////////////////////////

final class GSSClient {
    private string $ak; private string $sid; private string $sup;
    public function __construct(array $a){ $this->ak=(string)$a['access_key']; $this->sid=(string)$a['site_id']; $this->sup=(string)($a['supportemail'] ?? 'it@local'); }
    private function H(): array { return ['Content-Type: application/json', 'access_key: '.$this->ak, 'site_id: '.$this->sid, 'supportemail: '.$this->sup]; }
    private function http(string $m, string $u, ?array $b=null): array {
        $ch=curl_init($u);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>$this->H(),CURLOPT_TIMEOUT=>30]);
        if($b!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($b));
        $out=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
        if($out===false) throw new RuntimeException("GSS_HTTP_ERR:".$err);
        $j=json_decode($out,true);
        if($j===null && $code>=400) throw new RuntimeException("GSS_HTTP_{$code}:{$out}");
        return [$code,$j,$out];
    }
    public function rates(array $payload): array { [, $j] = $this->http('POST','https://api.gosweetspot.com/api/rates',$payload); return $j ?? []; }
    public function ship(array $payload): array  { [, $j] = $this->http('POST','https://api.gosweetspot.com/api/shipments',$payload); return $j ?? []; }
    public function labels(string $connote,string $format='LABEL_PDF'): array {
        [, $j] = $this->http('GET','https://api.gosweetspot.com/api/labels?format='.$format.'&connote='.rawurlencode($connote),null); return $j ?? [];
    }
    public function cancel(string $connote): bool { [$c] = $this->http('DELETE','https://api.gosweetspot.com/api/shipments?connote='.rawurlencode($connote),null); return $c>=200 && $c<300; }
}
final class NZPostClient {
    private array $a;
    public function __construct(array $auth){ $this->a=$auth; }
    private function token(): string {
        $ch=curl_init('https://api.nzpost.co.nz/oauth2/token');
        $data=http_build_query(['grant_type'=>'client_credentials','scope'=>'parcellabel.read parcellabel.write']);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_USERPWD=>$this->a['client_id'].':'.$this->a['client_secret'],CURLOPT_POSTFIELDS=>$data,CURLOPT_TIMEOUT=>20]);
        $out=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
        if($out===false) throw new RuntimeException("NZP_OAUTH_ERR:".$err);
        $j=json_decode($out,true); if(!isset($j['access_token'])) throw new RuntimeException('NZP_OAUTH_HTTP_'.$code.':'.$out);
        return (string)$j['access_token'];
    }
    public function labelOauth(array $payload): array {
        $tok=$this->token(); $ch=curl_init('https://api.nzpost.co.nz/parcellabel/v3/labels');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json',"Authorization: Bearer {$tok}"],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>30]);
        $out=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
        if($out===false) throw new RuntimeException("NZP_HTTP_ERR:".$err);
        $j=json_decode($out,true); if(!$j || ($code<200 || $code>=300)) throw new RuntimeException('NZP_LABEL_HTTP_'.$code.':'.$out);
        return $j;
    }
    public function labelLegacy(array $b): array {
        $b['api_key']=$this->a['api_key'];
        $ch=curl_init('https://api.nzpost.co.nz/labels/generate');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($b),CURLOPT_TIMEOUT=>30]);
        $out=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
        if($out===false) throw new RuntimeException("NZP_HTTP_ERR:".$err);
        $j=json_decode($out,true); if(!$j || ($j['success'] ?? false)!==true) throw new RuntimeException('NZP_LABEL_ERR:'.($j['message'] ?? ('HTTP '.$code)));
        return $j;
    }
    public function cancelPlaceholder(string $labelId): bool { return true; }
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// Input normalization (parcels / weight)
///////////////////////////////////////////////////////////////////////////////////////////////////

function normalize_parcels(array $par, int $domG, int $volDiv = 5000): array {
    $fixes = [];
    $out   = [];

    // synthesize from dom_weight only
    if (!is_array($par) || !count($par)) {
        if ($domG > 0) {
            $out = [['type'=>'satchel','length_mm'=>0,'width_mm'=>0,'height_mm'=>0,'weight_g'=>$domG]];
            $fixes[] = ['type'=>'copied_dom_weight','detail'=>'Created single satchel from dom_weight_g'];
        } else {
            // placeholder 1kg
            $out = [['type'=>'satchel','length_mm'=>0,'width_mm'=>0,'height_mm'=>0,'weight_g'=>1000]];
            $fixes[] = ['type'=>'fallback_default_weight','detail'=>'No weight provided; assumed 1kg'];
        }
        return [$out,$fixes];
    }

    // coerce each parcel
    foreach ($par as $i => $p) {
        $w = (int)($p['weight_g'] ?? 0);
        $L = (int)($p['length_mm'] ?? 0);
        $W = (int)($p['width_mm']  ?? 0);
        $H = (int)($p['height_mm'] ?? 0);

        // kg→g guess
        if ($w >= 2 && $w <= 200) { $w *= 1000; $fixes[] = ['type'=>'unit_coerced','detail'=>"parcel[$i] weight looked like kg; coerced to g"]; }

        // if 0 weight but usable dims → volumetric
        if ($w <= 0 && $L>0 && $W>0 && $H>0 && $volDiv>0) {
            $kg_vol = (($L/10)*($W/10)*($H/10))/$volDiv;
            $w = (int)ceil($kg_vol * 1000);
            if ($w>0) $fixes[] = ['type'=>'volumetric_used','detail'=>"parcel[$i] weight from volume"];
        }

        // dims clamp to avoid zeros
        if ($L<=0) $L=1; if ($W<=0) $W=1; if ($H<=0) $H=1;

        $out[] = [
            'type'      => (string)($p['type'] ?? 'box'),
            'length_mm' => $L,
            'width_mm'  => $W,
            'height_mm' => $H,
            'weight_g'  => $w
        ];
    }

    // if still effective 0 → copy domG or fallback 1kg
    $sum = array_reduce($out, fn($c,$p)=>$c + (int)$p['weight_g'], 0);
    if ($sum <= 0) {
        if ($domG > 0) {
            $out[0]['weight_g'] = $domG;
            $fixes[] = ['type'=>'copied_dom_weight','detail'=>'copied dom_weight_g to parcel[0]'];
        } else {
            $out[0]['weight_g'] = 1000;
            $fixes[] = ['type'=>'fallback_default_weight','detail'=>'No weight provided; assumed 1kg'];
        }
    }

    // round to 10g and clamp parcel count
    foreach ($out as $i => &$p) {
        $p['weight_g'] = (int)(ceil(max(0,(int)$p['weight_g'])/10)*10);
    }
    if (count($out) > 50) {
        $out = array_slice($out, 0, 50);
        $fixes[] = ['type'=>'parcel_limit','detail'=>'trimmed to 50 parcels'];
    }

    return [$out,$fixes];
}

// Limits you can tune per business rules
const MAX_PARCELS     = 50;
const MAX_WEIGHT_G    = 30000;  // 30 kg per parcel hard cap
const MIN_WEIGHT_G    = 10;     // treat <10g as invalid noise
const MAX_DIM_MM      = 1500;   // 1.5 m edge
const VOL_DIVISOR     = 5000;

function validate_parcels_input(array $parcels, ?int $domG): void {
    if (!is_array($parcels)) {
        R::err('INPUT_INVALID', ['human'=>'Parcels must be an array.','category'=>'INPUT'], 422);
    }
    if (count($parcels) > MAX_PARCELS) {
        R::err('INPUT_TOO_MANY_PARCELS', ['human'=>"Max ".MAX_PARCELS." parcels.",'category'=>'INPUT'], 422);
    }

    $fields = [];
    foreach ($parcels as $i => $p) {
        $w = (int)($p['weight_g'] ?? 0);
        $L = (int)($p['length_mm'] ?? 0);
        $W = (int)($p['width_mm']  ?? 0);
        $H = (int)($p['height_mm'] ?? 0);

        // Accept zero here; normalize will fill from dom_weight_g — but enforce upper/lower bounds
        if ($w < 0 || $w > MAX_WEIGHT_G) {
            $fields["parcel[$i]-weight_g"] = "Weight must be 0 or between ".MIN_WEIGHT_G." and ".MAX_WEIGHT_G." grams.";
        }
        foreach ([['L',$L,'length_mm'],['W',$W,'width_mm'],['H',$H,'height_mm']] as $d) {
            if ($d[1] < 0 || $d[1] > MAX_DIM_MM) {
                $fields["parcel[$i]-{$d[2]}"] = strtoupper($d[0])." must be 0 or ≤ ".MAX_DIM_MM." mm.";
            }
        }
    }

    if ($fields) {
        R::err('INPUT_OUT_OF_RANGE', [
            'human'  => 'One or more parcels have invalid weight/dimensions.',
            'category' => 'INPUT',
            'fields' => $fields
        ], 422);
    }

    if ($domG !== null && $domG < 0) {
        R::err('INPUT_INVALID', ['human'=>'dom_weight_g must be ≥ 0','category'=>'INPUT'], 422);
    }
}


///////////////////////////////////////////////////////////////////////////////////////////////////
// Router prelude
///////////////////////////////////////////////////////////////////////////////////////////////////

$action      = (string) sval('action', '');
$transferId  = (int) jpost('transfer_id', (int) sval('transfer_id', (int)($_SESSION['transfer'] ?? 0)));
if ($action === '') R::err('MISSING_ACTION', ['human'=>'Action required'], 400);

///////////////////////////////////////////////////////////////////////////////////////////////////
// RATES — resilient quoting with container fallback
///////////////////////////////////////////////////////////////////////////////////////////////////
/* ===== EDITING BEGINS: RATES block replacement ===== */
if ($action === 'rates') {
    if ($transferId <= 0) R::err('MISSING_TRANSFER', [], 422);

    // Load transfer
    try { $t = get_transfer($transferId); }
    catch (Throwable $e) { error_log("[CourierAPI][$__REQ_ID] get_transfer: ".$e->getMessage()); R::err('DB_UNAVAILABLE', ['human'=>'Database not available.'], 503); }

    $preferSatchel = (bool) jpost('prefer_satchel', true);
    $sig           = (bool) jpost('sig_required', true);
    $domG          = (int)  jpost('dom_weight_g', 0);
    $saturday      = (bool) jpost('saturday', false);
    $pref          = strtoupper((string) sval('pref_carrier', 'AUTO'));
    $ttl           = 90;
    
    // Accept custom destination override
    $customDest    = jpost('destination', []);

    // Validate & normalize inputs first
    $incomingParcels = jpost('parcels', []);
    validate_parcels_input($incomingParcels, $domG);
    [$par,$fixes]  = normalize_parcels($incomingParcels, $domG, VOL_DIVISOR);

    // Container baseline (NZ Post catalog) — soft fail
    $containers = ['containers'=>[], 'est_cost'=>0.0];
    $container_meta = ['list'=>[], 'est_total_cost'=>0.0];
    try {
        $containers = cc_pick_containers('NZ_POST', null, $par, $preferSatchel);
        $container_meta = [
           'list' => array_map(static fn($r)=>[
               'container_id'=>(int)($r['container_id'] ?? 0),
               'container_code'=>(string)($r['container_code'] ?? ''),
               'container_name'=>(string)($r['container_name'] ?? ''),
               'kind'=>(string)($r['kind'] ?? 'unknown'),
               'length_mm'=>(int)($r['length_mm'] ?? 0),
               'width_mm' =>(int)($r['width_mm']  ?? 0),
               'height_mm'=>(int)($r['height_mm'] ?? 0),
               'cap_g'    =>(int)($r['container_cap_g'] ?? 0),
               'cost'     =>(float)($r['cost'] ?? 0),
           ], $containers['containers'] ?? []),
           'est_total_cost' => (float)($containers['est_cost'] ?? 0),
        ];
    } catch (Throwable $e) {
        error_log("[CourierAPI][$__REQ_ID] containers fallback: ".$e->getMessage());
        $fixes[] = ['type'=>'containers_fallback','detail'=>'catalog unavailable'];
    }

    $rates = [];
    $auth  = carrier_creds((string)($t['outlet_from'] ?? ''));

    // If user explicitly selected GSS but creds missing → actionable error
    if ($pref === 'GSS' && (($auth['carrier'] ?? 'NONE') !== 'GSS')) {
        R::err('CARRIER_CREDENTIALS_MISSING', [
            'human'=>'GoSweetSpot credentials missing for this outlet.',
            'category'=>'AUTHZ',
            'fields'=>['pref_carrier'=>'No GSS credentials configured']
        ], 403);
    }

    // GSS attempt when allowed
    $triedGss=false; $gssFailed=false; $gssErrHuman=null;
    if ($auth['carrier']==='GSS' || $pref==='GSS' || $pref==='AUTO') {
        $triedGss = true;
        try {
            // Get destination address (with custom override support)
            $destInfo = merge_destination_address($transferId, $customDest);
            
            // Use comprehensive GSS address normalization
            $addressWarnings = [];
            $dest = build_gss_contact($destInfo, $addressWarnings);
            
            // Log address warnings for debugging
            if (!empty($addressWarnings)) {
                foreach ($addressWarnings as $warn) {
                    error_log("[CourierAPI][$__REQ_ID] GSS address warning: " . json_encode($warn));
                }
                $fixes = array_merge($fixes, $addressWarnings);
            }
            $pk=[];
            foreach ($par as $i=>$p) {
                $kg = max(0.01, round(((int)$p['weight_g'])/1000, 2));
                $L  = max(1, (int)($p['length_mm'] ?? ($container_meta['list'][$i]['length_mm'] ?? 1)));
                $W  = max(1, (int)($p['width_mm']  ?? ($container_meta['list'][$i]['width_mm']  ?? 1)));
                $H  = max(1, (int)($p['height_mm'] ?? ($container_meta['list'][$i]['height_mm'] ?? 1)));
                $pkg=['Name'=> (($p['type'] ?? '')==='satchel' ? 'GSS-SATCHEL' : 'BOX'),'Length'=>$L,'Width'=>$W,'Height'=>$H,'Kg'=>$kg];
                if (!empty($container_meta['list'][$i]['container_code'])) $pkg['PackageCode']=$container_meta['list'][$i]['container_code'];
                $pk[]=$pkg;
            }
            $payload=['Destination'=>$dest,'IsSignatureRequired'=>$sig,'Packages'=>$pk];
            if ($saturday) $payload['SaturdayDelivery']=true;
            if (!empty($destInfo['dest_instructions'])) $payload['DeliveryInstructions']=$destInfo['dest_instructions'];
            if (!empty($destInfo['reference'])) $payload['Reference']=$destInfo['reference'];

            $g = new GSSClient($auth['gss']);
            $j = $g->rates($payload);
            error_log("[CourierAPI][$__REQ_ID] GSS rates raw response: ".json_encode($j));

            if (!is_array($j) || empty($j['Available'])) {
                $gssFailed = true; $gssErrHuman = 'No GSS services available for these parcels/destination.';
            } else {
                foreach ($j['Available'] as $row) {
                    // Extract full cost breakdown from GSS
                    $baseCost = (float)($row['Cost'] ?? 0);
                    $fuelSurcharge = (float)($row['FuelSurcharge'] ?? 0);
                    $ruralSurcharge = (float)($row['RuralSurcharge'] ?? 0);
                    $saturdaySurcharge = (float)($row['SaturdaySurcharge'] ?? 0);
                    $signatureSurcharge = (float)($row['SignatureSurcharge'] ?? 0);
                    $additionalSurcharges = (float)($row['AdditionalSurcharges'] ?? 0);
                    $totalCost = (float)($row['TotalCost'] ?? $row['Cost'] ?? 0);
                    
                    $rates[] = [
                        'provider'   => 'GSS',
                        'carrier'    => $row['CarrierName'] ?? 'GSS',
                        'service'    => $row['DeliveryType'] ?? 'Standard',
                        'cost'       => $totalCost > 0 ? $totalCost : $baseCost,
                        'note'       => trim(($row['Comments'] ?? '').' '.($row['ServiceStandard'] ?? '')),
                        'quote_id'   => $row['QuoteId'] ?? null,
                        'is_satchel' => (bool)(stripos($row['Comments'] ?? '', 'satchel') !== false),
                        'is_rural'   => (bool)($row['IsRuralDelivery'] ?? false),
                        'is_saturday'=> (bool)($row['IsSaturdayDelivery'] ?? false),
                        'breakdown'  => [
                            'base_cost'      => $baseCost,
                            'fuel_surcharge' => $fuelSurcharge,
                            'rural_surcharge'=> $ruralSurcharge,
                            'saturday_surcharge' => $saturdaySurcharge,
                            'signature_surcharge' => $signatureSurcharge,
                            'additional_surcharges' => $additionalSurcharges,
                            'total'          => $totalCost > 0 ? $totalCost : $baseCost
                        ],
                        'raw_response' => $row,
                        'meta'       => ['containers'=>$container_meta]
                    ];
                }
            }
        } catch (Throwable $e) {
            $gssFailed = true;
            $gssErrHuman = 'Carrier rate API error.';
            error_log("[CourierAPI][$__REQ_ID] GSS rates error: ".$e->getMessage());
        }
    }

    // If user forced GSS and it failed → return actionable error
    if (empty($rates) && $pref==='GSS') {
        R::err('GSS_RATE_ERR', [
            'human'    => $gssErrHuman ?: 'GSS did not return any services.',
            'category' => 'UPSTREAM',
            'fields'   => ['cx-parcel-rows'=>'Check weights/dimensions and destination address.']
        ], 502);
    }

    // NZ Post estimate fallback (or primary if preferred/AUTO)
    if (empty($rates) && ($auth['carrier']==='NZ_POST' || $pref==='NZ_POST' || $pref==='AUTO' || $gssFailed)) {
        $est = (float)$container_meta['est_total_cost'];
        if ($est <= 0.0) {
            $w = array_reduce($par, fn($c,$p)=>$c+(int)($p['weight_g']??0), 0);
            $est = 10.00 + max(1,$w)/500*1.50 + ($sig?1.0:0) + ($saturday?3.0:0);
        }
        $rates[] = [
            'provider'   => 'NZ_POST',
            'carrier'    => 'NZ Post',
            'service'    => 'Domestic',
            'cost'       => round($est, 2),
            'note'       => $gssFailed ? 'GSS unavailable — showing NZ Post estimate.' : 'Estimated from container view',
            'is_satchel' => true,
            'breakdown'  => ['base'=>7.90,'per_kg'=>1.25,'extras'=> ($sig?1.0:0)+($saturday?3.0:0)],
            'meta'       => ['containers'=>$container_meta]
        ];
    }

    // Nothing at all? Return explicit error instead of INTERNAL
    if (empty($rates)) {
        R::err('NO_RATES_AVAILABLE', [
            'human'=>'No carrier could produce a quote for these parcels.',
            'category'=>'UPSTREAM',
            'fields'=>['cx-parcel-rows'=>'Try reducing weight/dimensions or try a different carrier.']
        ], 502);
    }

    // sort: satchel first then price
    $preferSatchelSort = $preferSatchel;
    usort($rates, function($a,$b) use ($preferSatchelSort){
        $ac=$a['cost']??99999; $bc=$b['cost']??99999;
        $as=($a['is_satchel']??false)?0:1; $bs=($b['is_satchel']??false)?0:1;
        return $preferSatchelSort ? ($as<=>$bs ?: $ac<=>$bc) : ($ac<=>$bc);
    });

    R::ok(['rates'=>$rates,'chosen'=>$rates[0] ?? null,'ttl_sec'=>$ttl,'version'=>API_VERSION],
          200, ['input_fixes'=>$fixes]);
}
/* ===== EDITING ENDS: RATES block replacement ===== */


///////////////////////////////////////////////////////////////////////////////////////////////////
// BUY LABEL — idempotent, GSS / NZ Post paths
///////////////////////////////////////////////////////////////////////////////////////////////////

/* ===== EDITING BEGINS: BUY_LABEL block replacement ===== */
if ($action === 'buy_label') {
    if ($transferId <= 0) R::err('MISSING_TRANSFER', [], 422);

    // Load transfer/shipment and authz
    $t      = get_transfer($transferId);
    $ship   = ensure_shipment($transferId);
    $userId = 1;
    // Lock system removed - all operations are open

    // Idempotency
    $idem   = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? (sval('idem_key') ?: null);
    idem_check($idem);

    $rate   = jpost('rate', []);
    $customDest = jpost('destination', []); // Accept custom destination override
    $labelData  = jpost('label_data', []); // Accept extended label metadata (reference, instructions, etc)
    $incomingParcels = jpost('parcels', []);
    validate_parcels_input($incomingParcels, (int)jpost('dom_weight_g', 0)); // explicit bounds check

    [$par,$fixes] = normalize_parcels($incomingParcels, 0, VOL_DIVISOR);

    // prohibit zero or out-of-range weights before calling carriers
    $sum = array_reduce($par, fn($c,$p)=>$c+(int)($p['weight_g'] ?? 0), 0);
    if ($sum <= 0) {
        R::err('INPUT_ZERO_WEIGHT', [
            'human'=>'Please enter weight for at least one parcel.',
            'fields'=>['cx-parcel-rows'=>'At least one parcel with weight > 0 is required.']
        ], 422);
    }
    foreach ($par as $i=>$p) {
        $w=(int)($p['weight_g'] ?? 0);
        if ($w > MAX_WEIGHT_G || ($w>0 && $w < MIN_WEIGHT_G)) {
            R::err('INPUT_OUT_OF_RANGE', [
                'human'=>'Parcel weight outside allowed range.',
                'fields'=>["parcel[$i]-weight_g"=>"Use between ".MIN_WEIGHT_G." and ".MAX_WEIGHT_G." grams."],
                'category'=>'INPUT'
            ], 422);
        }
    }

    // Check for existing label (unless force_new=true to replace)
    $forceNew = (bool)jpost('force_new', false);
    if (!$forceNew) {
        $chk = pdo_db()->prepare("SELECT carrier_code AS carrier, tracking, label_url FROM transfer_labels WHERE transfer_id=? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
        $chk->execute([$transferId]);
        if ($exists = $chk->fetch(PDO::FETCH_ASSOC)) {
            $body = ['ok'=>true,'data'=>['labels'=>[ $exists ],'note'=>'Existing label returned (use force_new=true to replace)'],'request_id'=>$__REQ_ID,'version'=>API_VERSION];
            idem_store($idem, $body, 200);
            echo json_encode($body, JSON_UNESCAPED_SLASHES); exit;
        }
    } else {
        // Clear existing label data when forcing new label
        pdo_db()->prepare("UPDATE transfer_labels SET deleted_at=NOW(), deleted_by=? WHERE transfer_id=? AND deleted_at IS NULL")->execute([$userId, $transferId]);
        pdo_db()->prepare("DELETE FROM transfer_parcels WHERE shipment_id IN (SELECT id FROM transfer_shipments WHERE transfer_id=?)")->execute([$transferId]);
        pdo_db()->prepare("UPDATE transfer_shipments SET tracking_number=NULL, tracking_url=NULL, dispatched_at=NULL WHERE transfer_id=?")->execute([$transferId]);
        audit_log($transferId,'LABEL_REPLACED','success',['note'=>'Cleared previous label data for new label']);
    }

    $auth = carrier_creds((string)($t['outlet_from'] ?? ''));

    // Prefer provider inferred from rate, else outlet carrier, else NZ Post
    $provider = strtoupper((string)($rate['provider'] ?? $auth['carrier'] ?? 'NZ_POST'));

    // Provider credential guards (actionable errors)
    if ($provider === 'GSS' && (($auth['carrier'] ?? 'NONE') !== 'GSS')) {
        R::err('CARRIER_CREDENTIALS_MISSING', [
            'human'=>'GoSweetSpot credentials missing for this outlet.',
            'category'=>'AUTHZ',
            'fields'=>['pref_carrier'=>'No GSS credentials configured']
        ], 403);
    }
    if ($provider === 'NZ_POST') {
        $nzAuth = $auth['nzpost'] ?? [];
        $nzCredsOk = (!empty($nzAuth['client_id']) && !empty($nzAuth['client_secret'])) || !empty($nzAuth['api_key']) || !empty($nzAuth['subscription_key']);
        if (!$nzCredsOk) {
            R::err('CARRIER_CREDENTIALS_MISSING', [
                'human'=>'NZ Post credentials missing for this outlet.',
                'category'=>'AUTHZ',
                'fields'=>['pref_carrier'=>'No NZ Post credentials configured']
            ], 403);
        }
    }

    // Enrich dims from chosen containers if provided
    $containers = $rate['meta']['containers']['list'] ?? [];

    if ($provider === 'GSS') {
        $g = new GSSClient($auth['gss']);
        pdo_db()->beginTransaction();
        try {
            // persist parcels
            foreach ($par as $i=>$p) {
                $L=(int)($p['length_mm'] ?? ($containers[$i]['length_mm'] ?? 0));
                $W=(int)($p['width_mm']  ?? ($containers[$i]['width_mm']  ?? 0));
                $H=(int)($p['height_mm'] ?? ($containers[$i]['height_mm'] ?? 0));
                $G=(int)($p['weight_g'] ?? 0);
                pdo_db()->prepare("INSERT INTO transfer_parcels (shipment_id, box_number, weight_grams, length_mm, width_mm, height_mm, weight_kg, status, created_at)
                               VALUES (?, (SELECT IFNULL(MAX(box_number),0)+1 FROM transfer_parcels WHERE shipment_id=?), ?, ?, ?, ?, ?, 'pending', NOW())")
                  ->execute([$ship['id'],$ship['id'],$G,$L,$W,$H,($G>0?round($G/1000,2):null)]);
            }

            // Use custom destination if provided, otherwise from shipment
            $destInfo = !empty($customDest) ? merge_destination_address($transferId, $customDest) : $ship;
            
            // Use comprehensive GSS address normalization
            $addressWarnings = [];
            $dest = build_gss_contact($destInfo, $addressWarnings);
            
            // Log address warnings and audit
            if (!empty($addressWarnings)) {
                foreach ($addressWarnings as $warn) {
                    error_log("[CourierAPI][$__REQ_ID] GSS label address warning: " . json_encode($warn));
                }
                audit_log($transferId, 'ADDRESS_NORMALIZED', 'warning', ['warnings' => $addressWarnings]);
            }
            $pk=[];
            foreach ($par as $i=>$p) {
                $L=(int)($p['length_mm'] ?? ($containers[$i]['length_mm'] ?? 1));
                $W=(int)($p['width_mm']  ?? ($containers[$i]['width_mm']  ?? 1));
                $H=(int)($p['height_mm'] ?? ($containers[$i]['height_mm'] ?? 1));
                $G=max(0.01, round(((int)$p['weight_g'])/1000, 2));
                $pkg=['Name'=> (($p['type'] ?? '')==='satchel' ? 'GSS-SATCHEL' : 'BOX'),'Length'=>$L,'Width'=>$W,'Height'=>$H,'Kg'=>$G];
                if (!empty($containers[$i]['container_code'])) $pkg['PackageCode']=$containers[$i]['container_code'];
                $pk[]=$pkg;
            }
            $payload = ['Destination'=>$dest,'Packages'=>$pk,'IsSignatureRequired'=>true];
            if (!empty($rate['quote_id'])) $payload['QuoteId']=$rate['quote_id'];
            if (!empty($labelData['reference'])) $payload['Reference']=$labelData['reference'];
            if (!empty($labelData['instructions'])) $payload['DeliveryInstructions']=$labelData['instructions'];
            if (!empty($labelData['saturday'])) $payload['SaturdayDelivery']=true;

            $j = $g->ship($payload);
            $cons = $j['Consignments'][0] ?? null;
            if (!$cons) throw new RuntimeException('GSS_SHIPMENT_EMPTY');
            $connote = (string)($cons['Connote'] ?? '');
            $turl    = (string)($cons['TrackingUrl'] ?? '');
            
            // Capture actual costs from shipment response
            $actualCost = (float)($cons['Cost'] ?? $cons['TotalCost'] ?? 0);
            $costBreakdown = [
                'base_cost' => (float)($cons['BaseCost'] ?? $cons['Cost'] ?? 0),
                'fuel_surcharge' => (float)($cons['FuelSurcharge'] ?? 0),
                'rural_surcharge' => (float)($cons['RuralSurcharge'] ?? 0),
                'saturday_surcharge' => (float)($cons['SaturdaySurcharge'] ?? 0),
                'signature_surcharge' => (float)($cons['SignatureSurcharge'] ?? 0),
                'additional_surcharges' => (float)($cons['AdditionalSurcharges'] ?? 0),
                'total' => $actualCost
            ];
            
            $labels  = $g->labels($connote, 'LABEL_PDF');
            $b64     = (is_array($labels) && !empty($labels)) ? ($labels[0] ?? null) : null;

            $path = null;
            if ($b64) {
                $docroot = (string)($_SERVER['DOCUMENT_ROOT'] ?? getcwd());
                $rel     = '/labels/' . preg_replace('#[^A-Za-z0-9._-]#', '_', $connote) . '.pdf';
                $abs     = rtrim($docroot,'/\\') . $rel;
                if (!safe_write_file($abs, base64_decode($b64,true) ?: '')) throw new RuntimeException('LABEL_WRITE_FAILED');
                $path = $rel;
            }

            pdo_db()->prepare("INSERT INTO transfer_carrier_orders (transfer_id, carrier, order_id, order_number, payload)
                           VALUES (?, 'GSS', ?, ?, ?)")->execute([$transferId, $connote, 'TR-'.$transferId, json_encode($j)]);
            pdo_db()->prepare("INSERT INTO transfer_labels (transfer_id, order_id, carrier_code, tracking, label_url, spooled, created_by, created_at)
                           VALUES (?, NULL, 'GSS', ?, ?, 1, ?, NOW())")->execute([$transferId, $connote, (string)$path, $userId]);
            pdo_db()->prepare("UPDATE transfer_shipments SET carrier_name='GSS', tracking_number=?, tracking_url=?, dispatched_at=NOW() WHERE id=?")
              ->execute([$connote,$turl,(int)$ship['id']]);
            pdo_db()->prepare("UPDATE transfer_parcels SET status='labelled', courier='GSS', tracking_number=?, label_url=? WHERE shipment_id=?")
              ->execute([$connote,(string)$path,(int)$ship['id']]);

            audit_log($transferId,'LABEL_PURCHASED','success',['provider'=>'GSS','connote'=>$connote,'cost'=>$actualCost,'cost_breakdown'=>$costBreakdown,'containers'=>$containers,'fixes'=>$fixes]);

            $body=['ok'=>true,'data'=>['labels'=>[['carrier'=>'GSS','tracking'=>$connote,'label_url'=>$path,'cost'=>$actualCost,'cost_breakdown'=>$costBreakdown]]],'request_id'=>$__REQ_ID,'version'=>API_VERSION];
            idem_store($idem,$body,200);
            pdo_db()->commit();
            echo json_encode($body, JSON_UNESCAPED_SLASHES); exit;
        } catch (Throwable $e) {
            pdo_db()->rollBack();
            audit_log($transferId,'LABEL_PURCHASED','failed',['err'=>$e->getMessage()]);
            R::err('GSS_SHIP_ERR', ['human'=>'Carrier failed to create shipment.','fields'=>['cx-parcel-rows'=>'Check weights & dims']], 502);
        }
    }

    // NZ Post path
    $nz = new NZPostClient($auth['nzpost']);
    pdo_db()->beginTransaction();
    try {
        foreach ($par as $i=>$p) {
            $L=(int)($p['length_mm'] ?? ($containers[$i]['length_mm'] ?? 0));
            $W=(int)($p['width_mm']  ?? ($containers[$i]['width_mm']  ?? 0));
            $H=(int)($p['height_mm'] ?? ($containers[$i]['height_mm'] ?? 0));
            $G=(int)($p['weight_g'] ?? 0);
            pdo_db()->prepare("INSERT INTO transfer_parcels (shipment_id, box_number, weight_grams, length_mm, width_mm, height_mm, weight_kg, status, created_at)
                           VALUES (?, (SELECT IFNULL(MAX(box_number),0)+1 FROM transfer_parcels WHERE shipment_id=?), ?, ?, ?, ?, ?, 'pending', NOW())")
              ->execute([$ship['id'],$ship['id'],$G,$L,$W,$H,($G>0?round($G/1000,2):null)]);
        }

        $service_id  = (int)($rate['meta']['service']['service_id'] ?? 0);
        $svc_code    = $service_id ? sc_resolve_provider_code($service_id, 'NZ_POST') : null;
        if (!$svc_code) $svc_code = 'PCM3C4'; // sensible default

        $p0 = $par[0] ?? [];
        $L  = (int)($p0['length_mm'] ?? ($containers[0]['length_mm'] ?? 300));
        $W  = (int)($p0['width_mm']  ?? ($containers[0]['width_mm']  ?? 200));
        $H  = (int)($p0['height_mm'] ?? ($containers[0]['height_mm'] ?? 200));
        $G  = max(0.01, round(((int)($p0['weight_g'] ?? 0))/1000, 2));

        $printUrl=null; $tracking='';

        if ((($auth['nzpost']['mode'] ?? 'parcellabel') === 'parcellabel') && !empty($auth['nzpost']['client_id'])) {
            $payload = [
                'labels' => [[
                    'carrier'  => 'NZP',
                    'domestic' => [[
                        'to' => [
                            'name'    => $ship['dest_name'] ?? 'Receiver',
                            'company' => $ship['dest_company'] ?? '',
                            'phone'   => $ship['dest_phone'] ?? '',
                            'email'   => $ship['dest_email'] ?? '',
                            'address' => [
                                'line1'   => $ship['dest_addr1'] ?? 'Address',
                                'suburb'  => $ship['dest_suburb'] ?? '',
                                'city'    => $ship['dest_city'] ?? '',
                                'postcode'=> $ship['dest_postcode'] ?? '',
                            ],
                        ],
                        'service_code' => $svc_code,
                        'packages'     => [[ 'length'=>$L,'width'=>$W,'height'=>$H,'weight'=>$G ]],
                    ]],
                ]],
            ];
            $j = $nz->labelOauth($payload);
            $printUrl = $j['labels'][0]['files'][0]['url'] ?? null;
            $tracking = (string)($j['labels'][0]['tracking_numbers'][0] ?? '');
        } else {
            $payload = [
                'destination_contact_name' => $ship['dest_name'] ?? 'Receiver',
                'destination_street'       => $ship['dest_addr1'] ?? 'Address',
                'destination_city'         => $ship['dest_city'] ?? '',
                'destination_country_code' => 'NZ',
                'sender_contact_name'      => 'Warehouse',
                'sender_street'            => 'Warehouse',
                'sender_city'              => 'Hamilton',
                'service_code'             => $svc_code,
                'user_reference_code'      => "TR-{$transferId}-".time(),
                'parcel_length'            => $L,
                'parcel_height'            => $H,
                'parcel_width'             => $W,
                'parcel_unit_description'  => 'Retail stock',
                'parcel_unit_quantity'     => 1,
                'parcel_unit_value'        => 1.00,
                'parcel_unit_currency'     => 'NZD',
                'parcel_unit_weight'       => $G,
                'insurance_required'       => 0,
                'documents'                => 0,
                'non_delivery_instruction' => 'RETURN',
                'validate_only'            => 0,
                'skip_print'               => 0,
                'force_regenerate'         => 1,
            ];
            $j = $nz->labelLegacy($payload);
            $printUrl = $j['print_url'] ?? null;
            $tracking = (string)($j['labels'][0]['tracking_numbers'][0] ?? ($j['tracking_number'] ?? ''));
        }

        pdo_db()->prepare("INSERT INTO transfer_carrier_orders (transfer_id, carrier, order_id, order_number, payload)
                       VALUES (?, 'NZ_POST', ?, ?, ?)")->execute([$transferId,$tracking,'TR-'.$transferId,json_encode($j)]);
        pdo_db()->prepare("INSERT INTO transfer_labels (transfer_id, order_id, carrier_code, tracking, label_url, spooled, created_by, created_at)
                       VALUES (?, NULL, 'NZ_POST', ?, ?, 1, ?, NOW())")->execute([$transferId,$tracking,(string)$printUrl,$userId]);
        pdo_db()->prepare("UPDATE transfer_shipments SET carrier_name='NZ Post', tracking_number=?, tracking_url=?, dispatched_at=NOW() WHERE id=?")
          ->execute([$tracking,(string)$printUrl,(int)$ship['id']]);
        pdo_db()->prepare("UPDATE transfer_parcels SET status='labelled', courier='NZ_POST', tracking_number=?, label_url=? WHERE shipment_id=?")
          ->execute([$tracking,(string)$printUrl,(int)$ship['id']]);

        audit_log($transferId,'LABEL_PURCHASED','success',['provider'=>'NZ_POST','tracking'=>$tracking,'containers'=>$containers,'fixes'=>$fixes]);

        pdo_db()->commit();
        R::ok(['labels'=>[['carrier'=>'NZ_POST','tracking'=>$tracking,'label_url'=>$printUrl]],'version'=>API_VERSION]);
    } catch (Throwable $e) {
        pdo_db()->rollBack();
        audit_log($transferId,'LABEL_PURCHASED','failed',['err'=>$e->getMessage()]);
        R::err('NZP_LABEL_ERR', ['human'=>'NZ Post failed to issue a label.'], 502);
    }
}
/* ===== EDITING ENDS: BUY_LABEL block replacement ===== */


///////////////////////////////////////////////////////////////////////////////////////////////////
// CANCEL LABEL — idempotent, shipment-scoped
///////////////////////////////////////////////////////////////////////////////////////////////////

if ($action === 'cancel_label') {
    if ($transferId <= 0) R::err('MISSING_TRANSFER', [], 422);

    $idem = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? (sval('idem_key') ?: null);
    idem_check($idem);

    $t    = get_transfer($transferId);
    $auth = carrier_creds((string)($t['outlet_from'] ?? ''));

    $s = pdo_db()->prepare("SELECT * FROM transfer_labels WHERE transfer_id=? ORDER BY id DESC LIMIT 1");
    $s->execute([$transferId]);
    $lab = $s->fetch(PDO::FETCH_ASSOC);
    if (!$lab) R::err('NO_LABEL', ['human'=>'There is no label to cancel.'], 404);

    $userId = require_user();

    if (strtoupper((string)$lab['carrier_code']) === 'GSS' && $auth['carrier']==='GSS') {
        try {
            $g = new GSSClient($auth['gss']);
            if (!$g->cancel((string)$lab['tracking'])) R::err('GSS_CANCEL_FAILED', ['human'=>'Carrier could not cancel this label.'], 502);

            pdo_db()->beginTransaction();
            pdo_db()->prepare("UPDATE transfer_labels SET deleted_at=NOW(), deleted_by=? WHERE transfer_id=? AND deleted_at IS NULL")->execute([$userId, $transferId]);
            pdo_db()->prepare("DELETE FROM transfer_parcels WHERE shipment_id IN (SELECT id FROM transfer_shipments WHERE transfer_id=?)")
              ->execute([$transferId]);
            pdo_db()->prepare("UPDATE transfer_shipments SET status='packed', tracking_number=NULL, tracking_url=NULL, dispatched_at=NULL WHERE transfer_id=?")
              ->execute([$transferId]);
            audit_log($transferId,'LABEL_CANCELLED','success',['provider'=>'GSS','connote'=>$lab['tracking']]);

            $body=['ok'=>true,'data'=>['cancelled'=>true],'request_id'=>$__REQ_ID,'version'=>API_VERSION];
            idem_store($idem,$body,200);
            pdo_db()->commit();
            echo json_encode($body, JSON_UNESCAPED_SLASHES); exit;
        } catch (Throwable $e) {
            R::err('GSS_CANCEL_ERR', ['human'=>'Carrier error while cancelling.'], 500);
        }
    } else {
        pdo_db()->beginTransaction();
        pdo_db()->prepare("UPDATE transfer_labels SET deleted_at=NOW(), deleted_by=? WHERE transfer_id=? AND deleted_at IS NULL")->execute([$userId, $transferId]);
        pdo_db()->prepare("DELETE FROM transfer_parcels WHERE shipment_id IN (SELECT id FROM transfer_shipments WHERE transfer_id=?)")
          ->execute([$transferId]);
        pdo_db()->prepare("UPDATE transfer_shipments SET status='packed', tracking_number=NULL, tracking_url=NULL, dispatched_at=NULL WHERE transfer_id=?")
          ->execute([$transferId]);
        audit_log($transferId,'LABEL_CANCELLED','success',['provider'=>'NZ_POST','note'=>'local cancel']);

        $body=['ok'=>true,'data'=>['cancelled'=>true],'request_id'=>$__REQ_ID,'version'=>API_VERSION];
        idem_store($idem,$body,200);
        pdo_db()->commit();
        echo json_encode($body, JSON_UNESCAPED_SLASHES); exit;
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// ADDRESS VALIDATE / SAVE
///////////////////////////////////////////////////////////////////////////////////////////////////

if ($action === 'address_validate') {
    $addr = jpost('address', []);
    R::ok([
        'status'=>'OK',
        'suggest'=>[
            'formatted'=>true,
            'line1'   => $addr['line1']    ?? null,
            'suburb'  => $addr['suburb']   ?? null,
            'city'    => $addr['city']     ?? null,
            'postcode'=> $addr['postcode'] ?? null,
        ],
        'version'=>API_VERSION
    ]);
}

if ($action === 'address_save') {
    if ($transferId <= 0) R::err('MISSING_TRANSFER', [], 422);
    $userId = require_user();

    $addr = jpost('address', []);
    $ship = ensure_shipment($transferId);

    $q="UPDATE transfer_shipments
        SET dest_name=?, dest_company=?, dest_addr1=?, dest_addr2=?, dest_suburb=?, dest_city=?, dest_postcode=?, dest_email=?, dest_phone=?, updated_at=NOW()
        WHERE id=?";
    pdo_db()->prepare($q)->execute([
        $addr['name']??null, $addr['company']??null, $addr['line1']??null, $addr['line2']??null,
        $addr['suburb']??null, $addr['city']??null, $addr['postcode']??null, $addr['email']??null, $addr['phone']??null,
        (int)$ship['id']
    ]);
    R::ok(['saved'=>true,'version'=>API_VERSION]);
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// MANUAL DISPATCH (manual / drop-off / pickup)
///////////////////////////////////////////////////////////////////////////////////////////////////

if ($action === 'manual_dispatch') {
    if ($transferId <= 0) R::err('MISSING_TRANSFER', [], 422);
    $userId = require_user();

    $mode     = (string) sval('mode','manual');       // manual|pickup|dropoff
    $carrier  = (string) sval('carrier','MANUAL');
    $tracking = (string) sval('tracking','');

    // Extra fields from UI
    $notes    = (string) sval('notes','');
    $do_loc   = (string) sval('dropoff_location','');
    $do_ref   = (string) sval('dropoff_ref','');
    $pu_date  = (string) sval('pickup_date','');
    $pu_ready = (string) sval('pickup_ready','');
    $pu_close = (string) sval('pickup_close','');
    $contact  = (string) sval('contact','');
    $phone    = (string) sval('phone','');

    $ship = ensure_shipment($transferId);

    pdo_db()->beginTransaction();
    try {
        $deliveryMode = ($mode === 'pickup') ? 'pickup' : (($mode === 'dropoff') ? 'dropoff' : 'courier');
        pdo_db()->prepare("UPDATE transfer_shipments
                       SET delivery_mode=?, status='packed', dispatched_at=NOW(), carrier_name=?, tracking_number=?, updated_at=NOW()
                       WHERE id=?")
          ->execute([$deliveryMode,$carrier,$tracking,(int)$ship['id']]);

        pdo_db()->prepare("INSERT INTO transfer_labels (transfer_id, carrier_code, tracking, label_url, spooled, created_by, created_at)
                       VALUES (?, ?, ?, '', 0, ?, NOW())")
          ->execute([$transferId, $carrier, $tracking, $userId]);

        // Persist structured notes to audit log; avoid schema coupling
        $meta = ['mode'=>$mode,'carrier'=>$carrier,'tracking'=>$tracking,'notes'=>$notes,
                 'dropoff_location'=>$do_loc,'dropoff_ref'=>$do_ref,
                 'pickup_date'=>$pu_date,'pickup_ready'=>$pu_ready,'pickup_close'=>$pu_close,
                 'contact'=>$contact,'phone'=>$phone];
        audit_log($transferId,'MANUAL_DISPATCH','success',$meta);
        event_log($transferId,'MANUAL_DISPATCH_SAVED',$meta);

        pdo_db()->commit();
        R::ok(['saved'=>true,'version'=>API_VERSION]);
    } catch (Throwable $e) {
        pdo_db()->rollBack();
        R::err('MANUAL_DISPATCH_ERR', ['human'=>'Failed to save manual dispatch.'], 500);
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// PREFS
///////////////////////////////////////////////////////////////////////////////////////////////////

if ($action === 'load_prefs') {
    if ($transferId <= 0) R::err('MISSING_TRANSFER', [], 422);
    $uid = require_user();

    $s = pdo_db()->prepare("SELECT state_json FROM transfer_ui_sessions WHERE transfer_id=? AND user_id=?");
    $s->execute([$transferId,$uid]);
    $row=$s->fetch(PDO::FETCH_ASSOC);
    $prefs=[];
    if($row){ $j=json_decode($row['state_json'] ?? '{}',true); if(isset($j['prefs']) && is_array($j['prefs'])) $prefs=$j['prefs']; }
    R::ok(['prefs'=>$prefs,'version'=>API_VERSION]);
}

if ($action === 'save_prefs') {
    if ($transferId <= 0) R::err('MISSING_TRANSFER', [], 422);
    $uid = require_user();
    $prefs = jpost('prefs', []);
    pdo_db()->prepare("INSERT INTO transfer_ui_sessions(transfer_id,user_id,state_json,autosave_at)
                   VALUES(?,?,?,NOW())
                   ON DUPLICATE KEY UPDATE state_json=VALUES(state_json), autosave_at=NOW()")
      ->execute([$transferId,$uid,json_encode(['prefs'=>$prefs])]);
    R::ok(['saved'=>true,'version'=>API_VERSION]);
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// GET ORIGIN ADDRESS
///////////////////////////////////////////////////////////////////////////////////////////////////

if ($action === 'get_origin_address') {
    if ($transferId <= 0) R::err('MISSING_TRANSFER', [], 422);
    
    try {
        $t = get_transfer($transferId);
        $outletId = trim((string)($t['outlet_from'] ?? ''));
        
        if(empty($outletId)){
            R::err('NO_ORIGIN_OUTLET', ['human'=>'No origin outlet for transfer'], 422);
        }
        
        $s = pdo_db()->prepare("SELECT name, physical_address_1, physical_address_2, physical_suburb, physical_city, physical_postcode, email, physical_phone_number
                                FROM vend_outlets WHERE id = ?");
        $s->execute([$outletId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        
        if(!$row){
            R::err('OUTLET_NOT_FOUND', ['human'=>'Origin outlet not found'], 404);
        }
        
        // Get store manager name (role_id = 3)
        $managerName = '';
        $mgr = pdo_db()->prepare("SELECT CONCAT(TRIM(first_name), ' ', TRIM(last_name)) as full_name 
                                  FROM users 
                                  WHERE role_id = 3 
                                  AND default_outlet = ? 
                                  AND staff_active = 1 
                                  ORDER BY last_active DESC 
                                  LIMIT 1");
        $mgr->execute([$outletId]);
        $mgrRow = $mgr->fetch(PDO::FETCH_ASSOC);
        if($mgrRow) {
            $managerName = trim((string)($mgrRow['full_name'] ?? ''));
        }
        
        $address = [
            'name'     => trim((string)($row['name'] ?? '')),
            'company'  => trim((string)($row['name'] ?? '')),
            'line1'    => trim((string)($row['physical_address_1'] ?? '')),
            'line2'    => trim((string)($row['physical_address_2'] ?? '')),
            'suburb'   => trim((string)($row['physical_suburb'] ?? '')),
            'city'     => trim((string)($row['physical_city'] ?? '')),
            'postcode' => trim((string)($row['physical_postcode'] ?? '')),
            'email'    => trim((string)($row['email'] ?? '')),
            'phone'    => trim((string)($row['physical_phone_number'] ?? '')),
            'manager'  => $managerName
        ];
        
        R::ok(['address'=>$address,'outlet_id'=>$outletId,'version'=>API_VERSION]);
        
    } catch (Throwable $e) {
        error_log("[CourierAPI][$__REQ_ID] get_origin_address: ".$e->getMessage());
        R::err('DB_ERROR', ['human'=>'Failed to load origin address'], 500);
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// GET DESTINATION ADDRESS (JSON response)
///////////////////////////////////////////////////////////////////////////////////////////////////

if ($action === 'get_destination_address') {
    if ($transferId <= 0) R::err('MISSING_TRANSFER', [], 422);
    
    try {
        $dest = get_destination_address($transferId);
        
        if(!$dest || empty($dest['dest_name'])){
            R::err('NO_DESTINATION', ['human'=>'No destination configured'], 422);
        }
        
        // Get store manager name (role_id = 3) for destination outlet
        $managerName = '';
        $destOutletId = trim((string)($dest['dest_outlet_id'] ?? ''));
        if(!empty($destOutletId)) {
            $mgr = pdo_db()->prepare("SELECT CONCAT(TRIM(first_name), ' ', TRIM(last_name)) as full_name 
                                      FROM users 
                                      WHERE role_id = 3 
                                      AND default_outlet = ? 
                                      AND staff_active = 1 
                                      ORDER BY last_active DESC 
                                      LIMIT 1");
            $mgr->execute([$destOutletId]);
            $mgrRow = $mgr->fetch(PDO::FETCH_ASSOC);
            if($mgrRow) {
                $managerName = trim((string)($mgrRow['full_name'] ?? ''));
            }
        }
        
        $address = [
            'name'     => trim((string)($dest['dest_name'] ?? '')),
            'company'  => trim((string)($dest['dest_company'] ?? '')),
            'line1'    => trim((string)($dest['dest_addr1'] ?? '')),
            'line2'    => trim((string)($dest['dest_addr2'] ?? '')),
            'suburb'   => trim((string)($dest['dest_suburb'] ?? '')),
            'city'     => trim((string)($dest['dest_city'] ?? '')),
            'postcode' => trim((string)($dest['dest_postcode'] ?? '')),
            'email'    => trim((string)($dest['dest_email'] ?? '')),
            'phone'    => trim((string)($dest['dest_phone'] ?? '')),
            'manager'  => $managerName
        ];
        
        R::ok(['address'=>$address,'outlet_id'=>(int)($dest['dest_outlet_id']??0),'version'=>API_VERSION]);
        
    } catch (Throwable $e) {
        error_log("[CourierAPI][$__REQ_ID] get_destination_address: ".$e->getMessage());
        R::err('DB_ERROR', ['human'=>'Failed to load destination address'], 500);
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// VALIDATE ADDRESS (NZ POST)
///////////////////////////////////////////////////////////////////////////////////////////////////

if ($action === 'validate_address_nzpost') {
    $name     = trim((string) jpost('name', ''));
    $company  = trim((string) jpost('company', ''));
    $line1    = trim((string) jpost('line1', ''));
    $line2    = trim((string) jpost('line2', ''));
    $suburb   = trim((string) jpost('suburb', ''));
    $city     = trim((string) jpost('city', ''));
    $postcode = trim((string) jpost('postcode', ''));
    
    if(empty($name) || empty($city) || empty($postcode)){
        R::err('INVALID_ADDRESS', ['human'=>'Name, city, and postcode required','fields'=>['name'=>'Required','city'=>'Required','postcode'=>'Required']], 422);
    }
    
    // NZ Post ParcelSend API address validation
    // Endpoint: POST https://api.nzpost.co.nz/parcelsend/v1/validate/address
    // Requires OAuth2 token from NZ Post credentials
    
    try {
        $auth = carrier_creds(''); // Get default credentials (adjust as needed)
        
        if(($auth['carrier'] ?? '') !== 'NZ_POST'){
            // No NZ Post credentials available - soft fail
            R::ok(['validated'=>false,'message'=>'NZ Post validation unavailable','original'=>[
                'name'=>$name,'company'=>$company,'line1'=>$line1,'line2'=>$line2,
                'suburb'=>$suburb,'city'=>$city,'postcode'=>$postcode
            ],'version'=>API_VERSION]);
        }
        
        $token = $auth['token'] ?? '';
        if(empty($token)){
            R::ok(['validated'=>false,'message'=>'NZ Post token unavailable','original'=>[
                'name'=>$name,'company'=>$company,'line1'=>$line1,'line2'=>$line2,
                'suburb'=>$suburb,'city'=>$city,'postcode'=>$postcode
            ],'version'=>API_VERSION]);
        }
        
        // Call NZ Post validation API
        $apiUrl = 'https://api.nzpost.co.nz/parcelsend/v1/validate/address';
        $payload = [
            'address' => [
                'name' => $name,
                'company' => $company,
                'street_address' => $line1,
                'street_address_2' => $line2,
                'suburb' => $suburb,
                'city' => $city,
                'postcode' => $postcode,
                'country' => 'NZ'
            ]
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'client_id: ' . ($auth['client_id'] ?? '')
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        
        if($curlErr || $httpCode !== 200){
            error_log("[CourierAPI][$__REQ_ID] NZ Post validation failed: HTTP $httpCode, $curlErr");
            R::ok(['validated'=>false,'message'=>'NZ Post API error','original'=>[
                'name'=>$name,'company'=>$company,'line1'=>$line1,'line2'=>$line2,
                'suburb'=>$suburb,'city'=>$city,'postcode'=>$postcode
            ],'version'=>API_VERSION]);
        }
        
        $data = json_decode($resp, true);
        
        if(!$data || !isset($data['address'])){
            R::ok(['validated'=>false,'message'=>'Invalid response from NZ Post','original'=>[
                'name'=>$name,'company'=>$company,'line1'=>$line1,'line2'=>$line2,
                'suburb'=>$suburb,'city'=>$city,'postcode'=>$postcode
            ],'version'=>API_VERSION]);
        }
        
        $validated = [
            'name'     => $data['address']['name'] ?? $name,
            'company'  => $data['address']['company'] ?? $company,
            'line1'    => $data['address']['street_address'] ?? $line1,
            'line2'    => $data['address']['street_address_2'] ?? $line2,
            'suburb'   => $data['address']['suburb'] ?? $suburb,
            'city'     => $data['address']['city'] ?? $city,
            'postcode' => $data['address']['postcode'] ?? $postcode,
        ];
        
        R::ok(['validated'=>$validated,'confidence'=>($data['confidence']??'unknown'),'version'=>API_VERSION]);
        
    } catch (Throwable $e) {
        error_log("[CourierAPI][$__REQ_ID] validate_address_nzpost exception: ".$e->getMessage());
        R::ok(['validated'=>false,'message'=>'Exception: '.$e->getMessage(),'original'=>[
            'name'=>$name,'company'=>$company,'line1'=>$line1,'line2'=>$line2,
            'suburb'=>$suburb,'city'=>$city,'postcode'=>$postcode
        ],'version'=>API_VERSION]);
    }
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// ADDRESS AUTOCOMPLETE SUGGESTIONS (NZ POST)
///////////////////////////////////////////////////////////////////////////////////////////////////

if ($action === 'address_suggestions_nzpost') {
    $query = trim((string) jpost('query', ''));
    
    if(strlen($query) < 3){
        R::ok(['suggestions'=>[],'version'=>API_VERSION]);
    }
    
    // NZ Post Address Finder API (if available)
    // For now, return empty suggestions (can be enhanced later with real API)
    R::ok(['suggestions'=>[],'message'=>'Address finder not yet implemented','version'=>API_VERSION]);
}

///////////////////////////////////////////////////////////////////////////////////////////////////
// Unknown
///////////////////////////////////////////////////////////////////////////////////////////////////

R::err('UNKNOWN_ACTION', ['human'=>'Unknown action.'], 400);
