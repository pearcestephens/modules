<?php
declare(strict_types=1);

namespace Modules\Core;

/**
 * Loads the CIS main app bootstrap (config, session, DB, constants)
 * exactly once, without double-starting sessions or redefining things.
 */
final class Bootstrap
{
    private static bool $done = false;

    /**
     * @param string $modulesPath  Absolute path to /modules
     * @param string|null $cisRoot Optional absolute path to CIS root; if null, infer ../
     */
    public static function init(string $modulesPath, ?string $cisRoot = null): void
    {
        if (self::$done) return;
        self::$done = true;

        // 1) Resolve main app root (public_html or project root)
        $cisRoot ??= realpath($modulesPath . '/..'); // one level up from /modules
        if (!is_dir($cisRoot)) {
            throw new \RuntimeException("CIS root not found at: {$cisRoot}");
        }

        // 2) Include CIS config/bootstrap exactly once (adjust these to your app)
        // Prefer the same file your legacy pages include, e.g. assets/functions/config.php
        $configFile = $cisRoot . '/assets/functions/config.php';
        if (!is_file($configFile)) {
            throw new \RuntimeException("CIS config.php not found at: {$configFile}");
        }

        // Prevent accidental BOM/whitespace before headers
        ob_get_level() === 0 && ob_start();

        require_once $configFile;

        // 3) Ensure session is started (but don't double-start)
        if (PHP_SESSION_NONE === session_status()) {
            // Adopt main app cookie settings if defined; else use defaults
            @session_start();
        }

        // 4) Optional: expose common globals into a simple accessor
        // (so controllers can use App::db(), App::cfg() without touching globals)
        App::prime();
    }
}

/**
 * Simple accessor to reuse main app services without tight coupling.
 * You can adapt these to your real globals/singletons.
 */
final class App
{
    private static bool $primed = false;
    public static function prime(): void { self::$primed = true; }

    /** Example DB getter for mysqli */
    public static function db(): \mysqli
    {
        // If your main app defines $mysqli global:
        global $mysqli;
        if (!$mysqli instanceof \mysqli) {
            throw new \RuntimeException('Main DB handle ($mysqli) is not available.');
        }
        return $mysqli;
    }

    /** Example: base URL helper passthrough if you use Helpers::url() */
    public static function url(string $path = '/'): string
    {
        if (class_exists('\\Helpers') && method_exists('\\Helpers', 'url')) {
            return \Helpers::url($path);
        }
        // Fallback: assume root-relative
        return $path;
    }
}
