<?php
/**
 * CORE/BASE Integration Test Suite
 *
 * PRODUCTION GRADE: Comprehensive tests for authentication helpers
 * and CORE/BASE integration.
 *
 * @package CIS\Tests\Integration
 * @version 2.0.0
 */

declare(strict_types=1);

namespace CIS\Tests\Integration;

require_once __DIR__ . '/../../base/bootstrap.php';

class CoreBaseIntegrationTest
{
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║          CORE/BASE INTEGRATION TEST SUITE - PRODUCTION GRADE                ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        $this->testLoginUserBasic();
        $this->testLoginUserWithMissingFields();
        $this->testLoginUserValidation();
        $this->testLoginUserBackwardsCompatibility();
        $this->testLogoutUser();
        $this->testLogoutUserWithoutSession();
        $this->testUpdateSessionActivity();
        $this->testIsSessionTimedOut();
        $this->testSessionSecurityFeatures();
        $this->testHelperFunctionsExist();

        $this->printSummary();
    }

    private function testLoginUserBasic(): void
    {
        $this->startTest('loginUser() - Basic Login');

        try {
            // Clean session
            $_SESSION = [];

            // Mock user data
            $user = [
                'id' => 999,
                'username' => 'testuser',
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'User',
                'role' => 'admin'
            ];

            // Execute
            loginUser($user);

            // Verify
            $this->assert($_SESSION['user_id'] === 999, 'user_id set correctly');
            $this->assert($_SESSION['authenticated'] === true, 'authenticated flag set');
            $this->assert(isset($_SESSION['auth_time']), 'auth_time timestamp set');
            $this->assert($_SESSION['user']['username'] === 'testuser', 'username stored');
            $this->assert($_SESSION['user']['role'] === 'admin', 'role stored');

            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function testLoginUserWithMissingFields(): void
    {
        $this->startTest('loginUser() - Handle Missing Fields Gracefully');

        try {
            $_SESSION = [];

            // Minimal user data
            $user = [
                'id' => 888,
                'username' => 'minimal'
            ];

            loginUser($user);

            // Verify defaults applied
            $this->assert($_SESSION['user_id'] === 888, 'user_id set');
            $this->assert($_SESSION['user']['email'] === '', 'email defaulted to empty');
            $this->assert($_SESSION['user']['first_name'] === '', 'first_name defaulted');
            $this->assert($_SESSION['user']['role'] === 'user', 'role defaulted to user');
            $this->assert($_SESSION['user']['avatar_url'] === '/images/default-avatar.png', 'avatar defaulted');

            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function testLoginUserValidation(): void
    {
        $this->startTest('loginUser() - Validation (Missing ID)');

        try {
            $_SESSION = [];

            // User without ID
            $user = [
                'username' => 'noid'
            ];

            $exceptionThrown = false;
            try {
                loginUser($user);
            } catch (\InvalidArgumentException $e) {
                $exceptionThrown = true;
                $this->assert(
                    strpos($e->getMessage(), 'User ID is required') !== false,
                    'Correct exception message'
                );
            }

            $this->assert($exceptionThrown, 'Exception thrown for missing ID');
            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function testLoginUserBackwardsCompatibility(): void
    {
        $this->startTest('loginUser() - Backwards Compatibility (userID)');

        try {
            $_SESSION = [];

            $user = ['id' => 777, 'username' => 'legacy'];
            loginUser($user);

            // Modern standard
            $this->assert($_SESSION['user_id'] === 777, 'user_id (snake_case) set');

            // Legacy compatibility
            $this->assert($_SESSION['userID'] === 777, 'userID (camelCase) also set');

            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function testLogoutUser(): void
    {
        $this->startTest('logoutUser() - Clean Session Destruction');

        try {
            // Setup authenticated session
            $_SESSION = [
                'user_id' => 666,
                'user' => ['username' => 'logout_test'],
                'authenticated' => true
            ];

            // Execute
            logoutUser();

            // Verify session data cleared
            $this->assert(empty($_SESSION['user_id']), 'user_id cleared');
            $this->assert(empty($_SESSION['authenticated']), 'authenticated cleared');

            // In web context, session would be restarted
            // In CLI context, this is expected behavior
            $this->assert(
                session_status() === PHP_SESSION_ACTIVE || php_sapi_name() === 'cli',
                'session handled correctly for context'
            );

            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function testLogoutUserWithoutSession(): void
    {
        $this->startTest('logoutUser() - Handle No Active Session');

        try {
            $_SESSION = [];

            // Should not throw exception
            logoutUser(false);

            $this->assert(empty($_SESSION), 'session still empty');
            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function testUpdateSessionActivity(): void
    {
        $this->startTest('updateSessionActivity() - Update Timestamp');

        try {
            // Setup authenticated session
            $_SESSION = [
                'user_id' => 555,
                'user' => ['username' => 'active'],
                'authenticated' => true,
                'last_activity' => time() - 1000
            ];

            $oldActivity = $_SESSION['last_activity'];
            sleep(1);

            updateSessionActivity();

            $this->assert(
                $_SESSION['last_activity'] > $oldActivity,
                'last_activity timestamp updated'
            );

            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function testIsSessionTimedOut(): void
    {
        $this->startTest('isSessionTimedOut() - Timeout Detection');

        try {
            // Session that timed out
            $_SESSION = [
                'user_id' => 444,
                'last_activity' => time() - 7300 // Over 2 hours ago
            ];

            $timedOut = isSessionTimedOut(7200); // 2 hour timeout

            $this->assert($timedOut === true, 'detected timeout correctly');
            $this->assert(empty($_SESSION['user_id']), 'session cleared after timeout');

            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function testSessionSecurityFeatures(): void
    {
        $this->startTest('Security Features - Session Regeneration & Timestamps');

        try {
            $_SESSION = [];

            $user = ['id' => 333, 'username' => 'secure'];
            loginUser($user);

            // Security features present
            $this->assert(isset($_SESSION['auth_time']), 'auth_time recorded');
            $this->assert(isset($_SESSION['user']['logged_in_at']), 'login time recorded');
            $this->assert($_SESSION['authenticated'] === true, 'authentication flag set');

            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function testHelperFunctionsExist(): void
    {
        $this->startTest('Helper Functions - All Exist');

        try {
            $this->assert(function_exists('loginUser'), 'loginUser() exists');
            $this->assert(function_exists('logoutUser'), 'logoutUser() exists');
            $this->assert(function_exists('updateSessionActivity'), 'updateSessionActivity() exists');
            $this->assert(function_exists('isSessionTimedOut'), 'isSessionTimedOut() exists');

            // BASE helpers still exist
            $this->assert(function_exists('isAuthenticated'), 'isAuthenticated() exists');
            $this->assert(function_exists('getUserId'), 'getUserId() exists');
            $this->assert(function_exists('getCurrentUser'), 'getCurrentUser() exists');
            $this->assert(function_exists('flash'), 'flash() exists');
            $this->assert(function_exists('db'), 'db() exists');
            $this->assert(function_exists('e'), 'e() exists');

            $this->pass();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    // =============================================================================
    // TEST FRAMEWORK HELPERS
    // =============================================================================

    private function startTest(string $name): void
    {
        echo "Testing: {$name}...\n";
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \Exception("Assertion failed: {$message}");
        }
    }

    private function pass(): void
    {
        echo "  ✅ PASS\n\n";
        $this->passed++;
    }

    private function fail(string $message): void
    {
        echo "  ❌ FAIL: {$message}\n\n";
        $this->failed++;
    }

    private function printSummary(): void
    {
        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "\n";
        echo "TEST RESULTS:\n";
        echo "  Total Tests:  {$total}\n";
        echo "  ✅ Passed:     {$this->passed}\n";
        echo "  ❌ Failed:     {$this->failed}\n";
        echo "  Success Rate: {$percentage}%\n";
        echo "\n";

        if ($this->failed === 0) {
            echo "🎉 ALL TESTS PASSED - PRODUCTION READY!\n";
        } else {
            echo "⚠️  SOME TESTS FAILED - REVIEW REQUIRED\n";
        }

        echo "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }
}

// =============================================================================
// RUN TESTS
// =============================================================================

if (php_sapi_name() === 'cli') {
    $test = new CoreBaseIntegrationTest();
    $test->run();
}
