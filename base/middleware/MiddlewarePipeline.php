<?php
/**
 * Middleware Pipeline
 *
 * Manages and executes middleware stack
 */

namespace App\Middleware;

class MiddlewarePipeline
{
    private $middlewares = [];

    /**
     * Add middleware to pipeline
     */
    public function add($middleware)
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Execute middleware pipeline
     */
    public function handle($request, $finalHandler)
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            function($next, $middleware) {
                return function($request) use ($middleware, $next) {
                    return $middleware->handle($request, $next);
                };
            },
            $finalHandler
        );

        return $pipeline($request);
    }

    /**
     * Create default middleware stack
     */
    public static function createDefault()
    {
        $pipeline = new self();

        // Add middleware in order of execution
        $pipeline
            ->add(new CompressionMiddleware())
            ->add(new LoggingMiddleware())
            ->add(new RateLimitMiddleware())
            ->add(new CsrfMiddleware())
            ->add(new CacheMiddleware());

        return $pipeline;
    }

    /**
     * Create authenticated middleware stack
     */
    public static function createAuthenticated()
    {
        $pipeline = new self();

        $pipeline
            ->add(new CompressionMiddleware())
            ->add(new LoggingMiddleware())
            ->add(new RateLimitMiddleware())
            ->add(new AuthMiddleware())
            ->add(new CsrfMiddleware());

        return $pipeline;
    }
}
