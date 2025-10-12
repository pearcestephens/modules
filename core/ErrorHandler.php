<?php
/**
 * Enterprise Error Handler for CIS Modules
 * 
 * Provides comprehensive error handling with:
 * - Full stack traces with syntax highlighting
 * - Context-aware error display (dev vs production)
 * - Request/environment information
 * - SQL query debugging
 * - Performance metrics
 * - Log file integration
 * 
 * @package Modules\Core
 * @version 2.0.0
 * @since   2025-10-12
 */

declare(strict_types=1);

namespace Modules\Core;

use Throwable;

final class ErrorHandler
{
    private static bool $registered = false;
    private static bool $debugMode = false;
    private static string $logPath = '';
    private static array $context = [];
    
    /**
     * Register the error handler
     *
     * @param bool $debugMode Enable detailed error display
     * @param string $logPath Path to error log file
     * @return void
     */
    public static function register(bool $debugMode = false, string $logPath = ''): void
    {
        if (self::$registered) {
            return;
        }
        
        self::$debugMode = $debugMode;
        self::$logPath = $logPath ?: $_SERVER['DOCUMENT_ROOT'] . '/logs/php-errors.log';
        
        // PHP error reporting configuration
        error_reporting(E_ALL);
        ini_set('display_errors', $debugMode ? '1' : '0');
        ini_set('display_startup_errors', $debugMode ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::$logPath);
        
        // Register handlers
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$registered = true;
    }
    
    /**
     * Add context information for error reporting
     *
     * @param string $key Context key
     * @param mixed $value Context value
     * @return void
     */
    public static function addContext(string $key, $value): void
    {
        self::$context[$key] = $value;
    }
    
    /**
     * Handle uncaught exceptions
     *
     * @param Throwable $exception
     * @return void
     */
    public static function handleException(Throwable $exception): void
    {
        $errorId = self::generateErrorId();
        
        // Log the error
        self::logError($exception, $errorId);
        
        // Send appropriate response
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        
        if (self::$debugMode) {
            self::renderDebugScreen($exception, $errorId);
        } else {
            self::renderProductionScreen($exception, $errorId);
        }
        
        exit(1);
    }
    
