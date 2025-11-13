<?php
/**
 * Input Validator for Bank Transactions Module
 *
 * Provides comprehensive input validation for all API endpoints
 * Prevents injection attacks, SQL injection, XSS
 *
 * @package BankTransactions\Lib
 */

declare(strict_types=1);

namespace CIS\BankTransactions\Lib;

class Validator
{
    private static array $errors = [];

    /**
     * Validate date format (YYYY-MM-DD)
     *
     * @param string $value Date to validate
     * @param string $field Field name for error messages
     * @return bool
     */
    public static function validateDate(string $value, string $field = 'Date'): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            self::$errors[] = "$field must be in YYYY-MM-DD format";
            return false;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            self::$errors[] = "$field is not a valid date";
            return false;
        }

        return true;
    }

    /**
     * Validate date range
     *
     * @param string $fromDate Start date (YYYY-MM-DD)
     * @param string $toDate End date (YYYY-MM-DD)
     * @return bool
     */
    public static function validateDateRange(string $fromDate, string $toDate): bool
    {
        if (!self::validateDate($fromDate, 'From date')) {
            return false;
        }

        if (!self::validateDate($toDate, 'To date')) {
            return false;
        }

        if ($fromDate > $toDate) {
            self::$errors[] = 'From date must be before or equal to to date';
            return false;
        }

        return true;
    }

    /**
     * Validate integer
     *
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param int $min Minimum value (optional)
     * @param int $max Maximum value (optional)
     * @return bool
     */
    public static function validateInteger($value, string $field = 'Value', ?int $min = null, ?int $max = null): bool
    {
        if (!is_numeric($value) || (int)$value != $value) {
            self::$errors[] = "$field must be an integer";
            return false;
        }

        $intValue = (int)$value;

        if ($min !== null && $intValue < $min) {
            self::$errors[] = "$field must be at least $min";
            return false;
        }

        if ($max !== null && $intValue > $max) {
            self::$errors[] = "$field must not exceed $max";
            return false;
        }

        return true;
    }

    /**
     * Validate float/decimal
     *
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param float $min Minimum value (optional)
     * @param float $max Maximum value (optional)
     * @return bool
     */
    public static function validateFloat($value, string $field = 'Value', ?float $min = null, ?float $max = null): bool
    {
        if (!is_numeric($value)) {
            self::$errors[] = "$field must be a valid number";
            return false;
        }

        $floatValue = (float)$value;

        if ($min !== null && $floatValue < $min) {
            self::$errors[] = "$field must be at least $min";
            return false;
        }

        if ($max !== null && $floatValue > $max) {
            self::$errors[] = "$field must not exceed $max";
            return false;
        }

        return true;
    }

    /**
     * Validate string length
     *
     * @param string $value String to validate
     * @param string $field Field name
     * @param int $minLength Minimum length
     * @param int $maxLength Maximum length (optional)
     * @return bool
     */
    public static function validateString(string $value, string $field = 'Text', int $minLength = 1, ?int $maxLength = null): bool
    {
        $length = strlen($value);

        if ($length < $minLength) {
            self::$errors[] = "$field must be at least $minLength characters";
            return false;
        }

        if ($maxLength !== null && $length > $maxLength) {
            self::$errors[] = "$field must not exceed $maxLength characters";
            return false;
        }

        return true;
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool
     */
    public static function validateEmail(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::$errors[] = 'Email address is invalid';
            return false;
        }

        return true;
    }

    /**
     * Validate enum value (must be in allowed list)
     *
     * @param mixed $value Value to validate
     * @param array $allowedValues Allowed values
     * @param string $field Field name
     * @return bool
     */
    public static function validateEnum($value, array $allowedValues, string $field = 'Value'): bool
    {
        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            self::$errors[] = "$field must be one of: $allowed";
            return false;
        }

        return true;
    }

    /**
     * Sanitize string (remove HTML/script tags)
     *
     * @param string $value String to sanitize
     * @return string Sanitized string
     */
    public static function sanitizeString(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize for database use (trim whitespace)
     *
     * @param string $value Value to sanitize
     * @return string Sanitized value
     */
    public static function sanitizeForDb(string $value): string
    {
        return trim($value);
    }

    /**
     * Get validation errors
     *
     * @return array Array of error messages
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Clear validation errors
     */
    public static function clearErrors(): void
    {
        self::$errors = [];
    }

    /**
     * Check if there are validation errors
     *
     * @return bool
     */
    public static function hasErrors(): bool
    {
        return count(self::$errors) > 0;
    }

    /**
     * Validate complete request data
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules:
     *        [
     *            'field_name' => [
     *                'type' => 'date|integer|float|string|email|enum',
     *                'required' => true,
     *                'min' => 1,
     *                'max' => 100,
     *                'enum' => ['value1', 'value2'],
     *            ]
     *        ]
     * @return bool
     */
    public static function validate(array $data, array $rules): bool
    {
        self::clearErrors();

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            // Check if required
            if ($rule['required'] ?? false) {
                if ($value === null || $value === '') {
                    self::$errors[] = "$field is required";
                    continue;
                }
            }

            // Skip validation if not required and empty
            if (!($rule['required'] ?? false) && ($value === null || $value === '')) {
                continue;
            }

            // Validate by type
            switch ($rule['type'] ?? null) {
                case 'date':
                    self::validateDate((string)$value, $field);
                    break;

                case 'integer':
                    self::validateInteger($value, $field, $rule['min'] ?? null, $rule['max'] ?? null);
                    break;

                case 'float':
                    self::validateFloat($value, $field, $rule['min'] ?? null, $rule['max'] ?? null);
                    break;

                case 'string':
                    self::validateString((string)$value, $field, $rule['min'] ?? 1, $rule['max'] ?? null);
                    break;

                case 'email':
                    self::validateEmail((string)$value);
                    break;

                case 'enum':
                    self::validateEnum($value, $rule['enum'] ?? [], $field);
                    break;
            }
        }

        return !self::hasErrors();
    }
}
