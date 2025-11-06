<?php
/**
 * EnvironmentSync - Sync data between Dev/Staging/Production
 *
 * @package CIS\ControlPanel
 * @version 1.0.0
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

namespace CIS\ControlPanel;

class EnvironmentSync
{
    private $pdo;
    private $config;
    private $logFile;

    public function __construct($pdo, $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'sync_enabled' => $_ENV['SYNC_ENABLED'] ?? false,
            'current_env' => $_ENV['APP_ENV'] ?? 'production',
            // Development environment
            'dev_db_host' => $_ENV['DEV_DB_HOST'] ?? '',
            'dev_db_name' => $_ENV['DEV_DB_NAME'] ?? '',
            'dev_db_user' => $_ENV['DEV_DB_USER'] ?? '',
            'dev_db_pass' => $_ENV['DEV_DB_PASS'] ?? '',
            'dev_path' => $_ENV['DEV_PATH'] ?? '',
            // Staging environment
            'staging_db_host' => $_ENV['STAGING_DB_HOST'] ?? '',
            'staging_db_name' => $_ENV['STAGING_DB_NAME'] ?? '',
            'staging_db_user' => $_ENV['STAGING_DB_USER'] ?? '',
            'staging_db_pass' => $_ENV['STAGING_DB_PASS'] ?? '',
            'staging_path' => $_ENV['STAGING_PATH'] ?? '',
            // Production environment
            'prod_db_host' => $_ENV['PROD_DB_HOST'] ?? '',
            'prod_db_name' => $_ENV['PROD_DB_NAME'] ?? '',
            'prod_db_user' => $_ENV['PROD_DB_USER'] ?? '',
            'prod_db_pass' => $_ENV['PROD_DB_PASS'] ?? '',
            'prod_path' => $_ENV['PROD_PATH'] ?? '',
            // Sanitization rules
            'sanitize_pii' => $_ENV['SYNC_SANITIZE_PII'] ?? true,
            'exclude_tables' => $_ENV['SYNC_EXCLUDE_TABLES'] ?? 'sessions,logs,cache',
        ], $config);

        $this->logFile = __DIR__ . '/../logs/sync.log';
        $this->ensureLogDirectory();
        $this->initializeTable();
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Initialize cis_sync_jobs table
     */
    private function initializeTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS cis_sync_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sync_direction VARCHAR(50) NOT NULL COMMENT 'e.g. prod->staging',
            sync_type ENUM('database', 'files', 'full') NOT NULL,
            status ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
            started_at DATETIME,
            completed_at DATETIME,
            duration_seconds INT,
            tables_synced TEXT COMMENT 'JSON array of table names',
            files_synced INT DEFAULT 0,
            bytes_transferred BIGINT DEFAULT 0,
            sanitized TINYINT(1) DEFAULT 0,
            dry_run TINYINT(1) DEFAULT 0,
            created_by INT,
            error_message TEXT,
            metadata JSON,
            INDEX idx_direction (sync_direction),
            INDEX idx_status (status),
            INDEX idx_started_at (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }

    /**
     * Sync database from one environment to another
     */
    public function syncDatabase($from, $to, $options = [])
    {
        if (!$this->config['sync_enabled']) {
            return ['success' => false, 'error' => 'Environment sync is disabled'];
        }

        // Safety check: prevent accidental production overwrite
        if ($to === 'production' && !($options['force_prod'] ?? false)) {
            return ['success' => false, 'error' => 'Cannot sync TO production without force_prod=true'];
        }

        $dryRun = $options['dry_run'] ?? false;
        $sanitize = $options['sanitize'] ?? $this->config['sanitize_pii'];
        $tables = $options['tables'] ?? [];

        $syncId = $this->logSyncStart("$from->$to", 'database', $dryRun);

        try {
            $startTime = microtime(true);

            // Get source connection
            $sourceConn = $this->getConnection($from);
            if (!$sourceConn['success']) {
                throw new \Exception("Failed to connect to $from: {$sourceConn['error']}");
            }
            $sourcePdo = $sourceConn['connection'];

            // Get target connection
            $targetConn = $this->getConnection($to);
            if (!$targetConn['success']) {
                throw new \Exception("Failed to connect to $to: {$targetConn['error']}");
            }
            $targetPdo = $targetConn['connection'];

            // Get tables to sync
            if (empty($tables)) {
                $tables = $this->getTables($sourcePdo);
            }

            // Filter excluded tables
            $excludeList = explode(',', $this->config['exclude_tables']);
            $tables = array_diff($tables, $excludeList);

            $this->log("Syncing " . count($tables) . " tables from $from to $to" . ($dryRun ? ' (DRY RUN)' : ''));

            $syncedTables = [];
            $totalBytes = 0;

            foreach ($tables as $table) {
                $result = $this->syncTable($sourcePdo, $targetPdo, $table, $sanitize, $dryRun);
                if ($result['success']) {
                    $syncedTables[] = $table;
                    $totalBytes += $result['bytes'];
                    $this->log("  ✓ $table ({$result['rows']} rows, {$result['bytes']} bytes)");
                } else {
                    $this->log("  ✗ $table FAILED: {$result['error']}", 'ERROR');
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            $this->logSyncComplete($syncId, $syncedTables, 0, $totalBytes, $duration, $sanitize);
            $this->log("Sync completed in {$duration}s");

            return [
                'success' => true,
                'sync_id' => $syncId,
                'tables_synced' => count($syncedTables),
                'bytes_transferred' => $totalBytes,
                'duration' => $duration,
                'dry_run' => $dryRun
            ];

        } catch (\Exception $e) {
            $this->logSyncError($syncId, $e->getMessage());
            $this->log("Sync FAILED: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync a single table
     */
    private function syncTable($sourcePdo, $targetPdo, $table, $sanitize, $dryRun)
    {
        try {
            // Get row count
            $stmt = $sourcePdo->query("SELECT COUNT(*) FROM `$table`");
            $rowCount = $stmt->fetchColumn();

            if ($rowCount === 0) {
                return ['success' => true, 'rows' => 0, 'bytes' => 0];
            }

            if (!$dryRun) {
                // Truncate target table
                $targetPdo->exec("TRUNCATE TABLE `$table`");

                // Copy data
                $stmt = $sourcePdo->query("SELECT * FROM `$table`");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    // Get column names
                    $columns = array_keys($rows[0]);
                    $placeholders = implode(',', array_fill(0, count($columns), '?'));
                    $columnNames = implode('`,`', $columns);

                    $insertSql = "INSERT INTO `$table` (`$columnNames`) VALUES ($placeholders)";
                    $insertStmt = $targetPdo->prepare($insertSql);

                    // Insert rows
                    foreach ($rows as $row) {
                        // Sanitize PII if enabled
                        if ($sanitize) {
                            $row = $this->sanitizeRow($table, $row);
                        }
                        $insertStmt->execute(array_values($row));
                    }
                }
            }

            // Estimate bytes (rough calculation)
            $bytes = $rowCount * 100; // Rough estimate

            return ['success' => true, 'rows' => $rowCount, 'bytes' => $bytes];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sanitize sensitive data in a row
     */
    private function sanitizeRow($table, $row)
    {
        // Define PII columns to sanitize
        $piiColumns = [
            'email' => 'user_' . substr(md5(uniqid()), 0, 8) . '@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'phone' => '021-000-0000',
            'address' => '123 Test Street',
            'credit_card' => '4111111111111111',
            'ssn' => '000-00-0000',
            'bank_account' => '00-0000-0000000-00',
            'ird_number' => '000-000-000',
        ];

        foreach ($piiColumns as $column => $replacement) {
            if (isset($row[$column]) && !empty($row[$column])) {
                $row[$column] = $replacement;
            }
        }

        return $row;
    }

    /**
     * Get database connection for environment
     */
    private function getConnection($env)
    {
        try {
            $host = $this->config["{$env}_db_host"];
            $dbname = $this->config["{$env}_db_name"];
            $user = $this->config["{$env}_db_user"];
            $pass = $this->config["{$env}_db_pass"];

            if (empty($host) || empty($dbname)) {
                return ['success' => false, 'error' => "Database config for $env not set"];
            }

            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            return ['success' => true, 'connection' => $pdo];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all tables from database
     */
    private function getTables($pdo)
    {
        $stmt = $pdo->query("SHOW TABLES");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Compare schemas between environments
     */
    public function compareSchemas($env1, $env2)
    {
        $conn1 = $this->getConnection($env1);
        $conn2 = $this->getConnection($env2);

        if (!$conn1['success'] || !$conn2['success']) {
            return ['success' => false, 'error' => 'Failed to connect to environments'];
        }

        $tables1 = $this->getTables($conn1['connection']);
        $tables2 = $this->getTables($conn2['connection']);

        $onlyIn1 = array_diff($tables1, $tables2);
        $onlyIn2 = array_diff($tables2, $tables1);
        $common = array_intersect($tables1, $tables2);

        return [
            'success' => true,
            'only_in_' . $env1 => $onlyIn1,
            'only_in_' . $env2 => $onlyIn2,
            'common_tables' => $common,
            'identical' => empty($onlyIn1) && empty($onlyIn2)
        ];
    }

    /**
     * Get all sync jobs
     */
    public function getSyncHistory($limit = 50)
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cis_sync_jobs ORDER BY started_at DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Log sync start
     */
    private function logSyncStart($direction, $type, $dryRun)
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cis_sync_jobs (sync_direction, sync_type, status, started_at, dry_run)
             VALUES (?, ?, 'in_progress', NOW(), ?)"
        );
        $stmt->execute([$direction, $type, $dryRun ? 1 : 0]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Log sync completion
     */
    private function logSyncComplete($syncId, $tables, $files, $bytes, $duration, $sanitized)
    {
        $stmt = $this->pdo->prepare(
            "UPDATE cis_sync_jobs
             SET status = 'completed', completed_at = NOW(),
                 tables_synced = ?, files_synced = ?, bytes_transferred = ?,
                 duration_seconds = ?, sanitized = ?
             WHERE id = ?"
        );
        $stmt->execute([
            json_encode($tables),
            $files,
            $bytes,
            $duration,
            $sanitized ? 1 : 0,
            $syncId
        ]);
    }

    /**
     * Log sync error
     */
    private function logSyncError($syncId, $error)
    {
        $stmt = $this->pdo->prepare(
            "UPDATE cis_sync_jobs SET status = 'failed', completed_at = NOW(), error_message = ? WHERE id = ?"
        );
        $stmt->execute([$error, $syncId]);
    }

    /**
     * Get sync configuration
     */
    public function getSyncConfig()
    {
        return [
            'enabled' => $this->config['sync_enabled'],
            'current_env' => $this->config['current_env'],
            'sanitize_pii' => $this->config['sanitize_pii'],
            'exclude_tables' => explode(',', $this->config['exclude_tables']),
            'environments' => [
                'dev' => !empty($this->config['dev_db_host']),
                'staging' => !empty($this->config['staging_db_host']),
                'prod' => !empty($this->config['prod_db_host']),
            ]
        ];
    }

    /**
     * Write to log file
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
}
