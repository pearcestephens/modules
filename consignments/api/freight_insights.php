<?php
declare(strict_types=1);
/**
 * Freight insights for a transfer (DB-first; manual mode)
 * Input: POST JSON { transfer_id:int, lines?: [[product_id, qty], ...] }
 * Output: { success, insights:{ total_weight_g, db_cost, pick:{...}, coverage:{P,C,D} }, warnings:[] }
 * Uses CisFreight if available; otherwise graceful DB fallbacks.
 */

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method Not Allowed']); exit; }
    require_once dirname(__DIR__) . '/bootstrap.php';

    $j = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
    $tid = (int)($j['transfer_id'] ?? 0);
    if ($tid <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid transfer_id']); exit; }

    $pdo = cis_resolve_pdo();

    // Lines: prefer client snapshot for immediacy; else from DB (counted or planned)
    $lines = [];
    if (isset($j['lines']) && is_array($j['lines'])) {
        foreach ($j['lines'] as $ln) {
            if (!is_array($ln) || count($ln) < 2) continue;
            $pid = (string)$ln[0]; $q = max(0, (int)$ln[1]);
            if ($pid !== '' && $q > 0) $lines[] = [$pid, $q];
        }
    }
    if (!$lines) {
        $g = $pdo->prepare("SELECT product_id, GREATEST(COALESCE(qty_sent_total,0), qty_requested) AS q
                            FROM transfer_items WHERE transfer_id=?");
        $g->execute([$tid]);
        foreach ($g->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $q = (int)$row['q']; if ($q > 0) $lines[] = [(string)$row['product_id'], $q];
        }
    }

    // Resolve coverage P/C/D for the involved products
    $coverage = ['P'=>0,'C'=>0,'D'=>0];
    if ($lines) {
        $in = implode(',', array_fill(0, count($lines), '?'));
        $ids = array_map(fn($ln)=> (string)$ln[0], $lines);
        $sql = "SELECT vp.id,
                       vp.avg_weight_grams AS pw,
                       cw.avg_weight_grams AS cw
                  FROM vend_products vp
             LEFT JOIN product_classification_unified pcu ON pcu.product_id = vp.id
             LEFT JOIN category_weights cw               ON cw.category_id = pcu.category_id
                 WHERE vp.id IN ($in)";
        $st = $pdo->prepare($sql); $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!is_null($r['pw'])) $coverage['P']++;
            elseif (!is_null($r['cw'])) $coverage['C']++;
            else $coverage['D']++;
        }
    }

    // Try CisFreight if available
    $insights = ['coverage'=>$coverage];
    $warnings = [];

    $cfPath = dirname(__DIR__).'/lib/CisFreight.php';
    $hasCF = is_file($cfPath);
    if ($hasCF) { require_once $cfPath; if (class_exists('CisFreight')) { CisFreight::initFromEnv($pdo); } else { $hasCF=false; } }

    // Compute total weight + DB pick cost
    if ($hasCF) {
        $totalG = CisFreight::totalWeightG($lines);
        $insights['total_weight_g'] = $totalG;

        // prefer NZ Post carrier=1 as default; adjust via env if needed
        $carrierId = (int)(getenv('DEFAULT_DB_CARRIER_ID') ?: 1);
        $db = CisFreight::priceCartConsolidated($lines, $carrierId);
        $insights['db_cost'] = isset($db['cost']) ? (float)$db['cost'] : null;

        // Dimensions & dynamic pick (if dimension data exists, else weight-only)
        $dims = CisFreight::calculateTransferDimensions($lines);
        $insights['dimensions'] = ['has_all_dimensions'=>$dims['has_all_dimensions']];
        $pick = CisFreight::pickContainerDynamic($carrierId, (int)$db['total_weight_g'], (int)($dims['total_volume_cm3'] ?? 0),
                                                 (int)($dims['bounding_box']['max_length_mm'] ?? 0),
                                                 (int)($dims['bounding_box']['max_width_mm'] ?? 0),
                                                 (int)($dims['bounding_box']['max_height_mm'] ?? 0));
        if (isset($pick['success']) && $pick['success']) {
            $p = $pick['container'];
            $insights['pick'] = [
                'container_id'   => $p['container_id'] ?? null,
                'container_name' => $p['container_name'] ?? ($p['container_code'] ?? null),
                'container_code' => $p['container_code'] ?? null,
                'utilization_pct'=> $pick['fit_analysis']['utilization_pct'] ?? null,
                'cost_per_kg'    => $pick['fit_analysis']['cost_per_kg'] ?? null
            ];
        } elseif (!empty($pick['user_message'])) {
            $warnings[] = $pick['user_message'];
        }

        // Health flags (if views exist)
        foreach (['v_missing_container_rules','v_zero_or_null_prices'] as $v) {
            $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='VIEW' AND table_name=?");
            $q->execute([$v]);
            if ((int)$q->fetchColumn() > 0) {
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM $v")->fetchColumn();
                if ($cnt > 0) $warnings[] = "$v has $cnt rows";
            }
        }
    } else {
        // Lightweight fallback: simple total weight (500g fallback)
        $sql = "SELECT COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, 500) AS unit_g
                  FROM vend_products vp
             LEFT JOIN product_classification_unified pcu ON pcu.product_id=vp.id
             LEFT JOIN category_weights cw               ON cw.category_id=pcu.category_id
                 WHERE vp.id=:p LIMIT 1";
        $st = $pdo->prepare($sql); $sum=0;
        foreach ($lines as $ln) { $st->execute([':p'=>$ln[0]]); $u = (int)($st->fetchColumn() ?: 500); $sum += $u * (int)$ln[1]; }
        $insights['total_weight_g'] = $sum;
        $warnings[] = 'CisFreight class not installed — running fallback weight only.';
    }

    echo json_encode(['success'=>true, 'insights'=>$insights, 'warnings'=>$warnings]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
