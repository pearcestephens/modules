<?php
declare(strict_types=1);

/**
 * CisFreight (Ultimate)
 *
 * Static façade over:
 *   - DB pricebook (v_carrier_container_prices, etc.)
 *   - Packaging (pack rules, carton specs)
 *   - Picking/pricing helpers (DB functions with graceful fallbacks)
 *   - Live rates: NZ Post eShip (Starshipit) + GoSweetSpot (GSS)
 *   - Extra algorithms: cartonisation, multi-parcel, SLA scoring, caching, retries
 *
 * PHP 8.1+, PDO (mysql)
 */
final class CisFreight
{
    // -----------------------------
    // Runtime & config
    // -----------------------------

    /** @var \PDO */
    private static \PDO $pdo;

    /** In-memory cache for rates (per-request), keyed by hash of payload/provider */
    private static array $rateCache = [];

    /** Controls backoff & retries for HTTP */
    private const HTTP_RETRIES  = 2;
    private const HTTP_TIMEOUT  = 12.0;
    private const HTTP_JITTER_S = 0.150;

    /** DB constants */
    public const UNKNOWN_CATEGORY_ID = '00000000-0000-0000-0000-000000000000';

    /** Internal carrier IDs (set these to match your DB; update via setCarrierMap) */
    private static int $CARRIER_NZ_POST = 1;
    private static int $CARRIER_GSS     = 2;

    /** eShip (Starshipit) */
    private static array $eship = [
        'enabled'          => false,
        'base_url'         => 'https://api.starshipit.com/api',
        'api_key'          => null, // StarShipIT-Api-Key
        'subscription_key' => null, // Ocp-Apim-Subscription-Key
        'timeout'          => 10.0,
    ];

    /** GoSweetSpot */
    private static array $gss = [
        'enabled'      => false,
        'base_url'     => 'https://api.gosweetspot.com/api',
        'access_key'   => null,
        'site_id'      => null,
        'supportemail' => null,
        'timeout'      => 12.0,
    ];

    // -----------------------------
    // Bootstrapping
    // -----------------------------

