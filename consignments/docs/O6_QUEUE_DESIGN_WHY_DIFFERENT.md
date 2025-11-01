# O6 Queue Design: Why This One Won't Fail

## 🚨 Past Queue Problems (Root Causes)

### Problem 1: **Race Conditions & Duplicate Processing**
**What Happened:**
- Multiple workers grabbed the same job
- Job processed 2-3 times → duplicate Lightspeed consignments
- No atomic locking mechanism

**Why It Happened:**
```sql
-- Old approach (BROKEN):
SELECT * FROM queue_jobs WHERE status='pending' LIMIT 1;
UPDATE queue_jobs SET status='processing' WHERE id=123;
-- ⚠️ GAP between SELECT and UPDATE = race condition
```

**O6 Solution:**
```sql
-- New approach (ATOMIC):
SELECT * FROM queue_jobs 
WHERE status='pending' 
ORDER BY priority DESC, id ASC 
LIMIT 1
FOR UPDATE SKIP LOCKED;
-- ✅ Row locked immediately, other workers skip it
```

### Problem 2: **Stuck Jobs (Deadlocks)**
**What Happened:**
- Worker crashed mid-job
- Job stuck in "processing" forever
- Manual intervention required

**Why It Happened:**
- No heartbeat monitoring
- No timeout detection
- Worker crashes = orphaned jobs

**O6 Solution:**
```php
// Heartbeat every 30 seconds
$stmt = $pdo->prepare("UPDATE queue_jobs SET heartbeat_at = NOW() WHERE id = ?");

// Separate monitor detects stale jobs:
SELECT * FROM queue_jobs 
WHERE status='processing' 
AND heartbeat_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE);
// → Auto-reset to pending
```

### Problem 3: **No Dead Letter Queue**
**What Happened:**
- Failed jobs retried forever
- Poison messages blocked queue
- No visibility into permanent failures

**O6 Solution:**
```php
// After max_attempts, move to DLQ:
INSERT INTO queue_jobs_dlq 
SELECT * FROM queue_jobs WHERE id=? AND attempts >= max_attempts;

DELETE FROM queue_jobs WHERE id=?;
// ✅ Failed jobs archived, don't block queue
```

### Problem 4: **No Idempotency**
**What Happened:**
- Network timeout → job retried
- Same consignment created 2x in Lightspeed
- Data corruption

**O6 Solution:**
```php
// Idempotency key in payload:
$payload = [
    'idempotency_key' => hash('sha256', $job_type . $data),
    'data' => $data
];

// LightspeedClient (O5) auto-sends Idempotency-Key header
// Lightspeed deduplicates on their end
```

### Problem 5: **No Cursor-Based Polling**
**What Happened:**
- Poller used `WHERE updated_at > ?`
- Clock skew → missed events
- Deleted records lost forever

**O6 Solution:**
```php
// Cursor tracks LAST SEEN ID (not timestamp):
$cursor = getLastCursor('consignments');
$response = $client->get('/consignments', [
    'after' => $cursor,
    'page_size' => 100
]);

// Update cursor to highest ID seen:
updateCursor('consignments', max($responseIds));
// ✅ Never miss an event, even if clock skews
```

---

## ✅ O6 Guarantees

| Guarantee | Mechanism | Test |
|-----------|-----------|------|
| **No duplicate processing** | `FOR UPDATE SKIP LOCKED` | Run 3 workers, verify 1 job = 1 execution |
| **No stuck jobs** | Heartbeat + timeout monitor | Kill worker mid-job, verify auto-reset |
| **Failed jobs archived** | DLQ after max_attempts | Poison job → verify moves to DLQ |
| **Idempotent operations** | SHA-256 keys + LS client | Retry same job 3x → 1 consignment created |
| **No missed events** | Cursor-based polling | Delete events during poll → verify captured |
| **Concurrent-safe** | Row-level locking | 10 workers, 1000 jobs → all processed once |
| **Graceful shutdown** | SIGTERM handler | `kill -TERM` → finishes current job, exits clean |
| **Backoff on errors** | Exponential: 200ms → 400ms → 800ms | API 429 → verify delays increase |

---

## 🏗️ O6 Architecture

