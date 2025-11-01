# Consignments Module - Strategic Roadmap

**Last Updated:** November 1, 2025
**Horizon:** 6 Months (Nov 2025 → Apr 2026)
**Aligned With:** [STRATEGIC_REPORT_WHERE_WE_ARE_AND_WHERE_TO_GO.md](../_kb/STRATEGIC_REPORT_WHERE_WE_ARE_AND_WHERE_TO_GO.md)

---

## 🎯 Vision Statement

Transform the Consignments module into a **rock-solid, production-grade transfer management system** that:
- Seamlessly integrates with Lightspeed Retail's native consignment model
- Provides bulletproof reliability through idempotent operations and comprehensive auditing
- Enables efficient multi-outlet operations with automated workflows
- Scales to support future growth (mobile, multi-warehouse, supplier portal)

---

## 📅 Timeline Overview

```
NOW                 2 WEEKS              1 MONTH              2 MONTHS             3 MONTHS             6 MONTHS
├───────────────────┼────────────────────┼────────────────────┼────────────────────┼────────────────────┼──────>
│   O1-O4           │   O5-O8            │   O9-O11           │   O12-O13          │   Advanced         │ Scale
│   Foundation      │   Infrastructure   │   Features         │   Polish           │   Features         │ & Growth
│                   │                    │                    │                    │                    │
│ Status Map        │ Queue Worker       │ Receiving++        │ Test Coverage 90%  │ Mobile App         │ Multi-Warehouse
│ Security          │ Webhooks           │ Admin Dashboard    │ CI/CD Pipeline     │ Supplier Portal    │ Advanced AI
│ Service Sync      │ LS Client++        │ Transfer Types     │ Docs Consolidation │ Advanced Analytics │ Integrations
└───────────────────┴────────────────────┴────────────────────┴────────────────────┴────────────────────┴──────>
```

---

## 🚀 Phase 1: Foundation (Weeks 1-2)

**Goal:** Establish core architecture patterns, eliminate critical gaps, harden security

### Objectives (O1-O4)

#### O1: Directory Hygiene ✅ **COMPLETE**
- [x] Create hexagonal architecture structure
- [x] Move all docs to `/docs/`
- [x] Remove dead files (*.bak, *.old, duplicates)
- [x] Add CODEOWNERS
- [x] Create README, STATUS, ROADMAP

#### O2: Canonical Status Map ⏳ **IN PROGRESS**
- [ ] Create `domain/ValueObjects/Status.php`
- [ ] Create `domain/Policies/StateTransitionPolicy.php`
- [ ] Create `infra/Lightspeed/StatusMap.php` (CIS ↔ LS)
- [ ] Enforce transitions in all write paths (return 422 on illegal)
- [ ] Unit tests: happy paths + illegal transitions

#### O3: Service/API Method Sync ⏳ **NEXT**
- [ ] Audit all API action → service method calls
- [ ] Implement missing `updateItemPackedQty()`
- [ ] Unify `setStatus()` vs `updateStatus()`
- [ ] Add controller helpers: `requirePost()`, `verifyCsrf()`, `getJsonInput()`
- [ ] API tests for all status/update endpoints

#### O4: Security Hardening ⏳ **NEXT**
- [ ] Remove ALL hardcoded secrets (grep for `password`, `PIN_CODE`, tokens)
- [ ] Enforce env vars with startup validation
- [ ] Add `.env.example` with all required vars
- [ ] CSRF checks on ALL write endpoints
- [ ] Path traversal protection (reject `..`, normalize with `realpath()`)
- [ ] Security test suite

**Acceptance Criteria:**
- ✅ All tests pass (unit + API)
- ✅ No secrets in code (`grep` returns clean)
- ✅ Invalid status transitions → 422 with error details
- ✅ Missing CSRF → 403
- ✅ Missing env vars → startup error with instructions

---

## ⚙️ Phase 2: Infrastructure (Weeks 3-4)

**Goal:** Build async processing foundation, enable real-time Lightspeed sync

### Objectives (O5-O8)

#### O5: Lightspeed Client Enhancement
- [ ] OAuth2 token auto-refresh
- [ ] Idempotency keys on POST/PUT (hash of endpoint + body)
- [ ] Exponential backoff with jitter (429, 5xx, network errors)
- [ ] Anti-corruption layer (LS JSON ↔ Domain DTOs)
- [ ] Integration tests (mock 429/500, verify retries)

#### O6: Queue Worker & Poller
- [ ] `bin/queue-worker.php` - Process `queue_jobs` table
  - Claim jobs with `FOR UPDATE SKIP LOCKED`
  - Dispatch to handlers by `job_type`
  - Retry with exponential backoff
  - Move to DLQ after max attempts
