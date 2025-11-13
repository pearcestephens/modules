# URGENT: Classic CIS Theme Fix Required

## Problem Identified
The newly created pages (home.php, ai-insights.php) use:
- ❌ Purple gradient backgrounds (#667eea, #764ba2)
- ❌ Bootstrap 5.3.x standalone templates
- ❌ "Dashboard fluffy" styling
- ❌ NOT using base/_templates/layouts/dashboard.php

## Required Fix
ALL pages MUST use:
- ✅ /modules/base/_templates/layouts/dashboard.php (classic CIS template)
- ✅ CoreUI 2.x + Bootstrap 4.x
- ✅ Professional white/grey business theme
- ✅ Clean tables, simple cards, no gradients
- ✅ Match pack-advanced-layout-a.php professional style

## Files Requiring Immediate Fix

### 1. /modules/consignments/views/home.php (543 lines - NEEDS COMPLETE REWRITE)
**Current Issues:**
- Standalone HTML with <!DOCTYPE>
- Bootstrap 5.3.0 CDN
- Purple hero section: `background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)`
- Bootstrap Icons instead of FontAwesome
- Rounded cards with shadows
- NOT using base template

**Required Changes:**
- Remove standalone HTML structure
- Use output buffering: `ob_start()` → `$content = ob_get_clean()`
- Include: `require_once dirname(dirname(__DIR__)) . '/base/_templates/layouts/dashboard.php';`
- Remove ALL purple colors
- Use simple white cards with borders
- Professional business styling only

### 2. /modules/consignments/views/ai-insights.php (289 lines - ALREADY CORRECT!)
✅ This file is CORRECTLY implemented:
- Uses output buffering
- Includes base/_templates/layouts/dashboard.php
- No purple gradients
- Professional cards with borders
- Clean business styling

## Action Required

Upload corrected version of:
- ✅ home.php (needs complete rewrite - 400 lines clean code)

All other view files are correct:
- ✅ transfer-manager.php
- ✅ purchase-orders.php
- ✅ stock-transfers.php
- ✅ freight.php
- ✅ control-panel.php
- ✅ queue-status.php
- ✅ admin-controls.php
- ✅ ai-insights.php (NEW - correctly implemented)

## Classic CIS Theme Standards

### Colors:
- Primary: #007bff (blue)
- Success: #28a745 (green)
- Warning: #ffc107 (yellow/orange)
- Danger: #dc3545 (red)
- Info: #17a2b8 (cyan)
- Light: #f8f9fa (background grey)
- Border: #dee2e6
- Text: #333, #6c757d

### Cards:
```css
background: #fff;
border: 1px solid #dee2e6;
border-radius: 6px;
padding: 20px;
box-shadow: 0 2px 4px rgba(0,0,0,0.05);
```

### NO:
- ❌ Gradients
- ❌ Purple colors
- ❌ Large rounded corners (>8px)
- ❌ Heavy shadows
- ❌ Animated backgrounds

### YES:
- ✅ Clean white cards
- ✅ Simple borders
- ✅ Professional blue/green/grey
- ✅ Subtle shadows only
- ✅ Business-appropriate

## Next Steps

1. Upload corrected home.php (provided separately)
2. Test all routes - verify classic CIS theme throughout
3. Confirm NO purple anywhere
4. Proceed with remaining todo items

**Status**: Ready for upload - corrected home.php file prepared
