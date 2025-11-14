# Staff Email Hub Module

**A professional web-based email client + all-in-one customer management system for Vape Shed staff**

---

## Overview

The Staff Email Hub combines a powerful email client with comprehensive customer relationship management. Staff can:

- **Send/Manage Emails** - Draft, send, assign, and track emails with full audit trails
- **View Customer History** - Complete purchase history, communication logs, and profile data
- **Verify ID Documents** - Upload and verify customer IDs with OCR and automatic verification
- **Search Everything** - Full-text search across customers and emails company-wide
- **Apply Templates** - Quick email templates with variable substitution
- **Flag Content** - R18 product flagging, customer flagging, and notes system
- **Track Interactions** - Every customer interaction logged and searchable

---

## Architecture

### Directory Structure

```
staff-email-hub/
├── Core/              # Domain logic and business rules
├── Services/          # Business logic services
├── Controllers/       # HTTP request handlers
├── Models/           # Data models and entities
├── Database/         # Migrations and database schema
├── Templates/        # Email templates
├── Views/           # Blade template views
├── Assets/
│   ├── css/         # Stylesheets
│   └── js/          # JavaScript/AJAX
├── Helpers/         # Utility functions
├── Events/          # Event listeners
├── Middleware/      # Request middleware
├── Contracts/       # Interfaces
└── README.md        # This file
```

### Database Schema

**11 Core Tables:**

1. **staff_emails** - Email storage with full metadata
2. **staff_email_templates** - Reusable email templates
3. **email_attachments** - Email attachments
4. **customer_hub_profile** - Customer master profile
5. **customer_id_uploads** - ID verification uploads
6. **customer_purchase_history** - Purchase transactions
7. **customer_communication_log** - Communication timeline
8. **customer_search_index** - Full-text search index for customers
9. **email_search_index** - Full-text search index for emails
10. **email_automation_rules** - Workflow automation rules
11. **id_verification_audit_log** - ID verification audit trail

---

## Services

### StaffEmailService

Professional email client functionality.

```php
$emailService = new StaffEmailService($pdo);

// Create draft
$emailService->createDraft($staffId, [
    'to_email' => 'customer@example.com',
    'customer_id' => 123,
    'subject' => 'Hello',
    'body_plain' => 'Message body',
    'template_id' => 5
]);

// Get inbox
$emailService->getInbox($staffId, $page = 1, $perPage = 50);

// Send email
$emailService->sendEmail($emailId, $staffId);

// Apply template
$emailService->applyTemplate($emailId, $templateId, [
    'customer_name' => 'John'
]);

// Flag as R18
$emailService->flagR18($emailId, 'Contains restricted product info');

// Add note
$emailService->addNote($emailId, 'Follow up Monday');

// Assign to staff
$emailService->assignEmail($emailId, $assignToStaffId);
```

### CustomerHubService

Complete customer relationship management.

```php
$customerService = new CustomerHubService($pdo);

// Get complete customer profile
$profile = $customerService->getCustomerProfile($customerId);
// Returns: name, contact, address, purchase_count, total_spent,
// id_verified status, VIP status, flags, notes, tags, last interaction

// Get purchase history
$customerService->getPurchaseHistory($customerId, $limit = 50);

// Get communication log
$customerService->getCommunicationLog($customerId, $limit = 100);

// Add note
$customerService->addNote($customerId, 'VIP customer, prefers email', $staffId);

// Flag customer
$customerService->flagCustomer($customerId, 'Under investigation');

// Set VIP status
$customerService->setVIP($customerId, true);

// Add tag
$customerService->addTag($customerId, 'vip-customer');

// Record communication
$customerService->recordCommunication($customerId, [
    'communication_type' => 'email',
    'direction' => 'outbound',
    'subject' => 'Order Confirmation'
]);

// Get ID verification status
$customerService->getIdVerificationStatus($customerId);
```

### SearchService

Full-text and advanced search across all data.

