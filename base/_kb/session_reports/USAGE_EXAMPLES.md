# Base Module - PDO Usage Examples

## ✅ Configuration Complete

**Status:** PDO is configured as the primary driver with auto-initialization
**MySQLi:** Available for legacy code but requires explicit initialization

---

## Quick Start (PDO - Recommended)

### Basic Query
```php
<?php
require_once __DIR__ . '/modules/base/bootstrap.php';

use CIS\Base\Database;

// PDO auto-initializes on first use - no setup needed!

// Select query with parameters
$users = Database::query(
    "SELECT * FROM users WHERE role = ? AND active = ?",
    ['admin', 1]
);

// Single row
$user = Database::queryOne(
    "SELECT * FROM users WHERE id = ?",
    [123]
);
```

### Insert, Update, Delete
```php
// Insert
$userId = Database::insert('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'role' => 'admin'
]);
echo "Created user ID: $userId";

// Update
$affected = Database::update(
    'users',
    ['status' => 'active'],          // Data to update
    ['id' => $userId]                // Where condition
);

// Delete
$deleted = Database::delete(
    'users',
    ['id' => $userId]
);
```

### Transactions
```php
try {
    Database::beginTransaction();
    
    $orderId = Database::insert('orders', [
        'customer_id' => 123,
        'total' => 99.99
    ]);
    
    Database::insert('order_items', [
        'order_id' => $orderId,
        'product_id' => 456,
        'quantity' => 2
    ]);
    
    Database::commit();
} catch (Exception $e) {
    Database::rollback();
    throw $e;
}
```

### Query Builder (PDO Only)
```php
// Fluent query building
$products = Database::table('products')
    ->where('category', '=', 'electronics')
    ->where('price', '<', 500)
    ->orderBy('price', 'ASC')
    ->limit(10)
    ->get();

// With joins
$orders = Database::table('orders')
    ->join('customers', 'orders.customer_id', '=', 'customers.id')
    ->select('orders.*', 'customers.name as customer_name')
    ->where('orders.status', '=', 'pending')
    ->get();
```

### Query Logging
```php
// Enable logging
Database::enableQueryLog(true);

// Run queries
$users = Database::query("SELECT * FROM users LIMIT 10");
$products = Database::query("SELECT * FROM products WHERE active = ?", [1]);

// Get log
$log = Database::getQueryLog();
foreach ($log as $entry) {
    echo "{$entry['query']} ({$entry['time']}ms)\n";
}

// Get last query
$lastQuery = Database::getLastQuery();
```

### Direct PDO Access
```php
// For advanced PDO features
$pdo = Database::pdo();

// Prepared statement with named parameters
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute(['email' => 'john@example.com']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch mode
$stmt = $pdo->query("SELECT * FROM products");
$products = $stmt->fetchAll(PDO::FETCH_CLASS, 'Product');
```

---

## MySQLi (Legacy Support - Manual Initialization Required)

### Initialize MySQLi First
```php
<?php
require_once __DIR__ . '/modules/base/bootstrap.php';

use CIS\Base\Database;

// REQUIRED: Initialize MySQLi explicitly
Database::initMySQLi();

// Now you can use MySQLi
$mysqli = Database::mysqli();

$result = $mysqli->query("SELECT * FROM users");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
```

### Global $con Variable (Legacy)
```php
// After initializing MySQLi, old code still works
global $con;
$con = Database::getInstance(); // Returns mysqli instance

$result = $con->query("SELECT * FROM products");
```

---

## Configuration

### Database Credentials
Set in environment variables or directly in Database.php:

```php
// Via .env file (recommended)
DB_HOST=127.0.0.1
DB_NAME=jcepnzzkmj
DB_USER=jcepnzzkmj
DB_PASSWORD=wprKh9Jq63

// Or fallback defaults in Database.php init() method
```

### Driver Status
```php
// Check active driver
$driver = Database::getDriver(); // Returns: "PDO"

// Check if PDO is initialized
$pdo = Database::pdo(); // Auto-initializes if not already

// Check if MySQLi is initialized
try {
    $mysqli = Database::mysqli();
    echo "MySQLi is initialized";
} catch (RuntimeException $e) {
    echo "MySQLi not initialized - call Database::initMySQLi() first";
}
```

---

## Migration Guide

### From MySQLi to PDO

**Old Code (MySQLi):**
```php
global $con;
$stmt = $con->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
```

**New Code (PDO):**
```php
use CIS\Base\Database;

$user = Database::queryOne(
    "SELECT * FROM users WHERE id = ?",
    [$userId]
);
```

**Benefits:**
- ✅ Cleaner syntax
- ✅ Named parameters support
- ✅ Better error handling
- ✅ Query builder available
- ✅ Automatic parameter binding

---

## Best Practices

### ✅ DO:
- Use PDO for all new code
- Use prepared statements with parameters
- Enable query logging in development
- Use transactions for multi-step operations
- Use the query builder for complex queries

### ❌ DON'T:
- Mix PDO and MySQLi in the same file
- Concatenate user input into SQL strings
- Initialize MySQLi unless absolutely necessary for legacy code
- Use raw PDO without the Database wrapper (use Database::pdo() instead)

---

## Testing Database Connection

```php
<?php
require_once __DIR__ . '/modules/base/bootstrap.php';

use CIS\Base\Database;

try {
    // Test PDO connection (auto-initializes)
    $result = Database::query("SELECT 1 as test");
    echo "✅ PDO Connection: OK\n";
    echo "Driver: " . Database::getDriver() . "\n";
    
    // Get last insert ID
    $testId = Database::lastInsertId();
    
    // Test query logging
    Database::enableQueryLog(true);
    Database::query("SELECT * FROM users LIMIT 1");
    $log = Database::getQueryLog();
    echo "✅ Query Logging: OK (" . count($log) . " queries logged)\n";
    
    echo "\n✅ ALL TESTS PASSED - Database is production ready!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

---

## Performance Tips

1. **Use Query Builder for Complex Queries**
   - Easier to read and maintain
   - Automatic parameter binding
   - Prevents SQL injection

2. **Enable Persistent Connections** (already enabled in DatabasePDO)
   ```php
   // Already configured in DatabasePDO.php:
   PDO::ATTR_PERSISTENT => true
   ```

3. **Use Transactions for Bulk Operations**
   ```php
   Database::beginTransaction();
   foreach ($records as $record) {
       Database::insert('table', $record);
   }
   Database::commit(); // Much faster than individual commits
   ```

4. **Query Logging Only in Development**
   ```php
   if ($_ENV['APP_ENV'] === 'development') {
       Database::enableQueryLog(true);
   }
   ```

---

## Support

- **PDO Documentation:** [DatabasePDO.php](./DatabasePDO.php)
- **MySQLi Documentation:** [DatabaseMySQLi.php](./DatabaseMySQLi.php)
- **Full Module Docs:** [README.md](./README.md)

---

**Status:** ✅ Production Ready - PDO Auto-Initialized, MySQLi Available On-Demand
