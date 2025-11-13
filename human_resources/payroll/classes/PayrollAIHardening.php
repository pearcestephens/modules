<?php
/**
 * AI Decision Engine Hardening Layer
 * - Rate limiting, circuit breaker, fallback logic, dead letter queue
 * - To be included in all AI processor scripts
 */
class PayrollAIHardening {
    private static $rate_limit = 10; // calls per minute
    private static $circuit_breaker_threshold = 3;
    private static $calls = [];
    private static $failures = 0;
    private static $circuit_open = false;
    private static $dead_letter_file = __DIR__ . '/dead_letter_queue.json';

    public static function checkRateLimit() {
        $now = time();
        self::$calls = array_filter(self::$calls, fn($t) => $t > $now - 60);
        if (count(self::$calls) >= self::$rate_limit) {
            throw new Exception("[RATE_LIMIT] OpenAI API rate limit exceeded");
        }
        self::$calls[] = $now;
    }

    public static function recordFailure($context) {
        self::$failures++;
        if (self::$failures >= self::$circuit_breaker_threshold) {
            self::$circuit_open = true;
            self::logDeadLetter(['reason' => 'Circuit breaker tripped', 'context' => $context]);
        }
    }

    public static function resetFailures() {
        self::$failures = 0;
        self::$circuit_open = false;
    }

    public static function isCircuitOpen() {
        return self::$circuit_open;
    }

    public static function logDeadLetter($entry) {
        $queue = [];
        if (file_exists(self::$dead_letter_file)) {
            $queue = json_decode(file_get_contents(self::$dead_letter_file), true) ?: [];
        }
        $queue[] = $entry;
        file_put_contents(self::$dead_letter_file, json_encode($queue, JSON_PRETTY_PRINT));
    }
}
