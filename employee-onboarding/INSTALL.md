# 🚀 INSTALLATION GUIDE - Universal Employee Onboarding

## Step 1: Install Database Schema

Run this command (you'll be prompted for MySQL password):

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/employee-onboarding
mysql -u jcepnzzkmj -p jcepnzzkmj < database/schema.sql
```

This creates:
- ✅ 9 tables (users, roles, permissions, etc.)
- ✅ 10 pre-defined roles (Director, Manager, Staff, etc.)
- ✅ 60+ permissions
- ✅ 1 view (vw_users_complete)
- ✅ 1 stored procedure (check_user_permission)

## Step 2: Add Yourself as Director

Run this command:

```bash
php add-pearce.php
```

This creates your account with:
- **Username:** pearce
- **Password:** changeme123 (CHANGE THIS!)
- **Role:** Director (Full System Access)
- **Email:** pearce.stephens@ecigdis.co.nz

## Step 3: Login & Change Password

1. Login at: https://staff.vapeshed.co.nz/login.php
2. **IMMEDIATELY change your password!**

## Step 4: Start Onboarding Employees!

Access the wizard at:
```
https://staff.vapeshed.co.nz/modules/employee-onboarding/onboarding-wizard.php
```

View all employees at:
```
https://staff.vapeshed.co.nz/modules/employee-onboarding/dashboard.php
```

---

## Optional: Configure Deputy Integration

Add to `.env` file:

```bash
echo "DEPUTY_ENDPOINT=vapeshed.au.deputy.com" >> ../../.env
echo "DEPUTY_TOKEN=your_token_here" >> ../../.env
```

---

## Troubleshooting

### "Access denied" when installing schema
Make sure you have the correct MySQL password. Try:
```bash
mysql -u jcepnzzkmj -p
# Enter password when prompted
```

### "Tables already exist"
If you need to reinstall, drop the tables first:
```bash
mysql -u jcepnzzkmj -p jcepnzzkmj -e "DROP TABLE IF EXISTS sync_queue, onboarding_log, external_system_mappings, user_permissions_override, user_roles, role_permissions, permissions, roles, users;"
```

Then run the schema.sql again.

### Can't login after creating account
Make sure:
1. Session handling is working
2. The login.php file exists
3. Your account was created (check: `SELECT * FROM users WHERE email = 'pearce.stephens@ecigdis.co.nz'`)

---

## 🎉 You're Ready!

Once installed, you can:
- ✅ Add employees in ONE form
- ✅ Auto-provision to Xero, Deputy, Lightspeed
- ✅ Manage 60+ permissions across 10 roles
- ✅ Track sync status with visual badges
- ✅ View complete audit trail

**ONE EMPLOYEE SIGNUP → PROVISIONS EVERYWHERE!** 🌟
