<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

final class QueueTesterController
{
    private Application $app;
    public function __construct(Application $app) { $this->app = $app; }

    public function index(Request $request): Response
    {
        return Response::success([
            'jobs' => [],
            'message' => 'Queue tester skeleton'
        ]);
    }

    public function dispatch(Request $request): Response
    {
        $type = (string)$request->input('type', 'demo');
        return Response::success(['dispatched' => true, 'type' => $type]);
    }

    public function cancel(Request $request): Response
    {
        $id = (string)$request->input('id', '');
        if ($id === '') { return Response::error('id required', 422); }
        return Response::success(['cancelled' => true, 'id' => $id]);
    }
}
