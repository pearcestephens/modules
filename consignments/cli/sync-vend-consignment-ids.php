#!/usr/bin/env php
<?php
declare(strict_types=1);

// Sync missing vend_consignment_id into queue_consignments by matching recent Vend consignments
// Usage: php sync-vend-consignment-ids.php [--days=60] [--apply]

require_once __DIR__ . '/../bootstrap.php';

function out(string $s): void { fwrite(STDOUT, $s . PHP_EOL); }
function err(string $s): void { fwrite(STDERR, $s . PHP_EOL); }

// Parse args
$days = 60; $apply = false;
foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d{1,4})$/', $arg, $m)) { $days = (int)$m[1]; }
    if ($arg === '--apply') { $apply = true; }
}

// Resolve config
$baseUrl = getenv('LIGHTSPEED_BASE_URL') ?: (function_exists('cis_config_get') ? (cis_config_get('lightspeed_base_url', false) ?: 'https://vapeshed.vendhq.com/api/2.0') : 'https://vapeshed.vendhq.com/api/2.0');
$token   = getenv('LIGHTSPEED_API_TOKEN') ?: (function_exists('cis_vend_access_token') ? (cis_vend_access_token(true) ?? '') : '');
if ($token === '') {
    err('ERROR: No Lightspeed API token configured (env LIGHTSPEED_API_TOKEN or config vend_access_token).');
    exit(1);
}

// Simple HTTP GET helper
function httpGet(string $url, string $token): array {
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
    if ($status >= 200 && $status < 300 && $body) {
        $json = json_decode($body, true);
        return [$status, $json, null];
    }
    return [$status, null, $err ?: ('HTTP ' . $status)];
}

// Fetch recent vend consignments
$since = (new DateTimeImmutable('-' . $days . ' days'))->format(DateTime::ATOM);
$url = rtrim($baseUrl, '/') . '/consignments?per_page=200&updated_since=' . rawurlencode($since);
out("Fetching Vend consignments since $since ...");
[$status, $json, $httpErr] = httpGet($url, $token);
if (!$json || !isset($json['data'])) {
    err('ERROR: Failed to fetch consignments: ' . ($httpErr ?? 'no data'));
    exit(1);
}
$vendList = $json['data'];
out('Fetched ' . count($vendList) . ' consignments from Vend.');

// Build a quick index by composite keys for matching
$indexByKey = [];
foreach ($vendList as $c) {
    $id    = (string)($c['id'] ?? '');
    $name  = trim((string)($c['reference'] ?? $c['name'] ?? ''));
    $src   = (string)($c['source_outlet_id'] ?? $c['outlet_id'] ?? '');
    $dst   = (string)($c['destination_outlet_id'] ?? '');
    $count = (int)($c['item_count'] ?? 0);
    $keyName = strtolower($name);
    $composites = [];
    if ($keyName !== '') $composites[] = 'name:' . $keyName;
    if ($src !== '' && $dst !== '') $composites[] = 'route:' . $src . '>' . $dst;
    if ($count > 0) $composites[] = 'count:' . $count;
    foreach ($composites as $ck) {
        $indexByKey[$ck] = $indexByKey[$ck] ?? [];
        $indexByKey[$ck][] = ['id'=>$id,'name'=>$name,'src'=>$src,'dst'=>$dst,'count'=>$count];
    }
}

// Query DB for missing vend ids
$pdo = \CIS\Base\Database::pdo();
$stmt = $pdo->query("SELECT id, name, source_outlet_id, destination_outlet_id, item_count FROM queue_consignments WHERE (vend_consignment_id IS NULL OR vend_consignment_id='') LIMIT 500");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
out('Missing vend_consignment_id rows: ' . count($rows));

$updated = 0; $skipped = 0; $ambiguous = 0;
foreach ($rows as $r) {
    $id = (int)$r['id'];
    $name = trim((string)$r['name']);
    $src = (string)$r['source_outlet_id'];
    $dst = (string)$r['destination_outlet_id'];
    $count = (int)$r['item_count'];

    $candidates = [];
    $keys = [];
    if ($name !== '') $keys[] = 'name:' . strtolower($name);
    if ($src !== '' && $dst !== '') $keys[] = 'route:' . $src . '>' . $dst;
    if ($count > 0) $keys[] = 'count:' . $count;

    foreach ($keys as $k) {
        if (isset($indexByKey[$k])) {
            $candidates = array_merge($candidates, $indexByKey[$k]);
        }
    }
    // Deduplicate candidates by vend id
    $byId = [];
    foreach ($candidates as $c) { $byId[$c['id']] = $c; }
    $candidates = array_values($byId);

    if (count($candidates) === 1) {
        $vendId = $candidates[0]['id'];
        out("[MATCH] qc.id=$id → vend.id=$vendId name='{$candidates[0]['name']}'");
        if ($apply) {
            $up = $pdo->prepare("UPDATE queue_consignments SET vend_consignment_id = ?, updated_at = NOW() WHERE id = ?");
            $up->execute([$vendId, $id]);
            $updated++;
        }
    } elseif (count($candidates) === 0) {
        out("[SKIP] qc.id=$id no matches (name='$name', route=$src>$dst, count=$count)");
        $skipped++;
    } else {
        out("[AMBIG] qc.id=$id multiple matches (" . count($candidates) . ") — name='$name'");
        $ambiguous++;
    }
}

out('--- Summary ---');
out('Updated: ' . $updated . ($apply ? '' : ' (dry-run)'));
out('Skipped: ' . $skipped);
out('Ambiguous: ' . $ambiguous);
out('Done.');
