#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Prune User Events Script
 * Deletes rows in cis_user_events older than retention window (90 days).
 * Safety guard: requires --confirm flag.
 */

chdir(dirname(__DIR__));
require_once __DIR__ . '/../base/Database.php';
use CIS\Base\Database;

$args = $argv ?? [];
if (!in_array('--confirm', $args, true)) {
    fwrite(STDERR, "Refusing to prune without --confirm flag.\n");
    exit(1);
}

$retentionDays = 90;
$cutoff = date('Y-m-d H:i:s', time() - ($retentionDays * 86400));

try {
    $deleted = Database::execute("DELETE FROM cis_user_events WHERE created_at < ?", [$cutoff]);
    echo "Pruned {$deleted} events older than {$retentionDays} days (cutoff {$cutoff}).\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, "Prune failed: " . $e->getMessage() . "\n");
    exit(2);
}
