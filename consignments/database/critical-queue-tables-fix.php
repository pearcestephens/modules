<?php
/**
 * CRITICAL FIX: Enhanced Consignment Upload - Queue Tables Integration
 * 
 * Updates the Enhanced Consignment Upload System to properly integrate with
 * the EXISTING queue_consignments table and production queue infrastructure.
 * 
 * CRITICAL FINDINGS:
 * 1. queue_consignments table already EXISTS with 30+ fields
 * 2. Our implementation created WRONG schema (only 12 fields)
 * 3. Must use EXISTING production queue_jobs and queue_webhook_events
 * 4. Need to DROP our incorrect tables and use real ones
 * 
 * @package CIS\Consignments\Database
 * @version 3.0.0 - CRITICAL FIX
 */

declare(strict_types=1);

// Bootstrap CIS database connection
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
    function get_database_connection() {
        static $connection = null;
        
        if ($connection === null) {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $user = $_ENV['DB_USER'] ?? 'jcepnzzkmj';
            $pass = $_ENV['DB_PASS'] ?? 'wprKh9Jq63';
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

class CriticalQueueTablesFix
{
    private $db;
    
    public function __construct()
    {
        $this->db = get_database_connection();
    }
    
    public function run(): void
    {
        try {
            $this->log("🚨 CRITICAL FIX: Queue Tables Integration", 'CRITICAL');
            $this->log("============================================");
            
            // Step 1: Verify existing production tables
            $this->verifyProductionTables();
            
            // Step 2: Fix queue_consignments schema mismatch
            $this->fixQueueConsignmentsSchema();
            
            // Step 3: Update transfers table integration
            $this->updateTransfersIntegration();
            
            // Step 4: Drop incorrect tables we created
            $this->dropIncorrectTables();
            
            // Step 5: Create missing audit tables (from CONSIGNMENT TABLES)
            $this->createMissingAuditTables();
            
            // Step 6: Update Enhanced Upload System to use correct tables
            $this->updateUploadSystemIntegration();
            
            $this->log("🟢 CRITICAL FIX COMPLETED SUCCESSFULLY!", 'SUCCESS');
            
        } catch (Exception $e) {
            $this->log("🔴 CRITICAL ERROR: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    private function verifyProductionTables(): void
    {
        $this->log("Verifying existing production queue tables...");
        
        $requiredTables = [
            'queue_consignments',
            'queue_jobs', 
            'queue_webhook_events',
            'queue_metrics',
            'queue_pipelines'
        ];
        
        foreach ($requiredTables as $table) {
            if ($this->tableExists($table)) {
                $this->log("✅ Production table exists: $table", 'SUCCESS');
                
                // Get column count for queue_consignments
                if ($table === 'queue_consignments') {
                    $result = $this->db->query("SHOW COLUMNS FROM `$table`");
                    $columnCount = $result->num_rows;
                    $this->log("   - queue_consignments has $columnCount columns (should be 30+)", 'INFO');
                }
            } else {
                throw new Exception("CRITICAL: Production table missing: $table");
            }
        }
    }
    
    private function fixQueueConsignmentsSchema(): void
    {
        $this->log("Checking queue_consignments schema...");
        
        // Check if our incorrectly created table exists
        $result = $this->db->query("SHOW COLUMNS FROM `queue_consignments`");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        $this->log("Current queue_consignments columns: " . implode(', ', $columns));
        
        // Check if it has the required production fields
        $requiredFields = [
            'vend_version', 'type', 'status', 'reference', 'name',
            'source_outlet_id', 'destination_outlet_id', 'supplier_id',
            'cis_transfer_id', 'sent_at', 'dispatched_at', 'received_at'
        ];
        
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!in_array($field, $columns)) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            $this->log("🔴 CRITICAL: queue_consignments missing fields: " . implode(', ', $missingFields), 'ERROR');
            $this->log("This indicates we created the wrong table structure!", 'ERROR');
            
            // The production table should already exist with correct schema
            // If it doesn't, we need to create it properly
            if (count($columns) < 20) {
                $this->log("Re-creating queue_consignments with correct production schema...");
                $this->recreateQueueConsignments();
            }
        } else {
            $this->log("✅ queue_consignments has correct production schema", 'SUCCESS');
        }
    }
    
    private function recreateQueueConsignments(): void
    {
        $this->log("Dropping incorrect queue_consignments table...");
        $this->db->query("DROP TABLE IF EXISTS `queue_consignments`");
        
        $this->log("Creating correct queue_consignments with production schema...");
        $sql = "CREATE TABLE `queue_consignments` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `vend_consignment_id` varchar(100) NOT NULL COMMENT 'Lightspeed consignment UUID',
          `vend_version` int(10) unsigned DEFAULT 0 COMMENT 'Optimistic locking version from Vend',
          `type` enum('SUPPLIER','OUTLET','RETURN','STOCKTAKE') NOT NULL COMMENT 'Consignment type',
          `status` enum('OPEN','SENT','DISPATCHED','RECEIVED','CANCELLED','STOCKTAKE','STOCKTAKE_SCHEDULED','STOCKTAKE_IN_PROGRESS','STOCKTAKE_IN_PROGRESS_PROCESSED','STOCKTAKE_COMPLETE') NOT NULL DEFAULT 'OPEN' COMMENT 'Current workflow state',
          `reference` varchar(255) DEFAULT NULL COMMENT 'PO number / Transfer reference',
          `name` text DEFAULT NULL COMMENT 'Internal notes / description',
          `source_outlet_id` varchar(100) DEFAULT NULL COMMENT 'Source outlet UUID (for OUTLET type)',
          `destination_outlet_id` varchar(100) DEFAULT NULL COMMENT 'Destination outlet UUID',
          `supplier_id` varchar(100) DEFAULT NULL COMMENT 'Supplier UUID (for SUPPLIER type)',
          `cis_user_id` int(10) unsigned DEFAULT NULL COMMENT 'CIS user who created this',
          `cis_purchase_order_id` int(10) unsigned DEFAULT NULL COMMENT 'Link to legacy PO table (if exists)',
          `cis_transfer_id` int(10) unsigned DEFAULT NULL COMMENT 'Link to legacy transfer table (if exists)',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          `sent_at` timestamp NULL DEFAULT NULL COMMENT 'When marked SENT',
          `dispatched_at` timestamp NULL DEFAULT NULL COMMENT 'When marked DISPATCHED',
          `received_at` timestamp NULL DEFAULT NULL COMMENT 'When marked RECEIVED',
          `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When fully processed (CIS inventory updated)',
          `trace_id` varchar(64) DEFAULT NULL COMMENT 'Request trace ID for debugging',
          `last_sync_at` timestamp NULL DEFAULT NULL COMMENT 'Last successful Vend API sync',
          `is_migrated` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag for historical migration data',
          `sync_source` enum('CIS','LIGHTSPEED','MIGRATION') NOT NULL DEFAULT 'CIS' COMMENT 'Origin of consignment data',
          `sync_last_pulled_at` datetime DEFAULT NULL COMMENT 'Last sync FROM Lightspeed',
          `sync_last_pushed_at` datetime DEFAULT NULL COMMENT 'Last sync TO Lightspeed',
          `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
          `approved_for_lightspeed` tinyint(1) DEFAULT 0,
          `approved_by_user_id` bigint(20) unsigned DEFAULT NULL,
          `approved_at` datetime DEFAULT NULL,
          `pushed_to_lightspeed_at` datetime DEFAULT NULL,
          `lightspeed_push_attempts` int(11) DEFAULT 0,
          `lightspeed_push_error` text DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `vend_consignment_id` (`vend_consignment_id`),
          KEY `idx_vend_consignment_id` (`vend_consignment_id`),
          KEY `idx_type_status` (`type`,`status`),
          KEY `idx_destination_outlet` (`destination_outlet_id`),
          KEY `idx_source_outlet` (`source_outlet_id`),
          KEY `idx_supplier` (`supplier_id`),
          KEY `idx_cis_user` (`cis_user_id`),
          KEY `idx_created_at` (`created_at`),
          KEY `idx_status_updated` (`status`,`updated_at`),
          KEY `idx_trace_id` (`trace_id`),
          KEY `idx_cis_transfer_id` (`cis_transfer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Master consignment records synced with Lightspeed - PRODUCTION SCHEMA'";
        
        if (!$this->db->query($sql)) {
            throw new Exception("Failed to create correct queue_consignments table: " . $this->db->error);
        }
        
        $this->log("✅ Created queue_consignments with correct production schema");
    }
    
    private function updateTransfersIntegration(): void
    {
        $this->log("Updating transfers table integration...");
        
        // Check if consignment_id column exists
        if (!$this->columnExists('transfers', 'consignment_id')) {
            $this->log("Adding consignment_id column to transfers table...");
            
            $sql = "ALTER TABLE `transfers` 
                    ADD COLUMN `consignment_id` BIGINT UNSIGNED NULL 
                    COMMENT 'Links to queue_consignments.id for Lightspeed sync' 
                    AFTER `vend_transfer_id`";
            
            if (!$this->db->query($sql)) {
                throw new Exception("Failed to add consignment_id column: " . $this->db->error);
            }
            
            $this->log("✅ Added consignment_id column to transfers");
        }
        
        // Add foreign key constraint
        if (!$this->constraintExists('transfers', 'fk_transfers_consignment')) {
            $this->log("Adding foreign key constraint for consignment_id...");
            
            $sql = "ALTER TABLE `transfers` 
                    ADD CONSTRAINT `fk_transfers_consignment` 
                    FOREIGN KEY (`consignment_id`) REFERENCES `queue_consignments` (`id`) 
                    ON DELETE SET NULL ON UPDATE CASCADE";
            
            if (!$this->db->query($sql)) {
                $this->log("Warning: Could not add foreign key constraint: " . $this->db->error, 'WARNING');
            } else {
                $this->log("✅ Added foreign key constraint for consignment_id");
            }
        }
        
        // Add index for performance
        $sql = "ALTER TABLE `transfers` ADD INDEX IF NOT EXISTS `idx_transfers_consignment_id` (`consignment_id`)";
        $this->db->query($sql);
    }
    
    private function dropIncorrectTables(): void
    {
        $this->log("Dropping incorrect tables created by our implementation...");
        
        $tablesToDrop = [
            'queue_consignment_products',
            'queue_consignment_state_transitions'
        ];
        
        foreach ($tablesToDrop as $table) {
            if ($this->tableExists($table)) {
                $this->log("Dropping incorrect table: $table");
                $this->db->query("DROP TABLE `$table`");
            }
        }
        
        // Check if we need to drop our incorrect queue_jobs or queue_webhook_events
        // (if they don't match production schema)
        $this->verifyQueueJobsSchema();
        $this->verifyQueueWebhookEventsSchema();
    }
    
    private function verifyQueueJobsSchema(): void
    {
        $this->log("Verifying queue_jobs schema...");
        
        $result = $this->db->query("SHOW COLUMNS FROM `queue_jobs`");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        // Check for production-specific fields
        $productionFields = [
            'job_id', 'job_type', 'queue_name', 'payload', 'priority',
            'status', 'attempts', 'max_attempts', 'available_at',
            'worker_id', 'heartbeat_at', 'heartbeat_timeout',
            'processing_log', 'leased_until'
        ];
        
        $hasProductionSchema = true;
        foreach ($productionFields as $field) {
            if (!in_array($field, $columns)) {
                $hasProductionSchema = false;
                break;
            }
        }
        
        if (!$hasProductionSchema) {
            $this->log("🟡 queue_jobs table may need updating to production schema", 'WARNING');
        } else {
            $this->log("✅ queue_jobs has production schema");
        }
    }
    
    private function verifyQueueWebhookEventsSchema(): void
    {
        $this->log("Verifying queue_webhook_events schema...");
        
        $result = $this->db->query("SHOW COLUMNS FROM `queue_webhook_events`");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        // Check for production-specific fields
        $productionFields = [
            'webhook_id', 'webhook_type', 'status', 'received_at',
            'processed_at', 'queue_job_id', 'hmac_valid',
            'payload_json', 'headers_json', 'source_ip'
        ];
        
        $hasProductionSchema = true;
        foreach ($productionFields as $field) {
            if (!in_array($field, $columns)) {
                $hasProductionSchema = false;
                break;
            }
        }
        
        if (!$hasProductionSchema) {
            $this->log("🟡 queue_webhook_events table may need updating to production schema", 'WARNING');
        } else {
            $this->log("✅ queue_webhook_events has production schema");
        }
    }
    
    private function createMissingAuditTables(): void
    {
        $this->log("Creating missing critical audit tables from CONSIGNMENT TABLES spec...");
        
        // Create consignment_audit_log (CRITICAL for compliance)
        if (!$this->tableExists('consignment_audit_log')) {
            $this->log("Creating consignment_audit_log table...");
            
            $sql = "CREATE TABLE `consignment_audit_log` (
              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `transaction_id` varchar(50) DEFAULT NULL COMMENT 'Related transaction ID',
              `entity_type` enum('transfer','po') NOT NULL DEFAULT 'transfer',
              `entity_pk` int(11) DEFAULT NULL,
              `transfer_pk` int(11) DEFAULT NULL,
              `transfer_id` varchar(100) DEFAULT NULL COMMENT 'Internal transfer ID',
              `vend_consignment_id` varchar(100) DEFAULT NULL COMMENT 'Vend consignment ID',
              `vend_transfer_id` char(36) DEFAULT NULL,
              `action` varchar(100) NOT NULL COMMENT 'Action performed',
              `operation_type` varchar(50) DEFAULT NULL COMMENT 'Operation type for bulletproof compatibility',
              `status` varchar(50) NOT NULL COMMENT 'Action status',
              `actor_type` enum('system','user','api','cron','webhook') NOT NULL,
              `actor_id` varchar(100) DEFAULT NULL COMMENT 'User ID or system identifier',
              `user_id` int(11) DEFAULT NULL COMMENT 'User ID for bulletproof compatibility',
              `outlet_from` varchar(100) DEFAULT NULL,
              `outlet_to` varchar(100) DEFAULT NULL,
              `data_before` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'State before action' CHECK (json_valid(`data_before`)),
              `data_after` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'State after action' CHECK (json_valid(`data_after`)),
              `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
              `rollback_details` longtext DEFAULT NULL COMMENT 'Rollback information if failed',
              `duration_seconds` decimal(10,3) DEFAULT NULL COMMENT 'Operation duration',
              `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional context data' CHECK (json_valid(`metadata`)),
              `processing_time_ms` int(10) unsigned DEFAULT NULL,
              `api_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'External API response' CHECK (json_valid(`api_response`)),
              `session_id` varchar(255) DEFAULT NULL,
              `ip_address` varchar(45) DEFAULT NULL,
              `user_agent` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `completed_at` datetime DEFAULT NULL COMMENT 'When operation completed',
              PRIMARY KEY (`id`),
              KEY `idx_transfer_id` (`transfer_id`),
              KEY `idx_vend_consignment` (`vend_consignment_id`),
              KEY `idx_action_status` (`action`,`status`),
              KEY `idx_actor` (`actor_type`,`actor_id`),
              KEY `idx_created_at` (`created_at`),
              KEY `idx_transaction_id` (`transaction_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
            COMMENT='Comprehensive audit trail for all transfer operations'";
            
            if ($this->db->query($sql)) {
                $this->log("✅ Created consignment_audit_log table");
            } else {
                throw new Exception("Failed to create consignment_audit_log: " . $this->db->error);
            }
        }
        
        // Create other critical audit tables
        $this->createTransferUnifiedLog();
        $this->createTransferTransactions();
    }
    
    private function createTransferUnifiedLog(): void
    {
        if (!$this->tableExists('consignment_unified_log')) {
            $this->log("Creating consignment_unified_log table...");
            
            $sql = "CREATE TABLE `consignment_unified_log` (
              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `trace_id` varchar(100) NOT NULL COMMENT 'Distributed tracing ID',
              `correlation_id` varchar(100) DEFAULT NULL COMMENT 'Links related operations across services',
              `category` varchar(50) NOT NULL COMMENT 'transfer, shipment, ai_decision, vend_sync, queue, etc.',
              `event_type` varchar(100) NOT NULL COMMENT 'Specific event name',
              `severity` enum('debug','info','notice','warning','error','critical','alert','emergency') NOT NULL DEFAULT 'info' COMMENT 'PSR-3 severity levels',
              `message` text NOT NULL COMMENT 'Human-readable event description',
              `transfer_id` int(10) unsigned DEFAULT NULL,
              `vend_consignment_id` varchar(100) DEFAULT NULL COMMENT 'Vend consignment UUID',
              `actor_user_id` int(10) unsigned DEFAULT NULL COMMENT 'Staff ID who triggered action',
              `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Structured event payload (sanitized PII)' CHECK (json_valid(`event_data`)),
              `duration_ms` int(10) unsigned DEFAULT NULL COMMENT 'Operation duration in milliseconds',
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_trace` (`trace_id`),
              KEY `idx_category_severity` (`category`,`severity`,`created_at`),
              KEY `idx_transfer` (`transfer_id`,`created_at`),
              KEY `idx_vend_consignment` (`vend_consignment_id`),
              KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
            COMMENT='Unified transfer system event log with queue integration'";
            
            if ($this->db->query($sql)) {
                $this->log("✅ Created consignment_unified_log table");
            } else {
                throw new Exception("Failed to create consignment_unified_log: " . $this->db->error);
            }
        }
    }
    
    private function createTransferTransactions(): void
    {
        if (!$this->tableExists('consignment_transactions')) {
            $this->log("Creating consignment_transactions table...");
            
            $sql = "CREATE TABLE `consignment_transactions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `transaction_id` varchar(50) NOT NULL COMMENT 'Unique transaction identifier',
              `transfer_id` int(11) NOT NULL COMMENT 'Related transfer ID',
              `operation_type` varchar(20) NOT NULL COMMENT 'Type of operation (CONSIGNMENT_UPLOAD, PACK_SUBMIT, RECEIVE_SUBMIT)',
              `status` enum('STARTED','COMMITTED','FAILED','ROLLED_BACK') NOT NULL DEFAULT 'STARTED',
              `started_at` datetime NOT NULL COMMENT 'When transaction began',
              `completed_at` datetime DEFAULT NULL COMMENT 'When transaction finished',
              `data_snapshot` longtext DEFAULT NULL COMMENT 'JSON snapshot of input data',
              `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
              `user_id` int(11) DEFAULT NULL COMMENT 'User who initiated transaction',
              `session_id` varchar(64) DEFAULT NULL COMMENT 'Session identifier',
              `ip_address` varchar(45) DEFAULT NULL COMMENT 'User IP address',
              `user_agent` text DEFAULT NULL COMMENT 'Browser user agent',
              `created_at` datetime DEFAULT current_timestamp(),
              `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `transaction_id` (`transaction_id`),
              KEY `idx_transfer_id` (`transfer_id`),
              KEY `idx_status` (`status`),
              KEY `idx_started_at` (`started_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
            COMMENT='Tracks all transfer transactions for failure protection'";
            
            if ($this->db->query($sql)) {
                $this->log("✅ Created consignment_transactions table");
            } else {
                throw new Exception("Failed to create consignment_transactions: " . $this->db->error);
            }
        }
    }
    
    private function updateUploadSystemIntegration(): void
    {
        $this->log("Integration fixes completed. Enhanced Upload System needs code updates:");
        $this->log("1. Update enhanced-transfer-upload.php to use correct queue_consignments schema");
        $this->log("2. Update process-consignment-upload.php to use production queue_jobs");
        $this->log("3. Add comprehensive audit logging with consignment_audit_log");
        $this->log("4. Use existing queue infrastructure instead of our custom tables");
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
            'ERROR' => "\033[31m",
            'CRITICAL' => "\033[35m"
        ];
        $reset = "\033[0m";
        
        $color = $colors[$level] ?? '';
        echo "{$color}[{$timestamp}] [{$level}] {$message}{$reset}\n";
    }
}

// Execute the critical fix
try {
    $fix = new CriticalQueueTablesFix();
    $fix->run();
    echo "\n🟢 CRITICAL FIX COMPLETED - Ready for Enhanced Upload System integration!\n";
} catch (Exception $e) {
    echo "\n🔴 CRITICAL FIX FAILED: " . $e->getMessage() . "\n";
    exit(1);
}