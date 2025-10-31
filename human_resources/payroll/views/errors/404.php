<?php
/**
 * Payroll Module - 404 Not Found Page
 * Matches CIS global error handler style from bootstrap.php
 * Modified with blue theme for 404 errors
 */

// Get error details if available
$type = '404 Not Found';
$msg = $_GET['msg'] ?? 'The requested page or resource could not be found.';
$route = $_GET['route'] ?? ($_SERVER['REQUEST_URI'] ?? '/');
$file = __FILE__;
$line = 0;

// Format file path (shortened)
$short = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);

// Memory usage
$mem = number_format(memory_get_usage(true)/1024/1024, 2) . ' MB';
$peak = number_format(memory_get_peak_usage(true)/1024/1024, 2) . ' MB';
?>
<!doctype html><html><head><meta charset="utf-8"><title><?=htmlspecialchars($type)?></title>
<style>
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;background:#f5f5f7;color:#1d1d1f;padding:20px}
    .card{max-width:1100px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);overflow:hidden}
    .hd{background:linear-gradient(135deg,#2563eb,#1e40af);color:#fff;padding:16px 20px}
    .sec{padding:18px 20px;border-top:1px solid #eee}
    .mono{font:12px/1.4 ui-monospace,Menlo,Monaco,Consolas,monospace;background:#eff6ff;color:#1e40af;padding:10px;border-radius:6px}
    .kv{display:grid;grid-template-columns:120px 1fr;gap:8px;margin-top:8px}
    .back-link{margin-top:16px;padding:10px 16px;background:#eff6ff;border-radius:6px;display:inline-block;color:#1e40af;text-decoration:none;font-size:14px}
    .back-link:hover{background:#dbeafe}
</style></head><body><div class="card">
<div class="hd"><strong>Page Not Found</strong><div style="opacity:.9;font-size:12px"><?=date('Y-m-d H:i:s')?> • <?=htmlspecialchars($route)?></div></div>
<div class="sec">
    <div class="kv"><div>Error Code</div><div><?=htmlspecialchars($type)?></div>
    <div>Message</div><div class="mono"><?=htmlspecialchars($msg)?></div>
    <div>Requested Route</div><div><?=htmlspecialchars($route)?></div>
    <div>File</div><div><?=htmlspecialchars($short)?></div>
    <div>Memory</div><div><?=$mem?> (peak <?=$peak?>)</div></div>

    <div style="margin-top:20px">
        <a href="/modules/human_resources/payroll/" class="back-link">← Back to Payroll Dashboard</a>
    </div>
</div>
</div></body></html>
