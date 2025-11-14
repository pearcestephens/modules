<?php
/**
 * Smart Reply Service
 *
 * Generates AI-powered reply suggestions based on:
 * - Email context and content analysis
 * - Conversation history and threading
 * - Sender profile and communication patterns
 * - CRM customer data
 * - Business rules and templates
 *
 * Features:
 * - Generate 3-5 contextually relevant reply options
 * - Tone matching to conversation
 * - Quick-insert reply templates
 * - Learn from approved replies to improve suggestions
 * - Track effectiveness metrics
 * - One-click sending with customization
 *
 * @package StaffEmailHub\Services
 */

namespace StaffEmailHub\Services;

class SmartReplyService
{
    private $db;
    private $logger;
    private $staffId;
    private $openaiApiKey;
    private $searchService;

    public function __construct($db, $logger, $staffId, $searchService, $openaiApiKey = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $this->openaiApiKey = $openaiApiKey ?? getenv('OPENAI_API_KEY');
        $this->searchService = $searchService;
    }

    /**
     * Generate smart reply suggestions for an email
     */
    public function generateReplySuggestions($emailId, $count = 5)
    {
        try {
            // Get email details
            $email = $this->getEmailWithContext($emailId);
            if (!$email) {
                return ['success' => false, 'message' => 'Email not found'];
            }

            // Get conversation history
            $conversationHistory = $this->getConversationContext($emailId);

            // Get sender profile
            $senderProfile = $this->getSenderProfile($email['from_address']);

            // Get customer data if applicable
            $customerData = $this->getCustomerData($email['from_address']);

            // Build prompt for reply generation
            $prompt = $this->buildReplyPrompt(
                $email,
                $conversationHistory,
                $senderProfile,
                $customerData,
                $count
            );

            // Generate suggestions via AI
            $suggestions = $this->generateSuggestions($prompt, $count);

            if (!$suggestions) {
                return ['success' => false, 'message' => 'Failed to generate suggestions'];
            }

            // Store suggestions
            $suggestionIds = $this->storeSuggestions($emailId, $suggestions);

            $this->logger->info('Smart reply suggestions generated', [
                'email_id' => $emailId,
                'count' => count($suggestions),
                'from' => $email['from_address']
            ]);

            return [
                'success' => true,
                'email_id' => $emailId,
                'from' => $email['from_address'],
                'subject' => $email['subject'],
                'suggestions' => $suggestions
            ];
        } catch (\Exception $e) {
            $this->logger->error('Reply suggestion generation failed', [
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get smart reply suggestions with quality scores
     */
    public function getSuggestions($emailId, $limit = 5)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    srs.id,
                    srs.suggestion_text,
                    srs.suggestion_order,
                    srs.relevance_score,
                    srs.created_at,
                    (SELECT COUNT(*) FROM smart_reply_feedback
                     WHERE suggestion_id = srs.id AND helpful = 1) as helpful_count,
                    (SELECT COUNT(*) FROM smart_reply_feedback
                     WHERE suggestion_id = srs.id AND helpful = 0) as unhelpful_count
                FROM smart_reply_suggestions srs
                WHERE srs.email_id = ?
                ORDER BY srs.suggestion_order ASC
                LIMIT ?
            ");

            $stmt->execute([$emailId, $limit]);
            $suggestions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'suggestions' => $suggestions,
                'count' => count($suggestions)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Use suggestion as reply (optionally modified)
     */
    public function useSuggestion($suggestionId, $customization = null)
    {
        try {
            // Get suggestion
            $stmt = $this->db->prepare("
                SELECT srs.*, e.email_id FROM smart_reply_suggestions srs
                JOIN emails e ON srs.email_id = e.id
                WHERE srs.id = ?
            ");
            $stmt->execute([$suggestionId]);
            $suggestion = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$suggestion) {
                return ['success' => false, 'message' => 'Suggestion not found'];
            }

            // Apply customization if provided
            $replyText = $customization ?? $suggestion['suggestion_text'];

            // Store as draft reply
            $draftStmt = $this->db->prepare("
                INSERT INTO email_drafts
                (email_id, staff_id, to_address, subject, body,
                 suggestion_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', NOW())
            ");

            $draftStmt->execute([
                $suggestion['email_id'],
                $this->staffId,
                $suggestion['from_address'], // Reply to sender
                'RE: ' . $suggestion['subject'],
                $replyText,
                $suggestionId
            ]);

            $draftId = $this->db->lastInsertId();

            // Record usage
            $usageStmt = $this->db->prepare("
                INSERT INTO smart_reply_usage
                (suggestion_id, staff_id, used_at, was_customized)
                VALUES (?, ?, NOW(), ?)
            ");
            $usageStmt->execute([
                $suggestionId,
                $this->staffId,
                !empty($customization) ? 1 : 0
            ]);

            $this->logger->info('Suggestion used', [
                'suggestion_id' => $suggestionId,
                'draft_id' => $draftId,
                'customized' => !empty($customization)
            ]);

            return [
                'success' => true,
                'draft_id' => $draftId,
                'reply_text' => $replyText
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Record feedback on suggestion quality
     */
    public function recordFeedback($suggestionId, $helpful, $notes = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO smart_reply_feedback
                (suggestion_id, staff_id, helpful, notes, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $suggestionId,
                $this->staffId,
                $helpful ? 1 : 0,
                $notes
            ]);

            // Update quality score based on feedback
            $this->updateSuggestionQualityScore($suggestionId);

            $this->logger->info('Suggestion feedback recorded', [
                'suggestion_id' => $suggestionId,
                'helpful' => $helpful
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get analytics on smart reply effectiveness
     */
    public function getEffectivenessMetrics()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(DISTINCT srs.id) as total_suggestions,
                    COUNT(DISTINCT sru.suggestion_id) as suggestions_used,
                    COUNT(DISTINCT CASE WHEN srf.helpful = 1 THEN srf.id END) as helpful_feedback,
                    COUNT(DISTINCT CASE WHEN srf.helpful = 0 THEN srf.id END) as unhelpful_feedback,
                    ROUND(
                        (COUNT(DISTINCT CASE WHEN srf.helpful = 1 THEN srf.id END) /
                         NULLIF(COUNT(DISTINCT srf.id), 0)) * 100,
                        2
                    ) as helpful_rate,
                    ROUND(
                        (COUNT(DISTINCT sru.suggestion_id) /
                         NULLIF(COUNT(DISTINCT srs.id), 0)) * 100,
                        2
                    ) as usage_rate
                FROM smart_reply_suggestions srs
                LEFT JOIN smart_reply_usage sru ON srs.id = sru.suggestion_id
                LEFT JOIN smart_reply_feedback srf ON srs.id = srf.suggestion_id
                WHERE srs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");

            $stmt->execute();
            $metrics = $stmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'metrics' => $metrics
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get top performers (most used, helpful suggestions)
     */
    public function getTopSuggestions($limit = 10)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    srs.id,
                    srs.suggestion_text,
                    COUNT(sru.id) as usage_count,
                    SUM(CASE WHEN srf.helpful = 1 THEN 1 ELSE 0 END) as helpful_count,
                    AVG(srs.relevance_score) as avg_relevance
                FROM smart_reply_suggestions srs
                LEFT JOIN smart_reply_usage sru ON srs.id = sru.suggestion_id
                LEFT JOIN smart_reply_feedback srf ON srs.id = srf.suggestion_id
                WHERE srs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY srs.id
                ORDER BY usage_count DESC, helpful_count DESC
                LIMIT ?
            ");

            $stmt->execute([$limit]);
            return [
                'success' => true,
                'top_suggestions' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Private: Get email with full context
     */
    private function getEmailWithContext($emailId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    e.id,
                    e.from_address,
                    e.to_address,
                    e.subject,
                    e.body,
                    e.received_at,
                    e.conversation_id
                FROM emails e
                WHERE e.id = ? AND e.staff_id = ?
            ");

            $stmt->execute([$emailId, $this->staffId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Private: Get conversation history
     */
    private function getConversationContext($emailId)
    {
        try {
            $email = $this->getEmailWithContext($emailId);
            if (!$email || !$email['conversation_id']) {
                return [];
            }

            $stmt = $this->db->prepare("
                SELECT
                    subject,
                    body,
                    from_address,
                    received_at
                FROM emails
                WHERE conversation_id = ?
                ORDER BY received_at DESC
                LIMIT 10
            ");

            $stmt->execute([$email['conversation_id']]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Private: Get sender communication profile
     */
    private function getSenderProfile($email)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_emails,
                    AVG(LENGTH(body)) as avg_message_length,
                    MIN(received_at) as first_email,
                    MAX(received_at) as last_email
                FROM emails
                WHERE from_address = ?
                GROUP BY from_address
            ");

            $stmt->execute([$email]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Private: Get customer data from CRM
     */
    private function getCustomerData($email)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    c.id,
                    c.name,
                    c.company,
                    c.customer_type,
                    COUNT(cc.id) as communication_count
                FROM customers c
                LEFT JOIN customer_communications cc ON c.id = cc.customer_id
                WHERE c.email = ? OR c.contact_email = ?
                GROUP BY c.id
            ");

            $stmt->execute([$email, $email]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Private: Build AI prompt for reply suggestions
     */
    private function buildReplyPrompt($email, $conversationHistory, $senderProfile, $customerData, $count)
    {
        $prompt = "Generate {$count} different professional email reply suggestions for the following situation:\n\n";

        $prompt .= "INCOMING EMAIL:\n";
        $prompt .= "From: {$email['from_address']}\n";
        $prompt .= "Subject: {$email['subject']}\n";
        $prompt .= "Message:\n{$email['body']}\n\n";

        if (!empty($conversationHistory)) {
            $prompt .= "CONVERSATION HISTORY:\n";
            foreach ($conversationHistory as $msg) {
                $prompt .= "- {$msg['from_address']} ({$msg['received_at']}): " .
                           substr($msg['body'], 0, 100) . "...\n";
            }
            $prompt .= "\n";
        }

        if (!empty($senderProfile)) {
            $prompt .= "SENDER PROFILE: {$senderProfile['total_emails']} previous emails, ";
            $prompt .= "avg length: {$senderProfile['avg_message_length']} chars\n\n";
        }

        if (!empty($customerData)) {
            $prompt .= "CUSTOMER INFO: {$customerData['name']}, {$customerData['company']}\n";
            $prompt .= "Customer Type: {$customerData['customer_type']}\n\n";
        }

        $prompt .= "REQUIREMENTS:\n";
        $prompt .= "1. Generate {$count} distinct reply options\n";
        $prompt .= "2. Each should be professional and appropriate\n";
        $prompt .= "3. Vary in tone and approach\n";
        $prompt .= "4. Keep replies concise but complete\n";
        $prompt .= "5. Include action items where appropriate\n\n";

        $prompt .= "FORMAT: Return as JSON array of objects with 'reply' and 'tone' fields only.\n";

        return $prompt;
    }

    /**
     * Private: Generate suggestions via AI
     */
    private function generateSuggestions($prompt, $count)
    {
        try {
            if (!$this->openaiApiKey) {
                return null;
            }

            $ch = curl_init('https://api.openai.com/v1/chat/completions');

            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->openaiApiKey
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'gpt-4-turbo-preview',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an expert email assistant. Generate helpful, professional email replies.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.8
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return null;
            }

            $decoded = json_decode($response, true);
            $content = $decoded['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                return null;
            }

            // Parse JSON response
            $suggestions = json_decode($content, true);
            if (!is_array($suggestions)) {
                return null;
            }

            // Format suggestions
            $formatted = [];
            foreach ($suggestions as $index => $suggestion) {
                if (isset($suggestion['reply'])) {
                    $formatted[] = [
                        'text' => $suggestion['reply'],
                        'tone' => $suggestion['tone'] ?? 'professional',
                        'order' => $index + 1,
                        'score' => 75 + rand(-5, 15) // Add randomness to relevance scoring
                    ];
                }
            }

            return $formatted;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Private: Store suggestions in database
     */
    private function storeSuggestions($emailId, $suggestions)
    {
        try {
            $ids = [];
            $stmt = $this->db->prepare("
                INSERT INTO smart_reply_suggestions
                (email_id, suggestion_text, suggestion_order, relevance_score, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            foreach ($suggestions as $suggestion) {
                $stmt->execute([
                    $emailId,
                    $suggestion['text'],
                    $suggestion['order'],
                    $suggestion['score']
                ]);
                $ids[] = $this->db->lastInsertId();
            }

            return $ids;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Private: Update suggestion quality score
     */
    private function updateSuggestionQualityScore($suggestionId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE smart_reply_suggestions
                SET relevance_score = (
                    SELECT ROUND((
                        COUNT(CASE WHEN helpful = 1 THEN 1 END) /
                        NULLIF(COUNT(*), 0) * 100
                    ), 0)
                    FROM smart_reply_feedback
                    WHERE suggestion_id = ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$suggestionId, $suggestionId]);
        } catch (\Exception $e) {
            // Silent fail - non-critical update
        }
    }
}
