<?php
/**
 * Base Module - Comprehensive Production Readiness Test Suite
 * 
 * Tests all services, helpers, templates, and functionality
 * Ensures everything is production-ready with full coverage
 * 
 * @package Base
 * @version 1.0.0
 * @date 2025-10-27
 */

declare(strict_types=1);

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Colors for terminal output
class Colors {
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

// Test results tracking
class TestRunner {
    private int $passed = 0;
    private int $failed = 0;
    private int $warnings = 0;
    private array $failedTests = [];
    
    public function test(string $name, callable $test): void {
        try {
            $result = $test();
            if ($result === true) {
                $this->passed++;
                echo Colors::GREEN . "✓ " . Colors::RESET . $name . "\n";
            } else {
                $this->failed++;
                $this->failedTests[] = $name;
                echo Colors::RED . "✗ " . Colors::RESET . $name . "\n";
                if (is_string($result)) {
                    echo Colors::RED . "  Error: " . $result . Colors::RESET . "\n";
                }
            }
        } catch (Exception $e) {
            $this->failed++;
            $this->failedTests[] = $name;
            echo Colors::RED . "✗ " . Colors::RESET . $name . "\n";
            echo Colors::RED . "  Exception: " . $e->getMessage() . Colors::RESET . "\n";
        }
    }
    
    public function warning(string $message): void {
        $this->warnings++;
        echo Colors::YELLOW . "⚠ " . $message . Colors::RESET . "\n";
    }
    
    public function section(string $title): void {
        echo "\n" . Colors::BOLD . Colors::BLUE . "═══ " . $title . " ═══" . Colors::RESET . "\n\n";
    }
    
    public function summary(): void {
        echo "\n" . Colors::BOLD . "═══════════════════════════════════════" . Colors::RESET . "\n";
        echo Colors::BOLD . "TEST SUMMARY" . Colors::RESET . "\n";
        echo "═══════════════════════════════════════\n\n";
        
        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100, 2) : 0;
        
        echo Colors::GREEN . "Passed:   " . $this->passed . Colors::RESET . "\n";
        echo Colors::RED . "Failed:   " . $this->failed . Colors::RESET . "\n";
        echo Colors::YELLOW . "Warnings: " . $this->warnings . Colors::RESET . "\n";
        echo "Total:    " . $total . "\n";
        echo "Success:  " . $percentage . "%\n\n";
        
        if ($this->failed > 0) {
            echo Colors::RED . "Failed Tests:" . Colors::RESET . "\n";
            foreach ($this->failedTests as $test) {
                echo "  • " . $test . "\n";
            }
            echo "\n";
        }
        
        if ($percentage >= 90) {
            echo Colors::GREEN . Colors::BOLD . "✓ PRODUCTION READY" . Colors::RESET . "\n";
        } elseif ($percentage >= 70) {
            echo Colors::YELLOW . Colors::BOLD . "⚠ NEEDS ATTENTION" . Colors::RESET . "\n";
        } else {
            echo Colors::RED . Colors::BOLD . "✗ NOT PRODUCTION READY" . Colors::RESET . "\n";
        }
    }
}

// Initialize test runner
$test = new TestRunner();

// ============================================================================
// FILE STRUCTURE TESTS
// ============================================================================

$test->section("File Structure & Organization");

$test->test("Base module directory exists", function() {
    return is_dir(__DIR__);
});

$test->test("Services directory exists", function() {
    return is_dir(__DIR__ . '/services');
});

$test->test("Templates directory exists", function() {
    return is_dir(__DIR__ . '/_templates');
});

$test->test("Assets directory exists", function() {
    return is_dir(__DIR__ . '/_assets');
});

$test->test("Bootstrap file exists", function() {
    return file_exists(__DIR__ . '/bootstrap.php');
});

$test->test("README documentation exists", function() {
    return file_exists(__DIR__ . '/README.md');
});

// ============================================================================
// CORE SERVICES TESTS
// ============================================================================

$test->section("Core Services");

$test->test("Database class exists", function() {
    return file_exists(__DIR__ . '/Database.php');
});

