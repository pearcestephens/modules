<?php
/**
 * AI Orchestrator - Store Reports Intelligence Engine
 *
 * PERSONALITY: Meticulous, detail-obsessed, thorough analyst with high standards
 * MISSION: Extract maximum insight from every store inspection with zero tolerance for ambiguity
 *
 * Features:
 * - Multi-provider AI support (OpenAI, Anthropic, MCP Hub)
 * - Comprehensive logging to existing ai_agent_requests table
 * - Strict validation and error handling
 * - Cost tracking and optimization
 * - Conversation threading and context management
 * - Vision analysis with follow-up questioning
 * - Automatic quality grading with detailed justification
 */

declare(strict_types=1);

class AIOrchestrator
{
    private PDO $pdo;
    private array $config;
    private ?string $conversationId = null;
    private array $conversationHistory = [];
    private int $totalTokens = 0;
    private float $totalCostNZD = 0.0;

    // Personality configuration
    private const PERSONALITY = [
        'role' => 'meticulous_quality_analyst',
        'traits' => [
            'detail_obsessed' => true,
            'zero_tolerance_for_vague' => true,
            'always_asks_followup' => true,
            'demands_photographic_evidence' => true,
            'strict_grading_standards' => true
        ],
        'tone' => 'professional_thorough_exacting'
    ];

    // Hard standards for analysis
    private const QUALITY_STANDARDS = [
        'cleanliness' => [
            'A' => 'Spotless, hospital-grade clean, no dust/marks/spills visible',
            'B' => 'Clean with minor dust in hard-to-reach areas only',
            'C' => 'Acceptable but visible cleaning needed in 1-2 areas',
            'D' => 'Multiple areas need immediate cleaning attention',
            'F' => 'Unacceptable hygiene standards, immediate action required'
        ],
        'organization' => [
            'A' => 'Perfect alignment, facing, pricing visible, professional display',
            'B' => 'Well organized with minor adjustments needed',
            'C' => 'Functional but lacks professional polish',
            'D' => 'Disorganized, customer experience impacted',
            'F' => 'Chaotic, requires complete reorganization'
        ],
        'safety' => [
            'A' => 'Zero hazards, all compliance visible, exits clear',
            'B' => 'Safe with minor non-critical items to address',
            'C' => 'Acceptable but 1-2 safety items need attention',
            'D' => 'Multiple safety concerns present',
            'F' => 'Critical safety violations, immediate action required'
        ],
        'compliance' => [
            'A' => 'All signage visible, regulations clearly followed',
            'B' => 'Compliant with minor signage improvements possible',
            'C' => 'Meets minimum requirements',
            'D' => 'Non-compliance issues present',
            'F' => 'Serious regulatory violations'
        ]
    ];

