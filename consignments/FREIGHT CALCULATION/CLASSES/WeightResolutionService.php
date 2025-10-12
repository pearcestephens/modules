<?php
declare(strict_types=1);

namespace Modules\Transfers\Stock\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * File: WeightResolutionService.php
 * Purpose: Resolve stock-transfer product weights using curated data, dimensional analysis,
 *          historical parcel medians, and category defaults to produce audit-ready results.
 * Author: GitHub Copilot
 * Last Modified: 2025-10-08
 * Dependencies: PDO (Core\\DB::instance(), cis_pdo(), or global $pdo)
 */
final class WeightResolutionService
{
    private const VOLUMETRIC_DIVISOR = 5000; // cm³ per kg for courier volumetric rules
    private const MIN_OBSERVATIONS = 3;
    private const MIN_WEIGHT_FOR_REVIEW = 20; // grams
    private const FALLBACK_BASELINE = 80; // grams when every cascade layer fails

    /**
     * Category/type keywords mapped to sensible default weights (grams).
     * @var array<string,int>
     */
    private const CATEGORY_DEFAULTS = [
        'pods' => 30,
        'pod' => 30,
        'coil' => 20,
        'coils' => 20,
        '60ml' => 110,
        '120ml' => 180,
        'device' => 250,
        'devices' => 250,
        'tank' => 120,
        'tanks' => 120,
        'accessor' => 50,
        'accessories' => 50,
    ];

    private PDO $db;

