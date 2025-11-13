# 🧪 QUICK TEST GUIDE - CIS V2 Authentication

**Site:** staff.vapeshed.co.nz  
**Status:** ✅ Ready to test

---

## 🚀 QUICK START TESTING

### 1. Test Login (2 minutes)
```
URL: https://staff.vapeshed.co.nz/modules/core/login.php

Test Cases:
✅ Enter valid credentials → Should login successfully
✅ Enter invalid password → Should show error message
✅ Try without CSRF token → Should get HTTP 403 error
```

### 2. Test Logout (30 seconds)
```
URL: https://staff.vapeshed.co.nz/modules/core/logout.php

Test Cases:
✅ Click logout while logged in → Redirects to login
✅ See "Logged out successfully" flash message
✅ Try to access protected page → Redirects to login
```

### 3. Test Change Password (3 minutes)
```
URL: https://staff.vapeshed.co.nz/modules/core/change-password.php

Test Cases:
✅ Enter wrong current password → Error message
✅ Enter weak new password → Validation errors
✅ Watch password strength meter → Updates in real-time
✅ Enter valid passwords → Success, can login with new password
```

### 4. Test Forgot Password (2 minutes)
```
URL: https://staff.vapeshed.co.nz/modules/core/forgot-password.php

Test Cases:
✅ Enter registered email → Success message
✅ Check logs for reset URL (if APP_DEBUG=true)
✅ Token stored in database → Check password_resets table
```

### 5. Test Reset Password (3 minutes)
```
URL: https://staff.vapeshed.co.nz/modules/core/reset-password.php?token=TOKEN

Test Cases:
✅ Valid token → Shows form with user email
✅ Invalid token → Error message
✅ Expired token (>1 hour) → Error message
✅ Used token → Error message (can't reuse)
✅ Enter weak password → Validation errors
✅ Enter strong password → Success, can login with new password
```

---

## 🔍 SECURITY TESTING

### CSRF Protection Test
```bash
# Try to login without CSRF token
curl -X POST https://staff.vapeshed.co.nz/modules/core/login.php \
  -d "email=test@example.com&password=test123"

Expected: HTTP 403 Forbidden
```

### Rate Limiting Test
```bash
# Make 65+ rapid requests
for i in {1..65}; do
  curl -s -o /dev/null -w "%{http_code}\n" \
    https://staff.vapeshed.co.nz/modules/core/login.php
done

Expected: First 60 = 200, rest = 429
```

### Bot Bypass Test (Internal Only)
```bash
# Test with bot bypass header
curl -H "X-Bot-Bypass: c4bcc95c94bd3320fea53038b15cc847174f7c02f128157117118f5defec1ca7" \
  https://staff.vapeshed.co.nz/modules/core/index.php

Expected: Access granted (session created)
```

---

## 📊 PASSWORD REQUIREMENTS

When testing password changes/resets, passwords must have:
- ✅ At least 12 characters
- ✅ At least 1 uppercase letter (A-Z)
- ✅ At least 1 lowercase letter (a-z)
- ✅ At least 1 number (0-9)
- ✅ At least 1 special character (!@#$%^&*)

**Valid Examples:**
- `MyPassword123!`
- `SuperSecure2024#`
- `Testing@12345`

**Invalid Examples:**
- `short` → Too short
- `onlylowercase123!` → Missing uppercase
- `ONLYUPPERCASE123!` → Missing lowercase
- `NoNumbers!` → Missing number
- `NoSpecial123` → Missing special character

---

## 🗄️ DATABASE CHECKS

### Check Password Reset Tokens
```sql
-- See all active tokens
SELECT pr.*, sa.email 
FROM password_resets pr
JOIN staff_accounts sa ON pr.user_id = sa.id
WHERE pr.used_at IS NULL 
  AND pr.expires_at > NOW()
ORDER BY pr.created_at DESC;

-- See used tokens
SELECT pr.*, sa.email
FROM password_resets pr
JOIN staff_accounts sa ON pr.user_id = sa.id
WHERE pr.used_at IS NOT NULL
ORDER BY pr.used_at DESC;
```

### Check Activity Logs
```sql
-- Recent login attempts
SELECT * FROM activity_log 
WHERE action = 'login_attempt'
ORDER BY created_at DESC 
LIMIT 20;

-- Recent password changes
SELECT * FROM activity_log
WHERE action IN ('password_changed', 'password_reset')
ORDER BY created_at DESC
LIMIT 20;
```

---

## 📝 LOG MONITORING

### Watch Request Logs
```bash
tail -f /home/master/applications/jcepnzzkmj/public_html/modules/_logs/requests.log
```

### Watch Activity Logs
```bash
tail -f /home/master/applications/jcepnzzkmj/public_html/modules/_logs/activity.log
```

### Watch Error Logs
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
```

---

## ⚠️ TROUBLESHOOTING

### Issue: "CSRF token invalid"
**Solution:** Clear cookies and refresh page to get new token

### Issue: "Too many requests"
**Solution:** Wait 1 minute for rate limit to reset

### Issue: "Password reset token invalid"
**Solution:** Token may have expired (>1 hour) or been used already

### Issue: Can't login after password change
**Solution:** Make sure new password meets all requirements

### Issue: Email not in database
**Solution:** Check staff_accounts table, verify email is correct

### Issue: Session not persisting
**Solution:** Check session directory permissions and session.save_path

---

## ✅ EXPECTED RESULTS

All tests should:
- ✅ Load without PHP errors
- ✅ Show proper validation messages
- ✅ Redirect correctly after success
- ✅ Display flash messages
- ✅ Log activities to database
- ✅ Respect CSRF protection
- ✅ Respect rate limiting
- ✅ Show password strength indicators
- ✅ Be responsive on mobile

---

## 🎯 TESTING COMPLETE WHEN:

- [ ] Login works with valid credentials
- [ ] Login fails with invalid credentials
- [ ] Logout destroys session properly
- [ ] Change password works with correct current password
- [ ] Change password fails with incorrect current password
- [ ] Forgot password generates token
- [ ] Reset password works with valid token
- [ ] Reset password fails with invalid/expired/used token
- [ ] CSRF protection blocks requests without token
- [ ] Rate limiting blocks excessive requests
- [ ] Password strength indicators work
- [ ] All flash messages display correctly
- [ ] All logs are being written
- [ ] Mobile layout looks good

---

**Testing Time Estimate:** 15-20 minutes for full test suite

**Status:** ✅ READY TO TEST

