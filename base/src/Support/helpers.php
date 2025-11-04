<?php
/**
 * Global Helper Functions
 *
 * @package CIS\Base\Support
 * @version 2.0.0
 */

if (!function_exists('app')) {
    /**
     * Get application instance or service from container
     */
    function app(?string $service = null) {
        $app = \CIS\Base\Core\Application::getInstance();
        return $service ? $app->make($service) : $app;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config(string $key, $default = null) {
        return app()->config($key, $default);
    }
}

if (!function_exists('view')) {
    /**
     * Render a view template
     */
    function view(string $template, array $data = []): string {
        $engine = app(\CIS\Base\View\TemplateEngine::class);
        return $engine->render($template, $data);
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to URL
     */
    function redirect(string $url, int $code = 302): void {
        header("Location: {$url}", true, $code);
        exit;
    }
}

if (!function_exists('back')) {
    /**
     * Redirect back to previous page
     */
    function back(): void {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('session')) {
    /**
     * Get/set session value
     */
    function session(?string $key = null, $default = null) {
        if ($key === null) {
            return $_SESSION;
        }

        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        }

        return $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Flash message for next request
     */
    function flash(string $type, string $message): void {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }

        if (!isset($_SESSION['flash_messages'][$type])) {
            $_SESSION['flash_messages'][$type] = [];
        }

        $_SESSION['flash_messages'][$type][] = $message;
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value
     */
    function old(string $key, $default = null) {
        return $_SESSION['old_input'][$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get CSRF token
     */
    function csrf_token(): string {
        return $_SESSION['csrf_token'] ?? '';
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF hidden input field
     */
    function csrf_field(): string {
        $token = csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }
}

if (!function_exists('method_field')) {
    /**
     * Generate method spoofing field
     */
    function method_field(string $method): string {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset(string $path): string {
        $baseUrl = config('assets.url', '/modules/base/public/assets');
        $version = config('assets.version', '2.0.0');
        return $baseUrl . '/' . ltrim($path, '/') . '?v=' . $version;
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     */
    function url(string $path = ''): string {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/' . ltrim($path, '/');
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die
     */
    function dd(...$vars): void {
        echo '<pre style="background: #1F2937; color: #F9FAFB; padding: 20px; margin: 20px; border-radius: 8px;">';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
        die(1);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump variable
     */
    function dump(...$vars): void {
        echo '<pre style="background: #1F2937; color: #F9FAFB; padding: 20px; margin: 20px; border-radius: 8px;">';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable
     */
    function env(string $key, $default = null) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;

        // Convert string boolean values
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true' || $lower === '(true)') {
                return true;
            } elseif ($lower === 'false' || $lower === '(false)') {
                return false;
            } elseif ($lower === 'null' || $lower === '(null)') {
                return null;
            }
        }

        return $value;
    }
}

if (!function_exists('now')) {
    /**
     * Get current datetime
     */
    function now(string $format = 'Y-m-d H:i:s'): string {
        return date($format);
    }
}

if (!function_exists('today')) {
    /**
     * Get today's date
     */
    function today(string $format = 'Y-m-d'): string {
        return date($format);
    }
}

if (!function_exists('str_limit')) {
    /**
     * Limit string length
     */
    function str_limit(string $value, int $limit = 100, string $end = '...'): string {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }
}

if (!function_exists('str_slug')) {
    /**
     * Generate URL slug
     */
    function str_slug(string $value, string $separator = '-'): string {
        $value = preg_replace('/[^\p{L}\p{N}\s-]/u', '', strtolower($value));
        $value = preg_replace('/[\s-]+/', $separator, $value);
        return trim($value, $separator);
    }
}

if (!function_exists('array_get')) {
    /**
     * Get array value using dot notation
     */
    function array_get(array $array, string $key, $default = null) {
        $keys = explode('.', $key);

        foreach ($keys as $k) {
            if (!is_array($array) || !array_key_exists($k, $array)) {
                return $default;
            }
            $array = $array[$k];
        }

        return $array;
    }
}

if (!function_exists('array_set')) {
    /**
     * Set array value using dot notation
     */
    function array_set(array &$array, string $key, $value): void {
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }
}
