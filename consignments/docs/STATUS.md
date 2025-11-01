# Consignments Module - Current Status

**Last Updated:** November 1, 2025 03:00 UTC
**Version:** 2.0.0-alpha
**Phase:** Active Refactoring (Hexagonal Architecture Migration)

---

## 📊 Completion Overview

| Category | Completion | Status |
|----------|------------|--------|
| **O1: Directory Hygiene** | 100% | ✅ Complete |
| **O2: Status Map & Policy** | 0% | ⏳ In Progress |
| **O3: Service/API Sync** | 0% | 🔜 Pending |
| **O4: Security Hardening** | 0% | 🔜 Pending |
| **O5: Lightspeed Client** | 30% | 🟡 Partial |
| **O6: Queue Worker & Poller** | 0% | 🔜 Pending |
| **O7: Webhooks** | 0% | 🔜 Pending |
| **O8: Transfer Type Services** | 25% | 🟡 Partial (PO only) |
| **O9: Receiving & Evidence** | 40% | 🟡 Partial |
| **O10: Freight Integration** | 80% | 🟢 Mostly Complete |
| **O11: Admin Dashboard** | 0% | 🔜 Pending |
| **O12: Tests & CI** | 20% | 🟡 Partial |
| **O13: Documentation** | 50% | 🟡 Partial |
| **OVERALL** | **28%** | 🟡 In Progress |

---

## ✅ What's Working (Production-Ready)

### Core Features
- ✅ **Purchase Order Creation** - Draft → Approval → Active workflow
- ✅ **Multi-Tier Approvals** - $0-2k, $2k-5k, $5k+ tiers with role-based routing
- ✅ **Freight Integration** - Weight/volume calculation, container suggestions, NZ Post/GoSweetSpot quotes
- ✅ **Pack Interface** - Barcode scanning, real-time weight updates, draft auto-save
- ✅ **Receiving Flow** - Partial receives, variance tracking, signature capture
- ✅ **AI Insights** - GPT-4/Claude recommendations on transfers
- ✅ **Gamification** - Points, achievements, leaderboards for staff performance

### Infrastructure
- ✅ **Database Schema** - 18+ tables (vend_consignments, transfers, queue tables)
- ✅ **Lightspeed Sync** - Consignment creation, status updates (at receive time)
- ✅ **Audit Logging** - Complete before/after tracking in transfer_audit_log

---

## 🟡 What's Partially Done

### Needs Completion
- 🟡 **Lightspeed Client** - Has retry logic, needs idempotency keys & OAuth2 refresh
- 🟡 **Outlet Transfers** - Table structure exists, no dedicated service yet
- 🟡 **Supplier Returns** - Schema ready, workflows undefined
- 🟡 **Stocktakes** - Structure exists, integration unclear
- 🟡 **Queue System** - Tables exist, worker process not running
- 🟡 **Webhook Handler** - queue_webhook_events table exists, no endpoint
- 🟡 **Testing** - API tests exist, need unit/integration/smoke coverage

---

## 🔜 What's Not Started

### Critical Gaps
- ❌ **State Transition Policy** - No enforcement of illegal status changes
- ❌ **Status Mapping** - CIS ↔ Lightspeed conversions scattered in code
- ❌ **Queue Worker** - No bin/queue-worker.php daemon
- ❌ **Poller** - No bin/poll-ls-consignments.php cursor-based sync
- ❌ **Webhook Endpoint** - No /api/webhooks/lightspeed.php with HMAC validation
- ❌ **Admin Dashboard** - No /admin/consignments/sync-status monitoring UI
- ❌ **Security Audit** - No systematic secret scan, CSRF validation, or path traversal protection
- ❌ **CI Pipeline** - No GitHub Actions, tests not blocking PRs

---

## 🎯 Immediate Priorities (This Week)

### Day 1-2: Foundation
1. **O2: Status Map & Policy** - Canonical state machine, illegal transition blocking
2. **O3: Service/API Sync** - Fix method mismatches, implement missing methods
3. **O4: Security** - Remove secrets, enforce env vars, CSRF on writes

