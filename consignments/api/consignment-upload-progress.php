<?php
/**
 * REAL Consignment Upload Progress (SSE) — queue & direct aware
 * Streams from consignment_upload_progress + consignment_product_progress.
 *
 * GET: transfer_id, session (or session_id)
 */
declare(strict_types=1);

$transferId = isset($_GET['transfer_id']) && is_numeric($_GET['transfer_id']) ? (int)$_GET['transfer_id'] : 0;
$sessionId  = $_GET['session'] ?? ($_GET['session_id'] ?? '');

if ($transferId <= 0 || strlen($sessionId) < 8) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Bad request";
    exit;
}

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
if (function_exists('ob_get_level') && ob_get_level()) { @ob_end_flush(); }
@ob_implicit_flush(true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php'; // $pdo

function sse_send(string $event, array $payload): void {
    echo "event: {$event}\n";
    echo "data: " . json_encode($payload) . "\n\n";
    if (function_exists('ob_flush')) @ob_flush();
    flush();
}

$connectedSent = false;
$start = time();
$MAX_SECONDS = 1800; // 30 minutes

while (!connection_aborted() && (time() - $start) < $MAX_SECONDS) {
    try {
        // 1) Pull progress
        $stmt = $pdo->prepare("
          SELECT status, total_products, completed_products, failed_products,
                 current_operation, last_message, updated_at
          FROM consignment_upload_progress
          WHERE transfer_id = ? AND session_id = ?
          LIMIT 1
        ");
        $stmt->execute([$transferId, $sessionId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($progress) {
            if (!$connectedSent) {
                sse_send('connected', [
                    'transfer_id' => $transferId,
                    'session_id'  => $sessionId,
                    'total_items' => (int)$progress['total_products'],
                    'timestamp'   => date('c')
                ]);
                $connectedSent = true;
            }

            // 2) A few interesting recent products (newest first)
            $p = $pdo->prepare("
              SELECT name, sku, status, processed_at
              FROM consignment_product_progress
              WHERE transfer_id = ? AND session_id = ?
              AND status IN ('completed','failed')
              ORDER BY processed_at DESC
              LIMIT 6
            ");
            $p->execute([$transferId, $sessionId]);
            $recent = $p->fetchAll(PDO::FETCH_ASSOC);

            $done  = (int)($progress['completed_products'] ?? 0);
            $total = (int)($progress['total_products'] ?? 0);
            $failed= (int)($progress['failed_products'] ?? 0);
            $pct   = $total > 0 ? round(($done / $total) * 100, 1) : 0.0;

            sse_send('progress', [
                'progress_percentage' => $pct,
                'current_operation'   => $progress['current_operation'] ?? '…',
                'completed_products'  => $done,
                'failed_products'     => $failed,
                'total_products'      => $total,
                'recent_products'     => $recent,
                'status'              => $progress['status'],
                'updated_at'          => $progress['updated_at']
            ]);

            if (in_array($progress['status'], ['completed', 'failed'], true)) {
                sse_send('finished', [
                    'success'             => $progress['status'] === 'completed',
                    'status'              => $progress['status'],
                    'transfer_id'         => $transferId,
                    'session_id'          => $sessionId,
                    'completed_products'  => $done,
                    'failed_products'     => $failed,
                    'total_products'      => $total,
                    'timestamp'           => date('c')
                ]);
                break;
            }
        } else {
            // First seconds can race — still give a connected+waiting signal
            if (!$connectedSent) {
                sse_send('connected', [
                    'transfer_id' => $transferId,
                    'session_id'  => $sessionId,
                    'total_items' => 0,
                    'timestamp'   => date('c')
                ]);
                $connectedSent = true;
            }
            sse_send('progress', [
                'progress_percentage' => 0,
                'current_operation'   => 'Waiting for uploader…',
                'completed_products'  => 0,
                'failed_products'     => 0,
                'total_products'      => 0,
                'recent_products'     => [],
                'status'              => 'pending'
            ]);
        }

    } catch (Throwable $e) {
        sse_send('error', ['message' => $e->getMessage()]);
        break;
    }

    // heartbeat-ish cadence
    usleep(400000); // 0.4s
}
