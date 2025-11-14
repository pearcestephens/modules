# Staff Email Hub - API Reference

Complete API endpoint documentation for all controllers.

---

## Base URL

```
/api/staff-email-hub
or
/staff-email-hub
```

All responses are JSON. See response format at the end of this document.

---

## Email Controller Endpoints

### GET `/emails/inbox`
Retrieve user's inbox with pagination.

**Parameters:**
- `staff_id` (int, required) - Staff member ID
- `page` (int, optional) - Page number, default: 1
- `per_page` (int, optional) - Results per page, default: 50

**Response:**
```json
{
    "status": "success",
    "data": {
        "emails": [
            {
                "id": 1,
                "trace_id": "EMAIL-abc123",
                "from_staff_id": 1,
                "to_email": "customer@example.com",
                "customer_id": 123,
                "subject": "Hello",
                "status": "sent",
                "is_r18_flagged": false,
                "created_at": "2024-01-01T10:00:00Z",
                "sent_at": "2024-01-01T10:05:00Z",
                "tags": ["follow-up", "vip"]
            }
        ],
        "page": 1,
        "per_page": 50,
        "total": 150
    }
}
```

---

### GET `/emails/{id}`
Retrieve single email with full details.

**Parameters:**
- `id` (int, required) - Email ID
- `staff_id` (int, required) - Staff member ID (for permission check)

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 1,
        "trace_id": "EMAIL-abc123",
        "from_staff_id": 1,
        "to_email": "customer@example.com",
        "customer_id": 123,
        "subject": "Hello",
        "body_plain": "Message text",
        "body_html": "<p>Message text</p>",
        "status": "sent",
        "is_r18_flagged": false,
        "assigned_to": 2,
        "tags": ["follow-up"],
        "notes": "Remember to follow up Monday",
        "created_at": "2024-01-01T10:00:00Z",
        "sent_at": "2024-01-01T10:05:00Z",
        "customer": {
            "id": 123,
            "full_name": "John Doe",
            "email": "john@example.com",
            "phone": "09 123 4567",
            "total_spent": 500.00,
            "purchase_count": 5
        }
    }
}
```

---

### POST `/emails`
Create draft email.

**Body Parameters:**
```json
{
    "to_email": "customer@example.com",
    "customer_id": 123,
    "subject": "Order Confirmation",
    "body_plain": "Your order has been confirmed.",
    "body_html": "<p>Your order has been confirmed.</p>",
    "template_id": 5,
    "priority": "normal",
    "tags": ["order", "confirmation"]
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 456,
        "trace_id": "EMAIL-def456",
        "message": "Draft created"
    }
}
```

---

### PUT `/emails/{id}`
Update draft email (only before sending).

**Parameters:**
- `id` (int, required) - Email ID
- `staff_id` (int, required) - Staff member ID (owner check)

**Body Parameters:**
```json
{
    "subject": "Updated Subject",
    "body_plain": "Updated body text",
    "to_email": "newemail@example.com"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Draft updated"
    }
}
```

---

### POST `/emails/{id}/send`
Send queued draft email.

**Parameters:**
- `id` (int, required) - Email ID
- `staff_id` (int, required) - Staff member ID (ownership check)

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Email sent successfully",
        "sent_at": "2024-01-01T10:05:00Z"
    }
}
```

---

### POST `/emails/{id}/assign`
Assign email to staff member.

**Parameters:**
- `id` (int, required) - Email ID
- `assign_to_staff_id` (int, required) - Staff ID to assign to
- `current_staff_id` (int, required) - Current staff ID (creator/admin)

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Email assigned"
    }
}
```

---

### POST `/emails/{id}/flag-r18`
Flag email as R18 restricted.

**Parameters:**
- `id` (int, required) - Email ID
- `staff_id` (int, required) - Staff member ID

**Body Parameters:**
```json
{
    "reason": "Contains restricted product information"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Email flagged as R18"
    }
}
```

---

### POST `/emails/{id}/note`
Add timestamped note to email.

**Parameters:**
- `id` (int, required) - Email ID
- `staff_id` (int, required) - Staff member ID

**Body Parameters:**
```json
{
    "note": "Follow up Monday morning"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Note added"
    }
}
```

---

### DELETE `/emails/{id}`
Soft delete email.

**Parameters:**
- `id` (int, required) - Email ID
- `staff_id` (int, required) - Staff member ID (permission check)

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Email deleted"
    }
}
```

