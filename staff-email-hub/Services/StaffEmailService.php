<?php
declare(strict_types=1);

namespace StaffEmailHub\Services;

use PDO;
use Exception;

/**
 * StaffEmailService - Professional email client for staff
 *
 * Features: Draft/Send/Archive, Templates, Attachments, Trace IDs
 *
 * @version 1.0.0
 */
class StaffEmailService
{
    private PDO $db;
    private string $baseStoragePath = '/var/storage/emails';

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureStorageDirectories();
    }

    /**
     * Create draft email
     */
    public function createDraft(int $staffId, array $data): array
    {
        try {
            $traceId = $this->generateTraceId('EMAIL');

            $stmt = $this->db->prepare("
                INSERT INTO staff_emails
                (trace_id, from_staff_id, to_email, customer_id, subject, body_html,
                 body_plain, template_used, status, notes, tags, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW())
            ");

            $stmt->execute([
                $traceId,
                $staffId,
                $data['to_email'] ?? '',
                $data['customer_id'] ?? null,
                $data['subject'] ?? '',
                $data['body_html'] ?? '',
                $data['body_plain'] ?? '',
                $data['template_used'] ?? null,
                $data['notes'] ?? '',
                json_encode($data['tags'] ?? [])
            ]);

            return [
                'success' => true,
                'trace_id' => $traceId,
                'email_id' => $this->db->lastInsertId(),
                'status' => 'draft'
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Send email
     */
    public function sendEmail(int $emailId, int $staffId): array
    {
        try {
            $email = $this->getEmailById($emailId);

            if (!$email || $email['from_staff_id'] !== $staffId) {
                return $this->error('Email not found or unauthorized');
            }

            // Send email
            $result = $this->sendViaMailer(
                $email['to_email'],
                $email['subject'],
                $email['body_html'],
                $email['body_plain']
            );

            if (!$result['success']) {
                return $result;
            }

            // Update status
            $stmt = $this->db->prepare("
                UPDATE staff_emails
                SET status = 'sent', sent_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$emailId]);

            return [
                'success' => true,
                'email_id' => $emailId,
                'sent_at' => date('Y-m-d H:i:s'),
                'trace_id' => $email['trace_id']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get inbox (paginated)
     */
    public function getInbox(int $staffId, int $page = 1, int $perPage = 20): array
    {
        try {
            $offset = ($page - 1) * $perPage;

            $stmt = $this->db->prepare("
                SELECT id, trace_id, to_email, customer_id, subject, status,
                       assigned_to, is_r18_flagged, created_at, sent_at, read_at,
                       reply_count, tags, notes
                FROM staff_emails
                WHERE from_staff_id = ? OR assigned_to = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$staffId, $staffId, $perPage, $offset]);
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count total
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM staff_emails
                WHERE from_staff_id = ? OR assigned_to = ?
            ");
            $countStmt->execute([$staffId, $staffId]);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'success' => true,
                'emails' => $emails,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $perPage)
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get email by ID
     */
    public function getEmailById(int $emailId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM staff_emails WHERE id = ?");
            $stmt->execute([$emailId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get templates
     */
    public function getTemplates(int $limit = 50): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, category, subject, variables, is_active, usage_count
                FROM staff_email_templates
                WHERE is_active = TRUE
                ORDER BY usage_count DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);

            return [
                'success' => true,
                'templates' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Apply template to draft
     */
    public function applyTemplate(int $emailId, int $templateId, array $variables = []): array
    {
        try {
            // Get template
            $stmt = $this->db->prepare("
                SELECT subject, body_html, body_plain, variables
                FROM staff_email_templates
                WHERE id = ?
            ");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                return $this->error('Template not found');
            }

            // Replace variables
            $html = $template['body_html'];
            $plain = $template['body_plain'];

            foreach ($variables as $key => $value) {
                $html = str_replace("{{$key}}", $value, $html);
                $plain = str_replace("{{$key}}", $value, $plain);
            }

            // Update email
            $updateStmt = $this->db->prepare("
                UPDATE staff_emails
                SET subject = ?, body_html = ?, body_plain = ?, template_used = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$template['subject'], $html, $plain, $templateId, $emailId]);

            return [
                'success' => true,
                'email_id' => $emailId,
                'template_id' => $templateId
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Assign email to staff member
     */
    public function assignEmail(int $emailId, int $assignTo): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE staff_emails
                SET assigned_to = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$assignTo, $emailId]);

            return ['success' => true, 'email_id' => $emailId];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Flag email as R18
     */
    public function flagR18(int $emailId, string $reason = ''): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE staff_emails
                SET is_r18_flagged = TRUE, r18_flag_reason = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reason, $emailId]);

            return ['success' => true, 'flagged' => true];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Add note to email
     */
    public function addNote(int $emailId, string $note): array
    {
        try {
            $email = $this->getEmailById($emailId);
            $existingNotes = $email['notes'] ? $email['notes'] . "\n" : '';
            $newNotes = $existingNotes . date('Y-m-d H:i:s') . " - " . $note;

            $stmt = $this->db->prepare("
                UPDATE staff_emails
                SET notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newNotes, $emailId]);

            return ['success' => true, 'email_id' => $emailId];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function sendViaMailer(string $to, string $subject, string $html, string $plain): array
    {
        // TODO: Implement actual mail sending (PHPMailer/Symfony Mailer)
        // For now, return success
        return [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function ensureStorageDirectories(): void
    {
        @mkdir($this->baseStoragePath, 0755, true);
        @mkdir($this->baseStoragePath . '/attachments', 0755, true);
        @mkdir($this->baseStoragePath . '/id-uploads', 0755, true);
    }

    private function generateTraceId(string $prefix): string
    {
        return $prefix . '-' . strtoupper(uniqid());
    }

    private function error(string $message): array
    {
        error_log("[StaffEmailService] {$message}");
        return ['success' => false, 'error' => $message];
    }
}
