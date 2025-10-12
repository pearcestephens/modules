<?php
declare(strict_types=1);

namespace Transfers\Lib;

use PDO;

final class Helpers
{
    public static function fetchTransfer(PDO $pdo, int $transferId): array
    {
        $q = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
        $q->execute([$transferId]);
        $t = $q->fetch();
        if (!$t) throw new \RuntimeException("Transfer not found");
        return $t;
    }

    public static function assertState(array $transfer, array $allowedStates): void
    {
        if (!in_array($transfer['state'], $allowedStates, true)) {
            throw new \RuntimeException("Invalid state {$transfer['state']} for this operation");
        }
    }

    public static function normalizeDeliveryMode(string $mode): string
    {
        // UI: manual_courier|pickup|dropoff|internal_drive → DB uses 'manual','pickup','dropoff','internal_drive'
        return $mode === 'manual_courier' ? 'manual' : $mode;
    }
}
