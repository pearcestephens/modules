<?php
declare(strict_types=1);

namespace Modules\Consignments\Transfers\lib\Controller;

use Modules\Shared\Controller\PageController as SharedPageController;
use Modules\Consignments\Transfers\lib\Db;
use PDO;

abstract class BaseController extends SharedPageController
{
    public function __construct()
    {
        parent::__construct();
        // switch to bare layout to avoid double container/card
        // __DIR__ = modules/consignments/transfers/lib/Controller
        // dirname(__DIR__, 3) = modules/consignments
        $this->layout = dirname(__DIR__, 3) . '/_shared/views/layouts/cis-template-bare.php';
    }

    /** Resolve a view relative to transfers/views root and render with shared layout. */
    protected function renderView(string $relativePath, array $data = []): string
    {
        $abs = __DIR__ . '/../../views/' . ltrim($relativePath, '/');
        return $this->view($abs, $data);
    }

    /** Common small helper for count of transfers (safe fallback to 0 on error). */
    protected function countTransfers(): int
    {
        $count = 0;
        try {
            $pdo = Db::pdo();
            $stmt = $pdo->query('SELECT COUNT(*) AS c FROM transfers');
            if ($stmt !== false) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['c'])) {
                    $count = (int)$row['c'];
                }
            }
        } catch (\Throwable $e) {
            $count = 0;
        }
        return $count;
    }
}
