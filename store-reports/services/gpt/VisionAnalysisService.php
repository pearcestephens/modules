<?php

/**
 * AI Vision Service for Store Report Image Analysis
 * Uses OpenAI GPT-4 Vision API to analyze store photos
 */

class StoreReportAIVisionService {

    private $openai_api_key;
    private $model = 'gpt-4o'; // Latest model with vision
    private $max_tokens = 4096;
    private $temperature = 0.3; // Lower for more consistent/factual analysis

    private $db;
    private $logger;

    public function __construct() {
        global $con;
        $this->db = $con;

        // Get OpenAI API key from environment
        $this->openai_api_key = getenv('OPENAI_API_KEY') ?: $_ENV['OPENAI_API_KEY'] ?? null;

        if (!$this->openai_api_key) {
            throw new Exception('OpenAI API key not configured');
        }

        // Initialize logger
        $this->logger = new CISLogger('store-reports-ai');
    }

    /**
     * Analyze a single store image
     */
    public function analyzeImage(int $imageId): array {
        $this->log("Starting analysis for image ID: {$imageId}");

        // Get image details
        $image = $this->getImageDetails($imageId);
        if (!$image) {
            throw new Exception("Image not found: {$imageId}");
        }

        // Update status to analyzing
        $this->updateImageStatus($imageId, 'analyzing');

        try {
            $startTime = microtime(true);

            // Build analysis prompt based on context
            $prompt = $this->buildAnalysisPrompt($image);

            // Call OpenAI Vision API
            $response = $this->callVisionAPI($image['file_path'], $prompt);

            $duration = round((microtime(true) - $startTime) * 1000); // milliseconds

            // Parse and extract structured data
            $analysis = $this->parseAIResponse($response);

            // Cost estimation (rough): assume tokens proportional to description length + objects/issues
            $tokenEstimate = strlen(json_encode($analysis)) / 4; // heuristic
            $costPer1K = 0.03; // approximate high-detail vision output cost per 1K tokens
            $estimatedCost = round(($tokenEstimate/1000) * $costPer1K, 4);

            // Store analysis results
            $this->storeAnalysisResults($imageId, $analysis, $duration);

            // Update image status
            $this->updateImageStatus($imageId, 'analyzed', true);

            // Check if AI wants follow-up photos
            if (!empty($analysis['follow_up_requests'])) {
                $this->createAIPhotoRequests($image['report_id'], $imageId, $analysis['follow_up_requests']);
            }

            $this->log("Analysis completed for image {$imageId} in {$duration}ms");

            return [
                'success' => true,
                'image_id' => $imageId,
                'analysis' => $analysis,
                'duration_ms' => $duration
            ];

        } catch (Exception $e) {
            $this->log("Analysis failed for image {$imageId}: " . $e->getMessage(), 'error');

            $this->updateImageStatus($imageId, 'failed', false, $e->getMessage());
            $this->incrementRetryCount($imageId);

            return [
                'success' => false,
                'image_id' => $imageId,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Batch analyze all images for a report
     */
    public function analyzeReportImages(int $reportId): array {
        $this->log("Starting batch analysis for report ID: {$reportId}");

        // Update report AI status
        $this->updateReportAIStatus($reportId, 'processing');

        // Get all unanalyzed images
        $images = $this->getReportImages($reportId, false);

        $results = [
            'total' => count($images),
            'successful' => 0,
            'failed' => 0,
            'analyses' => []
        ];

        foreach ($images as $image) {
            $analysis = $this->analyzeImage($image['id']);
            $results['analyses'][] = $analysis;

            if ($analysis['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }

        // Generate overall report summary
        $summary = $this->generateReportSummary($reportId);

        // Update report with AI analysis
        $this->updateReportAIResults($reportId, $summary);

        // Update report AI status to completed
        $this->updateReportAIStatus($reportId, 'completed');

        $this->log("Batch analysis completed for report {$reportId}: {$results['successful']}/{$results['total']} successful");

        return $results;
    }

    /**
     * Build contextual prompt for image analysis
     */
    private function buildAnalysisPrompt(array $image): string {
        $context = $this->getReportContext($image['report_id']);

        $prompt = "You are an expert retail store inspector analyzing a photo from a vape retail store inspection.\n\n";

        $prompt .= "STORE CONTEXT:\n";
        $prompt .= "- Store: {$context['outlet_name']}\n";
        $prompt .= "- Location in store: " . ($image['location_in_store'] ?? 'Not specified') . "\n";

        if (!empty($image['caption'])) {
            $prompt .= "- Staff note: {$image['caption']}\n";
        }

        if ($image['checklist_item_id']) {
            $checklistItem = $this->getChecklistItem($image['checklist_item_id']);
            $prompt .= "- Related checklist item: {$checklistItem['title']}\n";
        }

        $prompt .= "\nYOUR TASK:\n";
        $prompt .= "Analyze this image comprehensively and provide structured feedback in JSON format.\n\n";

        $prompt .= "ANALYZE FOR:\n";
        $prompt .= "1. **Cleanliness** (0-100): Overall cleanliness and hygiene\n";
        $prompt .= "2. **Organization** (0-100): Product arrangement, tidiness, accessibility\n";
        $prompt .= "3. **Safety** (0-100): Safety hazards, compliance with safety standards\n";
        $prompt .= "4. **Compliance** (0-100): Regulatory compliance (age restrictions, warnings, etc.)\n";
        $prompt .= "5. **Visual Appeal** (0-100): Customer-facing aesthetics and professionalism\n\n";

        $prompt .= "PROVIDE:\n";
        $prompt .= "- Detailed description of what you see\n";
        $prompt .= "- List of detected objects/elements\n";
        $prompt .= "- Specific issues or concerns (be critical!)\n";
        $prompt .= "- Positive aspects (what's done well)\n";
        $prompt .= "- Recommendations for improvement\n";
        $prompt .= "- Any red flags or compliance concerns\n";
        $prompt .= "- Whether you need additional photos for better assessment\n\n";

        $prompt .= "BE THOROUGH AND CRITICAL. Look for:\n";
        $prompt .= "- Dust, dirt, stains, spills\n";
        $prompt .= "- Clutter, disorganization, poor display\n";
        $prompt .= "- Safety hazards (cords, obstructions, sharp edges)\n";
        $prompt .= "- Compliance issues (age warnings, proper signage)\n";
        $prompt .= "- Product damage or expiry\n";
        $prompt .= "- Poor lighting or visibility\n";
        $prompt .= "- Unprofessional appearance\n\n";

        $prompt .= "RESPOND ONLY WITH VALID JSON in this exact structure:\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "description": "Detailed description of the image",'."\n";
        $prompt .= '  "detected_objects": ["object1", "object2"],'."\n";
        $prompt .= '  "issues": ["issue1", "issue2"],'."\n";
        $prompt .= '  "positives": ["positive1", "positive2"],'."\n";
        $prompt .= '  "recommendations": ["rec1", "rec2"],'."\n";
        $prompt .= '  "flags": [{"type": "warning|danger|info", "message": "..."}],'."\n";
        $prompt .= '  "scores": {'."\n";
        $prompt .= '    "cleanliness": 85,'."\n";
        $prompt .= '    "organization": 75,'."\n";
        $prompt .= '    "safety": 90,'."\n";
        $prompt .= '    "compliance": 95,'."\n";
        $prompt .= '    "visual_appeal": 80,'."\n";
        $prompt .= '    "overall": 85,'."\n";
        $prompt .= '    "confidence": 90'."\n";
        $prompt .= '  },'."\n";
        $prompt .= '  "follow_up_needed": false,'."\n";
        $prompt .= '  "follow_up_requests": []'."\n";
        $prompt .= "}\n";
        $prompt .= "```\n\n";

        $prompt .= "If you need additional photos for better assessment, set follow_up_needed to true and add specific requests to follow_up_requests array with this structure:\n";
        $prompt .= '{"title": "Request title", "description": "What you need to see", "priority": "low|medium|high|critical", "reason": "Why you need it"}'."\n\n";

        $prompt .= "Be professional, specific, and constructive. Focus on actionable feedback.";

        return $prompt;
    }

    /**
     * Call OpenAI Vision API
     */
    private function callVisionAPI(string $imagePath, string $prompt): string {
        // Convert image to base64
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new Exception("Failed to read image file: {$imagePath}");
        }

        $base64Image = base64_encode($imageData);
        $mimeType = mime_content_type($imagePath);

        // Build API request
        $apiUrl = 'https://api.openai.com/v1/chat/completions';

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$base64Image}",
                                'detail' => 'high' // High detail for thorough analysis
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openai_api_key
            ],
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: {$error}");
        }

        if ($httpCode !== 200) {
            $this->log("OpenAI API error (HTTP {$httpCode}): {$response}", 'error');
            throw new Exception("OpenAI API returned HTTP {$httpCode}");
        }

        $data = json_decode($response, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("Invalid response from OpenAI API");
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Parse AI response and extract structured data
     */
    private function parseAIResponse(string $response): array {
        // Extract JSON from response (handle markdown code blocks)
        preg_match('/```json\s*(.*?)\s*```/s', $response, $matches);

        if (isset($matches[1])) {
            $jsonStr = $matches[1];
        } else {
            // Try parsing entire response as JSON
            $jsonStr = $response;
        }

        $data = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON parse error: " . json_last_error_msg(), 'error');
            $this->log("Response: " . substr($response, 0, 500), 'error');
            throw new Exception("Failed to parse AI response as JSON");
        }

        // Validate required fields
        $required = ['description', 'scores'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field in AI response: {$field}");
            }
        }

        return $data;
    }

    /**
     * Store analysis results in database
     */
    private function storeAnalysisResults(int $imageId, array $analysis, int $duration): void {
        $pdo = sr_pdo();
        if (!$pdo) { throw new Exception('Database unavailable'); }
        $sql = "UPDATE store_report_images SET
            ai_analyzed = TRUE,
            ai_analysis_timestamp = NOW(),
            ai_analysis_duration_ms = :duration,
            ai_model_version = :model,
            ai_description = :description,
            ai_detected_objects = :objects,
            ai_detected_issues = :issues,
            ai_detected_positives = :positives,
            ai_cleanliness_score = :cleanliness,
            ai_organization_score = :organization,
            ai_compliance_score = :compliance,
            ai_safety_score = :safety,
            ai_overall_score = :overall,
            ai_confidence = :confidence,
            ai_flags = :flags,
            ai_recommendations = :recs,
            ai_follow_up_needed = :follow_needed,
            ai_follow_up_request = :follow_req,
            updated_at = NOW()
        WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $followReq = !empty($analysis['follow_up_requests']) ? json_encode($analysis['follow_up_requests']) : null;
        $ok = $stmt->execute([
            ':duration' => $duration,
            ':model' => $this->model,
            ':description' => $analysis['description'],
            ':objects' => json_encode($analysis['detected_objects'] ?? []),
            ':issues' => json_encode($analysis['issues'] ?? []),
            ':positives' => json_encode($analysis['positives'] ?? []),
            ':cleanliness' => $analysis['scores']['cleanliness'] ?? 0,
            ':organization' => $analysis['scores']['organization'] ?? 0,
            ':compliance' => $analysis['scores']['compliance'] ?? 0,
            ':safety' => $analysis['scores']['safety'] ?? 0,
            ':overall' => $analysis['scores']['overall'] ?? 0,
            ':confidence' => $analysis['scores']['confidence'] ?? 0,
            ':flags' => json_encode($analysis['flags'] ?? []),
            ':recs' => json_encode($analysis['recommendations'] ?? []),
            ':follow_needed' => $analysis['follow_up_needed'] ?? 0,
            ':follow_req' => $followReq,
            ':id' => $imageId
        ]);
        if (!$ok) {
            $errInfo = $stmt->errorInfo();
            throw new Exception('Failed to store analysis results: '.$errInfo[2]);
        }
        if (function_exists('sr_log_event')) {
            sr_log_event('ai_analysis_stored', [
                'image_id'=>$imageId,
                'duration_ms'=>$duration,
                'overall_score'=>$analysis['scores']['overall'] ?? 0,
                'confidence'=>$analysis['scores']['confidence'] ?? 0,
                'follow_up_requests'=>count($analysis['follow_up_requests'] ?? []),
            ]);
        }
    }

    /**
     * Generate overall report summary from all image analyses
     */
    private function generateReportSummary(int $reportId): array {
        $pdo = sr_pdo();
        if (!$pdo) { throw new Exception('Database unavailable'); }
        $stats = sr_query_one("SELECT
            COUNT(*) as total_images,
            AVG(ai_overall_score) as avg_score,
            AVG(ai_cleanliness_score) as avg_cleanliness,
            AVG(ai_organization_score) as avg_organization,
            AVG(ai_safety_score) as avg_safety,
            AVG(ai_compliance_score) as avg_compliance,
            AVG(ai_confidence) as avg_confidence
        FROM store_report_images
        WHERE report_id = ? AND ai_analyzed = TRUE AND deleted_at IS NULL", [$reportId]) ?? [];

        $rows = sr_query("SELECT ai_detected_issues, ai_recommendations, ai_flags
                FROM store_report_images
                WHERE report_id = ? AND ai_analyzed = TRUE AND deleted_at IS NULL", [$reportId]);

        $allIssues = [];
        $allRecommendations = [];
        $allFlags = [];
        foreach ($rows as $row) {
            if (!empty($row['ai_detected_issues'])) {
                $issues = json_decode((string)$row['ai_detected_issues'], true);
                $allIssues = array_merge($allIssues, $issues ?: []);
            }
            if (!empty($row['ai_recommendations'])) {
                $recs = json_decode((string)$row['ai_recommendations'], true);
                $allRecommendations = array_merge($allRecommendations, $recs ?: []);
            }
            if (!empty($row['ai_flags'])) {
                $flags = json_decode((string)$row['ai_flags'], true);
                $allFlags = array_merge($allFlags, $flags ?: []);
            }
        }

        // Deduplicate and prioritize
        $allIssues = array_unique($allIssues);
        $allRecommendations = array_unique($allRecommendations);

        // Generate executive summary using AI
        $summary = $this->generateExecutiveSummary($reportId, $stats, $allIssues, $allRecommendations, $allFlags);

        return [
            'summary' => $summary,
            'stats' => $stats,
            'issues' => array_values($allIssues),
            'recommendations' => array_values($allRecommendations),
            'flags' => $allFlags
        ];
    }

    /**
     * Generate executive summary using AI
     */
    private function generateExecutiveSummary(int $reportId, array $stats, array $issues, array $recommendations, array $flags): string {
        $context = $this->getReportContext($reportId);

        $prompt = "You are generating an executive summary for a store inspection report.\n\n";
        $prompt .= "Store: {$context['outlet_name']}\n";
        $prompt .= "Images analyzed: {$stats['total_images']}\n";
        $prompt .= "Overall AI score: " . round($stats['avg_score'], 1) . "/100\n";
        $prompt .= "Cleanliness: " . round($stats['avg_cleanliness'], 1) . "/100\n";
        $prompt .= "Organization: " . round($stats['avg_organization'], 1) . "/100\n";
        $prompt .= "Safety: " . round($stats['avg_safety'], 1) . "/100\n";
        $prompt .= "Compliance: " . round($stats['avg_compliance'], 1) . "/100\n\n";

        $prompt .= "Issues detected:\n";
        foreach (array_slice($issues, 0, 10) as $issue) {
            $prompt .= "- {$issue}\n";
        }

        $prompt .= "\nProvide a 2-3 paragraph executive summary highlighting:\n";
        $prompt .= "1. Overall assessment and grade\n";
        $prompt .= "2. Key strengths\n";
        $prompt .= "3. Primary concerns\n";
        $prompt .= "4. Critical action items\n\n";
        $prompt .= "Be professional, specific, and balanced.";

        try {
            $apiUrl = 'https://api.openai.com/v1/chat/completions';

            $payload = [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert retail operations consultant.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 500,
                'temperature' => 0.5
            ];

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->openai_api_key
                ],
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            return $data['choices'][0]['message']['content'] ?? 'Summary generation failed.';

        } catch (Exception $e) {
            $this->log("Failed to generate executive summary: " . $e->getMessage(), 'error');
            return "Store inspection completed with " . count($issues) . " issues identified. Review detailed analysis for specifics.";
        }
    }

    /**
     * Helper functions
     */

    private function getImageDetails(int $imageId): ?array {
        $sql = "SELECT * FROM store_report_images WHERE id = ?";
    $row = sr_query_one($sql, [$imageId]);
    return $row ?: null;
    }

    private function getReportImages(int $reportId, bool $analyzedOnly = false): array {
        $sql = "SELECT * FROM store_report_images WHERE report_id = ? AND deleted_at IS NULL";
        if ($analyzedOnly) {
            $sql .= " AND ai_analyzed = TRUE";
        } else {
            $sql .= " AND (ai_analyzed = FALSE OR ai_analyzed IS NULL OR status = 'uploaded')";
        }
        $sql .= " ORDER BY upload_timestamp ASC";

        return sr_query($sql, [$reportId]) ?? [];
    }

    private function getReportContext(int $reportId): array {
        $sql = "SELECT sr.*, vo.name as outlet_name
                FROM store_reports sr
                JOIN vend_outlets vo ON sr.outlet_id = vo.id
                WHERE sr.id = ?";
    return sr_query_one($sql, [$reportId]) ?? [];
    }

    private function getChecklistItem(int $itemId): array {
        $sql = "SELECT * FROM store_report_checklist WHERE id = ?";
    return sr_query_one($sql, [$itemId]) ?? [];
    }

    private function updateImageStatus(int $imageId, string $status, bool $analyzed = null, string $error = null): void {
        $sql = "UPDATE store_report_images SET status = ?";
        $params = [$status];
        $types = 's';

        if ($analyzed !== null) {
            $sql .= ", ai_analyzed = ?";
            $params[] = $analyzed;
            $types .= 'i';
        }

        if ($error) {
            $sql .= ", ai_error_message = ?";
            $params[] = $error;
            $types .= 's';
        }

        $sql .= " WHERE id = ?";
        $params[] = $imageId;
        $types .= 'i';

    $pdo = sr_pdo(); if(!$pdo) return; $stmt = $pdo->prepare($sql); $stmt->execute($params);
    }

    private function incrementRetryCount(int $imageId): void {
        $sql = "UPDATE store_report_images SET ai_retry_count = ai_retry_count + 1 WHERE id = ?";
    $pdo = sr_pdo(); if(!$pdo) return; $stmt = $pdo->prepare($sql); $stmt->execute([$imageId]);
    }

    private function updateReportAIStatus(int $reportId, string $status): void {
        $field = $status === 'processing' ? 'ai_analysis_started_at' : 'ai_analysis_completed_at';

        $sql = "UPDATE store_reports SET ai_analysis_status = ?, {$field} = NOW() WHERE id = ?";
    $pdo = sr_pdo(); if(!$pdo) return; $stmt = $pdo->prepare($sql); $stmt->execute([$status, $reportId]);
    }

    private function updateReportAIResults(int $reportId, array $summary): void {
        $sql = "UPDATE store_reports SET
            ai_score = ?,
            ai_summary = ?,
            ai_concerns = ?,
            ai_recommendations = ?,
            ai_confidence_score = ?,
            images_analyzed = ?
        WHERE id = ?";

        $pdo = sr_pdo(); if(!$pdo) return; $stmt = $pdo->prepare($sql); $stmt->execute([
            $summary['stats']['avg_score'],
            $summary['summary'],
            json_encode($summary['issues']),
            json_encode($summary['recommendations']),
            $summary['stats']['avg_confidence'],
            $summary['stats']['total_images'],
            $reportId
        ]);
        if (function_exists('sr_log_event')) { sr_log_event('ai_report_summary', ['report_id'=>$reportId,'avg_score'=>$summary['stats']['avg_score'],'images_analyzed'=>$summary['stats']['total_images']]); }
    }

    private function createAIPhotoRequests(int $reportId, int $triggerImageId, array $requests): void {
        foreach ($requests as $request) {
            $sql = "INSERT INTO store_report_ai_requests
                    (report_id, trigger_image_id, request_type, priority, request_title, request_description, ai_reasoning)
                    VALUES (?, ?, 'follow_up', ?, ?, ?, ?)";

            $pdo = sr_pdo(); if(!$pdo) return; $stmt = $pdo->prepare($sql); $stmt->execute([
                $reportId,
                $triggerImageId,
                $request['priority'] ?? 'medium',
                $request['title'],
                $request['description'],
                $request['reason'] ?? ''
            ]);
        }
        if (function_exists('sr_log_event')) { sr_log_event('ai_followup_requests_created', ['report_id'=>$reportId,'trigger_image_id'=>$triggerImageId,'count'=>count($requests)]); }
    }

    private function log(string $message, string $level = 'info'): void {
        if ($this->logger) {
            $this->logger->log($level, $message);
        } else {
            error_log("[StoreReportAI] [{$level}] {$message}");
        }
    }
}
