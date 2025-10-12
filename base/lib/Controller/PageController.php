<?php
declare(strict_types=1);

namespace Modules\Base\Controller;

use Modules\Base\View;

abstract class PageController extends BaseController
{
    protected string $layout;

    public function __construct()
    {
        parent::__construct();
        // Use the master template that includes real CIS components
        $this->layout = __DIR__ . '/../../views/layouts/master.php';
    }

    protected function view(string $absViewPath, array $data = []): string
    {
        // Capture content section
        $content = $this->view->render($absViewPath, $data);
        $data['content'] = $content;
        $data['view'] = $this->view;
        return $this->view->render($this->layout, $data);
    }
}
