<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

use Modules\Base\Controller\PageController;
use Modules\Base\Helpers;
use Modules\Consignments\Transfers\lib\Db;

final class PackController extends PageController
{
    private function countTransfers(): int
    {
        try {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM transfers');
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function index(): string
    {
        $count = $this->countTransfers();
        // Resolve transfer id from query (?transfer= preferred; legacy ?id= supported)
        $transferId = 0;
        if (isset($_GET['transfer'])) { $transferId = (int)$_GET['transfer']; }
        elseif (isset($_GET['id'])) { $transferId = (int)$_GET['id']; }

        // Try load transfer + items; fail safe to empty
        $transfer = null;
        $items = [];
        try {
            $pdo = Db::pdo();
            if ($transferId > 0) {
                $t = $pdo->prepare('SELECT * FROM transfers WHERE id = ?');
                $t->execute([$transferId]);
                $transfer = $t->fetch(\PDO::FETCH_ASSOC) ?: null;

                // Minimal compatible shape; adjust to your schema if needed
                $i = $pdo->prepare('SELECT * FROM transfer_items WHERE transfer_id = ? ORDER BY id');
                $i->execute([$transferId]);
                $items = $i->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Throwable) {
            // Leave $transfer as null and $items empty
        }

        // Render the full pack page under CIS template (previous behavior)
        $pageTitle = $transferId > 0 ? ('Pack Transfer #' . (string)$transferId) : 'Pack';
        $pageBlurb = 'Total transfers: ' . (string)$count;
        $breadcrumbs = [
            ['label' => 'Transfers', 'href' => Helpers::url('/transfers')],
            ['label' => $pageTitle, 'active' => true],
        ];
        return $this->view(dirname(__DIR__) . '/views/pack/full.php', [
            'title' => 'Pack',
            'page_title' => $pageTitle,
            'page_blurb' => $pageBlurb,
            'page_id' => 'consignments_pack',
            'breadcrumbs' => $breadcrumbs,
            'transferCount' => $count,
            'transferId' => $transferId,
            'transfer' => $transfer,
            'items' => $items,
        ]);
    }
}