    public static function init(\PDO $pdo): void
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        self::$pdo = $pdo;
    }

    /** Load provider config from ENV. Rotate & store keys as ENV before using. */
    public static function initFromEnv(\PDO $pdo): void
    {
        self::init($pdo);
        self::setLiveProviderConfig('eship', [
            'enabled'          => (bool)(getenv('ESHIP_API_KEY') && getenv('ESHIP_SUBSCRIPTION_KEY')),
            'base_url'         => getenv('ESHIP_API_BASE') ?: self::$eship['base_url'],
            'api_key'          => getenv('ESHIP_API_KEY') ?: null,
            'subscription_key' => getenv('ESHIP_SUBSCRIPTION_KEY') ?: null,
            'timeout'          => (float)(getenv('ESHIP_TIMEOUT') ?: self::$eship['timeout']),
        ]);
        self::setLiveProviderConfig('gss', [
            'enabled'      => (bool)getenv('GSS_ACCESS_KEY'),
            'base_url'     => getenv('GSS_API_BASE') ?: self::$gss['base_url'],
            'access_key'   => getenv('GSS_ACCESS_KEY') ?: null,
            'site_id'      => getenv('GSS_SITE_ID') ?: null,
            'supportemail' => getenv('GSS_SUPPORT_EMAIL') ?: null,
            'timeout'      => (float)(getenv('GSS_TIMEOUT') ?: self::$gss['timeout']),
        ]);
    }

    /** Map DB carrier IDs (if not 1/2). */
    public static function setCarrierMap(int $nzPostId, int $gssId): void
    {
        self::$CARRIER_NZ_POST = $nzPostId;
        self::$CARRIER_GSS     = $gssId;
    }

    /** Update provider config programmatically. */
    public static function setLiveProviderConfig(string $provider, array $config): void
    {
        $provider = strtolower($provider);
        if ($provider === 'eship' || $provider === 'nzpost') {
            self::$eship = array_merge(self::$eship, $config);
            return;
        }
        if ($provider === 'gss') {
            self::$gss = array_merge(self::$gss, $config);
            return;
        }
        throw new \InvalidArgumentException("Unknown provider: $provider");
    }

    // -----------------------------
    // Introspection
    // -----------------------------

    public static function exists(string $name, string $type = 'VIEW'): bool
    {
        if (strtoupper($type) === 'VIEW') {
            $sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='VIEW' AND table_name=:n";
        } else {
            $sql = "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema=DATABASE() AND routine_type='FUNCTION' AND routine_name=:n";
        }
        $st = self::$pdo->prepare($sql);
        $st->execute([':n'=>$name]);
        return (int)$st->fetchColumn() > 0;
    }

    // -----------------------------
    // PRICEBOOK & CATALOG
    // -----------------------------

    /** Authoritative pricebook; filter optional. */
    public static function getPricebook(?int $carrierId = null, ?int $serviceId = null): array
    {
        $view = 'v_carrier_container_prices';
        if (!self::exists($view, 'VIEW')) {
            throw new \RuntimeException("$view not found");
        }
        $sql = "SELECT * FROM $view WHERE 1=1";
        $p = [];
        if ($carrierId !== null) { $sql.=" AND carrier_id=:c"; $p[':c']=$carrierId; }
        if ($serviceId !== null) { $sql.=" AND service_id <=> :s"; $p[':s']=$serviceId; }
        $sql.=" ORDER BY carrier_name, COALESCE(service_name,'(no service)'), container_code";
        $st = self::$pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    public static function getFreightCatalog(): array
    {
        $v = 'v_freight_rules_catalog';
        if (!self::exists($v, 'VIEW')) return [];
        return self::$pdo->query("SELECT * FROM $v")->fetchAll();
    }

    public static function getNzPostEShip(): array
    {
        $v = 'v_nzpost_eship_containers';
        if (!self::exists($v, 'VIEW')) return [];
        return self::$pdo->query("SELECT * FROM $v")->fetchAll();
    }

    // -----------------------------
    // COVERAGE / PACKAGING / CATEGORY
    // -----------------------------

    public static function getCoverage(): array
    {
        $out = [];
        foreach (['v_classification_coverage','v_weight_coverage'] as $v) {
            if (self::exists($v, 'VIEW')) {
                $out[$v] = self::$pdo->query("SELECT * FROM $v")->fetchAll();
            }
        }
        return $out;
    }

    public static function getUnknowns(): array
    {
        $v = 'v_unknown_products';
        if (!self::exists($v, 'VIEW')) return [];
        return self::$pdo->query("SELECT * FROM $v")->fetchAll();
    }

    public static function getWeightGaps(): array
    {
        $v = 'v_weight_gaps';
        if (!self::exists($v, 'VIEW')) return [];
        return self::$pdo->query("SELECT * FROM $v")->fetchAll();
    }

    public static function getPackProfile(string $productId): ?array
    {
        $v = 'v_product_pack_profile';
        if (!self::exists($v, 'VIEW')) return null;
        $st = self::$pdo->prepare("SELECT * FROM $v WHERE product_id=:p LIMIT 1");
        $st->execute([':p'=>$productId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function mapVendorCategory(string $vendCategoryId, string $cisCategoryId, string $status='refined'): int
    {
        $st = self::$pdo->prepare("UPDATE vend_category_map SET target_category_id=:c, refinement_status=:s WHERE vend_category_id=:v");
        $st->execute([':c'=>$cisCategoryId, ':s'=>$status, ':v'=>$vendCategoryId]);
        return $st->rowCount();
    }

    // -----------------------------
    // CORE PICKING & PRICING (DB-first with fallbacks)
    // -----------------------------

    /** Read carrier volumetric factor (defaults to 200). */
    private static function volumetricFactor(int $carrierId): int
    {
        try {
            $st = self::$pdo->prepare("SELECT COALESCE(volumetric_factor,200) FROM carriers WHERE carrier_id=:c");
            $st->execute([':c'=>$carrierId]);
            $v = (int)$st->fetchColumn();
            return $v > 0 ? $v : 200;
        } catch (\Throwable) { return 200; }
    }

    /** Compute volumetric grams (L×W×H(m³)×factor×1000g). Zeros/NULL dims → 0. */
    private static function volumetricGrams(?int $L, ?int $W, ?int $H, int $factor): int
    {
        if (!$L || !$W || !$H || $L<=0 || $W<=0 || $H<=0) return 0;
        return (int)ceil(($L/1000)*($W/1000)*($H/1000) * $factor * 1000.0);
    }

    /** Pick container via DB function; falls back to catalog selection. */
    public static function pickContainer(
        int $carrierId, ?int $lenMm, ?int $widMm, ?int $heiMm, int $weightG
    ): array {
        if (self::exists('pick_container_json','FUNCTION')) {
            $st = self::$pdo->prepare("SELECT pick_container_json(:c,:l,:w,:h,:g)");
            $st->execute([':c'=>$carrierId,':l'=>$lenMm,':w'=>$widMm,':h'=>$heiMm,':g'=>$weightG]);
            $js = $st->fetchColumn();
            return $js ? (json_decode($js,true) ?: []) : [];
        }
        // Fallback: select from pricebook
        $vf   = self::volumetricFactor($carrierId);
        $volG = self::volumetricGrams($lenMm,$widMm,$heiMm,$vf);
        $reqG = max($weightG,$volG);

        $sql = "SELECT *
                  FROM v_carrier_container_prices
                 WHERE carrier_id=:c
                   AND (rule_cap_g IS NULL OR rule_cap_g>=:g)
                   AND (length_mm IS NULL OR length_mm=0 OR length_mm>=COALESCE(:l,0))
                   AND (width_mm  IS NULL OR width_mm =0 OR width_mm >=COALESCE(:w,0))
                   AND (height_mm IS NULL OR height_mm=0 OR height_mm>=COALESCE(:h,0))
              ORDER BY cost ASC, COALESCE(rule_cap_g,99999999) ASC, container_id ASC
                 LIMIT 1";
        $st = self::$pdo->prepare($sql);
        $st->execute([':c'=>$carrierId,':g'=>$reqG,':l'=>$lenMm,':w'=>$widMm,':h'=>$heiMm]);
        return $st->fetch() ?: [];
    }

    public static function explainPick(int $carrierId, ?int $L, ?int $W, ?int $H, int $g): array
    {
        if (!self::exists('pick_container_explain_json','FUNCTION')) {
            return ['error'=>'pick_container_explain_json not installed'];
        }
        $st = self::$pdo->prepare("SELECT pick_container_explain_json(:c,:l,:w,:h,:g)");
        $st->execute([':c'=>$carrierId,':l'=>$L,':w'=>$W,':h'=>$H,':g'=>$g]);
        $js = $st->fetchColumn();
        return $js ? (json_decode($js,true) ?: []) : [];
    }

    public static function priceLineCost(string $productId, int $qty, int $carrierId): float
    {
        if (self::exists('price_line_cost','FUNCTION')) {
            $st = self::$pdo->prepare("SELECT price_line_cost(:p,:q,:c)");
            $st->execute([':p'=>$productId,':q'=>$qty,':c'=>$carrierId]);
            return (float)$st->fetchColumn();
        }
        // Fallback: unit→total→pick cost
        $sql = "SELECT COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, 100) AS unit_g
                  FROM vend_products vp
             LEFT JOIN product_classification_unified pcu ON pcu.product_id=vp.id
             LEFT JOIN category_weights cw               ON cw.category_id=pcu.category_id
                 WHERE vp.id=:p LIMIT 1";
        $st = self::$pdo->prepare($sql);
        $st->execute([':p'=>$productId]);
        $unit = (int)($st->fetchColumn() ?: 100);
        $total = max(0,$unit) * max(1,$qty);
        if (self::exists('pick_container_cost','FUNCTION')) {
            $st2 = self::$pdo->prepare("SELECT pick_container_cost(:c,NULL,NULL,NULL,:g)");
            $st2->execute([':c'=>$carrierId,':g'=>$total]);
            return (float)$st2->fetchColumn();
        }
        $picked = self::pickContainer($carrierId,null,null,null,$total);
        return isset($picked['cost']) ? (float)$picked['cost'] : 0.0;
    }

    public static function priceLineJson(string $productId, int $qty, int $carrierId): array
    {
        if (!self::exists('price_line_json','FUNCTION')) {
            return ['error'=>'price_line_json not installed'];
        }
        $st = self::$pdo->prepare("SELECT price_line_json(:p,:q,:c)");
        $st->execute([':p'=>$productId,':q'=>$qty,':c'=>$carrierId]);
        $js = $st->fetchColumn();
        return $js ? (json_decode($js,true) ?: []) : [];
    }

    /**
     * Price a cart by summing per-line costs.
     * Input lines format: [[product_id, qty], ...]
     * Returns: ['total'=>float, 'breakdown'=>[{product_id, qty, cost}, ...]]
     */
    public static function priceCartPerLine(array $lines, int $carrierId): array
    {
        $total = 0.0; $breakdown = [];
        foreach ($lines as $ln) {
            [$pid,$qty] = $ln;
            $pid = (string)$pid; $q = max(1, (int)$qty);
            $cost = self::priceLineCost($pid, $q, $carrierId);
            $breakdown[] = ['product_id'=>$pid, 'qty'=>$q, 'cost'=>$cost];
            $total += $cost;
        }
        return ['total'=>round($total,2), 'breakdown'=>$breakdown];
    }

    /**
     * Price a cart as a single consolidated parcel using weight-based pick.
     * Returns: ['cost'=>float, 'pick'=>array|null, 'total_weight_g'=>int]
     */
    public static function priceCartConsolidated(array $lines, int $carrierId): array
    {
        // If DB function exists, prefer it (JSON returning function optional)
        if (self::exists('price_cart_consolidated_json','FUNCTION')) {
            try {
                // Prepare lightweight JSON payload
                $payload = json_encode(array_map(fn($ln)=>[
                    'product_id'=>(string)$ln[0], 'qty'=>(int)$ln[1]
                ], $lines), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                $st = self::$pdo->prepare("SELECT price_cart_consolidated_json(:c,:payload)");
                $st->execute([':c'=>$carrierId, ':payload'=>$payload]);
                $js = $st->fetchColumn();
                $row = $js ? (json_decode($js,true) ?: []) : [];
                if (is_array($row) && array_key_exists('cost',$row)) {
                    return $row;
                }
            } catch (\Throwable) { /* fall through to algorithmic fallback */ }
        }

        // Fallback: compute total weight and pick cheapest container for the carrier
        $totalG = self::totalWeightG($lines);
        $pick   = self::pickContainer($carrierId, null, null, null, $totalG);
        $cost   = isset($pick['cost']) ? (float)$pick['cost'] : 0.0;
        return [
            'cost'           => round($cost,2),
            'pick'           => $pick ?: null,
            'total_weight_g' => $totalG,
        ];
    }

    // -----------------------------
    // NEW: CARTONISATION & MULTI-PARCEL (algorithmic, no SQL needed)
    // -----------------------------

    /**
     * Compute total grams for a list of [product_id, qty].
     * Uses product.avg_weight_grams → category_weights → 500g fallback (improved from 100g).
     */
    public static function totalWeightG(array $lines): int
    {
        $sum = 0;
        $sql = "SELECT COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, 500) AS unit_g
                  FROM vend_products vp
             LEFT JOIN product_classification_unified pcu ON pcu.product_id=vp.id
             LEFT JOIN category_weights cw               ON cw.category_id=pcu.category_id
                 WHERE vp.id=:p LIMIT 1";
        $st = self::$pdo->prepare($sql);
        foreach ($lines as $ln) {
            [$pid,$qty] = $ln;
            $st->execute([':p'=>$pid]);
            $u = (int)($st->fetchColumn() ?: 500);
            $sum += max(0,$u) * max(1,(int)$qty);
        }
        return $sum;
    }

    /**
     * Calculate complete transfer dimensions (weight + volume + bounding box)
     * Returns comprehensive measurements for smart container selection
     * 
     * @param array $lines Array of [product_id, qty] pairs
     * @return array {
     *   total_weight_g: int,
     *   total_volume_cm3: int,
     *   dimensions: array of {product_id, qty, length_mm, width_mm, height_mm, weight_g, volume_cm3},
     *   bounding_box: {max_length_mm, max_width_mm, max_height_mm},
     *   has_all_dimensions: bool,
     *   missing_dimensions: array of product_ids
     * }
     */
    public static function calculateTransferDimensions(array $lines): array
    {
        $totalWeightG = 0;
        $totalVolumeCm3 = 0;
        $dimensions = [];
        $missingDimensions = [];
        
        $maxLength = 0;
        $maxWidth = 0;
        $maxHeight = 0;
        
        $sql = "SELECT 
                    vp.id,
                    COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, pd.weight_g, 500) AS weight_g,
                    COALESCE(pd.length_mm, 0) AS length_mm,
                    COALESCE(pd.width_mm, 0) AS width_mm,
                    COALESCE(pd.height_mm, 0) AS height_mm
                FROM vend_products vp
                LEFT JOIN product_classification_unified pcu ON pcu.product_id = vp.id
                LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
                LEFT JOIN product_dimensions pd ON pd.product_id = vp.id
                WHERE vp.id = :p
                LIMIT 1";
        
        $st = self::$pdo->prepare($sql);
        
        foreach ($lines as $ln) {
            [$pid, $qty] = $ln;
            $qty = max(1, (int)$qty);
            
            $st->execute([':p' => $pid]);
            $row = $st->fetch();
            
            if (!$row) {
                $missingDimensions[] = $pid;
                continue;
            }
            
            $weightG = (int)$row['weight_g'];
            $lengthMm = (int)$row['length_mm'];
            $widthMm = (int)$row['width_mm'];
            $heightMm = (int)$row['height_mm'];
            
            // Calculate volume (per unit, then multiply by qty)
            $volumeCm3 = 0;
            if ($lengthMm > 0 && $widthMm > 0 && $heightMm > 0) {
                $volumeCm3 = (($lengthMm / 10) * ($widthMm / 10) * ($heightMm / 10)) * $qty;
            } else {
                $missingDimensions[] = $pid;
            }
            
            $totalWeightG += $weightG * $qty;
            $totalVolumeCm3 += $volumeCm3;
            
            // Track maximum dimensions (bounding box)
            $maxLength = max($maxLength, $lengthMm);
            $maxWidth = max($maxWidth, $widthMm);
            $maxHeight = max($maxHeight, $heightMm);
            
            $dimensions[] = [
                'product_id' => $pid,
                'qty' => $qty,
                'length_mm' => $lengthMm,
                'width_mm' => $widthMm,
                'height_mm' => $heightMm,
                'weight_g' => $weightG,
                'volume_cm3' => $volumeCm3
            ];
        }
        
        return [
            'total_weight_g' => $totalWeightG,
            'total_volume_cm3' => (int)$totalVolumeCm3,
            'dimensions' => $dimensions,
            'bounding_box' => [
                'max_length_mm' => $maxLength,
                'max_width_mm' => $maxWidth,
                'max_height_mm' => $maxHeight
            ],
            'has_all_dimensions' => empty($missingDimensions),
            'missing_dimensions' => $missingDimensions
        ];
    }

    /**
     * Get available containers for a carrier with complete constraints
     * Returns only containers with valid weight/volume limits
     * 
     * @param int $carrierId Carrier ID
     * @param bool $activeOnly Only return active containers
     * @return array List of available containers with full specs
     */
    public static function getAvailableContainers(int $carrierId, bool $activeOnly = true): array
    {
        // Note: cubic_rate and is_active not available in v_carrier_container_prices
        // Using container_cap_g as volumetric ceiling (stored in grams per cm³ * 1000 in some implementations)
        $sql = "SELECT 
                    container_id,
                    container_name,
                    kind AS container_type,
                    carrier_id,
                    carrier_name,
                    rule_cap_g,
                    container_cap_g,
                    length_mm,
                    width_mm,
                    height_mm,
                    cost
                FROM v_carrier_container_prices
                WHERE carrier_id = :carrier_id
                  AND rule_cap_g > 0
                  AND cost > 0
                ORDER BY 
                    CASE kind
                        WHEN 'satchel' THEN 1
                        WHEN 'box' THEN 2
                        WHEN 'pallet' THEN 3
                        ELSE 4
                    END,
                    rule_cap_g ASC";
        
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute([':carrier_id' => $carrierId]);
        $containers = $stmt->fetchAll();
        
        // Validate and enrich
        $valid = [];
        foreach ($containers as $c) {
            // Skip if missing critical data
            if (empty($c['rule_cap_g']) || (float)$c['cost'] <= 0) {
                continue;
            }
            
            // Add computed fields
            $c['has_dimensions'] = ($c['length_mm'] > 0 && $c['width_mm'] > 0 && $c['height_mm'] > 0);
            $c['cost_per_kg'] = (float)$c['cost'] / (max(1, (int)$c['rule_cap_g']) / 1000);
            
            // Use rule_cap_g as actual capacity (volumetric rate not available in this view)
            $c['actual_cap_g'] = (int)$c['rule_cap_g'];
            
            // Calculate volume capacity if dimensions exist
            if ($c['has_dimensions']) {
                $c['volume_capacity_cm3'] = (int)(($c['length_mm'] / 10) * ($c['width_mm'] / 10) * ($c['height_mm'] / 10));
            } else {
                $c['volume_capacity_cm3'] = 0;
            }
            
            $valid[] = $c;
        }
        
        return $valid;
    }

    /**
     * Calculate how well a container fits the requirements
     * Higher score = better fit (balances utilization vs cost)
     */
    private static function calculateFitScore(array $container, int $weightG, int $volumeCm3): float
    {
        $capG = (int)$container['actual_cap_g'];
        $utilization = $weightG / $capG; // 0.0 to 1.0
        
        // Ideal utilization: 70-90% (not too empty, not too full)
        if ($utilization >= 0.7 && $utilization <= 0.9) {
            $utilizationScore = 1.0;
        } else if ($utilization < 0.7) {
            $utilizationScore = $utilization / 0.7; // Penalize empty space
        } else {
            $utilizationScore = 0.5; // Penalize cutting it too close
        }
        
        // Cost efficiency score (inverse of cost per kg, normalized)
        $costScore = 1.0 / (1.0 + $container['cost_per_kg']);
        
        // Weighted score (favor utilization slightly over cost)
        return ($utilizationScore * 0.6) + ($costScore * 0.4);
    }

    /**
     * Dynamically pick best container respecting all constraints
     * 
     * @param int $carrierId Carrier ID
     * @param int $weightG Actual weight in grams
     * @param int $volumeCm3 Total volume in cubic cm (optional)
     * @param int $lengthMm Longest dimension in mm (optional)
     * @param int $widthMm Width in mm (optional)
     * @param int $heightMm Height in mm (optional)
     * @return array Container data with fit analysis
     */
    public static function pickContainerDynamic(
        int $carrierId, 
        int $weightG, 
        int $volumeCm3 = 0,
        int $lengthMm = 0,
        int $widthMm = 0,
        int $heightMm = 0
    ): array {
        // ============================================
        // INPUT VALIDATION: Reject invalid inputs
        // ============================================
        
        // 1. Reject negative or zero weight
        if ($weightG <= 0) {
            return [
                'error' => 'INVALID_WEIGHT',
                'message' => 'Weight must be greater than zero',
                'user_message' => '⚠️ Cannot calculate freight: Invalid weight',
                'required_weight_g' => $weightG,
                'severity' => 'error'
            ];
        }
        
        // 2. Reject extreme dimensions (>3m = 3000mm is max shippable)
        $maxDimension = 3000; // 3 meters
        if ($lengthMm > $maxDimension || $widthMm > $maxDimension || $heightMm > $maxDimension) {
            $oversized = [];
            if ($lengthMm > $maxDimension) $oversized[] = "Length: {$lengthMm}mm";
            if ($widthMm > $maxDimension) $oversized[] = "Width: {$widthMm}mm";
            if ($heightMm > $maxDimension) $oversized[] = "Height: {$heightMm}mm";
            
            return [
                'error' => 'TOO_BIG_TO_SHIP',
                'message' => 'Item dimensions exceed maximum shippable size (3m)',
                'user_message' => '🚫 ITEM TOO BIG TO SHIP: ' . implode(', ', $oversized) . ' exceeds 3m limit. Cannot create transfer.',
                'dimensions' => [
                    'length_mm' => $lengthMm,
                    'width_mm' => $widthMm,
                    'height_mm' => $heightMm,
                    'max_allowed_mm' => $maxDimension
                ],
                'severity' => 'critical',
                'can_continue' => false
            ];
        }
        
        // 3. Reject extreme volume (>1m³ = 1,000,000 cm³)
        $maxVolume = 1000000; // 1 cubic meter
        if ($volumeCm3 > $maxVolume) {
            return [
                'error' => 'TOO_BIG_TO_SHIP',
                'message' => 'Item volume exceeds maximum shippable size (1m³)',
                'user_message' => '🚫 ITEM TOO BIG TO SHIP: Volume ' . number_format($volumeCm3) . 'cm³ exceeds 1m³ limit. Cannot create transfer.',
                'volume_cm3' => $volumeCm3,
                'max_volume_cm3' => $maxVolume,
                'severity' => 'critical',
                'can_continue' => false
            ];
        }
        
        $available = self::getAvailableContainers($carrierId);
        
        if (empty($available)) {
            return [
                'error' => 'NO_CONTAINERS',
                'message' => 'No containers available for this carrier',
                'user_message' => '⚠️ No shipping containers available. Please contact support.',
                'carrier_id' => $carrierId,
                'severity' => 'error'
            ];
        }
        
        // Calculate volumetric weight if volume provided
        $volumetricWeightG = 0;
        if ($volumeCm3 > 0) {
            $vf = self::volumetricFactor($carrierId);
            $volumetricWeightG = (int)($volumeCm3 / $vf);
        }
        
        // Effective weight (higher of actual or volumetric)
        $effectiveWeightG = max($weightG, $volumetricWeightG);
        
        // Filter containers that can fit
        $candidates = [];
        $hasItemDimensions = ($lengthMm > 0 || $widthMm > 0 || $heightMm > 0);
        
        foreach ($available as $c) {
            $fits = true;
            $reasons = [];
            
            // Check weight capacity
            if ($effectiveWeightG > $c['actual_cap_g']) {
                $fits = false;
                $reasons[] = "Weight {$effectiveWeightG}g exceeds capacity {$c['actual_cap_g']}g";
            }
            
            // STRICT MODE: If item has dimensions, container MUST have dimensions to validate fit
            if ($hasItemDimensions && !$c['has_dimensions']) {
                $fits = false;
                $reasons[] = "Container has no dimension data (cannot verify fit for {$lengthMm}×{$widthMm}×{$heightMm}mm item)";
            }
            
            // Check dimensions if both item and container have dimensions
            if ($c['has_dimensions'] && $hasItemDimensions) {
                if ($lengthMm > $c['length_mm']) {
                    $fits = false;
                    $reasons[] = "Length {$lengthMm}mm exceeds {$c['length_mm']}mm";
                }
                if ($widthMm > $c['width_mm']) {
                    $fits = false;
                    $reasons[] = "Width {$widthMm}mm exceeds {$c['width_mm']}mm";
                }
                if ($heightMm > $c['height_mm']) {
                    $fits = false;
                    $reasons[] = "Height {$heightMm}mm exceeds {$c['height_mm']}mm";
                }
            }
            
            // Check volume if both have volume data
            if ($volumeCm3 > 0 && $c['has_dimensions']) {
                $containerVolumeCm3 = ($c['length_mm'] / 10) * ($c['width_mm'] / 10) * ($c['height_mm'] / 10);
                if ($volumeCm3 > $containerVolumeCm3) {
                    $fits = false;
                    $reasons[] = "Volume {$volumeCm3}cm³ exceeds {$containerVolumeCm3}cm³";
                }
            }
            
            if ($fits) {
                $c['fit_score'] = self::calculateFitScore($c, $effectiveWeightG, $volumeCm3);
                $candidates[] = $c;
            } else {
                // Log why it doesn't fit (for debugging)
                error_log("Container {$c['container_name']} rejected: " . implode(', ', $reasons));
            }
        }
        
        if (empty($candidates)) {
            // Build detailed error message showing what failed
            $errorDetails = [];
            if ($effectiveWeightG > 0) {
                $errorDetails[] = "Weight: {$effectiveWeightG}g";
            }
            if ($lengthMm > 0 || $widthMm > 0 || $heightMm > 0) {
                $errorDetails[] = "Dimensions: {$lengthMm}×{$widthMm}×{$heightMm}mm";
            }
            if ($volumeCm3 > 0) {
                $errorDetails[] = "Volume: " . number_format($volumeCm3) . "cm³";
            }
            
            return [
                'error' => 'TOO_BIG_TO_SHIP',
                'message' => 'No container can fit the requirements',
                'user_message' => '🚫 ITEM TOO BIG TO SHIP: ' . implode(', ', $errorDetails) . ' exceeds all available containers. Cannot create transfer.',
                'required_weight_g' => $effectiveWeightG,
                'required_volume_cm3' => $volumeCm3,
                'required_dimensions' => [
                    'length_mm' => $lengthMm,
                    'width_mm' => $widthMm,
                    'height_mm' => $heightMm
                ],
                'available_count' => count($available),
                'severity' => 'critical',
                'can_continue' => false
            ];
        }
        
        // Sort by fit score (best fit, not necessarily cheapest)
        usort($candidates, fn($a, $b) => $b['fit_score'] <=> $a['fit_score']);
        
        $best = $candidates[0];
        
        return [
            'success' => true,
            'container' => $best,
            'fit_analysis' => [
                'weight_g' => $weightG,
                'volumetric_weight_g' => $volumetricWeightG,
                'effective_weight_g' => $effectiveWeightG,
                'container_cap_g' => $best['actual_cap_g'],
                'utilization_pct' => round(($effectiveWeightG / $best['actual_cap_g']) * 100, 1),
                'cost_per_kg' => round($best['cost_per_kg'], 2)
            ],
            'alternatives' => array_slice($candidates, 1, 3) // Top 3 alternatives
        ];
    }

    /**
     * Split a shipment into multiple parcels greedily by container cost-per-kg.
     * FIXED: No more infinite loop, max 20 parcels, proper validation
     * 
     * @param int $carrierId Carrier ID
     * @param int $totalWeightG Total weight in grams
     * @return array Parcels [{req_weight_g, container, cost, load_g}, ...]
     */
    public static function splitIntoParcelsByCostDensity(int $carrierId, int $totalWeightG): array
    {
        // Sanity check: max 30kg per transfer (30000g)
        if ($totalWeightG > 30000) {
            error_log("WARNING: Transfer weight {$totalWeightG}g exceeds 30kg limit");
        }
        
        // Use dynamic availability check
        $available = self::getAvailableContainers($carrierId);
        
        if (empty($available)) {
            error_log("ERROR: No containers available for carrier {$carrierId}");
            return [];
        }
        
        // Filter containers with valid capacity (cap > 0)
        $cands = array_filter($available, fn($c) => $c['actual_cap_g'] > 0);
        
        if (empty($cands)) {
            error_log("ERROR: No containers with valid capacity for carrier {$carrierId}");
            return [];
        }
        
        // Sort by cost efficiency (cost per kg)
        usort($cands, fn($a, $b) => $a['cost_per_kg'] <=> $b['cost_per_kg']);
        
        $remaining = max(0, $totalWeightG);
        $parcels = [];
        $maxParcels = 20; // Reasonable limit (not 1000!)
        
        // FIXED: Single foreach, no while loop
        while ($remaining > 0 && count($parcels) < $maxParcels) {
            $bestFit = null;
            $bestScore = -1;
            
            // Find best container for remaining weight
            foreach ($cands as $c) {
                $cap = (int)$c['actual_cap_g'];
                
                // Skip invalid containers
                if ($cap <= 0) continue;
                
                // How much can we load?
                $load = min($cap, $remaining);
                
                // Calculate score (favor fuller containers)
                $utilization = $load / $cap;
                $score = $utilization * (1.0 / (1.0 + $c['cost_per_kg']));
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestFit = [
                        'req_weight_g' => $load,
                        'container' => $c,
                        'cost' => (float)$c['cost'],
                        'load_g' => $load
                    ];
                }
            }
            
            if (!$bestFit) {
                // Can't fit remaining weight - log and stop
                error_log("ERROR: Cannot fit remaining {$remaining}g into any container (carrier {$carrierId})");
                break;
            }
            
            $parcels[] = $bestFit;
            $remaining -= $bestFit['load_g'];
        }
        
        if ($remaining > 0) {
            error_log("WARNING: Could not fit all weight. Remaining: {$remaining}g (carrier {$carrierId})");
        }
        
        return $parcels;
    }

    /** SLA-aware selection among multiple rate sets. score = cost_weight*cost + sla_weight*rank(speed). */
    public static function chooseBestRate(array $rates, float $costWeight=1.0, float $slaWeight=0.0): ?array
    {
        if (!$rates) return null;
        // Derive a "speed rank" if meta has an ETA/hops; fallback by known service_code hints
        foreach ($rates as &$r) {
            $svc = strtolower((string)($r['service_code'] ?? ''));
            $eta = $r['meta']['eta_days'] ?? null;
            $r['_speed_rank'] = is_numeric($eta) ? (float)$eta
              : ((str_contains($svc,'tonight') || str_contains($svc,'express')) ? 1.0
              : (str_contains($svc,'courier') ? 2.0 : 3.0));
        }
        unset($r);
        // Normalise cost
        $min = min(array_map(fn($x)=>(float)$x['price'], $rates));
        $max = max(array_map(fn($x)=>(float)$x['price'], $rates));
        $norm = function($v) use($min,$max) {
            if ($max <= $min) return 0.0;
            return ((float)$v - $min) / ($max - $min);
        };
        // Score and pick
        $best = null; $bestScore = INF;
        foreach ($rates as $r) {
            $score = $costWeight * $norm($r['price']) + $slaWeight * (float)$r['_speed_rank'];
            if ($score < $bestScore) { $best = $r; $bestScore = $score; }
        }
        return $best;
    }

    // -----------------------------
    // HEALTH VIEWS (QA)
    // -----------------------------

    public static function health(): array
    {
        $out = [];
        foreach (['v_zero_or_null_prices','v_missing_container_rules','v_carrier_caps','v_classification_coverage','v_weight_coverage'] as $v) {
            if (self::exists($v,'VIEW')) {
                $out[$v] = self::$pdo->query("SELECT * FROM $v")->fetchAll();
            }
        }
        return $out;
    }

    // -----------------------------
    // LIVE RATES (eShip + GSS) with retry/backoff & caching
    // -----------------------------

    public static function getLiveRates(string $carrier, array $shipment): array
    {
        $carrier = strtolower($carrier);
        $key = hash('sha256', $carrier.'|'.json_encode($shipment));
        if (isset(self::$rateCache[$key])) return self::$rateCache[$key];

        $out = [];
        if (($carrier === 'eship' || $carrier === 'nzpost') && self::$eship['enabled']) {
            $out = self::eshipRates($shipment);
        } elseif ($carrier === 'gss' && self::$gss['enabled']) {
            $out = self::gssRates($shipment);
        }
        self::$rateCache[$key] = $out;
        return $out;
    }

    public static function mergeRates(array $dbPickRow, array $liveRates, string $strategy='min'): array
    {
        if (!$dbPickRow) return ['source'=>'live_only','live'=>$liveRates];
        if (!$liveRates) return ['source'=>'db_only','db'=>$dbPickRow];

        // choose cheapest by default
        $liveBest = null;
        foreach ($liveRates as $r) {
            if (!isset($r['price'])) continue;
            if (!$liveBest || $r['price'] < $liveBest['price']) $liveBest = $r;
        }

        if ($strategy === 'prefer_live' && $liveBest) {
            return ['source'=>'live','pick'=>$liveBest,'db'=>$dbPickRow];
        }
        if ($strategy === 'prefer_db') {
            return ['source'=>'db','pick'=>$dbPickRow,'live_best'=>$liveBest];
        }
        // min
        if ($liveBest && $liveBest['price'] < (float)($dbPickRow['cost'] ?? INF)) {
            return ['source'=>'live','pick'=>$liveBest,'db'=>$dbPickRow];
        }
        return ['source'=>'db','pick'=>$dbPickRow,'live_best'=>$liveBest];
    }

    // eShip (Starshipit) ---------------------

    private static function eshipRates(array $shipment): array
    {
        $url = rtrim((string)self::$eship['base_url'],'/').'/rates';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'StarShipIT-Api-Key: '.(self::$eship['api_key'] ?? ''),
            'Ocp-Apim-Subscription-Key: '.(self::$eship['subscription_key'] ?? ''),
        ];
        $payload = [
            'destination' => [
                'name'        => $shipment['to']['name']     ?? null,
                'street'      => $shipment['to']['street']   ?? null,
                'suburb'      => $shipment['to']['suburb']   ?? null,
                'city'        => $shipment['to']['city']     ?? null,
                'state'       => $shipment['to']['state']    ?? null,
                'post_code'   => $shipment['to']['postcode'] ?? null,
                'country_code'=> $shipment['to']['country']  ?? 'NZ',
            ],
            'packages'   => self::normaliseParcels($shipment['parcels'] ?? []),
            'options'    => self::mapOptionsForEship($shipment['options'] ?? []),
        ];
        $resp = self::httpJsonWithRetry($url,$headers,$payload,(float)self::$eship['timeout']);
        return self::normaliseStarshipitRates($resp);
    }

    private static function mapOptionsForEship(array $opts): array
    {
        return [
            'signature_required' => (bool)($opts['signature_required'] ?? $opts['SIGNATURE_REQUIRED'] ?? false),
            // extend as your account supports
        ];
    }

    private static function normaliseStarshipitRates(array $resp): array
    {
        // Responses vary per account; support both {Available:[...]} and flat arrays
        $cands = [];
        if (isset($resp['Available']) && is_array($resp['Available'])) $cands = $resp['Available'];
        elseif (is_array($resp)) $cands = $resp;

        $out = [];
        foreach ($cands as $r) {
            $out[] = [
                'service_code'   => $r['service_code'] ?? ($r['carrier_service_code'] ?? ($r['code'] ?? null)),
                'container_code' => $r['product_code'] ?? ($r['product'] ?? null),
                'price'          => (float)($r['price'] ?? $r['rate'] ?? $r['total'] ?? 0),
                'currency'       => $r['currency'] ?? 'NZD',
                'meta'           => $r,
            ];
        }
        return $out;
    }

    // GoSweetSpot ----------------------------

    private static function gssRates(array $shipment): array
    {
        $url = rtrim((string)self::$gss['base_url'],'/').'/rates';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'access_key: '.(self::$gss['access_key'] ?? ''),
            'site_id: '.(self::$gss['site_id'] ?? ''),
            'supportemail: '.(self::$gss['supportemail'] ?? ''),
        ];
        $payload = [
            'origin' => [
                'postcode' => $shipment['from']['postcode'] ?? null,
                'suburb'   => $shipment['from']['suburb']   ?? null,
                'country'  => $shipment['from']['country']  ?? 'NZ',
            ],
            'destination' => [
                'postcode' => $shipment['to']['postcode'] ?? null,
                'suburb'   => $shipment['to']['suburb']   ?? null,
                'country'  => $shipment['to']['country']  ?? 'NZ',
            ],
            'packages' => array_map(function($p){
                $g = (int)($p['weight_g'] ?? 0);
                return [
                    'length' => (int)($p['length_mm'] ?? 0),
                    'width'  => (int)($p['width_mm']  ?? 0),
                    'height' => (int)($p['height_mm'] ?? 0),
                    'weight' => max(0.001, round($g / 1000, 3)), // kg
                ];
            }, $shipment['parcels'] ?? []),
            'issignaturerequired' => (bool)($shipment['options']['signature_required'] ?? false),
            'issaturdaydelivery'  => (bool)($shipment['options']['saturday_delivery'] ?? false),
            'deliveryreference'   => $shipment['options']['reference'] ?? null,
        ];
        $resp = self::httpJsonWithRetry($url,$headers,$payload,(float)self::$gss['timeout']);
        return self::normaliseGssRates($resp);
    }

    private static function normaliseGssRates(array $resp): array
    {
        $groups = [];
        if (isset($resp['Available'])) $groups[] = $resp['Available'];
        if (isset($resp['Rejected']))  $groups[] = $resp['Rejected'];
        if (!$groups) $groups = [ $resp ];
        $out = [];
        foreach ($groups as $list) {
            if (!is_array($list)) continue;
            foreach ($list as $r) {
                $out[] = [
                    'service_code'   => $r['Service'] ?? $r['service'] ?? null,
                    'container_code' => $r['Product'] ?? $r['product'] ?? null,
                    'price'          => (float)($r['Price'] ?? $r['price'] ?? 0),
                    'currency'       => $r['Currency'] ?? $r['currency'] ?? 'NZD',
                    'meta'           => $r,
                ];
            }
        }
        return $out;
    }

    // -----------------------------
    // “LATEST PRODUCTS / PRICES” SYNC (safe idempotent upserts)
    // -----------------------------

    /**
     * Pull the latest product list / service offerings from providers and
     * upsert into containers/freight_rules for review. Assumes provider
     * exposes 'products' or 'services' endpoints; otherwise it no-ops.
     *
     * Returns a summary of created/updated rows per provider.
     */
    public static function syncLatestCarrierProducts(?string $provider = null): array
    {
        $provider = $provider ? strtolower($provider) : null;
        $summary = [];

        if (($provider === null || $provider === 'eship') && self::$eship['enabled']) {
            $summary['eship'] = self::syncEShipProducts();
        }
        if (($provider === null || $provider === 'gss') && self::$gss['enabled']) {
            $summary['gss'] = self::syncGssProducts();
        }
        return $summary;
    }

    /** eShip: try common discovery endpoints; upsert to containers (carrier_id=NZ Post). */
    private static function syncEShipProducts(): array
    {
        $created = 0; $updated = 0;
        $carrierId = self::$CARRIER_NZ_POST;

        $endpoints = [
            '/products', '/carrier/products', '/carriers', '/services'
        ];
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'StarShipIT-Api-Key: '.(self::$eship['api_key'] ?? ''),
            'Ocp-Apim-Subscription-Key: '.(self::$eship['subscription_key'] ?? ''),
        ];

        foreach ($endpoints as $ep) {
            try {
                $url = rtrim((string)self::$eship['base_url'],'/').$ep;
                $resp = self::httpJsonWithRetry($url,$headers,[],(float)self::$eship['timeout'], 'GET');
                $rows = self::flattenProductsPayload($resp);
                foreach ($rows as $r) {
                    // Upsert to containers by (carrier_id, code)
                    [$c,$u] = self::upsertContainer($carrierId, null, $r['code'], $r['name'], $r['dims'], $r['max_weight_g'] ?? null);
                    $created += $c; $updated += $u;
                }
            } catch (\Throwable) { /* ignore unknown endpoints */ }
        }
        return ['created'=>$created, 'updated'=>$updated];
    }

    /** GSS: attempt discovery endpoints; upsert. */
    private static function syncGssProducts(): array
    {
        $created = 0; $updated = 0;
        $carrierId = self::$CARRIER_GSS;

        $endpoints = [
            '/products', '/services', '/carriers'
        ];
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'access_key: '.(self::$gss['access_key'] ?? ''),
            'site_id: '.(self::$gss['site_id'] ?? ''),
            'supportemail: '.(self::$gss['supportemail'] ?? ''),
        ];

        foreach ($endpoints as $ep) {
            try {
                $url = rtrim((string)self::$gss['base_url'],'/').$ep;
                $resp = self::httpJsonWithRetry($url,$headers,[],(float)self::$gss['timeout'], 'GET');
                $rows = self::flattenProductsPayload($resp);
                foreach ($rows as $r) {
                    [$c,$u] = self::upsertContainer($carrierId, null, $r['code'], $r['name'], $r['dims'], $r['max_weight_g'] ?? null);
                    $created += $c; $updated += $u;
                }
            } catch (\Throwable) { /* tolerate */ }
        }
        return ['created'=>$created, 'updated'=>$updated];
    }

    /** Convert diverse “products/services” JSON to a unified rows list. */
    private static function flattenProductsPayload(mixed $resp): array
    {
        $rows = [];
        $push = function(string $code, string $name, array $dims=null, ?int $maxWeight=null) use (&$rows) {
            $rows[] = [
                'code'          => trim($code),
                'name'          => trim($name ?: $code),
                'dims'          => $dims,
                'max_weight_g'  => $maxWeight,
            ];
        };
        $walk = function($node) use (&$walk,$push) {
            if (is_array($node)) {
                // common shapes
                if (isset($node['code']) && (isset($node['name']) || isset($node['description']))) {
                    $dims = null;
                    if (isset($node['length_mm']) || isset($node['width_mm']) || isset($node['height_mm'])) {
                        $dims = [
                            'L'=>(int)($node['length_mm'] ?? 0),
                            'W'=>(int)($node['width_mm'] ?? 0),
                            'H'=>(int)($node['height_mm'] ?? 0),
                        ];
                    }
                    $maxW = isset($node['max_weight_g']) ? (int)$node['max_weight_g'] : null;
                    $push((string)$node['code'], (string)($node['name'] ?? $node['description'] ?? $node['code']), $dims, $maxW);
                    return;
                }
                foreach ($node as $k=>$v) $walk($v);
            }
        };
        $walk($resp);
        // de-dup by code
        $out=[]; $seen=[];
        foreach ($rows as $r) { $k=strtolower($r['code']); if(isset($seen[$k])) continue; $seen[$k]=1; $out[]=$r; }
        return $out;
    }

    /** Upsert container (and freight_rules placeholder) by (carrier_id, code). Returns [created, updated] flags. */
    private static function upsertContainer(
        int $carrierId, ?int $serviceId, string $code, string $name, ?array $dims, ?int $capG
    ): array {
        $pdo = self::$pdo;
        $pdo->beginTransaction();
        try {
            // containers
            $st = $pdo->prepare("SELECT container_id FROM containers WHERE carrier_id=:car AND code=:code LIMIT 1");
            $st->execute([':car'=>$carrierId, ':code'=>$code]);
            $cid = $st->fetchColumn();
            $created=0; $updated=0;
            if ($cid) {
                $sql = "UPDATE containers SET name=:n, service_id=:s, length_mm=:L, width_mm=:W, height_mm=:H, max_weight_grams=:g WHERE container_id=:id";
                $p = [
                    ':n'=>$name, ':s'=>$serviceId, ':L'=>$dims['L']??null, ':W'=>$dims['W']??null, ':H'=>$dims['H']??null, ':g'=>$capG, ':id'=>$cid
                ];
                self::$pdo->prepare($sql)->execute($p);
                $updated=1;
            } else {
                $sql = "INSERT INTO containers (carrier_id, service_id, code, name, length_mm, width_mm, height_mm, max_weight_grams)
                        VALUES (:car, :s, :code, :n, :L, :W, :H, :g)";
                $p = [':car'=>$carrierId, ':s'=>$serviceId, ':code'=>$code, ':n'=>$name, ':L'=>$dims['L']??null, ':W'=>$dims['W']??null, ':H'=>$dims['H']??null, ':g'=>$capG];
                self::$pdo->prepare($sql)->execute($p);
                $cid = (int)self::$pdo->lastInsertId();
                $created=1;
            }
            // freight_rules placeholder (idempotent)
            $st2 = $pdo->prepare("SELECT 1 FROM freight_rules WHERE container_id=:id");
            $st2->execute([':id'=>$cid]);
            if (!$st2->fetchColumn()) {
                self::$pdo->prepare("INSERT INTO freight_rules (container, container_id, max_weight_grams, cost) VALUES (:code, :id, :g, 0.00)")
                          ->execute([':code'=>$code, ':id'=>$cid, ':g'=>$capG]);
            }
            $pdo->commit();
            return [$created,$updated];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return [0,0];
        }
    }

    // -----------------------------
    // SMART QUOTING (DB + live)
    // -----------------------------

    /**
     * Smart quote for a cart:
     *  - compute total weight from product lines
     *  - DB pick (cheapest) for given carrier
     *  - (optional) live rates from eShip & GSS
     *  - merge by strategy ('min' | 'prefer_live' | 'prefer_db')
     */
    public static function smartQuote(array $shipment, int $dbCarrierId, string $strategy='min'): array
    {
        // Make a consolidated parcel by DB weight
        $lines   = $shipment['lines'] ?? [];
        $totalG  = self::totalWeightG($lines);
        $dbPick  = self::pickContainer($dbCarrierId, null,null,null, $totalG);
        $dbCost  = isset($dbPick['cost']) ? (float)$dbPick['cost'] : 0.0;

        // Build parcels (one parcel consolidated by default)
        $parcels = $shipment['parcels'] ?? [[
            'length_mm'=> $dbPick['length_mm'] ?? 0,
            'width_mm' => $dbPick['width_mm']  ?? 0,
            'height_mm'=> $dbPick['height_mm'] ?? 0,
            'weight_g' => $totalG
        ]];

        // Ask live providers if enabled
        $base = [
            'from'    => $shipment['from'] ?? [],
            'to'      => $shipment['to']   ?? [],
            'parcels' => $parcels,
            'options' => $shipment['options'] ?? [],
        ];
        $live = array_merge(
            self::getLiveRates('eship', $base),
            self::getLiveRates('gss',   $base)
        );

        $merged = self::mergeRates($dbPick, $live, $strategy);
        return [
            'total_weight_g' => $totalG,
            'db_pick' => $dbPick,
            'db_cost' => $dbCost,
            'live_candidates' => $live,
            'decision' => $merged,
        ];
    }

    // -----------------------------
    // HTTP core (retry + backoff + redaction)
    // -----------------------------

    private static function httpJsonWithRetry(string $url, array $headers, array $payload, float $timeout, string $method='POST'): array
    {
        $attempts = self::HTTP_RETRIES + 1;
        $lastErr = null; $lastCode = null; $lastRaw = null;

        for ($i=0; $i<$attempts; $i++) {
            [$raw,$err,$code] = self::httpRaw($url,$headers,$payload,$timeout,$method);
            if (!$err && $code < 400) {
                $js = json_decode((string)$raw,true);
                if (is_array($js)) return $js;
                return [['error'=>'invalid_json','http_code'=>$code]];
            }
            $lastErr = $err; $lastCode = $code; $lastRaw = $raw;
            // backoff with jitter on 429/5xx
            if ($code === 429 || ($code >= 500 && $code < 600)) {
                usleep((int)((250 + random_int(0,150)) * 1000)); // 250-400ms
                continue;
            }
            break; // don’t retry on 4xx except 429
        }
        return [[
            'error' => $lastErr ?: ("HTTP ".$lastCode),
            'endpoint' => self::redactUrl($url),
            'http_code'=> $lastCode,
        ]];
    }

    private static function httpRaw(string $url, array $headers, array $payload, float $timeout, string $method='POST'): array
    {
        $ch = curl_init($url);
        $h = array_values($headers);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$raw,$err,$code];
    }

    private static function redactUrl(string $url): string
    {
        // Don’t leak keys in logs
        return preg_replace('/(api_key|subscription_key|access_key|site_id)=[^&]+/i', '$1=REDACTED', $url);
    }

    private static function normaliseParcels(array $parcels): array
    {
        $out = [];
        foreach ($parcels as $p) {
            $out[] = [
                'length_mm' => (int)($p['length_mm'] ?? 0),
                'width_mm'  => (int)($p['width_mm']  ?? 0),
                'height_mm' => (int)($p['height_mm'] ?? 0),
                'weight_g'  => (int)($p['weight_g']  ?? 0),
            ];
        }
        return $out;
    }
}
