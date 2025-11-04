<?php
/**
 * Transfer Manager Controller
 *
 * Main interface for managing stock transfers.
 * Shows dashboard with transfers list, stats, and quick actions.
 *
 * @package CIS\Consignments\Controllers
 */

declare(strict_types=1);

namespace Consignments\Controllers;

class TransferManagerController extends BaseController
{
    /**
     * Main dashboard view
     */
    public function index(): void
    {
        // Load transfer statistics
        $stats = $this->getTransferStats();

        // Load outlets for filters
        $outlets = $this->getOutlets();

        // Load suppliers for filters
        $suppliers = $this->getSuppliers();

        // Get sync status
        $syncStatus = $this->getSyncStatus();

        // Render dashboard using base template
        $this->render('dashboard', 'transfer-manager/index', [
            'pageTitle' => 'Transfer Manager',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
                ['label' => 'Consignments', 'url' => $this->viewData['moduleUrl']],
                ['label' => 'Transfer Manager', 'active' => true],
            ],
            'pageCSS' => [
                '/modules/consignments/assets/css/transfer-manager.css',
            ],
            'pageJS' => [
                '/modules/consignments/assets/js/transfer-manager.js',
            ],
            'stats' => $stats,
            'outlets' => $outlets,
            'suppliers' => $suppliers,
            'syncStatus' => $syncStatus,
        ]);
    }

    /**
     * Get transfer statistics
     */
    private function getTransferStats(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total_transfers,
                SUM(CASE WHEN state = 'OPEN' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN state = 'SENT' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN state = 'RECEIVING' THEN 1 ELSE 0 END) as receiving,
                SUM(CASE WHEN state = 'RECEIVED' THEN 1 ELSE 0 END) as received,
                SUM(CASE WHEN state = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled,
                SUM(total_count) as total_items,
                SUM(total_cost) as total_value
            FROM vend_consignments
            WHERE transfer_category = 'STOCK_TRANSFER'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_transfers' => 0,
            'open' => 0,
            'sent' => 0,
            'receiving' => 0,
            'received' => 0,
            'cancelled' => 0,
            'total_items' => 0,
            'total_value' => 0,
        ];
    }

    /**
     * Get outlets list
     */
    private function getOutlets(): array
    {
        $stmt = $this->db->query("
            SELECT id,
                   COALESCE(NULLIF(name,''), NULLIF(store_code,''), id) as label,
                   physical_city
            FROM vend_outlets
            WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'
            ORDER BY label ASC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get suppliers list
     */
    private function getSuppliers(): array
    {
        $stmt = $this->db->query("
            SELECT id, name
            FROM vend_suppliers
            WHERE deleted_at IS NULL OR deleted_at = ''
            ORDER BY name ASC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get Lightspeed sync status
     */
    private function getSyncStatus(): array
    {
        // Check if sync is enabled
        $syncFile = dirname(__DIR__) . '/TransferManager/.sync_enabled';
        $syncEnabled = true;
        if (file_exists($syncFile)) {
            $syncEnabled = (trim(file_get_contents($syncFile)) === '1');
        }

        // Get queue stats
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                MAX(completed_at) as last_sync
            FROM vend_consignment_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");

        $queueStats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_jobs' => 0,
            'completed' => 0,
            'failed' => 0,
            'processing' => 0,
            'last_sync' => null,
        ];

        // Calculate sync age
        $syncAgeMinutes = null;
        $syncStatus = 'unknown';

        if ($queueStats['last_sync']) {
            $lastSyncTime = strtotime($queueStats['last_sync']);
            $syncAgeMinutes = (int)((time() - $lastSyncTime) / 60);

            if ($syncAgeMinutes < 15) {
                $syncStatus = 'healthy';
            } elseif ($syncAgeMinutes < 60) {
                $syncStatus = 'warning';
            } else {
                $syncStatus = 'stale';
            }
        }

        return [
            'enabled' => $syncEnabled,
            'status' => $syncStatus,
            'last_sync' => $queueStats['last_sync'],
            'age_minutes' => $syncAgeMinutes,
            'queue' => $queueStats,
        ];
    }
}