---

### GET `/emails/search`
Search emails with filters.

**Parameters:**
- `query` (string, required) - Search term (min 2 chars)
- `limit` (int, optional) - Results limit, default: 50
- `status` (string, optional) - Filter by status (draft/sent/failed)
- `r18_only` (boolean, optional) - Show only R18 flagged
- `assigned_to` (int, optional) - Staff ID emails assigned to
- `customer_id` (int, optional) - Filter by customer ID
- `date_from` (string, optional) - Start date (YYYY-MM-DD)
- `date_to` (string, optional) - End date (YYYY-MM-DD)
- `sort_by` (string, optional) - Sort order (recent/oldest/priority/subject)

**Response:**
```json
{
    "status": "success",
    "data": {
        "emails": [...],
        "count": 25,
        "query": "order confirmation",
        "page": 1,
        "per_page": 50
    }
}
```

---

### GET `/emails/templates`
List available email templates.

**Response:**
```json
{
    "status": "success",
    "data": {
        "templates": [
            {
                "id": 1,
                "name": "Order Confirmation",
                "category": "orders",
                "subject": "Your order has been confirmed",
                "variables": ["order_number", "customer_name", "total_amount"],
                "is_active": true,
                "usage_count": 150
            }
        ]
    }
}
```

---

### POST `/emails/{id}/apply-template`
Apply template to email with variable substitution.

**Parameters:**
- `id` (int, required) - Email ID
- `template_id` (int, required) - Template ID

**Body Parameters:**
```json
{
    "variables": {
        "order_number": "ORD-12345",
        "customer_name": "John Doe",
        "total_amount": "250.00"
    }
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Template applied"
    }
}
```

---

## Customer Hub Controller Endpoints

### GET `/customers/search`
Search customers with filters.

**Parameters:**
- `query` (string, optional) - Search term
- `limit` (int, optional) - Results limit, default: 50
- `vip_only` (boolean, optional) - Show only VIP
- `flagged_only` (boolean, optional) - Show only flagged
- `id_verified` (boolean, optional) - Show only ID verified
- `min_spent` (number, optional) - Minimum total spent
- `sort_by` (string, optional) - Sort order (recent/spent/purchases/name)

**Response:**
```json
{
    "status": "success",
    "data": {
        "customers": [
            {
                "id": 123,
                "customer_id": "CUST-123",
                "full_name": "John Doe",
                "email": "john@example.com",
                "phone": "09 123 4567",
                "total_spent": 500.00,
                "purchase_count": 5,
                "is_vip": true,
                "is_flagged": false,
                "id_verified": true
            }
        ],
        "count": 1,
        "query": "john",
        "page": 1,
        "per_page": 50
    }
}
```

---

### GET `/customers/{id}`
Get complete customer profile with all related data.

