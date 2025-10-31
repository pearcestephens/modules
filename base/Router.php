<?php
/**
 * CIS Base Router
 * 
 * Simple routing helpers.
 * 
 * @package CIS\Base
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Base;

class Router
{
    /**
     * Get current route/endpoint
     */
    public static function getRoute(): string
    {
        return $_GET['endpoint'] ?? $_GET['route'] ?? 'index';
    }
    
    /**
     * Get request method
     */
    public static function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Check if POST request
     */
    public static function isPost(): bool
    {
        return self::getMethod() === 'POST';
    }
    
    /**
     * Check if GET request
     */
    public static function isGet(): bool
    {
        return self::getMethod() === 'GET';
    }
}
