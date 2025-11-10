<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

final class WebhookLabController
{
    private Application $app;
    public function __construct(Application $app) { $this->app = $app; }

    public function index(Request $request): Response
    {
        $html = '<h1>Webhook Test Lab</h1><p>Skeleton page – build UI with editor & history.</p>';
        return Response::html($html);
    }

    public function send(Request $request): Response
    {
        $target = (string)$request->input('target', '');
        $payload = (string)$request->input('payload', '{}');
        if ($target === '') { return Response::error('target required', 422); }
        // For safety, do not perform external HTTP yet – placeholder only
        return Response::success([
            'target' => $target,
            'payload' => $payload,
            'status' => 'dry-run'
        ], 'Webhook send placeholder');
    }
}
