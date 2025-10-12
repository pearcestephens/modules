<?php
declare(strict_types=1);

namespace Modules\Shared\Controller;

use Modules\Shared\View;

abstract class PageController extends BaseController
{
    protected string $layout;

    public function __construct()
    {
    parent::__construct();
    $this->layout = __DIR__ . '/../../views/layouts/cis-template.php';
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
