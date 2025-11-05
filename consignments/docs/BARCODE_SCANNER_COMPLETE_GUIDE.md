# CIS Barcode Scanner System - Complete Documentation

## 🎯 Overview

Enterprise-grade barcode scanning system with **comprehensive management controls** allowing configuration at:
- **Global level** (all outlets, all users)
- **Per-outlet level** (specific store settings)
- **Per-user level** (individual preferences)

### Features

✅ **Dual Scanner Support**
- USB hardware scanners (keyboard wedge)
- Camera-based scanning (phone/webcam)
- Manual entry fallback

✅ **Advanced Configuration**
- Enable/disable per outlet, user, or globally
- Audio feedback (3 customizable tones)
- Visual feedback (colored flashes)
- Scan cooldown & duplicate prevention
- Exact/fuzzy matching modes

✅ **Complete Logging & Analytics**
- Every scan recorded to database
- User statistics tracking
- Outlet performance metrics
- Daily/weekly/monthly reports

✅ **Management Control Panel**
- Beautiful admin UI for all settings
- Real-time scan monitoring
- Audit trail of all changes
- Export capabilities

---

## 📁 File Structure

```
/modules/consignments/
├── db/schema/
│   └── barcode_system.sql              # Complete database schema
├── admin/
│   └── barcode-management.php          # Management control panel UI
├── api/
│   ├── barcode_config.php              # Configuration CRUD API
│   └── barcode_log.php                 # Scan logging API
└── stock-transfers/js/
    ├── barcode-scanner.js              # Main scanner library
    └── pack-with-scanner.js            # Integration example
```

---

## 🗄️ Database Tables

### 1. **BARCODE_SCANS**
Complete scan history with analytics
```sql
- transfer_id, consignment_id, purchase_order_id (context)
- barcode_value, barcode_format, scan_method
- vend_product_id, sku, product_name (matched product)
- scan_result (success|not_found|duplicate|quantity_exceeded|blocked)
- qty_scanned, audio_feedback
- user_id, outlet_id, scan_timestamp
- scan_duration_ms (performance tracking)
- device_type, user_agent
```

### 2. **BARCODE_CONFIGURATION**
Global & per-outlet settings
```sql
- outlet_id (NULL = global default)
- enabled, usb_scanner_enabled, camera_scanner_enabled, manual_entry_enabled
- scan_mode (auto|usb_only|camera_only|manual_only)
- require_exact_match, allow_duplicate_scans, block_on_qty_exceed
- audio_enabled, audio_volume, tone1/2/3_frequency, tone_duration_ms
- visual_feedback_enabled, success/warning/error_color, flash_duration_ms
- scan_cooldown_ms, camera_fps, camera_resolution
- log_all_scans, log_failed_scans, log_retention_days
```

### 3. **BARCODE_USER_PREFERENCES**
Per-user overrides
```sql
- user_id, outlet_id (NULL = all outlets)
- usb_scanner_enabled, camera_scanner_enabled, manual_entry_enabled
- audio_enabled, audio_volume, visual_feedback_enabled
- preferred_scan_method, preferred_device
- total_scans, successful_scans, failed_scans, avg_scan_speed_ms
```

### 4. **BARCODE_AUDIT_LOG**
Track all configuration changes
```sql
- action, target_type, target_id
- changed_by, field_name, old_value, new_value
- ip_address, user_agent, created_at
```

### 5. **BARCODE_ANALYTICS**
Aggregated daily statistics
```sql
- date, outlet_id, user_id
- total_scans, successful_scans, failed_scans
- usb_scans, camera_scans, manual_scans
- avg_scan_duration_ms, unique_products_scanned
```

---

## 🚀 Setup Instructions

### Step 1: Install Database Schema

```bash
mysql -u root -p cis_database < /modules/consignments/db/schema/barcode_system.sql
```

This creates:
- 5 tables (SCANS, CONFIGURATION, USER_PREFERENCES, AUDIT_LOG, ANALYTICS)
- 3 views (v_barcode_config_active, v_barcode_user_stats, v_barcode_daily_summary)
- Default global configuration

### Step 2: Set Permissions

Ensure admin users have `barcode_admin` permission:
```sql
INSERT INTO user_permissions (user_id, permission) VALUES (1, 'barcode_admin');
```

### Step 3: Access Management Panel

Navigate to:
```
https://staff.vapeshed.co.nz/modules/consignments/admin/barcode-management.php
```

### Step 4: Configure Global Settings

1. **Master Controls**: Enable/disable scanner types
2. **Scanner Behavior**: Set detection mode, cooldown, duplicate handling
3. **Audio Settings**: Configure 3 tone frequencies and volume
4. **Visual Feedback**: Set success/warning/error colors
5. **Logging**: Configure what to log and retention period

