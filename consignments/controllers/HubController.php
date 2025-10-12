<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

use Modules\Base\Controller\PageController;

class HubController extends PageController
{
    public function __construct()
    {
        parent::__construct();
        $this->layout = dirname(__DIR__, 2) . '/_base/views/layouts/cis-template-bare.php';
    }

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