**Parameters:**
- `id` (int, required) - Customer ID

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 123,
        "customer_id": "VEND-123",
        "full_name": "John Doe",
        "email": "john@example.com",
        "phone": "09 123 4567",
        "date_of_birth": "1990-01-01",
        "address": "123 Main St",
        "suburb": "Auckland",
        "postcode": "1010",
        "preferred_contact": "email",
        "total_spent": 500.00,
        "purchase_count": 5,
        "last_purchase_at": "2024-01-15",
        "loyalty_points": 50,
        "is_vip": true,
        "is_flagged": false,
        "flag_reason": null,
        "id_verified": true,
        "id_verified_at": "2024-01-01",
        "notes": "VIP customer, prefers email contact",
        "tags": ["vip-customer", "wholesale"],
        "purchase_history": [
            {
                "vend_sale_id": "SALE-123",
                "outlet_id": 1,
                "sale_date": "2024-01-15",
                "total_amount": 250.00,
                "item_count": 3,
                "items": [...]
            }
        ],
        "communication_log": [
            {
                "communication_type": "email",
                "direction": "outbound",
                "subject": "Order Confirmation",
                "staff_id": 1,
                "created_at": "2024-01-15"
            }
        ],
        "emails": [
            {
                "id": 1,
                "subject": "Order Confirmation",
                "status": "sent",
                "created_at": "2024-01-15"
            }
        ],
        "id_verification": {
            "is_verified": true,
            "status": "verified",
            "score": 95,
            "id_type": "drivers_license",
            "expires_at": "2030-01-01"
        }
    }
}
```

---

### PUT `/customers/{id}`
Update customer profile.

**Parameters:**
- `id` (int, required) - Customer ID

**Body Parameters:**
```json
{
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "09 123 4567",
    "date_of_birth": "1990-01-01",
    "address": "123 Main St",
    "suburb": "Auckland",
    "postcode": "1010",
    "preferred_contact": "email"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Customer profile updated"
    }
}
```

---

### GET `/customers/{id}/emails`
Get all emails for customer.

**Parameters:**
- `id` (int, required) - Customer ID
- `limit` (int, optional) - Results limit, default: 50

**Response:**
```json
{
    "status": "success",
    "data": {
        "emails": [...],
        "count": 5
    }
}
```

---

### GET `/customers/{id}/history`
Get purchase history.

**Parameters:**
- `id` (int, required) - Customer ID
- `limit` (int, optional) - Results limit, default: 100

**Response:**
```json
{
    "status": "success",
    "data": {
        "purchases": [
            {
                "vend_sale_id": "SALE-123",
                "outlet_id": 1,
                "sale_date": "2024-01-15",
                "total_amount": 250.00,
                "item_count": 3,
                "items": [...]
            }
        ],
        "count": 5,
        "total_spent": 500.00,
        "average_order": 100.00,
        "frequency": "frequent"
    }
}
```

---

### GET `/customers/{id}/communications`
Get communication log.

**Parameters:**
- `id` (int, required) - Customer ID
- `limit` (int, optional) - Results limit, default: 100

**Response:**
```json
{
    "status": "success",
    "data": {
        "communications": [
            {
                "communication_type": "email",
                "direction": "outbound",
                "subject": "Order Confirmation",
                "staff_id": 1,
                "tags": ["order"],
                "created_at": "2024-01-15"
            }
        ],
        "by_type": {
            "email": [...],
            "phone": [...],
            "in_person": [...]
        },
        "count": 10
    }
}
```

---

### POST `/customers/{id}/note`
Add note to customer.

**Parameters:**
- `id` (int, required) - Customer ID
- `staff_id` (int, required) - Staff member ID

**Body Parameters:**
```json
{
    "note": "VIP customer, prefers email contact. Follow up next week."
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Note added"
    }
}
```

---

### POST `/customers/{id}/flag`
Flag customer with reason.

**Parameters:**
- `id` (int, required) - Customer ID

**Body Parameters:**
```json
{
    "reason": "Suspected duplicate account or fraudulent activity"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Customer flagged"
    }
}
```

---

### POST `/customers/{id}/unflag`
Remove customer flag.

**Parameters:**
- `id` (int, required) - Customer ID

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Customer flag removed"
    }
}
```

---

### POST `/customers/{id}/vip`
Set VIP status.

**Parameters:**
- `id` (int, required) - Customer ID

**Body Parameters:**
```json
{
    "is_vip": true
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Customer marked as VIP"
    }
}
```

---

### POST `/customers/{id}/tag`
Add tag to customer.

**Parameters:**
- `id` (int, required) - Customer ID

**Body Parameters:**
```json
{
    "tag": "vip-customer"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "Tag added"
    }
}
```

---

### GET `/customers/{id}/id-status`
Get ID verification status.

**Parameters:**
- `id` (int, required) - Customer ID

**Response:**
```json
{
    "status": "success",
    "data": {
        "is_verified": true,
        "status": "verified",
        "score": 95,
        "verified_at": "2024-01-01",
        "verified_by_staff": 1,
        "is_expired": false,
        "expires_at": "2030-01-01",
        "id_type": "drivers_license",
        "created_at": "2024-01-01"
    }
}
```

