<?php
/**
 * Migration: Create Staff Identity Map Table
 *
 * Purpose: Canonical mapping between Xero employees and Vend customers
 * Task: T1 - Canonical ID Mapping Table
 * Priority: P0 (Blocker for all allocations)
 *
 * @package CIS\Payroll\Migrations
 * @version 1.0.0
 * @created 2025-11-02
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../app.php';

class CreateStaffIdentityMap
{
    private PDO $db;

    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
    }

    /**
     * Run the migration
     */
    public function up(): void
    {
        echo "Creating staff_identity_map table...\n";

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS staff_identity_map (
  id INT AUTO_INCREMENT PRIMARY KEY,
  xero_employee_id VARCHAR(64) NOT NULL,
  vend_customer_id VARCHAR(64) NOT NULL,
  staff_number VARCHAR(64) NULL COMMENT 'Internal staff number if different from IDs',
  display_name VARCHAR(255) NOT NULL COMMENT 'Full name for display purposes',
  active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=terminated/inactive',
  metadata JSON NULL COMMENT 'Additional info: email, department, role, etc',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT NULL COMMENT 'User ID who created this mapping',
  updated_by INT NULL COMMENT 'User ID who last updated this mapping',

  -- Enforce uniqueness
  UNIQUE KEY unique_xero_emp (xero_employee_id),
  UNIQUE KEY unique_vend_cust (vend_customer_id),

  -- Indexes for fast lookups
  INDEX idx_xero_emp (xero_employee_id),
  INDEX idx_vend_cust (vend_customer_id),
  INDEX idx_active (active),
  INDEX idx_staff_number (staff_number),
  INDEX idx_display_name (display_name)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Canonical staff identity mapping: Xero employees <-> Vend customers';
SQL;

        $this->db->exec($sql);

        echo "✅ staff_identity_map table created successfully\n";

        // Create audit log table for mapping changes
        echo "Creating staff_identity_map_audit table...\n";

        $auditSql = <<<SQL
CREATE TABLE IF NOT EXISTS staff_identity_map_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  mapping_id INT NOT NULL COMMENT 'Reference to staff_identity_map.id',
  action ENUM('CREATE','UPDATE','DELETE','DEACTIVATE','REACTIVATE') NOT NULL,
  field_changed VARCHAR(64) NULL COMMENT 'Which field was updated',
  old_value TEXT NULL COMMENT 'Previous value (JSON for complex data)',
  new_value TEXT NULL COMMENT 'New value (JSON for complex data)',
  changed_by INT NOT NULL COMMENT 'User ID who made the change',
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45) NULL COMMENT 'IP of user making change',
  user_agent TEXT NULL COMMENT 'Browser/client info',

  INDEX idx_mapping_id (mapping_id),
  INDEX idx_action (action),
  INDEX idx_changed_by (changed_by),
  INDEX idx_changed_at (changed_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit trail for staff identity mapping changes';
SQL;

        $this->db->exec($auditSql);

        echo "✅ staff_identity_map_audit table created successfully\n";

        // Log migration
        $this->logMigration('001_create_staff_identity_map', 'up');
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        echo "Dropping staff_identity_map tables...\n";

        $this->db->exec("DROP TABLE IF EXISTS staff_identity_map_audit");
        $this->db->exec("DROP TABLE IF EXISTS staff_identity_map");

        echo "✅ Tables dropped successfully\n";

        // Log migration
        $this->logMigration('001_create_staff_identity_map', 'down');
    }

    /**
     * Log migration execution
     */
    private function logMigration(string $migration, string $direction): void
    {
        // Create migrations log table if not exists
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
            // Table might already exist, ignore
        }

        // Log this migration
        $stmt = $this->db->prepare(
            "INSERT INTO payroll_migrations (migration, direction)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE direction = ?, executed_at = NOW()"
        );
        $stmt->execute([$migration, $direction, $direction]);
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $args = getopt('', ['up', 'down']);

    $migration = new CreateStaffIdentityMap();

    if (isset($args['down'])) {
        echo "\n🔄 Rolling back migration: 001_create_staff_identity_map\n\n";
        $migration->down();
    } else {
        echo "\n🚀 Running migration: 001_create_staff_identity_map\n\n";
        $migration->up();
    }

    echo "\n✅ Migration complete!\n\n";
}
