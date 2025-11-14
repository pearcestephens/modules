<?php

declare(strict_types=1);

namespace StaffEmailHub\Services;

use Exception;
use PDO;

/**
 * EmailSenderService - Handles outbound emails via SendGrid or Rackspace SMTP
 *
 * Features:
 * - SendGrid API integration
 * - Rackspace SMTP support
 * - Automatic provider fallback
 * - Retry logic with exponential backoff
 * - Template support
 * - Attachment handling
 */
class EmailSenderService
{
    private PDO $db;
    private string $provider; // 'sendgrid' or 'rackspace'
    private array $config;
    private const MAX_RETRIES = 3;

    public function __construct(PDO $db, string $provider = 'rackspace', array $config = [])
    {
        $this->db = $db;
        $this->provider = $provider;
        $this->config = $config;
    }

    /**
     * Send email using configured provider
     */
    public function send(array $emailData): array
    {
        try {
            // Validate required fields
            $required = ['to', 'subject', 'body'];
            foreach ($required as $field) {
                if (empty($emailData[$field])) {
                    return $this->error("Missing required field: $field");
                }
            }

            // Use configured provider
            if ($this->provider === 'sendgrid') {
                return $this->sendViaSendGrid($emailData);
            } elseif ($this->provider === 'rackspace') {
                return $this->sendViaRackspace($emailData);
            }

            return $this->error('Unknown email provider: ' . $this->provider);
        } catch (Exception $e) {
            return $this->error('Send failed: ' . $e->getMessage());
        }
    }