---

## Search Controller Endpoints

### GET `/search`
Global search across all data.

**Parameters:**
- `query` (string, required) - Search term (min 2 chars)
- `page` (int, optional) - Page number, default: 1
- `per_page` (int, optional) - Results per page, default: 20

**Response:**
```json
{
    "status": "success",
    "data": {
        "query": "john",
        "customers": [...],
        "emails": [...],
        "total_results": 10,
        "page": 1,
        "per_page": 20
    }
}
```

---

### GET `/search/customers`
Search customers with advanced filters.

**Parameters:**
- `query` (string, optional) - Search term
- `limit` (int, optional) - Results limit, default: 50
- `vip_only` (boolean, optional) - Show only VIP
- `flagged_only` (boolean, optional) - Show only flagged
- `id_verified` (boolean, optional) - Show only ID verified
- `min_spent` (number, optional) - Minimum total spent
- `sort_by` (string, optional) - Sort order (recent/spent/purchases/name)

**Response:**
```json
{
    "status": "success",
    "data": {
        "customers": [...],
        "count": 5,
        "query": "john",
        "filters": {...},
        "page": 1,
        "per_page": 50
    }
}
```

---

### GET `/search/emails`
Search emails with advanced filters.

**Parameters:**
- `query` (string, optional) - Search term
- `limit` (int, optional) - Results limit, default: 50
- `status` (string, optional) - Filter by status
- `r18_only` (boolean, optional) - Show only R18 flagged
- `assigned_to` (int, optional) - Staff ID
- `customer_id` (int, optional) - Filter by customer ID
- `date_from` (string, optional) - Start date (YYYY-MM-DD)
- `date_to` (string, optional) - End date (YYYY-MM-DD)
- `sort_by` (string, optional) - Sort order

**Response:**
```json
{
    "status": "success",
    "data": {
        "emails": [...],
        "count": 10,
        "query": "order",
        "filters": {...},
        "page": 1,
        "per_page": 50
    }
}
```

---

### GET `/search/by-email/{email}`
Find customer by email address.

**Parameters:**
- `email` (string, required) - Email address

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 123,
        "full_name": "John Doe",
        "email": "john@example.com",
        ...
    }
}
```

Or if not found:
```json
{
    "status": "success",
    "data": null,
    "message": "Customer not found"
}
```

---

### GET `/search/by-phone/{phone}`
Find customer by phone number.

**Parameters:**
- `phone` (string, required) - Phone number

**Response:** Same as `/search/by-email`

---

### GET `/search/facets`
Get search facets for building UI filters.

**Response:**
```json
{
    "status": "success",
    "data": {
        "email_statuses": [
            {"status": "sent", "count": 500},
            {"status": "draft", "count": 50},
            {"status": "failed", "count": 5}
        ],
        "vip_customers": 25,
        "flagged_customers": 10,
        "id_verified_customers": 250
    }
}
```

---

### GET `/search/recent`
Get recently accessed customers and emails.

**Parameters:**
- `limit` (int, optional) - Results limit, default: 20

**Response:**
```json
{
    "status": "success",
    "data": {
        "recent_customers": [...],
        "recent_emails": [...]
    }
}
```

---

## ID Upload Controller Endpoints

### POST `/id-verification/upload`
Upload ID documents for verification.

**Parameters:**
- `customer_id` (int, required) - Customer ID
- `front_image` (file, required) - Front of ID (jpg, png, webp)
- `back_image` (file, optional) - Back of ID
- `id_type` (string, optional) - ID type (passport/drivers_license/national_id)

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "ID uploaded successfully",
        "trace_id": "ID-abc123",
        "record_id": 456,
        "next_step": "Verification in progress. We will review and confirm within 24 hours."
    }
}
```

---

### GET `/id-verification/status/{customerId}`
Get ID verification status for customer.

**Parameters:**
- `customerId` (int, required) - Customer ID

**Response:**
```json
{
    "status": "success",
    "data": {
        "is_verified": true,
        "status": "verified",
        "score": 95,
        "verified_at": "2024-01-01",
        "verified_by_staff": 1,
        "is_expired": false,
        "expires_at": "2030-01-01",
        "id_type": "drivers_license",
        "created_at": "2024-01-01"
    }
}
```

