<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../services/ScoreCalculator.php';

$cases = [
    'empty' => [[],[], ['ai_score'=>null,'checklist_score'=>null,'overall'=>null]],
    'only_images' => [[['overall'=>80],['overall'=>90]],[], ['ai_score'=>85.0,'checklist_score'=>null,'overall'=>85.0]],
    'only_items' => [[],[['points_earned'=>30,'max_points'=>40]], ['ai_score'=>null,'checklist_score'=>75.0,'overall'=>75.0]],
    'both' => [[['overall'=>70],['overall'=>90]],[['points_earned'=>16,'max_points'=>20]], ['ai_score'=>80.0,'checklist_score'=>80.0,'overall'=>80.0]],
    'weights_precision' => [[['overall'=>83.3333]],[['points_earned'=>5,'max_points'=>10]], ['ai_score'=>83.33,'checklist_score'=>50.0,'overall'=>round((83.33*0.7)+(50*0.3),2)]],
];

$failures = 0;
foreach ($cases as $name=>$c) {
    $out = ScoreCalculator::compute($c[0],$c[1]);
    foreach ($c[2] as $k=>$expected) {
        if ($out[$k] !== $expected) {
            echo "[FAIL] $name $k expected=".var_export($expected,true)." got=".var_export($out[$k],true)."\n";
            $failures++;
        }
    }
}
if ($failures === 0) {
    echo "All ScoreCalculator tests passed.\n";
    exit(0);
}
echo "Failures: $failures\n";
exit(1);
?>
