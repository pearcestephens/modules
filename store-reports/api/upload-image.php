<?php
require_once __DIR__ . '/../bootstrap.php';
sr_require_auth();
sr_rate_limit('upload', 40);
if (!verify_csrf()) { sr_json(['success'=>false,'error'=>'CSRF failed'],403); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sr_json(['success'=>false,'error'=>'Method not allowed'],405); }

$reportId = sr_int($_POST['report_id'] ?? 0);
if ($reportId <= 0) sr_json(['success'=>false,'error'=>'Invalid report_id'],400);

if (empty($_FILES['file'])) sr_json(['success'=>false,'error'=>'No file uploaded'],400);
$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) sr_json(['success'=>false,'error'=>'Upload error'],400);

$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
$mime = mime_content_type($file['tmp_name']);
if (!isset($allowed[$mime])) sr_json(['success'=>false,'error'=>'Unsupported file type'],415);
if ($file['size'] > 5*1024*1024) sr_json(['success'=>false,'error'=>'File too large'],413);

$ext = $allowed[$mime];
$name = 'img_' . $reportId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destDir = SR_UPLOAD_DIR . '/' . $reportId;
if (!is_dir($destDir)) { @mkdir($destDir,0775,true); }
$destPath = $destDir . '/' . $name;
if (!move_uploaded_file($file['tmp_name'], $destPath)) sr_json(['success'=>false,'error'=>'Failed to move file'],500);

if (!sr_db_available()) sr_json(['success'=>false,'error'=>'Database unavailable'],503);
$pdo = sr_pdo();
$imageModel = new StoreReportImage($pdo);
$imageId = $imageModel->create([
    'report_id' => $reportId,
    'filename' => $name,
    'file_path' => $destPath,
    'uploaded_by_user' => function_exists('current_user_id') ? current_user_id() : 0,
    'caption' => sr_str($_POST['caption'] ?? ''),
    'location_in_store' => sr_str($_POST['location_in_store'] ?? '')
]);

sr_json(['success'=>true,'image_id'=>$imageId]);
?>
