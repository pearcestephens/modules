<?php
/**
 * Smart-Cron Task: AI Insight Generation
 *
 * Generates AI-powered insights for users using ChatGPT API
 * Runs every hour to process pending insight requests
 *
 * @package CIS\FlaggedProducts\Cron
 */

require_once __DIR__ . '/bootstrap.php';

use FlaggedProducts\Lib\Logger;

// Track execution start
$executionStart = microtime(true);

try {
    // Log task start
    Logger::cronTaskStarted('generate_ai_insights', [
        'scheduled_time' => date('Y-m-d H:i:s')
    ]);

    CISLogger::info('flagged_products_cron', 'Starting AI insight generation');

    // Get pending AI insight tasks from smart_cron_tasks
    $sql = "SELECT * FROM smart_cron_tasks
            WHERE task_name = 'generate_flagged_products_insight'
            AND status = 'pending'
            AND (retry_count < 3 OR retry_count IS NULL)
            ORDER BY created_at ASC
            LIMIT 10";

    $tasks = sql_query_collection_safe($sql, []);

    if (empty($tasks)) {
        CISLogger::info('flagged_products_cron', 'No pending AI insight tasks');

        // Log completion with no tasks
        $executionTime = microtime(true) - $executionStart;
        Logger::cronTaskCompleted('generate_ai_insights', true, [
            'pending_tasks' => 0,
            'processed' => 0,
            'failed' => 0,
            'execution_time' => round($executionTime, 2)
        ]);

        echo json_encode(['success' => true, 'message' => 'No pending tasks']);
        exit;
    }

    $processed = 0;
    $failed = 0;
    $totalTokensUsed = 0;
    $aiMethod = 'unknown';

    foreach ($tasks as $task) {
        try {
            $taskData = json_decode($task->task_data, true);
            $userId = $taskData['user_id'];
            $outletId = $taskData['outlet_id'];
            $stats = $taskData['stats'];

            // Generate AI insight using ChatGPT API
            $insightResult = generateChatGPTInsight($stats);
            $insight = $insightResult['insight'];
            $aiMethod = $insightResult['method'];
            $tokensUsed = $insightResult['tokens_used'] ?? 0;
            $totalTokensUsed += $tokensUsed;

            if ($insight) {
                // Store insight
                $sql = "INSERT INTO flagged_products_ai_insights
                        (user_id, outlet_id, insight_text, stats_snapshot, generated_at)
                        VALUES (?, ?, ?, ?, NOW())";

                sql_query_update_or_insert_safe($sql, [
                    $userId,
                    $outletId,
                    $insight,
                    json_encode($stats)
                ]);

                // Mark task as completed
                $updateSql = "UPDATE smart_cron_tasks
                              SET status = 'completed',
                                  completed_at = NOW(),
                                  result_data = ?
                              WHERE id = ?";

                sql_query_update_or_insert_safe($updateSql, [
                    json_encode(['success' => true, 'insight_length' => strlen($insight)]),
                    $task->id
                ]);

                // Log insight generation
                Logger::insightGenerated('ai_insight', [
                    'user_id' => $userId,
                    'outlet_id' => $outletId,
                    'method' => $aiMethod,
                    'tokens_used' => $tokensUsed,
                    'insight_length' => strlen($insight),
                    'stats' => $stats
                ], $insightResult['confidence'] ?? null);

                $processed++;
            } else {
                throw new Exception('Failed to generate insight');
            }

        } catch (Exception $e) {
            CISLogger::error('flagged_products_cron', "Insight generation failed for task {$task->id}: " . $e->getMessage());

            // Mark task as failed, increment retry
            $updateSql = "UPDATE smart_cron_tasks
                          SET status = 'failed',
                              retry_count = COALESCE(retry_count, 0) + 1,
                              result_data = ?
                          WHERE id = ?";

            sql_query_update_or_insert_safe($updateSql, [
                json_encode(['error' => $e->getMessage()]),
                $task->id
            ]);

            $failed++;
        }
    }

    CISLogger::info('flagged_products_cron', "AI insight generation completed: {$processed} succeeded, {$failed} failed");

    // Log successful completion
    $executionTime = microtime(true) - $executionStart;
    Logger::cronTaskCompleted('generate_ai_insights', true, [
        'pending_tasks' => count($tasks),
        'processed' => $processed,
        'failed' => $failed,
        'ai_method' => $aiMethod,
        'total_tokens_used' => $totalTokensUsed,
        'execution_time' => round($executionTime, 2)
    ]);

    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'failed' => $failed,
        'total_tasks' => count($tasks)
    ]);

} catch (Exception $e) {
    CISLogger::error('flagged_products_cron', 'AI insight generation failed: ' . $e->getMessage());

    // Log failed completion
    $executionTime = microtime(true) - $executionStart;
    Logger::cronTaskCompleted('generate_ai_insights', false, [
        'execution_time' => round($executionTime, 2)
    ], $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Generate ChatGPT insight from user stats
 */
function generateChatGPTInsight(array $stats): array {
    // Get OpenAI API key from environment or config
    $apiKey = getenv('OPENAI_API_KEY') ?: null;

    if (!$apiKey) {
        CISLogger::warning('flagged_products_cron', 'OpenAI API key not configured, using fallback insights');
        return [
            'insight' => generateFallbackInsight($stats),
            'method' => 'fallback',
            'tokens_used' => 0,
            'confidence' => 0.7
        ];
    }

    $prompt = "You are analyzing a retail employee's stock verification performance. Generate a brief, encouraging insight (2-3 sentences) based on these stats:\n\n";
    $prompt .= "- Products completed: {$stats['products_completed']}\n";
    $prompt .= "- Accuracy: {$stats['accuracy']}%\n";
    $prompt .= "- Current streak: {$stats['current_streak']} days\n";
    $prompt .= "- Average time per product: {$stats['avg_time_per_product']} seconds\n";
    $prompt .= "- Total points: {$stats['total_points']}\n\n";
    $prompt .= "Focus on: achievements, areas of excellence, and one actionable tip to improve.";

    try {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a motivational performance coach for retail staff.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 150,
                'temperature' => 0.7
            ]),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $insight = $data['choices'][0]['message']['content'] ?? null;
            $tokensUsed = $data['usage']['total_tokens'] ?? 0;

            return [
                'insight' => $insight,
                'method' => 'chatgpt',
                'tokens_used' => $tokensUsed,
                'confidence' => 0.95
            ];
        } else {
            CISLogger::warning('flagged_products_cron', "OpenAI API returned {$httpCode}, using fallback");
            return [
                'insight' => generateFallbackInsight($stats),
                'method' => 'fallback_after_error',
                'tokens_used' => 0,
                'confidence' => 0.7
            ];
        }

    } catch (Exception $e) {
        CISLogger::error('flagged_products_cron', 'ChatGPT API error: ' . $e->getMessage());
        return [
            'insight' => generateFallbackInsight($stats),
            'method' => 'fallback_after_exception',
            'tokens_used' => 0,
            'confidence' => 0.7
        ];
    }
}

