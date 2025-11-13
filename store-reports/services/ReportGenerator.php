<?php
class ReportGenerator {
    public static function executiveSummary(array $report, array $images): string {
        $score = $report['overall_score'] ?? $report['ai_score'] ?? 'N/A';
        $issues = [];
        foreach ($images as $img) {
            $detected = json_decode($img['ai_detected_issues'] ?? '[]', true);
            if ($detected) { $issues = array_merge($issues, $detected); }
        }
        $issues = array_unique($issues);
        $top = implode(', ', array_slice($issues, 0, 5));
        return "Store inspection completed. Overall score: {$score}. Key issues: {$top}";
    }
}
?>
