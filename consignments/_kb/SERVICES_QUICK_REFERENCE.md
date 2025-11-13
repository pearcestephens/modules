# Consignments Services - Quick Reference Card
**Updated:** 2025-11-13  
**Location:** `/assets/services/consignments/`

---

## 📍 NEW SERVICE LOCATIONS

```
/assets/services/consignments/
├── core/          - Main business logic
├── ai/            - AI features
├── integration/   - External APIs
└── support/       - Helper services
```

---

## 🔧 HOW TO USE

### Import Services (New Way)
```php
// ✅ NEW - Use these imports
use CIS\Services\Consignments\Core\ConsignmentService;
use CIS\Services\Consignments\Core\TransferService;
use CIS\Services\Consignments\Core\PurchaseOrderService;
use CIS\Services\Consignments\Integration\FreightService;
use CIS\Services\Consignments\AI\AIConsignmentAssistant;
use CIS\Services\Consignments\Support\ProductService;

// ❌ OLD - Don't use these anymore
use CIS\Consignments\Services\TransferService;  // DEPRECATED
use Consignments\Services\ConsignmentService;    // DEPRECATED
```

### Instantiate Services
```php
// Autoloader handles it automatically
$consignmentService = new ConsignmentService($db);
$transferService = new TransferService($db);
$freightService = new FreightService();
```

---

## 📋 SERVICE DIRECTORY

### Core Services (Business Logic)
| Service | Class | Purpose |
|---------|-------|---------|
| **ConsignmentService** | `CIS\Services\Consignments\Core\ConsignmentService` | Main consignment operations |
| **TransferService** | `CIS\Services\Consignments\Core\TransferService` | Stock transfers |
| **PurchaseOrderService** | `CIS\Services\Consignments\Core\PurchaseOrderService` | Purchase orders |
| **ReceivingService** | `CIS\Services\Consignments\Core\ReceivingService` | Goods receiving |
| **ReturnToSupplierService** | `CIS\Services\Consignments\Core\ReturnToSupplierService` | Supplier returns |

### AI Services
| Service | Class | Purpose |
|---------|-------|---------|
| **AIConsignmentAssistant** | `CIS\Services\Consignments\AI\AIConsignmentAssistant` | AI-powered assistant |
| **AIService** | `CIS\Services\Consignments\AI\AIService` | AI analysis engine |
| **UniversalAIRouter** | `CIS\Services\Consignments\AI\UniversalAIRouter` | AI request routing |

### Integration Services (External APIs)
| Service | Class | Purpose |
|---------|-------|---------|
| **FreightService** | `CIS\Services\Consignments\Integration\FreightService` | Freight/shipping APIs |
| **SupplierService** | `CIS\Services\Consignments\Integration\SupplierService` | Supplier communications |
| **VendSyncService** | `CIS\Services\Consignments\Integration\VendSyncService` | Vend API sync |
| **LightspeedSync** | `CIS\Services\Consignments\Integration\LightspeedSync` | Lightspeed sync |

### Support Services (Helpers)
| Service | Class | Purpose |
|---------|-------|---------|
| **ConfigService** | `CIS\Services\Consignments\Support\ConfigService` | Configuration |
| **ProductService** | `CIS\Services\Consignments\Support\ProductService` | Product lookups |
| **ApprovalService** | `CIS\Services\Consignments\Support\ApprovalService` | Approval workflows |
| **EmailService** | `CIS\Services\Consignments\Support\EmailService` | Email notifications |
| **NotificationService** | `CIS\Services\Consignments\Support\NotificationService` | Multi-channel notifications |
| **TransferReviewService** | `CIS\Services\Consignments\Support\TransferReviewService` | Transfer review |

---

## 🚀 QUICK START EXAMPLES

### Example 1: Create a Transfer
```php
use CIS\Services\Consignments\Core\TransferService;

$transferService = new TransferService($db);
$result = $transferService->createTransfer([
    'from_outlet_id' => 'OUTLET001',
    'to_outlet_id' => 'OUTLET002',
    'products' => [
        ['product_id' => 'PROD001', 'quantity' => 10]
    ]
]);
```

### Example 2: Get Freight Quote
```php
use CIS\Services\Consignments\Integration\FreightService;

$freightService = new FreightService();
$quote = $freightService->getQuote([
    'from' => 'Auckland',
    'to' => 'Wellington',
    'weight' => 15.5
]);
```

### Example 3: Use AI Assistant
```php
use CIS\Services\Consignments\AI\AIConsignmentAssistant;

$aiAssistant = new AIConsignmentAssistant();
$suggestion = $aiAssistant->suggestOptimalRoute($transferData);
```

---

## 🔍 TROUBLESHOOTING

### "Class not found" Error
```
✅ Solution: Check your import statement
   Use: CIS\Services\Consignments\Core\TransferService
   Not: CIS\Consignments\Services\TransferService
```

### "Autoloader not working"
```
✅ Solution: Ensure bootstrap is loaded
   require_once __DIR__ . '/bootstrap.php';
```

### "Service dependencies missing"
```
✅ Solution: Some services need Database, Config, etc.
   Pass required dependencies to constructor
```

---

## 📚 RELATED DOCS

- **Full Guide:** `CONSOLIDATION_FINAL_SUMMARY.md`
- **Original Plan:** `CONSOLIDATION_PLAN_REVISED.md`
- **Gap Analysis:** `GAP_ANALYSIS_COMPREHENSIVE.md`

---

## 💡 TIPS

1. **Always use full namespace** in imports
2. **Autoloader handles loading** - no manual requires needed
3. **Services are in `/assets/services/`** - company-wide access
4. **Check service constructor** for required dependencies

---

**Last Updated:** 2025-11-13  
**Status:** ✅ Production Ready  
**Questions?** Check docs or contact dev team
