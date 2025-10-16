# 🎯 DUAL MODE UPLOAD SYSTEM - COMPLETE!

**Status:** ✅ **READY TO TEST**  
**Date:** October 16, 2025  
**Mode:** Direct Upload (Queue workers are dead anyway)

---

## 🎛️ What We Built

A **switchable upload system** that supports BOTH:
1. 🔥 **DIRECT MODE** (default) - No queue, instant upload, modal display
2. 🔄 **QUEUE MODE** - Uses queue workers (when they're actually working lol)

Plus a **GANGSTA MODAL** that auto-expires to boring corporate mode after 2 weeks! 😂

---

## 📁 Files Modified

### 1. **Config File** (NEW)
`/modules/consignments/config/upload_mode.php`
- Set `mode` to `'direct'` or `'queue'`
- Set `display` to `'modal'` or `'popup'`
- Gangsta mode expires: **October 30, 2025**

### 2. **Backend API**
`/modules/consignments/api/submit_transfer_simple.php`
- Reads config to determine mode
- Returns different response based on mode:
  - **Direct:** `upload_session_id`, `upload_url`, `progress_url`
  - **Queue:** `queue_job_id`, `queue_job_uuid`

### 3. **Frontend JavaScript**
`/modules/consignments/stock-transfers/js/pack.js`
- Detects upload mode from API response
- Opens **gangsta modal** for direct mode
- Shows queue status for queue mode
- Modal personality changes based on days until expiry

---

## 🎨 Gangsta Modal Personality Levels

| Days Left | Personality | Title Example |
|-----------|-------------|---------------|
| **14+ days** | 🔥 **FULL GANGSTA** | "🔥 YO WE PUSHIN THIS SHIT TO LIGHTSPEED RETAIL FAM 🔥" |
| **7-13 days** | 😎 **HOOD PROFESSIONAL** | "😎 Syncing to Lightspeed Retail (Direct Upload)" |
| **3-6 days** | 👔 **CORPORATE FRIENDLY** | "👔 Lightspeed Retail Synchronization" |
| **0-2 days** | 💤 **BORING AF** | "Lightspeed Retail Upload Progress" |

---

## ⚡ How To Use

### Switch Between Modes

Edit `/modules/consignments/config/upload_mode.php`:

```php
return [
    'mode' => 'direct',  // Change to 'queue' when workers are fixed
    'display' => 'modal', // Change to 'popup' for old behavior
    // ...
];
```

**No code changes needed!** Just edit the config file and it works.

---

## 🧪 Testing Steps

### Test 1: Direct Mode (Default)
1. Make sure config has `'mode' => 'direct'`
2. Go to pack page: `https://staff.vapeshed.co.nz/modules/consignments/stock-transfers/pack.php?id=27043`
3. Click "Submit Transfer"
4. Should see:
   - ✅ Submission overlay with success message
   - ✅ **GANGSTA MODAL** opens with upload progress (green glowing border!)
   - ✅ Progress page shows in modal (not popup)
   - ✅ Upload starts immediately (no queue)

### Test 2: Queue Mode (When Workers Work)
1. Change config to `'mode' => 'queue'`
2. Submit transfer
3. Should see:
   - ✅ "Queue job created" message
   - ✅ Returns queue_job_id and queue_job_uuid
   - ✅ No modal (waits for workers)

### Test 3: Personality Expiry
1. Current date: Oct 16, 2025
2. Expiry date: Oct 30, 2025
3. Days left: **14 days**
4. Should show: **FULL GANGSTA MODE** 🔥

**To test other personalities:**
- Change expiry date in config to force different modes
- Or wait for time to pass naturally

---

## 🎯 What Each Mode Does

### Direct Mode (NO QUEUE)
```
User clicks Submit
    ↓
API saves transfer (state = SENT)
    ↓
API generates upload_session_id
    ↓
Frontend opens GANGSTA MODAL
    ↓
Frontend calls enhanced-transfer-upload.php
    ↓
Upload happens in modal (real-time progress)
    ↓
Modal shows: "💯 NO QUEUE WORKERS NEEDED - STRAIGHT FIRE 🔥"
    ↓
Done! Transfer synced to Lightspeed Retail
```

### Queue Mode (REQUIRES WORKERS)
```
User clicks Submit
    ↓
API saves transfer (state = SENT)
    ↓
API creates queue_jobs entry
    ↓
Frontend shows "Job created, waiting for workers..."
    ↓
Workers pick up job (IF THEY'RE ALIVE)
    ↓
Workers process upload
    ↓
Done! (eventually, maybe, if workers didn't die again)
```

---

## 🔧 Config Options Explained

```php
return [
    // Main upload mode
    'mode' => 'direct', // 'direct' or 'queue'
    
    // How to display upload progress
    'display' => 'modal', // 'modal' or 'popup'
    
    // When gangsta mode expires
    'gangsta_mode_expiry' => '2025-10-30 23:59:59',
    
    // Force a personality (or 'auto' for time-based)
    'personality_mode' => 'auto', // 'gangsta', 'hood', 'corporate', 'boring', 'auto'
    
    // Queue settings (only used if mode = 'queue')
    'queue_job_type' => 'transfer.create_consignment',
    'queue_priority' => 8, // 1-10, 10 = highest
    
    // Direct upload settings
    'direct_upload' => [
        'show_real_time_progress' => true,
        'auto_close_on_success' => false,
        'modal_width' => '900px',
        'modal_height' => '600px',
    ],
    
    // Debugging
    'debug_mode' => true,
    'log_path' => '/logs/consignment-upload.log',
];
```

---

## 🎉 Benefits

### Direct Mode:
- ✅ **NO DEAD WORKERS PROBLEM** - Bypasses queue entirely
- ✅ **INSTANT FEEDBACK** - See errors immediately
- ✅ **REAL-TIME PROGRESS** - Watch it sync live
- ✅ **GANGSTA AF** - Cool modal with personality

### Queue Mode:
- ✅ **BACKGROUND PROCESSING** - User doesn't wait
- ✅ **RETRY LOGIC** - Workers retry on failure
- ✅ **SCALABLE** - Can handle many uploads at once
- ❌ **WORKERS KEEP DYING** - Main problem

---

## 🚨 Known Issues

1. **Workers die every day** - That's why direct mode is default
2. **Job type not registered** - `transfer.create_consignment` handler doesn't exist
3. **Nobody knows how to use queue system** - Documentation was missing (until now!)

---

## 💡 Future Improvements

### Short Term (This Week):
- [ ] Test direct mode end-to-end
- [ ] Verify modal shows progress correctly
- [ ] Test personality expiry on Oct 30

### Medium Term (Next Month):
- [ ] Fix queue workers so they don't die
- [ ] Register `transfer.create_consignment` job handler
- [ ] Add proper worker monitoring

### Long Term (Someday):
- [ ] Add retry button in modal if upload fails
- [ ] Show upload history in modal
- [ ] Add "Skip queue, do it now" button for queue mode

---

## 📞 Quick Reference

### Change to Direct Mode:
```php
'mode' => 'direct', // in config/upload_mode.php
```

### Change to Queue Mode:
```php
'mode' => 'queue', // in config/upload_mode.php
```

### Force Gangsta Mode Forever:
```php
'personality_mode' => 'gangsta', // Override auto-expiry
```

### Use Old Popup Instead of Modal:
```php
'display' => 'popup', // Opens in new window
```

---

## 🎯 Summary

**WE BUILT:**
- ✅ Dual-mode upload system (queue or direct)
- ✅ Gangsta modal with auto-expiry
- ✅ Config-based switching (no code changes!)
- ✅ Proper branding (Lightspeed Retail, not just Lightspeed)

**DEFAULT BEHAVIOR:**
- Mode: **Direct** (because workers are dead)
- Display: **Modal** (gangsta style for 14 days)
- Personality: **Auto-expires** to boring corporate mode

**READY TO TEST:** YES! 🚀

---

**Built by:** Ultimate Problem-Solving Dev Bot  
**For:** Pearce @ The Vape Shed  
**Status:** ✅ COMPLETE AND GANGSTA AF 🔥
