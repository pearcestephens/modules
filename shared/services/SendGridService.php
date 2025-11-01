<?php
/**
 * SendGridService - Centralized Email Service using SendGrid API
 *
 * Purpose: Shared email sending service for ALL modules
 * Usage: SendGridService::send($to, $subject, $body, $attachments)
 *
 * @package CIS\Shared\Services
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Shared\Services;

use Exception;

class SendGridService
{
    private static ?string $apiKey = null;
    private static ?string $fromEmail = null;
    private static ?string $fromName = null;

    /**
     * Initialize SendGrid configuration
     *
     * @param string $apiKey SendGrid API key
     * @param string $fromEmail Default sender email
     * @param string $fromName Default sender name
     */
    public static function init(string $apiKey, string $fromEmail, string $fromName = 'The Vape Shed'): void
    {
        self::$apiKey = $apiKey;
        self::$fromEmail = $fromEmail;
        self::$fromName = $fromName;
    }

    /**
     * Auto-initialize from environment variables or config
     */
    private static function autoInit(): void
    {
        if (self::$apiKey !== null) {
            return; // Already initialized
        }

        // Try to load from environment
        $apiKey = getenv('SENDGRID_API_KEY') ?: ($_ENV['SENDGRID_API_KEY'] ?? null);
        $fromEmail = getenv('SENDGRID_FROM_EMAIL') ?: ($_ENV['SENDGRID_FROM_EMAIL'] ?? 'noreply@vapeshed.co.nz');
        $fromName = getenv('SENDGRID_FROM_NAME') ?: ($_ENV['SENDGRID_FROM_NAME'] ?? 'The Vape Shed');

        // Try to load from config file
        if (!$apiKey) {
            $configPaths = [
                $_SERVER['DOCUMENT_ROOT'] . '/config/sendgrid.php',
                __DIR__ . '/../../config/sendgrid.php'
            ];

            foreach ($configPaths as $configPath) {
                if (file_exists($configPath)) {
                    $config = require $configPath;
                    $apiKey = $config['api_key'] ?? null;
                    $fromEmail = $config['from_email'] ?? $fromEmail;
                    $fromName = $config['from_name'] ?? $fromName;
                    break;
                }
            }
        }

        if (!$apiKey) {
            throw new Exception('SendGrid API key not configured. Set SENDGRID_API_KEY environment variable or create config/sendgrid.php');
        }

        self::init($apiKey, $fromEmail, $fromName);
    }

    /**
     * Send email via SendGrid API
     *
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @param string|null $textBody Plain text email body (optional)
     * @param array $attachments Array of attachments: [['filename'=>'', 'content'=>'base64', 'mime'=>'']]
     * @param string|null $fromEmail Override default sender
     * @param string|null $fromName Override default sender name
     * @return array Response with success status and message
     */
    public static function send(
        $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $attachments = [],
        ?string $fromEmail = null,
        ?string $fromName = null
    ): array {
        try {
            self::autoInit();

            // Build recipient list
            $recipients = [];
            if (is_string($to)) {
                $recipients[] = ['email' => $to];
            } elseif (is_array($to)) {
                foreach ($to as $email) {
                    $recipients[] = ['email' => $email];
                }
            }

            if (empty($recipients)) {
                throw new Exception('No recipients specified');
            }

            // Build SendGrid API payload
            $payload = [
                'personalizations' => [
                    [
                        'to' => $recipients,
                        'subject' => $subject
                    ]
                ],
                'from' => [
                    'email' => $fromEmail ?? self::$fromEmail,
                    'name' => $fromName ?? self::$fromName
                ],
                'content' => []
            ];

            // Add text content if provided
            if ($textBody) {
                $payload['content'][] = [
                    'type' => 'text/plain',
                    'value' => $textBody
                ];
            }

            // Add HTML content
            $payload['content'][] = [
                'type' => 'text/html',
                'value' => $htmlBody
            ];

            // Add attachments
            if (!empty($attachments)) {
                $payload['attachments'] = [];
                foreach ($attachments as $attachment) {
                    if (!isset($attachment['filename']) || !isset($attachment['content'])) {
                        continue;
                    }

                    $payload['attachments'][] = [
                        'content' => $attachment['content'], // Already base64 encoded
                        'filename' => $attachment['filename'],
                        'type' => $attachment['mime'] ?? 'application/octet-stream',
                        'disposition' => 'attachment'
                    ];
                }
            }

            // Send via SendGrid API
            $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . self::$apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($payload)
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception('cURL error: ' . $error);
            }

            // SendGrid returns 202 Accepted on success
            if ($httpCode === 202) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'response_code' => $httpCode
                ];
            }

            // Parse error response
            $responseData = json_decode($response, true);
            $errorMessage = 'SendGrid API error (HTTP ' . $httpCode . ')';
            if (isset($responseData['errors'][0]['message'])) {
                $errorMessage .= ': ' . $responseData['errors'][0]['message'];
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'response_code' => $httpCode,
                'response' => $responseData
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'exception' => get_class($e)
            ];
        }
    }

    /**
     * Send email using data from email_queue table format
     *
     * @param array $queueData Row from email_queue table
     * @return array Response with success status
     */
    public static function sendFromQueue(array $queueData): array
    {
        $attachments = [];
        if (isset($queueData['attachments']) && is_string($queueData['attachments'])) {
            $attachments = json_decode($queueData['attachments'], true) ?? [];
        } elseif (isset($queueData['attachments']) && is_array($queueData['attachments'])) {
            $attachments = $queueData['attachments'];
        }

        return self::send(
            $queueData['email_to'] ?? '',
            $queueData['subject'] ?? 'No Subject',
            $queueData['html_body'] ?? '',
            $queueData['text_body'] ?? null,
            $attachments,
            $queueData['email_from'] ?? null
        );
    }

    /**
     * Test SendGrid connection and credentials
     *
     * @return array Test result
     */
    public static function test(): array
    {
        try {
            self::autoInit();

            // Send a simple test request to validate API key
            $ch = curl_init('https://api.sendgrid.com/v3/user/profile');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . self::$apiKey
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $profile = json_decode($response, true);
                return [
                    'success' => true,
                    'message' => 'SendGrid connection successful',
                    'account' => $profile['username'] ?? 'Unknown',
                    'from_email' => self::$fromEmail
                ];
            }

            return [
                'success' => false,
                'message' => 'SendGrid authentication failed (HTTP ' . $httpCode . ')',
                'http_code' => $httpCode
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if SendGrid is configured and ready to use
     *
     * @return bool True if configured, false otherwise
     */
    public static function isConfigured(): bool
    {
        try {
            self::autoInit();
            return !empty(self::$apiKey) && !empty(self::$fromEmail);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get service status and configuration
     *
     * @return array Status information
     */
    public static function getStatus(): array
    {
        try {
            self::autoInit();
            return [
                'configured' => true,
                'api_key_set' => !empty(self::$apiKey),
                'from_email' => self::$fromEmail,
                'from_name' => self::$fromName
            ];
        } catch (Exception $e) {
            return [
                'configured' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