---

### POST `/id-verification/verify/{recordId}`
Run automatic verification on upload.

**Parameters:**
- `recordId` (int, required) - ID upload record ID

**Response:**
```json
{
    "status": "success",
    "data": {
        "verification_score": 85,
        "is_verified": true,
        "status": "verified",
        "issues": []
    }
}
```

---

### POST `/id-verification/approve/{recordId}`
Approve ID verification (staff action).

**Parameters:**
- `recordId` (int, required) - ID upload record ID
- `staff_id` (int, required) - Staff member ID

**Body Parameters:**
```json
{
    "notes": "Verified manually by staff"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "ID verification approved"
    }
}
```

---

### POST `/id-verification/reject/{recordId}`
Reject ID verification (staff action).

**Parameters:**
- `recordId` (int, required) - ID upload record ID
- `staff_id` (int, required) - Staff member ID

**Body Parameters:**
```json
{
    "reason": "Image too blurry, cannot verify identity"
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "message": "ID verification rejected"
    }
}
```

---

### GET `/id-verification/pending`
List pending ID verifications (staff only).

**Parameters:**
- `page` (int, optional) - Page number, default: 1
- `per_page` (int, optional) - Results per page, default: 20

**Response:**
```json
{
    "status": "success",
    "data": {
        "records": [
            {
                "id": 456,
                "customer_id": 123,
                "full_name": "John Doe",
                "email": "john@example.com",
                "id_type": "drivers_license",
                "verification_status": "pending",
                "created_at": "2024-01-01"
            }
        ],
        "count": 5,
        "total": 12,
        "page": 1,
        "per_page": 20,
        "pages": 2
    }
}
```

---

### POST `/id-verification/check-age/{customerId}`
Check customer age from ID verification.

**Parameters:**
- `customerId` (int, required) - Customer ID

**Response:**
```json
{
    "status": "success",
    "data": {
        "age": 28,
        "is_adult": true,
        "can_purchase_r18": true,
        "dob": "1996-05-15"
    }
}
```

---

### GET `/id-verification/stats`
Get ID verification statistics (admin).

**Response:**
```json
{
    "status": "success",
    "data": {
        "total_uploads": 250,
        "verified": 230,
        "verification_rate": 92.0,
        "pending": 15,
        "rejected": 5,
        "by_id_type": [
            {"id_type": "drivers_license", "count": 180},
            {"id_type": "passport", "count": 60},
            {"id_type": "national_id", "count": 10}
        ]
    }
}
```

---

## Standard Response Format

### Success Response
```json
{
    "status": "success",
    "data": {
        // Endpoint-specific data
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

### HTTP Status Codes
- **200** - Success
- **400** - Bad request (validation error)
- **401** - Unauthorized (not authenticated)
- **403** - Forbidden (no permission)
- **404** - Not found
- **500** - Server error

---

## Authentication

All endpoints require authentication. Include `Authorization` header:

```
Authorization: Bearer {token}
or
Authorization: Staff {staff_id}
```

---

## Rate Limiting

- **Search**: Max 10 requests/minute
- **Email send**: Max 100 requests/hour
- **ID upload**: Max 5 requests/hour
- **Other**: Max 1000 requests/hour

---

## Pagination

Paginated endpoints support:
- `page` - Page number (default: 1)
- `per_page` - Results per page (default: varies, max: 100)

Response includes:
```json
{
    "page": 1,
    "per_page": 50,
    "total": 250,
    "pages": 5
}
```

---

## Filtering

Most search endpoints support filters via query parameters:

```
GET /search/customers?query=john&vip_only=true&sort_by=spent
```

---

## Sorting

Sort options vary by endpoint. Common sorts:
- `recent` - Most recent first
- `oldest` - Oldest first
- `spent` - Highest spending first
- `purchases` - Most purchases first
- `name` - Alphabetical

---

**API Version**: 1.0.0
**Last Updated**: 2024-11-04
**Status**: Production Ready
