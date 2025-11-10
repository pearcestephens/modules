<?php
declare(strict_types=1);
// Common bootstrap for cis-v2 widget endpoints

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// Require authentication (simple gate)
if (empty($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

// Resolve PDO safely
function cisv2_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    try {
        if (class_exists('CIS\\Base\\Database')) {
            /** @var PDO */
            $pdo = CIS\Base\Database::pdo();
            return $pdo;
        }
    } catch (Throwable $e) { /* continue to fallback */ }

    global $pdo as $globalPdo;
    if ($globalPdo instanceof PDO) return $globalPdo;

    throw new RuntimeException('Database unavailable');
}

function respond($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function tryQuery(PDO $pdo, string $sql, array $params = []): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}
