# 🏗️ Consignments Module - Architecture Analysis & Refactor Plan

**Date**: 2025-11-05  
**Analysis**: MVC Separation of Concerns  
**Status**: Identified Design Pattern Issues

---

## 📊 Current State Assessment

### ✅ WORKING APIs

#### 1. ConsignmentsAPI.php (500 lines)
- **Location**: `/lib/ConsignmentsAPI.php`
- **Entry Point**: `/api.php`
- **Extends**: BaseAPI ✅
- **Purpose**: Consignment CRUD operations
- **Actions**: get_recent, get_consignment, create_consignment, add_item, update_status, search_consignments, get_stats, update_item_qty
- **Service Layer**: Uses ConsignmentService ✅
- **Status**: **Fixed visibility bug** ✅
- **Design**: GOOD - Proper MVC separation

#### 2. PurchaseOrdersAPI.php (419 lines)
- **Location**: `/lib/PurchaseOrdersAPI.php`
- **Entry Point**: `/purchase-orders/api.php` (assumed)
- **Extends**: BaseAPI ✅
- **Purpose**: Purchase order management with approval workflows
- **Actions**: list, get, create, update, approve, delete
- **Service Layer**: Uses PurchaseOrderService ✅
- **Status**: Production-ready
- **Design**: GOOD - Proper MVC separation

#### 3. TransferManagerAPI.php (834 lines)
- **Location**: `/lib/TransferManagerAPI.php`
- **Entry Point**: `/TransferManager/backend-v2.php`
- **Extends**: BaseAPI ✅
- **Purpose**: Transfer management for ALL operations
- **Actions**: init, toggle_sync, verify_sync, list_transfers, get_transfer_detail, search_products, create_transfer, add_transfer_item, update_transfer_item, remove_transfer_item, mark_sent, mark_receiving, receive_all, cancel_transfer, add_note, recreate_transfer, revert_to_open, revert_to_sent, revert_to_receiving
- **Service Layer**: **NONE - Direct database access** ❌
- **Status**: **Violates MVC** ⚠️
- **Design**: **POOR - God Object Anti-Pattern**

---

## 🚨 Architecture Problems Identified

### Problem #1: TransferManagerAPI Violates Single Responsibility Principle

**Current**: TransferManagerAPI does EVERYTHING
- Transfer CRUD
- Product search
- Outlet management
- Supplier management
- Sync management
- Lightspeed integration
- Direct database queries (no service layer)

**Impact**:
- 834 lines of tightly-coupled code
- Hard to test
- Hard to reuse logic
- Violates MVC pattern
- No separation of concerns

### Problem #2: No Service Layer for Transfers

**Current**: TransferManagerAPI directly accesses database via mysqli
```php
$stmt = $this->db->prepare("SELECT * FROM transfers WHERE id = ?");
```

**Should be**:
```php
$transfer = $this->transferService->getById($id);
```

### Problem #3: Mixed Responsibilities

TransferManagerAPI handles:
1. **Transfers** (core responsibility) ✅
2. **Products** (should be ProductsAPI) ❌
3. **Configuration** (should be ConfigAPI) ❌
4. **Sync** (should be SyncAPI) ❌

---

## 🎯 Proposed Refactor - Proper MVC Architecture

### Step 1: Create Service Layer

#### TransferService.php
```php
<?php
declare(strict_types=1);

namespace CIS\Consignments\Services;

use PDO;

class TransferService {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    public static function make(): self {
        return new self(db_ro()); // Or db connection
    }
    
    // READ operations
    public function getById(int $id): ?array { /*...*/ }
    public function list(array $filters, int $page, int $limit): array { /*...*/ }
    public function search(string $query): array { /*...*/ }
    public function getStats(): array { /*...*/ }
    
    // WRITE operations (requires RW connection)
    public function create(array $data): int { /*...*/ }
    public function addItem(int $transferId, array $itemData): int { /*...*/ }
    public function updateStatus(int $id, string $status): bool { /*...*/ }
    public function delete(int $id): bool { /*...*/ }
}
```

