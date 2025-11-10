<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

/**
 * Traffic monitoring controller (Section 11 skeleton)
 */
final class TrafficController
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /** Dashboard HTML shell */
    public function monitor(Request $request): Response
    {
        $html = '<h1>Traffic Monitor</h1><p>Skeleton page. Live metrics forthcoming.</p>';
        return Response::html($html);
    }

    /** SSE live feed placeholder */
    public function live(Request $request): Response
    {
        // For now, return static JSON; later will stream with text/event-stream
        return Response::success([
            'events' => [],
            'message' => 'SSE stream placeholder – implement real-time emitter.'
        ]);
    }

    /** Aggregated stats */
    public function stats(Request $request): Response
    {
        $pdo = $this->app->database()->pdo();
        $out = [
            'visitor_count_last_5m' => 0,
            'requests_per_second' => 0,
            'error_404_last_hour' => 0,
            'error_500_last_hour' => 0,
        ];
        try {
            // Basic counts if web_traffic_errors exists
            $tables = $pdo->query("SHOW TABLES LIKE 'web_traffic_errors'")->fetchAll();
            if (!empty($tables)) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM web_traffic_errors WHERE error_code=404 AND timestamp >= NOW() - INTERVAL 1 HOUR");
                $out['error_404_last_hour'] = (int)$stmt->fetchColumn();
                $stmt = $pdo->query("SELECT COUNT(*) FROM web_traffic_errors WHERE error_code=500 AND timestamp >= NOW() - INTERVAL 1 HOUR");
                $out['error_500_last_hour'] = (int)$stmt->fetchColumn();
            }
        } catch (\Throwable $e) {
            $out['warning'] = 'Stats query failed: ' . $e->getMessage();
        }
        return Response::success($out);
    }
}
