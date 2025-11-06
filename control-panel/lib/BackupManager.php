<?php
/**
 * BackupManager - Database & File Backup System
 *
 * @package CIS\ControlPanel
 * @version 1.0.0
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 */

namespace CIS\ControlPanel;

class BackupManager
{
    private $pdo;
    private $config;
    private $backupDir;
    private $logFile;

    public function __construct($pdo, $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'backup_dir' => __DIR__ . '/../backups',
            'retention_days' => 30,
            'compress' => true,
            'db_host' => $_ENV['DB_HOST'] ?? 'localhost',
            'db_name' => $_ENV['DB_NAME'] ?? 'cis',
            'db_user' => $_ENV['DB_USER'] ?? 'root',
            'db_pass' => $_ENV['DB_PASS'] ?? '',
            // Offsite backup configuration
            'offsite_enabled' => $_ENV['BACKUP_OFFSITE_ENABLED'] ?? false,
            'offsite_type' => $_ENV['BACKUP_OFFSITE_TYPE'] ?? 's3', // s3, ftp, sftp, rsync
            'offsite_keep_local' => $_ENV['BACKUP_KEEP_LOCAL'] ?? true,
            // S3 Config
            's3_bucket' => $_ENV['BACKUP_S3_BUCKET'] ?? '',
            's3_region' => $_ENV['BACKUP_S3_REGION'] ?? 'ap-southeast-2',
            's3_key' => $_ENV['BACKUP_S3_KEY'] ?? '',
            's3_secret' => $_ENV['BACKUP_S3_SECRET'] ?? '',
            's3_endpoint' => $_ENV['BACKUP_S3_ENDPOINT'] ?? null, // For compatible services
            // FTP/SFTP Config
            'ftp_host' => $_ENV['BACKUP_FTP_HOST'] ?? '',
            'ftp_port' => $_ENV['BACKUP_FTP_PORT'] ?? 21,
            'ftp_user' => $_ENV['BACKUP_FTP_USER'] ?? '',
            'ftp_pass' => $_ENV['BACKUP_FTP_PASS'] ?? '',
            'ftp_path' => $_ENV['BACKUP_FTP_PATH'] ?? '/backups',
            'ftp_ssl' => $_ENV['BACKUP_FTP_SSL'] ?? true,
            // Rsync Config
            'rsync_host' => $_ENV['BACKUP_RSYNC_HOST'] ?? '',
            'rsync_user' => $_ENV['BACKUP_RSYNC_USER'] ?? '',
            'rsync_path' => $_ENV['BACKUP_RSYNC_PATH'] ?? '',
            'rsync_key' => $_ENV['BACKUP_RSYNC_KEY'] ?? '', // SSH key path
        ], $config);

        $this->backupDir = $this->config['backup_dir'];
        $this->logFile = $this->backupDir . '/backup.log';
        $this->ensureDirectoryExists();
        $this->initializeTable();
    }

    /**
     * Create backups directory and log file
     */
    private function ensureDirectoryExists()
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }

    /**
     * Initialize cis_backups table
     */
    private function initializeTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS cis_backups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_type ENUM('database', 'files', 'full') NOT NULL,
            filename VARCHAR(255) NOT NULL,
            filepath VARCHAR(500) NOT NULL,
            offsite_location VARCHAR(500),
            storage_type ENUM('local', 'offsite', 'both') DEFAULT 'local',
            size_bytes BIGINT UNSIGNED NOT NULL,
            size_formatted VARCHAR(20),
            compression ENUM('none', 'gzip', 'zip') DEFAULT 'none',
            status ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
            offsite_status ENUM('pending', 'uploading', 'completed', 'failed') DEFAULT 'pending',
            checksum VARCHAR(64),
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            offsite_completed_at DATETIME,
            error_message TEXT,
            offsite_error TEXT,
            metadata JSON,
            INDEX idx_backup_type (backup_type),
            INDEX idx_status (status),
            INDEX idx_storage_type (storage_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }

    /**
     * Create database backup using mysqldump
     */
    public function backupDatabase($userId = null, $tables = [])
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "db_backup_{$timestamp}.sql";
        $filepath = $this->backupDir . '/' . $filename;

        // Log start
        $backupId = $this->logBackupStart('database', $filename, $filepath, $userId);

        try {
            // Build mysqldump command
            $tablesStr = empty($tables) ? '' : implode(' ', $tables);
            $command = sprintf(
                'mysqldump -h %s -u %s -p%s %s %s > %s 2>&1',
                escapeshellarg($this->config['db_host']),
                escapeshellarg($this->config['db_user']),
                escapeshellarg($this->config['db_pass']),
                escapeshellarg($this->config['db_name']),
                $tablesStr,
                escapeshellarg($filepath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("mysqldump failed: " . implode("\n", $output));
            }

            // Compress if enabled
            if ($this->config['compress']) {
                $this->compressFile($filepath, 'gzip');
                $filepath .= '.gz';
                $filename .= '.gz';
            }

            // Calculate checksum
            $checksum = hash_file('sha256', $filepath);
            $size = filesize($filepath);

            // Update backup record
            $this->logBackupComplete($backupId, $filepath, $size, $checksum);

            $this->log("Database backup completed: $filename ($size bytes)");

            // Upload to offsite if enabled
            $offsiteResult = null;
            if ($this->config['offsite_enabled']) {
                $offsiteResult = $this->uploadToOffsite($backupId);
                if ($offsiteResult['success']) {
                    $this->log("Offsite upload completed for backup ID: $backupId");
                } else {
                    $this->log("Offsite upload FAILED for backup ID: $backupId - {$offsiteResult['error']}", 'ERROR');
                }
            }

            return [
                'success' => true,
                'backup_id' => $backupId,
                'filename' => $filename,
                'size' => $size,
                'checksum' => $checksum,
                'offsite_result' => $offsiteResult
            ];

        } catch (\Exception $e) {
            $this->logBackupError($backupId, $e->getMessage());
            $this->log("Database backup FAILED: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Compress file using gzip or zip
     */
    private function compressFile($filepath, $method = 'gzip')
    {
        if ($method === 'gzip' && function_exists('gzencode')) {
            $content = file_get_contents($filepath);
            $compressed = gzencode($content, 9);
            file_put_contents($filepath . '.gz', $compressed);
            unlink($filepath);
            return true;
        }
        return false;
    }

    /**
     * Restore database from backup
     */
    public function restoreDatabase($backupId)
    {
        $backup = $this->getBackupById($backupId);
        if (!$backup) {
            return ['success' => false, 'error' => 'Backup not found'];
        }

        $filepath = $backup['filepath'];

        // Decompress if needed
        if ($backup['compression'] === 'gzip') {
            $decompressed = $this->backupDir . '/temp_restore.sql';
            $gz = gzopen($filepath, 'rb');
            $out = fopen($decompressed, 'wb');
            while (!gzeof($gz)) {
                fwrite($out, gzread($gz, 4096));
            }
            gzclose($gz);
            fclose($out);
            $filepath = $decompressed;
        }

        try {
            // Execute SQL file
            $sql = file_get_contents($filepath);
            $this->pdo->exec($sql);

            // Clean up temp file
            if (isset($decompressed) && file_exists($decompressed)) {
                unlink($decompressed);
            }

            $this->log("Database restored from backup ID: $backupId");

            return ['success' => true, 'message' => 'Database restored successfully'];

        } catch (\Exception $e) {
            $this->log("Database restore FAILED: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all backups with optional filtering
     */
    public function getBackups($filters = [])
    {
        $sql = "SELECT * FROM cis_backups WHERE 1=1";
        $params = [];

        if (!empty($filters['type'])) {
            $sql .= " AND backup_type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get backup by ID
     */
    public function getBackupById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cis_backups WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Delete old backups based on retention policy
     */
    public function cleanupOldBackups()
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$this->config['retention_days']} days"));

        $stmt = $this->pdo->prepare("SELECT * FROM cis_backups WHERE created_at < ? AND status = 'completed'");
        $stmt->execute([$cutoffDate]);
        $oldBackups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $deleted = 0;
        foreach ($oldBackups as $backup) {
            if (file_exists($backup['filepath'])) {
                unlink($backup['filepath']);
            }
            $deleteStmt = $this->pdo->prepare("DELETE FROM cis_backups WHERE id = ?");
            $deleteStmt->execute([$backup['id']]);
            $deleted++;
        }

        $this->log("Cleanup: Deleted $deleted old backups (older than {$this->config['retention_days']} days)");

        return ['success' => true, 'deleted' => $deleted];
    }

    /**
     * Log backup start
     */
    private function logBackupStart($type, $filename, $filepath, $userId)
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cis_backups (backup_type, filename, filepath, size_bytes, created_by, status)
             VALUES (?, ?, ?, 0, ?, 'in_progress')"
        );
        $stmt->execute([$type, $filename, $filepath, $userId]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Log backup completion
     */
    private function logBackupComplete($backupId, $filepath, $size, $checksum)
    {
        $compression = strpos($filepath, '.gz') !== false ? 'gzip' : 'none';
        $sizeFormatted = $this->formatBytes($size);

        $stmt = $this->pdo->prepare(
            "UPDATE cis_backups
             SET filepath = ?, size_bytes = ?, size_formatted = ?, checksum = ?,
                 compression = ?, status = 'completed', completed_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$filepath, $size, $sizeFormatted, $checksum, $compression, $backupId]);
    }

    /**
     * Log backup error
     */
    private function logBackupError($backupId, $error)
    {
        $stmt = $this->pdo->prepare(
            "UPDATE cis_backups SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$error, $backupId]);
    }

    /**
     * Format bytes to human-readable size
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
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

    /**
     * Upload backup to offsite storage
     */
    public function uploadToOffsite($backupId)
    {
        if (!$this->config['offsite_enabled']) {
            return ['success' => false, 'error' => 'Offsite backup not enabled'];
        }

        $backup = $this->getBackupById($backupId);
        if (!$backup || !file_exists($backup['filepath'])) {
            return ['success' => false, 'error' => 'Backup file not found'];
        }

        $this->updateOffsiteStatus($backupId, 'uploading');

        try {
            $result = match ($this->config['offsite_type']) {
                's3' => $this->uploadToS3($backup),
                'ftp' => $this->uploadToFTP($backup, false),
                'sftp' => $this->uploadToFTP($backup, true),
                'rsync' => $this->uploadToRsync($backup),
                default => ['success' => false, 'error' => 'Unknown offsite type']
            };

            if ($result['success']) {
                $this->updateOffsiteStatus($backupId, 'completed', $result['location']);

                // Delete local copy if configured
                if (!$this->config['offsite_keep_local']) {
                    unlink($backup['filepath']);
                    $this->log("Local backup deleted (offsite only): {$backup['filename']}");
                }
            } else {
                $this->updateOffsiteStatus($backupId, 'failed', null, $result['error']);
            }

            return $result;

        } catch (\Exception $e) {
            $this->updateOffsiteStatus($backupId, 'failed', null, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload to Amazon S3 or compatible service
     */
    private function uploadToS3($backup)
    {
        // Basic S3 upload using AWS SDK (requires composer package: aws/aws-sdk-php)
        if (!class_exists('\Aws\S3\S3Client')) {
            return ['success' => false, 'error' => 'AWS SDK not installed'];
        }

        try {
            $s3Config = [
                'version' => 'latest',
                'region'  => $this->config['s3_region'],
                'credentials' => [
                    'key'    => $this->config['s3_key'],
                    'secret' => $this->config['s3_secret'],
                ],
            ];

            if ($this->config['s3_endpoint']) {
                $s3Config['endpoint'] = $this->config['s3_endpoint'];
            }

            $s3 = new \Aws\S3\S3Client($s3Config);

            $key = 'cis-backups/' . basename($backup['filename']);

            $result = $s3->putObject([
                'Bucket' => $this->config['s3_bucket'],
                'Key'    => $key,
                'SourceFile' => $backup['filepath'],
                'ServerSideEncryption' => 'AES256',
            ]);

            $location = $result['ObjectURL'] ?? "s3://{$this->config['s3_bucket']}/{$key}";
            $this->log("Uploaded to S3: {$backup['filename']} -> $location");

            return ['success' => true, 'location' => $location];

        } catch (\Exception $e) {
            $this->log("S3 upload failed: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload via FTP or SFTP
     */
    private function uploadToFTP($backup, $useSFTP = false)
    {
        try {
            if ($useSFTP) {
                // SFTP using ssh2 extension
                if (!function_exists('ssh2_connect')) {
                    return ['success' => false, 'error' => 'SSH2 extension not installed'];
                }

                $conn = ssh2_connect($this->config['ftp_host'], $this->config['ftp_port']);
                ssh2_auth_password($conn, $this->config['ftp_user'], $this->config['ftp_pass']);
                $sftp = ssh2_sftp($conn);

                $remotePath = $this->config['ftp_path'] . '/' . basename($backup['filename']);
                $stream = fopen("ssh2.sftp://$sftp$remotePath", 'w');
                $localStream = fopen($backup['filepath'], 'r');
                stream_copy_to_stream($localStream, $stream);
                fclose($stream);
                fclose($localStream);

                $location = "sftp://{$this->config['ftp_host']}$remotePath";

            } else {
                // Regular FTP
                if ($this->config['ftp_ssl']) {
                    $conn = ftp_ssl_connect($this->config['ftp_host'], $this->config['ftp_port']);
                } else {
                    $conn = ftp_connect($this->config['ftp_host'], $this->config['ftp_port']);
                }

                if (!$conn || !ftp_login($conn, $this->config['ftp_user'], $this->config['ftp_pass'])) {
                    return ['success' => false, 'error' => 'FTP connection failed'];
                }

                ftp_pasv($conn, true);
                $remotePath = $this->config['ftp_path'] . '/' . basename($backup['filename']);

                if (!ftp_put($conn, $remotePath, $backup['filepath'], FTP_BINARY)) {
                    return ['success' => false, 'error' => 'FTP upload failed'];
                }

                ftp_close($conn);
                $location = "ftp://{$this->config['ftp_host']}$remotePath";
            }

            $this->log("Uploaded to " . ($useSFTP ? 'SFTP' : 'FTP') . ": {$backup['filename']}");
            return ['success' => true, 'location' => $location];

        } catch (\Exception $e) {
            $this->log("FTP upload failed: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload via Rsync
     */
    private function uploadToRsync($backup)
    {
        try {
            $remotePath = "{$this->config['rsync_user']}@{$this->config['rsync_host']}:{$this->config['rsync_path']}";

            $command = sprintf(
                'rsync -avz --progress %s %s %s 2>&1',
                $this->config['rsync_key'] ? "-e 'ssh -i {$this->config['rsync_key']}'" : '',
                escapeshellarg($backup['filepath']),
                escapeshellarg($remotePath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Rsync failed: " . implode("\n", $output));
            }

            $location = $remotePath . '/' . basename($backup['filename']);
            $this->log("Uploaded to Rsync: {$backup['filename']} -> $location");

            return ['success' => true, 'location' => $location];

        } catch (\Exception $e) {
            $this->log("Rsync upload failed: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update offsite upload status
     */
    private function updateOffsiteStatus($backupId, $status, $location = null, $error = null)
    {
        $sql = "UPDATE cis_backups SET offsite_status = ?";
        $params = [$status];

        if ($location) {
            $sql .= ", offsite_location = ?, storage_type = 'both'";
            $params[] = $location;
        }

        if ($status === 'completed') {
            $sql .= ", offsite_completed_at = NOW()";
            if (!$this->config['offsite_keep_local']) {
                $sql .= ", storage_type = 'offsite'";
            }
        }

        if ($error) {
            $sql .= ", offsite_error = ?";
            $params[] = $error;
        }

        $sql .= " WHERE id = ?";
        $params[] = $backupId;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Get storage configuration summary
     */
    public function getStorageConfig()
    {
        return [
            'local_enabled' => true,
            'local_path' => $this->backupDir,
            'offsite_enabled' => $this->config['offsite_enabled'],
            'offsite_type' => $this->config['offsite_type'],
            'offsite_keep_local' => $this->config['offsite_keep_local'],
            'retention_days' => $this->config['retention_days'],
            'compression' => $this->config['compress'],
        ];
    }
}
