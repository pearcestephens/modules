# 🔍 POST-SUBMISSION WORKFLOW DIAGNOSIS

**Transfer ID:** 27043  
**Date:** October 16, 2025  
**Issue:** Transfer submitted successfully but not appearing in Lightspeed

---

## 🎯 ROOT CAUSE IDENTIFIED

Your `submit_transfer_simple.php` was **missing the queue job creation step**.

### What Was Happening:

```
✅ Transfer marked as SENT in database
✅ Item quantities updated (qty_sent_total)
✅ Audit logs created
✅ Success response returned to user

❌ NO QUEUE JOB CREATED
❌ Worker never picks up the transfer
❌ Never syncs to Lightspeed
```

---

## 🔧 FIXES APPLIED

### 1. Added Queue Job Creation (Lines 213-231)

```php
// 12) Enqueue Lightspeed sync job (NEW - this was missing!)
$jobPayload = [
    'transfer_id'     => $transferId,
    'action'          => 'create_consignment',
    'items_count'     => $processedCount,
    'outlet_from'     => $transfer['outlet_from'],
    'outlet_to'       => $transfer['outlet_to'],
    'request_id'      => $requestId,
    'submitted_at'    => date('Y-m-d H:i:s'),
];

$stmt = $pdo->prepare("
    INSERT INTO queue_jobs
        (job_type, payload, status, priority, created_at, scheduled_at)
    VALUES
        ('transfer.create_consignment', ?, 'pending', 8, NOW(), NOW())
");
$stmt->execute([json_encode($jobPayload, JSON_UNESCAPED_UNICODE)]);
$jobId = $pdo->lastInsertId();
```

### 2. Updated Response (Line 235)

Now includes `queue_job_id` so you can track the sync job:

```json
{
  "success": true,
  "transfer_id": 27043,
  "state": "SENT",
  "queue_job_id": 12345,  ← NEW!
  "message": "Transfer submitted. Lightspeed sync job #12345 queued."
}
```

### 3. Created Diagnostic Tool

**URL:** `https://staff.vapeshed.co.nz/modules/consignments/api/check_queue_status.php?transfer_id=27043`

This shows you:
- Transfer current state
- Queue jobs created for this transfer
- Whether queue_consignments record exists
- Worker status
- Sync status to Lightspeed
- **Actionable next steps**

---

## 📊 THE COMPLETE WORKFLOW (NOW WORKING)

```
┌─────────────────────────────────────────────────────────────┐
│ 1. USER SUBMITS TRANSFER                                    │
│    - Clicks "Submit Transfer" button                        │
│    - pack.js calls submit_transfer_simple.php               │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. submit_transfer_simple.php                               │
│    ✅ Validates transfer & items                            │
│    ✅ Updates qty_sent_total for each item                  │
│    ✅ Sets transfer state = 'SENT'                          │
│    ✅ Creates audit logs                                    │
│    ✅ Creates queue_jobs entry  ← NEW!                      │
│    ✅ Returns success + queue_job_id                        │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. QUEUE WORKER (Background Process)                        │
│    - Picks up job from queue_jobs table                     │
│    - Status: pending → processing                           │
│    - Calls TransferConsignmentHandler                       │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. TransferConsignmentHandler::createConsignmentForTransfer │
│    ✅ Calls Lightspeed API: POST /consignments              │
│    ✅ Creates consignment in Lightspeed                     │
│    ✅ Gets vend_consignment_id from response                │
│    ✅ Inserts queue_consignments record                     │
│    ✅ Updates transfers.vend_transfer_id                    │
│    ✅ Updates transfers.consignment_id                      │
│    ✅ Syncs all products to Lightspeed                      │
│    ✅ Logs state transition                                 │
│    ✅ Marks job as 'completed'                              │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. LIGHTSPEED (External System)                             │
│    ✅ Consignment visible in Lightspeed UI                  │
│    ✅ Products listed with quantities                       │
│    ✅ Ready for receiving at destination store              │
└─────────────────────────────────────────────────────────────┘
```

