#!/usr/bin/env php
<?php
declare(strict_types=1);

// DB Introspection helper: lists tables, row counts, key columns, and sample rows
// Usage: php modules/tools/db-introspect.php [--focus=table1,table2] [--limit=3]

require_once __DIR__ . '/../base/bootstrap.php';

function println($s=''): void {
    if (!is_string($s)) {
        $s = print_r($s, true);
    }
    fwrite(STDOUT, $s."\n");
}
function json_out($data): void {
    $enc = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($enc === false) {
        $enc = json_encode(['error' => 'json_encode_failed', 'json_last_error' => json_last_error_msg()]);
    }
    println($enc);
}

$focus = [];
$limit = 3;
foreach ($argv as $arg) {
    if (preg_match('/^--focus=(.+)$/', $arg, $m)) { $focus = array_filter(array_map('trim', explode(',', $m[1]))); }
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) { $limit = max(1, (int)$m[1]); }
}

$pdo = \CIS\Base\Database::pdo();

// Resolve current DB name
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();

// List tables with row counts
$tables = $pdo->prepare("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME");
$tables->execute([$dbName]);
$list = $tables->fetchAll(PDO::FETCH_ASSOC);

$result = [
    'database' => $dbName,
    'tables' => [],
];

$interestingCols = ['phone','telephone','mobile','contact','email','opening','closing','open_time','close_time','hours','trading','timezone','on_call','roster','shift','packed_by','packed_at','vend','outlet','address','city','postcode'];

foreach ($list as $t) {
    $name = $t['TABLE_NAME'];
    if (!empty($focus) && !in_array($name, $focus, true)) { continue; }

    // Columns
    $colsStmt = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
    $colsStmt->execute([$dbName, $name]);
    $cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Sample rows
    $sample = [];
    try {
        $sample = $pdo->query("SELECT * FROM `".$name."` LIMIT ".$limit)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    // Detect interesting columns
    $has = [];
    foreach ($cols as $c) {
        $col = strtolower($c['COLUMN_NAME']);
        foreach ($interestingCols as $kw) { if (str_contains($col, $kw)) { $has[] = $c['COLUMN_NAME']; } }
    }

    $result['tables'][] = [
        'name' => $name,
        'approx_rows' => (int)$t['TABLE_ROWS'],
        'columns' => $cols,
        'interesting_columns' => array_values(array_unique($has)),
        'sample' => $sample,
    ];
}

json_out($result);
exit(0);
