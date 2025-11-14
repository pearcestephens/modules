<?php
/**
 * Notification Service
 *
 * Handles Burst SMS and email notifications for order updates and age verification.
 * Integrates with Burst SMS API and existing vapeshedSendEmail function.
 *
 * @package CIS\Modules\EcommerceOps\Services
 */

namespace CIS\Modules\EcommerceOps;

class NotificationService {

    private $db;
    private $burstApiKey;
    private $burstSender;
    private $emailFrom;

    /**
     * Notification templates
     */
    private $templates = [
        'order.created' => [
            'sms' => "Hi {customer_name}, thanks for your order #{order_id}! We'll send you updates as it's processed. - {sender}",
            'email_subject' => 'Order Confirmation - Order #{order_id}',
            'email_body' => 'Thank you for your order! Your order #{order_id} has been received and is being processed.'
        ],
        'order.dispatched' => [
            'sms' => "Your order #{order_id} has been dispatched via {courier}. Tracking: {tracking_number}. - {sender}",
            'email_subject' => 'Your Order Has Been Dispatched - #{order_id}',
            'email_body' => 'Great news! Your order #{order_id} has been dispatched and is on its way. Tracking number: {tracking_number}'
        ],
        'order.out_for_delivery' => [
            'sms' => "Your order #{order_id} is out for delivery today! Please be available to receive it. - {sender}",
            'email_subject' => 'Your Order Is Out For Delivery - #{order_id}',
            'email_body' => 'Your order #{order_id} is out for delivery and should arrive today.'
        ],
        'order.ready_for_pickup' => [
            'sms' => "Your order #{order_id} is ready for pickup at {outlet_name}. Bring ID. - {sender}",
            'email_subject' => 'Your Order Is Ready For Pickup - #{order_id}',
            'email_body' => 'Your order #{order_id} is ready for pickup at {outlet_name}. Please bring valid ID.'
        ],
        'age_verification.required' => [
            'sms' => "Hi {customer_name}, we need to verify your age for order #{order_id}. Please upload ID at: {verification_link} - {sender}",
            'email_subject' => 'Age Verification Required - Order #{order_id}',
            'email_body' => 'Before we can dispatch your order #{order_id}, we need to verify your age. Please upload a photo of your passport or driver license at: {verification_link}'
        ],
        'age_verification.approved' => [
            'sms' => "Age verification approved! Your order #{order_id} is now being processed. - {sender}",
            'email_subject' => 'Age Verification Approved - Order #{order_id}',
            'email_body' => 'Your age verification has been approved and your order #{order_id} is now being processed for dispatch.'
        ],
        'age_verification.rejected' => [
            'sms' => "Unfortunately we cannot verify your age for order #{order_id}. A full refund has been processed. - {sender}",
            'email_subject' => 'Age Verification Failed - Order #{order_id}',
            'email_body' => 'Unfortunately we cannot verify your age for order #{order_id}. Your order has been cancelled and a full refund has been processed.'
        ],
        'order.cancelled' => [
            'sms' => "Your order #{order_id} has been cancelled. Refund will be processed within 3-5 business days. - {sender}",
            'email_subject' => 'Order Cancelled - #{order_id}',
            'email_body' => 'Your order #{order_id} has been cancelled. A full refund will be processed within 3-5 business days.'
        ]
    ];

    /**
     * Constructor
     */
    public function __construct() {
        global $conn;
        $this->db = $conn;
        $this->burstApiKey = ecomm_env('BURST_SMS_API_KEY', '');
        $this->burstSender = ecomm_env('BURST_SMS_SENDER', 'VapeShed');
        $this->emailFrom = ecomm_env('EMAIL_FROM', 'orders@vapeshed.co.nz');
    }

    /**
     * Send SMS via Burst SMS API
     *
     * @param string $to Phone number
     * @param string $message SMS text
     * @return bool
     */
    private function sendSMS(string $to, string $message): bool {
        if (empty($this->burstApiKey)) {
            ecomm_log_error("Burst SMS API key not configured");
            return false;
        }

        // Clean phone number (remove spaces, dashes, etc)
        $to = preg_replace('/[^0-9+]/', '', $to);

        // Ensure NZ format (+64...)
        if (strpos($to, '0') === 0) {
            $to = '+64' . substr($to, 1);
        } elseif (strpos($to, '64') === 0) {
            $to = '+' . $to;
        } elseif (strpos($to, '+64') !== 0) {
            $to = '+64' . $to;
        }

        // Burst SMS API endpoint
        $url = 'https://api.transmitsms.com/send-sms.json';

        $data = [
            'message' => $message,
            'to' => $to,
            'from' => $this->burstSender
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($this->burstApiKey),
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            ecomm_log_error("SMS sent successfully", [
                'to' => $to,
                'message_length' => strlen($message)
            ]);

            // Log to database
            $this->logNotification('sms', $to, $message, 'sent');

            return true;
        } else {
            ecomm_log_error("Failed to send SMS", [
                'to' => $to,
                'http_code' => $httpCode,
                'response' => $response
            ]);

            // Log failure
            $this->logNotification('sms', $to, $message, 'failed', $response);

            return false;
        }
    }

    /**
     * Send email via existing vapeshedSendEmail function
     *
     * @param string $to Email address
     * @param string $subject Email subject
     * @param string $body Email body
     * @return bool
     */
    private function sendEmail(string $to, string $subject, string $body): bool {
        // Use existing CIS email function
        if (function_exists('vapeshedSendEmail')) {
            $result = vapeshedSendEmail($to, $subject, $body, $this->emailFrom);

            // Log to database
            $this->logNotification('email', $to, $subject, $result ? 'sent' : 'failed');

            return $result;
        }

        // Fallback to PHP mail() if function not available
        $headers = [
            'From: ' . $this->emailFrom,
            'Reply-To: ' . $this->emailFrom,
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/html; charset=UTF-8'
        ];

        $result = mail($to, $subject, $body, implode("\r\n", $headers));

        // Log to database
        $this->logNotification('email', $to, $subject, $result ? 'sent' : 'failed');

        return $result;
    }

