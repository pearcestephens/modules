<?php
declare(strict_types=1);

namespace Modules\Shared;

use Throwable;

final class Kernel
{
    public static function boot(): void
    {
        // Load main app once for sessions, config, cis_pdo
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($docRoot === '') {
            $docRoot = dirname(__DIR__, 4); // fallback
        }

        $appFile = $docRoot . '/app.php';
        if (!file_exists($appFile)) {
            $alt = dirname($docRoot) . '/private_html/app.php';
            if (file_exists($alt)) {
                $appFile = $alt;
            }
        }
        if (file_exists($appFile)) {
            // Load app.php with error handling for parse errors in config
            $originalErrorHandler = set_error_handler(function($severity, $message, $file, $line) {
                // Convert parse errors to exceptions we can catch
                if ($severity & (E_PARSE | E_ERROR | E_COMPILE_ERROR | E_CORE_ERROR)) {
                    throw new \ErrorException($message, 0, $severity, $file, $line);
                }
                return false; // Let default handler take over for other errors
            });
            
            try {
                require_once $appFile;
            } catch (\Throwable $e) {
                // If app.php fails to load due to config issues, continue without it
                // This allows the module to work even when main CIS config has issues
                error_log("Module Kernel: Bypassing app.php due to error - " . $e->getMessage());
                
                // Ensure basic session start for auth if not already active
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
            } finally {
                // Restore original error handler
                if ($originalErrorHandler !== null) {
                    set_error_handler($originalErrorHandler);
                } else {
                    restore_error_handler();
                }
            }
        }

        // Security headers
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');

        // DB TZ if set
        if (!empty($_ENV['DB_TZ'])) {
            try {
                if (function_exists('cis_pdo')) {
                    $pdo = cis_pdo();
                    $stmt = $pdo->prepare('SET time_zone = :tz');
                    $stmt->execute([':tz' => (string)$_ENV['DB_TZ']]);
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        // Session CSRF bootstrap
        if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // PSR-4 autoload for module namespaces
        spl_autoload_register(static function (string $class): void {
            $prefixes = [
                'Modules\\Shared\\' => __DIR__ . '/',
                'Modules\\Consignments\\' => dirname(__DIR__, 2) . '/consignments/',
            ];
            foreach ($prefixes as $prefix => $baseDir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    continue;
                }
                $relative = substr($class, $len);
                $relativePath = str_replace('\\', '/', $relative) . '.php';
                $file = rtrim($baseDir, '/') . '/' . $relativePath;
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        });

        // Auth gate except for JSON API when BOT_BYPASS_AUTH is true
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $isApi = (str_contains($uri, '/modules/consignments/') && str_contains($uri, '/api/'));
        $bypass = !empty($_ENV['BOT_BYPASS_AUTH']) && ($_ENV['BOT_BYPASS_AUTH'] === '1' || strtolower((string)$_ENV['BOT_BYPASS_AUTH']) === 'true');
        if (!$bypass && session_status() === PHP_SESSION_ACTIVE) {
            $userId = $_SESSION['userID'] ?? null;
            if (empty($userId)) {
                $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
                $isJson = stripos((string)$contentType, 'application/json') !== false;
                if ($isApi && $isJson) {
                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
                    exit;
                }
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $redirect = $scheme . '://' . $host . $uri;
                header('Location: /login.php?redirect=' . rawurlencode($redirect));
                exit;
            }
        }
    }
}
