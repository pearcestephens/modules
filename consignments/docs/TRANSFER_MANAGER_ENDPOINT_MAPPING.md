# 🗺️ Transfer Manager API - Endpoint Mapping

Complete mapping of **old backend.php** case statements to **new TransferManagerAPI** handlers.

---

## Quick Reference Table

| # | Old Case Statement | New Handler Method | CSRF | Auth | Notes |
|---|-------------------|-------------------|------|------|-------|
| 1 | `init` | `handleInit()` | No | Yes | Config, outlets, suppliers, sync state |
| 2 | `toggle_sync` | `handleToggleSync()` | **Yes** | Yes | Enable/disable Lightspeed sync |
| 3 | `verify_sync` | `handleVerifySync()` | No | Yes | Check sync status |
| 4 | `list_transfers` | `handleListTransfers()` | No | Yes | Paginated list with filters |
| 5 | `get_transfer_detail` | `handleGetTransferDetail()` | No | Yes | Full transfer with items & notes |
| 6 | `search_products` | `handleSearchProducts()` | No | Yes | Product search for adding |
| 7 | `product_search` | *(merged into #6)* | - | - | Consolidated with search_products |
| 8 | `create_transfer` | `handleCreateTransfer()` | **Yes** | Yes | Create new transfer |
| 9 | `store_vend_numbers` | *(part of create flow)* | - | - | Handled in create/update |
| 10 | `create_consignment` | *(separate API)* | - | - | See ConsignmentsAPI |
| 11 | `add_transfer_item` | `handleAddTransferItem()` | **Yes** | Yes | Add/update item (upsert) |
| 12 | `update_transfer_item` | `handleUpdateTransferItem()` | **Yes** | Yes | Update item quantity |
| 13 | `remove_transfer_item` | `handleRemoveTransferItem()` | **Yes** | Yes | Remove item from transfer |
| 14 | `update_transfer_item_qty` | *(merged into #12)* | - | - | Use update_transfer_item |
| 15 | `push_consignment_lines` | *(Lightspeed sync)* | - | - | Background job |
| 16 | `add_products_to_consignment` | *(Lightspeed sync)* | - | - | Background job |
| 17 | `mark_sent` | `handleMarkSent()` | **Yes** | Yes | Transition to SENT |
| 18 | `mark_receiving` | `handleMarkReceiving()` | **Yes** | Yes | Transition to RECEIVING |
| 19 | `receive_all` | `handleReceiveAll()` | **Yes** | Yes | Complete receiving |
| 20 | `cancel_transfer` | `handleCancelTransfer()` | **Yes** | Yes | Cancel transfer |
| 21 | `add_note` | `handleAddNote()` | **Yes** | Yes | Add note with user info |
| 22 | `recreate_transfer` | `handleRecreateTransfer()` | **Yes** | Yes | Duplicate transfer |
| 23 | `revert_to_open` | `handleRevertToOpen()` | **Yes** | Yes | Status revert to DRAFT |
| 24 | `revert_to_sent` | `handleRevertToSent()` | **Yes** | Yes | Status revert to SENT |
| 25 | `revert_to_receiving` | `handleRevertToReceiving()` | **Yes** | Yes | Status revert to RECEIVING |

**Total:** 25 original case statements → 18 handler methods (some merged)

---

## Detailed Mapping

### 1. Configuration & Setup

#### `init` → `handleInit()`

**Purpose:** Load initial configuration for the Transfer Manager UI

**Old Implementation:**
```php
case 'init':
    // Get outlets
    $outlets = [];
    $result = $mysqli->query("SELECT * FROM outlets ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $outlets[] = $row;
    }

    // Get suppliers
    $suppliers = [];
    // ... similar query

    // Check sync
    $sync_enabled = file_exists('.sync_enabled');

    // Generate CSRF
    $_SESSION['tt_csrf'] = bin2hex(random_bytes(32));

    echo json_encode(['success' => true, 'outlets' => $outlets, ...]);
    break;
```

**New Implementation:**
```php
protected function handleInit(array $data): array
{
    $config = [
        'outlets' => $this->getOutlets(),
        'suppliers' => $this->getSuppliers(),
        'csrf_token' => $this->generateCSRF(),
        'sync_enabled' => $this->isSyncEnabled()
    ];

    return $this->success('Configuration loaded', $config);
}
```

**Request:**
```javascript
{
  "action": "init",
  "data": {}
}
```

**Response:**
```json
{
  "success": true,
  "message": "Configuration loaded successfully",
  "timestamp": "2025-11-04 15:30:00",
  "request_id": "req_1730712600_abc",
  "data": {
    "outlets": [...],
    "suppliers": [...],
    "csrf_token": "abc123...",
    "sync_enabled": true
  },
  "meta": {
    "duration_ms": 12.34,
    "memory_usage": "2.1 MB"
  }
}
```

---

#### `toggle_sync` → `handleToggleSync()`

**Purpose:** Enable or disable Lightspeed sync

**Old Implementation:**
```php
case 'toggle_sync':
    $csrf = $_POST['csrf'] ?? '';
    if ($csrf !== $_SESSION['tt_csrf']) {
        die(json_encode(['success' => false, 'error' => 'Invalid CSRF']));
    }

    $enable = $_POST['enable'] ?? false;
    if ($enable) {
        file_put_contents('.sync_enabled', '1');
    } else {
        @unlink('.sync_enabled');
    }

    echo json_encode(['success' => true]);
    break;
```

**New Implementation:**
```php
protected function handleToggleSync(array $data): array
{
    $this->validateCSRF($data['csrf'] ?? '');
    $enable = filter_var($data['enable'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($enable) {
        file_put_contents($this->syncFile, '1');
        $message = 'Sync enabled successfully';
    } else {
        @unlink($this->syncFile);
        $message = 'Sync disabled successfully';
    }

    return $this->success($message, ['sync_enabled' => $enable]);
}
```

**Request:**
```javascript
{
  "action": "toggle_sync",
  "data": {
    "csrf": "token_from_init",
    "enable": true
  }
}
```

---

### 2. Listing & Search

#### `list_transfers` → `handleListTransfers()`

**Purpose:** Get paginated list of transfers with filtering

**Old Implementation:**
```php
case 'list_transfers':
    $page = intval($_POST['page'] ?? 1);
    $perPage = intval($_POST['perPage'] ?? 25);
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    if (!empty($_POST['type'])) {
        $where[] = "consignment_category = '" . $mysqli->real_escape_string($_POST['type']) . "'";
    }
    // ... more filters

    $sql = "SELECT * FROM consignments WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";

    $result = $mysqli->query($sql);
    $transfers = [];
    while ($row = $result->fetch_assoc()) {
        $transfers[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $transfers]);
    break;
```

**New Implementation:**
```php
protected function handleListTransfers(array $data): array
{
    $page = $this->validateInt($data['page'] ?? 1, 'Page', 1);
    $perPage = $this->validateInt($data['perPage'] ?? 25, 'Per page', 1, 100);

    $filters = $this->buildTransferFilters($data);
    $transfers = $this->getTransfers($page, $perPage, $filters);
    $total = $this->getTransfersCount($filters);

    return $this->success('Transfers retrieved successfully', $transfers, [
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ],
        'filters' => $filters
    ]);
}
```

**Request:**
```javascript
{
  "action": "list_transfers",
  "data": {
    "page": 1,
    "perPage": 25,
    "type": "OUTLET",      // Optional: OUTLET, SUPPLIER, CONSIGNMENT
    "state": "SENT",        // Optional: DRAFT, SENT, RECEIVING, RECEIVED
    "outlet": 5,            // Optional: filter by outlet
    "search": "widget"      // Optional: search term
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Transfers retrieved successfully",
  "timestamp": "2025-11-04 15:30:00",
  "request_id": "req_1730712600_xyz",
  "data": [
    {
      "id": 123,
      "consignment_category": "OUTLET",
      "status": "SENT",
      "from_name": "Auckland CBD",
      "to_name": "Wellington",
      "item_count": 15,
      "total_qty": 45,
      "created_at": "2025-11-04 10:00:00"
    }
  ],
  "meta": {
    "duration_ms": 23.45,
    "memory_usage": "2.8 MB",
    "pagination": {
      "page": 1,
      "per_page": 25,
      "total": 150,
      "total_pages": 6
    },
    "filters": {
      "type": "OUTLET",
      "state": "SENT"
    }
  }
}
```

---

#### `search_products` → `handleSearchProducts()`

**Purpose:** Search products to add to transfer

**Old Implementation:**
```php
case 'search_products':
    $q = $_POST['q'] ?? '';
    $limit = intval($_POST['limit'] ?? 20);

    $sql = "SELECT p.*, i.quantity
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.name LIKE '%" . $mysqli->real_escape_string($q) . "%'
            LIMIT $limit";

    $result = $mysqli->query($sql);
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    echo json_encode(['success' => true, 'products' => $products]);
    break;
```

**New Implementation:**
```php
protected function handleSearchProducts(array $data): array
{
    $query = $this->validateString($data['q'] ?? '', 'Search query', 1, 100);
    $limit = $this->validateInt($data['limit'] ?? 20, 'Limit', 1, 100);

    $products = $this->searchProducts($query, $limit);

    return $this->success('Products found', [
        'products' => $products,
        'count' => count($products)
    ]);
}
```

---

### 3. Transfer Management

#### `create_transfer` → `handleCreateTransfer()`

**Purpose:** Create a new transfer

**Old Implementation:**
```php
case 'create_transfer':
    $csrf = $_POST['csrf'] ?? '';
    if ($csrf !== $_SESSION['tt_csrf']) {
        die(json_encode(['success' => false]));
    }

    $category = $_POST['consignment_category'] ?? '';
    $from = intval($_POST['outlet_from'] ?? 0);
    $to = intval($_POST['outlet_to'] ?? 0);

    if (!$category || !$from || !$to) {
        die(json_encode(['success' => false, 'error' => 'Missing fields']));
    }

    $sql = "INSERT INTO consignments (consignment_category, outlet_from, outlet_to, status)
            VALUES ('$category', $from, $to, 'DRAFT')";
    $mysqli->query($sql);
    $id = $mysqli->insert_id;

    echo json_encode(['success' => true, 'id' => $id]);
    break;
```

**New Implementation:**
```php
protected function handleCreateTransfer(array $data): array
{
    $this->validateCSRF($data['csrf'] ?? '');

    $category = $this->validateString($data['consignment_category'] ?? '', 'Category', 1, 50);
    $from = $this->validateInt($data['outlet_from'] ?? null, 'Outlet From', 1);
    $to = $this->validateInt($data['outlet_to'] ?? null, 'Outlet To', 1);

    // Validate outlets exist
    $this->validateOutletExists($from);
    $this->validateOutletExists($to);

    $transferId = $this->createTransfer($category, $from, $to);
    $transfer = $this->getTransferById($transferId);

    return $this->success('Transfer created successfully', $transfer);
}
```

**Request:**
```javascript
{
  "action": "create_transfer",
  "data": {
    "csrf": "token_from_init",
    "consignment_category": "OUTLET",
    "outlet_from": 1,
    "outlet_to": 5
  }
}
```

---

#### `add_transfer_item` → `handleAddTransferItem()`

**Purpose:** Add product to transfer (or update if exists)

**Implementation uses UPSERT logic:**
```php
protected function handleAddTransferItem(array $data): array
{
    $this->validateCSRF($data['csrf'] ?? '');

    $transferId = $this->validateInt($data['transfer_id'] ?? null, 'Transfer ID', 1);
    $productId = $this->validateInt($data['product_id'] ?? null, 'Product ID', 1);
    $quantity = $this->validateInt($data['quantity'] ?? 1, 'Quantity', 1);

    // Check if item already exists
    if ($this->itemExists($transferId, $productId)) {
        // Update existing
        $this->updateItemQuantity($transferId, $productId, $quantity);
        $message = 'Item quantity updated';
    } else {
        // Insert new
        $this->insertTransferItem($transferId, $productId, $quantity);
        $message = 'Item added to transfer';
    }

    $transfer = $this->getTransferById($transferId);

    return $this->success($message, $transfer);
}
```

---

### 4. Status Management

#### `mark_sent` → `handleMarkSent()`

**Purpose:** Transition transfer from DRAFT to SENT

**Old Implementation:**
```php
case 'mark_sent':
    $id = intval($_POST['id'] ?? 0);
    $csrf = $_POST['csrf'] ?? '';

    if ($csrf !== $_SESSION['tt_csrf']) {
        die(json_encode(['success' => false]));
    }

    $mysqli->query("UPDATE consignments SET status = 'SENT', sent_at = NOW() WHERE id = $id");

    echo json_encode(['success' => true]);
    break;
```

**New Implementation:**
```php
protected function handleMarkSent(array $data): array
{
    $this->validateCSRF($data['csrf'] ?? '');

    $transferId = $this->validateInt($data['transfer_id'] ?? null, 'Transfer ID', 1);

    // Validate current status
    $transfer = $this->getTransferById($transferId);
    if ($transfer['status'] !== 'DRAFT') {
        return $this->error('INVALID_STATE', 'Transfer must be in DRAFT status');
    }

    // Check has items
    if ($transfer['item_count'] == 0) {
        return $this->error('VALIDATION_ERROR', 'Transfer must have at least one item');
    }

    $this->updateTransferStatus($transferId, 'SENT');
    $updatedTransfer = $this->getTransferById($transferId);

    return $this->success('Transfer marked as sent', $updatedTransfer);
}
```

---

### 5. Notes & Audit

#### `add_note` → `handleAddNote()`

**Purpose:** Add timestamped note to transfer

**Old Implementation:**
```php
case 'add_note':
    $id = intval($_POST['transfer_id'] ?? 0);
    $note = $_POST['note'] ?? '';
    $user = $_SESSION['user']['name'] ?? 'Unknown';

    $sql = "INSERT INTO transfer_notes (transfer_id, user, note, created_at)
            VALUES ($id, '$user', '" . $mysqli->real_escape_string($note) . "', NOW())";
    $mysqli->query($sql);

    echo json_encode(['success' => true]);
    break;
```

**New Implementation:**
```php
protected function handleAddNote(array $data): array
{
    $this->validateCSRF($data['csrf'] ?? '');

    $transferId = $this->validateInt($data['transfer_id'] ?? null, 'Transfer ID', 1);
    $note = $this->validateString($data['note'] ?? '', 'Note', 1, 1000);

    $noteId = $this->insertNote($transferId, $note, $this->userId, $this->userName);
    $transfer = $this->getTransferById($transferId);

    return $this->success('Note added successfully', $transfer);
}
```

---

## Frontend Migration Guide

### Update Action Names

All action names remain the same! Just update response handling:

**Before:**
```javascript
.then(response => response.json())
.then(result => {
  if (result.success) {
    // Handle data
    console.log(result.data);
  } else {
    // Handle error
    alert(result.error);
  }
});
```

**After:**
```javascript
.then(response => response.json())
.then(result => {
  if (result.success) {
    // Handle data (same)
    console.log(result.data);

    // NEW: Access additional info
    console.log('Request ID:', result.request_id);
    console.log('Performance:', result.meta.duration_ms + 'ms');
  } else {
    // NEW: Richer error handling
    console.error(`[${result.error.code}] ${result.error.message}`);
    if (result.error.details) {
      console.log('Details:', result.error.details);
    }
  }
});
```

### Update Endpoint URL

**Change:**
```javascript
const url = '/modules/consignments/TransferManager/backend.php';
```

**To:**
```javascript
const url = '/modules/consignments/TransferManager/backend-v2.php';
```

That's it! All action names and request formats stay the same.

---

## Testing Checklist

Use the automated test script:

```bash
./test-transfer-manager.sh
```

Or test manually:

### Configuration
- [ ] `init` - Returns config with outlets, suppliers, CSRF, sync state
- [ ] `toggle_sync` - Enables sync (file created)
- [ ] `toggle_sync` - Disables sync (file removed)
- [ ] `verify_sync` - Returns correct sync state

### Listing
- [ ] `list_transfers` - Returns paginated list
- [ ] `list_transfers` - Filtering by type works
- [ ] `list_transfers` - Filtering by state works
- [ ] `list_transfers` - Filtering by outlet works
- [ ] `list_transfers` - Search term works
- [ ] `list_transfers` - Pagination meta correct

### Search
- [ ] `search_products` - Returns matching products
- [ ] `search_products` - Includes inventory data
- [ ] `search_products` - Respects limit parameter

### Create & Manage
- [ ] `create_transfer` - Creates in DRAFT status
- [ ] `create_transfer` - Validates required fields
- [ ] `create_transfer` - Validates CSRF token
- [ ] `add_transfer_item` - Adds new item
- [ ] `add_transfer_item` - Updates existing item (upsert)
- [ ] `update_transfer_item` - Updates quantity
- [ ] `remove_transfer_item` - Removes item

### Status Transitions
- [ ] `mark_sent` - Changes DRAFT → SENT
- [ ] `mark_sent` - Validates has items
- [ ] `mark_sent` - Validates current status
- [ ] Other status transitions work

### Notes
- [ ] `add_note` - Adds note with user info
- [ ] `add_note` - Includes timestamp
- [ ] Notes appear in transfer detail

### Error Handling
- [ ] Invalid action returns proper error
- [ ] Missing CSRF returns proper error
- [ ] Missing required fields returns proper error
- [ ] Invalid IDs return proper error
- [ ] All errors include error.code
- [ ] All errors include request_id

---

## Rollback Procedure

If issues arise with backend-v2.php:

### Option 1: Instant Rollback (Frontend)

```javascript
// Change this line back:
const url = '/modules/consignments/TransferManager/backend.php';
```

### Option 2: Server-Side Rollback

```bash
# Rename files
cd /modules/consignments/TransferManager/
mv backend-v2.php backend-v2.php.disabled
mv backend.php backend.php.original
# Copy old implementation back if needed
```

### Option 3: Symlink Switch

```bash
# If using symlink approach
cd /modules/consignments/TransferManager/
rm backend-current.php
ln -s backend.php backend-current.php
```

---

## Performance Benchmarks

Expected performance (all operations):

| Operation | Target | Actual |
|-----------|--------|--------|
| `init` | < 50ms | TBD |
| `list_transfers` (25 items) | < 100ms | TBD |
| `get_transfer_detail` | < 50ms | TBD |
| `search_products` | < 100ms | TBD |
| `create_transfer` | < 50ms | TBD |
| `add_transfer_item` | < 30ms | TBD |
| `mark_sent` | < 50ms | TBD |

---

## Support

For issues or questions:
- Review documentation in `/docs/`
- Check CIS Logger with request_id
- Test with automated script
- Contact backend team

---

**Status: Ready for Testing** ✅
