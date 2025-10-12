<?php
declare(strict_types=1);

require_once __DIR__ . '/_shared/lib/Helpers.php';
use Modules\Shared\Helpers;

$id = isset($_GET['transfer']) ? (int)$_GET['transfer'] : (int)($_GET['id'] ?? 0);
$qs = $id ? ('?transfer=' . $id) : '';
header('Location: ' . Helpers::url('/transfers/receive') . $qs, true, 302);
exit;
