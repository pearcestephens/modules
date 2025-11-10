<?php
declare(strict_types=1);

// Easy Vend Consignments (EVC) — quick GET proxy to Lightspeed/Vend
// URL: /modules/consignments/public/evc.php?limit=18[&today=1]
// Note: Requires a valid Vend/X-Series access token configured.

// Bootstrap CIS/Consignments for config/helpers
require_once __DIR__ . '/../bootstrap.php';
// Load env helper and hydrate from public_html/.env (and modules/.env if present)
$envLoader = dirname(__DIR__) . '/config/env-loader.php';
if (is_file($envLoader)) {
    require_once $envLoader;
    if (function_exists('loadEnvFromFile')) {
        // Document root .env (e.g., /home/.../public_html/.env)
        if (defined('ROOT_PATH')) {
            $rootEnv = rtrim(ROOT_PATH, '/') . '/.env';
            if (is_file($rootEnv)) { loadEnvFromFile($rootEnv); }
        }
        // Modules-level .env as fallback
        $modulesEnv = dirname(__DIR__, 1) . '/.env';
        if (is_file($modulesEnv)) { loadEnvFromFile($modulesEnv); }
    }
}

header('Content-Type: application/json; charset=utf-8');

// API versioning and correlation
$apiVersion = '1.0.0';
$correlationId = bin2hex(random_bytes(8));

// Resolve access token (prefer config table, then env fallbacks)
$token = null;
// Prefer explicit env var after loading .env
$envGet = function(string $k) { return function_exists('env') ? env($k) : getenv($k); };
$token = (string)($envGet('LS_API_TOKEN') ?? '') ?: '';
if ($token === '' && function_exists('cis_vend_access_token')) {
    try { $token = (string)(cis_vend_access_token(false) ?? ''); } catch (Throwable $e) { $token = ''; }
}
if ($token === '') {
    foreach (['LIGHTSPEED_API_TOKEN','VEND_ACCESS_TOKEN','VEND_API_TOKEN','LIGHTSPEED_TOKEN'] as $k) {
        $v = (string)($envGet($k) ?? '');
        if ($v !== '') { $token = $v; break; }
    }
}
if (!$token) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'missing_token',
        'message' => 'Vend/X-Series API token not configured. Set configuration key vend_access_token or export LS_API_TOKEN.',
    ]);
    exit;
}

// Resolve base URL
$baseUrl = null;
if (function_exists('cis_config_get')) {
    try { $baseUrl = cis_config_get('lightspeed_base_url', false); } catch (Throwable $e) { $baseUrl = null; }
}
if (!$baseUrl) {
    $baseUrl = (string)($envGet('LS_BASE_URL') ?? '') ?: (string)($envGet('LIGHTSPEED_BASE_URL') ?? '') ?: 'https://vapeshed.retail.lightspeed.app/api/2.0';
}

// Inputs
$limit = max(1, min((int)($_GET['limit'] ?? 18), 100));
$todayOnly = isset($_GET['today']) && (string)$_GET['today'] !== '0';
$format = strtolower((string)($_GET['format'] ?? 'json')); // 'json' | 'ndjson'
$fieldsParam = isset($_GET['fields']) ? (string)$_GET['fields'] : '';
$minimal = isset($_GET['minimal']) && (string)$_GET['minimal'] !== '0';

// Field selector
$selectFields = null;
if ($fieldsParam !== '') {
    $selectFields = array_values(array_filter(array_map('trim', explode(',', $fieldsParam))));
}

// Build request (fetch a bit more then trim client-side to honor "created latest")
$perPage = max($limit, 50);
$url = rtrim($baseUrl, '/') . '/consignments?per_page=' . $perPage;

// HTTP GET helper
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($status < 200 || $status >= 300 || !$body) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'vend_http_error',
        'status' => $status,
        'message' => $err ?: 'Upstream error',
    ]);
    exit;
}

$json = json_decode($body, true);
if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'vend_bad_response',
        'message' => 'Unexpected response shape from Vend API',
    ]);
    exit;
}

$rows = $json['data'];

// Sort by created_at desc if present
usort($rows, function ($a, $b) {
    $ca = isset($a['created_at']) ? strtotime((string)$a['created_at']) : 0;
    $cb = isset($b['created_at']) ? strtotime((string)$b['created_at']) : 0;
    return $cb <=> $ca;
});