```php
$searchService = new SearchService($pdo);

// Global search
$searchService->globalSearch('John Smith');

// Search customers
$searchService->searchCustomers('John', $limit = 50, [
    'vip_only' => true,
    'id_verified' => true,
    'min_spent' => 100,
    'sort_by' => 'spent'
]);

// Search emails
$searchService->searchEmails('order confirmation', $limit = 50, [
    'status' => 'sent',
    'date_from' => '2024-01-01',
    'date_to' => '2024-01-31',
    'sort_by' => 'recent'
]);

// Find customer by email
$searchService->findByEmail('john@example.com');

// Find customer by phone
$searchService->findByPhone('09 123 4567');

// Find customer by Vend ID
$searchService->findByVendId('VEND-12345');

// Get search facets for UI
$searchService->getFacets();
```

### IDVerificationService

Secure ID upload and verification with OCR.

```php
$idService = new IDVerificationService($pdo);

// Upload ID images
$idService->uploadIdImage($customerId, $frontImageArray, $backImageArray, 'drivers_license');

// Process OCR
$idService->processOCR($recordId, $imagePath);

// Verify identity (automatic)
$idService->verifyIdentity($recordId, $customerId);

// Check age
$ageData = $idService->checkAge($customerId);
// Returns: age, is_adult, dob

// Check expiry
$idService->checkExpiry($recordId);

// Get verification status
$idService->getVerificationStatus($customerId);

// Approve (staff)
$idService->approveVerification($recordId, $staffId, 'Verified manually');

// Reject (staff)
$idService->rejectVerification($recordId, $staffId, 'Blurry image');
```

---

## Controllers

### EmailController

Handle email CRUD, sending, and related actions.

**Routes:**
```
GET    /emails/inbox                    - List user's inbox
GET    /emails/{id}                     - View single email
POST   /emails                          - Create draft
PUT    /emails/{id}                     - Update draft
POST   /emails/{id}/send                - Send email
POST   /emails/{id}/assign              - Assign to staff
POST   /emails/{id}/flag-r18            - Flag as R18
POST   /emails/{id}/note                - Add note
POST   /emails/{id}/delete              - Delete email
GET    /emails/search                   - Search emails
GET    /emails/templates                - List templates
POST   /emails/{id}/apply-template      - Apply template
```

### CustomerHubController

Customer profile and management.

**Routes:**
```
GET    /customers/search                - Search customers
GET    /customers/{id}                  - Get profile
PUT    /customers/{id}                  - Update profile
GET    /customers/{id}/emails           - Get customer's emails
GET    /customers/{id}/history          - Get purchase history
GET    /customers/{id}/communications   - Get communication log
POST   /customers/{id}/note             - Add note
POST   /customers/{id}/flag             - Flag customer
POST   /customers/{id}/unflag           - Remove flag
POST   /customers/{id}/vip              - Set VIP status
POST   /customers/{id}/tag              - Add tag
GET    /customers/{id}/id-status        - Get ID status
```

### IDUploadController

ID verification uploads and workflow.

**Routes:**
```
POST   /id-verification/upload          - Upload ID
GET    /id-verification/status/{id}     - Check status
POST   /id-verification/verify/{id}     - Run verification
POST   /id-verification/approve/{id}    - Approve (staff)
POST   /id-verification/reject/{id}     - Reject (staff)
GET    /id-verification/pending         - List pending
POST   /id-verification/check-age/{id}  - Check age
```

### SearchController

Global and advanced search.

**Routes:**
```
GET    /search                          - Global search
GET    /search/customers                - Search customers
GET    /search/emails                   - Search emails
GET    /search/facets                   - Get facets
GET    /search/by-email/{email}         - Find by email
GET    /search/by-phone/{phone}         - Find by phone
GET    /search/by-vend-id/{id}          - Find by Vend ID
GET    /search/recent                   - Get recent items
GET    /search/top                      - Get top customers
```

---

## Features

### Email Client

