<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

final class PerformanceController
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function dashboard(Request $request): Response
    {
        return Response::success([
            'message' => 'Performance dashboard skeleton',
            'db_version' => $this->app->database()->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME),
        ]);
    }

    public function queries(Request $request): Response
    {
        // Placeholder slow query list (to be replaced with real log parsing)
        return Response::success([
            'slow_queries' => [],
            'message' => 'Slow query list placeholder'
        ]);
    }

    public function explain(Request $request): Response
    {
        $sql = trim((string)$request->input('sql', ''));
        if ($sql === '') {
            return Response::error('SQL required for explain', 422);
        }
        $pdo = $this->app->database()->pdo();
        try {
            $stmt = $pdo->query('EXPLAIN ' . $sql);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return Response::success(['explain' => $rows]);
        } catch (\Throwable $e) {
            return Response::error('Explain failed: ' . $e->getMessage(), 400);
        }
    }
}
