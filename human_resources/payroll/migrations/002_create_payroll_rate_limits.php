<?php
/**
 * Migration: Create Payroll Rate Limits Table
 *
 * Purpose: Persist rate-limit telemetry for external payroll integrations
 * Task: T2 - Deputy telemetry hardening
 * Priority: P0 (required for alerting and observability)
 *
 * @package CIS\Payroll\Migrations
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../app.php';

class CreatePayrollRateLimits
{
    private PDO $db;

    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
    }

    public function up(): void
    {
        echo "Creating payroll_rate_limits table...\n";

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS payroll_rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(64) NOT NULL COMMENT 'Integration source e.g. deputy, vend, xero',
  endpoint VARCHAR(255) NOT NULL COMMENT 'API endpoint or logical operation',
  status_code SMALLINT UNSIGNED NULL COMMENT 'HTTP status code returned by provider',
  retry_after INT UNSIGNED NULL COMMENT 'Retry-After seconds (if provided)',
  request_fingerprint CHAR(64) NULL COMMENT 'Hash of request payload for dedupe/analytics',
  meta JSON NULL COMMENT 'Additional metadata (headers, payload snippet, correlation ids)',
  occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the rate limit was encountered',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_provider_endpoint (provider, endpoint),
  INDEX idx_occurred_at (occurred_at),
  INDEX idx_status_code (status_code),
  INDEX idx_request_fingerprint (request_fingerprint)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Rate limit telemetry for payroll integrations';
SQL;

        $this->db->exec($sql);

        echo "✅ payroll_rate_limits table created successfully\n";

        $this->logMigration('002_create_payroll_rate_limits', 'up');
    }

    public function down(): void
    {
        echo "Dropping payroll_rate_limits table...\n";

        $this->db->exec('DROP TABLE IF EXISTS payroll_rate_limits');

        echo "✅ payroll_rate_limits table dropped successfully\n";

        $this->logMigration('002_create_payroll_rate_limits', 'down');
    }

    private function logMigration(string $migration, string $direction): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS payroll_migrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL UNIQUE,
  direction ENUM('up','down') NOT NULL,
  executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            // table already exists, ignore
        }

        $stmt = $this->db->prepare(
            "INSERT INTO payroll_migrations (migration, direction)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE direction = ?, executed_at = NOW()"
        );
        $stmt->execute([$migration, $direction, $direction]);
    }
}

if (php_sapi_name() === 'cli') {
    $args = getopt('', ['up', 'down']);
    $migration = new CreatePayrollRateLimits();

    if (isset($args['down'])) {
        echo "\n🔄 Rolling back migration: 002_create_payroll_rate_limits\n\n";
        $migration->down();
    } else {
        echo "\n🚀 Running migration: 002_create_payroll_rate_limits\n\n";
        $migration->up();
    }

    echo "\n✅ Migration complete!\n\n";
}
