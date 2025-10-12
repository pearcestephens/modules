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
        // Force every inheriting controller to render through the bare CIS template
        $this->layout = __DIR__ . '/../../views/layouts/cis-template-bare.php';
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
