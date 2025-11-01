<?php
/**
 * Admin-only phpinfo endpoint. Ensure upstream middleware enforces auth.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config/env-loader.php';

if (!defined('PHPINFO_ALLOWED') || PHPINFO_ALLOWED !== true) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

phpinfo();