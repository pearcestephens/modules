<?php
/**
 * File: config/env-loader.php
 * Purpose: Bootstrap environment variables from the project .env file and expose the env() helper.
 * Author: GitHub Copilot
 * Last Modified: 2025-11-01
 * Dependencies: Standard PHP extensions only.
 */

declare(strict_types=1);

if (!function_exists('loadEnvFromFile')) {
    /**
     * Hydrate environment variables from a dotenv-style file.
     *
     * @param string $filePath
     * @return void
     */
    function loadEnvFromFile(string $filePath): void
    {
        static $loaded = false;

        if ($loaded || !is_file($filePath) || !is_readable($filePath)) {
            $loaded = true;
            return;
        }

        $loaded = true;
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return;
        }

        $lines = preg_split('/\r?\n/', $contents) ?: [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!str_contains($trimmed, '=')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $trimmed, 2));

            if ($name === '') {
                continue;
            }

            $value = normalizeEnvValue($value);

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv(sprintf('%s=%s', $name, $value));
        }
    }
}

if (!function_exists('normalizeEnvValue')) {
    /**
     * Normalise dotenv scalar values into native PHP types.
     *
     * @param string $value
     * @return mixed
     */
    function normalizeEnvValue(string $value): mixed
    {
        $unwrapped = $value;

        if ($unwrapped !== '' && ($unwrapped[0] === '"' || $unwrapped[0] === '\'')) {
            $quote = $unwrapped[0];
            $unwrapped = trim($unwrapped, $quote);
        }

        $lower = strtolower($unwrapped);

        return match ($lower) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => is_numeric($unwrapped) ? (str_contains($unwrapped, '.') ? (float)$unwrapped : (int)$unwrapped) : $unwrapped,
        };
    }
}

if (!function_exists('env')) {
    /**
     * Retrieve an environment value with optional default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        static $bootstrapped = false;

        if (!$bootstrapped) {
            $bootstrapped = true;
            $projectRoot = dirname(__DIR__);
            loadEnvFromFile($projectRoot . '/.env');
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}
