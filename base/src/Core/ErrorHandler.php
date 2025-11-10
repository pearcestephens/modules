<?php

/**
 * ErrorHandler Service - Exception and Error Handling.
 *
 * @version 2.0.0
 */

declare(strict_types=1);

namespace CIS\Base\Core;

use ErrorException;
use Throwable;

use function in_array;

use const E_ALL;
use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_DEPRECATED;
use const E_ERROR;
use const E_PARSE;
use const E_STRICT;

class ErrorHandler
{
    private Application $app;

    private Logger $logger;

    private bool $debug;

    /**
     * Create error handler instance.
     */
    public function __construct(Application $app)
    {
        $this->app    = $app;
        $this->logger = $app->make(Logger::class);
        $this->debug  = $app->config('app.debug', false);
    }

    /**
     * Register error and exception handlers.
     */
    public function register(): void
    {
        // Error handler
        set_error_handler([$this, 'handleError']);

        // Exception handler
        set_exception_handler([$this, 'handleException']);

        // Shutdown handler (for fatal errors)
        register_shutdown_function([$this, 'handleShutdown']);

        // Set error reporting
        if ($this->debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '1');  // 🔥 FORCE DISPLAY ERRORS EVEN IN PROD
        }
    }

    /**
     * Handle PHP errors.
     */
    public function handleError(
        int $level,
        string $message,
        string $file = '',
        int $line = 0,
    ): bool {
        if (!(error_reporting() & $level)) {
            return false;
        }

        // Convert to exception
        throw new ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * Handle uncaught exceptions.
     */
    public function handleException(Throwable $exception): void
    {
        try {
            // Log exception
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception::class,
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'trace'     => $exception->getTraceAsString(),
            ]);

            // Render error page
            $this->renderException($exception);
        } catch (Throwable $e) {
            // If error handling fails, show basic error
            $this->renderFallbackError($e);
        }
    }

    /**
     * Handle shutdown (fatal errors).
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $this->logger->critical('Fatal error: ' . $error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
            ]);

            if (!headers_sent()) {
                http_response_code(500);

                if ($this->debug) {
                    echo '<h1>Fatal Error</h1>';
                    echo "<p><strong>Message:</strong> {$error['message']}</p>";
                    echo "<p><strong>File:</strong> {$error['file']}</p>";
                    echo "<p><strong>Line:</strong> {$error['line']}</p>";
                } else {
                    echo '<h1>500 Internal Server Error</h1>';
                    echo '<p>The application encountered an error.</p>';
                }
            }
        }
    }

    /**
     * Render exception page.
     */
    private function renderException(Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code($this->getStatusCode($exception));
            header('Content-Type: text/html; charset=UTF-8');
        }

        if ($this->debug) {
            $this->renderDebugPage($exception);
        } else {
            $this->renderProductionPage($exception);
        }
    }

    /**
     * Get HTTP status code from exception.
     */
    private function getStatusCode(Throwable $exception): int
    {
        // Check if exception has getStatusCode method (HTTP exceptions)
        if (method_exists($exception, 'getStatusCode')) {
            return $exception->getStatusCode();
        }

        return 500;
    }

    /**
     * Render debug error page (development).
     */
    private function renderDebugPage(Throwable $exception): void
    {
        $class   = $exception::class;
        $message = $exception->getMessage();
        $file    = $exception->getFile();
        $line    = $exception->getLine();
        $trace   = $exception->getTraceAsString();

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - {$class}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .error-box { background: white; border-left: 4px solid #dc2626; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #dc2626; font-size: 24px; margin-bottom: 20px; }
        .message { background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: 4px; margin-bottom: 20px; color: #991b1b; }
        .details { margin-bottom: 20px; }
        .details strong { color: #374151; display: inline-block; width: 80px; }
        .details span { color: #6b7280; }
        .trace { background: #1f2937; color: #f9fafb; padding: 20px; border-radius: 4px; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-box">
            <h1>{$class}</h1>
            <div class="message">{$message}</div>
            <div class="details">
                <strong>File:</strong> <span>{$file}</span><br>
                <strong>Line:</strong> <span>{$line}</span>
            </div>
            <h2 style="color: #374151; font-size: 18px; margin-bottom: 10px;">Stack Trace</h2>
            <div class="trace">{$trace}</div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render production error page (minimal info).
     */
    private function renderProductionPage(Throwable $exception): void
    {
        $statusCode = $this->getStatusCode($exception);

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error {$statusCode}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .error-box { background: white; border-radius: 8px; padding: 60px 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        h1 { color: #374151; font-size: 72px; margin-bottom: 20px; }
        h2 { color: #6b7280; font-size: 24px; margin-bottom: 10px; font-weight: normal; }
        p { color: #9ca3af; margin-bottom: 30px; }
        a { display: inline-block; background: #2563eb; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; transition: background 0.3s; }
        a:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>{$statusCode}</h1>
        <h2>Something went wrong</h2>
        <p>We're sorry, but something went wrong. Please try again later.</p>
        <a href="/">Go Home</a>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render fallback error (when error handling itself fails).
     */
    private function renderFallbackError(Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        echo '<h1>500 Internal Server Error</h1>';
        echo '<p>A critical error occurred.</p>';

        if ($this->debug) {
            echo '<pre>' . $exception->getMessage() . '</pre>';
        }
    }
}
