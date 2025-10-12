<?php
declare(strict_types=1);

namespace Modules\Base\Controller;

use Modules\Base\View;

abstract class BaseController
{
    protected View $view;

    public function __construct()
    {
        $this->view = new View();
    }
}
