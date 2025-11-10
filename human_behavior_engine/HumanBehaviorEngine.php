<?php
/**
 * Human Behavior Engine
 *
 * Scientifically accurate human browsing behavior simulation
 * Based on behavioral psychology, cognitive science, and UX research
 * Makes bot traffic indistinguishable from real humans
 *
 * Features:
 * - Realistic mouse movement patterns (Fitts's Law)
 * - Natural reading time (based on word count + complexity)
 * - Human attention patterns (F-pattern, Z-pattern scanning)
 * - Circadian rhythm scheduling
 * - Fatigue modeling (slower responses over time)
 * - Realistic scroll behavior (non-linear acceleration)
 * - Natural typos and corrections
 * - Genuine browsing patterns (not just product pages)
 * - Session duration variance (not predictable)
 * - Multi-page journeys (not single-page hits)
 *
 * @package CIS\Crawlers
 * @version 1.0.0
 */

namespace CIS\Crawlers;

class HumanBehaviorEngine {

    private $logger;
    private $sessionStart;
    private $pagesVisited = 0;
    private $totalTimeSpent = 0;
    private $currentFatigueLevel = 0;
    private $currentProfile;

    // Behavioral constants based on research
    const AVG_READING_SPEED_WPM = 238; // Average adult reading speed
    const AVG_SCANNING_SPEED_WPM = 450; // Scanning is faster than reading
    const MIN_ATTENTION_SPAN_SEC = 8; // Modern web users
    const AVG_ATTENTION_SPAN_SEC = 15;
    const MAX_ATTENTION_SPAN_SEC = 45;

    // Circadian rhythm patterns (hour => energy_multiplier)
    const CIRCADIAN_PATTERNS = [
        0 => 0.3,  // Midnight - very slow
        1 => 0.2,  // 1am - slowest
        2 => 0.2,
        3 => 0.25,
        4 => 0.3,
        5 => 0.4,
        6 => 0.6,  // 6am - waking up
        7 => 0.8,
        8 => 0.9,
        9 => 1.0,  // 9am - peak morning
        10 => 1.0,
        11 => 0.95,
        12 => 0.85, // Lunch dip
        13 => 0.75,
        14 => 0.8,
        15 => 0.9,  // Afternoon recovery
        16 => 0.95,
        17 => 0.9,
        18 => 0.85,
        19 => 0.8,
        20 => 0.75, // Evening slowdown
        21 => 0.7,
        22 => 0.6,
        23 => 0.4,
    ];

    // Realistic browsing profiles
    const BROWSING_PROFILES = [
        'quick_scanner' => [
            'reading_speed_multiplier' => 1.8,
            'scroll_speed_multiplier' => 1.5,
            'attention_span_multiplier' => 0.6,
            'bounce_rate' => 0.35,
            'pages_per_session' => [2, 5],
            'personality' => 'impatient, goal-oriented, fast decisions',
        ],
        'thorough_researcher' => [
            'reading_speed_multiplier' => 0.7,
            'scroll_speed_multiplier' => 0.6,
            'attention_span_multiplier' => 2.0,
            'bounce_rate' => 0.15,
            'pages_per_session' => [8, 20],
            'personality' => 'methodical, reads reviews, compares prices',
        ],
        'casual_browser' => [
            'reading_speed_multiplier' => 1.0,
            'scroll_speed_multiplier' => 1.0,
            'attention_span_multiplier' => 1.0,
            'bounce_rate' => 0.50,
            'pages_per_session' => [3, 8],
            'personality' => 'exploring, no urgent intent, easily distracted',
        ],
        'mobile_user' => [
            'reading_speed_multiplier' => 0.85,
            'scroll_speed_multiplier' => 1.3,
            'attention_span_multiplier' => 0.5,
            'bounce_rate' => 0.55,
            'pages_per_session' => [2, 6],
            'personality' => 'on-the-go, shorter sessions, thumb-scrolling',
        ],
        'price_hunter' => [
            'reading_speed_multiplier' => 2.0,
            'scroll_speed_multiplier' => 1.8,
            'attention_span_multiplier' => 0.4,
            'bounce_rate' => 0.40,
            'pages_per_session' => [5, 12],
            'personality' => 'only looks at prices, ignores content',
        ],
    ];

    public function __construct(\CIS\Crawlers\CentralLogger $logger) {
        $this->logger = $logger;
        $this->sessionStart = microtime(true);
        $this->selectBrowsingProfile();
    }

    /**
     * Select a random browsing profile for this session
     */
    private function selectBrowsingProfile() {
        $profiles = array_keys(self::BROWSING_PROFILES);
        $profileName = $profiles[array_rand($profiles)];
        $this->currentProfile = self::BROWSING_PROFILES[$profileName];
        $this->currentProfile['name'] = $profileName;

        $this->logger->log('debug', 'Human behavior profile selected', [
            'profile' => $profileName,
            'personality' => $this->currentProfile['personality'],
        ]);
    }