    // Cost tracking (NZD cents per 1K tokens)
    private const TOKEN_COSTS = [
        'gpt-4-vision-preview' => ['input' => 1.5, 'output' => 4.5],
        'gpt-4-turbo-preview' => ['input' => 1.5, 'output' => 4.5],
        'gpt-4o' => ['input' => 0.75, 'output' => 2.25],
        'gpt-3.5-turbo' => ['input' => 0.075, 'output' => 0.15],
        'claude-3-opus' => ['input' => 2.25, 'output' => 11.25],
        'claude-3-sonnet' => ['input' => 0.45, 'output' => 2.25]
    ];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'log_to_db' => true,
            'log_to_file' => true,
            'use_mcp_hub' => false,
            'mcp_hub_url' => null,
            'strict_mode' => true,
            'personality_enabled' => true
        ], $config);

        $this->conversationId = $this->generateConversationId();
    }

    /**
     * Analyze image with GPT-4 Vision - METICULOUS MODE
     *
     * @param string $imagePath Full path to image file
     * @param string $analysisType Type of analysis requested
     * @param array $context Additional context about what to look for
     * @return array Analysis results with detailed findings
     */
    public function analyzeImage(string $imagePath, string $analysisType = 'comprehensive', array $context = []): array
    {
        $startTime = microtime(true);

        // Validate image exists
        if (!file_exists($imagePath)) {
            throw new \RuntimeException("Image not found: {$imagePath}");
        }

        // Build meticulous prompt based on personality
        $prompt = $this->buildImageAnalysisPrompt($analysisType, $context);

        // Encode image
        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);

        // Make API call
        $response = $this->callVisionAPI($prompt, $imageData, $mimeType);

        $duration = round((microtime(true) - $startTime) * 1000);

        // Parse response and extract structured data
        $analysis = $this->parseAnalysisResponse($response['content'], $analysisType);

        // Log to database
        $this->logAIRequest([
            'endpoint' => 'vision_analysis',
            'model' => $this->config['model'],
            'prompt_tokens' => $response['usage']['prompt_tokens'],
            'completion_tokens' => $response['usage']['completion_tokens'],
            'total_tokens' => $response['usage']['total_tokens'],
            'cost_nzd_cents' => $this->calculateCost($response['usage']),
            'response_time_ms' => $duration,
            'status' => 'success',
            'request_payload' => json_encode([
                'analysis_type' => $analysisType,
                'image_path' => basename($imagePath),
                'context' => $context
            ]),
            'response_body' => json_encode($response)
        ]);

        return [
            'success' => true,
            'analysis' => $analysis,
            'raw_response' => $response['content'],
            'metadata' => [
                'tokens_used' => $response['usage']['total_tokens'],
                'cost_nzd' => $this->calculateCost($response['usage']) / 100,
                'duration_ms' => $duration,
                'model' => $this->config['model']
            ],
            'follow_up_questions' => $this->generateFollowUpQuestions($analysis)
        ];
    }

    /**
     * Build meticulous, detail-demanding prompt based on analysis type
     */
    private function buildImageAnalysisPrompt(string $type, array $context): string
    {
        $basePersonality = "You are a METICULOUS retail quality analyst with EXTREMELY HIGH STANDARDS. ";
        $basePersonality .= "You notice EVERY detail, no matter how small. ";
        $basePersonality .= "You are thorough, precise, and demand photographic evidence for all claims. ";
        $basePersonality .= "You NEVER accept vague descriptions - you specify exact locations, measurements, and conditions.\n\n";

        $prompts = [
            'comprehensive' => $basePersonality .
                "Analyze this retail store image with EXTREME THOROUGHNESS:\n\n" .
                "1. CLEANLINESS (be specific about locations):\n" .
                "   - Identify EVERY visible surface and its cleanliness level\n" .
                "   - Note dust, marks, spills, fingerprints with EXACT locations\n" .
                "   - Grade: " . $this->formatGradingCriteria('cleanliness') . "\n\n" .
                "2. ORGANIZATION (demand perfection):\n" .
                "   - Product facing and alignment (specify which products)\n" .
                "   - Pricing visibility (identify missing/unclear prices)\n" .
                "   - Display quality and professional appearance\n" .
                "   - Grade: " . $this->formatGradingCriteria('organization') . "\n\n" .
                "3. SAFETY (zero tolerance):\n" .
                "   - Fire hazards, blocked exits, trip hazards\n" .
                "   - Damaged fixtures or equipment\n" .
                "   - Electrical safety concerns\n" .
                "   - Grade: " . $this->formatGradingCriteria('safety') . "\n\n" .
                "4. COMPLIANCE (strict regulatory standards):\n" .
                "   - Age restriction signage visibility\n" .
                "   - Product warnings and legal notices\n" .
                "   - Regulatory compliance indicators\n" .
                "   - Grade: " . $this->formatGradingCriteria('compliance') . "\n\n" .
                "5. OVERALL ASSESSMENT:\n" .
                "   - List 3-5 SPECIFIC areas of excellence\n" .
                "   - List 3-5 SPECIFIC areas requiring immediate attention\n" .
                "   - Provide DETAILED recommendations with exact actions\n\n" .
                "OUTPUT FORMAT:\n" .
                "- Be SPECIFIC with locations (e.g., 'top left shelf, third product from right')\n" .
                "- Use MEASUREMENTS when possible (e.g., 'approximately 2cm gap')\n" .
                "- GRADE each category (A/B/C/D/F) with detailed justification\n" .
                "- End with 3-5 SPECIFIC follow-up questions you need answered\n\n" .
                "Context: " . json_encode($context),

            'cleanliness' => $basePersonality .
                "Focus EXCLUSIVELY on cleanliness with MICROSCOPIC attention:\n\n" .
                "Examine EVERY visible surface:\n" .
                "- Floors: dust, marks, spills, scuffs (specify exact locations)\n" .
                "- Shelves/displays: dust accumulation, fingerprints, marks\n" .
                "- Glass surfaces: smudges, streaks, clarity\n" .
                "- Products: dust on packaging, cleanliness of displayed items\n" .
                "- Fixtures: light fixtures, vents, corners, baseboards\n\n" .
                "Rate using STRICT criteria:\n" . $this->formatGradingCriteria('cleanliness') . "\n\n" .
                "List EVERY cleaning issue found with:\n" .
                "- Exact location\n" .
                "- Severity (critical/moderate/minor)\n" .
                "- Recommended action\n\n" .
                "Context: " . json_encode($context),

            'organization' => $basePersonality .
                "Analyze organization with PERFECTIONISTIC standards:\n\n" .
                "Product Display:\n" .
                "- Front-facing alignment (which products are backwards/sideways?)\n" .
                "- Spacing and gaps (specify locations)\n" .
                "- Pricing visibility (identify ALL missing/unclear prices)\n" .
                "- Product grouping logic\n\n" .
                "Visual Merchandising:\n" .
                "- Professional appearance\n" .
                "- Brand standards compliance\n" .
                "- Customer sight lines\n" .
                "- Impulse purchase optimization\n\n" .
                "Rate using criteria:\n" . $this->formatGradingCriteria('organization') . "\n\n" .
                "Provide SPECIFIC adjustments needed with exact locations.\n\n" .
                "Context: " . json_encode($context),

            'safety' => $basePersonality .
                "Conduct ZERO-TOLERANCE safety inspection:\n\n" .
                "CRITICAL HAZARDS:\n" .
                "- Fire safety: exits, extinguishers, flammable materials\n" .
                "- Trip hazards: cables, boxes, uneven surfaces\n" .
                "- Falling hazards: unstable displays, overloaded shelves\n" .
                "- Electrical: exposed wires, overloaded outlets, damaged equipment\n\n" .
                "REGULATORY COMPLIANCE:\n" .
                "- Emergency exit signage and accessibility\n" .
                "- Safety equipment accessibility\n" .
                "- Hazardous material storage\n\n" .
                "Rate using criteria:\n" . $this->formatGradingCriteria('safety') . "\n\n" .
                "For EVERY issue found, specify:\n" .
                "- Exact location and description\n" .
                "- Risk level (critical/high/medium/low)\n" .
                "- Immediate action required\n" .
                "- Regulatory implications\n\n" .
                "Context: " . json_encode($context),

            'compliance' => $basePersonality .
                "Verify STRICT regulatory compliance:\n\n" .
                "Age Restriction Requirements:\n" .
                "- Signage visibility and placement\n" .
                "- Legal text readability\n" .
                "- Compliance with local vaping laws\n\n" .
                "Product Warnings:\n" .
                "- Health warnings visibility\n" .
                "- Nicotine content disclosures\n" .
                "- Safe use instructions\n\n" .
                "Legal Requirements:\n" .
                "- Business license display\n" .
                "- Operating hours posted\n" .
                "- Consumer rights information\n\n" .
                "Rate using criteria:\n" . $this->formatGradingCriteria('compliance') . "\n\n" .
                "Identify ANY non-compliance with specific regulatory reference.\n\n" .
                "Context: " . json_encode($context)
        ];

        return $prompts[$type] ?? $prompts['comprehensive'];
    }

    /**
     * Format grading criteria for prompt
     */
    private function formatGradingCriteria(string $category): string
    {
        $criteria = self::QUALITY_STANDARDS[$category] ?? [];
        $formatted = "";
        foreach ($criteria as $grade => $description) {
            $formatted .= "   {$grade}: {$description}\n";
        }
        return trim($formatted);
    }

    /**
     * Call Vision API (OpenAI GPT-4 Vision or Claude with images)
     */
    private function callVisionAPI(string $prompt, string $imageData, string $mimeType): array
    {
        $apiKey = getenv('OPENAI_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY not configured');
        }

        $payload = [
            'model' => $this->config['model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$imageData}",
                                'detail' => 'high' // Maximum detail for meticulous analysis
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => $this->config['max_tokens'],
            'temperature' => $this->config['temperature']
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("OpenAI API error (HTTP {$httpCode}): {$response}");
        }

        $result = json_decode($response, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            throw new \RuntimeException('Invalid API response structure');
        }

        $this->conversationHistory[] = [
            'role' => 'assistant',
            'content' => $result['choices'][0]['message']['content'],
            'tokens' => $result['usage']['total_tokens']
        ];

        $this->totalTokens += $result['usage']['total_tokens'];
        $this->totalCostNZD += $this->calculateCost($result['usage']);

        return [
            'content' => $result['choices'][0]['message']['content'],
            'usage' => $result['usage']
        ];
    }

    /**
     * Parse AI response into structured analysis data
     */
    private function parseAnalysisResponse(string $response, string $analysisType): array
    {
        // Extract grades using regex
        $grades = [];
        preg_match_all('/(?:Grade|Rating):\s*([A-F])/i', $response, $gradeMatches);
        if (!empty($gradeMatches[1])) {
            $categories = ['cleanliness', 'organization', 'safety', 'compliance'];
            foreach ($gradeMatches[1] as $i => $grade) {
                if (isset($categories[$i])) {
                    $grades[$categories[$i]] = $grade;
                }
            }
        }

        // Extract issues (look for bullet points or numbered lists)
        $issues = [];
        if (preg_match_all('/[-•]\s*(.+?)(?=\n[-•]|\n\n|$)/s', $response, $issueMatches)) {
            $issues = array_map('trim', $issueMatches[1]);
        }

        // Extract recommendations
        $recommendations = [];
        if (preg_match('/(?:Recommendations?|Actions?|Improvements?):\s*(.+?)(?=\n\n|\z)/is', $response, $recMatch)) {
            if (preg_match_all('/[-•]\s*(.+?)(?=\n[-•]|\n\n|$)/s', $recMatch[1], $recMatches)) {
                $recommendations = array_map('trim', $recMatches[1]);
            }
        }

        // Calculate overall score (weighted average)
        $overallScore = 0;
        if (!empty($grades)) {
            $gradeValues = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'F' => 1];
            $sum = 0;
            $count = 0;
            foreach ($grades as $grade) {
                $sum += $gradeValues[$grade] ?? 3;
                $count++;
            }
            $overallScore = $count > 0 ? round(($sum / $count) * 20, 1) : 0; // Convert to 0-100 scale
        }

        return [
            'summary' => $this->extractSummary($response),
            'grades' => $grades,
            'overall_score' => $overallScore,
            'issues_found' => $issues,
            'recommendations' => $recommendations,
            'detail_level' => $this->assessDetailLevel($response),
            'confidence' => $this->assessConfidence($response),
            'requires_followup' => count($issues) > 5 || empty($grades)
        ];
    }

    /**
     * Extract summary from response (first paragraph or key findings)
     */
    private function extractSummary(string $response): string
    {
        // Get first paragraph or first 200 chars
        $lines = explode("\n", trim($response));
        $summary = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (strlen($line) > 50) {
                $summary = $line;
                break;
            }
        }
        return substr($summary, 0, 500);
    }

    /**
     * Assess level of detail in response
     */
    private function assessDetailLevel(string $response): string
    {
        $wordCount = str_word_count($response);
        $specificityIndicators = preg_match_all('/\b(exactly|specifically|precisely|located at|measured|approximately)\b/i', $response);

        if ($wordCount > 500 && $specificityIndicators > 10) return 'exceptional';
        if ($wordCount > 300 && $specificityIndicators > 5) return 'detailed';
        if ($wordCount > 150) return 'adequate';
        return 'insufficient';
    }

    /**
     * Assess AI confidence in analysis
     */
    private function assessConfidence(string $response): float
    {
        // Look for uncertainty markers
        $uncertaintyMarkers = preg_match_all('/\b(appears|seems|possibly|maybe|unclear|difficult to see)\b/i', $response);
        $certaintyMarkers = preg_match_all('/\b(clearly|definitely|obviously|visible|confirmed)\b/i', $response);

        $confidenceScore = 0.7; // Base confidence
        $confidenceScore += ($certaintyMarkers * 0.05);
        $confidenceScore -= ($uncertaintyMarkers * 0.1);

        return max(0.1, min(1.0, $confidenceScore));
    }

    /**
     * Generate intelligent follow-up questions based on analysis
     */
    private function generateFollowUpQuestions(array $analysis): array
    {
        $questions = [];

        // If grades are missing or low, demand more info
        if (empty($analysis['grades'])) {
            $questions[] = "I need clearer visibility of key areas. Can you provide additional photos of: shelving, displays, and safety equipment?";
        }

        foreach ($analysis['grades'] ?? [] as $category => $grade) {
            if (in_array($grade, ['D', 'F'])) {
                $questions[] = "The {$category} grade is concerning. I need close-up photos of the specific problem areas to provide detailed remediation steps.";
            }
        }

        // If issues found but vague, demand specifics
        if (count($analysis['issues_found']) > 0 && count($analysis['issues_found']) < 3) {
            $questions[] = "I've identified some issues but need more angles. Can you photograph from different viewpoints to capture the full context?";
        }

        // Always want more detail (personality trait)
        if ($analysis['detail_level'] !== 'exceptional') {
            $questions[] = "For a complete analysis, I need additional photos showing: corners, behind displays, under fixtures, and any hidden areas.";
        }

        // If high confidence issues found, ask for timeline
        if (count($analysis['issues_found']) >= 3 && $analysis['confidence'] > 0.7) {
            $questions[] = "When were these issues last addressed? I need the maintenance log for this area to establish patterns.";
        }

        return array_slice($questions, 0, 5); // Max 5 follow-up questions
    }

    /**
     * Ask follow-up question in existing conversation
     */
    public function askFollowUp(string $question, ?string $imageData = null): array
    {
        $startTime = microtime(true);

        $messages = [];

        // Add conversation history
        foreach ($this->conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }

        // Add new question
        $userContent = [['type' => 'text', 'text' => $question]];

        if ($imageData) {
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $imageData, 'detail' => 'high']
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userContent];

        // Call API
        $apiKey = getenv('OPENAI_API_KEY');
        $payload = [
            'model' => $this->config['model'],
            'messages' => $messages,
            'max_tokens' => $this->config['max_tokens']
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        $duration = round((microtime(true) - $startTime) * 1000);

        // Log to database
        $this->logAIRequest([
            'endpoint' => 'followup_question',
            'model' => $this->config['model'],
            'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $result['usage']['total_tokens'] ?? 0,
            'cost_nzd_cents' => $this->calculateCost($result['usage'] ?? []),
            'response_time_ms' => $duration,
            'status' => $httpCode === 200 ? 'success' : 'error',
            'request_payload' => json_encode(['question' => $question]),
            'response_body' => $response
        ]);

        $content = $result['choices'][0]['message']['content'] ?? '';

        $this->conversationHistory[] = [
            'role' => 'user',
            'content' => $question
        ];
        $this->conversationHistory[] = [
            'role' => 'assistant',
            'content' => $content
        ];

        return [
            'success' => true,
            'response' => $content,
            'tokens_used' => $result['usage']['total_tokens'] ?? 0,
            'cost_nzd' => $this->calculateCost($result['usage'] ?? []) / 100
        ];
    }

    /**
     * Calculate cost in NZD cents
     */
    private function calculateCost(array $usage): int
    {
        $model = $this->config['model'];
        $costs = self::TOKEN_COSTS[$model] ?? ['input' => 1.0, 'output' => 3.0];

        $promptCost = ($usage['prompt_tokens'] ?? 0) * $costs['input'] / 1000;
        $completionCost = ($usage['completion_tokens'] ?? 0) * $costs['output'] / 1000;

        return (int)round($promptCost + $completionCost);
    }

    /**
     * Log AI request to database (ai_agent_requests table)
     */
    private function logAIRequest(array $data): void
    {
        if (!$this->config['log_to_db']) return;

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ai_agent_requests (
                    provider,
                    conversation_id,
                    request_uuid,
                    model,
                    endpoint,
                    prompt_tokens,
                    completion_tokens,
                    total_tokens,
                    cost_nzd_cents,
                    response_time_ms,
                    status,
                    error_message,
                    request_payload,
                    response_body,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->config['provider'],
                $this->conversationId,
                $this->generateUUID(),
                $data['model'] ?? $this->config['model'],
                $data['endpoint'] ?? 'unknown',
                $data['prompt_tokens'] ?? 0,
                $data['completion_tokens'] ?? 0,
                $data['total_tokens'] ?? 0,
                $data['cost_nzd_cents'] ?? 0,
                $data['response_time_ms'] ?? 0,
                $data['status'] ?? 'unknown',
                $data['error_message'] ?? null,
                $data['request_payload'] ?? '{}',
                $data['response_body'] ?? '{}'
            ]);

        } catch (\Exception $e) {
            error_log("Failed to log AI request to database: " . $e->getMessage());
        }
    }

    /**
     * Get conversation summary and statistics
     */
    public function getConversationStats(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'total_messages' => count($this->conversationHistory),
            'total_tokens' => $this->totalTokens,
            'total_cost_nzd' => $this->totalCostNZD / 100,
            'avg_tokens_per_message' => count($this->conversationHistory) > 0
                ? round($this->totalTokens / count($this->conversationHistory))
                : 0
        ];
    }

    /**
     * Generate unique conversation ID
     */
    private function generateConversationId(): string
    {
        return 'conv_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Generate UUID for request tracking
     */
    private function generateUUID(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