### Step 5: Configure Per-Outlet (Optional)

Go to "Outlet Configuration" tab:
- Click "Configure Outlet"
- Select outlet
- Override specific settings for that store
- Save

Settings cascade: **User Prefs → Outlet Config → Global Config**

### Step 6: Integrate Scanner into Your Page

```html
<!-- Include scanner library -->
<script src="/modules/consignments/stock-transfers/js/barcode-scanner.js"></script>

<!-- Camera preview container (optional, for camera scanning) -->
<div id="barcodeCameraPreview" style="width: 100%; max-width: 640px; height: 480px;"></div>

<!-- Manual entry (optional) -->
<input type="text" id="manualBarcodeInput" placeholder="Scan or type barcode...">

<!-- Camera toggle button (optional) -->
<button id="toggleCamera">Start Camera</button>
```

```javascript
// Initialize scanner
const scanner = new CISBarcodeScanner({
    transferId: 123,          // Current transfer ID
    userId: 45,               // Current user ID
    outletId: 7,              // Current outlet ID
    container: '#barcodeCameraPreview',

    onScan: async (barcode, method, format) => {
        console.log(`Scanned: ${barcode} via ${method}`);

        // Your logic here: find product, update quantity, etc.
        const product = findProductByBarcode(barcode);

        if (product) {
            incrementProductQuantity(product);
            return {
                success: true,
                productId: product.id,
                sku: product.sku,
                productName: product.name,
                qty: 1,
                element: product.rowElement  // For visual feedback
            };
        } else {
            return {
                success: false,
                reason: 'not_found'
            };
        }
    },

    onError: (error) => {
        console.error('Scanner error:', error);
        alert('Scanner error: ' + error.message);
    }
});

// Camera controls
document.getElementById('toggleCamera').addEventListener('click', () => {
    scanner.toggleCamera();
});

// Manual entry
document.getElementById('manualBarcodeInput').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        scanner.manualEntry(e.target.value);
        e.target.value = '';
    }
});
```

---

## 🎛️ Management Control Panel Features

### Dashboard Overview
- **Total Scans (30d)**: All scans in last 30 days
- **Successful Scans**: Success rate percentage
- **Active Users**: Number of users actively scanning
- **Active Outlets**: Stores with scanning activity

### Global Settings Tab
Complete control over:
- Master enable/disable toggle
- Scanner type enables (USB/Camera/Manual)
- Detection mode (Auto/USB Only/Camera Only/Manual Only)
- Exact match vs fuzzy matching
- Duplicate scan handling
- Quantity exceed blocking
- Scan cooldown (milliseconds)
- Audio tones (3 frequencies, volume, duration)
- Visual feedback (3 colors, flash duration)
- Logging preferences
- Data retention period

### Outlet Configuration Tab
- View all outlet-specific configs
- Add new outlet config
- Override global settings per outlet
- Delete outlet config (revert to global)
- Badge indicators show active overrides

### User Preferences Tab
- View all user preferences
- Set per-user scanner preferences
- Allow users to choose preferred method
- Track user scan statistics
- Delete user preferences

### Scan History Tab
**Filters:**
- Date range (24h, 7d, 30d, 90d, 1y)
- Outlet filter
- Scan method filter (USB/Camera/Manual)
- Result filter (Success/Not Found/Duplicate/Blocked)

**Data Displayed:**
- Timestamp
- User name
- Outlet name
- Barcode value
- Product matched
- Scan method
- Result
- Duration (ms)

**Actions:**
- Export to CSV
- Refresh data
- Pagination support

### Analytics Tab
**Charts & Graphs:**
- Daily scan volume
- Success rate trends
- Method distribution (USB vs Camera vs Manual)
- Top performing users
- Top scanned products
- Average scan speed per user
- Peak scanning hours

### Audit Log Tab
Complete trail of:
- Configuration changes
- Scanner enable/disable actions
- Setting modifications
- Who changed what, when
- IP address tracking
- User agent logging

---

## 🔧 API Endpoints

### Configuration API (`/api/barcode_config.php`)

#### Get Global Config
```javascript
POST /api/barcode_config.php
{
  "action": "get_global"
}
```

#### Update Global Config
```javascript
POST /api/barcode_config.php
{
  "action": "update_global",
  "enabled": 1,
  "usb_scanner_enabled": 1,
  "camera_scanner_enabled": 1,
  "audio_enabled": 1,
  "audio_volume": 0.5,
  ...
}
```

