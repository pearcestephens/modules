# 🔐 CIS Consignments API Playbook

**Author**: CIS Queue Engineering
  
**Audience**: Module engineers, integration owners, SRE/on-call
  
**Scope**: How every CIS module, bot, cron, or third-party bridge must interact with the Consignments domain when we go live in production.

---

## 1. Purpose & Guarantees

The Consignments API establishes **one authoritative orchestration layer** between CIS (legacy transfer tables) and Lightspeed Retail consignments. Every module that needs to create, sync, receive, or audit consignments must integrate through this layer. Direct Lightspeed calls from business modules are forbidden in production. Following this playbook guarantees:

- **Single source of truth** for transfer state, consignments, and product quantities
- **Idempotent** operations with queue-backed retry and audit logs
- **Predictable state transitions** (CIS ↔ Lightspeed) with replayable change history
- **Separation of concerns**: application modules drive business intent, queue services perform the integration work
- **Safe rollouts**: feature flags, safe mode, rate limiting, and metrics coverage already baked in

---

## 2. System Topology at a Glance

```
[Module (UI/Cron/Bot)]
        │  REST POST/GET (public/api/transfers/*)
        ▼
[Consignments API Gateway]
        │  wraps schema validation + auth + rate limit
        ▼
[Queue Jobs]  (queue_jobs table, key = transfer.*)
        │  async execution via worker-process.php
        ▼
[TransferConsignmentHandler]
        │  orchestrates DB + Lightspeed REST calls
        ▼
[Lightspeed Retail API]
```

Supporting services:
- `QueueV2\API\VendApiClient` – fully managed Lightspeed client with token, domain prefix, retry, throttling
- `Queue\Services\TransferStateMapper` – maps CIS transfer states ↔ Lightspeed statuses
- `queue_webhook_events` → worker pipeline – inbound webhooks always land here, never handled by modules

---

## 3. Lines of Responsibility

