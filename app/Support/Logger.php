<?php

/**
 * Minimal structured logger for bootstrap components.
 */

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class Logger
{
    private string $channel;

    private DateTimeZone $timezone;

    public function __construct(string $channel = 'app', string $timezone = 'Pacific/Auckland')
    {
        $this->channel  = $channel;
        $this->timezone = new DateTimeZone($timezone);
    }

    /**
     * Log a message with context data.
     *
     * @param array<string,mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $timestamp = (new DateTimeImmutable('now', $this->timezone))->format('c');
        $entry     = [
            'ts'      => $timestamp,
            'channel' => $this->channel,
            'level'   => strtolower($level),
            'message' => $message,
            'context' => $context,
        ];

        error_log(json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Convenience debug logger.
     *
     * @param array<string,mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Convenience info logger.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Convenience warning logger.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Convenience error logger.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
}
