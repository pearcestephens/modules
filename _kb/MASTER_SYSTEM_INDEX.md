# 🧠 ECIGDIS CIS - MASTER SYSTEM KNOWLEDGE BASE

**Generated:** 2025-11-05 10:30 UTC  
**System:** Intelligence Hub - Central Information System  
**Database:** 23,608 transfers | 90,940 PHP files indexed  
**Status:** 🟢 OPERATIONAL

---

## 📊 SYSTEM ARCHITECTURE OVERVIEW

### **Company Information**
- **Legal Entity:** Ecigdis Limited
- **Trading As:** The Vape Shed
- **Industry:** Vape Retail & Wholesale
- **Locations:** 17 stores across New Zealand
- **Founded:** 2015
- **Core Mission:** Quality vaping products with expert customer service

### **Technology Stack**
- **Backend:** PHP 8.1.33
- **Database:** MySQL/MariaDB (23,608 transfers, 100+ tables)
- **POS System:** Vend/Lightspeed Retail
- **Accounting:** Xero
- **Hosting:** Cloudways (Nginx/PHP-FPM)
- **Frontend:** Bootstrap 5 + Vanilla JS
- **Architecture:** MVC with Service Layer

---

## 🏗️ MODULE ARCHITECTURE

### **1. CONSIGNMENTS MODULE** (Primary - Recently Refactored)

**Location:** `/public_html/modules/consignments/`

**Purpose:** Complete transfer management system for stock movements, purchase orders, supplier consignments

**Key Components:**

#### **API Layer** (Refactored Phase 2 - Complete)
- `lib/TransferManagerAPI.php` (600 lines - reduced from 834)
- `lib/ConsignmentsAPI.php` (15KB)
- `lib/PurchaseOrdersAPI.php` (12KB)
- Single endpoint pattern with action routing
- Standardized response envelopes (success/error)

#### **Service Layer** (Phase 1 - Complete)
- `lib/Services/TransferService.php` (690 lines) - Transfer CRUD, pagination, status management
- `lib/Services/ProductService.php` (383 lines) - Product search, inventory, analytics
- `lib/Services/ConfigService.php` (412 lines) - Configuration, outlets, suppliers, types
- `lib/Services/SyncService.php` (222 lines) - Lightspeed sync state
- `lib/Services/FreightService.php` (28KB) - Freight optimization & booking
- `lib/Services/PurchaseOrderService.php` (28KB) - PO workflow management
- `lib/Services/ReceivingService.php` (22KB) - Goods receiving processes
- `lib/Services/SupplierService.php` (19KB) - Supplier management
- `lib/Services/ApprovalService.php` (24KB) - Approval workflows
- `lib/Services/AIService.php` (35KB) - AI insights & recommendations

#### **Transfer Types Supported**
1. **STOCK** - Inter-store transfers
2. **JUICE** - E-liquid transfers (special handling)
3. **PURCHASE_ORDER** - Supplier orders
4. **INTERNAL** - Internal stock movements
5. **RETURN** - Returns to supplier
6. **STOCKTAKE** - Stocktake adjustments

#### **Database Tables**
- `queue_consignments` - Main transfer records
- `queue_consignment_products` - Transfer line items
- `vend_outlets` - Store locations
- `vend_products` - Product catalog
- `vend_inventory` - Stock levels
- `ls_suppliers` - Supplier database

#### **Recent Improvements** (Last 7 days)
- ✅ Service layer extraction complete
- ✅ 25/25 tests passing with real data
- ✅ 28% code reduction in API
- ✅ MVC pattern with dependency injection
- ✅ Zero direct database queries in controllers
- ✅ PSR-12 compliant, strict typing

---

### **2. BASE MODULE** (Foundation)

**Location:** `/public_html/modules/base/`

**Purpose:** Core framework for all modules

**Key Components:**
- `lib/BaseAPI.php` (644 lines) - Template Method pattern for all APIs
- `lib/Log.php` - CIS Logger with correlation IDs
- Request lifecycle management (validate → auth → route → execute → respond)
- Response envelope standardization
- CSRF protection
- Rate limiting framework
- Authentication gates

---

### **3. BANK TRANSACTIONS MODULE**

**Purpose:** Bank transaction reconciliation and processing

---

### **4. HUMAN RESOURCES MODULE**

**Purpose:** Staff management, payroll integration

---

### **5. STAFF ACCOUNTS MODULE**

**Purpose:** Staff authentication and permissions

---

### **6. STAFF PERFORMANCE MODULE**

**Purpose:** Performance tracking and analytics

---

### **7. SMART CRON MODULE**

**Purpose:** Scheduled task management

---

### **8. FLAGGED PRODUCTS MODULE**

**Purpose:** Product quality and compliance monitoring

---

## 🔌 INTEGRATION POINTS

### **Vend/Lightspeed Retail**
- Real-time inventory sync
- Product catalog sync
- Sales data sync
- Customer data sync
- Store/outlet management
- Consignment creation API

### **Xero Accounting**
- Invoice creation
- Payment reconciliation
- Supplier bills
- Payroll integration (via Deputy)

### **GoSweetSpot Freight**
- Freight quote requests
- Booking creation
- Label generation
- Tracking updates

### **AI Services**
- Intelligence Hub integration
- OpenAI/Claude adapters
- AI insights for purchase decisions
- Automated recommendations

---

## 📁 FILE STRUCTURE CONVENTIONS

```
/modules/{module-name}/
├── lib/                    # Core classes
│   ├── {Module}API.php    # API controllers
│   └── Services/          # Business logic
├── api/                    # API endpoints
├── controllers/           # MVC controllers
├── views/                 # UI templates
├── assets/                # CSS/JS/images
├── tests/                 # PHPUnit tests
├── docs/                  # Documentation
├── _kb/                   # Knowledge base
└── bootstrap.php          # Module initialization
```

