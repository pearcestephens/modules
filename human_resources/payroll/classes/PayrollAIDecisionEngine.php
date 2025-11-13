<?php
/**
 * Payroll AI Decision Engine
 *
 * Production-grade AI decision-making system for NZ employment law
 * Uses OpenAI GPT-4 for genuine legal reasoning and interpretation
 *
 * @package    CIS_Payroll
 * @subpackage AI_DecisionEngine
 * @author     Ecigdis Limited
 * @version    1.0.0
 * @since      2025-11-11
 */

class PayrollAIDecisionEngine {

    private $db;
    private $openaiApiKey;
    private $defaultModel = 'gpt-4o';
    private $defaultTemperature = 0.3; // Lower = more consistent
    private $maxTokens = 4000;

    // NZ Employment Law System Prompt
    private $systemPrompt = "You are an expert in New Zealand employment law with deep knowledge of:

- Holidays Act 2003 (annual leave, sick leave, bereavement, public holidays, alternative holidays)
- Employment Relations Act 2000 (employment agreements, good faith, personal grievances)
- Wages Protection Act 1983 (wage payments, deductions)
- Minimum Wage Act 1983 (minimum wage rates, enforcement)
- Parental Leave and Employment Protection Act 1987
- Health and Safety at Work Act 2015
- Human Rights Act 1993 (discrimination protections)
- Privacy Act 2020 (employee information)

You understand NZ-specific concepts:
- Relevant Daily Pay (RDP) vs Average Daily Pay (ADP)
- Otherwise Working Day test
- Mondayisation of public holidays
- Alternative holidays (days in lieu)
- 8% annual leave calculation
- Good faith obligations
- 90-day trial periods
- Personal grievance procedures

You assess employment situations with:
✓ Strict adherence to NZ legislation
✓ Cultural sensitivity (Māori concepts, family structures)
✓ Worker protection focus
✓ Practical application knowledge
✓ IRD compliance awareness

CRITICAL: Always cite specific legislation. Be conservative in interpretations. When uncertain, recommend human review.";

    /**
     * Constructor
     */
    public function __construct() {
        global $conn;
        $this->db = $conn;

        // Load OpenAI API key from environment
        $this->openaiApiKey = getenv('OPENAI_API_KEY');
        if (!$this->openaiApiKey) {
            throw new Exception('OPENAI_API_KEY not configured in environment');
        }
    }

