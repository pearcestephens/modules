<?php
require_once __DIR__ . '/../bootstrap.php';
sr_require_auth();
sr_rate_limit('get_trends', 60);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') sr_json(['success'=>false,'error'=>'Method not allowed'],405);
if (!sr_db_available()) {
	sr_json(['success'=>false,'error'=>'Database unavailable']);
}
$recent = sr_query("SELECT id,outlet_id,overall_score,grade,report_date FROM store_reports WHERE deleted_at IS NULL ORDER BY report_date DESC LIMIT 10");
$bench = sr_query("SELECT outlet_id,outlet_name,latest_score,latest_grade,avg_score,best_score,worst_score,current_rank FROM vw_store_report_benchmarks ORDER BY current_rank ASC LIMIT 50");
sr_json(['success'=>true,'recent'=>$recent,'benchmarks'=>$bench]);
?>