- **Draft Management** - Create, edit, and send email drafts
- **Template Support** - Apply reusable templates with variable substitution
- **Pagination** - Efficiently browse large inboxes
- **Full Search** - Find emails by subject, content, recipient, date range
- **Status Tracking** - Track email lifecycle (draft→sent→delivered)
- **Assignment** - Assign emails to staff members for follow-up
- **R18 Flagging** - Flag sensitive emails for restricted products
- **Notes** - Add timestamped notes to any email

### Customer Hub

- **Complete Profile** - All customer data in one view
- **Purchase History** - Full transaction history with items and amounts
- **Communication Log** - All interactions (email, phone, in-person, system)
- **Contact Information** - Phone, email, address, preferences
- **VIP Status** - Mark important customers
- **Customer Flags** - Flag for alerts and special handling
- **Notes** - Add timestamped notes (attributed to staff)
- **Tags** - Categorize customers (vip-customer, wholesale, etc.)

### ID Verification

- **Secure Upload** - Upload front and back ID images
- **OCR Processing** - Extract text using Tesseract OCR
- **Auto-Verification** - Validate extracted data against profile
- **Fraud Detection** - Detect tampering, quality issues
- **Age Verification** - Check if customer is 18+
- **Expiry Checking** - Track ID expiration dates
- **Manual Approval** - Staff can approve/reject verifications
- **Audit Logging** - Full audit trail of all verification actions

### Search

- **Full-Text Search** - Search customers and emails by any field
- **Advanced Filters** - Filter by status, date range, VIP, flags, etc.
- **Faceted Navigation** - Build filter UI from search facets
- **Quick Lookups** - Find customer by email, phone, or Vend ID
- **Recent Items** - Quickly access recently viewed customers/emails
- **Top Customers** - View top customers by various metrics

---

## Security

### Privacy & Audit

- ✅ **Audit Logging** - All sensitive actions logged with timestamps and actor
- ✅ **Access Control** - Role-based access control (staff, admin, customer)
- ✅ **Data Encryption** - IDs stored securely, PII protected
- ✅ **PII Redaction** - Sensitive data redacted from logs
- ✅ **Trace IDs** - All operations trackable via trace IDs
- ✅ **GDPR Compliant** - Designed with privacy by default

### Input Validation

- ✅ **Prepared Statements** - All SQL queries use prepared statements
- ✅ **File Upload Validation** - Image MIME type and size validation
- ✅ **Field Validation** - Email format, phone format, date format
- ✅ **Length Limits** - Fields have appropriate length constraints

---

## Installation

### 1. Database Setup

```bash
mysql -u root -p your_database < Database/migrations_staff_email_hub.sql
```

### 2. Service Registration

```php
// In your application bootstrap:
use StaffEmailHub\Services\{
    StaffEmailService,
    CustomerHubService,
    SearchService,
    IDVerificationService
};

$container->bind(StaffEmailService::class, function ($app) {
    return new StaffEmailService($app->get('pdo'));
});

// ... register other services
```

### 3. Controller Registration

```php
// Register controllers in your routing
$router->group(['prefix' => '/staff-email-hub'], function ($router) {
    $router->get('/emails/inbox', 'EmailController@getInbox');
    $router->get('/customers/{id}', 'CustomerHubController@getProfile');
    // ... register other routes
});
```

---

## Usage Examples

### Workflow 1: Send Email to Customer

```php
$emailController = new EmailController($pdo);

// Create draft
$draft = $emailController->createDraft($staffId, [
    'to_email' => 'john@example.com',
    'subject' => 'Order Confirmation',
    'body_plain' => 'Your order has been confirmed',
    'template_id' => 5  // Use template
]);

// Send it
$sent = $emailController->sendEmail($draft['data']['id'], $staffId);
```

### Workflow 2: Look up Customer and View History