    public function __construct()
    {
        if (class_exists('\\Core\\DB') && method_exists('\\Core\\DB', 'instance')) {
            $pdo = \Core\DB::instance();
        } elseif (function_exists('cis_pdo')) {
            $pdo = cis_pdo();
        } elseif (class_exists('\\DB') && method_exists('\\DB', 'instance')) {
            $pdo = \DB::instance();
        } elseif (!empty($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $pdo = $GLOBALS['pdo'];
        } else {
            throw new RuntimeException('DB not initialized — include /app.php before using services.');
        }

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('DB provider did not return a PDO instance.');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $pdo;
    }

    /**
     * Resolve weights for a set of product IDs.
     *
     * @param array<int|string> $productIds
     * @return array{
     *     weights: array<string,array{
     *         resolved_weight_g:int,
     *         source:string,
     *         product_weight_g:?int,
     *         category_weight_g:?int,
     *         category_id:?string,
     *         category_code:?string,
     *         product_type_code:?string,
     *         notes:array<string,mixed>
     *     }>,
     *     summary: array<string,int>
     * }
     */
    public function resolveWeights(array $productIds): array
    {
        $ids = $this->normalizeProductIds($productIds);
        if ($ids === []) {
            return ['weights' => [], 'summary' => []];
        }

        $meta = $this->fetchProductMeta($ids);
        $curatedWeights = $this->fetchCuratedWeights($ids);
        $productDimensions = $this->fetchProductDimensions($ids);

        $categoryIds = array_values(array_filter(array_unique(array_map(
            static fn(array $row): ?string => $row['category_id'] ?? null,
            $meta
        ))));

        $categoryDimensions = $this->fetchCategoryDimensions($categoryIds);
        $productMedians = $this->fetchProductHistoricalMedians($ids);
        $categoryMedians = $this->fetchCategoryHistoricalMedians($categoryIds);

        $weights = [];
        $summary = [
            'curated' => 0,
            'dimension' => 0,
            'historical' => 0,
            'category_historical' => 0,
            'category_default' => 0,
            'fallback' => 0,
        ];

        foreach ($ids as $productId) {
            $metaRow = $meta[$productId] ?? [
                'category_id' => null,
                'category_code' => null,
                'product_type_code' => null,
                'category_weight_g' => null,
                'type_avg_weight_g' => null,
            ];

            $categoryId = $metaRow['category_id'] ?? null;
            $resolvedWeight = null;
            $productWeight = null;
            $categoryWeight = $metaRow['category_weight_g'] ?? null;
            $notes = [];
            $source = 'fallback';

            if (isset($curatedWeights[$productId])) {
                $resolvedWeight = self::roundUpToNearestTen($curatedWeights[$productId]);
                $productWeight = (int) $curatedWeights[$productId];
                $source = 'curated';
            } else {
                $dimData = $productDimensions[$productId] ?? (
                    $categoryId !== null ? ($categoryDimensions[$categoryId] ?? null) : null
                );

                $productMedian = $productMedians[$productId]['weight'] ?? null;
                $productMedianObs = $productMedians[$productId]['observations'] ?? null;

                $categoryMedian = $categoryId !== null
                    ? ($categoryMedians[$categoryId]['weight'] ?? null)
                    : null;
                $categoryMedianObs = $categoryId !== null
                    ? ($categoryMedians[$categoryId]['observations'] ?? null)
                    : null;

                $volumetricWeight = $this->calculateVolumetricWeight($dimData);
                if ($volumetricWeight !== null) {
                    $actualCandidate = $productMedian ?? $categoryMedian ?? null;
                    if ($actualCandidate !== null) {
                        $notes['actual_candidate_g'] = (int) $actualCandidate;
                    }

                    $billable = $volumetricWeight;
                    if ($actualCandidate !== null) {
                        $billable = max($billable, (float) $actualCandidate);
                    }

                    if ($billable > 0) {
                        $resolvedWeight = self::roundUpToNearestTen($billable);
                        $source = 'dimension';
                        $notes['volumetric_weight_g'] = (int) ceil($volumetricWeight);

                        if ($productMedian !== null) {
                            $productWeight = (int) $productMedian;
                            $notes['product_observations'] = $productMedianObs;
                        } elseif ($categoryMedian !== null) {
                            $categoryWeight = (int) $categoryMedian;
                            $notes['category_observations'] = $categoryMedianObs;
                        }
                    }
                }

                if ($resolvedWeight === null && $productMedian !== null) {
                    $resolvedWeight = self::roundUpToNearestTen($productMedian);
                    $source = 'historical';
                    $productWeight = (int) $productMedian;
                    $notes['product_observations'] = $productMedianObs;
                }

                if ($resolvedWeight === null && $categoryMedian !== null) {
                    $resolvedWeight = self::roundUpToNearestTen($categoryMedian);
                    $source = 'category_historical';
                    $categoryWeight = (int) $categoryMedian;
                    $notes['category_observations'] = $categoryMedianObs;
                }

                if ($resolvedWeight === null) {
                    $defaultWeight = $this->inferDefaultWeight($metaRow);
                    if ($defaultWeight !== null) {
                        $resolvedWeight = self::roundUpToNearestTen($defaultWeight);
                        $source = 'category_default';
                        $categoryWeight = (int) $defaultWeight;
                    }
                }
            }

            if ($resolvedWeight === null) {
                $fallbackWeight = $metaRow['category_weight_g']
                    ?? $metaRow['type_avg_weight_g']
                    ?? self::FALLBACK_BASELINE;
                $resolvedWeight = self::roundUpToNearestTen($fallbackWeight);
                $source = 'fallback';
                if ($categoryWeight === null) {
                    $categoryWeight = (int) $fallbackWeight;
                }
            }

            $resolvedWeight = max(10, (int) $resolvedWeight);

            if ($resolvedWeight < self::MIN_WEIGHT_FOR_REVIEW) {
                $this->logLowWeight($productId, $resolvedWeight, $source);
            }

            $weights[$productId] = [
                'resolved_weight_g' => $resolvedWeight,
                'source' => $source,
                'product_weight_g' => $productWeight,
                'category_weight_g' => $categoryWeight,
                'category_id' => $metaRow['category_id'] ?? null,
                'category_code' => $metaRow['category_code'] ?? null,
                'product_type_code' => $metaRow['product_type_code'] ?? null,
                'notes' => $notes,
            ];

            if (!isset($summary[$source])) {
                $summary[$source] = 0;
            }
            $summary[$source]++;
        }

        ksort($summary);

        return ['weights' => $weights, 'summary' => $summary];
    }

    /**
     * @param array<int|string> $productIds
     * @return array<int,string>
     */
    private function normalizeProductIds(array $productIds): array
    {
        $ids = [];
        foreach ($productIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int,string> $productIds
     * @return array<string,array{
     *     category_id:?string,
     *     category_code:?string,
     *     product_type_code:?string,
     *     category_weight_g:?int,
     *     type_avg_weight_g:?int
     * }>
     */
    private function fetchProductMeta(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT
                    pcu.product_id,
                    pcu.category_id,
                    pcu.category_code,
                    pcu.product_type_code,
                    cw.avg_weight_grams AS category_weight_g,
                    pt.avg_weight_grams AS type_avg_weight_g
                FROM product_classification_unified pcu
                LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
                LEFT JOIN product_types pt ON pt.code = pcu.product_type_code
                WHERE pcu.product_id IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        foreach ($productIds as $idx => $pid) {
            $stmt->bindValue($idx + 1, $pid, PDO::PARAM_STR);
        }

        try {
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('WeightResolutionService::fetchProductMeta failed: ' . $e->getMessage());
            return [];
        }

        $meta = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productId = (string) $row['product_id'];
            $meta[$productId] = [
                'category_id' => isset($row['category_id']) ? (string) $row['category_id'] : null,
                'category_code' => isset($row['category_code']) ? (string) $row['category_code'] : null,
                'product_type_code' => isset($row['product_type_code']) ? (string) $row['product_type_code'] : null,
                'category_weight_g' => self::normalizeWeight($row['category_weight_g'] ?? null),
                'type_avg_weight_g' => self::normalizeWeight($row['type_avg_weight_g'] ?? null),
            ];
        }

        return $meta;
    }

    /**
     * @param array<int,string> $productIds
     * @return array<string,int>
     */
    private function fetchCuratedWeights(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT id AS product_id, avg_weight_grams
                FROM vend_products
                WHERE id IN ($placeholders)
                  AND avg_weight_grams IS NOT NULL
                  AND avg_weight_grams > 0";

        $stmt = $this->db->prepare($sql);
        foreach ($productIds as $idx => $pid) {
            $stmt->bindValue($idx + 1, $pid, PDO::PARAM_STR);
        }

        try {
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('WeightResolutionService::fetchCuratedWeights failed: ' . $e->getMessage());
            return [];
        }

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $weight = self::normalizeWeight($row['avg_weight_grams'] ?? null);
            if ($weight !== null && $weight > 0) {
                $map[(string) $row['product_id']] = $weight;
            }
        }

        return $map;
    }

    /**
     * @param array<int,string> $productIds
     * @return array<string,array{length_mm:?float,width_mm:?float,height_mm:?float,volume_cm3:?float}>
     */
    private function fetchProductDimensions(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT product_id, 
                       COALESCE(length_mm, 0) as avg_length_mm, 
                       COALESCE(width_mm, 0) as avg_width_mm, 
                       COALESCE(height_mm, 0) as avg_height_mm, 
                       COALESCE(volume_cm3, 0) as avg_volume_cm3
                FROM product_dimensions
                WHERE product_id IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        foreach ($productIds as $idx => $pid) {
            $stmt->bindValue($idx + 1, $pid, PDO::PARAM_STR);
        }

        try {
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('WeightResolutionService::fetchProductDimensions failed: ' . $e->getMessage());
            return [];
        }

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(string) $row['product_id']] = [
                'length_mm' => self::normalizePositiveNumber($row['avg_length_mm'] ?? null),
                'width_mm' => self::normalizePositiveNumber($row['avg_width_mm'] ?? null),
                'height_mm' => self::normalizePositiveNumber($row['avg_height_mm'] ?? null),
                'volume_cm3' => self::normalizePositiveNumber($row['avg_volume_cm3'] ?? null),
            ];
        }

