# ✅ Transfer Submission Test Checklist

**Test Transfer:** #27043 (or create new one)  
**Testing URL:** https://staff.vapeshed.co.nz/modules/consignments/stock-transfers/pack.php?id=27043

---

## 🧪 Test 1: Submission Works Without Errors

### Steps:
1. Navigate to pack page for transfer #27043
2. Click "Submit Transfer" button
3. Watch submission overlay appear

### Expected Result:
✅ **NO database column errors**  
✅ Success overlay appears  
✅ No console errors in browser DevTools  
✅ Response includes `queue_job_id` and `queue_job_uuid`

### Previous Error (FIXED):
```
❌ SQLSTATE[42S22]: Column not found: 1054 Unknown column 'scheduled_at' in 'field list'
```

### Success Response Format:
```json
{
  "success": true,
  "transfer_id": 27043,
  "state": "SENT",
  "legacy_workflow": "SUBMITTED",
  "items_processed": 150,
  "request_id": "abc123...",
  "queue_job_id": 19680,
  "queue_job_uuid": "a3f5e9c2d8b14f6e9a7c3e8d5b2f1a6c",
  "timestamp": "2025-01-XX 14:30:00",
  "message": "Transfer submitted successfully. Processed 150 items. Lightspeed sync job #19680 (UUID: a3f5e9...) queued."
}
```

---

## 🧪 Test 2: Queue Job Created in Database

### SQL Check:
```sql
SELECT 
    id,
    job_id,
    job_type,
    status,
    priority,
    payload,
    created_at
FROM queue_jobs
WHERE job_type = 'transfer.create_consignment'
ORDER BY id DESC
LIMIT 5;
```

### Expected Result:
✅ New row with:
- `id` = auto-increment number (e.g., 19680)
- `job_id` = 32-character hex string (e.g., `a3f5e9c2d8b14f6e9a7c3e8d5b2f1a6c`)
- `job_type` = `'transfer.create_consignment'`
- `status` = `'pending'`
- `priority` = `8` (high priority)
- `payload` = JSON with transfer_id, action, items_count, etc.
- `created_at` = recent timestamp

### Sample Result:
```
+-------+----------------------------------+-----------------------------+---------+----------+-------------------------------------------+---------------------+
| id    | job_id                           | job_type                    | status  | priority | payload                                   | created_at          |
+-------+----------------------------------+-----------------------------+---------+----------+-------------------------------------------+---------------------+
| 19680 | a3f5e9c2d8b14f6e9a7c3e8d5b2f1a6c | transfer.create_consignment | pending |        8 | {"transfer_id":27043,"action":"create_... | 2025-01-XX 14:30:00 |
+-------+----------------------------------+-----------------------------+---------+----------+-------------------------------------------+---------------------+
```

---

## 🧪 Test 3: Diagnostic Tool Shows Job

### Check Status:
```
https://staff.vapeshed.co.nz/modules/consignments/api/check_queue_status.php?transfer_id=27043
```

### Expected Result:
```json
{
  "success": true,
  "transfer": {
    "id": 27043,
    "state": "SENT",
    "has_consignment_id": false,
    "has_vend_id": false,
    "updated_at": "2025-01-XX 14:30:00"
  },
  "queue_jobs": {
    "total": 1,
    "jobs": [
      {
        "id": 19680,
        "job_id": "a3f5e9c2d8b14f6e9a7c3e8d5b2f1a6c",
        "job_type": "transfer.create_consignment",
        "status": "pending",
        "priority": 8,
        "payload": "{\"transfer_id\":27043,...}",
        "created_at": "2025-01-XX 14:30:00"
      }
    ]
  },
  "queue_consignment": {
    "exists": false,
    "message": "No queue_consignments record found - this means the job hasn't been processed yet"
  },
  "diagnosis": {
    "transfer_submitted": true,
    "job_created": true,
    "job_processed": false,
    "synced_to_lightspeed": false
  },
  "next_steps": [
    "✅ Transfer successfully submitted (state: SENT)",
    "✅ Queue job created successfully",
    "⏳ Waiting for worker to process job (status: pending)",
    "ACTION: Check if Lightspeed workers are running"
  ]
}
```

---

## 🧪 Test 4: Check Workers Are Running

### SQL Check:
```sql
-- Check for recent processing activity
SELECT 
    status,
    COUNT(*) as count,
    MAX(started_at) as last_started,
    MAX(completed_at) as last_completed
FROM queue_jobs
GROUP BY status;
```

### Expected Result (if workers running):
```
+------------+-------+---------------------+---------------------+
| status     | count | last_started        | last_completed      |
+------------+-------+---------------------+---------------------+
| pending    |    12 | NULL                | NULL                |
| processing |     2 | 2025-01-XX 14:29:55 | NULL                |
| completed  |  8945 | 2025-01-XX 14:29:50 | 2025-01-XX 14:29:52 |
| failed     |    23 | 2025-01-XX 14:20:10 | 2025-01-XX 14:20:12 |
+------------+-------+---------------------+---------------------+
```