    /**
     * Calculate realistic page reading time based on content
     *
     * Uses:
     * - Word count estimation
     * - Image count (pauses to view)
     * - Page complexity
     * - Current fatigue level
     * - Circadian rhythm
     * - Browsing profile
     * - VARIABLE reading speed (not fixed 238 WPM!)
     *
     * @param array $pageMetrics Content metrics
     * @return float Seconds to spend on page
     */
    public function calculateReadingTime(array $pageMetrics = []) {
        // Estimate word count (if not provided) - CHAOTIC boundaries
        $wordCount = $pageMetrics['word_count'] ?? rand(
            rand(185, 215),  // Not fixed 200!
            rand(785, 815)   // Not fixed 800!
        );
        $imageCount = $pageMetrics['image_count'] ?? rand(
            rand(2, 4),      // Not fixed 3!
            rand(14, 16)     // Not fixed 15!
        );
        $complexity = $pageMetrics['complexity'] ?? rand(1, 10); // 1=simple, 10=complex

        // VARIABLE base reading speed (real-world distribution)
        // Average adult: 200-250 WPM (mean: 238 WPM)
        // But individuals vary: 150-400 WPM range
        // Add session-specific variance with CHAOTIC multipliers
        $sessionReadingSpeed = self::AVG_READING_SPEED_WPM * $this->randomFloat(
            $this->randomFloat(0.82, 0.88),  // Not fixed 0.85!
            $this->randomFloat(1.12, 1.18)   // Not fixed 1.15!
        );

        // Base reading time (words per minute adjusted for scanning)
        $baseReadingSpeed = $sessionReadingSpeed * $this->currentProfile['reading_speed_multiplier'];
        $baseTime = ($wordCount / $baseReadingSpeed) * 60; // Convert to seconds

        // Add time for viewing images (VARIABLE - not fixed 2-5!)
        // Fast scanners: 1-3 seconds per image
        // Careful readers: 3-8 seconds per image
        $imageViewTimeMin = $this->currentProfile['reading_speed_multiplier'] > 1.5
            ? $this->randomFloat(
                $this->randomFloat(0.8, 1.2),   // Not fixed 1.0!
                $this->randomFloat(2.8, 3.2)    // Not fixed 3.0!
              )
            : $this->randomFloat(
                $this->randomFloat(2.3, 2.7),   // Not fixed 2.5!
                $this->randomFloat(6.8, 7.2)    // Not fixed 7.0!
              );
        $imageViewTimeMax = $imageViewTimeMin + $this->randomFloat(
            $this->randomFloat(0.8, 1.2),       // Not fixed 1.0!
            $this->randomFloat(3.8, 4.2)        // Not fixed 4.0!
        );
        $imageTime = $imageCount * $this->randomFloat($imageViewTimeMin, $imageViewTimeMax);

        // Complexity adjustment (harder content = slower reading)
        // Not linear! Exponential difficulty with CHAOTIC multipliers
        $complexityMultiplier = 1 + ($complexity * $this->randomFloat(
            $this->randomFloat(0.075, 0.085),   // Not fixed 0.08!
            $this->randomFloat(0.145, 0.155)    // Not fixed 0.15!
        ));

        // Circadian rhythm adjustment
        $hour = (int)date('H');
        $circadianMultiplier = self::CIRCADIAN_PATTERNS[$hour];

        // Fatigue adjustment (slower as session progresses) - CHAOTIC multiplier
        $fatigueMultiplier = 1 + ($this->currentFatigueLevel * $this->randomFloat(0.18, 0.22)); // Not fixed 0.2!

        // Calculate total time
        $totalTime = ($baseTime + $imageTime) * $complexityMultiplier * $circadianMultiplier * $fatigueMultiplier;

        // Apply minimum and maximum bounds (realistic)
        // But VARY the bounds per session (not everyone has same attention span!)
        $minTime = self::MIN_ATTENTION_SPAN_SEC * $this->currentProfile['attention_span_multiplier'] * $this->randomFloat(
            $this->randomFloat(0.65, 0.75),     // Not fixed 0.7!
            $this->randomFloat(1.25, 1.35)      // Not fixed 1.3!
        );
        $maxTime = self::MAX_ATTENTION_SPAN_SEC * $this->currentProfile['attention_span_multiplier'] * $this->randomFloat(
            $this->randomFloat(0.75, 0.85),     // Not fixed 0.8!
            $this->randomFloat(1.15, 1.25)      // Not fixed 1.2!
        );
        $totalTime = max($minTime, min($maxTime, $totalTime));

        // Add natural variance (humans aren't consistent) - WIDER range with CHAOTIC boundaries
        $variance = $this->randomFloat(
            $this->randomFloat(0.72, 0.78),     // Not fixed 0.75!
            $this->randomFloat(1.32, 1.38)      // Not fixed 1.35!
        );
        $totalTime *= $variance;

        $this->logger->log('debug', 'Calculated realistic reading time', [
            'base_time' => round($baseTime, 2),
            'image_time' => round($imageTime, 2),
            'complexity_mult' => round($complexityMultiplier, 2),
            'circadian_mult' => round($circadianMultiplier, 2),
            'fatigue_mult' => round($fatigueMultiplier, 2),
            'total_time' => round($totalTime, 2),
            'word_count' => $wordCount,
            'profile' => $this->currentProfile['name'],
        ]);

        // Increase fatigue
        $this->currentFatigueLevel += 0.1;

        return $totalTime;
    }

