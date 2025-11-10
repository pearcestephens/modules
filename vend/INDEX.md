# Vend Sync Manager - Complete Index

**Navigation hub for all system documentation and code**

---

## 🚀 I want to...

### Get Started
- **Install the system** → [DEPLOYMENT_CHECKLIST.md](cli/DEPLOYMENT_CHECKLIST.md) + [setup.sql](cli/setup.sql)
- **Learn basic commands** → [QUICK_REFERENCE.md](cli/QUICK_REFERENCE.md)
- **Understand the system** → [README.md](README.md) → [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md)
- **See architecture** → [ARCHITECTURE.md](ARCHITECTURE.md)

### Use the CLI
- **Run my first command** → [QUICK_REFERENCE.md § Quick Start](cli/QUICK_REFERENCE.md#most-common-commands)
- **Sync products** → `php vend-sync-manager.php sync:products`
- **Sync everything** → `php vend-sync-manager.php sync:all`
- **Check system health** → `php vend-sync-manager.php health:check`
- **View all commands** → `php vend-sync-manager.php help`
- **Complete CLI guide** → [VEND_SYNC_USAGE.md](cli/VEND_SYNC_USAGE.md)

### Use the API
- **See API documentation** → [API_DOCUMENTATION.md](api/API_DOCUMENTATION.md)
- **Get authentication token** → [API_DOCUMENTATION.md § Authentication](api/API_DOCUMENTATION.md#authentication)
- **See API examples** → [API_DOCUMENTATION.md § Integration Examples](api/API_DOCUMENTATION.md#integration-examples)
- **Test API endpoint** → `curl -X POST 'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=version' -H 'Authorization: Bearer TOKEN'`
- **Process webhook** → [API_DOCUMENTATION.md § Webhook Operations](api/API_DOCUMENTATION.md#webhook-operations)

### Manage Consignments
- **Understand state machine** → [QUICK_REFERENCE.md § Consignment State Machine](cli/QUICK_REFERENCE.md#consignment-state-machine)
- **Validate consignment** → `php vend-sync-manager.php consignment:validate --id=12345`
- **Change state** → `php vend-sync-manager.php consignment:transition --id=12345 --to=PACKING`
- **Cancel consignment** → `php vend-sync-manager.php consignment:cancel --id=12345`
- **View state rules** → `php vend-sync-manager.php consignment:rules`
- **Business rules** → [VEND_SYNC_USAGE.md § Consignment State Machine](cli/VEND_SYNC_USAGE.md#consignment-state-machine)

### Manage Queue
- **View queue stats** → `php vend-sync-manager.php queue:stats`
- **Process queue** → `php vend-sync-manager.php queue:process`
- **Retry failed items** → `php vend-sync-manager.php queue:process-failed`
- **Clear old items** → `php vend-sync-manager.php queue:clear --status=success --days=30`
- **Queue management guide** → [VEND_SYNC_USAGE.md § Queue Commands](cli/VEND_SYNC_USAGE.md#queue-commands)

### Process Webhooks
- **Process webhook** → `php vend-sync-manager.php webhook:process --payload='...'`
- **Test webhook** → `php vend-sync-manager.php webhook:test --url=... --event=...`
- **Simulate webhook** → `php vend-sync-manager.php webhook:simulate --event=product.updated`
- **List events** → `php vend-sync-manager.php webhook:events`
- **Webhook guide** → [VEND_SYNC_USAGE.md § Webhook Commands](cli/VEND_SYNC_USAGE.md#webhook-commands)
- **API webhook processing** → [API_DOCUMENTATION.md § Webhook Operations](api/API_DOCUMENTATION.md#webhook-operations)

### Troubleshoot
- **Quick troubleshooting** → [QUICK_REFERENCE.md § Troubleshooting](cli/QUICK_REFERENCE.md#troubleshooting)
- **Emergency procedures** → [QUICK_REFERENCE.md § Emergency Procedures](cli/QUICK_REFERENCE.md#emergency-procedures)
- **Check health** → `php vend-sync-manager.php health:check`
- **View logs** → `php vend-sync-manager.php audit:logs --limit=50`
- **Check sync status** → `php vend-sync-manager.php audit:sync-status`
- **Common issues** → [README.md § Common Issues](README.md#common-issues)

### Deploy
- **Deployment checklist** → [DEPLOYMENT_CHECKLIST.md](cli/DEPLOYMENT_CHECKLIST.md)
- **Setup database** → [setup.sql](cli/setup.sql)
- **Configure token** → [DEPLOYMENT_CHECKLIST.md § Configuration](cli/DEPLOYMENT_CHECKLIST.md#configuration)
- **Setup cron jobs** → [DEPLOYMENT_CHECKLIST.md § Cron Setup](cli/DEPLOYMENT_CHECKLIST.md#cron-setup)
- **Post-deployment** → [DEPLOYMENT_CHECKLIST.md § Post-Deployment](cli/DEPLOYMENT_CHECKLIST.md#post-deployment-verification)

### Learn
- **System overview** → [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md)
- **Architecture diagrams** → [ARCHITECTURE.md](ARCHITECTURE.md)
- **File structure** → [FILE_STRUCTURE.md](FILE_STRUCTURE.md)
- **Complete usage guide** → [VEND_SYNC_USAGE.md](cli/VEND_SYNC_USAGE.md)
- **API reference** → [API_DOCUMENTATION.md](api/API_DOCUMENTATION.md)

---

## 📚 Documentation Index

### Quick Reference
| Document | Size | Purpose | Audience |
|----------|------|---------|----------|
| [README.md](README.md) | 300+ lines | System overview & entry point | Everyone |
| [QUICK_REFERENCE.md](cli/QUICK_REFERENCE.md) | 300+ lines | One-page cheat sheet | Everyone |
| [INDEX.md](INDEX.md) | This file | Navigation hub | Everyone |

### Complete Guides
| Document | Size | Purpose | Audience |
|----------|------|---------|----------|
| [VEND_SYNC_USAGE.md](cli/VEND_SYNC_USAGE.md) | 500+ lines | Complete CLI usage guide | Developers, Ops |
| [API_DOCUMENTATION.md](api/API_DOCUMENTATION.md) | 550+ lines | RESTful API reference | Developers |
| [DEPLOYMENT_CHECKLIST.md](cli/DEPLOYMENT_CHECKLIST.md) | 400+ lines | Step-by-step deployment | DevOps |

### System Documentation
| Document | Size | Purpose | Audience |
|----------|------|---------|----------|
| [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) | 400+ lines | Complete system overview | Management, Leads |
| [ARCHITECTURE.md](ARCHITECTURE.md) | 400+ lines | Visual architecture diagrams | Architects |
| [FILE_STRUCTURE.md](FILE_STRUCTURE.md) | 300+ lines | File organization & metrics | Developers |

### Setup & Configuration
| Document | Size | Purpose | Audience |
|----------|------|---------|----------|
| [setup.sql](cli/setup.sql) | 300+ lines | Database setup script | DevOps, DBAs |

---

## 💻 Code Index

### Main Systems
| File | Lines | Purpose | Status |
|------|-------|---------|--------|
| [cli/vend-sync-manager.php](cli/vend-sync-manager.php) | 3,519 | Main CLI system | ✅ Complete |
| [api/sync.php](api/sync.php) | 450+ | JSON API endpoint | ✅ Complete |

### Classes (in vend-sync-manager.php)
| Class | Lines | Purpose |
|-------|-------|---------|
| CLIOutput | ~200 | Beautiful terminal output |
| ConfigManager | ~150 | CIS config integration |
| LightspeedAPIClient | ~400 | API client with 15+ methods |
| DatabaseManager | ~300 | Batch operations & queries |
| SyncEngine | ~600 | 10 entity sync handlers |
| QueueManager | ~300 | Queue operations |
| AuditLogger | ~200 | Logging with correlation IDs |
| WebhookProcessor | ~350 | 12 webhook event handlers |
| ConsignmentStateManager | ~400 | 11-state machine |

### Legacy Code
| File | Lines | Status |
|------|-------|--------|
| [cli/lightspeed-cli.php](cli/lightspeed-cli.php) | 814 | ⚠️ Superseded by vend-sync-manager.php |

---

## 🎯 Command Index

### Sync Commands (10)
```bash
sync:products [--full] [--since=DATE]     # Sync products
sync:sales [--full] [--since=DATE]        # Sync sales
sync:customers [--full]                   # Sync customers
sync:inventory [--outlet=ID]              # Sync inventory
sync:consignments [--full] [--status=X]   # Sync consignments
sync:outlets                              # Sync outlets
sync:categories                           # Sync categories
sync:registers                            # Sync registers
sync:payment-types                        # Sync payment types
sync:taxes                                # Sync taxes
sync:all [--full]                         # Sync everything
```

### Queue Commands (7)
```bash
queue:stats                               # View queue statistics
queue:view [--entity=TYPE] [--status=X]   # View queue items
queue:process [--limit=N]                 # Process queue
queue:process-failed [--limit=N]          # Retry failed items
queue:clear --status=X --days=N           # Clear old items
queue:retry --id=N                        # Retry specific item
queue:delete --id=N                       # Delete queue item
```

### Test Commands (2)
```bash
test:connection                           # Test API connection
test:auth                                 # Test authentication
```

### Consignment Commands (4)
```bash
consignment:validate --id=N               # Validate consignment
consignment:transition --id=N --to=STATE  # Change state
consignment:cancel --id=N [--reason=""]   # Cancel consignment
consignment:rules                         # Display state rules
```

### Webhook Commands (4)
```bash
webhook:process --payload='JSON'          # Process webhook
webhook:test --url=URL [--event=X]        # Test webhook
webhook:simulate --event=X                # Simulate webhook
webhook:events                            # List supported events
```

### Health Commands (3)
```bash
health:check [--verbose]                  # Full health check
health:api                                # Check API connectivity
health:database                           # Check database
```

### Audit Commands (2)
```bash
audit:logs [--entity=TYPE] [--limit=N]    # View audit logs
audit:sync-status                         # View sync statistics
```

### Utility Commands (2)
```bash
util:cursor [--entity=TYPE] [--reset]     # Manage sync cursors
util:version                              # Show version info
```

### Help Commands (1)
```bash
help                                      # Show all commands
```

---

## 🌐 API Endpoint Index

### Sync Operations
- `?action=sync&entity=products` - Sync specific entity
- `?action=sync_all` - Sync all entities

### Queue Operations
- `?action=queue_stats` - Queue statistics
- `?action=queue_process` - Process queue
- `?action=queue_failed` - Retry failed items

### Webhook Operations
- `?action=webhook_process` - Process webhook
- `?action=webhook_events` - List events

### Consignment Operations
- `?action=consignment_validate&id=N` - Validate consignment
- `?action=consignment_transition&id=N&to=STATE` - Change state

### Health Operations
- `?action=health` - Health check
- `?action=health_api` - API connectivity

### Audit Operations
- `?action=audit_logs` - View logs
- `?action=audit_status` - Sync status

### Utility Operations
- `?action=version` - Version info

---

## 🗄️ Database Index

### Shadow Tables (Vend Sync)
| Table | Records | Purpose |
|-------|---------|---------|
| vend_products | 9,006 | Product catalog |
| vend_sales | 1,715,800 | Sales transactions |
| vend_sales_line_items | 2,770,072 | Line items |
| vend_customers | 98,462 | Customer data |
| vend_inventory | 189,293 | Stock levels |
| vend_consignments | 24,454 | Transfer tracking |
| vend_product_qty_history | 80,027,741 | Stock history |
| vend_outlets | ~20 | Store locations |
| vend_categories | ~100 | Product categories |
| vend_registers | ~30 | POS terminals |
| vend_payment_types | ~10 | Payment methods |
| vend_taxes | ~5 | Tax configurations |
| + 16 more tables | Various | Other entities |

### System Tables
| Table | Records | Purpose |
|-------|---------|---------|
| vend_queue | 98,859 | Sync queue |
| vend_api_logs | Variable | Audit logs |
| vend_sync_cursors | 10+ | Incremental sync tracking |
| configuration | 1+ | API token storage |

### CIS Native Tables
| Table | Purpose |
|-------|---------|
| consignment_* | Native consignment data |
| product_* | Native product data |
| sales_* | Native sales data |
| customer_* | Native customer data |

---

## 🔍 Search Index

### By Topic

#### Installation
- [DEPLOYMENT_CHECKLIST.md](cli/DEPLOYMENT_CHECKLIST.md)
- [setup.sql](cli/setup.sql)
- [README.md § Installation](README.md#installation)

#### Configuration
- [DEPLOYMENT_CHECKLIST.md § Configuration](cli/DEPLOYMENT_CHECKLIST.md)
- [VEND_SYNC_USAGE.md § Configuration](cli/VEND_SYNC_USAGE.md#configuration)
- [API_DOCUMENTATION.md § Authentication](api/API_DOCUMENTATION.md#authentication)

#### Usage
- [QUICK_REFERENCE.md](cli/QUICK_REFERENCE.md)
- [VEND_SYNC_USAGE.md](cli/VEND_SYNC_USAGE.md)
- [API_DOCUMENTATION.md](api/API_DOCUMENTATION.md)

#### Troubleshooting
- [QUICK_REFERENCE.md § Troubleshooting](cli/QUICK_REFERENCE.md#troubleshooting)
- [README.md § Common Issues](README.md#common-issues)
- [VEND_SYNC_USAGE.md § Troubleshooting](cli/VEND_SYNC_USAGE.md#troubleshooting)

#### Architecture
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [SYSTEM_SUMMARY.md § Architecture](SYSTEM_SUMMARY.md#architecture)
- [FILE_STRUCTURE.md](FILE_STRUCTURE.md)

#### Development
- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md)
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [vend-sync-manager.php](cli/vend-sync-manager.php)

#### API Integration
- [API_DOCUMENTATION.md](api/API_DOCUMENTATION.md)
- [sync.php](api/sync.php)

---

## 📊 Statistics Index

### Code Statistics
- **Total Production Code:** 3,969 lines
- **Total Documentation:** 3,150+ lines
- **Total System:** 7,119+ lines
- **Classes:** 9
- **Commands:** 39
- **API Endpoints:** 15+

### Database Statistics
- **Total Tables:** 28 Vend tables
- **Total Records:** 84M+ across all tables
- **Largest Table:** vend_product_qty_history (80M records)
- **Queue Items:** 98,859 (99.996% success)

### Performance Statistics
- **Queue Success Rate:** 99.996%
- **Processing Speed:** 1,000 items/min
- **API Response Time:** <500ms average
- **Products Sync:** 9K in <60s
- **Sales Sync:** 100K in <5min

---

## 🎓 Learning Path

### Beginner
1. Read [README.md](README.md) (5 min)
2. Read [QUICK_REFERENCE.md](cli/QUICK_REFERENCE.md) (10 min)
3. Run `php vend-sync-manager.php help` (1 min)
4. Try `php vend-sync-manager.php health:check` (1 min)
5. Try `php vend-sync-manager.php queue:stats` (1 min)

### Intermediate
1. Read [VEND_SYNC_USAGE.md](cli/VEND_SYNC_USAGE.md) (30 min)
2. Practice sync commands (10 min)
3. Practice consignment commands (10 min)
4. Read [DEPLOYMENT_CHECKLIST.md](cli/DEPLOYMENT_CHECKLIST.md) (20 min)

### Advanced
1. Read [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) (20 min)
2. Read [ARCHITECTURE.md](ARCHITECTURE.md) (20 min)
3. Study [vend-sync-manager.php](cli/vend-sync-manager.php) source (60 min)
4. Read [API_DOCUMENTATION.md](api/API_DOCUMENTATION.md) (30 min)
5. Build custom integration (varies)

---

## 🔗 Quick Links

### Most Visited
- [QUICK_REFERENCE.md](cli/QUICK_REFERENCE.md) - Daily reference
- [VEND_SYNC_USAGE.md](cli/VEND_SYNC_USAGE.md) - Complete guide
- [API_DOCUMENTATION.md](api/API_DOCUMENTATION.md) - API reference

### Most Useful
- [setup.sql](cli/setup.sql) - Database setup
- [DEPLOYMENT_CHECKLIST.md](cli/DEPLOYMENT_CHECKLIST.md) - Deployment
- [QUICK_REFERENCE.md § Emergency Procedures](cli/QUICK_REFERENCE.md#emergency-procedures)

### Most Important
- [README.md](README.md) - Entry point
- [SYSTEM_SUMMARY.md](SYSTEM_SUMMARY.md) - Complete overview
- [vend-sync-manager.php](cli/vend-sync-manager.php) - Main system

---

## 📞 Support Index

### Documentation Support
- **Quick Answer:** [QUICK_REFERENCE.md](cli/QUICK_REFERENCE.md)
- **Complete Answer:** [VEND_SYNC_USAGE.md](cli/VEND_SYNC_USAGE.md)
- **API Answer:** [API_DOCUMENTATION.md](api/API_DOCUMENTATION.md)

### Command Support
- **All Commands:** `php vend-sync-manager.php help`
- **Version Info:** `php vend-sync-manager.php util:version`
- **Health Check:** `php vend-sync-manager.php health:check`

### Emergency Support
- **Troubleshooting:** [QUICK_REFERENCE.md § Troubleshooting](cli/QUICK_REFERENCE.md#troubleshooting)
- **Emergency Procedures:** [QUICK_REFERENCE.md § Emergency Procedures](cli/QUICK_REFERENCE.md#emergency-procedures)
- **Common Issues:** [README.md § Common Issues](README.md#common-issues)

---

## ✅ Checklist Index

### Installation Checklist
→ [DEPLOYMENT_CHECKLIST.md § Pre-Deployment](cli/DEPLOYMENT_CHECKLIST.md)

### Deployment Checklist
→ [DEPLOYMENT_CHECKLIST.md § Deployment Steps](cli/DEPLOYMENT_CHECKLIST.md)

### Testing Checklist
→ [DEPLOYMENT_CHECKLIST.md § Post-Deployment](cli/DEPLOYMENT_CHECKLIST.md)

### Monitoring Checklist
→ [DEPLOYMENT_CHECKLIST.md § Monitoring](cli/DEPLOYMENT_CHECKLIST.md)

---

**Index Version:** 1.0.0
**Last Updated:** 2024
**System Status:** ✅ PRODUCTION READY

---

*This index provides quick navigation to all system documentation and code. For the best experience, start with [README.md](README.md) or [QUICK_REFERENCE.md](cli/QUICK_REFERENCE.md).*
