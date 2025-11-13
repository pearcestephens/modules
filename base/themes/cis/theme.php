<?php
/**
 * CIS Theme Settings
 * Exposes runtime-configurable settings for UI behavior.
 * Returned array is merged into ThemeManager::getSettings().
 */
return [
    // Sidebar: collapsed by default on load
    'sidebar' => [
        'collapsed' => false,     // Set true to start sidebar closed
        'hoverExpand' => true,    // Expand on hover when collapsed
        'width' => 256,
    ],

    // Header bar visibility
    'topbar' => [
        'visible' => true,
        'sticky' => true,
    ],

    // Quick product search
    'quickSearch' => [
        'enabled' => true,
        'minChars' => 2,
        'debounceMs' => 300,
    ],

    // Theme flags
    'features' => [
        'notifications' => true,
        'personalMenu' => true,
    ],
];
