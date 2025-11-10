<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

final class LightspeedSyncController
{
    private Application $app;
    public function __construct(Application $app) { $this->app = $app; }

    public function index(Request $request): Response
    {
        $html = '<h1>Lightspeed Sync Tester</h1><p>Skeleton – add sync test buttons.</p>';
        return Response::html($html);
    }

    public function run(Request $request): Response
    {
        $mode = (string)$request->input('mode', 'full');
        return Response::success([
            'mode' => $mode,
            'status' => 'queued'
        ], 'Sync placeholder');
    }
}
