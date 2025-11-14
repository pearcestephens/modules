<?php

/**
 * Fraud Detection Configuration Manager
 *
 * Handles loading, validating, and accessing fraud detection configuration.
 * Provides outlet-specific overrides and staff exclusions.
 */

namespace FraudDetection;

use Exception;

class ConfigManager
{
    private array $config;
    private static ?ConfigManager $instance = null;

    private function __construct(string $configPath = null)
    {
        $configPath = $configPath ?? __DIR__ . '/config/fraud_detection_config.php';

        if (!file_exists($configPath)) {
            throw new Exception("Configuration file not found: {$configPath}");
        }

        $this->config = require $configPath;
        $this->validate();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(string $configPath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($configPath);
        }
        return self::$instance;
    }

    /**
     * Get configuration value
     * Supports dot notation: 'payment_type_fraud.unusual_payment_type_threshold'
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get configuration with outlet-specific overrides applied
     */
    public function getForOutlet(string $key, string $outletId, $default = null)
    {
        // Get base value
        $value = $this->get($key, $default);

        // Check for outlet override
        $overridePath = "outlet_overrides.outlet_{$outletId}." . $key;
        $override = $this->get($overridePath);

        return $override ?? $value;
    }

    /**
     * Check if staff member is excluded from analysis
     */
    public function isStaffExcluded(int $staffId): bool
    {
        $excludedIds = $this->get('staff_exclusions.excluded_staff_ids', []);
        return in_array($staffId, $excludedIds);
    }

    /**
     * Check if staff is partially excluded from specific section
     */
    public function isStaffExcludedFromSection(int $staffId, string $section): bool
    {
        $partialExclusions = $this->get("staff_exclusions.partial_exclusions.{$staffId}.sections", []);
        return in_array($section, $partialExclusions);
    }

    /**
     * Check if staff is excluded from specific indicator type
     */
    public function isStaffExcludedFromIndicator(int $staffId, string $indicator): bool
    {
        $partialExclusions = $this->get("staff_exclusions.partial_exclusions.{$staffId}.indicators", []);
        return in_array($indicator, $partialExclusions);
    }

    /**
     * Check if payment type is whitelisted
     */
    public function isPaymentTypeWhitelisted(string $paymentType): bool
    {
        $whitelisted = $this->get('whitelisting.whitelisted_payment_types', []);
        return in_array($paymentType, $whitelisted);
    }

    /**
     * Check if customer is whitelisted
     */
    public function isCustomerWhitelisted(string $customerId): bool
    {
        $whitelisted = $this->get('whitelisting.whitelisted_customer_ids', []);
        return in_array($customerId, $whitelisted);
    }

    /**
     * Check if product is whitelisted for adjustments
     */
    public function isProductWhitelisted(string $productId): bool
    {
        $whitelisted = $this->get('whitelisting.whitelisted_product_ids', []);
        return in_array($productId, $whitelisted);
    }

