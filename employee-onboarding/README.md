# 🚀 UNIVERSAL EMPLOYEE ONBOARDING SYSTEM

## ONE EMPLOYEE SIGNUP → PROVISIONS EVERYWHERE

Create a new employee **ONCE** and automatically provision them across:
- ✅ **CIS** (Central Information System - Master Database)
- ✅ **Xero Payroll NZ** (Payroll & Leave Management)
- ✅ **Deputy** (Timesheet Tracking & Rostering)
- ✅ **Lightspeed/Vend** (POS System Access)

---

## 🎯 FEATURES

### Universal Employee Creation
- **Single Source of Truth**: CIS is the master database
- **Automatic Provisioning**: Syncs to all external systems
- **Rollback Protection**: Failed syncs queued for retry
- **Comprehensive Audit Trail**: Every action logged

### Role-Based Permissions
- **10 Pre-Defined Roles**: Director, Store Manager, Staff, etc.
- **60+ Permissions**: Granular access control
- **Approval Limits**: Role-based spending limits
- **Custom Overrides**: User-specific permission grants/revokes

### Integration Services
- **Xero**: Creates employees, tax info, bank accounts, pay templates
- **Deputy**: Creates employees, links to locations, sets rosters
- **Lightspeed**: Creates POS users with appropriate access levels

### Smart Retry System
- **Auto-Retry Queue**: Failed syncs retry automatically
- **Exponential Backoff**: 5 minute, 15 min, 1 hour, 4 hours, 24 hours
- **Manual Re-Sync**: Trigger re-sync from dashboard
- **Error Tracking**: Detailed error messages for troubleshooting

---

## 📂 FILE STRUCTURE

```
/modules/employee-onboarding/
├── database/
│   └── schema.sql                      # Complete database schema (9 tables)
├── services/
│   ├── UniversalOnboardingService.php  # Main orchestrator
│   ├── XeroEmployeeService.php         # Xero Payroll integration
│   ├── DeputyEmployeeService.php       # Deputy integration
│   └── LightspeedEmployeeService.php   # Lightspeed/Vend integration
├── api/
│   └── onboard.php                     # REST API endpoint
├── onboarding-wizard.php               # Beautiful 5-step wizard UI
├── dashboard.php                       # Employee management dashboard
└── README.md                           # This file
```

---

## 🗄️ DATABASE SCHEMA

### 9 Core Tables

1. **users** - Master employee table (17 fields)
   - Personal info, employment details, system access
   - Status tracking, login tracking
   
2. **roles** - Role definitions (10 pre-seeded)
   - Name, display name, description
   - Hierarchy level, approval limits
   
3. **permissions** - Permission definitions (60+ pre-seeded)
   - Module-based organization
   - Dangerous permission flags
   
4. **role_permissions** - Role → Permissions mapping (many-to-many)

5. **user_roles** - User → Roles mapping (many-to-many)
   - Supports temporary role assignments

6. **external_system_mappings** - Integration linkage
   - Stores external IDs for Xero/Deputy/Lightspeed
   - Sync status tracking
   
7. **onboarding_log** - Complete audit trail
   - Every API call logged
   - Request/response data
   
8. **sync_queue** - Retry queue for failed syncs
   - Priority-based processing
   - Exponential backoff logic
   
9. **user_permissions_override** - User-specific overrides
   - Grant or revoke permissions at user level

### Views

- **vw_users_complete** - Full user data with roles, permissions, external IDs

---

## 🚀 INSTALLATION

### 1. Install Database Schema

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/employee-onboarding
mysql -u jcepnzzkmj -p jcepnzzkmj < database/schema.sql
```

This creates:
- 9 tables
- 1 view
- 1 stored procedure
- Seeds 10 default roles
- Seeds 60+ permissions
- Pre-assigns permissions to roles

### 2. Configure Environment Variables

Add to your `.env` file:

```env
# Deputy API Configuration
DEPUTY_ENDPOINT=vapeshed.au.deputy.com
DEPUTY_TOKEN=your_deputy_api_token_here

