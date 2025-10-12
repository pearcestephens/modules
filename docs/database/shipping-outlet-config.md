# Transfer System Shipping & Outlet Configuration

**Updated:** October 12, 2025  
**Context:** Critical shipping fields for transfer operations  

## NZ Post Integration Fields

### Per-Outlet Shipping Configuration
```sql
-- Get outlet shipping configuration
SELECT id, name,
       nz_post_api_key,
       nz_post_subscription_key,
       gss_token
FROM vend_outlets 
WHERE deleted_at = '0000-00-00 00:00:00'
  AND id = ?
```

**Field Usage:**
- `nz_post_api_key` - Required for NZ Post shipping label generation
- `nz_post_subscription_key` - Required for NZ Post subscription services  
- `gss_token` - Google services integration (tracking, maps, etc.)

## Complete Address Data

### Full Outlet Address Query
```sql
-- Get complete outlet address for shipping labels
SELECT id, name, email, physical_phone_number,
       physical_street_number,
       physical_street,
       physical_address_1,
       physical_address_2,
       physical_suburb,
       physical_city,
       physical_postcode,
       physical_state,
       physical_country_id,
       outlet_lat,
       outlet_long
FROM vend_outlets 
WHERE deleted_at = '0000-00-00 00:00:00'
  AND id = ?
```

## Transfer System Address Usage

### From/To Address Resolution
```sql
-- Get both outlet addresses for transfer
SELECT 
    -- FROM outlet
    o_from.id AS from_outlet_id,
    o_from.name AS from_outlet_name,
    o_from.physical_address_1 AS from_address_1,
    o_from.physical_address_2 AS from_address_2,
    o_from.physical_suburb AS from_suburb,
    o_from.physical_city AS from_city,
    o_from.physical_postcode AS from_postcode,
    o_from.physical_phone_number AS from_phone,
    o_from.nz_post_api_key AS from_api_key,
    
    -- TO outlet  
    o_to.id AS to_outlet_id,
    o_to.name AS to_outlet_name,
    o_to.physical_address_1 AS to_address_1,
    o_to.physical_address_2 AS to_address_2,
    o_to.physical_suburb AS to_suburb,
    o_to.physical_city AS to_city,
    o_to.physical_postcode AS to_postcode,
    o_to.physical_phone_number AS to_phone,
    o_to.nz_post_api_key AS to_api_key
    
FROM transfers t
LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id
LEFT JOIN vend_outlets o_to ON t.to_outlet_id = o_to.id
WHERE t.id = ?
  AND o_from.deleted_at = '0000-00-00 00:00:00'
  AND o_to.deleted_at = '0000-00-00 00:00:00'
```

## Address Formatting Functions

### NZ Address Format
```php
function formatNZAddress(array $outlet): string {
    $parts = [];
    
    if (!empty($outlet['physical_street_number']) || !empty($outlet['physical_street'])) {
        $street = trim($outlet['physical_street_number'] . ' ' . $outlet['physical_street']);
        if ($street) $parts[] = $street;
    }
    
    if (!empty($outlet['physical_address_1'])) {
        $parts[] = $outlet['physical_address_1'];
    }
    
    if (!empty($outlet['physical_address_2'])) {
        $parts[] = $outlet['physical_address_2'];
    }
    
    if (!empty($outlet['physical_suburb'])) {
        $parts[] = $outlet['physical_suburb'];
    }
    
    $cityPostcode = trim($outlet['physical_city'] . ' ' . $outlet['physical_postcode']);
    if ($cityPostcode) $parts[] = $cityPostcode;
    
    if (!empty($outlet['physical_state'])) {
        $parts[] = $outlet['physical_state'];
    }
    
    return implode("\n", $parts);
}
```

### Shipping Label Data
```php
function getShippingLabelData(int $transferId): array {
    global $con;
    
    $stmt = $con->prepare("
        SELECT t.id AS transfer_id,
               -- FROM outlet
               o_from.name AS from_name,
               o_from.physical_address_1 AS from_address_1,
               o_from.physical_suburb AS from_suburb,
               o_from.physical_city AS from_city,
               o_from.physical_postcode AS from_postcode,
               o_from.physical_phone_number AS from_phone,
               o_from.nz_post_api_key AS from_api_key,
               
               -- TO outlet
               o_to.name AS to_name,
               o_to.physical_address_1 AS to_address_1,
               o_to.physical_suburb AS to_suburb,
               o_to.physical_city AS to_city,
               o_to.physical_postcode AS to_postcode,
               o_to.physical_phone_number AS to_phone,
               o_to.nz_post_api_key AS to_api_key
               
        FROM transfers t
        LEFT JOIN vend_outlets o_from ON t.from_outlet_id = o_from.id
        LEFT JOIN vend_outlets o_to ON t.to_outlet_id = o_to.id
        WHERE t.id = ?
          AND o_from.deleted_at = '0000-00-00 00:00:00'
          AND o_to.deleted_at = '0000-00-00 00:00:00'
    ");
    
    $stmt->bind_param('i', $transferId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc() ?: [];
}
```

## API Integration Requirements

### NZ Post API Usage
- **API Key Location**: `vend_outlets.nz_post_api_key`
- **Subscription Key**: `vend_outlets.nz_post_subscription_key`
- **Required for**: Shipping label generation, tracking, delivery estimates
- **Validation**: Check both keys exist before attempting API calls

### Google Services Integration  
- **Token Location**: `vend_outlets.gss_token`
- **Used for**: Maps integration, geocoding, delivery optimization
- **Optional**: System should work without Google integration

## Validation Rules

### Address Completeness Check
```sql
-- Check if outlet has complete address for shipping
SELECT id, name,
       CASE WHEN physical_address_1 IS NOT NULL 
            AND physical_address_1 != ''
            AND physical_city IS NOT NULL
            AND physical_city != ''
            AND physical_postcode IS NOT NULL
            AND physical_postcode != ''
       THEN 1 ELSE 0 END AS address_complete,
       
       CASE WHEN nz_post_api_key IS NOT NULL 
            AND nz_post_api_key != ''
       THEN 1 ELSE 0 END AS shipping_enabled
       
FROM vend_outlets
WHERE deleted_at = '0000-00-00 00:00:00'
  AND id = ?
```

## Critical Notes

1. **Always check deleted_at = '0000-00-00 00:00:00'** for valid outlets
2. **API keys are outlet-specific** - different stores may use different shipping accounts
3. **Address validation required** before generating shipping labels
4. **Phone numbers essential** for delivery coordination
5. **Lat/Long coordinates available** for route optimization