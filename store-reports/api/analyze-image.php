<?php
require_once __DIR__ . '/../bootstrap.php';
sr_require_auth();
sr_rate_limit('analyze', 30);
if (!verify_csrf()) { sr_json(['success'=>false,'error'=>'CSRF failed'],403); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sr_json(['success'=>false,'error'=>'Method not allowed'],405); }
$imageId = sr_int($_POST['image_id'] ?? 0);
if ($imageId <= 0) sr_json(['success'=>false,'error'=>'Invalid image_id'],400);

try {
    $service = new StoreReportAIVisionService();
    $result = $service->analyzeImage($imageId);
    sr_json($result, $result['success'] ? 200 : 500);
} catch (Exception $e) {
    sr_json(['success'=>false,'error'=>$e->getMessage()],500);
}
?>
