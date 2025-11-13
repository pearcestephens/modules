<?php
declare(strict_types=1);

namespace Consignments\Infra\Freight;

use PDO;

/**
 * CredentialsProvider
 *
 * Securely resolves courier credentials per-outlet from environment variables
 * and optionally from DB if a `courier_credentials` table exists.
 *
 * IMPORTANT: Never return raw secrets to callers. Only expose masked previews
 * and presence flags. All real API calls must be made server-side.
 */
class CredentialsProvider
{
    private PDO $pdo;
    private array $carriers;

    public function __construct(PDO $pdo, array $carriers = [])
    {
        $this->pdo = $pdo;
        $this->carriers = $carriers ?: $this->defaultCarriers();
    }

    public function carriers(): array
    {
        return $this->carriers;
    }

    /**
     * Get credential presence/mask info for an outlet across all carriers.
     */
    public function getOutletStatus(int $outletId): array
    {
        $out = [
            'outlet_id' => $outletId,
            'carriers' => []
        ];

        foreach ($this->carriers as $carrier) {
            $env = $this->readEnv($carrier, $outletId);
            $db = $this->readDb($carrier, $outletId);

            // Prefer DB when available, fall back to env
            $src = $db ?: $env;
            $hasKey = !empty($src['api_key'] ?? '') || !empty($src['access_token'] ?? '');
            $hasSecret = !empty($src['api_secret'] ?? '') || !empty($src['client_secret'] ?? '');

            $out['carriers'][$carrier] = [
                'configured' => (bool)($src['configured'] ?? ($hasKey || $hasSecret)),
                'key_masked' => $this->mask($src['api_key'] ?? ($src['access_token'] ?? '')),
                'secret_masked' => $this->mask($src['api_secret'] ?? ($src['client_secret'] ?? '')),
                'updated_at' => $src['updated_at'] ?? null,
                'source' => $src ? ($src['source'] ?? 'env') : null,
            ];
        }

        return $out;
    }

    /**
     * Build a summary for multiple outlets at once.
     */
    public function getMultiOutletStatus(array $outletIds): array
    {
        $rows = [];
        foreach ($outletIds as $oid) {
            $rows[] = $this->getOutletStatus((int)$oid);
        }
        return $rows;
    }

    private function defaultCarriers(): array
    {
        $envList = getenv('FREIGHT_CARRIERS') ?: '';
        if ($envList) {
            $arr = array_filter(array_map('trim', explode(',', (string)$envList)));
            if ($arr) return array_map('strtolower', $arr);
        }
        // Sensible defaults; pluggable
    return ['nzpost', 'nzcouriers'];
    }

    private function readEnv(string $carrier, int $outletId): array
    {
        $prefix = strtoupper($carrier);
        $oid = (string)$outletId;
        $map = [
            "FREIGHT_{$prefix}_OUTLET_{$oid}_API_KEY" => 'api_key',
            "FREIGHT_{$prefix}_OUTLET_{$oid}_API_SECRET" => 'api_secret',
            "FREIGHT_{$prefix}_OUTLET_{$oid}_ACCESS_TOKEN" => 'access_token',
            "FREIGHT_{$prefix}_OUTLET_{$oid}_CLIENT_SECRET" => 'client_secret',
        ];
        $out = [];
        foreach ($map as $envKey => $field) {
            $val = getenv($envKey);
            if ($val !== false && $val !== null && $val !== '') {
                $out[$field] = (string)$val;
            }
        }
        if ($out) {
            $out['configured'] = true;
            $out['source'] = 'env';
        }
        return $out;
    }

    private function readDb(string $carrier, int $outletId): array
    {
        try {
            // Check if table exists quickly
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'courier_credentials'");
            if (!$stmt->fetchColumn()) {
                return [];
            }

            $sql = "SELECT api_key, api_secret, access_token, client_secret, updated_at
                    FROM courier_credentials
                    WHERE outlet_id = :oid AND carrier = :carrier AND deleted_at IS NULL
                    ORDER BY updated_at DESC LIMIT 1";
            $q = $this->pdo->prepare($sql);
            $q->execute([':oid' => $outletId, ':carrier' => strtolower($carrier)]);
            $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
            if ($row) {
                $row['configured'] = true;
                $row['source'] = 'db';
            }
            return $row;
        } catch (\Throwable $e) {
            // Fail closed; do not leak errors or secrets
            return [];
        }
    }

    private function mask(?string $secret): ?string
    {
        if (!$secret) return null;
        $len = strlen($secret);
        if ($len <= 4) return '****';
        $start = substr($secret, 0, 4);
        $end = substr($secret, -2);
        return $start . str_repeat('*', max(0, $len - 6)) . $end;
    }
}
