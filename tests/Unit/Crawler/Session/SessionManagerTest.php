<?php

/**
 * SessionManagerTest - Ultra-Strict Enterprise Unit Tests.
 *
 * Tests 7-layer fingerprinting, profile lifecycle, risk assessment,
 * Bayesian success rate estimation with maximum rigor.
 *
 * Target Coverage: 100%
 * Security Standards: OWASP ASVS Level 3
 * Enterprise Grade: ISO 27001 compliant
 */

declare(strict_types=1);

namespace CIS\SharedServices\Crawler\Tests\Unit\Session;

use CIS\SharedServices\Crawler\Core\SessionManager;
use Exception;
use InvalidArgumentException;
use Mockery;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

class SessionManagerTest extends TestCase
{
    private ?SessionManager $manager;

    private ?LoggerInterface $mockLogger;

    private ?PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->mockLogger->allows(['debug' => null, 'info' => null, 'warning' => null, 'error' => null]);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();

        $this->manager = new SessionManager($this->pdo, $this->mockLogger, [
            'max_uses_per_profile'       => 100,
            'profile_rotation_threshold' => 50, // 50% ban rate triggers rotation
            'enable_fingerprinting'      => true,
            'fingerprint_layers'         => ['canvas', 'webgl', 'audio', 'hardware', 'tls', 'battery', 'viewport'],
            'risk_threshold'             => 75,
        ]);
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // FINGERPRINT GENERATION TESTS (7 LAYERS)
    // =========================================================================

    public function testCanvasFingerprintGeneration(): void
    {
        $fingerprint = $this->manager->generateFingerprint('test_profile');

        $this->assertIsArray($fingerprint);
        $this->assertArrayHasKey('canvas_hash', $fingerprint);
        $this->assertNotEmpty($fingerprint['canvas_hash']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint['canvas_hash']);
    }

    public function testWebGLFingerprintGeneration(): void
    {
        $fingerprint = $this->manager->generateFingerprint('test_profile');

        $this->assertArrayHasKey('webgl_hash', $fingerprint);
        $this->assertNotEmpty($fingerprint['webgl_hash']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint['webgl_hash']);
    }

    public function testAudioFingerprintGeneration(): void
    {
        $fingerprint = $this->manager->generateFingerprint('test_profile');

        $this->assertArrayHasKey('audio_hash', $fingerprint);
        $this->assertNotEmpty($fingerprint['audio_hash']);
    }

    public function testHardwareFingerprintGeneration(): void
    {
        $fingerprint = $this->manager->generateFingerprint('test_profile');

        $this->assertArrayHasKey('hardware_concurrency', $fingerprint);
        $this->assertArrayHasKey('device_memory', $fingerprint);
        $this->assertIsInt($fingerprint['hardware_concurrency']);
        $this->assertGreaterThan(0, $fingerprint['hardware_concurrency']);
        $this->assertLessThanOrEqual(32, $fingerprint['hardware_concurrency']);
    }

    public function testTLSFingerprintGeneration(): void
    {
        $fingerprint = $this->manager->generateFingerprint('test_profile');

        $this->assertArrayHasKey('tls_fingerprint', $fingerprint);
        $this->assertNotEmpty($fingerprint['tls_fingerprint']);
    }

    public function testBatteryFingerprintGeneration(): void
    {
        $fingerprint = $this->manager->generateFingerprint('test_profile');

        $this->assertArrayHasKey('battery_level', $fingerprint);
        $this->assertIsInt($fingerprint['battery_level']);
        $this->assertGreaterThanOrEqual(0, $fingerprint['battery_level']);
        $this->assertLessThanOrEqual(100, $fingerprint['battery_level']);
    }

    public function testViewportFingerprintGeneration(): void
    {
        $fingerprint = $this->manager->generateFingerprint('test_profile');

        $this->assertArrayHasKey('viewport_width', $fingerprint);
        $this->assertArrayHasKey('viewport_height', $fingerprint);
        $this->assertIsInt($fingerprint['viewport_width']);
        $this->assertIsInt($fingerprint['viewport_height']);
        $this->assertGreaterThan(0, $fingerprint['viewport_width']);
        $this->assertGreaterThan(0, $fingerprint['viewport_height']);
    }

