<?php
/**
 * Admin UI Configuration & System Information
 * Manages versioning, theming, AI agents, and system settings
 *
 * @version 1.0.0
 */

declare(strict_types=1);

define('ADMIN_UI_VERSION', '1.0.0');
define('ADMIN_UI_BUILD', '20251030');
define('BUILD_ID', '20251030');
define('ADMIN_UI_RELEASE_DATE', '2025-10-30');
define('ADMIN_UI_COMPONENTS_PATH', __DIR__ . '/_templates/components');

// System Configuration
$adminUIConfig = [
    'version' => ADMIN_UI_VERSION,
    'build' => ADMIN_UI_BUILD,
    'release_date' => ADMIN_UI_RELEASE_DATE,
    'name' => 'Theme Builder IDE',
    'description' => 'Professional theme builder with AI agent integration, validation, formatting, and file management',

    // Theme Configuration
    'themes' => [
        'vscode-dark' => [
            'name' => 'VS Code Dark',
            'description' => 'VS Code inspired dark theme for web development',
            'primary' => '#1e1e1e',
            'secondary' => '#252526',
            'accent' => '#007acc',
            'text' => '#d4d4d4',
            'text_secondary' => '#858585',
            'success' => '#4ec9b0',
            'warning' => '#dcdcaa',
            'error' => '#f48771',
            'background' => '#0d0d0d',
        ],
        'light' => [
            'name' => 'Light',
            'description' => 'Clean light theme for daytime development',
            'primary' => '#ffffff',
            'secondary' => '#f5f5f5',
            'accent' => '#0066cc',
            'text' => '#333333',
            'text_secondary' => '#666666',
            'success' => '#27ae60',
            'warning' => '#f39c12',
            'error' => '#e74c3c',
            'background' => '#fafafa',
        ],
        'high-contrast' => [
            'name' => 'High Contrast',
            'description' => 'High contrast theme for accessibility',
            'primary' => '#000000',
            'secondary' => '#f0f0f0',
            'accent' => '#ffff00',
            'text' => '#000000',
            'text_secondary' => '#444444',
            'success' => '#00aa00',
            'warning' => '#aa5500',
            'error' => '#ff0000',
            'background' => '#ffffff',
        ],
    ],

    // AI Agent Configuration
    'ai_agents' => [
        'openai' => [
            'name' => 'OpenAI GPT-4',
            'enabled' => false,
            'api_key_env' => 'OPENAI_API_KEY',
            'model' => 'gpt-4',
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ],
        'local' => [
            'name' => 'Local AI (Built-in)',
            'enabled' => true,
            'type' => 'local',
            'rules' => [
                'max_response_length' => 5000,
                'max_suggestions' => 5,
                'enable_validation' => true,
                'enable_formatting' => true,
                'enable_optimization' => true,
            ]
        ],
        'anthropic' => [
            'name' => 'Anthropic Claude',
            'enabled' => false,
            'api_key_env' => 'ANTHROPIC_API_KEY',
            'model' => 'claude-3-opus',
            'temperature' => 0.7,
        ],
    ],

    // Feature Flags
    'features' => [
        'validation' => true,
        'formatting' => true,
        'minification' => true,
        'file_explorer' => true,
        'php_sandbox' => true,
        'ai_agent' => true,
        'watch_mode' => true,
        'collaborative_editing' => false, // Phase 2
        'dark_mode' => true,
        'theme_selector' => true,
        'version_info' => true,
    ],

    // Performance Settings
    'performance' => [
        'debounce_ms' => 1000,
        'max_file_size' => 5242880, // 5MB
        'validation_timeout' => 5000,
        'sandbox_timeout' => 5,
        'cache_enabled' => true,
        'cache_ttl' => 3600,
    ],

    // Security Settings
    'security' => [
        'allowed_dirs' => ['/modules', '/private_html', '/conf'],
        'blocked_functions' => [
            'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen',
            'eval', 'assert', 'create_function',
            'include', 'require', 'file_get_contents', 'file_put_contents', 'unlink',
            'PDO', 'mysqli_query', 'mysql_query',
            'mail', 'fsockopen', 'socket_create'
        ],
        'require_auth' => true,
        'rate_limit' => 100, // requests per minute
    ],
];

// Helper function to get theme configuration
function getTheme($theme = 'vscode-dark') {
    global $adminUIConfig;
    return $adminUIConfig['themes'][$theme] ?? $adminUIConfig['themes']['vscode-dark'];
}

// Helper function to get AI agent configuration
function getAIAgent($agent = 'local') {
    global $adminUIConfig;
    return $adminUIConfig['ai_agents'][$agent] ?? $adminUIConfig['ai_agents']['local'];
}

// Helper function to check if feature is enabled
function isFeatureEnabled($feature) {
    global $adminUIConfig;
    return $adminUIConfig['features'][$feature] ?? false;
}

// Helper function to get all enabled AI agents
function getEnabledAIAgents() {
    global $adminUIConfig;
    $enabled = [];
    foreach ($adminUIConfig['ai_agents'] as $name => $config) {
        if ($config['enabled']) {
            $enabled[$name] = $config;
        }
    }
    return $enabled;
}

// Return configuration for external use
return $adminUIConfig;