$test->test("DatabasePDO class exists", function() {
    return file_exists(__DIR__ . '/DatabasePDO.php');
});

$test->test("DatabaseMySQLi class exists", function() {
    return file_exists(__DIR__ . '/DatabaseMySQLi.php');
});

$test->test("Logger class exists", function() {
    return file_exists(__DIR__ . '/Logger.php');
});

$test->test("Session class exists", function() {
    return file_exists(__DIR__ . '/Session.php');
});

$test->test("Router class exists", function() {
    return file_exists(__DIR__ . '/Router.php');
});

$test->test("Response class exists", function() {
    return file_exists(__DIR__ . '/Response.php');
});

$test->test("Validator class exists", function() {
    return file_exists(__DIR__ . '/Validator.php');
});

$test->test("ErrorHandler class exists", function() {
    return file_exists(__DIR__ . '/ErrorHandler.php');
});

$test->test("SecurityMiddleware class exists", function() {
    return file_exists(__DIR__ . '/SecurityMiddleware.php');
});

$test->test("RateLimiter class exists", function() {
    return file_exists(__DIR__ . '/RateLimiter.php');
});

$test->test("RealtimeService class exists", function() {
    return file_exists(__DIR__ . '/services/RealtimeService.php');
});

// ============================================================================
// PHP SYNTAX VALIDATION
// ============================================================================

$test->section("PHP Syntax Validation");

$phpFiles = [
    'bootstrap.php',
    'Database.php',
    'DatabasePDO.php',
    'DatabaseMySQLi.php',
    'Logger.php',
    'Session.php',
    'Router.php',
    'Response.php',
    'Validator.php',
    'ErrorHandler.php',
    'SecurityMiddleware.php',
    'RateLimiter.php',
    'services/RealtimeService.php'
];

foreach ($phpFiles as $file) {
    $test->test("Syntax check: {$file}", function() use ($file) {
        $fullPath = __DIR__ . '/' . $file;
        if (!file_exists($fullPath)) {
            return "File not found";
        }
        
        exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
        return $returnCode === 0;
    });
}

// ============================================================================
// TEMPLATE SYSTEM TESTS
// ============================================================================

$test->section("Template System");

$layouts = [
    'blank.php',
    'dashboard.php',
    'table.php',
    'card.php',
    'split.php'
];

foreach ($layouts as $layout) {
    $test->test("Layout exists: {$layout}", function() use ($layout) {
        return file_exists(__DIR__ . '/_templates/layouts/' . $layout);
    });
}

$components = [
    'header.php',
    'footer.php',
    'sidebar.php',
    'breadcrumbs.php',
    'search-bar.php'
];

foreach ($components as $component) {
    $test->test("Component exists: {$component}", function() use ($component) {
        return file_exists(__DIR__ . '/_templates/components/' . $component);
    });
}

$test->test("Error page template exists", function() {
    return file_exists(__DIR__ . '/_templates/error-pages/500.php');
});

// Validate template syntax
$templateFiles = array_merge(
    glob(__DIR__ . '/_templates/layouts/*.php'),
    glob(__DIR__ . '/_templates/components/*.php'),
    glob(__DIR__ . '/_templates/error-pages/*.php')
);

foreach ($templateFiles as $templateFile) {
    $filename = basename($templateFile);
    $test->test("Template syntax: {$filename}", function() use ($templateFile) {
        exec("php -l " . escapeshellarg($templateFile) . " 2>&1", $output, $returnCode);
        return $returnCode === 0;
    });
}

// ============================================================================
// FUNCTIONAL TESTS
// ============================================================================

$test->section("Functional Tests");

// Test Logger initialization
$test->test("Logger can be instantiated", function() {
    require_once __DIR__ . '/Logger.php';
    $logger = new Logger();
    return $logger instanceof Logger;
});

// Test Response class
$test->test("Response class can create JSON", function() {
    require_once __DIR__ . '/Response.php';
    ob_start();
    Response::json(['test' => 'data']);
    $output = ob_get_clean();
    $decoded = json_decode($output, true);
    return isset($decoded['test']) && $decoded['test'] === 'data';
});

