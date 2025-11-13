<?php
/**
 * Vape Ultra Theme Configuration
 *
 * Central configuration for the base theme system
 * Modules inherit this and inject their content
 */

return [
    'theme' => [
        'name' => 'Vape Ultra',
        'version' => '1.0.0',
        'author' => 'Ecigdis Limited',
        'description' => 'Maximum feature-rich admin theme for CIS Pro',
    ],

    'assets' => [
        'css' => [
            // Core framework
            'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',

            // Theme CSS
            '/modules/base/templates/vape-ultra/assets/css/variables.css',
            '/modules/base/templates/vape-ultra/assets/css/base.css',
            '/modules/base/templates/vape-ultra/assets/css/layout.css',
            '/modules/base/templates/vape-ultra/assets/css/components.css',
            '/modules/base/templates/vape-ultra/assets/css/utilities.css',
            '/modules/base/templates/vape-ultra/assets/css/animations.css',
        ],

        'js' => [
            // Core libraries
            'https://code.jquery.com/jquery-3.7.1.min.js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            'https://cdn.jsdelivr.net/npm/axios@1.6.0/dist/axios.min.js',
            'https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js',
            'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11',

            // Theme JS
            '/modules/base/templates/vape-ultra/assets/js/core.js',
            '/modules/base/templates/vape-ultra/assets/js/components.js',
            '/modules/base/templates/vape-ultra/assets/js/utils.js',
            '/modules/base/templates/vape-ultra/assets/js/api.js',
            '/modules/base/templates/vape-ultra/assets/js/notifications.js',
            '/modules/base/templates/vape-ultra/assets/js/charts.js',
        ],
    ],

    'layout' => [
        'default' => 'main',
        'available' => ['main', 'minimal', 'mobile', 'print'],
    ],

    'features' => [
        'live_updates' => true,
        'notifications' => true,
        'dark_mode' => true,
        'mobile_responsive' => true,
        'pwa_support' => true,
        'offline_mode' => false,
    ],

    'middleware' => [
        'auth' => true,
        'csrf' => true,
        'rate_limit' => true,
        'logging' => true,
        'cache' => true,
        'compression' => true,
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'driver' => 'file', // file, redis, memcached
    ],
];