/**
 * Generate fallback insight when ChatGPT unavailable
 */
function generateFallbackInsight(array $stats): string {
    $insights = [];

    // Accuracy-based insights
    if ($stats['accuracy'] >= 98) {
        $insights[] = "Outstanding accuracy of {$stats['accuracy']}%! Your attention to detail is exceptional.";
    } elseif ($stats['accuracy'] >= 90) {
        $insights[] = "Great accuracy at {$stats['accuracy']}%. Keep up the consistent performance!";
    } else {
        $insights[] = "Your accuracy is {$stats['accuracy']}%. Take a bit more time on each product to improve precision.";
    }

    // Streak-based insights
    if ($stats['current_streak'] >= 7) {
        $insights[] = "Impressive {$stats['current_streak']}-day streak! Your consistency is remarkable.";
    } elseif ($stats['current_streak'] >= 3) {
        $insights[] = "Nice {$stats['current_streak']}-day streak building! Keep the momentum going.";
    }

    // Speed insights
    if ($stats['avg_time_per_product'] <= 30) {
        $insights[] = "You're completing products quickly at {$stats['avg_time_per_product']}s average. Efficient work!";
    } elseif ($stats['avg_time_per_product'] > 60) {
        $insights[] = "Try to maintain focus to reduce your {$stats['avg_time_per_product']}s average time per product.";
    }

    // Volume insights
    if ($stats['products_completed'] >= 50) {
        $insights[] = "Wow! {$stats['products_completed']} products completed. You're a verification champion!";
    } elseif ($stats['products_completed'] >= 20) {
        $insights[] = "Great progress with {$stats['products_completed']} products verified.";
    }

    // Pick 2-3 random insights
    shuffle($insights);
    return implode(' ', array_slice($insights, 0, 2));
}
