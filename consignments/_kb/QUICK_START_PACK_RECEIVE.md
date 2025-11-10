# 🚀 QUICK START: Pack & Receive Pages - IMMEDIATE ACTION GUIDE

**Created:** November 9, 2025
**Priority:** GET OPERATIONAL NOW
**Est. Time:** 8-12 hours to full production

---

## ⚡ FASTEST PATH TO OPERATIONAL

### **Option 1: Packing First** (Recommended - 4 hours)
```
Hour 1: Wire real data to pack.php
Hour 2: Add barcode scanning
Hour 3: Implement auto-save + state transitions
Hour 4: Test & deploy to staging
```

### **Option 2: Receiving First** (4 hours)
```
Hour 1: Build receive.php structure
Hour 2: Add barcode scanning for receiving
Hour 3: Signature pad + photo upload basics
Hour 4: Complete receiving submission
```

### **Option 3: Both Parallel** (8 hours - 2 people)
```
Person A: Packing (4 hours)
Person B: Receiving (4 hours)
Together: Integration testing (2 hours)
```

---

## 🎯 CRITICAL FILES TO EDIT

### **For Packing:**
```
📁 /modules/consignments/stock-transfers/
├── pack.php (or pack-layout-a-v2.php) ← Main UI file
├── js/pack.js ← Wire API calls here
├── js/barcode-handler.js ← Create this (barcode scanning)
├── js/auto-save.js ← Create this (auto-save logic)
└── css/pack.css ← Style tweaks

📁 /modules/consignments/TransferManager/
└── backend.php ← API endpoint (already 2,219 lines - just use it!)
```

### **For Receiving:**
```
📁 /modules/consignments/stock-transfers/
├── receive.php ← CREATE THIS (main UI)
├── js/receive.js ← CREATE THIS (core logic)
├── js/signature-pad.min.js ← Download from CDN
└── css/receive.css ← CREATE THIS (styling)

📁 /modules/consignments/api/
└── purchase-orders/receive.php ← Already exists, use it!
```

---

## 🔥 COPY-PASTE READY CODE SNIPPETS

### **1. Load Transfer Data (Packing)**
```javascript
// Add to pack.js
async function loadTransferData(transferId) {
    try {
        const response = await fetch(
            `/modules/consignments/TransferManager/backend.php?action=get_transfer_detail&id=${transferId}`,
            {headers: {'X-Requested-With': 'XMLHttpRequest'}}
        );

        if (!response.ok) throw new Error('Failed to load transfer');

        const data = await response.json();

        if (data.success) {
            renderTransferUI(data.data);
        } else {
            showError(data.message);
        }
    } catch (error) {
        console.error('Load error:', error);
        showError('Failed to load transfer data');
    }
}

// Call on page load
document.addEventListener('DOMContentLoaded', () => {
    const transferId = new URLSearchParams(window.location.search).get('transfer_id');
    if (transferId) {
        loadTransferData(transferId);
    }
});
```

### **2. Barcode Scanning (Universal)**
```javascript
// Create: js/barcode-handler.js
class BarcodeScanner {
    constructor(onScan) {
        this.buffer = '';
        this.timeout = null;
        this.onScan = onScan;
        this.setupListeners();
    }

    setupListeners() {
        document.addEventListener('keypress', (e) => {
            // Ignore if typing in input
            if (e.target.tagName === 'INPUT' && e.target.id !== 'barcode-input') {
                return;
            }

            clearTimeout(this.timeout);

            if (e.key === 'Enter' && this.buffer) {
                this.onScan(this.buffer);
                this.buffer = '';
            } else {
                this.buffer += e.key;
                this.timeout = setTimeout(() => {
                    this.buffer = '';
                }, 100); // Reset after 100ms (barcodes scan fast)
            }
        });
    }
}

// Usage:
const scanner = new BarcodeScanner((barcode) => {
    console.log('Scanned:', barcode);
    findAndSelectProduct(barcode);
});
```

