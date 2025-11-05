# 📸 Photo Upload System - Complete Guide

## Overview

The barcode scanner widget includes a **QR code-based photo upload system** for mobile devices. Staff can:

1. **Scan QR code** on PC screen with phone
2. **Upload photos** directly from phone (no login required)
3. **15-minute upload window** per session
4. **Review/assign photos** on PC in media section

---

## Quick Start

### 1. Database Setup

Run the SQL schema to create photo tables:

```bash
mysql -u username -p database_name < /modules/consignments/db/schema/photo_upload_sessions.sql
```

This creates:
- `PHOTO_UPLOAD_SESSIONS` - Temporary upload sessions
- `TRANSFER_PHOTOS` - Uploaded photos
- Auto-cleanup event for expired sessions

### 2. Create Upload Directory

```bash
mkdir -p uploads/transfer-photos
chmod 755 uploads/transfer-photos
```

### 3. Widget Integration (Already Done!)

The widget automatically includes photo mode with QR code generation. No additional setup needed!

---

## How It Works

### On PC (Desktop)

1. **Open transfer page** (pack, receive, etc.)
2. **Click photo mode** tab in barcode widget
3. **QR code displays** automatically
4. **15-minute timer** starts counting down

### On Phone (Mobile)

1. **Scan QR code** with camera
2. **Opens upload page** in browser (no login!)
3. **Take photos** or upload from gallery
4. **Automatic upload** to transfer
5. **Session expires** after 15 minutes

### Back on PC

1. **Click "Media" button** or navigate to photos page
2. **View all uploaded photos** in grid
3. **Click photo** to assign to product
4. **Select issue type** (damaged, repaired, missing)
5. **Add notes** and save

---

## Widget Photo Mode Features

### QR Code Generation

```javascript
// Automatic when switching to photo mode
cisBarcodeWidget.switchMode('photo');

// QR code displays with:
// - 200x200px QR code image
// - Countdown timer (15:00)
// - "Regenerate" button
// - OR "Use PC Camera" option
```

### Countdown Timer

- **Green**: > 5 minutes remaining
- **Orange**: 1-5 minutes remaining
- **Red**: < 1 minute (blinking)
- **Expired**: "EXPIRED" text, must regenerate

### PC Camera Option

If staff prefer to use PC webcam instead of phone:

```html
<button onclick="cisBarcodeWidget.showPhotoCapture()">
    Use PC Camera
</button>
```

---

## Mobile Upload Page

### URL Format

```
https://your-domain.com/modules/consignments/mobile-upload.php?token=SESSION_TOKEN
```

### Features

- ✅ **No login required** (token-based auth)
- ✅ **Touch-friendly** interface
- ✅ **Camera integration** (capture or upload)
- ✅ **Drag & drop** support
- ✅ **Multi-photo upload**
- ✅ **Progress tracking**
- ✅ **Auto-expiry** at 15 minutes

### Session Validation

Page automatically:
1. Validates token on load
2. Shows expired message if invalid
3. Starts countdown timer
4. Disables upload when expired

---

## Photo Management Page

### Access

```php
// Direct URL
/modules/consignments/stock-transfers/photos.php?transfer_id=123

// Or embed in transfer page
<a href="photos.php?transfer_id=<?= $transferId ?>" class="btn btn-secondary">
    <i class="fas fa-images"></i> View Photos
</a>
```

### Features

#### Dashboard Stats
- Total photos uploaded
- Assigned vs unassigned
- Damaged items count

#### Photo Grid
- Thumbnail view (200x200px)
- Color-coded borders:
  - **Gray**: Unassigned
  - **Green**: Assigned to product
- Issue type badges
- Upload timestamp

#### Filters
- All photos
- Assigned only
- Unassigned only
- Damaged items only

#### Photo Detail Modal
- Full-size photo preview
- Assign to product (dropdown)
- Issue type (damaged/repaired/missing/other)
- Notes (textarea)
- Save or Delete buttons

---

## API Endpoints

### 1. Create Upload Session

```javascript
POST /modules/consignments/api/photo_upload_session.php

{
    "action": "create_session",
    "transfer_id": 123,
    "transfer_type": "stock_transfer",
    "user_id": 45,
    "outlet_id": 2
}

// Response
{
    "success": true,
    "session_id": 789,
    "session_token": "abc123...",
    "upload_url": "https://domain.com/mobile-upload.php?token=abc123...",
    "expires_at": "2025-11-04 15:30:00",
    "expires_in_seconds": 900
}
```

### 2. Validate Session

```javascript
GET /modules/consignments/api/photo_upload_session.php?action=validate_session&token=abc123...

// Response
{
    "success": true,
    "valid": true,
    "session": {
        "session_id": 789,
        "transfer_id": 123,
        "seconds_remaining": 840
    }
}
```

### 3. Upload Photo

```javascript
POST /modules/consignments/api/photo_upload_session.php

FormData {
    action: "upload_photo",
    token: "abc123...",
    photo: <File>
}

// Response
{
    "success": true,
    "photo_id": 456,
    "filename": "photo_abc_1234567890.jpg",
    "message": "Photo uploaded successfully"
}
```

### 4. Get Photos

