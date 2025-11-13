<?php
require_once __DIR__ . '/../bootstrap.php';
sr_require_auth();
sr_rate_limit('get_report', 120);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { sr_json(['success'=>false,'error'=>'Method not allowed'],405); }
$reportId = sr_int($_GET['report_id'] ?? 0);
if ($reportId <= 0) sr_json(['success'=>false,'error'=>'Invalid report_id'],400);

if (!sr_db_available()) { sr_json(['success'=>false,'error'=>'Database unavailable'],503); }
$pdo = sr_pdo();
$reportModel = new StoreReport($pdo);

$report = $reportModel->find($reportId);
if (!$report) sr_json(['success'=>false,'error'=>'Not found'],404);

$images = sr_query("SELECT * FROM store_report_images WHERE report_id=? AND (deleted_at IS NULL)",[$reportId]);
$items = sr_query("SELECT points_earned,max_points FROM store_report_items WHERE report_id=?",[$reportId]);
$requests = sr_query("SELECT * FROM store_report_ai_requests WHERE report_id=?",[$reportId]);
$history = sr_query("SELECT * FROM store_report_history WHERE report_id=? ORDER BY id DESC LIMIT 50",[$reportId]);

// Compute scores if completed
$scores = ScoreCalculator::compute(array_map(function($img){
    return ['overall'=>$img['ai_overall_score']];
}, $images), $items);

sr_json([
    'success' => true,
    'report' => $report,
    'images' => $images,
    'items' => $items,
    'requests' => $requests,
    'history' => $history,
    'computed_scores' => $scores
]);
?>
