# Payroll Module: Permission Hierarchy

**Document Version:** 1.0  
**Last Updated:** 2025-11-01  
**Purpose:** Complete reference for payroll module permission system

---

## Permission Model Overview

The payroll module uses a **hierarchical permission model** with granular, resource-specific permissions.

**Namespace:** All permissions use the `payroll.*` prefix  
**Total Permissions:** 22 unique permissions  
**Permission Philosophy:** Principle of least privilege

---

## Permission Hierarchy

```
payroll.admin (TOP LEVEL - Full System Access)
├── Overrides all other permissions
├── Used for: System automation, OAuth setup, admin statistics
└── Routes: 3 (automation process, xero oauth authorize, vend payment stats)

TIER 1: PAYROLL EXECUTION
├── payroll.create_payruns
│   └── Create new pay runs in system
│   └── Routes: 2 (payruns create, xero payrun create)
│
├── payroll.approve_payruns
│   └── Approve finalized pay runs
│   └── Routes: 1 (payruns approve)
│
├── payroll.export_payruns
│   └── Export approved pay runs to Xero
│   └── Routes: 1 (payruns export)
│
└── payroll.view_payruns
    └── View pay run data
    └── Routes: 4 (payruns index, view, list, print)

TIER 2: PAYMENT PROCESSING
├── payroll.create_payments
│   └── Create batch payments in Xero
│   └── Routes: 1 (xero payments batch)
│
└── payroll.approve_vend_payments
    └── Approve/decline Vend payment requests
    └── Routes: 2 (vend-payments approve, decline)

TIER 3: ADJUSTMENTS & AMENDMENTS
├── payroll.approve_amendments
│   └── Approve/decline pay amendments
│   └── Routes: 2 (amendments approve, decline)
│
├── payroll.create_bonus
│   └── Create manual bonuses
│   └── Routes: 1 (bonuses create)
│
├── payroll.approve_bonus
│   └── Approve/decline bonus payments
│   └── Routes: 2 (bonuses approve, decline)
│
└── payroll.approve_leave
    └── Approve/decline leave requests
    └── Routes: 2 (leave approve, decline)

TIER 4: DISCREPANCY MANAGEMENT
├── payroll.submit_discrepancy
│   └── Submit wage discrepancy reports
│   └── Routes: 2 (discrepancies submit, upload-evidence)
│
├── payroll.view_discrepancy
│   └── View discrepancy details
│   └── Routes: 2 (discrepancies get, my-history)
│
└── payroll.manage_discrepancies
    └── Full discrepancy management (approve, decline, view all, stats)
    └── Routes: 5 (pending, approve, decline, statistics)

TIER 5: DASHBOARD & REPORTING
├── payroll.view_dashboard
│   └── Access main payroll dashboard
│   └── Routes: 2 (dashboard index, data)
│
└── payroll.view_reconciliation
    └── Access CIS vs Xero reconciliation tools
    └── Routes: 4 (reconciliation index, dashboard, variances, compare)
```

---

## Permission Reference Table

| Permission | Level | Purpose | Routes | User Types |
|------------|-------|---------|--------|------------|
| `payroll.admin` | 1 | Full system access | 3 | System Admin, Payroll Manager |
| `payroll.create_payruns` | 2 | Create pay runs | 2 | Payroll Officer |
| `payroll.approve_payruns` | 2 | Approve pay runs | 1 | Payroll Manager |
| `payroll.export_payruns` | 2 | Export to Xero | 1 | Payroll Manager |
| `payroll.view_payruns` | 2 | View pay run data | 4 | All Payroll Staff |
| `payroll.create_payments` | 3 | Create Xero payments | 1 | Payroll Officer |
| `payroll.approve_vend_payments` | 3 | Approve Vend payments | 2 | Store Manager, Payroll Manager |
| `payroll.approve_amendments` | 4 | Approve amendments | 2 | Payroll Manager |
| `payroll.create_bonus` | 4 | Create bonuses | 1 | Payroll Officer |
| `payroll.approve_bonus` | 4 | Approve bonuses | 2 | Payroll Manager |
| `payroll.approve_leave` | 4 | Approve leave | 2 | HR Manager, Store Manager |
| `payroll.submit_discrepancy` | 5 | Submit discrepancies | 2 | All Staff (own only) |
| `payroll.view_discrepancy` | 5 | View discrepancies | 2 | All Staff (own only) |
| `payroll.manage_discrepancies` | 4 | Manage all discrepancies | 5 | Payroll Manager, HR Manager |
| `payroll.view_dashboard` | 5 | Access dashboard | 2 | All Payroll Staff |
| `payroll.view_reconciliation` | 5 | View reconciliation | 4 | Payroll Officer, Accountant |

---

## Role-Based Permission Sets

### 1. System Administrator
**Permissions:**
- `payroll.admin` (overrides all)

**Access:**
- Full system access
- All routes available

---

