<?php
declare(strict_types=1);

namespace Modules\Shared\Controller;

use Modules\Shared\View;

abstract class BaseController
{
    protected View $view;

    public function __construct()
    {
        $this->view = new View();
    }
}
