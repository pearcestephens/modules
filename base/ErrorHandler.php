<?php
/**
 * CIS Base Error Handler
 *
 * Extends ErrorMiddleware.php and integrates with CISLogger.php
 * Provides beautiful error pages and comprehensive logging.
 *
 * @package CIS\Base
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Base;

require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/ErrorMiddleware.php';
// CISLogger already loaded by bootstrap.php - no need to require again

class ErrorHandler
{
    private static bool $initialized = false;
    private static bool $devMode = false;

    public static function isDevMode(): bool
    {
        return self::$devMode;
    }

    public static function init(): void
    {
        if (self::$initialized) return;

        // Detect dev mode
        self::$devMode = ($_SERVER['ENVIRONMENT'] ?? 'production') === 'development';

        // Set error handler
        set_error_handler([self::class, 'handleError']);

        // Set exception handler
        set_exception_handler([self::class, 'handleException']);

        // Set fatal error handler
        register_shutdown_function([self::class, 'handleFatalError']);

        self::$initialized = true;
    }

    /**
     * Handle PHP errors (convert to exceptions)
     */
    public static function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException(\Throwable $e): void
    {
        // Log to CISLogger
        \CISLogger::action(
            category: 'system',
            actionType: 'uncaught_exception',
            result: 'failure',
            entityType: 'error',
            entityId: null,
            context: [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ],
            actorType: 'system'
        );

        // Log to module-specific directory or default error_log
        $logFile = self::getModuleLogPath();
        $logMessage = sprintf(
            "[%s] Uncaught %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        if ($logFile) {
            error_log($logMessage, 3, $logFile);
        } else {
            error_log($logMessage);
        }

        // Check if JSON request
        if (self::isJsonRequest()) {
            self::sendJsonError($e);
        } else {
            self::sendHtmlError($e);
        }
    }

    /**
     * Get module-specific log path (if in module context)
     */
    private static function getModuleLogPath(): ?string {
        // Check if we're in a module context
        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';
        if (preg_match('#/modules/([^/]+)/#', $scriptPath, $matches)) {
            $moduleName = $matches[1];
            $logDir = $_SERVER['DOCUMENT_ROOT'] . '/modules/' . $moduleName . '/logs';

            // Create logs directory if it doesn't exist
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            if (is_writable($logDir)) {
                return $logDir . '/errors.log';
            }
        }
        return null;
    }

    /**
     * Handle fatal PHP errors
     */
    public static function handleFatalError(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $e = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            self::handleException($e);
        }
    }

    /**
     * Detect JSON request
     */
    private static function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) return true;

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#/(api|ajax)/#i', $uri)) return true;

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    /**
     * Send JSON error response
     */
    private static function sendJsonError(\Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: application/json');

        $response = [
            'success' => false,
            'error' => [
                'message' => self::$devMode ? $e->getMessage() : 'Internal server error',
                'code' => 'INTERNAL_ERROR',
                'type' => get_class($e)
            ],
            'request_id' => uniqid('req_', true),
            'timestamp' => date('c')
        ];

        if (self::$devMode) {
            $response['debug'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ];
        }

        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send HTML error page
     */
    private static function sendHtmlError(\Throwable $e): void
    {
        http_response_code(500);

        // 🔥 ALWAYS SHOW ERROR DETAILS - PRODUCTION CHECK DISABLED
        // In production, show generic message
        // if (!self::$devMode) {
        //     echo "<h1>Unexpected error</h1>";
        //     exit;
        // }

        // Dev mode: show error details (FORCED ALWAYS ON)
        $type = get_class($e);
        $msg = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $trace = $e->getTrace();

        $short = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
        $mem = number_format(memory_get_usage(true)/1048576, 2) . ' MB';
        $peak = number_format(memory_get_peak_usage(true)/1048576, 2) . ' MB';

        echo '<!doctype html><html><head><meta charset="utf-8"><title>System Error</title>
        <style>
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif;background:#f5f5f7;color:#1d1d1f;padding:20px}
            .card{max-width:1100px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);overflow:hidden}
            .hd{background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff;padding:16px 20px}
            .sec{padding:18px 20px;border-top:1px solid #eee}
            .mono{font:12px/1.4 ui-monospace,Menlo,Monaco,Consolas,monospace;background:#fef2f2;color:#991b1b;padding:10px;border-radius:6px}
            .trace{background:#111;color:#eee;padding:12px;border-radius:6px;max-height:300px;overflow:auto;font:12px/1.5 ui-monospace,monospace}
            .kv{display:grid;grid-template-columns:120px 1fr;gap:8px;margin-top:8px}
        </style></head><body><div class="card">
        <div class="hd"><strong>System Error</strong><div style="opacity:.9;font-size:12px">' . date('Y-m-d H:i:s') . ' • ' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') . '</div></div>
        <div class="sec">
            <div class="kv">
            <div>Type</div><div>' . htmlspecialchars($type) . '</div>
            <div>Message</div><div class="mono">' . htmlspecialchars($msg) . '</div>
            <div>File</div><div>' . htmlspecialchars($short) . '</div>
            <div>Line</div><div>' . $line . '</div>
            <div>Memory</div><div>' . $mem . ' (peak ' . $peak . ')</div>
            </div>
        </div>';

        if (!empty($trace)) {
            echo '<div class="sec"><div class="trace">';
            foreach($trace as $i => $t) {
                echo '<div>#' . $i . ' ' . htmlspecialchars(($t['file'] ?? '') . ' ' . ($t['line'] ?? '?')) . '</div>';
                echo '<div style="opacity:.8">' . htmlspecialchars(($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '')) . '</div>';
                echo '<div style="height:8px"></div>';
            }
            echo '</div></div>';
        }

        echo '</div></body></html>';
        exit;
    }
}
