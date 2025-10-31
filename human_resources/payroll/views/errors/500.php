<?php
/**
 * Payroll Module - 500 Internal Server Error Page
 * Matches CIS global error handler style from bootstrap.php
 */

// Get error details if available
$type = '500 Internal Server Error';
$msg = $_GET['msg'] ?? 'An unexpected error occurred while processing your request.';
$file = $_GET['file'] ?? __FILE__;
$line = $_GET['line'] ?? 0;
$route = $_GET['route'] ?? ($_SERVER['REQUEST_URI'] ?? '/');
$trace = $_GET['trace'] ?? null;

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
    .hd{background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff;padding:16px 20px}
    .sec{padding:18px 20px;border-top:1px solid #eee}
    .mono{font:12px/1.4 ui-monospace,Menlo,Monaco,Consolas,monospace;background:#fef2f2;color:#991b1b;padding:10px;border-radius:6px}
    .trace{background:#111;color:#eee;padding:12px;border-radius:6px;max-height:300px;overflow:auto;font:12px/1.5 ui-monospace,monospace}
    .kv{display:grid;grid-template-columns:120px 1fr;gap:8px;margin-top:8px}
</style></head><body><div class="card">
<div class="hd"><strong>System Error</strong><div style="opacity:.9;font-size:12px"><?=date('Y-m-d H:i:s')?> • <?=htmlspecialchars($route)?></div></div>
<div class="sec">
    <div class="kv"><div>Type</div><div><?=htmlspecialchars($type)?></div>
    <div>Message</div><div class="mono"><?=htmlspecialchars($msg)?></div>
    <div>File</div><div><?=htmlspecialchars($short)?></div>
    <div>Line</div><div><?=$line?></div>
    <div>Memory</div><div><?=$mem?> (peak <?=$peak?>)</div></div>
</div>
<?php if ($trace): ?>
<div class="sec"><div class="trace">
    <?php foreach($trace as $i=>$t): ?>
        <div>#<?=$i?> <?=htmlspecialchars(($t['file']??'').' '.(($t['line']??'?')))?></div>
        <div style="opacity:.8"><?=htmlspecialchars(($t['class']??'').($t['type']??'').($t['function']??''))?></div>
        <div style="height:8px"></div>
    <?php endforeach; ?>
</div></div>
<?php endif; ?>
</div></body></html>
