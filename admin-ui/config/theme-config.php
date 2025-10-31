<?php
/**
 * Admin UI Theme Configuration
 * Stores theme settings and version history
 * 
 * @package CIS\Modules\AdminUI
 */

return [
    'version' => '1.0.0',
    'last_updated' => '2025-10-28 00:00:00',
    
    // Primary Theme Colors (Foundation - rarely changed)
    'primary' => [
        'main' => '#8B5CF6',
        'light' => '#A78BFA',
        'dark' => '#7C3AED',
        'contrast' => '#ffffff',
    ],
    
    'secondary' => [
        'main' => '#64748B',
        'light' => '#94A3B8',
        'dark' => '#475569',
    ],
    
    'success' => '#10b981',
    'warning' => '#f59e0b',
    'danger' => '#ef4444',
    'info' => '#3b82f6',
    
    // Layout
    'sidebar' => [
        'width' => '260px',
        'bg' => '#495057',
        'text' => 'rgba(255,255,255,0.8)',
        'hover' => 'rgba(255,255,255,0.95)',
    ],
    
    'header' => [
        'height' => '60px',
        'bg' => '#ffffff',
        'border' => '#e5e7eb',
    ],
    
    // Typography
    'fonts' => [
        'primary' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        'mono' => 'Monaco, Consolas, "Courier New", monospace',
    ],
    
    // Spacing
    'spacing' => [
        'unit' => '8px', // Base unit for spacing calculations
    ],
    
    // Border Radius
    'radius' => [
        'sm' => '6px',
        'md' => '12px',
        'lg' => '16px',
    ],
    
    // Shadows
    'shadows' => [
        'sm' => '0 1px 3px rgba(0,0,0,0.1)',
        'md' => '0 4px 6px rgba(0,0,0,0.07)',
        'lg' => '0 12px 24px rgba(0,0,0,0.12)',
    ],
];
