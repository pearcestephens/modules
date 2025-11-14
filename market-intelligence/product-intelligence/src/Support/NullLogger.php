<?php

declare(strict_types=1);

/**
 * Null Logger - PSR-3 compliant logger that does nothing.
 *
 * Used for testing and dependency injection when actual logging is not needed.
 *
 * @package CIS\SharedServices\ProductIntelligence\Support
 */

namespace CIS\SharedServices\ProductIntelligence\Support;

use Psr\Log\LoggerInterface;

class NullLogger implements LoggerInterface
{
    /**
     * System is unusable.
     */
    public function emergency($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Action must be taken immediately.
     */
    public function alert($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Critical conditions.
     */
    public function critical($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Runtime errors that do not require immediate action.
     */
    public function error($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Exceptional occurrences that are not errors.
     */
    public function warning($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Normal but significant events.
     */
    public function notice($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Interesting events.
     */
    public function info($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Detailed debug information.
     */
    public function debug($message, array $context = []): void
    {
        // Do nothing
    }

    /**
     * Logs with an arbitrary level.
     */
    public function log($level, $message, array $context = []): void
    {
        // Do nothing
    }
}
