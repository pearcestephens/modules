<?php
/**
 * Natural Language Processing - Communication Analysis Engine
 * Analyzes internal communications (email, chat, SMS) for fraud planning indicators
 *
 * Features:
 * - Real-time message analysis
 * - Keyword and phrase detection
 * - Sentiment analysis for stress/desperation
 * - Code word identification
 * - Collusion pattern detection
 * - Evidence preservation and timeline reconstruction
 * - Multi-source integration (Microsoft 365, Slack, Teams, Gmail, SMS)
 *
 * Detection Capabilities:
 * - Fraud planning ("cover for me at 3 AM")
 * - Evidence destruction ("delete those messages")
 * - Financial stress indicators ("need money fast")
 * - Code words ("special customer", "hook up")
 * - External coordination (resale platforms, competitors)
 *
 * @package FraudDetection
 * @version 2.0.0
 * @author Ecigdis Intelligence System
 */

namespace FraudDetection;

use PDO;
use Exception;

class CommunicationAnalysisEngine
{
    private PDO $db;
    private array $config;
    private array $integrations;
    private array $suspiciousPatterns;

    // Detection thresholds
    private const ALERT_THRESHOLD = 0.70;
    private const CRITICAL_THRESHOLD = 0.85;
    private const SENTIMENT_NEGATIVE_THRESHOLD = -0.60;

    // Message retention
    private const MAX_RETENTION_DAYS = 90;
    private const EVIDENCE_RETENTION_DAYS = 730; // 2 years

    /**
     * Suspicious keyword patterns with weights
     */
    private const FRAUD_PATTERNS = [
        'collusion' => [
            'cover for me' => 0.85,
            'can you help me with' => 0.60,
            'special customer' => 0.75,
            'family discount' => 0.70,
            'hook up' => 0.80,
            'do me a favor' => 0.55,
            'nobody needs to know' => 0.95,
            'between us' => 0.75,
            'our secret' => 0.90
        ],
        'evidence_destruction' => [
            'delete' => 0.70,
            'erase' => 0.75,
            'get rid of' => 0.80,
            'destroy the' => 0.90,
            'remove from system' => 0.95,
            'clear the logs' => 0.90,
            'wipe' => 0.75
        ],
        'off_hours_planning' => [
            'tonight after close' => 0.85,
            'when nobody is there' => 0.90,
            'early morning' => 0.60,
            'before opening' => 0.65,
            '3 AM' => 0.80,
            '4 AM' => 0.80,
            'after everyone leaves' => 0.75,
            'weekend when closed' => 0.70
        ],
        'financial_stress' => [
            'need money' => 0.75,
            'desperate' => 0.80,
            'rent is due' => 0.70,
            'can\'t afford' => 0.65,
            'in debt' => 0.70,
            'loan shark' => 0.85,
            'payday loan' => 0.75,
            'behind on payments' => 0.70,
            'going to lose' => 0.75
        ],
        'resentment' => [
            'they don\'t pay me enough' => 0.70,
            'screw this company' => 0.85,
            'they owe me' => 0.75,
            'deserve more' => 0.60,
            'hate this job' => 0.65,
            'stealing from me' => 0.80,
            'ripping us off' => 0.75
        ],
        'external_coordination' => [
            'facebook marketplace' => 0.70,
            'trademe' => 0.75,
            'selling on' => 0.80,
            'buyer lined up' => 0.85,
            'customer waiting' => 0.70,
            'ready to sell' => 0.80,
            'can flip these' => 0.85
        ],
        'discount_abuse' => [
            'how much discount' => 0.60,
            'can you give' => 0.55,
            'max discount is' => 0.65,
            'override code' => 0.80,
            'manager approval' => 0.60,
            'nobody will notice' => 0.90
        ]
    ];

