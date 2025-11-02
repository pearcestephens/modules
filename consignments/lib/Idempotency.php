<?php
declare(strict_types=1);

namespace Consignments\Lib;

final class Idempotency
{
    /** Deterministic sha256 over method, path, and normalized payload. */
    public static function hashFor(string $method, string $path, array $payload = []): string
    {
        $method = strtoupper($method);
        ksort($payload);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $method.'|'.$path.'|'.$body);
    }
}