### **3. Auto-Save (Packing)**
```javascript
// Create: js/auto-save.js
const AutoSave = {
    transferId: null,
    timeout: null,

    init(transferId) {
        this.transferId = transferId;

        // Watch all quantity inputs
        document.querySelectorAll('.qty-packed').forEach(input => {
            input.addEventListener('input', () => this.trigger());
        });
    },

    trigger() {
        clearTimeout(this.timeout);
        this.showSaving();

        this.timeout = setTimeout(() => {
            this.save();
        }, 2000); // Save after 2 seconds of inactivity
    },

    async save() {
        const data = this.collectPackedData();

        try {
            const response = await fetch('/modules/consignments/TransferManager/backend.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    action: 'auto_save_packing',
                    transfer_id: this.transferId,
                    items: data
                })
            });

            if (response.ok) {
                this.showSaved();
            }
        } catch (error) {
            console.error('Auto-save error:', error);
        }
    },

    collectPackedData() {
        const items = [];
        document.querySelectorAll('.pack-item').forEach(row => {
            const productId = row.dataset.productId;
            const qty = row.querySelector('.qty-packed').value;
            if (qty > 0) {
                items.push({product_id: productId, qty_packed: parseInt(qty)});
            }
        });
        return items;
    },

    showSaving() {
        document.getElementById('save-status').textContent = '💾 Saving...';
    },

    showSaved() {
        const status = document.getElementById('save-status');
        status.textContent = '✅ Saved';
        setTimeout(() => {
            status.textContent = '';
        }, 2000);
    }
};

// Initialize on page load
AutoSave.init(transferId);
```

### **4. Receiving Page Structure**
```php
<?php
// Create: stock-transfers/receive.php
require_once '../bootstrap.php';

$transferId = $_GET['transfer_id'] ?? null;
if (!$transferId) {
    die('Transfer ID required');
}

// Load transfer data
$consignmentService = new \Consignments\Lib\ConsignmentsService();
$transfer = $consignmentService->getTransferById((int)$transferId);

if (!$transfer) {
    die('Transfer not found');
}

// Check status
if (!in_array($transfer['status'], ['SENT', 'RECEIVING'])) {
    die('Transfer cannot be received (status: ' . $transfer['status'] . ')');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receive Transfer <?= htmlspecialchars($transfer['public_id']) ?></title>
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/receive.css">
</head>
<body>
    <div class="container-fluid py-3">
        <h1>📥 Receive Transfer from <?= htmlspecialchars($transfer['outlet_from_name']) ?></h1>

        <!-- Barcode Scanner -->
        <div class="scanner-section mb-4">
            <label>Scan Barcode:</label>
            <input type="text"
                   id="barcode-input"
                   class="form-control form-control-lg"
                   placeholder="Ready to scan..."
                   autocomplete="off"
                   autofocus>
            <small class="text-muted">Focus this field and scan barcodes</small>
        </div>

        <!-- Items Table -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Expected</th>
                    <th>Received</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="items-list">
                <?php foreach ($transfer['items'] as $item): ?>
                <tr class="receive-item" data-product-id="<?= $item['product_id'] ?>">
                    <td>
                        <?php if ($item['image_url']): ?>
                        <img src="<?= htmlspecialchars($item['image_url']) ?>"
                             alt="<?= htmlspecialchars($item['name']) ?>"
                             style="width:50px;height:50px;object-fit:cover;">
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                        <small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                    </td>
                    <td><?= $item['qty_expected'] ?></td>
                    <td>
                        <input type="number"
                               class="form-control qty-received"
                               min="0"
                               max="<?= $item['qty_expected'] * 2 ?>"
                               value="0"
                               data-expected="<?= $item['qty_expected'] ?>">
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary photo-btn">
                            📷 Photo
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Notes -->
        <div class="mb-3">
            <label>Receiving Notes:</label>
            <textarea id="receiving-notes" class="form-control" rows="3"></textarea>
        </div>

        <!-- Signature -->
        <div class="signature-section mb-4">
            <label>Signature:</label>
            <canvas id="signature-pad" width="400" height="200" style="border:1px solid #ddd;"></canvas>
            <button id="clear-signature" class="btn btn-sm btn-secondary mt-2">Clear</button>
        </div>

        <!-- Actions -->
        <div class="d-flex gap-2">
            <button id="save-progress" class="btn btn-secondary">💾 Save Progress</button>
            <button id="complete-receive" class="btn btn-success">✅ Complete Receiving</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="js/receive.js"></script>
</body>
</html>
```

