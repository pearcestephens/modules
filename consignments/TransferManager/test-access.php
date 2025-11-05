<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Access test successful',
    'timestamp' => date('c'),
    'file' => __FILE__,
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'
]);
