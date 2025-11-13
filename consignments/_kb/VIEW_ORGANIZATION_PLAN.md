# View Organization & Cleanup Plan

## Current State Analysis

### ✅ GOOD - Using render('base')
- home.php - NEW clean version
- admin-controls.php - Stub
- ai-insights.php - Stub
- control-panel.php - Stub
- dashboard.php - Stub
- freight.php - Stub
- queue-status.php - Stub
- receiving.php - Stub

### ⚠️ NEEDS UPDATE - Using CISClassicTheme
- stock-transfers/stock-transfers.php (3.9K) - Uses old theme
- purchase-orders/purchase-orders.php (14K) - Uses old theme
- transfer-manager/transfer-manager.php (24K) - Uses old theme
- transfer-manager/transfer-manager-v5.php (17K) - Duplicate version

### 🗑️ TO DELETE - Backups/Old Versions
- home-CLEAN.php (12K) - OLD backup
- home.php.OLD (if exists) - Backup we created
- freight-WORKING.php (3.5K) - OLD backup
- ai-insights-FIXED.php (1.4K) - OLD backup
- queue-status-SIMPLE.php (2.1K) - OLD backup
- backups/ folder - All backup files
- vapeultra-demo-full.php (19K) - Demo file
- vapeultra-demo-test.php (13K) - Demo file

### 📁 KEEP BUT ORGANIZE
- _outlet_sidebar.php (2.4K) - Component
- _view-wrapper.php (340) - Documentation
- buttons-preview.php (4.8K) - Design lab
- gamification-modal.php (14K) - Modal component
- messaging-center.php (36K) - Keep best version
- messaging-center-integrated.php (47K) - Decide which to keep

## Action Plan

### Phase 1: Update Main Views to Use render('base')
1. stock-transfers/stock-transfers.php → Convert to render('base')
2. purchase-orders/purchase-orders.php → Convert to render('base')  
3. transfer-manager/transfer-manager.php → Convert to render('base')

### Phase 2: Clean Up Duplicates
1. Delete all files in backups/ folder
2. Delete *-CLEAN, *-WORKING, *-FIXED, *-SIMPLE versions
3. Delete *-v5 duplicate versions
4. Delete demo files (vapeultra-*)
5. Keep only ONE messaging-center version

### Phase 3: Organize Components
1. Move _outlet_sidebar.php to components/
2. Move gamification-modal.php to components/
3. Move buttons-preview.php to components/ or delete if not used

### Phase 4: Final Structure
```
views/
├── components/              # Reusable UI components
│   ├── _outlet_sidebar.php
│   └── gamification-modal.php
├── stock-transfers/
│   └── stock-transfers.php  # UPDATED to use render('base')
├── purchase-orders/
│   └── purchase-orders.php  # UPDATED to use render('base')
├── transfer-manager/
│   └── transfer-manager.php # UPDATED to use render('base')
├── home.php                 # ✅ DONE
├── control-panel.php        # ✅ DONE (stub)
├── receiving.php            # ✅ DONE (stub)
├── freight.php              # ✅ DONE (stub)
├── queue-status.php         # ✅ DONE (stub)
├── admin-controls.php       # ✅ DONE (stub)
├── ai-insights.php          # ✅ DONE (stub)
├── dashboard.php            # ✅ DONE (stub)
└── messaging-center.php     # Keep best version
```

## Execution Steps

1. ✅ Create components/ directory
2. ⏳ Update stock-transfers.php to use render('base')
3. ⏳ Update purchase-orders.php to use render('base')
4. ⏳ Update transfer-manager.php to use render('base')
5. ⏳ Delete all backup/old files
6. ⏳ Move components to components/
7. ⏳ Test all routes return 200