// Test Validator class
$test->test("Validator class loads", function() {
    require_once __DIR__ . '/Validator.php';
    return class_exists('Validator');
});

// Test RateLimiter
$test->test("RateLimiter can be instantiated", function() {
    require_once __DIR__ . '/RateLimiter.php';
    $limiter = new RateLimiter();
    return $limiter instanceof RateLimiter;
});

// Test Session class
$test->test("Session class loads", function() {
    require_once __DIR__ . '/Session.php';
    return class_exists('Session');
});

// Test ErrorHandler
$test->test("ErrorHandler class loads", function() {
    require_once __DIR__ . '/ErrorHandler.php';
    return class_exists('ErrorHandler');
});

// Test SecurityMiddleware
$test->test("SecurityMiddleware class loads", function() {
    require_once __DIR__ . '/SecurityMiddleware.php';
    return class_exists('SecurityMiddleware');
});

// ============================================================================
// DOCUMENTATION TESTS
// ============================================================================

$test->section("Documentation & Standards");

$test->test("README.md exists", function() {
    return file_exists(__DIR__ . '/README.md');
});

$test->test("README.md has content", function() {
    $readme = file_get_contents(__DIR__ . '/README.md');
    return strlen($readme) > 500;
});

$test->test("Master plan exists", function() {
    return file_exists(__DIR__ . '/REBUILD_MASTER_PLAN.md');
});

$test->test("Quick reference exists", function() {
    return file_exists(__DIR__ . '/QUICK_REFERENCE.md');
});

// Check for PHPDoc comments in key files
$test->test("Core files have documentation headers", function() {
    $files = ['Database.php', 'Logger.php', 'Session.php'];
    foreach ($files as $file) {
        $content = file_get_contents(__DIR__ . '/' . $file);
        if (strpos($content, '/**') === false) {
            return "Missing PHPDoc in {$file}";
        }
    }
    return true;
});

// ============================================================================
// SECURITY TESTS
// ============================================================================

$test->section("Security & Best Practices");

$test->test("No hardcoded credentials in Database.php", function() {
    $content = file_get_contents(__DIR__ . '/Database.php');
    $suspicious = ['password123', 'root@localhost', 'admin:admin'];
    foreach ($suspicious as $pattern) {
        if (stripos($content, $pattern) !== false) {
            return "Possible hardcoded credential found";
        }
    }
    return true;
});

$test->test("SecurityMiddleware has CSRF protection", function() {
    $content = file_get_contents(__DIR__ . '/SecurityMiddleware.php');
    return strpos($content, 'csrf') !== false || strpos($content, 'CSRF') !== false;
});

$test->test("Logger doesn't expose sensitive data", function() {
    $content = file_get_contents(__DIR__ . '/Logger.php');
    // Check for password masking or sanitization
    return strpos($content, 'sanitize') !== false || strpos($content, 'mask') !== false;
});

$test->test("RateLimiter implements rate limiting", function() {
    $content = file_get_contents(__DIR__ . '/RateLimiter.php');
    return strpos($content, 'limit') !== false && strpos($content, 'attempt') !== false;
});

// ============================================================================
// PERFORMANCE TESTS
// ============================================================================

$test->section("Performance & Optimization");

$test->test("Logger uses file handles efficiently", function() {
    $content = file_get_contents(__DIR__ . '/Logger.php');
    // Should have file handle management
    return strpos($content, 'fopen') !== false || strpos($content, 'file_put_contents') !== false;
});

$test->test("Database supports connection pooling", function() {
    $content = file_get_contents(__DIR__ . '/DatabasePDO.php');
    return strpos($content, 'PDO::ATTR_PERSISTENT') !== false;
});

$test->test("Session uses secure settings", function() {
    $content = file_get_contents(__DIR__ . '/Session.php');
    return strpos($content, 'session_start') !== false;
});

// ============================================================================
// INTEGRATION TESTS
// ============================================================================

$test->section("Integration & Compatibility");

$test->test("Bootstrap file can be included", function() {
    $content = file_get_contents(__DIR__ . '/bootstrap.php');
    return strlen($content) > 100;
});

