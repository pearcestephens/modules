<?php
/**
 * Global Error & Exception Handler
 * 
 * Provides pretty error pages for browser and JSON responses for AJAX
 */

namespace CIS\Base;

class ErrorHandler {
    private static $initialized = false;
    private static $debugMode = false;
    
    /**
     * Initialize error handlers
     */
    public static function init(bool $debug = false): void {
        if (self::$initialized) {
            return;
        }
        
        self::$debugMode = $debug;
        
        // Set error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        
        // Set error handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$initialized = true;
    }
    
    /**
     * Check if request is AJAX
     */
    private static function isAjaxRequest(): bool {
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) || (
            isset($_SERVER['CONTENT_TYPE']) && 
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
        ) || (
            isset($_SERVER['HTTP_ACCEPT']) && 
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        );
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($errno, $errstr, $errfile, $errline): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Log error
        $error = sprintf(
            "[%s] PHP Error [%d]: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $errno,
            $errstr,
            $errfile,
            $errline
        );
        error_log($error);
        
        // Check if AJAX request
        if (self::isAjaxRequest()) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
                self::renderJsonError('PHP Error', $errstr, $errfile, $errline, $errno);
                exit;
            }
        } else {
            // Show pretty error page for browser
            if (!headers_sent()) {
                http_response_code(500);
                self::renderErrorPage('PHP Error', $errstr, $errfile, $errline, $errno);
                exit;
            }
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception): void {
        // Log exception
        $error = sprintf(
            "[%s] Uncaught Exception [%s]: %s in %s on line %d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        error_log($error);
        
        // Check if AJAX request
        if (self::isAjaxRequest()) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
                self::renderJsonError(
                    'Uncaught Exception',
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine(),
                    get_class($exception)
                );
            }
        } else {
            // Show pretty error page for browser
            if (!headers_sent()) {
                http_response_code(500);
                self::renderErrorPage(
                    'Uncaught Exception',
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine(),
                    get_class($exception),
                    $exception->getTrace()
                );
            } else {
                echo "<h1>Fatal Error</h1><p>An unexpected error occurred.</p>";
            }
        }
        exit;
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Log fatal error
            $errorLog = sprintf(
                "[%s] Fatal Error [%d]: %s in %s on line %d",
                date('Y-m-d H:i:s'),
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
            error_log($errorLog);
            
            // Clean any output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Check if AJAX request
            if (self::isAjaxRequest()) {
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: application/json');
                    self::renderJsonError(
                        'Fatal Error',
                        $error['message'],
                        $error['file'],
                        $error['line'],
                        $error['type']
                    );
                }
            } else {
                // Show pretty error page for browser
                if (!headers_sent()) {
                    http_response_code(500);
                    self::renderErrorPage(
                        'Fatal Error',
                        $error['message'],
                        $error['file'],
                        $error['line'],
                        $error['type']
                    );
                }
            }
        }
    }
    
    /**
     * Render JSON error for AJAX
     */
    private static function renderJsonError(string $title, string $message, string $file, int $line, $code = null): void {
        $response = [
            'success' => false,
            'error' => self::$debugMode ? $message : 'An unexpected error occurred. Please try again later.',
            'error_title' => $title
        ];
        
        if (self::$debugMode) {
            $response['error_details'] = sprintf(
                "File: %s\nLine: %d\nCode: %s",
                $file,
                $line,
                $code ?? 'N/A'
            );
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
    
    /**
     * Render pretty error page
     */
    private static function renderErrorPage(
        string $title,
        string $message,
        string $file,
        int $line,
        $code = null,
        array $trace = []
    ): void {
        // Clean message for production
        $safeMessage = self::$debugMode ? $message : 'An unexpected error occurred. Please try again later.';
        $showDetails = self::$debugMode;
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error - CIS Staff Portal</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .error-container {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    max-width: 800px;
                    width: 100%;
                    overflow: hidden;
                }
                .error-header {
                    background: #dc3545;
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .error-header h1 {
                    font-size: 2em;
                    margin-bottom: 10px;
                }
                .error-code {
                    font-size: 4em;
                    font-weight: bold;
                    opacity: 0.9;
                    margin-bottom: 10px;
                }
                .error-body {
                    padding: 30px;
                }
                .error-message {
                    background: #f8f9fa;
                    border-left: 4px solid #dc3545;
                    padding: 15px 20px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                }
                .error-message p {
                    color: #333;
                    line-height: 1.6;
                    font-size: 1.1em;
                }
                .error-details {
                    background: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                    padding: 15px;
                    margin-bottom: 20px;
                    font-family: 'Courier New', monospace;
                    font-size: 0.9em;
                }
                .error-details-title {
                    font-weight: bold;
                    color: #495057;
                    margin-bottom: 10px;
                }
                .error-file {
                    color: #856404;
                    background: #fff3cd;
                    padding: 8px 12px;
                    border-radius: 4px;
                    margin: 5px 0;
                    word-break: break-all;
                }
                .error-trace {
                    background: #f8f9fa;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                    padding: 15px;
                    margin-top: 20px;
                    max-height: 400px;
                    overflow-y: auto;
                }
                .error-trace pre {
                    color: #495057;
                    font-size: 0.85em;
                    line-height: 1.5;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                }
                .error-actions {
                    display: flex;
                    gap: 10px;
                    margin-top: 20px;
                }
                .btn {
                    padding: 12px 24px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: 500;
                    display: inline-block;
                    transition: all 0.3s;
                }
                .btn-primary {
                    background: #667eea;
                    color: white;
                }
                .btn-primary:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }
                .btn-secondary {
                    background: #6c757d;
                    color: white;
                }
                .btn-secondary:hover {
                    background: #5a6268;
                }
                .debug-badge {
                    display: inline-block;
                    background: #ffc107;
                    color: #856404;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 0.8em;
                    font-weight: bold;
                    margin-left: 10px;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-header">
                    <div class="error-code">500</div>
                    <h1><?= htmlspecialchars($title) ?></h1>
                    <?php if ($showDetails): ?>
                        <span class="debug-badge">DEBUG MODE</span>
                    <?php endif; ?>
                </div>
                
                <div class="error-body">
                    <div class="error-message">
                        <p><?= htmlspecialchars($safeMessage) ?></p>
                    </div>
                    
                    <?php if ($showDetails): ?>
                        <div class="error-details">
                            <div class="error-details-title">Error Details:</div>
                            <?php if ($code): ?>
                                <div><strong>Code:</strong> <?= htmlspecialchars($code) ?></div>
                            <?php endif; ?>
                            <div class="error-file">
                                <strong>File:</strong> <?= htmlspecialchars($file) ?><br>
                                <strong>Line:</strong> <?= $line ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($trace)): ?>
                            <div class="error-trace">
                                <div class="error-details-title">Stack Trace:</div>
                                <pre><?php
                                    foreach ($trace as $i => $t) {
                                        $traceFile = $t['file'] ?? 'unknown';
                                        $traceLine = $t['line'] ?? 0;
                                        $traceFunction = $t['function'] ?? 'unknown';
                                        $traceClass = isset($t['class']) ? $t['class'] . $t['type'] : '';
                                        
                                        echo sprintf(
                                            "#%d %s%s() called at [%s:%d]\n",
                                            $i,
                                            htmlspecialchars($traceClass),
                                            htmlspecialchars($traceFunction),
                                            htmlspecialchars($traceFile),
                                            $traceLine
                                        );
                                    }
                                ?></pre>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #6c757d; text-align: center; margin: 20px 0;">
                            This error has been logged and will be reviewed by our team.
                        </p>
                    <?php endif; ?>
                    
                    <div class="error-actions">
                        <a href="javascript:history.back()" class="btn btn-secondary">← Go Back</a>
                        <a href="/" class="btn btn-primary">🏠 Go to Dashboard</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
