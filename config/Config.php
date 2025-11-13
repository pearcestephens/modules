<?php
declare(strict_types=1);

namespace CIS\Config;

/**
 * Central configuration loader with caching & redaction support.
 * Only reads from environment; separation of secrets vs non-secrets.
 */
final class Config
{
    /** @var array<string,string> */
    private static array $cache = [];

    /** @var array<string,bool> */
    private static array $sensitive = [
        'DB_PASSWORD' => true,
        'VEND_ACCESS_TOKEN' => true,
        'VEND_REFRESH_TOKEN' => true,
        'VEND_CLIENT_SECRET' => true,
        'SENDGRID_API_KEY' => true,
        'OPENAI_API_KEY' => true,
        'GOOGLE_MAPS_API_KEY' => true,
        'DEPUTY_ACCESS_TOKEN' => true,
        'ENCRYPTION_KEY' => true,
        'SESSION_SECRET' => true,
        'JWT_SECRET' => true,
    ];

    /** Get a config value (required unless default supplied). */
    public static function get(string $key, ?string $default = null): string
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $val = getenv($key);
        if ($val === false || $val === '') {
            if ($default !== null) {
                self::$cache[$key] = $default;
                return $default;
            }
            throw new \RuntimeException("Missing required env key: {$key}");
        }
        self::$cache[$key] = $val;
        return $val;
    }

    /** Return a safe array of currently cached values (redacting sensitive). */
    public static function safeSnapshot(): array
    {
        $out = [];
        foreach (self::$cache as $k => $v) {
            $out[$k] = isset(self::$sensitive[$k]) ? '[REDACTED]' : $v;
        }
        return $out;
    }

    /** Identify whether key is sensitive. */
    public static function isSensitive(string $key): bool
    {
        return isset(self::$sensitive[$key]);
    }
}