- [ ] `bin/poll-ls-consignments.php` - Cursor-based sync
  - Fetch changes since last cursor
  - Upsert to shadow tables
  - Reconcile local state
- [ ] Cron examples in `docs/Runbooks/QueueAndPolling.md`
- [ ] Supervisor config for production

#### O7: Webhook Handler
- [ ] `infra/Webhooks/LightspeedWebhookHandler.php`
- [ ] `public/webhooks/lightspeed.php` endpoint
- [ ] HMAC signature validation (LS_WEBHOOK_SECRET)
- [ ] Idempotent job creation (dedupe by event_id)
- [ ] Return 202 immediately, process async
- [ ] Document setup in `docs/API/LightspeedWebhooks.md`

#### O8: Transfer Type Services
- [ ] `domain/Services/OutletTransferService.php` (store-to-store)
- [ ] `domain/Services/SupplierReturnService.php` (return flow)
- [ ] `domain/Services/StocktakeService.php` (variance workflow)
- [ ] Type-specific approval policies
- [ ] End-to-end test for OUTLET_TRANSFER

**Acceptance Criteria:**
- ✅ Queue worker processes jobs locally (smoke test)
- ✅ DLQ receives failed jobs after max retries
- ✅ Poller advances cursor, updates shadows
- ✅ Webhook endpoint validates HMAC, creates jobs
- ✅ Replay-safe (duplicate events ignored)
- ✅ OUTLET_TRANSFER: create → send → receive works

---

## 🎨 Phase 3: Features & Observability (Month 2)

**Goal:** Complete remaining transfer types, add admin visibility, enhance receiving

### Objectives (O9-O11)

#### O9: Receiving & Evidence
- [ ] Enhance `ReceivingService`:
  - Photo capture & storage
  - Signature capture & validation
  - Per-item variance tracking
  - Trigger LS receive at Step 8 (actuals, not planned)
- [ ] Complete audit trail (before/after JSON in transfer_audit_log)
- [ ] UI: receive with signature + photos
- [ ] Verify LS reflects final counts

#### O10: Freight Integration Testing
- [ ] Wrap existing `FreightIntegration` in domain service
- [ ] Tests: weight/volume calc, container suggestion, quote caching
- [ ] Test booking idempotency
- [ ] Document carrier credentials in `docs/Runbooks/FreightSetup.md`

#### O11: Admin Sync Status Dashboard
- [ ] `/admin/consignments/sync-status` UI
- [ ] Display metrics:
  - Queue depth, processing rate, error rate
  - Recent failures with retry button
  - Webhook last received timestamp
  - Poller cursor position
  - Worker heartbeat
- [ ] Basic counters (file/DB-based if no Prometheus):
  - `jobs_processed_total`
  - `jobs_failed_total`
  - `webhooks_received_total`

**Acceptance Criteria:**
- ✅ Receive flow captures signature + photos
- ✅ Variance tracked per line item
- ✅ LS receives actual quantities (not planned)
- ✅ Audit log shows before/after JSON
- ✅ Admin dashboard shows live metrics
- ✅ Retry button re-queues failed jobs

---

## 🧪 Phase 4: Testing & Documentation (Months 2-3)

**Goal:** Achieve 90% test coverage, consolidate docs, enable CI

### Objectives (O12-O13)

#### O12: Tests & CI
- [ ] **API Tests:** Extend `test-consignment-api.sh`
  - All status transitions
  - Receive workflow
  - Variance handling
- [ ] **Unit Tests:**
  - `StatusMap`, `StateTransitionPolicy`
  - `LightspeedClient` retry/idempotency
- [ ] **Integration Tests:**
  - Queue worker (happy + fail → DLQ)
  - Webhook handler HMAC validation
- [ ] **Smoke Tests:**
  - End-to-end: create → send → receive (minimal flow with mocks)
- [ ] **CI Pipeline:**
  - GitHub Actions (or equivalent)
  - Run tests on every PR
  - Block merge if tests fail
  - Security scan (secrets, dependencies)

#### O13: Documentation Finalization
- [ ] Update `docs/STATUS.md` (snapshot: % complete, date, risks)
- [ ] Sync `docs/Roadmap.md` with this document
- [ ] Create runbooks:
  - `docs/Runbooks/QueueAndPolling.md`
  - `docs/Runbooks/FreightSetup.md`
  - `docs/Runbooks/EmergencyRecovery.md`
- [ ] API documentation:
  - `docs/API/Endpoints.md` (full reference)
  - `docs/API/LightspeedWebhooks.md`
- [ ] Testing guide: `docs/Testing.md`
- [ ] Troubleshooting guide: `docs/Troubleshooting.md`
- [ ] Remove duplicate/conflicting docs
- [ ] Consolidate to single "Where We Are" (use Strategic Report)

