<?php
declare(strict_types=1);

namespace Modules\Consignments\Transfers\lib;

use PDO;
use Throwable;

final class Db
{
    public static function pdo(): PDO
    {
        if (!function_exists('cis_pdo')) {
            throw new \RuntimeException('cis_pdo() not available. Ensure app.php is loaded.');
        }
        $pdo = cis_pdo();
        // Optional: set per-connection TZ if configured
        try {
            if (!empty($_ENV['DB_TZ'])) {
                $stmt = $pdo->prepare('SET time_zone = :tz');
                $stmt->execute([':tz' => (string)$_ENV['DB_TZ']]);
            }
        } catch (Throwable $e) {
            // ignore
        }
        return $pdo;
    }
}
