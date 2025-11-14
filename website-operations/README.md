# Website Operations Module

**Enterprise-Grade E-Commerce Management System for VapeShed & Ecigdis**

Version: 1.0.0
Status: Production Ready
Build Date: 2025

---

## 🚀 Overview

The Website Operations Module is a comprehensive management system for multi-channel e-commerce operations. Built to replace the missing VapeShed API and provide enterprise-level capabilities for managing:

- **VapeShed.co.nz** (Retail E-Commerce)
- **Ecigdis.co.nz** (Wholesale Operations)
- **17 Physical Stores** across New Zealand
- **Multi-Carrier Shipping** with cost optimization

### Key Features

✅ **Complete REST API** - Full CRUD operations for orders, products, customers
✅ **Shipping Cost Optimization** - Saves money on EVERY order through intelligent routing
✅ **Multi-Channel Support** - Unified management across retail & wholesale
✅ **Real-Time Dashboard** - Live monitoring with auto-refresh
✅ **Bulk Operations** - Update hundreds of products/orders at once
✅ **Production-Grade UI** - Beautiful, responsive, mobile-friendly
✅ **Performance Analytics** - Track KPIs, trends, and savings
✅ **Wholesale Management** - Dedicated B2B workflow

---

## 💰 Business Impact

### Shipping Optimization Algorithm

The **Shipping Optimization Service** automatically:

1. Analyzes inventory at all 17 store locations
2. Calculates distance from customer
3. Compares rates across NZ Post, CourierPost, Fastway
4. Selects optimal fulfillment location + carrier combination
5. **Tracks savings** vs. most expensive option

**Result**: Consistent cost savings on every order, projected $XX,XXX annual savings.

---

## 📁 Module Structure

```
/modules/website-operations/
├── api/
│   └── index.php                    # Complete REST API router
├── services/
│   ├── WebsiteOperationsService.php # Main orchestrator
│   ├── OrderManagementService.php   # Order lifecycle
│   ├── ShippingOptimizationService.php # MONEY-SAVING ALGORITHM
│   ├── ProductManagementService.php # Catalog management
│   ├── CustomerManagementService.php # Customer accounts
│   ├── WholesaleService.php         # B2B operations
│   └── PerformanceService.php       # Analytics
├── views/
│   ├── dashboard.php                # Main dashboard
│   ├── orders.php                   # Order management
│   ├── products.php                 # Product catalog
│   ├── customers.php                # Customer directory
│   └── wholesale.php                # Wholesale accounts
├── components/
│   ├── order-card.php
│   ├── product-card.php
│   └── stat-widget.php
├── assets/
│   ├── css/
│   │   └── website-operations.css
│   └── js/
│       ├── dashboard.js
│       └── api-client.js
├── config/
│   └── carriers.php                 # Shipping carrier configs
├── migrations/
│   └── 001_create_tables.sql
└── module.json                      # Module configuration
```

---

## 🔌 API Documentation

### Base URL

```
https://staff.vapeshed.co.nz/modules/website-operations/api/index.php
```

### Authentication

All API requests require an API key:

**Header:**
```
X-API-KEY: your_api_key_here
```

**Query Parameter:**
```
?api_key=your_api_key_here
```

### Endpoints

#### 1. Health Check

```http
GET /health
```

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2025-11-06T12:00:00+00:00"
}
```

---

#### 2. Orders

##### Get Orders

```http
GET /orders?status=pending&channel=vapeshed&limit=20
```

**Parameters:**
- `status` (string): pending, processing, completed, cancelled
- `channel` (string): vapeshed, ecigdis
- `outlet` (string): Store ID or 'all'
- `date_from` (date): YYYY-MM-DD
- `date_to` (date): YYYY-MM-DD
- `limit` (int): Results per page

**Response:**
```json
[
  {
    "id": 12345,
    "order_number": "VS-251106-0001",
    "customer_id": 678,
    "customer_name": "John Doe",
    "channel": "vapeshed",
    "status": "pending",
    "subtotal": 89.50,
    "shipping_cost": 5.90,
    "shipping_cost_saved": 2.30,
    "tax_amount": 13.43,
    "total_amount": 108.83,
    "fulfillment_location": "Auckland Central",
    "shipping_carrier": "NZ Post",
    "shipping_service": "Standard",
    "created_at": "2025-11-06 10:30:00"
  }
]
```

##### Get Single Order

```http
GET /orders/12345
```

**Response:**
```json
{
  "id": 12345,
  "order_number": "VS-251106-0001",
  "customer": { ... },
  "items": [
    {
      "id": 1,
      "product_id": 456,
      "sku": "VAPE-001",
      "name": "Vape Product",
      "quantity": 2,
      "price": 39.99,
      "total": 79.98
    }
  ],
  "shipping_history": [ ... ],
  "status_history": [ ... ]
}
```

##### Create Order

```http
POST /orders
Content-Type: application/json