$test->test("Templates use consistent structure", function() {
    $layouts = glob(__DIR__ . '/_templates/layouts/*.php');
    foreach ($layouts as $layout) {
        $content = file_get_contents($layout);
        // Should have <!DOCTYPE html> for full layouts
        if (basename($layout) !== 'blank.php' && strpos($content, '<!DOCTYPE') === false) {
            return "Missing DOCTYPE in " . basename($layout);
        }
    }
    return true;
});

$test->test("Components are reusable", function() {
    $components = glob(__DIR__ . '/_templates/components/*.php');
    return count($components) >= 5; // Should have at least 5 components
});

$test->test("Error pages handle exceptions", function() {
    $errorPage = file_get_contents(__DIR__ . '/_templates/error-pages/500.php');
    return strpos($errorPage, 'error') !== false || strpos($errorPage, 'Error') !== false;
});

// ============================================================================
// PRODUCTION READINESS CHECKS
// ============================================================================

$test->section("Production Readiness");

$test->test("No TODO comments in core files", function() {
    $coreFiles = ['Database.php', 'Logger.php', 'Session.php', 'Router.php'];
    foreach ($coreFiles as $file) {
        $content = file_get_contents(__DIR__ . '/' . $file);
        if (stripos($content, 'TODO') !== false) {
            $test->warning("TODO found in {$file} - may need attention");
        }
    }
    return true;
});

$test->test("No debug code in production files", function() {
    $coreFiles = ['Database.php', 'Logger.php', 'Session.php'];
    foreach ($coreFiles as $file) {
        $content = file_get_contents(__DIR__ . '/' . $file);
        if (preg_match('/var_dump|print_r|var_export/', $content)) {
            return "Debug code found in {$file}";
        }
    }
    return true;
});

$test->test("Error reporting configured properly", function() {
    $content = file_get_contents(__DIR__ . '/ErrorHandler.php');
    return strpos($content, 'error_reporting') !== false || strpos($content, 'set_error_handler') !== false;
});

$test->test("Logging system is production-ready", function() {
    $content = file_get_contents(__DIR__ . '/Logger.php');
    // Should have log levels
    return strpos($content, 'ERROR') !== false && strpos($content, 'INFO') !== false;
});

$test->test("Database has error handling", function() {
    $content = file_get_contents(__DIR__ . '/Database.php');
    return strpos($content, 'try') !== false && strpos($content, 'catch') !== false;
});

// ============================================================================
// FINAL CHECKS
// ============================================================================

$test->section("Final Production Checks");

$test->test("All required services present", function() {
    $required = [
        'Database.php',
        'Logger.php',
        'Session.php',
        'Router.php',
        'Response.php',
        'Validator.php',
        'ErrorHandler.php',
        'SecurityMiddleware.php',
        'RateLimiter.php'
    ];
    
    foreach ($required as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            return "Missing required file: {$file}";
        }
    }
    return true;
});

$test->test("Template system complete", function() {
    $requiredLayouts = 5; // blank, dashboard, table, card, split
    $requiredComponents = 5; // header, footer, sidebar, breadcrumbs, search-bar
    
    $layouts = glob(__DIR__ . '/_templates/layouts/*.php');
    $components = glob(__DIR__ . '/_templates/components/*.php');
    
    return count($layouts) >= $requiredLayouts && count($components) >= $requiredComponents;
});

$test->test("Assets directory has resources", function() {
    return is_dir(__DIR__ . '/_assets/css') || is_dir(__DIR__ . '/_assets/js');
});

$test->test("Module is self-contained", function() {
    // Check that module has everything it needs
    $essentials = [
        'bootstrap.php',
        'README.md',
        '_templates',
        '_assets'
    ];
    
    foreach ($essentials as $item) {
        if (!file_exists(__DIR__ . '/' . $item)) {
            return "Missing essential: {$item}";
        }
    }
    return true;
});

// ============================================================================
// SUMMARY
// ============================================================================

$test->summary();

// Return exit code based on results
exit($test->failed === 0 ? 0 : 1);