    /**
     * Generate realistic inter-request delay
     *
     * Not uniform random! Based on:
     * - Gamma distribution (human reaction times)
     * - Circadian rhythm
     * - Fatigue
     * - Profile characteristics
     *
     * @param string $actionType Type of action (click, scroll, navigate, etc.)
     * @return float Seconds to wait
     */
    public function getInterRequestDelay(string $actionType = 'navigate') {
        // Base delays for different actions (in seconds)
        $baseDelays = [
            'click' => [0.3, 1.2],        // Fast reaction
            'scroll' => [0.5, 2.0],       // Medium
            'navigate' => [1.5, 4.5],     // Page transitions
            'search' => [2.0, 8.0],       // Thinking + typing
            'compare' => [3.0, 12.0],     // Decision making
            'checkout' => [5.0, 20.0],    // Form filling
        ];

        $range = $baseDelays[$actionType] ?? [1.0, 5.0];

        // Use Gamma distribution (realistic human reaction times)
        // Shape=2, Scale=1 creates right-skewed distribution
        $delay = $this->gammaRandom(2.0, 1.0);

        // Scale to our range
        $delay = $range[0] + ($delay * ($range[1] - $range[0]));

        // Apply circadian rhythm
        $hour = (int)date('H');
        $circadianMultiplier = self::CIRCADIAN_PATTERNS[$hour];
        $delay *= (2.0 - $circadianMultiplier); // Slower at night

        // Apply fatigue (exponential slowdown)
        $fatigueMultiplier = 1 + ($this->currentFatigueLevel * 0.3);
        $delay *= $fatigueMultiplier;

        // Apply profile speed
        $delay /= $this->currentProfile['reading_speed_multiplier'];

        // Occasional "distraction" spikes (5% chance of 2-3x delay)
        if ($this->randomFloat(0, 1) < 0.05) {
            $delay *= $this->randomFloat(2.0, 3.0);
            $this->logger->log('debug', 'Simulated distraction - longer delay', [
                'action' => $actionType,
                'delay' => round($delay, 2),
            ]);
        }

        // Occasional "impatient" drops (10% chance of 0.5x delay)
        if ($this->randomFloat(0, 1) < 0.10) {
            $delay *= $this->randomFloat(0.4, 0.6);
            $this->logger->log('debug', 'Simulated impatience - shorter delay', [
                'action' => $actionType,
                'delay' => round($delay, 2),
            ]);
        }

        $this->logger->log('debug', 'Generated inter-request delay', [
            'action' => $actionType,
            'base_range' => $range,
            'circadian_mult' => round($circadianMultiplier, 2),
            'fatigue_mult' => round($fatigueMultiplier, 2),
            'final_delay' => round($delay, 2),
            'hour' => $hour,
        ]);

        return max(0.2, $delay); // Minimum 200ms (human limit)
    }

