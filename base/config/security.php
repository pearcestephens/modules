<?php
/**
 * Security Configuration
 *
 * Security, authentication, and authorization settings
 *
 * @package CIS\Base
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'enabled' => env('ADMIN_AUTH_REQUIRED', true),
        'session_key' => 'auth_user',
        'remember_key' => 'remember_token',
        'password_timeout' => 10800, // 3 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */
    'csrf' => [
        'enabled' => env('CSRF_ENABLED', true),
        'token_name' => '_token',
        'header_name' => 'X-CSRF-TOKEN',
        'cookie_name' => 'XSRF-TOKEN',
        'exclude_uris' => [
            'api/*',
            'webhooks/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist
    |--------------------------------------------------------------------------
    | Comma-separated list of allowed IPs (empty = all allowed)
    */
    'ip_whitelist' => array_filter(explode(',', env('ALLOWED_IPS', ''))),

    /*
    |--------------------------------------------------------------------------
    | Admin Users
    |--------------------------------------------------------------------------
    | Users with admin access (by user_id)
    */
    'admin_users' => array_filter(explode(',', env('ADMIN_USER_IDS', '1'))),

    /*
    |--------------------------------------------------------------------------
    | Password Requirements
    |--------------------------------------------------------------------------
    */
    'password' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Headers
    |--------------------------------------------------------------------------
    | Security headers to send with responses
    */
    'headers' => [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    */
    'csp' => [
        'enabled' => env('CSP_ENABLED', false),
        'report_only' => env('CSP_REPORT_ONLY', true),
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'cdn.jsdelivr.net'],
            'style-src' => ["'self'", "'unsafe-inline'", 'cdn.jsdelivr.net'],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'", 'data:', 'cdn.jsdelivr.net'],
            'connect-src' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    */
    'encryption' => [
        'key' => env('APP_KEY', ''),
        'cipher' => 'AES-256-CBC',
    ],

    /*
    |--------------------------------------------------------------------------
    | PII Redaction
    |--------------------------------------------------------------------------
    | Patterns to redact from logs
    */
    'pii_redaction' => [
        'enabled' => true,
        'patterns' => [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'phone' => '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/',
            'credit_card' => '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
            'password' => '/(password|passwd|pwd)=[^&\s]+/i',
            'token' => '/(token|api_key|secret)=[^&\s]+/i',
        ],
        'replacement' => '[REDACTED]',
    ],
];
