<?php
/**
 * Base Module Configuration
 *
 * @package CIS\Base
 * @version 2.0.0
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */
    'environment' => $_SERVER['ENVIRONMENT'] ?? 'production',
    'debug' => $_SERVER['DEBUG'] ?? false,

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    */
    'database' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'pdo',
                'host' => $_SERVER['DB_HOST'] ?? '127.0.0.1',
                'port' => $_SERVER['DB_PORT'] ?? '3306',
                'database' => $_SERVER['DB_NAME'] ?? 'jcepnzzkmj',
                'username' => $_SERVER['DB_USER'] ?? 'jcepnzzkmj',
                'password' => $_SERVER['DB_PASS'] ?? 'wprKh9Jq63',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */
    'session' => [
        'driver' => 'native',
        'lifetime' => 7200, // 2 hours
        'cookie_name' => 'cis_session',
        'path' => '/',
        'domain' => null,
        'secure' => true,
        'http_only' => true,
        'same_site' => 'lax',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'default' => 'daily',
        'channels' => [
            'daily' => [
                'driver' => 'daily',
                'path' => $_SERVER['DOCUMENT_ROOT'] . '/logs/base.log',
                'level' => 'debug',
                'days' => 14,
            ],
            'database' => [
                'driver' => 'database',
                'table' => 'logs',
                'level' => 'info',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'csrf' => [
            'enabled' => true,
            'token_name' => 'csrf_token',
            'token_length' => 32,
        ],
        'rate_limit' => [
            'enabled' => true,
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ],
        'encryption' => [
            'key' => $_SERVER['ENCRYPTION_KEY'] ?? '',
            'cipher' => 'AES-256-CBC',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | View/Template Configuration
    |--------------------------------------------------------------------------
    */
    'view' => [
        'paths' => [
            __DIR__ . '/../templates',
        ],
        'cache' => [
            'enabled' => true,
            'path' => $_SERVER['DOCUMENT_ROOT'] . '/storage/cache/views',
        ],
        'auto_escape' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'hub_url' => 'https://gpt.ecigdis.co.nz/mcp/server_v4.php',
        'api_key' => '31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35',
        'timeout' => 30,
        'unit_id' => 2, // CIS Unit
        'cache_enabled' => true,
        'cache_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Configuration
    |--------------------------------------------------------------------------
    */
    'assets' => [
        'url' => '/modules/base/public/assets',
        'version' => '2.0.0',
        'cdn_enabled' => false,
    ],

];
