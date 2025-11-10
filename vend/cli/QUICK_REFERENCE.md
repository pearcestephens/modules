# Vend Sync Manager - Quick Reference Card

**Version:** 1.0.0
**Location:** `/public_html/modules/vend/cli/vend-sync-manager.php`

---

## 🚀 Most Common Commands

### Daily Sync Operations
```bash
# Sync everything (incremental)
php vend-sync-manager.php sync:all

# Sync specific entity
php vend-sync-manager.php sync:products
php vend-sync-manager.php sync:sales --since=2024-01-01
php vend-sync-manager.php sync:consignments --status=OPEN
```

### Queue Management
```bash
# View queue status
php vend-sync-manager.php queue:stats

# Process failed items
php vend-sync-manager.php queue:process-failed --limit=50

# Clear old items
php vend-sync-manager.php queue:clear --status=success --days=7
```

### Health Checks
```bash
# Quick health check
php vend-sync-manager.php health:check

# API connectivity
php vend-sync-manager.php health:api
```

---

## 📊 Consignment State Machine

### States Flow
```
DRAFT → OPEN → PACKING → PACKAGED → SENT → RECEIVING → PARTIAL/RECEIVED → CLOSED
                                                              ↓
                                                          ARCHIVED
         ↓
    CANCELLED (only from DRAFT/OPEN)
```

### Quick State Rules

| State      | Can Cancel? | Can Edit?           | Next States                |
|------------|-------------|---------------------|----------------------------|
| DRAFT      | ✅ YES      | ✅ 100%             | OPEN, CANCELLED            |
| OPEN       | ✅ YES      | ✅ Add/Remove       | PACKING, CANCELLED         |
| PACKING    | ❌ NO       | ✅ Add/Remove       | PACKAGED                   |
| PACKAGED   | ❌ NO       | ✅ Add only         | SENT                       |
| SENT       | ❌ NO       | ❌ No edits         | RECEIVING                  |
| RECEIVING  | ❌ NO       | ✅ Receive products | PARTIAL, RECEIVED          |
| PARTIAL    | ❌ NO       | ✅ Receive products | RECEIVED                   |
| RECEIVED   | ❌ NO       | ✅ Can amend        | CLOSED                     |
| CLOSED     | ❌ NO       | ❌ No edits         | ARCHIVED                   |

### Consignment Commands
```bash
# Validate state
php vend-sync-manager.php consignment:validate --id=12345

# Change state
php vend-sync-manager.php consignment:transition --id=12345 --to=PACKING

# Cancel (DRAFT/OPEN only!)
php vend-sync-manager.php consignment:cancel --id=12345 --reason="Wrong outlet"

# Show all rules
php vend-sync-manager.php consignment:rules
```

---

## 🎣 Webhook Handling

### Supported Events
```
product.created     sale.created         customer.created
product.updated     sale.updated         customer.updated
product.deleted     consignment.created  inventory.updated
                    consignment.updated
                    consignment.sent
                    consignment.received
```

### Webhook Commands
```bash
# Process webhook (from endpoint)
php vend-sync-manager.php webhook:process --payload='{"event":"product.updated","id":"wh_123","data":{"id":"prod_456"}}'

# Test webhook endpoint
php vend-sync-manager.php webhook:test --url=https://example.com/webhook --event=product.updated

# Simulate locally
php vend-sync-manager.php webhook:simulate --event=consignment.sent

# List events
php vend-sync-manager.php webhook:events
```

---

## 🔍 Troubleshooting

### Check Logs
```bash
# View recent logs
php vend-sync-manager.php audit:logs --entity=product --limit=50

# Check sync status
php vend-sync-manager.php audit:sync-status
```

### Check Failed Items
```bash
# View failed queue items
SELECT entity_type, COUNT(*) as count, MAX(created_at) as latest
FROM vend_queue
WHERE status = 'failed'
GROUP BY entity_type;

# Retry failed items
php vend-sync-manager.php queue:process-failed --limit=100
```

### Check API Connectivity
```bash
# Test connection
php vend-sync-manager.php test:connection

# Test auth
php vend-sync-manager.php test:auth
```

---

## ⚙️ Configuration

### Required Config
```sql
-- Check API token
SELECT * FROM configuration WHERE key = 'vend_access_token';

-- Or environment variable
export VEND_API_TOKEN="your-token-here"
```

### Database Tables
- **vend_products** - Products sync
- **vend_sales** - Sales transactions
- **vend_consignments** - Consignment tracking
- **vend_inventory** - Stock levels
- **vend_queue** - Sync queue (98K+ items)
- **vend_api_logs** - Audit trail
- **vend_sync_cursors** - Incremental sync tracking

---

## 🚨 Emergency Procedures

### System Down
```bash
# 1. Check health
php vend-sync-manager.php health:check --verbose

# 2. Check database
php vend-sync-manager.php health:database

# 3. Check API
php vend-sync-manager.php health:api

# 4. Review recent errors
php vend-sync-manager.php audit:logs --limit=100 | grep ERROR
```

### Queue Stuck
```bash
# 1. Check queue stats
php vend-sync-manager.php queue:stats

# 2. Process failed items
php vend-sync-manager.php queue:process-failed

# 3. If necessary, reset queue
php vend-sync-manager.php queue:clear --status=processing --days=1
```

### Full Resync Needed
```bash
# Reset cursor
php vend-sync-manager.php util:cursor --entity=products --reset

# Full sync
php vend-sync-manager.php sync:products --full

# Or sync everything
php vend-sync-manager.php sync:all --full
```

---

## 📞 Contact & Escalation

### Quick Support
- **Documentation**: `/modules/vend/cli/VEND_SYNC_USAGE.md`
- **Deployment Guide**: `/modules/vend/cli/DEPLOYMENT_CHECKLIST.md`
- **Setup Script**: `/modules/vend/cli/setup.sql`

### Issue Reporting
When reporting issues, include:
1. Command that failed
2. Error message
3. Output from `health:check`
4. Recent log entries (`audit:logs`)
5. Queue status (`queue:stats`)

### Performance Benchmarks
- **Products sync**: ~9K records in <60s
- **Sales sync**: 100K records in <5min
- **Queue processing**: 1000 items/min
- **API calls**: <500ms average

---

## 🎯 Best Practices

### ✅ DO
- Run `sync:all` daily via cron
- Monitor queue stats weekly
- Clear old success logs monthly
- Test state transitions in dev first
- Use `--dry-run` for state changes
- Check health before major syncs

### ❌ DON'T
- Cancel consignments after PACKING
- Run full sync during business hours
- Process queue manually if cron running
- Edit SENT/RECEIVING consignments directly
- Delete queue items without investigating
- Bypass state machine validation

---

## 📅 Maintenance Schedule

### Daily (Automated)
```bash
0 */6 * * * /usr/bin/php /path/to/vend-sync-manager.php sync:all
```

### Weekly (Manual)
```bash
# Check failed items
php vend-sync-manager.php queue:stats

# Clear old success logs
php vend-sync-manager.php queue:clear --status=success --days=30
```

### Monthly (Manual)
```bash
# Archive old audit logs
# Review sync performance
php vend-sync-manager.php audit:sync-status
```

---

**Quick Help:**
```bash
php vend-sync-manager.php help
```

**Version Info:**
```bash
php vend-sync-manager.php util:version
```

---

*Generated by Vend Sync Manager v1.0.0*
*Last Updated: 2024*
