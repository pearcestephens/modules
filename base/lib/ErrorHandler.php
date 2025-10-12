<?php
declare(strict_types=1);

namespace Modules\Base;

final class ErrorHandler
{
    /**
     * Register debug-aware error and exception handlers
     * 
     * @param bool $debug If true, shows detailed error messages
     */
    public static function register(bool $debug = false): void
    {
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_exception_handler(function (\Throwable $e) use ($debug) {
            http_response_code(500);
            
            $wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
                      || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
            
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => false,
                    'error' => $debug ? $e->getMessage() : 'Internal server error',
                    'file' => $debug ? $e->getFile() : null,
                    'line' => $debug ? $e->getLine() : null,
                    'trace' => $debug ? explode("\n", $e->getTraceAsString()) : null,
                    'time' => date('c'),
                ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
                echo '<h1>Internal Server Error</h1>';
                if ($debug) {
                    echo '<h2>Exception: ' . htmlspecialchars(get_class($e)) . '</h2>';
                    echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' <strong>Line:</strong> ' . $e->getLine() . '</p>';
                    echo '<h3>Stack Trace:</h3>';
                    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                } else {
                    echo '<p>An error occurred. Please contact support if this persists.</p>';
                }
                echo '</body></html>';
            }
            exit(1);
        });

        set_error_handler(function (int $severity, string $message, string $file, int $line) use ($debug) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }
}