| Layer | Owned By | Responsibilities | Absolutely Never |
|-------|----------|------------------|------------------|
| **Business Modules** (UI, CLI, bots) | Feature team | • Decide *when* to sync  • Call appropriate REST endpoint  • Display job status  • Respect API rate limits & auth | • Call Lightspeed API directly  • Mutate `queue_*` tables  • Bypass queue/job system  • Write to consignment shadow tables |
| **Consignments API (public/api/transfers/*.php)** | Queue team | • Input validation  • Auth/CSRF enforcement  • Launch queue jobs  • Provide polling endpoints  • Enforce safe mode & feature flags | • Perform long-running sync inline  • Modify business tables outside defined procedures |
| **Worker & Handler (`TransferConsignmentHandler`)** | Queue team | • Perform Lightspeed operations  • Maintain shadow tables  • Update CIS transfer state + items  • Emit state transitions + metrics | • Accept external requests  • Bypass state mapper  • Skip audit logging |
| **Lightspeed API** | External service | • Store authoritative consignment in cloud | • Understand CIS state machine  • Retry logic for us |

**Rule of thumb**: everything north of `public/api/transfers/` is synchronous and fast, everything south is async and resilient.

---

## 4. Accessing the Consignments API

All public endpoints reside under:
```
https://staff.vapeshed.co.nz/assets/services/queue/public/api/transfers/
```
Use **HTTPS**, include CIS session auth or `_bot` bypass (when approved), and always set `Content-Type: application/x-www-form-urlencoded` (POST) or query params (GET).

### 4.1 Endpoint Matrix

| Endpoint | Method | Purpose | Typical Caller |
|----------|--------|---------|----------------|
| `create-consignment.php` | POST | Create Lightspeed consignment for a CIS transfer | Transfer UI “Create & Sync” button |
| `sync-to-lightspeed.php` | POST | Push latest CIS data/state to Lightspeed | Transfer edit form save, cron healers |
| `sync-from-lightspeed.php` | POST | Pull Lightspeed state/products back to CIS | Reconcile tools, webhook fallbacks |
| `consignment-status.php` | GET | Fetch combined status, state transitions, pending jobs | UI dashboards, mobile scanners |
| `force-resync.php` | POST | Reset job pipeline & requeue everything (admin only) | Ops hotfix, on-call |
| `jobs/{id}/status` | GET | Poll queue job state (envelope provided by job API) | UI toast, automation bots |

**All other functionality** (product bulk sync, inventory handoff, webhook processing) is triggered internally by jobs; modules never invoke handlers directly.

### 4.2 Example: Creating a Consignment from UI

```bash
curl -X POST \
  'https://staff.vapeshed.co.nz/assets/services/queue/public/api/transfers/create-consignment.php' \
  -H 'Cookie: PHPSESSID=...' \
  --data 'transfer_id=13218'
```
Response:
```json
{
  "success": true,
  "job_id": 982341,
  "vend_consignment_id": null,
  "poll_url": "/assets/services/queue/public/api/jobs/982341/status"
}
```

Module behaviour after POST:
1. Display immediate success/failure.
2. Start polling `poll_url` (every 1–2 seconds for up to 30s, then exponential backoff).
3. Reflect job state using standard badges (`pending`, `processing`, `completed`, `failed`).
4. Use `consignment-status.php` to render final Lightspeed + CIS state summary.

---

## 5. Queue Job Contracts

All Consignment actions enqueue jobs with `job_type` pattern `transfer.*`:

| Job Type | Enqueued By | Handler Method | Description |
|----------|-------------|----------------|-------------|
| `transfer.create_consignment` | `create-consignment.php` | `createConsignmentForTransfer()` | Build new Lightspeed consignment, sync products |
| `transfer.sync_to_lightspeed` | `sync-to-lightspeed.php` | `syncTransferStateToLightspeed()` + `syncTransferProductsToLightspeed()` | Push CIS changes up |
| `transfer.sync_from_lightspeed` | `sync-from-lightspeed.php` | `syncLightspeedStateToTransfer()` + `syncLightspeedProductsToTransfer()` | Pull latest status/items down |
| `transfer.force_resync` | `force-resync.php` | composite | Clears shadow caches, replays both directions |
| `transfer.inventory_post_receive` | Internal (handler) | `processReceivedConsignment()` | Trigger downstream inventory sync |

**Operational expectations**:
- Jobs are **idempotent**. Replays safe even if previous run succeeded.
- All error paths set `queue_jobs.last_error` with structured JSON.
- Handler writes audit line to `queue_consignment_state_transitions` for every state change.

Modules should never manipulate `queue_jobs` rows directly. To cancel a job, call the queue admin endpoint (future Section 12 tooling) or use operator dashboard.

---

## 6. State & Status Mapping

### 6.1 Allowed CIS Transfer States
```
DRAFT → PENDING_SEND → SENT → RECEIVED → CLOSED
              ↘ FAILED
```

### 6.2 Lightspeed Statuses
```
OPEN → IN_TRANSIT → RECEIVED
```

### 6.3 Mapping Rules (TransferStateMapper)

| CIS State | Lightspeed Status | Notes |
|-----------|------------------|-------|
| `DRAFT` / `PENDING_SEND` | `OPEN` | Consignment stays editable |
| `SENT` | `IN_TRANSIT` | Worker sets when CIS marks “Sent” |
| `RECEIVED` | `RECEIVED` | Worker also triggers inventory sync |
| `FAILED` | no direct LS state | Handler logs error, leaves LS untouched |

**Webhook-driven updates** use `TransferStateMapper::suggestCisState()` to avoid regressions (won’t move `RECEIVED` back to `SENT`).

---

## 7. Data Contracts

### 7.1 Transfer Shadow Tables

- `queue_consignments` – 1:1 with transfer consignment record, stores LS IDs, status, vendor version, timestamps
- `queue_consignment_products` – line items, desired vs received counts, SKU references
- `queue_consignment_state_transitions` – immutable log of state movement and cause (`cis_sync`, `webhook`, `force_resync`)

### 7.2 Payload Examples

#### Create Consignment Payload
```json
{
  "outlet_id": "UUID",
  "supplier_id": "UUID",
  "status": "OPEN",
  "due_at": "2025-10-12T10:00:00Z",
  "description": "Transfer 13218 → Hamilton",
  "products": [
    {"product_id": "prod_abc", "count": 25},
    {"product_id": "prod_xyz", "count": 12}
  ]
}
```

#### Consignment Status Response (`consignment-status.php`)
```json
{
  "success": true,
  "sync_status": {
    "is_synced": true,
    "current_state": "SENT",
    "lightspeed_status": "IN_TRANSIT",
    "last_sync_at": "2025-10-10 17:42:18"
  },
  "state_transitions": [
    {"from": "PENDING_SEND", "to": "SENT", "source": "cis_sync", "occurred_at": "2025-10-10 09:01:02"},
    {"from": "SENT", "to": "RECEIVED", "source": "webhook", "occurred_at": "2025-10-11 03:14:22"}
  ],
  "pending_jobs": []
}
```

---

## 8. Error Handling & Retries

1. **API Layer** returns standard envelope: `{ success, error|data, meta }`. Failures never emit raw stack traces.
2. **Queue Worker** logs structured errors, increments `queue_jobs.attempts`, and respects `max_attempts` with exponential delay.
3. **Lightspeed Failures** (429, network, 5xx) auto-retry with jitter inside `VendApiClient`. Hard failures propagate to job error.
4. **Module Response Plan**:
   - If API 400: fix input (module bug or invalid transfer state).
   - If API 409/423: concurrency conflict; schedule retry button in UI.
   - If API 202 with job, but job eventually `failed`: fetch `queue_jobs.last_error`, surface to user, offer retry.
5. **Force Resync** is the nuclear option: clears pending jobs, wipes shadow caches, enqueues fresh sync. Requires admin permission.

---

## 9. Security & Compliance Checklist

- All calls require CIS auth or `_bot` bypass with approved token.
- Safe Mode (`DASHBOARD_SAFE_MODE=1`) blocks destructive endpoints automatically (create, force-resync). For emergency disable, follow runbook in `IMPLEMENTATION_COMPLETE.md`.
- CSRF enforced on POST endpoints when session-based.
- API endpoints respect `RateLimiter` – default 60 write ops per minute; modules must handle `429` gracefully.
- Secrets (`VEND_API_TOKEN`, `VEND_DOMAIN_PREFIX`) live in `.env`; modules never read them directly.

---

## 10. Operational Monitoring

- **Queue**: `php reports/system_snapshot.php` → shows job totals, recent webhook volume, worker health.
- **Logs**: `tail -f runtime/logs/master.log` (lightspeed jobs) and `logs/application.log` (API layer).
- **Metrics**: `queue_metrics` table receives master process metrics when enabled; integrate with Grafana.
- **Alert thresholds**:
  - Any job stuck `processing` > 5 minutes → page on-call.
  - `consignment-status` showing `is_synced=false` for >15 minutes after send/receive → investigate.
  - Webhook count last 24h < baseline (in `COMPREHENSIVE_QUEUE_SYSTEM_DIAGNOSTIC.md`) → call Lightspeed.

---

## 11. Integration Rules (Line in the Sand)

**You must**:
- Use the provided REST endpoints for all consignment actions.
- Store only CIS transfer IDs inside modules; never persist Lightspeed IDs yourself.
- Poll job status instead of assuming immediate success.
- Respect state machine; do not manually set transfer state to a value the mapper would never produce.

**You must not**:
- Call Lightspeed REST endpoints directly from modules.
- Write to `queue_*` tables, `queue_jobs`, or consignment shadow tables.
- Change transfer state in CIS without telling the API (use `sync-to-lightspeed` after edits).
- Create ad-hoc cron scripts that bypass queue handlers.

**Edge cases**:
- Reopening a received consignment: raise an ops ticket; handler workflow assumes monotonic state.
- Inventory adjustments post-receive: use inventory modules; handler only enqueues first sync.

---

## 12. Go-Live Checklist

1. [ ] Confirm `.env` has correct `VEND_DOMAIN_PREFIX` & token.
2. [ ] Queue master + workers running (`php bin/master-process-manager.php status`).
3. [ ] Webhooks registered in Lightspeed admin (use `bin/register-all-vend-webhooks.php`).
4. [ ] Run `php bin/test-phase-2.php` (38/38 passing).
5. [ ] Execute `curl` tests for all 5 endpoints (200 or 202 responses).
6. [ ] Perform real transfer dry run:
   - Create transfer in CIS staging.
   - Call `create-consignment.php`.
   - Confirm job completes, Lightspeed consignment appears.
   - Mark transfer sent in CIS → call `sync-to-lightspeed.php`.
   - Trigger webhook (or manual) to mark received → verify state mapping.
7. [ ] Monitor `queue_jobs` and `queue_webhook_events` for 24h baseline metrics.

---

## 13. Support & Escalation

- **First line**: Queue Engineering (Transfer ownership).
- **Incident bridge**: escalate immediately if >15 minutes of webhook silence or queue backlog > 20 jobs.
- **Runbooks**: see `IMPLEMENTATION_COMPLETE.md` (Runbooks 1–6) and `SECURITY_DEPLOYMENT_GUIDE.md` for safe mode operations.

---

## 14. Class-Driven Contract (Authoritative Interfaces)

Every integration must respect the following class responsibilities. These are **production contracts**; do not fork or bypass them when cloning the pattern for other pipelines.

| Class | Namespace | Purpose | Calls Into |
|-------|-----------|---------|------------|
| `Queue\\Handlers\\TransferConsignmentHandler` | `https://staff.vapeshed.co.nz/assets/services/queue/src/Handlers/TransferConsignmentHandler.php` | Orchestrates CIS ↔ Lightspeed state & product sync, webhook handling, inventory triggers | `Queue\\Services\\TransferStateMapper`, `QueueV2\\API\\VendApiClient`, CIS PDO connection |
| `QueueV2\\API\\VendApiClient` | `https://staff.vapeshed.co.nz/assets/services/queue/src/API/VendApiClient.php` | Wraps Lightspeed REST calls with auth, retries, throttling, telemetry | `QueueV2\\Core\\Http\\HttpClient`, env config |
| `QueueV2\\Pipeline\\PipelineManager` | `https://staff.vapeshed.co.nz/assets/services/queue/src/Pipeline/PipelineManager.php` | Registers & executes pipelines, manages execution records, queues jobs | `QueueV2\\Database\\PdoConnection`, `QueueV2\\Logging\\Logger`, `QueueV2\\Metrics\\MetricsCollector` |
| `QueueV2\\Workers\\JobWorker` | `https://staff.vapeshed.co.nz/assets/services/queue/src/Workers/JobWorker.php` | Picks jobs from `queue_jobs`, instantiates handlers, applies retries | `QueueV2\\Jobs\\JobProcessor`, `QueueV2\\Config\\QueueConfig` |
| `Queue\\Services\\TransferStateMapper` | `https://staff.vapeshed.co.nz/assets/services/queue/src/Services/TransferStateMapper.php` | Deterministic mapping between CIS transfer states and Lightspeed statuses | pure mapping table |

**Class Contract Rules**
- Only `TransferConsignmentHandler` mutates `queue_consignments*` tables.
- All external API calls flow through `VendApiClient`; never instantiate curl/Guzzle in modules.
- Pipelines are declared via `PipelineManager::registerPipeline()` and executed via `PipelineManager::executePipeline()`.
- Workers are managed by `https://staff.vapeshed.co.nz/assets/services/queue/bin/master-process-manager.php`; do not launch ad-hoc scripts instantiating handlers manually.

### 14.1 Handler Method Reference (Must-Use Signatures)

```php
use Queue\\Handlers\\TransferConsignmentHandler;

$handler = new TransferConsignmentHandler($pdo);

$handler->createConsignmentForTransfer(int $transferId): array;
$handler->syncTransferStateToLightspeed(int $transferId): array;
$handler->syncLightspeedStateToTransfer(string $vendConsignmentId): array;
$handler->syncTransferProductsToLightspeed(int $transferId): array;
$handler->syncLightspeedProductsToTransfer(string $vendConsignmentId): array;
$handler->processReceivedConsignment(int $transferId): array;
$handler->handleWebhook(string $type, array $payload): array;
```

Return payloads always follow `{ success: bool, ... }`. When cloning this pattern for new domains, keep the shape identical so dashboards can reuse status widgets.

---

## 15. Pipeline Architecture & Replication Guide

### 15.1 Core Pipeline Flow

```
PipelineManager::registerPipeline()
      │
      ▼
queue_pipelines (definition) ──► queue_pipeline_steps / validation rules (optional)
      │
      ▼
PipelineManager::executePipeline($pipelineId, $payload)
      │
      ├─ sync mode → instantiate handler immediately
      ├─ async mode → enqueue job in queue_jobs (job_type = pipeline.execution)
      └─ background → spawn detached PHP process
      ▼
Worker picks job → instantiates handler → calls `handle($payload, $jobContext)`
      ▼
Execution status persisted in `queue_pipeline_executions` + `queue_pipeline_execution_steps`
```

### 15.2 Registering the Consignment Pipeline (One-Time Migration)

```php
use QueueV2\\Pipeline\\PipelineManager;
use Queue\\Handlers\\TransferConsignmentHandler;

$manager = new PipelineManager();

$pipelineId = $manager->registerPipeline([
  'name' => 'transfer-consignment-sync',
  'label' => 'Transfer → Consignment Synchroniser',
  'description' => 'Creates and keeps Lightspeed consignments in sync with CIS transfers.',
  'handler_class' => TransferConsignmentHandler::class,
  'execution_mode' => 'async', // ensures work lands on queue workers
  'timeout_seconds' => 420,
  'retry_attempts' => 4,
  'handler_config' => [
    'safe_mode' => getenv('DASHBOARD_SAFE_MODE') === '1',
    'metrics_channel' => 'consignments',
  ],
  'tags' => ['vend', 'transfers', 'mission-critical'],
  'steps' => [
    ['name' => 'map-payload', 'handler' => 'Queue\\Pipeline\\Steps\\PayloadMapperStep'],
    ['name' => 'enqueue-job', 'handler' => 'Queue\\Pipeline\\Steps\\QueueJobStep'],
  ],
  'validation_rules' => [
    ['field' => 'transfer_id', 'rule' => 'required|integer'],
  ],
]);

printf("Registered consignment pipeline: %s\n", $pipelineId);
```

> **Note**: Register once per environment. Definitions live in `queue_pipelines`. Use `PipelineManager::getAllPipelines()` before creating to avoid duplicates.

### 15.3 Executing the Pipeline (API Layer Example)

```php
use QueueV2\\Pipeline\\PipelineManager;

$manager = new PipelineManager();

$result = $manager->executePipeline('transfer-consignment-sync', [
  'transfer_id' => (int) $_POST['transfer_id'],
  'actor' => $currentUser->getId(),
  'source' => 'ui.transfer.form',
], [
  'priority' => 7,
]);

if ($result['success'] && $result['execution_mode'] === 'async') {
  return $response->json([
    'success' => true,
    'job_id' => $result['result']['job_id'],
    'poll_url' => sprintf(
      '/assets/services/queue/public/api/jobs/%s/status',
      $result['result']['job_id']
    ),
  ]);
}

return $response->json($result, $result['success'] ? 200 : 500);
```

### 15.4 Status & Tracing

```php
$manager = new PipelineManager();

$status = $manager->getExecutionStatus($executionId);

$status['execution']['status'];        // pending|queued|running|completed|failed
$status['execution']['execution_time_ms'];
$status['trace_timeline'];             // arrays for UI timeline widgets
```

Mirror this in dashboards to visualise each stage while replicating the pattern to other domains.

---

## 16. Configuration Surface (Env + Config Files)

All pipeline-powered integrations must read configuration exclusively from `https://staff.vapeshed.co.nz/assets/services/queue/config` files and `.env` keys. **Do not** hard-code values when cloning this playbook.

| File | Responsibility | Key Options |
|------|----------------|-------------|
| `config/app.php` | Core app metadata, feature flags | `app_environment`, `feature_flags` |
| `config/queue.php` | Worker limits, queue priorities, safety rails | `workers.max_workers`, `queues.vend.priority`, `safety.max_queue_size` |
| `config/security.php` | Rate limiting, CSRF, admin guards | `csrf.enabled`, `rate_limits.consignments` |
| `.env` | Secrets + environment identifiers | `VEND_DOMAIN_PREFIX`, `VEND_API_TOKEN`, `QUEUE_DB_DSN` |

### 16.1 Required Environment Keys

```
VEND_DOMAIN_PREFIX=vapeshed
VEND_DOMAIN=retail.lightspeed.app
VEND_API_TOKEN=***
QUEUE_DB_DSN=mysql:host=127.0.0.1;dbname=cis_queue
QUEUE_DB_USER=cis_queue
QUEUE_DB_PASS=***
QUEUE_RATE_LIMIT_WINDOW=60
QUEUE_RATE_LIMIT_LIMIT=60
```

Any new pipeline must document additional keys and update `https://staff.vapeshed.co.nz/assets/services/queue/docs/CONFIGURATION_REFERENCE.md`.

### 16.2 Safe Mode Propagation

- `DASHBOARD_SAFE_MODE=1` → API layer blocks destructive calls.
- `SAFE_PIPELINE_MODES=transfer-consignment-sync,inventory-audit` → only listed pipeline IDs execute when safe mode is active.
- Test via `https://staff.vapeshed.co.nz/assets/services/queue/bin/master-process-manager.php safe-mode --status` (see `SECURITY_DEPLOYMENT_GUIDE.md`).

---

## 17. Replication Checklist for New Pipelines

1. Duplicate this playbook section, substituting class names with the new handler.
2. Implement handler skeleton following `TransferConsignmentHandler` docblocks and response signatures.
3. Add configuration overrides to `config/queue.php` if bespoke queue behaviour is required.
4. Register pipeline via `PipelineManager::registerPipeline()` migration script.
5. Expose REST endpoint under `https://staff.vapeshed.co.nz/assets/services/queue/public/api/{domain}/` that delegates to `executePipeline()`.
6. Ensure `QueueV2\\Workers\\JobWorker` routes new `job_type` values to the handler (follow existing vend mapping).
7. Ship integration tests hitting the REST endpoint, asserting job execution and DB state transitions.
8. Update documentation: `MANIFEST.md`, relevant runbooks, and the cloned playbook.

**Comments & Code Expectations**
- Every new handler method requires PHPDoc (purpose, params, return type).
- Inline comments only where logic is non-obvious (follow `TransferConsignmentHandler` patterns).
- Provide at least one runnable example (CLI or REST) in the documentation delivered with the pipeline.

---

**Verdict**: If your module respects this contract, the Consignments platform will stay consistent, auditable, and production-safe as we go live.
