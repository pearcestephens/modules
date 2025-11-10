<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

final class TrafficSourcesController
{
    private Application $app;
    public function __construct(Application $app) { $this->app = $app; }

    public function map(Request $request): Response
    {
        return Response::success([
            'geo_sources' => [],
            'message' => 'Geo map placeholder'
        ]);
    }

    public function browsers(Request $request): Response
    {
        return Response::success([
            'browsers' => [],
            'message' => 'Browser distribution placeholder'
        ]);
    }

    public function bots(Request $request): Response
    {
        return Response::success([
            'bots' => [],
            'message' => 'Bot detection placeholder'
        ]);
    }
}
