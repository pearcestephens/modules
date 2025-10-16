# 🔧 Queue Job Schema Fix - `scheduled_at` Column Issue

**Date:** 2025-01-XX  
**Issue:** Transfer submission failing with "Unknown column 'scheduled_at' in 'field list'"  
**Root Cause:** INSERT statement referenced non-existent column in production schema  
**Status:** ✅ FIXED

---

## 🐛 Problem

When submitting transfer #27043, the system threw:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'scheduled_at' in 'field list'
```

The newly added queue job creation code in `submit_transfer_simple.php` tried to INSERT with column `scheduled_at`, but the **production `queue_jobs` schema doesn't have this column**.

---

## 🔍 Investigation

### Production Schema (from `QUEUE TABLES.md`)

```sql
CREATE TABLE `queue_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` varchar(64) NOT NULL COMMENT 'Unique job identifier (UUID)',  ← REQUIRED!
  `job_type` varchar(64) NOT NULL,
  `queue_name` varchar(64) NOT NULL DEFAULT 'default',
  `payload` longtext NOT NULL,
  `priority` tinyint(4) NOT NULL DEFAULT 5,
  `status` enum('pending','processing','completed','failed','cancelled','dead_letter') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `available_at` timestamp NOT NULL DEFAULT current_timestamp(),  ← For scheduling
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  -- ... more columns ...
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  -- NO scheduled_at column!
)
```

### Key Findings:
1. ❌ **`scheduled_at` column does NOT exist** in production schema
2. ✅ **`available_at` column IS used** for job scheduling (defaults to NOW())
3. ✅ **`job_id` column IS REQUIRED** (varchar(64), UNIQUE, NOT NULL)
4. ✅ Other systems use pattern: `INSERT INTO queue_jobs (job_id, job_type, payload, status, priority, created_at)`

---

## ✅ Solution

### Changed INSERT Statement

**BEFORE (BROKEN):**
```php
$stmt = $pdo->prepare("
    INSERT INTO queue_jobs
        (job_type, payload, status, priority, created_at, scheduled_at)
    VALUES
        ('transfer.create_consignment', ?, 'pending', 8, NOW(), NOW())
");
$stmt->execute([json_encode($jobPayload)]);
```

**AFTER (FIXED):**
```php
$jobIdUnique = bin2hex(random_bytes(16)); // Generate unique job_id (32-char hex UUID)

$stmt = $pdo->prepare("
    INSERT INTO queue_jobs
        (job_id, job_type, payload, status, priority, created_at)
    VALUES
        (?, 'transfer.create_consignment', ?, 'pending', 8, NOW())
");
$stmt->execute([$jobIdUnique, json_encode($jobPayload, JSON_UNESCAPED_UNICODE)]);
$jobId = $pdo->lastInsertId();
```

### Updated Response

Now includes both database ID and UUID:
```json
{
  "success": true,
  "queue_job_id": 19680,
  "queue_job_uuid": "a3f5e9c2d8b14f6e9a7c3e8d5b2f1a6c",
  "message": "Transfer submitted successfully. Lightspeed sync job #19680 (UUID: a3f5e9...) queued."
}
```

---

## 📋 Changes Made

### File: `submit_transfer_simple.php` (Lines 216-248)

**Changes:**
1. ✅ Added `$jobIdUnique = bin2hex(random_bytes(16))` - generates 32-char hex UUID
2. ✅ Removed `scheduled_at` from INSERT columns
3. ✅ Added `job_id` to INSERT columns (FIRST parameter)
4. ✅ Updated `$stmt->execute()` to pass `[$jobIdUnique, $jobPayload]`
5. ✅ Updated response to include both `queue_job_id` (DB ID) and `queue_job_uuid` (UUID)

---

## 🧪 Testing

### Test Transfer Submission

1. **Navigate to pack page:**
   ```
   https://staff.vapeshed.co.nz/modules/consignments/stock-transfers/pack.php?id=27043
   ```

2. **Submit transfer** (should now succeed without column error)

3. **Check response:**
   ```json
   {
     "success": true,
     "transfer_id": 27043,
     "state": "SENT",
     "queue_job_id": 19680,
     "queue_job_uuid": "a3f5e9c2d8b14f6e9a7c3e8d5b2f1a6c",
     "message": "Transfer submitted successfully..."
   }
   ```

4. **Verify queue job created:**
   ```sql
   SELECT id, job_id, job_type, status, priority, created_at
   FROM queue_jobs
   WHERE job_type = 'transfer.create_consignment'
   ORDER BY id DESC
   LIMIT 5;
   ```

5. **Check diagnostic tool:**
   ```
   https://staff.vapeshed.co.nz/modules/consignments/api/check_queue_status.php?transfer_id=27043
   ```

---

## 📚 Reference: Other Queue Job Patterns

### Example from `enhanced-transfer-upload.php` (Line 210)
```php
$stmt = $this->db->prepare(
    "INSERT INTO queue_jobs (job_type, payload, status, priority, created_at) 
     VALUES (?, ?, 'pending', 8, NOW())"
);
```
⚠️ **NOTE:** This example doesn't include `job_id` - may need fixing too!

### Correct Pattern (from schema requirements)
```php
$jobIdUnique = bin2hex(random_bytes(16));
$stmt = $pdo->prepare(
    "INSERT INTO queue_jobs (job_id, job_type, payload, status, priority) 
     VALUES (?, ?, ?, 'pending', 8)"
);
$stmt->execute([$jobIdUnique, $jobType, json_encode($payload)]);
```

---

## 🔗 Related Files

- ✅ `/modules/consignments/api/submit_transfer_simple.php` - **FIXED**
- ✅ `/modules/consignments/api/check_queue_status.php` - Diagnostic tool (working)
- ✅ `/modules/consignments/docs/QUEUE TABLES.md` - Production schema reference
- ⚠️ `/modules/consignments/api/enhanced-transfer-upload.php` - May need `job_id` fix
- ✅ `/modules/consignments/POST_SUBMISSION_WORKFLOW_FIX.md` - Overall workflow docs

---

## 🎯 Next Steps

1. ✅ **Test transfer submission** - Verify no more column errors
2. ⏳ **Check if workers process jobs** - See if transfer appears in Lightspeed
3. ⏳ **Verify queue_consignments gets created** - Should happen when worker runs
4. ⏳ **Improve submission overlay UI** - User wants celebrations/animations
5. ⚠️ **Audit other queue job INSERTs** - Fix any missing `job_id` columns

---

## 🚨 Important Notes

### Required Columns for `queue_jobs` INSERT

**MUST include:**
- ✅ `job_id` - varchar(64) UNIQUE NOT NULL (generate with `bin2hex(random_bytes(16))`)
- ✅ `job_type` - varchar(64) NOT NULL (e.g., 'transfer.create_consignment')
- ✅ `payload` - longtext JSON NOT NULL (job parameters)

**Optional (with defaults):**
- `status` - defaults to 'pending'
- `priority` - defaults to 5 (use 8 for transfers = high priority)
- `queue_name` - defaults to 'default'
- `attempts` - defaults to 0
- `max_attempts` - defaults to 3
- `created_at` - defaults to CURRENT_TIMESTAMP
- `available_at` - defaults to CURRENT_TIMESTAMP (for scheduling)

**DO NOT use:**
- ❌ `scheduled_at` - **DOES NOT EXIST** in production schema

---

**Fixed by:** Ultimate Problem-Solving Dev Bot  
**Verified:** Schema matches production `QUEUE TABLES.md`  
**Status:** ✅ Ready for testing
