<?php
/**
 * Smart Cron Alert Manager
 *
 * Handles alert creation, notification, and management.
 *
 * @version 2.0
 */

class SmartCronAlert
{
    private mysqli $db;
    private ?SmartCronLogger $logger;

    public function __construct(mysqli $db, ?SmartCronLogger $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Create a new alert
     */
    public function createAlert(array $data): ?int
    {
        $stmt = $this->db->prepare("
            INSERT INTO smart_cron_alerts (
                alert_type, alert_severity, task_id, task_name, execution_id,
                alert_title, alert_message, alert_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            if ($this->logger) {
                $this->logger->error('Failed to prepare alert statement', [
                    'error' => $this->db->error
                ]);
            }
            return null;
        }

        $alertData = !empty($data['data']) ? json_encode($data['data']) : null;

        $stmt->bind_param(
            'ssisssss',
            $data['type'],
            $data['severity'],
            $data['task_id'],
            $data['task_name'],
            $data['execution_id'],
            $data['title'],
            $data['message'],
            $alertData
        );

        $stmt->execute();
        $alertId = $stmt->insert_id;
        $stmt->close();

        if ($this->logger) {
            $this->logger->warning('Alert created', [
                'alert_id' => $alertId,
                'type' => $data['type'],
                'severity' => $data['severity'],
                'title' => $data['title']
            ]);
        }

        return $alertId;
    }

    /**
     * Send email notification
     */
    public function sendEmailNotification(string $email, int $alertId = null): bool
    {
        // Get alert details if ID provided
        if ($alertId) {
            $stmt = $this->db->prepare("SELECT alert_title, alert_message, alert_severity FROM smart_cron_alerts WHERE id = ?");
            $stmt->bind_param('i', $alertId);
            $stmt->execute();
            $alert = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$alert) {
                return false;
            }
        } else {
            $alert = [
                'alert_title' => 'Smart Cron System Alert',
                'alert_message' => 'An alert was triggered',
                'alert_severity' => 'warning'
            ];
        }

        // Build email
        $subject = sprintf('[Smart Cron][%s] %s', strtoupper($alert['alert_severity']), $alert['alert_title']);
        $message = $this->buildEmailBody($alert);
        $headers = [
            'From: Smart Cron <noreply@ecigdis.co.nz>',
            'Content-Type: text/html; charset=UTF-8',
            'X-Priority: ' . ($alert['alert_severity'] === 'critical' ? '1' : '3')
        ];

        // Send email
        $sent = @mail($email, $subject, $message, implode("\r\n", $headers));

        // Log notification attempt
        if ($alertId) {
            $response = $sent ? 'sent' : 'failed';
            $stmt = $this->db->prepare("
                UPDATE smart_cron_alerts
                SET notification_sent = ?, notification_sent_at = NOW(),
                    notification_method = 'email', notification_response = ?
                WHERE id = ?
            ");
            $stmt->bind_param('isi', $sent, $response, $alertId);
            $stmt->execute();
            $stmt->close();
        }

        return $sent;
    }

    /**
     * Build email body HTML
     */
    private function buildEmailBody(array $alert): string
    {
        $severityColor = [
            'critical' => '#dc3545',
            'error' => '#fd7e14',
            'warning' => '#ffc107',
            'info' => '#17a2b8'
        ][$alert['alert_severity']] ?? '#6c757d';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {$severityColor}; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
        .footer { background: #e9ecef; padding: 15px; border-radius: 0 0 5px 5px; font-size: 12px; color: #6c757d; }
        .badge { display: inline-block; padding: 5px 10px; border-radius: 3px; color: white; background: {$severityColor}; font-size: 12px; font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin: 0;">⚠️ Smart Cron Alert</h2>
        </div>
        <div class="content">
            <p><span class="badge">{$alert['alert_severity']}</span></p>
            <h3>{$alert['alert_title']}</h3>
            <p>{$alert['alert_message']}</p>
            <p><small>Generated: " . date('Y-m-d H:i:s') . "</small></p>
        </div>
        <div class="footer">
            <p>This is an automated alert from Smart Cron System.<br>
            Please log in to the Smart Cron Dashboard to view details and acknowledge this alert.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Acknowledge alert
     */
    public function acknowledgeAlert(int $alertId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE smart_cron_alerts
            SET acknowledged = 1, acknowledged_at = NOW(), acknowledged_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $userId, $alertId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Resolve alert
     */
    public function resolveAlert(int $alertId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE smart_cron_alerts
            SET resolved = 1, resolved_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('i', $alertId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts(int $limit = 50): array
    {
        $sql = "
            SELECT * FROM smart_cron_active_alerts
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $alerts = [];
        while ($row = $result->fetch_assoc()) {
            $alerts[] = $row;
        }

        $stmt->close();
        return $alerts;
    }
}
