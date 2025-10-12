<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

echo json_encode([
    'ok'      => true,
    'module'  => 'consignments',
    'version' => \Modules\Base\Shared::VERSION ?? '1.0.0',
    'time'    => date('c'),
], JSON_UNESCAPED_SLASHES);
