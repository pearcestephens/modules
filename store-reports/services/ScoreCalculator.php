<?php
class ScoreCalculator {
    public static function compute(array $imageScores, array $itemPoints): array {
        // Aggregate AI image scores (average of overall)
        $overallImage = null;
        if ($imageScores) {
            $sum = 0; $count = 0;
            foreach ($imageScores as $s) { if (isset($s['overall'])) { $sum += (float)$s['overall']; $count++; } }
            $overallImage = $count ? round($sum / $count, 2) : null;
        }
        // Checklist points
        $totalEarned = 0; $totalMax = 0;
        foreach ($itemPoints as $row) {
            $totalEarned += (float)($row['points_earned'] ?? 0);
            $totalMax += (float)($row['max_points'] ?? 0);
        }
        $checklistScore = $totalMax > 0 ? round(($totalEarned / $totalMax) * 100, 2) : null;
        // Combine (simple weighting: 70% AI, 30% checklist if both present)
        $combined = null;
        if ($overallImage !== null && $checklistScore !== null) {
            $combined = round(($overallImage * 0.7) + ($checklistScore * 0.3), 2);
        } elseif ($overallImage !== null) { $combined = $overallImage; }
        elseif ($checklistScore !== null) { $combined = $checklistScore; }
        return [
            'ai_score' => $overallImage,
            'checklist_score' => $checklistScore,
            'overall' => $combined
        ];
    }
}
?>
