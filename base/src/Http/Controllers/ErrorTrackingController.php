<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

final class ErrorTrackingController
{
    private Application $app;
    public function __construct(Application $app) { $this->app = $app; }

    public function notFound(Request $request): Response
    {
        $pdo = $this->app->database()->pdo();
        $list = [];
        try {
            $stmt = $pdo->query("SELECT endpoint, COUNT(*) as hits FROM web_traffic_errors WHERE error_code=404 GROUP BY endpoint ORDER BY hits DESC LIMIT 50");
            $list = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { /* ignore */ }
        return Response::success(['top_404s' => $list]);
    }

    public function serverErrors(Request $request): Response
    {
        $pdo = $this->app->database()->pdo();
        $list = [];
        try {
            $stmt = $pdo->query("SELECT endpoint, COUNT(*) as hits FROM web_traffic_errors WHERE error_code=500 GROUP BY endpoint ORDER BY hits DESC LIMIT 50");
            $list = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { /* ignore */ }
        return Response::success(['top_500s' => $list]);
    }

    public function createRedirect(Request $request): Response
    {
        $from = trim((string)$request->input('from', ''));
        $to   = trim((string)$request->input('to', ''));
        $code = (int)$request->input('code', '301');
        if ($from === '' || $to === '') {
            return Response::error('from and to required', 422);
        }
        $pdo = $this->app->database()->pdo();
        try {
            $stmt = $pdo->prepare("INSERT INTO web_traffic_redirects (from_path, to_path, status_code, is_active, created_at) VALUES (?,?,?,?,NOW())");
            $stmt->execute([$from, $to, $code, 1]);
            return Response::success(['created' => true]);
        } catch (\Throwable $e) {
            return Response::error('Insert failed: ' . $e->getMessage(), 500);
        }
    }
}
