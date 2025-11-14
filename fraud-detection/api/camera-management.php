<?php
/**
 * Camera Network Management API
 *
 * REST API for managing camera network:
 * - Register new cameras
 * - Update camera configuration
 * - Test camera streams
 * - Bulk import from CSV
 * - Monitor camera health
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../../shared/functions/db_connect.php';
require_once __DIR__ . '/../lib/EncryptionService.php';

use FraudDetection\Lib\EncryptionService;

header('Content-Type: application/json');

// Initialize encryption service
try {
    $encryption = new EncryptionService();
} catch (\Exception $e) {
    error_log('Encryption service init failed: ' . $e->getMessage());
    $encryption = null;
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Database connection
$db = db_connect();

// Route requests
try {
    switch ($action) {
        case 'list':
            handleListCameras($db, $encryption);
            break;

        case 'get':
            handleGetCamera($db, $encryption);
            break;

        case 'add':
            handleAddCamera($db, $encryption);
            break;

        case 'update':
            handleUpdateCamera($db, $encryption);
            break;

        case 'delete':
            handleDeleteCamera($db);
            break;

        case 'test':
            handleTestCamera($db, $encryption);
            break;

        case 'bulk_import':
            handleBulkImport($db, $encryption);
            break;

        case 'health_check':
            handleHealthCheck($db);
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }

} catch (\Exception $e) {
    error_log('Camera API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

/**
 * List all cameras
 */
function handleListCameras(\PDO $db, ?EncryptionService $encryption): void
{
    $stmt = $db->prepare("
        SELECT
            camera_id,
            camera_name,
            location,
            outlet_id,
            camera_type,
            ptz_capable,
            resolution,
            fps,
            online,
            health_status,
            last_seen,
            priority,
            created_at
        FROM camera_network
        ORDER BY outlet_id, location, camera_name
    ");

    $stmt->execute();
    $cameras = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Add decoded status
    foreach ($cameras as &$camera) {
        $camera['online'] = (bool)$camera['online'];
        $camera['ptz_capable'] = (bool)$camera['ptz_capable'];
    }

    jsonResponse([
        'success' => true,
        'cameras' => $cameras,
        'total' => count($cameras),
        'encryption_enabled' => $encryption ? $encryption->isEnabled() : false
    ]);
}

/**
 * Get single camera details (with decrypted stream URL)
 */
function handleGetCamera(\PDO $db, ?EncryptionService $encryption): void
{
    $cameraId = $_GET['camera_id'] ?? 0;

    if (!$cameraId) {
        jsonResponse(['success' => false, 'error' => 'camera_id required'], 400);
    }

    $stmt = $db->prepare("
        SELECT *
        FROM camera_network
        WHERE camera_id = :camera_id
    ");

    $stmt->execute(['camera_id' => $cameraId]);
    $camera = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$camera) {
        jsonResponse(['success' => false, 'error' => 'Camera not found'], 404);
    }

    // Decrypt stream URL if encrypted
    if ($camera['stream_url_encrypted'] && $encryption && $encryption->isEnabled()) {
        try {
            $encryptedBundle = json_decode($camera['stream_url_encrypted'], true);
            $camera['stream_url'] = $encryption->decryptCameraUrl(
                $encryptedBundle,
                ['camera_id' => $camera['camera_id']]
            );
        } catch (\Exception $e) {
            error_log('Failed to decrypt camera URL: ' . $e->getMessage());
            $camera['stream_url'] = '[ENCRYPTED - Decryption Failed]';
        }
    }

    // Remove encrypted version from response
    unset($camera['stream_url_encrypted']);

    jsonResponse([
        'success' => true,
        'camera' => $camera
    ]);
}

/**
 * Add new camera
 */
function handleAddCamera(\PDO $db, ?EncryptionService $encryption): void
{
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['camera_name', 'location', 'outlet_id', 'stream_url'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            jsonResponse(['success' => false, 'error' => "Missing required field: $field"], 400);
        }
    }

    // Encrypt stream URL
    $streamUrlEncrypted = null;
    if ($encryption && $encryption->isEnabled()) {
        try {
            $encryptedBundle = $encryption->encryptCameraUrl(
                $data['stream_url'],
                ['outlet_id' => $data['outlet_id']]
            );
            $streamUrlEncrypted = json_encode($encryptedBundle);
        } catch (\Exception $e) {
            error_log('Failed to encrypt camera URL: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Encryption failed'], 500);
        }
    }

    // Insert camera
    $stmt = $db->prepare("
        INSERT INTO camera_network (
            camera_name,
            location,
            outlet_id,
            stream_url_encrypted,
            camera_type,
            ptz_capable,
            resolution,
            fps,
            priority,
            detection_zones
        ) VALUES (
            :camera_name,
            :location,
            :outlet_id,
            :stream_url_encrypted,
            :camera_type,
            :ptz_capable,
            :resolution,
            :fps,
            :priority,
            :detection_zones
        )
    ");

    $stmt->execute([
        'camera_name' => $data['camera_name'],
        'location' => $data['location'],
        'outlet_id' => $data['outlet_id'],
        'stream_url_encrypted' => $streamUrlEncrypted,
        'camera_type' => $data['camera_type'] ?? 'fixed',
        'ptz_capable' => isset($data['ptz_capable']) ? (int)$data['ptz_capable'] : 0,
        'resolution' => $data['resolution'] ?? '1920x1080',
        'fps' => $data['fps'] ?? 30,
        'priority' => $data['priority'] ?? 5,
        'detection_zones' => !empty($data['detection_zones']) ? json_encode($data['detection_zones']) : null
    ]);

    $cameraId = $db->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => 'Camera added successfully',
        'camera_id' => $cameraId
    ]);
}

