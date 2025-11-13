# 🚀 Advanced Barcode Scanner Widget - Integration Guide

## Quick Start (2 Lines of Code!)

Add this to **any page** where you want the barcode scanner:

```html
<!-- At bottom of page, before </body> -->
<script src="/modules/consignments/stock-transfers/js/barcode-scanner.js"></script>
<script src="/modules/consignments/stock-transfers/js/barcode-widget-advanced.js"></script>
<script src="/modules/consignments/stock-transfers/js/barcode-widget-integration.js"></script>
```

**That's it!** The widget will appear as a small icon in the bottom-right corner.

---

## Auto-Detection (Recommended)

Add data attributes to your `<body>` tag for automatic configuration:

```html
<body data-transfer-id="<?= $transferId ?>"
      data-transfer-type="stock_transfer"
      data-user-id="<?= $_SESSION['user_id'] ?>"
      data-outlet-id="<?= $_SESSION['outlet_id'] ?>">
```

---

## Custom Integration (Advanced)

If you need custom behavior, set options **before** loading the integration script:

```html
<script>
// Define custom options
window.cisScannerOptions = {
    transferId: 123,
    transferType: 'consignment', // or 'purchase_order', 'stock_transfer'
    userId: 45,
    outletId: 2,

    // Custom scan handler
    onScan: async function(barcode) {
        // Your custom logic here
        console.log('Custom scan handler:', barcode);

        // Example: Call your existing function
        if (typeof processBarcode === 'function') {
            return await processBarcode(barcode);
        }

        return { success: true, message: 'Processed' };
    },

    // Custom photo handler
    onPhoto: async function(photoBlob) {
        // Your custom photo upload logic
        const formData = new FormData();
        formData.append('photo', photoBlob);
        formData.append('product_id', currentProductId);

        const response = await fetch('/api/upload', {
            method: 'POST',
            body: formData
        });

        return await response.json();
    }
};
</script>

<!-- Then load the scripts -->
<script src="/modules/consignments/stock-transfers/js/barcode-scanner.js"></script>
<script src="/modules/consignments/stock-transfers/js/barcode-widget-advanced.js"></script>
<script src="/modules/consignments/stock-transfers/js/barcode-widget-integration.js"></script>
```

---

## Features Overview

### 1. **Compact Icon**
- Small purple button in bottom-right corner
- Pulses when active
- Click to expand

### 2. **Layout Modes**
- **Bottom Bar** (default on desktop): 280px height bar at bottom
- **Right Sidebar** (desktop): 380px wide sidebar on right
- **Fullscreen** (mobile): Takes full screen for easy scanning
- Click layout button in header to cycle modes

### 3. **Barcode Mode**
- USB scanner support (automatic)
- Camera scanner (point at barcode)
- Real-time scanning feedback
- Audio tones: scan, success, not found

### 4. **Photo Mode**
- Switch to photo tab
- Capture photos for damage documentation
- Auto-upload to server
- Gallery view of captured photos

### 5. **Audio Feedback**
7 distinct tones:
- **Scan**: Standard beep (800Hz, 100ms)
- **Success**: Higher pitched (1000Hz, 150ms)
- **Error**: Low buzz (400Hz, 200ms)
- **Not Found**: Lower buzz (300Hz, 300ms)
- **Complete**: Triple ascending tone (600→800→1000Hz)
- **Target Reached**: Double tone (1200→1000Hz)
- **Photo**: Quick high beep (1200Hz, 50ms)

### 6. **State Persistence**
- Remembers expanded/collapsed state
- Remembers last mode (barcode/photo)
- Remembers layout preference
- Session statistics persist across page loads
- Scan history saved (last 20 scans)

### 7. **Mobile Optimized**
- Auto-switches to fullscreen mode on mobile
- Touch-friendly controls
- Large buttons for photo capture
- Positioned above mobile navigation bars

---

## Integration Examples

### Example 1: Stock Transfer Pack Page

```php
<!-- pack.php -->
<body data-transfer-id="<?= $transfer['transfer_id'] ?>"
      data-transfer-type="stock_transfer"
      data-user-id="<?= $_SESSION['user_id'] ?>"
      data-outlet-id="<?= $_SESSION['outlet_id'] ?>">

<div class="container">
    <h1>Pack Transfer #<?= $transfer['transfer_id'] ?></h1>

    <table class="table">
        <tbody>
            <?php foreach ($products as $product): ?>
            <tr data-barcode="<?= $product['barcode'] ?>">
                <td><?= $product['product_name'] ?></td>
                <td><?= $product['quantity'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Scanner widget auto-loads -->
<script src="/modules/consignments/stock-transfers/js/barcode-scanner.js"></script>
<script src="/modules/consignments/stock-transfers/js/barcode-widget-advanced.js"></script>
<script src="/modules/consignments/stock-transfers/js/barcode-widget-integration.js"></script>

</body>
```

