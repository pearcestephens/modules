<?php
// Non-secret feature flags moved out of .env to reduce secret surface.
return [
    'ai' => true,
    'analytics' => true,
    // Enables /modules/base/public/behavior_inspect.php endpoint for debugging
    'behavior_debug' => false,
    // Enables /modules/base/public/login_simulate.php test helper (MUST remain false in production)
    'auth_debug' => true,
    // Optional token required for login_simulate.php when set (leave blank to skip token check)
    'auth_debug_token' => '',
    // Enables /modules/base/public/session_debug.php endpoint (MUST remain false in production)
    'session_debug' => true,
];

// Provide a global helper if not already defined (minimize duplication in endpoints)
if (!function_exists('feature_flags')) {
    function feature_flags(): array {
        static $cached = null;
        if ($cached !== null) return $cached;
        $cached = require __FILE__;
        return is_array($cached) ? $cached : [];
    }
}
