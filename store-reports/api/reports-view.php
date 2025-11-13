<?php
/**
 * Report View API
 * GET /api/reports-view
 *
 * Retrieves complete report details including all related data
 *
 * @endpoint GET /api/reports-view?report_id=123
 * @response JSON with full report data, checklist items, images, memos, AI analysis
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get query parameters
$reportId = $_GET['report_id'] ?? null;

if (!$reportId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameter: report_id']);
    exit;
}

// Get authenticated user
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    $pdo = sr_pdo();

    // Get report basic info
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            o.outlet_name,
            o.outlet_code,
            o.outlet_address,
            creator.first_name as creator_first_name,
            creator.last_name as creator_last_name,
            creator.email as creator_email,
            reviewer.first_name as reviewer_first_name,
            reviewer.last_name as reviewer_last_name,
            reviewer.email as reviewer_email
        FROM store_reports r
        LEFT JOIN vend_outlets o ON r.outlet_id = o.outlet_id
        LEFT JOIN users creator ON r.created_by = creator.user_id
        LEFT JOIN users reviewer ON r.reviewed_by = reviewer.user_id
        WHERE r.report_id = ?
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

    // Get checklist items with responses
    $stmt = $pdo->prepare("
        SELECT
            c.checklist_id,
            c.question_text,
            c.response_type,
            c.weight,
            c.category,
            c.requires_photo,
            c.sort_order,
            ri.response_value,
            ri.response_text,
            ri.staff_notes,
            ri.is_flagged,
            ri.updated_at as response_updated_at
        FROM store_report_checklist c
        LEFT JOIN store_report_items ri ON c.checklist_id = ri.checklist_id AND ri.report_id = ?
        WHERE c.is_active = 1
        ORDER BY c.sort_order ASC
    ");
    $stmt->execute([$reportId]);
    $checklistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get images with AI analysis
    $stmt = $pdo->prepare("
        SELECT
            i.image_id,
            i.file_path,
            i.thumbnail_path,
            i.caption,
            i.file_size,
            i.width,
            i.height,
            i.ai_analysis_status,
            i.ai_analysis_summary,
            i.ai_last_analyzed_at,
            i.created_at,
            GROUP_CONCAT(
                CONCAT_WS('|',
                    air.request_id,
                    air.request_type,
                    air.confidence_score,
                    air.created_at
                ) SEPARATOR ':::'
            ) as ai_analyses
        FROM store_report_images i
        LEFT JOIN store_report_ai_requests air ON i.image_id = air.image_id
        WHERE i.report_id = ?
        GROUP BY i.image_id
        ORDER BY i.created_at ASC
    ");
    $stmt->execute([$reportId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse AI analyses for each image
    foreach ($images as &$image) {
        $analyses = [];
        if ($image['ai_analyses']) {
            foreach (explode(':::', $image['ai_analyses']) as $analysis) {
                list($requestId, $type, $confidence, $createdAt) = explode('|', $analysis);
                $analyses[] = [
                    'request_id' => (int)$requestId,
                    'type' => $type,
                    'confidence' => (float)$confidence,
                    'created_at' => $createdAt
                ];
            }
        }
        $image['ai_analyses'] = $analyses;
        $image['image_id'] = (int)$image['image_id'];
        $image['file_size'] = (int)$image['file_size'];
    }

    // Get voice memos
    $stmt = $pdo->prepare("
        SELECT
            vm.memo_id,
            vm.checklist_id,
            vm.file_path,
            vm.duration_seconds,
            vm.transcription,
            vm.transcription_confidence,
            vm.caption,
            vm.created_at,
            u.first_name,
            u.last_name
        FROM store_report_voice_memos vm
        LEFT JOIN users u ON vm.recorded_by = u.user_id
        WHERE vm.report_id = ?
        ORDER BY vm.created_at ASC
    ");
    $stmt->execute([$reportId]);
    $voiceMemos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($voiceMemos as &$memo) {
        $memo['memo_id'] = (int)$memo['memo_id'];
        $memo['duration_seconds'] = (int)$memo['duration_seconds'];
        $memo['recorder'] = trim($memo['first_name'] . ' ' . $memo['last_name']);
        unset($memo['first_name'], $memo['last_name']);
    }

    // Get autosave checkpoints count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as checkpoint_count,
               MAX(created_at) as last_checkpoint
        FROM store_report_autosave_checkpoints
        WHERE report_id = ?
    ");
    $stmt->execute([$reportId]);
    $autosaveInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get history events (last 20)
    $stmt = $pdo->prepare("
        SELECT
            h.history_id,
            h.action,
            h.details,
            h.created_at,
            u.first_name,
            u.last_name
        FROM store_report_history h
        LEFT JOIN users u ON h.user_id = u.user_id
        WHERE h.report_id = ?
        ORDER BY h.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$reportId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($history as &$event) {
        $event['history_id'] = (int)$event['history_id'];
        $event['user_name'] = trim($event['first_name'] . ' ' . $event['last_name']);
        $event['details'] = json_decode($event['details'], true);
        unset($event['first_name'], $event['last_name']);
    }

    // Format report data
    $report['report_id'] = (int)$report['report_id'];
    $report['completion_percentage'] = (int)$report['completion_percentage'];
    $report['grade_score'] = (float)$report['grade_score'];
    $report['created_by'] = (int)$report['created_by'];

    $report['outlet'] = [
        'outlet_id' => $report['outlet_id'],
        'name' => $report['outlet_name'],
        'code' => $report['outlet_code'],
        'address' => $report['outlet_address']
    ];

    $report['creator'] = [
        'name' => trim($report['creator_first_name'] . ' ' . $report['creator_last_name']),
        'email' => $report['creator_email']
    ];

    if ($report['reviewed_by']) {
        $report['reviewer'] = [
            'name' => trim($report['reviewer_first_name'] . ' ' . $report['reviewer_last_name']),
            'email' => $report['reviewer_email']
        ];
    }

    // Remove redundant fields
    unset($report['outlet_name'], $report['outlet_code'], $report['outlet_address'],
          $report['creator_first_name'], $report['creator_last_name'], $report['creator_email'],
          $report['reviewer_first_name'], $report['reviewer_last_name'], $report['reviewer_email']);

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'report' => $report,
        'checklist_items' => $checklistItems,
        'images' => $images,
        'voice_memos' => $voiceMemos,
        'autosave' => [
            'checkpoint_count' => (int)$autosaveInfo['checkpoint_count'],
            'last_checkpoint' => $autosaveInfo['last_checkpoint']
        ],
        'history' => $history,
        'message' => 'Report details retrieved'
    ]);

} catch (Exception $e) {
    sr_log_error('reports_view_error', [
        'report_id' => $reportId,
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve report details',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