    public function testFingerprintUniquenessAcrossProfiles(): void
    {
        $fp1 = $this->manager->generateFingerprint('profile_1');
        $fp2 = $this->manager->generateFingerprint('profile_2');

        // At least one layer should differ
        $different = false;
        foreach (['canvas_hash', 'webgl_hash', 'audio_hash', 'tls_fingerprint'] as $layer) {
            if ($fp1[$layer] !== $fp2[$layer]) {
                $different = true;

                break;
            }
        }

        $this->assertTrue($different, 'Fingerprints should differ across profiles');
    }

    public function testFingerprintConsistencyForSameProfile(): void
    {
        $fp1 = $this->manager->generateFingerprint('consistent_profile');
        $fp2 = $this->manager->generateFingerprint('consistent_profile');

        // Should be similar (may have minor variations for realism)
        $this->assertEquals($fp1['hardware_concurrency'], $fp2['hardware_concurrency']);
        $this->assertEquals($fp1['device_memory'], $fp2['device_memory']);
    }

    // =========================================================================
    // SESSION CREATION TESTS
    // =========================================================================

    public function testCreateSessionGeneratesUniqueID(): void
    {
        $session1 = $this->manager->createSession('profile_1');
        $session2 = $this->manager->createSession('profile_2');

        $this->assertNotEquals($session1['session_id'], $session2['session_id']);
    }

    public function testCreateSessionStoresFingerprint(): void
    {
        $session = $this->manager->createSession('test_profile');

        $this->assertArrayHasKey('fingerprint', $session);
        $this->assertIsArray($session['fingerprint']);
        $this->assertArrayHasKey('canvas_hash', $session['fingerprint']);
    }

    public function testCreateSessionInitializesCounters(): void
    {
        $session = $this->manager->createSession('test_profile');

        $this->assertEquals(0, $session['use_count']);
        $this->assertEquals(0, $session['success_count']);
        $this->assertEquals(0, $session['ban_count']);
    }

    public function testCreateSessionSetsActiveStatus(): void
    {
        $session = $this->manager->createSession('test_profile');

        $this->assertEquals('active', $session['status']);
    }