{
  "customer_id": 678,
  "channel": "vapeshed",
  "items": [
    {
      "product_id": 456,
      "sku": "VAPE-001",
      "name": "Vape Product",
      "quantity": 2,
      "price": 39.99,
      "total": 79.98
    }
  ],
  "shipping_address": {
    "address": "123 Queen Street",
    "city": "Auckland",
    "postcode": "1010",
    "country": "NZ"
  },
  "shipping_preference": "cost"
}
```

**Response:**
```json
{
  "success": true,
  "order_id": 12345,
  "order_number": "VS-251106-0001",
  "total": 108.83,
  "shipping_cost": 5.90,
  "cost_saved": 2.30,
  "fulfillment_location": "Auckland Central",
  "optimization_details": {
    "carrier": "NZ Post",
    "service": "Standard",
    "alternatives": [ ... ]
  }
}
```

##### Update Order Status

```http
PUT /orders/12345
Content-Type: application/json

{
  "status": "processing",
  "notes": "Order picked and packed"
}
```

---

#### 3. Products

##### Get Products

```http
GET /products?search=vape&category=5&status=active&page=1&per_page=50
```

**Parameters:**
- `search` (string): Search name, SKU, description
- `category` (int): Category ID
- `status` (string): active, inactive, deleted
- `stock` (string): low, out
- `channel` (string): vapeshed, ecigdis
- `page` (int): Page number
- `per_page` (int): Results per page

**Response:**
```json
{
  "products": [ ... ],
  "total": 250,
  "page": 1,
  "per_page": 50,
  "total_pages": 5
}
```

##### Get Single Product

```http
GET /products/456
```

**Response:**
```json
{
  "id": 456,
  "sku": "VAPE-001",
  "name": "Vape Product",
  "description": "...",
  "price": 39.99,
  "cost": 20.00,
  "status": "active",
  "variants": [ ... ],
  "images": [ ... ],
  "inventory": [
    {
      "outlet_id": 1,
      "store_name": "Auckland Central",
      "quantity": 25
    }
  ],
  "sales_history": [ ... ]
}
```

##### Create Product

```http
POST /products
Content-Type: application/json

{
  "sku": "VAPE-002",
  "name": "New Vape Product",
  "description": "Product description",
  "category_id": 5,
  "price": 49.99,
  "cost": 25.00,
  "status": "active",
  "channel": "vapeshed",
  "weight": 250,
  "variants": [ ... ],
  "images": [ ... ]
}
```

##### Update Product

```http
PUT /products/456
Content-Type: application/json

{
  "price": 44.99,
  "status": "active"
}
```

##### Sync Product to Channel

```http
GET /products/456/sync?channel=vapeshed
```

---

#### 4. Customers

##### Get Customers

```http
GET /customers?search=john&is_wholesale=1
```

##### Get Single Customer

```http
GET /customers/678
```

##### Create Customer

```http
POST /customers
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "021234567",
  "company": "ACME Corp",
  "is_wholesale": 1
}
```

---

#### 5. Wholesale

##### Get Wholesale Accounts

```http
GET /wholesale?status=pending
```

##### Approve Wholesale Account

```http
PUT /wholesale/123/approve
```

---

#### 6. Dashboard

##### Get Dashboard Data

```http
GET /dashboard?date_range=30d&outlet=all
```

**Response:**
```json
{
  "summary": {
    "orders": { "total": 1250, "pending": 45, "processing": 23, ... },
    "revenue": { "total": 125000, "average_order": 100, "growth": 12.5 },
    "customers": { "total": 850, "active": 320, "new": 45, "wholesale": 23 },
    "products": { "total": 450, "active": 420, "low_stock": 15, "out_of_stock": 3 }
  },
  "orders": [ ... ],
  "performance": { ... },
  "alerts": [ ... ],
  "trending_products": [ ... ],
  "revenue": [ ... ],
  "fulfillment": {
    "total_saved": 2345.67,
    "average_processing_time": 2.5,
    "on_time_delivery_rate": 94.5
  }
}
```

---

#### 7. Shipping

##### Get Shipping Savings Report

```http
GET /shipping/savings?days=30
```

**Response:**
```json
{
  "savings": 2345.67
}
```

---

## 🛠️ Installation

### 1. Deploy Module Files

Copy the entire `website-operations` directory to `/modules/`:

```bash
cp -r website-operations /path/to/cis/modules/
```

### 2. Run Database Migrations

```bash
mysql -u username -p database_name < modules/website-operations/migrations/001_create_tables.sql
```

### 3. Configure Environment

Create `.env` file or add to existing:

```env
VAPESHED_API_KEY=your_vapeshed_key_here
ECIGDIS_API_KEY=your_ecigdis_key_here
INTERNAL_API_KEY=your_internal_key_here

