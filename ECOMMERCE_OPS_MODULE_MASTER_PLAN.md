# 🚀 E-COMMERCE OPERATIONS MODULE - MASTER BUILD PLAN

> **Project:** Complete rebuild of legacy e-commerce management pages
> **Target:** Modern MVC architecture with smart fulfillment & freight optimization
> **Timeline:** 4-6 weeks (4 phases)
> **Date:** November 5, 2025
> **Updated:** November 5, 2025 - Added Burst SMS, R18 verification, per-outlet courier support

---

## 🎯 **MISSION:**

Replace 12 legacy root-level PHP pages with a unified, modern E-Commerce Operations Module featuring:
- ✅ Multi-store fulfillment (20 stores)
- ✅ Freight optimization (OUT OF THE BOX: GSS + NZ Post with per-outlet credentials)
- ✅ Smart packing algorithms
- ✅ Cost savings tracking
- ✅ Advanced analytics
- ✅ **Burst SMS notifications** (order updates, delivery alerts, R18 reminders)
- ✅ **R18 Age Verification** (fraud detection, customer blocking, compliance)
- ✅ **Per-Outlet Courier Authentication** (gss_token, nz_post_api_key per store)

**⚠️ ARCHITECTURE CLARIFICATION:**
- **Customer-Facing:** Happens on retail websites (vapeshed.co.nz, ecigdis.co.nz, vapingkiwi.co.nz, vapehq.co.nz)
- **Staff Management:** ALL controlled through CIS (staff.vapeshed.co.nz)
- **Integration:** CIS provides APIs that retail websites consume
- **Control:** CIS is the single source of truth for orders, customers, verification, fraud blocking

```
┌────────────────────────────────────────────────────────────────────┐
│                    SYSTEM ARCHITECTURE                             │
└────────────────────────────────────────────────────────────────────┘

 CUSTOMER SIDE                        STAFF SIDE
 (Retail Websites)                    (CIS Staff Portal)
 ─────────────────                    ──────────────────

┌─────────────────┐                  ┌──────────────────┐
│ vapeshed.co.nz  │                  │ staff.vapeshed   │
│ ecigdis.co.nz   │◄────────────────►│    .co.nz        │
│ vapingkiwi.co   │   REST APIs      │                  │
│ vapehq.co.nz    │                  │ E-Commerce Ops   │
└─────────────────┘                  │ Module           │
                                     └──────────────────┘
Customer Actions:                    Staff Actions:
• Browse products                    • Review orders
• Add to cart                        • Verify IDs
• Checkout (no age gate)             • Assign stores
• Upload ID photo                    • Book freight
• Track order                        • Flag fraud
                                     • Process refunds
        │                                    │
        └────────────► CIS APIS ◄───────────┘
                         │
                    ┌────▼─────┐
                    │ Database │
                    │ • Orders │
                    │ • Fraud  │
                    │ • Vend   │
                    └──────────┘
```

---## 📋 **LEGACY PAGES TO REPLACE:**

| Old Page | New Location | Status |
|----------|--------------|--------|
| `customers-overview.php` | `/modules/ecommerce-ops/views/customers/overview.php` | ⏳ Pending |
| `view-customer.php` | `/modules/ecommerce-ops/views/customers/view.php` | ⏳ Pending |
| `edit-website-product.php` | `/modules/ecommerce-ops/views/products/edit.php` | ⏳ Pending |
| `edit-create-website-content.php` | `/modules/ecommerce-ops/views/content/editor.php` | ⏳ Pending |
| `view-web-order.php` | `/modules/ecommerce-ops/views/orders/view.php` | ⏳ Pending |
| `view-web-order-outlet.php` | `/modules/ecommerce-ops/views/orders/by-outlet.php` | ⏳ Pending |
| `wholesale-accounts.php` | `/modules/ecommerce-ops/views/wholesale/accounts.php` | ⏳ Pending |
| `website-reviews.php` | `/modules/ecommerce-ops/views/reviews/manage.php` | ⏳ Pending |
| `website-ip-logs.php` | `/modules/ecommerce-ops/views/logs/ip-logs.php` | ⏳ Pending |
| `website-email-logs.php` | `/modules/ecommerce-ops/views/logs/email-logs.php` | ⏳ Pending |
| `website-addon-templates.php` | `/modules/ecommerce-ops/views/templates/addons.php` | ⏳ Pending |
| `web-order-performance.php` | `/modules/ecommerce-ops/views/analytics/performance.php` | ⏳ Pending |

---

## 🏗️ **MODULE STRUCTURE:**

