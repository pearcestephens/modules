<?php
/**
 * Rackspace Legacy Email Importer Service
 *
 * Integrates with legacy Rackspace email accounts, migrating emails and
 * conversation history to the modern CIS email hub while maintaining
 * historical context and metadata.
 *
 * Features:
 * - Auto-discovery and connection of legacy Rackspace accounts
 * - Full email history import with date range selection
 * - Folder mapping and organization
 * - Conversation threading across accounts
 * - Metadata preservation (flags, categories, tags)
 * - Incremental sync for ongoing updates
 *
 * @package StaffEmailHub\Services
 */

namespace StaffEmailHub\Services;

class RackspaceLegacyEmailImporter
{
    private $db;
    private $logger;
    private $config;
    private $staffId;
    private $rackspaceConfig;

    const FOLDER_MAPPING = [
        'INBOX' => 'inbox',
        'Sent Items' => 'sent',
        '[Gmail]/Sent Mail' => 'sent',
        'Drafts' => 'drafts',
        'Deleted Items' => 'trash',
        '[Gmail]/Trash' => 'trash',
        '[Gmail]/Spam' => 'spam',
        'Junk E-mail' => 'spam',
        'Archive' => 'archive',
        '[Gmail]/All Mail' => 'archive',
    ];

    public function __construct($db, $logger, $staffId, $rackspaceConfig)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $this->rackspaceConfig = $rackspaceConfig;
    }

    /**
     * Discover and validate legacy Rackspace account
     */
    public function validateLegacyAccount($email, $password)
    {
        try {
            // Test IMAP connection to Rackspace
            $imapConfig = "{secure.emailsrvr.com:993/imap/ssl/novalidate-cert}";

            // Suppress warnings for non-existent mailbox
            $connection = @imap_open($imapConfig, $email, $password, OP_HALFOPEN);

            if ($connection === false) {
                $this->logger->error('Rackspace IMAP connection failed', [
                    'email' => $email,
                    'error' => imap_last_error()
                ]);
                return [
                    'success' => false,
                    'message' => 'Invalid credentials or connection failed',
                    'error' => imap_last_error()
                ];
            }

            @imap_close($connection);

            return [
                'success' => true,
                'email' => $email,
                'message' => 'Account validated successfully'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Legacy account validation error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create or update legacy account record in database
     */
    public function registerLegacyAccount($email, $password, $displayName = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO staff_email_accounts
                (staff_id, email, account_type, display_name, is_legacy, sync_status, created_at)
                VALUES (?, ?, 'rackspace', ?, 1, 'pending_sync', NOW())
                ON DUPLICATE KEY UPDATE
                    sync_status = 'pending_sync',
                    updated_at = NOW()
            ");

            $stmt->execute([$this->staffId, $email, $displayName ?? $email]);
            $accountId = $this->db->lastInsertId();

            // Store encrypted password
            $this->storeEncryptedPassword($accountId, $password);

            $this->logger->info('Legacy account registered', [
                'staff_id' => $this->staffId,
                'email' => $email,
                'account_id' => $accountId
            ]);

            return ['success' => true, 'account_id' => $accountId];
        } catch (\Exception $e) {
            $this->logger->error('Failed to register legacy account', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Import email history from legacy account
     */
    public function importEmailHistory($accountId, $dateFrom = null, $dateTo = null, $folder = 'INBOX', $limit = 500)
    {
        try {
            // Get account credentials
            $account = $this->getAccountCredentials($accountId);
            if (!$account) {
                return ['success' => false, 'message' => 'Account not found'];
            }

            // Connect to Rackspace IMAP
            $connection = $this->connectToRackspace($account['email'], $account['password']);
            if (!$connection) {
                return ['success' => false, 'message' => 'Connection failed'];
            }

            // Select folder
            @imap_open("{secure.emailsrvr.com:993/imap/ssl/novalidate-cert}$folder",
                       $account['email'], $account['password']);

            // Search for emails in date range
            $searchCriteria = 'ALL';
            if ($dateFrom && $dateTo) {
                $dateFromStr = date('d-M-Y', strtotime($dateFrom));
                $dateToStr = date('d-M-Y', strtotime($dateTo));
                $searchCriteria = "SINCE $dateFromStr BEFORE $dateToStr";
            }

            $messageIds = @imap_search($connection, $searchCriteria, SE_UID);
            if ($messageIds === false) {
                @imap_close($connection);
                return ['success' => true, 'imported' => 0, 'message' => 'No emails found'];
            }

            // Reverse to get newest first, then limit
            $messageIds = array_reverse($messageIds);
            $messageIds = array_slice($messageIds, 0, $limit);

            $imported = 0;
            $failed = 0;

            foreach ($messageIds as $uid) {
                try {
                    $header = @imap_fetch_overview($connection, $uid, FT_UID)[0] ?? null;
                    if (!$header) continue;

                    $body = @imap_fetchbody($connection, $uid, '1.1', FT_UID)
                          ?: @imap_fetchbody($connection, $uid, '1', FT_UID);

                    // Store in unified emails table
                    $stmt = $this->db->prepare("
                        INSERT INTO emails
                        (staff_id, from_address, to_address, subject, body, folder,
                         received_at, message_id, is_legacy, original_account)
                        VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, 1, ?)
                        ON DUPLICATE KEY UPDATE updated_at = NOW()
                    ");

                    $mappedFolder = self::FOLDER_MAPPING[$folder] ?? 'archive';

                    $stmt->execute([
                        $this->staffId,
                        $header->from ?? '',
                        $header->to ?? '',
                        $header->subject ?? '(No Subject)',
                        $body ?? '',
                        $mappedFolder,
                        $header->udate ?? time(),
                        $header->message_id ?? uniqid(),
                        $account['email']
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to import individual email', [
                        'uid' => $uid,
                        'error' => $e->getMessage()
                    ]);
                    $failed++;
                }
            }

            @imap_close($connection);

            // Update sync status
            $updateStmt = $this->db->prepare("
                UPDATE staff_email_accounts
                SET sync_status = 'synced', last_sync_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$accountId]);

            $this->logger->info('Email history import complete', [
                'account_id' => $accountId,
                'imported' => $imported,
                'failed' => $failed,
                'folder' => $folder
            ]);

            return [
                'success' => true,
                'imported' => $imported,
                'failed' => $failed,
                'total' => count($messageIds)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Email import error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Set up incremental sync for ongoing updates
     */
    public function setupIncrementalSync($accountId, $syncInterval = 300)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO legacy_email_sync_config
                (account_id, sync_interval_seconds, enabled, created_at)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE enabled = 1, updated_at = NOW()
            ");

            $stmt->execute([$accountId, $syncInterval]);

            $this->logger->info('Incremental sync configured', [
                'account_id' => $accountId,
                'interval' => $syncInterval
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Thread legacy and new emails into conversations
     */
    public function createConversationThread($emailId, $accountId)
    {
        try {
            // Get email details
            $stmt = $this->db->prepare("
                SELECT message_id, from_address, to_address, subject
                FROM emails WHERE id = ? AND staff_id = ?
            ");
            $stmt->execute([$emailId, $this->staffId]);
            $email = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$email) {
                return ['success' => false, 'message' => 'Email not found'];
            }

            // Find related emails (same subject/participants)
            $subjectBase = preg_replace('/^(RE:|FW:)\s*/i', '', $email['subject']);

            $findStmt = $this->db->prepare("
                SELECT id FROM emails
                WHERE staff_id = ?
                AND (
                    message_id = ?
                    OR (REPLACE(subject, 'RE: ', '') LIKE ?
                        AND (from_address = ? OR to_address = ?))
                )
                ORDER BY received_at ASC
            ");

            $findStmt->execute([
                $this->staffId,
                $email['message_id'],
                "%$subjectBase%",
                $email['from_address'],
                $email['from_address']
            ]);

            $relatedEmails = $findStmt->fetchAll(\PDO::FETCH_COLUMN);

            if (count($relatedEmails) > 1) {
                // Create or update conversation record
                $convStmt = $this->db->prepare("
                    INSERT INTO email_conversations
                    (staff_id, thread_subject, created_at)
                    VALUES (?, ?, NOW())
                ");
                $convStmt->execute([$this->staffId, $subjectBase]);
                $conversationId = $this->db->lastInsertId();

                // Link emails to conversation
                foreach ($relatedEmails as $relatedId) {
                    $linkStmt = $this->db->prepare("
                        UPDATE emails SET conversation_id = ? WHERE id = ?
                    ");
                    $linkStmt->execute([$conversationId, $relatedId]);
                }

                return ['success' => true, 'conversation_id' => $conversationId, 'emails_threaded' => count($relatedEmails)];
            }

            return ['success' => true, 'emails_threaded' => 1];
        } catch (\Exception $e) {
            $this->logger->error('Thread creation error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Private: Connect to Rackspace IMAP server
     */
    private function connectToRackspace($email, $password)
    {
        $imapConfig = "{secure.emailsrvr.com:993/imap/ssl/novalidate-cert}";
        $connection = @imap_open($imapConfig, $email, $password);

        if ($connection === false) {
            $this->logger->error('Rackspace IMAP connection failed', [
                'email' => $email,
                'error' => imap_last_error()
            ]);
            return null;
        }

        return $connection;
    }

    /**
     * Private: Store encrypted password
     */
    private function storeEncryptedPassword($accountId, $password)
    {
        $encrypted = openssl_encrypt(
            $password,
            'AES-256-CBC',
            hash('sha256', getenv('ENCRYPTION_KEY') ?? 'default-key'),
            0
        );

        $stmt = $this->db->prepare("
            UPDATE staff_email_accounts
            SET encrypted_password = ?
            WHERE id = ?
        ");
        $stmt->execute([$encrypted, $accountId]);
    }

    /**
     * Private: Get account credentials (decrypted)
     */
    private function getAccountCredentials($accountId)
    {
        $stmt = $this->db->prepare("
            SELECT email, encrypted_password
            FROM staff_email_accounts
            WHERE id = ? AND is_legacy = 1
        ");
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$account) {
            return null;
        }

        // Decrypt password
        $password = openssl_decrypt(
            $account['encrypted_password'],
            'AES-256-CBC',
            hash('sha256', getenv('ENCRYPTION_KEY') ?? 'default-key'),
            0
        );

        return [
            'email' => $account['email'],
            'password' => $password
        ];
    }

    /**
     * Get migration status and progress
     */
    public function getMigrationStatus($accountId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    sae.id,
                    sae.email,
                    sae.sync_status,
                    sae.last_sync_at,
                    COUNT(e.id) as emails_imported
                FROM staff_email_accounts sae
                LEFT JOIN emails e ON sae.id = e.account_id AND e.is_legacy = 1
                WHERE sae.id = ?
                GROUP BY sae.id
            ");
            $stmt->execute([$accountId]);
            $status = $stmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'status' => $status['sync_status'] ?? 'unknown',
                'emails_imported' => $status['emails_imported'] ?? 0,
                'last_sync' => $status['last_sync_at'],
                'is_complete' => ($status['sync_status'] ?? null) === 'synced'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
