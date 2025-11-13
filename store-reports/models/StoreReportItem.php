<?php
declare(strict_types=1);
class StoreReportItem extends AbstractModel {
    protected $table = 'store_report_items';

    public function create(array $data): int {
        $req = ['report_id','checklist_id'];
        foreach ($req as $r) { if (empty($data[$r])) throw new InvalidArgumentException("Missing $r"); }
        $clean = [
            'report_id' => (int)$data['report_id'],
            'checklist_id' => (int)$data['checklist_id'],
            'response_value' => isset($data['response_value']) ? (int)$data['response_value'] : null,
            'response_text' => substr($data['response_text'] ?? '',0,2000),
            'is_na' => !empty($data['is_na']) ? 1 : 0,
            'max_points' => (int)($data['max_points'] ?? 4),
            'points_earned' => (float)($data['points_earned'] ?? 0),
            'weight' => (float)($data['weight'] ?? 1.0)
        ];
        return $this->insert($clean);
    }
}
?>