        return $map;
    }

    /**
     * @param array<int,string> $categoryIds
     * @return array<string,array{length_mm:?float,width_mm:?float,height_mm:?float,volume_cm3:?float}>
     */
    private function fetchCategoryDimensions(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $sql = "SELECT category_id, 
                       COALESCE(typical_length_mm, 0) as avg_length_mm, 
                       COALESCE(typical_width_mm, 0) as avg_width_mm, 
                       COALESCE(typical_height_mm, 0) as avg_height_mm,
                       COALESCE(ROUND((typical_length_mm * typical_width_mm * typical_height_mm) / 1000), 0) as avg_volume_cm3
                FROM product_category_dimensions
                WHERE category_id IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        foreach ($categoryIds as $idx => $cid) {
            $stmt->bindValue($idx + 1, $cid, PDO::PARAM_STR);
        }

        try {
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('WeightResolutionService::fetchCategoryDimensions failed: ' . $e->getMessage());
            return [];
        }

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(string) $row['category_id']] = [
                'length_mm' => self::normalizePositiveNumber($row['avg_length_mm'] ?? null),
                'width_mm' => self::normalizePositiveNumber($row['avg_width_mm'] ?? null),
                'height_mm' => self::normalizePositiveNumber($row['avg_height_mm'] ?? null),
                'volume_cm3' => self::normalizePositiveNumber($row['avg_volume_cm3'] ?? null),
            ];
        }

        return $map;
    }

    /**
     * @param array<int,string> $productIds
     * @return array<string,array{weight:int,observations:int}>
     */
    private function fetchProductHistoricalMedians(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = <<<SQL
WITH parcel_qty AS (
    SELECT parcel_id, SUM(qty) AS total_qty
      FROM transfer_parcel_items
     GROUP BY parcel_id
),
product_obs AS (
    SELECT
        ti.product_id,
        pq.total_qty,
        tp.weight_grams,
        CASE
            WHEN tp.weight_grams > 0 AND pq.total_qty > 0
                THEN tp.weight_grams / pq.total_qty
            ELSE NULL
        END AS unit_estimate_g
    FROM transfer_parcel_items tpi
    INNER JOIN transfer_parcels tp ON tp.id = tpi.parcel_id
    INNER JOIN parcel_qty pq ON pq.parcel_id = tpi.parcel_id
    INNER JOIN transfer_items ti ON ti.id = tpi.item_id
    WHERE ti.product_id IN ($placeholders)
      AND tp.weight_grams IS NOT NULL
      AND tp.weight_grams > 0
      AND pq.total_qty > 0
),
ranked AS (
    SELECT
        product_id,
        unit_estimate_g,
        COUNT(*) OVER (PARTITION BY product_id) AS total_obs,
        PERCENT_RANK() OVER (PARTITION BY product_id ORDER BY unit_estimate_g) AS pr
    FROM product_obs
    WHERE unit_estimate_g IS NOT NULL
),
filtered AS (
    SELECT
        product_id,
        unit_estimate_g,
        total_obs
    FROM ranked
    WHERE total_obs < 20 OR (pr BETWEEN 0.05 AND 0.95)
),
final AS (
    SELECT
        product_id,
        total_obs,
        COUNT(*) OVER (PARTITION BY product_id) AS filtered_count,
        PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY unit_estimate_g) OVER (PARTITION BY product_id) AS median_weight_g,
        ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY unit_estimate_g) AS rn
    FROM filtered
)
SELECT product_id,
       total_obs,
       filtered_count,
       median_weight_g
  FROM final
 WHERE rn = 1
   AND total_obs >= ?
   AND filtered_count >= ?;
