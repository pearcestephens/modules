<?php
/**
 * Theme API Automated Test Harness (Local Include Mode)
 * Simulates HTTP requests by setting superglobals and including script.
 * Run: php theme_api_harness.php
 */

// Establish document root for CLI
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = '/home/master/applications/jcepnzzkmj/public_html';
}

// Minimal HTTP simulation
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Use existing authentication logic from bootstrap (loaded by API include). Just ensure session vars exist.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['roles'] = ['design_admin'];

$apiFile = $_SERVER['DOCUMENT_ROOT'] . '/modules/base/templates/vape-ultra/api/theme-api.php';
$cid = bin2hex(random_bytes(6));

function invokeApi($action, $method = 'GET', $getParams = [], $postParams = []) {
    global $apiFile, $cid;
    // Clear previous state
    $_GET = [];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET['action'] = $action;
    $_GET['cid'] = $cid;
    foreach ($getParams as $k=>$v) { $_GET[$k] = $v; }
    foreach ($postParams as $k=>$v) { $_POST[$k] = $v; }
    ob_start();
    try {
        include $apiFile; // Script exits after echo (success/error) so output captured
    } catch (Throwable $e) {
        $out = json_encode(['success'=>false,'error'=>'Harness exception: '.$e->getMessage(),'meta'=>['cid'=>$cid]]);
        echo $out; // ensure something
    }
    $raw = ob_get_clean();
    $decoded = json_decode($raw,true);
    return [$raw,$decoded];
}

$report = [];
$timings = [];

function timed($label, $fn) { global $timings; $start = microtime(true); $res = $fn(); $timings[$label] = round((microtime(true)-$start)*1000,2); return $res; }

// 1. list
[$rawList,$resList] = timed('list', fn()=>invokeApi('list')); $report['list']=$resList;
// 2. save
$themePayload = [
    'name' => 'HarnessTest '.date('His'),
    'description' => 'Automated harness theme',
    'theme_data' => json_encode([
        'colors' => ['primary' => '#3366ff','accent' => '#ff3366','background' => '#0f172a','text' => '#ffffff'],
        'typography' => ['fontFamily'=>'Inter','fontSize'=>'14px','lineHeight'=>'1.6','letterSpacing'=>'0'],
        'layout' => ['borderRadius'=>'8px','spacingDensity'=>1.0,'shadowDepth'=>'medium']
    ])
];
[$rawSave,$resSave] = timed('save', fn()=>invokeApi('save','POST',[], $themePayload)); $report['save']=$resSave; $tid = $resSave['data']['theme_id'] ?? null;
// 3. load
if ($tid) { [$rawLoad,$resLoad] = timed('load', fn()=>invokeApi('load','GET',['theme_id'=>$tid])); $report['load']=$resLoad; }
// 4. generate
[$rawGen,$resGen] = timed('generate', fn()=>invokeApi('generate','POST',[], ['hue'=>210,'scheme'=>'triadic'])); $report['generate']=$resGen;
// 5. export
if ($tid) { [$rawExp,$resExp] = timed('export', fn()=>invokeApi('export','GET',['theme_id'=>$tid])); $report['export']=$resExp; }
// 6. set_active
if ($tid) { [$rawAct,$resAct] = timed('set_active', fn()=>invokeApi('set_active','POST',[], ['theme_id'=>$tid])); $report['set_active']=$resAct; }
// 7. get_active
[$rawActive,$resActive] = timed('get_active', fn()=>invokeApi('get_active')); $report['get_active']=$resActive;
// 8. list_packs
[$rawPacks,$resPacks] = timed('list_packs', fn()=>invokeApi('list_packs')); $report['list_packs']=$resPacks; $firstPack = $resPacks['data']['packs'][0]['slug'] ?? null;
// 9. load_pack
if ($firstPack) { [$rawLP,$resLP] = timed('load_pack', fn()=>invokeApi('load_pack','GET',['slug'=>$firstPack])); $report['load_pack']=$resLP; }
// 10. switch_runtime (requires design_admin role already set)
if ($firstPack) { [$rawSR,$resSR] = timed('switch_runtime', fn()=>invokeApi('switch_runtime','POST',[], ['slug'=>$firstPack])); $report['switch_runtime']=$resSR; }

// 11. Import (use export payload) + integrity mismatch test
if (!empty($report['export']['data']['export'])) {
    $exportPayload = $report['export']['data']['export'];
    $validImport = $exportPayload; // intact integrity
    [$rawImport,$resImport] = timed('import_valid', fn()=>invokeApi('import','POST',[], $validImport));
    $report['import_valid'] = $resImport;
    // Tamper name to break hash
    $tampered = $exportPayload; $tampered['name'] = $tampered['name'].' X';
    [$rawImportBad,$resImportBad] = timed('import_bad', fn()=>invokeApi('import','POST',[], $tampered));
    $report['import_bad'] = $resImportBad;
}

// Assertions
$failures = [];
foreach ($report as $key=>$payload) {
    if (empty($payload) || !isset($payload['success'])) { $failures[] = "$key: no response"; continue; }
    if ($key === 'import_bad') {
        // Expect failure for integrity mismatch if integrity present
        if ($payload['success'] === true) { $failures[] = 'import_bad: expected failure on tampered integrity'; }
        continue;
    }
    if ($payload['success'] !== true && !in_array($key,['import_bad'])) {
        $failures[] = "$key: failed (".($payload['error'] ?? 'unknown').")";
    }
}

header('Content-Type: application/json');
echo json_encode([
    'summary' => array_map(fn($v)=> $v['success'] ?? false, $report),
    'detail' => $report,
    'timings_ms' => $timings,
    'failures' => $failures,
    'cid' => $cid
], JSON_PRETTY_PRINT);

if (!empty($failures)) { exit(1); }
exit(0);

header('Content-Type: application/json');
echo json_encode([
    'summary' => array_map(fn($v)=> $v['success'] ?? false, $report),
    'detail' => $report,
    'cid' => $cid
], JSON_PRETTY_PRINT);