# Xero uses existing certificate-based auth
# No new environment variables needed
```

### 3. Access the System

**Onboarding Wizard:**
```
https://staff.vapeshed.co.nz/modules/employee-onboarding/onboarding-wizard.php
```

**Employee Dashboard:**
```
https://staff.vapeshed.co.nz/modules/employee-onboarding/dashboard.php
```

---

## 📋 USAGE

### Creating a New Employee

1. **Navigate to Onboarding Wizard**
   - `onboarding-wizard.php`
   
2. **Step 1: Personal Information**
   - First name, last name (required)
   - Email (required, must be unique)
   - Phone, mobile, date of birth
   
3. **Step 2: Employment Details**
   - Job title, department
   - Start date, employment type (full_time, part_time, casual, contractor)
   - Primary location, manager
   - **Roles** (select at least one)
   
4. **Step 3: System Provisioning**
   - Toggle Xero (recommended)
   - Toggle Deputy (recommended)
   - Toggle Lightspeed (recommended)
   
5. **Step 4: Review & Confirm**
   - Review all entered data
   - Click "Create Employee"
   
6. **Step 5: Complete**
   - View sync results for each system
   - ✅ Success = Employee created and synced
   - ⚠️ Warning = Created in CIS, sync queued for retry
   - ❌ Error = Check logs for details

### API Usage

**POST /modules/employee-onboarding/api/onboard.php**

```javascript
const formData = new FormData();
formData.append('first_name', 'John');
formData.append('last_name', 'Smith');
formData.append('email', 'john.smith@vapeshed.co.nz');
formData.append('job_title', 'Store Manager');
formData.append('start_date', '2025-11-10');
formData.append('location_id', '3');
formData.append('roles[]', '4'); // Store Manager role ID
formData.append('sync_xero', 'on');
formData.append('sync_deputy', 'on');
formData.append('sync_lightspeed', 'on');

fetch('/modules/employee-onboarding/api/onboard.php', {
    method: 'POST',
    body: formData
})
.then(res => res.json())
.then(data => {
    if (data.success) {
        console.log('User ID:', data.user_id);
        console.log('Sync Results:', data.sync_results);
    } else {
        console.error('Error:', data.error);
    }
});
```

**Response:**
```json
{
    "success": true,
    "user_id": 42,
    "message": "Employee onboarded successfully",
    "sync_results": {
        "xero": {
            "status": "success",
            "external_id": "abc-123-def",
            "message": "Successfully created in Xero"
        },
        "deputy": {
            "status": "success",
            "external_id": "456789",
            "message": "Successfully created in Deputy"
        },
        "lightspeed": {
            "status": "success",
            "user_id": "vend-user-123",
            "message": "Successfully created in Lightspeed"
        }
    }
}
```

---

## 👥 PRE-DEFINED ROLES

| Role | Level | Approval Limit | Description |
|------|-------|----------------|-------------|
| Director | 100 | $999,999 | Company Director - Full System Access |
| IT Administrator | 90 | $0 | System administrator |
| Finance Manager | 85 | $10,000 | Financial operations |
| Retail Ops Manager | 80 | $5,000 | Oversees all retail operations |
| Comms Manager | 80 | $5,000 | Marketing and Communications |
| Store Manager | 60 | $2,000 | Manages individual store |
| Assistant Manager | 50 | $500 | Assists store manager |
| Senior Staff | 40 | $200 | Experienced retail staff |
| Staff Member | 30 | $0 | Regular retail staff |
| Casual Staff | 20 | $0 | Casual/part-time staff |

---

## 🔐 PERMISSION MODULES

### System Admin (5 permissions)
- system.admin - Full system access
- system.view_logs - View audit logs
- system.manage_users - Create/edit/delete users
- system.manage_roles - Manage roles
- system.manage_permissions - Assign permissions

### Payroll (9 permissions)
- payroll.view_dashboard
- payroll.approve_amendments
- payroll.approve_discrepancies
- payroll.approve_bonuses
- payroll.approve_vend_payments
- payroll.approve_leave
- payroll.manage_automation
- payroll.xero_admin
- payroll.deputy_admin

### Transfers (6 permissions)
- transfers.create
- transfers.approve_0_2k ($0-$2000)
- transfers.approve_2k_5k ($2000-$5000)
- transfers.approve_5k_plus ($5000+)
- transfers.receive
- transfers.cancel

### Purchase Orders (5 permissions)
- po.create
- po.approve_0_2k
- po.approve_2k_5k
- po.approve_5k_plus
- po.receive

### Consignments (3 permissions)
- consignments.create
- consignments.approve
- consignments.receive

### Inventory (3 permissions)
- inventory.view
- inventory.adjust
- inventory.audit

### Store Reports (3 permissions)
- store_reports.create
- store_reports.view_all
- store_reports.approve

### Staff Accounts (3 permissions)
- staff_accounts.view_own
- staff_accounts.view_all
- staff_accounts.make_payment

### Reports (3 permissions)
- reports.view_basic
- reports.view_advanced
- reports.export

### HR (3 permissions)
- hr.view_staff
- hr.manage_staff
- hr.view_payroll

### Finance (3 permissions)
- finance.view_dashboard
- finance.reconcile
- finance.approve_payments

**Total: 60+ permissions**

---

## 🔄 SYNC RETRY LOGIC

### When Does Retry Happen?

Failed syncs are automatically queued when:
- Network error connecting to external API
- External system returns 5xx error
- External system rate limits (429)
- Timeout during API call

### Retry Schedule

| Attempt | Delay |
|---------|-------|
| 1 | 5 minutes |
| 2 | 15 minutes |
| 3 | 1 hour |
| 4 | 4 hours |
| 5 | 24 hours (final) |

After 5 failed attempts, the sync is marked as `failed` and requires manual intervention.

### Manual Re-Sync

From employee dashboard:
1. Click "View Details" on employee
2. Click "Re-Sync to [System]"
3. System attempts sync immediately

---

## 🛠️ TROUBLESHOOTING

### Employee Created but Sync Failed

**Check `onboarding_log` table:**
```sql
SELECT * FROM onboarding_log 
WHERE user_id = [USER_ID] 
AND status = 'failed' 
ORDER BY created_at DESC;
```

**Common Issues:**
- **Xero**: Invalid IRD number, missing payroll calendar
- **Deputy**: Location mapping not configured
- **Lightspeed**: Outlet ID not found

### Check Retry Queue

```sql
SELECT * FROM sync_queue 
WHERE status IN ('pending', 'failed')
ORDER BY priority ASC, next_retry_at ASC;
```

### Manually Trigger Sync

```php
require_once __DIR__ . '/services/UniversalOnboardingService.php';
use CIS\EmployeeOnboarding\UniversalOnboardingService;

