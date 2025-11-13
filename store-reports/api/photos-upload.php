<?php
/**
 * API: Upload Photo
 * POST /api/photos-upload
 *
 * Handles photo upload with validation and queues optimization
 *
 * @author Enterprise Team
 */

declare(strict_types=1);

header('Content-Type: application/json');

if (!defined('BOT_BYPASS')) {
    define('BOT_BYPASS', true);
}

require_once __DIR__ . '/../bootstrap.php';

sr_require_auth();

if (!verify_csrf()) {
    sr_json(['success' => false, 'error' => 'CSRF validation failed'], 403);
}

sr_rate_limit('photo_upload', 20);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sr_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

try {
    // Validate report_id
    $reportId = (int)($_POST['report_id'] ?? 0);

    if (!$reportId) {
        sr_json(['success' => false, 'error' => 'Missing report_id'], 400);
    }

    $userId = current_user_id() ?? $_SESSION['user_id'] ?? $_SESSION['userID'] ?? null;

    $pdo = sr_pdo();

    if (!$pdo) {
        sr_json(['success' => false, 'error' => 'Database unavailable'], 503);
    }

    // Verify report exists
    $stmt = $pdo->prepare("SELECT id, performed_by_user FROM store_reports WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    if (!$report) {
        sr_json(['success' => false, 'error' => 'Report not found'], 404);
    }

    // Check file upload
    if (empty($_FILES['photo'])) {
        sr_json(['success' => false, 'error' => 'No file uploaded'], 400);
    }

    $file = $_FILES['photo'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sr_json(['success' => false, 'error' => 'Upload error: ' . $file['error']], 400);
    }

    // Check mime type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mimeType = mime_content_type($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        sr_json(['success' => false, 'error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP'], 400);
    }

    // Check file size (max 10MB)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        sr_json(['success' => false, 'error' => 'File too large. Max 10MB'], 400);
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $uniqueName = $reportId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    // Ensure upload directory exists
    $uploadDir = SR_UPLOAD_DIR . '/' . date('Y-m');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $targetPath = $uploadDir . '/' . $uniqueName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        sr_json(['success' => false, 'error' => 'Failed to save file'], 500);
    }

    // Get image dimensions
    $imageInfo = getimagesize($targetPath);
    $width = $imageInfo[0] ?? null;
    $height = $imageInfo[1] ?? null;

    // Store in database
    $insertStmt = $pdo->prepare("
        INSERT INTO store_report_images
        (report_id, checklist_item_id, original_filename, original_file_path,
         original_file_size, original_mime_type, original_width, original_height,
         uploaded_by_user, upload_timestamp, caption, location_in_store,
         status, uploaded_offline, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'uploaded', ?, NOW())
    ");

    $checklistItemId = !empty($_POST['checklist_item_id']) ? (int)$_POST['checklist_item_id'] : null;
    $caption = $_POST['caption'] ?? null;
    $location = $_POST['location'] ?? null;
    $offline = !empty($_POST['offline']) ? 1 : 0;

    $insertStmt->execute([
        $reportId,
        $checklistItemId,
        $file['name'],
        str_replace(SR_UPLOAD_DIR . '/', '', $targetPath),
        $file['size'],
        $mimeType,
        $width,
        $height,
        $userId,
        $caption,
        $location,
        $offline
    ]);

    $imageId = (int)$pdo->lastInsertId();

    // Update report image count
    $pdo->prepare("UPDATE store_reports SET total_images = total_images + 1 WHERE id = ?")
        ->execute([$reportId]);

    // Queue for optimization
    $pdo->prepare("
        INSERT INTO store_report_photo_optimization_queue
        (image_id, report_id, status, priority, created_at)
        VALUES (?, ?, 'pending', 5, NOW())
    ")->execute([$imageId, $reportId]);

    // Log upload
    $pdo->prepare("
        INSERT INTO store_report_history
        (report_id, user_id, action_type, entity_type, entity_id, description, created_at)
        VALUES (?, ?, 'image_added', 'image', ?, 'Photo uploaded', NOW())
    ")->execute([$reportId, $userId, $imageId]);

    sr_log_info("Photo uploaded: ID=$imageId, report=$reportId, size={$file['size']}");

    sr_json([
        'success' => true,
        'image_id' => $imageId,
        'filename' => $uniqueName,
        'file_size' => $file['size'],
        'mime_type' => $mimeType,
        'width' => $width,
        'height' => $height,
        'queued_for_optimization' => true,
        'message' => 'Photo uploaded successfully'
    ], 201);

} catch (PDOException $e) {
    sr_log_error("Database error uploading photo: " . $e->getMessage());
    sr_json(['success' => false, 'error' => 'Database error', 'details' => $e->getMessage()], 500);
} catch (Exception $e) {
    sr_log_error("Error uploading photo: " . $e->getMessage());
    sr_json(['success' => false, 'error' => $e->getMessage()], 500);
}
