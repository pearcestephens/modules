<?php
declare(strict_types=1);

/**
 * OriginalDbSessionShim
 * Optional helper to prime environment vars before including the original
 * assets/functions/sessions/inc.session.php handler.
 *
 * Usage (before session_start):
 *   putenv('USE_ORIGINAL_DB_SESSIONS=1');
 *   putenv('ADOPT_PHPSESSID=1'); // optional
 *   // Ensure DB_* env vars are present for the handler to pick up
 */

namespace CIS\Base\Support;

class OriginalDbSessionShim
{
    public static function primeEnv(array $map = []): void
    {
        foreach ($map as $k => $v) {
            if ($v === null || $v === '') continue;
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}
