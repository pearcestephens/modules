<?php
declare(strict_types=1);
class StoreReportImage extends AbstractModel {
    protected $table = 'store_report_images';

    public function create(array $data): int {
        $req = ['report_id','filename','file_path','uploaded_by_user'];
        foreach ($req as $r) { if (empty($data[$r])) throw new InvalidArgumentException("Missing $r"); }
        $clean = [
            'report_id' => (int)$data['report_id'],
            'filename' => substr($data['filename'],0,255),
            'file_path' => substr($data['file_path'],0,500),
            'uploaded_by_user' => (int)$data['uploaded_by_user'],
            'caption' => substr($data['caption'] ?? '',0,1000),
            'location_in_store' => substr($data['location_in_store'] ?? '',0,255),
            'status' => 'uploaded'
        ];
        return $this->insert($clean);
    }
}
?>