#### ProductService.php (for product search)
```php
<?php
declare(strict_types=1);

namespace CIS\Consignments\Services;

class ProductService {
    public function search(string $query, int $limit = 50): array { /*...*/ }
    public function getById(int $id): ?array { /*...*/ }
    public function getByIds(array $ids): array { /*...*/ }
}
```

#### ConfigService.php (for outlets/suppliers)
```php
<?php
declare(strict_types=1);

namespace CIS\Consignments\Services;

class ConfigService {
    public function getOutlets(): array { /*...*/ }
    public function getSuppliers(): array { /*...*/ }
    public function getTransferTypes(): array { /*...*/ }
}
```

#### SyncService.php (for Lightspeed sync)
```php
<?php
declare(strict_types=1);

namespace CIS\Consignments\Services;

class SyncService {
    public function isEnabled(): bool { /*...*/ }
    public function enable(): void { /*...*/ }
    public function disable(): void { /*...*/ }
    public function getStatus(): array { /*...*/ }
}
```

### Step 2: Refactor TransferManagerAPI to use Services

**Before** (834 lines):
```php
class TransferManagerAPI extends BaseAPI {
    private mysqli $db; // Direct DB access ❌
    
    protected function handleSearchProducts(array $data): array {
        $stmt = $this->db->prepare("SELECT * FROM products..."); // Direct query ❌
    }
}
```

**After** (~300 lines):
```php
class TransferManagerAPI extends BaseAPI {
    private TransferService $transferService; // Service layer ✅
    private ProductService $productService;   // Separate concern ✅
    private ConfigService $configService;     // Separate concern ✅
    private SyncService $syncService;         // Separate concern ✅
    
    protected function handleSearchProducts(array $data): array {
        $query = $this->validateString($data, 'query');
        $products = $this->productService->search($query); // Clean ✅
        
        return $this->success('Products found', [
            'products' => $products,
            'count' => count($products)
        ]);
    }
}
```

### Step 3: Extract Separate APIs

#### ProductsAPI.php (NEW)
```php
class ProductsAPI extends BaseAPI {
    private ProductService $service;
    
    protected function handleSearch(array $data): array { /*...*/ }
    protected function handleGet(array $data): array { /*...*/ }
    protected function handleGetBatch(array $data): array { /*...*/ }
}
```

#### ConfigAPI.php (NEW)
```php
class ConfigAPI extends BaseAPI {
    private ConfigService $service;
    
    protected function handleGetOutlets(array $data): array { /*...*/ }
    protected function handleGetSuppliers(array $data): array { /*...*/ }
    protected function handleGetTransferTypes(array $data): array { /*...*/ }
}
```

#### SyncAPI.php (NEW)
```php
class SyncAPI extends BaseAPI {
    private SyncService $service;
    
    protected function handleGetStatus(array $data): array { /*...*/ }
    protected function handleEnable(array $data): array { /*...*/ }
    protected function handleDisable(array $data): array { /*...*/ }
}
```

---

## 📁 Proposed File Structure

```
/modules/consignments/
├── lib/
│   ├── Services/          # NEW - Business logic layer
│   │   ├── TransferService.php
│   │   ├── ProductService.php
│   │   ├── ConfigService.php
│   │   └── SyncService.php
│   │
│   ├── API/               # Reorganized - API layer
│   │   ├── ConsignmentsAPI.php      (existing ✅)
│   │   ├── PurchaseOrdersAPI.php    (existing ✅)
│   │   ├── TransferManagerAPI.php   (refactored)
│   │   ├── ProductsAPI.php          (NEW)
│   │   ├── ConfigAPI.php            (NEW)
│   │   └── SyncAPI.php              (NEW)
│   │
│   └── Models/            # Optional - Data models
│       ├── Transfer.php
│       ├── TransferItem.php
│       └── Product.php
│
├── api.php                (ConsignmentsAPI entry)
├── purchase-orders/
│   └── api.php            (PurchaseOrdersAPI entry)
├── TransferManager/
│   └── backend-v2.php     (TransferManagerAPI entry)
├── products/
│   └── api.php            (ProductsAPI entry - NEW)
├── config/
│   └── api.php            (ConfigAPI entry - NEW)
└── sync/
    └── api.php            (SyncAPI entry - NEW)
```

