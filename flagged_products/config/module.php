<?php
/**
 * Flagged Products Module - Configuration
 *
 * Module-specific configuration settings
 *
 * @package CIS\Modules\FlaggedProducts
 * @version 1.0.0
 */

return [
    'module' => [
        'name' => 'Flagged Products',
        'slug' => 'flagged_products',
        'version' => '1.0.0',
        'description' => 'Stock accuracy tracking and verification system for daily stock takes',
        'author' => 'CIS Development Team',
    ],

    'routes' => [
        'index' => '/',
        'outlet' => '/outlet',
        'stats' => '/stats',
        'accuracy' => '/accuracy',
        'api' => '/api',
    ],

    'permissions' => [
        'view' => ['staff', 'manager', 'admin'],
        'create' => ['staff', 'manager', 'admin'],
        'complete' => ['staff', 'manager', 'admin'],
        'delete' => ['manager', 'admin'],
        'stats' => ['manager', 'admin'],
    ],

    'database' => [
        'table' => 'flagged_products',
        'views' => [
            'accuracy_stats',
            'outlet_performance',
        ],
    ],

    'features' => [
        'real_time_accuracy' => true,
        'dummy_products' => true,
        'bulk_operations' => true,
        'export_csv' => true,
        'email_notifications' => false,
    ],

    'defaults' => [
        'accuracy_threshold' => 95.0, // Minimum acceptable accuracy percentage
        'days_to_analyze' => 30,
        'items_per_page' => 25,
    ],

    'theme' => [
        'template' => 'default', // Can be: default, classic_cis, modern
        'primary_color' => '#4169E1',
        'layout' => 'full-width',
    ],
];