    /**
     * Code words commonly used in retail fraud
     */
    private const CODE_WORDS = [
        'sample' => 'Taking inventory for personal use',
        'testing' => 'Using product without payment',
        'holding' => 'Setting aside for unauthorized discount',
        'broken' => 'False claim to void transaction',
        'comp' => 'Complimentary (unauthorized freebies)',
        'my friend' => 'Collusion target',
        'regular customer' => 'Frequent fraud collaborator',
        'special order' => 'Pre-arranged fraud transaction'
    ];

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'enable_real_time' => true,
            'enable_email_monitoring' => true,
            'enable_chat_monitoring' => true,
            'enable_sms_monitoring' => false, // Requires carrier integration
            'staff_consent_required' => true,
            'alert_managers' => true,
            'preserve_evidence' => true,
            'sentiment_api_key' => null,
            'translation_enabled' => false
        ], $config);

        $this->initializeIntegrations();
        $this->loadSuspiciousPatterns();
    }

    /**
     * Initialize communication platform integrations
     */
    private function initializeIntegrations(): void
    {
        $this->integrations = [
            'microsoft_365' => [
                'enabled' => !empty($_ENV['MICROSOFT_GRAPH_API_KEY']),
                'api_key' => $_ENV['MICROSOFT_GRAPH_API_KEY'] ?? null,
                'endpoint' => 'https://graph.microsoft.com/v1.0',
                'types' => ['email', 'teams_chat']
            ],
            'google_workspace' => [
                'enabled' => !empty($_ENV['GOOGLE_WORKSPACE_API_KEY']),
                'api_key' => $_ENV['GOOGLE_WORKSPACE_API_KEY'] ?? null,
                'endpoint' => 'https://www.googleapis.com/gmail/v1',
                'types' => ['email', 'chat']
            ],
            'slack' => [
                'enabled' => !empty($_ENV['SLACK_API_TOKEN']),
                'api_key' => $_ENV['SLACK_API_TOKEN'] ?? null,
                'endpoint' => 'https://slack.com/api',
                'types' => ['channel', 'direct_message']
            ],
            'internal_messaging' => [
                'enabled' => true,
                'database_table' => 'internal_messages',
                'types' => ['internal_chat']
            ]
        ];
    }

    /**
     * Load and compile suspicious pattern database
     */
    private function loadSuspiciousPatterns(): void
    {
        // Load patterns from database (allows dynamic updates)
        $sql = "
            SELECT
                pattern_category,
                pattern_text,
                risk_weight,
                case_sensitive
            FROM communication_fraud_patterns
            WHERE active = 1
        ";

        $stmt = $this->db->query($sql);
        $dbPatterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Merge with hardcoded patterns
        $this->suspiciousPatterns = self::FRAUD_PATTERNS;

        foreach ($dbPatterns as $pattern) {
            $category = $pattern['pattern_category'];
            if (!isset($this->suspiciousPatterns[$category])) {
                $this->suspiciousPatterns[$category] = [];
            }
            $this->suspiciousPatterns[$category][$pattern['pattern_text']] = (float)$pattern['risk_weight'];
        }
    }

    /**
     * Analyze specific message for fraud indicators
     *
     * @param array $message Message data (text, sender, recipient, timestamp, platform)
     * @return array Analysis results
     */
    public function analyzeMessage(array $message): array
    {
        $required = ['text', 'sender_id', 'timestamp'];
        foreach ($required as $field) {
            if (!isset($message[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $text = $message['text'];
        $sender = $message['sender_id'];
        $recipients = $message['recipients'] ?? [];

        // Pattern matching
        $patternMatches = $this->detectPatterns($text);

        // Code word detection
        $codeWords = $this->detectCodeWords($text);

        // Sentiment analysis
        $sentiment = $this->analyzeSentiment($text);

        // Context analysis (time of message, participants)
        $context = $this->analyzeContext($message);

        // Calculate composite risk score
        $riskScore = $this->calculateRiskScore($patternMatches, $codeWords, $sentiment, $context);

        // Check for collusion (multiple participants)
        $collusionIndicators = $this->detectCollusion($sender, $recipients, $text);

        // Generate alert if threshold exceeded
        $alert = null;
        if ($riskScore['total_score'] >= self::ALERT_THRESHOLD) {
            $alert = $this->generateAlert($message, $riskScore, $patternMatches, $codeWords);
        }

        // Store analysis
        $this->storeAnalysis($message, $riskScore, $patternMatches, $codeWords, $sentiment);

        // Preserve evidence if critical
        if ($riskScore['total_score'] >= self::CRITICAL_THRESHOLD) {
            $this->preserveEvidence($message, $riskScore);
        }

        return [
            'success' => true,
            'message_id' => $message['message_id'] ?? uniqid('msg_'),
            'sender_id' => $sender,
            'risk_score' => $riskScore,
            'pattern_matches' => $patternMatches,
            'code_words_detected' => $codeWords,
            'sentiment' => $sentiment,
            'context_analysis' => $context,
            'collusion_indicators' => $collusionIndicators,
            'alert_generated' => $alert !== null,
            'alert_details' => $alert,
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Monitor all communications for specific staff member
     *
     * @param int $staffId Staff member to monitor
     * @param int $days Number of days to analyze
     * @return array Monitoring results
     */
    public function monitorStaffCommunications(int $staffId, int $days = 30): array
    {
        $messages = $this->retrieveStaffMessages($staffId, $days);

        if (empty($messages)) {
            return [
                'success' => true,
                'staff_id' => $staffId,
                'messages_analyzed' => 0,
                'risk_level' => 'NONE',
                'message' => 'No communications found for staff member'
            ];
        }

        $analyses = [];
        $totalRiskScore = 0.0;
        $highRiskMessages = [];
        $suspiciousPatterns = [];

        foreach ($messages as $message) {
            $analysis = $this->analyzeMessage($message);

            if ($analysis['success']) {
                $analyses[] = $analysis;
                $totalRiskScore += $analysis['risk_score']['total_score'];

                if ($analysis['risk_score']['total_score'] >= self::ALERT_THRESHOLD) {
                    $highRiskMessages[] = [
                        'message_id' => $analysis['message_id'],
                        'timestamp' => $message['timestamp'],
                        'risk_score' => $analysis['risk_score']['total_score'],
                        'primary_concerns' => array_keys($analysis['pattern_matches'])
                    ];
                }

                // Aggregate pattern types
                foreach ($analysis['pattern_matches'] as $category => $matches) {
                    if (!isset($suspiciousPatterns[$category])) {
                        $suspiciousPatterns[$category] = 0;
                    }
                    $suspiciousPatterns[$category] += count($matches);
                }
            }
        }

        $avgRiskScore = count($analyses) > 0 ? $totalRiskScore / count($analyses) : 0.0;

        // Detect communication patterns
        $temporalPatterns = $this->analyzeTemporalPatterns($messages);
        $networkAnalysis = $this->analyzeCommun icationNetwork($staffId, $messages);

        return [
            'success' => true,
            'staff_id' => $staffId,
            'monitoring_period_days' => $days,
            'messages_analyzed' => count($messages),
            'avg_risk_score' => round($avgRiskScore, 3),
            'overall_risk_level' => $this->determineRiskLevel($avgRiskScore),
            'high_risk_messages' => $highRiskMessages,
            'suspicious_pattern_summary' => $suspiciousPatterns,
            'temporal_patterns' => $temporalPatterns,
            'communication_network' => $networkAnalysis,
            'recommendations' => $this->generateRecommendations($avgRiskScore, $suspiciousPatterns)
        ];
    }

    /**
     * Batch analyze all staff communications (daily sweep)
     *
     * @return array Batch analysis results
     */
    public function dailyCommunicationSweep(): array
    {
        $startTime = microtime(true);

        // Get all active staff
        $sql = "
            SELECT staff_id, staff_name, email
            FROM staff
            WHERE active = 1
                AND monitoring_consent = 1
        ";

        $stmt = $this->db->query($sql);
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $highRiskStaff = [];

        foreach ($staff as $member) {
            $analysis = $this->monitorStaffCommunications($member['staff_id'], 1); // Last 24 hours

            if ($analysis['success']) {
                $results[] = $analysis;

                if ($analysis['avg_risk_score'] >= self::ALERT_THRESHOLD) {
                    $highRiskStaff[] = [
                        'staff_id' => $member['staff_id'],
                        'staff_name' => $member['staff_name'],
                        'risk_score' => $analysis['avg_risk_score'],
                        'high_risk_messages' => count($analysis['high_risk_messages'])
                    ];
                }
            }
        }

        $processingTime = microtime(true) - $startTime;

        return [
            'success' => true,
            'sweep_type' => 'daily',
            'staff_monitored' => count($staff),
            'total_messages_analyzed' => array_sum(array_column($results, 'messages_analyzed')),
            'high_risk_staff_count' => count($highRiskStaff),
            'high_risk_staff' => $highRiskStaff,
            'processing_time_seconds' => round($processingTime, 2),
            'completed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Real-time message monitoring webhook handler
     * Processes incoming messages from integrated platforms
     *
     * @param string $platform Source platform (microsoft_365, slack, etc)
     * @param array $webhookData Webhook payload
     * @return array Processing result
     */
    public function processWebhookMessage(string $platform, array $webhookData): array
    {
        if (!$this->config['enable_real_time']) {
            return [
                'success' => false,
                'error' => 'Real-time monitoring disabled'
            ];
        }

        // Parse webhook data based on platform
        $message = $this->parseWebhookData($platform, $webhookData);

        if (!$message) {
            return [
                'success' => false,
                'error' => 'Unable to parse webhook data',
                'platform' => $platform
            ];
        }

        // Check if sender is monitored staff
        $staffId = $this->resolveStaffId($message['sender_email'] ?? $message['sender_id']);

        if (!$staffId) {
            // Not a staff member, ignore
            return [
                'success' => true,
                'action' => 'ignored',
                'reason' => 'Sender not a staff member'
            ];
        }

        $message['sender_id'] = $staffId;

        // Analyze immediately
        $analysis = $this->analyzeMessage($message);

        // Send real-time alert if needed
        if ($analysis['alert_generated']) {
            $this->sendRealTimeAlert($analysis);
        }

        return [
            'success' => true,
            'action' => 'analyzed',
            'staff_id' => $staffId,
            'risk_score' => $analysis['risk_score']['total_score'],
            'alert_sent' => $analysis['alert_generated']
        ];
    }

    /**
     * Detect suspicious patterns in text
     *
     * @param string $text Message text
     * @return array Detected patterns with scores
     */
    private function detectPatterns(string $text): array
    {
        $text = strtolower($text);
        $matches = [];

        foreach ($this->suspiciousPatterns as $category => $patterns) {
            $categoryMatches = [];

            foreach ($patterns as $pattern => $weight) {
                if (stripos($text, strtolower($pattern)) !== false) {
                    $categoryMatches[] = [
                        'pattern' => $pattern,
                        'weight' => $weight,
                        'context' => $this->extractContext($text, $pattern)
                    ];
                }
            }

            if (!empty($categoryMatches)) {
                $matches[$category] = $categoryMatches;
            }
        }

        return $matches;
    }

    /**
     * Detect code words in text
     *
     * @param string $text Message text
     * @return array Detected code words
     */
    private function detectCodeWords(string $text): array
    {
        $text = strtolower($text);
        $detected = [];

        foreach (self::CODE_WORDS as $codeWord => $meaning) {
            if (stripos($text, $codeWord) !== false) {
                $detected[] = [
                    'code_word' => $codeWord,
                    'likely_meaning' => $meaning,
                    'confidence' => 0.70,
                    'context' => $this->extractContext($text, $codeWord)
                ];
            }
        }

        return $detected;
    }

    /**
     * Analyze sentiment of message text
     * Uses external API or local ML model
     *
     * @param string $text Message text
     * @return array Sentiment analysis
     */
    private function analyzeSentiment(string $text): array
    {
        // Simple sentiment analysis (in production, use proper NLP API)
        $negativeWords = [
            'hate', 'angry', 'frustrated', 'stressed', 'desperate',
            'anxious', 'worried', 'scared', 'afraid', 'furious'
        ];

        $positiveWords = [
            'happy', 'great', 'good', 'excellent', 'wonderful',
            'pleased', 'satisfied', 'grateful', 'thankful'
        ];

        $text = strtolower($text);
        $words = str_word_count($text, 1);

        $negativeCount = 0;
        $positiveCount = 0;

        foreach ($words as $word) {
            if (in_array($word, $negativeWords)) {
                $negativeCount++;
            }
            if (in_array($word, $positiveWords)) {
                $positiveCount++;
            }
        }

        $totalEmotionalWords = $negativeCount + $positiveCount;
        $sentimentScore = $totalEmotionalWords > 0
            ? (($positiveCount - $negativeCount) / $totalEmotionalWords)
            : 0.0;

        $polarity = 'neutral';
        if ($sentimentScore < -0.3) $polarity = 'negative';
        if ($sentimentScore < -0.6) $polarity = 'very_negative';
        if ($sentimentScore > 0.3) $polarity = 'positive';
        if ($sentimentScore > 0.6) $polarity = 'very_positive';

        return [
            'score' => round($sentimentScore, 3),
            'polarity' => $polarity,
            'negative_words_found' => $negativeCount,
            'positive_words_found' => $positiveCount,
            'stress_indicator' => $sentimentScore < self::SENTIMENT_NEGATIVE_THRESHOLD
        ];
    }

    /**
     * Analyze message context (timing, participants, etc)
     *
     * @param array $message Message data
     * @return array Context analysis
     */
    private function analyzeContext(array $message): array
    {
        $timestamp = strtotime($message['timestamp']);
        $hour = (int)date('H', $timestamp);
        $dayOfWeek = (int)date('N', $timestamp);

        $contextFlags = [];
        $contextScore = 0.0;

        // Off-hours communication (10 PM - 6 AM)
        if ($hour >= 22 || $hour <= 6) {
            $contextFlags[] = 'off_hours_communication';
            $contextScore += 0.30;
        }

        // Weekend communication (if unusual)
        if ($dayOfWeek >= 6) {
            $contextFlags[] = 'weekend_communication';
            $contextScore += 0.15;
        }

        // Multiple recipients (potential collusion)
        $recipientCount = count($message['recipients'] ?? []);
        if ($recipientCount >= 3) {
            $contextFlags[] = 'multiple_recipients';
            $contextScore += 0.20;
        }

        // External recipients (non-company emails)
        if (isset($message['recipients'])) {
            foreach ($message['recipients'] as $recipient) {
                if (!$this->isInternalEmail($recipient)) {
                    $contextFlags[] = 'external_recipient';
                    $contextScore += 0.25;
                    break;
                }
            }
        }

        return [
            'timestamp' => $message['timestamp'],
            'hour' => $hour,
            'day_of_week' => $dayOfWeek,
            'recipient_count' => $recipientCount,
            'flags' => $contextFlags,
            'context_score' => min(1.0, $contextScore)
        ];
    }

    /**
     * Calculate composite risk score
     *
     * @param array $patterns Pattern matches
     * @param array $codeWords Code words detected
     * @param array $sentiment Sentiment analysis
     * @param array $context Context analysis
     * @return array Risk score breakdown
     */
    private function calculateRiskScore(array $patterns, array $codeWords, array $sentiment, array $context): array
    {
        $patternScore = 0.0;
        $patternCount = 0;

        foreach ($patterns as $category => $matches) {
            foreach ($matches as $match) {
                $patternScore += $match['weight'];
                $patternCount++;
            }
        }

        $patternScore = $patternCount > 0 ? $patternScore / $patternCount : 0.0;

        $codeWordScore = count($codeWords) > 0 ? 0.70 : 0.0;

        $sentimentScore = $sentiment['stress_indicator'] ? 0.30 : 0.0;

        $contextScore = $context['context_score'];

        // Weighted composite
        $totalScore = (
            ($patternScore * 0.50) +
            ($codeWordScore * 0.20) +
            ($sentimentScore * 0.15) +
            ($contextScore * 0.15)
        );

        return [
            'total_score' => round(min(1.0, $totalScore), 3),
            'risk_level' => $this->determineRiskLevel($totalScore),
            'pattern_score' => round($patternScore, 3),
            'code_word_score' => round($codeWordScore, 3),
            'sentiment_score' => round($sentimentScore, 3),
            'context_score' => round($contextScore, 3),
            'pattern_count' => $patternCount,
            'code_word_count' => count($codeWords)
        ];
    }

    /**
     * Detect collusion indicators between staff members
     *
     * @param int $sender Sender staff ID
     * @param array $recipients Recipient IDs/emails
     * @param string $text Message text
     * @return array Collusion indicators
     */
    private function detectCollusion(int $sender, array $recipients, string $text): array
    {
        $indicators = [];

        // Check if recipients are also staff
        $staffRecipients = [];
        foreach ($recipients as $recipient) {
            $recipientStaffId = $this->resolveStaffId($recipient);
            if ($recipientStaffId && $recipientStaffId !== $sender) {
                $staffRecipients[] = $recipientStaffId;
            }
        }

        if (empty($staffRecipients)) {
            return [
                'detected' => false,
                'indicators' => []
            ];
        }

        // Check for previous suspicious communications between these parties
        $sql = "
            SELECT COUNT(*) as prev_suspicious_count
            FROM communication_analysis
            WHERE ((sender_id = :sender AND recipient_id IN (" . implode(',', $staffRecipients) . "))
                OR (sender_id IN (" . implode(',', $staffRecipients) . ") AND recipient_id = :sender))
                AND risk_score >= :threshold
                AND analyzed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'sender' => $sender,
            'threshold' => self::ALERT_THRESHOLD
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $previousSuspicious = $result['prev_suspicious_count'] ?? 0;

        if ($previousSuspicious > 0) {
            $indicators[] = [
                'type' => 'repeat_suspicious_communication',
                'description' => "Previous suspicious communications detected between these parties",
                'count' => $previousSuspicious,
                'severity' => 'HIGH'
            ];
        }

        // Check if they work at same location
        $sharedLocations = $this->checkSharedLocations($sender, $staffRecipients);
        if (!empty($sharedLocations)) {
            $indicators[] = [
                'type' => 'same_location',
                'description' => "Parties work at same location(s)",
                'locations' => $sharedLocations,
                'severity' => 'MEDIUM'
            ];
        }

        // Check if message contains coordinat ion keywords
        $coordinationKeywords = ['meet', 'tonight', 'plan', 'together', 'help me', 'cover', 'split'];
        foreach ($coordinationKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $indicators[] = [
                    'type' => 'coordination_keyword',
                    'description' => "Coordination keyword detected: $keyword",
                    'keyword' => $keyword,
                    'severity' => 'MEDIUM'
                ];
            }
        }

        return [
            'detected' => !empty($indicators),
            'staff_recipients' => $staffRecipients,
            'indicator_count' => count($indicators),
            'indicators' => $indicators,
            'collusion_risk_score' => $this->calculateCollusionRisk($indicators)
        ];
    }

    // ========== UTILITY METHODS ==========

    private function retrieveStaffMessages(int $staffId, int $days): array
    {
        // Retrieve from all integrated platforms
        $messages = [];

        // Internal messages
        $sql = "
            SELECT
                message_id,
                sender_id,
                recipients,
                message_text as text,
                created_at as timestamp,
                'internal' as platform
            FROM internal_messages
            WHERE (sender_id = :staff_id OR recipients LIKE :staff_pattern)
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'staff_id' => $staffId,
            'staff_pattern' => "%:$staffId:%",
            'days' => $days
        ]);

        $messages = array_merge($messages, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // TODO: Integrate with Microsoft 365, Slack, etc APIs

        return $messages;
    }

    private function resolveStaffId($emailOrId): ?int
    {
        if (is_numeric($emailOrId)) {
            return (int)$emailOrId;
        }

        $sql = "SELECT staff_id FROM staff WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $emailOrId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['staff_id'] : null;
    }

    private function extractContext(string $text, string $pattern, int $chars = 50): string
    {
        $pos = stripos($text, $pattern);
        if ($pos === false) return '';

        $start = max(0, $pos - $chars);
        $length = strlen($pattern) + ($chars * 2);

        return '...' . substr($text, $start, $length) . '...';
    }

    private function isInternalEmail(string $email): bool
    {
        $internalDomains = ['vapeshed.co.nz', 'ecigdis.co.nz', 'staff.vapeshed.co.nz'];

        foreach ($internalDomains as $domain) {
            if (strpos($email, '@' . $domain) !== false) {
                return true;
            }
        }

        return false;
    }

    private function checkSharedLocations(int $staffId1, array $otherStaffIds): array
    {
        $placeholders = implode(',', array_fill(0, count($otherStaffIds), '?'));

        $sql = "
            SELECT DISTINCT l.location_name
            FROM staff_locations sl1
            JOIN staff_locations sl2 ON sl1.location_id = sl2.location_id
            JOIN locations l ON sl1.location_id = l.id
            WHERE sl1.staff_id = ?
                AND sl2.staff_id IN ($placeholders)
        ";

        $params = array_merge([$staffId1], $otherStaffIds);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'location_name');
    }

    private function calculateCollusionRisk(array $indicators): float
    {
        $score = 0.0;

        foreach ($indicators as $indicator) {
            switch ($indicator['severity']) {
                case 'CRITICAL':
                    $score += 0.50;
                    break;
                case 'HIGH':
                    $score += 0.30;
                    break;
                case 'MEDIUM':
                    $score += 0.15;
                    break;
                case 'LOW':
                    $score += 0.05;
                    break;
            }
        }

        return min(1.0, $score);
    }

    private function analyzeTemporalPatterns(array $messages): array
    {
        // Analyze message timing patterns
        $hourDistribution = array_fill(0, 24, 0);
        $dayDistribution = array_fill(1, 7, 0);

        foreach ($messages as $message) {
            $timestamp = strtotime($message['timestamp']);
            $hour = (int)date('H', $timestamp);
            $day = (int)date('N', $timestamp);

            $hourDistribution[$hour]++;
            $dayDistribution[$day]++;
        }

        // Identify unusual patterns
        $peakHours = [];
        $avgPerHour = count($messages) / 24;

        foreach ($hourDistribution as $hour => $count) {
            if ($count > $avgPerHour * 2) {
                $peakHours[] = $hour;
            }
        }

        return [
            'total_messages' => count($messages),
            'hour_distribution' => $hourDistribution,
            'day_distribution' => $dayDistribution,
            'peak_hours' => $peakHours,
            'off_hours_percentage' => $this->calculateOffHoursPercentage($hourDistribution)
        ];
    }

    private function calculateOffHoursPercentage(array $hourDist): float
    {
        $offHours = 0;
        $total = array_sum($hourDist);

        for ($h = 0; $h < 24; $h++) {
            if ($h < 7 || $h >= 22) {
                $offHours += $hourDist[$h];
            }
        }

        return $total > 0 ? ($offHours / $total) * 100 : 0.0;
    }

    private function analyzeCommunicationNetwork(int $staffId, array $messages): array
    {
        // Build communication network graph
        $connections = [];

        foreach ($messages as $message) {
            $recipients = $message['recipients'] ?? [];
            foreach ($recipients as $recipient) {
                $recipientId = $this->resolveStaffId($recipient);
                if ($recipientId) {
                    if (!isset($connections[$recipientId])) {
                        $connections[$recipientId] = 0;
                    }
                    $connections[$recipientId]++;
                }
            }
        }

        arsort($connections);

        return [
            'unique_contacts' => count($connections),
            'top_contacts' => array_slice($connections, 0, 5, true),
            'network_density' => count($connections)
        ];
    }

    private function generateRecommendations(float $avgRiskScore, array $patterns): array
    {
        $recommendations = [];

        if ($avgRiskScore >= self::CRITICAL_THRESHOLD) {
            $recommendations[] = [
                'priority' => 'CRITICAL',
                'action' => 'immediate_investigation',
                'description' => 'Initiate immediate investigation with HR and security team'
            ];
        }

        if (isset($patterns['evidence_destruction']) && $patterns['evidence_destruction'] > 0) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'action' => 'preserve_all_communications',
                'description' => 'Immediately preserve all communication logs before potential deletion'
            ];
        }

        if (isset($patterns['collusion']) && $patterns['collusion'] >= 3) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'action' => 'investigate_network',
                'description' => 'Investigate all parties involved in communication network'
            ];
        }

        return $recommendations;
    }

    private function determineRiskLevel(float $score): string
    {
        if ($score >= 0.85) return 'CRITICAL';
        if ($score >= 0.70) return 'HIGH';
        if ($score >= 0.50) return 'MEDIUM';
        if ($score >= 0.30) return 'LOW';
        return 'MINIMAL';
    }

    private function generateAlert(array $message, array $riskScore, array $patterns, array $codeWords): array
    {
        return [
            'alert_id' => uniqid('comm_alert_'),
            'severity' => $riskScore['risk_level'],
            'type' => 'SUSPICIOUS_COMMUNICATION',
            'message' => "High-risk communication detected",
            'staff_id' => $message['sender_id'],
            'risk_score' => $riskScore['total_score'],
            'pattern_categories' => array_keys($patterns),
            'code_words' => array_column($codeWords, 'code_word'),
            'timestamp' => $message['timestamp'],
            'requires_action' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    private function storeAnalysis(array $message, array $riskScore, array $patterns, array $codeWords, array $sentiment): void
    {
        $sql = "
            INSERT INTO communication_analysis (
                message_id,
                sender_id,
                recipient_ids,
                platform,
                risk_score,
                risk_level,
                pattern_matches,
                code_words,
                sentiment_data,
                analyzed_at,
                created_at
            ) VALUES (
                :message_id,
                :sender_id,
                :recipients,
                :platform,
                :risk_score,
                :risk_level,
                :patterns,
                :code_words,
                :sentiment,
                NOW(),
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'message_id' => $message['message_id'] ?? uniqid('msg_'),
            'sender_id' => $message['sender_id'],
            'recipients' => json_encode($message['recipients'] ?? []),
            'platform' => $message['platform'] ?? 'unknown',
            'risk_score' => $riskScore['total_score'],
            'risk_level' => $riskScore['risk_level'],
            'patterns' => json_encode($patterns),
            'code_words' => json_encode($codeWords),
            'sentiment' => json_encode($sentiment)
        ]);
    }

    private function preserveEvidence(array $message, array $riskScore): void
    {
        $sql = "
            INSERT INTO communication_evidence (
                message_id,
                sender_id,
                message_text,
                recipients,
                timestamp,
                platform,
                risk_score,
                metadata,
                preserved_at,
                retention_until
            ) VALUES (
                :message_id,
                :sender_id,
                :text,
                :recipients,
                :timestamp,
                :platform,
                :risk_score,
                :metadata,
                NOW(),
                DATE_ADD(NOW(), INTERVAL :retention_days DAY)
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'message_id' => $message['message_id'] ?? uniqid('msg_'),
            'sender_id' => $message['sender_id'],
            'text' => $message['text'],
            'recipients' => json_encode($message['recipients'] ?? []),
            'timestamp' => $message['timestamp'],
            'platform' => $message['platform'] ?? 'unknown',
            'risk_score' => $riskScore['total_score'],
            'metadata' => json_encode($message),
            'retention_days' => self::EVIDENCE_RETENTION_DAYS
        ]);
    }

    private function sendRealTimeAlert(array $analysis): void
    {
        // Send immediate notification to security team
        // Implementation depends on notification system (email, SMS, dashboard, etc)
        error_log("CRITICAL COMMUNICATION ALERT: Staff {$analysis['sender_id']} - Risk: {$analysis['risk_score']['total_score']}");
    }

    private function parseWebhookData(string $platform, array $data): ?array
    {
        // Parse webhook data based on platform format
        // Each platform has different payload structure
        return null; // Placeholder - implement per platform
    }
}