### **5. Receiving Logic**
```javascript
// Create: js/receive.js
const ReceiveManager = {
    transferId: null,
    signaturePad: null,

    init(transferId) {
        this.transferId = transferId;
        this.setupBarcodeScanner();
        this.setupSignaturePad();
        this.setupButtons();
    },

    setupBarcodeScanner() {
        const input = document.getElementById('barcode-input');
        let buffer = '';

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.handleBarcodeScan(buffer || input.value);
                buffer = '';
                input.value = '';
            } else {
                buffer += e.key;
            }
        });
    },

    handleBarcodeScan(barcode) {
        console.log('Scanned:', barcode);

        // Find product by SKU or barcode
        const rows = document.querySelectorAll('.receive-item');
        let found = false;

        rows.forEach(row => {
            const sku = row.querySelector('small').textContent.replace('SKU: ', '');
            if (sku === barcode || row.dataset.barcode === barcode) {
                const input = row.querySelector('.qty-received');
                input.value = parseInt(input.value || 0) + 1;
                input.classList.add('highlight-scan');
                setTimeout(() => input.classList.remove('highlight-scan'), 500);
                found = true;
            }
        });

        if (!found) {
            alert('Product not found: ' + barcode);
        }
    },

    setupSignaturePad() {
        const canvas = document.getElementById('signature-pad');
        this.signaturePad = new SignaturePad(canvas);

        document.getElementById('clear-signature').addEventListener('click', () => {
            this.signaturePad.clear();
        });
    },

    setupButtons() {
        document.getElementById('save-progress').addEventListener('click', () => {
            this.saveProgress();
        });

        document.getElementById('complete-receive').addEventListener('click', () => {
            this.completeReceiving();
        });
    },

    async saveProgress() {
        const data = this.collectData(false);
        await this.submitToAPI('save_receiving_progress', data);
    },

    async completeReceiving() {
        if (!confirm('Complete receiving? This cannot be undone.')) return;

        const data = this.collectData(true);

        if (this.signaturePad.isEmpty()) {
            alert('Signature required');
            return;
        }

        data.signature = this.signaturePad.toDataURL();

        const result = await this.submitToAPI('complete_receiving', data);

        if (result.success) {
            alert('Receiving completed!');
            window.location.href = '/modules/consignments/TransferManager/frontend.php';
        }
    },

    collectData(isFinal) {
        const items = [];
        document.querySelectorAll('.receive-item').forEach(row => {
            const productId = row.dataset.productId;
            const qtyReceived = parseInt(row.querySelector('.qty-received').value || 0);
            const qtyExpected = parseInt(row.querySelector('.qty-received').dataset.expected);

            items.push({
                product_id: productId,
                qty_received: qtyReceived,
                qty_expected: qtyExpected,
                discrepancy: qtyReceived !== qtyExpected
            });
        });

        return {
            transfer_id: this.transferId,
            items: items,
            notes: document.getElementById('receiving-notes').value,
            completed: isFinal
        };
    },

    async submitToAPI(action, data) {
        try {
            const response = await fetch('/modules/consignments/TransferManager/backend.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({action, data})
            });

            return await response.json();
        } catch (error) {
            console.error('API error:', error);
            alert('Error: ' + error.message);
            return {success: false};
        }
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    const transferId = new URLSearchParams(window.location.search).get('transfer_id');
    ReceiveManager.init(transferId);
});
```

---

## 🧪 TESTING CHECKLIST

### **Packing Interface:**
```
☐ Load transfer with real ID
☐ Display all products correctly
☐ Scan barcode → quantity increments
☐ Manual quantity entry works
☐ Auto-save triggers after 2 seconds
☐ "Mark as Sent" button appears
☐ Click "Mark as Sent" → status changes
☐ Freight console shows carrier options
☐ Print label button works
☐ Mobile responsive (test on iPad)
```

### **Receiving Interface:**
```
☐ Load transfer with status=SENT
☐ Display expected quantities
☐ Scan barcode → received qty increments
☐ Manual entry works
☐ Discrepancies highlighted (qty ≠ expected)
☐ Photo upload button functional
☐ Signature pad works
☐ "Complete Receiving" validates signature
☐ Submission succeeds
☐ Status changes to RECEIVED
```

### **End-to-End Flow:**
```
☐ Create transfer (DRAFT)
☐ Add products
☐ Open pack.php
☐ Pack items
☐ Mark as SENT
☐ Freight booking triggered
☐ Tracking number saved
☐ Open receive.php
☐ Scan items
☐ Sign
☐ Complete receiving
☐ Status = RECEIVED
☐ Vend sync job queued
```

