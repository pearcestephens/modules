<?php

declare(strict_types=1);
/**
 * Session Interface.
 *
 * Contract for Chrome session management with fingerprint rotation
 * Defines methods for profile creation, rotation, and anti-detection
 *
 * @version 2.0.0 - Ultra-Sophisticated ML/AI Enhanced
 */

namespace CIS\SharedServices\Crawler\Contracts;

interface SessionInterface
{
    /**
     * Get or create a Chrome profile
     * Enhanced with: ML-based profile selection, success rate prediction.
     *
     * @param bool $forceNew Force creation of new profile
     *
     * @return array Profile data (id, name, path, fingerprint)
     */
    public function getProfile(bool $forceNew = false): array;

    /**
     * Get profile configuration (user agent, viewport, fingerprint)
     * Enhanced with: Canvas/WebGL/Audio fingerprinting, TLS rotation.
     *
     * @param array $profile Profile data
     *
     * @return array Configuration (user_agent, viewport, timezone, fingerprint)
     */
    public function getProfileConfig(array $profile): array;

    /**
     * Update profile success rate based on crawl results
     * Enhanced with: Bayesian success rate estimation, adaptive banning.
     *
     * @param int  $profileId Profile ID
     * @param bool $success   Whether crawl was successful
     */
    public function updateProfileSuccess(int $profileId, bool $success): void;

    /**
     * Ban a profile (detected/blocked by target)
     * Enhanced with: ML-based detection pattern analysis.
     *
     * @param int $profileId Profile ID
     */
    public function banProfile(int $profileId): void;

    /**
     * Clean up old profiles (older than X days)
     * Enhanced with: Usage-based cleanup prioritization.
     *
     * @param int $olderThanDays Age threshold in days
     */
    public function cleanup(int $olderThanDays = 30): void;

    /**
     * Get session management statistics
     * Enhanced with: Profile performance analytics.
     *
     * @return array Stats (total_profiles, active, banned, avg_success_rate)
     */
    public function getStats(): array;

    /**
     * Generate advanced fingerprint with anti-detection features
     * NEW: Canvas noise, WebGL, Audio context, TLS fingerprinting.
     *
     * @return array Complete fingerprint (browser, device, network, behavioral)
     */
    public function generateAdvancedFingerprint(): array;

    /**
     * Rotate fingerprint for existing profile
     * NEW: Intelligent rotation based on detection risk.
     *
     * @param int $profileId Profile ID
     *
     * @return array New fingerprint data
     */
    public function rotateFingerprint(int $profileId): array;

    /**
     * Check if profile is at risk of detection
     * NEW: ML-based risk scoring with anomaly detection.
     *
     * @param int $profileId Profile ID
     *
     * @return array Risk assessment (score, reasons, recommendations)
     */
    public function assessDetectionRisk(int $profileId): array;

    /**
     * Create residential proxy rotation schedule
     * NEW: Geographic distribution, ISP variety, timing optimization.
     *
     * @param array $requirements Geographic, ISP, timing constraints
     *
     * @return array Proxy rotation schedule
     */
    public function createProxySchedule(array $requirements): array;
}