```javascript
GET /modules/consignments/api/photo_upload_session.php?action=get_photos&transfer_id=123

// Response
{
    "success": true,
    "photos": [
        {
            "photo_id": 456,
            "transfer_id": 123,
            "filename": "photo_abc.jpg",
            "product_id": 789,
            "issue_type": "damaged",
            "notes": "Cracked screen",
            "uploaded_at": "2025-11-04 14:15:00"
        }
    ],
    "count": 1
}
```

### 5. Assign Photo

```javascript
POST /modules/consignments/api/photo_upload_session.php

{
    "action": "assign_photo",
    "photo_id": 456,
    "product_id": 789,
    "issue_type": "damaged",
    "notes": "Cracked screen"
}

// Response
{
    "success": true,
    "message": "Photo assigned successfully"
}
```

### 6. Delete Photo

```javascript
POST /modules/consignments/api/photo_upload_session.php

{
    "action": "delete_photo",
    "photo_id": 456
}

// Response
{
    "success": true,
    "message": "Photo deleted successfully"
}
```

---

## Security Features

### Token-Based Authentication
- **64-character random token** per session
- **No password required** for upload (token IS the auth)
- **Time-limited** (15 minutes max)
- **Single-use recommended** (regenerate after use)

### Validation
- File type: JPG/PNG only
- File size: 10MB max
- Token validation on every upload
- Expired sessions rejected

### Storage
- Photos stored in `/uploads/transfer-photos/{transfer_id}/`
- Unique filenames: `photo_{uniqid}_{timestamp}.{ext}`
- Database tracking for all uploads

---

## Workflow Example

### Receiving Damaged Goods

1. **Receiver opens receive page**
2. **Barcode widget** in corner (collapsed)
3. **Click to expand**, switch to **Photo mode**
4. **QR code displays** on screen
5. **Receiver scans with phone**
6. **Mobile page opens** (no login)
7. **Takes photos** of damaged items (3 items)
8. **Photos auto-upload** to session
9. **Returns to PC**
10. **Clicks "View Photos"** button
11. **Sees 3 photos** in grid
12. **Clicks first photo**
13. **Assigns to Product #123**
14. **Selects "Damaged"** issue type
15. **Adds note**: "Box crushed during shipping"
16. **Saves assignment**
17. **Repeats** for other 2 photos
18. **Completes receive** process

### Reports/Analytics

Photos can now be included in:
- Damage reports
- Supplier claims
- Quality control audits
- Historical records

---

## Customization

### Change Session Expiry

Edit `photo_upload_session.php`:

```php
// Change from 15 minutes to 30 minutes
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
```

### Change QR Code Size

Edit `barcode-widget-advanced.js`:

```javascript
// Change from 200x200 to 300x300
const qrUrl = `https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=${encodeURIComponent(result.upload_url)}`;
```

### Change Upload Directory

Edit `photo_upload_session.php`:

```php
$uploadDir = '../../../uploads/my-custom-path/' . $session['transfer_id'] . '/';
```

### Add Photo Watermark

```php
// In uploadPhoto() function after move_uploaded_file()
$image = imagecreatefromjpeg($filepath);
$watermark = imagecreatefrompng('watermark.png');
imagecopy($image, $watermark, 10, 10, 0, 0, imagesx($watermark), imagesy($watermark));
imagejpeg($image, $filepath);
```

---

## Troubleshooting

### QR Code Not Generating

**Check:**
1. Database tables created?
2. API endpoint accessible?
3. Browser console for errors
4. Transfer ID provided to widget?

**Fix:**
```javascript
// Test API directly
fetch('/modules/consignments/api/photo_upload_session.php', {
    method: 'POST',
    body: JSON.stringify({
        action: 'create_session',
        transfer_id: 123
    })
}).then(r => r.json()).then(console.log);
```

### Mobile Page Shows "Expired"

**Reasons:**
- Session actually expired (> 15 min)
- Invalid token in URL
- Database session deleted

**Fix:**
- Regenerate QR code on PC
- Check token in URL matches database

### Photos Not Uploading

**Check:**
1. File size < 10MB?
2. File type JPG/PNG?
3. Upload directory writable?
4. Session still valid?

**Fix:**
```bash
# Check permissions
ls -la uploads/transfer-photos/
chmod 755 uploads/transfer-photos/

# Check disk space
df -h
```

### Photos Not Showing in Grid

**Check:**
1. Transfer ID correct?
2. Photos in correct directory?
3. Database records exist?

**Fix:**
```sql
-- Check database
SELECT * FROM TRANSFER_PHOTOS WHERE transfer_id = 123;

-- Check files
ls -la uploads/transfer-photos/123/
```

---

## Next Steps

1. ✅ **Test QR code generation** on a transfer page
2. ✅ **Scan with phone** and upload test photo
3. ✅ **View in media page** and assign to product
4. ⏳ **Train staff** on photo workflow
5. ⏳ **Add "Media" button** to all transfer pages
6. ⏳ **Create damage report** that includes photos
7. ⏳ **Add photo thumbnails** to transfer detail views

---

## Support

Questions? Check main docs:
- `/docs/BARCODE_SCANNER_COMPLETE_GUIDE.md`
- `/docs/BARCODE_DEPLOYMENT_CHECKLIST.md`
- `BARCODE_WIDGET_INTEGRATION.md`
