# Staff Email Hub - Integration Guide

Quick start guide for integrating Staff Email Hub into your CIS application.

---

## 1. Database Installation

### Create Tables

```bash
# From project root:
mysql -u root -p your_database < modules/staff-email-hub/Database/migrations_staff_email_hub.sql
```

### Verify Installation

```sql
-- Check tables created
SHOW TABLES LIKE 'staff_emails%';
SHOW TABLES LIKE 'customer_%';
SHOW TABLES LIKE 'email_%';
SHOW TABLES LIKE 'id_%';

-- Should show 11 tables total
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = 'your_database'
AND table_name LIKE 'staff_emails%'
OR table_name LIKE 'customer_%'
OR table_name LIKE 'email_%'
OR table_name LIKE 'id_%';
```

---

## 2. Service Registration (Dependency Injection)

### Option A: Laravel Container

```php
// In: app/Providers/StaffEmailHubServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use StaffEmailHub\Services\{
    StaffEmailService,
    CustomerHubService,
    SearchService,
    IDVerificationService
};

class StaffEmailHubServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(StaffEmailService::class, function () {
            return new StaffEmailService($this->app['db']->connection()->getPdo());
        });

        $this->app->singleton(CustomerHubService::class, function () {
            return new CustomerHubService($this->app['db']->connection()->getPdo());
        });

        $this->app->singleton(SearchService::class, function () {
            return new SearchService($this->app['db']->connection()->getPdo());
        });

        $this->app->singleton(IDVerificationService::class, function () {
            return new IDVerificationService(
                $this->app['db']->connection()->getPdo(),
                storage_path('app/id-uploads')
            );
        });
    }
}
```

Register in `config/app.php`:
```php
'providers' => [
    // ...
    App\Providers\StaffEmailHubServiceProvider::class,
],
```

### Option B: Manual Instantiation

```php
// In your controller or service:
use StaffEmailHub\Services\StaffEmailService;

$emailService = new StaffEmailService($pdo);
$result = $emailService->getInbox($staffId);
```

---

## 3. Routing Setup

### Option A: Laravel Routes

```php
// In: routes/api.php or routes/web.php

Route::middleware(['auth:staff', 'verified'])->group(function () {

    // Email routes
    Route::get('/emails/inbox', 'StaffEmailHub\EmailController@getInbox');
    Route::get('/emails/{id}', 'StaffEmailHub\EmailController@getEmail');
    Route::post('/emails', 'StaffEmailHub\EmailController@createDraft');
    Route::put('/emails/{id}', 'StaffEmailHub\EmailController@updateDraft');
    Route::post('/emails/{id}/send', 'StaffEmailHub\EmailController@sendEmail');
    Route::post('/emails/{id}/assign', 'StaffEmailHub\EmailController@assignEmail');
    Route::post('/emails/{id}/flag-r18', 'StaffEmailHub\EmailController@flagR18');
    Route::post('/emails/{id}/note', 'StaffEmailHub\EmailController@addNote');
    Route::delete('/emails/{id}', 'StaffEmailHub\EmailController@deleteEmail');
    Route::get('/emails/search', 'StaffEmailHub\EmailController@search');
    Route::get('/emails/templates', 'StaffEmailHub\EmailController@getTemplates');

    // Customer routes
    Route::get('/customers', 'StaffEmailHub\CustomerHubController@listAll');
    Route::get('/customers/search', 'StaffEmailHub\CustomerHubController@search');
    Route::get('/customers/{id}', 'StaffEmailHub\CustomerHubController@getProfile');
    Route::put('/customers/{id}', 'StaffEmailHub\CustomerHubController@updateProfile');
    Route::get('/customers/{id}/emails', 'StaffEmailHub\CustomerHubController@getEmails');
    Route::get('/customers/{id}/history', 'StaffEmailHub\CustomerHubController@getPurchaseHistory');
    Route::get('/customers/{id}/communications', 'StaffEmailHub\CustomerHubController@getCommunications');
    Route::post('/customers/{id}/note', 'StaffEmailHub\CustomerHubController@addNote');
    Route::post('/customers/{id}/flag', 'StaffEmailHub\CustomerHubController@flagCustomer');
    Route::post('/customers/{id}/unflag', 'StaffEmailHub\CustomerHubController@unflagCustomer');
    Route::post('/customers/{id}/vip', 'StaffEmailHub\CustomerHubController@setVIP');
    Route::post('/customers/{id}/tag', 'StaffEmailHub\CustomerHubController@addTag');

    // Search routes
    Route::get('/search', 'StaffEmailHub\SearchController@globalSearch');
    Route::get('/search/customers', 'StaffEmailHub\SearchController@searchCustomers');
    Route::get('/search/emails', 'StaffEmailHub\SearchController@searchEmails');
    Route::get('/search/facets', 'StaffEmailHub\SearchController@getFacets');
    Route::get('/search/by-email/{email}', 'StaffEmailHub\SearchController@findByEmail');
    Route::get('/search/by-phone/{phone}', 'StaffEmailHub\SearchController@findByPhone');
    Route::get('/search/recent', 'StaffEmailHub\SearchController@getRecent');

    // ID verification routes
    Route::post('/id-verification/upload', 'StaffEmailHub\IDUploadController@upload');
    Route::get('/id-verification/status/{customerId}', 'StaffEmailHub\IDUploadController@getStatus');
    Route::post('/id-verification/verify/{recordId}', 'StaffEmailHub\IDUploadController@runVerification');
    Route::post('/id-verification/approve/{recordId}', 'StaffEmailHub\IDUploadController@approveVerification');
    Route::post('/id-verification/reject/{recordId}', 'StaffEmailHub\IDUploadController@rejectVerification');
    Route::get('/id-verification/pending', 'StaffEmailHub\IDUploadController@getPending');
    Route::post('/id-verification/check-age/{customerId}', 'StaffEmailHub\IDUploadController@checkAge');

});
```

