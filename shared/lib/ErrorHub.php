<?php
declare(strict_types=1);

namespace CIS\Shared;

/**
 * CIS Error Hub - Enterprise Error Handling System
 * 
 * Features:
 * - Beautiful HTML error pages with full stack traces
 * - JSON error responses for API/AJAX requests
 * - Copy buttons for easy debugging
 * - Production-safe (hides details in production)
 * - Comprehensive logging
 * - Memory usage tracking
 * 
 * @package CIS\Shared
 * @version 2.0.0
 * @author Ecigdis Limited
 */
final class ErrorHub
{
    private const MB = 1048576;
    
    /**
     * Register all error handlers
     */
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        set_error_handler([self::class, 'onError']);
        set_exception_handler([self::class, 'onException']);
        register_shutdown_function([self::class, 'onShutdown']);
    }
    
    /**
     * Handle PHP errors
     */
    public static function onError(int $errno, string $errstr, string $file, int $line): bool
    {
        self::logLine(self::format($errno, $errstr, $file, $line));
        
        if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            self::render(500, self::typeName($errno), $errstr, $file, $line);
        }
        
        return true; // We handled it
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function onException(\Throwable $e): void
    {
        self::logLine(self::format(E_ERROR, $e->getMessage(), $e->getFile(), $e->getLine()));
        self::render(500, 'Fatal Error', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTrace());
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public static function onShutdown(): void
    {
        $last = error_get_last();
        if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::logLine(self::format($last['type'], $last['message'], $last['file'], $last['line']));
            self::render(500, self::typeName($last['type']), $last['message'], $last['file'], $last['line']);
        }
    }
    
    /**
     * Log error to file
     */
    private static function logLine(string $line): void
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/cis-errors.log';
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Format error message for logging
     */
    private static function format(int $type, string $message, string $file, int $line): string
    {
        return sprintf(
            '[%s] [%s] %s in %s:%d',
            date('Y-m-d H:i:s'),
            self::typeName($type),
            $message,
            $file,
            $line
        );
    }
    
    /**
     * Get human-readable error type name
     */
    public static function typeName(int $errno): string
    {
        return [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ][$errno] ?? 'Unknown';
    }
    
    /**
     * Detect if request expects JSON response
     */
    private static function isJsonRequest(): bool
    {
        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }
        
        // Check if AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        
        // Check if API endpoint (has ?action= or /api/ in URL)
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos($uri, '?action=') !== false || stripos($uri, '/api/') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if we're in production environment
     */
    private static function isProduction(): bool
    {
        // Check if debug mode is explicitly enabled
        if (defined('APP_DEBUG') && APP_DEBUG === true) {
            return false;
        }
        
        // Check environment
        if (defined('APP_ENV') && APP_ENV === 'production') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Render error (HTML or JSON based on request type)
     */
    private static function render(
        int $code,
        string $type,
        string $msg,
        string $file,
        int $line,
        array $trace = []
    ): void {
        if (!headers_sent()) {
            http_response_code($code);
        }
        
        // Determine output format
        if (self::isJsonRequest()) {
            self::renderJson($code, $type, $msg, $file, $line, $trace);
        } else {
            self::renderHtml($code, $type, $msg, $file, $line, $trace);
        }
        
        exit;
    }
    
    /**
     * Render JSON error response
     */
    private static function renderJson(
        int $code,
        string $type,
        string $msg,
        string $file,
        int $line,
        array $trace = []
    ): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        
        $isProduction = self::isProduction();
        
        $error = [
            'success' => false,
            'error' => [
                'type' => $type,
                'message' => $isProduction ? 'An internal server error occurred' : $msg,
                'code' => $code,
                'http_code' => $code
            ],
            'meta' => [
                'timestamp' => date('c'),
                'request_id' => uniqid('err_', true),
                'environment' => $isProduction ? 'production' : 'development'
            ]
        ];
        
        // Add debug info in development
        if (!$isProduction) {
            $error['debug'] = [
                'file' => str_replace($_SERVER['DOCUMENT_ROOT'], '', $file),
                'line' => $line,
                'trace' => self::formatTraceForJson($trace),
                'memory' => [
                    'current' => round(memory_get_usage(true) / self::MB, 2) . ' MB',
                    'peak' => round(memory_get_peak_usage(true) / self::MB, 2) . ' MB'
                ],
                'request' => [
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
                    'uri' => $_SERVER['REQUEST_URI'] ?? '/',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]
            ];
        }
        
        echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Format stack trace for JSON output
     */
    private static function formatTraceForJson(array $trace): array
    {
        $formatted = [];
        foreach ($trace as $i => $t) {
            $formatted[] = [
                'index' => $i,
                'file' => isset($t['file']) ? str_replace($_SERVER['DOCUMENT_ROOT'], '', $t['file']) : 'unknown',
                'line' => $t['line'] ?? 0,
                'function' => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''),
                'args' => isset($t['args']) ? count($t['args']) : 0
            ];
        }
        return $formatted;
    }
    
    /**
     * Render HTML error page
     */
    private static function renderHtml(
        int $code,
        string $type,
        string $msg,
        string $file,
        int $line,
        array $trace = []
    ): void {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        $isProduction = self::isProduction();
        
        if ($isProduction) {
            echo self::renderProductionHtml();
            return;
        }
        
        // Development mode - show full details
        $short = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);
        $mem = number_format(memory_get_usage(true) / self::MB, 2) . ' MB';
        $peak = number_format(memory_get_peak_usage(true) / self::MB, 2) . ' MB';
        $uri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/');
        $timestamp = date('Y-m-d H:i:s');
        
        // Prepare full error text for copying
        $copyText = self::formatErrorForCopy($type, $msg, $file, $line, $trace, $mem, $peak);
        
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($type) ?> - CIS Error</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: #f5f5f7;
            color: #1d1d1f;
            padding: 20px;
            line-height: 1.6;
        }
        .card {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .hd {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: #fff;
            padding: 20px 24px;
        }
        .hd strong {
            font-size: 24px;
            display: block;
            margin-bottom: 8px;
        }
        .hd-sub {
            opacity: 0.9;
            font-size: 13px;
            font-family: ui-monospace, monospace;
        }
        .sec {
            padding: 20px 24px;
            border-top: 1px solid #e5e7eb;
        }
        .sec:first-of-type {
            border-top: none;
        }
        .sec-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 12px;
            color: #374151;
        }
        .mono {
            font: 13px/1.5 ui-monospace, Menlo, Monaco, Consolas, monospace;
            background: #fef2f2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .trace {
            background: #1f2937;
            color: #e5e7eb;
            padding: 16px;
            border-radius: 8px;
            max-height: 400px;
            overflow: auto;
            font: 12px/1.6 ui-monospace, monospace;
        }
        .trace-item {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #374151;
        }
        .trace-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .trace-file {
            color: #60a5fa;
        }
        .trace-func {
            color: #fbbf24;
            opacity: 0.9;
            font-size: 11px;
        }
        .kv {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 12px;
            margin-top: 12px;
        }
        .kv-label {
            font-weight: 600;
            color: #6b7280;
        }
        .btn {
            display: inline-block;
            padding: 10px 18px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #2563eb;
        }
        .btn-group {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            background: #ef4444;
            color: #fff;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .copy-success {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 1000;
        }
        .copy-success.show {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="hd">
            <strong>🚨 System Error <span class="badge"><?= htmlspecialchars($type) ?></span></strong>
            <div class="hd-sub"><?= $timestamp ?> • <?= $uri ?></div>
        </div>
        
        <div class="sec">
            <div class="sec-title">Error Details</div>
            <div class="kv">
                <div class="kv-label">Type</div>
                <div><?= htmlspecialchars($type) ?></div>
                
                <div class="kv-label">Message</div>
                <div class="mono"><?= htmlspecialchars($msg) ?></div>
                
                <div class="kv-label">File</div>
                <div><?= htmlspecialchars($short) ?></div>
                
                <div class="kv-label">Line</div>
                <div><?= $line ?></div>
                
                <div class="kv-label">Memory Usage</div>
                <div><?= $mem ?> (peak <?= $peak ?>)</div>
            </div>
            
            <div class="btn-group">
                <button class="btn" onclick="copyError()">📋 Copy Full Error</button>
                <button class="btn" onclick="copyTrace()">📋 Copy Stack Trace</button>
            </div>
        </div>
        
        <?php if (!empty($trace)): ?>
        <div class="sec">
            <div class="sec-title">Stack Trace</div>
            <div class="trace" id="trace-content">
<?php foreach ($trace as $i => $t): ?>
<div class="trace-item">
<div><strong>#<?= $i ?></strong> <span class="trace-file"><?= htmlspecialchars(($t['file'] ?? 'unknown') . ':' . ($t['line'] ?? '?')) ?></span></div>
<div class="trace-func"><?= htmlspecialchars(($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '')) ?></div>
</div>
<?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="copy-success" id="copy-success">✓ Copied to clipboard!</div>
    
    <textarea id="copy-buffer" style="position:absolute;left:-9999px;"></textarea>
    
    <script>
        const fullError = <?= json_encode($copyText, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const traceOnly = document.getElementById('trace-content')?.innerText || '';
        
        function copyToClipboard(text) {
            const buffer = document.getElementById('copy-buffer');
            buffer.value = text;
            buffer.select();
            document.execCommand('copy');
            
            const success = document.getElementById('copy-success');
            success.classList.add('show');
            setTimeout(() => success.classList.remove('show'), 2000);
        }
        
        function copyError() {
            copyToClipboard(fullError);
        }
        
        function copyTrace() {
            copyToClipboard(traceOnly);
        }
    </script>
</body>
</html>
        <?php
    }
    
    /**
     * Render simple production error page
     */
    private static function renderProductionHtml(): string
    {
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Error</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f5f5f7; padding: 40px; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc2626; margin-bottom: 16px; }
        p { color: #6b7280; line-height: 1.6; }
        a { color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ Unexpected Error</h1>
        <p>We're sorry, but something went wrong. Our team has been notified and is working to fix the issue.</p>
        <p><a href="/">← Return to homepage</a></p>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Format error for copy/paste
     */
    private static function formatErrorForCopy(
        string $type,
        string $msg,
        string $file,
        int $line,
        array $trace,
        string $mem,
        string $peak
    ): string {
        $output = [];
        $output[] = "=== CIS ERROR REPORT ===";
        $output[] = "Timestamp: " . date('Y-m-d H:i:s');
        $output[] = "Type: $type";
        $output[] = "Message: $msg";
        $output[] = "File: $file";
        $output[] = "Line: $line";
        $output[] = "Memory: $mem (peak $peak)";
        $output[] = "URI: " . ($_SERVER['REQUEST_URI'] ?? '/');
        $output[] = "";
        $output[] = "=== STACK TRACE ===";
        
        foreach ($trace as $i => $t) {
            $output[] = "#$i " . ($t['file'] ?? 'unknown') . ':' . ($t['line'] ?? '?');
            $output[] = "   " . ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '');
        }
        
        return implode("\n", $output);
    }
}