$onboarding = new UniversalOnboardingService($pdo);
$result = $onboarding->updateEmployee($userId, [
    'first_name' => 'Updated Name'
], [
    'sync_xero' => true,
    'sync_deputy' => true,
    'sync_lightspeed' => true
]);
```

---

## 🎨 UI FEATURES

### Onboarding Wizard
- **5 Beautiful Steps**: Personal → Employment → Systems → Review → Complete
- **Visual Progress Indicator**: Shows current step with animations
- **Form Validation**: Real-time validation with error highlighting
- **System Toggles**: Enable/disable each external system
- **Review Screen**: Confirm all data before submission
- **Result Display**: Beautiful success/error display with badge statuses

### Employee Dashboard
- **Card-Based Layout**: Modern, responsive design
- **Sync Status Badges**: Visual indicators for each system
  - ✅ Green = Synced successfully
  - ❌ Red = Sync failed
  - ⏳ Yellow = Pending retry
  - ⊖ Gray = Not enabled
- **Role Display**: Shows all assigned roles as badges
- **Quick Actions**: View details, edit, deactivate

---

## 📊 DATABASE VIEWS & STORED PROCEDURES

### View: vw_users_complete

Combines user data with roles, permissions, and external system mappings.

```sql
SELECT * FROM vw_users_complete WHERE status = 'active';
```

Returns:
- All user fields
- JSON array of roles
- External IDs (xero_id, deputy_id, lightspeed_id)
- Sync statuses for each system

### Stored Procedure: check_user_permission

Check if user has specific permission.

```sql
CALL check_user_permission(42, 'payroll.approve_amendments', @has_permission);
SELECT @has_permission; -- Returns TRUE or FALSE
```

---

## 🚨 SECURITY

### Password Handling
- Passwords hashed with PHP `password_hash()` (bcrypt)
- `must_change_password` flag forces reset on first login

### Permission Checks
- Every API endpoint checks authentication
- Permission checks via `checkPermission()` method
- Admin users bypass permission checks (is_admin = TRUE)

### Audit Logging
- Every onboarding action logged to `onboarding_log`
- Includes IP address, user agent, timestamp
- Request/response data stored as JSON

### Data Sanitization
- All inputs validated before database insertion
- Sensitive data (passwords) removed from logs
- External API errors sanitized before display

---

## 📈 FUTURE ENHANCEMENTS

- [ ] Bulk employee import (CSV/Excel)
- [ ] Employee self-service portal
- [ ] Automated offboarding workflow
- [ ] Integration with Active Directory/LDAP
- [ ] Photo upload for employee profiles
- [ ] Document management (contracts, certifications)
- [ ] Employee performance tracking
- [ ] Automated role assignment based on job title
- [ ] Mobile app for onboarding
- [ ] Integration with recruitment systems

---

## 🤝 SUPPORT

For issues or questions:
- Check `onboarding_log` table for errors
- Review sync queue: `SELECT * FROM sync_queue WHERE status = 'failed'`
- Contact: IT Administrator or System Owner

---

## 📜 LICENSE

Proprietary - Ecigdis Limited / The Vape Shed
For internal use only.

---

**Built with ❤️ for The Vape Shed**
*Making employee onboarding seamless across 17 locations*
