#!/usr/bin/env php
<?php
declare(strict_types=1);

// Dry-run: list DLQ candidates by optional filters
$options  = getopt('', ['endpoint::', 'code::']);
$endpoint = $options['endpoint'] ?? null;
$code     = $options['code'] ?? null;

require_once __DIR__ . '/../../base/bootstrap.php';
$mysqli = db(); // existing bootstrap should expose db()

$filters = [];
if ($endpoint) $filters[] = "endpoint LIKE '".addslashes($endpoint)."'";
if ($code)     $filters[] = "error_code = '".addslashes($code)."'";
$where = $filters ? ('WHERE '.implode(' AND ', $filters)) : '';

$sql = "SELECT id, request_id, endpoint, error_code, created_at
        FROM consignments_dlq $where
        ORDER BY created_at DESC
        LIMIT 100";

$res = $mysqli->query($sql);
if (!$res) {
    fwrite(STDERR, "Query failed: ".$mysqli->error.PHP_EOL);
    exit(2);
}
if ($res->num_rows === 0) { echo "(no matches)\n"; exit(0); }

while ($row = $res->fetch_assoc()) {
    printf("#%d %s %s [%s]\n", $row['id'], $row['request_id'], $row['endpoint'], $row['error_code'] ?? '-');
}