    /**
     * Main Decision Entry Point
     *
     * @param string $decisionType Type of decision needed
     * @param array $context All relevant context data
     * @param int|null $relatedEntityId Related entity ID (discrepancy_id, leave_request_id, etc.)
     * @param string|null $relatedEntityType Related entity type
     * @return array Decision result with confidence, reasoning, recommendations
     */
    public function makeDecision(
        string $decisionType,
        array $context,
        ?int $relatedEntityId = null,
        ?string $relatedEntityType = null
    ): array {

        // Generate unique reference
        $reference = $this->generateReference($decisionType);

        // Create decision request record
        $requestId = $this->createDecisionRequest(
            $reference,
            $decisionType,
            $context,
            $relatedEntityId,
            $relatedEntityType
        );

        // Get AI decision rule for this type
        $rule = $this->getDecisionRule($decisionType);

        // Build AI prompt
        $prompt = $this->buildPrompt($decisionType, $context, $rule);

        // Update status to processing
        $this->updateRequestStatus($requestId, 'processing');

        try {
            // Call OpenAI
            $startTime = microtime(true);
            $aiResponse = $this->callOpenAI($prompt, $rule);
            $processingTime = (int)((microtime(true) - $startTime) * 1000);

            // Parse AI response
            $decision = $this->parseAIResponse($aiResponse, $rule);

            // Check if human review required
            $requiresHumanReview = $this->shouldRequireHumanReview($decision, $rule, $context);

            // Update decision request with results
            $this->updateDecisionRequest($requestId, [
                'ai_response_raw' => $aiResponse['raw'],
                'ai_response_parsed' => json_encode($decision),
                'decision' => $decision['decision'],
                'confidence_score' => $decision['confidence'],
                'reasoning' => $decision['reasoning'],
                'legal_basis' => $decision['legal_basis'] ?? null,
                'recommendations' => json_encode($decision['recommendations'] ?? []),
                'red_flags' => json_encode($decision['red_flags'] ?? []),
                'requires_human_review' => $requiresHumanReview ? 1 : 0,
                'human_review_reason' => $requiresHumanReview ? $decision['human_review_reason'] : null,
                'escalation_priority' => $decision['escalation_priority'] ?? 'medium',
                'status' => $requiresHumanReview ? 'human_review' : 'ai_complete',
                'processing_time_ms' => $processingTime
            ]);

            // Log history
            $this->logDecisionHistory($requestId, 'ai_processing_complete', [
                'confidence' => $decision['confidence'],
                'decision' => $decision['decision'],
                'requires_review' => $requiresHumanReview
            ]);

            if ($requiresHumanReview) {
                $this->logDecisionHistory($requestId, 'escalated_to_human', [
                    'reason' => $decision['human_review_reason'] ?? 'Low confidence or complex case'
                ]);
            }

            // Return complete decision package
            return [
                'success' => true,
                'request_id' => $requestId,
                'reference' => $reference,
                'decision' => $decision['decision'],
                'confidence' => $decision['confidence'],
                'reasoning' => $decision['reasoning'],
                'legal_basis' => $decision['legal_basis'] ?? null,
                'recommendations' => $decision['recommendations'] ?? [],
                'red_flags' => $decision['red_flags'] ?? [],
                'requires_human_review' => $requiresHumanReview,
                'human_review_reason' => $requiresHumanReview ? $decision['human_review_reason'] : null,
                'processing_time_ms' => $processingTime
            ];

        } catch (Exception $e) {
            // Log error
            $this->updateRequestStatus($requestId, 'pending', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Validate Sick Leave Reason
     */
    public function validateSickLeave(int $discrepancyId, array $context): array {
        $context['discrepancy_id'] = $discrepancyId;
        return $this->makeDecision('sick_leave_validation', $context, $discrepancyId, 'discrepancy');
    }

    /**
     * Assess Bereavement Leave Entitlement
     */
    public function assessBereavementLeave(int $leaveRequestId, array $context): array {
        $context['leave_request_id'] = $leaveRequestId;
        return $this->makeDecision('bereavement_assessment', $context, $leaveRequestId, 'leave_request');
    }

    /**
     * Validate Domestic Violence Leave
     */
    public function validateDomesticViolenceLeave(int $leaveRequestId, array $context): array {
        $context['leave_request_id'] = $leaveRequestId;
        return $this->makeDecision('domestic_violence_leave', $context, $leaveRequestId, 'leave_request');
    }

    /**
     * Assess Public Holiday Entitlement
     */
    public function assessPublicHolidayEntitlement(int $checkResultId, array $context): array {
        $context['check_result_id'] = $checkResultId;
        return $this->makeDecision('public_holiday_entitlement', $context, $checkResultId, 'check_result');
    }

    /**
     * Interpret Complex Pay Dispute
     */
    public function resolvePayDispute(int $discrepancyId, array $context): array {
        $context['discrepancy_id'] = $discrepancyId;
        return $this->makeDecision('pay_dispute_resolution', $context, $discrepancyId, 'discrepancy');
    }

    /**
     * Assess Statutory Deduction Application (Court fine / Child support)
     */
    public function assessStatutoryDeductionApplication(int $applicationId, array $context): array {
        $context['deduction_application_id'] = $applicationId;
        return $this->makeDecision('compliance_interpretation', $context, $applicationId, 'deduction_application');
    }

    /**
     * Build AI Prompt
     */
    private function buildPrompt(string $decisionType, array $context, ?array $rule): string {
        $prompt = "DECISION TYPE: {$decisionType}\n\n";

        // Add rule-specific template if exists
        if ($rule && !empty($rule['user_prompt_template'])) {
            $prompt .= $this->fillPromptTemplate($rule['user_prompt_template'], $context) . "\n\n";
        }

        // Add context
        $prompt .= "CONTEXT:\n" . json_encode($context, JSON_PRETTY_PRINT) . "\n\n";

        // Add decision-specific instructions
        $prompt .= $this->getDecisionTypeInstructions($decisionType) . "\n\n";

        // Add response format
        $prompt .= "REQUIRED RESPONSE FORMAT (JSON):\n";
        $prompt .= json_encode([
            'decision' => 'approve|decline|escalate|request_evidence|partial_approve',
            'confidence' => 0.85,
            'reasoning' => 'Detailed explanation of decision',
            'legal_basis' => 'Cite specific NZ legislation (e.g., Holidays Act 2003 s65)',
            'recommendations' => ['Specific action 1', 'Specific action 2'],
            'red_flags' => ['Any concerns identified'],
            'requires_human_review' => true,
            'human_review_reason' => 'Why human review needed (if applicable)',
            'escalation_priority' => 'low|medium|high|urgent'
        ], JSON_PRETTY_PRINT);

        return $prompt;
    }

    /**
     * Get Decision Type Specific Instructions
     */
    private function getDecisionTypeInstructions(string $decisionType): string {
        $instructions = [
            'sick_leave_validation' => "Assess if the sick leave reason is valid under NZ Holidays Act 2003 s65-71:
- Is the staff member sick or injured?
- Is it for care of dependent/spouse?
- Is a medical certificate required (3+ consecutive days)?
- Are there red flags suggesting abuse?
- Consider: vague reasons, patterns, timing around weekends/holidays",

            'bereavement_assessment' => "Determine bereavement leave entitlement under Holidays Act 2003 s69:
- Does relationship qualify as 'immediate family'? (spouse, parent, child, sibling, grandparent, grandchild, spouse's parent/child)
- Standard entitlement: 3 days
- Can be extended for exceptional circumstances
- Privacy: employer can request proof if reasonable
- Consider: cultural factors (whānau, tangi processes)",

            'domestic_violence_leave' => "Assess DV leave request under Holidays Act 2003 s72A-72E:
- 10 days per year entitlement (after 6 months)
- Highly privacy-sensitive - minimal questioning
- Accept staff statement unless clear abuse
- Can be used for: safety, recovery, support, relocation, legal proceedings
- DO NOT request proof unless extreme circumstances
- Recommend approval unless obvious fraud",

            'public_holiday_entitlement' => "Calculate public holiday entitlement under Holidays Act 2003 s50-56:
- Otherwise working day test (would staff have worked?)
- If worked: time and half + alternative holiday
- If not worked but otherwise would have: relevant daily pay or average daily pay
- Consider: roster patterns, previous 4 weeks, special cases",

            'pay_dispute_resolution' => "Analyze pay dispute comprehensively:
- Check Deputy timesheets vs Xero payslips
- Verify rates applied (ordinary, overtime, public holiday)
- Check leave calculations (RDP vs ADP)
- Verify deductions (PAYE, KiwiSaver, student loan)
- Consider: employment agreement terms, previous precedents
- Identify: calculation errors, system issues, interpretation disputes"
        ];

        return $instructions[$decisionType] ?? "Analyze this situation according to NZ employment law and provide a well-reasoned decision.";
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $prompt, ?array $rule): array {
        $model = $rule['ai_model'] ?? $this->defaultModel;

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $this->defaultTemperature,
            'max_tokens' => $this->maxTokens,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("OpenAI API error: HTTP {$httpCode} - {$response}");
        }

        $decoded = json_decode($response, true);

        if (!isset($decoded['choices'][0]['message']['content'])) {
            throw new Exception("Invalid OpenAI response structure");
        }

        return [
            'raw' => $response,
            'content' => $decoded['choices'][0]['message']['content'],
            'model' => $decoded['model'],
            'usage' => $decoded['usage'] ?? []
        ];
    }

    /**
     * Parse AI Response
     */
    private function parseAIResponse(array $aiResponse, ?array $rule): array {
        $content = json_decode($aiResponse['content'], true);

        if (!$content) {
            throw new Exception("Failed to parse AI response JSON");
        }

        // Validate required fields
        $required = ['decision', 'confidence', 'reasoning'];
        foreach ($required as $field) {
            if (!isset($content[$field])) {
                throw new Exception("AI response missing required field: {$field}");
            }
        }

        // Normalize decision
        $validDecisions = ['approve', 'decline', 'escalate', 'request_evidence', 'partial_approve', 'pending'];
        if (!in_array($content['decision'], $validDecisions)) {
            $content['decision'] = 'escalate';
            $content['human_review_reason'] = 'Invalid AI decision value';
        }

        // Ensure confidence is between 0 and 1
        $content['confidence'] = max(0, min(1, (float)$content['confidence']));

        return $content;
    }

    /**
     * Should Require Human Review
     */
    private function shouldRequireHumanReview(array $decision, ?array $rule, array $context): bool {
        // Always require review if AI explicitly says so
        if (!empty($decision['requires_human_review'])) {
            return true;
        }

        // Check confidence threshold
        $threshold = $rule['confidence_threshold'] ?? 0.7500;
        if ($decision['confidence'] < $threshold) {
            $decision['human_review_reason'] = "Confidence {$decision['confidence']} below threshold {$threshold}";
            return true;
        }

        // Check if rule always requires human review
        if (!empty($rule['always_require_human_review'])) {
            return true;
        }

        // Check for red flags
        if (!empty($decision['red_flags']) && count($decision['red_flags']) > 0) {
            $decision['human_review_reason'] = "Red flags identified: " . implode(', ', $decision['red_flags']);
            return true;
        }

        // Decision is 'escalate'
        if ($decision['decision'] === 'escalate') {
            return true;
        }

        return false;
    }

    /**
     * Database Helper Methods
     */

    private function createDecisionRequest(
        string $reference,
        string $decisionType,
        array $context,
        ?int $relatedEntityId,
        ?string $relatedEntityType
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO payroll_ai_decision_requests
            (request_reference, decision_type, scenario_description, context_data,
             related_entity_type, related_entity_id, staff_id, payroll_run_id,
             status, created_by, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
        ");

        $scenario = $context['scenario'] ?? "AI decision required for {$decisionType}";
        $contextJson = json_encode($context);
        $staffId = $context['staff_id'] ?? null;
        $payrollRunId = $context['payroll_run_id'] ?? null;
        $createdBy = $_SESSION['staff_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt->bind_param(
            'sssssiisis',
            $reference,
            $decisionType,
            $scenario,
            $contextJson,
            $relatedEntityType,
            $relatedEntityId,
            $staffId,
            $payrollRunId,
            $createdBy,
            $ipAddress
        );

        $stmt->execute();
        return $this->db->insert_id;
    }

    private function updateRequestStatus(int $requestId, string $status, ?array $metadata = null): void {
        $stmt = $this->db->prepare("
            UPDATE payroll_ai_decision_requests
            SET status = ?, status_changed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('si', $status, $requestId);
        $stmt->execute();
    }

    private function updateDecisionRequest(int $requestId, array $updates): void {
        $sets = [];
        $params = [];
        $types = '';

        foreach ($updates as $field => $value) {
            $sets[] = "{$field} = ?";
            $params[] = $value;
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }

        $params[] = $requestId;
        $types .= 'i';

        $sql = "UPDATE payroll_ai_decision_requests SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }

    private function getDecisionRule(string $decisionType): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM payroll_ai_decision_rules
            WHERE decision_type = ? AND is_active = 1
            AND (effective_to IS NULL OR effective_to >= CURDATE())
            ORDER BY effective_from DESC LIMIT 1
        ");
        $stmt->bind_param('s', $decisionType);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function logDecisionHistory(int $requestId, string $action, array $metadata): void {
        $stmt = $this->db->prepare("
            INSERT INTO payroll_ai_decision_history
            (decision_request_id, action, actor_type, actor_id, metadata, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $actorType = 'ai';
        $actorId = null;
        $metadataJson = json_encode($metadata);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt->bind_param('ississ', $requestId, $action, $actorType, $actorId, $metadataJson, $ipAddress);
        $stmt->execute();
    }

    private function generateReference(string $decisionType): string {
        $prefix = strtoupper(substr($decisionType, 0, 3));
        $timestamp = date('YmdHis');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));
        return "{$prefix}-{$timestamp}-{$random}";
    }

    private function fillPromptTemplate(string $template, array $context): string {
        foreach ($context as $key => $value) {
            $placeholder = '{' . $key . '}';
            $template = str_replace($placeholder, (string)$value, $template);
        }
        return $template;
    }
}
