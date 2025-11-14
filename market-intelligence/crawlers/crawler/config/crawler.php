<?php
/**
 * Crawler Service Configuration
 *
 * Ultra-Sophisticated configuration for world-class web crawler
 * Includes ML/AI settings, anti-detection, performance tuning
 *
 * @package CIS\SharedServices\Crawler
 * @version 2.0.0
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Core Crawler Settings
    |--------------------------------------------------------------------------
    */

    'crawler' => [
        'user_agent_pool_size' => 50,
        'max_concurrent_requests' => 10,
        'request_timeout' => 30,
        'connect_timeout' => 10,
        'max_redirects' => 5,
        'verify_ssl' => true,
        'allow_http2' => true,
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Strategy
    |--------------------------------------------------------------------------
    | Algorithm: token_bucket, leaky_bucket, sliding_window, ml_adaptive
    */

    'rate_limiting' => [
        'algorithm' => 'ml_adaptive', // ML-based adaptive rate limiting
        'requests_per_second' => 2.0,
        'burst_size' => 10,
        'per_domain_limits' => true,
        'domain_limits' => [
            'shosha.co.nz' => 1.0,
            'vapo.co.nz' => 1.5,
            'default' => 2.0,
        ],
        'ml_prediction' => [
            'enabled' => true,
            'learning_rate' => 0.01,
            'history_window' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stealth & Anti-Detection
    |--------------------------------------------------------------------------
    | Levels: low, medium, high, extreme, quantum
    */

    'stealth' => [
        'default_level' => 'high',
        'rotate_fingerprints' => true,
        'rotate_after_requests' => 100,
        'human_behavior' => [
            'enabled' => true,
            'profile_rotation' => true,
            'realistic_timing' => true,
            'mouse_movement_simulation' => true,
            'scroll_behavior_simulation' => true,
        ],
        'fingerprinting' => [
            'canvas_noise' => true,
            'webgl_noise' => true,
            'audio_noise' => true,
            'font_randomization' => true,
            'hardware_spoofing' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Management
    |--------------------------------------------------------------------------
    */

    'session' => [
        'profile_base_path' => '/home/master/applications/jcepnzzkmj/private_html/crawler-profiles/',
        'max_profiles' => 100,
        'profile_rotation_after' => 100,
        'profile_ban_threshold' => 0.5,
        'success_rate_tracking' => true,
        'automatic_cleanup' => true,
        'cleanup_after_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Pattern
    |--------------------------------------------------------------------------
    */

    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,
        'timeout' => 60, // seconds
        'half_open_requests' => 3,
        'success_threshold' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Machine Learning Configuration
    |--------------------------------------------------------------------------
    */

    'machine_learning' => [
        'pattern_recognition' => [
            'enabled' => true,
            'algorithm' => 'isolation_forest',
            'anomaly_threshold' => 0.1,
        ],
        'behavior_learning' => [
            'enabled' => true,
            'algorithm' => 'q_learning',
            'learning_rate' => 0.1,
            'discount_factor' => 0.9,
            'epsilon' => 0.1, // exploration rate
            'epsilon_decay' => 0.995,
        ],
        'forecasting' => [
            'enabled' => true,
            'algorithms' => ['prophet', 'arima', 'lstm'],
            'prediction_window' => 24, // hours
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage & Caching
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'driver' => 'mysql',
        'cache_driver' => 'redis',
        'cache_ttl' => 3600,
        'compress_results' => true,
        'version_control' => true,
        'max_versions' => 10,
    ],

    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 1,
        'prefix' => 'crawler:',
        'timeout' => 5,
    ],

    'influxdb' => [
        'enabled' => false, // Enable when InfluxDB is available
        'host' => '127.0.0.1',
        'port' => 8086,
        'database' => 'crawler_metrics',
        'username' => '',
        'password' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging & Monitoring
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'level' => 'info', // debug, info, warning, error, critical
        'channels' => ['file', 'database', 'redis'],
        'file_path' => '/home/master/applications/jcepnzzkmj/private_html/logs/crawler/',
        'correlation_id' => true,
        'distributed_tracing' => false, // Enable when Jaeger is available
        'performance_logging' => true,
        'security_logging' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxy Configuration
    |--------------------------------------------------------------------------
    */

    'proxy' => [
        'enabled' => false,
        'type' => 'residential', // residential, datacenter, mobile
        'rotation_strategy' => 'round_robin',
        'providers' => [
            // Add proxy provider configurations here
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot Protection Bypass
    |--------------------------------------------------------------------------
    */

    'bot_protection' => [
        'cloudflare_bypass' => [
            'enabled' => true,
            'solver_service' => null, // Add 2captcha/anticaptcha service
        ],
        'recaptcha_v3' => [
            'enabled' => true,
            'solver_service' => null,
            'min_score' => 0.7,
        ],
        'perimeter_x' => [
            'enabled' => true,
            'evasion_strategy' => 'fingerprint_rotation',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    */

    'performance' => [
        'connection_pooling' => true,
        'keep_alive' => true,
        'dns_cache' => true,
        'compression' => true,
        'lazy_loading' => true,
        'batch_processing' => true,
        'async_operations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Event System
    |--------------------------------------------------------------------------
    */

    'events' => [
        'enabled' => true,
        'async_dispatch' => false, // Enable when RabbitMQ is available
        'queue_driver' => 'sync', // sync, redis, rabbitmq
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Alerting
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'prometheus' => [
            'enabled' => false,
            'metrics_port' => 9090,
        ],
        'grafana' => [
            'enabled' => false,
            'dashboard_id' => null,
        ],
        'alerts' => [
            'detection_rate_threshold' => 0.3,
            'error_rate_threshold' => 0.1,
            'response_time_threshold' => 5000, // ms
        ],
    ],
];
