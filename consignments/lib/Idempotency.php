<?php
declare(strict_types=1);

namespace Transfers\Lib;

use PDO;

final class Idempotency
{
    public static function makeKey(string $scope, int $transferId, string $nonce): string
    {
        return $scope.':'.$transferId.':'.$nonce;
    }

    public static function begin(PDO $pdo, string $key): ?array
    {
        // Return prior response if exists, else reserve the key
        $stmt = $pdo->prepare("SELECT response_json, status_code FROM transfer_idempotency WHERE idem_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row) {
            return ['cached' => true, 'status_code' => (int)$row['status_code'], 'response_json' => $row['response_json']];
        }
        $hash = hash('sha256', $key);
        $stmt = $pdo->prepare("INSERT INTO transfer_idempotency (idem_key, idem_hash, created_at) VALUES (?,?, NOW())");
        $stmt->execute([$key, $hash]);
        return ['cached' => false];
    }

    public static function finish(PDO $pdo, string $key, int $statusCode, array $response): void
    {
        $stmt = $pdo->prepare("UPDATE transfer_idempotency SET status_code = ?, response_json = ?, created_at = NOW() WHERE idem_key = ?");
        $stmt->execute([$statusCode, json_encode($response, JSON_UNESCAPED_SLASHES), $key]);
    }
}
