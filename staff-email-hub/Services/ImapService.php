<?php

declare(strict_types=1);

namespace StaffEmailHub\Services;

use Exception;
use PDO;

/**
 * ImapService - Handles Rackspace IMAP email synchronization
 *
 * Features:
 * - IMAP connection pooling
 * - Email synchronization
 * - Attachment handling
 * - Folder management
 * - Error recovery
 */
class ImapService
{
    private PDO $db;
    private $mailbox = null;
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private bool $useSSL;

    public function __construct(
        PDO $db,
        string $host,
        int $port = 993,
        string $username = '',
        string $password = '',
        bool $useSSL = true
    ) {
        $this->db = $db;
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->useSSL = $useSSL;
    }

    /**
     * Connect to IMAP server
     */
    public function connect(): array
    {
        try {
            if ($this->mailbox !== null) {
                return ['success' => true, 'message' => 'Already connected'];
            }

            $protocol = $this->useSSL ? 'imap/ssl' : 'imap';
            $imapPath = "{" . $this->host . ":" . $this->port . "/" . $protocol . "}";

            $this->mailbox = @imap_open($imapPath, $this->username, $this->password);

            if (!$this->mailbox) {
                return $this->error('IMAP connection failed: ' . imap_last_error());
            }

            return ['success' => true, 'message' => 'Connected to IMAP server'];
        } catch (Exception $e) {
            return $this->error('Connection error: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect from IMAP server
     */
    public function disconnect(): void
    {
        if ($this->mailbox) {
            imap_close($this->mailbox);
            $this->mailbox = null;
        }
    }

    /**
     * Get list of folders/mailboxes
     */
    public function getFolders(): array
    {
        try {
            if (!$this->mailbox) {
                $connect = $this->connect();
                if (!$connect['success']) {
                    return $connect;
                }
            }

            $folders = imap_list($this->mailbox, "{" . $this->host . ":" . $this->port . "/imap/ssl}", "*");

            if ($folders === false) {
                return $this->error('Failed to list folders: ' . imap_last_error());
            }

            $folderList = [];
            foreach ($folders as $folder) {
                // Extract folder name from {server}FOLDERNAME format
                $parts = explode('}', $folder);
                $folderName = end($parts);
                $folderList[] = [
                    'name' => $folderName,
                    'path' => $folder,
                    'is_inbox' => strtoupper($folderName) === 'INBOX',
                ];
            }

            return [
                'success' => true,
                'folders' => $folderList,
                'count' => count($folderList),
            ];
        } catch (Exception $e) {
            return $this->error('Failed to get folders: ' . $e->getMessage());
        }
    }

    /**
     * Get unread email count
     */
    public function getUnreadCount(string $folder = 'INBOX'): array
    {
        try {
            if (!$this->mailbox) {
                $connect = $this->connect();
                if (!$connect['success']) {
                    return $connect;
                }
            }

            $folderPath = "{" . $this->host . ":" . $this->port . "/imap/ssl}" . $folder;
            imap_reopen($this->mailbox, $folderPath);

            $status = imap_status($this->mailbox, $folderPath, SA_UNSEEN);

            if ($status === false) {
                return $this->error('Failed to get unread count: ' . imap_last_error());
            }

            return [
                'success' => true,
                'folder' => $folder,
                'unread_count' => $status->unseen,
                'total_count' => $status->messages,
            ];
        } catch (Exception $e) {
            return $this->error('Failed to get unread count: ' . $e->getMessage());
        }
    }

    /**
     * Sync emails from IMAP folder to database
     */
    public function syncEmails(string $folder = 'INBOX', int $staffId = 0, int $limit = 50): array
    {
        try {
            if (!$this->mailbox) {
                $connect = $this->connect();
                if (!$connect['success']) {
                    return $connect;
                }
            }

            // Open folder
            $folderPath = "{" . $this->host . ":" . $this->port . "/imap/ssl}" . $folder;
            imap_reopen($this->mailbox, $folderPath);

            // Get message count
            $messageCount = imap_num_msg($this->mailbox);

            if ($messageCount === 0) {
                return [
                    'success' => true,
                    'synced_count' => 0,
                    'message' => 'No new messages',
                ];
            }

            // Process messages (latest first)
            $start = max(1, $messageCount - $limit + 1);
            $synced = 0;

            for ($i = $messageCount; $i >= $start; $i--) {
                try {
                    $header = imap_headerinfo($this->mailbox, $i);

                    // Check if already synced
                    $msgId = trim($header->message_id, '<>');
                    $stmt = $this->db->prepare("SELECT id FROM staff_emails WHERE message_id = ?");
                    $stmt->execute([$msgId]);

                    if ($stmt->rowCount() > 0) {
                        continue; // Already synced
                    }

                    // Extract email details
                    $from = $header->from[0]->mailbox . '@' . $header->from[0]->host;
                    $subject = $header->subject;
                    $date = $header->date;

                    // Get body
                    $body = $this->getEmailBody($i);

                    // Get attachments
                    $attachments = $this->getAttachments($i, $msgId);

                    // Look up customer
                    $customerStmt = $this->db->prepare("
                        SELECT id FROM customer_hub_profile
                        WHERE email = ? OR alt_email = ?
                        LIMIT 1
                    ");
                    $customerStmt->execute([$from, $from]);
                    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
                    $customerId = $customer['id'] ?? null;

                    // Insert email
                    $insertStmt = $this->db->prepare("
                        INSERT INTO staff_emails
                        (staff_id, customer_id, subject, from_address, to_address, body,
                         message_id, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");

                    $insertStmt->execute([
                        $staffId,
                        $customerId,
                        $subject,
                        $from,
                        $this->username,
                        $body,
                        $msgId,
                        'received',
                    ]);

                    $emailId = $this->db->lastInsertId();

                    // Insert attachments if any
                    foreach ($attachments as $attachment) {
                        $this->saveAttachment($emailId, $attachment);
                    }

                    // Log access
                    $this->logAccess($emailId, $staffId, 'imap_sync');

                    $synced++;
                } catch (Exception $e) {
                    error_log("[ImapService] Failed to sync message $i: " . $e->getMessage());
                    continue;
                }
            }

            return [
                'success' => true,
                'synced_count' => $synced,
                'folder' => $folder,
                'message' => "$synced emails synced from $folder",
            ];
        } catch (Exception $e) {
            return $this->error('Email sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Get email body (handles multipart emails)
     */
    private function getEmailBody(int $messageId): string
    {
        try {
            $structure = imap_fetchstructure($this->mailbox, $messageId);

            // Simple email
            if ($structure->type == 0) {
                return quoted_printable_decode(imap_body($this->mailbox, $messageId));
            }

            // Multipart email - get text part
            if ($structure->type == 1) {
                foreach ($structure->parts as $part) {
                    if ($part->type == 0 && $part->subtype == 'plain') {
                        $partId = '1.' . ($part->partid ?? '1');
                        return quoted_printable_decode(
                            imap_fetchbody($this->mailbox, $messageId, $partId)
                        );
                    }
                }
            }

            // Fallback
            return imap_body($this->mailbox, $messageId);
        } catch (Exception $e) {
            error_log("[ImapService] Failed to get email body: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Get email attachments
     */
    private function getAttachments(int $messageId, string $msgId): array
    {
        try {
            $structure = imap_fetchstructure($this->mailbox, $messageId);

            if (!isset($structure->parts)) {
                return [];
            }

            $attachments = [];

            foreach ($structure->parts as $partIndex => $part) {
                if (isset($part->dparameters)) {
                    foreach ($part->dparameters as $param) {
                        if ($param->attribute === 'filename') {
                            $fileName = $param->value;
                            $partId = ($partIndex + 1);

                            $data = imap_fetchbody($this->mailbox, $messageId, (string)$partId);

                            if ($part->encoding === 3) { // BASE64
                                $data = base64_decode($data);
                            } elseif ($part->encoding === 4) { // QUOTED-PRINTABLE
                                $data = quoted_printable_decode($data);
                            }

                            $attachments[] = [
                                'filename' => $fileName,
                                'data' => $data,
                                'size' => strlen($data),
                                'type' => $part->subtype,
                            ];
                        }
                    }
                }
            }

            return $attachments;
        } catch (Exception $e) {
            error_log("[ImapService] Failed to get attachments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save attachment to storage
     */
    private function saveAttachment(int $emailId, array $attachment): void
    {
        try {
            $storage = '/storage/email_attachments/';
            $filename = md5($attachment['filename'] . time()) . '.' . pathinfo($attachment['filename'], PATHINFO_EXTENSION);
            $path = $storage . $filename;

            file_put_contents($path, $attachment['data']);

            // Insert into database
            $stmt = $this->db->prepare("
                INSERT INTO email_attachments
                (email_id, filename, original_filename, file_path, file_size, mime_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $emailId,
                $filename,
                $attachment['filename'],
                $path,
                $attachment['size'],
                $attachment['type'],
            ]);
        } catch (Exception $e) {
            error_log("[ImapService] Failed to save attachment: " . $e->getMessage());
        }
    }

    /**
     * Log email access for audit trail
     */
    private function logAccess(int $emailId, int $staffId, string $action): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_access_log (email_id, staff_id, action, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $emailId,
                $staffId,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'cli',
                $_SERVER['HTTP_USER_AGENT'] ?? 'imap-sync',
            ]);
        } catch (Exception $e) {
            error_log("[ImapService] Failed to log access: " . $e->getMessage());
        }
    }

    /**
     * Generic error response
     */
    private function error(string $message): array
    {
        error_log("[ImapService] $message");
        return [
            'success' => false,
            'error' => $message,
        ];
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
