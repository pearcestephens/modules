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

    $transferId = (int)($_POST['transfer_id'] ?? 0);
    if ($transferId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Missing transfer_id']);
        exit;
    }

    $baseDir = __DIR__ . '/../uploads/' . date('Y/m/d');
    if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true); }

    $saved = [];
    foreach ($_FILES as $key => $file) {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) { continue; }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe = preg_replace('/[^A-Za-z0-9_\.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $name = $safe . '_' . uniqid() . ($ext ? '.' . strtolower($ext) : '');
        $dest = $baseDir . '/' . $name;
        if (@move_uploaded_file($file['tmp_name'], $dest)) {
            $url = '/modules/consignments/uploads/' . date('Y/m/d') . '/' . $name;
            $saved[] = [
                'name' => $file['name'],
                'stored_as' => $name,
                'size' => (int)$file['size'],
                'mime' => (string)($file['type'] ?? ''),
                'url' => $url
            ];
        }
    }

    echo json_encode(['success' => true, 'files' => $saved]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