### Example 2: Consignment Receive Page with Custom Handler

```php
<!-- receive.php -->
<script>
window.cisScannerOptions = {
    transferId: <?= $consignment['consignment_id'] ?>,
    transferType: 'consignment',

    onScan: async function(barcode) {
        // Use existing receiveProduct function
        const result = await receiveProduct(barcode);
        return result;
    },

    onPhoto: async function(photoBlob) {
        // Upload damage photo
        return await uploadDamagePhoto(photoBlob, currentProductId);
    }
};
</script>

<script src="/modules/consignments/stock-transfers/js/barcode-scanner.js"></script>
<script src="/modules/consignments/stock-transfers/js/barcode-widget-advanced.js"></script>
<script src="/modules/consignments/stock-transfers/js/barcode-widget-integration.js"></script>
```

### Example 3: Purchase Order Page

```html
<body data-transfer-id="456"
      data-transfer-type="purchase_order"
      data-user-id="12"
      data-outlet-id="3">

    <!-- Your page content -->

    <!-- Widget auto-loads -->
    <script src="/modules/consignments/stock-transfers/js/barcode-scanner.js"></script>
    <script src="/modules/consignments/stock-transfers/js/barcode-widget-advanced.js"></script>
    <script src="/modules/consignments/stock-transfers/js/barcode-widget-integration.js"></script>
</body>
```

---

## Manual Control (JavaScript API)

If you need to control the widget programmatically:

```javascript
// Access the widget instance
const widget = window.cisBarcodeWidget;

// Expand/collapse
widget.expand();
widget.collapse();
widget.toggle();

// Switch modes
widget.switchMode('barcode'); // or 'photo'

// Change layout
widget.setLayoutMode('bottom'); // or 'right', 'fullscreen'
widget.cycleLayout(); // Cycle through modes

// Start/stop scanner
widget.startScanner();
widget.stopScanner();

// Capture photo (when in photo mode)
widget.capturePhoto();

// Play audio tones
widget.playTone('success'); // scan, success, error, notfound, complete, target, photo

// Access stats
console.log(widget.sessionStats);
// { totalScans: 15, successfulScans: 12, failedScans: 3, photosTaken: 2 }

// Access history
console.log(widget.scanHistory);
// [ {barcode: '123456', timestamp: Date, mode: 'barcode'}, ... ]
```

---

## Per-Transfer-Type Configuration

Configure scanner availability per transfer type in the admin panel:

1. Go to **Admin → Barcode Management**
2. Navigate to **"Per-Transfer-Type Settings"** tab
3. Enable/disable for:
   - Stock Transfers
   - Consignments
   - Purchase Orders

The widget will automatically hide if disabled for the current page type.

---

## Keyboard Shortcuts

- **Escape**: Collapse widget
- **Space**: Toggle expand/collapse (when focused)
- **B**: Switch to barcode mode
- **P**: Switch to photo mode
- **L**: Cycle layout mode

---

## Mobile Experience

On mobile devices (< 768px width):
- Widget automatically uses fullscreen mode
- Larger touch targets
- Positioned above mobile nav bars
- Photo capture optimized for camera access

---

## Troubleshooting

### Widget doesn't appear
1. Check browser console for errors
2. Verify all 3 scripts are loaded
3. Check that scanner is enabled for transfer type in admin panel

### Camera not working
1. Ensure HTTPS (cameras require secure context)
2. Grant camera permissions when prompted
3. Check browser console for camera errors

### Audio not playing
1. Audio requires user interaction first (click to expand widget)
2. Check volume settings
3. Verify `audio_enabled` in config

### Scans not working
1. Check `onScan` callback is returning correct format: `{success: boolean, message: string}`
2. Verify barcode exists on page with `data-barcode` attribute
3. Check browser console for callback errors

---

## Next Steps

1. ✅ Add to pack layouts (A, B, C)
2. ✅ Add to receive layouts
3. ✅ Test on mobile devices
4. ⏳ Create photo upload API endpoint
5. ⏳ Add per-transfer-type database config
6. ⏳ Train staff on photo mode for damage documentation

---

## Support

Questions? Check the full documentation:
- `/docs/BARCODE_SCANNER_COMPLETE_GUIDE.md`
- `/docs/BARCODE_DEPLOYMENT_CHECKLIST.md`
