<?php
namespace Modules\Core;

final class ErrorHandler {
    public static function register(bool $displayErrors = false): void {
        ini_set('display_errors', $displayErrors ? '1' : '0');
        ini_set('log_errors', '1');
        
        set_exception_handler(function(\Throwable $e) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo "<h1>500 – Module Error</h1>";
            if (getenv('APP_DEBUG') === '1') {
                echo "<pre>" . htmlspecialchars((string)$e) . "</pre>";
            }
        });
    }
}
