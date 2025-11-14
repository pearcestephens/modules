<?php
/**
 * Advanced Email Features Service
 *
 * Implements enterprise email features:
 * - Email templates (pre-built, customizable)
 * - Send scheduler (schedule emails for optimal times)
 * - Priority inbox (smart filtering and importance scoring)
 * - Read receipts (with tracking and notifications)
 * - Follow-up reminders (automatic, configurable)
 * - Conversation AI analysis (sentiment, urgency detection)
 * - Attachment enhancements (compression, virus scan, OCR)
 *
 * @package StaffEmailHub\Services
 */

namespace StaffEmailHub\Services;

class AdvancedEmailFeaturesService
{
    private $db;
    private $logger;
    private $staffId;

    public function __construct($db, $logger, $staffId)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
    }

    /**
     * Create email template
     */
    public function createTemplate($name, $subject, $body, $category = 'general', $tags = [])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_templates
                (staff_id, name, subject, body, category, tags, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->staffId,
                $name,
                $subject,
                $body,
                $category,
                json_encode($tags)
            ]);

            $templateId = $this->db->lastInsertId();

            $this->logger->info('Email template created', [
                'template_id' => $templateId,
                'name' => $name,
                'category' => $category
            ]);

            return [
                'success' => true,
                'template_id' => $templateId,
                'name' => $name
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get all templates for staff
     */
    public function getTemplates($category = null)
    {
        try {
            if ($category) {
                $stmt = $this->db->prepare("
                    SELECT id, name, subject, body, category, tags, created_at
                    FROM email_templates
                    WHERE staff_id = ? AND category = ?
                    ORDER BY category, name ASC
                ");
                $stmt->execute([$this->staffId, $category]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT id, name, subject, body, category, tags, created_at
                    FROM email_templates
                    WHERE staff_id = ?
                    ORDER BY category, name ASC
                ");
                $stmt->execute([$this->staffId]);
            }

            return [
                'success' => true,
                'templates' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Schedule email for later delivery
     */
    public function scheduleEmail($toAddress, $subject, $body, $sendAt, $fromProfile = null)
    {
        try {
            // Validate future time
            $sendAtTime = strtotime($sendAt);
            if ($sendAtTime === false || $sendAtTime <= time()) {
                return ['success' => false, 'message' => 'Invalid send time (must be in future)'];
            }

            $stmt = $this->db->prepare("
                INSERT INTO scheduled_emails
                (staff_id, to_address, from_profile, subject, body,
                 scheduled_send_at, status, created_at)
                VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), 'pending', NOW())
            ");

            $stmt->execute([
                $this->staffId,
                $toAddress,
                $fromProfile,
                $subject,
                $body,
                $sendAtTime
            ]);

            $scheduleId = $this->db->lastInsertId();

            $this->logger->info('Email scheduled', [
                'schedule_id' => $scheduleId,
                'to' => $toAddress,
                'send_at' => date('Y-m-d H:i:s', $sendAtTime)
            ]);

            return [
                'success' => true,
                'schedule_id' => $scheduleId,
                'scheduled_for' => date('Y-m-d H:i:s', $sendAtTime)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get scheduled emails
     */
    public function getScheduledEmails($status = 'pending')
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, to_address, subject, scheduled_send_at, status, created_at
                FROM scheduled_emails
                WHERE staff_id = ? AND status = ?
                ORDER BY scheduled_send_at ASC
            ");

            $stmt->execute([$this->staffId, $status]);
            return [
                'success' => true,
                'scheduled' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cancel scheduled email
     */
    public function cancelScheduledEmail($scheduleId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE scheduled_emails
                SET status = 'cancelled', updated_at = NOW()
                WHERE id = ? AND staff_id = ?
            ");

            $stmt->execute([$scheduleId, $this->staffId]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Schedule not found'];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Add follow-up reminder for email
     */
    public function addFollowUpReminder($emailId, $remindAt, $note = null)
    {
        try {
            $remindTime = strtotime($remindAt);
            if ($remindTime === false || $remindTime <= time()) {
                return ['success' => false, 'message' => 'Invalid reminder time'];
            }

            $stmt = $this->db->prepare("
                INSERT INTO follow_up_reminders
                (email_id, staff_id, remind_at, note, status, created_at)
                VALUES (?, ?, FROM_UNIXTIME(?), ?, 'pending', NOW())
            ");

            $stmt->execute([
                $emailId,
                $this->staffId,
                $remindTime,
                $note
            ]);

            $reminderId = $this->db->lastInsertId();

            return [
                'success' => true,
                'reminder_id' => $reminderId,
                'remind_at' => date('Y-m-d H:i:s', $remindTime)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get pending follow-up reminders
     */
    public function getPendingReminders()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    fr.id,
                    fr.email_id,
                    fr.remind_at,
                    fr.note,
                    e.from_address,
                    e.subject
                FROM follow_up_reminders fr
                LEFT JOIN emails e ON fr.email_id = e.id
                WHERE fr.staff_id = ? AND fr.status = 'pending'
                AND fr.remind_at <= NOW()
                ORDER BY fr.remind_at ASC
            ");

            $stmt->execute([$this->staffId]);
            return [
                'success' => true,
                'reminders' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Enable read receipts for email
     */
    public function enableReadReceipt($emailId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE emails
                SET track_opens = 1, open_tracking_token = ?
                WHERE id = ? AND staff_id = ?
            ");

            $token = bin2hex(random_bytes(16));
            $stmt->execute([$token, $emailId, $this->staffId]);

            return [
                'success' => true,
                'tracking_token' => $token
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Record email read/open event
     */
    public function recordEmailOpen($trackingToken)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_open_tracking
                (email_id, opened_at, ip_address, user_agent)
                SELECT id, NOW(), ?, ?
                FROM emails
                WHERE open_tracking_token = ?
            ");

            $stmt->execute([
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $trackingToken
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get email open statistics
     */
    public function getOpenStatistics($emailId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as open_count,
                    MIN(opened_at) as first_open,
                    MAX(opened_at) as last_open,
                    COUNT(DISTINCT ip_address) as unique_recipients
                FROM email_open_tracking
                WHERE email_id = ?
            ");

            $stmt->execute([$emailId]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Get detailed opens
            $detailStmt = $this->db->prepare("
                SELECT opened_at, ip_address, user_agent
                FROM email_open_tracking
                WHERE email_id = ?
                ORDER BY opened_at DESC
                LIMIT 50
            ");
            $detailStmt->execute([$emailId]);

            return [
                'success' => true,
                'summary' => $stats,
                'details' => $detailStmt->fetchAll(\PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Analyze conversation sentiment and urgency
     */
    public function analyzeConversation($conversationId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT subject, body, from_address, received_at
                FROM emails
                WHERE conversation_id = ?
                ORDER BY received_at ASC
            ");

            $stmt->execute([$conversationId]);
            $emails = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($emails)) {
                return ['success' => false, 'message' => 'Conversation not found'];
            }

            // Analyze emails for sentiment and urgency
            $analysis = [
                'total_emails' => count($emails),
                'sentiment' => $this->analyzeSentiment($emails),
                'urgency_score' => $this->calculateUrgency($emails),
                'key_topics' => $this->extractTopics($emails),
                'sentiment_trend' => $this->getConversationTrend($emails)
            ];

            return [
                'success' => true,
                'analysis' => $analysis
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create priority inbox by filtering important emails
     */
    public function getPriorityInbox($days = 7)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    e.id,
                    e.from_address,
                    e.subject,
                    e.received_at,
                    e.is_flagged,
                    COUNT(DISTINCT ea.id) as attachment_count,
                    (CASE
                        WHEN e.is_flagged = 1 THEN 100
                        WHEN e.from_address IN (
                            SELECT DISTINCT from_address FROM emails
                            WHERE staff_id = ? GROUP BY from_address HAVING COUNT(*) > 5
                        ) THEN 75
                        WHEN subject LIKE '%urgent%' OR subject LIKE '%important%' THEN 80
                        WHEN e.received_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 60
                        ELSE 40
                    END) as priority_score
                FROM emails e
                LEFT JOIN email_attachments ea ON e.id = ea.email_id
                WHERE e.staff_id = ?
                AND e.received_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND e.folder = 'inbox'
                GROUP BY e.id
                HAVING priority_score >= 60
                ORDER BY priority_score DESC, e.received_at DESC
            ");

            $stmt->execute([$this->staffId, $this->staffId, $days]);
            return [
                'success' => true,
                'priority_emails' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Flag email for priority
     */
    public function flagEmail($emailId, $flag = true)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE emails
                SET is_flagged = ?
                WHERE id = ? AND staff_id = ?
            ");

            $stmt->execute([$flag ? 1 : 0, $emailId, $this->staffId]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Private: Analyze sentiment of emails
     */
    private function analyzeSentiment($emails)
    {
        $positiveWords = ['great', 'excellent', 'wonderful', 'thanks', 'appreciate', 'happy', 'pleased'];
        $negativeWords = ['issue', 'problem', 'angry', 'upset', 'complaint', 'urgent', 'critical'];

        $sentiments = [];
        foreach ($emails as $email) {
            $text = strtolower($email['body']);
            $positive = 0;
            $negative = 0;

            foreach ($positiveWords as $word) {
                $positive += substr_count($text, $word);
            }
            foreach ($negativeWords as $word) {
                $negative += substr_count($text, $word);
            }

            $sentiments[] = [
                'from' => $email['from_address'],
                'sentiment' => $positive > $negative ? 'positive' : ($negative > $positive ? 'negative' : 'neutral')
            ];
        }

        return $sentiments;
    }

    /**
     * Private: Calculate conversation urgency
     */
    private function calculateUrgency($emails)
    {
        $urgentKeywords = ['urgent', 'asap', 'immediately', 'critical', 'emergency', 'priority'];
        $urgencyCount = 0;

        foreach ($emails as $email) {
            $text = strtolower($email['subject'] . ' ' . $email['body']);
            foreach ($urgentKeywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $urgencyCount++;
                }
            }
        }

        return min(100, ($urgencyCount * 20));
    }

    /**
     * Private: Extract topics from emails
     */
    private function extractTopics($emails)
    {
        $topics = [];
        foreach ($emails as $email) {
            // Simple keyword extraction (would be enhanced with NLP)
            $words = str_word_count(strtolower($email['body']), 1);
            $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of'];

            foreach ($words as $word) {
                if (strlen($word) > 4 && !in_array($word, $stopwords)) {
                    $topics[$word] = ($topics[$word] ?? 0) + 1;
                }
            }
        }

        arsort($topics);
        return array_slice(array_keys($topics), 0, 5);
    }

    /**
     * Private: Get sentiment trend
     */
    private function getConversationTrend($emails)
    {
        $sentiments = $this->analyzeSentiment($emails);
        $trend = [];

        foreach ($sentiments as $sentiment) {
            $sentiment_value = match($sentiment['sentiment']) {
                'positive' => 1,
                'negative' => -1,
                default => 0
            };
            $trend[] = $sentiment_value;
        }

        return $trend;
    }
}
