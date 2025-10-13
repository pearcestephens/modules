# Transfer Pages - Standard Data Contract

## Overview
This document defines the standard data structure that all transfer pages (Pack, Receive, Hub) should follow for consistency and maintainability.

## Standard Page Data Structure

### Required Variables
All transfer pages must provide these variables to the base template:

```php
// Example from PackController, ReceiveController, HubController
return $this->renderView('pack/view.php', [
    // === CORE PAGE DATA ===
    'page_title' => 'Pack Transfer #' . $transferId,        // string
    'page_blurb' => 'Pack and ship items for transfer',     // string  
    'page_id' => 'consignments_pack',                       // string (for CSS/JS targeting)
    
    // === NAVIGATION ===
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => '/'],
        ['label' => 'Transfers', 'href' => '/modules/consignments/transfers'],
        ['label' => 'Pack', 'active' => true],
    ],
    
    // === TRANSFER DATA ===
    'transferId' => $transferId,         // int
    'transfer' => $transfer,             // array|null (from database)
    'items' => $items,                   // array (transfer items)
    'transferCount' => $count,           // int (total transfers available)
    
    // === UI STATE ===
    'bodyClass' => 'vs-transfer vs-transfer--pack',  // string (page-specific CSS)
    'moduleCSS' => ['/modules/consignments/assets/css/pack.css'],
    'moduleJS' => ['/modules/consignments/assets/js/pack.bundle.js'],
]);
```

### Optional Variables
These provide additional flexibility:

```php
// === ALERTS & NOTIFICATIONS ===
'alerts' => [
    ['type' => 'success', 'message' => 'Transfer saved successfully'],
    ['type' => 'warning', 'message' => 'Some items are out of stock'],
],

// === PERMISSIONS & FEATURES ===
'canEdit' => true,                    // bool
'canDelete' => false,                 // bool  
'enableScanner' => true,              // bool
'enablePrinting' => true,             // bool

// === ADDITIONAL CONTEXT ===
'outlets' => $outlets,                // array (available outlets)
'recentTransfers' => $recent,         // array (for quick access)
'userPreferences' => $prefs,          // array (saved settings)
```

## Controller Implementation Pattern

### Base Transfer Controller Structure
```php
abstract class BaseTransferController extends PageController
{
    protected function getStandardTransferData(int $transferId): array
    {
        $transfer = $this->loadTransfer($transferId);
        $items = $this->loadTransferItems($transferId);
        
        return [
            'transferId' => $transferId,
            'transfer' => $transfer,
            'items' => $items,
            'transferCount' => $this->countTransfers(),
        ];
    }
    
    protected function loadTransfer(int $id): ?array
    {
        // Standard transfer loading logic
        // Used by Pack, Receive, Hub controllers
    }
    
    protected function loadTransferItems(int $transferId): array
    {
        // Standard items loading logic
    }
    
    protected function countTransfers(): int
    {
        // Standard count logic (already exists in your controllers)
    }
}
```

### Specific Controller Implementation
```php
final class PackController extends BaseTransferController
{
    public function index(): string
    {
        $transferId = (int)($_GET['transfer'] ?? $_GET['id'] ?? 0);
        
        // Get standard transfer data
        $standardData = $this->getStandardTransferData($transferId);
        
        // Add pack-specific data
        $packData = [
            'page_title' => $transferId > 0 ? "Pack Transfer #{$transferId}" : 'New Pack',
            'page_blurb' => 'Pack and ship items for transfer',
            'page_id' => 'consignments_pack',
            'bodyClass' => 'vs-transfer vs-transfer--pack',
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Transfers', 'href' => '/modules/consignments/transfers'],
                ['label' => 'Pack', 'active' => true],
            ],
            'moduleCSS' => ['/modules/consignments/assets/css/pack.css'],
            'moduleJS' => ['/modules/consignments/assets/js/pack.bundle.js'],
            'enableScanner' => true,
            'enablePrinting' => true,
        ];
        
        return $this->renderView('pack/view.php', array_merge($standardData, $packData));
    }
}
```

## View Template Standards

### Standard View Structure
All transfer views should follow this structure:

```php
<?php
// views/pack/view.php, views/receive/view.php, views/hub/view.php

// Validate required data
$transferId = $transferId ?? 0;
$transfer = $transfer ?? null;
$items = $items ?? [];
$page_id = $page_id ?? 'consignments_default';

// Set page-specific CSS class
$bodyClass = ($bodyClass ?? '') . ' ' . $page_id;
?>

<!-- PAGE HEADER -->
<div class="vs-page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h2 mb-1"><?= htmlspecialchars($page_title ?? '', ENT_QUOTES) ?></h1>
            <?php if (!empty($page_blurb)): ?>
                <p class="text-muted mb-0"><?= htmlspecialchars($page_blurb, ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>
        <div class="vs-page-actions">
            <!-- Page-specific action buttons -->
        </div>
    </div>
</div>

<!-- ALERTS -->
<?php if (!empty($alerts)): ?>
    <div class="vs-alerts">
        <?php foreach ($alerts as $alert): ?>
            <div class="alert alert-<?= htmlspecialchars($alert['type'], ENT_QUOTES) ?> alert-dismissible">
                <?= htmlspecialchars($alert['message'], ENT_QUOTES) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- MAIN CONTENT -->
<div class="vs-transfer-content <?= htmlspecialchars($page_id, ENT_QUOTES) ?>">
    
    <!-- TRANSFER INFO PANEL -->
    <?php if ($transferId > 0): ?>
        <?php include __DIR__ . '/../components/transfer-info-panel.php'; ?>
    <?php endif; ?>
    
    <!-- PAGE-SPECIFIC CONTENT -->
    <div class="vs-transfer-workspace">
        <?php
        // Include page-specific components
        // Pack: table_pack.php, shipping.php, printers.php
        // Receive: table_receive.php, confidence.php, actions.php  
        // Hub: dashboard.php, recent_transfers.php, quick_actions.php
        ?>
    </div>
    
</div>

<!-- PAGE-SPECIFIC JAVASCRIPT INITIALIZATION -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize page-specific functionality
    const config = <?= json_encode([
        'transferId' => $transferId,
        'enableScanner' => $enableScanner ?? false,
        'enablePrinting' => $enablePrinting ?? false,
    ]) ?>;
    
    <?php if ($page_id === 'consignments_pack'): ?>
        initPack(config);
    <?php elseif ($page_id === 'consignments_receive'): ?>
        initReceive(config);
    <?php elseif ($page_id === 'consignments_hub'): ?>
        initHub(config);
    <?php endif; ?>
});
</script>
```

## CSS Standards

### Standard CSS Classes
All transfer pages use these consistent CSS classes:

```css
/* Page-level classes */
.vs-transfer { /* Base transfer page styles */ }
.vs-transfer--pack { /* Pack-specific styles */ }
.vs-transfer--receive { /* Receive-specific styles */ }
.vs-transfer--hub { /* Hub-specific styles */ }

/* Component classes */  
.vs-page-header { /* Consistent page headers */ }
.vs-page-actions { /* Action button containers */ }
.vs-alerts { /* Alert message containers */ }
.vs-transfer-content { /* Main content area */ }
.vs-transfer-workspace { /* Working area for tables/forms */ }
```

## JavaScript Standards

### Standard Initialization Pattern
```javascript
// pack.bundle.js, receive.bundle.js, hub.bundle.js
export function initPack(config) {
    const root = document.querySelector('.vs-transfer--pack');
    if (!root) return;
    
    // Standard initialization pattern
    mountDraftControls(root, config);
    bindTableActions(root, config);
    bindFormSubmission(root, config);
    
    if (config.enableScanner) {
        mountKeyboardScanner(root, config);
    }
    
    if (config.enablePrinting) {
        initPrinterSelection(root, config);
    }
}
```

## Benefits of This Standard

1. **Consistency**: All transfer pages look and behave similarly
2. **Maintainability**: Changes to base template affect all pages
3. **Extensibility**: Easy to add new transfer types following the pattern
4. **Testing**: Standard data contracts make testing predictable
5. **Documentation**: Clear expectations for new developers

## Migration Path

To implement this standard:

1. **Create BaseTransferController** with shared logic
2. **Update existing controllers** (Pack, Receive, Hub) to extend base
3. **Standardize view templates** using the structure above
4. **Create shared components** (transfer-info-panel.php, etc.)
5. **Update CSS/JS** to use consistent class names

This provides a clean, simple foundation that scales well without over-engineering.