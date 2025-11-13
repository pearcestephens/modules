<?php
require_once __DIR__ . '/../bootstrap.php';
sr_require_auth(true); // admin only

if (!sr_db_available()) { sr_json(['success'=>false,'error'=>'Database unavailable'],503); }
$pdo = sr_pdo();
$checklist = new StoreReportChecklist($pdo);
$defaults = [
    ['category'=>'Cleanliness','name'=>'floors_clean','title'=>'Floors are clean and free of debris','max_points'=>4,'weight'=>1.0,'is_critical'=>0],
    ['category'=>'Cleanliness','name'=>'glass_cabinets_clean','title'=>'Glass display cabinets are streak-free','max_points'=>4,'weight'=>1.2,'is_critical'=>0],
    ['category'=>'Organization','name'=>'products_arranged','title'=>'Products are neatly arranged by brand/type','max_points'=>4,'weight'=>1.0,'is_critical'=>0],
    ['category'=>'Safety','name'=>'cords_secured','title'=>'All cords are secured and not a trip hazard','max_points'=>4,'weight'=>1.0,'is_critical'=>1],
    ['category'=>'Compliance','name'=>'age_sign_visible','title'=>'Age restriction signage clearly visible','max_points'=>4,'weight'=>1.0,'is_critical'=>1],
    ['category'=>'Visual','name'=>'lighting_adequate','title'=>'Lighting is adequate and professional','max_points'=>4,'weight'=>0.8,'is_critical'=>0]
];

$inserted = [];
foreach ($defaults as $row) {
    // Skip if exists
    $exists = sr_query_one("SELECT id FROM store_report_checklist WHERE name=? LIMIT 1", [$row['name']]);
    if ($exists) continue;
    $inserted[] = $checklist->create($row);
}

sr_json(['success'=>true,'inserted_ids'=>$inserted,'count'=>count($inserted)]);
?>