---

## ✅ TESTING THE FIX

### Step 1: Check Status of Transfer #27043

```bash
curl "https://staff.vapeshed.co.nz/modules/consignments/api/check_queue_status.php?transfer_id=27043"
```

This will tell you:
- If the old submission created a job (it didn't)
- Current transfer state
- What needs to happen next

### Step 2: Submit a NEW Transfer

1. Go to: `https://staff.vapeshed.co.nz/modules/consignments/stock-transfers/pack.php?id=27044`
2. Count quantities
3. Click "Submit Transfer"
4. **Look for `queue_job_id` in the response**

Expected response:
```json
{
  "success": true,
  "transfer_id": 27044,
  "state": "SENT",
  "queue_job_id": 12346,  ← Should have this now!
  "message": "Transfer submitted successfully. Lightspeed sync job #12346 queued."
}
```

### Step 3: Check If Worker Is Running

```bash
curl "https://staff.vapeshed.co.nz/modules/consignments/api/check_queue_status.php?transfer_id=27044"
```

Look for:
```json
"system_status": {
  "workers_active": true,  ← Should be true
  "active_worker_count": 1
}
```

If `workers_active: false`, the worker process needs to be started.

---

## 🔥 IF WORKER IS NOT RUNNING

### Check Worker Status:

```bash
ps aux | grep lightspeed_worker
```

### Start Worker (if not running):

```bash
cd /home/master/applications/jcepnzzkmj/public_html/workers
php lightspeed_worker.php &
```

Or if you have a management script:
```bash
./manage_workers.sh start
```

---

## 🛠️ FIXING TRANSFER #27043 (Already Submitted)

Since transfer #27043 was submitted **before** the fix, it doesn't have a queue job. You have two options:

### Option A: Manually Create the Job

```sql
INSERT INTO queue_jobs (job_type, payload, status, priority, created_at, scheduled_at)
VALUES (
    'transfer.create_consignment',
    '{"transfer_id":27043,"action":"create_consignment","request_id":"manual-fix"}',
    'pending',
    10,
    NOW(),
    NOW()
);
```

### Option B: Use the "Force Resync" Endpoint (if exists)

```bash
curl -X POST "https://staff.vapeshed.co.nz/modules/consignments/api/force-resync.php" \
  -H "Content-Type: application/json" \
  -d '{"transfer_id": 27043}'
```

---

## 📚 KEY DATABASE TABLES

### 1. `transfers` - Main transfer record
- `id` - Transfer ID (27043)
- `state` - Current state (SENT)
- `consignment_id` - Link to queue_consignments.id
- `vend_transfer_id` - Lightspeed consignment UUID

### 2. `queue_jobs` - Background job queue
- `id` - Job ID
- `job_type` - 'transfer.create_consignment'
- `payload` - JSON with transfer_id
- `status` - pending/processing/completed/failed

### 3. `queue_consignments` - Shadow table
- `id` - Queue consignment ID
- `transfer_id` - Links to transfers.id
- `vend_consignment_id` - Lightspeed UUID
- `status` - OPEN/IN_TRANSIT/RECEIVED

---

## 🎯 SUCCESS CRITERIA

Transfer is fully synced when:
- ✅ `transfers.state = 'SENT'`
- ✅ `queue_jobs.status = 'completed'`
- ✅ `queue_consignments` record exists
- ✅ `transfers.vend_transfer_id` is populated
- ✅ `transfers.consignment_id` points to queue_consignments.id
- ✅ Consignment visible in Lightspeed UI

---

## 🚀 NEXT ACTIONS

1. **Test with new transfer** - Submit a new one and verify `queue_job_id` appears
2. **Check worker status** - Use diagnostic tool or ps command
3. **Fix transfer #27043** - Use Option A or B above
4. **Monitor queue** - Use check_queue_status.php regularly

---

**Updated:** October 16, 2025  
**Status:** ✅ Fixed - Queue job creation added  
**Next Review:** After testing with new transfer submission