    /**
     * Generate realistic scroll behavior with MAXIMUM VARIANCE
     *
     * Humans don't scroll at constant speed!
     * - Initial acceleration
     * - Mid-scroll cruising speed
     * - Deceleration at end
     * - Pauses to read interesting sections
     * - Variable scroll distances (NOT PREDICTABLE!)
     * - Device-specific behavior (mouse wheel vs trackpad vs touch)
     * - Reading patterns (skim vs careful)
     *
     * @param int $pageHeight Total page height in pixels
     * @return array Scroll pattern with positions and timings
     */
    public function generateScrollPattern(int $pageHeight = 5000) {
        $scrollPattern = [];
        $currentPosition = 0;
        $scrollCount = 0;

        // Number of scroll actions (HIGHLY VARIABLE by profile + session)
        $baseScrollFactor = $this->currentProfile['scroll_speed_multiplier'] * $this->randomFloat(0.6, 1.4);
        $numScrolls = rand(
            (int)($pageHeight / $this->randomFloat(1000, 1400) * $baseScrollFactor),
            (int)($pageHeight / $this->randomFloat(500, 700) * $baseScrollFactor)
        );
        $numScrolls = max(2, $numScrolls); // At least 2 scrolls

        while ($currentPosition < $pageHeight && $scrollCount < $numScrolls) {
            // Realistic scroll distance (CHAOTIC BOUNDARIES - NO PREDICTABLE RANGES!)
            // Even the category thresholds vary per action
            $scrollBehaviorRoll = $this->randomFloat(0, 100);

            // VARIABLE category boundaries (not fixed 10, 60, 85!)
            $tinyThreshold = $this->randomFloat(8, 13);      // ~10% but varies
            $normalThreshold = $this->randomFloat(55, 65);   // ~60% but varies
            $mediumThreshold = $this->randomFloat(80, 88);   // ~85% but varies

            if ($scrollBehaviorRoll < $tinyThreshold) {
                // Tiny scroll - CHAOTIC range boundaries
                $min = rand(35, 65);   // Not fixed 50!
                $max = rand(155, 205); // Not fixed 180!
                $scrollDistance = rand($min, $max);
            } elseif ($scrollBehaviorRoll < $normalThreshold) {
                // Normal scroll - CHAOTIC range boundaries
                $min = rand(160, 210);  // Not fixed 180!
                $max = rand(620, 680);  // Not fixed 650!
                $scrollDistance = rand($min, $max);
            } elseif ($scrollBehaviorRoll < $mediumThreshold) {
                // Medium jump - CHAOTIC range boundaries
                $min = rand(620, 685);   // Not fixed 650!
                $max = rand(1170, 1235); // Not fixed 1200!
                $scrollDistance = rand($min, $max);
            } else {
                // Big jump - CHAOTIC range boundaries
                $min = rand(1170, 1240);  // Not fixed 1200!
                $max = rand(2450, 2550);  // Not fixed 2500!
                $scrollDistance = rand($min, $max);
            }

            // Device-specific variance
            if ($this->currentProfile['name'] === 'mobile_user') {
                // Touch scrolling is more variable (finger swipes)
                $scrollDistance = (int)($scrollDistance * $this->randomFloat(0.7, 1.8));
            }

            $currentPosition += $scrollDistance;
            $currentPosition = min($currentPosition, $pageHeight);

            // Pause duration at this position (reading time) - CHAOTIC boundaries
            $pauseDuration = $this->randomFloat(
                $this->randomFloat(0.25, 0.35),  // Not fixed 0.3!
                $this->randomFloat(5.45, 5.55)   // Not fixed 5.5!
            );

            // Content interest patterns (NOT PREDICTABLE!)
            $interestRoll = $this->randomFloat(0, 100);

            // CHAOTIC interest thresholds (not fixed 15, 30, 85!)
            $veryInterestingThreshold = $this->randomFloat(13, 17);    // ~15% but varies
            $modInterestingThreshold = $this->randomFloat(28, 32);     // ~30% but varies
            $normalThreshold = $this->randomFloat(83, 87);             // ~85% but varies

            if ($interestRoll < $veryInterestingThreshold) {
                // Very interesting section - long pause with CHAOTIC multipliers
                $pauseDuration *= $this->randomFloat(
                    $this->randomFloat(2.8, 3.2),   // Not fixed 3.0!
                    $this->randomFloat(7.8, 8.2)    // Not fixed 8.0!
                );
            } elseif ($interestRoll < $modInterestingThreshold) {
                // Moderately interesting - medium pause with CHAOTIC multipliers
                $pauseDuration *= $this->randomFloat(
                    $this->randomFloat(1.4, 1.6),   // Not fixed 1.5!
                    $this->randomFloat(2.9, 3.1)    // Not fixed 3.0!
                );
            } elseif ($interestRoll < $normalThreshold) {
                // Normal - slight variance with CHAOTIC multipliers
                $pauseDuration *= $this->randomFloat(
                    $this->randomFloat(0.75, 0.85),  // Not fixed 0.8!
                    $this->randomFloat(1.45, 1.55)   // Not fixed 1.5!
                );
            } else {
                // Boring section - barely pause (fast scroll past) with CHAOTIC multipliers
                $pauseDuration *= $this->randomFloat(
                    $this->randomFloat(0.18, 0.22),  // Not fixed 0.2!
                    $this->randomFloat(0.48, 0.52)   // Not fixed 0.5!
                );
            }

            // Apply profile attention span WITH VARIANCE and CHAOTIC boundaries
            $pauseDuration *= $this->currentProfile['attention_span_multiplier'] * $this->randomFloat(
                $this->randomFloat(0.65, 0.75),      // Not fixed 0.7!
                $this->randomFloat(1.35, 1.45)       // Not fixed 1.4!
            );

            // Scroll velocity (NOT CONSTANT!)
            // Varies wildly depending on intent and device with CHAOTIC boundaries
            $scrollVelocity = $scrollDistance / $this->randomFloat(
                $this->randomFloat(0.13, 0.17),      // Not fixed 0.15!
                $this->randomFloat(1.18, 1.22)       // Not fixed 1.2!
            );

            // Occasional instant snap scroll (keyboard Page Down, space bar, etc.)
            // CHAOTIC probability threshold (not fixed 0.08!)
            if ($this->randomFloat(0, 1) < $this->randomFloat(0.07, 0.09)) {
                $scrollVelocity *= $this->randomFloat(
                    $this->randomFloat(4.8, 5.2),    // Not fixed 5.0!
                    $this->randomFloat(14.7, 15.3)   // Not fixed 15.0!
                );
                $pauseDuration *= $this->randomFloat(
                    $this->randomFloat(0.28, 0.32),  // Not fixed 0.3!
                    $this->randomFloat(0.58, 0.62)   // Not fixed 0.6!
                );
            }

            $scrollPattern[] = [
                'position' => $currentPosition,
                'distance' => $scrollDistance,
                'pause_duration' => $pauseDuration,
                'velocity' => $scrollVelocity,
            ];

            $scrollCount++;
        }

        $this->logger->log('debug', 'Generated realistic scroll pattern', [
            'page_height' => $pageHeight,
            'num_scrolls' => count($scrollPattern),
            'total_time' => round(array_sum(array_column($scrollPattern, 'pause_duration')), 2),
            'profile' => $this->currentProfile['name'],
        ]);

        return $scrollPattern;
    }

