<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

final class VendApiTesterController
{
    private Application $app;
    public function __construct(Application $app) { $this->app = $app; }

    public function index(Request $request): Response
    {
        $html = '<h1>Vend API Tester</h1><p>Skeleton page – implement endpoint selector & history.</p>';
        return Response::html($html);
    }

    public function call(Request $request): Response
    {
        $endpoint = (string)$request->input('endpoint', '');
        if ($endpoint === '') { return Response::error('endpoint required', 422); }
        return Response::success([
            'endpoint' => $endpoint,
            'status' => 'dry-run'
        ], 'Vend API call placeholder');
    }
}