    /**
     * Handle PHP errors
     *
     * @param int $severity Error severity
     * @param string $message Error message
     * @param string $file File where error occurred
     * @param int $line Line number
     * @return bool
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    
    /**
     * Handle fatal errors on shutdown
     *
     * @return void
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleException(
                new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                )
            );
        }
    }
    
    /**
     * Render detailed debug screen for development
     *
     * @param Throwable $exception
     * @param string $errorId
     * @return void
     */
    private static function renderDebugScreen(Throwable $exception, string $errorId): void
    {
        $trace = self::formatStackTrace($exception);
        $request = self::getRequestInfo();
        $environment = self::getEnvironmentInfo();
        $context = self::$context;
        
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error: <?= htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: #1a1a1a;
            color: #e0e0e0;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .error-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            padding: 30px;
            border-radius: 8px 8px 0 0;
            border-left: 5px solid #bd2130;
        }
        .error-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #fff;
        }
        .error-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: rgba(255,255,255,0.9);
            margin-top: 15px;
        }
        .error-meta span { display: flex; align-items: center; gap: 5px; }
        .error-meta code {
            background: rgba(0,0,0,0.3);
            padding: 3px 8px;
            border-radius: 3px;
            font-family: 'Monaco', 'Courier New', monospace;
        }
        .section {
            background: #2d2d2d;
            margin-bottom: 2px;
            border-radius: 0;
        }
        .error-header + .section { border-radius: 0; }
        .section:last-child { border-radius: 0 0 8px 8px; }
        .section-header {
            background: #353535;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 14px;
            color: #fff;
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-header:hover { background: #3d3d3d; }
        .section-header::after {
            content: '▼';
            font-size: 10px;
            transition: transform 0.2s;
        }
        .section-header.collapsed::after { transform: rotate(-90deg); }
        .section-content {
            padding: 20px 30px;
            max-height: 600px;
            overflow: auto;
        }
        .section-content.collapsed { display: none; }
        .stack-trace { font-size: 13px; }
        .stack-frame {
            background: #252525;
            margin-bottom: 15px;
            border-radius: 6px;
            border-left: 3px solid #6c757d;
            overflow: hidden;
        }
        .stack-frame.frame-0 { border-left-color: #dc3545; }
        .stack-frame-header {
            padding: 12px 15px;
            background: #2a2a2a;
            font-weight: 500;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .frame-number {
            background: #dc3545;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .frame-file {
            color: #61afef;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 12px;
        }
        .frame-line { color: #e5c07b; }
        .frame-function {
            color: #c678dd;
            font-family: 'Monaco', 'Courier New', monospace;
        }
        .code-preview {
            padding: 15px;
            background: #1e1e1e;
            overflow-x: auto;
        }
        .code-line {
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            padding: 2px 0;
            white-space: pre;
        }
        .code-line.highlight {
            background: rgba(220, 53, 69, 0.2);
            border-left: 3px solid #dc3545;
            padding-left: 10px;
            margin-left: -10px;
        }
        .line-number {
            display: inline-block;
            width: 50px;
            color: #6c757d;
            text-align: right;
            margin-right: 15px;
            user-select: none;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px 20px;
            font-size: 13px;
        }
        .info-label {
            color: #98c379;
            font-weight: 600;
        }
        .info-value {
            font-family: 'Monaco', 'Courier New', monospace;
            color: #e0e0e0;
            word-break: break-all;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-error { background: #dc3545; color: #fff; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-info { background: #17a2b8; color: #fff; }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
            font-family: 'Monaco', 'Courier New', monospace;
        }
        .error-id {
            font-size: 11px;
            color: rgba(255,255,255,0.7);
            margin-top: 10px;
        }
        .copy-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }
        .copy-btn:hover {
            background: rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-header">
            <h1>💥 <?= htmlspecialchars(get_class($exception), ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="error-meta">
                <span>
                    📁 <code><?= htmlspecialchars(self::shortenPath($exception->getFile()), ENT_QUOTES, 'UTF-8') ?></code>
                </span>
                <span>
                    📍 Line <code><?= $exception->getLine() ?></code>
                </span>
                <span>
                    🔢 Code <code><?= $exception->getCode() ?></code>
                </span>
            </div>
            <div class="error-id">Error ID: <?= htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="section">
            <div class="section-header" onclick="toggleSection(this)">
                🔍 Stack Trace (<?= count($trace) ?> frames)
            </div>
            <div class="section-content">
                <div class="stack-trace">
                    <?php foreach ($trace as $index => $frame): ?>
                        <div class="stack-frame frame-<?= $index ?>">
                            <div class="stack-frame-header">
                                <div>
                                    <span class="frame-number">#<?= $index ?></span>
                                    <?php if (!empty($frame['file'])): ?>
                                        <span class="frame-file"><?= htmlspecialchars(self::shortenPath($frame['file']), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="frame-line">:<?= $frame['line'] ?? '?' ?></span>
                                    <?php else: ?>
                                        <span class="frame-file">[internal function]</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if (!empty($frame['function'])): ?>
                                        <span class="frame-function">
                                            <?= htmlspecialchars($frame['class'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            <?= htmlspecialchars($frame['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            <?= htmlspecialchars($frame['function'], ENT_QUOTES, 'UTF-8') ?>()
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($frame['file']) && is_file($frame['file'])): ?>
                                <div class="code-preview">
                                    <?= self::renderCodePreview($frame['file'], $frame['line'] ?? 1) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($context)): ?>
        <div class="section">
            <div class="section-header" onclick="toggleSection(this)">
                📦 Context Information
            </div>
            <div class="section-content">
                <pre><?= htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
        </div>
        <?php endif; ?>

        <div class="section">
            <div class="section-header" onclick="toggleSection(this)">
                🌐 Request Information
            </div>
            <div class="section-content">
                <div class="info-grid">
                    <?php foreach ($request as $key => $value): ?>
                        <div class="info-label"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>:</div>
                        <div class="info-value"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-header collapsed" onclick="toggleSection(this)">
                ⚙️ Environment
            </div>
            <div class="section-content collapsed">
                <div class="info-grid">
                    <?php foreach ($environment as $key => $value): ?>
                        <div class="info-label"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>:</div>
                        <div class="info-value"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSection(header) {
            header.classList.toggle('collapsed');
            header.nextElementSibling.classList.toggle('collapsed');
        }
    </script>
</body>
</html>
        <?php
    }
    
    /**
     * Render simple production error screen
     *
     * @param Throwable $exception
     * @param string $errorId
     * @return void
     */
    private static function renderProductionScreen(Throwable $exception, string $errorId): void
    {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Error</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .error-container {
            background: #fff;
            padding: 60px 40px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            text-align: center;
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 32px;
            margin-bottom: 15px;
            color: #333;
        }
        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .error-id {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: #fff;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">😔</div>
        <h1>Oops! Something went wrong</h1>
        <p>We're sorry, but an unexpected error has occurred. Our team has been notified and we're working on fixing this issue.</p>
        <div class="error-id">
            <strong>Error Reference:</strong> <?= htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <a href="/" class="btn">← Back to Home</a>
    </div>
</body>
</html>
        <?php
    }
    
    /**
     * Format stack trace with detailed information
     *
     * @param Throwable $exception
     * @return array
     */
    private static function formatStackTrace(Throwable $exception): array
    {
        return $exception->getTrace();
    }
    
    /**
     * Render code preview around error line
     *
     * @param string $file File path
     * @param int $errorLine Error line number
     * @param int $contextLines Lines of context to show
     * @return string
     */
    private static function renderCodePreview(string $file, int $errorLine, int $contextLines = 5): string
    {
        if (!is_file($file)) {
            return '';
        }
        
        $lines = file($file);
        $start = max(0, $errorLine - $contextLines - 1);
        $end = min(count($lines), $errorLine + $contextLines);
        
        $html = '';
        for ($i = $start; $i < $end; $i++) {
            $lineNumber = $i + 1;
            $isErrorLine = $lineNumber === $errorLine;
            $class = $isErrorLine ? 'code-line highlight' : 'code-line';
            
            $html .= sprintf(
                '<div class="%s"><span class="line-number">%d</span>%s</div>',
                $class,
                $lineNumber,
                htmlspecialchars($lines[$i], ENT_QUOTES, 'UTF-8')
            );
        }
        
        return $html;
    }
    
    /**
     * Get request information
     *
     * @return array
     */
    private static function getRequestInfo(): array
    {
        return [
            'Method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'Query String' => $_SERVER['QUERY_STRING'] ?? '',
            'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'IP Address' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'Referer' => $_SERVER['HTTP_REFERER'] ?? 'N/A',
            'Timestamp' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Get environment information
     *
     * @return array
     */
    private static function getEnvironmentInfo(): array
    {
        return [
            'PHP Version' => PHP_VERSION,
            'OS' => PHP_OS,
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time'),
            'Memory Usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'Peak Memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
        ];
    }
    
    /**
     * Log error to file
     *
     * @param Throwable $exception
     * @param string $errorId
     * @return void
     */
    private static function logError(Throwable $exception, string $errorId): void
    {
        $logEntry = sprintf(
            "[%s] [%s] %s: %s in %s:%d\nStack trace:\n%s\nError ID: %s\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getCode(),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
            $errorId,
            str_repeat('-', 80)
        );
        
        error_log($logEntry, 3, self::$logPath);
    }
    
    /**
     * Generate unique error ID
     *
     * @return string
     */
    private static function generateErrorId(): string
    {
        return strtoupper(substr(md5((string)microtime(true) . (string)rand()), 0, 12));
    }
    
    /**
     * Shorten file path for display
     *
     * @param string $path
     * @return string
     */
    private static function shortenPath(string $path): string
    {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot !== '' && strpos($path, $docRoot) === 0) {
            return substr($path, strlen($docRoot));
        }
        return $path;
    }
}
