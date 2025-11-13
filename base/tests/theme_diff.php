<?php
/**
 * Theme Diff Utility
 * Compares two theme IDs and outputs differences in colors, typography, layout + contrast deltas.
 * Usage: php theme_diff.php A=1 B=2
 */

if (empty($_SERVER['DOCUMENT_ROOT'])) { $_SERVER['DOCUMENT_ROOT'] = '/home/master/applications/jcepnzzkmj/public_html'; }
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';
use Services\Database;

$args = [];
foreach ($argv as $arg) { if (strpos($arg,'=')!==false) { [$k,$v]=explode('=',$arg,2); $args[$k]=$v; } }
$A = $args['A'] ?? null; $B = $args['B'] ?? null;
if (!$A || !$B) { fwrite(STDERR,"Provide A and B theme IDs (A=1 B=2)\n"); exit(1); }

$db = Database::getInstance(); $pdo = $db->getConnection();
function fetchTheme($id,$pdo){ $stmt=$pdo->prepare('SELECT id,name,theme_data FROM user_themes WHERE id=?'); $stmt->execute([$id]); $row=$stmt->fetch(); if(!$row) return null; $row['theme_data']=json_decode($row['theme_data'],true); return $row; }
$tA = fetchTheme($A,$pdo); $tB = fetchTheme($B,$pdo);
if(!$tA||!$tB){ fwrite(STDERR,"One or both themes not found\n"); exit(1); }

function contrast($hex1,$hex2){ $hex1=ltrim($hex1,'#'); $hex2=ltrim($hex2,'#'); $toRGB=function($h){ if(strlen($h)==3){$h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2];} return [hexdec(substr($h,0,2))/255,hexdec(substr($h,2,2))/255,hexdec(substr($h,4,2))/255];}; $lum=function($h) use($toRGB){ [$r,$g,$b]=$toRGB($h); $f=function($c){return $c<=0.03928?$c/12.92:pow(($c+0.055)/1.055,2.4);}; $r=$f($r);$g=$f($g);$b=$f($b); return 0.2126*$r+0.7152*$g+0.0722*$b; }; $l1=$lum($hex1); $l2=$lum($hex2); $L=max($l1,$l2); $S=min($l1,$l2); return round(($L+0.05)/($S+0.05),2);}

$diff = ['meta'=>['A'=>$tA['id'],'B'=>$tB['id'],'nameA'=>$tA['name'],'nameB'=>$tB['name']],'colors'=>[],'typography'=>[],'layout'=>[],'contrast_deltas'=>[]];

$colorsA = $tA['theme_data']['colors'] ?? []; $colorsB = $tB['theme_data']['colors'] ?? [];
foreach(array_unique(array_merge(array_keys($colorsA),array_keys($colorsB))) as $key){ $va=$colorsA[$key]??null; $vb=$colorsB[$key]??null; if($va!==$vb){ $diff['colors'][$key]=['A'=>$va,'B'=>$vb]; } }

$typoA = $tA['theme_data']['typography'] ?? []; $typoB = $tB['theme_data']['typography'] ?? [];
foreach(array_unique(array_merge(array_keys($typoA),array_keys($typoB))) as $key){ $va=$typoA[$key]??null; $vb=$typoB[$key]??null; if($va!==$vb){ $diff['typography'][$key]=['A'=>$va,'B'=>$vb]; } }

$layoutA = $tA['theme_data']['layout'] ?? []; $layoutB = $tB['theme_data']['layout'] ?? [];
foreach(array_unique(array_merge(array_keys($layoutA),array_keys($layoutB))) as $key){ $va=$layoutA[$key]??null; $vb=$layoutB[$key]??null; if($va!==$vb){ $diff['layout'][$key]=['A'=>$va,'B'=>$vb]; } }

// Contrast deltas for key pairs
$pairs=[['text','background'],['textSecondary','background'],['primary','background'],['accent','background'],['danger','background']];
foreach($pairs as $pair){ [$fg,$bg]=$pair; if(isset($colorsA[$fg],$colorsA[$bg],$colorsB[$fg],$colorsB[$bg])){ $diff['contrast_deltas'][$fg.'On'.$bg]=['A'=>contrast($colorsA[$fg],$colorsA[$bg]),'B'=>contrast($colorsB[$fg],$colorsB[$bg])]; } }

echo json_encode($diff, JSON_PRETTY_PRINT);
