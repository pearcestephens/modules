<?php
declare(strict_types=1);

/**
 * Notification Service - Queue-based Email Notification System
 *
 * Processes email queue with:
 * - Priority-based processing (urgent → low)
 * - Retry logic with exponential backoff
 * - Batch processing for non-urgent emails
 * - Error handling and DLQ (dead letter queue)
 * - Rate limiting per SendGrid limits
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author CIS Development Team
 * @created 2025-11-08
 */

namespace CIS\Consignments\Services;

use PDO;
use PDOException;
use CIS\Shared\Services\SendGridService;
use Exception;

class NotificationService
{
    private PDO $pdo;

    /**
     * Maximum retry attempts before moving to DLQ
     */
    private const MAX_RETRIES = 5;

    /**
     * Retry delays (exponential backoff in minutes)
     */
    private const RETRY_DELAYS = [
        1 => 5,      // 1st retry: 5 minutes
        2 => 15,     // 2nd retry: 15 minutes
        3 => 60,     // 3rd retry: 1 hour
        4 => 240,    // 4th retry: 4 hours
        5 => 1440    // 5th retry: 24 hours
    ];

    /**
     * Batch sizes per priority
     */
    private const BATCH_SIZES = [
        1 => 50,    // Urgent: process immediately, 50 at a time
        2 => 100,   // High: process every 5 min, 100 at a time
        3 => 200,   // Normal: process every 30 min, 200 at a time
        4 => 500    // Low: process daily, 500 at a time
    ];

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Factory method using global database helper
     *
     * @return self
     */
    public static function make(): self
    {
        return new self(db());
    }

    // ========================================================================
    // QUEUE PROCESSING
    // ========================================================================

    /**
     * Process email queue
     *
     * @param int|null $priority Process specific priority (null = all urgent/high)
     * @param int $limit Maximum emails to process
     * @return array Processing statistics
     */
    public function processQueue(?int $priority = null, int $limit = 100): array
    {
        $stats = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'retried' => 0,
            'dlq' => 0,
            'start_time' => microtime(true)
        ];

        try {
            // Get pending emails
            $emails = $this->getPendingEmails($priority, $limit);

            $stats['processed'] = count($emails);

            foreach ($emails as $email) {
                // Mark as processing
                $this->updateStatus($email['id'], 'processing');

                try {
                    // Send email
                    $result = $this->sendEmail($email);

                    if ($result['success']) {
                        // Mark as sent
                        $this->updateStatus($email['id'], 'sent');
                        $this->logSuccess($email);
                        $stats['sent']++;
                    } else {
                        // Handle failure
                        $this->handleFailure($email, $result['message']);

                        if ($email['retry_count'] >= self::MAX_RETRIES) {
                            $stats['dlq']++;
                        } else {
                            $stats['retried']++;
                        }
                    }

                } catch (Exception $e) {
                    // Handle exception
                    $this->handleFailure($email, $e->getMessage());
                    $stats['failed']++;
                }
            }

        } catch (Exception $e) {
            error_log("NotificationService::processQueue failed: " . $e->getMessage());
        }

        $stats['duration'] = round(microtime(true) - $stats['start_time'], 3);

