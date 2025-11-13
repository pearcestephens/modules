# Q27-Q35 Quick Reference - Implementation Specs

**All questions answered and locked in. Ready for autonomous build.**

---

## Q27: Email Templates

**Two templates:** Internal + Supplier (same design, different content)

**Design:**
- Black header + yellow accent bar (#ffcc00)
- 600px fixed width (mobile scales)
- Table-based layout (email-safe)
- Inline CSS only (no external resources)
- VML fallback for Outlook

**Customization:**
- ✅ Logo URL, company details, contact info, support email
- ❌ Design locked (no template editor for users)
- Optional: Per-supplier overrides (future enhancement)

**Content:**
- Event summary (created/approved/sent/received)
- Key details (supplier, store, total, items)
- Product table (full list if < 20 items, summary if > 20)
- CTAs (View in CIS, Download PDF/PO)
- Footer (company info, GST/NZBN, support, trademark)

---

## Q28: Email Delivery

**Strategy: HYBRID**

- **Real-time (immediate):** Rejections, approvals, errors, budget alerts, over-receipts
- **Batched (30min):** PO confirmations, status changes
- **Daily digest (8 AM):** Routine notifications, summaries

**Implementation:**
- Queue system: `email_queue` table (priority field)
- Cron jobs: 1min (urgent), 30min (batch), 8 AM (digest)
- User preferences: Configurable per role (default values)
- Max retries: 3 with exponential backoff (1min, 5min, 15min)
- Archive: 30 days, then move to archive table

**User controls:**
- Can configure: Real-time vs digest per email type
- Can unsubscribe: Digest only (urgent emails mandatory)
- Preferences table: `user_email_preferences`

---

## Q29: Exception Handling

**Three-tier routing:**

**TIER 1 - System Errors (→ Tech team)**
- Xero sync fail (after 3 retries)
- Lightspeed timeout
- PDF generation fail
- Photo upload fail

**TIER 2 - Business Exceptions (→ Finance/Approvers)**
- Over-receipt detected (real-time)
- Price mismatch (if > $50 OR > 5%)
- Supplier not found in LS
- Barcode scan failed

**TIER 3 - Approval Exceptions (→ Approvers + Escalate)**
- Approval timeout (Day 7→Manager, Day 10→Director, Day 14→Auto-cancel)
- Rejected 3x (alert manager + requester)
- Budget exceeded (alert finance, escalate if > $10K)

**Exception dashboard:**
- All open exceptions with age + assigned owner
- Status tracking (new, acknowledged, in-progress, resolved)
- Manual retry buttons + comments section
- Severity badges (minor, major, critical)

**Manual override:** Available for all exceptions (audit logged with reason)

---

## Q30: Integration Sequencing

**Strategy: Optimistic commit (user-facing status commits immediately, integrations retry async)**

**SEND event sequence:**
1. SYNC (blocking): Validate all data + Lightspeed sync
   - If fail: STOP, keep DRAFT, error to user
   - If OK: Continue to step 2
2. STATUS: Set to SENT (commit to DB)
3. ASYNC (background queue):
   - Xero sync (retry 24h)
   - Email (send immediately)
   - PDFs (generate)
   - Photos (upload)

**RECEIVE event sequence:**
1. SYNC (blocking): Validate qty, detect over-receipt
2. STATUS: Set to RECEIVED (commit to DB)
3. ASYNC: Lightspeed sync + Xero sync + Email + Archive photos

**Retry logic:**
- Exponential backoff: 1min → 5min → 15min → 1h → 4h → 24h
- Max retries: 10 attempts over 72 hours
- After 3 retries (5 min mark): Responsible team alerted

**Partial success OK:**
- Lightspeed succeeds, Xero fails? Status still SENT, Xero retried
- User sees: "Sent to Lightspeed. Finalizing..." (email when done)
- Eventually consistent (all systems sync within 24h)

---

## Q31: Data Validation

**Three tiers:**

**TIER 1 - Format validation (block)**
- Required fields, length limits, special chars
- Email format, phone format, date format
- Numbers (no negatives, reasonable ranges)
- Result: Block invalid data immediately

**TIER 2 - Existence validation (warn/block)**
- Supplier in Lightspeed? Missing → Create OR skip
- Outlet configured? Missing → BLOCK (tech must setup)
- SKUs exist? Missing → Warn + suggest fuzzy matches
- Quantities reasonable? Warn if 0 or > 1000
- Result: User chooses action (create, skip, cancel)

**TIER 3 - Business logic validation (warn)**
- Budget check (PO exceeds monthly budget?)
- Approval authority (approver can approve this amount?)
- Duplicate check (similar PO created recently?)
- Result: Warn, let user proceed with override

**Smart features:**
- Fuzzy matching: "Did you mean?" for typos (> 80% similar)
- Manual override: With reason + audit logging
- Validation audit log: Track all validations + user choices
- Tech alerts: If same validation block 5+ times = trend analysis

---

## Q32: Rate Limiting & Bulk Operations

**Soft/hard limits:**

- **Bulk PO:** 50 per batch (soft), 100 per 24h (hard)
- **Bulk amend:** 20 items at once
- **Photo upload:** 100 per session, 500 per 24h
- **Lightspeed API:** 30 calls/min (queue if exceeded)
- **Xero API:** 60 calls/min (queue if exceeded)
- **Items per PO:** 500 soft, 1000 hard
- **Concurrent drafts:** Unlimited (soft: 10 per user)

**User experience:**
- Progress bar: Current count, percentage, time remaining
- Pauseable: [Pause/Resume/Cancel] buttons
- Errors: Inline feedback with retry options
- Estimated time: "~3 minutes at current speed"

**Configurable (admin panel):**
- All limits adjustable (dropdown defaults provided)
- API limits: Auto or manual
- Alert thresholds: When to notify tech team

---

## Q33: Backup & Recovery

**Strategy: Incremental hourly + daily full + weekly off-site**

**Frequency:**
- Hourly: Incremental (only changes) → 7-day retention
- Daily: Full dump → 30-day retention
- Weekly: Full + incremental to off-site → 12-month retention

**What's backed up:**
- ✅ All consignment data tables
- ✅ Email queue + archive
- ✅ Approval history, integrations logs, validation attempts
- ✅ Photos referenced in DB (actual files in S3, provider-backed)

**Recovery procedures:**
- **Self-service:** Manager can restore deleted PO (preview first, then confirm)
- **Tech team:** Can restore full DB from any point in time
- **Disaster:** Off-site backup restores within 2-4 hours

**Testing:** Quarterly (first Sunday each month) - restore test backup + verify data

**RTO/RPO:**
- RTO (recovery time): 1 hour (from hourly backup), 4 hours (from off-site)
- RPO (recovery point): 1 hour (hourly), 7 days (off-site)

---

## Q34: Audit Trail

**What's logged:**
- User actions: Created, amended, sent, received, deleted
- Approval actions: Requested, granted, rejected, escalated
- Integration actions: Sync attempts, results, errors, external IDs
- Validation: All validations performed + user choices
- Exceptions: All exceptions with routing + resolution

**Audit table schema:**
```sql
audit_log
  - timestamp, user_id, action_type, record_type, record_id
  - old_value (JSON), new_value (JSON)
  - ip_address, reason, comment
  - external_system, external_id, error_message
  - duration_ms, retry_attempt
```

**Retention:** 7 years (tax compliance)
- Current: In `audit_log` table (queryable)
- 7+ years: Moved to `audit_log_archive` (separate storage)
- All backed up (hourly, daily, off-site)

**Access control (role-based):**
- Staff: Own records only
- Manager: Team records
- Finance: All records
- Tech: All records (filtering by system)
- Audit/compliance: All records (read-only, export-able)

**Reports:**
- "All POs created by user X in date range"
- "All approvals > $5000"
- "Integration failure history"
- "Manual overrides & exceptions"
- "Complete timeline for PO #X"
- Exportable: CSV, PDF, JSON

**Immutability:** Cannot be edited/deleted (audit integrity enforced)

---

## Q35: Performance Targets

**Page load SLAs:**
- Create/edit form: P95 < 500ms
- PO list/dashboard: P95 < 800ms
- Search autocomplete: < 300ms after keystroke
- Core web vitals: LCP < 2.5s, CLS < 0.1

**API SLAs:**
- POST /create: P95 < 1.5s
- POST /approve: P95 < 500ms
- POST /send: P95 < 3s (includes Lightspeed sync)
- POST /receive: P95 < 500ms
- GET /list: P95 < 1s

**Integration SLAs (async, background):**
- Lightspeed: Complete < 5s (queue if slow)
- Xero: Complete < 10s (queue if slow)
- Freight quote: Complete < 3s (GSS + NZ Post parallel)
- Email: Send immediately, process < 1s each

**Background jobs:**
- Email processor: Every 1 min (urgent), 30 min (batch), 8 AM (digest)
- Retry failed syncs: Every 5 min (early retries), 1h (mid), 24h (late)
- Report generation: Daily 1 AM, weekly Sunday 2 AM
- Cleanup: Daily 3 AM (email queue), monthly (archive old data)

**Monitoring & alerts:**
- Real-time dashboard: API perf, DB health, queue status, user activity
- Auto-alerts: CRITICAL (<30min response), WARNING (<2h), INFO (logging)
- Alert thresholds:
  - Page load P99 > 5s → Critical
  - API P99 > 3s → Warning
  - Error rate > 1% → Warning
  - Email queue > 500 → Warning

**Concurrency & capacity:**
- Support 100+ concurrent users
- Support 1000+ POs per day
- Uptime SLA: 99.5% (CIS)
- Load tests: Monthly (10, 50, 100, 1000 concurrent simulations)

**Cache strategy:**
- Browser: 1 day (JS bundles, CSS)
- Product search: 30 min (first 500 products)
- Approval counts: 5 min refresh
- Freight quotes: 30 min cache

---

## Build Readiness Checklist

✅ Q1-Q15: Complete (business logic)
✅ Q16-Q20: Complete (UX features)
✅ Q21-Q26: Complete (approvals + email)
✅ Q27-Q35: Complete (operations)

**Ready to build:**
- Base module structure
- Database schema
- Core PO CRUD operations
- Multi-tier approval system
- Email templates + queue
- Lightspeed + Xero integrations
- Freight integrations (weight/volume/rate)
- Exception handling + escalation
- Audit logging
- Rate limiting + monitoring

**Estimated build timeline:** 5-7 days (autonomous)

**Start build?** Waiting for your signal → "next questions" or "build now"

---

**Last Updated:** November 2, 2025
**Status:** All 35 questions answered ✅
**Ready:** YES - Ready for autonomous build
