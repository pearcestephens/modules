<?php

/**
 * Fraud Detection Alert Manager
 *
 * Handles sending alerts via multiple channels:
 * - Email
 * - Slack
 * - SMS (future)
 * - Webhooks (future)
 *
 * Includes throttling to prevent alert spam
 */

namespace FraudDetection;

use PDO;
use Exception;

class AlertManager
{
    private PDO $pdo;
    private ConfigManager $config;
    private array $alertLog = [];

    public function __construct(PDO $pdo, ConfigManager $config = null)
    {
        $this->pdo = $pdo;
        $this->config = $config ?? ConfigManager::getInstance();
    }

    /**
     * Send fraud detection alert for staff member
     *
     * @param int $staffId Staff member ID
     * @param array $analysisResults Complete analysis results
     * @return array Alert status for each channel
     */
    public function sendFraudAlert(int $staffId, array $analysisResults): array
    {
        $results = [];

        try {
            // Check if alerts are enabled
            if (!$this->config->get('alerts.enabled', true)) {
                return ['status' => 'disabled', 'message' => 'Alerts are disabled'];
            }

            // Check if this risk level warrants an alert
            if (!$this->config->shouldAlert(
                $analysisResults['risk_level'],
                $analysisResults['risk_score']
            )) {
                return ['status' => 'skipped', 'message' => 'Risk level below alert threshold'];
            }

            // Check throttling
            if ($this->config->shouldThrottleAlert($staffId, $this->pdo)) {
                $this->logAlert($staffId, 'throttled', 'low', 0, 'system', [
                    'reason' => 'Alert throttled - too many recent alerts'
                ], 'sent');

                return ['status' => 'throttled', 'message' => 'Alert throttled to prevent spam'];
            }

            // Get staff details
            $staff = $this->getStaffDetails($staffId);

            // Send via enabled channels
            if ($this->config->get('alerts.email_alerts.enabled', true)) {
                $results['email'] = $this->sendEmailAlert($staff, $analysisResults);
            }

            if ($this->config->get('alerts.slack_alerts.enabled', false)) {
                $results['slack'] = $this->sendSlackAlert($staff, $analysisResults);
            }

            if ($this->config->get('alerts.sms_alerts.enabled', false)) {
                $results['sms'] = $this->sendSMSAlert($staff, $analysisResults);
            }

            return $results;

        } catch (Exception $e) {
            error_log("Failed to send fraud alert for staff {$staffId}: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Send email alert
     */
    private function sendEmailAlert(array $staff, array $analysis): array
    {
        try {
            $recipients = $this->config->get('alerts.email_alerts.recipients', []);
            $from = $this->config->get('alerts.email_alerts.from_address', 'fraud-detection@company.com');
            $subjectPrefix = $this->config->get('alerts.email_alerts.subject_prefix', '[FRAUD ALERT]');

            $subject = sprintf(
                "%s %s Risk - %s (Score: %d/100)",
                $subjectPrefix,
                strtoupper($analysis['risk_level']),
                $staff['name'],
                $analysis['risk_score']
            );

            $body = $this->generateEmailBody($staff, $analysis);

            $headers = [
                "From: {$from}",
                "Reply-To: {$from}",
                "Content-Type: text/html; charset=UTF-8",
                "X-Priority: 1", // High priority for critical alerts
            ];

            $sent = [];
            foreach ($recipients as $recipient) {
                $success = mail($recipient, $subject, $body, implode("\r\n", $headers));

                $this->logAlert(
                    $staff['id'],
                    'email',
                    $analysis['risk_level'],
                    $analysis['risk_score'],
                    $recipient,
                    ['subject' => $subject, 'to' => $recipient],
                    $success ? 'sent' : 'failed'
                );

                $sent[] = [
                    'recipient' => $recipient,
                    'success' => $success
                ];
            }

            return [
                'status' => 'sent',
                'recipients' => $sent,
                'count' => count($recipients)
            ];

        } catch (Exception $e) {
            error_log("Email alert failed: " . $e->getMessage());
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate HTML email body
     */
    private function generateEmailBody(array $staff, array $analysis): string
    {
        $riskColor = match($analysis['risk_level']) {
            'critical' => '#DC3545',
            'high' => '#FD7E14',
            'medium' => '#FFC107',
            default => '#28A745'
        };

        $criticalAlerts = array_slice($analysis['critical_alerts'] ?? [], 0, 5);
        $topIndicators = array_slice($analysis['fraud_indicators'] ?? [], 0, 10);

        $criticalHTML = '';
        foreach ($criticalAlerts as $alert) {
            $criticalHTML .= "<li><strong>{$alert['type']}:</strong> {$alert['description']}</li>";
        }

        $indicatorsHTML = '';
        foreach ($topIndicators as $indicator) {
            $severityPercent = round($indicator['severity'] * 100);
            $indicatorsHTML .= "<tr>";
            $indicatorsHTML .= "<td>{$indicator['category']}</td>";
            $indicatorsHTML .= "<td>{$indicator['type']}</td>";
            $indicatorsHTML .= "<td>{$indicator['description']}</td>";
            $indicatorsHTML .= "<td>{$severityPercent}%</td>";
            $indicatorsHTML .= "</tr>";
        }

        $sourcesHTML = implode(', ', $analysis['sources_analyzed'] ?? []);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: {$riskColor}; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .risk-score { font-size: 48px; font-weight: bold; }
        .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid {$riskColor}; }
        .section h3 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #343a40; color: white; }
        .footer { padding: 20px; background: #f8f9fa; text-align: center; font-size: 12px; color: #666; }
        .button { display: inline-block; padding: 10px 20px; background: {$riskColor}; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚨 FRAUD DETECTION ALERT</h1>
        <div class="risk-score">{$analysis['risk_score']}/100</div>
        <p>Risk Level: <strong>{$analysis['risk_level']}</strong></p>
    </div>

    <div class="content">
        <div class="section">
            <h3>Staff Member</h3>
            <p><strong>Name:</strong> {$staff['name']}</p>
            <p><strong>ID:</strong> {$staff['id']}</p>
            <p><strong>Outlet:</strong> {$staff['outlet']}</p>
            <p><strong>Analysis Date:</strong> {$analysis['analysis_timestamp']}</p>
        </div>

        <div class="section">
            <h3>Critical Alerts ({$analysis['critical_alert_count']})</h3>
            <ul>{$criticalHTML}</ul>
        </div>

        <div class="section">
            <h3>Fraud Indicators (Top 10 of {$analysis['indicator_count']})</h3>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Severity</th>
                    </tr>
                </thead>
                <tbody>
                    {$indicatorsHTML}
                </tbody>
            </table>
        </div>

        <div class="section">
            <h3>Data Sources Analyzed</h3>
            <p>{$sourcesHTML}</p>
        </div>

        <div class="section">
            <h3>Recommended Actions</h3>
            <ol>
                <li>Review fraud indicators immediately</li>
                <li>Check camera footage for suspicious activity</li>
                <li>Review recent transactions and adjustments</li>
                <li>Interview staff member if warranted</li>
                <li>Mark incidents as investigated in system</li>
            </ol>
        </div>

        <p style="text-align: center; margin: 30px 0;">
            <a href="https://staff.vapeshed.co.nz/fraud-detection/staff/{$staff['id']}" class="button">
                View Full Analysis
            </a>
        </p>
    </div>

    <div class="footer">
        <p>This is an automated alert from the Fraud Detection System</p>
        <p>Analysis ID: {$analysis['staff_id']}-{$analysis['analysis_timestamp']}</p>
        <p>Do not reply to this email</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Send Slack alert
     */
    private function sendSlackAlert(array $staff, array $analysis): array
    {
        try {
            $webhookUrl = $this->config->get('alerts.slack_alerts.webhook_url');
            $channel = $this->config->get('alerts.slack_alerts.channel', '#fraud-alerts');

            if (empty($webhookUrl)) {
                return ['status' => 'skipped', 'message' => 'Slack webhook URL not configured'];
            }

            $color = match($analysis['risk_level']) {
                'critical' => 'danger',
                'high' => 'warning',
                'medium' => '#FFC107',
                default => 'good'
            };

            $criticalAlerts = implode("\n", array_map(
                fn($a) => "• {$a['description']}",
                array_slice($analysis['critical_alerts'] ?? [], 0, 5)
            ));

            $payload = [
                'channel' => $channel,
                'username' => 'Fraud Detection Bot',
                'icon_emoji' => ':rotating_light:',
                'attachments' => [
                    [
                        'color' => $color,
                        'title' => "🚨 {$analysis['risk_level']} Risk Alert - {$staff['name']}",
                        'text' => "Risk Score: *{$analysis['risk_score']}/100*",
                        'fields' => [
                            [
                                'title' => 'Staff Member',
                                'value' => "{$staff['name']} (ID: {$staff['id']})",
                                'short' => true
                            ],
                            [
                                'title' => 'Fraud Indicators',
                                'value' => $analysis['indicator_count'],
                                'short' => true
                            ],
                            [
                                'title' => 'Critical Alerts',
                                'value' => $criticalAlerts,
                                'short' => false
                            ],
                        ],
                        'footer' => 'Fraud Detection System',
                        'ts' => time()
                    ]
                ]
            ];

            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $success = $httpCode === 200;

            $this->logAlert(
                $staff['id'],
                'slack',
                $analysis['risk_level'],
                $analysis['risk_score'],
                $channel,
                ['webhook_response' => $response],
                $success ? 'sent' : 'failed'
            );

            return [
                'status' => $success ? 'sent' : 'failed',
                'http_code' => $httpCode,
                'response' => $response
            ];

        } catch (Exception $e) {
            error_log("Slack alert failed: " . $e->getMessage());
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Send SMS alert (placeholder for future implementation)
     */
    private function sendSMSAlert(array $staff, array $analysis): array
    {
        // TODO: Implement SMS via Twilio or similar
        return ['status' => 'not_implemented', 'message' => 'SMS alerts not yet implemented'];
    }

    /**
     * Get staff details
     */
    private function getStaffDetails(int $staffId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    s.id,
                    s.name,
                    s.email,
                    o.name as outlet
                FROM staff_accounts s
                LEFT JOIN outlets o ON s.default_outlet_id = o.id
                WHERE s.id = ?
            ");
            $stmt->execute([$staffId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'id' => $staffId,
                'name' => 'Unknown Staff',
                'email' => '',
                'outlet' => 'Unknown'
            ];
        } catch (Exception $e) {
            return [
                'id' => $staffId,
                'name' => 'Staff ID ' . $staffId,
                'email' => '',
                'outlet' => 'Unknown'
            ];
        }
    }

    /**
     * Log alert sent to database
     */
    private function logAlert(
        int $staffId,
        string $alertType,
        string $riskLevel,
        float $riskScore,
        string $recipient,
        array $alertData,
        string $deliveryStatus,
        ?string $failureReason = null
    ): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO fraud_alert_log
                (staff_id, alert_type, risk_level, risk_score, recipient,
                 alert_data, delivery_status, failure_reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $staffId,
                $alertType,
                $riskLevel,
                $riskScore,
                $recipient,
                json_encode($alertData),
                $deliveryStatus,
                $failureReason
            ]);

            // Update config manager's alert log
            $this->config->logAlertSent($staffId, $alertType, $this->pdo);

        } catch (Exception $e) {
            error_log("Failed to log alert: " . $e->getMessage());
        }
    }
}
