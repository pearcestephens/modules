<?php
/**
 * Security configuration values for middleware and guards.
 *
 * @package CIS\\Config
 */

declare(strict_types=1);

require_once __DIR__ . '/env-loader.php';

return [
    'admin_session_key' => env('ADMIN_SESSION_KEY', 'admin_session'),
    'csrf_token_key' => env('CSRF_TOKEN_KEY', '_csrf_token'),
    'allowed_ips' => array_filter(
        array_map('trim', explode(',', (string)env('ADMIN_ALLOWED_IPS', '')))
    ),
    'rate_limit' => [
        'requests_per_minute' => (int)env('ROUTER_RATE_LIMIT_PER_MINUTE', 60),
        'burst' => (int)env('ROUTER_RATE_LIMIT_BURST', 20),
    ],
    'quick_dial' => [
        'requests_per_minute' => (int)env('QUICK_DIAL_REQUESTS_PER_MINUTE', 10),
        'max_lines' => (int)env('QUICK_DIAL_MAX_LINES', 500),
        'snapshot_dir' => rtrim((string)env('QUICK_DIAL_SNAPSHOT_DIR', '/var/log/cis/snapshots'), '/'),
        'log_filename' => env('QUICK_DIAL_LOG_FILENAME', 'apache_phpstack-129337-518184.cloudwaysapps.com.error.log'),
    ],
    'phpinfo_enabled' => filter_var(env('ADMIN_PHPINFO_ENABLED', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
];
