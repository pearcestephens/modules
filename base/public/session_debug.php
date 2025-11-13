<?php declare(strict_types=1);
// Session Debug Endpoint (feature-flag gated)
// Shows cookie name, save handler/path, and key snapshot

require_once __DIR__ . '/../Support/Response.php';
require_once __DIR__ . '/../Support/SessionGuard.php';

use CIS\Base\Support\Response;
use CIS\Base\Support\SessionGuard;

// Load flags
$flagsPath = dirname(__DIR__, 2) . '/config/feature-flags.php';
$flags = file_exists($flagsPath) ? (require $flagsPath) : [];
if (!is_array($flags)) $flags = [];
if (empty($flags['session_debug'])) {
    Response::error('session_debug_disabled', 'Enable session_debug flag to use this endpoint', 403);
    return;
}

SessionGuard::ensureStarted();

$cookieName = session_name();
$savePath = session_save_path();
$saveHandler = ini_get('session.save_handler');

$keys = [
    'user_id' => $_SESSION['user_id'] ?? null,
    'userID' => $_SESSION['userID'] ?? null,
    'csrf_token' => $_SESSION['csrf_token'] ?? null,
    'LAST_ACTIVITY' => $_SESSION['LAST_ACTIVITY'] ?? null,
];

Response::json([
    'cookie_name' => $cookieName,
    'save_handler' => $saveHandler,
    'save_path' => $savePath,
    'keys' => $keys,
]);
