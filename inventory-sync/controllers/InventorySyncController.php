<?php
/**
 * ============================================================================
 * INVENTORY SYNC CONTROLLER
 * API endpoints for inventory sync operations
 * ============================================================================
 */

namespace CIS\InventorySync;

require_once __DIR__ . '/../classes/InventorySyncEngine.php';

class InventorySyncController {
    protected $sync_engine;
    protected $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->sync_engine = new InventorySyncEngine($pdo);
    }

    /**
     * Main request handler
     */
    public function handle() {
        // Get action from request
        $action = $_GET['action'] ?? $_POST['action'] ?? 'status';

        // Set JSON header
        header('Content-Type: application/json');

        try {
            switch ($action) {
                case 'check':
                    return $this->checkSync();

                case 'force_to_vend':
                    return $this->forceSyncToVend();

                case 'force_from_vend':
                    return $this->forceSyncFromVend();

                case 'transfer':
                    return $this->recordTransfer();

                case 'status':
                    return $this->getSyncStatus();

                case 'alerts':
                    return $this->getAlerts();

                case 'resolve_alert':
                    return $this->resolveAlert();

                case 'history':
                    return $this->getHistory();

                case 'metrics':
                    return $this->getMetrics();

                default:
                    return $this->error('Invalid action', 400);
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Check sync for specific product or all products
     * GET /api/inventory-sync?action=check&product_id=123&outlet_id=1
     */
    protected function checkSync() {
        $product_id = $_GET['product_id'] ?? null;
        $outlet_id = $_GET['outlet_id'] ?? null;

        $report = $this->sync_engine->checkSync($product_id, $outlet_id);

        return $this->success($report, 'Sync check completed');
    }

    /**
     * Force sync TO Vend (local is master)
     * POST /api/inventory-sync?action=force_to_vend
     * Body: {"product_id": 123, "outlet_id": 1}
     */
    protected function forceSyncToVend() {
        $data = $this->getJsonInput();

        if (!isset($data['product_id']) || !isset($data['outlet_id'])) {
            return $this->error('Missing product_id or outlet_id', 400);
        }

        $result = $this->sync_engine->forceSyncToVend(
            $data['product_id'],
            $data['outlet_id']
        );

        if ($result['success']) {
            return $this->success($result, 'Successfully synced to Vend');
        } else {
            return $this->error($result['error'] ?? 'Sync failed', 500);
        }
    }

    /**
     * Force sync FROM Vend (Vend is master)
     * POST /api/inventory-sync?action=force_from_vend
     * Body: {"product_id": 123, "outlet_id": 1}
     */
    protected function forceSyncFromVend() {
        $data = $this->getJsonInput();

        if (!isset($data['product_id']) || !isset($data['outlet_id'])) {
            return $this->error('Missing product_id or outlet_id', 400);
        }

        $result = $this->sync_engine->forceSyncFromVend(
            $data['product_id'],
            $data['outlet_id']
        );

        if ($result['success']) {
            return $this->success($result, 'Successfully synced from Vend');
        } else {
            return $this->error($result['error'] ?? 'Sync failed', 500);
        }
    }

    /**
     * Record a transfer and ensure sync
     * POST /api/inventory-sync?action=transfer
     * Body: {
     *   "product_id": 123,
     *   "from_outlet_id": 1,
     *   "to_outlet_id": 2,
     *   "quantity": 10
     * }
     */
    protected function recordTransfer() {
        $data = $this->getJsonInput();

        $required = ['product_id', 'from_outlet_id', 'to_outlet_id', 'quantity'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->error("Missing required field: $field", 400);
            }
        }

        $result = $this->sync_engine->recordTransfer($data);

        if ($result['success']) {
            return $this->success($result, 'Transfer recorded and synced');
        } else {
            return $this->error($result['error'] ?? 'Transfer failed', 500);
        }
    }

    /**
     * Get current sync status
     * GET /api/inventory-sync?action=status
     */
    protected function getSyncStatus() {
        $sql = "SELECT * FROM v_sync_health_24h LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $health = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Get last check
        $sql = "SELECT * FROM inventory_sync_checks ORDER BY scan_time DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $last_check = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Get unresolved alerts count
        $sql = "SELECT COUNT(*) as alert_count FROM inventory_sync_alerts WHERE resolved = FALSE";
        $stmt = $this->pdo->query($sql);
        $alerts = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $this->success([
            'health_24h' => $health,
            'last_check' => $last_check,
            'unresolved_alerts' => (int)$alerts['alert_count'],
            'overall_status' => $this->determineOverallStatus($health, $last_check),
        ], 'Sync status retrieved');
    }

    /**
     * Get alerts (all or unresolved only)
     * GET /api/inventory-sync?action=alerts&resolved=false
     */
    protected function getAlerts() {
        $resolved = $_GET['resolved'] ?? 'false';
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        if ($resolved === 'false' || $resolved === '0') {
            $sql = "SELECT * FROM v_unresolved_alerts LIMIT ? OFFSET ?";
        } else {
            $sql = "
                SELECT a.*, p.name as product_name, o.name as outlet_name
                FROM inventory_sync_alerts a
                LEFT JOIN vend_products p ON a.product_id = p.product_id
                LEFT JOIN vend_outlets o ON a.outlet_id = o.outlet_id
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit, $offset]);
        $alerts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get total count
        $where = ($resolved === 'false' || $resolved === '0') ? 'WHERE resolved = FALSE' : '';
        $sql = "SELECT COUNT(*) as total FROM inventory_sync_alerts $where";
        $stmt = $this->pdo->query($sql);
        $total = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $this->success([
            'alerts' => $alerts,
            'total' => (int)$total['total'],
            'limit' => $limit,
            'offset' => $offset,
        ], 'Alerts retrieved');
    }

    /**
     * Resolve an alert
     * POST /api/inventory-sync?action=resolve_alert
     * Body: {"alert_id": 123, "resolution_notes": "Fixed manually"}
     */
    protected function resolveAlert() {
        $data = $this->getJsonInput();

        if (!isset($data['alert_id'])) {
            return $this->error('Missing alert_id', 400);
        }

        $sql = "
            UPDATE inventory_sync_alerts
            SET resolved = TRUE,
                resolved_at = NOW(),
                resolved_by = ?,
                resolution_notes = ?
            WHERE alert_id = ?
        ";

        $user = $_SESSION['user_id'] ?? 'system';
        $notes = $data['resolution_notes'] ?? '';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user, $notes, $data['alert_id']]);

        if ($stmt->rowCount() > 0) {
            return $this->success(['alert_id' => $data['alert_id']], 'Alert resolved');
        } else {
            return $this->error('Alert not found', 404);
        }
    }

    /**
     * Get inventory change history
     * GET /api/inventory-sync?action=history&product_id=123&outlet_id=1&limit=100
     */
    protected function getHistory() {
        $product_id = $_GET['product_id'] ?? null;
        $outlet_id = $_GET['outlet_id'] ?? null;
        $limit = (int)($_GET['limit'] ?? 100);
        $offset = (int)($_GET['offset'] ?? 0);

        $where = [];
        $params = [];

        if ($product_id) {
            $where[] = "product_id = ?";
            $params[] = $product_id;
        }

        if ($outlet_id) {
            $where[] = "outlet_id = ?";
            $params[] = $outlet_id;
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT l.*, p.name as product_name, o.name as outlet_name
            FROM inventory_change_log l
            LEFT JOIN vend_products p ON l.product_id = p.product_id
            LEFT JOIN vend_outlets o ON l.outlet_id = o.outlet_id
            $where_sql
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get total count
        $sql = "SELECT COUNT(*) as total FROM inventory_change_log $where_sql";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_slice($params, 0, -2)); // Remove limit/offset
        $total = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $this->success([
            'history' => $history,
            'total' => (int)$total['total'],
            'limit' => $limit,
            'offset' => $offset,
        ], 'History retrieved');
    }

    /**
     * Get sync metrics
     * GET /api/inventory-sync?action=metrics&days=7
     */
    protected function getMetrics() {
        $days = (int)($_GET['days'] ?? 7);

        $sql = "
            SELECT *
            FROM inventory_sync_metrics
            WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY metric_date DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days]);
        $metrics = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->success([
            'metrics' => $metrics,
            'days' => $days,
        ], 'Metrics retrieved');
    }

    /**
     * Determine overall status from health data
     */
    protected function determineOverallStatus($health, $last_check) {
        if (!$health || !$last_check) {
            return [
                'status' => 'unknown',
                'message' => 'No sync data available',
                'color' => 'gray',
            ];
        }

        $accuracy = (float)$health['accuracy_percent'];
        $critical = (int)$health['critical_issues'];
        $major = (int)$health['major_drifts'];

        if ($critical > 0) {
            return [
                'status' => 'critical',
                'message' => "$critical critical issues require immediate attention",
                'color' => 'red',
            ];
        }

        if ($major > 10) {
            return [
                'status' => 'warning',
                'message' => "$major major drifts detected",
                'color' => 'orange',
            ];
        }

        if ($accuracy >= 99.5) {
            return [
                'status' => 'excellent',
                'message' => "Sync accuracy: {$accuracy}%",
                'color' => 'green',
            ];
        }

        if ($accuracy >= 97.0) {
            return [
                'status' => 'good',
                'message' => "Sync accuracy: {$accuracy}%",
                'color' => 'blue',
            ];
        }

        return [
            'status' => 'fair',
            'message' => "Sync accuracy: {$accuracy}%",
            'color' => 'yellow',
        ];
    }

    /**
     * Get JSON input from request body
     */
    protected function getJsonInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: [];
    }

    /**
     * Success response
     */
    protected function success($data, $message = 'Success') {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c'),
        ]);
        exit;
    }

    /**
     * Error response
     */
    protected function error($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code,
            'timestamp' => date('c'),
        ]);
        exit;
    }
}

// If called directly, handle the request
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    // Connect to database
    try {
        $pdo = new \PDO(
            "mysql:host=" . (getenv('DB_HOST') ?: 'localhost') . ";dbname=" . (getenv('DB_NAME') ?: 'vend'),
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: ''
        );
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $controller = new InventorySyncController($pdo);
        $controller->handle();

    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'timestamp' => date('c'),
        ]);
    }
}
