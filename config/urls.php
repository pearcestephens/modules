<?php
/**
 * File: config/urls.php
 * Purpose: Define safe endpoint routing metadata consumed by the HTTP kernel.
 * Author: GitHub Copilot
 * Last Modified: 2025-11-01
 * Dependencies: config/env-loader.php
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
