# Flagged Products Module

**Version:** 1.0.0
**Author:** CIS Development Team
**Package:** `CIS\FlaggedProducts`

---

## 📋 Overview

The Flagged Products module is a professional MVC-based system for tracking and managing inventory discrepancies across retail outlets. It provides:

- **Real-time tracking** of stock count inaccuracies
- **Accuracy metrics** to measure store performance
- **Historical analysis** to identify commonly problematic products
- **Staff accountability** with completion tracking
- **Bulk operations** for efficient management

---

## 🏗️ Architecture

### MVC Structure

```
modules/flagged_products/
├── api/
│   └── FlaggedProductsAPI.php      # RESTful API endpoint
├── assets/
│   ├── css/
│   │   └── styles.css              # Custom styling
│   └── js/
│       └── app.js                  # Frontend application
├── config/
│   └── module.php                  # Configuration settings
├── controllers/
│   └── FlaggedProductController.php # Request handling
├── models/
│   └── FlaggedProductModel.php     # Data access layer
├── views/
│   ├── index.php                   # Outlet selection
│   └── outlet.php                  # Outlet detail view
├── bootstrap.php                   # Module initialization
├── index.php                       # Entry point
└── README.md                       # This file
```

### Namespace

All classes use PSR-4 autoloading with namespace: `CIS\FlaggedProducts\`

---

## 🚀 Installation

### 1. File Placement

Ensure the module is placed in:
```
/home/master/applications/jcepnzzkmj/public_html/modules/flagged_products/
```

### 2. Database Requirements

The module requires the following tables:

- `flagged_products` - Main tracking table
- `vend_products` - Product information
- `vend_inventory` - Stock levels
- `vend_outlets` - Store locations

**Database schema:**
```sql
CREATE TABLE IF NOT EXISTS `flagged_products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(50) NOT NULL,
    `outlet_id` VARCHAR(50) NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `qty_before` DECIMAL(10,2) NOT NULL,
    `qty_after` DECIMAL(10,2) DEFAULT NULL,
    `complete` TINYINT(1) DEFAULT 0,
    `flagged_datetime` DATETIME NOT NULL,
    `completed_datetime` DATETIME DEFAULT NULL,
    `staff_id` INT DEFAULT NULL,
    `dummy_product` TINYINT(1) DEFAULT 0,
    `deleted_at` DATETIME DEFAULT NULL,
    KEY `idx_outlet` (`outlet_id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_complete` (`complete`),
    KEY `idx_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3. Configuration

Edit `config/module.php` to customize:

```php
return [
    'features' => [
        'real_time_accuracy' => true,
        'dummy_products' => true,
        'bulk_operations' => true,
        'export_csv' => true
    ],
    'defaults' => [
        'accuracy_threshold' => 95,  // Target percentage
        'days_to_analyze' => 30,     // Historical period
        'items_per_page' => 50
    ],
    'theme' => 'default'  // Options: default, classic_cis, modern
];
```

---

## 📖 Usage

### Accessing the Module

Navigate to: `/modules/flagged_products/`

### Workflow

1. **Select Outlet** - Choose store from grid view
2. **Review Flagged Products** - See pending items with stock discrepancies
3. **Take Action**:
   - **Complete** - Verify and mark resolved
   - **Delete** - Remove false flags
   - **Bulk Complete** - Process all at once
4. **Monitor Accuracy** - Track performance metrics

---

## 🎯 Features

### 1. Outlet Selection (Index View)

- **Grid Display** - All outlets with store codes
- **Info Cards** - Explanation of system purpose
- **Quick Navigation** - Direct links to outlet detail

### 2. Outlet Detail View

**Stats Dashboard:**
- Pending items count
- Completed items (30 days)
- Accuracy rate percentage
- Total products flagged

**Accuracy Visualization:**
- Progress bar with color coding:
  - 🟢 Green: ≥95% (Target)
  - 🟡 Yellow: 85-94% (Warning)
  - 🔴 Red: <85% (Critical)

