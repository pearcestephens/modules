<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

final class SnippetLibraryController
{
    private Application $app;
    public function __construct(Application $app) { $this->app = $app; }

    public function index(Request $request): Response
    {
        return Response::success([
            'snippets' => [
                ['name' => 'cURL Transfer Fetch', 'language' => 'bash', 'code' => 'curl -s "?endpoint=api/transfers"'],
                ['name' => 'PHP API Call', 'language' => 'php', 'code' => '<?php /* placeholder */ ?>'],
            ],
            'message' => 'Snippet library skeleton'
        ]);
    }
}
