<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

use Modules\Base\Controller\PageController;

class HubController extends PageController
{
    public function index(): string
    {
        // Minimal data; heavy data can be loaded via AJAX later
        $data = [
            'page_title' => 'Consignment Hub',
            'page_blurb' => 'Stock transfer and consignment control center',
        ];
    return $this->view(dirname(__DIR__) . '/views/hub/index.php', $data);
    }
}
