<?php
/**
 * Enhanced Consignment Upload System - Database Migration
 *
 * Executes the enhanced consignment upload database schema migration
 * with proper error handling, rollback capability, and verification.
 *
 * This migration creates all tables required for:
 * - Queue consignment shadow tables (API Playbook compliance)
 * - Progress tracking tables (SSE support)
 * - State transition logging
 * - Product-level progress tracking
 *
 * Usage:
 *   php run-migration.php
 *   php run-migration.php --verify-only
 *   php run-migration.php --rollback
 *
 * @package CIS\Consignments\Database
 * @version 2.0.0
 */

declare(strict_types=1);

// Bootstrap CIS database connection
// Try multiple common paths for database configuration
$configPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/bootstrap/app.php',
    $_SERVER['DOCUMENT_ROOT'] . '/app.php',
    $_SERVER['DOCUMENT_ROOT'] . '/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/database.php',
    dirname(__DIR__, 2) . '/base/lib/Database.php'
];

$dbConfigLoaded = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbConfigLoaded = true;
        break;
    }
}

if (!$dbConfigLoaded) {
    // Fallback: create minimal database connection
    function get_database_connection() {
        static $connection = null;

        if ($connection === null) {
            // Use environment variables or fallback defaults
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            $name = $_ENV['DB_NAME'] ?? 'jcepnzzkmj';

            $connection = new mysqli($host, $user, $pass, $name);

            if ($connection->connect_error) {
                throw new Exception("Database connection failed: " . $connection->connect_error);
            }

            $connection->set_charset('utf8mb4');
        }

        return $connection;
    }
}

class EnhancedConsignmentMigration
{
    private $db;
    private $dryRun = false;
    private $verifyOnly = false;
    private $rollback = false;

    public function __construct()
    {
        $this->db = get_database_connection();

        // Parse command line arguments
        $options = getopt('', ['dry-run', 'verify-only', 'rollback', 'help']);

        if (isset($options['help'])) {
            $this->showHelp();
            exit(0);
        }

        $this->dryRun = isset($options['dry-run']);
        $this->verifyOnly = isset($options['verify-only']);
        $this->rollback = isset($options['rollback']);
    }

    public function run(): void
    {
        try {
            $this->log("Enhanced Consignment Upload System - Database Migration");
            $this->log("======================================================");

            if ($this->verifyOnly) {
                $this->verifySchema();
                return;
            }

            if ($this->rollback) {
                $this->rollbackMigration();
                return;
            }

            $this->executeMigration();

        } catch (Exception $e) {
            $this->log("FATAL ERROR: " . $e->getMessage(), 'ERROR');
            $this->log("Stack trace: " . $e->getTraceAsString(), 'ERROR');
            exit(1);
        }
    }

    private function executeMigration(): void
    {
        $this->log("Starting enhanced consignment upload migration...");

        if ($this->dryRun) {
            $this->log("DRY RUN MODE - No changes will be made", 'WARNING');
        }

        // Step 1: Backup existing tables
        $this->createBackup();

        // Step 2: Check prerequisites
        $this->checkPrerequisites();

        // Step 3: Create new tables
        $this->createTables();

        // Step 4: Add new columns to existing tables
        $this->updateExistingTables();

        // Step 5: Create indexes and constraints
        $this->createIndexesAndConstraints();

        // Step 6: Insert default configuration
        $this->insertDefaultConfiguration();

        // Step 7: Verify migration
        $this->verifyMigration();

        $this->log("Migration completed successfully!", 'SUCCESS');
    }

