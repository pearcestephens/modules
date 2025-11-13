<?php
// Store Reports — Auth/Session Probe (temporary helper)
// Minimal, non-PII, to debug user_id vs userID session alignment and redirect loops.

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

$hasSession = session_status() === PHP_SESSION_ACTIVE && session_id() !== '';
$currUserId = function_exists('current_user_id') ? current_user_id() : null;
$kUserId = !empty($_SESSION['user_id'] ?? null);
$kUserID = !empty($_SESSION['userID'] ?? null);

// Reuse the same detection logic as sr_require_auth without redirecting
$authed = false;
if (function_exists('current_user_id')) { $authed = (bool) current_user_id(); }
if (!$authed) { $authed = $kUserId || $kUserID; }

$out = [
    'module' => 'store-reports',
    'ts' => date('c'),
    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
    'session' => [
        'active' => $hasSession,
        'name' => session_name(),
        'id_present' => $hasSession,
        'cookies_present' => !empty($_COOKIE),
        'cookie_names' => array_keys($_COOKIE),
    ],
    'keys' => [
        'user_id_present' => $kUserId,
        'userID_present' => $kUserID,
        'current_user_id' => $currUserId !== null ? (int)$currUserId : null,
    ],
    'detection' => [
        'sr_wants_json' => sr_wants_json(),
        'authed' => $authed,
    ],
    'notes' => 'Temporary probe. Remove after debugging. No PII returned.'
];

echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
exit;