**Flagged Products Table:**
- SKU and product name
- Reason for flagging
- Qty before vs current stock
- Flagged date/time
- Action buttons (Complete/Delete)

**Commonly Inaccurate Products:**
- Historical analysis
- Flag frequency
- Accuracy rates per product

### 3. API Endpoints

#### GET Requests

```bash
# List products for outlet
GET /api/FlaggedProductsAPI.php?action=list&outlet_id=123

# Get stats
GET /api/FlaggedProductsAPI.php?action=stats&outlet_id=123

# Get history
GET /api/FlaggedProductsAPI.php?action=history&outlet_id=123

# Get commonly inaccurate
GET /api/FlaggedProductsAPI.php?action=commonly_inaccurate&outlet_id=123&limit=10

# Export CSV
GET /api/FlaggedProductsAPI.php?action=export&outlet_id=123
```

#### POST Requests

```javascript
// Create flagged product
POST /api/FlaggedProductsAPI.php
{
    "action": "create",
    "product_id": "prod_123",
    "outlet_id": "outlet_456",
    "reason": "Stock count mismatch",
    "qty_before": 50,
    "staff_id": 1
}

// Complete flagged product
POST /api/FlaggedProductsAPI.php
{
    "action": "complete",
    "product_id": "prod_123",
    "outlet_id": "outlet_456",
    "staff_id": 1,
    "qty_before": 50,
    "qty_after": 48
}

// Delete flagged product
POST /api/FlaggedProductsAPI.php
{
    "action": "delete",
    "product_id": "prod_123",
    "outlet_id": "outlet_456"
}

// Bulk complete all
POST /api/FlaggedProductsAPI.php
{
    "action": "bulk_complete",
    "outlet_id": "outlet_456",
    "staff_id": 1
}
```

---

## 🔧 Model Methods

### FlaggedProductModel Class

#### Core Methods

```php
// Get pending flagged products for outlet
$products = $model->getByOutlet($outletId);

// Get count of pending items
$count = $model->getPendingCount($outletId);

// Check if product already flagged
$exists = $model->exists($productId, $outletId, $reason);

// Create new flagged product
$id = $model->create([
    'product_id' => 'prod_123',
    'outlet_id' => 'outlet_456',
    'reason' => 'Stock mismatch',
    'qty_before' => 50,
    'staff_id' => 1
]);

// Mark as complete
$success = $model->markComplete($productId, $outletId, $staffId, $qtyAfter, $qtyBefore);

// Soft delete
$success = $model->delete($outletId);
```

#### Analysis Methods

```php
// Get completed items from last 30 days
$history = $model->getLast30Days($outletId);

// Get commonly inaccurate products
$products = $model->getCommonlyInaccurate($outletId, $limit = 10);

// Get accuracy statistics
$accuracy = $model->getAccuracyStats($outletId, $days = 30);
// Returns: ['accuracy' => 92.5, 'accurate_count' => 37, 'total_completed' => 40]
```

---

## 🎨 Theming

### Available Themes

1. **default** - Modern Bootstrap 5 (current)
2. **classic_cis** - Legacy CIS styling
3. **modern** - Dark mode variant

### Custom Styling

Add custom CSS to `assets/css/styles.css`:

```css
:root {
    --fp-primary: #4169E1;      /* Primary color */
    --fp-success: #28a745;      /* Success color */
    --fp-warning: #ffc107;      /* Warning color */
    --fp-danger: #dc3545;       /* Danger color */
}
```

---

## 📊 Accuracy Calculation

### Formula

```
Accuracy % = (Accurate Counts / Total Counts) × 100
```

Where:
- **Accurate** = `qty_before` matches `qty_after`
- **Total** = All completed flagged products

### Thresholds

- **≥95%** - ✅ Excellent (Green)
- **85-94%** - ⚠️ Good (Yellow)
- **<85%** - ❌ Needs Improvement (Red)

