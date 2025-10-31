<?php
/**
 * CIS Base Validator
 * 
 * Input validation helpers.
 * 
 * @package CIS\Base
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Base;

class Validator
{
    /**
     * Validate required field
     */
    public static function required($value, string $fieldName): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException("Field '{$fieldName}' is required");
        }
    }
    
    /**
     * Validate email
     */
    public static function email(string $email): bool
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Validate integer
     */
    public static function int($value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : null;
    }
    
    /**
     * Sanitize string
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
