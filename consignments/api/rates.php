<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $from = (string)($data['from_outlet'] ?? '');
    $to   = (string)($data['to_outlet'] ?? '');
    $boxes= (int)($data['boxes'] ?? 1);
    if ($from === '' || $to === '' || $boxes <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
        exit;
    }

    // Try FreightEngine if available
    $rates = [];
    if (class_exists('FreightEngine')) {
        try {
            $fe = new FreightEngine(\CIS\Base\Database::pdo());
            // If the engine exposes a quoter, adapt here (placeholder):
            // $rates = $fe->getRates($from, $to, $boxes);
        } catch (\Throwable $e) {
            // fall through to demo
        }
    }

    if (empty($rates)) {
        // Demo data
        $rates = [
            [ 'carrier' => 'nzpost', 'service' => 'standard', 'price' => 8.50, 'currency' => 'NZD', 'eta' => '2-3 days' ],
            [ 'carrier' => 'gss',    'service' => 'economy',  'price' => 7.90, 'currency' => 'NZD', 'eta' => '3-4 days' ],
        ];
    }

    echo json_encode(['success' => true, 'data' => ['rates' => $rates, 'boxes' => $boxes]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
