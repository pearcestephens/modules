<?php
declare(strict_types=1);

namespace Transfers\Lib;

use PDO;

final class Queue
{
    /**
     * Enqueue a transfer operation for downstream processing (e.g., vend_consignment_sync)
     * Writes to transfer_queue_log (observed by Queue V2 cron).
     */
    public static function enqueue(PDO $pdo, string $queueName, int $transferId, string $operation, array $payload): int
    {
        $stmt = $pdo->prepare("INSERT INTO transfer_queue_log
            (trace_id, queue_name, operation, transfer_id, idempotency_key, attempt_number, max_attempts, status, priority, request_payload, created_at, updated_at)
            VALUES (:trace, :queue, :op, :tid, :idem, 1, 3, 'pending', 5, :payload, NOW(), NOW())");
        $trace = bin2hex(random_bytes(12));
        $idem  = hash('sha256', $queueName.'|'.$transferId.'|'.$operation.'|'.json_encode($payload));
        $stmt->execute([
            'trace'=>$trace, 'queue'=>$queueName, 'op'=>$operation, 'tid'=>$transferId,
            'idem'=>$idem, 'payload'=>json_encode($payload, JSON_UNESCAPED_SLASHES)
        ]);
        return (int)$pdo->lastInsertId();
    }
}