### If NO workers running:
```
+------------+-------+---------------------+---------------------+
| status     | count | last_started        | last_completed      |
+------------+-------+---------------------+---------------------+
| pending    |   156 | NULL                | NULL                |
| completed  |  8945 | 2025-01-XX 10:15:22 | 2025-01-XX 10:15:24 |
| failed     |    23 | 2025-01-XX 10:10:00 | 2025-01-XX 10:10:02 |
+------------+-------+---------------------+---------------------+
```
⚠️ **ACTION NEEDED:** If `processing` status is missing and last activity is old, workers may not be running!

### Check Worker Heartbeats:
```sql
SELECT 
    worker_id,
    hostname,
    last_heartbeat,
    jobs_processed,
    status
FROM queue_worker_heartbeats
ORDER BY last_heartbeat DESC
LIMIT 5;
```

### Expected (if workers running):
```
+--------------------+-----------+---------------------+----------------+--------+
| worker_id          | hostname  | last_heartbeat      | jobs_processed | status |
+--------------------+-----------+---------------------+----------------+--------+
| worker-01-abc123   | app-srv-1 | 2025-01-XX 14:30:05 |            234 | active |
| worker-02-def456   | app-srv-1 | 2025-01-XX 14:30:03 |            189 | active |
+--------------------+-----------+---------------------+----------------+--------+
```

---

## 🧪 Test 5: Wait for Job Processing (5-30 seconds)

### Monitor Job Status:
```sql
-- Run this every 5 seconds to watch status change
SELECT 
    id,
    job_type,
    status,
    attempts,
    started_at,
    completed_at,
    last_error
FROM queue_jobs
WHERE job_type = 'transfer.create_consignment'
AND id = 19680;
```

### Expected Progression:
```
1. Initially:   status='pending', started_at=NULL, completed_at=NULL
2. Processing:  status='processing', started_at='2025-01-XX 14:30:10', completed_at=NULL
3. Success:     status='completed', started_at='2025-01-XX 14:30:10', completed_at='2025-01-XX 14:30:12'
```

### If Failed:
```
status='failed', attempts=1, last_error='Some error message'
```
⚠️ **Check `last_error` for details**

---

## 🧪 Test 6: Verify Lightspeed Sync

### Once job completes, check for queue_consignments:
```sql
SELECT 
    id,
    transfer_id,
    vend_consignment_id,
    status,
    sync_status,
    last_sync_at
FROM queue_consignments
WHERE transfer_id = 27043;
```

### Expected Result:
```
+----+-------------+---------------------+--------+-------------+---------------------+
| id | transfer_id | vend_consignment_id | status | sync_status | last_sync_at        |
+----+-------------+---------------------+--------+-------------+---------------------+
| 89 |       27043 | vend-abc-123-xyz    | SENT   | synced      | 2025-01-XX 14:30:12 |
+----+-------------+---------------------+--------+-------------+---------------------+
```

### Check transfer updated with Lightspeed ID:
```sql
SELECT 
    id,
    state,
    vend_transfer_id,
    consignment_id,
    updated_at
FROM transfers
WHERE id = 27043;
```

### Expected Result:
```
+-------+-------+------------------+----------------+---------------------+
| id    | state | vend_transfer_id | consignment_id | updated_at          |
+-------+-------+------------------+----------------+---------------------+
| 27043 | SENT  | vend-abc-123-xyz |             89 | 2025-01-XX 14:30:12 |
+-------+-------+------------------+----------------+---------------------+
```

---

## 🎯 Success Criteria

### ✅ Phase 1: Submission Fixed (CURRENT)
- [x] No "scheduled_at" column error
- [x] Queue job created successfully
- [x] Response includes queue_job_id and queue_job_uuid
- [x] Transfer state updated to SENT

### ⏳ Phase 2: Queue Processing (NEXT)
- [ ] Worker picks up job within 30 seconds
- [ ] Job status changes to 'processing' then 'completed'
- [ ] queue_consignments record created
- [ ] Transfer updated with vend_transfer_id

### ⏳ Phase 3: Lightspeed Verification (FINAL)
- [ ] Consignment appears in Lightspeed/Vend POS
- [ ] Products match transfer items
- [ ] Quantities correct
- [ ] Source/destination outlets correct

---

## 🚨 Troubleshooting

### Issue: Queue job created but status stays 'pending'
**Diagnosis:** Workers not running  
**Action:** Check worker processes, restart if needed

### Issue: Job fails immediately with error
**Diagnosis:** Check `last_error` in queue_jobs  
**Action:** Fix error cause (API credentials, network, etc.)

### Issue: Job completes but no queue_consignments
**Diagnosis:** Worker logic error or missing step  
**Action:** Check worker logs, verify queue_consignments INSERT logic

### Issue: queue_consignments created but no vend_consignment_id
**Diagnosis:** Lightspeed API call failed  
**Action:** Check Lightspeed API logs, credentials, rate limits

---

## 📞 Next Actions After Testing

1. **If submission works:** ✅ Move to Phase 2 (check workers)
2. **If workers not running:** 🚨 Restart worker processes
3. **If job fails:** 🔍 Investigate `last_error` and fix root cause
4. **If sync successful:** 🎉 Mark as COMPLETE and improve UI
5. **If sync fails:** 🔧 Debug Lightspeed API integration

---

**Test Date:** ___________  
**Tester:** ___________  
**Result:** ⬜ Pass | ⬜ Fail | ⬜ Partial  
**Notes:**

