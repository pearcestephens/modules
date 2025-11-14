<?php
/**
 * Computer Vision Results Callback API
 *
 * Receives async results from Python CV pipeline:
 * - Frame analysis results
 * - Behavioral detections
 * - Anomaly alerts
 * - Batch processing completions
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../../shared/functions/db_connect.php';

header('Content-Type: application/json');

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Only POST requests allowed'], 405);
}

// Verify authentication token
$authToken = $_SERVER['HTTP_X_CV_AUTH_TOKEN'] ?? '';
$expectedToken = $_ENV['CV_PIPELINE_TOKEN'] ?? 'default_dev_token';

if ($authToken !== $expectedToken) {
    error_log('CV Callback: Invalid auth token');
    jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

// Parse JSON payload
$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload) {
    jsonResponse(['success' => false, 'error' => 'Invalid JSON payload'], 400);
}

// Route by result type
$resultType = $payload['result_type'] ?? 'unknown';

try {
    $db = db_connect();

    switch ($resultType) {
        case 'frame_analysis':
            handleFrameAnalysis($db, $payload);
            break;

        case 'behavioral_detection':
            handleBehavioralDetection($db, $payload);
            break;

        case 'anomaly_alert':
            handleAnomalyAlert($db, $payload);
            break;

        case 'batch_complete':
            handleBatchComplete($db, $payload);
            break;

        case 'baseline_update':
            handleBaselineUpdate($db, $payload);
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Unknown result type: ' . $resultType], 400);
    }

} catch (\Exception $e) {
    error_log('CV Callback error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
}

/**
 * Handle frame analysis results
 */