#### Get Effective Config (User + Outlet + Global merged)
```javascript
POST /api/barcode_config.php
{
  "action": "get_effective_config",
  "user_id": 45,
  "outlet_id": 7
}

// Returns merged config with precedence: User > Outlet > Global
```

#### Update Outlet Config
```javascript
POST /api/barcode_config.php
{
  "action": "update_outlet",
  "outlet_id": 7,
  "enabled": 1,
  "usb_scanner_enabled": 0,  // Disable USB at this outlet
  ...
}
```

#### Update User Preferences
```javascript
POST /api/barcode_config.php
{
  "action": "update_user_prefs",
  "user_id": 45,
  "outlet_id": 7,  // Optional, NULL = all outlets
  "preferred_scan_method": "camera",
  "audio_enabled": 0,
  ...
}
```

#### Get Scan History
```javascript
POST /api/barcode_config.php
{
  "action": "get_scan_history",
  "date_range": 30,
  "outlet_id": 7,
  "scan_method": "usb_scanner",
  "limit": 100,
  "offset": 0
}
```

### Logging API (`/api/barcode_log.php`)

#### Log a Scan
```javascript
POST /api/barcode_log.php
{
  "transfer_id": 123,
  "barcode_value": "9781234567890",
  "barcode_format": "EAN13",
  "scan_method": "usb_scanner",
  "vend_product_id": "abc-123-def",
  "sku": "PROD-001",
  "product_name": "Example Product",
  "scan_result": "success",
  "qty_scanned": 1,
  "audio_feedback": "tone1",
  "user_id": 45,
  "outlet_id": 7,
  "scan_duration_ms": 234
}
```

---

## 📊 Configuration Hierarchy

Settings are resolved in this order (highest priority first):

1. **User Preferences** (specific user, specific outlet)
2. **User Preferences** (specific user, all outlets)
3. **Outlet Configuration** (specific outlet)
4. **Global Configuration** (default for all)

### Example Scenario:

**Global Config:**
```json
{
  "enabled": true,
  "usb_scanner_enabled": true,
  "camera_scanner_enabled": true,
  "audio_enabled": true,
  "audio_volume": 0.5
}
```

**Outlet 7 Config:**
```json
{
  "usb_scanner_enabled": false,  // Override: Disable USB at outlet 7
  "audio_volume": 0.3            // Override: Quieter audio at outlet 7
}
```

**User 45 Preferences:**
```json
{
  "audio_enabled": false  // Override: This user wants no audio
}
```

**Effective Config for User 45 at Outlet 7:**
```json
{
  "enabled": true,                    // From global
  "usb_scanner_enabled": false,       // From outlet config
  "camera_scanner_enabled": true,     // From global
  "audio_enabled": false,             // From user prefs (highest priority)
  "audio_volume": 0.3                 // From outlet config (but user disabled audio anyway)
}
```

---

## 🔊 Audio Feedback Tones

Three customizable tones for different scan outcomes:

### Tone 1 (Success) - Default 1200 Hz
Played when:
- Product successfully scanned and matched
- Quantity incremented
- Everything went smoothly

### Tone 2 (Warning) - Default 800 Hz
Played when:
- Duplicate scan detected
- Product found but unexpected
- Quantity approaching limit
- Non-critical issue

### Tone 3 (Error) - Default 400 Hz
Played when:
- Product not found
- Scan failed
- Quantity exceeded and blocking enabled
- Critical error

**All tones are customizable:**
- Frequency (Hz): 200-2000
- Duration (ms): 50-1000
- Volume: 0.0-1.0

---

## 🎨 Visual Feedback

Colored flash animations on the scanned row:

