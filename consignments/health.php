<?php
declare(strict_types=1);

require_once __DIR__ . '/module_bootstrap.php';
/**
 * Consignments Module Health Check
 */
require_once dirname(__DIR__) . '/base/lib/Kernel.php';
\Modules\Base\Kernel::boot();

header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

$version = '1.0.0';
if (class_exists('Modules\\Base\\Shared') && defined('Modules\\Base\\Shared::VERSION')) {
    $version = \Modules\Base\Shared::VERSION;
}

echo json_encode([
    'ok'      => true,
    'module'  => 'consignments',
    'version' => $version,
    'time'    => date('c'),
    'bot_bypass' => !empty($_ENV['BOT_BYPASS_AUTH']) ? true : false,
], JSON_UNESCAPED_SLASHES);
