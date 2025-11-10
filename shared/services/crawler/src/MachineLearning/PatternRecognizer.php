<?php

declare(strict_types=1);
/**
 * PatternRecognizer - ML-Based Pattern Recognition & Anomaly Detection.
 *
 * Features:
 * - Isolation Forest for anomaly detection
 * - K-Means clustering for behavior grouping
 * - Time-series pattern matching
 * - Confidence scoring
 *
 * @version 2.0.0
 */

namespace CIS\SharedServices\Crawler\MachineLearning;

use Psr\Log\LoggerInterface;

use function count;
use function is_array;

use const PHP_FLOAT_MAX;

class PatternRecognizer
{
    private LoggerInterface $logger;

    private array $config;

    private array $trainingData = [];

    private array $models = [];

    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'anomaly_threshold' => 0.1,
            'min_samples'       => 50,
            'contamination'     => 0.1,
        ], $config);
    }

    /**
     * Detect bot signature in request pattern.
     */
    public function detectBotSignature(array|string $requestData): array
    {
        $data = is_string($requestData) ? ['user_agent' => $requestData] : $requestData;
        // Stub - analyze patterns for bot behavior
        return [
            'is_bot' => false,
            'confidence' => 0.0,
            'signatures' => []
        ];
    }

    /**
     * Detect bot by pattern matching.
     */
    public function detectBotByPattern(array $pattern): bool
    {
        // Stub - pattern-based bot detection
        return false;
    }

    /**
     * Detect anomalies using Isolation Forest algorithm.
     */
    public function detectAnomalies(array $data): array
    {
        if (count($data) < $this->config['min_samples']) {
            $this->logger->warning('Insufficient data for anomaly detection', [
                'samples'  => count($data),
                'required' => $this->config['min_samples'],
            ]);

            return [];
        }

        $scores = [];
        foreach ($data as $idx => $point) {
            $score     = $this->isolationScore($point, $data);
            $isAnomaly = $score > (1 - $this->config['contamination']);

            $scores[] = [
                'index'      => $idx,
                'data'       => $point,
                'score'      => round($score, 4),
                'is_anomaly' => $isAnomaly,
            ];
        }

        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);

        $anomalyCount = count(array_filter($scores, fn ($s) => $s['is_anomaly']));
        $this->logger->info('Anomaly detection complete', [
            'total_samples'      => count($data),
            'anomalies_detected' => $anomalyCount,
            'anomaly_rate'       => round(($anomalyCount / count($data)) * 100, 2),
        ]);

        return $scores;
    }

    /**
     * Cluster data points using K-Means.
     */
    public function cluster(array $data, int $k = 3): array
    {
        if (count($data) < $k) {
            return [];
        }

        // Initialize centroids randomly
        $centroids = array_rand($data, $k);
        $centroids = array_map(fn ($idx) => $data[$idx], $centroids);

        $maxIterations = 100;
        $iteration     = 0;

        do {
            $clusters = array_fill(0, $k, []);
            $changed  = false;

            // Assign points to nearest centroid
            foreach ($data as $idx => $point) {
                $nearestCluster              = $this->findNearestCentroid($point, $centroids);
                $clusters[$nearestCluster][] = ['index' => $idx, 'data' => $point];
            }

            // Update centroids
            $newCentroids = [];
            foreach ($clusters as $clusterIdx => $cluster) {
                if (empty($cluster)) {
                    $newCentroids[] = $centroids[$clusterIdx];

                    continue;
                }

                $newCentroid = $this->calculateCentroid($cluster);
                if ($this->distance($newCentroid, $centroids[$clusterIdx]) > 0.01) {
                    $changed = true;
                }
                $newCentroids[] = $newCentroid;
            }

            $centroids = $newCentroids;
            $iteration++;
        } while ($changed && $iteration < $maxIterations);

        $this->logger->info('K-Means clustering complete', [
            'k'             => $k,
            'iterations'    => $iteration,
            'cluster_sizes' => array_map('count', $clusters),
        ]);

        return [
            'clusters'   => $clusters,
            'centroids'  => $centroids,
            'iterations' => $iteration,
        ];
    }

    /**
     * Match patterns with similarity scoring.
     */
    public function matchPattern(array $pattern, array $candidates): array
    {
        $matches = [];

        foreach ($candidates as $idx => $candidate) {
            $similarity = $this->cosineSimilarity($pattern, $candidate);

            $matches[] = [
                'index'      => $idx,
                'candidate'  => $candidate,
                'similarity' => round($similarity, 4),
            ];
        }

        usort($matches, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return $matches;
    }

    /**
     * Train pattern recognition model.
     */
    public function train(array $trainingData): void
    {
        $this->trainingData = $trainingData;

        // Build statistical model
        $this->models['statistics'] = $this->calculateStatistics($trainingData);

        $this->logger->info('Pattern recognition model trained', [
            'samples'  => count($trainingData),
            'features' => count($trainingData[0] ?? []),
        ]);
    }

    /**
     * Predict if new data matches learned patterns.
     */
    public function predict(array $data): array
    {
        if (empty($this->trainingData)) {
            return ['prediction' => 'unknown', 'confidence' => 0.0];
        }

        $similarities = [];
        foreach ($this->trainingData as $trainingSample) {
            $similarities[] = $this->cosineSimilarity($data, $trainingSample);
        }

        $avgSimilarity = array_sum($similarities) / count($similarities);
        $maxSimilarity = max($similarities);

        return [
            'prediction'     => $maxSimilarity > 0.8 ? 'match' : 'no_match',
            'confidence'     => round($maxSimilarity, 4),
            'avg_similarity' => round($avgSimilarity, 4),
        ];
    }

    // ============================================================================
    // PRIVATE METHODS
    // ============================================================================

    private function isolationScore(array $point, array $dataset): float
    {
        $numTrees      = 10;
        $subsampleSize = min(256, count($dataset));
        $depths        = [];

        for ($i = 0; $i < $numTrees; $i++) {
            $subsample = $this->randomSubsample($dataset, $subsampleSize);
            $depth     = $this->isolationTreeDepth($point, $subsample, 0, log($subsampleSize, 2));
            $depths[]  = $depth;
        }

        $avgDepth = array_sum($depths) / count($depths);
        $c        = 2 * (log($subsampleSize - 1) + 0.5772156649) - (2 * ($subsampleSize - 1) / $subsampleSize);

        return 2 ** (-($avgDepth / $c));
    }

    private function isolationTreeDepth(array $point, array $data, int $currentDepth, float $maxDepth): float
    {
        if ($currentDepth >= $maxDepth || count($data) <= 1) {
            return $currentDepth;
        }

        $feature    = array_rand($point);
        $splitValue = $this->randomValue($data, $feature);

        $left  = array_filter($data, fn ($p) => ($p[$feature] ?? 0) < $splitValue);
        $right = array_filter($data, fn ($p) => ($p[$feature] ?? 0) >= $splitValue);

        if (($point[$feature] ?? 0) < $splitValue) {
            return $this->isolationTreeDepth($point, $left, $currentDepth + 1, $maxDepth);
        }

        return $this->isolationTreeDepth($point, $right, $currentDepth + 1, $maxDepth);
    }

    private function findNearestCentroid(array $point, array $centroids): int
    {
        $minDistance = PHP_FLOAT_MAX;
        $nearestIdx  = 0;

        foreach ($centroids as $idx => $centroid) {
            $dist = $this->distance($point, $centroid);
            if ($dist < $minDistance) {
                $minDistance = $dist;
                $nearestIdx  = $idx;
            }
        }

        return $nearestIdx;
    }

    private function calculateCentroid(array $cluster): array
    {
        $centroid = [];
        $count    = count($cluster);

        foreach ($cluster[0]['data'] as $key => $value) {
            $sum            = array_sum(array_column(array_column($cluster, 'data'), $key));
            $centroid[$key] = $sum / $count;
        }

        return $centroid;
    }

    private function distance(array $a, array $b): float
    {
        $sum = 0;
        foreach ($a as $key => $value) {
            $sum += ($value - ($b[$key] ?? 0)) ** 2;
        }

        return sqrt($sum);
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0;
        $magA       = 0;
        $magB       = 0;

        foreach ($a as $key => $value) {
            $dotProduct += $value * ($b[$key] ?? 0);
            $magA += $value * $value;
            $magB += ($b[$key] ?? 0) * ($b[$key] ?? 0);
        }

        if ($magA === 0 || $magB === 0) {
            return 0;
        }

        return $dotProduct / (sqrt($magA) * sqrt($magB));
    }

    private function calculateStatistics(array $data): array
    {
        $stats = [];

        if (empty($data)) {
            return $stats;
        }

        foreach ($data[0] as $key => $value) {
            $values      = array_column($data, $key);
            $stats[$key] = [
                'mean' => array_sum($values) / count($values),
                'min'  => min($values),
                'max'  => max($values),
                'std'  => $this->standardDeviation($values),
            ];
        }

        return $stats;
    }

    private function standardDeviation(array $values): float
    {
        $mean     = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / count($values);

        return sqrt($variance);
    }

    private function randomSubsample(array $data, int $size): array
    {
        $keys = array_rand($data, min($size, count($data)));
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        return array_intersect_key($data, array_flip($keys));
    }

    private function randomValue(array $data, string $feature): float
    {
        $values = array_column($data, $feature);

        return $values[array_rand($values)] ?? 0;
    }

    // Isolation Forest methods
    private function initIsolationForest(int $numTrees, int $subsampleSize): array
    {
        return [
            'num_trees' => $numTrees,
            'subsample_size' => $subsampleSize,
            'trees' => [],
        ];
    }

    private function trainIsolationForest(array $samples, int $numTrees = 100): array
    {
        $model = $this->initIsolationForest($numTrees, min(256, count($samples)));

        for ($i = 0; $i < $numTrees; $i++) {
            $subsample = $this->randomSubsample($samples, $model['subsample_size']);
            $model['trees'][] = $this->buildIsolationTree($subsample, 0, 10);
        }

        return $model;
    }

    private function buildIsolationTrees(array $samples, int $numTrees, int $maxDepth): array
    {
        $trees = [];
        for ($i = 0; $i < $numTrees; $i++) {
            $trees[] = $this->buildIsolationTree($samples, 0, $maxDepth);
        }
        return $trees;
    }

    private function buildIsolationTree(array $samples, int $depth, int $maxDepth): array
    {
        if ($depth >= $maxDepth || count($samples) <= 1) {
            return ['type' => 'leaf', 'size' => count($samples)];
        }

        $features = array_keys($samples[0] ?? []);
        if (empty($features)) {
            return ['type' => 'leaf', 'size' => count($samples)];
        }

        $splitFeature = $features[array_rand($features)];
        $splitValue = $this->randomValue($samples, $splitFeature);

        $left = array_filter($samples, fn($s) => ($s[$splitFeature] ?? 0) < $splitValue);
        $right = array_filter($samples, fn($s) => ($s[$splitFeature] ?? 0) >= $splitValue);

        return [
            'type' => 'node',
            'feature' => $splitFeature,
            'split_value' => $splitValue,
            'left' => $this->buildIsolationTree($left, $depth + 1, $maxDepth),
            'right' => $this->buildIsolationTree($right, $depth + 1, $maxDepth),
        ];
    }

    private function calculateAnomalyScore(array $sample, array $model): float
    {
        $avgPathLength = 0;
        foreach ($model['trees'] as $tree) {
            $avgPathLength += $this->pathLength($sample, $tree, 0);
        }
        $avgPathLength /= count($model['trees']);

        $c = $this->calculateAveragePathLength($model['subsample_size']);
        return pow(2, -($avgPathLength / $c));
    }

    private function pathLength(array $sample, array $node, int $depth): float
    {
        if ($node['type'] === 'leaf') {
            return $depth + $this->calculateAveragePathLength($node['size']);
        }

        $feature = $node['feature'];
        $value = $sample[$feature] ?? 0;

        if ($value < $node['split_value']) {
            return $this->pathLength($sample, $node['left'], $depth + 1);
        }
        return $this->pathLength($sample, $node['right'], $depth + 1);
    }

    private function calculateAveragePathLength(int $n): float
    {
        if ($n <= 1) {
            return 0;
        }
        $H = log($n - 1) + 0.5772156649; // Euler's constant
        return 2 * $H - (2 * ($n - 1) / $n);
    }

    private function setAnomalyThreshold(float $threshold): void
    {
        $this->config['anomaly_threshold'] = $threshold;
    }

    private function getAnomalyThreshold(): float
    {
        return $this->config['anomaly_threshold'];
    }

    private function extractFeatures(array $requestData): array
    {
        return [
            'request_rate' => $requestData['request_rate'] ?? 0,
            'path_diversity' => $requestData['path_diversity'] ?? 0,
            'session_duration' => $requestData['session_duration'] ?? 0,
            'page_views' => $requestData['page_views'] ?? 0,
            'unique_paths' => $requestData['unique_paths'] ?? 0,
            'user_agent_entropy' => $this->calculateEntropy($requestData['user_agent'] ?? ''),
        ];
    }

    private function calculateEntropy(string $str): float
    {
        if (empty($str)) {
            return 0;
        }

        $len = strlen($str);
        $freq = array_count_values(str_split($str));
        $entropy = 0;

        foreach ($freq as $count) {
            $p = $count / $len;
            $entropy -= $p * log($p, 2);
        }

        return $entropy;
    }

    private function normalizeFeatures(array $features): array
    {
        // Min-max normalization to [0, 1]
        $normalized = [];
        foreach ($features as $key => $value) {
            $normalized[$key] = max(0, min(1, $value / 100)); // Simple normalization
        }
        return $normalized;
    }

    private function clusterSamples(array $samples, int $k): array
    {
        // Simple K-means stub
        $clusters = array_fill(0, $k, []);
        foreach ($samples as $idx => $sample) {
            $clusters[$idx % $k][] = $sample;
        }
        return $clusters;
    }

    private function saveModel(array $model, string $modelId): bool
    {
        $this->models[$modelId] = $model;
        return true;
    }

    private function loadModel(string $modelId): ?array
    {
        return $this->models[$modelId] ?? null;
    }
}