- **Success** (#28a745 green): Product found and quantity updated
- **Warning** (#ffc107 yellow): Duplicate or unexpected scan
- **Error** (#dc3545 red): Not found or scan failed

**Customizable:**
- Flash colors (hex codes)
- Flash duration (100-2000ms)
- Enable/disable per outlet or user

---

## 📱 USB Scanner Setup

### Recommended Hardware
- **Zebra DS2208** (wired) - $150-200
- **Honeywell Voyager 1250g** (handheld) - $120-180
- **Symbol LS2208** (budget-friendly) - $80-100

### Configuration
1. Set scanner to **keyboard wedge mode**
2. Enable **auto-enter** (sends Enter key after barcode)
3. Configure for **USB HID** input
4. No software drivers needed - works immediately

### Testing
1. Open any text field
2. Scan barcode
3. Should type barcode + press Enter automatically

---

## 📷 Camera Scanner Setup

### Requirements
- **HTTPS required** (browser security requirement)
- Camera access permission
- Modern browser (Chrome, Firefox, Safari, Edge)

### Supported Formats
- EAN-13 (most common retail)
- EAN-8
- UPC-A
- UPC-E
- Code 128
- Code 39

### Performance
- Default: 10 FPS scan rate
- Resolution: 640x480 (configurable)
- Auto-focus enabled
- Works on phone cameras and webcams

---

## 🔐 Security & Permissions

### Admin Access
- Requires `barcode_admin` permission
- Access to management panel
- Can modify global/outlet/user settings

### User Access
- No special permission needed to use scanner
- Users can view their own scan history
- Users can modify their own preferences (if enabled)

### Data Privacy
- User agent logged (for device tracking)
- IP address logged (for audit trail)
- No PII in barcode logs
- Configurable retention period (7-365 days)

---

## 📈 Analytics & Reporting

### Real-Time Metrics
- Current scans per minute
- Active scanners count
- Success rate (last hour)
- Failed scan alerts

### Daily Reports
- Total scans by outlet
- Top performers (users)
- Most scanned products
- Average scan speed

### Export Options
- CSV export of scan history
- Date range selection
- Filter by outlet/user/method
- Include/exclude failed scans

---

## 🐛 Troubleshooting

### USB Scanner Not Working
1. Check scanner is in **keyboard wedge mode**
2. Test in text editor (should type barcode)
3. Verify **scan cooldown** not too high
4. Check **enabled** in settings

### Camera Not Starting
1. Verify **HTTPS** (required by browsers)
2. Check camera permissions in browser
3. Ensure **camera_scanner_enabled** = true
4. Try different browser

### Scans Not Logging
1. Check **log_all_scans** setting
2. Verify database connection
3. Check API endpoint accessible
4. Review browser console for errors

### Audio Not Playing
1. Check **audio_enabled** setting
2. Verify **audio_volume** > 0
3. Test in browser with user interaction first (autoplay policy)
4. Check if user has disabled audio in preferences

### Product Not Found
1. Verify barcode matches SKU in database
2. Check **require_exact_match** setting (try disabling for fuzzy match)
3. Ensure product exists in transfer/PO
4. Check barcode format correct

---

## 🎓 Best Practices

### For Management

1. **Start with global defaults** - Configure good defaults that work for most outlets
2. **Use outlet configs sparingly** - Only override when truly needed
3. **Review audit log regularly** - Track who changes what
4. **Monitor analytics weekly** - Identify training needs or issues
5. **Set appropriate retention** - Balance storage vs. analytics needs (90 days recommended)

### For Developers

1. **Always check config before scanning** - Respect enabled flags
2. **Handle all scan results** - Success, warning, error cases
3. **Provide visual feedback** - Users need confirmation
4. **Log everything** - Analytics depend on complete data
5. **Test with real barcodes** - EAN-13, UPC-A, QR codes
6. **Implement debouncing** - Prevent accidental double-scans
7. **Graceful degradation** - If scanner fails, allow manual entry

### For Users

1. **Hold barcode steady** - Camera needs clear view
2. **Good lighting** - Especially for camera scanning
3. **Wait for beep** - Audio confirmation before moving to next
4. **Check screen flash** - Green = success, Red = error
5. **Report issues** - Help improve the system

---

## 📝 Version History

### v1.0.0 (2025-11-04)
- ✅ Initial release
- ✅ Dual scanner support (USB + Camera)
- ✅ 3-level configuration (Global/Outlet/User)
- ✅ Complete management control panel
- ✅ Full logging & analytics
- ✅ Audio & visual feedback
- ✅ Audit trail
- ✅ Export capabilities

---

## 🚧 Future Enhancements

### Phase 2 (Planned)
- [ ] Mobile app for dedicated scanning
- [ ] Bluetooth scanner support
- [ ] 2D barcode support (Data Matrix, PDF417)
- [ ] Batch scanning mode
- [ ] Voice feedback option
- [ ] Haptic feedback (mobile)

### Phase 3 (Planned)
- [ ] AI-powered product recognition (if barcode damaged/missing)
- [ ] Real-time dashboard (WebSocket live updates)
- [ ] Predictive analytics (scan time forecasting)
- [ ] A/B testing framework for settings
- [ ] Gamification (leaderboards, achievements)

---

## 📞 Support

For issues or questions:
- **Email**: pearce.stephens@ecigdis.co.nz
- **Internal Wiki**: https://www.wiki.vapeshed.co.nz
- **Helpdesk**: https://helpdesk.vapeshed.co.nz

---

## 📄 License

Proprietary - Ecigdis Limited © 2025
All rights reserved.

---

**Built with ❤️ for The Vape Shed team**
