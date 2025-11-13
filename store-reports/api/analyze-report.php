<?php
require_once __DIR__ . '/../bootstrap.php';
sr_require_auth();
sr_rate_limit('analyze_report', 10);
if (!verify_csrf()) { sr_json(['success'=>false,'error'=>'CSRF failed'],403); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sr_json(['success'=>false,'error'=>'Method not allowed'],405); }
$reportId = sr_int($_POST['report_id'] ?? 0);
if ($reportId <= 0) sr_json(['success'=>false,'error'=>'Invalid report_id'],400);
try {
    $service = new StoreReportAIVisionService();
    $result = $service->analyzeReportImages($reportId);
    sr_json(['success'=>true]+$result);
} catch (Exception $e) {
    sr_json(['success'=>false,'error'=>$e->getMessage()],500);
}
?>
