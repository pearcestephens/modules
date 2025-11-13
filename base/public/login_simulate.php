<?php
// Developer/testing helper to simulate an authenticated session.
// Gated by feature flag to prevent accidental exposure in production.
// Usage: curl -s "?endpoint=login_simulate&user_id=123&token=DEVKEY" with proper routing wrapper.
// Returns JSON envelope describing session state.

declare(strict_types=1);

use CIS\Base\Support\SessionGuard;
use CIS\Base\Support\Response;

require_once __DIR__ . '/../Support/SessionGuard.php';
require_once __DIR__ . '/../Support/Response.php';

// Safe session init (suppresses CLI banner header warnings)
SessionGuard::ensureStarted();

// Feature flags may live in modules/config/feature-flags.php
// Centralized feature flag loader (avoid duplication across endpoints)
if (!function_exists('cis_load_feature_flags')) {
    function cis_load_feature_flags(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $flagsPath = dirname(__DIR__, 2) . '/config/feature-flags.php';
        $cache = file_exists($flagsPath) ? (require $flagsPath) : [];
        // Defensive normalization (ensure array)
        if (!is_array($cache)) $cache = [];
        return $cache;
    }
}
$loadedFlags = cis_load_feature_flags();
require_once __DIR__ . '/../Config.php';
require_once __DIR__ . '/../Database.php';
// Database class is namespaced; import for clarity
use CIS\Base\Database;

// Security: only allow when feature flag explicitly enabled.
// Allow global helper if defined elsewhere, otherwise use local loader
$flags = function_exists('feature_flags') ? feature_flags() : $loadedFlags;
$force = getenv('FORCE_AUTH_DEBUG');
if (empty($flags['auth_debug']) || $flags['auth_debug'] !== true) {
    if ($force !== '1') {
        Response::error('forbidden', 'auth_debug_flag_disabled', 403);
        return;
    }
}

// Optional simple token check to avoid random external triggering.
// Centralized token resolution (env overrides feature flag)
if (!function_exists('cis_resolve_auth_sim_token')) {
    function cis_resolve_auth_sim_token(array $flags): string {
        $envToken = getenv('DEV_AUTH_SIM_TOKEN');
        if ($envToken !== false && $envToken !== '') return $envToken;
        return (string)($flags['auth_debug_token'] ?? '');
    }
}
$provided = (string)($_GET['token'] ?? '');
$expected = cis_resolve_auth_sim_token($flags);
if ($expected && $provided !== $expected) {
    Response::error('unauthorized', 'invalid_token', 401);
    return;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
    Response::error('bad_request', 'missing_or_invalid_user_id', 400);
    return;
}

// (Optional) verify user exists (skip in test mode to avoid table requirement)
$verified = true;
if (!defined('TESTING')) {
    try {
        $row = Database::queryOne('SELECT id FROM staff_accounts WHERE id = ? LIMIT 1', [$userId]);
        if (!$row) {
            $verified = false;
        }
    } catch (Exception $e) {
        // Table may not exist in all environments; ignore.
    }
}

$_SESSION['user_id'] = $userId;
$_SESSION['simulated_login'] = true;
$_SESSION['simulated_verified'] = $verified;
// Maintain legacy compatibility for modules expecting userID
if (!isset($_SESSION['userID']) || $_SESSION['userID'] !== $userId) {
    $_SESSION['userID'] = $userId;
}
// Mark as authenticated for modules checking this flag
$_SESSION['authenticated'] = true;
// Provide a default username if absent (optional convenience)
if (empty($_SESSION['username'])) {
    $_SESSION['username'] = 'tester_' . $userId;
}

Response::json([
    'ok' => true,
    'user_id' => $userId,
    'verified' => $verified,
    'flags' => [
        'auth_debug' => $flags['auth_debug'] ?? false,
        'force_auth_debug' => $force === '1',
    ],
    'token_mode' => $expected !== '' ? 'required' : 'optional',
    'notes' => $verified ? 'Simulated session established.' : 'User not verified (record missing); session still set for testing.'
]);