```php
$searchController = new SearchController($pdo);
$customerController = new CustomerHubController($pdo);

// Find customer
$customer = $searchController->findByEmail('john@example.com');

// Get profile with all data
$profile = $customerController->getProfile($customer['data']['id']);

// Get purchase history
$history = $customerController->getPurchaseHistory($customer['data']['id']);

// Get communications
$comms = $customerController->getCommunications($customer['data']['id']);
```

### Workflow 3: Verify Customer ID

```php
$idController = new IDUploadController($pdo);

// Customer uploads ID
$upload = $idController->upload($customerId, $_FILES['front_id'], $_FILES['back_id']);

// Run verification
$verification = $idController->runVerification($upload['data']['record_id']);

// Check age
$age = $idController->checkAge($customerId);
if ($age['data']['is_adult']) {
    // Allow R18 products
}
```

### Workflow 4: Search Across System

```php
$searchController = new SearchController($pdo);

// Global search
$results = $searchController->globalSearch('John');

// Search customers with filters
$vips = $searchController->searchCustomers('', 50, ['vip_only' => true]);

// Search emails by date
$recent = $searchController->searchEmails('order', 50, [
    'date_from' => '2024-01-01',
    'sort_by' => 'recent'
]);
```

---

## API Response Format

All endpoints return a consistent JSON response format:

### Success Response
```json
{
    "status": "success",
    "data": {
        "id": 123,
        "name": "John Doe",
        // ... endpoint-specific data
    }
}
```

### Error Response
```json
{
    "status": "error",
    "error": "Error message describing what went wrong",
    "code": 400
}
```

---

## Performance Optimization

### Indexes

The database schema includes 17 optimized indexes:

- Full-text indexes on search fields for fast queries
- Composite indexes on frequently filtered columns
- Foreign key indexes for relationship queries
- Date/status indexes for time-range searches

### Query Optimization

- Pagination limits prevent memory overflow
- Lazy loading of related data
- Prepared statements prevent query analysis overhead
- Connection pooling support ready

---

## Testing

### Unit Tests Location

```
_tests/StaffEmailHub/Services/
_tests/StaffEmailHub/Controllers/
```

### Test Examples

```php
// Test email creation
$emailService->createDraft($staffId, [
    'to_email' => 'test@example.com',
    'subject' => 'Test',
    'body_plain' => 'Test body'
]);

// Test customer lookup
$searchService->findByEmail('test@example.com');

// Test ID verification
$idService->verifyIdentity($recordId, $customerId);
```

---

## Troubleshooting

### Email Not Sending

1. Check mail configuration in config/email.php
2. Verify SMTP credentials in .env
3. Check logs: `tail -f /var/log/cis/staff-email-hub.log`
4. Check trace ID in database for specific email

### OCR Not Processing

1. Check Tesseract installed: `which tesseract`
2. Check image quality (min 200x200px)
3. Check disk space for temp files
4. Check logs for OCR errors

### Search Not Finding Results

1. Minimum query length is 2 characters
2. Check if search indexes are populated
3. Try rebuilding search indexes
4. Check full-text search configuration

---

## Future Enhancements

- [ ] Email templates with conditional blocks
- [ ] Automated email campaigns and sequences
- [ ] SMS integration for customer communication
- [ ] Voice call logging and recording (where legal)
- [ ] AI-powered customer sentiment analysis
- [ ] Email scheduling and batch sending
- [ ] Document storage and attachments
- [ ] Customer portal for account management
- [ ] Integration with Vend API for real-time sync
- [ ] Advanced reporting and analytics

---

## Version History

### v1.0.0 (Current)
- Initial release
- Email client with full CRUD
- Customer hub with complete profile
- ID verification with OCR
- Full-text search
- 11 database tables with 17 indexes
- 5 core services
- 4 controllers
- Comprehensive audit logging

---

## License

Proprietary - Ecigdis Limited

---

## Support

For issues or feature requests, contact the development team at:
- **Email**: dev@ecigdis.co.nz
- **Slack**: #staff-email-hub
- **Wiki**: https://wiki.vapeshed.co.nz/staff-email-hub
