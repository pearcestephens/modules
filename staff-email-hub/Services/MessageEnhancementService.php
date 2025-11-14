<?php
/**
 * AI Message Enhancement Service
 *
 * Leverages GPT/Claude to enhance email messages with:
 * - Tone adjustment (professional, friendly, formal, casual, warm)
 * - Length optimization (expand, condense, summarize)
 * - Grammar and clarity checking
 * - Professionalism scoring
 * - Before/after comparison
 * - Approval workflow
 *
 * Features:
 * - Preserve message intent while improving presentation
 * - Context-aware enhancement based on conversation history
 * - Multiple tone options with preview
 * - Approval workflow before sending
 * - Enhancement history and audit trail
 * - Safe HTML rendering with sanitization
 *
 * @package StaffEmailHub\Services
 */

namespace StaffEmailHub\Services;

class MessageEnhancementService
{
    private $db;
    private $logger;
    private $openaiApiKey;
    private $staffId;

    const TONE_PROFESSIONAL = 'professional';
    const TONE_FRIENDLY = 'friendly';
    const TONE_FORMAL = 'formal';
    const TONE_CASUAL = 'casual';
    const TONE_WARM = 'warm';

    const LENGTH_EXPAND = 'expand';
    const LENGTH_CONDENSE = 'condense';
    const LENGTH_SUMMARIZE = 'summarize';

    public function __construct($db, $logger, $staffId, $openaiApiKey = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
        $this->openaiApiKey = $openaiApiKey ?? getenv('OPENAI_API_KEY');
    }

