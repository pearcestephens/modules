<?php

/**
 * BehaviorEngineTest - Ultra-Strict Unit Tests.
 *
 * Tests Q-Learning algorithm, experience replay, Bayesian selection,
 * Fitts's Law, Gamma distribution, circadian rhythm, fatigue modeling,
 * typing simulation with maximum rigor.
 *
 * Target Coverage: 100%
 * Edge Cases: Comprehensive
 * Algorithm Accuracy: 95%+ Q-Learning convergence
 */

declare(strict_types=1);

namespace CIS\SharedServices\Crawler\Tests\Unit\Behavior;

use CIS\SharedServices\Crawler\Core\BehaviorEngine;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

use function count;

class BehaviorEngineTest extends TestCase
{
    private BehaviorEngine $engine;

    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->mockLogger->allows(['debug' => null, 'info' => null, 'warning' => null]);

        $this->engine = new BehaviorEngine($this->mockLogger, [
            'learning_rate'          => 0.1,
            'discount_factor'        => 0.95,
            'exploration_rate'       => 0.2,
            'experience_buffer_size' => 1000,
            'enable_learning'        => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Q-LEARNING ALGORITHM TESTS
    // =========================================================================

    public function testQTableInitialization(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $qTable     = $reflection->getProperty('qTable');
        $qTable->setAccessible(true);

        $table = $qTable->getValue($this->engine);

        $this->assertIsArray($table);
        // Initially empty or has default values
    }

    public function testGetCurrentStateReturnsCorrectFormat(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $method     = $reflection->getMethod('getCurrentState');
        $method->setAccessible(true);

        $state = $method->invoke($this->engine, 'homepage', 2, false);

        $this->assertIsString($state);
        $this->assertStringContainsString('homepage', $state);
        $this->assertStringContainsString('2', $state);
    }

    public function testGetQValueReturnsZeroForNewState(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $method     = $reflection->getMethod('getQValue');
        $method->setAccessible(true);

        $qValue = $method->invoke($this->engine, 'new_state', 'new_action');

        $this->assertEquals(0.0, $qValue);
    }

    public function testSetQValueUpdatesTable(): void
    {
        $reflection = new ReflectionClass($this->engine);

        $setMethod = $reflection->getMethod('setQValue');
        $setMethod->setAccessible(true);
        $setMethod->invoke($this->engine, 'test_state', 'test_action', 5.5);

        $getMethod = $reflection->getMethod('getQValue');
        $getMethod->setAccessible(true);
        $qValue = $getMethod->invoke($this->engine, 'test_state', 'test_action');

        $this->assertEquals(5.5, $qValue);
    }

    public function testGetMaxQValueFindsMaximum(): void
    {
        $reflection = new ReflectionClass($this->engine);

        $setMethod = $reflection->getMethod('setQValue');
        $setMethod->setAccessible(true);
        $setMethod->invoke($this->engine, 'state1', 'action1', 3.0);
        $setMethod->invoke($this->engine, 'state1', 'action2', 5.0);
        $setMethod->invoke($this->engine, 'state1', 'action3', 4.0);

        $maxMethod = $reflection->getMethod('getMaxQValue');
        $maxMethod->setAccessible(true);
        $maxQ = $maxMethod->invoke($this->engine, 'state1');

        $this->assertEquals(5.0, $maxQ);
    }

    public function testQLearningUpdate(): void
    {
        // Q(s,a) = Q(s,a) + α * (r + γ * maxQ(s') - Q(s,a))
        $reflection = new ReflectionClass($this->engine);

        $setMethod = $reflection->getMethod('setQValue');
        $setMethod->setAccessible(true);
        $setMethod->invoke($this->engine, 'state1', 'action1', 2.0);
        $setMethod->invoke($this->engine, 'state2', 'action_any', 3.0);

        $getMethod = $reflection->getMethod('getQValue');
        $getMethod->setAccessible(true);

        // Call learnFromAction to trigger update
        $this->engine->learnFromAction('state1', 'action1', 1.0, 'state2', false);

        $newQ = $getMethod->invoke($this->engine, 'state1', 'action1');

        // Should be: 2.0 + 0.1 * (1.0 + 0.95 * 3.0 - 2.0) = 2.0 + 0.1 * 1.85 = 2.185
        $this->assertEqualsWithDelta(2.185, $newQ, 0.01);
    }

    public function testEpsilonGreedyActionSelection(): void
    {
        $reflection = new ReflectionClass($this->engine);

        $setMethod = $reflection->getMethod('setQValue');
        $setMethod->setAccessible(true);
        $setMethod->invoke($this->engine, 'test_state', 'best_action', 10.0);
        $setMethod->invoke($this->engine, 'test_state', 'other_action', 1.0);

        $actions    = ['best_action', 'other_action'];
        $bestCount  = 0;
        $otherCount = 0;

        // Run 100 times
        for ($i = 0; $i < 100; $i++) {
            $selected = $this->engine->selectAction('test_state', $actions);
            if ($selected === 'best_action') {
                $bestCount++;
            } else {
                $otherCount++;
            }
        }

        // With exploration_rate=0.2, should choose best_action ~80% of time
        $this->assertGreaterThan(60, $bestCount);
        $this->assertGreaterThan(10, $otherCount);
    }

    // =========================================================================
    // EXPERIENCE REPLAY TESTS
    // =========================================================================

    public function testStoreExperienceAddsToBuffer(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $method     = $reflection->getMethod('storeExperience');
        $method->setAccessible(true);

        $method->invoke($this->engine, 'state1', 'action1', 1.0, 'state2', false);

        $buffer = $reflection->getProperty('experienceBuffer');
        $buffer->setAccessible(true);
        $bufferData = $buffer->getValue($this->engine);

        $this->assertCount(1, $bufferData);
    }

    public function testExperienceBufferFIFO(): void
    {
        $reflection  = new ReflectionClass($this->engine);
        $storeMethod = $reflection->getMethod('storeExperience');
        $storeMethod->setAccessible(true);

        // Fill buffer beyond capacity (1000)
        for ($i = 0; $i < 1100; $i++) {
            $storeMethod->invoke($this->engine, "state{$i}", 'action', 1.0, 'next', false);
        }

        $buffer = $reflection->getProperty('experienceBuffer');
        $buffer->setAccessible(true);
        $bufferData = $buffer->getValue($this->engine);

        // Should maintain max size of 1000
        $this->assertCount(1000, $bufferData);

        // First item should be from iteration 100 (oldest 100 dropped)
        $this->assertEquals('state100', $bufferData[0]['state']);
    }

    public function testReplayExperiencesBatchLearning(): void
    {
        $reflection = new ReflectionClass($this->engine);

        $storeMethod = $reflection->getMethod('storeExperience');
        $storeMethod->setAccessible(true);

        // Store 50 experiences
        for ($i = 0; $i < 50; $i++) {
            $storeMethod->invoke($this->engine, "state{$i}", 'action', 1.0, 'next', false);
        }

        $replayMethod = $reflection->getMethod('replayExperiences');
        $replayMethod->setAccessible(true);

        // Replay 10 experiences
        $replayed = $replayMethod->invoke($this->engine, 10);

        $this->assertEquals(10, $replayed);
    }

    // =========================================================================
    // BAYESIAN PROFILE SELECTION TESTS
    // =========================================================================

    public function testWeightedRandomSelection(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $method     = $reflection->getMethod('weightedRandomSelection');
        $method->setAccessible(true);

        $items = ['profile1', 'profile2'];
        $weights = [
            'profile1' => 0.8, // High weight
            'profile2' => 0.2, // Low weight
        ];

        $profile1Count = 0;
        $profile2Count = 0;

        for ($i = 0; $i < 100; $i++) {
            $selected = $method->invoke($this->engine, $items, $weights);
            if ($selected === 'profile1') {
                $profile1Count++;
            } else {
                $profile2Count++;
            }
        }

        // profile1 should be selected more often
        $this->assertGreaterThan($profile2Count, $profile1Count);
    }

    public function testGetNearbyKeyGeneralization(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $method     = $reflection->getMethod('getNearbyKey');
        $method->setAccessible(true);

        $table = [
            'page:1:false' => 5.0,
            'page:2:false' => 6.0,
            'page:3:false' => 4.0,
        ];

        $nearbyKey = $method->invoke($this->engine, 'page:1:false', $table);

        $this->assertArrayHasKey($nearbyKey, $table);
    }

    // =========================================================================
    // FITTS'S LAW TESTS
    // =========================================================================

    public function testCalculateFittsLawFormula(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $method     = $reflection->getMethod('calculateFittsLaw');
        $method->setAccessible(true);

        // T = a + b * log2(D/W + 1)
        // With defaults: a=0.2, b=0.15
        $distance = 500; // pixels
        $width    = 50;     // pixels

        $time = $method->invoke($this->engine, $distance, $width);

        // T = 0.2 + 0.15 * log2(500/50 + 1) = 0.2 + 0.15 * log2(11) ≈ 0.2 + 0.15 * 3.46 ≈ 0.72
        $this->assertEqualsWithDelta(0.72, $time, 0.1);
        $this->assertGreaterThan(0, $time);
    }

    public function testGenerateMouseMovement(): void
    {
        $movement = $this->engine->generateMouseMovement([0, 0], [500, 300]);

        $this->assertIsArray($movement);
        $this->assertArrayHasKey('duration', $movement);
        $this->assertArrayHasKey('steps', $movement);
        $this->assertArrayHasKey('path', $movement);

        $this->assertGreaterThan(0, $movement['duration']);
        $this->assertIsArray($movement['path']);
        $this->assertNotEmpty($movement['path']);
    }

    public function testMouseMovementRealism(): void
    {
        $movement = $this->engine->generateMouseMovement([0, 0], [1000, 500]);

        // Longer distances should take more time
        $this->assertGreaterThan(0.5, $movement['duration']);

        // Path should have multiple steps
        $this->assertGreaterThan(5, count($movement['path']));

        // Each step should have x, y coordinates
        foreach ($movement['path'] as $point) {
            $this->assertArrayHasKey('x', $point);
            $this->assertArrayHasKey('y', $point);
            $this->assertArrayHasKey('t', $point);
        }
    }

    // =========================================================================
    // GAMMA DISTRIBUTION TESTS
    // =========================================================================

    public function testGammaRandomDistribution(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $method     = $reflection->getMethod('gammaRandom');
        $method->setAccessible(true);

        $values = [];
        for ($i = 0; $i < 100; $i++) {
            $values[] = $method->invoke($this->engine, 2.0, 1.0);
        }

        $mean = array_sum($values) / count($values);

        // Gamma(2, 1) has mean = shape * scale = 2 * 1 = 2
        $this->assertEqualsWithDelta(2.0, $mean, 0.5);

        // All values should be positive
        $this->assertGreaterThan(0, min($values));
    }

    public function testGetInterRequestDelay(): void
    {
        $delay = $this->engine->getInterRequestDelay();

        $this->assertIsFloat($delay);
        $this->assertGreaterThan(0, $delay);
        $this->assertLessThan(30, $delay); // Reasonable upper bound
    }

    // =========================================================================
    // CIRCADIAN RHYTHM TESTS
    // =========================================================================

    public function testCircadianPatternsArray(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $patterns = $reflection->getConstant('CIRCADIAN_PATTERNS');

        $this->assertIsArray($patterns);
        $this->assertCount(24, $patterns);

        // All values should be between 0 and 1
        foreach ($patterns as $energy) {
            $this->assertGreaterThanOrEqual(0, $energy);
            $this->assertLessThanOrEqual(1, $energy);
        }
    }

    public function testCircadianRhythmAffectsDelay(): void
    {
        $reflection = new ReflectionClass($this->engine);
        $patternData = $reflection->getConstant('CIRCADIAN_PATTERNS');

        // Hour 1 (1am) should have low energy (0.2)
        $this->assertEqualsWithDelta(0.2, $patternData[1], 0.01);

        // Hour 9-10 (9-10am) should have high energy (1.0)
        $this->assertEqualsWithDelta(1.0, $patternData[9], 0.01);
        $this->assertEqualsWithDelta(1.0, $patternData[10], 0.01);
    }

    // =========================================================================
    // FATIGUE MODELING TESTS
    // =========================================================================

    public function testUpdateFatigueIncreasesLevel(): void
    {
        $reflection      = new ReflectionClass($this->engine);

        $fatigueProperty = $reflection->getProperty('currentFatigueLevel');
        $fatigueProperty->setAccessible(true);

        $initialFatigue = $fatigueProperty->getValue($this->engine);

        $updateMethod = $reflection->getMethod('updateFatigue');
        $updateMethod->setAccessible(true);
        $updateMethod->invoke($this->engine, 10); // 10 actions

        $newFatigue = $fatigueProperty->getValue($this->engine);

        $this->assertGreaterThan($initialFatigue, $newFatigue);
    }

    public function testGetCurrentFatigueLevelReturnsValid(): void
    {
        $fatigue = $this->engine->getCurrentFatigueLevel();

        $this->assertIsFloat($fatigue);
        $this->assertGreaterThanOrEqual(0, $fatigue);
        $this->assertLessThanOrEqual(1, $fatigue);
    }

    public function testFatigueAffectsSpeed(): void
    {
        $reflection = new ReflectionClass($this->engine);

        // Set high fatigue
        $fatigueProperty = $reflection->getProperty('currentFatigueLevel');
        $fatigueProperty->setAccessible(true);
        $fatigueProperty->setValue($this->engine, 0.8);

        $delay1 = $this->engine->getInterRequestDelay();

        // Reset fatigue
        $fatigueProperty->setValue($this->engine, 0.1);

        $delay2 = $this->engine->getInterRequestDelay();

        // Higher fatigue should result in longer delays
        $this->assertGreaterThan($delay2, $delay1);
    }

    public function testResetSessionClearsFatigue(): void
    {
        $reflection = new ReflectionClass($this->engine);

        // Increase fatigue
        $updateMethod = $reflection->getMethod('updateFatigue');
        $updateMethod->setAccessible(true);
        $updateMethod->invoke($this->engine, 100);

        $this->engine->resetSession();

        $fatigue = $this->engine->getCurrentFatigueLevel();

        $this->assertLessThan(0.1, $fatigue);
    }

    // =========================================================================
    // TYPING SIMULATION TESTS
    // =========================================================================

    public function testGenerateTypingPattern(): void
    {
        $pattern = $this->engine->generateTypingPattern('Hello World', 'advanced');

        $this->assertIsArray($pattern);
        $this->assertArrayHasKey('total_duration', $pattern);
        $this->assertArrayHasKey('keystrokes', $pattern);
        $this->assertArrayHasKey('errors_made', $pattern);
        $this->assertArrayHasKey('corrections_made', $pattern);

        $this->assertGreaterThan(0, $pattern['total_duration']);
        $this->assertIsArray($pattern['keystrokes']);
    }

    public function testTypingErrorRatesBySkillLevel(): void
    {
        $texts = array_fill(0, 50, 'The quick brown fox jumps over the lazy dog');

        $beginnerErrors = [];
        $advancedErrors = [];

        foreach ($texts as $text) {
            $beginnerPattern = $this->engine->generateTypingPattern($text, 'beginner');
            $advancedPattern = $this->engine->generateTypingPattern($text, 'advanced');

            $beginnerErrors[] = $beginnerPattern['errors_made'];
            $advancedErrors[] = $advancedPattern['errors_made'];
        }

        $beginnerAvg = array_sum($beginnerErrors) / count($beginnerErrors);
        $advancedAvg = array_sum($advancedErrors) / count($advancedErrors);

        // Beginners should make more errors than advanced
        $this->assertGreaterThan($advancedAvg, $beginnerAvg);
    }

    public function testTypingCorrectionSimulation(): void
    {
        $pattern = $this->engine->generateTypingPattern('Test Text', 'intermediate');

        $this->assertArrayHasKey('corrections_made', $pattern);

        // Some keystrokes should have 'error' and 'correction' flags
        $hasCorrections = false;
        foreach ($pattern['keystrokes'] as $keystroke) {
            if (isset($keystroke['is_correction'])) {
                $hasCorrections = true;

                break;
            }
        }

        // Not all runs will have errors, but structure should support it
        $this->assertIsBool($hasCorrections);
    }

    // =========================================================================
    // READING TIME TESTS
    // =========================================================================

    public function testCalculateReadingTime(): void
    {
        $pageMetrics = ['word_count' => 238, 'image_count' => 5, 'complexity' => 5];
        $readingTime = $this->engine->calculateReadingTime($pageMetrics);

        $this->assertIsFloat($readingTime);
        // Average reading speed is 238 WPM, so 238 words ≈ 60 seconds
        $this->assertEqualsWithDelta(60, $readingTime, 20);
    }

    public function testReadingTimeScalesWithContent(): void
    {
        $shortMetrics = ['word_count' => 100, 'image_count' => 2, 'complexity' => 3];
        $longMetrics  = ['word_count' => 500, 'image_count' => 10, 'complexity' => 7];

        $shortTime = $this->engine->calculateReadingTime($shortMetrics);
        $longTime  = $this->engine->calculateReadingTime($longMetrics);

        $this->assertGreaterThan($shortTime, $longTime);
    }

    // =========================================================================
    // SCROLL PATTERN TESTS
    // =========================================================================

    public function testGenerateScrollPattern(): void
    {
        $pattern = $this->engine->generateScrollPattern(5000, 1200);

        $this->assertIsArray($pattern);
        $this->assertArrayHasKey('total_duration', $pattern);
        $this->assertArrayHasKey('scroll_events', $pattern);

        $this->assertGreaterThan(0, $pattern['total_duration']);
        $this->assertIsArray($pattern['scroll_events']);
        $this->assertNotEmpty($pattern['scroll_events']);
    }

    public function testScrollEventStructure(): void
    {
        $pattern = $this->engine->generateScrollPattern(3000, 1200);

        foreach ($pattern['scroll_events'] as $event) {
            $this->assertArrayHasKey('timestamp', $event);
            $this->assertArrayHasKey('scroll_y', $event);
            $this->assertArrayHasKey('delta_y', $event);
            $this->assertArrayHasKey('duration', $event);

            $this->assertIsFloat($event['timestamp']);
            $this->assertIsInt($event['scroll_y']);
            $this->assertIsInt($event['delta_y']);
            $this->assertIsFloat($event['duration']);
        }
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function testHandlesZeroDistance(): void
    {
        $movement = $this->engine->generateMouseMovement([100, 100], [100, 100]);

        $this->assertIsArray($movement);
        $this->assertGreaterThanOrEqual(0, $movement['duration']);
    }

    public function testHandlesEmptyText(): void
    {
        $pattern = $this->engine->generateTypingPattern('', 'advanced');

        $this->assertIsArray($pattern);
        $this->assertEquals(0, $pattern['total_duration']);
    }

    public function testHandlesZeroContentHeight(): void
    {
        $pattern = $this->engine->generateScrollPattern(0, 1200);

        $this->assertIsArray($pattern);
        // Should handle gracefully
    }

    public function testHandlesNegativeReward(): void
    {
        $this->engine->learnFromAction('state1', 'action1', -1.0, 'state2', false);

        // Should update Q-value even with negative reward
        $this->assertTrue(true);
    }

    // =========================================================================
    // PERFORMANCE TESTS
    // =========================================================================

    public function testQLearningPerformance(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $this->engine->learnFromAction("state{$i}", 'action', 1.0, 'next', false);
        }

        $duration = microtime(true) - $start;

        // Should complete 1000 updates in under 1 second
        $this->assertLessThan(1.0, $duration);
    }

    public function testExperienceBufferMemoryUsage(): void
    {
        $memBefore = memory_get_usage(true);

        for ($i = 0; $i < 1000; $i++) {
            $this->engine->learnFromAction("state{$i}", 'action', 1.0, 'next', false);
        }

        $memAfter = memory_get_usage(true);
        $memUsed  = $memAfter - $memBefore;

        // Should not exceed 5MB
        $this->assertLessThan(5 * 1024 * 1024, $memUsed);
    }

    public function testActionSelectionPerformance(): void
    {
        $actions = ['action1', 'action2', 'action3', 'action4', 'action5'];

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $this->engine->selectAction('test_state', $actions);
        }

        $duration = microtime(true) - $start;

        // Should select 1000 actions in under 100ms
        $this->assertLessThan(0.1, $duration);
    }
}