// Optional: filter to NZ "today" if requested (Pacific/Auckland)
if ($todayOnly) {
    $tz = new DateTimeZone('Pacific/Auckland');
    $nzToday = (new DateTime('now', $tz))->format('Y-m-d');
    $rows = array_values(array_filter($rows, function ($r) use ($tz, $nzToday) {
        if (!isset($r['created_at'])) return false;
        try {
            $dt = new DateTime((string)$r['created_at']);
            $dt->setTimezone($tz);
            return $dt->format('Y-m-d') === $nzToday;
        } catch (Throwable $e) { return false; }
    }));
}

// Trim to requested limit
$rows = array_slice($rows, 0, $limit);

// Enrich with useful metadata and CIS links
try {
    $pdo = \CIS\Base\Database::pdo();
} catch (Throwable $e) {
    $pdo = null;
}

$uiBase = rtrim((string)((function_exists('env') ? env('APP_URL', 'https://staff.vapeshed.co.nz') : (getenv('APP_URL') ?: 'https://staff.vapeshed.co.nz'))), '/');
// Derive console base (strip "/api/2.0" from base URL and use "/app/2.0")
$parsed = parse_url($baseUrl);
$scheme = $parsed['scheme'] ?? 'https';
$host = $parsed['host'] ?? 'vapeshed.retail.lightspeed.app';
$consoleBase = $scheme . '://' . $host . '/app/2.0';

