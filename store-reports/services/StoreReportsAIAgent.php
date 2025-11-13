<?php
/**
 * Store Reports AI Agent - Full Personality Implementation
 *
 * AGENT PROFILE:
 * Name: "Inspector Iris" (Intelligent Retail Inspection System)
 * Personality: Meticulous, detail-obsessed, perfectionist with high standards
 * Mission: Ensure every store meets EXCEPTIONAL quality standards
 * Tone: Professional but demanding, thorough, never satisfied with "good enough"
 *
 * CAPABILITIES:
 * - Multi-modal analysis (vision, voice, text)
 * - Conversation threading with memory
 * - Automatic quality grading with strict standards
 * - Follow-up question generation
 * - Cost tracking and optimization
 * - Integration with MCP Intelligence Hub (when available)
 */

declare(strict_types=1);

require_once __DIR__ . '/AIOrchestrator.php';

class StoreReportsAIAgent
{
    private AIOrchestrator $orchestrator;
    private PDO $pdo;
    private array $agentConfig;
    private array $sessionContext = [];

    // Agent personality constants
    private const AGENT_NAME = "Inspector Iris";
    private const AGENT_ROLE = "Elite Store Quality Analyst";

    private const PERSONALITY_TRAITS = [
        'perfectionist' => 10,      // 1-10 scale, 10 = maximum perfectionism
        'detail_oriented' => 10,    // Never misses anything
        'curiosity' => 9,           // Always asks follow-up questions
        'strictness' => 9,          // High grading standards
        'thoroughness' => 10,       // Exhaustive analysis
        'professionalism' => 10     // Always maintains professional tone
    ];

    private const RESPONSE_TEMPLATES = [
        'greeting' => "I'm {name}, your {role}. I maintain EXCEPTIONALLY HIGH STANDARDS and will analyze every detail of your store inspection. My analysis will be thorough, specific, and demanding. Let's ensure your store operates at PEAK QUALITY.",

        'image_received' => "Image received. Initiating METICULOUS ANALYSIS with high-detail processing. I will examine every visible surface, fixture, and detail. Stand by...",

        'low_quality_detected' => "⚠️ QUALITY CONCERN DETECTED: I've identified {count} areas requiring immediate attention. These are NOT minor issues - they impact customer experience and brand standards. Detailed analysis follows.",

        'excellence_acknowledged' => "✅ EXCELLENCE NOTED: {area} meets high standards. However, I've identified {count} opportunities for FURTHER improvement. We're not settling for 'good' - we're aiming for EXCEPTIONAL.",

        'needs_more_info' => "❓ INSUFFICIENT DATA: I need additional information to complete my analysis. Specifically: {details}. I cannot provide a thorough assessment without these details - that would compromise my standards.",

        'analysis_complete' => "📊 COMPREHENSIVE ANALYSIS COMPLETE: {word_count} words, {issue_count} issues identified, {recommendation_count} specific recommendations provided. Overall Assessment: {grade}. {followup_count} follow-up questions generated.",

        'demand_photo_evidence' => "📸 PHOTOGRAPHIC EVIDENCE REQUIRED: Your description is insufficient. I need clear, well-lit photos showing: {requirements}. I don't accept vague reports - show me the evidence.",

        'grade_justification' => "GRADE {grade} JUSTIFICATION: {reasoning}. This grade reflects {standard_name} standards, which are NON-NEGOTIABLE.",

        'cost_report' => "💰 Analysis Cost: ${cost_nzd} NZD ({tokens} tokens). This investment in quality assurance identified ${value_nzd} worth of potential issues.",

        'closing' => "Analysis complete. I've maintained my UNCOMPROMISING STANDARDS throughout. Address the critical issues within 24 hours, moderate issues within 72 hours. Schedule follow-up inspection in {days} days."
    ];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;

        // Initialize orchestrator with personality settings
        $this->orchestrator = new AIOrchestrator($pdo, array_merge([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'max_tokens' => 2000,       // More tokens for detailed analysis
            'temperature' => 0.3,       // Lower temperature for consistent, precise output
            'personality_enabled' => true,
            'strict_mode' => true
        ], $config));

