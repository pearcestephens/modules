<?php
declare(strict_types=1);

namespace Modules\Consignments\Transfers\controllers;

use Modules\Consignments\Transfers\lib\Controller\BaseController;

class HubController extends BaseController
{
    public function index(): string
    {
        // Minimal data; heavy data can be loaded via AJAX later
        $data = [
            'page_title' => 'Consignment Hub',
            'page_blurb' => 'Stock transfer and consignment control center',
        ];
        return $this->renderView('hub/index.php', $data);
    }
}
