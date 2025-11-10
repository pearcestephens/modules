<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

final class ApiEndpointTesterController
{
    private Application $app;
    public function __construct(Application $app) { $this->app = $app; }

    public function index(Request $request): Response
    {
        return Response::success([
            'suites' => ['Transfer','PO','Inventory','Webhook'],
            'message' => 'API endpoint tester skeleton'
        ]);
    }

    public function runSuite(Request $request): Response
    {
        $suite = (string)$request->input('suite', '');
        if ($suite === '') { return Response::error('suite required', 422); }
        return Response::success([
            'suite' => $suite,
            'results' => [],
            'status' => 'dry-run'
        ], 'Suite run placeholder');
    }
}
