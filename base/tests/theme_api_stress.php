<?php
/**
 * Theme API Stress Harness
 * Simulates rapid theme generate + list calls.
 * Usage: php theme_api_stress.php cycles=50 concurrency=5
 */
if (empty($_SERVER['DOCUMENT_ROOT'])) { $_SERVER['DOCUMENT_ROOT']='/home/master/applications/jcepnzzkmj/public_html'; }
$opts=[]; foreach($argv as $a){ if(strpos($a,'=')!==false){ [$k,$v]=explode('=',$a,2); $opts[$k]=$v; }}
$cycles=(int)($opts['cycles']??30); $concurrency=(int)($opts['concurrency']??3);
$base='/modules/base/templates/vape-ultra/api/theme-api.php';

function apiCall($action,$post=null){ $url=$_SERVER['DOCUMENT_ROOT'].$base.'?action='.$action; if($post){ $content=http_build_query($post); $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>$content]]); } else { $ctx=null; } $raw=@file_get_contents($url,false,$ctx); return json_decode($raw,true); }

$start=microtime(true); $errors=0; $latencies=[];
for($i=0;$i<$cycles;$i++){
    $batch=[]; for($c=0;$c<$concurrency;$c++){ $batch[]=['hue'=>rand(0,360),'scheme'=>'triadic']; }
    foreach($batch as $gen){ $t0=microtime(true); $r=apiCall('generate',$gen); $latencies[] = round((microtime(true)-$t0)*1000,2); if(!$r || !$r['success']) $errors++; }
    $t0=microtime(true); $list=apiCall('list'); $latencies[] = round((microtime(true)-$t0)*1000,2); if(!$list || !$list['success']) $errors++;
}
$totalTime=round((microtime(true)-$start),2);
$avgLatency = count($latencies)? round(array_sum($latencies)/count($latencies),2) : 0;
$maxLatency = count($latencies)? max($latencies):0;
$result=['cycles'=>$cycles,'concurrency'=>$concurrency,'errors'=>$errors,'avg_latency_ms'=>$avgLatency,'max_latency_ms'=>$maxLatency,'total_time_s'=>$totalTime,'calls'=>count($latencies)];
header('Content-Type: application/json'); echo json_encode($result, JSON_PRETTY_PRINT);
