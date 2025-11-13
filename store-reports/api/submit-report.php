<?php
require_once __DIR__ . '/../bootstrap.php';
sr_require_auth();
sr_rate_limit('submit_report', 10);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sr_json(['success'=>false,'error'=>'Method not allowed'],405);
if (!verify_csrf()) { sr_json(['success'=>false,'error'=>'CSRF failed'],403); }

$reportId = sr_int($_POST['report_id'] ?? 0);
if ($reportId <= 0) sr_json(['success'=>false,'error'=>'Invalid report_id'],400);

if (!sr_db_available()) sr_json(['success'=>false,'error'=>'Database unavailable'],503);
$pdo = sr_pdo();
$reportModel = new StoreReport($pdo);
$imageRows = sr_query("SELECT ai_overall_score as overall FROM store_report_images WHERE report_id=? AND ai_analyzed=1",[$reportId]);
$itemRows = sr_query("SELECT points_earned, max_points FROM store_report_items WHERE report_id=?",[$reportId]);
$scores = ScoreCalculator::compute($imageRows,$itemRows);
try {
    $reportModel->finalize($reportId, ['overall'=>$scores['overall'],'ai_score'=>$scores['ai_score']], null);
    sr_json(['success'=>true,'report_id'=>$reportId,'scores'=>$scores]);
} catch (Exception $e) {
    sr_json(['success'=>false,'error'=>$e->getMessage()],500);
}
?>