```
/modules/ecommerce-ops/
├── bootstrap.php                      # Module initialization
├── index.php                          # Dashboard / Router
├── .env.example                       # Configuration template
│
├── lib/                               # Service Classes (Business Logic)
│   ├── CustomerService.php            # Customer CRUD + analytics
│   ├── ProductService.php             # Product management + Vend sync
│   ├── OrderService.php               # Order processing + status
│   ├── WholesaleService.php           # B2B accounts (ecigdis.co.nz)
│   ├── FulfillmentEngine.php          ⭐ NEW - Smart store selection
│   ├── FreightOptimizer.php           ⭐ NEW - Integrates with existing FreightService
│   ├── PackingAlgorithm.php           ⭐ NEW - Box optimization
│   ├── InventoryService.php           # Multi-store inventory (20 stores)
│   ├── NotificationService.php        ⭐ NEW - Burst SMS + Email notifications
│   ├── AgeVerificationService.php     ⭐ NEW - R18 compliance + fraud detection
│   ├── ReviewService.php              # Review management
│   ├── LoggingService.php             # IP/Email logs
│   ├── TemplateService.php            # Addon templates
│   └── AnalyticsService.php           # Performance tracking
│
├── controllers/                       # Request Handlers
│   ├── CustomerController.php
│   ├── OrderController.php
│   ├── FulfillmentController.php      ⭐ NEW
│   └── AnalyticsController.php
│
├── views/                             # UI Pages
│   ├── dashboard.php                  # Main overview
│   ├── customers/
│   │   ├── overview.php               # Customer list with filters
│   │   └── view.php                   # Customer details
│   ├── products/
│   │   ├── list.php                   # Product catalog
│   │   └── edit.php                   # Product editor
│   ├── orders/
│   │   ├── list.php                   # All orders
│   │   ├── view.php                   # Order details
│   │   ├── by-outlet.php              # Orders filtered by store
│   │   └── fulfillment.php            ⭐ NEW - Smart fulfillment UI
│   ├── wholesale/
│   │   └── accounts.php               # B2B customer management
│   ├── content/
│   │   └── editor.php                 # CMS content editor
│   ├── reviews/
│   │   └── manage.php                 # Review moderation
│   ├── logs/
│   │   ├── ip-logs.php                # IP address logs
│   │   └── email-logs.php             # Email history
│   ├── templates/
│   │   └── addons.php                 # Addon template manager
│   └── analytics/
│       ├── performance.php            # Order performance
│       ├── fulfillment.php            ⭐ NEW - Store performance
│       └── freight-costs.php          ⭐ NEW - Shipping cost analysis
│
├── api/                               # RESTful API Endpoints
│   ├── customers/
│   │   ├── list.php                   # GET - Customer list
│   │   ├── get.php                    # GET - Single customer
│   │   └── update.php                 # POST - Update customer
│   ├── orders/
│   │   ├── create.php                 # POST - Create order (from retail website)
│   │   ├── update-status.php          # POST - Update order status
│   │   └── suggest-fulfillment.php    ⭐ NEW - AI store selection
│   ├── fulfillment/
│   │   ├── check-inventory.php        # GET - Check 20 stores
│   │   ├── select-store.php           ⭐ NEW - Optimal store
│   │   ├── calculate-shipping.php     ⭐ NEW - Best courier
│   │   └── pack-order.php             ⭐ NEW - Box optimization
│   ├── freight/
│   │   ├── get-rates.php              # POST - Compare couriers
│   │   ├── book-pickup.php            # POST - Schedule courier
│   │   └── track-shipment.php         # GET - Real-time tracking
│   └── age-verification/              ⭐ NEW - R18 Compliance (for retail websites)
│       ├── check-customer.php         # POST - Check if customer blocked (called by website at checkout)
│       ├── create-verification.php    # POST - Start verification process (called when order created)
│       ├── upload-id.php              # POST - Receive ID photo from website upload portal
│       ├── get-status.php             # GET - Check verification status
│       └── sync-blacklist.php         # GET - Download fraud blacklist (website caches this)
│
├── database/
│   └── migrations/
│       ├── 001_ecommerce_base_tables.sql
│       ├── 002_fulfillment_tables.sql     ⭐ NEW
│       ├── 003_freight_tracking.sql       ⭐ NEW
│       └── 004_analytics_tables.sql       ⭐ NEW
│
├── css/
│   └── ecommerce-ops.css              # Module-specific styles
│
├── js/
│   ├── fulfillment.js                 ⭐ NEW - Store selection UI
│   ├── freight.js                     # Freight quote UI
│   └── analytics.js                   # Charts/dashboards
│
└── docs/
    ├── README.md                      # Module documentation
    ├── API_REFERENCE.md               # API endpoint docs
    └── FULFILLMENT_GUIDE.md           ⭐ NEW - How it works
```

---

## 🔗 **EXISTING INTEGRATIONS:**

### **1. Freight Service (ALREADY EXISTS)**
**Location:** `/modules/consignments/lib/Services/FreightService.php` (866 lines)

**What it does:**
- ✅ Weight/volume calculations
- ✅ GoSweetSpot (GSS) integration
- ✅ NZ Post integration
- ✅ Carrier selection
- ✅ Label generation
- ✅ Tracking

**Files:**
- `/assets/functions/gss.php` - GoSweetSpot API wrapper
- `/assets/services/core/freight/` - Complete freight engine
  - `FreightEngine.php` (78KB)
  - `WeightCalculator.php`
  - `VolumeCalculator.php`
  - `ContainerSelector.php`
  - `FreightGateway.php`
  - `FreightQuoter.php`
  - `LabelManager.php`