**Acceptance Criteria:**
- ✅ Test coverage ≥ 90% for touched code
- ✅ All test suites green locally & in CI
- ✅ Failing tests block PR merge
- ✅ Docs lint passes (no broken links)
- ✅ No contradictory completion claims
- ✅ Single authoritative status document

---

## 🚀 Phase 5: Advanced Features (Months 3-4)

**Goal:** Expand capabilities, prepare for mobile & supplier portal

### Key Initiatives

#### Mobile App Planning
- Native iOS/Android apps for:
  - Barcode scanning
  - Signature capture
  - Photo documentation
  - Push notifications
- Offline mode with sync
- Design mockups & UX flows
- API enhancements for mobile

#### Advanced Analytics
- Transfer performance dashboard
- Supplier reliability scoring
- Outlet efficiency metrics
- Cost analysis & trends
- Predictive insights (ML)

#### Automation Enhancements
- Auto-PO generation (reorder points)
- Smart container selection (ML)
- Automated carrier selection
- Predictive freight costs
- Auto-reconciliation (invoice vs PO)

#### Q16-Q35 Gap Analysis
- Complete remaining business requirement questions
- Document in `docs/ROADMAP_Q16_Q35.md`
- Implement features:
  - Product search & autocomplete (Q16)
  - PO amendment & cancellation (Q17)
  - Duplicate PO prevention (Q18)
  - Photo capture management (Q19)
  - GRNI generation (Q20)

**Target Completion:** End of Month 4

---

## 🌐 Phase 6: Scale & Growth (Months 5-6)

**Goal:** Enable multi-warehouse operations, supplier self-service, enterprise features

### Strategic Initiatives

#### Supplier Portal
- Self-service PO management
- Real-time status visibility
- Invoice upload
- Shipping updates
- Performance reports
- Launch pilot with 3-5 key suppliers

#### Multi-Warehouse Optimization
- Cross-warehouse inventory balancing
- Optimal transfer routing
- Consolidated shipping
- Central distribution hub model

#### Advanced Integrations
- Accounting system (Xero, MYOB)
- CRM (Salesforce, HubSpot)
- E-commerce platforms (Shopify, WooCommerce)
- Marketplace integrations (Amazon, eBay)
- EDI with suppliers

#### AI-Driven Operations
- Demand forecasting
- Auto-reordering based on predicted needs
- Anomaly detection (fraud, theft, errors)
- Natural language queries
- Chatbot assistant for staff

**Target Completion:** End of Month 6

---

## 📊 Success Metrics (6-Month Targets)

### Technical KPIs
- **System Uptime:** 99.9%
- **API Response Time (p95):** <200ms
- **Lightspeed Sync Success Rate:** >99%
- **Queue Processing Time (p95):** <30s
- **Test Coverage:** >90%
- **Security Scan Pass Rate:** 100%

### Business KPIs
- **PO Creation Time:** -50% (vs baseline)
- **Receiving Time:** -40% (vs baseline)
- **Freight Cost Savings:** -15% (via optimization)
- **Stockout Incidents:** -30% (via better forecasting)
- **User Satisfaction Score:** >4.5/5.0
- **Support Ticket Volume:** -60% (better UX + docs)

### Financial Impact (12-Month Target)
- **ROI:** >200%
- **Labor Cost Savings:** >$50k/year
- **Freight Optimization Savings:** >$20k/year
- **Reduced Stock Discrepancies:** >$10k/year

---

## 🚨 Risk Mitigation Strategies

### High-Priority Risks

#### Lightspeed API Rate Limiting
- **Mitigation:** Exponential backoff, webhooks over polling, aggressive caching, request rate limit increase

#### Queue Worker Failures
- **Mitigation:** Supervisor process management, health checks every 5min, auto-restart, alerting

#### Data Consistency Issues
- **Mitigation:** Idempotent operations, transaction boundaries, conflict resolution logic, regular reconciliation jobs

#### Incomplete User Training
- **Mitigation:** Video tutorials, in-person sessions, ongoing support team, feedback loops

---

## 🔄 Review & Adjustment Schedule

- **Weekly:** Review progress against this roadmap during active refactoring
- **Bi-weekly:** Adjust priorities based on blockers and new requirements
- **Monthly:** Update metrics, conduct retrospectives
- **Quarterly:** Strategic review, align with business priorities

---

## 📞 Stakeholders & Contacts

- **Module Owner:** Pearce Stephens (@pearcestephens)
- **Operations Lead:** @ops-lead
- **Development Team:** @dev-team
- **QA Lead:** @qa-lead
- **Product Owner:** @product-owner

---

**Next Major Review:** November 15, 2025 (After O1-O4 Complete)
**Next Minor Update:** November 8, 2025 (Weekly sync)