/**
 * Update camera
 */
function handleUpdateCamera(\PDO $db, ?EncryptionService $encryption): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $cameraId = $data['camera_id'] ?? 0;

    if (!$cameraId) {
        jsonResponse(['success' => false, 'error' => 'camera_id required'], 400);
    }

    // Build UPDATE query dynamically
    $updates = [];
    $params = ['camera_id' => $cameraId];

    $allowedFields = [
        'camera_name', 'location', 'outlet_id', 'camera_type',
        'ptz_capable', 'resolution', 'fps', 'priority', 'online'
    ];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = :$field";
            $params[$field] = $data[$field];
        }
    }

    // Handle stream URL encryption if provided
    if (isset($data['stream_url']) && $encryption && $encryption->isEnabled()) {
        try {
            $encryptedBundle = $encryption->encryptCameraUrl(
                $data['stream_url'],
                ['camera_id' => $cameraId]
            );
            $updates[] = "stream_url_encrypted = :stream_url_encrypted";
            $params['stream_url_encrypted'] = json_encode($encryptedBundle);
        } catch (\Exception $e) {
            error_log('Failed to encrypt camera URL: ' . $e->getMessage());
        }
    }

    // Handle detection zones
    if (isset($data['detection_zones'])) {
        $updates[] = "detection_zones = :detection_zones";
        $params['detection_zones'] = json_encode($data['detection_zones']);
    }

    if (empty($updates)) {
        jsonResponse(['success' => false, 'error' => 'No fields to update'], 400);
    }

    $sql = "UPDATE camera_network SET " . implode(', ', $updates) . " WHERE camera_id = :camera_id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    jsonResponse([
        'success' => true,
        'message' => 'Camera updated successfully',
        'updated_fields' => array_keys(array_diff_key($params, ['camera_id' => 1]))
    ]);
}

/**
 * Delete camera
 */
function handleDeleteCamera(\PDO $db): void
{
    $cameraId = $_GET['camera_id'] ?? $_POST['camera_id'] ?? 0;

    if (!$cameraId) {
        jsonResponse(['success' => false, 'error' => 'camera_id required'], 400);
    }

    $stmt = $db->prepare("DELETE FROM camera_network WHERE camera_id = :camera_id");
    $stmt->execute(['camera_id' => $cameraId]);

    if ($stmt->rowCount() > 0) {
        jsonResponse([
            'success' => true,
            'message' => 'Camera deleted successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Camera not found'], 404);
    }
}

/**
 * Test camera stream connectivity
 */
function handleTestCamera(\PDO $db, ?EncryptionService $encryption): void
{
    $cameraId = $_GET['camera_id'] ?? 0;
    $testUrl = $_GET['test_url'] ?? null;

    if (!$cameraId && !$testUrl) {
        jsonResponse(['success' => false, 'error' => 'camera_id or test_url required'], 400);
    }

    $streamUrl = $testUrl;

    // Get stream URL from database if camera_id provided
    if ($cameraId) {
        $stmt = $db->prepare("SELECT stream_url_encrypted FROM camera_network WHERE camera_id = :camera_id");
        $stmt->execute(['camera_id' => $cameraId]);
        $camera = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$camera) {
            jsonResponse(['success' => false, 'error' => 'Camera not found'], 404);
        }

        // Decrypt stream URL
        if ($camera['stream_url_encrypted'] && $encryption && $encryption->isEnabled()) {
            try {
                $encryptedBundle = json_decode($camera['stream_url_encrypted'], true);
                $streamUrl = $encryption->decryptCameraUrl($encryptedBundle, ['camera_id' => $cameraId]);
            } catch (\Exception $e) {
                jsonResponse(['success' => false, 'error' => 'Failed to decrypt camera URL'], 500);
            }
        }
    }

    // Test RTSP/HTTP stream
    $testResult = testStreamConnectivity($streamUrl);

    // Update camera health status if camera_id provided
    if ($cameraId && $testResult['reachable']) {
        $db->prepare("
            UPDATE camera_network
            SET online = 1, health_status = 'healthy', last_seen = NOW()
            WHERE camera_id = :camera_id
        ")->execute(['camera_id' => $cameraId]);
    } elseif ($cameraId) {
        $db->prepare("
            UPDATE camera_network
            SET online = 0, health_status = :error
            WHERE camera_id = :camera_id
        ")->execute([
            'camera_id' => $cameraId,
            'error' => $testResult['error'] ?? 'unreachable'
        ]);
    }

    jsonResponse([
        'success' => $testResult['reachable'],
        'test_result' => $testResult
    ]);
}

