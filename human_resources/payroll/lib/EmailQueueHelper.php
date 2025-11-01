<?php
/**
 * Filename: EmailQueueHelper.php
 * Purpose : Connect to VapeShed DB and insert email into queue table
 * Author  : GitHub Copilot
 * Last Modified: 2025-10-31
 * Dependencies: connectToVapeShedSQL() from mysql.php
 */
declare(strict_types=1);

/**
 * Enqueue email to VapeShed database email_queue table
 *
 * @param string $recipient Email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML or plain text)
 * @param array $attachments Array of attachment arrays with keys: filename, content (base64), mime
 * @param string $fromAddress Sender email (default: noreply@vapeshed.co.nz)
 * @param int $priority Priority level (1=immediate, 2=batched, 3=digest)
 *
 * @return int|false Insert ID on success, false on failure
 */
function queue_enqueue_email(
    string $recipient,
    string $subject,
    string $body,
    array $attachments = [],
    string $fromAddress = 'payroll@vapeshed.co.nz',
    int $priority = 2
) {
    try {
        // Connect to VapeShed DB using our module's connection function
        require_once __DIR__ . '/VapeShedDb.php';
        $vapeShedCon = \HumanResources\Payroll\Lib\getVapeShedConnection();

        if (!$vapeShedCon) {
            error_log('EmailQueueHelper: Failed to connect to VapeShed DB');
            return false;
        }

        // Prepare attachment data (store as JSON)
        $attachmentJson = !empty($attachments) ? json_encode($attachments) : null;

        // Build insert query matching VapeShed email_queue schema
        $sql = "INSERT INTO email_queue SET
            email_from = ?,
            email_to = ?,
            subject = ?,
            html_body = ?,
            text_body = ?,
            attachments = ?,
            priority = ?,
            status = 'pending',
            created_at = NOW()";

        $stmt = $vapeShedCon->prepare($sql);

        if (!$stmt) {
            error_log('EmailQueueHelper: Failed to prepare statement: ' . $vapeShedCon->error);
            return false;
        }

        // Bind parameters
        $stmt->bind_param(
            'ssssssi',
            $fromAddress,
            $recipient,
            $subject,
            $body,
            $body, // text_body (duplicate for now)
            $attachmentJson,
            $priority
        );

        // Execute
        if (!$stmt->execute()) {
            error_log('EmailQueueHelper: Failed to execute: ' . $stmt->error);
            $stmt->close();
            return false;
        }

        $insertId = $stmt->insert_id;
        $stmt->close();

        return $insertId;

    } catch (Exception $e) {
        error_log('EmailQueueHelper exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get email queue stats (for testing/monitoring)
 *
 * @return array Statistics from email_queue table
 */
function queue_get_stats(): array
{
    try {
        // Connect to VapeShed DB using our module's connection function
        require_once __DIR__ . '/VapeShedDb.php';
        $vapeShedCon = \HumanResources\Payroll\Lib\getVapeShedConnection();

        if (!$vapeShedCon) {
            return ['error' => 'Failed to connect'];
        }

        $sql = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM email_queue";

        $result = $vapeShedCon->query($sql);

        if (!$result) {
            return ['error' => $vapeShedCon->error];
        }

        $stats = $result->fetch_assoc();
        $result->close();

        return $stats ?: [];

    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}