    /**
     * Decide if should visit another page (realistic bounce rate)
     *
     * @return bool True if should continue, false if should exit
     */
    public function shouldContinueBrowsing(): bool {
        $this->pagesVisited++;

        // Check against profile's typical pages per session
        $targetPages = rand(
            $this->currentProfile['pages_per_session'][0],
            $this->currentProfile['pages_per_session'][1]
        );

        // Natural session fatigue (exponential decay)
        $continueChance = 1.0 - ($this->pagesVisited / $targetPages);
        $continueChance = max(0, $continueChance);

        // Apply bounce rate
        if ($this->pagesVisited === 1) {
            $continueChance = 1.0 - $this->currentProfile['bounce_rate'];
        }

        // Circadian rhythm affects attention span
        $hour = (int)date('H');
        $circadianMultiplier = self::CIRCADIAN_PATTERNS[$hour];
        $continueChance *= $circadianMultiplier;

        // Random decision
        $continue = $this->randomFloat(0, 1) < $continueChance;

        $this->logger->log('debug', 'Evaluated session continuation', [
            'pages_visited' => $this->pagesVisited,
            'target_pages' => $targetPages,
            'continue_chance' => round($continueChance, 2),
            'decision' => $continue ? 'continue' : 'exit',
            'session_duration' => round(microtime(true) - $this->sessionStart, 2),
        ]);

        return $continue;
    }

