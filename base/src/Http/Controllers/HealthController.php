<?php

declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

class HealthController
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function ping(Request $request): Response
    {
        return Response::success(['status' => 'ok', 'timestamp' => time()]);
    }

    public function check(Request $request): Response
    {
        return Response::success(['status' => 'healthy', 'timestamp' => time()]);
    }
}
