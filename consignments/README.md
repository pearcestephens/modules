# Consignments Module

**Version:** 2.0.0
**Last Updated:** November 1, 2025
**Status:** In Active Refactoring (Hexagonal Architecture Migration)

## Purpose

The Consignments module manages all transfer workflows for CIS (Central Information System), integrating with Lightspeed Retail's native consignment model. It handles:

- **Purchase Orders** (SUPPLIER type): External supplier → CIS outlets
- **Outlet Transfers** (OUTLET type): CIS outlet → CIS outlet
- **Supplier Returns** (RETURN type): CIS outlet → External supplier
- **Stocktakes** (STOCKTAKE type): Inventory adjustments

## Architecture

This module follows **Hexagonal (Ports & Adapters)** architecture:

```
├── domain/           # Core business logic (framework-agnostic)
│   ├── Entities/     # Domain models (Consignment, LineItem, etc.)
│   ├── ValueObjects/ # Immutable types (Status, ConsignmentId, etc.)
│   ├── Services/     # Business orchestrations
│   └── Policies/     # Business rules (approvals, state transitions)
├── infra/            # External integrations & adapters
│   ├── Lightspeed/   # Lightspeed API client + anti-corruption layer
│   ├── Queue/        # Async job processing
│   ├── Webhooks/     # Inbound webhook handlers
│   ├── Persistence/  # Database repositories
│   ├── Freight/      # Shipping integrations
│   └── Http/         # HTTP utilities
├── app/              # Application layer
│   ├── Api/          # JSON API endpoints
│   ├── Controllers/  # UI controllers (if MVC)
│   └── UseCases/     # Application-specific orchestrations
├── bin/              # CLI scripts (queue worker, poller)
├── tests/            # All test types
└── docs/             # All documentation
```

## Quick Start

### Installation

```bash
# Install dependencies
composer install

# Run migrations
php bin/migrate.php up

# Start queue worker
php bin/queue-worker.php
```

### Configuration

Copy `.env.example` to `.env` and configure:

```env
# Lightspeed API
LS_API_TOKEN=your_token_here
LS_BASE_URL=https://api.vendhq.com
LS_WEBHOOK_SECRET=your_webhook_secret

# Database
DB_HOST=localhost
DB_NAME=jcepnzzkmj
DB_USER=your_user
DB_PASS=your_password

# Queue
QUEUE_CONCURRENCY=4
QUEUE_MAX_ATTEMPTS=3
QUEUE_BACKOFF_BASE_MS=1000
```

### Running Tests

```bash
# All tests
./vendor/bin/phpunit

# Specific suite
./vendor/bin/phpunit tests/unit
./vendor/bin/phpunit tests/integration

# API tests
./tests/api/test-consignment-api.sh https://staff.vapeshed.co.nz
```

## Key Entry Points

### API Endpoints

- `POST /api/consignments/create` - Create new consignment
- `POST /api/consignments/add_item` - Add line item
- `POST /api/consignments/status` - Update status
- `POST /api/consignments/receive` - Mark as received
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
