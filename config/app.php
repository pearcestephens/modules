<?php
/**
 * File: config/app.php
 * Purpose: Provide environment-driven application configuration shared across modules.
 * Author: GitHub Copilot
 * Last Modified: 2025-11-02
 * Dependencies: config/env-loader.php
 *
 * All entries are environment driven to keep secrets out of source control.
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
    'log_path' => rtrim((string)env('LOG_PATH', __DIR__ . '/../logs'), '/'),

    // ============================================================================
    // MODULE-SPECIFIC SETTINGS
    // ============================================================================

    /**
     * Payroll Module Authentication
     *
     * Controls whether authentication is enforced on payroll module routes.
     *
     * When FALSE: All routes accessible without login (development/testing mode)
     * When TRUE: Routes enforce auth based on 'auth' => true in routes.php
     *
     * @var bool
     */
    'payroll_auth_enabled' => false,  // Set to true for production
];
