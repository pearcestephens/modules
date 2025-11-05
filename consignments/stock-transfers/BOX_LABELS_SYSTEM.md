# 📦 Box Labels System - Complete Guide

## Overview

**Box Labels** are INTERNAL IDENTIFICATION labels printed BEFORE shipping labels are created. They help warehouse staff identify boxes in a sea of packages.

**Shipping Labels** are generated separately via courier API with tracking numbers.

---

## 🎯 Two Types of Labels

### 1. **BOX LABELS (Internal)**
- **Purpose**: Warehouse identification
- **When**: Print at packing time, BEFORE shipping
- **What**: Box # of Total, Destination Store, Transfer ID, From Store
- **Tracking**: Shows tracking IF already generated, otherwise "Not yet generated"
- **Format**: A4 paper, 2 per page
- **File**: `print-box-labels.php`

### 2. **SHIPPING LABELS (Courier)**
- **Purpose**: Courier tracking and delivery
- **When**: Generated via API after packing complete
- **What**: Courier barcode, tracking #, addresses, weight
- **Format**: Thermal 80mm × 100mm
- **File**: `generate-shipping-labels.php` (to be created)

---

## 🔗 Access Points

### Direct URL:
```
/modules/consignments/stock-transfers/print-box-labels.php?transfer_id=12345
```

### Integrated in All 3 Layouts:

**Layout A (Sidebar):**
- Button in freight console: "Print Box Labels (Internal)"
- Tool button in tools grid: "Box Labels"

**Layout B (Tabs):**
- Button in Freight tab: "Print Box Labels (Internal Use)"
- Tool card in Tools tab: "Box Labels (Internal)"

**Layout C (Accordion):**
- Button in Freight panel: "Print Box Labels (Internal Use)"
- Tool card in Tools panel: "Box Labels (Internal)"

---

## 📋 Box Label Design

### Large Format Features:

```
┌─────────────────────────────────────┐
│         BOX 1                       │  ← 64px font
│         OF 3                        │  ← 24px font
├─────────────────────────────────────┤
│  ╔═══════════════════════════════╗ │
│  ║   DESTINATION:                ║ │
│  ║   WELLINGTON LAMBTON QUAY     ║ │  ← 42px font, RED background
│  ║   456 Lambton Quay, Wellington║ │  ← 16px font
│  ╚═══════════════════════════════╝ │
├─────────────────────────────────────┤
│  Transfer ID: #12345                │
│  From: Auckland Central             │
│  Weight: 5.2kg                      │
│  Items: 12 items                    │
├─────────────────────────────────────┤
│  Tracking: NZ123456789WLG           │  ← If available
│  (or "Not yet generated")           │
└─────────────────────────────────────┘
```

### Critical Design Elements:

✅ **HUGE Destination Store Name** (42px, all caps, red background)
✅ **Clear Address** (16px, readable from distance)
✅ **Box Number** (64px - impossible to miss)
✅ **Transfer ID** (for cross-referencing)
✅ **From Store** (prevent confusion)
✅ **Tracking Number** (if exists, otherwise shows "Not yet generated")

---

## 🖨️ Print Workflow

### Option 1: Print Only (No Submit)
```
1. Click "Print Box Labels" button
2. Review labels on screen
3. Click "Print Labels Only (No Submit)" button
4. Print dialog opens
5. Labels print on A4 paper (2 per page)
6. Return to packing page (no status change)
```

**Use Case**: Print labels early in packing process, before ready to ship

### Option 2: Print & Continue to Shipping
```
1. Click "Print Box Labels" button
2. Review labels on screen
3. Click "Print & Continue to Shipping" button
4. Print dialog opens
5. Labels print
6. Automatically redirects to shipping label generation page
7. Courier API creates tracking numbers
8. Thermal shipping labels generated (80mm × 100mm)
```

**Use Case**: Complete workflow - pack, label boxes, generate shipping labels

---

## 🎨 Screen Features

### Before Printing (Screen View):

**Header Card:**
- Transfer ID
- From/To stores with addresses
- Visual transfer info grid

**Important Alert:**
- Yellow warning box
- Critical reminder to verify destination
- Explains difference between box labels and shipping labels

**Huge Destination Display:**
- Red background
- Destination store in MASSIVE letters
- Full address below
- Impossible to miss

**Print Controls:**
- Total boxes count
- "Back to Packing" button
- "Print Labels Only" button (primary)
- "Print & Continue to Shipping" button (success)

**Labels Preview:**
- 2-column grid showing all labels
- Exactly as they'll print
- Review before printing

### During Printing (Print View):

- All screen controls hidden
- Only labels visible
- A4 format, 2 per page
- Page breaks between labels
- Clean, professional layout

---

## 🔐 Critical Safety Features

### ⚠️ Verification Prompts:

**On Screen:**
```
⚠️ CRITICAL: Verify Destination Store

These labels are for INTERNAL IDENTIFICATION ONLY.
Always double-check the destination store is correct
before applying labels to boxes.
```

