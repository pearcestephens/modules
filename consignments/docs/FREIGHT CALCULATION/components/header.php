<?php
/**
 * CIS Transfer Header Component
 * Professional business-style header matching CIS design system
 * 
 * Required variables:
 * @var int    $txId              Transfer ID
 * @var string $fromLbl           From outlet name
 * @var string $toLbl             To outlet name
 * @var array  $fromOutlet        From outlet data (optional)
 * @var array  $toOutlet          To outlet data (optional)

 * @var string $transferCreatedAt Transfer creation timestamp (optional)
 */

if (!isset($txId, $fromLbl, $toLbl)) {
    throw new \RuntimeException('Transfer header requires: $txId, $fromLbl, $toLbl');
}

// Extract data
$transferCreatedAt = $transferCreatedAt ?? null;
$fromOutlet = $fromOutlet ?? [];
$toOutlet = $toOutlet ?? [];



// Extract outlet information - FULL DATA (matching TransfersService::getOutletMeta keys)
$fromStreetNumber = $fromOutlet['street_number'] ?? '';
$fromStreet = $fromOutlet['street'] ?? '';
$fromAddress1 = $fromOutlet['address1'] ?? '';
$fromAddress2 = $fromOutlet['address2'] ?? '';
$fromSuburb = $fromOutlet['suburb'] ?? '';
$fromCity = $fromOutlet['city'] ?? '';
$fromPostcode = $fromOutlet['postcode'] ?? '';
$fromPhone = $fromOutlet['phone'] ?? '';
$fromEmail = $fromOutlet['email'] ?? '';
$fromStoreCode = $fromOutlet['store_code'] ?? '';
$fromIsWarehouse = !empty($fromOutlet['is_warehouse']) && $fromOutlet['is_warehouse'] == 1;
$fromGoogleRating = $fromOutlet['google_rating'] ?? $fromOutlet['rating'] ?? null;
$fromGoogleReviews = $fromOutlet['google_reviews'] ?? $fromOutlet['review_count'] ?? null;

// Build FROM full address (with de-duplication logic)
$fromAddressParts = [];
if (!empty(trim($fromStreetNumber . ' ' . $fromStreet))) {
    $fromAddressParts[] = trim($fromStreetNumber . ' ' . $fromStreet);
}
if (!empty($fromAddress1)) $fromAddressParts[] = $fromAddress1;
if (!empty($fromAddress2)) $fromAddressParts[] = $fromAddress2;
// Skip suburb if it matches city (avoid duplication)
if (!empty($fromSuburb) && (empty($fromCity) || strtolower(trim($fromSuburb)) !== strtolower(trim($fromCity)))) {
    $fromAddressParts[] = $fromSuburb;
}
if (!empty($fromCity)) $fromAddressParts[] = $fromCity;
if (!empty($fromPostcode)) $fromAddressParts[] = $fromPostcode;
$fromFullAddress = trim(implode(', ', $fromAddressParts));

// Extract TO outlet information - FULL DATA (matching TransfersService::getOutletMeta keys)
$toStreetNumber = $toOutlet['street_number'] ?? '';
$toStreet = $toOutlet['street'] ?? '';
$toAddress1 = $toOutlet['address1'] ?? '';
$toAddress2 = $toOutlet['address2'] ?? '';
$toSuburb = $toOutlet['suburb'] ?? '';
$toCity = $toOutlet['city'] ?? '';
$toPostcode = $toOutlet['postcode'] ?? '';
$toPhone = $toOutlet['phone'] ?? '';
$toEmail = $toOutlet['email'] ?? '';
$toStoreCode = $toOutlet['store_code'] ?? '';
$toIsWarehouse = !empty($toOutlet['is_warehouse']) && $toOutlet['is_warehouse'] == 1;
$toGoogleRating = $toOutlet['google_rating'] ?? $toOutlet['rating'] ?? null;
$toGoogleReviews = $toOutlet['google_reviews'] ?? $toOutlet['review_count'] ?? null;

// Build TO full address (with de-duplication logic)
$toAddressParts = [];
if (!empty(trim($toStreetNumber . ' ' . $toStreet))) {
    $toAddressParts[] = trim($toStreetNumber . ' ' . $toStreet);
}
if (!empty($toAddress1)) $toAddressParts[] = $toAddress1;
if (!empty($toAddress2)) $toAddressParts[] = $toAddress2;
// Skip suburb if it matches city (avoid "Gisborne, Gisborne")
if (!empty($toSuburb) && (empty($toCity) || strtolower(trim($toSuburb)) !== strtolower(trim($toCity)))) {
    $toAddressParts[] = $toSuburb;
}
if (!empty($toCity)) $toAddressParts[] = $toCity;
if (!empty($toPostcode)) $toAddressParts[] = $toPostcode;
$toFullAddress = trim(implode(', ', $toAddressParts));

// Calculate time since creation
$createdTimeAgo = 'Just now';
$createdDateTime = '';
if ($transferCreatedAt) {
    try {
        $createdDate = new DateTime($transferCreatedAt);
        $createdDateTime = $createdDate->format('M j, Y @ g:i A');
        $now = new DateTime();
        $interval = $createdDate->diff($now);
        
        if ($interval->y > 0) {
            $createdTimeAgo = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
        } elseif ($interval->m > 0) {
            $createdTimeAgo = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
        } elseif ($interval->d > 0) {
            $createdTimeAgo = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
        } elseif ($interval->h > 0) {
            $createdTimeAgo = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        } elseif ($interval->i > 0) {
            $createdTimeAgo = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
        }
    } catch (Exception $e) {
        // Fallback
    }
}

