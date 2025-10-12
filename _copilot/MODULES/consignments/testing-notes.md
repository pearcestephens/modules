# Consignments Module - Testing Notes

## Overview
Test cases, validation scenarios, and testing procedures for the Consignments module.

**Last Updated:** 2025-10-12 07:00 UTC

## Unit Tests

### Controller Tests
- **PackController:** Pack operations, validation, error handling
- **ReceiveController:** Receive operations, quantity validation  
- **HubController:** Dashboard display, transfer listing
- **API Controllers:** Endpoint responses, error codes

### Model Tests
- **Transfer Model:** CRUD operations, status transitions
- **TransferLine Model:** Quantity validation, product associations
- **Inventory Model:** Stock calculations, movement tracking

## Integration Tests

### Pack Flow Tests
```php
// Test: Complete pack operation
1. Create transfer with lines
2. Load pack interface  
3. Add additional products
4. Update quantities
5. Submit pack
6. Verify inventory deduction
7. Verify audit trail
```

### Receive Flow Tests  
```php
// Test: Complete receive operation
1. Load packed transfer
2. Enter receive quantities
3. Handle discrepancies
4. Submit receive
5. Verify inventory addition
6. Verify status update
```

### API Integration Tests
- CSRF token validation
- Authentication checks
- JSON response format
- Error code consistency
- Rate limiting

## User Acceptance Tests

### Pack Operations
| Test Case | Steps | Expected Result |
|-----------|-------|-----------------|
| Pack Valid Transfer | Select transfer → Add products → Set quantities → Submit | Transfer marked as packed, inventory reduced |
| Pack Insufficient Stock | Add product with qty > available | Error message, no changes saved |
| Pack Locked Transfer | Attempt to edit locked transfer | Read-only mode, lock notification |
| Autosave Recovery | Close browser → Reopen pack page | Previous form state restored |

### Receive Operations  
| Test Case | Steps | Expected Result |
|-----------|-------|-----------------|
| Receive Full Quantity | Enter qty_received = qty_packed → Submit | Transfer completed, inventory added |
| Receive Partial Quantity | Enter qty_received < qty_packed → Submit | Discrepancy recorded, partial completion |
| Receive Excess Quantity | Enter qty_received > qty_packed | Validation error, submission blocked |

## Performance Tests

### Load Testing
- **Pack Page:** Load time < 2 seconds with 100 products
- **Product Search:** Response time < 500ms for 10,000 products  
- **API Endpoints:** Throughput > 100 requests/second
- **Database Queries:** Execution time < 100ms for complex joins

### Stress Testing
- **Concurrent Users:** 50 users packing simultaneously
- **Large Transfers:** 500+ line items per transfer
- **Search Volume:** 1000+ searches per minute
- **Memory Usage:** < 256MB per request

## Security Tests

### Authentication Tests
- Unauthenticated access blocked
- Session timeout handling
- Permission-based access control
- Cross-outlet access prevention

### CSRF Protection Tests
- Form submissions require valid tokens
- Token rotation on sensitive operations
- Ajax requests include tokens
- Invalid token handling

### Input Validation Tests
- SQL injection prevention  
- XSS protection
- File upload restrictions
- Integer overflow handling

## Browser Compatibility Tests

### Desktop Browsers
- **Chrome 90+:** Full functionality
- **Firefox 88+:** Full functionality  
- **Safari 14+:** Full functionality
- **Edge 90+:** Full functionality

### Mobile Browsers
- **Chrome Mobile:** Responsive layout, touch interface
- **Safari Mobile:** iOS compatibility, gesture support
- **Samsung Internet:** Android compatibility

## Regression Tests

### Critical Path Tests
Run before each release:
1. User login and authentication
2. Pack transfer end-to-end
3. Receive transfer end-to-end  
4. Product search functionality
5. Inventory calculation accuracy

### Database Tests
- Data integrity constraints
- Foreign key relationships
- Transaction rollback scenarios
- Concurrent access handling

## Test Data Setup

### Sample Data
```sql
-- Test outlets
INSERT INTO outlets (id, name, code) VALUES 
(1, 'Store A', 'STA'), (2, 'Store B', 'STB');

-- Test products  
INSERT INTO products (id, sku, name, barcode) VALUES
(1, 'TEST001', 'Test Product 1', '1234567890'),
(2, 'TEST002', 'Test Product 2', '2345678901');

-- Test inventory
INSERT INTO inventory (outlet_id, product_id, qty_available) VALUES
(1, 1, 100), (1, 2, 50), (2, 1, 75), (2, 2, 25);
```

## Automated Testing

### PHPUnit Configuration
```php
// phpunit.xml
<phpunit bootstrap="tests/bootstrap.php">
    <testsuites>
        <testsuite name="Consignments">
            <directory>tests/Consignments</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Continuous Integration
- Run tests on every commit
- Deploy only after tests pass
- Performance regression detection
- Security scan integration

## Bug Testing Scenarios

### Common Issues
- Race conditions in inventory updates
- Session timeout during long operations
- Browser cache affecting autosave
- Network timeouts on API calls

### Edge Cases
- Zero quantity transfers
- Deleted products in active transfers
- Outlet deactivation during transfers
- User permission changes mid-operation

---
*Generated by CIS Knowledge Base System*
