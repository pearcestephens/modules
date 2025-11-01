<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Controllers;

use HumanResources\Payroll\Services\ReconciliationService;
use PDO;

/**
 * Reconciliation Controller
 *
 * Handles variance reporting between CIS, Xero, and Deputy
 *
 * @package HumanResources\Payroll\Controllers
 */
class ReconciliationController
{
    private PDO $db;
    private ReconciliationService $service;

    public function __construct()
    {
        $this->db = $this->getDb();
        $this->service = new ReconciliationService($this->db);
    }

    /**
     * Show reconciliation dashboard view
     */
    public function index(): void
    {
        require __DIR__ . '/../views/reconciliation.php';
    }

    /**
     * Get dashboard data (API)
     */
    public function dashboard(): void
    {
        try {
            $data = $this->service->getDashboardData();

            $this->jsonResponse([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current variances (API)
     */
    public function getVariances(): void
    {
        try {
            $period = $_GET['period'] ?? 'current';
            $threshold = (float)($_GET['threshold'] ?? 0.01);

            $variances = $this->service->getVariances($period, $threshold);

            $this->jsonResponse([
                'success' => true,
                'variances' => $variances,
                'count' => count($variances)
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compare specific run (API)
     */
    public function compareRun(int $runId): void
    {
        try {
            $comparison = $this->service->compareRun($runId);

            $this->jsonResponse([
                'success' => true,
                'comparison' => $comparison
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database connection
     */
    private function getDb(): PDO
    {
        static $pdo = null;

        if ($pdo === null) {
            $pdo = new PDO(
                "mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4",
                "jcepnzzkmj",
                "wprKh9Jq63",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        }

        return $pdo;
    }

    /**
     * JSON response helper
     */
    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