// Shipment facts (from pack.php variables)
$totalWeightKg = $estimatedWeight ?? 0; // Already in kg from pack.php
$itemCount = max($countedSum ?? 0, $plannedSum ?? 0);
$uniqueSkuCount = $shipmentInsights['unique_skus'] ?? 0;
$billableWeightKg = $shipmentInsights['billable_weight_kg'] ?? $totalWeightKg;

// Calculate estimated containers based on weight
$estimatedBoxes = null;
$estimatedSatchels = null;

if ($totalWeightKg > 0) {
    if ($totalWeightKg < 3) {
        $estimatedSatchels = 1;
    } elseif ($totalWeightKg < 10) {
        $estimatedSatchels = (int)ceil($totalWeightKg / 5);
    } else {
        $estimatedBoxes = (int)ceil($totalWeightKg / 10);
    }
}

// ===== DESTINATION STORE STATUS LOGIC =====

// Get destination outlet opening hours
$toOutletId = $toOutlet['id'] ?? null;
$toOpeningHours = [];
$toIsOpenNow = false;
$toClosesIn = '';
$toNextOpen = '';
$toIsTemporarilyClosed = false;

if ($toOutletId && isset($db)) {
    try {
        // FIRST: Check if outlet is in closed notifications table (temporary closures)
        $closedCheckStmt = $db->prepare("
            SELECT id, created_at 
            FROM vend_outlets_closed_notifications 
            WHERE outlet_id = ? 
            AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");
        if ($closedCheckStmt) {
            $closedCheckStmt->bind_param('s', $toOutletId);
            $closedCheckStmt->execute();
            $closedResult = $closedCheckStmt->get_result();
            
            if ($closedRow = $closedResult->fetch_assoc()) {
                $toIsTemporarilyClosed = true;
                $toIsOpenNow = false;
                $toNextOpen = 'Temporarily closed today';
                error_log("[Header Debug] Outlet {$toOutletId} marked as temporarily closed on " . $closedRow['created_at']);
            }
            $closedCheckStmt->close();
        }
        
        // SECOND: Only check regular hours if NOT temporarily closed
        if (!$toIsTemporarilyClosed) {
            // Query opening hours using UUID
            $hoursStmt = $db->prepare("SELECT * FROM vend_outlets_open_hours WHERE outlet_id = ? LIMIT 1");
            if ($hoursStmt) {
                $hoursStmt->bind_param('s', $toOutletId);
                $hoursStmt->execute();
                $hoursResult = $hoursStmt->get_result();
                
                error_log("[Header Debug] Querying hours for outlet: {$toOutletId}");
                
                if ($hoursRow = $hoursResult->fetch_assoc()) {
                    $toOpeningHours = $hoursRow;
                    
                    // Determine if store is open now (NZ timezone)
                    $now = new DateTime('now', new DateTimeZone('Pacific/Auckland'));
                    $dayOfWeek = strtolower($now->format('l')); // monday, tuesday, etc.
                    $currentTime = $now->format('H:i:s');
                    
                    $openKey = $dayOfWeek . '_open';
                    $closeKey = $dayOfWeek . '_close';
                    
                    $openTime = $toOpeningHours[$openKey] ?? null;
                    $closeTime = $toOpeningHours[$closeKey] ?? null;
                    
                    // Normalize time format (add seconds if missing)
                    if ($openTime && strlen($openTime) === 5) {
                        $openTime .= ':00';
                    }
                    if ($closeTime && strlen($closeTime) === 5) {
                        $closeTime .= ':00';
                    }
                    
                    // Debug: Log the values
                    error_log("[Header Debug] Day: {$dayOfWeek}, Current: {$currentTime}, Open: {$openTime}, Close: {$closeTime}");
                    
                    if ($openTime && $closeTime && $openTime !== '00:00:00' && $closeTime !== '00:00:00') {
                        // Convert times to comparable format
                        $currentTimeSeconds = strtotime($currentTime);
                        $openTimeSeconds = strtotime($openTime);
                        $closeTimeSeconds = strtotime($closeTime);
                        
                        $toIsOpenNow = ($currentTimeSeconds >= $openTimeSeconds && $currentTimeSeconds < $closeTimeSeconds);
                        
                        error_log("[Header Debug] Open now? " . ($toIsOpenNow ? 'YES' : 'NO'));
                        
                        if ($toIsOpenNow) {
                            // Calculate time until closing
                            $closeDateTime = DateTime::createFromFormat('H:i:s', $closeTime, new DateTimeZone('Pacific/Auckland'));
                            $nowDateTime = DateTime::createFromFormat('H:i:s', $currentTime, new DateTimeZone('Pacific/Auckland'));
                            if ($closeDateTime && $nowDateTime) {
                                $interval = $nowDateTime->diff($closeDateTime);
                                $hours = $interval->h;
                                $minutes = $interval->i;
                                
                                error_log("[Header Debug] Closes in: {$hours}h {$minutes}m");
                                
                                if ($hours > 0) {
                                    $toClosesIn = "Closes in {$hours}h {$minutes}m";
                                } elseif ($minutes > 0) {
                                    $toClosesIn = "Closes in {$minutes}m";
                                } else {
                                    $toClosesIn = "Closing soon";
                                }
                            } else {
                                // Fallback if DateTime::createFromFormat fails
                                error_log("[Header Debug] DateTime creation failed for close time");
                                $toClosesIn = "Open until " . date('g:i A', strtotime($closeTime));
                            }
                        } else {
                            // Find next opening time
                            $toNextOpen = "Opens " . date('g:i A', strtotime($openTime));
                        }
                    } else {
                        $toNextOpen = 'Closed today';
                    }
                } else {
                    $toNextOpen = 'Hours not configured';
                }
                $hoursStmt->close();
            }
        }
    } catch (Exception $e) {
        error_log("[Header Debug] Hours check failed: " . $e->getMessage());
        $toNextOpen = 'Hours unavailable';
    }
} else {
    $toNextOpen = 'Hours unavailable';
}

// Count low stock products at destination
$lowStockCount = 0;
$totalProductsInTransfer = 0;
$lowStockProducts = []; // NEW: List of actual low stock products

// NEW QUERIES FOR STATUS CARD - Get unique actionable information
$transferNotes = [];
$shipmentInfo = null;
$recentTransferCount = 0;
$lastTransferDate = null;
$staffOnDuty = [];
$transferValue = 0; // NEW: Total $ value
$completionPercent = 0; // NEW: Packed vs planned

if ($toOutletId && isset($txId) && isset($db)) {
    try {
        // 1. Get LOW STOCK PRODUCTS with names (not just count!)
        $lowStockStmt = $db->prepare("
            SELECT p.name, p.sku, 
                   COALESCE(SUM(tsi.quantity), 0) as transfer_qty,
                   COALESCE(inv.inventory_level, 0) as current_stock
            FROM vend_products p
            INNER JOIN transfers t ON t.id = ?
            LEFT JOIN (
                SELECT product_id, SUM(quantity) as quantity
                FROM (
                    SELECT product_id, planned_quantity as quantity FROM transfer_stock_planned WHERE transfer_id = ? AND deleted_at IS NULL
                    UNION ALL
                    SELECT product_id, counted_quantity as quantity FROM transfer_stock_counted WHERE transfer_id = ? AND deleted_at IS NULL
                ) combined
                GROUP BY product_id
            ) tsi ON tsi.product_id = p.id
            LEFT JOIN (
                SELECT product_id, inventory_level 
                FROM vend_product_inventory 
                WHERE outlet_id = ?
            ) inv ON inv.product_id = p.id
            WHERE tsi.product_id IS NOT NULL
            AND COALESCE(inv.inventory_level, 0) < 10
            ORDER BY current_stock ASC, p.name
            LIMIT 5
        ");
        if ($lowStockStmt) {
            $lowStockStmt->bind_param('iiis', $txId, $txId, $txId, $toOutletId);
            $lowStockStmt->execute();
            $lowStockResult = $lowStockStmt->get_result();
            while ($productRow = $lowStockResult->fetch_assoc()) {
                $lowStockProducts[] = $productRow;
                $lowStockCount++;
            }
            $lowStockStmt->close();
        }
        
        // 2. Calculate TRANSFER VALUE (sum of product prices * quantities)
        $valueStmt = $db->prepare("
            SELECT SUM(p.price * combined.quantity) as total_value
            FROM vend_products p
            INNER JOIN (
                SELECT product_id, SUM(quantity) as quantity
                FROM (
                    SELECT product_id, planned_quantity as quantity FROM transfer_stock_planned WHERE transfer_id = ? AND deleted_at IS NULL
                    UNION ALL
                    SELECT product_id, counted_quantity as quantity FROM transfer_stock_counted WHERE transfer_id = ? AND deleted_at IS NULL
                ) combined
                GROUP BY product_id
            ) combined ON combined.product_id = p.id
        ");
        if ($valueStmt) {
            $valueStmt->bind_param('ii', $txId, $txId);
            $valueStmt->execute();
            $valueResult = $valueStmt->get_result();
            if ($valueRow = $valueResult->fetch_assoc()) {
                $transferValue = floatval($valueRow['total_value'] ?? 0);
            }
            $valueStmt->close();
        }
        
        // 3. Calculate COMPLETION PERCENTAGE
        $completionStmt = $db->prepare("
            SELECT 
                COALESCE(SUM(tsp.planned_quantity), 0) as planned_total,
                COALESCE(SUM(tsc.counted_quantity), 0) as counted_total
            FROM transfers t
            LEFT JOIN transfer_stock_planned tsp ON tsp.transfer_id = t.id AND tsp.deleted_at IS NULL
            LEFT JOIN transfer_stock_counted tsc ON tsc.transfer_id = t.id AND tsc.deleted_at IS NULL
            WHERE t.id = ?
        ");
        if ($completionStmt) {
            $completionStmt->bind_param('i', $txId);
            $completionStmt->execute();
            $completionResult = $completionStmt->get_result();
            if ($completionRow = $completionResult->fetch_assoc()) {
                $plannedTotal = intval($completionRow['planned_total']);
                $countedTotal = intval($completionRow['counted_total']);
                if ($plannedTotal > 0) {
                    $completionPercent = round(($countedTotal / $plannedTotal) * 100);
                }
            }
            $completionStmt->close();
        }
        
        // 4. Get shipment tracking info (carrier, tracking number, status)
        $shipmentStmt = $db->prepare("
            SELECT carrier_name, tracking_number, tracking_url, status, 
                   dispatched_at, received_at, delivery_mode, dest_instructions
            FROM transfer_shipments 
            WHERE transfer_id = ? AND deleted_at IS NULL
            ORDER BY created_at DESC LIMIT 1
        ");
        if ($shipmentStmt) {
            $shipmentStmt->bind_param('i', $txId);
            $shipmentStmt->execute();
            $shipmentResult = $shipmentStmt->get_result();
            if ($shipmentRow = $shipmentResult->fetch_assoc()) {
                $shipmentInfo = $shipmentRow;
            }
            $shipmentStmt->close();
        }
        
        // 5. Get transfer notes (latest 2 notes)
        $notesStmt = $db->prepare("
            SELECT tn.note_text, tn.created_at, u.first_name, u.last_name
            FROM transfer_notes tn
            LEFT JOIN users u ON u.id = tn.created_by
            WHERE tn.transfer_id = ? AND tn.deleted_at IS NULL
            ORDER BY tn.created_at DESC LIMIT 2
        ");
        if ($notesStmt) {
            $notesStmt->bind_param('i', $txId);
            $notesStmt->execute();
            $notesResult = $notesStmt->get_result();
            while ($noteRow = $notesResult->fetch_assoc()) {
                $transferNotes[] = $noteRow;
            }
            $notesStmt->close();
        }
        
        // 6. Count recent transfers between these stores (last 30 days)
        $fromOutletId = $fromOutlet['id'] ?? null;
        if ($fromOutletId && $toOutletId) {
            $historyStmt = $db->prepare("
                SELECT COUNT(*) as transfer_count, MAX(created_at) as last_transfer
                FROM transfers 
                WHERE outlet_from = ? AND outlet_to = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND deleted_at IS NULL
                AND id != ?
            ");
            if ($historyStmt) {
                $historyStmt->bind_param('ssi', $fromOutletId, $toOutletId, $txId);
                $historyStmt->execute();
                $historyResult = $historyStmt->get_result();
                if ($historyRow = $historyResult->fetch_assoc()) {
                    $recentTransferCount = (int)$historyRow['transfer_count'];
                    $lastTransferDate = $historyRow['last_transfer'];
                }
                $historyStmt->close();
            }
        }
        
        // 4. Get staff assigned to DESTINATION outlet (the receiving team)
        if ($toOutletId) {
            $staffStmt = $db->prepare("
                SELECT first_name, last_name, role_id
                FROM users 
                WHERE default_outlet = ? 
                AND staff_active = 1 
                AND account_locked = 0
                LIMIT 5
            ");
            if ($staffStmt) {
                $staffStmt->bind_param('s', $toOutletId);
                $staffStmt->execute();
                $staffResult = $staffStmt->get_result();
                while ($staffRow = $staffResult->fetch_assoc()) {
                    $staffOnDuty[] = $staffRow;
                }
                $staffStmt->close();
            }
        }
        
    } catch (Exception $e) {
        // Silent fail for status card extras
    }
}
?>

<!-- Redesigned Transfer Header Styles -->
<style>
/* Animated Gradient */
.transfer-header-animated-gradient {
    background: linear-gradient(250deg, #f200ff, #008cff, #ff0085);
    background-size: 600% 600%;
    -webkit-animation: GradientShift 27s ease infinite;
    -moz-animation: GradientShift 27s ease infinite;
    animation: GradientShift 27s ease infinite;
}

@-webkit-keyframes GradientShift {
    0%{background-position:0% 81%}
    50%{background-position:100% 20%}
    100%{background-position:0% 81%}
}
@-moz-keyframes GradientShift {
    0%{background-position:0% 81%}
    50%{background-position:100% 20%}
    100%{background-position:0% 81%}
}
@keyframes GradientShift {
    0%{background-position:0% 81%}
    50%{background-position:100% 20%}
    100%{background-position:0% 81%}
}

/* Header Wrapper */
.transfer-header-new {
    margin-bottom: 2rem;
}

/* === SECTION 1: THIN TOP BAR === */
.header-bar-top {
    background: #2e3a59;
    padding: 5px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
    font-size: 0.7rem;
}

.top-bar-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.top-date {
    font-weight: 600;
}

.top-date i {
    margin-right: 6px;
    opacity: 0.8;
}

.top-title {
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.top-bar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.top-btn {
    padding: 4px 10px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 3px;
    color: #fff;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.top-btn:hover {
    background: rgba(255,255,255,0.25);
}

/* === SECTION 2: MIDDLE HERO SECTION === */
.header-bar-middle {
    background: #fff;
    padding: 8px 16px;
    border-left: 4px solid transparent;
    border-image: linear-gradient(180deg, #f200ff, #008cff, #ff0085) 1;
}

.middle-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.hero-title-group {
    flex: 1;
}

.hero-id {
    font-size: 1.3rem;
    font-weight: 900;
    color: #1a202c;
    margin: 0;
    line-height: 1;
}

.hero-subtitle {
    font-size: 0.6rem;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-top: 2px;
}



/* Outlet Info Grid - Now 3 columns */
.middle-outlets {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 10px;
}

.outlet-source {
    background: #f7fafc;
    padding: 4px 8px;
    border-radius: 3px;
    border-left: 2px solid #4299e1;
}

.outlet-label {
    font-size: 0.55rem;
    text-transform: uppercase;
    color: #718096;
    font-weight: 700;
    letter-spacing: 0.3px;
    margin-bottom: 1px;
}

.outlet-name-small {
    font-size: 0.75rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 0;
}

.outlet-code-badge {
    display: inline-block;
    padding: 2px 8px;
    background: #4299e1;
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 3px;
}

.outlet-destination {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 10px 12px;
    border-radius: 4px;
    color: #fff;
}

/* Store Status Dashboard Styles */
.store-status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.store-status-title {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
}

.store-status-title i {
    font-size: 1rem;
    opacity: 0.9;
}

.store-name {
    font-size: 1rem;
    font-weight: 800;
}

.store-code-badge {
    padding: 3px 8px;
    background: rgba(255,255,255,0.25);
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: 700;
    border: 1px solid rgba(255,255,255,0.3);
}

.store-status-badge {
    padding: 4px 10px;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 5px;
}

.store-status-badge.open {
    background: rgba(72, 187, 120, 0.9);
    color: #fff;
}

.store-status-badge.closed {
    background: rgba(245, 101, 101, 0.9);
    color: #fff;
}

.store-status-badge.warning {
    background: rgba(255, 152, 0, 0.9);
    color: #fff;
    animation: pulse-warning 2s ease-in-out infinite;
}

@keyframes pulse-warning {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.store-status-badge i {
    font-size: 0.6rem;
}

.store-status-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 8px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 3px;
    font-size: 0.75rem;
}

.status-item i {
    font-size: 0.8rem;
    opacity: 0.9;
}

.status-item.warning {
    background: rgba(237, 137, 54, 0.2);
    border-left: 3px solid #ed8936;
}

.status-item.success {
    background: rgba(72, 187, 120, 0.2);
    border-left: 3px solid #48bb78;
}

.status-text {
    flex: 1;
    line-height: 1.3;
}

.status-text strong {
    font-weight: 800;
}

.store-address {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    padding: 6px 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    font-size: 0.75rem;
    margin-bottom: 8px;
    line-height: 1.3;
}

.store-address i {
    margin-top: 2px;
    opacity: 0.9;
}

.store-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.store-action-btn {
    padding: 4px 10px;
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    text-decoration: none;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.store-action-btn:hover {
    background: rgba(255, 255, 255, 0.35);
    transform: translateY(-1px);
    text-decoration: none;
    color: #fff;
}

.store-action-btn i {
    font-size: 0.75rem;
}

/* Store Contact Info - Text with Icons for Copy/Paste - Single Line */
.store-contact-info {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 8px;
    padding: 6px 0;
    align-items: center;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.68rem;
    padding: 2px 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.contact-item i {
    font-size: 0.65rem;
    opacity: 0.9;
}

.contact-link {
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
    user-select: text;
}

.contact-link:hover {
    color: #fff;
    text-decoration: underline;
    opacity: 0.9;
}

/* Transfer Status Card (Third Column) - Combined Option 4 + 5 */
.transfer-status-card {
    background: #f7fafc;
    border-radius: 4px;
    border: 2px solid #e2e8f0;
    min-width: 280px;
    max-width: 320px;
}

.status-card-header {
    background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
    color: #fff;
    padding: 8px 12px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
    border-radius: 2px 2px 0 0;
}

.status-card-header i {
    font-size: 0.85rem;
}

.status-card-body {
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.status-card-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    background: #fff;
    border-radius: 4px;
    border-left: 3px solid #cbd5e0;
    font-size: 0.75rem;
    color: #2d3748;
    transition: all 0.2s;
}

.status-card-item:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-card-item i {
    font-size: 1rem;
    color: #4a5568;
    flex-shrink: 0;
    width: 20px;
    text-align: center;
}

.status-card-item.warning {
    border-left-color: #ed8936;
    background: linear-gradient(90deg, #fffaf0 0%, #fff 100%);
}

.status-card-item.warning i {
    color: #ed8936;
}

.status-card-item.success {
    border-left-color: #48bb78;
    background: linear-gradient(90deg, #f0fff4 0%, #fff 100%);
}

.status-card-item.success i {
    color: #48bb78;
}

.status-card-item.info {
    border-left-color: #4299e1;
    background: linear-gradient(90deg, #ebf8ff 0%, #fff 100%);
}

.status-card-item.info i {
    color: #4299e1;
}

.status-card-item.courier {
    border-left-color: #9f7aea;
    background: linear-gradient(90deg, #faf5ff 0%, #fff 100%);
}

.status-card-item.courier i {
    color: #9f7aea;
}

.status-card-text {
    flex: 1;
    line-height: 1.4;
}

.status-card-text strong {
    font-weight: 800;
    color: #1a202c;
}

.status-card-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    color: #718096;
    font-weight: 700;
    margin-bottom: 2px;
    letter-spacing: 0.3px;
}

.courier-recommendation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2px;
}

.courier-name {
    font-weight: 800;
    color: #1a202c;
}

.courier-price {
    font-size: 0.85rem;
    font-weight: 800;
    color: #9f7aea;
}

.courier-meta {
    display: flex;
    gap: 8px;
    margin-top: 3px;
    font-size: 0.7rem;
    color: #718096;
}

.courier-meta span {
    display: flex;
    align-items: center;
    gap: 3px;
}

/* Mini Status Cards - Compact 2x2 Grid Layout */
.status-mini-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 8px 10px;
    transition: all 0.2s;
}

.status-mini-card:hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transform: translateY(-1px);
}

.status-mini-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    color: #718096;
    font-weight: 700;
    letter-spacing: 0.3px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.status-mini-label i {
    font-size: 0.65rem;
}

.status-mini-value {
    font-size: 0.75rem;
    color: #2d3748;
    line-height: 1.3;
}

.status-mini-card.status-warning {
    border-left: 3px solid #ed8936;
    background: linear-gradient(135deg, #fffaf0 0%, #fff 100%);
}

.status-mini-card.status-info {
    border-left: 3px solid #4299e1;
    background: linear-gradient(135deg, #ebf8ff 0%, #fff 100%);
}

.status-mini-card.status-success {
    border-left: 3px solid #48bb78;
    background: linear-gradient(135deg, #f0fff4 0%, #fff 100%);
}

.status-mini-card.status-courier {
    border-left: 3px solid #9f7aea;
    background: linear-gradient(135deg, #faf5ff 0%, #fff 100%);
}

/* Quick Note Input Styling */
.quick-note-textarea {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #cbd5e0;
    border-radius: 3px;
    font-size: 0.7rem;
    font-family: inherit;
    resize: none;
    transition: all 0.2s;
    min-height: 40px;
    max-height: 60px;
    line-height: 1.3;
}

.quick-note-textarea:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 2px rgba(66, 153, 225, 0.1);
}

.quick-note-textarea::placeholder {
    color: #a0aec0;
    opacity: 0.7;
}

.quick-note-btn {
    padding: 4px 10px;
    background: #4299e1;
    color: #fff;
    border: none;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 4px;
}

.quick-note-btn:hover:not(:disabled) {
    background: #3182ce;
    transform: translateY(-1px);
}

.quick-note-btn:disabled {
    background: #cbd5e0;
    cursor: not-allowed;
    opacity: 0.6;
}

.quick-note-btn i {
    font-size: 0.7rem;
}

/* === SECTION 3: SUMMARY BAR === */
.header-bar-summary {
    background: #1a202c;
    padding: 8px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-grid {
    display: flex;
    gap: 20px;
    align-items: center;
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.summary-label {
    font-size: 0.55rem;
    text-transform: uppercase;
    color: rgba(255,255,255,0.6);
    letter-spacing: 0.5px;
    font-weight: 600;
}

.summary-value {
    font-size: 0.8rem;
    font-weight: 800;
    color: #fff;
}

.summary-value.large {
    font-size: 1rem;
}

.summary-value i {
    margin-right: 6px;
    opacity: 0.8;
}

.summary-actions {
    display: flex;
    gap: 8px;
}

.summary-btn {
    padding: 6px 14px;
    background: #4299e1;
    border: none;
    border-radius: 4px;
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.summary-btn:hover {
    background: #3182ce;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.summary-btn.secondary {
    background: rgba(255,255,255,0.15);
}

.summary-btn.secondary:hover {
    background: rgba(255,255,255,0.25);
}

/* Responsive */
@media (max-width: 992px) {
    .middle-outlets {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        flex-wrap: wrap;
        gap: 16px;
    }
    
    .header-bar-summary {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
}

@media (max-width: 640px) {
    .hero-id {
        font-size: 2rem;
    }
    
    .middle-hero {
        flex-direction: column;
        gap: 16px;
    }
    
    .top-bar-left {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
}
</style>

<!-- Transfer Header Component - Redesigned -->
<div class="transfer-header-new">
    

    
    <!-- SECTION 1: THIN TOP BAR -->
    <div class="header-bar-top">
        <div class="top-bar-left">
            <span class="top-date">
                <i class="far fa-calendar-alt"></i>
                <?= htmlspecialchars($createdDateTime ?: date('M j, Y @ g:i A'), ENT_QUOTES) ?>
                <span style="opacity: 0.8; margin-left: 8px;">(<?= htmlspecialchars($createdTimeAgo, ENT_QUOTES) ?>)</span>
            </span>
            <span class="top-title">
                <i class="fas fa-exchange-alt"></i>
                Stock Transfer System
            </span>
        </div>
        <div class="top-bar-right">
            <button class="top-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <!-- SECTION 2: MIDDLE HERO SECTION -->
    <div class="header-bar-middle">
        
        <!-- Hero Title & Lock Status -->
        <div class="middle-hero">
            <div class="hero-title-group">
                <h1 class="hero-id">STOCK TRANSFER #<?= htmlspecialchars((string)$txId, ENT_QUOTES) ?></h1>
                <p class="hero-subtitle">LIGHTSPEED STOCK CONSIGNMENT</p>
            </div>
            

        </div>
        
        <!-- Outlet Information -->
        <div class="middle-outlets">
            
            <!-- SOURCE (Brief) -->
            <div class="outlet-source">
                <div class="outlet-label">
                    <i class="fas fa-arrow-up"></i> Source
                </div>
                <div class="outlet-name-small">
                    <?= htmlspecialchars($fromLbl, ENT_QUOTES) ?>
                </div>
                <?php if ($fromStoreCode): ?>
                    <span class="outlet-code-badge"><?= htmlspecialchars($fromStoreCode, ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>
            
            <!-- DESTINATION (Simple) -->
            <div class="outlet-destination">
                <div class="store-status-header">
                    <div class="store-status-title">
                        <i class="fas fa-store"></i> 
                        <span class="store-name"><?= htmlspecialchars($toLbl, ENT_QUOTES) ?></span>
                        <?php if ($toStoreCode): ?>
                            <span class="store-code-badge"><?= htmlspecialchars($toStoreCode, ENT_QUOTES) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="store-status-badge <?= $toIsTemporarilyClosed ? 'warning' : ($toIsOpenNow ? 'open' : 'closed') ?>">
                        <i class="fas <?= $toIsTemporarilyClosed ? 'fa-exclamation-triangle' : 'fa-circle' ?>"></i>
                        <?= $toIsTemporarilyClosed ? 'TEMP CLOSED' : ($toIsOpenNow ? 'OPEN' : 'CLOSED') ?>
                    </div>
                </div>
                
                <div class="store-status-row">
                    <div class="status-item">
                        <i class="far fa-clock"></i>
                        <span class="status-text">
                            <?php if ($toIsTemporarilyClosed): ?>
                                <strong style="color: #ff9800;">Temporarily closed today</strong>
                            <?php elseif ($toIsOpenNow): ?>
                                <?php if ($toClosesIn): ?>
                                    <?= htmlspecialchars($toClosesIn, ENT_QUOTES) ?>
                                <?php else: ?>
                                    Open now
                                <?php endif; ?>
                            <?php elseif ($toNextOpen): ?>
                                <?= htmlspecialchars($toNextOpen, ENT_QUOTES) ?>
                            <?php else: ?>
                                Hours not available
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if ($toIsOpenNow && !empty($staffOnDuty)): ?>
                    <div class="status-item">
                        <i class="fas fa-users"></i>
                        <span class="status-text">
                            <strong>Staff:</strong> 
                            <?php 
                            $staffNames = array_map(function($s) { 
                                return $s['first_name']; 
                            }, array_slice($staffOnDuty, 0, 3)); 
                            echo htmlspecialchars(implode(', ', $staffNames), ENT_QUOTES);
                            ?>
                            <?php if (count($staffOnDuty) > 3): ?>
                                <span style="opacity: 0.8;"> +<?= count($staffOnDuty) - 3 ?> more</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($toFullAddress): ?>
                <div class="store-address">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($toFullAddress, ENT_QUOTES) ?>
                </div>
                <?php endif; ?>
                
                <div class="store-contact-info">
                    <?php if ($toPhone): ?>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <a href="tel:<?= htmlspecialchars($toPhone, ENT_QUOTES) ?>" class="contact-link">
                            <?= htmlspecialchars($toPhone, ENT_QUOTES) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($toEmail): ?>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:<?= htmlspecialchars($toEmail, ENT_QUOTES) ?>" class="contact-link">
                            <?= htmlspecialchars($toEmail, ENT_QUOTES) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($toFullAddress): ?>
                    <div class="contact-item">
                        <i class="fas fa-map"></i>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($toFullAddress) ?>" 
                           target="_blank" class="contact-link">
                            View Map
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- AI TRANSFER INTELLIGENCE (BOTTOM ROW IN DESTINATION CARD) -->
                <?php
                $aiCardPath = __DIR__ . '/ai_insight_card.php';
                if (file_exists($aiCardPath)) {
                    include $aiCardPath;
                }
                ?>
                
            </div>
            
            <!-- TRANSFER STATUS INFO (NEW THIRD CARD) -->
            <div class="transfer-status-card">
                <div class="status-card-header">
                    <i class="fas fa-clipboard-list"></i>
                    SHIPMENT & STATUS
                </div>
                
                <div class="status-card-body">
                    
                    <!-- COMPACT 2x2 GRID FOR KEY METRICS -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 8px;">
                        
                        <!-- PACKING PROGRESS (TOP LEFT) -->
                        <div class="status-mini-card">
                            <div class="status-mini-label">
                                <i class="fas fa-tasks"></i> PACKING PROGRESS
                            </div>
                            <div class="status-mini-value">
                                <strong style="font-size: 1.3rem; color: <?= $completionPercent >= 100 ? '#48bb78' : '#4299e1' ?>;">
                                    <?= $completionPercent ?>%
                                </strong>
                                <span style="font-size: 0.65rem; opacity: 0.7; display: block;">complete</span>
                            </div>
                            <div style="background: #e2e8f0; height: 4px; border-radius: 2px; margin-top: 4px; overflow: hidden;">
                                <div style="background: <?= $completionPercent >= 100 ? '#48bb78' : '#4299e1' ?>; height: 100%; width: <?= min($completionPercent, 100) ?>%; transition: width 0.5s ease;"></div>
                            </div>
                            <?php if ($completionPercent == 0): ?>
                                <div style="font-size: 0.6rem; opacity: 0.6; margin-top: 2px;">No items packed yet</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- STAFF ON DUTY (TOP RIGHT) - ALWAYS VISIBLE -->
                        <div class="status-mini-card <?= !empty($staffOnDuty) ? 'status-success' : '' ?>">
                            <div class="status-mini-label">
                                <i class="fas fa-users"></i> STAFF AT DESTINATION
                            </div>
                            <div class="status-mini-value">
                                <?php if (!empty($staffOnDuty)): ?>
                                    <strong style="font-size: 0.8rem;">
                                        <?php 
                                        $staffNames = array_map(function($s) { 
                                            return $s['first_name']; 
                                        }, array_slice($staffOnDuty, 0, 2)); 
                                        echo htmlspecialchars(implode(', ', $staffNames), ENT_QUOTES);
                                        ?>
                                    </strong>
                                    <?php if (count($staffOnDuty) > 2): ?>
                                        <span style="font-size: 0.65rem; opacity: 0.7; display: block; margin-top: 2px;">
                                            +<?= count($staffOnDuty) - 2 ?> more staff
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; opacity: 0.7;">No staff assigned</span>
                                <?php endif; ?>
                            </div>
                        </div>                        <!-- RECENT TRANSFERS (BOTTOM LEFT) -->
                        <div class="status-mini-card">
                            <div class="status-mini-label">
                                <i class="fas fa-history"></i> RECENT TRANSFERS
                            </div>
                            <div class="status-mini-value">
                                <?php if ($recentTransferCount > 0): ?>
                                    <strong style="font-size: 1.3rem; color: #4299e1;"><?= $recentTransferCount ?></strong>
                                    <span style="font-size: 0.65rem; opacity: 0.7; display: block;">in last 30 days</span>
                                <?php else: ?>
                                    <strong style="font-size: 0.75rem;">First transfer</strong>
                                    <span style="font-size: 0.65rem; opacity: 0.7; display: block;">in 30 days</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- QUICK NOTE DISABLED - Replaced by Order History component below courier console
                        <div class="status-mini-card" id="quick-note-card">
                            <div class="status-mini-label">
                                <i class="fas fa-sticky-note"></i> QUICK NOTE
                            </div>
                            <div class="status-mini-value">
                                <textarea 
                                    id="quick-note-input" 
                                    class="quick-note-textarea" 
                                    placeholder="Add a quick note..."
                                    maxlength="200"
                                    rows="2"
                                ></textarea>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                                    <button 
                                        id="quick-note-save" 
                                        class="quick-note-btn" 
                                        onclick="saveQuickNote(<?= $txId ?>)"
                                        disabled
                                    >
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                    <span id="quick-note-status" style="font-size: 0.6rem; opacity: 0.7;"></span>
                                </div>
                            </div>
                        </div>
                        -->
                    </div>
                    
                    <script>
                    // Quick Note functionality - DISABLED (replaced by Order History component)
                    /*
                    (function() {
                        const textarea = document.getElementById('quick-note-input');
                        const saveBtn = document.getElementById('quick-note-save');
                        const statusSpan = document.getElementById('quick-note-status');
                        
                        if (textarea && saveBtn) {
                            // Enable save button when text is entered
                            textarea.addEventListener('input', function() {
                                saveBtn.disabled = this.value.trim().length === 0;
                            });
                            
                            // Auto-resize textarea
                            textarea.addEventListener('input', function() {
                                this.style.height = 'auto';
                                this.style.height = Math.min(this.scrollHeight, 60) + 'px';
                            });
                        }
                        
                        // Load existing note if any
                        const transferId = <?= $txId ?>;
                        if (transferId && textarea) {
                            fetch('/modules/transfers/stock/api/notes.php?transfer_id=' + transferId + '&limit=1')
                                .then(r => r.json())
                                .then(data => {
                                    if (data.ok && data.notes && data.notes.length > 0) {
                                        const latestNote = data.notes[0];
                                        textarea.value = latestNote.note_text || '';
                                        textarea.dispatchEvent(new Event('input'));
                                    }
                                })
                                .catch(e => console.error('Failed to load note:', e));
                        }
                    })();
                    
                    function saveQuickNote(transferId) {
                        const textarea = document.getElementById('quick-note-input');
                        const saveBtn = document.getElementById('quick-note-save');
                        const statusSpan = document.getElementById('quick-note-status');
                        
                        if (!textarea || !transferId) return;
                        
                        const noteText = textarea.value.trim();
                        if (!noteText) return;
                        
                        saveBtn.disabled = true;
                        statusSpan.textContent = 'Saving...';
                        statusSpan.style.color = '#4299e1';
                        
                        fetch('/modules/transfers/stock/api/notes.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                transfer_id: transferId,
                                note_text: noteText
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.ok) {
                                statusSpan.textContent = '✓ Saved';
                                statusSpan.style.color = '#48bb78';
                                setTimeout(() => {
                                    statusSpan.textContent = '';
                                    saveBtn.disabled = false;
                                }, 2000);
                            } else {
                                throw new Error(data.error || 'Save failed');
                            }
                        })
                        .catch(err => {
                            console.error('Save error:', err);
                            statusSpan.textContent = '✗ Failed';
                            statusSpan.style.color = '#ed8936';
                            saveBtn.disabled = false;
                        });
                    }
                    */
                    // END Quick Note - feature disabled, use Order History component instead
                    </script>
                    
                    
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- SECTION 3: SUMMARY BAR -->
    <div class="header-bar-summary">
        <div class="summary-grid">
            
            <div class="summary-item">
                <div class="summary-label">Source</div>
                <div class="summary-value">
                    <i class="fas fa-store"></i>
                    <?= htmlspecialchars($fromLbl, ENT_QUOTES) ?>
                </div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Destination</div>
                <div class="summary-value">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($toLbl, ENT_QUOTES) ?>
                </div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Total Weight</div>
                <div class="summary-value large">
                    <i class="fas fa-weight"></i>
                    <?= $totalWeightKg > 0 ? htmlspecialchars(number_format($totalWeightKg, 2), ENT_QUOTES) . ' kg' : 'Calculating...' ?>
                </div>
            </div>
            
            <?php if ($itemCount > 0): ?>
            <div class="summary-item">
                <div class="summary-label">Items</div>
                <div class="summary-value">
                    <i class="fas fa-boxes"></i>
                    <?= htmlspecialchars(number_format($itemCount), ENT_QUOTES) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($estimatedBoxes): ?>
            <div class="summary-item">
                <div class="summary-label">Est. Boxes</div>
                <div class="summary-value">
                    <i class="fas fa-box"></i>
                    <?= htmlspecialchars((string)$estimatedBoxes, ENT_QUOTES) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($estimatedSatchels): ?>
            <div class="summary-item">
                <div class="summary-label">Est. Satchels</div>
                <div class="summary-value">
                    <i class="fas fa-envelope-open"></i>
                    <?= htmlspecialchars((string)$estimatedSatchels, ENT_QUOTES) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="summary-item">
                <div class="summary-label">Time Elapsed</div>
                <div class="summary-value">
                    <i class="far fa-clock"></i>
                    <?= htmlspecialchars($createdTimeAgo, ENT_QUOTES) ?>
                </div>
            </div>
            
        </div>
        
        <div class="summary-actions">
            <button class="summary-btn" onclick="alert('Add products functionality coming soon!')">
                <i class="fas fa-plus-circle"></i>
                Add Products to Page
            </button>
        </div>
    </div>
    
</div>
