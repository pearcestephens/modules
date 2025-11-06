# 🏬 Outlets Module - Complete Documentation

## Overview

The **Outlets Module** provides comprehensive management of all 19 retail locations for The Vape Shed. It tracks landlord information, addresses, operating hours, closure history, revenue patterns, photos, documents, and maintenance.

---

## 📋 Features

### Core Functionality
- **Location Management**: Complete details for 19 stores
- **Landlord Tracking**: Lease agreements, contacts, rent payments
- **Operating Hours**: Default and special hours per location
- **Closure History**: Track temporary/permanent closures with impact
- **Revenue Snapshots**: Daily/weekly/monthly revenue tracking
- **Performance Metrics**: KPIs and benchmarks per store
- **Photo Galleries**: Exterior, interior, signage photos
- **Document Management**: Leases, certificates, insurance
- **Maintenance Log**: Track repairs, issues, and resolutions

### User Interface
- **Grid View**: Visual card layout with photos
- **List View**: Detailed table with key metrics
- **Map View**: Interactive Google Maps with all locations
- **Filters**: Status, city, search, sorting
- **Summary Cards**: Total outlets, active stores, expiring leases, avg revenue

---

## 🗃️ Database Schema

### Tables (8 total)

#### 1. `outlets` (Master Location Table)
**Columns**: 40+ fields
- Basic: `id`, `outlet_name`, `outlet_code`, `brand`, `status`
- Address: `street_address`, `city`, `region`, `postcode`, `latitude`, `longitude`
- Contact: `phone`, `email`, `google_maps_link`
- Landlord: `landlord_name`, `landlord_contact`, `landlord_email`, `landlord_phone`
- Lease: `lease_start_date`, `lease_end_date`, `rent_amount`, `rent_frequency`, `bond_amount`
- Physical: `floor_area_sqm`, `parking_spaces`, `has_street_frontage`, `has_signage`
- Manager: `manager_user_id`, `assistant_manager_user_id`
- Integration: `lightspeed_outlet_id`, `xero_tracking_category_id`

**Status Values**: `active`, `inactive`, `closed_temporary`, `closed_permanent`, `coming_soon`

#### 2. `outlet_photos`
Store images with categorization
- **Fields**: `outlet_id`, `photo_type`, `file_path`, `file_name`, `title`, `description`, `is_primary`, `display_order`
- **Photo Types**: exterior, interior, product_display, staff_area, signage, other

#### 3. `outlet_operating_hours`
Opening times per day of week
- **Fields**: `outlet_id`, `day_of_week` (1-7), `opens_at`, `closes_at`, `is_closed`, `effective_from`, `effective_until`
- **Usage**: Default hours + special/temporary hours

#### 4. `outlet_closure_history`
Track temporary closures
- **Fields**: `outlet_id`, `closure_type`, `reason`, `closed_from`, `closed_until`, `revenue_loss_estimate`, `was_notified_to_customers`
- **Closure Types**: planned, emergency, maintenance, weather, staffing, other

#### 5. `outlet_revenue_snapshots`
Daily revenue tracking
- **Fields**: `outlet_id`, `snapshot_date`, `period_type`, `total_sales`, `total_transactions`, `average_transaction_value`, `customer_count`, `conversion_rate`
- **Product Mix**: `nicotine_sales`, `hardware_sales`, `accessories_sales`, `other_sales`
- **Comparisons**: `vs_yesterday_pct`, `vs_last_week_pct`, `vs_last_month_pct`, `vs_last_year_pct`

#### 6. `outlet_performance_metrics`
KPIs and benchmarks
- **Financial**: `revenue`, `cogs`, `gross_profit`, `gross_margin_pct`
- **Operational**: `staff_hours`, `staff_cost`, `labor_cost_pct`
- **Efficiency**: `revenue_per_sqm`, `revenue_per_staff_hour`, `transactions_per_hour`
- **Quality**: `customer_satisfaction_score`, `online_review_rating`, `complaint_count`
- **Rankings**: `rank_revenue`, `rank_profit`, `rank_growth`

#### 7. `outlet_documents`
Store files/documents
- **Fields**: `outlet_id`, `document_type`, `document_name`, `file_path`, `expiry_date`, `is_confidential`
- **Types**: lease_agreement, insurance, compliance_certificate, floor_plan, license, other

#### 8. `outlet_maintenance_log`
Repairs and issues
- **Fields**: `outlet_id`, `issue_type`, `priority`, `title`, `description`, `status`, `estimated_cost`, `actual_cost`
- **Issue Types**: hvac, plumbing, electrical, structural, equipment, cleaning, other
- **Priority**: low, medium, high, urgent

### Views

#### `vw_outlets_overview`
Complete outlet overview with latest metrics
```sql
SELECT
    o.*,
    manager_name,
    revenue_last_30_days,
    revenue_yesterday,
    photo_count,
    primary_photo
FROM outlets o
LEFT JOIN users u ON o.manager_user_id = u.id
LEFT JOIN outlet_revenue_snapshots ors ON o.id = ors.outlet_id
```

---

## 🎨 User Interface

