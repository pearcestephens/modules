# Payroll Module - CLI Tools Reference

**Branch:** payroll-hardening-20251101
**Last Updated:** 2025-11-02

---

## Available CLI Tools

### 1. Staff Identity Mapping (`map-staff-identity.php`)

Map staff identities between Xero and Vend systems.

```bash
# List all mappings
php cli/map-staff-identity.php --list

# Add new mapping
php cli/map-staff-identity.php --add

# Fix unmapped staff
php cli/map-staff-identity.php --fix
```

**Purpose:** Maintain canonical staff identity mapping for payroll reconciliation.

---

### 2. Reconciliation Runner (`run-reconciliation.php`)

Execute Deputy vs Xero reconciliation for a date range.

```bash
# Run weekly reconciliation
php cli/run-reconciliation.php --start=2025-01-01 --end=2025-01-07

# Run monthly reconciliation
php cli/run-reconciliation.php --start=2025-01-01 --end=2025-01-31
```

**Output:**
- ✅ No variances = Perfect match
- ⚠️ Variances found = Detailed report with staff name, hours, difference

---

### 3. Rate Limit Reporter (`rate-limit-report.php`)

Display rate limit telemetry for Deputy and Xero APIs.

```bash
# Show last 7 days
php cli/rate-limit-report.php --days=7

# Filter by service
php cli/rate-limit-report.php --service=deputy --days=30

# Show last 24 hours
php cli/rate-limit-report.php --days=1
```

**Shows:**
- Service name (deputy, xero)
- Endpoint hit
- HTTP status code
- Retry-After header value
- Timestamp

---

### 4. Activity Log Viewer (`activity-log.php`)

View payroll activity logs with filtering.

```bash
# Last 24 hours
php cli/activity-log.php --hours=24

# Show only errors
php cli/activity-log.php --level=error --hours=72

# Filter by category
php cli/activity-log.php --category=deputy --limit=100

# Show warnings from last 48 hours
php cli/activity-log.php --level=warning --hours=48
```

**Filter Options:**
- `--hours=N` - Time window (default: 24)
- `--level=LEVEL` - info, warning, error
- `--category=CAT` - deputy, xero, recon
- `--limit=N` - Max entries (default: 50)

---

## Common Workflows

### Daily Reconciliation Check
```bash
# Check yesterday's timesheet data
php cli/run-reconciliation.php \
  --start=$(date -d "yesterday" +%Y-%m-%d) \
  --end=$(date +%Y-%m-%d)
```

### Investigate Rate Limit Issues
```bash
# Check if we're hitting rate limits
php cli/rate-limit-report.php --days=7

# If limits found, check activity logs
php cli/activity-log.php --level=warning --category=deputy
```

### Weekly Reconciliation + Report
```bash
# Run full week reconciliation
START=$(date -d "last monday" +%Y-%m-%d)
END=$(date -d "last sunday" +%Y-%m-%d)
php cli/run-reconciliation.php --start=$START --end=$END

# Check for any errors during process
php cli/activity-log.php --level=error --hours=168
```

### Staff Identity Audit
```bash
# List all mappings to verify accuracy
php cli/map-staff-identity.php --list

# Fix any unmapped staff before reconciliation
php cli/map-staff-identity.php --fix
```

---

## Automation Examples

### Cron Jobs

```cron
# Daily reconciliation at 6 AM
0 6 * * * cd /path/to/payroll && php cli/run-reconciliation.php --start=$(date -d "yesterday" +\%Y-\%m-\%d) --end=$(date +\%Y-\%m-\%d) >> /var/log/payroll-recon.log 2>&1

# Weekly rate limit check on Monday morning
0 8 * * 1 cd /path/to/payroll && php cli/rate-limit-report.php --days=7 >> /var/log/payroll-rate-limits.log 2>&1
```

### Shell Script Wrapper

```bash
#!/bin/bash
# payroll-daily-check.sh

PAYROLL_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll"
cd "$PAYROLL_DIR"

echo "=== Daily Payroll Check $(date) ==="

# Run reconciliation
php cli/run-reconciliation.php \
  --start=$(date -d "yesterday" +%Y-%m-%d) \
  --end=$(date +%Y-%m-%d)

# Check for errors
ERROR_COUNT=$(php cli/activity-log.php --level=error --hours=24 | grep -c "ERROR")

if [ "$ERROR_COUNT" -gt 0 ]; then
  echo "⚠️  Found $ERROR_COUNT error(s) in last 24 hours"
  php cli/activity-log.php --level=error --hours=24
fi

# Check rate limits
php cli/rate-limit-report.php --days=1
```

---

## Troubleshooting

### CLI Not Found
```bash
# Ensure you're in the payroll directory
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll

# Verify file exists
ls -la cli/
```

### Permission Denied
```bash
# Make CLI scripts executable
chmod +x cli/*.php
```

### Database Connection Error
```bash
# Verify database credentials in bootstrap/env-loader.php
php -r "require 'bootstrap/env-loader.php'; \$pdo = makeDbConnection(); echo 'Connected OK';"
```

### Missing Tables
```bash
# Run migrations
php cli/run-migrations.php
```

---

## Exit Codes

All CLI tools use standard exit codes:

- **0** - Success
- **1** - Error (with message to STDERR)

This allows for reliable automation and error detection in scripts.

---

**Need Help?**

Add `--help` to any CLI tool for usage instructions:

```bash
php cli/run-reconciliation.php --help
php cli/rate-limit-report.php --help
php cli/activity-log.php --help
php cli/map-staff-identity.php --help
```