---

## 🔐 Security

### Features

- ✅ Prepared statements (SQL injection prevention)
- ✅ Input validation on all operations
- ✅ Soft deletes (data retention)
- ✅ Staff ID tracking (accountability)
- ✅ JSON API with proper headers
- ✅ Error logging without exposing details

### Permissions

Configure in `config/module.php`:

```php
'permissions' => [
    'view' => 'staff',
    'create' => 'staff',
    'complete' => 'staff',
    'delete' => 'manager',
    'bulk_operations' => 'manager',
    'export' => 'manager'
]
```

---

## 🐛 Troubleshooting

### Common Issues

**1. Module not loading**
- Check file permissions: `chmod 755 modules/flagged_products/`
- Verify bootstrap.php is being included
- Check error logs: `/var/log/apache2/error.log`

**2. Database errors**
- Verify tables exist: `SHOW TABLES LIKE 'flagged_products'`
- Check database credentials in parent CIS config
- Ensure MySQL connection is active

**3. CSS not loading**
- Verify path: `/modules/flagged_products/assets/css/styles.css`
- Check browser console for 404 errors
- Clear browser cache

**4. API not responding**
- Check `api/FlaggedProductsAPI.php` exists
- Verify JSON Content-Type header
- Check PHP error logs

### Debug Mode

Enable in `config/module.php`:

```php
'debug' => [
    'enabled' => true,
    'log_queries' => true,
    'show_errors' => true
]
```

---

## 📈 Performance

### Optimization Tips

1. **Indexes** - Ensure database indexes on:
   - `flagged_products.outlet_id`
   - `flagged_products.product_id`
   - `flagged_products.complete`
   - `flagged_products.deleted_at`

2. **Caching** - Consider caching:
   - Outlet list (rarely changes)
   - Accuracy stats (update hourly)

3. **Pagination** - For large datasets:
   ```php
   $products = $model->getByOutlet($outletId, $page, $perPage);
   ```

---

## 🔄 Upgrading

### From Legacy flagged-products.php

1. **Backup** existing data:
   ```bash
   mysqldump -u root -p database_name flagged_products > backup.sql
   ```

2. **Install** new module (see Installation)

3. **Test** in parallel before redirecting traffic

4. **Redirect** old URLs:
   ```php
   // In old flagged-products.php
   header('Location: /modules/flagged_products/');
   exit;
   ```

---

## 📝 Changelog

### Version 1.0.0 (2025-01-XX)

**Added:**
- Full MVC architecture with PSR-4 autoloading
- Professional Bootstrap 5 UI
- RESTful API with JSON responses
- Accuracy tracking and metrics
- Historical analysis features
- CSV export functionality
- Bulk operations support
- Commonly inaccurate products analysis

**Removed:**
- Unprofessional messaging
- Legacy procedural code
- Hardcoded configuration

**Improved:**
- Database security (prepared statements)
- Error handling and logging
- Code organization and maintainability
- UI/UX with modern design patterns

---

## 🤝 Contributing

### Code Standards

- **PSR-12** - PHP coding standards
- **Bootstrap 5** - UI framework
- **Font Awesome 6** - Icons
- **JSDoc** - JavaScript documentation
- **PHPDoc** - PHP documentation

### Pull Request Process

1. Create feature branch
2. Write/update tests
3. Update documentation
4. Submit PR with clear description

---

## 📞 Support

For issues or questions:

- **Internal Wiki**: https://wiki.vapeshed.co.nz
- **IT Manager**: [Contact Info]
- **GitHub Issues**: [Repo URL]

---

## 📜 License

Proprietary - Ecigdis Limited / The Vape Shed
Internal use only - Do not distribute

---

## ✨ Credits

**Built by:** CIS Development Team
**For:** The Vape Shed Store Operations
**Date:** January 2025

---

**Status:** ✅ Production Ready
**Quality:** High Calibre Professional Standard
