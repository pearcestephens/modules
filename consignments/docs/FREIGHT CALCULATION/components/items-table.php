<?php
/**
 * Items Table Component
 * 
 * Product items table with inline editing
 * 
 * Required variables:
 * @var array  $items            Array of transfer items
 * @var string $toLbl            Destination outlet name
 * @var int    $txId             Transfer ID
 * @var array  $sourceStockMap   Product ID => stock quantity map
 * @var int    $plannedSum       Total planned quantity
 * @var int    $countedSum       Total counted quantity
 * @var string $diffLabel        Difference label
 * @var float  $estimatedWeight  Total weight in kg
 * @var int    $accuracy         Accuracy percentage
 */

if (!isset($items, $toLbl, $txId)) {
    throw new \RuntimeException('Items table component requires: $items, $toLbl, $txId');
}

$plannedSum = $plannedSum ?? 0;
$countedSum = $countedSum ?? 0;
$diffLabel = $diffLabel ?? '0';
$estimatedWeight = $estimatedWeight ?? 0;
$accuracy = $accuracy ?? 0;
$sourceStockMap = $sourceStockMap ?? [];

// Helper function availability check
if (!function_exists('_first')) {
    function _first(...$vals) {
        foreach ($vals as $v) {
            if (is_string($v) && $v !== '') return $v;
            if (is_numeric($v)) return $v;
            if (is_array($v) && !empty($v)) return $v;
        }
        return null;
    }
}

if (!function_exists('_tfx_product_tag')) {
    function _tfx_product_tag($txId, $lineNum) {
        return htmlspecialchars("{$txId}-{$lineNum}", ENT_QUOTES, 'UTF-8');
    }
}

$plannedWeightTotalGrams = 0;
$actualWeightTotalGrams = 0;

if (!empty($items)) {
    foreach ($items as $weightProbe) {
        $unitWeightG = (int)_first(
            $weightProbe['derived_unit_weight_grams'] ?? null,
            $weightProbe['avg_weight_grams'] ?? null,
            $weightProbe['product_weight_grams'] ?? null,
            $weightProbe['weight_g'] ?? null,
            $weightProbe['unit_weight_g'] ?? null,
            $weightProbe['category_avg_weight_grams'] ?? null,
            $weightProbe['category_weight_grams'] ?? null,
            $weightProbe['cat_weight_g'] ?? null,
            100
        );

        $plannedQty = (int)_first(
            $weightProbe['qty_requested'] ?? null,
            $weightProbe['planned_qty'] ?? 0
        );
        
        $countedQty = (int)_first(
            $weightProbe['counted_qty'] ?? null,
            0
        );

        if ($plannedQty > 0 && $unitWeightG > 0) {
            $plannedWeightTotalGrams += $plannedQty * $unitWeightG;
        }
        
        if ($countedQty > 0 && $unitWeightG > 0) {
            $actualWeightTotalGrams += $countedQty * $unitWeightG;
        }
    }
}

$plannedWeightKg = $plannedWeightTotalGrams > 0 ? ($plannedWeightTotalGrams / 1000) : 0;
$initialActualWeightKg = $actualWeightTotalGrams > 0 ? ($actualWeightTotalGrams / 1000) : 0;
$initialActualWeightGrams = $actualWeightTotalGrams;
?>

