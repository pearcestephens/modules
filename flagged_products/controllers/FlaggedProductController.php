<?php
/**
 * Flagged Products Controller
 *
 * Main controller for flagged products module
 *
 * @package CIS\Modules\FlaggedProducts
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\FlaggedProducts\Controllers;

use CIS\FlaggedProducts\Models\FlaggedProductModel;
use Exception;

class FlaggedProductController
{
    private FlaggedProductModel $model;
    private array $config;

    public function __construct(array $config)
    {
        $this->model = new FlaggedProductModel();
        $this->config = $config;
    }

    /**
     * Display main index page
     */
    public function index(): void
    {
        // Get all outlets
        $db = db();
        $stmt = $db->prepare("
            SELECT id, name, store_code
            FROM vend_outlets
            WHERE deleted_at IS NULL
            OR deleted_at = ''
            ORDER BY name ASC
        ");
        $stmt->execute();
        $outlets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get current user
        $userID = $_SESSION['userID'] ?? 0;
        $userName = $_SESSION['userName'] ?? 'User';

        // Render view
        $this->render('index', [
            'outlets' => $outlets,
            'userID' => $userID,
            'userName' => $userName,
            'config' => $this->config,
        ]);
    }

    /**
     * Display outlet-specific page
     */
    public function outlet(): void
    {
        $outletID = $_GET['id'] ?? '';

        if (empty($outletID)) {
            header('Location: /modules/flagged_products/');
            exit;
        }

        // Get outlet info
        $db = db();
        $stmt = $db->prepare("SELECT * FROM vend_outlets WHERE id = ?");
        $stmt->bind_param('s', $outletID);
        $stmt->execute();
        $outlet = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$outlet) {
            header('Location: /modules/flagged_products/');
            exit;
        }

        // Get flagged products
        $products = $this->model->getByOutlet($outletID);
        $pendingCount = $this->model->getPendingCount($outletID);
        $accuracyStats = $this->model->getAccuracyStats($outletID);

        // Render view
        $this->render('outlet', [
            'outlet' => $outlet,
            'products' => $products,
            'pendingCount' => $pendingCount,
            'accuracyStats' => $accuracyStats,
            'config' => $this->config,
        ]);
    }

    /**
     * Display cron job dashboard
     */
    public function cronDashboard(): void
    {
        $db = db();

        // Fetch cron metrics (last 7 days)
        $metricsQuery = "
            SELECT
                task_name,
                COUNT(*) as total_runs,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_runs,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_runs,
                ROUND(AVG(execution_time), 2) as avg_execution_time,
                ROUND(MAX(execution_time), 2) as max_execution_time,
                ROUND(AVG(peak_memory / 1024 / 1024), 1) as avg_memory_mb,
                MAX(created_at) as last_run
            FROM flagged_products_cron_metrics
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY task_name
            ORDER BY task_name
        ";

        $result = $db->query($metricsQuery);
        $metrics = [];
        if ($result) {
            $metrics = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }

        // Fetch recent executions
        $recentQuery = "
            SELECT
                task_name,
                success,
                ROUND(execution_time, 2) as execution_time,
                ROUND(peak_memory / 1024 / 1024, 1) as memory_mb,
                created_at
            FROM flagged_products_cron_metrics
            ORDER BY created_at DESC
            LIMIT 20
        ";

        $result = $db->query($recentQuery);
        $recentExecutions = [];
        if ($result) {
            $recentExecutions = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }

        // Fetch performance view data
        $performanceQuery = "SELECT * FROM vw_flagged_products_cron_performance";
        $result = $db->query($performanceQuery);
        $performanceData = [];
        if ($result) {
            $performanceData = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }

        // Fetch health status
        $healthQuery = "SELECT * FROM vw_flagged_products_cron_health";
        $result = $db->query($healthQuery);
        $healthData = [];
        if ($result) {
            $healthData = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }

        // Calculate system health score
        $totalRuns = array_sum(array_column($metrics, 'total_runs'));
        $totalSuccess = array_sum(array_column($metrics, 'successful_runs'));
        $healthScore = $totalRuns > 0 ? round(($totalSuccess / $totalRuns) * 100, 1) : 0;

        // Render cron dashboard view
        $this->render('cron-dashboard', [
            'config' => $this->config,
            'metrics' => $metrics,
            'recentExecutions' => $recentExecutions,
            'performanceData' => $performanceData,
            'healthData' => $healthData,
            'totalRuns' => $totalRuns,
            'totalSuccess' => $totalSuccess,
            'healthScore' => $healthScore,
        ]);
    }

    /**
     * Render a view
     */
    private function render(string $view, array $data = []): void
    {
        extract($data);
        $viewFile = FLAGGED_PRODUCTS_MODULE_PATH . '/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            die("View not found: {$view}");
        }

        require $viewFile;
    }
}