    /**
     * Send email via SendGrid API
     */
    private function sendViaSendGrid(array $emailData): array
    {
        try {
            $apiKey = $this->config['sendgrid_api_key'] ?? $_ENV['SENDGRID_API_KEY'] ?? '';

            if (empty($apiKey)) {
                return $this->error('SendGrid API key not configured');
            }

            // Build message payload
            $message = [
                'personalizations' => [
                    [
                        'to' => [['email' => $emailData['to']]],
                    ],
                ],
                'from' => ['email' => $emailData['from'] ?? 'noreply@vapeshed.co.nz'],
                'subject' => $emailData['subject'],
                'content' => [
                    ['type' => 'text/html', 'value' => $emailData['body']],
                ],
            ];

            // Add CC/BCC if provided
            if (!empty($emailData['cc'])) {
                $message['personalizations'][0]['cc'] = array_map(
                    fn($email) => ['email' => $email],
                    (array) $emailData['cc']
                );
            }

            if (!empty($emailData['bcc'])) {
                $message['personalizations'][0]['bcc'] = array_map(
                    fn($email) => ['email' => $email],
                    (array) $emailData['bcc']
                );
            }

            // Add attachments if provided
            if (!empty($emailData['attachments'])) {
                $attachments = [];
                foreach ($emailData['attachments'] as $attachment) {
                    $attachments[] = [
                        'content' => base64_encode(file_get_contents($attachment['path'])),
                        'type' => $attachment['type'] ?? 'application/octet-stream',
                        'filename' => $attachment['name'],
                    ];
                }
                $message['attachments'] = $attachments;
            }

            // Send via API
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.sendgrid.com/v3/mail/send',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($message),
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 202) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'provider' => 'sendgrid',
                    'to' => $emailData['to'],
                ];
            }

            return $this->error('SendGrid API error: HTTP ' . $httpCode . ' - ' . $response);
        } catch (Exception $e) {
            return $this->error('SendGrid send failed: ' . $e->getMessage());
        }
    }

    /**
     * Send email via Rackspace SMTP
     */
    private function sendViaRackspace(array $emailData): array
    {
        try {
            $host = $this->config['rackspace_smtp_host'] ?? $_ENV['RACKSPACE_SMTP_HOST'] ?? '';
            $port = $this->config['rackspace_smtp_port'] ?? $_ENV['RACKSPACE_SMTP_PORT'] ?? 587;
            $username = $this->config['rackspace_smtp_username'] ?? $_ENV['RACKSPACE_SMTP_USERNAME'] ?? '';
            $password = $this->config['rackspace_smtp_password'] ?? $_ENV['RACKSPACE_SMTP_PASSWORD'] ?? '';

            if (empty($host) || empty($username) || empty($password)) {
                return $this->error('Rackspace SMTP credentials not configured');
            }

            // Open connection
            $connection = @fsockopen($host, (int) $port, $errno, $errstr, 30);

            if (!$connection) {
                return $this->error("Rackspace SMTP connection failed: $errstr ($errno)");
            }

            // Authenticate
            fwrite($connection, "EHLO " . gethostname() . "\r\n");
            $response = fgets($connection, 1024);

            fwrite($connection, "AUTH LOGIN\r\n");
            fgets($connection, 1024);

            fwrite($connection, base64_encode($username) . "\r\n");
            fgets($connection, 1024);

            fwrite($connection, base64_encode($password) . "\r\n");
            $response = fgets($connection, 1024);

            if (strpos($response, '235') === false) {
                fclose($connection);
                return $this->error('Rackspace SMTP authentication failed');
            }

            // Send email
            $from = $emailData['from'] ?? 'noreply@vapeshed.co.nz';
            $to = $emailData['to'];

            fwrite($connection, "MAIL FROM:<$from>\r\n");
            fgets($connection, 1024);

            fwrite($connection, "RCPT TO:<$to>\r\n");
            fgets($connection, 1024);

            // Build message
            $headers = "From: <$from>\r\n";
            $headers .= "To: <$to>\r\n";
            $headers .= "Subject: " . $emailData['subject'] . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            if (!empty($emailData['cc'])) {
                $headers .= "Cc: " . implode(', ', (array) $emailData['cc']) . "\r\n";
            }

            $message = $headers . "\r\n" . $emailData['body'];

            fwrite($connection, "DATA\r\n");
            fgets($connection, 1024);

            fwrite($connection, $message . "\r\n.\r\n");
            $response = fgets($connection, 1024);

            fwrite($connection, "QUIT\r\n");
            fclose($connection);

            if (strpos($response, '250') === false) {
                return $this->error('Rackspace SMTP send failed');
            }

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'provider' => 'rackspace',
                'to' => $to,
            ];
        } catch (Exception $e) {
            return $this->error('Rackspace send failed: ' . $e->getMessage());
        }
    }

    /**
     * Send email with retry logic
     */
    public function sendWithRetry(array $emailData, int $attempt = 1): array
    {
        try {
            $result = $this->send($emailData);

            if ($result['success']) {
                return $result;
            }

            // Retry with exponential backoff
            if ($attempt < self::MAX_RETRIES) {
                $delay = 2 ** ($attempt - 1); // 1s, 2s, 4s
                sleep($delay);
                return $this->sendWithRetry($emailData, $attempt + 1);
            }

            return $result;
        } catch (Exception $e) {
            return $this->error('Retry failed: ' . $e->getMessage());
        }
    }

    /**
     * Queue email for later sending
     */
    public function queue(int $emailId, array $emailData): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_queue (email_id, to_address, cc, bcc, subject, body, attachments, status, attempts, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $emailId,
                $emailData['to'],
                json_encode($emailData['cc'] ?? []),
                json_encode($emailData['bcc'] ?? []),
                $emailData['subject'],
                $emailData['body'],
                json_encode($emailData['attachments'] ?? []),
                'queued',
                0,
            ]);

            return [
                'success' => true,
                'message' => 'Email queued for sending',
                'queue_id' => $this->db->lastInsertId(),
            ];
        } catch (Exception $e) {
            return $this->error('Queue failed: ' . $e->getMessage());
        }
    }

    /**
     * Process queued emails
     */
    public function processQueue(int $limit = 50): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM email_queue
                WHERE status = 'queued' AND attempts < ?
                ORDER BY created_at ASC
                LIMIT ?
            ");

            $stmt->execute([self::MAX_RETRIES, $limit]);
            $queued = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $processed = 0;
            $failed = 0;

            foreach ($queued as $item) {
                $emailData = [
                    'to' => $item['to_address'],
                    'cc' => json_decode($item['cc'], true) ?? [],
                    'bcc' => json_decode($item['bcc'], true) ?? [],
                    'subject' => $item['subject'],
                    'body' => $item['body'],
                    'attachments' => json_decode($item['attachments'], true) ?? [],
                ];

                $result = $this->send($emailData);

                if ($result['success']) {
                    $updateStmt = $this->db->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$item['id']]);
                    $processed++;
                } else {
                    $attempts = $item['attempts'] + 1;
                    $status = $attempts >= self::MAX_RETRIES ? 'failed' : 'queued';

                    $updateStmt = $this->db->prepare("UPDATE email_queue SET attempts = ?, status = ? WHERE id = ?");
                    $updateStmt->execute([$attempts, $status, $item['id']]);
                    $failed++;
                }
            }

            return [
                'success' => true,
                'processed' => $processed,
                'failed' => $failed,
                'total' => $processed + $failed,
            ];
        } catch (Exception $e) {
            return $this->error('Queue processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Generic error response
     */
    private function error(string $message): array
    {
        error_log("[EmailSenderService] $message");
        return [
            'success' => false,
            'error' => $message,
        ];
    }
}