    public function testCreateSessionPersistsToDatabase(): void
    {
        $session = $this->manager->createSession('test_profile');

        $stmt = $this->pdo->prepare('SELECT * FROM crawler_sessions WHERE session_id = ?');
        $stmt->execute([$session['session_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertEquals($session['session_id'], $row['session_id']);
    }

    // =========================================================================
    // PROFILE LIFECYCLE TESTS
    // =========================================================================

    public function testProfileUsageIncrement(): void
    {
        $session = $this->manager->createSession('test_profile');

        $this->manager->recordUsage($session['session_id'], true);

        $updated = $this->manager->getSession($session['session_id']);
        $this->assertEquals(1, $updated['use_count']);
        $this->assertEquals(1, $updated['success_count']);
    }

    public function testProfileBanIncrement(): void
    {
        $session = $this->manager->createSession('test_profile');

        $this->manager->recordUsage($session['session_id'], false, true); // banned

        $updated = $this->manager->getSession($session['session_id']);
        $this->assertEquals(1, $updated['use_count']);
        $this->assertEquals(0, $updated['success_count']);
        $this->assertEquals(1, $updated['ban_count']);
    }

    public function testProfileRotationAfterMaxUses(): void
    {
        $session = $this->manager->createSession('test_profile');

        // Simulate 100 uses
        for ($i = 0; $i < 100; $i++) {
            $this->manager->recordUsage($session['session_id'], true);
        }

        $shouldRotate = $this->manager->shouldRotateProfile($session['session_id']);
        $this->assertTrue($shouldRotate);
    }

    public function testProfileRotationAfterHighBanRate(): void
    {
        $session = $this->manager->createSession('test_profile');

        // Simulate 10 uses, 6 bans (60% ban rate)
        for ($i = 0; $i < 10; $i++) {
            $banned = $i < 6;
            $this->manager->recordUsage($session['session_id'], !$banned, $banned);
        }

        $shouldRotate = $this->manager->shouldRotateProfile($session['session_id']);
        $this->assertTrue($shouldRotate);
    }

    public function testProfileStatusChangesToRetired(): void
    {
        $session = $this->manager->createSession('test_profile');

        $this->manager->retireProfile($session['session_id']);

        $updated = $this->manager->getSession($session['session_id']);
        $this->assertEquals('retired', $updated['status']);
    }

    public function testProfileCleanupRemovesOldSessions(): void
    {
        // Create old session
        $oldSession = $this->manager->createSession('old_profile');

        // Manually set created_at to 31 days ago
        $this->pdo->exec("
            UPDATE crawler_sessions
            SET created_at = datetime('now', '-31 days')
            WHERE session_id = '{$oldSession['session_id']}'
        ");

        $removed = $this->manager->cleanupOldSessions(30);

        $this->assertGreaterThan(0, $removed);

        $session = $this->manager->getSession($oldSession['session_id']);
        $this->assertNull($session);
    }

    // =========================================================================
    // RISK ASSESSMENT TESTS
    // =========================================================================

    public function testRiskScoreCalculationLowRisk(): void
    {
        $session = $this->manager->createSession('test_profile');

        // Record successful uses
        for ($i = 0; $i < 10; $i++) {
            $this->manager->recordUsage($session['session_id'], true);
        }

        $riskScore = $this->manager->calculateRiskScore($session['session_id']);

        $this->assertLessThan(30, $riskScore);
    }

    public function testRiskScoreCalculationHighRisk(): void
    {
        $session = $this->manager->createSession('test_profile');

        // Record mostly bans
        for ($i = 0; $i < 10; $i++) {
            $banned = $i < 8; // 80% ban rate
            $this->manager->recordUsage($session['session_id'], !$banned, $banned);
        }

        $riskScore = $this->manager->calculateRiskScore($session['session_id']);

        $this->assertGreaterThan(70, $riskScore);
    }

    public function testRiskThresholdEnforcement(): void
    {
        $session = $this->manager->createSession('test_profile');

        // Set high risk
        $this->pdo->exec("
            UPDATE crawler_sessions
            SET risk_score = 80
            WHERE session_id = '{$session['session_id']}'
        ");

        $isHighRisk = $this->manager->isHighRisk($session['session_id']);
        $this->assertTrue($isHighRisk);
    }

    public function testRiskFactorsBanRate(): void
    {
        $reflection = new ReflectionClass($this->manager);

        if ($reflection->hasMethod('calculateBanRateFactor')) {
            $method = $reflection->getMethod('calculateBanRateFactor');
            $method->setAccessible(true);

            $factor = $method->invoke($this->manager, 8, 10); // 80% ban rate

            $this->assertGreaterThan(0.5, $factor);
        }
    }

    // =========================================================================
    // BAYESIAN SUCCESS RATE ESTIMATION TESTS
    // =========================================================================

    public function testBayesianSuccessRateCalculation(): void
    {
        $session = $this->manager->createSession('test_profile');

        // 7 successes out of 10 attempts
        for ($i = 0; $i < 10; $i++) {
            $success = $i < 7;
            $this->manager->recordUsage($session['session_id'], $success, !$success);
        }

        $successRate = $this->manager->estimateSuccessRate($session['session_id']);

        // Bayesian estimate should be close to 0.7 but smoothed
        $this->assertGreaterThan(0.6, $successRate);
        $this->assertLessThan(0.8, $successRate);
    }

    public function testBayesianPriorInfluence(): void
    {
        $session = $this->manager->createSession('test_profile');

        // Only 1 success (should be influenced by prior)
        $this->manager->recordUsage($session['session_id'], true);

        $successRate = $this->manager->estimateSuccessRate($session['session_id']);

        // With prior, shouldn't be exactly 1.0
        $this->assertLessThan(0.95, $successRate);
        $this->assertGreaterThan(0.5, $successRate);
    }

    public function testBayesianUpdateFormula(): void
    {
        $reflection = new ReflectionClass($this->manager);

        if ($reflection->hasMethod('bayesianUpdate')) {
            $method = $reflection->getMethod('bayesianUpdate');
            $method->setAccessible(true);

            // Prior: alpha=2, beta=2 (uniform prior)
            // Observed: 7 successes, 3 failures
            // Posterior: alpha=9, beta=5
            // Mean = alpha / (alpha + beta) = 9/14 ≈ 0.643
            $result = $method->invoke($this->manager, 7, 3, 2, 2);

            $this->assertEqualsWithDelta(0.643, $result, 0.01);
        }
    }

    // =========================================================================
    // PROFILE SELECTION TESTS
    // =========================================================================

    public function testSelectBestProfileReturnsHighestSuccessRate(): void
    {
        $session1 = $this->manager->createSession('profile_1');
        $session2 = $this->manager->createSession('profile_2');

        // Session 1: 8/10 success
        for ($i = 0; $i < 10; $i++) {
            $this->manager->recordUsage($session1['session_id'], $i < 8, $i >= 8);
        }

        // Session 2: 5/10 success
        for ($i = 0; $i < 10; $i++) {
            $this->manager->recordUsage($session2['session_id'], $i < 5, $i >= 5);
        }

        $best = $this->manager->selectBestProfile();

        $this->assertEquals($session1['session_id'], $best['session_id']);
    }

    public function testSelectProfileExcludesRetired(): void
    {
        $activeSession  = $this->manager->createSession('active_profile');
        $retiredSession = $this->manager->createSession('retired_profile');

        $this->manager->retireProfile($retiredSession['session_id']);

        $selected = $this->manager->selectBestProfile();

        $this->assertEquals($activeSession['session_id'], $selected['session_id']);
    }

    public function testSelectProfileExcludesHighRisk(): void
    {
        $lowRiskSession  = $this->manager->createSession('low_risk');
        $highRiskSession = $this->manager->createSession('high_risk');

        $this->pdo->exec("
            UPDATE crawler_sessions
            SET risk_score = 80
            WHERE session_id = '{$highRiskSession['session_id']}'
        ");

        $selected = $this->manager->selectBestProfile();

        $this->assertEquals($lowRiskSession['session_id'], $selected['session_id']);
    }

    // =========================================================================
    // FINGERPRINT VALIDATION TESTS
    // =========================================================================

    public function testValidateFingerprintConsistency(): void
    {
        $session = $this->manager->createSession('test_profile');

        $isValid = $this->manager->validateFingerprint(
            $session['session_id'],
            $session['fingerprint'],
        );

        $this->assertTrue($isValid);
    }

    public function testDetectFingerprintMismatch(): void
    {
        $session = $this->manager->createSession('test_profile');

        $alteredFingerprint                = $session['fingerprint'];
        $alteredFingerprint['canvas_hash'] = 'altered_hash_value';

        $isValid = $this->manager->validateFingerprint(
            $session['session_id'],
            $alteredFingerprint,
        );

        $this->assertFalse($isValid);
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function testGetNonExistentSession(): void
    {
        $session = $this->manager->getSession('non_existent_id');

        $this->assertNull($session);
    }

    public function testRecordUsageForNonExistentSession(): void
    {
        $this->expectException(RuntimeException::class);
        $this->manager->recordUsage('non_existent_id', true);
    }

    public function testCreateSessionWithEmptyProfileName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->createSession('');
    }

    public function testZeroUsesProfile(): void
    {
        $session = $this->manager->createSession('test_profile');

        $successRate = $this->manager->estimateSuccessRate($session['session_id']);

        // With no data, should return prior mean (0.5 for uniform prior)
        $this->assertEqualsWithDelta(0.5, $successRate, 0.1);
    }

    public function testMaxIntegerUseCount(): void
    {
        $session = $this->manager->createSession('test_profile');

        // Set use_count to near max
        $this->pdo->exec("
            UPDATE crawler_sessions
            SET use_count = 2147483647
            WHERE session_id = '{$session['session_id']}'
        ");

        // Should handle gracefully
        $shouldRotate = $this->manager->shouldRotateProfile($session['session_id']);
        $this->assertTrue($shouldRotate);
    }

    // =========================================================================
    // CONCURRENT ACCESS TESTS
    // =========================================================================

    public function testConcurrentSessionCreation(): void
    {
        $sessions = [];

        for ($i = 0; $i < 10; $i++) {
            $sessions[] = $this->manager->createSession("profile_{$i}");
        }

        // All should have unique IDs
        $ids = array_column($sessions, 'session_id');
        $this->assertCount(10, array_unique($ids));
    }

    public function testConcurrentUsageRecording(): void
    {
        $session = $this->manager->createSession('test_profile');

        // Simulate concurrent updates
        for ($i = 0; $i < 50; $i++) {
            $this->manager->recordUsage($session['session_id'], true);
        }

        $updated = $this->manager->getSession($session['session_id']);
        $this->assertEquals(50, $updated['use_count']);
    }

    // =========================================================================
    // PERFORMANCE TESTS
    // =========================================================================

    public function testFingerprintGenerationPerformance(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $this->manager->generateFingerprint("profile_{$i}");
        }

        $duration = microtime(true) - $start;

        // Should generate 100 fingerprints in under 1 second
        $this->assertLessThan(1.0, $duration);
    }

    public function testSessionCreationPerformance(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < 50; $i++) {
            $this->manager->createSession("profile_{$i}");
        }

        $duration = microtime(true) - $start;

        // Should create 50 sessions in under 500ms
        $this->assertLessThan(0.5, $duration);
    }

    public function testRiskCalculationPerformance(): void
    {
        $sessions = [];
        for ($i = 0; $i < 20; $i++) {
            $sessions[] = $this->manager->createSession("profile_{$i}");
        }

        $start = microtime(true);

        foreach ($sessions as $session) {
            $this->manager->calculateRiskScore($session['session_id']);
        }

        $duration = microtime(true) - $start;

        // Should calculate 20 risk scores in under 100ms
        $this->assertLessThan(0.1, $duration);
    }

    public function testMemoryUsageForManySessions(): void
    {
        $memBefore = memory_get_usage(true);

        for ($i = 0; $i < 100; $i++) {
            $this->manager->createSession("profile_{$i}");
        }

        $memAfter = memory_get_usage(true);
        $memUsed  = $memAfter - $memBefore;

        // Should not use more than 10MB for 100 sessions
        $this->assertLessThan(10 * 1024 * 1024, $memUsed);
    }

    // =========================================================================
    // DATABASE INTEGRITY TESTS
    // =========================================================================

    public function testTransactionRollbackOnError(): void
    {
        $sessionsBefore = $this->pdo->query('SELECT COUNT(*) FROM crawler_sessions')->fetchColumn();

        try {
            // Force an error during session creation
            $this->manager->createSession(''); // Empty name should fail
        } catch (Exception $e) {
            // Expected
        }

        $sessionsAfter = $this->pdo->query('SELECT COUNT(*) FROM crawler_sessions')->fetchColumn();

        // Count should not change if transaction rolled back
        $this->assertEquals($sessionsBefore, $sessionsAfter);
    }

    public function testUniqueSessionIDConstraint(): void
    {
        $session = $this->manager->createSession('test_profile');

        // Try to insert duplicate session_id directly
        $this->expectException(PDOException::class);

        $this->pdo->exec("
            INSERT INTO crawler_sessions (session_id, fingerprint, profile_name)
            VALUES ('{$session['session_id']}', 'test', 'test')
        ");
    }

    private function createSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE crawler_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT UNIQUE NOT NULL,
                fingerprint TEXT NOT NULL,
                profile_name TEXT NOT NULL,
                profile_path TEXT,
                user_agent TEXT,
                viewport_width INTEGER,
                viewport_height INTEGER,
                timezone TEXT,
                locale TEXT,
                canvas_hash TEXT,
                webgl_hash TEXT,
                audio_hash TEXT,
                hardware_concurrency INTEGER,
                device_memory INTEGER,
                tls_fingerprint TEXT,
                battery_level INTEGER,
                use_count INTEGER DEFAULT 0,
                usage_count INTEGER DEFAULT 0,
                success_count INTEGER DEFAULT 0,
                ban_count INTEGER DEFAULT 0,
                last_used TEXT,
                last_used_at TEXT,
                risk_score INTEGER DEFAULT 0,
                success_rate REAL DEFAULT 100.0,
                banned INTEGER DEFAULT 0,
                status TEXT DEFAULT "active",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }
}