/**
 * Bulk import cameras from CSV
 */
function handleBulkImport(\PDO $db, ?EncryptionService $encryption): void
{
    if (!isset($_FILES['csv_file'])) {
        jsonResponse(['success' => false, 'error' => 'No CSV file uploaded'], 400);
    }

    $csvFile = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($csvFile, 'r');

    if (!$handle) {
        jsonResponse(['success' => false, 'error' => 'Failed to open CSV file'], 500);
    }

    // Read header row
    $headers = fgetcsv($handle);
    if (!$headers) {
        jsonResponse(['success' => false, 'error' => 'Invalid CSV format'], 400);
    }

    $imported = 0;
    $errors = [];

    // Process rows
    while (($row = fgetcsv($handle)) !== false) {
        try {
            $data = array_combine($headers, $row);

            // Encrypt stream URL
            $streamUrlEncrypted = null;
            if (!empty($data['stream_url']) && $encryption && $encryption->isEnabled()) {
                $encryptedBundle = $encryption->encryptCameraUrl(
                    $data['stream_url'],
                    ['outlet_id' => $data['outlet_id'] ?? 0]
                );
                $streamUrlEncrypted = json_encode($encryptedBundle);
            }

            // Insert camera
            $stmt = $db->prepare("
                INSERT INTO camera_network (
                    camera_name, location, outlet_id, stream_url_encrypted,
                    camera_type, ptz_capable, resolution, fps, priority
                ) VALUES (
                    :camera_name, :location, :outlet_id, :stream_url_encrypted,
                    :camera_type, :ptz_capable, :resolution, :fps, :priority
                )
            ");

            $stmt->execute([
                'camera_name' => $data['camera_name'] ?? 'Unknown',
                'location' => $data['location'] ?? 'Unknown',
                'outlet_id' => $data['outlet_id'] ?? 0,
                'stream_url_encrypted' => $streamUrlEncrypted,
                'camera_type' => $data['camera_type'] ?? 'fixed',
                'ptz_capable' => isset($data['ptz_capable']) ? (int)$data['ptz_capable'] : 0,
                'resolution' => $data['resolution'] ?? '1920x1080',
                'fps' => $data['fps'] ?? 30,
                'priority' => $data['priority'] ?? 5
            ]);

            $imported++;

        } catch (\Exception $e) {
            $errors[] = "Row $imported: " . $e->getMessage();
        }
    }

    fclose($handle);

    jsonResponse([
        'success' => true,
        'message' => "Imported $imported cameras",
        'imported' => $imported,
        'errors' => $errors
    ]);
}

/**
 * Health check for all cameras
 */
function handleHealthCheck(\PDO $db): void
{
    $stmt = $db->query("
        SELECT
            COUNT(*) as total,
            SUM(online) as online_count,
            SUM(CASE WHEN health_status = 'healthy' THEN 1 ELSE 0 END) as healthy_count,
            SUM(CASE WHEN last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as stale_count
        FROM camera_network
    ");

    $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'health' => [
            'total_cameras' => (int)$stats['total'],
            'online' => (int)$stats['online_count'],
            'healthy' => (int)$stats['healthy_count'],
            'stale' => (int)$stats['stale_count'],
            'offline' => (int)$stats['total'] - (int)$stats['online_count']
        ]
    ]);
}

/**
 * Test stream connectivity
 */
function testStreamConnectivity(string $streamUrl): array
{
    // Parse URL
    $parts = parse_url($streamUrl);

    if (!$parts || empty($parts['host'])) {
        return [
            'reachable' => false,
            'error' => 'Invalid URL format'
        ];
    }

    $host = $parts['host'];
    $port = $parts['port'] ?? ($parts['scheme'] === 'rtsp' ? 554 : 80);

    // Test TCP connectivity
    $startTime = microtime(true);
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    $latency = round((microtime(true) - $startTime) * 1000, 2);

    if ($socket) {
        fclose($socket);
        return [
            'reachable' => true,
            'latency_ms' => $latency,
            'host' => $host,
            'port' => $port,
            'scheme' => $parts['scheme']
        ];
    } else {
        return [
            'reachable' => false,
            'error' => "$errstr ($errno)",
            'host' => $host,
            'port' => $port
        ];
    }
}

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}