    /**
     * Get realistic "next page" based on human browsing patterns
     *
     * Humans don't visit pages randomly!
     * - Category browsing (products in same category)
     * - Related products (similar items)
     * - Price comparison (similar price range)
     * - Brand exploration (same brand)
     * - Back button usage (20% of navigations)
     *
     * @param array $currentPage Current page context
     * @param array $availablePages Available pages to visit
     * @return array Next page to visit
     */
    public function getNextPage(array $currentPage, array $availablePages): array {
        // 20% chance to "use back button" (revisit previous pages)
        if ($this->pagesVisited > 1 && $this->randomFloat(0, 1) < 0.20) {
            $this->logger->log('debug', 'Simulated back button press');
            // Return a previously visited page (simplified: just return current)
            return $currentPage;
        }

        // Prioritize related pages based on user intent
        $scoredPages = [];
        foreach ($availablePages as $page) {
            $score = 0;

            // Same category = high relevance
            if (isset($page['category'], $currentPage['category'])) {
                if ($page['category'] === $currentPage['category']) {
                    $score += 50;
                }
            }

            // Similar price = high relevance (price comparison)
            if (isset($page['price'], $currentPage['price'])) {
                $priceDiff = abs($page['price'] - $currentPage['price']);
                $score += max(0, 30 - $priceDiff);
            }

            // Same brand = medium relevance
            if (isset($page['brand'], $currentPage['brand'])) {
                if ($page['brand'] === $currentPage['brand']) {
                    $score += 25;
                }
            }

            // Add randomness (humans are unpredictable)
            $score += $this->randomFloat(0, 20);

            $scoredPages[] = ['page' => $page, 'score' => $score];
        }

        // Sort by score (highest first)
        usort($scoredPages, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        // Pick from top 5 (not always the highest - humans aren't perfect)
        $topPages = array_slice($scoredPages, 0, min(5, count($scoredPages)));
        $selectedPage = $topPages[array_rand($topPages)]['page'];

        $this->logger->log('debug', 'Selected next page based on relevance', [
            'current_page' => $currentPage['url'] ?? 'unknown',
            'next_page' => $selectedPage['url'] ?? 'unknown',
            'relevance_score' => round($topPages[0]['score'], 2),
        ]);

        return $selectedPage;
    }

    /**
     * Generate realistic mouse movement pattern
     *
     * Based on Fitts's Law: T = a + b * log2(D/W + 1)
     * - Distance to target (D)
     * - Target width (W)
     * - Not straight lines! Bezier curves with overshoot
     *
     * @param array $start Starting position [x, y]
     * @param array $target Target position [x, y]
     * @return array Movement pattern with timestamps
     */
    public function generateMouseMovement(array $start, array $target): array {
        $distance = sqrt(pow($target[0] - $start[0], 2) + pow($target[1] - $start[1], 2));

        // Fitts's Law constants (empirically derived)
        $a = 0.1; // seconds
        $b = 0.15; // seconds
        $w = 50; // Assumed target width in pixels

        // Calculate movement time
        $movementTime = $a + $b * log(($distance / $w) + 1, 2);

        // Apply profile speed
        $movementTime /= $this->currentProfile['reading_speed_multiplier'];

        // Generate curved path (not straight line!)
        $numPoints = max(5, (int)($distance / 100)); // More points for longer distances
        $path = [];

        for ($i = 0; $i <= $numPoints; $i++) {
            $t = $i / $numPoints;

            // Bezier curve with slight overshoot
            $overshoot = sin($t * M_PI) * $this->randomFloat(5, 20);

            $x = $start[0] + ($target[0] - $start[0]) * $t + $overshoot;
            $y = $start[1] + ($target[1] - $start[1]) * $t;

            $path[] = [
                'x' => (int)$x,
                'y' => (int)$y,
                'time' => $movementTime * $t,
            ];
        }

        // Final position (ensure exact target)
        $path[] = [
            'x' => $target[0],
            'y' => $target[1],
            'time' => $movementTime,
        ];

        return $path;
    }

    /**
     * Simulate typing speed with REALISTIC VARIANCE (NO PATTERNS!)
     *
     * Typing speed varies by:
     * - Individual skill level (23-120 WPM range)
     * - Time of day (circadian rhythm)
     * - Fatigue (slower over time)
     * - Content familiarity (technical terms slower)
     * - Device type (mobile vs desktop)
     * - Distraction level
     * - Error rate varies by speed (faster = more errors)
     *
     * @param string $text Text to type
     * @return array Typing pattern with timings
     */
    public function generateTypingPattern(string $text): array {
        $pattern = [];
        $textLength = strlen($text);
        $currentPosition = 0;

        // VARIABLE typing speed based on multiple factors
        // Real-world distribution: 23-120 WPM (95% of population)
        // But CHAOTIC category boundaries - not fixed percentiles!

        $skillLevelRoll = $this->randomFloat(0, 100);

        // CHAOTIC category thresholds (not fixed 20, 80, 95!)
        $slowThreshold = $this->randomFloat(17, 23);      // ~20% but varies
        $avgThreshold = $this->randomFloat(77, 83);       // ~80% but varies
        $fastThreshold = $this->randomFloat(93, 97);      // ~95% but varies

        if ($skillLevelRoll < $slowThreshold) {
            // Slow typist - CHAOTIC range boundaries
            $minWPM = $this->randomFloat(21, 25);     // Not fixed 23!
            $maxWPM = $this->randomFloat(33, 37);     // Not fixed 35!
            $baseWPM = $this->randomFloat($minWPM, $maxWPM);
            $baseErrorRate = $this->randomFloat(0.038, 0.082); // Not fixed 0.04-0.08!
        } elseif ($skillLevelRoll < $avgThreshold) {
            // Average typist - CHAOTIC range boundaries
            $minWPM = $this->randomFloat(34, 38);     // Not fixed 36!
            $maxWPM = $this->randomFloat(58, 62);     // Not fixed 60!
            $baseWPM = $this->randomFloat($minWPM, $maxWPM);
            $baseErrorRate = $this->randomFloat(0.013, 0.042); // Not fixed 0.015-0.04!
        } elseif ($skillLevelRoll < $fastThreshold) {
            // Fast typist - CHAOTIC range boundaries
            $minWPM = $this->randomFloat(59, 63);     // Not fixed 61!
            $maxWPM = $this->randomFloat(78, 82);     // Not fixed 80!
            $baseWPM = $this->randomFloat($minWPM, $maxWPM);
            $baseErrorRate = $this->randomFloat(0.018, 0.052); // Not fixed 0.02-0.05!
        } else {
            // Professional typist - CHAOTIC range boundaries
            $minWPM = $this->randomFloat(79, 83);     // Not fixed 81!
            $maxWPM = $this->randomFloat(118, 122);   // Not fixed 120!
            $baseWPM = $this->randomFloat($minWPM, $maxWPM);
            $baseErrorRate = $this->randomFloat(0.004, 0.016); // Not fixed 0.005-0.015!
        }

        // Apply circadian rhythm (slower at night)
        $hour = (int)date('H');
        $circadianMultiplier = self::CIRCADIAN_PATTERNS[$hour];
        $adjustedWPM = $baseWPM * $circadianMultiplier;

        // Apply fatigue (slower as session progresses)
        $fatigueMultiplier = 1 / (1 + ($this->currentFatigueLevel * 0.15));
        $adjustedWPM *= $fatigueMultiplier;

        // Apply profile characteristics (mobile users slower)
        $adjustedWPM *= $this->currentProfile['reading_speed_multiplier'];

        // Mobile users have higher error rate (smaller keyboard)
        if ($this->currentProfile['name'] === 'mobile_user') {
            $baseErrorRate *= $this->randomFloat(1.5, 2.5); // 1.5-2.5× more errors on mobile
        }

        // Convert WPM to chars/sec (average word = 5 chars)
        $charsPerSecond = ($adjustedWPM * 5) / 60;

        // Session-specific variance (not every keystroke is the same speed!)
        $sessionVariance = $this->randomFloat(0.8, 1.2);
        $charsPerSecond *= $sessionVariance;

        $this->logger->log('debug', 'Typing session parameters', [
            'base_wpm' => round($baseWPM, 1),
            'adjusted_wpm' => round($adjustedWPM, 1),
            'chars_per_second' => round($charsPerSecond, 2),
            'error_rate' => round($baseErrorRate * 100, 2) . '%',
            'circadian_multiplier' => round($circadianMultiplier, 2),
            'fatigue_multiplier' => round($fatigueMultiplier, 2),
            'profile' => $this->currentProfile['name'],
        ]);

        while ($currentPosition < $textLength) {
            $char = $text[$currentPosition];

            // Base delay per character (with per-keystroke variance!)
            $delay = (1 / $charsPerSecond) * $this->randomFloat(0.6, 1.4);

            // Longer pauses for:
            // - Spaces (thinking between words)
            if ($char === ' ') {
                $delay *= $this->randomFloat(1.5, 4.5); // Wide variance (thinking time)
            }
            // - Capital letters (shift key)
            if (ctype_upper($char)) {
                $delay *= $this->randomFloat(1.2, 2.0);
            }
            // - Special characters
            if (!ctype_alnum($char) && $char !== ' ') {
                $delay *= $this->randomFloat(1.3, 2.8);
            }
            // - Numbers (reach for number row)
            if (ctype_digit($char)) {
                $delay *= $this->randomFloat(1.4, 2.2);
            }

            // Start of sentence? Longer pause (thinking)
            if ($currentPosition === 0 || ($currentPosition > 0 && $text[$currentPosition - 1] === '.')) {
                $delay *= $this->randomFloat(2.0, 5.0);
            }

            // Technical terms or uncommon words? Slower typing
            // Check if within a "complex word" (just simulate with randomness)
            if ($currentPosition > 3 && $this->randomFloat(0, 1) < 0.15) {
                $delay *= $this->randomFloat(1.3, 1.8);
            }

            // VARIABLE error rate (not fixed 2%!)
            $typo = null;
            if ($this->randomFloat(0, 1) < $baseErrorRate) {
                // Generate nearby key typo
                $typo = $this->getNearbyKey($char);
                $pattern[] = [
                    'char' => $typo,
                    'delay' => $delay,
                    'is_typo' => true,
                ];

                // Variable correction delay (some people notice immediately, some take time)
                $correctionDelay = $delay * $this->randomFloat(0.3, 2.0);

                // Some errors require multiple backspaces (typo followed by more typing)
                $multipleBackspaces = $this->randomFloat(0, 1) < 0.15; // 15% chance
                if ($multipleBackspaces) {
                    $backspaceCount = rand(1, 3);
                    for ($i = 0; $i < $backspaceCount; $i++) {
                        $pattern[] = [
                            'char' => '[BACKSPACE]',
                            'delay' => $correctionDelay * $this->randomFloat(0.4, 0.8),
                            'is_correction' => true,
                        ];
                    }
                    // Retype the deleted characters
                    for ($i = 0; $i < $backspaceCount - 1; $i++) {
                        $pattern[] = [
                            'char' => $text[max(0, $currentPosition - $i - 1)],
                            'delay' => $delay * $this->randomFloat(0.7, 1.3),
                            'is_typo' => false,
                        ];
                    }
                } else {
                    // Single backspace correction
                    $pattern[] = [
                        'char' => '[BACKSPACE]',
                        'delay' => $correctionDelay,
                        'is_correction' => true,
                    ];
                }
            }

            // Occasional burst typing (muscle memory kicks in)
            // 5% chance of typing next 2-4 chars really fast
            $burstMode = $this->randomFloat(0, 1) < 0.05;
            if ($burstMode && $currentPosition < $textLength - 3) {
                $delay *= $this->randomFloat(0.3, 0.5); // Much faster
            }

            // Occasional pause (distraction, reading screen, etc.)
            // 3% chance of long pause mid-typing
            $distracted = $this->randomFloat(0, 1) < 0.03;
            if ($distracted) {
                $delay *= $this->randomFloat(3.0, 8.0); // Long pause
            }

            // Type the correct character
            $pattern[] = [
                'char' => $char,
                'delay' => $delay,
                'is_typo' => false,
            ];

            $currentPosition++;
        }

        $totalTime = array_sum(array_column($pattern, 'delay'));
        $actualWPM = ($textLength / 5) / ($totalTime / 60);

        $this->logger->log('debug', 'Generated realistic typing pattern with variance', [
            'text_length' => $textLength,
            'total_time' => round($totalTime, 2),
            'chars_per_second' => round($textLength / $totalTime, 2),
            'actual_wpm' => round($actualWPM, 1),
            'target_wpm' => round($adjustedWPM, 1),
            'typos' => count(array_filter($pattern, function($p) { return $p['is_typo'] ?? false; })),
            'corrections' => count(array_filter($pattern, function($p) { return $p['is_correction'] ?? false; })),
            'error_rate_actual' => round((count(array_filter($pattern, function($p) { return $p['is_typo'] ?? false; })) / $textLength) * 100, 2) . '%',
        ]);

        return $pattern;
    }

    /**
     * Get keyboard key near the target (for realistic typos)
     */
    private function getNearbyKey(string $char): string {
        $keyboard = [
            'q' => ['w', 'a'],
            'w' => ['q', 'e', 's'],
            'e' => ['w', 'r', 'd'],
            'r' => ['e', 't', 'f'],
            't' => ['r', 'y', 'g'],
            'y' => ['t', 'u', 'h'],
            'u' => ['y', 'i', 'j'],
            'i' => ['u', 'o', 'k'],
            'o' => ['i', 'p', 'l'],
            'p' => ['o', 'l'],
            'a' => ['q', 's', 'z'],
            's' => ['a', 'w', 'd', 'x'],
            'd' => ['s', 'e', 'f', 'c'],
            'f' => ['d', 'r', 'g', 'v'],
            'g' => ['f', 't', 'h', 'b'],
            'h' => ['g', 'y', 'j', 'n'],
            'j' => ['h', 'u', 'k', 'm'],
            'k' => ['j', 'i', 'l'],
            'l' => ['k', 'o', 'p'],
            'z' => ['a', 'x'],
            'x' => ['z', 's', 'c'],
            'c' => ['x', 'd', 'v'],
            'v' => ['c', 'f', 'b'],
            'b' => ['v', 'g', 'n'],
            'n' => ['b', 'h', 'm'],
            'm' => ['n', 'j'],
        ];

        $charLower = strtolower($char);
        if (isset($keyboard[$charLower])) {
            $nearbyKeys = $keyboard[$charLower];
            $typoKey = $nearbyKeys[array_rand($nearbyKeys)];
            return ctype_upper($char) ? strtoupper($typoKey) : $typoKey;
        }

        return $char; // No typo if key not in map
    }

    /**
     * Generate Gamma-distributed random number
     * Used for realistic reaction times (right-skewed distribution)
     *
     * @param float $shape Shape parameter (k)
     * @param float $scale Scale parameter (θ)
     * @return float Random number from Gamma distribution
     */
    private function gammaRandom(float $shape, float $scale): float {
        // Using Marsaglia and Tsang method for shape >= 1
        $d = $shape - 1/3;
        $c = 1 / sqrt(9 * $d);

        while (true) {
            do {
                $x = $this->normalRandom();
                $v = 1 + $c * $x;
            } while ($v <= 0);

            $v = $v * $v * $v;
            $u = $this->randomFloat(0, 1);

            if ($u < 1 - 0.0331 * ($x * $x) * ($x * $x)) {
                return $d * $v * $scale;
            }

            if (log($u) < 0.5 * $x * $x + $d * (1 - $v + log($v))) {
                return $d * $v * $scale;
            }
        }
    }

    /**
     * Generate normally-distributed random number (Box-Muller transform)
     */
    private function normalRandom(): float {
        static $hasSpare = false;
        static $spare;

        if ($hasSpare) {
            $hasSpare = false;
            return $spare;
        }

        $u = $this->randomFloat(0, 1);
        $v = $this->randomFloat(0, 1);

        $r = sqrt(-2 * log($u));
        $theta = 2 * M_PI * $v;

        $spare = $r * sin($theta);
        $hasSpare = true;

        return $r * cos($theta);
    }

    /**
     * Get session statistics
     */
    public function getSessionStats(): array {
        return [
            'profile' => $this->currentProfile['name'],
            'personality' => $this->currentProfile['personality'],
            'pages_visited' => $this->pagesVisited,
            'session_duration' => microtime(true) - $this->sessionStart,
            'fatigue_level' => round($this->currentFatigueLevel, 2),
            'avg_time_per_page' => $this->pagesVisited > 0
                ? round((microtime(true) - $this->sessionStart) / $this->pagesVisited, 2)
                : 0,
        ];
    }

    /**
     * Cryptographically secure random float
     */
    private function randomFloat(float $min, float $max): float {
        $range = $max - $min;
        $bytes = random_bytes(4);
        $randomInt = unpack('L', $bytes)[1];
        $randomFloat = $randomInt / 0xFFFFFFFF; // Normalize to 0-1
        return $min + ($randomFloat * $range);
    }
}