---

## 🚀 DEPLOYMENT COMMANDS

### **1. Create Feature Branch**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/consignments
git checkout -b feature/pack-receive-pages
```

### **2. Add Your Changes**
```bash
git add stock-transfers/pack.php
git add stock-transfers/receive.php
git add stock-transfers/js/
git add stock-transfers/css/
git commit -m "feat: Complete pack & receive interfaces with barcode scanning"
```

### **3. Push to Remote**
```bash
git push origin feature/pack-receive-pages
```

### **4. Test on Staging**
```bash
# SSH to staging server
ssh staging-server

# Pull changes
cd /var/www/staging/modules/consignments
git fetch
git checkout feature/pack-receive-pages
git pull

# Clear cache
php bin/clear-cache.php

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### **5. Merge to Production**
```bash
# After successful staging tests
git checkout main
git merge feature/pack-receive-pages
git push origin main

# Deploy to production
ssh production-server
cd /var/www/staff.vapeshed.co.nz/modules/consignments
git pull origin main
php database/run-migration.php
sudo systemctl restart php8.2-fpm
```

---

## 🆘 TROUBLESHOOTING

### **Problem: API returns 401 Unauthorized**
```
Solution: Check session is active
- Verify $_SESSION['user_id'] exists
- Check CSRF token in meta tag
- Ensure bootstrap.php loaded
```

### **Problem: Barcode scanner not working**
```
Solution: Check focus and event listeners
- Ensure barcode input has autofocus
- Check console for JavaScript errors
- Test with manual "Enter" key press
- Verify barcode format (no special chars)
```

### **Problem: Transfer not loading**
```
Solution: Check transfer_id parameter
- Verify URL has ?transfer_id=XXX
- Check transfer exists in database
- Verify user has permission to view
- Check status is valid for operation
```

### **Problem: Auto-save not triggering**
```
Solution: Check timeout and event binding
- Verify .qty-packed class on inputs
- Check console for errors
- Test manual save button first
- Increase timeout to 5 seconds for testing
```

---

## 📱 MOBILE OPTIMIZATION TIPS

### **CSS for Tablets:**
```css
/* Add to css/pack.css or css/receive.css */
@media (max-width: 768px) {
    .pack-item, .receive-item {
        font-size: 1.2rem;
        padding: 1rem;
    }

    input[type="number"] {
        font-size: 1.5rem;
        min-height: 60px;
        text-align: center;
    }

    button {
        min-height: 50px;
        font-size: 1.2rem;
        padding: 1rem;
    }

    .scanner-section input {
        font-size: 2rem;
        min-height: 80px;
    }
}

/* Touch-friendly buttons */
.btn-touch {
    min-width: 100px;
    min-height: 60px;
    font-size: 1.3rem;
    margin: 5px;
}

/* Highlight scanned items */
.highlight-scan {
    background-color: #ffc !important;
    animation: pulse 0.5s;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
```

---

## 🎯 SUCCESS CRITERIA

### **You're ready for production when:**
✅ All items in testing checklist pass
✅ Staff can complete full workflow without errors
✅ Mobile devices work smoothly
✅ Auto-save prevents data loss
✅ Barcodes scan correctly
✅ Vend sync jobs complete successfully
✅ No console errors in browser
✅ Page load < 1 second
✅ Works in Chrome, Firefox, Safari

---

## 🎉 NEXT STEPS AFTER OPERATIONAL

1. **Add Advanced Features:**
   - Photo upload with drag-and-drop
   - Batch barcode scanning
   - Voice input for hands-free
   - Offline mode with sync

2. **Build Reports:**
   - Packing efficiency dashboard
   - Receiving accuracy metrics
   - Staff performance tracking
   - Discrepancy analysis

3. **Extend to Other Types:**
   - Juice transfers (specialized handling)
   - Staff transfers (manager approval)
   - Purchase orders (supplier integration)

4. **Optimize Performance:**
   - Add Redis caching
   - Lazy load product images
   - Preload common barcodes
   - WebSocket for real-time updates

---

**READY TO START?** Pick your priority:
- ⚡ **Packing First:** Start with "1. Load Transfer Data" above
- ⚡ **Receiving First:** Start with "4. Receiving Page Structure" above
- ⚡ **Both Together:** Split the work with a colleague

**Let's build!** 🚀
