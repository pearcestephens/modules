<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Lib;

/**
 * PII Redactor
 *
 * Redacts Personally Identifiable Information from logs and API responses
 *
 * Protects:
 * - Email addresses
 * - Bank account numbers
 * - Phone numbers
 * - Physical addresses
 * - Tax file numbers / IRD numbers
 *
 * @package HumanResources\Payroll\Lib
 * @version 1.0.0
 */
class PiiRedactor
{
    /** PII field patterns */
    private const PII_FIELDS = [
        'email',
        'email_address',
        'phone',
        'phone_number',
        'mobile',
        'bank_account',
        'account_number',
        'ird_number',
        'tax_number',
        'address',
        'street',
        'home_address',
        'password',
        'token',
        'api_key',
        'secret'
    ];

    /**
     * Redact PII from array (recursive)
     *
     * @param array $data Data to redact
     * @param bool $preservePartial Keep first 2 chars for debugging (e.g., em***@***.nz)
     * @return array Redacted data
     */
    public static function redactArray(array $data, bool $preservePartial = true): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            // Check if key is a PII field
            $isPii = false;
            foreach (self::PII_FIELDS as $piiField) {
                if (str_contains($lowerKey, $piiField)) {
                    $isPii = true;
                    break;
                }
            }

            if ($isPii) {
                // Redact PII field
                if (is_string($value)) {
                    $redacted[$key] = $preservePartial
                        ? self::redactPartial($value, $lowerKey)
                        : '[REDACTED]';
                } else {
                    $redacted[$key] = '[REDACTED]';
                }
            } elseif (is_array($value)) {
                // Recursively redact nested arrays
                $redacted[$key] = self::redactArray($value, $preservePartial);
            } else {
                // Keep non-PII data
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Redact PII from JSON string
     *
     * @param string $json JSON string
     * @param bool $preservePartial Keep partial data for debugging
     * @return string Redacted JSON
     */
    public static function redactJson(string $json, bool $preservePartial = true): string
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return $json; // Invalid JSON, return as-is
        }

        $redacted = self::redactArray($data, $preservePartial);

        return json_encode($redacted, JSON_PRETTY_PRINT);
    }

    /**
     * Redact string with pattern-based detection
     *
     * @param string $text Text to redact
     * @param bool $preservePartial Keep partial data
     * @return string Redacted text
     */
    public static function redactString(string $text, bool $preservePartial = true): string
    {
        // Redact email addresses
        $text = preg_replace_callback(
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            function($matches) use ($preservePartial) {
                if (!$preservePartial) {
                    return '[EMAIL]';
                }
                $email = $matches[0];
                list($local, $domain) = explode('@', $email);
                $domainParts = explode('.', $domain);
                return substr($local, 0, 2) . '***@***.' . end($domainParts);
            },
            $text
        );

        // Redact phone numbers (NZ format)
        $text = preg_replace_callback(
            '/\b(\+64|0)[2-9]\d{7,9}\b/',
            function($matches) use ($preservePartial) {
                return $preservePartial ? substr($matches[0], 0, 4) . '******' : '[PHONE]';
            },
            $text
        );

        // Redact bank account numbers (NZ format: XX-XXXX-XXXXXXX-XXX)
        $text = preg_replace_callback(
            '/\b\d{2}-\d{4}-\d{7}-\d{2,3}\b/',
            function($matches) use ($preservePartial) {
                return $preservePartial ? substr($matches[0], 0, 7) . '-*******-***' : '[BANK_ACCOUNT]';
            },
            $text
        );

        // Redact IRD numbers (NZ format: XXX-XXX-XXX)
        $text = preg_replace_callback(
            '/\b\d{3}-\d{3}-\d{3}\b/',
            function($matches) use ($preservePartial) {
                return $preservePartial ? substr($matches[0], 0, 3) . '-***-***' : '[IRD_NUMBER]';
            },
            $text
        );

        return $text;
    }

    /**
     * Redact partial data based on field type
     */
    private static function redactPartial(string $value, string $fieldType): string
    {
        if (empty($value)) {
            return $value;
        }

        if (str_contains($fieldType, 'email')) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                list($local, $domain) = explode('@', $value);
                $domainParts = explode('.', $domain);
                return substr($local, 0, 2) . '***@***.' . end($domainParts);
            }
        }

        if (str_contains($fieldType, 'bank') || str_contains($fieldType, 'account')) {
            if (strlen($value) > 4) {
                return substr($value, 0, 4) . str_repeat('*', min(strlen($value) - 4, 10));
            }
        }

        if (str_contains($fieldType, 'phone') || str_contains($fieldType, 'mobile')) {
            if (strlen($value) > 4) {
                return substr($value, 0, 4) . str_repeat('*', strlen($value) - 4);
            }
        }

        // Default: show first 2 chars
        return substr($value, 0, 2) . str_repeat('*', min(strlen($value) - 2, 8));
    }

    /**
     * Check if data contains PII
     *
     * @param mixed $data Data to check
     * @return bool True if PII detected
     */
    public static function containsPii($data): bool
    {
        if (is_string($data)) {
            // Check for email pattern
            if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $data)) {
                return true;
            }

            // Check for phone pattern
            if (preg_match('/\b(\+64|0)[2-9]\d{7,9}\b/', $data)) {
                return true;
            }

            // Check for bank account pattern
            if (preg_match('/\b\d{2}-\d{4}-\d{7}-\d{2,3}\b/', $data)) {
                return true;
            }
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $lowerKey = strtolower($key);
                foreach (self::PII_FIELDS as $piiField) {
                    if (str_contains($lowerKey, $piiField)) {
                        return true;
                    }
                }

                if (self::containsPii($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Redact PII from log message
     *
     * @param string $message Log message
     * @param array $context Log context array
     * @return array [redacted_message, redacted_context]
     */
    public static function redactLog(string $message, array $context): array
    {
        return [
            self::redactString($message, true),
            self::redactArray($context, true)
        ];
    }
}