        $this->agentConfig = $config;
    }

    /**
     * Process complete store report with full AI analysis
     *
     * @param int $reportId Report ID from store_reports table
     * @return array Complete analysis with personality-driven insights
     */
    public function processStoreReport(int $reportId): array
    {
        $startTime = microtime(true);

        // Load report data
        $report = $this->loadReport($reportId);
        if (!$report) {
            throw new \RuntimeException("Report #{$reportId} not found");
        }

        // Initialize session context
        $this->sessionContext = [
            'report_id' => $reportId,
            'store_id' => $report['store_id'],
            'inspector_id' => $report['user_id'],
            'inspection_type' => $report['report_type'] ?? 'general',
            'started_at' => date('Y-m-d H:i:s')
        ];

        $results = [
            'agent' => self::AGENT_NAME,
            'greeting' => $this->generateGreeting(),
            'analyses' => [],
            'overall_assessment' => [],
            'conversation_log' => [],
            'cost_tracking' => [],
            'recommendations' => [],
            'follow_up_questions' => []
        ];

        // Analyze all images in report
        $images = $this->loadReportImages($reportId);

        if (empty($images)) {
            return $this->handleNoImages($reportId);
        }

        foreach ($images as $index => $image) {
            $analysisType = $image['analysis_type'] ?? 'comprehensive';

            $results['conversation_log'][] = [
                'type' => 'status',
                'message' => str_replace('{name}', self::AGENT_NAME, self::RESPONSE_TEMPLATES['image_received']),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // Perform meticulous analysis
            $analysis = $this->orchestrator->analyzeImage(
                $image['file_path'],
                $analysisType,
                [
                    'report_id' => $reportId,
                    'store_id' => $report['store_id'],
                    'image_index' => $index + 1,
                    'total_images' => count($images),
                    'previous_findings' => $this->getPreviousFindings($reportId)
                ]
            );

            // Store analysis in database
            $this->storeAnalysis($reportId, $image['image_id'], $analysis);

            // Apply personality-driven interpretation
            $interpretation = $this->applyPersonalityFilter($analysis);

            $results['analyses'][] = [
                'image_id' => $image['image_id'],
                'analysis' => $interpretation,
                'raw_response' => $analysis['raw_response'],
                'metadata' => $analysis['metadata']
            ];

            // Generate personality-driven commentary
            if (!empty($interpretation['issues_found'])) {
                $results['conversation_log'][] = [
                    'type' => 'concern',
                    'message' => str_replace(
                        '{count}',
                        count($interpretation['issues_found']),
                        self::RESPONSE_TEMPLATES['low_quality_detected']
                    ),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

            // Collect follow-up questions
            $results['follow_up_questions'] = array_merge(
                $results['follow_up_questions'],
                $analysis['follow_up_questions'] ?? []
            );
        }

        // Generate overall assessment with STRICT standards
        $overallAssessment = $this->generateOverallAssessment($results['analyses']);
        $results['overall_assessment'] = $overallAssessment;

        // Apply grade justification template
        if (isset($overallAssessment['overall_grade'])) {
            $results['conversation_log'][] = [
                'type' => 'grade',
                'message' => $this->formatGradeJustification($overallAssessment),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }

        // Compile comprehensive recommendations
        $results['recommendations'] = $this->compileRecommendations($results['analyses']);

        // Cost tracking and value justification
        $stats = $this->orchestrator->getConversationStats();
        $estimatedValue = $this->estimateIssueValue($results['analyses']);

        $results['cost_tracking'] = [
            'stats' => $stats,
            'cost_nzd' => $stats['total_cost_nzd'],
            'estimated_issue_value_nzd' => $estimatedValue,
            'roi_ratio' => $estimatedValue > 0 ? round($estimatedValue / max(0.01, $stats['total_cost_nzd']), 1) : 0
        ];

        $results['conversation_log'][] = [
            'type' => 'cost',
            'message' => str_replace(
                ['{cost_nzd}', '{tokens}', '{value_nzd}'],
                [
                    number_format($stats['total_cost_nzd'], 2),
                    number_format($stats['total_tokens']),
                    number_format($estimatedValue, 2)
                ],
                self::RESPONSE_TEMPLATES['cost_report']
            ),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Determine follow-up schedule based on findings
        $followUpDays = $this->calculateFollowUpSchedule($overallAssessment);

        $results['conversation_log'][] = [
            'type' => 'closing',
            'message' => str_replace('{days}', $followUpDays, self::RESPONSE_TEMPLATES['closing']),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Store complete analysis in database
        $this->storeCompleteAnalysis($reportId, $results);

        $results['processing_time_seconds'] = round(microtime(true) - $startTime, 2);

        return $results;
    }

    /**
     * Generate personalized greeting based on context
     */
    private function generateGreeting(): string
    {
        return str_replace(
            ['{name}', '{role}'],
            [self::AGENT_NAME, self::AGENT_ROLE],
            self::RESPONSE_TEMPLATES['greeting']
        );
    }

    /**
     * Apply personality filter to analysis (make it more demanding/thorough)
     */
    private function applyPersonalityFilter(array $analysis): array
    {
        $filtered = $analysis['analysis'];

        // Downgrade grades based on personality (we're STRICT)
        if (isset($filtered['grades'])) {
            foreach ($filtered['grades'] as $category => &$grade) {
                // If grade is B or C and we found issues, downgrade
                if (in_array($grade, ['B', 'C']) && count($filtered['issues_found']) > 2) {
                    $gradeValues = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'F' => 4];
                    $gradeLetters = ['A', 'B', 'C', 'D', 'F'];
                    $currentIndex = $gradeValues[$grade];
                    $grade = $gradeLetters[min(4, $currentIndex + 1)]; // Downgrade by one

                    $filtered['grade_adjustments'][] = [
                        'category' => $category,
                        'reason' => 'Downgraded due to high standards and multiple issues detected'
                    ];
                }
            }
        }

        // Add personality commentary to issues
        $filtered['issues_found'] = array_map(function($issue) {
            return "⚠️ " . strtoupper(substr($issue, 0, 1)) . substr($issue, 1);
        }, $filtered['issues_found'] ?? []);

        // Enhance recommendations with urgency
        $filtered['recommendations'] = array_map(function($rec) {
            if (stripos($rec, 'immediate') === false && stripos($rec, 'critical') === false) {
                return "ACTION REQUIRED: " . $rec;
            }
            return $rec;
        }, $filtered['recommendations'] ?? []);

        return $filtered;
    }

    /**
     * Generate overall assessment with strict grading
     */
    private function generateOverallAssessment(array $analyses): array
    {
        $allGrades = [];
        $allIssues = [];
        $allRecommendations = [];
        $criticalCount = 0;

        foreach ($analyses as $analysis) {
            $data = $analysis['analysis'];

            if (isset($data['grades'])) {
                foreach ($data['grades'] as $category => $grade) {
                    $allGrades[$category][] = $grade;
                }
            }

            $allIssues = array_merge($allIssues, $data['issues_found'] ?? []);
            $allRecommendations = array_merge($allRecommendations, $data['recommendations'] ?? []);

            // Count critical issues (D or F grades)
            foreach ($data['grades'] ?? [] as $grade) {
                if (in_array($grade, ['D', 'F'])) {
                    $criticalCount++;
                }
            }
        }

        // Calculate average grades per category
        $averageGrades = [];
        $gradeValues = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'F' => 1];
        $gradeLetters = ['A', 'B', 'C', 'D', 'F'];

        foreach ($allGrades as $category => $grades) {
            $sum = 0;
            foreach ($grades as $grade) {
                $sum += $gradeValues[$grade] ?? 3;
            }
            $avg = round($sum / count($grades));
            $averageGrades[$category] = $gradeLetters[5 - $avg] ?? 'C';
        }

        // Calculate overall grade (weighted)
        $overallScore = 0;
        $categoryWeights = [
            'cleanliness' => 0.25,
            'organization' => 0.25,
            'safety' => 0.35,      // Safety weighted higher
            'compliance' => 0.15
        ];

        foreach ($averageGrades as $category => $grade) {
            $weight = $categoryWeights[$category] ?? 0.25;
            $overallScore += ($gradeValues[$grade] ?? 3) * $weight;
        }

        $overallGrade = $gradeLetters[5 - round($overallScore)] ?? 'C';

        // Apply penalty for critical issues
        if ($criticalCount > 2) {
            $gradeIndex = array_search($overallGrade, $gradeLetters);
            $overallGrade = $gradeLetters[min(4, $gradeIndex + 1)] ?? 'D';
        }

        return [
            'overall_grade' => $overallGrade,
            'category_grades' => $averageGrades,
            'overall_score' => round($overallScore * 20, 1), // 0-100 scale
            'total_issues' => count($allIssues),
            'critical_issues' => $criticalCount,
            'total_recommendations' => count($allRecommendations),
            'status' => $this->determineStatus($overallGrade, $criticalCount),
            'priority' => $criticalCount > 0 ? 'HIGH' : ($overallGrade === 'C' ? 'MEDIUM' : 'LOW')
        ];
    }

    /**
     * Determine status based on grade and critical issues
     */
    private function determineStatus(string $grade, int $criticalCount): string
    {
        if ($grade === 'F' || $criticalCount > 3) return 'URGENT ACTION REQUIRED';
        if ($grade === 'D' || $criticalCount > 1) return 'IMMEDIATE ATTENTION NEEDED';
        if ($grade === 'C') return 'IMPROVEMENTS REQUIRED';
        if ($grade === 'B') return 'GOOD BUT CAN IMPROVE';
        return 'EXCELLENT STANDARDS MAINTAINED';
    }

    /**
     * Format grade justification with personality
     */
    private function formatGradeJustification(array $assessment): string
    {
        $grade = $assessment['overall_grade'];
        $reasoning = $this->getGradeReasoning($assessment);
        $standard = "EXCEPTIONAL RETAIL QUALITY";

        return str_replace(
            ['{grade}', '{reasoning}', '{standard_name}'],
            [$grade, $reasoning, $standard],
            self::RESPONSE_TEMPLATES['grade_justification']
        );
    }

    /**
     * Get detailed reasoning for grade
     */
    private function getGradeReasoning(array $assessment): string
    {
        $reasons = [];

        foreach ($assessment['category_grades'] as $category => $grade) {
            $reasons[] = ucfirst($category) . ": {$grade}";
        }

        $reasoning = implode(", ", $reasons);

        if ($assessment['critical_issues'] > 0) {
            $reasoning .= ". {$assessment['critical_issues']} CRITICAL ISSUES identified that must be addressed immediately";
        }

        return $reasoning;
    }

    /**
     * Compile comprehensive recommendations prioritized by urgency
     */
    private function compileRecommendations(array $analyses): array
    {
        $recommendations = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis['analysis']['recommendations'] ?? [] as $rec) {
                $priority = $this->determinePriority($rec);
                $recommendations[] = [
                    'text' => $rec,
                    'priority' => $priority,
                    'category' => $this->categorizeRecommendation($rec)
                ];
            }
        }

        // Sort by priority
        usort($recommendations, function($a, $b) {
            $priorities = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
            return ($priorities[$a['priority']] ?? 2) <=> ($priorities[$b['priority']] ?? 2);
        });

        return array_slice($recommendations, 0, 15); // Top 15 recommendations
    }

    /**
     * Determine recommendation priority from text
     */
    private function determinePriority(string $text): string
    {
        if (preg_match('/\b(critical|immediate|urgent|safety)\b/i', $text)) return 'CRITICAL';
        if (preg_match('/\b(required|must|need)\b/i', $text)) return 'HIGH';
        if (preg_match('/\b(should|recommend)\b/i', $text)) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Categorize recommendation
     */
    private function categorizeRecommendation(string $text): string
    {
        if (preg_match('/\b(clean|dust|wipe)\b/i', $text)) return 'cleanliness';
        if (preg_match('/\b(organiz|display|arrang)\b/i', $text)) return 'organization';
        if (preg_match('/\b(safety|hazard|risk)\b/i', $text)) return 'safety';
        if (preg_match('/\b(complian|regulat|legal)\b/i', $text)) return 'compliance';
        return 'general';
    }

    /**
     * Estimate monetary value of issues found
     */
    private function estimateIssueValue(array $analyses): float
    {
        $value = 0.0;

        foreach ($analyses as $analysis) {
            $data = $analysis['analysis'];

            // Safety issues: $500-2000 per issue
            foreach ($data['grades'] ?? [] as $category => $grade) {
                if ($category === 'safety' && in_array($grade, ['D', 'F'])) {
                    $value += 1500;
                }
            }

            // Cleanliness issues: $200-500
            if (isset($data['grades']['cleanliness']) && in_array($data['grades']['cleanliness'], ['C', 'D', 'F'])) {
                $value += 350;
            }

            // Organization issues: $100-300
            if (isset($data['grades']['organization']) && in_array($data['grades']['organization'], ['D', 'F'])) {
                $value += 200;
            }

            // Compliance: $1000-5000
            if (isset($data['grades']['compliance']) && $data['grades']['compliance'] === 'F') {
                $value += 3000;
            }
        }

        return $value;
    }

    /**
     * Calculate follow-up inspection schedule
     */
    private function calculateFollowUpSchedule(array $assessment): int
    {
        $grade = $assessment['overall_grade'];
        $critical = $assessment['critical_issues'];

        if ($grade === 'F' || $critical > 3) return 1;  // Tomorrow
        if ($grade === 'D' || $critical > 1) return 3;  // 3 days
        if ($grade === 'C') return 7;                   // 1 week
        if ($grade === 'B') return 14;                  // 2 weeks
        return 30;                                       // 1 month
    }

    /**
     * Handle case where no images provided
     */
    private function handleNoImages(int $reportId): array
    {
        $message = str_replace(
            '{details}',
            'Clear, well-lit photographs of ALL store areas including displays, fixtures, floors, and safety equipment',
            self::RESPONSE_TEMPLATES['needs_more_info']
        );

        return [
            'success' => false,
            'agent' => self::AGENT_NAME,
            'error' => 'NO_IMAGES',
            'message' => $message,
            'requirements' => [
                'Minimum 3 high-resolution photos required',
                'Must show: overall store view, product displays, and any problem areas',
                'Lighting must be adequate for detailed analysis',
                'Photos must be clear and in focus'
            ]
        ];
    }

    /**
     * Load report from database
     */
    private function loadReport(int $reportId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM store_reports WHERE report_id = ?");
        $stmt->execute([$reportId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Load report images
     */
    private function loadReportImages(int $reportId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT image_id, file_path, analysis_type, image_order
            FROM store_report_images
            WHERE report_id = ?
            ORDER BY image_order ASC
        ");
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get previous findings for context
     */
    private function getPreviousFindings(int $reportId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ai_response, confidence_score, created_at
            FROM store_report_ai_requests
            WHERE report_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Store individual image analysis
     */
    private function storeAnalysis(int $reportId, int $imageId, array $analysis): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO store_report_ai_requests (
                report_id,
                image_id,
                provider,
                model,
                ai_prompt,
                ai_response,
                tokens_used,
                cost_nzd,
                confidence_score,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $reportId,
            $imageId,
            'openai',
            $this->orchestrator->getConversationStats()['conversation_id'] ?? 'gpt-4o',
            'Image analysis with meticulous standards',
            $analysis['raw_response'],
            $analysis['metadata']['tokens_used'] ?? 0,
            $analysis['metadata']['cost_nzd'] ?? 0,
            $analysis['analysis']['confidence'] ?? 0.7
        ]);
    }

    /**
     * Store complete analysis results
     */
    private function storeCompleteAnalysis(int $reportId, array $results): void
    {
        // Update report with AI analysis summary
        $stmt = $this->pdo->prepare("
            UPDATE store_reports
            SET
                ai_analysis_complete = 1,
                ai_overall_grade = ?,
                ai_total_issues = ?,
                ai_critical_issues = ?,
                ai_analysis_cost_nzd = ?,
                ai_follow_up_days = ?,
                updated_at = NOW()
            WHERE report_id = ?
        ");

        $assessment = $results['overall_assessment'];

        $stmt->execute([
            $assessment['overall_grade'] ?? 'C',
            $assessment['total_issues'] ?? 0,
            $assessment['critical_issues'] ?? 0,
            $results['cost_tracking']['cost_nzd'] ?? 0,
            $this->calculateFollowUpSchedule($assessment),
            $reportId
        ]);
    }
}
