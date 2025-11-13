# 🚀 PAYROLL MODULE - COMPLETE TESTING GUIDE

**Time to Complete:** 5 minutes
**Test Email:** pearcestephens@gmail.com
**Date:** October 31, 2025

---

## 📦 STEP 1: Install Dompdf (30 seconds)

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules
composer install
```

**Expected Output:**
```
Loading composer repositories with package information
Installing dependencies from lock file
  - Installing dompdf/dompdf (v2.0.x)
Generating autoload files
```

**Success Indicator:** `vendor/dompdf/` directory created ✅

---

## 🧪 STEP 2: Run Complete Test Suite (60 seconds)

```bash
cd human_resources/payroll
php tests/test_complete.php
```

**Expected Output:**
```
╔═══════════════════════════════════════════════════════════════╗
║  PAYROLL MODULE - COMPLETE TEST SUITE                         ║
╚═══════════════════════════════════════════════════════════════╝

TEST 1: Email Queue Functions
────────────────────────────────────────────────────────────────
  1a. Getting queue stats... ✅ PASS
  1b. Queueing test email... ✅ PASS (ID: 123)
  1c. Queueing email with PDF attachment... ✅ PASS (ID: 124)
  1d. Verifying stats updated... ✅ PASS

TEST 2: PDF Service
────────────────────────────────────────────────────────────────
  2a. Checking PDF service status... ✅ Dompdf installed
  2b. Generating PDF from HTML... ✅ PASS (12,345 bytes)
  2c. Converting to base64... ✅ PASS (16,460 chars)

TEST 3: PayslipPdfGenerator
────────────────────────────────────────────────────────────────
  3a. Rendering payslip HTML... ✅ PASS
  3b. Converting to PDF bytes... ✅ PASS (15,678 bytes)

TEST 4: PayslipEmailer
────────────────────────────────────────────────────────────────
  4a. Queueing payslip email to pearcestephens@gmail.com... ✅ PASS

╔═══════════════════════════════════════════════════════════════╗
║  ✅ ALL TESTS PASSED!                                         ║
╚═══════════════════════════════════════════════════════════════╝
```

---

## 🌐 STEP 3: Test API Endpoints (30 seconds)

```bash
chmod +x tests/test-endpoints.sh
./tests/test-endpoints.sh
```

**Tests:**
1. PDF API status check → Expect: `{"success":true,"dompdf_installed":true}`
2. Generate simple PDF → Expect: base64 string returned
3. Payslip PDF download → Expect: HTTP 200 or 404 (if no data)
4. Payslip view → Expect: HTTP 200 or 404 (if no data)

---

## 💾 STEP 4: Verify Email Queue (10 seconds)

```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj \
  -e "SELECT id, email_to, subject, status, created_at FROM email_queue ORDER BY id DESC LIMIT 5;"
```

**Expected Output:**
```
+-----+----------------------------+---------------------------+---------+---------------------+
| id  | email_to                   | subject                   | status  | created_at          |
+-----+----------------------------+---------------------------+---------+---------------------+
| 124 | pearcestephens@gmail.com   | Payslip for Jane Smith... | pending | 2025-10-31 10:30:15 |
| 123 | pearcestephens@gmail.com   | Payroll Test with Att...  | pending | 2025-10-31 10:30:12 |
| 122 | pearcestephens@gmail.com   | Payroll Test Email        | pending | 2025-10-31 10:30:10 |
+-----+----------------------------+---------------------------+---------+---------------------+
```

**Success Indicator:** At least 3 entries with status='pending' ✅

---

## 📧 STEP 5: Check Your Email (1-5 minutes)

**To:** pearcestephens@gmail.com
**Wait Time:** 1-5 minutes (cron processes every few minutes)
**Expected Emails:**
1. "Payroll Test Email" (simple test)
2. "Payroll Test with Attachment" (with test PDF)
3. "Payslip for Jane Smith..." (full payslip with real PDF)

**Check:**
- ✅ Emails arrive in inbox
- ✅ PDFs are attached
- ✅ PDFs open correctly
- ✅ Formatting looks good

---

## 🔍 STEP 6: Inspect Test Results

### Check PDF Service Status
```bash
curl -s "https://staff.vapeshed.co.nz/modules/shared/api/pdf.php?action=status" | python3 -m json.tool
```

**Expected:**
```json
{
  "success": true,
  "data": {
    "service": "CIS PDF Service",
    "dompdf_installed": true,
    "version": "2.0.x",
    "fallback_available": true,
    "recommendation": "Service is fully operational"
  }
}
```

### Generate Sample PDF via API
```bash
curl -X POST "https://staff.vapeshed.co.nz/modules/shared/api/pdf.php?action=generate" \
  -H "Content-Type: application/json" \
  -d '{"html":"<h1>API Test</h1><p>Generated via REST API</p>","filename":"api-test.pdf","output":"base64"}' \
  | python3 -m json.tool | head -20