- `/assets/services/core/freight/gss/GSSClient.php` (32KB) - Enterprise GSS client
- `/assets/services/core/freight/FreightLibrary/` - Full freight library

**Our module will:**
- ✅ Use existing FreightService
- ✅ Extend it with e-commerce-specific logic
- ✅ Add real-time rate comparison UI
- ✅ Track cost savings

### **2. Vend/Lightspeed API (ALREADY EXISTS)**
**Location:** `/assets/services/VendAPI.php` (879 lines)

**Coverage:**
- ✅ Products, Customers, Orders, Inventory
- ✅ 17 store outlets
- ✅ Sales data
- ✅ Real-time sync

### **3. Database Tables (ALREADY EXISTS)**
- `vend_products` - Product catalog
- `vend_customers` - Customer data
- `vend_sales` - Order history
- `vend_outlets` - 20 store locations (with per-outlet courier credentials)
  - `gss_token` - GoSweetSpot API token (per outlet)
  - `nz_post_api_key` + `nz_post_subscription_key` - NZ Post authentication (per outlet)
- `vend_inventory` - Stock levels per store

### **4. Burst SMS Service (ALREADY EXISTS)**
**Purpose:** Customer notifications for order updates, delivery alerts, age verification

**Integration:**
- Burst SMS API (formerly Burst SMS, now just "Burst")
- Send order confirmations, dispatch notifications, delivery alerts
- R18 age verification reminders
- Delivery driver coordination

**Use Cases:**
- "Your order #12345 has been dispatched from Hamilton Grey St"
- "Your VapeShed order is out for delivery today"
- "Age verification required - please have ID ready"
- "Your order is ready for pickup at [Store Name]"

### **5. R18 Age Verification System (ALREADY EXISTS)**
**Location:** Throughout order processing system + **PUBLIC WEBSITE integration**

**⚠️ ACTUAL WORKFLOW:** NO age gate on website - verification happens **AFTER PAYMENT**

**How It Works:**
1. **Customer orders on website** → Payment processed (no age verification yet)
2. **Order created in CIS** → Automatic verification process starts
3. **CIS checks fraud database** → Match against known underage/fraud customers
4. **If NEW customer OR low-risk** → Automated email + SMS sent requesting ID
5. **Customer uploads photo** → Passport or Driver's License (automated system)
6. **System verifies ID** → AI/manual review of uploaded photo
7. **If VERIFIED** → Order dispatched
8. **If FAILED or NO RESPONSE** → Order held, refund offered
9. **If fraud detected** → Flagged in CIS + Vend, future orders auto-blocked

**CIS Features (automated + manual review):**
- ✅ Automated ID request emails + SMS (Burst)
- ✅ Customer photo upload portal (passport/license)
- ✅ AI/manual ID verification
- ✅ Automatic matching of underage customers across orders
- ✅ Vend customer profile fraud flags
- ✅ Order blocking for flagged accounts (hold order, offer refund)
- ✅ Age verification tracking (who verified, when, method)
- ✅ Staff override/remove blocks with audit trail

**Functions:**
- `markUnderage()` - Flag order as underage
- `markCustomerUnderageFraudVendNotes()` - Add fraud flag to Vend profile
- `removeFraudUnderageFromVendCustomerProfile()` - Remove block
- `doesOrderMatchExistingUnderage()` - Check if customer matches known underage accounts
- `updateCustomerAgeVerified()` - Mark customer as age verified (after ID upload)
- `updateAgeVerificationEmailSent()` - Track verification request emails/SMS

**Integration Flow:**
1. **Customer completes checkout on website** → Payment processed
2. **Order created in CIS** → Check against fraud database
3. **If HIGH RISK** → Order auto-held, staff review
4. **If NEW/UNKNOWN** → Automated email + SMS: "Please upload photo ID to verify age (R18)"
5. **Customer uploads ID** → System queues for verification
6. **Verified** → Order moves to fulfillment, SMS: "Order verified, dispatching today"
7. **Failed/Fake ID** → Order cancelled, refund processed, customer flagged
8. **Future orders from flagged customer** → Auto-held for manual review

---

## ⭐ **NEW FEATURES:**

### **A. SMART FULFILLMENT ENGINE**
**File:** `/modules/ecommerce-ops/lib/FulfillmentEngine.php`

**Algorithm:**
```php
FulfillmentEngine::suggestStore($order)
```

**Considers (weighted scoring):**
1. **Customer Location** (40%) - Closest store = lower shipping cost
2. **Stock Availability** (30%) - Items in stock?
3. **Store Capacity** (15%) - Current order queue
4. **Historical Performance** (10%) - Fastest stores
5. **Courier Zones** (5%) - Best rates for destination

**Returns:**
```json
{
  "recommended_store": {
    "id": 5,
    "name": "Hamilton Grey St",
    "distance_km": 2.5,
    "stock_available": true,
    "current_queue": 3,
    "avg_pack_time_min": 8,
    "courier_zone": "Hamilton Urban",
    "estimated_cost": 5.50,
    "estimated_delivery": "2025-11-07"
  },
  "score": 92,
  "reason": "Closest store with full stock and low queue",
  "alternatives": [
    {
      "store": "Hamilton Killarney",
      "score": 78,
      "cost": 6.00,
      "reason": "Also close but slightly more expensive"
    }
  ]
}
```