    /**
     * Enhance email message with AI
     */
    public function enhanceMessage($messageText, $tone = self::TONE_PROFESSIONAL, $lengthAdjustment = null, $context = [])
    {
        try {
            if (!$this->openaiApiKey) {
                return [
                    'success' => false,
                    'message' => 'AI enhancement not configured'
                ];
            }

            // Build enhancement prompt
            $prompt = $this->buildEnhancementPrompt(
                $messageText,
                $tone,
                $lengthAdjustment,
                $context
            );

            // Call OpenAI API
            $enhanced = $this->callOpenAiApi($prompt);

            if (!$enhanced) {
                return [
                    'success' => false,
                    'message' => 'AI service unavailable'
                ];
            }

            // Score the original and enhanced versions
            $originalScore = $this->calculateProfessionalismScore($messageText);
            $enhancedScore = $this->calculateProfessionalismScore($enhanced);

            $this->logger.info('Message enhanced', [
                'staff_id' => $this->staffId,
                'tone' => $tone,
                'original_length' => strlen($messageText),
                'enhanced_length' => strlen($enhanced),
                'original_score' => $originalScore,
                'enhanced_score' => $enhancedScore
            ]);

            return [
                'success' => true,
                'original' => $messageText,
                'enhanced' => $enhanced,
                'tone' => $tone,
                'original_score' => $originalScore,
                'enhanced_score' => $enhancedScore,
                'improvement' => $enhancedScore - $originalScore
            ];
        } catch (\Exception $e) {
            $this->logger->error('Message enhancement failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Generate multiple tone variations
     */
    public function generateToneVariations($messageText, $context = [])
    {
        try {
            $tones = [
                self::TONE_PROFESSIONAL,
                self::TONE_FRIENDLY,
                self::TONE_FORMAL,
                self::TONE_CASUAL,
                self::TONE_WARM
            ];

            $variations = [];

            foreach ($tones as $tone) {
                $enhanced = $this->enhanceMessage($messageText, $tone, null, $context);
                if ($enhanced['success']) {
                    $variations[$tone] = $enhanced['enhanced'];
                }
            }

            return [
                'success' => !empty($variations),
                'original' => $messageText,
                'variations' => $variations,
                'available_tones' => array_keys($variations)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check grammar and clarity
     */
    public function checkGrammarAndClarity($messageText)
    {
        try {
            $prompt = "Analyze the following email for grammar, clarity, and professionalism issues.
                      Return ONLY a JSON object with:
                      {
                        'issues': [{'type': 'grammar'|'clarity'|'tone', 'position': 'word/phrase', 'suggestion': 'corrected text', 'severity': 'high'|'medium'|'low'}],
                        'overall_clarity': 0-100,
                        'readability_score': 0-100,
                        'summary': 'brief assessment'
                      }

                      Email: {$messageText}";

            $response = $this->callOpenAiApi($prompt);

            if (!$response) {
                return ['success' => false, 'message' => 'Grammar check failed'];
            }

            // Parse JSON response
            $analysis = json_decode($response, true);

            if (!$analysis) {
                return ['success' => false, 'message' => 'Invalid response format'];
            }

            return [
                'success' => true,
                'issues' => $analysis['issues'] ?? [],
                'clarity_score' => $analysis['overall_clarity'] ?? 0,
                'readability_score' => $analysis['readability_score'] ?? 0,
                'summary' => $analysis['summary'] ?? ''
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Store enhancement suggestion for approval
     */
    public function storeEnhancementForApproval($emailId, $originalMessage, $enhancedMessage, $tone, $metadata = [])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_enhancements
                (email_id, staff_id, original_message, enhanced_message, tone,
                 metadata, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending_approval', NOW())
            ");

            $stmt->execute([
                $emailId,
                $this->staffId,
                $originalMessage,
                $enhancedMessage,
                $tone,
                json_encode($metadata)
            ]);

            $enhancementId = $this->db->lastInsertId();

            $this->logger->info('Enhancement stored for approval', [
                'enhancement_id' => $enhancementId,
                'email_id' => $emailId
            ]);

            return [
                'success' => true,
                'enhancement_id' => $enhancementId,
                'status' => 'pending_approval'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Approve and apply enhancement
     */
    public function approveEnhancement($enhancementId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE email_enhancements
                SET status = 'approved', approved_at = NOW()
                WHERE id = ? AND staff_id = ?
            ");

            $stmt->execute([$enhancementId, $this->staffId]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Enhancement not found or no permission'];
            }

            $this->logger->info('Enhancement approved', [
                'enhancement_id' => $enhancementId,
                'staff_id' => $this->staffId
            ]);

            return ['success' => true, 'status' => 'approved'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reject enhancement
     */
    public function rejectEnhancement($enhancementId, $reason = null)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE email_enhancements
                SET status = 'rejected', rejection_reason = ?, rejected_at = NOW()
                WHERE id = ? AND staff_id = ?
            ");

            $stmt->execute([$reason, $enhancementId, $this->staffId]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Enhancement not found'];
            }

            $this->logger->info('Enhancement rejected', [
                'enhancement_id' => $enhancementId,
                'reason' => $reason
            ]);

            return ['success' => true, 'status' => 'rejected'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get pending enhancements for review
     */
    public function getPendingEnhancements($limit = 20)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    ee.id,
                    ee.email_id,
                    ee.original_message,
                    ee.enhanced_message,
                    ee.tone,
                    ee.created_at,
                    e.from_address,
                    e.to_address,
                    e.subject
                FROM email_enhancements ee
                LEFT JOIN emails e ON ee.email_id = e.id
                WHERE ee.staff_id = ? AND ee.status = 'pending_approval'
                ORDER BY ee.created_at DESC
                LIMIT ?
            ");

            $stmt->execute([$this->staffId, $limit]);
            return [
                'success' => true,
                'pending' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get enhancement history
     */
    public function getEnhancementHistory($emailId = null, $limit = 50)
    {
        try {
            if ($emailId) {
                $stmt = $this->db->prepare("
                    SELECT * FROM email_enhancements
                    WHERE staff_id = ? AND email_id = ?
                    ORDER BY created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$this->staffId, $emailId, $limit]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT * FROM email_enhancements
                    WHERE staff_id = ?
                    ORDER BY created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$this->staffId, $limit]);
            }

            return [
                'success' => true,
                'history' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Private: Build enhancement prompt for OpenAI
     */
    private function buildEnhancementPrompt($messageText, $tone, $lengthAdjustment, $context)
    {
        $prompt = "Rewrite the following email message:\n\n";
        $prompt .= "Original Message:\n{$messageText}\n\n";

        $prompt .= "Requirements:\n";
        $prompt .= "1. Tone: Make it {$tone}\n";

        if ($lengthAdjustment) {
            $prompt .= "2. Length: " . match($lengthAdjustment) {
                self::LENGTH_EXPAND => "Expand the message with more detail while maintaining clarity",
                self::LENGTH_CONDENSE => "Condense to the essential points while preserving meaning",
                self::LENGTH_SUMMARIZE => "Create a brief summary version",
                default => ""
            } . "\n";
        }

        $prompt .= "3. Preserve the original intent and meaning\n";
        $prompt .= "4. Maintain professional standards\n";
        $prompt .= "5. Return ONLY the rewritten message, no explanations\n";

        if (!empty($context)) {
            $prompt .= "\nContext:\n";
            if (isset($context['recipient'])) {
                $prompt .= "Recipient: {$context['recipient']}\n";
            }
            if (isset($context['subject'])) {
                $prompt .= "Subject: {$context['subject']}\n";
            }
            if (isset($context['previous_messages'])) {
                $prompt .= "Previous messages: {$context['previous_messages']}\n";
            }
        }

        return $prompt;
    }

    /**
     * Private: Call OpenAI API
     */
    private function callOpenAiApi($prompt, $maxTokens = 1000)
    {
        try {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');

            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->openaiApiKey
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'gpt-4-turbo-preview',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an expert email writing assistant. Enhance emails while preserving intent.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.7
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->logger->error('OpenAI API error', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                return null;
            }

            $decoded = json_decode($response, true);
            return $decoded['choices'][0]['message']['content'] ?? null;
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API call failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Private: Calculate professionalism score
     */
    private function calculateProfessionalismScore($text)
    {
        $score = 50; // Base score

        // Check for formal language
        $formalWords = ['sincerely', 'regards', 'furthermore', 'nevertheless', 'therefore', 'whereas'];
        foreach ($formalWords as $word) {
            if (stripos($text, $word) !== false) {
                $score += 5;
            }
        }

        // Deduct for casual language
        $casualWords = ['lol', 'omg', 'gonna', 'wanna', 'hey', 'hey there', 'sup'];
        foreach ($casualWords as $word) {
            if (stripos($text, $word) !== false) {
                $score -= 10;
            }
        }

        // Deduct for excessive punctuation
        if (substr_count($text, '!') > 3) {
            $score -= 10;
        }
        if (substr_count($text, '?') > 3) {
            $score -= 5;
        }

        // Bonus for proper grammar indicators
        if (preg_match('/\b(would|could|may|might|shall)\b/i', $text)) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }
}