```

**Expected:** JSON with success=true and base64 string

---

## 🎉 SUCCESS CRITERIA

All of these should be true:

- ✅ Dompdf installed in `modules/vendor/dompdf/`
- ✅ `test_complete.php` shows all tests passed
- ✅ PDF API status returns `dompdf_installed: true`
- ✅ Email queue has at least 3 pending entries
- ✅ PDFs are generated (>1000 bytes each)
- ✅ Test emails arrive at pearcestephens@gmail.com
- ✅ PDF attachments open successfully
- ✅ No PHP errors in logs

---

## 🚨 Troubleshooting

### Composer Install Fails

**Problem:** Composer not installed or outdated

**Solution:**
```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
php composer.phar install

# Or update existing Composer
composer self-update
composer install
```

---

### Tests Fail with "Database Connection Error"

**Problem:** MySQL credentials incorrect or server down

**Solution:**
```bash
# Test MySQL connection
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT 1;"

# If fails, check credentials in config/database.php
```

---

### Tests Fail with "Class not found"

**Problem:** Autoloader not generated

**Solution:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules
composer dump-autoload
```

---

### PDF Generation Fails

**Problem:** Dompdf not installed or permissions issue

**Solution:**
```bash
# Check vendor directory exists
ls -la vendor/dompdf/

# Check file permissions
chmod -R 755 shared/services/
chmod -R 755 vendor/

# Reinstall Dompdf
rm -rf vendor/
composer install
```

---

### No Emails Received

**Problem:** Cron job not running or email queue processor disabled

**Solution:**
```bash
# Check cron jobs
crontab -l | grep process-email

# Manually process queue
php /home/master/applications/jcepnzzkmj/public_html/assets/cron/process-email.php

# Check for failed emails
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj \
  -e "SELECT id, email_to, status, last_error FROM email_queue WHERE status='failed';"
```

---

### API Returns 404

**Problem:** Incorrect URL or file permissions

**Solution:**
```bash
# Verify file exists
ls -la /home/master/applications/jcepnzzkmj/public_html/modules/shared/api/pdf.php

# Check Apache config allows .htaccess
# Check file permissions
chmod 644 shared/api/pdf.php
```

---

## 📊 Performance Checks

### Test PDF Generation Speed
```bash
time php -r "
require 'shared/services/PdfService.php';
\$pdf = \CIS\Shared\Services\PdfService::fromHtml('<h1>Test</h1>');
echo 'PDF bytes: ' . strlen(\$pdf->output()) . PHP_EOL;
"
```

**Expected:** < 2 seconds ✅

### Test Email Queue Performance
```bash
time php tests/test_complete.php
```

**Expected:** < 10 seconds ✅

### Check Database Response Time
```bash
time mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj \
  -e "SELECT COUNT(*) FROM email_queue;"
```

**Expected:** < 0.5 seconds ✅

---

## 📚 Additional Resources

- **Complete Documentation:** `OPTION_1_COMPLETE.md`
- **API Reference:** See "API Reference" section in `OPTION_1_COMPLETE.md`
- **Architecture Details:** See "Technical Architecture" section
- **Code Examples:** See test files in `tests/` directory

---

## 🎯 Next Steps After Testing

Once all tests pass:

1. **Create Sample Data:** Add real payslip data to test with actual IDs
2. **Test Browser UI:** Visit `views/payslip.php?id=1`
3. **Test Download:** Click "Download PDF" button
4. **Test Email:** Click "Email Payslip" button
5. **Verify Delivery:** Check inbox for payslip email
6. **Production Deploy:** If all good, mark as production-ready

---

## 📞 Support

**Test Email:** pearcestephens@gmail.com
**Module Path:** `/modules/human_resources/payroll/`
**Database:** jcepnzzkmj (main), dvaxgvsxmz (VapeShed)
**API Endpoint:** `https://staff.vapeshed.co.nz/modules/shared/api/pdf.php`

---

**Status:** ✅ READY FOR TESTING
**Estimated Time:** 5 minutes
**Confidence Level:** HIGH 🚀
**Last Updated:** October 31, 2025