### **B. FREIGHT OPTIMIZER**
**File:** `/modules/ecommerce-ops/lib/FreightOptimizer.php`

**Wraps existing FreightService + adds:**
- Real-time rate comparison across ALL couriers
- Automatic best-rate selection
- Cost savings tracking
- Performance analytics

**OUT OF THE BOX COURIER SUPPORT:**
- ✅ **GoSweetSpot (GSS)** - Uses per-outlet `gss_token` from `vend_outlets` table
- ✅ **NZ Post** - Uses per-outlet `nz_post_api_key` + `nz_post_subscription_key`
- ⏳ **CourierPost** - NEW integration (optional)
- ⏳ **Aramex** - NEW integration (optional)

**Per-Outlet Authentication:**
Each of the 20 stores has their own courier credentials stored in `vend_outlets`:
- Some outlets have `gss_token` (GoSweetSpot)
- Some outlets have `nz_post_api_key` + `nz_post_subscription_key`
- Some outlets have NONE (pickup only or use default HQ credentials)

**API Integration:**
```php
FreightOptimizer::getBestRate($order, $fromStore)
```

**Queries (checks outlet credentials first):**
1. **GoSweetSpot (GSS)** - IF outlet has `gss_token`
2. **NZ Post** - IF outlet has NZ Post keys
3. CourierPost - If enabled
4. Aramex - If enabled

**Returns:**
```json
{
  "recommended": {
    "courier": "NZ Post",
    "service": "ParcelPost",
    "cost": 5.50,
    "estimated_days": 1,
    "pickup_cutoff": "16:00"
  },
  "alternatives": [
    {"courier": "GSS", "cost": 6.50, "days": 1},
    {"courier": "CourierPost", "cost": 7.00, "days": 1}
  ],
  "savings_vs_avg": 1.50,
  "savings_percent": 21
}
```

### **C. PACKING ALGORITHM**
**File:** `/modules/ecommerce-ops/lib/PackingAlgorithm.php`

**Uses existing ContainerSelector + adds:**
- Multi-item optimization
- Fragile item handling
- Packing material calculation
- Time estimation

**API:**
```php
PackingAlgorithm::optimizeBox($orderItems)
```

**Returns:**
```json
{
  "recommended_box": "Medium (30x25x20cm)",
  "estimated_weight": 2.5,
  "items_fit": true,
  "packing_materials": [
    {"item": "Bubble wrap", "meters": 2},
    {"item": "Void fill", "grams": 50}
  ],
  "packing_time_estimate": 5,
  "fragile_items": 1,
  "special_instructions": "Wrap vape juice bottles separately"
}
```

### **D. MULTI-STORE INVENTORY**
**File:** `/modules/ecommerce-ops/lib/InventoryService.php`

