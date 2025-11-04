<?php
/**
 * URL Configuration
 *
 * Centralized URL routing and named routes
 *
 * @package CIS\Base
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL Configuration
    |--------------------------------------------------------------------------
    */
    'base_url' => env('APP_URL', 'https://staff.vapeshed.co.nz/modules/base'),
    'public_path' => '/public/index.php',

    /*
    |--------------------------------------------------------------------------
    | Named Routes
    |--------------------------------------------------------------------------
    | Format: 'name' => 'endpoint'
    */
    'routes' => [
        // Health & System
        'health.ping' => 'admin/health/ping',
        'health.checks' => 'admin/health/checks',
        'health.phpinfo' => 'admin/health/phpinfo',
        'health.dashboard' => 'admin/health/dashboard',

        // Section 11: Traffic Monitoring
        'traffic.monitor' => 'admin/traffic/monitor',
        'traffic.live' => 'admin/traffic/live',
        'traffic.stats' => 'admin/traffic/stats',

        'performance.dashboard' => 'admin/performance/dashboard',
        'performance.queries' => 'admin/performance/queries',
        'performance.explain' => 'admin/performance/explain',

        'sources.map' => 'admin/sources/map',
        'sources.browsers' => 'admin/sources/browsers',
        'sources.bots' => 'admin/sources/bots',

        'errors.404' => 'admin/errors/404',
        'errors.500' => 'admin/errors/500',
        'errors.create-redirect' => 'admin/errors/create-redirect',

        'logs.apache-tail' => 'admin/logs/apache-error-tail',
        'logs.php-fpm-tail' => 'admin/logs/php-fpm-tail',
        'logs.viewer' => 'admin/logs/viewer',

        // Section 12: API Testing
        'testing.webhook-lab' => 'admin/testing/webhook-lab',
        'testing.webhook-send' => 'admin/testing/webhook-send',

        'testing.vend-api' => 'admin/testing/vend-api',
        'testing.vend-api-call' => 'admin/testing/vend-api-call',

        'testing.lightspeed-sync' => 'admin/testing/lightspeed-sync',
        'testing.lightspeed-sync-run' => 'admin/testing/lightspeed-sync-run',

        'testing.queue-jobs' => 'admin/testing/queue-jobs',
        'testing.queue-dispatch' => 'admin/testing/queue-dispatch',
        'testing.queue-cancel' => 'admin/testing/queue-cancel',

        'testing.api-endpoints' => 'admin/testing/api-endpoints',
        'testing.api-run-suite' => 'admin/testing/api-run-suite',

        'testing.snippets' => 'admin/testing/snippets',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Groups
    |--------------------------------------------------------------------------
    | Organize routes by prefix and middleware
    */
    'groups' => [
        'admin' => [
            'prefix' => 'admin',
            'middleware' => ['auth', 'admin'],
        ],
        'api' => [
            'prefix' => 'api',
            'middleware' => ['api', 'rate-limit:api'],
        ],
        'public' => [
            'prefix' => '',
            'middleware' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Definitions
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'auth' => \CIS\Base\Http\Middleware\Authenticate::class,
        'admin' => \CIS\Base\Http\Middleware\AdminOnly::class,
        'csrf' => \CIS\Base\Http\Middleware\VerifyCsrfToken::class,
        'rate-limit' => \CIS\Base\Http\Middleware\RateLimiter::class,
        'api' => \CIS\Base\Http\Middleware\ApiMiddleware::class,
        'traffic-log' => \CIS\Base\Http\Middleware\TrafficLogger::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Middleware Stack
    |--------------------------------------------------------------------------
    | Applied to all requests
    */
    'global_middleware' => [
        'traffic-log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Method Mappings
    |--------------------------------------------------------------------------
    | Map endpoints to controller methods
    */
    'endpoints' => [
        // Health & System
        'admin/health/ping' => [
            'GET' => 'HealthController@ping',
        ],
        'admin/health/checks' => [
            'GET' => 'HealthController@checks',
        ],
        'admin/health/phpinfo' => [
            'GET' => 'HealthController@phpinfo',
        ],
        'admin/health/dashboard' => [
            'GET' => 'HealthController@dashboard',
        ],

        // Section 11: Traffic Monitoring
        'admin/traffic/monitor' => [
            'GET' => 'TrafficController@monitor',
        ],
        'admin/traffic/live' => [
            'GET' => 'TrafficController@live',
        ],
        'admin/traffic/stats' => [
            'GET' => 'TrafficController@stats',
        ],

        'admin/performance/dashboard' => [
            'GET' => 'PerformanceController@dashboard',
        ],
        'admin/performance/queries' => [
            'GET' => 'PerformanceController@queries',
        ],
        'admin/performance/explain' => [
            'POST' => 'PerformanceController@explain',
        ],

        'admin/sources/map' => [
            'GET' => 'TrafficSourcesController@map',
        ],
        'admin/sources/browsers' => [
            'GET' => 'TrafficSourcesController@browsers',
        ],
        'admin/sources/bots' => [
            'GET' => 'TrafficSourcesController@bots',
        ],

        'admin/errors/404' => [
            'GET' => 'ErrorTrackingController@notFound',
        ],
        'admin/errors/500' => [
            'GET' => 'ErrorTrackingController@serverErrors',
        ],
        'admin/errors/create-redirect' => [
            'POST' => 'ErrorTrackingController@createRedirect',
        ],

        'admin/logs/apache-error-tail' => [
            'GET' => 'LogsController@apacheTail',
        ],
        'admin/logs/php-fpm-tail' => [
            'GET' => 'LogsController@phpFpmTail',
        ],
        'admin/logs/viewer' => [
            'GET' => 'LogsController@viewer',
        ],

        // Section 12: API Testing
        'admin/testing/webhook-lab' => [
            'GET' => 'WebhookLabController@index',
        ],
        'admin/testing/webhook-send' => [
            'POST' => 'WebhookLabController@send',
        ],

        'admin/testing/vend-api' => [
            'GET' => 'VendApiTesterController@index',
        ],
        'admin/testing/vend-api-call' => [
            'POST' => 'VendApiTesterController@call',
        ],

        'admin/testing/lightspeed-sync' => [
            'GET' => 'LightspeedSyncController@index',
        ],
        'admin/testing/lightspeed-sync-run' => [
            'POST' => 'LightspeedSyncController@run',
        ],

        'admin/testing/queue-jobs' => [
            'GET' => 'QueueTesterController@index',
        ],
        'admin/testing/queue-dispatch' => [
            'POST' => 'QueueTesterController@dispatch',
        ],
        'admin/testing/queue-cancel' => [
            'POST' => 'QueueTesterController@cancel',
        ],

        'admin/testing/api-endpoints' => [
            'GET' => 'ApiEndpointTesterController@index',
        ],
        'admin/testing/api-run-suite' => [
            'POST' => 'ApiEndpointTesterController@runSuite',
        ],

        'admin/testing/snippets' => [
            'GET' => 'SnippetLibraryController@index',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    | Static redirects (will be augmented by database redirects)
    */
    'redirects' => [
        // Example: 'old-path' => ['new-path', 301],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Overrides
    |--------------------------------------------------------------------------
    | Custom rate limits for specific endpoints
    */
    'rate_limits' => [
        'admin/performance/explain' => [10, 1], // 10 per minute
        'admin/logs/apache-error-tail' => [20, 1], // 20 per minute
        'admin/logs/php-fpm-tail' => [20, 1],
        'admin/testing/lightspeed-sync-run' => [5, 1], // 5 per minute
        'admin/testing/api-run-suite' => [5, 1],
    ],
];
