# Consignments Module

[![Tests](https://github.com/vapeshed/consignments/actions/workflows/consignments-tests.yml/badge.svg)](https://github.com/vapeshed/consignments/actions/workflows/consignments-tests.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Proprietary-red.svg)]()

**Version:** 2.0.0  
**Last Updated:** November 2, 2025  
**Status:** ✅ Production Ready

---

## Purpose

The Consignments module manages all transfer workflows for CIS (Central Information System), integrating with Lightspeed Retail's native consignment model. It handles:

- **Purchase Orders** (SUPPLIER type): External supplier → CIS outlets
- **Stock Transfers** (OUTLET type): CIS outlet → CIS outlet  
- **Returns to Supplier** (RETURN type): CIS outlet → External supplier
- **Freight Integration**: Shipment booking and tracking
- **Receiving Evidence**: Photo uploads, signatures, damage notes

---

## Features

### ✨ Core Capabilities

- **Unified Transfer API**: Consistent interface for all transfer types (PO, ST, RTS)
- **Queue-Based Processing**: Async job processing with Dead Letter Queue (DLQ)
- **Lightspeed Integration**: Two-way sync via polling and webhooks
- **Real-Time Dashboard**: Admin monitoring with Chart.js visualizations
- **Freight Management**: Multi-provider freight booking and tracking
- **Evidence Capture**: Photo uploads, digital signatures, damage notes
- **HMAC Webhook Security**: Signed webhooks with replay protection
- **State Machine**: Enforced status transitions with validation
- **Exponential Backoff**: Automatic retry with exponential delays
- **Heartbeat Monitoring**: Detects and resets stuck jobs

### 🏗️ Architecture

This module follows **Hexagonal (Ports & Adapters)** architecture with clean separation:

```
├── app/              # Application layer
│   ├── Domain/       # Business logic (Status, Entities)
│   ├── Services/     # Transfer, Receiving, Freight services
│   └── Contracts/    # Interfaces for adapters
├── infrastructure/   # External integrations
│   ├── Lightspeed/   # API client with OAuth2 + retry logic
│   ├── Queue/        # Database-backed queue system
│   ├── Webhooks/     # Inbound webhook handlers
│   └── Freight/      # Freight provider integrations
├── bin/              # CLI scripts (queue-worker, poller)
├── tests/            # PHPUnit tests (120+ tests)
├── docs/             # Complete documentation
└── database/         # MySQL migrations
```

---

## Quick Start

### Installation (5 minutes)

```bash
# 1. Clone repository (if not already present)
cd /var/www/modules/consignments

# 2. Install dependencies
composer install

# 3. Configure environment
cp .env.example .env
nano .env  # Edit: DB credentials, LS token, webhook secret

# 4. Run migrations
mysql -u consignments_user -p consignments_prod < database/01-queue-tables.sql
mysql -u consignments_user -p consignments_prod < database/02-webhook-tables.sql
mysql -u consignments_user -p consignments_prod < database/03-consignment-updates.sql
mysql -u consignments_user -p consignments_prod < database/04-receiving-evidence.sql
mysql -u consignments_user -p consignments_prod < database/10-freight-bookings.sql

# 5. Start queue worker
php bin/queue-worker.php &

# 6. Configure poller (cron)
crontab -e
# Add: */5 * * * * cd /var/www/modules/consignments && php bin/poll-ls-consignments.php
```

### Configuration

Edit `.env` with your settings:

```env
# Lightspeed API
LS_API_TOKEN=your_bearer_token_here
LS_BASE_URL=https://api.vendhq.com
LS_WEBHOOK_SECRET=your_webhook_secret_from_ls_admin

# Database
DB_HOST=127.0.0.1
DB_NAME=consignments_prod
DB_USER=consignments_user
DB_PASS=secure_password

# Queue Settings
QUEUE_CONCURRENCY=4
QUEUE_MAX_ATTEMPTS=3
QUEUE_BACKOFF_BASE_MS=200

# Freight Provider
FREIGHT_PROVIDER=freightnow
FREIGHT_API_KEY=your_freight_api_key
```

### Verify Installation

```bash
# 1. Test database connection
php -r "require 'bootstrap.php'; getDbConnection(); echo 'DB OK';"

# 2. Run test suite
vendor/bin/phpunit

# 3. Check queue worker
ps aux | grep queue-worker

# 4. Access admin dashboard
# Visit: https://staff.vapeshed.co.nz/consignments/admin/dashboard.php
```

---

## Usage

### Creating a Purchase Order

```php
use App\Services\PurchaseOrderService;

$poService = new PurchaseOrderService($pdo);

$poId = $poService->create([
    'supplier_id' => 'SUP123',
    'source_outlet_id' => 'main-warehouse',
    'destination_outlet_id' => 'outlet-001',
    'items' => [
        ['product_id' => 'PROD001', 'quantity' => 10],
        ['product_id' => 'PROD002', 'quantity' 5],
    ]
]);

// Send to Lightspeed (queued)
$poService->send($poId);
```

### Receiving a Consignment

```php
use App\Services\ReceivingService;

$receivingService = new ReceivingService($pdo);

// Upload photo evidence
$receivingService->uploadPhoto($consignmentId, [
    'name' => 'evidence.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => '/tmp/phpXXXXXX',
    'size' => 245000
]);

// Capture signature
$receivingService->captureSignature($consignmentId, [
    'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANS...',
    'signed_by' => 'John Doe'
]);

// Add damage notes
$receivingService->addDamageNote($consignmentId, [
    'description' => 'Box damaged during transit',
    'severity' => 'minor',
    'items_affected' => ['PROD001']
]);
```

### Booking Freight

```php
use App\Services\FreightService;

$freightService = new FreightService($pdo);

$booking = $freightService->createShipment([
    'consignment_id' => 123,
    'from_address' => [...],
    'to_address' => [...],
    'items' => [
        ['description' => 'Box 1', 'weight' => 10.5, 'dimensions' => '30x30x30']
    ]
]);

// Track shipment
$tracking = $freightService->trackShipment($booking['tracking_number']);
echo "Status: {$tracking['status']}";
```

---

## Key Entry Points

### API Endpoints


#### Transfer APIs
- `POST /api/transfers/purchase-orders` - Create PO
- `POST /api/transfers/purchase-orders/{id}/send` - Send PO to Lightspeed
- `POST /api/transfers/purchase-orders/{id}/receive` - Mark PO received
- `POST /api/transfers/stock-transfers` - Create stock transfer
- `POST /api/transfers/returns` - Create return to supplier

#### Receiving APIs
- `POST /api/receiving/{id}/photo` - Upload photo evidence
- `POST /api/receiving/{id}/signature` - Capture signature
- `POST /api/receiving/{id}/damage` - Add damage notes

#### Freight APIs
- `POST /api/freight/shipments` - Book shipment
- `GET /api/freight/shipments/{tracking}` - Track shipment

#### Admin APIs
- `GET /admin/api/sync-status.php` - Dashboard metrics
- `GET /admin/api/dlq-list.php` - List failed jobs
- `POST /admin/api/retry-job.php` - Retry DLQ job
- `GET /admin/api/error-log.php` - Recent errors

#### Webhooks
- `POST /public/webhooks/lightspeed.php` - Lightspeed webhook receiver

### CLI Commands

```bash
# Queue worker (long-running process)
php bin/queue-worker.php

# Poller (run via cron every 5 minutes)
php bin/poll-ls-consignments.php

# Check queue status
php bin/queue-stats.php
```

### Admin Dashboard

Access real-time monitoring:
```
https://staff.vapeshed.co.nz/consignments/admin/dashboard.php
```

Features:
- 📊 **Queue Health**: Pending, processing, failed job counts
- 📈 **Webhook Stats**: Success rate, 24-hour volume, events by type
- 🔴 **Dead Letter Queue**: Failed jobs with retry buttons
- 📝 **Error Log**: Recent errors from webhooks and queue
- 📉 **Charts**: Line chart (queue activity), Doughnut chart (webhook types)

---

## Testing

### Running Tests (120+ tests)

```bash
# All tests
vendor/bin/phpunit

# Unit tests only
vendor/bin/phpunit tests/unit

# Integration tests only
vendor/bin/phpunit tests/integration

# Specific test
vendor/bin/phpunit tests/integration/QueueWorkerTest.php

# With coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Test Suites

- **Unit Tests**: Domain logic, status transitions, validation
- **Integration Tests**: Queue worker, database operations, API endpoints
- **Admin Tests**: Dashboard APIs, DLQ management
- **CI/CD**: GitHub Actions with PHP 8.1, 8.2, 8.3 matrix

---

## Documentation

Comprehensive documentation available:

- **[API Reference](docs/API.md)** - All endpoints with request/response examples
- **[Deployment Guide](docs/DEPLOYMENT.md)** - Production deployment procedures
- **[Operations Runbook](docs/RUNBOOK.md)** - Troubleshooting and maintenance
- **[CHANGELOG](CHANGELOG.md)** - Version history and migration guides

---

## Deployment

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for complete production deployment guide.

Quick checklist:
- ✅ PHP 8.1+ with required extensions
- ✅ MySQL 8.0+ with InnoDB
- ✅ Composer dependencies installed
- ✅ Environment configured (.env)
- ✅ Database migrations run
- ✅ Queue worker running (systemd)
- ✅ Poller scheduled (cron)
- ✅ Lightspeed webhook configured
- ✅ File permissions correct (uploads, logs)
- ✅ Admin dashboard accessible

---

## Troubleshooting

### Queue worker not processing?

```bash
# Check status
sudo systemctl status consignments-queue.service

# View logs
tail -100 /var/www/modules/consignments/logs/queue-worker.log

# Restart
sudo systemctl restart consignments-queue.service
```

### Webhooks not receiving?

```bash
# Test webhook manually
curl -X POST https://staff.vapeshed.co.nz/consignments/public/webhooks/lightspeed.php \
  -H "X-Lightspeed-Signature: sha256=..." \
  -H "Content-Type: application/json" \
  -d '{"event_id":"test","event_type":"consignment.test","data":{}}'

# Check webhook logs
tail -100 /var/www/modules/consignments/logs/webhooks.log
```

### Jobs stuck in DLQ?

1. Visit admin dashboard: https://staff.vapeshed.co.nz/consignments/admin/dashboard.php
2. Review DLQ table
3. Click "Retry" button for failed jobs
4. Or retry via API:
```bash
curl -X POST https://staff.vapeshed.co.nz/consignments/admin/api/retry-job.php \
  -H "Content-Type: application/json" \
  -d '{"dlq_id": 123}'
```

See [docs/RUNBOOK.md](docs/RUNBOOK.md) for complete troubleshooting guide.

---

## Architecture Decisions

### Why Queue-Based Processing?

- **Reliability**: Failed jobs automatically retry with exponential backoff
- **Performance**: Non-blocking webhook processing (< 200ms response)
- **Observability**: Dashboard shows queue health and error trends
- **Scalability**: Easy to add more workers
- **Recovery**: DLQ for manual intervention on persistent failures

### Why Hexagonal Architecture?

- **Testability**: Business logic isolated from frameworks
- **Flexibility**: Easy to swap Lightspeed for another POS
- **Maintainability**: Clear boundaries between layers
- **Domain-Driven**: Status transitions enforced by state machine

### Why HMAC Webhook Validation?

- **Security**: Prevents unauthorized webhook submissions
- **Authenticity**: Verifies webhooks from Lightspeed
- **Replay Protection**: 5-minute timestamp window
- **Industry Standard**: HMAC-SHA256 widely adopted

---

## Contributing

### Code Quality Standards

- **PSR-12**: PHP coding standard
- **PHPStan Level 8**: Static analysis
- **100% Type Coverage**: All parameters and returns typed
- **Test Coverage**: Minimum 80% for new code
- **Documentation**: PHPDoc for all public methods

### Pull Request Process

1. Create feature branch from `develop`
2. Write tests for new functionality
3. Ensure all tests pass: `vendor/bin/phpunit`
4. Run static analysis: `vendor/bin/phpstan analyze`
5. Check code style: `vendor/bin/phpcs`
6. Submit PR to `develop` branch
7. Wait for CI checks to pass
8. Request review from maintainer

---

## Dependencies

### Required
- **PHP**: >= 8.1 (strict types, enums, readonly properties)
- **MySQL**: >= 8.0 (FOR UPDATE SKIP LOCKED)
- **Composer**: >= 2.0

### PHP Extensions
- `pdo`, `pdo_mysql` - Database
- `mbstring` - String handling
- `json` - JSON parsing
- `curl` - HTTP requests
- `gd` - Image processing

### Composer Packages
- `phpunit/phpunit`: ^10.0 - Testing framework
- `squizlabs/php_codesniffer`: ^3.7 - Code style
- `phpstan/phpstan`: ^1.10 - Static analysis

---

## License

Proprietary - © 2025 The Vape Shed / Ecigdis Limited. All rights reserved.

---

## Support

- **Documentation**: See `docs/` directory
- **Issues**: Contact system administrator
- **Emergency**: See [docs/RUNBOOK.md](docs/RUNBOOK.md) for escalation contacts

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and migration guides.

**Current Version:** 2.0.0 (November 2, 2025)

Major refactoring with:
- ✅ Queue-based async processing
- ✅ Lightspeed webhook integration
- ✅ Real-time admin dashboard
- ✅ Freight booking and tracking
- ✅ Receiving evidence capture
- ✅ Comprehensive test suite (120+ tests)
- ✅ CI/CD pipeline with GitHub Actions

---

**Last Updated:** November 2, 2025  
**Maintained by:** Ecigdis IT Team

- `GET /api/consignments/recent` - List recent consignments
- `GET /api/consignments/get` - Get single consignment details

See [API Documentation](docs/API/Endpoints.md) for full reference.

### UI Pages

- `/modules/consignments/` - Main dashboard
- `/modules/consignments/stock-transfers/pack-pro.php` - Pack interface
- `/modules/consignments/purchase-orders/` - PO management
- `/admin/consignments/sync-status` - Sync monitoring dashboard

### Background Workers

- `bin/queue-worker.php` - Processes async jobs from queue
- `bin/poll-ls-consignments.php` - Polls Lightspeed for updates

## Status & Workflow

### Consignment States

```
DRAFT → SENT → RECEIVING → RECEIVED → COMPLETED
   ↓                          ↓
CANCELLED ←─────────────────────
```

**Internal States** (CIS):
- `draft` - Being created/edited
- `sent` - Sent to destination
- `receiving` - Being received (partial)
- `received` - Fully received
- `completed` - Finalized & archived
- `cancelled` - Cancelled at any stage

**Lightspeed Mapping**:
- `draft` → `OPEN`
- `sent` → `SENT`
- `receiving` → `DISPATCHED`
- `received` → `RECEIVED`
- `completed` → `RECEIVED` (finalized in CIS)
- `cancelled` → `CANCELLED`

See [docs/STATUS.md](docs/STATUS.md) for detailed status information.

## Development

### Code Style

- PSR-12 coding standard
- Strict types (`declare(strict_types=1);`)
- Type hints on all parameters and returns
- PHP 8.1+ features encouraged

### Commit Convention

```
feat(consignments): Add idempotency keys to Lightspeed client
fix(consignments): Resolve status transition validation bug
sec(consignments): Remove hardcoded credentials
docs(consignments): Update webhook setup guide
test(consignments): Add integration tests for receiving flow
```

### Before Submitting PR

- [ ] All tests pass (`./vendor/bin/phpunit`)
- [ ] API tests pass (`./tests/api/test-consignment-api.sh`)
- [ ] No secrets in code (`grep -R "password\|DB_PASS\|PIN_CODE"`)
- [ ] CSRF checks on write endpoints
- [ ] Docs updated (`docs/STATUS.md`, `docs/Roadmap.md`)
- [ ] Migration scripts include rollback

## Documentation

- **[STATUS.md](docs/STATUS.md)** - Current completion status
- **[Roadmap.md](docs/Roadmap.md)** - Short/medium/long-term plans
- **[API Reference](docs/API/)** - Endpoint specifications
- **[Runbooks](docs/Runbooks/)** - Operational guides
- **[ADRs](docs/ADRs/)** - Architecture decision records

## Support

- **Primary Contact:** Pearce Stephens (@pearcestephens)
- **Documentation:** See `docs/` directory
- **Issue Tracker:** GitHub Issues
- **Wiki:** [staff.vapeshed.co.nz/wiki](https://staff.vapeshed.co.nz/wiki)

## License

Proprietary - Ecigdis Limited / The Vape Shed
All rights reserved.
