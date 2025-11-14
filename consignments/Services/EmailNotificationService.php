<?php
declare(strict_types=1);

namespace ConsignmentsModule\Services;

/**
 * Email Notification Service for Consignments Module
 *
 * Handles all email notifications: transfer reviews, supplier notifications, etc.
 * Integrates with CIS mail system and logs all sent emails.
 */
class EmailNotificationService
{
    private \PDO $pdo;
    private array $config;

    public function __construct(\PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'from_email' => getenv('MAIL_FROM') ?: 'noreply@vapeshed.co.nz',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'The Vape Shed Consignments',
            'enabled' => getenv('MAIL_ENABLED') !== 'false',
            'debug' => getenv('APP_DEBUG') === 'true',
        ], $config);
    }

    /**
     * Send outlet weekly report
     *
     * @param int $outletId Outlet ID
     * @param array $report Report data
     * @return bool Success
     */
    public function sendOutletWeeklyReport(int $outletId, array $report): bool
    {
        try {
            // Get outlet details
            $stmt = $this->pdo->prepare("SELECT outlet_name, email FROM vend_outlets WHERE outlet_id = ? LIMIT 1");
            $stmt->execute([$outletId]);
            $outlet = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$outlet || empty($outlet['email'])) {
                $this->log('warning', "Outlet $outletId has no email address", ['outlet_id' => $outletId]);
                return false;
            }

            $subject = sprintf('[Weekly Report] Transfer Activity - %s', $outlet['outlet_name']);
            $body = $this->renderOutletReportTemplate($outlet, $report);

            return $this->send(
                to: $outlet['email'],
                subject: $subject,
                body: $body,
                metadata: [
                    'type' => 'outlet_weekly_report',
                    'outlet_id' => $outletId,
                ]
            );

        } catch (\Exception $e) {
            $this->log('error', 'Failed to send outlet weekly report', [
                'outlet_id' => $outletId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send supplier notification
     *
     * @param int $supplierId Supplier ID
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $attachments Optional attachments
     * @return bool Success
     */
    public function sendSupplierNotification(
        int $supplierId,
        string $subject,
        string $body,
        array $attachments = []
    ): bool {
        try {
            // Get supplier details
            $stmt = $this->pdo->prepare("SELECT supplier_name, email FROM suppliers WHERE id = ? LIMIT 1");
            $stmt->execute([$supplierId]);
            $supplier = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$supplier || empty($supplier['email'])) {
                throw new \RuntimeException("Supplier $supplierId has no email address");
            }

            // Log to supplier_email_log
            $stmt = $this->pdo->prepare("
                INSERT INTO supplier_email_log (
                    supplier_id,
                    to_email,
                    subject,
                    body,
                    has_attachments,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 'queued', NOW())
            ");

            $stmt->execute([
                $supplierId,
                $supplier['email'],
                $subject,
                $body,
                !empty($attachments) ? 1 : 0
            ]);

            $logId = (int) $this->pdo->lastInsertId();

            // Send email
            $sent = $this->send(
                to: $supplier['email'],
                subject: $subject,
                body: $body,
                metadata: [
                    'type' => 'supplier_notification',
                    'supplier_id' => $supplierId,
                    'log_id' => $logId
                ]
            );

            // Update log status
            if ($sent) {
                $stmt = $this->pdo->prepare("UPDATE supplier_email_log SET status = 'sent', sent_at = NOW() WHERE id = ?");
                $stmt->execute([$logId]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE supplier_email_log SET status = 'failed', error_message = 'Mail send failed' WHERE id = ?");
                $stmt->execute([$logId]);
            }

            return $sent;

        } catch (\Exception $e) {
            $this->log('error', 'Failed to send supplier notification', [
                'supplier_id' => $supplierId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send transfer notification
     *
     * @param int $transferId Transfer ID
     * @param string $type Notification type (created, sent, received, etc.)
     * @return bool Success
     */
    public function sendTransferNotification(int $transferId, string $type): bool
    {
        try {
            // Get transfer details
            $stmt = $this->pdo->prepare("
                SELECT c.*,
                       o1.outlet_name as origin_name, o1.email as origin_email,
                       o2.outlet_name as dest_name, o2.email as dest_email
                FROM vend_consignments c
                LEFT JOIN vend_outlets o1 ON c.outlet_id = o1.outlet_id
                LEFT JOIN vend_outlets o2 ON c.destination_outlet_id = o2.outlet_id
                WHERE c.id = ?
                LIMIT 1
            ");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$transfer) {
                throw new \RuntimeException("Transfer $transferId not found");
            }

            // Determine recipient based on type
            $to = match($type) {
                'created', 'sent' => $transfer['dest_email'],
                'received' => $transfer['origin_email'],
                default => $transfer['dest_email']
            };

            if (empty($to)) {
                $this->log('warning', "No email address for transfer notification", [
                    'transfer_id' => $transferId,
                    'type' => $type
                ]);
                return false;
            }

            $subject = sprintf('[Transfer #%d] %s', $transferId, ucfirst($type));
            $body = $this->renderTransferNotificationTemplate($transfer, $type);

            return $this->send(
                to: $to,
                subject: $subject,
                body: $body,
                metadata: [
                    'type' => 'transfer_notification',
                    'transfer_id' => $transferId,
                    'notification_type' => $type
                ]
            );

        } catch (\Exception $e) {
            $this->log('error', 'Failed to send transfer notification', [
                'transfer_id' => $transferId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Core send method - integrates with CIS mail system
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $metadata Optional metadata for logging
     * @return bool Success
     */
    private function send(string $to, string $subject, string $body, array $metadata = []): bool
    {
        // Check if email is enabled
        if (!$this->config['enabled']) {
            $this->log('info', 'Email sending disabled - would have sent', array_merge([
                'to' => $to,
                'subject' => $subject
            ], $metadata));
            return true; // Return true so workflow continues
        }

        // Debug mode - just log
        if ($this->config['debug']) {
            $this->log('debug', 'Email sent (debug mode)', array_merge([
                'to' => $to,
                'subject' => $subject,
                'body_length' => strlen($body)
            ], $metadata));
            return true;
        }

        try {
            // Use PHP mail() function
            $headers = [
                'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>',
                'Reply-To: ' . $this->config['from_email'],
                'X-Mailer: PHP/' . phpversion(),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8'
            ];

            $success = mail(
                $to,
                $subject,
                $body,
                implode("\r\n", $headers)
            );

            if ($success) {
                $this->log('info', 'Email sent successfully', array_merge([
                    'to' => $to,
                    'subject' => $subject
                ], $metadata));
            } else {
                $this->log('error', 'mail() returned false', array_merge([
                    'to' => $to,
                    'subject' => $subject
                ], $metadata));
            }

            return $success;

        } catch (\Exception $e) {
            $this->log('error', 'Exception during mail send', array_merge([
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ], $metadata));
            return false;
        }
    }

    /**
     * Render outlet report template
     */
    private function renderOutletReportTemplate(array $outlet, array $report): string
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .metric { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Weekly Transfer Report</h2>
            <p>{$outlet['outlet_name']}</p>
        </div>
        <div class="content">
            <p>Here's your weekly transfer activity summary:</p>

            <div class="metric">
                <strong>Total Transfers:</strong> {$report['total_transfers']}<br>
                <strong>Items Transferred:</strong> {$report['total_items']}<br>
                <strong>Total Value:</strong> {$report['total_value']}
            </div>

            <div class="metric">
                <strong>Incoming:</strong> {$report['incoming_count']} transfers<br>
                <strong>Outgoing:</strong> {$report['outgoing_count']} transfers
            </div>

            <p><a href="https://staff.vapeshed.co.nz/modules/consignments/">View Full Report</a></p>
        </div>
        <div class="footer">
            <p>The Vape Shed - Consignment Management System</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Render transfer notification template
     */
    private function renderTransferNotificationTemplate(array $transfer, string $type): string
    {
        $message = match($type) {
            'created' => 'A new transfer has been created and is ready for review.',
            'sent' => 'A transfer has been dispatched and is on its way to you.',
            'received' => 'Transfer has been received and processed.',
            default => 'Transfer status has been updated.'
        };

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .detail { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Transfer #{$transfer['id']} - {$type}</h2>
        </div>
        <div class="content">
            <p>{$message}</p>

            <div class="detail">
                <strong>From:</strong> {$transfer['origin_name']}<br>
                <strong>To:</strong> {$transfer['dest_name']}<br>
                <strong>Status:</strong> {$transfer['status']}<br>
                <strong>Reference:</strong> {$transfer['reference_code']}
            </div>

            <p><a href="https://staff.vapeshed.co.nz/modules/consignments/?route=stock-transfers&action=view&id={$transfer['id']}">View Transfer</a></p>
        </div>
        <div class="footer">
            <p>The Vape Shed - Consignment Management System</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Log message
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $logMessage = sprintf(
            '[%s] [EmailNotificationService] [%s] %s %s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        error_log($logMessage);

        // Also log to file if logger service available
        if (isset($GLOBALS['logger'])) {
            $GLOBALS['logger']->log($level, $message, $context);
        }
    }
}
