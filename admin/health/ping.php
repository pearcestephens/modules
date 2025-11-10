<?php
declare(strict_types=1);

// Admin Health: Ping endpoint
// Returns a minimal JSON payload confirming liveness.

require_once __DIR__ . '/../../app/Support/Response.php';

use App\Support\Response;

$now = (new DateTimeImmutable('now', new DateTimeZone('Pacific/Auckland')))->format(DateTimeInterface::ATOM);
$reqId = bin2hex(random_bytes(8));

Response::json([
    'success' => true,
    'service' => 'admin/health/ping',
    'time' => $now,
    'request_id' => $reqId,
]);
