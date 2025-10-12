<?php
declare(strict_types=1);

namespace Transfers\Lib;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    /**
     * Return the shared PDO handle from your cis_pdo() factory.
     */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        // Use your centralized PDO factory
        /** @var PDO $pdo */
        $pdo = \cis_pdo();

        // Optional: set session timezone if you want to override (e.g. via DB_TZ="+13:00")
        $tz = \getenv('DB_TZ');
        if ($tz !== false && $tz !== '') {
            $pdo->exec("SET time_zone = '" . str_replace("'", "''", $tz) . "'");
        }

        return self::$pdo = $pdo;
    }

    /** Optional helpers */
    public static function reset(): void { self::$pdo = null; }
    public static function ping(): bool
    {
        try { return (bool) self::pdo()->query('SELECT 1')->fetchColumn(); }
        catch (\Throwable) { return false; }
    }
}