        return $stats;
    }

    /**
     * Process urgent emails only (priority 1)
     *
     * @return array Processing statistics
     */
    public function processUrgent(): array
    {
        return $this->processQueue(1, self::BATCH_SIZES[1]);
    }

    /**
     * Process high priority emails (priority 2)
     *
     * @return array Processing statistics
     */
    public function processHigh(): array
    {
        return $this->processQueue(2, self::BATCH_SIZES[2]);
    }

    /**
     * Process normal priority emails (priority 3)
     *
     * @return array Processing statistics
     */
    public function processNormal(): array
    {
        return $this->processQueue(3, self::BATCH_SIZES[3]);
    }

    /**
     * Process low priority emails (priority 4)
     *
     * @return array Processing statistics
     */
    public function processLow(): array
    {
        return $this->processQueue(4, self::BATCH_SIZES[4]);
    }

    /**
     * Retry failed emails that are due for retry
     *
     * @return array Processing statistics
     */
    public function processRetries(): array
    {
        $stats = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'dlq' => 0
        ];

        try {
            // Get emails due for retry
            $stmt = $this->pdo->query("
                SELECT *
                FROM consignment_notification_queue
                WHERE status = 'failed'
                  AND retry_count < " . self::MAX_RETRIES . "
                  AND next_retry_at <= NOW()
                ORDER BY priority ASC, next_retry_at ASC
                LIMIT 50
            ");

            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats['processed'] = count($emails);

            foreach ($emails as $email) {
                // Mark as processing
                $this->updateStatus($email['id'], 'processing');

                try {
                    $result = $this->sendEmail($email);

                    if ($result['success']) {
                        $this->updateStatus($email['id'], 'sent');
                        $this->logSuccess($email);
                        $stats['sent']++;
                    } else {
                        $this->handleFailure($email, $result['message']);

                        if ($email['retry_count'] >= self::MAX_RETRIES) {
                            $stats['dlq']++;
                        } else {
                            $stats['failed']++;
                        }
                    }
                } catch (Exception $e) {
                    $this->handleFailure($email, $e->getMessage());
                    $stats['failed']++;
                }
            }

        } catch (Exception $e) {
            error_log("NotificationService::processRetries failed: " . $e->getMessage());
        }

        return $stats;
    }

    // ========================================================================
    // QUEUE MANAGEMENT
    // ========================================================================

    /**
     * Get pending emails from queue
     *
     * @param int|null $priority Specific priority level
     * @param int $limit Maximum emails to fetch
     * @return array Email records
     */
    private function getPendingEmails(?int $priority, int $limit): array
    {
        $sql = "
            SELECT *
            FROM consignment_notification_queue
            WHERE status = 'pending'
        ";

        if ($priority !== null) {
            $sql .= " AND priority = " . (int)$priority;
        } else {
            // Default: urgent and high priority
            $sql .= " AND priority IN (1, 2)";
        }

        $sql .= "
            ORDER BY priority ASC, created_at ASC
            LIMIT " . (int)$limit;

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update email status
     *
     * @param int $id Queue ID
     * @param string $status New status
     */
    private function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE consignment_notification_queue
            SET status = ?,
                processed_at = CASE WHEN ? = 'sent' THEN NOW() ELSE processed_at END
            WHERE id = ?
        ");

        $stmt->execute([$status, $status, $id]);
    }

    /**
     * Handle email send failure
     *
     * @param array $email Email record
     * @param string $errorMessage Error message
     */
    private function handleFailure(array $email, string $errorMessage): void
    {
        $retryCount = (int)$email['retry_count'] + 1;

        if ($retryCount >= self::MAX_RETRIES) {
            // Move to dead letter queue
            $this->moveToDeadLetterQueue($email, $errorMessage);
        } else {
            // Schedule retry
            $delayMinutes = self::RETRY_DELAYS[$retryCount] ?? 1440;

            $stmt = $this->pdo->prepare("
                UPDATE consignment_notification_queue
                SET status = 'failed',
                    retry_count = ?,
                    last_error = ?,
                    next_retry_at = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                WHERE id = ?
            ");

            $stmt->execute([
                $retryCount,
                $errorMessage,
                $delayMinutes,
                $email['id']
            ]);
        }

        // Log failure
        $this->logFailure($email, $errorMessage, $retryCount);
    }

    /**
     * Move email to dead letter queue (max retries exceeded)
     *
     * @param array $email Email record
     * @param string $finalError Final error message
     */
    private function moveToDeadLetterQueue(array $email, string $finalError): void
    {
        // Update status to cancelled (represents DLQ)
        $stmt = $this->pdo->prepare("
            UPDATE consignment_notification_queue
            SET status = 'cancelled',
                last_error = ?,
                processed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            "MAX_RETRIES_EXCEEDED: " . $finalError,
            $email['id']
        ]);

        // Log to audit
        error_log("Email moved to DLQ - ID: {$email['id']}, Error: {$finalError}");
    }

    // ========================================================================
    // EMAIL SENDING
    // ========================================================================

    /**
     * Send email via SendGrid
     *
     * @param array $email Email record
     * @return array ['success' => bool, 'message' => string]
     */
    private function sendEmail(array $email): array
    {
        try {
            $result = SendGridService::send(
                $email['recipient_email'],
                $email['subject'],
                $email['html_body'],
                $email['text_body']
            );

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // ========================================================================
    // AUDIT LOGGING
    // ========================================================================

    /**
     * Log successful email send
     *
     * @param array $email Email record
     */
    private function logSuccess(array $email): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO consignment_email_log (
                    queue_id, consignment_id, template_key,
                    recipient_email, recipient_name, subject_line,
                    email_type, priority, sent_by,
                    status, retry_count, sent_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent', ?, NOW()
                )
            ");

            $stmt->execute([
                $email['id'],
                $email['consignment_id'],
                $email['template_key'],
                $email['recipient_email'],
                $email['recipient_name'],
                $email['subject'],
                $email['email_type'],
                $email['priority'],
                $email['sent_by'],
                $email['retry_count']
            ]);

        } catch (PDOException $e) {
            error_log("Failed to log email success: " . $e->getMessage());
        }
    }

    /**
     * Log email failure
     *
     * @param array $email Email record
     * @param string $errorMessage Error message
     * @param int $retryCount Current retry count
     */
    private function logFailure(array $email, string $errorMessage, int $retryCount): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO consignment_email_log (
                    queue_id, consignment_id, template_key,
                    recipient_email, recipient_name, subject_line,
                    email_type, priority, sent_by,
                    status, retry_count, error_message
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, 'failed', ?, ?
                )
            ");

            $stmt->execute([
                $email['id'],
                $email['consignment_id'],
                $email['template_key'],
                $email['recipient_email'],
                $email['recipient_name'],
                $email['subject'],
                $email['email_type'],
                $email['priority'],
                $email['sent_by'],
                $retryCount,
                $errorMessage
            ]);

        } catch (PDOException $e) {
            error_log("Failed to log email failure: " . $e->getMessage());
        }
    }

    // ========================================================================
    // STATISTICS & MONITORING
    // ========================================================================

    /**
     * Get queue statistics
     *
     * @return array Queue statistics
     */
    public function getQueueStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                status,
                priority,
                COUNT(*) as count,
                MIN(created_at) as oldest,
                MAX(created_at) as newest
            FROM consignment_notification_queue
            WHERE status IN ('pending', 'processing', 'failed')
            GROUP BY status, priority
            ORDER BY priority ASC, status ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get dead letter queue items
     *
     * @param int $limit Maximum items to return
     * @return array DLQ items
     */
    public function getDeadLetterQueue(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM consignment_notification_queue
            WHERE status = 'cancelled'
            ORDER BY processed_at DESC
            LIMIT ?
        ");

        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Manually retry a DLQ item
     *
     * @param int $id Queue ID
     * @return bool Success status
     */
    public function retryFromDLQ(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE consignment_notification_queue
                SET status = 'pending',
                    retry_count = 0,
                    last_error = NULL,
                    next_retry_at = NULL
                WHERE id = ? AND status = 'cancelled'
            ");

            return $stmt->execute([$id]);

        } catch (PDOException $e) {
            error_log("Failed to retry from DLQ: " . $e->getMessage());
            return false;
        }
    }
}
