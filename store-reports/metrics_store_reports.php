<?php
require_once __DIR__.'/bootstrap.php';
sr_require_auth(true);
header('Content-Type: text/plain');

$db = DatabaseManager::health();
echo "store_reports_db_available ".($db['db_available']?1:0)."\n";
echo "store_reports_memory_mb ".round(memory_get_usage(true)/1048576,2)."\n";
echo "store_reports_phpinfo 1\n"; // placeholder static metric
exit;
