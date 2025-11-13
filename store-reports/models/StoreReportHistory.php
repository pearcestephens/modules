<?php
declare(strict_types=1);
class StoreReportHistory extends AbstractModel {
    protected $table = 'store_report_history';

    public function log(int $reportId, string $action, array $meta = []): int {
        $data = [
            'report_id' => $reportId,
            'action_type' => $action,
            'field_changed' => $meta['field'] ?? null,
            'old_value' => $meta['old'] ?? null,
            'new_value' => $meta['new'] ?? null,
            'description' => substr($meta['description'] ?? '',0,1000),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,500),
            'user_id' => $meta['user_id'] ?? (function_exists('current_user_id') ? current_user_id() : null)
        ];
        return $this->insert($data);
    }
}
?>