### Option B: Custom Router

```php
// In your routing middleware:
$router->group(['prefix' => 'staff-email-hub'], function($router) {

    // Parse endpoint from GET param or route
    $endpoint = $_GET['endpoint'] ?? $_POST['endpoint'] ?? null;
    $method = $_SERVER['REQUEST_METHOD'];

    // Route to appropriate controller based on endpoint
    if (strpos($endpoint, 'email') === 0) {
        $controller = new EmailController($pdo);
    } elseif (strpos($endpoint, 'customer') === 0) {
        $controller = new CustomerHubController($pdo);
    } elseif (strpos($endpoint, 'search') === 0) {
        $controller = new SearchController($pdo);
    } elseif (strpos($endpoint, 'id-verify') === 0) {
        $controller = new IDUploadController($pdo);
    }

    // Call appropriate method
    $action = str_replace('-', '', $endpoint);
    if (method_exists($controller, $action)) {
        echo json_encode($controller->$action($_GET, $_POST, $_FILES));
    }

});
```

---

## 4. Authentication & Authorization

### Middleware Example

```php
// In: app/Http/Middleware/StaffEmailHubAuth.php

namespace App\Http\Middleware;

use Closure;

class StaffEmailHubAuth
{
    public function handle($request, Closure $next)
    {
        // Verify user is staff
        if (!auth()->check() || auth()->user()->role !== 'staff') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if staff has email hub access
        if (!auth()->user()->hasPermission('email-hub.access')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
```

### Role-Based Access

```php
// In controller:
if ($email['from_staff_id'] !== auth()->id() &&
    !auth()->user()->hasRole('admin')) {
    return $this->error('Access denied', 403);
}
```

---

## 5. Configuration

### Environment Variables (.env)

```bash
# Email configuration
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=noreply@vapeshed.co.nz
MAIL_FROM_NAME="Vape Shed"

# Storage configuration
ID_UPLOAD_PATH=/var/storage/id-uploads
ID_UPLOAD_MAX_SIZE=5242880  # 5MB in bytes

# OCR configuration
TESSERACT_PATH=/usr/bin/tesseract
OCR_ENABLED=true

# Search configuration
SEARCH_MIN_LENGTH=2
SEARCH_MAX_RESULTS=100

# Verification configuration
ID_VERIFY_MIN_SCORE=70  # Percentage score to auto-approve
VERIFY_EXPIRY_YEARS=10  # Standard ID expiry period
```

### PHP Configuration

Create `config/staff-email-hub.php`:

```php
return [
    'services' => [
        'email' => [
            'queue' => env('QUEUE_DRIVER', 'sync'),
            'batch_size' => 50,
            'retry_failed' => true,
            'max_retries' => 3,
        ],
        'id_verification' => [
            'ocr_enabled' => env('OCR_ENABLED', true),
            'min_quality_score' => 70,
            'storage_path' => env('ID_UPLOAD_PATH', storage_path('id-uploads')),
        ],
        'search' => [
            'min_query_length' => env('SEARCH_MIN_LENGTH', 2),
            'max_results' => env('SEARCH_MAX_RESULTS', 100),
            'facet_limit' => 10,
        ]
    ]
];
```

---

## 6. Data Integration

### Sync with Vend API

```php
// Periodically sync customer data from Vend:
use Modules\Vend\Services\CustomerService as VendCustomerService;
use StaffEmailHub\Services\CustomerHubService;

$vendService = new VendCustomerService($pdo);
$hubService = new CustomerHubService($pdo);

// Get all customers from Vend
$vendCustomers = $vendService->getAllCustomers();

foreach ($vendCustomers as $vendCustomer) {
    // Check if customer exists in hub
    $hubCustomer = $hubService->findByVendId($vendCustomer['id']);

    if (!$hubCustomer['success']) {
        // Create new customer
        $this->db->prepare("
            INSERT INTO customer_hub_profile
            (vend_customer_id, full_name, email, phone, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([
            $vendCustomer['id'],
            $vendCustomer['name'],
            $vendCustomer['email'],
            $vendCustomer['phone']
        ]);
    }
}
```

### Sync Purchase History