    /**
     * Log notification to database
     *
     * @param string $type (sms|email)
     * @param string $recipient
     * @param string $content
     * @param string $status (sent|failed)
     * @param string|null $error
     * @return void
     */
    private function logNotification(string $type, string $recipient, string $content, string $status, ?string $error = null): void {
        $stmt = $this->db->prepare("
            INSERT INTO notification_logs (type, recipient, content, status, error, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([$type, $recipient, $content, $status, $error]);
    }

    /**
     * Replace template variables with actual values
     *
     * @param string $template
     * @param array $variables
     * @return string
     */
    private function replaceTemplateVars(string $template, array $variables): string {
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }

    /**
     * Send notification (SMS + Email)
     *
     * @param string $templateKey Template name from $templates
     * @param array $variables Template variables
     * @param string|null $phone Phone number for SMS (optional)
     * @param string|null $email Email address (optional)
     * @return array ['sms' => bool, 'email' => bool]
     */
    public function send(string $templateKey, array $variables, ?string $phone = null, ?string $email = null): array {
        $results = [
            'sms' => false,
            'email' => false
        ];

        if (!isset($this->templates[$templateKey])) {
            ecomm_log_error("Unknown notification template", ['template' => $templateKey]);
            return $results;
        }

        $template = $this->templates[$templateKey];
        $variables['sender'] = $this->burstSender;

        // Send SMS
        if ($phone && !empty($template['sms'])) {
            $smsMessage = $this->replaceTemplateVars($template['sms'], $variables);
            $results['sms'] = $this->sendSMS($phone, $smsMessage);
        }

        // Send Email
        if ($email && !empty($template['email_subject'])) {
            $emailSubject = $this->replaceTemplateVars($template['email_subject'], $variables);
            $emailBody = $this->replaceTemplateVars($template['email_body'], $variables);
            $results['email'] = $this->sendEmail($email, $emailSubject, $emailBody);
        }

        return $results;
    }

    /**
     * Send order created notification
     *
     * @param array $order
     * @return array
     */
    public function notifyOrderCreated(array $order): array {
        return $this->send('order.created', [
            'customer_name' => $order['customer_first_name'] ?? 'Customer',
            'order_id' => $order['id']
        ], $order['customer_phone'] ?? null, $order['customer_email'] ?? null);
    }

    /**
     * Send order dispatched notification
     *
     * @param array $order
     * @param string $courier
     * @param string $trackingNumber
     * @return array
     */
    public function notifyOrderDispatched(array $order, string $courier, string $trackingNumber): array {
        return $this->send('order.dispatched', [
            'order_id' => $order['id'],
            'courier' => $courier,
            'tracking_number' => $trackingNumber
        ], $order['customer_phone'] ?? null, $order['customer_email'] ?? null);
    }

    /**
     * Send order out for delivery notification
     *
     * @param array $order
     * @return array
     */
    public function notifyOrderOutForDelivery(array $order): array {
        return $this->send('order.out_for_delivery', [
            'order_id' => $order['id']
        ], $order['customer_phone'] ?? null, $order['customer_email'] ?? null);
    }

    /**
     * Send order ready for pickup notification
     *
     * @param array $order
     * @param string $outletName
     * @return array
     */
    public function notifyOrderReadyForPickup(array $order, string $outletName): array {
        return $this->send('order.ready_for_pickup', [
            'order_id' => $order['id'],
            'outlet_name' => $outletName
        ], $order['customer_phone'] ?? null, $order['customer_email'] ?? null);
    }

    /**
     * Send age verification required notification
     *
     * @param array $order
     * @param string $verificationLink
     * @return array
     */
    public function notifyAgeVerificationRequired(array $order, string $verificationLink): array {
        return $this->send('age_verification.required', [
            'customer_name' => $order['customer_first_name'] ?? 'Customer',
            'order_id' => $order['id'],
            'verification_link' => $verificationLink
        ], $order['customer_phone'] ?? null, $order['customer_email'] ?? null);
    }

    /**
     * Send age verification approved notification
     *
     * @param array $order
     * @return array
     */
    public function notifyAgeVerificationApproved(array $order): array {
        return $this->send('age_verification.approved', [
            'order_id' => $order['id']
        ], $order['customer_phone'] ?? null, $order['customer_email'] ?? null);
    }

    /**
     * Send age verification rejected notification
     *
     * @param array $order
     * @return array
     */
    public function notifyAgeVerificationRejected(array $order): array {
        return $this->send('age_verification.rejected', [
            'order_id' => $order['id']
        ], $order['customer_phone'] ?? null, $order['customer_email'] ?? null);
    }

    /**
     * Send order cancelled notification
     *
     * @param array $order
     * @return array
     */
    public function notifyOrderCancelled(array $order): array {
        return $this->send('order.cancelled', [
            'order_id' => $order['id']
        ], $order['customer_phone'] ?? null, $order['customer_email'] ?? null);
    }

    /**
     * Get notification history for a customer
     *
     * @param string $email
     * @param int $limit
     * @return array
     */
    public function getCustomerNotificationHistory(string $email, int $limit = 50): array {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_logs
            WHERE recipient = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $stmt->execute([$email, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
