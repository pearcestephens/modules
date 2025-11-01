<?php
declare(strict_types=1);
require_once __DIR__ . '/../../assets/functions/config.php';
require_once __DIR__ . '/../lib/ConsignmentService.php';

use Consignments\ConsignmentService;

header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }

$in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
$act = (string)($in['action'] ?? ''); $data = (array)($in['data'] ?? []);
$svc = ConsignmentService::make();

try {
  switch ($act) {
    case 'recent':  echo json_encode(['ok'=>true,'data'=>$svc->recent((int)($data['limit'] ?? 50))]); break;
    case 'get':     $id=(int)($data['id'] ?? 0); $row=$svc->get($id); $items=$svc->items($id);
                    echo json_encode(['ok'=>!!$row, 'data'=>['consignment'=>$row,'items'=>$items]]); break;
    case 'create':  $id=$svc->create($data); echo json_encode(['ok'=>true,'data'=>['id'=>$id]], JSON_UNESCAPED_SLASHES); break;
    case 'add_item':$cid=(int)($data['consignment_id']??0); $iid=$svc->addItem($cid,$data);
                    echo json_encode(['ok'=>true,'data'=>['id'=>$iid]]); break;
    case 'status':  $ok=$svc->setStatus((int)$data['id'], (string)$data['status']); echo json_encode(['ok'=>$ok]); break;
    default:        echo json_encode(['ok'=>false,'error'=>'unknown action']); http_response_code(400);
  }
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
