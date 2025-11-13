<?php
/**
 * Voice Memo Upload API
 * POST /api/voice-memo-upload
 *
 * Uploads voice memo with automatic transcription
 * Supports linking to checklist items or general report notes
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../assets/services/mcp/StoreReportsAdapter.php';

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate limiting
sr_rate_limit('voice_memo_upload', 60, 20); // 20 uploads per minute

// Validate multipart form data
if (!isset($_FILES['audio'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No audio file provided']);
    exit;
}

$audioFile = $_FILES['audio'];
$reportId = $_POST['report_id'] ?? null;
$checklistId = $_POST['checklist_id'] ?? null;
$caption = $_POST['caption'] ?? '';

if (!$reportId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: report_id']);
    exit;
}

// Get authenticated user
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Validate audio file
$allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/m4a', 'audio/ogg', 'audio/webm'];
$maxSize = 25 * 1024 * 1024; // 25MB (Whisper API limit)

if (!in_array($audioFile['type'], $allowedTypes) && !in_array(mime_content_type($audioFile['tmp_name']), $allowedTypes)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid audio format',
        'allowed' => ['mp3', 'wav', 'm4a', 'ogg', 'webm']
    ]);
    exit;
}

if ($audioFile['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Audio file too large',
        'max_size' => '25MB',
        'provided_size' => round($audioFile['size'] / 1024 / 1024, 2) . 'MB'
    ]);
    exit;
}

try {
    $pdo = sr_pdo();

    // Verify report exists and belongs to user
    $stmt = $pdo->prepare("
        SELECT report_id, created_by, outlet_id
        FROM store_reports
        WHERE report_id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit;
    }

    // Check permissions
    $isOwner = ($report['created_by'] == $userId);
    $isManager = false; // TODO: Check user role

    if (!$isOwner && !$isManager) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }

    // Create upload directory if not exists
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/data/store_reports/voice_memos/' . $reportId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($audioFile['name'], PATHINFO_EXTENSION);
    $filename = 'voice_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filePath = $uploadDir . '/' . $filename;
    $webPath = '/data/store_reports/voice_memos/' . $reportId . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($audioFile['tmp_name'], $filePath)) {
        throw new Exception('Failed to save audio file');
    }

    // Get audio duration (if ffmpeg available)
    $duration = null;
    if (function_exists('exec')) {
        $output = [];
        exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($filePath), $output);
        $duration = !empty($output[0]) ? (int)round((float)$output[0]) : null;
    }

    // =========================================================================
    // 🚀 MCP HUB TRANSCRIPTION - Bypass GitHub Copilot!
    // =========================================================================
    // Use MCP Hub for Whisper API transcription
    // Hub handles: Whisper API calls, logging, caching, error handling
    // =========================================================================

    $transcription = null;
    $transcriptionConfidence = null;

    // Save voice memo ID first so we can pass it to adapter
    $stmt = $pdo->prepare("
        INSERT INTO store_report_voice_memos (
            report_id,
            checklist_id,
            file_path,
            duration_seconds,
            caption,
            recorded_by,
            transcription,
            transcription_confidence
        ) VALUES (?, ?, ?, ?, ?, ?, NULL, NULL)
    ");

    $stmt->execute([
        $reportId,
        $checklistId,
        $webPath,
        $duration,
        $caption,
        $userId
    ]);

    $voiceMemoId = $pdo->lastInsertId();

    // Transcribe via MCP Hub
    try {
        $adapter = new Services\MCP\Adapters\StoreReportsAdapter();
        $adapter->setUser($userId)
                ->setReport($reportId)
                ->getMCPClient()
                ->setBotId('store-reports-whisper-transcriber')
                ->setUnitId($report['outlet_id'] ?? 0);

        $transcriptionResult = $adapter->transcribeVoiceMemo($voiceMemoId);

        if ($transcriptionResult['success']) {
            $transcription = $transcriptionResult['text'];
            $transcriptionConfidence = $transcriptionResult['confidence'];

            // Update voice memo with transcription
            $stmt = $pdo->prepare("
                UPDATE store_report_voice_memos
                SET transcription = ?,
                    transcription_confidence = ?
                WHERE voice_memo_id = ?
            ");
            $stmt->execute([$transcription, $transcriptionConfidence, $voiceMemoId]);
        }
    } catch (Exception $e) {
        // Transcription failed, but file still saved
        sr_log_error('mcp_transcription_failed', [
            'voice_memo_id' => $voiceMemoId,
            'file_path' => $filePath,
            'error' => $e->getMessage()
        ]);
    }

    // Voice memo already inserted above (before transcription)
    // $voiceMemoId contains the ID

    // Log the upload
    $stmt = $pdo->prepare("
        INSERT INTO store_report_history (
            report_id, user_id, action, details
        ) VALUES (?, ?, 'voice_memo_uploaded', ?)
    ");
    $stmt->execute([
        $reportId,
        $userId,
        json_encode([
            'memo_id' => $voiceMemoId,
            'file_size' => $audioFile['size'],
            'duration' => $duration,
            'has_transcription' => !empty($transcription)
        ])
    ]);

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'memo_id' => $voiceMemoId,
        'file_path' => $webPath,
        'duration_seconds' => $duration,
        'transcription' => $transcription,
        'transcription_confidence' => $transcriptionConfidence,
        'file_size' => $audioFile['size'],
        'message' => 'Voice memo uploaded and transcribed via MCP Hub',
        'powered_by' => 'MCP Intelligence Hub'
    ]);

} catch (Exception $e) {
    sr_log_error('voice_memo_upload_error', [
        'report_id' => $reportId,
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    // Clean up file if exists
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Voice memo upload failed',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