function handleFrameAnalysis(\PDO $db, array $payload): void
{
    $required = ['session_id', 'camera_id', 'frame_timestamp', 'analysis_results'];
    foreach ($required as $field) {
        if (!isset($payload[$field])) {
            jsonResponse(['success' => false, 'error' => "Missing required field: $field"], 400);
        }
    }

    // Store frame analysis
    $stmt = $db->prepare("
        INSERT INTO cv_analysis_results (
            staff_id,
            camera_id,
            analysis_period,
            analysis_date,
            total_frames_analyzed,
            behavioral_indicators,
            anomaly_detections,
            behavioral_risk_score,
            risk_level
        ) VALUES (
            :staff_id,
            :camera_id,
            'realtime',
            NOW(),
            1,
            :behavioral_indicators,
            :anomaly_detections,
            :risk_score,
            :risk_level
        )
        ON DUPLICATE KEY UPDATE
            total_frames_analyzed = total_frames_analyzed + 1,
            behavioral_indicators = :behavioral_indicators,
            anomaly_detections = :anomaly_detections,
            behavioral_risk_score = :risk_score,
            risk_level = :risk_level
    ");

    $analysis = $payload['analysis_results'];
    $riskScore = calculateRiskScore($analysis);
    $riskLevel = getRiskLevel($riskScore);

    $stmt->execute([
        'staff_id' => $payload['staff_id'] ?? null,
        'camera_id' => $payload['camera_id'],
        'behavioral_indicators' => json_encode($analysis['indicators'] ?? []),
        'anomaly_detections' => json_encode($analysis['anomalies'] ?? []),
        'risk_score' => $riskScore,
        'risk_level' => $riskLevel
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Frame analysis recorded',
        'risk_score' => $riskScore,
        'risk_level' => $riskLevel
    ]);
}

/**
 * Handle behavioral detection events
 */
function handleBehavioralDetection(\PDO $db, array $payload): void
{
    $required = ['staff_id', 'camera_id', 'detection_type', 'confidence'];
    foreach ($required as $field) {
        if (!isset($payload[$field])) {
            jsonResponse(['success' => false, 'error' => "Missing required field: $field"], 400);
        }
    }

    // Store behavioral detection
    $stmt = $db->prepare("
        INSERT INTO cv_behavioral_detections (
            staff_id,
            camera_id,
            detection_timestamp,
            detection_type,
            detection_category,
            confidence_score,
            deviation_from_baseline,
            context_data,
            video_frame_path
        ) VALUES (
            :staff_id,
            :camera_id,
            :detection_timestamp,
            :detection_type,
            :detection_category,
            :confidence_score,
            :deviation_from_baseline,
            :context_data,
            :video_frame_path
        )
    ");

    $stmt->execute([
        'staff_id' => $payload['staff_id'],
        'camera_id' => $payload['camera_id'],
        'detection_timestamp' => $payload['timestamp'] ?? date('Y-m-d H:i:s'),
        'detection_type' => $payload['detection_type'],
        'detection_category' => $payload['category'] ?? 'general',
        'confidence_score' => $payload['confidence'],
        'deviation_from_baseline' => $payload['deviation'] ?? null,
        'context_data' => json_encode($payload['context'] ?? []),
        'video_frame_path' => $payload['frame_path'] ?? null
    ]);

    $detectionId = $db->lastInsertId();

    // Check if alert should be generated
    if ($payload['confidence'] >= 0.8 || ($payload['deviation'] ?? 0) >= 0.7) {
        generateAlert($db, $detectionId, $payload);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Behavioral detection recorded',
        'detection_id' => $detectionId
    ]);
}

/**
 * Handle anomaly alerts
 */
function handleAnomalyAlert(\PDO $db, array $payload): void
{
    $required = ['staff_id', 'camera_id', 'anomaly_type', 'severity'];
    foreach ($required as $field) {
        if (!isset($payload[$field])) {
            jsonResponse(['success' => false, 'error' => "Missing required field: $field"], 400);
        }
    }

    // Generate alert
    $stmt = $db->prepare("
        INSERT INTO cv_behavioral_alerts (
            staff_id,
            camera_id,
            alert_timestamp,
            alert_type,
            severity,
            behavioral_indicators,
            risk_score,
            video_evidence,
            requires_investigation,
            notified_staff
        ) VALUES (
            :staff_id,
            :camera_id,
            NOW(),
            :alert_type,
            :severity,
            :behavioral_indicators,
            :risk_score,
            :video_evidence,
            :requires_investigation,
            0
        )
    ");

    $stmt->execute([
        'staff_id' => $payload['staff_id'],
        'camera_id' => $payload['camera_id'],
        'alert_type' => $payload['anomaly_type'],
        'severity' => $payload['severity'],
        'behavioral_indicators' => json_encode($payload['indicators'] ?? []),
        'risk_score' => $payload['risk_score'] ?? 0.9,
        'video_evidence' => json_encode($payload['evidence'] ?? []),
        'requires_investigation' => ($payload['severity'] === 'CRITICAL' || $payload['severity'] === 'HIGH') ? 1 : 0
    ]);

    $alertId = $db->lastInsertId();

    // Notify if critical
    if ($payload['severity'] === 'CRITICAL') {
        notifySecurityTeam($payload);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Anomaly alert generated',
        'alert_id' => $alertId,
        'requires_investigation' => ($payload['severity'] === 'CRITICAL' || $payload['severity'] === 'HIGH')
    ]);
}

/**
 * Handle batch processing completion
 */
function handleBatchComplete(\PDO $db, array $payload): void
{
    // Update session status
    $stmt = $db->prepare("
        UPDATE cv_analysis_results
        SET
            total_frames_analyzed = total_frames_analyzed + :frames_processed,
            processing_complete = 1
        WHERE camera_id = :camera_id
        AND analysis_date = CURDATE()
    ");

    $stmt->execute([
        'frames_processed' => $payload['frames_processed'] ?? 0,
        'camera_id' => $payload['camera_id'] ?? 0
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Batch processing complete',
        'frames_processed' => $payload['frames_processed'] ?? 0
    ]);
}

/**
 * Handle baseline updates
 */
function handleBaselineUpdate(\PDO $db, array $payload): void
{
    $required = ['staff_id', 'baseline_data'];
    foreach ($required as $field) {
        if (!isset($payload[$field])) {
            jsonResponse(['success' => false, 'error' => "Missing required field: $field"], 400);
        }
    }

    // Update or insert baseline
    $stmt = $db->prepare("
        INSERT INTO cv_behavioral_baselines (
            staff_id,
            baseline_period_start,
            baseline_period_end,
            emotion_profile,
            posture_profile,
            movement_patterns,
            gaze_patterns,
            interaction_patterns,
            total_frames_analyzed
        ) VALUES (
            :staff_id,
            :period_start,
            :period_end,
            :emotion_profile,
            :posture_profile,
            :movement_patterns,
            :gaze_patterns,
            :interaction_patterns,
            :total_frames
        )
        ON DUPLICATE KEY UPDATE
            emotion_profile = :emotion_profile,
            posture_profile = :posture_profile,
            movement_patterns = :movement_patterns,
            gaze_patterns = :gaze_patterns,
            interaction_patterns = :interaction_patterns,
            total_frames_analyzed = :total_frames,
            baseline_period_end = :period_end
    ");

    $baseline = $payload['baseline_data'];

    $stmt->execute([
        'staff_id' => $payload['staff_id'],
        'period_start' => $payload['period_start'] ?? date('Y-m-d', strtotime('-30 days')),
        'period_end' => $payload['period_end'] ?? date('Y-m-d'),
        'emotion_profile' => json_encode($baseline['emotions'] ?? []),
        'posture_profile' => json_encode($baseline['posture'] ?? []),
        'movement_patterns' => json_encode($baseline['movement'] ?? []),
        'gaze_patterns' => json_encode($baseline['gaze'] ?? []),
        'interaction_patterns' => json_encode($baseline['interactions'] ?? []),
        'total_frames' => $baseline['total_frames'] ?? 0
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Baseline updated successfully'
    ]);
}

/**
 * Calculate risk score from analysis
 */
function calculateRiskScore(array $analysis): float
{
    $indicators = $analysis['indicators'] ?? [];
    $anomalies = $analysis['anomalies'] ?? [];

    $score = 0;
    $weights = [
        'stress' => 0.3,
        'anxiety' => 0.25,
        'deception' => 0.4,
        'nervousness' => 0.2
    ];

    foreach ($indicators as $indicator => $value) {
        $weight = $weights[$indicator] ?? 0.1;
        $score += $value * $weight;
    }

    // Add anomaly contribution
    $score += count($anomalies) * 0.1;

    return min(1.0, $score);
}

/**
 * Get risk level from score
 */
function getRiskLevel(float $score): string
{
    if ($score >= 0.8) return 'CRITICAL';
    if ($score >= 0.65) return 'HIGH';
    if ($score >= 0.4) return 'MEDIUM';
    if ($score >= 0.2) return 'LOW';
    return 'MINIMAL';
}

/**
 * Generate alert from detection
 */
function generateAlert(\PDO $db, int $detectionId, array $payload): void
{
    $stmt = $db->prepare("
        INSERT INTO cv_behavioral_alerts (
            staff_id,
            camera_id,
            alert_timestamp,
            alert_type,
            severity,
            behavioral_indicators,
            risk_score,
            requires_investigation
        ) VALUES (
            :staff_id,
            :camera_id,
            NOW(),
            :alert_type,
            :severity,
            :behavioral_indicators,
            :risk_score,
            1
        )
    ");

    $stmt->execute([
        'staff_id' => $payload['staff_id'],
        'camera_id' => $payload['camera_id'],
        'alert_type' => $payload['detection_type'],
        'severity' => ($payload['confidence'] >= 0.9) ? 'HIGH' : 'MEDIUM',
        'behavioral_indicators' => json_encode($payload['context'] ?? []),
        'risk_score' => $payload['confidence']
    ]);
}

/**
 * Notify security team of critical alerts
 */
function notifySecurityTeam(array $payload): void
{
    // TODO: Implement notification system (email, SMS, Slack, etc.)
    error_log('CRITICAL ALERT: ' . json_encode($payload));
}

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}
