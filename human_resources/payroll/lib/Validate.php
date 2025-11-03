<?php
/**
 * Payroll Module - Input Validation Helper
 *
 * Strict validation utilities for payroll data types
 *
 * @package Payroll\Lib
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

namespace Payroll\Lib;

use InvalidArgumentException;
use DateTime;

final class Validate
{
    /**
     * Validate and return YYYY-MM-DD date string
     *
     * @param string $s Input date string
     * @return string Validated date string
     * @throws InvalidArgumentException If invalid date format
     */
    public static function dateYmd(string $s): string
    {
        $d = DateTime::createFromFormat('Y-m-d', $s);

        if (!$d || $d->format('Y-m-d') !== $s) {
            throw new InvalidArgumentException("Invalid date format: '{$s}'. Expected YYYY-MM-DD.");
        }

        return $s;
    }

    /**
     * Validate employee ID (non-empty, max 64 chars)
     *
     * @param string $s Employee ID
     * @return string Trimmed employee ID
     * @throws InvalidArgumentException If invalid
     */
    public static function employeeId(string $s): string
    {
        $t = trim($s);

        if ($t === '' || strlen($t) > 64) {
            throw new InvalidArgumentException("Invalid employee ID: '{$s}'. Must be 1-64 characters.");
        }

        return $t;
    }

    /**
     * Convert to cents (integer)
     *
     * Accepts:
     * - int (assumed already in cents)
     * - float/numeric string (dollars, converted to cents)
     *
     * @param int|float|string $n Amount
     * @return int Amount in cents
     * @throws InvalidArgumentException If not numeric
     */
    public static function cents(int|float|string $n): int
    {
        if (is_int($n)) {
            return $n;
        }

        if (is_numeric($n)) {
            return (int)round((float)$n * 100);
        }

        throw new InvalidArgumentException("Invalid amount: '{$n}'. Must be numeric.");
    }

    /**
     * Validate enum value against allowed list
     *
     * @param string $value Value to validate
     * @param array $allowed Allowed values
     * @param string $fieldName Field name for error message
     * @return string Validated value
     * @throws InvalidArgumentException If not in allowed list
     */
    public static function enum(string $value, array $allowed, string $fieldName = 'value'): string
    {
        if (!in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            throw new InvalidArgumentException(
                "Invalid {$fieldName}: '{$value}'. Allowed values: {$allowedStr}"
            );
        }

        return $value;
    }

    /**
     * Validate positive integer
     *
     * @param mixed $n Value to validate
     * @param string $fieldName Field name for error message
     * @return int Validated integer
     * @throws InvalidArgumentException If not positive integer
     */
    public static function positiveInt(mixed $n, string $fieldName = 'value'): int
    {
        if (!is_numeric($n)) {
            throw new InvalidArgumentException("{$fieldName} must be numeric");
        }

        $int = (int)$n;

        if ($int <= 0) {
            throw new InvalidArgumentException("{$fieldName} must be positive");
        }

        return $int;
    }
}
