<?php
/**
 * Photo Upload Session API
 *
 * Generates temporary upload sessions with QR codes for mobile photo upload
 * No authentication required during 15-minute window
 */

require_once '../../../config/database.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? null;

switch ($action) {
    case 'create_session':
        createUploadSession();
        break;

    case 'validate_session':
        validateSession();
        break;

    case 'upload_photo':
        uploadPhoto();
        break;

    case 'get_photos':
        getPhotos();
        break;

    case 'assign_photo':
        assignPhoto();
        break;

    case 'delete_photo':
        deletePhoto();
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Create a temporary upload session with QR code
 */
function createUploadSession() {
    global $conn;

    $transferId = $_POST['transfer_id'] ?? null;
    $transferType = $_POST['transfer_type'] ?? 'stock_transfer';
    $userId = $_POST['user_id'] ?? null;
    $outletId = $_POST['outlet_id'] ?? null;

    if (!$transferId) {
        echo json_encode(['success' => false, 'error' => 'Transfer ID required']);
        return;
    }

    // Generate unique session token
    $sessionToken = bin2hex(random_bytes(32));

    // Session expires in 15 minutes
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Store session in database
    $stmt = $conn->prepare("
        INSERT INTO PHOTO_UPLOAD_SESSIONS
        (session_token, transfer_id, transfer_type, user_id, outlet_id, expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param('sssiss',
        $sessionToken,
        $transferId,
        $transferType,
        $userId,
        $outletId,
        $expiresAt
    );

    if ($stmt->execute()) {
        $sessionId = $conn->insert_id;

        // Generate upload URL
        $uploadUrl = getBaseUrl() . "/modules/consignments/mobile-upload.php?token=" . $sessionToken;

        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'session_token' => $sessionToken,
            'upload_url' => $uploadUrl,
            'expires_at' => $expiresAt,
            'expires_in_seconds' => 900 // 15 minutes
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create session']);
    }
}

/**
 * Validate if session is still active
 */
function validateSession() {
    global $conn;

    $token = $_GET['token'] ?? null;

    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'Token required']);
        return;
    }

    $stmt = $conn->prepare("
        SELECT session_id, transfer_id, transfer_type, expires_at,
               TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_remaining
        FROM PHOTO_UPLOAD_SESSIONS
        WHERE session_token = ?
        AND expires_at > NOW()
        AND is_active = 1
    ");

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'valid' => true,
            'session' => $row
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'valid' => false,
            'error' => 'Session expired or invalid'
        ]);
    }
}

/**
 * Upload photo (no authentication required, uses session token)
 */
function uploadPhoto() {
    global $conn;

    $token = $_POST['token'] ?? null;

    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'Token required']);
        return;
    }

    // Validate session
    $stmt = $conn->prepare("
        SELECT session_id, transfer_id, transfer_type, user_id, outlet_id
        FROM PHOTO_UPLOAD_SESSIONS
        WHERE session_token = ?
        AND expires_at > NOW()
        AND is_active = 1
    ");

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$session = $result->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired session']);
        return;
    }

    // Handle file upload
    if (!isset($_FILES['photo'])) {
        echo json_encode(['success' => false, 'error' => 'No photo uploaded']);
        return;
    }

    $file = $_FILES['photo'];

    // Validate file
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG and PNG allowed']);
        return;
    }

    if ($file['size'] > 10 * 1024 * 1024) { // 10MB max
        echo json_encode(['success' => false, 'error' => 'File too large. Max 10MB']);
        return;
    }

    // Create upload directory
    $uploadDir = '../../../uploads/transfer-photos/' . $session['transfer_id'] . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('photo_') . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Store in database
        $stmt = $conn->prepare("
            INSERT INTO TRANSFER_PHOTOS
            (transfer_id, transfer_type, session_id, filename, filepath,
             file_size, uploaded_by_user_id, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param('ssissii',
            $session['transfer_id'],
            $session['transfer_type'],
            $session['session_id'],
            $filename,
            $filepath,
            $file['size'],
            $session['user_id']
        );

        if ($stmt->execute()) {
            $photoId = $conn->insert_id();

            // Update session photo count
            $conn->query("UPDATE PHOTO_UPLOAD_SESSIONS
                         SET photos_uploaded = photos_uploaded + 1
                         WHERE session_id = {$session['session_id']}");

            echo json_encode([
                'success' => true,
                'photo_id' => $photoId,
                'filename' => $filename,
                'message' => 'Photo uploaded successfully'
            ]);
        } else {
            // Delete file if database insert failed
            unlink($filepath);
            echo json_encode(['success' => false, 'error' => 'Failed to save photo info']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save photo file']);
    }
}

/**
 * Get photos for a transfer
 */
function getPhotos() {
    global $conn;

    $transferId = $_GET['transfer_id'] ?? null;

    if (!$transferId) {
        echo json_encode(['success' => false, 'error' => 'Transfer ID required']);
        return;
    }

    $stmt = $conn->prepare("
        SELECT
            p.*,
            s.session_token,
            s.created_at as session_created
        FROM TRANSFER_PHOTOS p
        LEFT JOIN PHOTO_UPLOAD_SESSIONS s ON p.session_id = s.session_id
        WHERE p.transfer_id = ?
        ORDER BY p.uploaded_at DESC
    ");

    $stmt->bind_param('s', $transferId);
    $stmt->execute();
    $result = $stmt->get_result();

    $photos = [];
    while ($row = $result->fetch_assoc()) {
        $photos[] = $row;
    }

    echo json_encode([
        'success' => true,
        'photos' => $photos,
        'count' => count($photos)
    ]);
}

/**
 * Assign photo to specific product/issue
 */
function assignPhoto() {
    global $conn;

    $photoId = $_POST['photo_id'] ?? null;
    $productId = $_POST['product_id'] ?? null;
    $issueType = $_POST['issue_type'] ?? null; // 'damaged', 'repaired', 'missing', etc.
    $notes = $_POST['notes'] ?? null;

    if (!$photoId) {
        echo json_encode(['success' => false, 'error' => 'Photo ID required']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE TRANSFER_PHOTOS
        SET product_id = ?,
            issue_type = ?,
            notes = ?,
            assigned_at = NOW()
        WHERE photo_id = ?
    ");

    $stmt->bind_param('issi', $productId, $issueType, $notes, $photoId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Photo assigned successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to assign photo']);
    }
}

/**
 * Delete photo
 */
function deletePhoto() {
    global $conn;

    $photoId = $_POST['photo_id'] ?? null;

    if (!$photoId) {
        echo json_encode(['success' => false, 'error' => 'Photo ID required']);
        return;
    }

    // Get photo info
    $stmt = $conn->prepare("SELECT filepath FROM TRANSFER_PHOTOS WHERE photo_id = ?");
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($photo = $result->fetch_assoc()) {
        // Delete file
        if (file_exists($photo['filepath'])) {
            unlink($photo['filepath']);
        }

        // Delete database record
        $stmt = $conn->prepare("DELETE FROM TRANSFER_PHOTOS WHERE photo_id = ?");
        $stmt->bind_param('i', $photoId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Photo deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete photo record']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Photo not found']);
    }
}

/**
 * Get base URL for the application
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}
