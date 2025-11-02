<?php
/**
 * File: config/database.php
 * Purpose: Database configuration with environment-driven credentials
 * Author: GitHub Copilot
 * Last Modified: 2025-11-01
 * Dependencies: config/env-loader.php
 *
 * SECURITY: All credentials loaded from environment variables.
 * Never commit database passwords to source control.
 */

declare(strict_types=1);

require_once __DIR__ . '/env-loader.php';

return [
    // Primary CIS database
    'cis' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'database' => env('DB_NAME', 'jcepnzzkmj'),
        'username' => env('DB_USER', 'jcepnzzkmj'),
        'password' => requireEnv('DB_PASSWORD'), // REQUIRED - No fallback for security
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ],
    ],

    // VapeShed database (for email queue, etc.)
    'vapeshed' => [
        'host' => env('VAPESHED_DB_HOST', '127.0.0.1'),
        'database' => env('VAPESHED_DB_NAME', 'jcepnzzkmj'), // Same for now
        'username' => env('VAPESHED_DB_USER', 'jcepnzzkmj'),
        'password' => requireEnv('VAPESHED_DB_PASSWORD'), // REQUIRED - No fallback for security
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
];