```
┌─────────────────────────────────────────────────────────┐
│  QUEUE WORKER (bin/queue-worker.php)                    │
│  - Polls queue_jobs with FOR UPDATE SKIP LOCKED         │
│  - Updates heartbeat every 30s                          │
│  - Dispatches to handlers                               │
│  - Retries with exponential backoff                     │
│  - Moves to DLQ after max_attempts                      │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│  JOB HANDLERS                                            │
│  - TransferCreateHandler                                │
│  - TransferUpdateHandler                                │
│  - TransferReceiveHandler                               │
│  - ConsignmentSyncHandler                               │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│  LIGHTSPEED CLIENT (O5)                                  │
│  - Idempotency keys                                      │
│  - Exponential backoff                                  │
│  - Correlation IDs                                       │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  POLLER (bin/poll-ls-consignments.php)                  │
│  - Cursor-based pagination                              │
│  - Upserts shadow table (queue_consignments)            │
│  - Reconciles local state                               │
│  - Runs every 5 minutes (cron)                          │
└─────────────────────────────────────────────────────────┘
```

---

## 📊 Comparison: Old vs O6

| Aspect | Old Queue | O6 Queue |
|--------|-----------|----------|
| **Locking** | None → race conditions | `FOR UPDATE SKIP LOCKED` |
| **Stuck jobs** | Manual intervention | Auto-reset after 5min |
| **Failed jobs** | Retry forever | DLQ after 3 attempts |
| **Idempotency** | None | SHA-256 keys |
| **Polling** | Timestamp (clock skew) | Cursor (ID-based) |
| **Concurrency** | Unsafe | Safe (row locks) |
| **Monitoring** | Manual SQL queries | Admin dashboard (O11) |
| **Heartbeat** | None | Every 30s |
| **Graceful shutdown** | `kill -9` | SIGTERM handler |
| **Backoff** | Fixed 1s | Exponential with jitter |

---

## 🧪 How We'll Test It

### Test 1: Concurrent Workers (No Duplicates)
```bash
# Start 5 workers
for i in {1..5}; do
  php bin/queue-worker.php &
done

# Enqueue 1000 jobs
for i in {1..1000}; do
  mysql -e "INSERT INTO queue_jobs (job_type, payload) VALUES ('test', '{}')"
done

# Wait for completion
sleep 60

# Verify: 1000 jobs processed, 0 duplicates
SELECT COUNT(*) FROM job_results; -- Should be exactly 1000
```

### Test 2: Worker Crash Recovery
```bash
# Start worker
php bin/queue-worker.php &
PID=$!

# Enqueue job
mysql -e "INSERT INTO queue_jobs (job_type, payload) VALUES ('test', '{}')"

# Kill worker mid-execution
sleep 2 && kill -9 $PID

# Wait for timeout (5 min)
sleep 301

# Verify: Job auto-reset to pending
SELECT status FROM queue_jobs WHERE id=1; -- Should be 'pending'

# Start new worker
php bin/queue-worker.php &

# Verify: Job completes
sleep 10
SELECT status FROM queue_jobs WHERE id=1; -- Should be 'completed'
```

### Test 3: DLQ After Max Attempts
```bash
# Enqueue poison job (will always fail)
mysql -e "INSERT INTO queue_jobs (job_type, payload, max_attempts) VALUES ('fail', '{}', 3)"

# Worker retries 3 times
php bin/queue-worker.php

# Verify: Job in DLQ
SELECT COUNT(*) FROM queue_jobs_dlq WHERE job_type='fail'; -- Should be 1
SELECT COUNT(*) FROM queue_jobs WHERE job_type='fail'; -- Should be 0
```

---

## 🎯 Why This Won't Fail

1. **Atomic Operations**: `FOR UPDATE SKIP LOCKED` is database-level, can't race
2. **Self-Healing**: Timeout monitor auto-recovers stuck jobs
3. **Bounded Retries**: DLQ prevents infinite retry loops
4. **Idempotent by Design**: Duplicate jobs = same outcome (safe)
5. **Cursor Pagination**: Never misses events, even during downtime
6. **Battle-Tested Pattern**: Used by Sidekiq, Celery, Laravel Horizon
7. **Observable**: Heartbeat + logs + admin dashboard (O11)
8. **Graceful Degradation**: Worker crash = job auto-retries, no data loss

---

## 📈 Expected Performance

- **Throughput**: 50-100 jobs/minute (single worker)
- **Concurrency**: Safe up to 10 workers
- **Latency**: < 500ms per job (simple operations)
- **Stuck jobs**: Auto-recovered in < 5 minutes
- **Failed jobs**: Archived to DLQ, don't block queue
- **Uptime**: 99.9% (supervisor auto-restart)

---

**Bottom line:** O6 uses proven database concurrency primitives (`FOR UPDATE SKIP LOCKED`) + idempotency + bounded retries + DLQ. It's not "another queue system" — it's the **correct** implementation of queue fundamentals that were missing before.
