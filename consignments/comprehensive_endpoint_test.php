<?php
declare(strict_types=1);

/**
 * CIS Consignments — Comprehensive Endpoint & Compliance Tester
 * Generates: HTML page + write-through Markdown report (TEST_REPORT.md)
 *
 * - Uses BOT_BYPASS_AUTH to hit endpoints without auth friction.
 * - Tests multiple endpoints when files exist (auto-skips missing ones).
 * - Validates critical DB schema/enum patterns (from your “authoritative” doc).
 * - Summarizes pass/fail by endpoint + by transfer + overall success rate.
 *
 * @package CIS\Consignments\Testing
 * @version 1.1.0
 * @updated 2025-10-13
 */

@date_default_timezone_set('Pacific/Auckland');

// ---------- Bot bypass ----------
$_ENV['BOT_BYPASS_AUTH']   = '1';
$_SERVER['BOT_BYPASS_AUTH'] = '1';
$_GET['bot']               = 'true';

require_once __DIR__ . '/module_bootstrap.php';

use Transfers\Lib\Db;

// ---------- Config ----------
const BASE_URL     = 'https://staff.vapeshed.co.nz';
const REPORT_PATH  = __DIR__ . '/TEST_REPORT.md';
const MAX_ITEMS    = 5;   // limit items per payload to keep autosave lean
const CURL_TIMEOUT = 30;

// Prefer explicit sample IDs if you have them; fallback to discover recent ones
$providedTransferIds = [26914, 26913, 26912, 26911, 26910, 26908, 26907];