SQL;

        $stmt = $this->db->prepare($sql);
        $idx = 1;
        foreach ($productIds as $pid) {
            $stmt->bindValue($idx++, $pid, PDO::PARAM_STR);
        }
        $stmt->bindValue($idx++, self::MIN_OBSERVATIONS, PDO::PARAM_INT);
        $stmt->bindValue($idx, self::MIN_OBSERVATIONS, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('WeightResolutionService::fetchProductHistoricalMedians failed: ' . $e->getMessage());
            return [];
        }

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $weight = self::normalizeWeight($row['median_weight_g'] ?? null);
            $obs = self::normalizePositiveNumber($row['total_obs'] ?? null);
            if ($weight !== null && $obs !== null) {
                $map[(string) $row['product_id']] = [
                    'weight' => $weight,
                    'observations' => (int) round($obs),
                ];
            }
        }

        return $map;
    }

    /**
     * @param array<int,string> $categoryIds
     * @return array<string,array{weight:int,observations:int}>
     */
    private function fetchCategoryHistoricalMedians(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $sql = <<<SQL
WITH parcel_qty AS (
    SELECT parcel_id, SUM(qty) AS total_qty
      FROM transfer_parcel_items
     GROUP BY parcel_id
),
category_obs AS (
    SELECT
        pcu.category_id,
        pq.total_qty,
        tp.weight_grams,
        CASE
            WHEN tp.weight_grams > 0 AND pq.total_qty > 0
                THEN tp.weight_grams / pq.total_qty
            ELSE NULL
        END AS unit_estimate_g
    FROM transfer_parcel_items tpi
    INNER JOIN transfer_parcels tp ON tp.id = tpi.parcel_id
    INNER JOIN parcel_qty pq ON pq.parcel_id = tpi.parcel_id
    INNER JOIN transfer_items ti ON ti.id = tpi.item_id
    INNER JOIN product_classification_unified pcu ON pcu.product_id = ti.product_id
    WHERE pcu.category_id IN ($placeholders)
      AND tp.weight_grams IS NOT NULL
      AND tp.weight_grams > 0
      AND pq.total_qty > 0
),
ranked AS (
    SELECT
        category_id,
        unit_estimate_g,
        COUNT(*) OVER (PARTITION BY category_id) AS total_obs,
        PERCENT_RANK() OVER (PARTITION BY category_id ORDER BY unit_estimate_g) AS pr
    FROM category_obs
    WHERE unit_estimate_g IS NOT NULL
),
filtered AS (
    SELECT
        category_id,
        unit_estimate_g,
        total_obs
    FROM ranked
    WHERE total_obs < 20 OR (pr BETWEEN 0.05 AND 0.95)
),
final AS (
    SELECT
        category_id,
        total_obs,
        COUNT(*) OVER (PARTITION BY category_id) AS filtered_count,
        PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY unit_estimate_g) OVER (PARTITION BY category_id) AS median_weight_g,
        ROW_NUMBER() OVER (PARTITION BY category_id ORDER BY unit_estimate_g) AS rn
    FROM filtered
)
SELECT category_id,
       total_obs,
       filtered_count,
       median_weight_g
  FROM final
 WHERE rn = 1
   AND total_obs >= ?
   AND filtered_count >= ?;
