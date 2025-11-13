<?php
declare(strict_types=1);

namespace CIS\Base;

/**
 * SecretRedactor: ensures no raw secret values are written to logs.
 * Strategy: build SHA256 hash map & direct value replacement.
 */
final class SecretRedactor
{
    /** @var array<string,string> hash => value */
    private array $secretValues = [];
    /** @var array<string,string> value => placeholder */
    private array $placeholders = [];

    public function __construct(array $sensitiveKeys)
    {
        foreach ($sensitiveKeys as $key) {
            $val = getenv($key);
            if ($val && strlen($val) >= 8) {
                $hash = hash('sha256', $val);
                $this->secretValues[$hash] = $val;
                $this->placeholders[$val] = '[REDACTED:' . $key . ']';
            }
        }
    }

    public function redact(string $message): string
    {
        foreach ($this->placeholders as $raw => $mask) {
            if (strpos($message, $raw) !== false) {
                $message = str_replace($raw, $mask, $message);
            }
        }
        return $message;
    }
}
