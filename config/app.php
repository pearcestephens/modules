<?php
/**
 * Application configuration values.
 *
 * All entries are environment driven to keep secrets out of source control.
 * This file should be required wherever global configuration is needed.
 *
 * @package CIS\\Config
 */

declare(strict_types=1);

require_once __DIR__ . '/env-loader.php';

return [
    'name' => env('APP_NAME', 'CIS Control Panel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool)env('APP_DEBUG', false),
    'timezone' => env('APP_TIMEZONE', 'Pacific/Auckland'),
    'url' => rtrim((string)env('APP_URL', 'https://staff.vapeshed.co.nz'), '/'),
    'locale' => env('APP_LOCALE', 'en_NZ'),
    'log_channel' => env('APP_LOG_CHANNEL', 'stack'),
];