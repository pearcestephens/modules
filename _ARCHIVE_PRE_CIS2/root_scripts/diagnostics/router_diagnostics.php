<?php
declare(strict_types=1);
$cid = bin2hex(random_bytes(8));
header('X-Correlation-ID: '.$cid);
header('X-CIS-Modules-Routed: diagnostics');

$core = is_file(__DIR__.'/core/bootstrap.php');
if ($core) require_once __DIR__.'/core/bootstrap.php';
$dbOk = false; $auth = false; $userId = null; $pdoErr = null;
try { if (function_exists('db')) { $pdo = db(); $dbOk = ($pdo instanceof PDO); } } catch (Throwable $e) { $pdoErr = $e->getMessage(); }
if (function_exists('isAuthenticated')) { $auth = isAuthenticated(); }
if (function_exists('getUserId')) { $userId = getUserId(); }
$rewrite = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'],'/modules/') !== false);

$report = [
  'routed' => true,
  'cid' => $cid,
  'core_loaded' => $core,
  'db_ok' => $dbOk,
  'db_error' => $pdoErr,
  'auth' => $auth,
  'user_id' => $userId,
  'rewrite_suspected' => $rewrite,
  'server' => [ 'host'=>($_SERVER['HTTP_HOST']??null), 'uri'=>($_SERVER['REQUEST_URI']??null) ]
];

if ((isset($_GET['json']) && $_GET['json']=='1') || (stripos($_SERVER['HTTP_ACCEPT']??'','application/json')!==false)) {
  header('Content-Type: application/json'); echo json_encode($report, JSON_UNESCAPED_SLASHES); exit; }

?><!DOCTYPE html><html><head><meta charset="utf-8"><title>CIS Modules Router Diagnostics</title>
<style>body{font-family:Arial;margin:30px;background:#0b1020;color:#e5e7eb} .card{background:#111827;border:1px solid #374151;border-radius:8px;padding:18px;max-width:800px} .ok{color:#10b981} .bad{color:#ef4444} pre{background:#1f2937;padding:10px;border-radius:6px;overflow:auto}</style>
</head><body><div class="card">
<h2>Router Diagnostics <span style="font-size:12px;opacity:.8">CID: <?=htmlspecialchars($cid)?></span></h2>
<ul>
  <li>CORE Loaded: <strong class="<?= $core?'ok':'bad'?>"><?=(int)$core?></strong></li>
  <li>DB OK: <strong class="<?= $dbOk?'ok':'bad'?>"><?=(int)$dbOk?></strong> <?= $pdoErr?('<em>'.htmlspecialchars($pdoErr).'</em>'):'' ?></li>
  <li>Authenticated: <strong class="<?= $auth?'ok':'bad'?>"><?=(int)$auth?></strong> User ID: <?=htmlspecialchars((string)$userId)?></li>
  <li>Rewrite Suspected: <strong><?=(int)$rewrite?></strong></li>
  <li>Host/URI: <code><?=htmlspecialchars(($_SERVER['HTTP_HOST']??'').($_SERVER['REQUEST_URI']??''))?></code></li>
  <li>Logs: <code>/modules/base/_logs/</code></li>
  <li>Tip: Look for header <code>X-CIS-Modules-Routed</code> to confirm forced routing.</li>
  <li>API tip: Append <code>?json=1</code> to get JSON diagnostics.</li>
  <li>Login: <code>/modules/core/login.php</code></li>
</ul>
<h3>Raw</h3>
<pre><?=htmlspecialchars(json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))?></pre>
</div></body></html>
