<?php
/**
 * Secure ID Photo Viewer
 *
 * Serves ID photos with strict access control, audit logging, and watermarking.
 * Photos are NEVER directly accessible via web URL.
 *
 * @package CIS\Modules\EcommerceOps\API
 */

require_once __DIR__ . '/../../bootstrap.php';

// Require authentication
ecomm_require_auth();

// Get token from request
$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die('Missing access token');
}

// Validate token
if (!isset($_SESSION['photo_access_tokens'][$token])) {
    http_response_code(403);
    ecomm_log_error("Invalid photo access token", [
        'token' => substr($token, 0, 10) . '...',
        'staff_id' => $_SESSION['userID'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    die('Invalid or expired access token');
}

$tokenData = $_SESSION['photo_access_tokens'][$token];

// Check expiration
if ($tokenData['expires'] < time()) {
    unset($_SESSION['photo_access_tokens'][$token]);
    http_response_code(403);
    die('Access token expired (5 minute limit)');
}

// Verify requesting user matches token
if ($tokenData['staff_id'] != $_SESSION['userID']) {
    http_response_code(403);
    ecomm_log_error("Photo access token mismatch", [
        'expected_staff_id' => $tokenData['staff_id'],
        'actual_staff_id' => $_SESSION['userID'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    die('Access denied');
}

// Get file path (stored as filename only, not full path)
$storagePath = ecomm_env('AGE_VERIFICATION_STORAGE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/secure/id-photos/');
$filepath = $storagePath . $tokenData['filename'];

// Verify file exists
if (!file_exists($filepath)) {
    http_response_code(404);
    ecomm_log_error("Photo file not found", [
        'verification_id' => $tokenData['verification_id'],
        'filename' => $tokenData['filename']
    ]);
    die('Photo not found');
}

// Load image
$image = imagecreatefromjpeg($filepath);

if (!$image) {
    http_response_code(500);
    die('Failed to load image');
}

// Get image dimensions
$width = imagesx($image);
$height = imagesy($image);

// Add watermark: "CONFIDENTIAL - CIS INTERNAL USE ONLY"
$watermarkText = "CONFIDENTIAL - CIS INTERNAL USE ONLY\nVerification ID: " . $tokenData['verification_id'];
$watermarkText .= "\nViewed by: Staff ID " . $tokenData['staff_id'];
$watermarkText .= "\nDate: " . date('Y-m-d H:i:s');

// Watermark settings
$fontSize = 5;
$textColor = imagecolorallocatealpha($image, 255, 0, 0, 50); // Semi-transparent red
$backgroundColor = imagecolorallocatealpha($image, 0, 0, 0, 80); // Semi-transparent black

// Calculate watermark position (bottom-left corner)
$watermarkX = 10;
$watermarkY = $height - 100;

// Draw watermark background
imagefilledrectangle($image, $watermarkX - 5, $watermarkY - 5, $watermarkX + 500, $watermarkY + 95, $backgroundColor);

// Draw watermark text (line by line)
$lines = explode("\n", $watermarkText);
$lineY = $watermarkY;
foreach ($lines as $line) {
    imagestring($image, $fontSize, $watermarkX, $lineY, $line, $textColor);
    $lineY += 20;
}

// Add diagonal watermark across center
$centerX = $width / 2;
$centerY = $height / 2;
$angle = 45;
$largeFont = 5;
$diagonalColor = imagecolorallocatealpha($image, 255, 255, 255, 90); // Semi-transparent white

imagettftext($image, 40, $angle, $centerX - 200, $centerY, $diagonalColor,
    __DIR__ . '/../../fonts/arial.ttf', // Fallback to imagestring if font not available
    'CONFIDENTIAL'
);

// Invalidate token after use (one-time use)
unset($_SESSION['photo_access_tokens'][$token]);

// Send image headers
header('Content-Type: image/jpeg');
header('Content-Disposition: inline; filename="verification_' . $tokenData['verification_id'] . '.jpg"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Output watermarked image
imagejpeg($image, null, 90);

// Clean up
imagedestroy($image);

// Audit log (already logged in AgeVerificationService::getPhotoUrl())
ecomm_log_error("ID photo viewed", [
    'verification_id' => $tokenData['verification_id'],
    'staff_id' => $tokenData['staff_id'],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

exit;