---

## 🚀 Implementation Plan

### Phase 1: Extract Service Layer (Week 1)
1. Create `lib/Services/TransferService.php`
2. Move all transfer database logic from TransferManagerAPI
3. Create `lib/Services/ProductService.php`
4. Move product search logic
5. Create `lib/Services/ConfigService.php`
6. Move outlet/supplier logic
7. Create `lib/Services/SyncService.php`
8. Move sync logic

### Phase 2: Refactor TransferManagerAPI (Week 2)
1. Inject services into constructor
2. Replace direct DB calls with service calls
3. Reduce from 834 → ~300 lines
4. Add PHPUnit tests for each service

### Phase 3: Extract New APIs (Week 3)
1. Create ProductsAPI.php
2. Create ConfigAPI.php  
3. Create SyncAPI.php
4. Update frontend to use new endpoints

### Phase 4: Testing & Migration (Week 4)
1. Run comprehensive test suite
2. Parallel run (old + new endpoints)
3. Monitor for errors
4. Deprecate old combined endpoint

---

## ✅ Benefits of Refactor

### Code Quality
- **Single Responsibility** - Each class does ONE thing
- **DRY** - Service layer reusable across APIs, CLI, jobs
- **Testable** - Services can be unit tested in isolation
- **Maintainable** - Smaller, focused classes

### Performance
- **Cacheable** - Service methods can cache results
- **Optimizable** - Database queries in one place
- **Scalable** - Services can be moved to microservices later

### Developer Experience
- **Clear** - Know exactly where to find code
- **Predictable** - Consistent patterns across all APIs
- **Documented** - Each service has clear purpose

---

## 🎓 Design Pattern Comparison

### BEFORE (Anti-Pattern: God Object)
```
TransferManagerAPI (834 lines)
├── Transfers (core)
├── Products (should be separate)
├── Config (should be separate)
├── Sync (should be separate)
└── Direct DB access (should be service layer)
```

### AFTER (Proper MVC)
```
Controller Layer:
├── TransferManagerAPI (300 lines)
├── ProductsAPI (150 lines)
├── ConfigAPI (100 lines)
└── SyncAPI (100 lines)

Service Layer:
├── TransferService (business logic)
├── ProductService (business logic)
├── ConfigService (business logic)
└── SyncService (business logic)

Model Layer:
└── Database (via PDO/mysqli)
```

---

## 🔗 Related Documentation

- Implementation Guide: `/docs/IMPLEMENTATION_GUIDE.md`
- API Standards: `/docs/API_ENVELOPE_STANDARDS.md`
- BASE Module: `/modules/base/README.md`
- Testing Guide: `/TEST_RESULTS_FINAL.md`

---

## 📝 Next Steps

1. ✅ Fix ConsignmentsAPI visibility bug (DONE)
2. ⏳ Create TransferService.php
3. ⏳ Refactor TransferManagerAPI to use service
4. ⏳ Extract ProductsAPI, ConfigAPI, SyncAPI
5. ⏳ Add comprehensive tests
6. ⏳ Deploy and monitor

---

**Conclusion**: Current TransferManagerAPI violates MVC and Single Responsibility Principle. Refactoring to proper service layer + separate APIs will improve testability, maintainability, and scalability.

**Recommendation**: Proceed with Phase 1 (Service Layer Extraction) immediately.