SQL;

        $stmt = $this->db->prepare($sql);
        $idx = 1;
        foreach ($categoryIds as $cid) {
            $stmt->bindValue($idx++, $cid, PDO::PARAM_STR);
        }
        $stmt->bindValue($idx++, self::MIN_OBSERVATIONS, PDO::PARAM_INT);
        $stmt->bindValue($idx, self::MIN_OBSERVATIONS, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('WeightResolutionService::fetchCategoryHistoricalMedians failed: ' . $e->getMessage());
            return [];
        }

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $weight = self::normalizeWeight($row['median_weight_g'] ?? null);
            $obs = self::normalizePositiveNumber($row['total_obs'] ?? null);
            if ($weight !== null && $obs !== null) {
                $map[(string) $row['category_id']] = [
                    'weight' => $weight,
                    'observations' => (int) round($obs),
                ];
            }
        }

        return $map;
    }

    /**
     * @param array{length_mm:?float,width_mm:?float,height_mm:?float,volume_cm3:?float}|null $dims
     */
    private function calculateVolumetricWeight(?array $dims): ?float
    {
        if ($dims === null) {
            return null;
        }

        $length = $dims['length_mm'] ?? null;
        $width = $dims['width_mm'] ?? null;
        $height = $dims['height_mm'] ?? null;
        $volumeCm3 = $dims['volume_cm3'] ?? null;

        if ($volumeCm3 === null || $volumeCm3 <= 0) {
            if ($length === null || $width === null || $height === null) {
                return null;
            }
            if ($length <= 0 || $width <= 0 || $height <= 0) {
                return null;
            }
            $lengthCm = $length / 10;
            $widthCm = $width / 10;
            $heightCm = $height / 10;
            $volumeCm3 = $lengthCm * $widthCm * $heightCm;
        }

        if ($volumeCm3 <= 0) {
            return null;
        }

        $volumetricKg = $volumeCm3 / self::VOLUMETRIC_DIVISOR;
        $grams = $volumetricKg * 1000;

        return $grams > 0 ? $grams : null;
    }

    /**
     * @param array<string,mixed> $metaRow
     */
    private function inferDefaultWeight(array $metaRow): ?int
    {
        $candidates = [];

        if (!empty($metaRow['category_code'])) {
            $candidates[] = $this->matchCategoryDefault((string) $metaRow['category_code']);
        }

        if (!empty($metaRow['product_type_code'])) {
            $candidates[] = $this->matchCategoryDefault((string) $metaRow['product_type_code']);
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate > 0) {
                return $candidate;
            }
        }

        $typeAvg = $metaRow['type_avg_weight_g'] ?? null;
        if ($typeAvg !== null && $typeAvg > 0) {
            return (int) $typeAvg;
        }

        return null;
    }

    private function matchCategoryDefault(?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        $normalized = strtolower($code);
        foreach (self::CATEGORY_DEFAULTS as $needle => $weight) {
            if (str_contains($normalized, $needle)) {
                return $weight;
            }
        }

        return null;
    }

    private static function roundUpToNearestTen(float|int $grams): int
    {
        if ($grams <= 0) {
            return 10;
        }

        return (int) (ceil($grams / 10) * 10);
    }

    private static function normalizeWeight(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        if ($float <= 0) {
            return null;
        }

        if ($float > 0 && $float < 1) {
            $float *= 1000;
        }

        return (int) round($float);
    }

    private static function normalizePositiveNumber(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        if ($float <= 0) {
            return null;
        }

        return $float;
    }

    private function logLowWeight(string $productId, int $weight, string $source): void
    {
        error_log(sprintf(
            'WeightResolutionService: product %s resolved to %dg via %s — below %dg threshold',
            $productId,
            $weight,
            $source,
            self::MIN_WEIGHT_FOR_REVIEW
        ));
    }
}