```php
// Sync sales data from Vend:
$sales = $vendService->getAllSales();

foreach ($sales as $sale) {
    // Find customer
    $customer = $hubService->findByVendId($sale['customer_id']);

    if ($customer['success']) {
        // Record purchase
        $this->db->prepare("
            INSERT INTO customer_purchase_history
            (customer_id, vend_sale_id, outlet_id, sale_date, total_amount,
             item_count, items_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $customer['customer']['id'],
            $sale['id'],
            $sale['outlet_id'],
            $sale['date'],
            $sale['total'],
            count($sale['items']),
            json_encode($sale['items'])
        ]);
    }
}
```

---

## 7. Frontend Integration

### AJAX Example

```html
<!-- Search Customers -->
<form id="customer-search">
    <input type="text" name="query" placeholder="Search customers...">
    <button type="submit">Search</button>
</form>

<script>
$('#customer-search').on('submit', function(e) {
    e.preventDefault();

    $.ajax({
        url: '/api/customers/search',
        data: {
            query: $('input[name=query]').val(),
            vip_only: $('#vip-checkbox').is(':checked')
        },
        success: function(response) {
            if (response.status === 'success') {
                // Display results
                displayCustomers(response.data.customers);
            } else {
                alert('Error: ' + response.error);
            }
        }
    });
});
</script>
```

### Display Customer Profile

```html
<div id="customer-profile" style="display:none;">
    <h2>{{customer.full_name}}</h2>
    <p>Email: {{customer.email}}</p>
    <p>Phone: {{customer.phone}}</p>
    <p>Total Spent: ${{customer.total_spent}}</p>
    <p>Purchases: {{customer.purchase_count}}</p>

    <button onclick="sendEmail({{customer.id}})">Send Email</button>
    <button onclick="viewHistory({{customer.id}})">View History</button>
    <button onclick="verifyID({{customer.id}})">Verify ID</button>
</div>
```

---

## 8. Testing Integration

### Simple Test Script

```php
// test_integration.php
<?php
require_once 'vendor/autoload.php';

use StaffEmailHub\Services\{
    StaffEmailService,
    CustomerHubService,
    SearchService
};

// Get database connection
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');

// Test email service
$emailService = new StaffEmailService($pdo);
$inbox = $emailService->getInbox(1); // Staff ID 1
echo "Inbox: " . count($inbox['emails']) . " emails\n";

// Test customer service
$customerService = new CustomerHubService($pdo);
$profile = $customerService->getCustomerProfile(1); // Customer ID 1
echo "Customer: " . $profile['customer']['full_name'] . "\n";

// Test search
$searchService = new SearchService($pdo);
$results = $searchService->searchCustomers('John');
echo "Search: " . count($results['customers']) . " customers found\n";

echo "✓ Integration test passed!\n";
```

Run test:
```bash
php test_integration.php
```

---

## 9. Performance Tuning

### Enable Query Caching

```php
// In your PDO connection:
$pdo = new PDO('mysql:host=localhost', 'user', 'pass', [
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
```

### Add Indexes for Your Queries

```sql
-- If you added custom columns, add indexes:
CREATE INDEX idx_customer_created_at ON customer_hub_profile(created_at DESC);
CREATE INDEX idx_email_status_created ON staff_emails(status, created_at DESC);
CREATE FULLTEXT INDEX idx_customer_search ON customer_hub_profile(full_name, email, notes);
```

### Use Pagination

```php
// Always paginate results:
$results = $emailService->getInbox($staffId, $page = 1, $perPage = 20);
```

---

## 10. Troubleshooting

### Check Database Tables

```sql
-- Verify all tables created
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'your_db'
AND TABLE_NAME LIKE 'staff_%'
OR TABLE_NAME LIKE 'customer_%';
```

### Check Service Loading

```php
// Test service instantiation
try {
    $service = new StaffEmailService($pdo);
    echo "✓ Service loaded successfully\n";
} catch (Exception $e) {
    echo "✗ Service load failed: " . $e->getMessage() . "\n";
}
```

### Check Permissions

```bash
# Check storage directory permissions
ls -la /var/storage/id-uploads

# Should be: drwxr-x--- (750)
# If not, fix: chmod 750 /var/storage/id-uploads
```

### View Error Logs

```bash
# Check PHP error log
tail -f /var/log/php/errors.log

# Check application logs
tail -f /var/log/cis/staff-email-hub.log
```

---

## Next Steps

1. ✅ **Create database tables** - Run migration SQL
2. ✅ **Register services** - Add to dependency injection container
3. ✅ **Setup routing** - Add API routes
4. ✅ **Configure authentication** - Protect endpoints
5. ✅ **Build frontend** - Create UI views
6. ✅ **Test endpoints** - Verify all working
7. ✅ **Deploy** - Push to production
8. ✅ **Monitor** - Track performance and errors

---

## Support

For integration help:
- **Documentation**: `/modules/staff-email-hub/README.md`
- **Examples**: `/modules/staff-email-hub/examples/`
- **Tests**: `/modules/staff-email-hub/_tests/`
- **Issues**: GitHub Issues for your project
