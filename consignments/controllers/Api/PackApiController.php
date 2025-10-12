<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers\Api;

use Modules\Base\Controller\ApiController;
use Transfers\Lib\Db;

final class PackApiController extends ApiController
{
    public function addLine(): array
    {
        // Determine if JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        $isJson = stripos((string)$contentType, 'application/json') !== false;

        if ($isJson) {
            $raw = (string)file_get_contents('php://input');
            $payload = json_decode($raw, true) ?: [];
        } else {
            // Validate CSRF for form posts
            $csrf = $_POST['csrf_token'] ?? '';
            if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$csrf)) {
                header('Location: /modules/consignments/transfers/pack?error=csrf');
                exit;
            }
            $payload = [
                'sku' => $_POST['sku'] ?? null,
                'qty' => $_POST['qty'] ?? null,
                'transfer' => $_POST['transfer'] ?? null,
            ];
        }

        [$ok, $errors] = $this->validate($payload, [
            'sku' => 'required',
            'qty' => 'required|int|min:1',
        ]);
        if (!$ok) {
            if (!$isJson) {
                header('Location: /modules/consignments/transfers/pack?error=validation');
                exit;
            }
            return $this->fail('Validation failed', $errors);
        }

    $transferId = 0;
    if (isset($_GET['transfer'])) { $transferId = (int)$_GET['transfer']; }
    elseif (isset($payload['transfer'])) { $transferId = (int)$payload['transfer']; }
    elseif (isset($_GET['id'])) { $transferId = (int)$_GET['id']; }

        try {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare('INSERT INTO transfer_items (transfer_id, sku, qty) VALUES (:tid, :sku, :qty)');
            $stmt->execute([
                ':tid' => $transferId,
                ':sku' => (string)$payload['sku'],
                ':qty' => (int)$payload['qty'],
            ]);
        } catch (\Throwable $e) {
            if (!$isJson) {
                $suffix = $transferId ? ('&transfer=' . (int)$transferId) : '';
                header('Location: /modules/consignments/transfers/pack?error=db' . $suffix);
                exit;
            }
            return $this->fail('DB insert failed');
        }

        if ($isJson) {
            return $this->ok();
        }
        // For form posts, redirect back to Pack page (reload UX)
    $suffix = $transferId ? ('&transfer=' . (int)$transferId) : '';
    header('Location: /modules/consignments/transfers/pack?success=1' . $suffix);
        exit;
    }
}
