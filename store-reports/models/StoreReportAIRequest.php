<?php
declare(strict_types=1);
class StoreReportAIRequest extends AbstractModel {
    protected $table = 'store_report_ai_requests';

    public function create(array $data): int {
        $req = ['report_id','request_title','request_description'];
        foreach ($req as $r) { if (empty($data[$r])) throw new InvalidArgumentException("Missing $r"); }
        $clean = [
            'report_id' => (int)$data['report_id'],
            'request_title' => substr($data['request_title'],0,500),
            'request_description' => substr($data['request_description'],0,2000),
            'priority' => $data['priority'] ?? 'medium',
            'request_type' => $data['request_type'] ?? 'clarification',
            'status' => 'pending'
        ];
        return $this->insert($clean);
    }
}
?>