// ---------- HTML head ----------
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>CIS Endpoint & Compliance Tester</title>
<style>
body{font-family:system-ui,Arial,sans-serif;margin:22px;}
h1{margin:0 0 10px 0}
small{color:#666}
.test-section{border:1px solid #ddd;margin:20px 0;padding:15px;border-radius:6px}
.success{background:#d4edda;border-color:#c3e6cb;color:#155724}
.error{background:#f8d7da;border-color:#f5c6cb;color:#721c24}
.warning{background:#fff3cd;border-color:#ffeaa7;color:#856404}
.info{background:#d1ecf1;border-color:#bee5eb;color:#0c5460}
pre{background:#f8f9fa;padding:10px;border-radius:4px;overflow:auto}
.endpoint-test{margin:10px 0;padding:10px;border-left:4px solid #007bff}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #ccc;padding:6px 8px;text-align:left}
th{background:#f7f7f7}
details{margin-top:8px}
.kv{font-family:ui-monospace,Consolas,monospace}
</style></head><body>";

echo "<h1>🧪 CIS Consignments — Endpoint & Compliance Tester</h1>";
echo "<p class='kv'><strong>Started:</strong> " . date('Y-m-d H:i:s') . " &nbsp;|&nbsp; <strong>BOT_BYPASS_AUTH:</strong> " . ($_ENV['BOT_BYPASS_AUTH'] ?? 'NOT SET') . "</p>";

// ============================================================================
// Transfer discovery (prefer provided list, fallback to latest OPEN-ish)
// ============================================================================
$transferIds = [];
foreach ($providedTransferIds as $id) {
    if (checkTransferExists($id)) {
        $transferIds[] = (int)$id;
    }
}
if (empty($transferIds)) {
    $transferIds = discoverRecentTransfers(10); // last 10 “in-flight” by your state rules
}

// ============================================================================
// Endpoints under test (auto-skip if file not found)
// ============================================================================
$endpoints = [
    // Unified autosave (pack/receive)
    'autosave' => [
        'path' => '/modules/consignments/api/autosave.php',
        'method' => 'POST',
        'type' => 'unified',
        'requires_items' => true
    ],
    // Optional endpoints (only if files exist — we’ll check)
    'autosave_load' => [
        'path' => '/modules/consignments/api/receive_autosave.php',
        'method' => 'POST',
        'type' => 'load',
        'requires_items' => false,
        'payload_action' => 'load'
    ],
    'pack_submit' => [
        'path' => '/modules/consignments/api/pack_submit.php',
        'method' => 'POST',
        'type' => 'submit',
        'requires_items' => true
    ],
    'receive_submit' => [
        'path' => '/modules/consignments/api/receive_submit.php',
        'method' => 'POST',
        'type' => 'submit',
        'requires_items' => true
    ],
    'add_line' => [
        'path' => '/modules/consignments/api/add_line.php',
        'method' => 'POST',
        'type' => 'mutate',
        'requires_items' => true
    ],
    'remove_line' => [
        'path' => '/modules/consignments/api/remove_line.php',
        'method' => 'POST',
        'type' => 'mutate',
        'requires_items' => true
    ],
    'update_line_qty' => [
        'path' => '/modules/consignments/api/update_line_qty.php',
        'method' => 'POST',
        'type' => 'mutate',
        'requires_items' => true
    ],
    'pack_lock' => [
        'path' => '/modules/consignments/api/pack_lock.php',
        'method' => 'POST',
        'type' => 'lock',
        'requires_items' => false
    ],
    'search_products' => [
        'path' => '/modules/consignments/api/search_products.php',
        'method' => 'POST',
        'type' => 'search',
        'requires_items' => false
    ],
];

// Filter endpoints by file existence so we don’t “fail” on optional files
$endpoints = array_filter($endpoints, function ($cfg) {
    return endpointFileExists($cfg['path']);
});

// ============================================================================
// Run tests per transfer
// ============================================================================
$results      = [];
$totalTests   = 0;
$passedTests  = 0;

foreach ($transferIds as $transferId) {
    echo "<div class='test-section info'><h2>🎯 Transfer ID: {$transferId}</h2>";

    $t = getTransferDetails($transferId);
    if (!$t) {
        echo "<div class='warning'>⚠️ Transfer not found or not active. Skipping…</div></div>";
        continue;
    }

    echo "<div class='kv'>
        ID: {$t['id']} &nbsp;|&nbsp; Category: " . ($t['transfer_category'] ?? 'unknown') .
        " &nbsp;|&nbsp; Mode: " . ($t['transfer_mode'] ?? 'GENERAL') .
        " &nbsp;|&nbsp; State: " . ($t['state'] ?? 'unknown') .
        "<br>From: " . ($t['from_outlet_name'] ?? 'n/a') . " → To: " . ($t['to_outlet_name'] ?? 'n/a') .
    "</div>";

    $items = getTransferItems($transferId, MAX_ITEMS);
    echo "<p><strong>Items detected:</strong> " . count($items) . "</p>";

    foreach ($endpoints as $name => $cfg) {
        echo "<div class='endpoint-test'><h4>Endpoint: {$name}</h4>";

        if ($cfg['requires_items'] && empty($items)) {
            echo "<div class='warning'>⚠️ Requires items; none found. Skipping.</div></div>";
            $results[] = compactResult($transferId, $name, false, 'No items available for item-requiring endpoint');
            $totalTests++;
            continue;
        }

        $payload = buildTestPayload($name, $cfg, $transferId, $t, $items);
        if (!$payload) {
            echo "<div class='error'>❌ Could not build payload.</div></div>";
            $results[] = compactResult($transferId, $name, false, 'Payload build failed');
            $totalTests++;
            continue;
        }

        $r = hitEndpoint($cfg['path'], $payload);
        $totalTests++;

        if ($r['success']) {
            $passedTests++;
            echo "<div class='success'>✅ {$r['message']}</div>";
        } else {
            echo "<div class='error'>❌ {$r['message']}</div>";
        }

        if (!empty($r['response'])) {
            echo "<details><summary>Response</summary><pre>" . htmlspecialchars(json_encode($r['response'], JSON_PRETTY_PRINT)) . "</pre></details>";
        }
        if (!empty($r['error'])) {
            echo "<details><summary>Error</summary><pre>" . htmlspecialchars($r['error']) . "</pre></details>";
        }

        echo "</div>"; // endpoint-test
        $results[] = [
            'transfer_id' => $transferId,
            'endpoint'    => $name,
            'success'     => $r['success'],
            'message'     => $r['message'],
            'http_code'   => $r['http_code'] ?? null
        ];

        usleep(120000); // 120ms between hits
    }

    echo "</div>"; // test-section
}

// ============================================================================
// Schema & Query-Pattern Compliance (from your authoritative rules)
// ============================================================================
$schemaChecks = runSchemaChecks();

// ============================================================================
// Summaries (HTML)
// ============================================================================
$successRate = round(($passedTests / max(1, $totalTests)) * 100, 2);

echo "<div class='test-section'>
    <h2>📊 Test Summary</h2>
    <p><strong>Total Tests:</strong> {$totalTests}</p>
    <p><strong>Passed:</strong> {$passedTests}</p>
    <p><strong>Failed:</strong> " . ($totalTests - $passedTests) . "</p>
    <p><strong>Success Rate:</strong> {$successRate}%</p>
</div>";

echo "<div class='test-section'><h2>📋 Detailed Results</h2>";
echo "<table><tr><th>Transfer</th><th>Endpoint</th><th>Status</th><th>Message</th><th>HTTP</th></tr>";
foreach ($results as $r) {
    $ok = $r['success'] ? '✅' : '❌';
    echo "<tr><td>{$r['transfer_id']}</td><td>{$r['endpoint']}</td><td>{$ok}</td><td>" .
         htmlspecialchars($r['message']) . "</td><td>" . htmlspecialchars((string)($r['http_code'] ?? '')) . "</td></tr>";
}
echo "</table></div>";

// Compliance matrix
echo "<div class='test-section'><h2>✅ Schema & Query Compliance</h2>";
echo "<table><tr><th>Check</th><th>Status</th><th>Details</th></tr>";
foreach ($schemaChecks as $check) {
    $ok = $check['pass'] ? '✅' : '❌';
    echo "<tr><td>" . htmlspecialchars($check['name']) . "</td><td>{$ok}</td><td>" .
         htmlspecialchars($check['details']) . "</td></tr>";
}
echo "</table></div>";

echo "<p class='kv'><strong>Completed:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p>📄 A Markdown report has been saved to <code>" . basename(REPORT_PATH) . "</code>.</p>";
echo "</body></html>";

// ============================================================================
// Write-through Markdown report for bots/devs
// ============================================================================
writeMarkdownReport(REPORT_PATH, $results, $schemaChecks, [
    'started_at'   => date('Y-m-d H:i:s'),
    'success_rate' => $successRate,
    'total'        => $totalTests,
    'passed'       => $passedTests,
    'failed'       => $totalTests - $passedTests,
    'endpoints'    => array_keys($endpoints),
    'transfers'    => $transferIds,
    'bot_bypass'   => $_ENV['BOT_BYPASS_AUTH'] ?? 'NOT SET',
]);

// ============================================================================
// Helpers
// ============================================================================

function endpointFileExists(string $path): bool {
    $doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $a   = $doc . $path;
    $b   = str_replace('/modules/consignments/modules/consignments', '/modules/consignments', __DIR__ . $path);
    return file_exists($a) || file_exists($b);
}

function compactResult(int $tid, string $endpoint, bool $ok, string $msg): array {
    return ['transfer_id' => $tid, 'endpoint' => $endpoint, 'success' => $ok, 'message' => $msg];
}

function discoverRecentTransfers(int $limit = 10): array {
    $ids = [];
    $sql = "
        SELECT id
        FROM transfers
        WHERE state IN ('OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL')
          AND deleted_at IS NULL
          AND (deleted_by IS NULL OR deleted_by = 0)
        ORDER BY updated_at DESC
        LIMIT ?
    ";
    $stmt = Db::prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['id'];
    }
    return $ids;
}

function checkTransferExists(int $transferId): bool {
    try {
        $stmt = Db::prepare("SELECT COUNT(*) AS c FROM transfers WHERE id = ? AND deleted_at IS NULL AND (deleted_by IS NULL OR deleted_by = 0)");
        $stmt->bind_param('i', $transferId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return ((int)($res['c'] ?? 0)) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function getTransferDetails(int $transferId): ?array {
    try {
        $stmt = Db::prepare("
            SELECT t.*,
                   o_from.name AS from_outlet_name,
                   o_to.name   AS to_outlet_name
            FROM transfers t
            LEFT JOIN vend_outlets o_from ON t.outlet_from = o_from.id AND o_from.deleted_at = '0000-00-00 00:00:00'
            LEFT JOIN vend_outlets o_to   ON t.outlet_to   = o_to.id   AND o_to.deleted_at   = '0000-00-00 00:00:00'
            WHERE t.id = ? AND t.deleted_at IS NULL AND (t.deleted_by IS NULL OR t.deleted_by = 0)
        ");
        $stmt->bind_param('i', $transferId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function getTransferItems(int $transferId, int $limit = 5): array {
    try {
        $stmt = Db::prepare("
            SELECT ti.*, p.name AS product_name, p.sku
            FROM transfer_items ti
            INNER JOIN vend_products p
                ON ti.product_id = p.id
               AND p.is_active = 1
               AND p.is_deleted = 0
               AND p.deleted_at = '0000-00-00 00:00:00'
            WHERE ti.transfer_id = ?
              AND ti.deleted_by IS NULL
            LIMIT ?
        ");
        $stmt->bind_param('ii', $transferId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
        return $items;
    } catch (Throwable $e) {
        return [];
    }
}

function buildTestPayload(string $endpoint, array $cfg, int $transferId, array $t, array $items): array {
    $base = [
        'transfer_id'   => $transferId,
        'transfer_mode' => $t['transfer_mode'] ?? 'GENERAL',
        'timestamp'     => date('Y-m-d H:i:s'),
        'bot_test'      => true
    ];

    switch ($endpoint) {
        case 'autosave':
            $lines = [];
            foreach ($items as $it) {
                $rq = (float)($it['qty_requested'] ?? 1);
                $rc = max(0.0, round($rq * 0.8, 3));
                $lines[] = [
                    'item_id'      => (int)$it['id'],
                    'product_id'   => (int)$it['product_id'],
                    'qty_requested'=> $rq,
                    'qty_received' => $rc,
                    'weight_grams' => 100.0
                ];
            }
            $base['items']     = $lines;
            $base['action']    = 'autosave';
            $base['is_manual'] = false;
            return $base;

        case 'autosave_load':
            $base['action'] = 'load';
            return $base;

        case 'receive_submit':
        case 'pack_submit':
            $lines = [];
            foreach ($items as $it) {
                $rq = (float)($it['qty_requested'] ?? 1);
                $pz = max(0.0, round($rq * 0.9, 3));
                $lines[] = [
                    'item_id'      => (int)$it['id'],
                    'product_id'   => (int)$it['product_id'],
                    'qty_requested'=> $rq,
                    'qty_received' => $pz,
                    'qty_packed'   => $pz,
                    'weight_grams' => 100.0
                ];
            }
            $base['items']         = $lines;
            $base['submitter_name']= 'Test User';
            $base['notes']         = 'Automated test submission';
            return $base;

        case 'add_line':
            $pid = (int)($items[0]['product_id'] ?? 0);
            if ($pid <= 0) return [];
            $base['product_id'] = $pid;
            $base['qty']        = 1;
            return $base;

        case 'remove_line':
            $iid = (int)($items[0]['id'] ?? 0);
            if ($iid <= 0) return [];
            $base['item_id'] = $iid;
            return $base;

        case 'update_line_qty':
            $iid = (int)($items[0]['id'] ?? 0);
            if ($iid <= 0) return [];
            $base['item_id'] = $iid;
            $base['qty']     = 2;
            return $base;

        case 'pack_lock':
            $base['action'] = 'lock';
            return $base;

        case 'search_products':
            $base['query'] = 'test';
            $base['limit'] = 10;
            return $base;
    }

    return $base; // default passthrough
}

function hitEndpoint(string $path, array $payload): array {
    $url = BASE_URL . $path . '?bot=true';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Requested-With: XMLHttpRequest',
            'User-Agent: CIS-Test-Bot/1.1',
            'X-Bot-Bypass: 1'
        ],
        CURLOPT_TIMEOUT        => CURL_TIMEOUT,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_COOKIE         => 'bot_bypass=1'
    ]);

    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return [
            'success' => false,
            'message' => "cURL error: {$err}",
            'error'   => $err,
            'http_code' => $code
        ];
    }

    $json = json_decode((string)$raw, true);
    if ($code === 200) {
        if (is_array($json) && !empty($json['success'])) {
            return ['success' => true, 'message' => 'HTTP 200 — success', 'response' => $json, 'http_code' => 200];
        }
        return [
            'success' => false,
            'message' => 'HTTP 200 but API indicates failure',
            'response'=> $json,
            'error'   => is_array($json) ? ($json['message'] ?? 'Unknown error') : 'Non-JSON response',
            'http_code' => 200
        ];
    }

    return [
        'success' => false,
        'message' => "HTTP {$code}",
        'response'=> $json,
        'error'   => 'Non-200 response',
        'http_code' => $code
    ];
}

// ---------- Schema checks (Information Schema) ----------

function runSchemaChecks(): array {
    $checks = [];

    // helpers
    $hasCol = function(string $table, string $col): bool {
        $sql = "SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $stmt = Db::prepare($sql);
        $stmt->bind_param('ss', $table, $col);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return ((int)($res['c'] ?? 0)) > 0;
    };

    $enumVals = function(string $table, string $col): array {
        $sql = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $stmt = Db::prepare($sql);
        $stmt->bind_param('ss', $table, $col);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return [];
        $type = (string)$row['COLUMN_TYPE']; // e.g., enum('A','B')
        if (stripos($type, "enum(") !== 0) return [];
        $inside = substr($type, 5, -1);
        $parts = array_map(function($s){ return trim($s, " '"); }, explode(",", $inside));
        return $parts;
    };

    // transfers
    $checks[] = namedCheck('transfers.outlet_from exists', $hasCol('transfers','outlet_from'));
    $checks[] = namedCheck('transfers.outlet_to exists',   $hasCol('transfers','outlet_to'));
    $checks[] = namedCheck('transfers.state exists',        $hasCol('transfers','state'));
    $checks[] = namedCheck('transfers.creation_method exists', $hasCol('transfers','creation_method'));
    // discourage legacy alias columns
    $checks[] = namedCheck('NO from_outlet_id legacy column',   !$hasCol('transfers','from_outlet_id'));
    $checks[] = namedCheck('NO to_outlet_id legacy column',     !$hasCol('transfers','to_outlet_id'));

    // state enum values
    $expectedState = ['DRAFT','OPEN','PACKING','PACKAGED','SENT','RECEIVING','PARTIAL','RECEIVED','CLOSED','CANCELLED','ARCHIVED'];
    $actualState   = $enumVals('transfers','state');
    $missing       = array_diff($expectedState, $actualState);
    $checks[] = [
        'name'   => 'transfers.state enum values',
        'pass'   => empty($missing),
        'details'=> empty($actualState) ? 'No enum detected' : 'Actual: [' . implode(', ', $actualState) . ']'
    ];

    // creation_method enum
    $expectedCM = ['MANUAL','AUTOMATED'];
    $actualCM   = $enumVals('transfers','creation_method');
    $missCM     = array_diff($expectedCM, $actualCM);
    $checks[] = [
        'name'   => 'transfers.creation_method enum values',
        'pass'   => empty($missCM),
        'details'=> empty($actualCM) ? 'No enum detected' : 'Actual: [' . implode(', ', $actualCM) . ']'
    ];

    // transfer_items deletion rule
    $checks[] = namedCheck('transfer_items.deleted_by exists', $hasCol('transfer_items','deleted_by'));

    // vend_outlets deleted_at rule
    $checks[] = namedCheck('vend_outlets.deleted_at exists', $hasCol('vend_outlets','deleted_at'));

    // vend_products status trio
    $checks[] = namedCheck('vend_products.is_active exists',  $hasCol('vend_products','is_active'));
    $checks[] = namedCheck('vend_products.is_deleted exists', $hasCol('vend_products','is_deleted'));
    $checks[] = namedCheck('vend_products.deleted_at exists', $hasCol('vend_products','deleted_at'));

    // users staff_active
    $checks[] = namedCheck('users.staff_active exists', $hasCol('users','staff_active'));

    return $checks;
}

function namedCheck(string $name, bool $pass, string $details = ''): array {
    return ['name' => $name, 'pass' => $pass, 'details' => $details];
}

// ---------- Markdown writer ----------
function writeMarkdownReport(string $path, array $results, array $schemaChecks, array $meta): void {
    // Aggregate by endpoint
    $byEndpoint = [];
    foreach ($results as $r) {
        $e = $r['endpoint'];
        if (!isset($byEndpoint[$e])) $byEndpoint[$e] = ['total'=>0,'pass'=>0,'fail'=>0];
        $byEndpoint[$e]['total']++;
        $r['success'] ? $byEndpoint[$e]['pass']++ : $byEndpoint[$e]['fail']++;
    }

    $md  = "# CIS Consignments — Endpoint & Compliance Report\n\n";
    $md .= "- **Generated:** {$meta['started_at']}\n";
    $md .= "- **BOT_BYPASS_AUTH:** {$meta['bot_bypass']}\n";
    $md .= "- **Transfers tested:** " . implode(', ', $meta['transfers']) . "\n";
    $md .= "- **Endpoints covered:** " . implode(', ', $meta['endpoints']) . "\n\n";

    $md .= "## Summary\n\n";
    $md .= "| Metric | Value |\n|---|---:|\n";
    $md .= "| Total tests | {$meta['total']} |\n";
    $md .= "| Passed | {$meta['passed']} |\n";
    $md .= "| Failed | {$meta['failed']} |\n";
    $md .= "| Success rate | {$meta['success_rate']}% |\n\n";

    $md .= "## Results by Endpoint\n\n";
    $md .= "| Endpoint | Total | Passed | Failed | Pass % |\n|---|---:|---:|---:|---:|\n";
    foreach ($byEndpoint as $ep => $agg) {
        $pct = round(($agg['pass'] / max(1,$agg['total'])) * 100, 2);
        $md .= "| `{$ep}` | {$agg['total']} | {$agg['pass']} | {$agg['fail']} | {$pct}% |\n";
    }
    $md .= "\n";

    $md .= "## Detailed Results\n\n";
    $md .= "| Transfer | Endpoint | Status | Message | HTTP |\n|---:|---|---:|---|---:|\n";
    foreach ($results as $r) {
        $ok  = $r['success'] ? '✅' : '❌';
        $msg = str_replace("\n", " ", (string)$r['message']);
        $md .= "| {$r['transfer_id']} | `{$r['endpoint']}` | {$ok} | {$msg} | " . ($r['http_code'] ?? '') . " |\n";
    }
    $md .= "\n";

    $md .= "## Schema & Query-Pattern Compliance\n\n";
    $md .= "| Check | Status | Details |\n|---|:---:|---|\n";
    foreach ($schemaChecks as $c) {
        $ok = $c['pass'] ? '✅' : '❌';
        $md .= "| {$c['name']} | {$ok} | " . ($c['details'] ?: '') . " |\n";
    }
    $md .= "\n";

    $md .= "> **Baseline rules applied**: `outlet_from/outlet_to`, `state` enum, `transfer_items.deleted_by IS NULL`, `vend_outlets.deleted_at='0000-00-00 00:00:00'`, `vend_products is_active=1 AND is_deleted=0 AND deleted_at='0000-00-00 00:00:00'`, `users.staff_active=1`.\n";

    @file_put_contents($path, $md);
}
