<?php
/**
 * Admin-only phpinfo endpoint. Ensure upstream middleware enforces auth.
 */

declare(strict_types=1);

// No external includes to avoid fatals; rely on constant exposed by upstream config/deploy

if (!defined('PHPINFO_ALLOWED') || PHPINFO_ALLOWED !== true) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// HEAD requests should not execute phpinfo; respond with method not allowed
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
phpinfo();
