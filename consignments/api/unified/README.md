# Unified Transfer API

## Overview

This folder contains the **unified API** that handles ALL transfer operations across the consignments module.

## Endpoint Structure

```
POST /modules/consignments/api/unified/
```

All requests are sent to the main index.php in this folder via the parent API router.

## Supported Transfer Types

- Stock Transfers (`STOCK_TRANSFER`)
- Purchase Orders (`PURCHASE_ORDER`)
- Supplier Returns (`SUPPLIER_RETURN`)
- Outlet Returns (`OUTLET_RETURN`)
- Adjustments (`ADJUSTMENT`)

## Example Usage

```bash
# Initialize (get config, stats, outlets)
curl -X POST http://localhost/modules/consignments/api/unified/ \
  -H "Content-Type: application/json" \
  -d '{"action":"init"}'

# Create a stock transfer
curl -X POST http://localhost/modules/consignments/api/unified/ \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create_transfer",
    "transfer_category": "STOCK_TRANSFER",
    "source_outlet_id": 1,
    "destination_outlet_id": 2
  }'

# List transfers with filters
curl -X POST http://localhost/modules/consignments/api/unified/ \
  -H "Content-Type: application/json" \
  -d '{
    "action": "list_transfers",
    "type": "PURCHASE_ORDER",
    "state": "OPEN",
    "page": 1
  }'

# Add item to transfer
curl -X POST http://localhost/modules/consignments/api/unified/ \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_transfer_item",
    "id": 123,
    "product_id": "abc123",
    "qty": 10
  }'

# Mark as sent
curl -X POST http://localhost/modules/consignments/api/unified/ \
  -H "Content-Type: application/json" \
  -d '{
    "action": "mark_sent",
    "id": 123,
    "total_boxes": 3
  }'
```

## Routing

Requests are routed through:

1. `/modules/consignments/api/index.php` (main API router)
2. Detects `transfers/*` endpoints
3. Routes to `/modules/consignments/api/unified/index.php`
4. Processes action via `TransferManagerService`

## Architecture

```
api/unified/
├── index.php              # Main API handler
└── README.md              # This file
```

The unified API uses:
- `TransferManagerService` - Core transfer operations
- `ConsignmentHelpers` - Shared utilities
- `LightspeedSync` - Lightspeed integration

## Benefits

✅ **DRY** - One API for all transfer types
✅ **Consistent** - Same contract across all operations
✅ **Maintainable** - Single source of truth
✅ **Extensible** - Easy to add new transfer types

For full documentation, see:
`/modules/consignments/_kb/UNIFIED_ARCHITECTURE_COMPLETE.md`
