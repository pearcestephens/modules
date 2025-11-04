<?php
/**
 * Service Provider Configuration
 *
 * Register all service providers here
 *
 * @package CIS\Base
 * @version 2.0.0
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Core Services (Always Loaded)
    |--------------------------------------------------------------------------
    */
    'core' => [
        \CIS\Base\Core\Database::class,
        \CIS\Base\Core\Session::class,
        \CIS\Base\Core\Logger::class,
        \CIS\Base\Core\ErrorHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Services
    |--------------------------------------------------------------------------
    */
    'http' => [
        \CIS\Base\Http\Router::class,
        \CIS\Base\Http\Request::class,
        \CIS\Base\Http\Response::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Services
    |--------------------------------------------------------------------------
    */
    'security' => [
        \CIS\Base\Security\SecurityMiddleware::class,
        \CIS\Base\Security\Validator::class,
        \CIS\Base\Security\RateLimiter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Services (Lazy Loaded)
    |--------------------------------------------------------------------------
    */
    'services' => [
        \CIS\Base\Services\AIService::class,
        \CIS\Base\Services\AIChatService::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | View Services
    |--------------------------------------------------------------------------
    */
    'view' => [
        \CIS\Base\View\TemplateEngine::class,
    ],

];