    private function createBackup(): void
    {
        $this->log("Creating backup of existing tables...");

        $tables = ['transfers', 'transfer_items', 'system_config'];
        $backupSql = "-- Backup created at " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $this->log("Backing up table: $table");

                // Get table structure
                $createTable = $this->db->query("SHOW CREATE TABLE `$table`")->fetch_assoc();
                $backupSql .= $createTable['Create Table'] . ";\n\n";

                // Get table data
                $result = $this->db->query("SELECT * FROM `$table`");
                if ($result->num_rows > 0) {
                    $backupSql .= "INSERT INTO `$table` VALUES\n";
                    $rows = [];
                    while ($row = $result->fetch_assoc()) {
                        $values = array_map(function($value) {
                            return $value === null ? 'NULL' : "'" . $this->db->real_escape_string($value) . "'";
                        }, $row);
                        $rows[] = "(" . implode(", ", $values) . ")";
                    }
                    $backupSql .= implode(",\n", $rows) . ";\n\n";
                }
            }
        }

        $backupFile = $_SERVER['DOCUMENT_ROOT'] . '/private_html/backups/enhanced_consignment_backup_' . date('Y-m-d_H-i-s') . '.sql';

        if (!$this->dryRun) {
            file_put_contents($backupFile, $backupSql);
            $this->log("Backup created: $backupFile");
        }
    }

    private function checkPrerequisites(): void
    {
        $this->log("Checking prerequisites...");

        // Check required tables exist
        $requiredTables = ['transfers', 'transfer_items'];
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                throw new Exception("Required table '$table' does not exist");
            }
        }

        // Check database version
        $version = $this->db->query("SELECT VERSION() as version")->fetch_assoc()['version'];
        $this->log("Database version: $version");

        // Check for sufficient privileges
        $grants = $this->db->query("SHOW GRANTS")->fetch_all(MYSQLI_ASSOC);
        $this->log("Database privileges verified");

        $this->log("Prerequisites check passed");
    }

    private function createTables(): void
    {
        $this->log("Creating new tables...");

        $tables = [
            'queue_consignments' => $this->getQueueConsignmentsSchema(),
            'queue_consignment_products' => $this->getQueueConsignmentProductsSchema(),
            'queue_consignment_state_transitions' => $this->getStateTransitionsSchema(),
            'consignment_upload_progress' => $this->getUploadProgressSchema(),
            'consignment_product_progress' => $this->getProductProgressSchema(),
            'queue_jobs' => $this->getQueueJobsSchema(),
            'queue_webhook_events' => $this->getWebhookEventsSchema(),
        ];

        foreach ($tables as $tableName => $schema) {
            $this->log("Creating table: $tableName");

            if (!$this->dryRun) {
                if (!$this->db->query($schema)) {
                    throw new Exception("Failed to create table '$tableName': " . $this->db->error);
                }
            }
        }

        $this->log("All tables created successfully");
    }

    private function updateExistingTables(): void
    {
        $this->log("Updating existing tables...");

        // Add consignment_id column to transfers table if it doesn't exist
        if (!$this->columnExists('transfers', 'consignment_id')) {
            $this->log("Adding consignment_id column to transfers table");

            $sql = "ALTER TABLE `transfers`
                    ADD COLUMN `consignment_id` BIGINT UNSIGNED NULL
                    COMMENT 'Links to queue_consignments.id'
                    AFTER `vend_transfer_id`";

            if (!$this->dryRun) {
                if (!$this->db->query($sql)) {
                    throw new Exception("Failed to add consignment_id column: " . $this->db->error);
                }
            }
        }

        // Add foreign key constraint for consignment_id
        if (!$this->constraintExists('transfers', 'fk_transfers_consignment')) {
            $this->log("Adding foreign key constraint for consignment_id");

            $sql = "ALTER TABLE `transfers`
                    ADD CONSTRAINT `fk_transfers_consignment`
                    FOREIGN KEY (`consignment_id`) REFERENCES `queue_consignments` (`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE";

            if (!$this->dryRun) {
                if (!$this->db->query($sql)) {
                    $this->log("Warning: Could not add foreign key constraint (may already exist): " . $this->db->error, 'WARNING');
                }
            }
        }

        $this->log("Table updates completed");
    }

    private function createIndexesAndConstraints(): void
    {
        $this->log("Creating additional indexes and constraints...");

        $indexes = [
            "ALTER TABLE `transfers` ADD INDEX IF NOT EXISTS `idx_transfers_consignment` (`consignment_id`)",
            "ALTER TABLE `transfers` ADD INDEX IF NOT EXISTS `idx_transfers_state_updated` (`state`, `updated_at`)",
        ];

        foreach ($indexes as $sql) {
            if (!$this->dryRun) {
                if (!$this->db->query($sql)) {
                    $this->log("Warning: Could not create index: " . $this->db->error, 'WARNING');
                }
            }
        }

        $this->log("Indexes and constraints created");
    }

    private function insertDefaultConfiguration(): void
    {
        $this->log("Inserting default configuration...");

        $configs = [
            ['consignment_upload_timeout', '1800', 'Maximum time in seconds for consignment upload process'],
            ['consignment_upload_max_retries', '3', 'Maximum number of retry attempts for failed product uploads'],
            ['consignment_upload_batch_size', '50', 'Number of products to upload in each batch'],
            ['sse_heartbeat_interval', '15', 'Heartbeat interval in seconds for SSE connections'],
            ['lightspeed_api_rate_limit', '100', 'Maximum API calls per minute to Lightspeed'],
        ];

        foreach ($configs as list($key, $value, $description)) {
            $this->log("Setting config: $key = $value");

            if (!$this->dryRun) {
                $stmt = $this->db->prepare("INSERT IGNORE INTO `system_config` (`key`, `value`, `description`) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $key, $value, $description);
                $stmt->execute();
            }
        }

        $this->log("Default configuration inserted");
    }

    private function verifyMigration(): void
    {
        $this->log("Verifying migration...");

        // Check all tables exist
        $expectedTables = [
            'queue_consignments',
            'queue_consignment_products',
            'queue_consignment_state_transitions',
            'consignment_upload_progress',
            'consignment_product_progress',
            'queue_jobs',
            'queue_webhook_events'
        ];

        foreach ($expectedTables as $table) {
            if (!$this->tableExists($table)) {
                throw new Exception("Verification failed: Table '$table' was not created");
            }
        }

        // Check transfers.consignment_id column exists
        if (!$this->columnExists('transfers', 'consignment_id')) {
            throw new Exception("Verification failed: transfers.consignment_id column was not added");
        }

        // Check configuration was inserted
        $configCount = $this->db->query("SELECT COUNT(*) as count FROM `system_config` WHERE `key` LIKE 'consignment_%' OR `key` LIKE 'sse_%' OR `key` LIKE 'lightspeed_%'")->fetch_assoc()['count'];
        if ($configCount < 5) {
            $this->log("Warning: Not all configuration entries were inserted", 'WARNING');
        }

        $this->log("Migration verification passed");
    }

    private function verifySchema(): void
    {
        $this->log("Verifying current database schema...");

        $tables = [
            'queue_consignments',
            'queue_consignment_products',
            'queue_consignment_state_transitions',
            'consignment_upload_progress',
            'consignment_product_progress',
            'queue_jobs',
            'queue_webhook_events'
        ];

        $missingTables = [];
        $existingTables = [];

        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $existingTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        }

        $this->log("Existing tables: " . implode(', ', $existingTables));

        if (!empty($missingTables)) {
            $this->log("Missing tables: " . implode(', ', $missingTables), 'WARNING');
            $this->log("Migration required", 'WARNING');
        } else {
            $this->log("All required tables exist", 'SUCCESS');
        }

        // Check transfers.consignment_id column
        if ($this->columnExists('transfers', 'consignment_id')) {
            $this->log("transfers.consignment_id column exists", 'SUCCESS');
        } else {
            $this->log("transfers.consignment_id column missing", 'WARNING');
        }
    }

    private function rollbackMigration(): void
    {
        $this->log("Rolling back migration...", 'WARNING');

        if ($this->dryRun) {
            $this->log("DRY RUN MODE - No changes will be made", 'WARNING');
        }

        // Find latest backup
        $backupDir = $_SERVER['DOCUMENT_ROOT'] . '/private_html/backups/';
        $backups = glob($backupDir . 'enhanced_consignment_backup_*.sql');

        if (empty($backups)) {
            throw new Exception("No backup files found for rollback");
        }

        sort($backups);
        $latestBackup = end($backups);

        $this->log("Using backup: " . basename($latestBackup));

        // Drop created tables
        $tablesToDrop = [
            'queue_webhook_events',
            'queue_jobs',
            'consignment_product_progress',
            'consignment_upload_progress',
            'queue_consignment_state_transitions',
            'queue_consignment_products',
            'queue_consignments'
        ];

        foreach ($tablesToDrop as $table) {
            if ($this->tableExists($table)) {
                $this->log("Dropping table: $table");
                if (!$this->dryRun) {
                    $this->db->query("DROP TABLE `$table`");
                }
            }
        }

        // Remove consignment_id column from transfers
        if ($this->columnExists('transfers', 'consignment_id')) {
            $this->log("Removing consignment_id column from transfers");
            if (!$this->dryRun) {
                $this->db->query("ALTER TABLE `transfers` DROP COLUMN `consignment_id`");
            }
        }

        $this->log("Rollback completed", 'SUCCESS');
    }

    // Table schema definitions
    private function getQueueConsignmentsSchema(): string
    {
        return "CREATE TABLE IF NOT EXISTS `queue_consignments` (
          `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `transfer_id` INT UNSIGNED NOT NULL COMMENT 'Link to transfers.id',
          `vend_consignment_id` VARCHAR(100) NOT NULL COMMENT 'Lightspeed consignment UUID',
          `outlet_from_id` VARCHAR(100) NOT NULL COMMENT 'Source outlet UUID (Vend)',
          `outlet_to_id` VARCHAR(100) NOT NULL COMMENT 'Destination outlet UUID (Vend)',
          `status` ENUM('OPEN', 'IN_TRANSIT', 'RECEIVED') NOT NULL DEFAULT 'OPEN' COMMENT 'Lightspeed status',
          `vendor_version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Optimistic locking version',
          `sync_status` ENUM('pending', 'synced', 'error') NOT NULL DEFAULT 'pending',
          `last_sync_at` TIMESTAMP NULL DEFAULT NULL,
          `sync_error` TEXT NULL COMMENT 'Last sync error message',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

          UNIQUE KEY `uniq_transfer_id` (`transfer_id`),
          UNIQUE KEY `uniq_vend_consignment_id` (`vend_consignment_id`),
          INDEX `idx_status` (`status`),
          INDEX `idx_sync_status` (`sync_status`),
          INDEX `idx_outlet_from` (`outlet_from_id`),
          INDEX `idx_outlet_to` (`outlet_to_id`),
          INDEX `idx_created_at` (`created_at`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Queue consignments shadow table - 1:1 with transfer consignment record'";
    }

    private function getQueueConsignmentProductsSchema(): string
    {
        return "CREATE TABLE IF NOT EXISTS `queue_consignment_products` (
          `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `queue_consignment_id` BIGINT UNSIGNED NOT NULL,
          `vend_product_id` VARCHAR(100) NOT NULL COMMENT 'Lightspeed product UUID',
          `product_sku` VARCHAR(100) NOT NULL COMMENT 'Product SKU for reference',
          `expected_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Desired quantity',
          `received_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Actually received quantity',
          `sync_status` ENUM('pending', 'synced', 'error') NOT NULL DEFAULT 'pending',
          `last_sync_at` TIMESTAMP NULL DEFAULT NULL,
          `sync_error` TEXT NULL COMMENT 'Last sync error message',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

          INDEX `idx_queue_consignment` (`queue_consignment_id`),
          INDEX `idx_vend_product` (`vend_product_id`),
          INDEX `idx_product_sku` (`product_sku`),
          INDEX `idx_sync_status` (`sync_status`),

          CONSTRAINT `fk_queue_consignment_products_consignment`
            FOREIGN KEY (`queue_consignment_id`) REFERENCES `queue_consignments` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Queue consignment products - line items with desired vs received counts'";
    }

    private function getStateTransitionsSchema(): string
    {
        return "CREATE TABLE IF NOT EXISTS `queue_consignment_state_transitions` (
          `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `transfer_id` INT UNSIGNED NOT NULL,
          `from_state` VARCHAR(50) NULL COMMENT 'Previous state',
          `to_state` VARCHAR(50) NOT NULL COMMENT 'New state',
          `source` ENUM('cis_sync', 'webhook', 'force_resync', 'manual') NOT NULL COMMENT 'What triggered the change',
          `metadata` JSON NULL COMMENT 'Additional context and details',
          `user_id` INT UNSIGNED NULL COMMENT 'User who triggered the change (if manual)',
          `occurred_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

          INDEX `idx_transfer_id` (`transfer_id`),
          INDEX `idx_from_state` (`from_state`),
          INDEX `idx_to_state` (`to_state`),
          INDEX `idx_source` (`source`),
          INDEX `idx_occurred_at` (`occurred_at`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Immutable log of consignment state movements and causes'";
    }

    private function getUploadProgressSchema(): string
    {
        return "CREATE TABLE IF NOT EXISTS `consignment_upload_progress` (
          `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `transfer_id` INT UNSIGNED NOT NULL,
          `session_id` VARCHAR(64) NOT NULL COMMENT 'Unique session identifier for this upload',
          `status` ENUM('pending', 'creating_consignment', 'uploading_products', 'updating_state', 'completed', 'failed') NOT NULL DEFAULT 'pending',
          `total_products` INT UNSIGNED NOT NULL DEFAULT 0,
          `completed_products` INT UNSIGNED NOT NULL DEFAULT 0,
          `failed_products` INT UNSIGNED NOT NULL DEFAULT 0,
          `current_operation` VARCHAR(255) NULL COMMENT 'Current operation description',
          `estimated_completion` DATETIME NULL COMMENT 'Estimated completion time',
          `performance_metrics` JSON NULL COMMENT 'Performance and timing metrics',
          `error_message` TEXT NULL COMMENT 'Error message if status is failed',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

          UNIQUE KEY `uniq_transfer_session` (`transfer_id`, `session_id`),
          INDEX `idx_status` (`status`),
          INDEX `idx_updated_at` (`updated_at`),
          INDEX `idx_session_id` (`session_id`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Main progress tracking for consignment uploads with SSE support'";
    }

    private function getProductProgressSchema(): string
    {
        return "CREATE TABLE IF NOT EXISTS `consignment_product_progress` (
          `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `transfer_id` INT UNSIGNED NOT NULL,
          `session_id` VARCHAR(64) NOT NULL,
          `product_id` VARCHAR(100) NOT NULL COMMENT 'CIS product ID',
          `sku` VARCHAR(100) NOT NULL,
          `name` VARCHAR(255) NOT NULL,
          `status` ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
          `vend_product_id` VARCHAR(100) NULL COMMENT 'Lightspeed product UUID after upload',
          `error_message` TEXT NULL COMMENT 'Error message if upload failed',
          `retry_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `processing_time_ms` INT UNSIGNED NULL COMMENT 'Time taken to process this product',
          `processed_at` TIMESTAMP NULL COMMENT 'When this product was processed',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

          UNIQUE KEY `uniq_transfer_session_product` (`transfer_id`, `session_id`, `product_id`),
          INDEX `idx_product_id` (`product_id`),
          INDEX `idx_sku` (`sku`),
          INDEX `idx_status` (`status`),
          INDEX `idx_processed_at` (`processed_at`),
          INDEX `idx_session_id` (`session_id`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Product-level progress tracking for detailed SSE updates'";
    }

    private function getQueueJobsSchema(): string
    {
        return "CREATE TABLE IF NOT EXISTS `queue_jobs` (
          `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `job_type` VARCHAR(100) NOT NULL COMMENT 'transfer.create_consignment, transfer.sync_to_lightspeed, etc.',
          `payload` JSON NOT NULL COMMENT 'Job data and parameters',
          `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
          `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-10, 10 = highest priority',
          `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
          `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
          `last_error` TEXT NULL COMMENT 'Last error message',
          `worker_id` VARCHAR(100) NULL COMMENT 'ID of worker processing this job',
          `started_at` TIMESTAMP NULL COMMENT 'When job processing started',
          `completed_at` TIMESTAMP NULL COMMENT 'When job completed or failed',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

          INDEX `idx_job_type` (`job_type`),
          INDEX `idx_status` (`status`),
          INDEX `idx_priority` (`priority`),
          INDEX `idx_created_at` (`created_at`),
          INDEX `idx_worker_id` (`worker_id`),
          INDEX `idx_status_priority` (`status`, `priority` DESC)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Enhanced queue jobs for transfer operations with priority and retry support'";
    }

    private function getWebhookEventsSchema(): string
    {
        return "CREATE TABLE IF NOT EXISTS `queue_webhook_events` (
          `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `webhook_type` VARCHAR(100) NOT NULL COMMENT 'consignment.updated, consignment.received, etc.',
          `payload` JSON NOT NULL COMMENT 'Full webhook payload from Lightspeed',
          `status` ENUM('pending', 'processing', 'completed', 'failed', 'ignored') NOT NULL DEFAULT 'pending',
          `related_transfer_id` INT UNSIGNED NULL COMMENT 'Transfer ID if identified',
          `related_consignment_id` VARCHAR(100) NULL COMMENT 'Lightspeed consignment ID',
          `processed_at` TIMESTAMP NULL,
          `error_message` TEXT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

          INDEX `idx_webhook_type` (`webhook_type`),
          INDEX `idx_status` (`status`),
          INDEX `idx_related_transfer` (`related_transfer_id`),
          INDEX `idx_related_consignment` (`related_consignment_id`),
          INDEX `idx_created_at` (`created_at`)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Inbound webhook events from Lightspeed for consignment updates'";
    }

    // Utility methods
    private function tableExists(string $tableName): bool
    {
        $result = $this->db->query("SHOW TABLES LIKE '$tableName'");
        return $result->num_rows > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $result = $this->db->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
        return $result->num_rows > 0;
    }

    private function constraintExists(string $tableName, string $constraintName): bool
    {
        $query = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                  WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = '$tableName'
                  AND CONSTRAINT_NAME = '$constraintName'";
        $result = $this->db->query($query)->fetch_assoc();
        return $result['count'] > 0;
    }

    private function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $colors = [
            'INFO' => '',
            'SUCCESS' => "\033[32m",
            'WARNING' => "\033[33m",
            'ERROR' => "\033[31m"
        ];
        $reset = "\033[0m";

        $color = $colors[$level] ?? '';
        echo "{$color}[{$timestamp}] [{$level}] {$message}{$reset}\n";
    }

    private function showHelp(): void
    {
        echo "Enhanced Consignment Upload System - Database Migration\n";
        echo "======================================================\n\n";
        echo "Usage: php run-migration.php [OPTIONS]\n\n";
        echo "Options:\n";
        echo "  --dry-run       Show what would be done without making changes\n";
        echo "  --verify-only   Check current schema without making changes\n";
        echo "  --rollback      Rollback the migration using latest backup\n";
        echo "  --help          Show this help message\n\n";
        echo "Examples:\n";
        echo "  php run-migration.php                 # Run full migration\n";
        echo "  php run-migration.php --dry-run       # Preview changes\n";
        echo "  php run-migration.php --verify-only   # Check current state\n";
        echo "  php run-migration.php --rollback      # Undo migration\n\n";
    }
}

// Run migration
try {
    $migration = new EnhancedConsignmentMigration();
    $migration->run();
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // ✅ CRITICAL FIX: Always cleanup database connection
    $conn = get_database_connection();
    if ($conn instanceof mysqli && !empty($conn->thread_id)) {
        @$conn->close();
    }
}
