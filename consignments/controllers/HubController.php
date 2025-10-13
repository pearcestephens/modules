<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

use Modules\Base\Controller\PageController;
use Transfers\Lib\Db;

final class HubController extends PageController
{
    public function __construct()
    {
        parent::__construct();
        // Use the new master layout with partials
        $this->layout = dirname(__DIR__, 2) . '/base/views/layouts/master.php';
    }

    private function getTransferStats(): array
    {
        try {
            $pdo = Db::pdo();
            
            // Get basic stats for dashboard
            $totalTransfers = $pdo->query('SELECT COUNT(*) FROM transfers')->fetchColumn();
            $pendingTransfers = $pdo->query('SELECT COUNT(*) FROM transfers WHERE status = "pending"')->fetchColumn();
            $completedToday = $pdo->query('SELECT COUNT(*) FROM transfers WHERE DATE(created_at) = CURDATE()')->fetchColumn();
            
            return [
                'totalTransfers' => (int)$totalTransfers,
                'pendingTransfers' => (int)$pendingTransfers,
                'completedToday' => (int)$completedToday,
            ];
        } catch (\Throwable) {
            return [
                'totalTransfers' => 0,
                'pendingTransfers' => 0,
                'completedToday' => 0,
            ];
        }
    }

    public function index(): string
    {
        $stats = $this->getTransferStats();
        
        $data = [
            'page_title' => 'Consignment Hub',
            'page_blurb' => 'Stock transfer and consignment control center',
            'page_id' => 'consignments_hub',
            'bodyClass' => 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show vs-hub vs-dashboard',
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Consignments', 'href' => '/modules/consignments/'],
                ['label' => 'Hub', 'active' => true],
            ],
            'moduleCSS' => ['/modules/consignments/assets/css/hub.css'],
            'moduleJS' => ['/modules/consignments/assets/js/hub.bundle.js'],
            // Hub-specific dashboard data
            'stats' => $stats,
        ];
        
        return $this->view(dirname(__DIR__) . '/views/hub/index.php', $data);
    }
}
