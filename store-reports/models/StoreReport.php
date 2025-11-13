<?php
class StoreReport extends AbstractModel {
    protected string $table = 'store_reports';

    public function create(array $input): int {
        $validated = $this->validateCreate($input);
        return $this->insert($validated);
    }

    private function validateCreate(array $data): array {
        $required = ['outlet_id','performed_by_user'];
        foreach ($required as $r) {
            if (empty($data[$r])) { throw new InvalidArgumentException("Missing required field $r"); }
        }
        $clean = [
            'outlet_id' => substr($data['outlet_id'],0,255),
            'performed_by_user' => (int)$data['performed_by_user'],
            'status' => 'draft',
            'report_date' => date('Y-m-d H:i:s')
        ];
        if (!empty($data['staff_notes'])) { $clean['staff_notes'] = substr($data['staff_notes'],0,2000); }
        return $clean;
    }

    public function finalize(int $id, array $scores, ?float $manual = null): bool {
        $overall = $scores['overall'] ?? null;
        if ($overall === null) throw new InvalidArgumentException('Overall score required');
        $grade = $this->gradeFromScore((float)$overall);
        return $this->update($id,[
            'overall_score' => $overall,
            'ai_score' => $scores['ai_score'] ?? $overall,
            'manual_score' => $manual,
            'grade' => $grade,
            'status' => 'completed'
        ]);
    }

    private function gradeFromScore(float $score): string {
        // Explicit descending ranges
        $ranges = [
            ['min'=>99,'max'=>100,'grade'=>'A+'],
            ['min'=>97,'max'=>98,'grade'=>'A'],
            ['min'=>95,'max'=>96,'grade'=>'A-'],
            ['min'=>93,'max'=>94,'grade'=>'B+'],
            ['min'=>91,'max'=>92,'grade'=>'B'],
            ['min'=>89,'max'=>90,'grade'=>'B-'],
            ['min'=>87,'max'=>88,'grade'=>'C+'],
            ['min'=>85,'max'=>86,'grade'=>'C'],
            ['min'=>83,'max'=>84,'grade'=>'C-'],
            ['min'=>81,'max'=>82,'grade'=>'D+'],
            ['min'=>79,'max'=>80,'grade'=>'D'],
            ['min'=>77,'max'=>78,'grade'=>'D-'],
            ['min'=>75,'max'=>76,'grade'=>'E']
        ];
        foreach ($ranges as $r) {
            if ($score >= $r['min'] && $score <= $r['max']) return $r['grade'];
        }
        return 'F';
    }
}
?>
