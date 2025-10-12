<?php
declare(strict_types=1);

namespace Modules\Consignments\Transfers\controllers;

use Modules\Consignments\Transfers\lib\Controller\BaseController;
use Modules\Consignments\Transfers\lib\Db;

final class ReceiveController extends BaseController
{
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

                $i = $pdo->prepare('SELECT * FROM transfer_items WHERE transfer_id = ? ORDER BY id');
                $i->execute([$transferId]);
                $items = $i->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Throwable) {
            // Leave as defaults
        }

        $pageTitle = $transferId > 0 ? ('Receive Transfer #' . (string)$transferId) : 'Receive';
        $pageBlurb = 'Total transfers: ' . (string)$count;
        $breadcrumbs = [
            ['label' => 'Transfers', 'href' => \Modules\Shared\Helpers::url('/transfers')],
            ['label' => $pageTitle, 'active' => true],
        ];
        return $this->renderView('receive/full.php', [
            'title' => 'Receive',
            'page_title' => $pageTitle,
            'page_blurb' => $pageBlurb,
            'page_id' => 'consignments_receive',
            'breadcrumbs' => $breadcrumbs,
            'transferCount' => $count,
            'transferId' => $transferId,
            'transfer' => $transfer,
            'items' => $items,
        ]);
    }
}