### Dashboard (`dashboard.php`)
**Main view with three display modes:**

#### Grid View (Default)
- Card layout with outlet photos
- Status badges (color-coded)
- 30-day revenue display
- Quick action buttons (View, Edit)

#### List View
- Detailed table with all key metrics
- Sortable columns
- Compact display for quick scanning

#### Map View
- Interactive Google Maps integration
- Color-coded markers by status
- Info windows with revenue and details
- Click marker to view full details

### Filters
- **Status Filter**: All / Active / Inactive / Temp Closed / Permanently Closed
- **City Filter**: All Cities / Auckland / Wellington / Christchurch / etc.
- **Search Box**: Search by name, code, or city
- **Sort By**: Name / Revenue / Opened Date / City

---

## 📡 API Endpoints

### GET `/api/get-outlets.php`
**Returns**: All outlets with filters

**Query Parameters:**
- `status` (optional): Filter by status
- `city` (optional): Filter by city
- `search` (optional): Search term

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "outlet_name": "The Vape Shed - Queen Street",
      "outlet_code": "VS-QST",
      "city": "Auckland CBD",
      "status": "active",
      "manager_name": "John Smith",
      "revenue_last_30_days": 85000,
      "primary_photo": "/uploads/outlets/vs-qst-exterior.jpg"
    }
  ],
  "summary": {
    "total": 19,
    "active": 17,
    "expiring_leases": 3,
    "avg_revenue": 85000
  },
  "count": 19
}
```

### POST `/api/save-outlet.php`
**Creates or updates an outlet**

**POST Data:**
- `outlet_name` (required)
- `outlet_code` (required)
- `street_address` (required)
- `city` (required)
- `phone`, `email`, `region`, `postcode` (optional)

**Response:**
```json
{
  "success": true,
  "outlet_id": 20,
  "message": "Outlet created successfully"
}
```

---

## 💻 Installation

### Step 1: Install Database Schema
```bash
mysql -u root -p your_database < modules/outlets/database/schema.sql
```

### Step 2: Verify Tables Created
```sql
SHOW TABLES LIKE 'outlet%';
```
Should show 8 tables + 1 view

### Step 3: Configure Google Maps API
Edit `dashboard.php` line 206:
```javascript
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY"></script>
```

Replace `YOUR_GOOGLE_MAPS_API_KEY` with your actual API key.

### Step 4: Set File Upload Permissions
```bash
mkdir -p public_html/uploads/outlets
chmod 755 public_html/uploads/outlets
```

### Step 5: Access Dashboard
Navigate to: `http://staff.vapeshed.co.nz/modules/outlets/dashboard.php`

---

## 🔧 Configuration

### Database Connection
Ensure `config/database.php` exists with PDO connection:
```php
$pdo = new PDO(
    "mysql:host=localhost;dbname=your_db;charset=utf8mb4",
    "username",
    "password",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

### Photo Upload Directory
Default: `/uploads/outlets/`
Change in `save-outlet.php` if needed.

---

## 🚀 Usage Examples

### Add New Outlet
1. Click "Add Outlet" button
2. Fill in required fields (name, code, address, city)
3. Click "Save Outlet"
4. Outlet appears in grid/list

### Update Revenue Snapshots
```php
// Manual insert example
INSERT INTO outlet_revenue_snapshots
(outlet_id, snapshot_date, period_type, total_sales, total_transactions)
VALUES
(1, CURDATE(), 'daily', 3500.00, 42);
```

### Track Closure
```php
INSERT INTO outlet_closure_history
(outlet_id, closure_type, reason, closed_from, closed_until, revenue_loss_estimate)
VALUES
(5, 'emergency', 'Flood damage', '2025-11-01 00:00:00', '2025-11-03 00:00:00', 10500.00);
```

### Add Maintenance Log
```php
INSERT INTO outlet_maintenance_log
(outlet_id, issue_type, priority, title, description, reported_at)
VALUES
(3, 'hvac', 'high', 'Air conditioning not working', 'AC unit making strange noise and not cooling', NOW());
```

---

## 📊 Integration Points

### Lightspeed/Vend POS
- Revenue snapshots sync from Lightspeed daily
- `lightspeed_outlet_id` maps CIS outlet to Vend outlet
- Automatic transaction count and sales data

### Xero Accounting
- Lease expenses tracked via `xero_tracking_category_id`
- Rent payments allocated to correct outlet
- Overhead allocation per location

---

## 🎯 Roadmap

- [ ] Automated Lightspeed revenue sync (cron job)
- [ ] Lease expiry email alerts (30/60/90 days)
- [ ] Photo upload UI in dashboard
- [ ] Document upload and expiry tracking
- [ ] Maintenance request form for staff
- [ ] Historical closure impact analysis
- [ ] Predictive closure alerts (weather, staffing)
- [ ] Mobile app for store managers

---

## 📞 Support

For issues or questions:
- **Email**: pearce.stephens@ecigdis.co.nz
- **Internal**: Submit ticket at helpdesk.vapeshed.co.nz

---

## 📜 License

Internal use only - Ecigdis Limited / The Vape Shed