# Shipping Carriers
NZPOST_API_KEY=xxx
COURIERPOST_API_KEY=xxx
FASTWAY_API_KEY=xxx
```

### 4. Set Permissions

```bash
chmod 755 modules/website-operations/api/index.php
chmod 644 modules/website-operations/module.json
```

### 5. Test Installation

Visit:
```
https://staff.vapeshed.co.nz/modules/website-operations/views/dashboard.php
```

Test API:
```
curl -H "X-API-KEY: your_key" https://staff.vapeshed.co.nz/modules/website-operations/api/index.php/health
```

---

## 🎨 UI Components

### Dashboard

**URL:** `/modules/website-operations/views/dashboard.php`

**Features:**
- Real-time metrics with auto-refresh (30s)
- Revenue charts (Chart.js)
- Order status overview
- System alerts
- Quick actions panel
- Trending products
- Shipping savings highlight

### Orders View

**URL:** `/modules/website-operations/views/orders.php`

**Features:**
- Filterable order list
- Bulk status updates
- Export to CSV/Excel
- Order detail modal
- Shipping label printing

### Products View

**URL:** `/modules/website-operations/views/products.php`

**Features:**
- Product grid/list view
- Bulk edit capabilities
- Image upload
- Category management
- Inventory tracking
- Channel sync buttons

---

## 📊 Database Schema

### Tables Created

1. **web_orders** - All orders from VapeShed/Ecigdis
2. **web_order_items** - Line items per order
3. **web_products** - Product catalog
4. **web_product_variants** - Product options/variants
5. **web_product_images** - Product photos
6. **web_customers** - Customer accounts
7. **web_categories** - Product categories
8. **wholesale_accounts** - B2B accounts
9. **store_configurations** - Store/outlet settings
10. **shipping_rates** - Carrier rate tables
11. **order_status_history** - Status change audit log
12. **order_shipping_history** - Shipping optimization audit

---

## 🔒 Security

- API key authentication on all endpoints
- SQL injection protection (prepared statements)
- XSS protection (escaped outputs)
- CSRF tokens on all forms
- Role-based access control
- Audit logging for all changes

---

## 📈 Performance

- Optimized queries with proper indexes
- Caching for frequently accessed data
- Lazy loading for large datasets
- Pagination on all list views
- CDN assets (Bootstrap, Chart.js)

---

## 🚨 Monitoring & Alerts

The system automatically monitors:

- Low stock items
- Pending orders > 10
- Wholesale accounts awaiting approval
- API connection failures
- Processing time anomalies

Alerts displayed on dashboard and can be configured for email/Slack notifications.

---

## 🔄 Integration Points

### VapeShed.co.nz
- Order sync (bidirectional)
- Product catalog sync
- Inventory updates
- Customer data

### Ecigdis.co.nz
- Wholesale orders
- Bulk pricing
- Account management
- Supplier portal data

### Vend POS
- Real-time inventory
- Store sales data
- Customer records

---

## 🧪 Testing

### API Tests

```bash
# Test health endpoint
curl -H "X-API-KEY: test_key" http://localhost/modules/website-operations/api/index.php/health

# Test orders endpoint
curl -H "X-API-KEY: test_key" http://localhost/modules/website-operations/api/index.php/orders?limit=5
```

### UI Tests

1. Visit dashboard: Check all metrics load
2. Create test order: Verify shipping optimization runs
3. Bulk update products: Test 10 products at once
4. Check alerts: Trigger low stock and verify alert appears

---

## 📞 Support

**Documentation:** This README
**API Reference:** See API Documentation section above
**Issues:** Contact Ecigdis Development Team
**Email:** dev@ecigdis.co.nz

---

## 📝 License

Proprietary - Ecigdis Limited © 2025
All Rights Reserved

---

## 🎯 Roadmap

### Phase 2 Features (Coming Soon)

- [ ] Advanced analytics dashboard
- [ ] Customer segmentation
- [ ] Marketing automation
- [ ] Inventory forecasting with AI
- [ ] Mobile app API
- [ ] Webhook system for real-time events
- [ ] Multi-currency support
- [ ] Advanced reporting builder

---

**Built with ❤️ by the Ecigdis Development Team**

*Making e-commerce operations effortless, one optimized shipment at a time.*