    /**
     * Check if adjustment reason is legitimate
     */
    public function isLegitimateAdjustmentReason(?string $reason): bool
    {
        if (empty($reason)) {
            return false;
        }

        $legitimate = $this->get('whitelisting.legitimate_adjustment_reasons', []);
        $reasonLower = strtolower($reason);

        foreach ($legitimate as $legit) {
            if (strpos($reasonLower, strtolower($legit)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if customer name matches suspicious patterns
     */
    public function isSuspiciousCustomerName(?string $name): bool
    {
        if (empty($name)) {
            return false;
        }

        $patterns = $this->get('customer_account_fraud.suspicious_customer_patterns', []);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get seasonal adjustment multiplier for a threshold
     */
    public function getSeasonalMultiplier(string $thresholdKey, string $date = null): float
    {
        if (!$this->get('learning.seasonal_adjustments.enabled', false)) {
            return 1.0;
        }

        $date = $date ?? date('m-d');
        $periods = $this->get('learning.seasonal_adjustments.periods', []);

        foreach ($periods as $period) {
            if ($date >= $period['start'] && $date <= $period['end']) {
                return $period['multipliers'][$thresholdKey] ?? 1.0;
            }
        }

        return 1.0;
    }

    /**
     * Get adjusted threshold with seasonal multiplier applied
     */
    public function getAdjustedThreshold(string $key, $default = null): float
    {
        $baseValue = $this->get($key, $default);
        $multiplier = $this->getSeasonalMultiplier($key);

        return $baseValue * $multiplier;
    }

    /**
     * Check if section is enabled
     */
    public function isSectionEnabled(string $section): bool
    {
        if (!$this->get('global.enabled', true)) {
            return false;
        }

        if (!$this->get('global.enable_all_sections', true)) {
            return $this->get("{$section}.enabled", false);
        }

        return $this->get("{$section}.enabled", true);
    }

    /**
     * Check if system is in dry-run mode
     */
    public function isDryRun(): bool
    {
        return $this->get('global.dry_run_mode', false);
    }

    /**
     * Check if debugging is enabled
     */
    public function isDebug(): bool
    {
        return $this->get('development.debug_mode', false);
    }

    /**
     * Get all config (for debugging)
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * Validate configuration
     */
    private function validate(): void
    {
        $required = [
            'global',
            'payment_type_fraud',
            'customer_account_fraud',
            'inventory_fraud',
            'register_closure_fraud',
            'banking_fraud',
            'transaction_manipulation',
            'reconciliation_fraud',
        ];

        foreach ($required as $key) {
            if (!isset($this->config[$key])) {
                throw new Exception("Missing required configuration section: {$key}");
            }
        }
    }

    /**
     * Set a configuration value (runtime only, not persisted)
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * Check if alert should be sent for this risk level/score
     */
    public function shouldAlert(string $riskLevel, float $riskScore): bool
    {
        if (!$this->get('alerts.enabled', true)) {
            return false;
        }

        $alertLevels = $this->get('alerts.alert_risk_levels', ['high', 'critical']);
        $scoreThreshold = $this->get('alerts.alert_risk_score_threshold', 80);

        return in_array($riskLevel, $alertLevels) || $riskScore >= $scoreThreshold;
    }

    /**
     * Check if staff should be throttled from alerts (too many recent alerts)
     */
    public function shouldThrottleAlert(int $staffId, \PDO $pdo): bool
    {
        $maxPerDay = $this->get('alerts.alert_throttle.max_alerts_per_staff_per_day', 3);
        $cooldownHours = $this->get('alerts.alert_throttle.cooldown_hours', 6);

        try {
            // Check alerts in last 24 hours
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM fraud_alert_log
                WHERE staff_id = ?
                AND sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$staffId]);
            $alertsToday = $stmt->fetchColumn();

            if ($alertsToday >= $maxPerDay) {
                return true; // Too many alerts today
            }

            // Check last alert time
            $stmt = $pdo->prepare("
                SELECT MAX(sent_at)
                FROM fraud_alert_log
                WHERE staff_id = ?
            ");
            $stmt->execute([$staffId]);
            $lastAlert = $stmt->fetchColumn();

            if ($lastAlert) {
                $hoursSinceLastAlert = (time() - strtotime($lastAlert)) / 3600;
                if ($hoursSinceLastAlert < $cooldownHours) {
                    return true; // Too soon since last alert
                }
            }

            return false; // Not throttled

        } catch (Exception $e) {
            error_log("Alert throttle check failed: " . $e->getMessage());
            return false; // Don't throttle on error
        }
    }

    /**
     * Log alert sent
     */
    public function logAlertSent(int $staffId, string $alertType, \PDO $pdo): void
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO fraud_alert_log
                (staff_id, alert_type, sent_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$staffId, $alertType]);
        } catch (Exception $e) {
            error_log("Failed to log alert: " . $e->getMessage());
        }
    }
}
