<?php
declare(strict_types=1);

/**
 * Email Service - Enterprise Email Integration for Consignments Module
 *
 * Wraps SendGridService with consignments-specific functionality:
 * - Template rendering with variable substitution
 * - Queue-based sending with priority levels
 * - Retry logic and error handling
 * - Audit logging
 * - Support for both internal and supplier emails
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
use InvalidArgumentException;
use RuntimeException;

class EmailService
{
    private PDO $pdo;

    /**
     * Priority levels for email queue
     */
    public const PRIORITY_URGENT = 1;      // Send immediately (real-time)
    public const PRIORITY_HIGH = 2;        // Batch every 5 minutes
    public const PRIORITY_NORMAL = 3;      // Batch every 30 minutes
    public const PRIORITY_LOW = 4;         // Daily digest

    /**
     * Email types
     */
    public const TYPE_INTERNAL = 'internal';
    public const TYPE_SUPPLIER = 'supplier';

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
    // PUBLIC API
    // ========================================================================

    /**
     * Send email using template
     *
     * @param string $templateKey Template identifier (e.g., 'po_created_internal')
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient name
     * @param array $templateData Data for template variable substitution
     * @param int $priority Priority level (use PRIORITY_* constants)
     * @param int|null $consignmentId Related consignment ID (for audit trail)
     * @param int|null $sentBy User ID who triggered the email
     * @return int Queue ID
     * @throws InvalidArgumentException If template not found or invalid data
     * @throws RuntimeException If queue insertion fails
     */
    public function sendTemplate(
        string $templateKey,
        string $recipientEmail,
        string $recipientName,
        array $templateData,
        int $priority = self::PRIORITY_NORMAL,
        ?int $consignmentId = null,
        ?int $sentBy = null
    ): int {
        // Validate email
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: {$recipientEmail}");
        }

        // Load template
        $template = $this->loadTemplate($templateKey);

        // Render template
        $rendered = $this->renderTemplate($template, $templateData);

        // Queue email
        $queueId = $this->queueEmail(
            $recipientEmail,
            $recipientName,
            $rendered['subject'],
            $rendered['html'],
            $rendered['text'] ?? null,
            $priority,
            $template['template_type'],
            $consignmentId,
            $templateKey,
            $sentBy
        );

        return $queueId;
    }

    /**
     * Send custom email (without template)
     *
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient name
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @param string|null $textBody Plain text email body (optional)
     * @param int $priority Priority level
     * @param string $emailType Type: 'internal' or 'supplier'
     * @param int|null $consignmentId Related consignment ID
     * @param int|null $sentBy User ID who triggered the email
     * @return int Queue ID
     * @throws InvalidArgumentException If invalid data
     * @throws RuntimeException If queue insertion fails
     */
    public function sendCustom(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        int $priority = self::PRIORITY_NORMAL,
        string $emailType = self::TYPE_INTERNAL,
        ?int $consignmentId = null,
        ?int $sentBy = null
    ): int {
        // Validate email
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: {$recipientEmail}");
        }

        // Validate email type
        if (!in_array($emailType, [self::TYPE_INTERNAL, self::TYPE_SUPPLIER])) {
            throw new InvalidArgumentException("Invalid email type: {$emailType}");
        }

        // Queue email
        return $this->queueEmail(
            $recipientEmail,
            $recipientName,
            $subject,
            $htmlBody,
            $textBody,
            $priority,
            $emailType,
            $consignmentId,
            null,
            $sentBy
        );
    }

    /**
     * Send email immediately (bypass queue)
     *
     * Use sparingly - only for critical/urgent notifications
     *
     * @param string $recipientEmail Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML body
     * @param string|null $textBody Plain text body
     * @return bool Success status
     */
    public function sendImmediate(
        string $recipientEmail,
        string $subject,
        string $htmlBody,
        ?string $textBody = null
    ): bool {
        try {
            $result = SendGridService::send(
                $recipientEmail,
                $subject,
                $htmlBody,
                $textBody
            );

            return $result['success'] ?? false;
        } catch (\Exception $e) {
            error_log("EmailService::sendImmediate failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email sending statistics
     *
     * @param int $days Number of days to look back
     * @return array Statistics data
     */
    public function getStatistics(int $days = 7): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                AVG(CASE WHEN status = 'sent' THEN retry_count ELSE NULL END) as avg_retries
            FROM consignment_notification_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");

        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================================================
    // INTERNAL METHODS
    // ========================================================================

    /**
     * Load email template from database
     *
     * @param string $templateKey Template identifier
     * @return array Template data
     * @throws InvalidArgumentException If template not found
     */
    private function loadTemplate(string $templateKey): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                template_key,
                template_type,
                name,
                subject_line,
                template_file,
                priority,
                is_active
            FROM consignment_email_templates
            WHERE template_key = ? AND is_active = 1
        ");

        $stmt->execute([$templateKey]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            throw new InvalidArgumentException("Email template not found or inactive: {$templateKey}");
        }

        return $template;
    }

    /**
     * Render email template with data
     *
     * @param array $template Template configuration
     * @param array $data Template variable data
     * @return array ['subject' => string, 'html' => string, 'text' => string|null]
     * @throws RuntimeException If template file not found or rendering fails
     */
    private function renderTemplate(array $template, array $data): array
    {
        $templatePath = __DIR__ . '/../../templates/email/' . $template['template_file'];

        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template file not found: {$templatePath}");
        }

        // Replace placeholders in subject line
        $subject = $this->replacePlaceholders($template['subject_line'], $data);

        // Render template content
        ob_start();
        extract($data);
        require $templatePath;
        $html = ob_get_clean();

        // Generate plain text version if not provided
        $text = $data['plain_text'] ?? $this->htmlToText($html);

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text
        ];
    }

    /**
     * Replace {placeholders} in string with data values
     *
     * @param string $template String with {placeholders}
     * @param array $data Replacement data
     * @return string Rendered string
     */
    private function replacePlaceholders(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            // Only replace scalar values
            if (is_scalar($value)) {
                $template = str_replace('{' . $key . '}', (string)$value, $template);
            }
        }

        return $template;
    }

    /**
     * Convert HTML to plain text
     *
     * @param string $html HTML content
     * @return string Plain text
     */
    private function htmlToText(string $html): string
    {
        // Strip tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim
        return trim($text);
    }

    /**
     * Queue email for background sending
     *
     * @param string $recipientEmail Recipient email
     * @param string $recipientName Recipient name
     * @param string $subject Email subject
     * @param string $htmlBody HTML body
     * @param string|null $textBody Plain text body
     * @param int $priority Priority level
     * @param string $emailType Email type (internal/supplier)
     * @param int|null $consignmentId Related consignment ID
     * @param string|null $templateKey Template used
     * @param int|null $sentBy User who triggered send
     * @return int Queue ID
     * @throws RuntimeException If queue insertion fails
     */
    private function queueEmail(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $htmlBody,
        ?string $textBody,
        int $priority,
        string $emailType,
        ?int $consignmentId,
        ?string $templateKey,
        ?int $sentBy
    ): int {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO consignment_notification_queue (
                    recipient_email,
                    recipient_name,
                    subject,
                    html_body,
                    text_body,
                    priority,
                    email_type,
                    consignment_id,
                    template_key,
                    sent_by,
                    status,
                    retry_count,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'pending', 0, NOW()
                )
            ");

            $stmt->execute([
                $recipientEmail,
                $recipientName,
                $subject,
                $htmlBody,
                $textBody,
                $priority,
                $emailType,
                $consignmentId,
                $templateKey,
                $sentBy
            ]);

            return (int)$this->pdo->lastInsertId();

        } catch (PDOException $e) {
            throw new RuntimeException("Failed to queue email: " . $e->getMessage());
        }
    }
}