**Giant Red Box:**
```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  DESTINATION STORE:           ┃
┃                               ┃
┃  WELLINGTON LAMBTON QUAY      ┃  ← 48px, impossible to miss
┃                               ┃
┃  456 Lambton Quay, Wellington ┃  ← 20px
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
```

---

## 📊 Data Flow

### Box Label Generation:
```
Transfer Record
  └─► Boxes Array
       ├─► Box 1: number, weight, items, tracking (optional)
       ├─► Box 2: number, weight, items, tracking (optional)
       └─► Box 3: number, weight, items, tracking (optional)
            ↓
       Generate Labels
            ↓
       Print on A4 (2 per page)
```

### Complete Workflow:
```
1. Packing Page (Layout A/B/C)
   └─► Pack items into boxes
   └─► Assign products to boxes

2. Click "Print Box Labels"
   └─► Opens print-box-labels.php
   └─► Shows all box labels
   └─► Print on A4 paper
   └─► Apply to physical boxes

3. Click "Generate Shipping Labels"
   └─► Calls courier API
   └─► Creates tracking numbers (1 per box)
   └─► Stores: Shipment → Parcel → Items
   └─► Generates thermal labels (80mm)
   └─► Optional: Reprint box labels WITH tracking
```

---

## 🛠️ Technical Details

### URL Parameters:
```php
print-box-labels.php?transfer_id=12345
```

### Database Query:
```php
// Fetch transfer with boxes
$transfer = [
    'id' => 12345,
    'from_store' => 'Auckland Central',
    'from_address' => '123 Queen Street...',
    'to_store' => 'WELLINGTON LAMBTON QUAY',
    'to_address' => '456 Lambton Quay...',
    'boxes' => [
        [
            'box_number' => 1,
            'tracking' => 'NZ123456789WLG', // or NULL
            'weight' => '5.2kg',
            'items' => 12
        ],
        // ... more boxes
    ]
];
```

### Print Styles:
```css
@media print {
    /* Hide screen controls */
    .no-print { display: none !important; }

    /* A4 format */
    @page {
        size: A4;
        margin: 10mm;
    }

    /* Page breaks */
    .box-label {
        page-break-after: always;
        page-break-inside: avoid;
    }
}
```

### JavaScript Functions:
```javascript
// Print without redirect
window.print()

// Print and continue
function printAndContinue() {
    window.print();
    setTimeout(() => {
        window.location.href = 'generate-shipping-labels.php';
    }, 1000);
}
```

---

## 🎯 Use Cases

### Scenario 1: Early Packing
**Problem**: Warehouse needs to identify boxes during packing
**Solution**: Print box labels immediately, shows "Not yet generated" for tracking
**Workflow**: Print Only → Continue packing → Generate shipping later

### Scenario 2: Complete Workflow
**Problem**: Ready to pack and ship in one go
**Solution**: Pack → Print box labels → Print shipping labels
**Workflow**: Print & Continue → Auto-redirect to shipping label generation

### Scenario 3: Reprint with Tracking
**Problem**: Originally printed without tracking, now have tracking numbers
**Solution**: Reprint box labels after shipping labels created
**Workflow**: Generate shipping labels → Reprint box labels (now shows tracking)

### Scenario 4: Sea of Boxes
**Problem**: 50+ boxes in warehouse, can't identify which goes where
**Solution**: Giant destination names on every box
**Result**: Staff can see "WELLINGTON LAMBTON QUAY" from 10 feet away

---

## ✅ Integration Complete

### All 3 Layouts Updated:

**Layout A (pack-layout-a-v2.php):**
- ✅ Button in freight console
- ✅ Tool button in tools grid
- ✅ Opens in new tab

**Layout B (pack-layout-b-v2.php):**
- ✅ Button in Freight tab
- ✅ Tool card in Tools tab
- ✅ Opens in new tab

**Layout C (pack-layout-c-v2.php):**
- ✅ Button in Freight panel
- ✅ Tool card in Tools panel
- ✅ Opens in new tab

---

## 🚀 Next Steps

### Immediate:
1. ✅ Box labels system created
2. ✅ Integrated into all 3 layouts
3. ✅ Print-only option available
4. ✅ Giant destination store display
5. ✅ Tracking number support (optional)

### To Do:
1. ⏳ Create `generate-shipping-labels.php` (courier API integration)
2. ⏳ Database schema for boxes/parcels
3. ⏳ Thermal label printer support (80mm × 100mm)
4. ⏳ Auto-save box assignments to database
5. ⏳ Reprint functionality with updated tracking

---

## 📝 Summary

**Box Labels = Internal warehouse identification**
**Shipping Labels = Courier tracking labels**

Both are important, both serve different purposes, both are now integrated into the packing workflow! 📦✅

**Destination store is UNMISSABLE** - 42-48px font, red background, all caps, with address. Exactly what the packers need! 🎯
