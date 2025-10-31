# Purchase Orders - Admin Configuration

Admin-only tools for configuring and managing the Purchase Orders system.

---

## 📋 Admin Pages

### Approval Thresholds Configuration
**File:** `approval-thresholds.php`
**Access:** Admin only (403 error for non-admins)
**URL:** `/modules/consignments/purchase-orders/admin/approval-thresholds.php`

Configure the multi-tier approval system that determines who needs to approve purchase orders based on their total value.

#### Features

**1. Default Threshold Configuration**
- Configure 5 approval tiers with customizable:
  - Min/Max amount ranges ($)
  - Required number of approvers (1-10)
  - Approved-by roles (manager, finance, admin)
- System-wide defaults that apply to all outlets
- Multi-select role assignment per tier

**2. Outlet-Specific Overrides**
- Create custom threshold configurations for specific outlets
- Useful for:
  - Franchise locations with different approval policies
  - High-volume outlets with higher thresholds
  - Special locations requiring stricter controls
- Each override has same 5-tier structure as defaults
- Edit/delete existing overrides
- Bootstrap modal UI for adding new overrides

**3. Test Calculator**
- Real-time preview of approval requirements
- Input any dollar amount
- Select outlet (optional, defaults to system-wide)
- Shows:
  - Which tier applies
  - Amount range
  - Required approver count
  - Approved-by roles
- Sticky sidebar for quick access

**4. Help & Documentation**
- Inline help explaining how system works
- Usage instructions
- Best practices

#### Default Tier Configuration

Out of the box, the system comes with these defaults:

| Tier | Amount Range | Required Approvers | Approved-By Roles |
|------|--------------|-------------------|-------------------|
| 1 | $0 - $1,000 | 1 | Manager |
| 2 | $1,000 - $2,500 | 1 | Manager, Finance |
| 3 | $2,500 - $5,000 | 2 | Manager, Finance |
| 4 | $5,000 - $10,000 | 2 | Finance, Admin |
| 5 | $10,000+ | 3 | Admin |

These can be customized to match your organization's policies.

#### Database Tables

**system_config**
- Stores default threshold configuration
- Key: 'approval_thresholds'
- Value: JSON-encoded tier config
- Tracks who updated and when

**approval_threshold_overrides**
- Stores outlet-specific overrides
- Unique constraint on outlet_id
- JSON column for threshold data
- Tracks created_by, updated_by, timestamps

#### API Endpoints

The configuration UI is backed by RESTful API endpoints:

**GET /api/purchase-orders/thresholds.php**
- Retrieve default or outlet-specific thresholds
- Query param: `outlet_id` (optional)
- Public access (any authenticated user)

**POST /api/purchase-orders/thresholds.php**
- Save default thresholds
- Admin only
- Payload: `{ "thresholds": {...5 tiers...} }`

**PUT /api/purchase-orders/thresholds.php**
- Save outlet-specific override
- Admin only
- Payload: `{ "outlet_id": "uuid", "thresholds": {...} }`

**DELETE /api/purchase-orders/thresholds.php**
- Remove outlet override
- Admin only
- Query param: `outlet_id`

#### How Approval Routing Works

When a purchase order is created:

1. System looks up outlet's threshold configuration
   - First checks for outlet-specific override
   - Falls back to default configuration if no override

2. Finds the tier matching the PO's total cost
   - Checks min_amount <= total_cost <= max_amount
   - Uses tier with lowest number that matches

3. Creates approval requests based on tier config
   - Required approvers count from tier
   - Assigns to users with specified roles
   - Sets tier number for tracking

4. As approvals come in:
   - Tracks progress (X of Y approvers)
   - When all required approvals received → auto-approves PO
   - State changes: PENDING_APPROVAL → APPROVED → SUBMITTED

#### Access Control

- **Page access:** Admin role required
- **API modifications:** POST/PUT/DELETE require admin role
- **API reads:** Any authenticated user can GET thresholds
- **Navigation:** "Configure Thresholds" button only visible to admins on approval dashboard

#### Navigation

Quick access from:
- Approval Dashboard: "Configure Thresholds" button (top-right, admin only)
- Direct URL: `/modules/consignments/purchase-orders/admin/approval-thresholds.php`

---

## 🔧 Future Admin Pages

Placeholder for additional admin configuration pages:

- [ ] **Email Template Editor** - Customize supplier notification emails
- [ ] **User Role Management** - Assign/revoke approver roles
- [ ] **Delegation Rules** - Auto-delegate during leave/absence
- [ ] **Performance Dashboard** - Approval turnaround metrics
- [ ] **Audit Log Viewer** - System-wide change history
- [ ] **Bulk Operations** - Mass update PO settings
- [ ] **Integration Settings** - Lightspeed API configuration

---

## 📁 File Structure

```
admin/
├── README.md (this file)
├── approval-thresholds.php (750 lines)
└── [future admin pages]
```

---

## 🔗 Related Files

- **Services:** `lib/Services/ApprovalService.php` (threshold lookup logic)
- **API:** `api/purchase-orders/thresholds.php` (RESTful CRUD)
- **Migration:** `database/migrations/2025-10-31-approval-thresholds.sql`
- **Frontend:** `approvals/dashboard.php` (user-facing approval UI)

---

## 📝 Notes

- All admin pages should check for admin role on page load
- Use consistent UI patterns (Bootstrap 5, same header/footer)
- Log all configuration changes for audit trail
- Test calculator is JavaScript-only (no page reload)
- Form validation uses browser native + server-side checks
- UPSERT pattern used for safe database updates

---

**Last Updated:** October 31, 2025
**Author:** AI Assistant
**Version:** 1.0.0
