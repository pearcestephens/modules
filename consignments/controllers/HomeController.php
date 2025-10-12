<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

use Modules\Base\Controller\PageController;

final class HomeController extends PageController
{
    public function __construct()
    {
        parent::__construct();
        $this->layout = dirname(__DIR__, 2) . '/_base/views/layouts/cis-template-bare.php';
    }

    public function index(): string
    {
        return $this->renderView('home/index.php', [
            'title' => 'Transfers Home',
        ]);
    }
}
