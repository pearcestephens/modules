<?php
declare(strict_types=1);

// Server-Sent Events (SSE) progress stub for pack uploads
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$sid = $_GET['sid'] ?? '';

// Simple heartbeat + demo progress
echo "event: connected\n";
echo 'data: ' . json_encode(['sid' => $sid]) . "\n\n";
@ob_flush(); @flush();

for ($i=0;$i<=100;$i+=20) {
    echo "event: progress\n";
    echo 'data: ' . json_encode(['status' => 'running', 'current_operation' => 'Uploading', 'progress_percentage' => $i, 'message' => "Step $i%" ]) . "\n\n";
    @ob_flush(); @flush();
    usleep(200000);
}

echo "event: finished\n";
echo 'data: ' . json_encode(['success' => true]) . "\n\n";
@ob_flush(); @flush();
