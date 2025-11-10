<?php
declare(strict_types=1);

// Admin Health: phpinfo endpoint (guarded by Kernel via 'phpinfo' flag)

if (!defined('PHPINFO_ALLOWED') || PHPINFO_ALLOWED !== true) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'forbidden']);
    exit;
}

phpinfo();