### Day 3-4: Sync Infrastructure
4. **O6: Queue Worker** - Build bin/queue-worker.php with retry & DLQ
5. **O7: Webhooks** - Create endpoint with HMAC validation
6. **O5: Lightspeed Client** - Add idempotency keys, OAuth2 token refresh

### Day 5: Observability
7. **O11: Admin Dashboard** - Basic sync status page with queue metrics
8. **O12: Tests** - Expand coverage for new components

---

## 📋 Known Issues & Blockers

### Critical
- 🔴 **No Queue Worker Running** - Async jobs not processing
- 🔴 **Webhook Endpoint Missing** - Can't receive Lightspeed events
- 🔴 **State Transitions Unvalidated** - Can set invalid statuses

### High
- 🟠 **Secrets in Code** - Some placeholder tokens still exist
- 🟠 **Method Name Mismatches** - API calls methods that don't exist
- 🟠 **No CSRF Protection** - Write endpoints vulnerable

### Medium
- 🟡 **Incomplete Test Coverage** - Only ~20% of code tested
- 🟡 **Documentation Scattered** - 12+ markdown files, some contradictory
- 🟡 **No CI Enforcement** - Tests don't block merges

---

## 🗓️ Roadmap Timeline

### Month 1 (Nov 2025) - Foundation
- ✅ Directory structure & hygiene
- ⏳ Status map & state machine
- ⏳ Queue worker & webhook infrastructure
- ⏳ Security hardening & secret removal
- ⏳ Basic test coverage (50%+)

### Month 2 (Dec 2025) - Expansion
- Transfer type services (Outlet, Return, Stocktake)
- Admin sync dashboard with metrics
- Comprehensive testing (80%+ coverage)
- CI/CD pipeline with automated checks
- Documentation consolidation

### Month 3 (Jan 2026) - Optimization
- Performance tuning & load testing
- Advanced monitoring & alerting
- Mobile app planning
- Supplier portal design
- Q16-Q35 gap analysis completion

### Months 4-6 (Q1 2026) - Scale
- Multi-warehouse optimization
- Advanced AI features
- Third-party integrations (Xero, CRM)
- Mobile app launch
- Enterprise features

---

## 📈 Metrics & KPIs

### Technical Health
- **Test Coverage:** 20% → Target: 90%
- **API Response Time (p95):** ~200ms → Target: <200ms
- **Sync Success Rate:** ~95% → Target: >99%
- **Queue Processing:** Not measured → Target: <30s p95

### Business Impact
- **PO Creation Time:** Baseline TBD → Target: -50%
- **Receiving Time:** Baseline TBD → Target: -40%
- **Freight Costs:** Baseline TBD → Target: -15%
- **User Satisfaction:** Baseline TBD → Target: 4.5+/5.0

---

## 🚨 Risk Register

### Technical Risks
- **Lightspeed API Rate Limiting** - Probability: HIGH | Impact: MEDIUM
  - Mitigation: Exponential backoff, webhooks over polling, aggressive caching
- **Queue Worker Failures** - Probability: MEDIUM | Impact: HIGH
  - Mitigation: Supervisor process management, health checks, auto-restart
- **Data Consistency** - Probability: MEDIUM | Impact: HIGH
  - Mitigation: Idempotent operations, transaction boundaries, reconciliation jobs

### Operational Risks
- **Incomplete Training** - Probability: HIGH | Impact: MEDIUM
  - Mitigation: Video tutorials, in-person sessions, ongoing support
- **Change Resistance** - Probability: MEDIUM | Impact: LOW
  - Mitigation: Stakeholder engagement, pilot program, feedback loops

---

## 📞 Contacts & Support

- **Module Owner:** Pearce Stephens (@pearcestephens)
- **Operations Lead:** @ops-lead
- **Documentation:** `/modules/consignments/docs/`
- **Issue Tracker:** GitHub Issues (pearcestephens/modules)

---

## 🔄 Update Schedule

This status document is updated:
- **Daily** during active development (current phase)
- **Weekly** during maintenance/minor work
- **After every major milestone** (O1-O13 completion)
- **Before/after production deployments**

---

**Next Review:** November 2, 2025
**Next Major Milestone:** O2-O4 Complete (Status Map + Security)
