<?php
declare(strict_types=1);
class StoreReportChecklist extends AbstractModel {
    protected $table = 'store_report_checklist';

    public function create(array $data): int {
        $req = ['category','name','title'];
        foreach ($req as $r) { if (empty($data[$r])) throw new InvalidArgumentException("Missing $r"); }
        $clean = [
            'category' => substr($data['category'],0,100),
            'name' => substr($data['name'],0,255),
            'title' => substr($data['title'],0,500),
            'question_type' => $data['question_type'] ?? 'rating',
            'max_points' => (int)($data['max_points'] ?? 4),
            'weight' => (float)($data['weight'] ?? 1.0),
            'is_critical' => !empty($data['is_critical']) ? 1 : 0,
            'counts_toward_grade' => empty($data['counts_toward_grade']) ? 1 : 0,
            'ai_analysis_enabled' => empty($data['ai_analysis_enabled']) ? 1 : 0,
            'photo_required' => !empty($data['photo_required']) ? 1 : 0,
            'display_order' => (int)($data['display_order'] ?? 0)
        ];
        return $this->insert($clean);
    }
}
?>
