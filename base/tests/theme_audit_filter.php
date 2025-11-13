<?php
/**
 * Theme Audit Log Filter
 * Usage (CLI): php theme_audit_filter.php action=export cid=abcd1234 theme_id=42
 */
$logFile = '/home/master/applications/jcepnzzkmj/logs/theme_audit.log';
if (!file_exists($logFile)) { fwrite(STDERR, "Log file not found\n"); exit(1); }

// Parse CLI args key=value
$filters = [];
foreach ($argv as $arg) {
    if (strpos($arg,'=') !== false) {
        [$k,$v] = explode('=',$arg,2); $filters[$k] = $v;
    }
}

$handle = fopen($logFile,'r');
$results = [];
while(($line = fgets($handle)) !== false) {
    $line = trim($line); if ($line==='') continue;
    $json = json_decode($line,true); if (!$json) continue;
    $match = true;
    foreach ($filters as $k=>$v) {
        if (!isset($json[$k]) || strval($json[$k]) !== $v) { $match = false; break; }
    }
    if ($match) { $results[] = $json; }
}
fclose($handle);

echo json_encode(['count'=>count($results),'filters'=>$filters,'entries'=>$results], JSON_PRETTY_PRINT);