<section class="card mb-4">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <?php include __DIR__ . '/autosave-indicator.php'; ?>
        
        <div class="d-flex align-items-center">
            <button type="button" 
                    id="autofillBtn" 
                    class="btn btn-outline-primary btn-sm mr-2"
                    data-action="autofill">
                <i class="fa fa-magic mr-1"></i>Autofill
            </button>
            <button type="button" 
                    id="resetBtn" 
                    class="btn btn-outline-secondary btn-sm"
                    data-action="reset">
                <i class="fa fa-undo mr-1"></i>Reset
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <div id="lineAnnouncement" 
             class="sr-only" 
             aria-live="polite" 
             aria-atomic="true" 
             style="position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden;">
            Line updates announced here.
        </div>

        <div class="table-responsive" id="transferItemsTableWrapper">
            <table class="table table-sm table-striped mb-0" id="transferItemsTable"
                   data-planned-weight-kg="<?= number_format($plannedWeightKg, 3, '.', '') ?>"
                   data-planned-weight-grams="<?= (int)$plannedWeightTotalGrams ?>"
                   data-actual-weight-kg="<?= number_format($initialActualWeightKg, 3, '.', '') ?>"
                   data-actual-weight-grams="<?= $initialActualWeightGrams ?>">
                <colgroup>
                    <col style="width:52px; min-width:52px; max-width:52px;">
                    <col style="width:38%;">
                    <col style="width:72px;">
                    <col style="width:60px;">
                    <col style="width:78px;">
                    <col style="width:64px;">
                    <col style="width:50px;">
                </colgroup>
                <thead class="thead-light">
                    <tr>
                        <th class="text-center"></th>
                        <th style="padding-left:4px;">Product</th>
                        <th class="text-center">Source Stock</th>
                        <th class="text-center">Planned</th>
                        <th class="text-center">Counted</th>
                        <th class="text-center">To</th>
                        <th class="text-center">Tag</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fa fa-exclamation-triangle mb-2"></i><br>
                            No items found in this transfer.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $rowNum = 1;
                    
                    foreach ($items as $item):
                        $itemId     = (int)_first($item['id'] ?? null, $rowNum);
                        $productId  = (string)_first($item['product_id'] ?? null, $item['vend_product_id'] ?? null, '');
                        $plannedQty = (int)_first($item['qty_requested'] ?? null, $item['planned_qty'] ?? 0);
                        $countedQty = (int)($item['counted_qty'] ?? 0);
                        $stockQty   = ($productId !== '' && isset($sourceStockMap[$productId])) 
                                      ? (int)$sourceStockMap[$productId] 
                                      : 0;
                        
                        // Unit weight resolution
                        $unitWeightG = (int)_first(
                            $item['derived_unit_weight_grams'] ?? null,
                            $item['avg_weight_grams'] ?? null,
                            $item['product_weight_grams'] ?? null,
                            $item['weight_g'] ?? null,
                            $item['unit_weight_g'] ?? null,
                            $item['category_avg_weight_grams'] ?? null,
                            $item['category_weight_grams'] ?? null,
                            $item['cat_weight_g'] ?? null,
                            100
                        );
                        
                        $weightSource = (string)_first(
                            $item['weight_source'] ?? null,
                            (isset($item['avg_weight_grams']) || isset($item['product_weight_grams']) || 
                             isset($item['weight_g']) || isset($item['unit_weight_g']))
                                ? 'product'
                                : ((isset($item['category_avg_weight_grams']) || 
                                    isset($item['category_weight_grams']) || 
                                    isset($item['cat_weight_g'])) ? 'category' : 'default')
                        );
                        
                        // Product image
                        $imageUrl = '';
                        foreach (['image_url', 'image_thumbnail_url', 'product_image_url', 
                                  'vend_image_url', 'thumbnail_url', 'image'] as $field) {
                            if (!empty($item[$field])) {
                                $imageUrl = $item[$field];
                                break;
                            }
                        }
                        
                        $hasImage = !empty($imageUrl)
                                    && $imageUrl !== 'https://secure.vendhq.com/images/placeholder/product/no-image-white-original.png'
                                    && filter_var($imageUrl, FILTER_VALIDATE_URL);
                    ?>
                    <tr id="item-row-<?= $itemId ?>"
                        class="pack-item-row"
                        data-item-id="<?= $itemId ?>"
                        data-product-id="<?= htmlspecialchars($productId, ENT_QUOTES) ?>"
                        data-planned-qty="<?= $plannedQty ?>"
                        data-source-stock="<?= $stockQty ?>"
                        data-unit-weight-g="<?= $unitWeightG ?>"
                        data-weight-source="<?= htmlspecialchars($weightSource, ENT_QUOTES) ?>">
                        
                        <!-- Image -->
                        <td class="text-center align-middle" style="width:52px; min-width:52px; max-width:52px; padding:0 2px;">
                            <?php if ($hasImage): ?>
                                <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES) ?>"
                                     class="product-thumb"
                                     data-action="show-image"
                                     data-src="<?= htmlspecialchars($imageUrl, ENT_QUOTES) ?>"
                                     data-name="<?= htmlspecialchars($item['product_name'] ?? 'Product', ENT_QUOTES) ?>"
                                     style="margin: 5px 0 5px 0; width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 1px solid #d1d5db; cursor: pointer;"
                                     alt="Product image">
                            <?php endif; ?>
                            <div class="product-thumb-placeholder" 
                                 style="width: 48px; height: 48px; background: #f8f9fa; border: 1px solid #d1d5db; border-radius: 8px; display: <?= $hasImage ? 'none' : 'flex' ?>; align-items: center; justify-content: center;">
                                <i class="fa fa-image text-muted" style="font-size: 18px;"></i>
                            </div>
                        </td>
                        
                        <!-- Product Name -->
                        <td class="align-middle" style="padding-left:0px; padding-right: 8px;">
                            <?php
                                $productName = (string)_first($item['product_name'] ?? null, $item['name'] ?? 'Product');
                                $productSku = (string)_first(
                                    $item['product_sku'] ?? null,
                                    $item['sku'] ?? null,
                                    $item['handle'] ?? null,
                                    ''
                                );
                                
                                // Weight and dimension data
                                $unitWeightKg = $unitWeightG / 1000;
                                $lineWeightG = $countedQty > 0 ? ($countedQty * $unitWeightG) : ($plannedQty * $unitWeightG);
                                $lineWeightKg = $lineWeightG / 1000;
                                
                                // Get dimensions from item
                                $lengthMm = (int)_first($item['length_mm'] ?? null, $item['product_length_mm'] ?? null, 0);
                                $widthMm = (int)_first($item['width_mm'] ?? null, $item['product_width_mm'] ?? null, 0);
                                $heightMm = (int)_first($item['height_mm'] ?? null, $item['product_height_mm'] ?? null, 0);
                                
                                // Calculate cubic measurement
                                $cubicCm = 0;
                                $cubicM = 0;
                                if ($lengthMm > 0 && $widthMm > 0 && $heightMm > 0) {
                                    $lengthCm = $lengthMm / 10;
                                    $widthCm = $widthMm / 10;
                                    $heightCm = $heightMm / 10;
                                    $cubicCm = $lengthCm * $widthCm * $heightCm;
                                    $cubicM = $cubicCm / 1000000;
                                }
                                
                                // Weight source display name
                                $sourceDisplay = match($weightSource) {
                                    'curated' => 'Verified Weight',
                                    'dimension' => 'Calculated (Volumetric)',
                                    'historical' => 'Historical Average',
                                    'category_historical' => 'Category Average',
                                    'category_default' => 'Category Default',
                                    'fallback' => 'Estimated',
                                    default => 'Estimated'
                                };
                                
                                // Tooltip content
                                $tooltipParts = [];
                                $tooltipParts[] = "<strong>Unit Weight:</strong> " . number_format($unitWeightKg, 3) . " kg (" . number_format($unitWeightG) . "g)";
                                $tooltipParts[] = "<strong>Source:</strong> " . $sourceDisplay;
                                
                                $qtyForCalc = $countedQty > 0 ? $countedQty : $plannedQty;
                                $tooltipParts[] = "<strong>Quantity:</strong> " . $qtyForCalc . " units";
                                $tooltipParts[] = "<strong>Line Total Weight:</strong> " . number_format($lineWeightKg, 2) . " kg (" . number_format($lineWeightG) . "g)";
                                
                                if ($lengthMm > 0 && $widthMm > 0 && $heightMm > 0) {
                                    $tooltipParts[] = "<strong>Dimensions:</strong> " . $lengthMm . "×" . $widthMm . "×" . $heightMm . " mm";
                                    $tooltipParts[] = "<strong>Cubic Measurement:</strong> " . number_format($cubicCm, 0) . " cm³ (" . number_format($cubicM, 6) . " m³)";
                                    
                                    // Volumetric weight
                                    $volumetricKg = $cubicCm / 5000;
                                    $tooltipParts[] = "<strong>Volumetric Weight:</strong> " . number_format($volumetricKg, 2) . " kg";
                                } else {
                                    $tooltipParts[] = "<em>No dimensions available</em>";
                                }
                                
                                $tooltipContent = implode("<br>", $tooltipParts);
                            ?>
                            <span class="product-title"><?= htmlspecialchars($productName, ENT_QUOTES) ?></span>
                            <div class="product-subtitle text-muted d-flex align-items-center" style="gap: 8px;">
                                <?php if ($productSku !== ''): ?>
                                    <span>SKU: <?= htmlspecialchars($productSku, ENT_QUOTES) ?></span>
                                    <span class="text-muted">|</span>
                                <?php endif; ?>
                                <span class="product-weight-badge"
                                      data-toggle="tooltip"
                                      data-html="true"
                                      data-placement="right"
                                      title="<?= htmlspecialchars($tooltipContent, ENT_QUOTES) ?>"
                                      style="cursor: help; font-weight: 500; color: #6c757d;">
                                    <i class="fa fa-balance-scale" style="font-size: 11px; opacity: 0.7;"></i>
                                    <?= number_format($unitWeightKg, 2) ?>kg ea
                                </span>
                            </div>
                        </td>
                        
                        <!-- Source Stock -->
                        <td class="text-center align-middle">
                            <?php if ($stockQty <= 0): ?>
                                <span class="text-danger font-weight-bold">0</span>
                                <div class="small text-danger">
                                    <i class="fa fa-exclamation-triangle" title="Out of Stock"></i>
                                </div>
                            <?php elseif ($stockQty < $plannedQty): ?>
                                <span class="text-warning font-weight-bold"><?= $stockQty ?></span>
                                <div class="small text-warning">
                                    <i class="fa fa-exclamation-triangle"></i> Low
                                </div>
                            <?php else: ?>
                                <span class="text-success font-weight-bold"><?= $stockQty ?></span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Planned -->
                        <td class="text-center align-middle">
                            <span class="font-weight-bold" data-planned="<?= $plannedQty ?>">
                                <?= $plannedQty ?>
                            </span>
                        </td>
                        
                        <!-- Counted (Input) -->
                        <td class="text-center align-middle counted-td">
                            <input type="number"
                                   class="form-control form-control-sm tfx-num qty-input"
                                   name="counted_qty[<?= $itemId ?>]"
                                   id="counted-<?= $itemId ?>"
                                   min="0" 
                                   step="1"
                                   <?= $countedQty > 0 ? 'value="' . $countedQty . '"' : 'placeholder="0"' ?>
                                   data-item-id="<?= $itemId ?>"
                                   data-planned="<?= $plannedQty ?>"
                                   data-source-stock="<?= $stockQty ?>"
                                   style="text-align: center;">
                        </td>
                        
                        <!-- Destination -->
                        <td class="text-center align-middle">
                            <?= htmlspecialchars($toLbl, ENT_QUOTES) ?>
                        </td>
                        
                        <!-- Tag -->
                        <td class="text-center align-middle mono" title="Product Tag">
                            <?= _tfx_product_tag((string)$txId, $rowNum) ?>
                        </td>
                    </tr>
                    <?php 
                        $rowNum++; 
                    endforeach; 
                    ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer border-top px-3 py-2">
            <div class="d-flex justify-content-between align-items-center" style="font-size:0.875rem;">
                <div class="text-left">
                    Planned <span class="font-weight-semibold" id="plannedTotalFooter"><?= (int)$plannedSum ?></span>
                    <span class="mx-1">•</span>
                    Counted <span class="font-weight-semibold" id="countedTotalFooter"><?= (int)$countedSum ?></span>
                    <span class="mx-1">•</span>
                    Difference <span class="font-weight-semibold" id="diffTotalFooter"><?= htmlspecialchars($diffLabel, ENT_QUOTES) ?></span>
                    <span class="mx-1">•</span>
                    Accuracy <span class="font-weight-semibold" id="accuracyFooter"><?= (int)$accuracy ?>%</span>
                </div>
                <div class="text-right">
                    Planned <span class="font-weight-semibold" id="plannedWeightFooterKgValue"><?= number_format($plannedWeightKg, 2) ?></span> kg
                    <span class="mx-1">/</span>
                    Actual <span class="font-weight-semibold" id="totalWeightFooterKgValue"><?= number_format($initialActualWeightKg, 2) ?></span> kg
                </div>
            </div>
            <span id="plannedWeightFooter" class="d-none"
                  data-total-weight-kg="<?= number_format($plannedWeightKg, 3, '.', '') ?>"
                  data-total-weight-grams="<?= (int)$plannedWeightTotalGrams ?>"></span>
            <span id="totalWeightFooter" class="d-none"
                  data-total-weight-kg="<?= number_format($initialActualWeightKg, 3, '.', '') ?>"
                  data-total-weight-grams="<?= $initialActualWeightGrams ?>"></span>
        </div>
    </div>
</section>