**Extends Vend inventory with:**
- Real-time stock check across all 20 stores
- Smart stock splitting (if one store doesn't have full order)
- Stock transfer suggestions

**API:**
```php
InventoryService::checkAvailability($productId, $quantity)
```

**Returns:**
```json
{
  "product_id": "abc123",
  "quantity_requested": 10,
  "total_available": 15,
  "can_fulfill": true,
  "stores_with_stock": [
    {"store": "Hamilton Grey St", "qty": 5, "distance_km": 2},
    {"store": "Hamilton Killarney", "qty": 3, "distance_km": 5},
    {"store": "Papakura", "qty": 7, "distance_km": 120}
  ],
  "fulfillment_strategy": "single_store",
  "recommended_store": "Hamilton Grey St + transfer 5 from Papakura",
  "alternative": "Split shipment (2 boxes)"
}
```

### **E. NOTIFICATION SERVICE (Burst SMS + Email)**
**File:** `/modules/ecommerce-ops/lib/NotificationService.php`

**Purpose:** Customer communication for order lifecycle events

**Channels:**
1. **Burst SMS** - Text message notifications
2. **Email** - HTML formatted emails (via existing `vapeshedSendEmail()`)

**API:**
```php
NotificationService::sendOrderNotification($order, $event, $channels = ['sms', 'email'])
```

**Events:**
- `order.created` - Order confirmation
- `order.dispatched` - "Your order has been shipped from [Store]"
- `order.out_for_delivery` - "Your order is out for delivery today"
- `order.ready_for_pickup` - "Your order is ready at [Store Name]"
- `order.age_verification_required` - "Please have ID ready (R18)"
- `order.delayed` - Delay notifications
- `order.cancelled` - Cancellation notice

**SMS Templates:**
```
"Your VapeShed order #12345 has been dispatched from Hamilton Grey St.
Track: https://vapeshed.co.nz/track/ABC123"

"Your order is out for delivery today! R18 ID required.
Questions? 0800 VAPE SHED"

"Your order #12345 is ready for pickup at Hamilton Grey St.
Please bring photo ID (R18 verification required)."
```

**Configuration:**
- Burst SMS API key in `.env`
- Per-event opt-in/opt-out
- Customer communication preferences
- Staff notification overrides

### **F. AGE VERIFICATION SERVICE (R18 Compliance)**
**File:** `/modules/ecommerce-ops/lib/AgeVerificationService.php`

**Purpose:** NZ vaping law compliance - prevent sales to under 18s

**⚠️ ARCHITECTURE:**
- **Customer Experience:** Happens on retail websites (vapeshed.co.nz, ecigdis.co.nz, etc.)
- **Management & Control:** ALL managed through CIS (staff.vapeshed.co.nz)
- **Integration:** CIS provides APIs → Retail websites consume them

**⚠️ WORKFLOW:** Verification happens **AFTER PAYMENT** (not at checkout)

**Post-Purchase Verification Flow:**
1. **Customer completes order on RETAIL WEBSITE** (no age check during checkout)
2. **RETAIL WEBSITE → CIS:** Order data sent to CIS API
3. **CIS receives order** → Automated verification workflow starts
4. **CIS checks fraud database:** Known customer? Previously verified? High risk?
5. **If NEW/UNKNOWN → CIS sends:** Email + SMS with upload link
6. **Customer clicks link → RETAIL WEBSITE:** Upload portal (vapeshed.co.nz/verify/[token])
7. **RETAIL WEBSITE → CIS:** Photo uploaded, stored in CIS
8. **CIS processes:** AI analysis + manual review queue
9. **If VERIFIED → CIS updates:** Order status → Dispatch
10. **If FAILED → CIS:** Order held, refund processed, customer flagged

**Where Things Happen:**

| Action | Location | Managed By |
|--------|----------|------------|
| Customer orders | **Retail Website** (vapeshed.co.nz) | Website |
| Order creation | **CIS API** | CIS |
| Fraud check | **CIS Backend** | CIS |
| Email/SMS sent | **CIS Backend** (Burst SMS) | CIS |
| ID upload portal | **Retail Website** (vapeshed.co.nz/verify) | Website (CIS API) |
| Photo storage | **CIS Storage** | CIS |
| AI verification | **CIS Backend** | CIS |
| Manual review | **CIS Staff Portal** | CIS Staff |
| Approval/rejection | **CIS Staff Portal** | CIS Staff |
| Fraud blacklist | **CIS Database** | CIS |
| Refund processing | **CIS Backend** | CIS |
| Vend sync | **CIS Backend** → Vend | CIS |

**Automated Features:**
1. **Risk Assessment (on order creation):**
   - Match email/phone/address/IP against fraud database
   - Check if customer previously verified
   - Flag suspicious patterns (multiple failed verifications, fake IDs, etc.)
   - Risk score: LOW (dispatch immediately) | MEDIUM (request ID) | HIGH (staff review)

2. **Automated ID Request:**
   - Email: "Please verify your age to complete your VapeShed order"
   - SMS: "Upload photo ID at vapeshed.co.nz/verify/[token] to dispatch your order"
   - Secure upload portal with unique token per order
   - Accept: Passport, Driver's License, 18+ Card
   - AI validation: Check expiry date, age calculation, photo quality

3. **Verification Queue:**
   - Low-risk customers: AI auto-approval (clear photo, age 25+, valid ID)
   - Medium-risk: Manual staff review
   - High-risk: Manager review + additional checks
   - Track verification time, bottlenecks, approval rates

4. **Customer Flagging:**
   - Mark as underage/fraud → Block all future orders
   - Add fraud notes to Vend profile
   - Automatic refund processing
   - Blacklist email/phone/address combinations

**CIS Staff Tools:**
- **ID Verification Dashboard:**
  - Queue of pending verifications
  - View uploaded photo ID
  - "Approve" / "Reject" / "Request Better Photo" buttons
  - Fraud match alerts
  - One-click refund processing

- **Order Review Page:**
  - Verification status badge (Pending, Verified, Rejected, Flagged)
  - "Mark Underage/Fraud" button
  - View uploaded ID photos
  - Fraud match warnings
  - Manual override with reason required

**API (automated post-purchase verification):**
```php
// Called when order is created
AgeVerificationService::processNewOrder($orderId)
```

**Returns:**
```json
{
  "action": "request_verification",
  "risk_level": "medium",
  "reason": "New customer, no previous orders",
  "verification_method": "photo_upload",
  "notifications_sent": {
    "email": "Verification request sent to customer@email.com",
    "sms": "SMS sent to +64 21 123 4567"
  },
  "upload_token": "abc123xyz",
  "upload_url": "https://vapeshed.co.nz/verify/abc123xyz",
  "expires_at": "2025-11-12T23:59:59Z"
}
```

**API (staff reviewing verification queue):**
```php
// Get pending verifications for staff dashboard
AgeVerificationService::getPendingVerifications($limit = 50)
```

**Returns:**
```json
{
  "pending_count": 12,
  "verifications": [
    {
      "order_id": 12345,
      "customer_name": "John Smith",
      "uploaded_at": "2025-11-05T14:30:00Z",
      "photo_url": "/secure/id-photos/abc123.jpg",
      "ai_result": {
        "confidence": 85,
        "age_detected": 28,
        "id_type": "nz_drivers_license",
        "expiry_valid": true,
        "recommendation": "approve"
      },
      "risk_factors": [],
      "requires_manual_review": false
    }
  ]
}
```

**API (checking for fraud matches):**
```php
// Check if customer matches known fraud patterns
AgeVerificationService::checkFraudMatches($email, $phone, $address, $ip)
```

**Returns:**
```json
{
  "is_flagged": true,
  "risk_level": "high",
  "matches": [
    {
      "type": "email",
      "previous_order": 12340,
      "flagged_date": "2025-10-15",
      "reason": "Fake ID presented - customer admitted being 16",
      "flagged_by_staff": "John Manager"
    }
  ],
  "recommendation": "HOLD - Manual review required",
  "auto_refund_eligible": false
}
```

**Integration Points:**
1. **Order Created → CIS:** Trigger verification workflow
2. **CIS → Customer:** Email + SMS with upload link
3. **Customer → CIS:** Upload photo ID via secure portal
4. **CIS AI/Staff → Order:** Approve/reject verification
5. **CIS → Vend:** Sync fraud flags to customer profile
6. **CIS → Website:** Sync verified customers (skip verification for repeat orders)

---

## 💰 **COST SAVINGS FEATURES:**

### **1. Shipping Cost Reduction**
**Target:** 15-25% savings on freight

**How:**
- ✅ Auto-select cheapest courier (save 10-20%)
- ✅ Optimize box sizes (save 10-15%)
- ✅ Use closest store (save 5-10%)
- ✅ Bulk courier discounts
- ✅ Zone optimization

**Tracking:**
```sql
CREATE TABLE ecommerce_cost_savings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    category ENUM('freight','packaging','labor','inventory'),
    actual_cost DECIMAL(10,2),
    baseline_cost DECIMAL(10,2),
    savings DECIMAL(10,2),
    savings_percent DECIMAL(5,2),
    method VARCHAR(255),
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### **2. Operational Efficiency**
**Target:** 20-30% faster order processing

**How:**
- ✅ Guided packing (algorithms tell staff what to do)
- ✅ Fewer errors (validation)
- ✅ Better staff allocation
- ✅ Queue management

**Tracking:**
```sql
CREATE TABLE ecommerce_packing_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    store_id INT NOT NULL,
    packed_by_user_id INT,
    started_at DATETIME,
    completed_at DATETIME,
    packing_time_seconds INT,
    algorithm_time_estimate INT,
    efficiency_score DECIMAL(5,2),
    errors INT DEFAULT 0
);
```

### **3. Inventory Optimization**
**Target:** 10-15% reduction in stock transfers

**How:**
- ✅ Ship from closest store with stock
- ✅ Balance inventory automatically
- ✅ Predict stock needs per store

---

## 📊 **NEW DATABASE TABLES:**

### **1. Fulfillment Jobs**
```sql
CREATE TABLE ecommerce_fulfillment_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    site VARCHAR(50) COMMENT 'vapeshed or ecigdis',
    customer_id INT NOT NULL,

    -- Store selection
    selected_store_id INT NOT NULL,
    algorithm_version VARCHAR(20) DEFAULT '1.0',
    selection_score DECIMAL(5,2),
    selection_reason TEXT,
    alternative_stores JSON COMMENT 'Other viable stores',

    -- Costs & estimates
    estimated_freight_cost DECIMAL(10,2),
    actual_freight_cost DECIMAL(10,2),
    estimated_delivery_date DATE,
    actual_delivery_date DATE,

    -- Packing
    packing_started_at DATETIME,
    packing_completed_at DATETIME,
    packing_time_seconds INT,
    packed_by_user_id INT,
    box_size VARCHAR(50),
    actual_weight_kg DECIMAL(10,2),

    -- Courier
    courier_service VARCHAR(100),
    courier_tracking_number VARCHAR(100),
    courier_booked_at DATETIME,
    courier_picked_up_at DATETIME,

    -- Status
    status ENUM('pending','packing','packed','dispatched','in_transit','delivered','cancelled','failed') DEFAULT 'pending',

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    INDEX idx_store (selected_store_id),
    INDEX idx_status (status),
    INDEX idx_courier_tracking (courier_tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **2. Freight Quotes**
```sql
CREATE TABLE ecommerce_freight_quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    from_store_id INT NOT NULL,
    to_postcode VARCHAR(10),
    to_city VARCHAR(100),

    -- Quote details
    courier_service VARCHAR(100) NOT NULL,
    service_level VARCHAR(100),
    quoted_cost DECIMAL(10,2) NOT NULL,
    estimated_delivery_days INT,
    pickup_cutoff_time TIME,

    -- Package details
    box_size VARCHAR(50),
    weight_kg DECIMAL(10,2),
    length_cm INT,
    width_cm INT,
    height_cm INT,

    -- Selection
    was_selected TINYINT(1) DEFAULT 0,
    selected_at DATETIME,

    -- Quote metadata
    quote_reference VARCHAR(100),
    quoted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,

    INDEX idx_order (order_id),
    INDEX idx_courier (courier_service),
    INDEX idx_selected (was_selected)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **3. Packing Optimization Logs**
```sql
CREATE TABLE ecommerce_packing_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    fulfillment_job_id INT,

    -- Algorithm details
    algorithm_version VARCHAR(20) DEFAULT '1.0',
    recommended_box_size VARCHAR(50),
    actual_box_size VARCHAR(50),
    box_size_match TINYINT(1) COMMENT 'Did staff use recommended box?',

    -- Materials
    recommended_materials JSON,
    actual_materials JSON,

    -- Performance
    estimated_pack_time_seconds INT,
    actual_pack_time_seconds INT,
    efficiency_score DECIMAL(5,2) COMMENT '0-100',

    -- Staff
    packed_by_user_id INT,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    INDEX idx_fulfillment (fulfillment_job_id),
    INDEX idx_efficiency (efficiency_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **4. Store Performance Metrics**
```sql
CREATE TABLE ecommerce_store_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    date DATE NOT NULL,

    -- Orders
    orders_fulfilled INT DEFAULT 0,
    orders_on_time INT DEFAULT 0,
    orders_late INT DEFAULT 0,
    orders_cancelled INT DEFAULT 0,

    -- Packing
    avg_packing_time_seconds INT,
    fastest_pack_time_seconds INT,
    slowest_pack_time_seconds INT,
    packing_errors INT DEFAULT 0,

    -- Shipping
    avg_freight_cost DECIMAL(10,2),
    total_freight_cost DECIMAL(10,2),
    freight_savings DECIMAL(10,2),

    -- Performance
    overall_efficiency_score DECIMAL(5,2),

    -- Updated
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_store_date (store_id, date),
    INDEX idx_date (date),
    INDEX idx_efficiency (overall_efficiency_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **5. Cost Savings Tracker**
```sql
CREATE TABLE ecommerce_cost_savings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    fulfillment_job_id INT,

    -- Savings category
    category ENUM('freight','packaging','labor','inventory','other') NOT NULL,

    -- Costs
    baseline_cost DECIMAL(10,2) NOT NULL COMMENT 'What it would have cost',
    actual_cost DECIMAL(10,2) NOT NULL COMMENT 'What it actually cost',
    savings DECIMAL(10,2) GENERATED ALWAYS AS (baseline_cost - actual_cost) STORED,
    savings_percent DECIMAL(5,2) GENERATED ALWAYS AS ((baseline_cost - actual_cost) / baseline_cost * 100) STORED,

    -- Method
    savings_method VARCHAR(255) COMMENT 'How we saved money',

    -- Audit
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    INDEX idx_category (category),
    INDEX idx_date (saved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **6. Age Verification Tracking**
```sql
CREATE TABLE ecommerce_age_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50),

    -- Verification request
    verification_token VARCHAR(100) UNIQUE NOT NULL,
    request_sent_at DATETIME NOT NULL,
    request_method ENUM('email','sms','both') DEFAULT 'both',

    -- Upload details
    id_uploaded_at DATETIME,
    id_photo_path VARCHAR(255),
    id_type ENUM('passport','drivers_license','18plus_card','other'),

    -- AI Analysis
    ai_processed_at DATETIME,
    ai_confidence_score DECIMAL(5,2),
    ai_detected_age INT,
    ai_id_valid TINYINT(1),
    ai_recommendation ENUM('approve','review','reject'),

    -- Manual Review
    reviewed_by_user_id INT,
    reviewed_at DATETIME,
    review_notes TEXT,

    -- Decision
    status ENUM('pending','uploaded','approved','rejected','expired','refunded') DEFAULT 'pending',
    approved_at DATETIME,
    rejected_reason VARCHAR(255),

    -- Risk Assessment
    risk_level ENUM('low','medium','high') DEFAULT 'medium',
    fraud_matches JSON COMMENT 'Array of fraud pattern matches',

    -- Expiry & Reminders
    expires_at DATETIME,
    reminder_sent_at DATETIME,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    INDEX idx_token (verification_token),
    INDEX idx_status (status),
    INDEX idx_risk (risk_level),
    INDEX idx_email (customer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **7. Fraud Blacklist**
```sql
CREATE TABLE ecommerce_fraud_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Customer identifiers
    email VARCHAR(255),
    phone VARCHAR(50),
    address_hash VARCHAR(64) COMMENT 'SHA256 hash of full address',
    ip_address VARCHAR(45),

    -- Original order that triggered flag
    flagged_order_id INT NOT NULL,
    flagged_at DATETIME NOT NULL,
    flagged_by_user_id INT NOT NULL,

    -- Reason
    fraud_type ENUM('underage','fake_id','stolen_payment','repeat_offender','other') NOT NULL,
    fraud_notes TEXT,

    -- Actions
    auto_block TINYINT(1) DEFAULT 1 COMMENT 'Auto-block future orders',
    refund_processed TINYINT(1) DEFAULT 0,
    refund_amount DECIMAL(10,2),

    -- Vend sync
    vend_customer_id VARCHAR(100),
    vend_notes_synced TINYINT(1) DEFAULT 0,
    vend_synced_at DATETIME,

    -- Removal (if false positive)
    removed_at DATETIME,
    removed_by_user_id INT,
    removal_reason TEXT,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_address (address_hash),
    INDEX idx_ip (ip_address),
    INDEX idx_flagged_order (flagged_order_id),
    INDEX idx_auto_block (auto_block)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🎯 **4-PHASE BUILD PLAN:**

### **PHASE 1: CORE E-COMMERCE MANAGEMENT (Week 1)**
**Goal:** Replace legacy pages with modern MVC equivalents

**Deliverables:**
1. ✅ Module structure (`/modules/ecommerce-ops/`)
2. ✅ Bootstrap & authentication
3. ✅ CustomerService + views (replaces customers-overview.php, view-customer.php)
4. ✅ ProductService + views (replaces edit-website-product.php)
5. ✅ OrderService + views (replaces view-web-order.php, view-web-order-outlet.php)
6. ✅ WholesaleService + views (replaces wholesale-accounts.php)
7. ✅ NotificationService (Burst SMS + Email) - Order notifications
8. ✅ AgeVerificationService (R18 compliance) - Fraud detection & blocking
9. ✅ Basic API endpoints (CRUD operations)

**Success Criteria:**
- [ ] All 12 legacy pages have modern equivalents
- [ ] Feature parity (everything old pages did)
- [ ] No regressions (existing functionality works)
- [ ] Burst SMS notifications working (order confirmation, dispatch, pickup)
- [ ] R18 age verification enforced (block underage, fraud detection)
- [ ] Per-outlet courier credentials working (GSS + NZ Post)
- [ ] Better UI/UX (Bootstrap 5, modern design)

---

### **PHASE 2: SMART FULFILLMENT ENGINE (Week 2)**
**Goal:** Multi-store order routing with intelligent store selection

**Deliverables:**
1. ✅ FulfillmentEngine.php (store selection algorithm)
2. ✅ InventoryService.php (20-store inventory checking)
3. ✅ Database tables (fulfillment_jobs, store_performance)
4. ✅ API endpoints (/api/fulfillment/*)
5. ✅ Fulfillment UI (views/orders/fulfillment.php)
6. ✅ Real-time inventory dashboard

**Success Criteria:**
- [ ] Algorithm suggests optimal store (weighted scoring)
- [ ] Staff can override with reason
- [ ] All 20 stores checked in <2 seconds
- [ ] Stock splitting works (multi-store orders)
- [ ] Performance tracking per store

---

### **PHASE 3: FREIGHT OPTIMIZATION (Week 3)**
**Goal:** Best courier/rate selection + cost savings tracking

**Deliverables:**
1. ✅ FreightOptimizer.php (wraps existing FreightService)
2. ✅ PackingAlgorithm.php (box optimization)
3. ✅ Courier API integrations (CourierPost, Aramex)
4. ✅ Database tables (freight_quotes, packing_logs, cost_savings)
5. ✅ API endpoints (/api/freight/*)
6. ✅ Freight comparison UI
7. ✅ Cost savings dashboard

**Success Criteria:**
- [ ] Real-time rate comparison (4+ couriers)
- [ ] Auto-select cheapest option
- [ ] Packing algorithm saves 10%+ on box sizes
- [ ] Cost savings tracked per order
- [ ] Monthly savings reports

---

### **PHASE 4: ANALYTICS & OPTIMIZATION (Week 4)**
**Goal:** Performance dashboards + continuous improvement

**Deliverables:**
1. ✅ AnalyticsService.php (reporting engine)
2. ✅ Store performance dashboard (views/analytics/fulfillment.php)
3. ✅ Freight cost analysis (views/analytics/freight-costs.php)
4. ✅ Cost savings reports
5. ✅ Store benchmarking (fastest/cheapest stores)
6. ✅ Alert system (slow stores, high costs)
7. ✅ Export to CSV/PDF

**Success Criteria:**
- [ ] Real-time dashboards (refresh every 30s)
- [ ] Drill-down by store/date/courier
- [ ] Identify inefficient stores
- [ ] Quantify total savings (monthly/yearly)
- [ ] Actionable recommendations

---

## 🚀 **READY TO START?**

**Next Steps:**
1. Create `/modules/ecommerce-ops/` directory structure
2. Build Phase 1: Core E-Commerce Management
3. Test against legacy pages (feature parity)
4. Deploy Phase 1 to production
5. Continue with Phases 2-4

**Estimated Timeline:**
- Phase 1: 5-7 days
- Phase 2: 5-7 days
- Phase 3: 5-7 days
- Phase 4: 5-7 days
- **Total: 4-6 weeks**

---

**Document Status:** Master Plan Complete
**Last Updated:** November 5, 2025
**Next Action:** Begin Phase 1 Implementation
**Approval Required:** Yes - confirm approach before building
