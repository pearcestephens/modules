<?php
/**
 * Store Reports - Photo Upload API
 * Handles photo uploads from mobile devices
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../private_html/check-login.php';
require_once __DIR__ . '/../config.php';

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = (int)$_SESSION['user_id'];
    $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : null;
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : null;
    $outletId = $_POST['outlet_id'] ?? null;

    // Validate file upload
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['photo'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and WebP allowed.');
    }

    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 10MB.');
    }

    // Create upload directory if needed
    $uploadDir = __DIR__ . '/../../../uploads/store-reports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = match($mimeType) {
        'image/jpeg', 'image/jpg' => '.jpg',
        'image/png' => '.png',
        'image/webp' => '.webp',
        default => '.jpg'
    };

    $filename = 'photo_' . date('Ymd_His') . '_' . uniqid() . $extension;
    $filepath = $uploadDir . $filename;
    $webPath = '/uploads/store-reports/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Get image dimensions
    $imageInfo = getimagesize($filepath);
    $width = $imageInfo[0] ?? null;
    $height = $imageInfo[1] ?? null;

    // Database connection
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create report if it doesn't exist
    if (!$reportId && $outletId) {
        $stmt = $db->prepare("INSERT INTO store_reports (
            outlet_id,
            performed_by_user,
            report_date,
            status,
            created_at
        ) VALUES (?, ?, NOW(), 'draft', NOW())");
        $stmt->execute([$outletId, $userId]);
        $reportId = (int)$db->lastInsertId();
    }

    // Insert image record
    $stmt = $db->prepare("INSERT INTO store_report_images (
        report_id,
        checklist_item_id,
        filename,
        file_path,
        file_size,
        mime_type,
        width,
        height,
        uploaded_by_user,
        uploaded_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    $stmt->execute([
        $reportId,
        $itemId,
        $filename,
        $webPath,
        $file['size'],
        $mimeType,
        $width,
        $height,
        $userId
    ]);

    $photoId = (int)$db->lastInsertId();

    // Update report image count
    $stmt = $db->prepare("UPDATE store_reports
        SET total_images = total_images + 1,
            last_autosave_at = NOW()
        WHERE id = ?");
    $stmt->execute([$reportId]);

    // Success response
    echo json_encode([
        'success' => true,
        'photo_id' => $photoId,
        'report_id' => $reportId,
        'url' => $webPath,
        'filename' => $filename,
        'size' => $file['size'],
        'width' => $width,
        'height' => $height,
        'message' => 'Photo uploaded successfully'
    ]);

} catch (PDOException $e) {
    error_log("Store Reports - Photo upload DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Store Reports - Photo upload error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