### 2. Payroll Manager (Full Payroll Access)
**Permissions:**
```
payroll.admin
payroll.create_payruns
payroll.approve_payruns
payroll.export_payruns
payroll.view_payruns
payroll.create_payments
payroll.approve_vend_payments
payroll.approve_amendments
payroll.create_bonus
payroll.approve_bonus
payroll.manage_discrepancies
payroll.view_dashboard
payroll.view_reconciliation
```

**Access:**
- Create, approve, export pay runs
- Manage all adjustments (amendments, bonuses)
- Approve Vend payments
- Manage discrepancies
- Full dashboard and reconciliation access

---

### 3. Payroll Officer (Execution Role)
**Permissions:**
```
payroll.create_payruns
payroll.view_payruns
payroll.create_payments
payroll.create_bonus
payroll.view_dashboard
payroll.view_reconciliation
```

**Access:**
- Create pay runs (cannot approve)
- Create bonuses (cannot approve)
- Create Xero payments
- View all payroll data
- Dashboard and reconciliation

**Restrictions:**
- Cannot approve payruns (maker-checker separation)
- Cannot approve bonuses
- Cannot manage discrepancies

---

### 4. Store Manager
**Permissions:**
```
payroll.approve_vend_payments
payroll.approve_leave
payroll.view_dashboard (optional)
```

**Access:**
- Approve/decline Vend payment requests for store
- Approve/decline leave requests for store staff
- View dashboard (if granted)

**Restrictions:**
- No pay run access
- No Xero integration access
- No discrepancy management

---

### 5. HR Manager
**Permissions:**
```
payroll.approve_leave
payroll.manage_discrepancies
payroll.view_dashboard
```

**Access:**
- Approve/decline all leave requests
- Manage wage discrepancies
- View dashboard

**Restrictions:**
- No pay run creation/approval
- No Xero integration
- No bonus/amendment approval

---

### 6. Staff Member (Self-Service)
**Permissions:**
```
payroll.submit_discrepancy
payroll.view_discrepancy
```

**Access:**
- Submit wage discrepancy reports (own only)
- View own discrepancy history
- Upload evidence files

**Restrictions:**
- Cannot view other staff discrepancies
- Cannot approve anything
- No dashboard access
- No pay run visibility

**Controller-Level Logic:**
Routes without explicit `permission` field use auth-only + controller logic:
- Amendments: Staff can view/create own, admins see all
- Bonuses: Staff can view own summary/history, admins see all
- Leave: Staff can create requests, admins approve

---

## Permission Implementation Notes

### Routes WITHOUT Explicit Permissions
Some routes have `auth=true` but no `permission` field:

```php
'GET /api/payroll/amendments/pending' => [
    'auth' => true,
    // No permission field
]
```

**Why:** Controller handles role-based filtering:
- **Staff:** See only their own amendments
- **Admin:** See all pending amendments

**Routes Using This Pattern:**
- Amendments: pending, history
- Bonuses: pending, history, summary, vape-drops, google-reviews
- Vend Payments: pending, history, allocations
- Leave: pending, history, balances

### Maker-Checker Pattern
**Separation of create vs approve permissions:**

```
CREATE              APPROVE
payroll.create_payruns    → payroll.approve_payruns
payroll.create_bonus      → payroll.approve_bonus
payroll.create_payments   → (approved at Xero level)
```

**Rationale:** Prevent single-person fraud. No one can both create and approve their own transactions.

---

## Permission Validation

### Route Middleware
File: `middleware/PermissionMiddleware.php`

**Validation:**
1. User authenticated? (auth check)
2. Route requires permission? (permission field)
3. User has permission? (user.permissions check)
4. Permission format valid? (payroll.* namespace)

### Test Coverage
File: `tests/Unit/RouteDefinitionsTest.php`

**Tests:**
- ✅ All permissions use `payroll.*` prefix
- ✅ All permissions are lowercase with underscores
- ✅ 22 unique permissions defined
- ✅ POST routes have CSRF protection
- ✅ OAuth callback correctly lacks auth

---

## Adding New Permissions

### Step 1: Define Permission
```php
// In routes.php
'POST /api/payroll/new-feature/action' => [
    'controller' => 'NewFeatureController',
    'action' => 'actionMethod',
    'auth' => true,
    'csrf' => true,
    'permission' => 'payroll.new_feature_action', // ← Add here
    'description' => 'Description of action'
]
```

### Step 2: Document Permission
Add to this file:
- Permission hierarchy diagram
- Permission reference table
- Role-based permission sets (if applicable)

### Step 3: Database Migration
```sql
-- Add permission to permissions table (if using database-backed permissions)
INSERT INTO permissions (name, description, module) VALUES 
('payroll.new_feature_action', 'Description of permission', 'payroll');
```

### Step 4: Assign to Roles
```sql
-- Grant to appropriate roles
INSERT INTO role_permissions (role_id, permission_name) VALUES 
(1, 'payroll.new_feature_action'), -- Admin
(2, 'payroll.new_feature_action'); -- Payroll Manager
```

