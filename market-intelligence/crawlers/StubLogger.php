<?php
/**
 * Stub Logger for Testing
 */

namespace CIS\Crawlers;

class StubLogger extends CentralLogger {
    public function __construct() {
        // Don't call parent constructor
    }

    public function log($level, $message, $context = []) { }
    public function info($message, $context = []) { }
    public function warning($message, $context = []) { }
    public function error($message, $context = []) { }
    public function debug($message, $context = []) { }
}