---

## 🔒 SECURITY STANDARDS

1. **Authentication:** Session-based with user_id
2. **CSRF Protection:** Token validation on all mutations
3. **SQL Injection Prevention:** PDO prepared statements only
4. **Input Validation:** Type checking, bounds checking, sanitization
5. **PII Protection:** Redacted from logs
6. **HTTPS:** Enforced with HSTS
7. **2FA:** Required for admin accounts
8. **Secrets Management:** Bitwarden vault + .env files

---

## 📊 DATABASE ARCHITECTURE

### **Core Tables**
- `queue_consignments` (23,608 records) - Transfer records
- `queue_consignment_products` - Line items
- `vend_outlets` - 17 stores
- `vend_products` - Product catalog
- `vend_inventory` - Stock levels per outlet
- `ls_suppliers` - Supplier database

### **AI Tables**
- `ai_agent_conversations` - AI conversation history
- `ai_agent_messages` - Message storage
- `ai_kb_docs` - Knowledge base documents
- `agent_conversation_clusters` - ML clustering
- `agent_importance_scores` - Message importance

### **Queue Tables**
- Queue-based architecture for async processing
- Status tracking: OPEN → SENT → DISPATCHED → RECEIVED
- Audit logging built-in

---

## 🎯 CURRENT PROJECT STATUS

### **Phase 1: Service Layer Extraction** ✅ COMPLETE
- Created 4 core services (2,296 lines)
- All business logic extracted from controllers
- Factory methods implemented
- Real database integration verified

### **Phase 1.5: Schema Mapping** ✅ COMPLETE
- Fixed 35+ schema mismatches
- 25/25 tests passing
- Complete documentation created

### **Phase 2: API Refactoring** ✅ COMPLETE
- TransferManagerAPI reduced 834 → 600 lines (28%)
- Dependency injection implemented
- 12/12 handler methods refactored
- Zero direct database queries
- 3 new service methods added

### **Phase 3: Testing & Deployment** 🔄 IN PROGRESS
- Service tests: 25/25 passing
- API syntax: validated
- Production readiness: pending final checks
- Performance benchmarking: needed
- Frontend integration: needs verification

---

## 🚀 DEPLOYMENT INFORMATION

### **Environments**
- **Production:** staff.vapeshed.co.nz
- **Hosting:** Cloudways managed servers
- **Database:** 127.0.0.1 (local MySQL)
- **PHP Version:** 8.1.33
- **Web Server:** Nginx + PHP-FPM

### **Backup Strategy**
- Daily database backups (30-day retention)
- Monthly archives (12-month retention)
- File backups included
- Quarterly restore tests

### **Deployment Process**
1. PR review required
2. CI: lint, static analysis, tests
3. Deploy to staging first
4. Smoke tests on staging
5. Production deploy (weekdays 9am-4pm NZT)

---

## 📞 KEY CONTACTS

- **Director/Owner:** Pearce Stephens (pearce.stephens@ecigdis.co.nz)
- **IT Manager:** [TBC]
- **Security Lead:** [TBC]

---

## 🔗 IMPORTANT URLS

### **Public Sites**
- https://www.vapeshed.co.nz - Main retail site
- https://www.vapingkiwi.co.nz - Secondary brand
- https://www.vapehq.co.nz - Third brand
- https://www.ecigdis.co.nz - Corporate site

### **Internal Systems**
- https://www.staff.vapeshed.co.nz - CIS Staff Portal
- https://www.gpt.ecigdis.co.nz - AI Control Panel
- https://www.wiki.vapeshed.co.nz - Internal Wiki

---

## 📚 DOCUMENTATION INDEX

### **Consignments Module**
- `ARCHITECTURE_SUMMARY.md` - Complete architecture overview
- `PHASE_1_AND_1.5_COMPLETE.md` - Service layer implementation
- `PHASE_2_COMPLETE.md` - API refactoring report
- `SCHEMA_MAPPING.md` - Database schema reference
- `README.md` - Quick start guide

### **API Documentation**
- `docs/API_ENVELOPE_STANDARDS.md` - Response format standards
- `docs/CONSIGNMENT_API_QUICKREF.md` - API quick reference
- `docs/TRANSFER_MANAGER_ENDPOINT_MAPPING.md` - Endpoint mapping

### **Knowledge Base**
- `_kb/MASTER_SYSTEM_INDEX.md` - This file
- `_kb/CONSIGNMENTS_MASTER_KNOWLEDGE_BASE.md` - Detailed KB
- `_kb/BUILD_STATUS.md` - Current build status

---

## 💡 DESIGN PATTERNS IN USE

1. **Template Method** - BaseAPI orchestrates request flow
2. **Strategy Pattern** - Action-based routing
3. **Factory Method** - Service::make() constructors
4. **Dependency Injection** - Services in constructors
5. **Repository Pattern** - Services abstract data access
6. **Single Responsibility** - One job per class
7. **MVC Pattern** - Separation of concerns
8. **Response Envelope** - Consistent API responses

---

## ⚡ PERFORMANCE TARGETS

- API Response Time: < 500ms (p95)
- Page Load (LCP): < 2.5s
- Database Queries: < 10 per page load
- Uptime: 99.9% monthly
- Error Rate: < 0.1%

---

## 🧪 TESTING STRATEGY

- **Unit Tests:** Service methods (25/25 passing)
- **Integration Tests:** Cross-service interactions
- **API Tests:** Endpoint responses
- **Smoke Tests:** Critical paths
- **Performance Tests:** Load testing with real data

---

*This knowledge base is dynamically generated and maintained by the AI system.*
*Last updated: 2025-11-05 10:30 UTC*