### Step 5: Test Coverage
Update `tests/Unit/RouteDefinitionsTest.php`:
```php
// Update expected permission count
$this->assertEquals(
    23, // Was 22, now 23
    count($uniquePermissions),
    'Expected exactly 23 unique permissions'
);
```

---

## Permission Naming Conventions

### DO ✅
```
✅ payroll.create_payruns       - Clear action
✅ payroll.approve_bonus        - Resource + action
✅ payroll.manage_discrepancies - Broad management permission
✅ payroll.view_dashboard       - Read-only access
```

### DON'T ❌
```
❌ payroll.payruns             - Missing action verb
❌ payroll.CreatePayruns       - Wrong case (use lowercase)
❌ payroll.approve-bonus       - Wrong separator (use underscores)
❌ create_payruns              - Missing namespace prefix
❌ payroll.admin.payruns       - Too many levels
```

### Recommended Patterns
```
payroll.create_*    - Create new resources
payroll.view_*      - Read-only access
payroll.approve_*   - Approval actions
payroll.manage_*    - Full CRUD access
payroll.export_*    - Export/integration actions
payroll.admin       - Full system access
```

---

## Security Best Practices

### 1. Principle of Least Privilege
**Rule:** Grant only the minimum permissions needed for the role.

**Example:**
```
Store Manager:
✅ payroll.approve_vend_payments (needs this for store operations)
❌ payroll.create_payruns (doesn't need full payroll access)
```

### 2. Maker-Checker Separation
**Rule:** Separate create and approve permissions.

**Example:**
```
Payroll Officer:
✅ payroll.create_payruns (can prepare pay runs)
❌ payroll.approve_payruns (cannot approve own work)
```

### 3. Explicit Over Implicit
**Rule:** Sensitive operations require explicit permissions.

**Example:**
```
✅ GOOD:
'POST /api/payroll/xero/payrun/create' => [
    'permission' => 'payroll.create_payruns', // Explicit
]

❌ BAD:
'POST /api/payroll/xero/payrun/create' => [
    // No permission - relies on controller logic
]
```

### 4. Regular Audits
**Schedule:** Review permissions quarterly
- Remove unused permissions
- Verify role assignments
- Check for permission creep
- Update documentation

---

## Troubleshooting

### "Permission Denied" Errors

**Symptom:** User gets 403 Forbidden on route

**Checklist:**
1. ✅ User authenticated? Check session/token
2. ✅ Route requires permission? Check routes.php
3. ✅ User has permission? Check user.permissions
4. ✅ Permission spelling correct? Check for typos

**Debug:**
```php
// In controller
$user = $this->requireAuth();
$requiredPermission = 'payroll.create_payruns';

if (!$user->hasPermission($requiredPermission)) {
    error_log("User {$user->id} missing permission: {$requiredPermission}");
    error_log("User permissions: " . implode(', ', $user->permissions));
}
```

### Permission Not Working After Grant

**Symptom:** Permission granted in database, but user still can't access

**Possible Causes:**
1. **Session cache:** User session still has old permissions. Solution: Log out/log in
2. **Permission cache:** Application caching old permission list. Solution: Clear cache
3. **Typo:** Permission name doesn't match route definition. Solution: Check spelling
4. **Role hierarchy:** User's role doesn't include permission. Solution: Check role_permissions

---

## Migration from Old System

If migrating from a previous permission system:

### Step 1: Map Old Permissions
```
OLD SYSTEM              NEW SYSTEM
admin.payroll          → payroll.admin
payroll.view           → payroll.view_dashboard + payroll.view_payruns
payroll.edit           → payroll.create_payruns + payroll.create_bonus
payroll.approve        → payroll.approve_payruns + payroll.approve_bonus
```

### Step 2: Database Migration
```sql
-- Create temporary mapping table
CREATE TABLE permission_migration (
    old_permission VARCHAR(100),
    new_permission VARCHAR(100)
);

-- Migrate user permissions
INSERT INTO user_permissions (user_id, permission_name)
SELECT up.user_id, pm.new_permission
FROM old_user_permissions up
JOIN permission_migration pm ON pm.old_permission = up.old_permission;
```

### Step 3: Verify Migration
```sql
-- Check for unmapped permissions
SELECT DISTINCT old_permission 
FROM old_user_permissions
WHERE old_permission NOT IN (SELECT old_permission FROM permission_migration);
```

---

## Related Documentation

- **Route Definitions:** See `docs/OBJECTIVE_8_COMPLETE.md` for full route inventory
- **Security Audit:** See `docs/OBJECTIVE_5_COMPLETE.md` for CSRF/auth audit
- **Controller Reference:** See `docs/CONTROLLERS.md` for permission checking implementation
- **Test Suite:** See `tests/Unit/RouteDefinitionsTest.php` for permission validation tests

---

**Document Status:** ✅ COMPLETE  
**Last Review:** 2025-11-01  
**Next Review:** 2026-02-01 (Quarterly)
