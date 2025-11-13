<?php
require_once __DIR__.'/bootstrap.php';
sr_require_auth(true); // admin only for now

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$logger = new JsonLogger('store-reports-sse');
$logger->info('sse_connection_opened');

// Simple heartbeat & placeholder event stream
$counter = 0;
while ($counter < 25) { // temporary cap
    $event = [
        'type' => 'heartbeat',
        'counter' => $counter,
        'time' => time()
    ];
    echo "event: heartbeat\n";
    echo 'data: '.json_encode($event)."\n\n";
    @ob_flush();
    @flush();
    if (connection_aborted()) { $logger->info('sse_connection_aborted'); break; }
    $counter++;
    usleep(750000); // 0.75s
}
$logger->info('sse_connection_closed');
exit;
