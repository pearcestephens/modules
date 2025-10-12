<?php
declare(strict_types=1);

namespace Modules\Consignments\Transfers\controllers;

use Modules\Consignments\Transfers\lib\Controller\BaseController;

final class HomeController extends BaseController
{
    public function index(): string
    {
        return $this->renderView('home/index.php', [
            'title' => 'Transfers Home',
        ]);
    }
}
