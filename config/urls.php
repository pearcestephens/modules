<?php
/**
 * URL configuration and endpoint routing map.
 *
 * Centralises mappings used by the lightweight router so new endpoints can be
 * registered without editing public/index.php directly.
 *
 * @package CIS\\Config
 */

declare(strict_types=1);

require_once __DIR__ . '/env-loader.php';

return [
    'default_endpoint' => env('APP_DEFAULT_ENDPOINT', 'admin/health/ping'),
    'whitelist' => [
        'admin/health/ping' => [
            'script' => __DIR__ . '/../admin/health/ping.php',
            'auth' => false,
        ],
        'admin/health/phpinfo' => [
            'script' => __DIR__ . '/../admin/health/phpinfo.php',
            'auth' => true,
            'flags' => ['phpinfo'],
        ],
    ],
];