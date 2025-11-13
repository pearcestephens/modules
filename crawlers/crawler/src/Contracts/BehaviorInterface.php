<?php

declare(strict_types=1);
/**
 * Behavior Interface.
 *
 * Contract for human behavior simulation engines
 * Defines methods for realistic browsing patterns, timing, and interaction
 *
 * @version 2.0.0 - Ultra-Sophisticated ML/AI Enhanced
 */

namespace CIS\SharedServices\Crawler\Contracts;

interface BehaviorInterface
{
    /**
     * Calculate realistic page reading time based on content analysis
     * Enhanced with: Bayesian inference, adaptive learning.
     *
     * @param array $pageMetrics Content metrics (word_count, image_count, complexity)
     *
     * @return float Seconds to spend on page
     */
    public function calculateReadingTime(array $pageMetrics = []): float;

    /**
     * Generate realistic inter-request delay with Gamma distribution
     * Enhanced with: Q-Learning for adaptive timing, Markov chains.
     *
     * @param string $actionType Type of action (click, scroll, navigate, etc.)
     *
     * @return float Seconds to wait
     */
    public function getInterRequestDelay(string $actionType = 'navigate'): float;

    /**
     * Generate realistic scroll behavior pattern
     * Enhanced with: Hick-Hyman Law, Weber-Fechner Law, experience replay.
     *
     * @param int $pageHeight Total page height in pixels
     *
     * @return array Scroll pattern with positions and timings
     */
    public function generateScrollPattern(int $pageHeight = 5000): array;

    /**
     * Decide if should continue browsing based on profile and fatigue
     * Enhanced with: Reinforcement learning, reward optimization.
     *
     * @return bool True if should continue, false if should exit
     */
    public function shouldContinueBrowsing(): bool;

    /**
     * Get next page based on human browsing patterns and relevance scoring
     * Enhanced with: Bayesian inference, collaborative filtering.
     *
     * @param array $currentPage    Current page context
     * @param array $availablePages Available pages to visit
     *
     * @return array Next page to visit
     */
    public function getNextPage(array $currentPage, array $availablePages): array;

    /**
     * Generate realistic mouse movement pattern using Fitts's Law
     * Enhanced with: Bezier curves with overshoot, acceleration profiles.
     *
     * @param array $start  Starting position [x, y]
     * @param array $target Target position [x, y]
     *
     * @return array Movement pattern with timestamps
     */
    public function generateMouseMovement(array $start, array $target): array;

    /**
     * Simulate typing speed with realistic variance and error patterns
     * Enhanced with: Skill-level distributions, fatigue modeling, typo generation.
     *
     * @param string $text Text to type
     *
     * @return array Typing pattern with timings and corrections
     */
    public function generateTypingPattern(string $text): array;

    /**
     * Get current session statistics
     * Enhanced with: ML-based pattern analysis, anomaly detection.
     *
     * @return array Session stats (profile, fatigue, pages, duration)
     */
    public function getSessionStats(): array;

    /**
     * Learn from past behavior patterns (Online Learning)
     * NEW: Q-Learning reinforcement learning integration.
     *
     * @param array $feedback Success rate, detection events, performance metrics
     */
    public function learnFromFeedback(array $feedback): void;

    /**
     * Get current browsing profile with personality traits
     * Enhanced with: Dynamic profile adaptation.
     *
     * @return array Profile data (name, personality, multipliers)
     */
    public function getCurrentProfile(): array;

    /**
     * Update fatigue level (impacts speed and attention)
     * Enhanced with: Exponential decay modeling.
     *
     * @param float $increment Fatigue increment (0.0 - 1.0)
     */
    public function updateFatigue(float $increment): void;

    /**
     * Reset session state (new browsing session).
     */
    public function resetSession(): void;
}
