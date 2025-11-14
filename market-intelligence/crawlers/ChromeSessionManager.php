<?php
/**
 * Chrome Session Manager
 *
 * Manages Chrome headless profiles, sessions, and fingerprint rotation
 * for sophisticated anti-detection crawling
 *
 * @package CIS\Crawlers
 * @version 1.0.0
 */

namespace CIS\Crawlers;

class ChromeSessionManager {

    private $db;
    private $config;
    private $logger;
    private $baseProfilePath;

    public function __construct($db, $logger, $config = []) {
        $this->db = $db;
        $this->logger = $logger;

        $this->config = array_merge([
            'profile_base_path' => '/home/129337.cloudwaysapps.com/jcepnzzkmj/private_html/crawler-profiles/',
            'max_profiles' => 50,
            'profile_rotation_after' => 100, // Uses before rotation
            'profile_ban_threshold' => 0.5, // Success rate threshold
            'headless' => true,
            'timeout' => 30000,
        ], $config);

        $this->baseProfilePath = $this->config['profile_base_path'];
        $this->ensureProfileDirectory();
    }    /**
     * Get or create a Chrome profile
     */
    public function getProfile($forceNew = false) {
        if ($forceNew) {
            return $this->createNewProfile();
        }

        // Try to get an existing profile
        $profile = $this->getAvailableProfile();

        if (!$profile) {
            return $this->createNewProfile();
        }

        // Update usage stats
        $this->updateProfileUsage($profile['id']);

        return $profile;
    }

    /**
     * Get available profile (not banned, low usage)
     */
    private function getAvailableProfile() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM chrome_sessions
                WHERE banned = FALSE
                AND success_rate > ?
                ORDER BY usage_count ASC, last_used ASC
                LIMIT 1
            ");