$enriched = [];
// Optional outlet name cache to reduce queries
$outletNameCache = [];
// Prepare reusable statements to reduce prepare overhead
$stmtMapQc = $stmtSumOrdered = $stmtSumReceived = $stmtShipCount = $stmtParcelCount = $stmtTrackCount = $stmtLatestShip = $stmtLatestReceipt = $stmtLatestNote = $stmtUserById = null;
if ($pdo) {
    try {
        $stmtMapQc = $pdo->prepare('SELECT id, state, cis_user_id, updated_by, created_at, updated_at FROM queue_consignments WHERE vend_consignment_id = ? LIMIT 1');
        $stmtSumOrdered = $pdo->prepare('SELECT SUM(count_ordered) FROM queue_consignment_products WHERE consignment_id = ?');
        $stmtSumReceived = $pdo->prepare('SELECT SUM(COALESCE(count_received,0)) FROM queue_consignment_products WHERE consignment_id = ?');
        $stmtShipCount = $pdo->prepare('SELECT COUNT(*) FROM consignment_shipments WHERE transfer_id = ?');
        $stmtParcelCount = $pdo->prepare('SELECT COUNT(*) FROM consignment_parcels p INNER JOIN consignment_shipments s ON p.shipment_id = s.id WHERE s.transfer_id = ?');
        $stmtTrackCount = $pdo->prepare('SELECT SUM(CASE WHEN COALESCE(p.tracking_number, p.tracking) IS NOT NULL AND COALESCE(p.tracking_number, p.tracking) <> "" THEN 1 ELSE 0 END) FROM consignment_parcels p INNER JOIN consignment_shipments s ON p.shipment_id = s.id WHERE s.transfer_id = ?');
        $stmtLatestShip = $pdo->prepare('SELECT carrier, created_at FROM consignment_shipments WHERE transfer_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmtLatestReceipt = $pdo->prepare('SELECT created_at FROM consignment_receipts WHERE transfer_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmtLatestNote = $pdo->prepare('SELECT note, created_at FROM consignment_notes WHERE transfer_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmtUserById = $pdo->prepare('SELECT CONCAT_WS(" ", first_name, last_name) as name FROM staff_accounts WHERE id = ?');
    } catch (Throwable $e) { /* ignore */ }
}

foreach ($rows as $r) {
    $vendId = (string)($r['id'] ?? '');
    $ref    = (string)($r['reference'] ?? '');
    $name   = (string)($r['name'] ?? '');
    $cn     = $ref !== '' ? $ref : ($name !== '' ? $name : substr($vendId, 0, 8));
    $srcId  = (string)($r['source_outlet_id'] ?? ($r['outlet_id'] ?? ''));
    $dstId  = (string)($r['destination_outlet_id'] ?? '');
    $created= (string)($r['created_at'] ?? '');
    $status = (string)($r['status'] ?? '');

    $cisId = null;
    $cisState = null;
    $itemCountTotal = null;
    if ($pdo && $vendId !== '') {
        try {
            if ($stmtMapQc) { $stmtMapQc->execute([$vendId]); $row = $stmtMapQc->fetch(PDO::FETCH_ASSOC); } else { $row = null; }
            if ($row) { $cisId = $row['id']; $cisState = $row['state'] ?? null; }
            // If we have CIS id, get item count total
            if ($cisId) {
                if ($stmtSumOrdered) { $stmtSumOrdered->execute([$cisId]); $itemCountTotal = (int)($stmtSumOrdered->fetchColumn() ?: 0); }
            }
        } catch (Throwable $e) { /* ignore mapping errors */ }
    }

    // Build links
    $cisPackUrl = $cisId ? ($uiBase . '/modules/consignments/stock-transfers/pack.php?id=' . urlencode((string)$cisId)) : null;
    $vendConsoleUrl = $consoleBase . '/consignments/' . urlencode($vendId);

    // NZ time shadow
    $createdNz = null;
    if ($created) {
        try { $dt = new DateTime($created); $dt->setTimezone(new DateTimeZone('Pacific/Auckland')); $createdNz = $dt->format(DateTime::ATOM); } catch (Throwable $e) { $createdNz = null; }
    }

    // Resolve outlet names and metadata (with small cache)
    $fromOutletName = null; $toOutletName = null;
    $fromOutletMeta = null; $toOutletMeta = null;
    if ($pdo) {
        try {
            if ($srcId) {
                if (!isset($outletNameCache[$srcId])) {
                    $st = $pdo->prepare('SELECT * FROM vend_outlets WHERE id = ? LIMIT 1');
                    $st->execute([$srcId]);
                    $meta = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                    $outletNameCache[$srcId] = $meta ? ($meta['name'] ?? null) : null;
                    $fromOutletMeta = $meta;
                }
                $fromOutletName = $outletNameCache[$srcId];
                if ($fromOutletMeta === null) {
                    // Quick fetch name-only cache hit; pull meta if needed later
                    $st = $pdo->prepare('SELECT * FROM vend_outlets WHERE id = ? LIMIT 1');
                    $st->execute([$srcId]);
                    $fromOutletMeta = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
            if ($dstId) {
                if (!isset($outletNameCache[$dstId])) {
                    $st2 = $pdo->prepare('SELECT * FROM vend_outlets WHERE id = ? LIMIT 1');
                    $st2->execute([$dstId]);
                    $meta2 = $st2->fetch(PDO::FETCH_ASSOC) ?: null;
                    $outletNameCache[$dstId] = $meta2 ? ($meta2['name'] ?? null) : null;
                    $toOutletMeta = $meta2;
                }
                $toOutletName = $outletNameCache[$dstId];
                if ($toOutletMeta === null) {
                    $st2 = $pdo->prepare('SELECT * FROM vend_outlets WHERE id = ? LIMIT 1');
                    $st2->execute([$dstId]);
                    $toOutletMeta = $st2->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        } catch (Throwable $e) { /* ignore */ }
    }

    // Shipment and parcels counts (if CIS id present)
    $shipmentsCount = null; $parcelsCount = null;
    $receivedCountTotal = null; $createdByName = null; $updatedByName = null; $latestShipCarrier = null; $latestShipAt = null; $latestReceiptAt = null; $latestNote = null;
    if ($pdo && $cisId) {
        try {
            if ($stmtSumReceived) { $stmtSumReceived->execute([$cisId]); $receivedCountTotal = (int)($stmtSumReceived->fetchColumn() ?: 0); }
            if ($stmtShipCount) { $stmtShipCount->execute([$cisId]); $shipmentsCount = (int)($stmtShipCount->fetchColumn() ?: 0); }
            if ($stmtParcelCount) { $stmtParcelCount->execute([$cisId]); $parcelsCount = (int)($stmtParcelCount->fetchColumn() ?: 0); }
            if ($stmtTrackCount) { $stmtTrackCount->execute([$cisId]); $trackingCount = (int)($stmtTrackCount->fetchColumn() ?: 0); } else { $trackingCount = null; }
            if ($stmtLatestShip) { $stmtLatestShip->execute([$cisId]); $ls = $stmtLatestShip->fetch(PDO::FETCH_ASSOC) ?: null; if ($ls) { $latestShipCarrier = $ls['carrier'] ?? null; $latestShipAt = $ls['created_at'] ?? null; } }
            if ($stmtLatestReceipt) { $stmtLatestReceipt->execute([$cisId]); $latestReceiptAt = $stmtLatestReceipt->fetchColumn() ?: null; }
            if ($stmtLatestNote) { $stmtLatestNote->execute([$cisId]); $ln = $stmtLatestNote->fetch(PDO::FETCH_ASSOC) ?: null; if ($ln) { $latestNote = $ln['note'] ?? null; } }
            if ($row && $stmtUserById) {
                if (!empty($row['cis_user_id'])) { $stmtUserById->execute([$row['cis_user_id']]); $createdByName = $stmtUserById->fetchColumn() ?: null; }
                if (!empty($row['updated_by'])) { $stmtUserById->execute([$row['updated_by']]); $updatedByName = $stmtUserById->fetchColumn() ?: null; }
            }
        } catch (Throwable $e) { /* ignore */ }
    }

    // Age in hours from NZ now
    $ageHours = null;
    if ($createdNz) {
        try { $nowNz = new DateTime('now', new DateTimeZone('Pacific/Auckland')); $ageHours = round((strtotime($nowNz->format(DateTime::ATOM)) - strtotime($createdNz)) / 3600, 1); } catch (Throwable $e) { $ageHours = null; }
    }

    $progressPct = null;
    if (is_int($itemCountTotal) && $itemCountTotal > 0 && is_int($receivedCountTotal)) {
        $progressPct = round(($receivedCountTotal / max(1, $itemCountTotal)) * 100, 1);
    }

    $enriched[] = array_merge($r, [
        'consignment_number' => $cn,
        'from_outlet_id' => $srcId,
        'from_outlet_name' => $fromOutletName,
        'from_outlet_meta' => $fromOutletMeta,
        'to_outlet_id' => $dstId,
        'to_outlet_name' => $toOutletName,
        'to_outlet_meta' => $toOutletMeta,
        'created_at_nz' => $createdNz,
        'links' => [
            'cis_pack' => $cisPackUrl,
            'vend_console' => $vendConsoleUrl,
        ],
        'cis_internal_id' => $cisId,
        'cis_state' => $cisState,
        'item_count_total' => $itemCountTotal,
        'received_count_total' => $receivedCountTotal,
        'progress_receive_pct' => $progressPct,
        'shipments_count' => $shipmentsCount,
        'parcels_count' => $parcelsCount,
        'tracking_count' => $trackingCount,
        'latest_shipment_carrier' => $latestShipCarrier,
        'latest_shipment_at' => $latestShipAt,
        'latest_receipt_at' => $latestReceiptAt,
        'latest_note' => $latestNote,
        'created_by_name' => $createdByName,
        'updated_by_name' => $updatedByName,
        'age_hours_nz' => $ageHours,
        'packable' => (bool)$cisId,
        'display_title' => $cn,
    ]);
}

$rows = $enriched;

// If minimal requested, shrink each row to a compact set
if ($minimal && empty($selectFields)) {
    $selectFields = ['id','consignment_number','from_outlet_name','to_outlet_name','cis_internal_id','cis_state','created_at_nz','links'];
}

if (is_array($selectFields) && !empty($selectFields)) {
    $rows = array_map(function($r) use ($selectFields) {
        $out = [];
        foreach ($selectFields as $k) {
            // Support dot access for links.*
            if (str_contains($k, '.')) {
                [$p,$c] = explode('.', $k, 2);
                if (isset($r[$p]) && is_array($r[$p]) && array_key_exists($c, $r[$p])) {
                    $out[$p] = $out[$p] ?? [];
                    $out[$p][$c] = $r[$p][$c];
                }
            } elseif (array_key_exists($k, $r)) {
                $out[$k] = $r[$k];
            }
        }
        return $out;
    }, $rows);
}

// Build meta
$host = $_SERVER['HTTP_HOST'] ?? 'staff.vapeshed.co.nz';
$generatedAt = (new DateTime('now', new DateTimeZone('Pacific/Auckland')))->format(DateTime::ATOM);
$schemaUrl = '/modules/consignments/public/evc-schema.json';

if ($format === 'ndjson') {
    header('Content-Type: application/x-ndjson; charset=utf-8');
    // Prepend a header line with meta as a comment for bots that support it
    // Clients can ignore lines starting with #
    echo '# meta ' . json_encode([
        'version' => $apiVersion,
        'host' => $host,
        'generated_at' => $generatedAt,
        'timezone' => 'Pacific/Auckland',
        'correlation_id' => $correlationId,
        'schema' => $schemaUrl,
        'count' => count($rows),
        'limit' => $limit,
        'today_nz' => $todayOnly,
    ], JSON_UNESCAPED_SLASHES) . "\n";
    foreach ($rows as $row) {
        echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
    }
    exit;
}

echo json_encode([
    'success' => true,
    'version' => $apiVersion,
    'meta' => [
        'host' => $host,
        'generated_at' => $generatedAt,
        'timezone' => 'Pacific/Auckland',
        'correlation_id' => $correlationId,
        'schema' => $schemaUrl,
    ],
    'count' => count($rows),
    'limit' => $limit,
    'today_nz' => $todayOnly,
    'data' => $rows,
]);
exit;
