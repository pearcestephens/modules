<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

use Modules\Base\Controller\PageController;

final class HomeController extends PageController
{
    public function index(): string
    {
        return $this->view(dirname(__DIR__) . '/views/home/index.php', [
            'title' => 'Transfers Home',
        ]);
    }
}