            $stmt->execute([$this->config['profile_ban_threshold'] * 100]);
            $profile = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$profile) {
                return null;
            }

            // Check if needs rotation
            if ($profile['usage_count'] >= $this->config['profile_rotation_after']) {
                $this->logger->info("Profile {$profile['profile_name']} reached rotation threshold, creating new one");
                return null;
            }

            return $profile;

        } catch (\PDOException $e) {
            $this->logger->error("Failed to get available profile", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create new Chrome profile with random fingerprint
     */
    private function createNewProfile() {
        $profileName = 'profile_' . time() . '_' . rand(1000, 9999);
        $profilePath = $this->baseProfilePath . $profileName;

        // Create profile directory
        if (!is_dir($profilePath)) {
            mkdir($profilePath, 0755, true);
        }

        // Generate random fingerprint
        $fingerprint = $this->generateFingerprint();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO chrome_sessions (
                    profile_name, profile_path, user_agent,
                    viewport_width, viewport_height, timezone, locale,
                    fingerprint, last_used
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $profileName,
                $profilePath,
                $fingerprint['user_agent'],
                $fingerprint['viewport']['width'],
                $fingerprint['viewport']['height'],
                $fingerprint['timezone'],
                $fingerprint['locale'],
                json_encode($fingerprint),
            ]);

            $profileId = $this->db->lastInsertId();

            $this->logger->info("Created new Chrome profile", [
                'profile_name' => $profileName,
                'user_agent' => $fingerprint['user_agent'],
            ]);

            return [
                'id' => $profileId,
                'profile_name' => $profileName,
                'profile_path' => $profilePath,
                'user_agent' => $fingerprint['user_agent'],
                'viewport_width' => $fingerprint['viewport']['width'],
                'viewport_height' => $fingerprint['viewport']['height'],
                'timezone' => $fingerprint['timezone'],
                'locale' => $fingerprint['locale'],
                'fingerprint' => json_encode($fingerprint),
            ];

        } catch (\PDOException $e) {
            $this->logger->error("Failed to create Chrome profile", ['error' => $e->getMessage()]);
            throw new \Exception("Failed to create Chrome profile: " . $e->getMessage());
        }
    }

    /**
     * Generate random fingerprint
     */
    private function generateFingerprint() {
        // Random viewport sizes (common desktop resolutions)
        $viewports = [
            ['width' => 1920, 'height' => 1080],
            ['width' => 1366, 'height' => 768],
            ['width' => 1536, 'height' => 864],
            ['width' => 1440, 'height' => 900],
            ['width' => 2560, 'height' => 1440],
        ];

        // Random user agents (recent Chrome versions)
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];

        // NZ timezones
        $timezones = [
            'Pacific/Auckland',
            'Pacific/Chatham',
        ];

        // Random locales
        $locales = [
            'en-NZ',
            'en-US',
            'en-GB',
        ];

        $viewport = $viewports[array_rand($viewports)];

        return [
            'user_agent' => $userAgents[array_rand($userAgents)],
            'viewport' => $viewport,
            'timezone' => $timezones[array_rand($timezones)],
            'locale' => $locales[array_rand($locales)],
            'platform' => 'Win32',
            'hardware_concurrency' => rand(4, 16),
            'device_memory' => rand(4, 32),
            'webgl_vendor' => 'Google Inc. (Intel)',
            'webgl_renderer' => 'ANGLE (Intel, Intel(R) UHD Graphics 630, OpenGL 4.1)',
        ];
    }

    /**
     * Get profile configuration (user agent, viewport, etc.)
     * NO CHROME BINARY NEEDED - Pure PHP/cURL solution!
     */
    public function getProfileConfig($profile) {
        return [
            'user_agent' => $profile['user_agent'],
            'viewport_width' => $profile['viewport_width'],
            'viewport_height' => $profile['viewport_height'],
            'timezone' => $profile['timezone'],
            'locale' => $profile['locale'],
            'fingerprint' => json_decode($profile['fingerprint'], true),
        ];
    }

    /**
     * Update profile usage stats
     */
    private function updateProfileUsage($profileId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE chrome_sessions
                SET usage_count = usage_count + 1,
                    last_used = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$profileId]);
        } catch (\PDOException $e) {
            $this->logger->warning("Failed to update profile usage", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update profile success rate
     */
    public function updateProfileSuccess($profileId, $success) {
        try {
            // Get current stats
            $stmt = $this->db->prepare("SELECT usage_count, success_rate FROM chrome_sessions WHERE id = ?");
            $stmt->execute([$profileId]);
            $profile = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$profile) return;

            // Calculate new success rate
            $totalAttempts = $profile['usage_count'];
            $currentSuccessRate = $profile['success_rate'] / 100;
            $currentSuccesses = $totalAttempts * $currentSuccessRate;

            if ($success) {
                $currentSuccesses++;
            }

            $newSuccessRate = ($currentSuccesses / $totalAttempts) * 100;

            // Update
            $stmt = $this->db->prepare("
                UPDATE chrome_sessions
                SET success_rate = ?
                WHERE id = ?
            ");

            $stmt->execute([$newSuccessRate, $profileId]);

            // Check if should ban
            if ($newSuccessRate < ($this->config['profile_ban_threshold'] * 100)) {
                $this->banProfile($profileId);
            }

        } catch (\PDOException $e) {
            $this->logger->warning("Failed to update profile success rate", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Ban a profile (detected/blocked)
     */
    public function banProfile($profileId) {
        try {
            $stmt = $this->db->prepare("UPDATE chrome_sessions SET banned = TRUE WHERE id = ?");
            $stmt->execute([$profileId]);

            $this->logger->warning("Profile banned due to low success rate", ['profile_id' => $profileId]);
        } catch (\PDOException $e) {
            $this->logger->error("Failed to ban profile", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Clean up old profiles
     */
    public function cleanup($olderThanDays = 30) {
        try {
            // Get old profiles
            $stmt = $this->db->prepare("
                SELECT profile_path FROM chrome_sessions
                WHERE last_used < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            $stmt->execute([$olderThanDays]);
            $profiles = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // Delete profile directories
            foreach ($profiles as $path) {
                if (is_dir($path)) {
                    $this->deleteDirectory($path);
                }
            }

            // Delete from database
            $stmt = $this->db->prepare("
                DELETE FROM chrome_sessions
                WHERE last_used < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            $stmt->execute([$olderThanDays]);

            $this->logger->info("Cleaned up old Chrome profiles", ['count' => count($profiles)]);

        } catch (\PDOException $e) {
            $this->logger->error("Failed to cleanup profiles", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Recursively delete directory
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * Ensure profile directory exists
     */
    private function ensureProfileDirectory() {
        if (!is_dir($this->baseProfilePath)) {
            mkdir($this->baseProfilePath, 0755, true);
        }
    }

    /**
     * Get profile stats
     */
    public function getStats() {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) as total_profiles,
                    COUNT(CASE WHEN banned = FALSE THEN 1 END) as active_profiles,
                    COUNT(CASE WHEN banned = TRUE THEN 1 END) as banned_profiles,
                    AVG(success_rate) as avg_success_rate,
                    AVG(usage_count) as avg_usage_count
                FROM chrome_sessions
            ");

            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }
}
