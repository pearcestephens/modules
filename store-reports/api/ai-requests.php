<?php
require_once __DIR__ . '/../bootstrap.php';
sr_require_auth();
sr_rate_limit('ai_requests', 60);

if (!sr_db_available()) sr_json(['success'=>false,'error'=>'Database unavailable'],503);
$pdo = sr_pdo();
$reqModel = new StoreReportAIRequest($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $reportId = sr_int($_GET['report_id'] ?? 0);
    if ($reportId <= 0) sr_json(['success'=>false,'error'=>'Invalid report_id'],400);
    $rows = sr_query("SELECT * FROM store_report_ai_requests WHERE report_id=? ORDER BY id DESC",[$reportId]);
    sr_json(['success'=>true,'requests'=>$rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { sr_json(['success'=>false,'error'=>'CSRF failed'],403); }
    $reportId = sr_int($_POST['report_id'] ?? 0);
    $requestId = sr_int($_POST['request_id'] ?? 0);
    $action = sr_str($_POST['action'] ?? '');
    if ($reportId <= 0 || $requestId <= 0) sr_json(['success'=>false,'error'=>'Invalid IDs'],400);
    $allowed = ['fulfill','skip','cannot_fulfill'];
    if (!in_array($action,$allowed,true)) sr_json(['success'=>false,'error'=>'Invalid action'],400);
    $statusMap = ['fulfill'=>'fulfilled','skip'=>'skipped','cannot_fulfill'=>'cannot_fulfill'];
    $note = substr($_POST['note'] ?? '',0,1000);
    $status = $statusMap[$action];
    sr_exec("UPDATE store_report_ai_requests SET status=?, staff_response_note=?, fulfilled_at=NOW() WHERE id=? AND report_id=? LIMIT 1",[$status,$note,$requestId,$reportId]);
    sr_json(['success'=>true,'request_id'=>$requestId,'status'=>$status]);
}

sr_json(['success'=>false,'error'=>'Method not allowed'],405);
?>
