# 🚚 FREIGHT & CARRIER SYSTEM - COMPREHENSIVE ANALYSIS
**Advanced Product Sizing, Categorization & Freight Calculation System**  
**Date:** October 12, 2025  
**Purpose:** Deep understanding of sophisticated freight calculation infrastructure

---

## 📋 SYSTEM OVERVIEW

This is a **sophisticated multi-carrier freight calculation engine** with:
- **Advanced product dimensioning** (weight, volume, fragility classification)
- **Dynamic container optimization** with live carrier API integration
- **Multi-carrier support** (NZ Post eShip, GoSweetSpot, manual carriers)
- **Intelligent box allocation** using AI-driven packing algorithms
- **Real-time pricing** with fallback to DB pricebooks
- **Volumetric weight calculations** with carrier-specific factors

---

## 🗄️ **CORE DATABASE SCHEMA ANALYSIS**

### **1. Product Dimensioning & Classification**

#### **Product Weight & Dimensions**
```sql
-- Primary product data (from vend_products)
vend_products.avg_weight_grams        -- Product weight fallback
vend_products.supplier_id             -- Links to vend_suppliers
vend_products.product_type_id         -- Links to vend_categories

-- Specific dimension overrides
product_dimensions.weight_g           -- Exact product weight
product_dimensions.length_mm          -- Product length  
product_dimensions.width_mm           -- Product width
product_dimensions.height_mm          -- Product height
product_dimensions.volume_cm3         -- Calculated volume
product_dimensions.fragile            -- Fragility flag
product_dimensions.stackable          -- Stacking capability

-- Category-level defaults (fallback system)
category_weights.avg_weight_grams     -- Category weight average
category_weights.avg_volume_cm3       -- Category volume average
category_dimensions.avg_length_mm     -- Category dimension defaults
category_dimensions.confidence_score  -- Data confidence level
```

#### **Sophisticated Fallback Hierarchy**
1. **Product-specific** dimensions (highest priority)
2. **Category averages** (medium priority) 
3. **Hard-coded defaults** (500g, basic dimensions)

### **2. Carrier & Container Infrastructure**

#### **Carrier Management**
```sql
carriers.carrier_id                   -- Primary key
carriers.code                         -- 'NZPOST', 'GSS'
carriers.name                         -- Display name
carriers.volumetric_factor           -- Weight calculation factor (default: 200)

carrier_services.service_id          -- Service variants
carrier_services.code                -- 'STANDARD', 'EXPRESS'
carrier_services.name                -- Service display name

containers.container_id              -- Container types
containers.code                      -- 'DLE', 'A4', 'PARCEL', 'Small Box'
containers.kind                      -- 'bag', 'box', 'document'
containers.length_mm                 -- Container dimensions
containers.max_weight_grams          -- Weight capacity
```

#### **Dynamic Pricing System**
```sql
pricing_rules.rule_id                -- Pricing entries
pricing_rules.carrier_id             -- Links to carriers
pricing_rules.service_id             -- Optional service
pricing_rules.container_id           -- Container type
pricing_rules.price                  -- Cost in NZD
pricing_rules.effective_from         -- Date range validity
pricing_rules.effective_to

-- Computed view for easy access
pricing_matrix                       -- Unified pricing view
freight_rules_catalog               -- Legacy compatibility
```

### **3. Transfer Integration Points**

#### **Transfer Tables (Core System)**
```sql
transfers.id                         -- Transfer ID
transfers.outlet_from               -- Source outlet
transfers.outlet_to                 -- Destination outlet
transfers.mode                      -- 'GENERAL', 'JUICE', 'STAFF', 'SUPPLIER'

transfer_items.transfer_id          -- Links to transfers
transfer_items.product_id           -- Links to vend_products
transfer_items.qty_requested        -- Requested quantity
transfer_items.qty_sent_total       -- Actual sent quantity

vend_outlets.id                     -- Outlet information
vend_outlets.physical_address_1     -- Shipping addresses
vend_outlets.physical_postcode      -- For distance calculation
vend_outlets.is_warehouse           -- Warehouse flag
```

#### **User & Permission System**
```sql
vend_users.id                       -- User management
vend_users.outlet_id                -- Default outlet
vend_users.restricted_outlet_id     -- Access restrictions

users.id                           -- CIS user system
users.default_outlet               -- Staff outlet assignment
users.role_id                      -- Permission level

user_roles.id                      -- Role definitions
user_permissions.permission_id     -- Granular permissions
```

---

## 🧠 **FREIGHT CALCULATION ENGINE**

### **1. CisFreight Class - Core API**

#### **Primary Methods**
```php
// Container selection with constraints
CisFreight::pickContainer($carrierId, $length, $width, $height, $weight)
CisFreight::pickContainerDynamic($carrierId, $weight, $volume, $dims...)

// Pricing calculations  
CisFreight::priceLineCost($productId, $qty, $carrierId)
CisFreight::priceCartConsolidated($lines, $carrierId)
CisFreight::priceCartPerLine($lines, $carrierId)

// Comprehensive transfer analysis
CisFreight::calculateTransferDimensions($lines)
CisFreight::getAvailableContainers($carrierId)
```

#### **Smart Input Validation**
- ❌ **Rejects invalid weights** (≤ 0g)
- ❌ **Blocks oversized items** (> 3m dimensions, > 1m³ volume)
- ✅ **Provides user-friendly error messages**
- ✅ **Calculates volumetric vs actual weight**

#### **Container Selection Algorithm**
1. **Load available containers** for carrier
2. **Apply constraints** (weight, dimensions, volume)
3. **Calculate fit scores** (utilization vs cost efficiency)
4. **Return optimal container** with pricing

### **2. BoxAllocationService - AI Packing**

#### **Sophisticated Auto-Sorting**
```php
// Main allocation workflow
BoxAllocationService::generateOptimalAllocation()
  ├── getTransferItemsWithDimensions()    // Full product data
  ├── preprocessItems()                    // Sort by compatibility  
  ├── runAllocationAlgorithm()            // Core packing logic
  ├── optimizeWithFreightConstraints()    // Carrier optimization
  └── generatePricingRecommendations()    // Cost analysis
```

#### **Product Compatibility Rules**
```php
$incompatible_categories = [
    'NICOTINE' => ['NON_NICOTINE'],        // Regulatory separation
    'GLASS' => ['HEAVY_METALS'],          // Damage prevention
    'LIQUIDS' => ['ELECTRONICS']          // Safety separation
];
```

#### **Box Templates**
```php
$box_templates = [
    'small'  => ['150×100×80mm',  'max_weight_g' => 1000],
    'medium' => ['300×200×150mm', 'max_weight_g' => 5000], 
    'large'  => ['400×300×200mm', 'max_weight_g' => 15000],
    'xl'     => ['500×400×300mm', 'max_weight_g' => 22000]
];
```

### **3. CarrierContainerOptimizer - Cost Optimization**

#### **Multi-Pass Optimization**
1. **Consolidation Pass** - Merge under-utilized boxes
2. **Downsizing Pass** - Move to smaller/cheaper containers
3. **Cost Analysis** - Calculate savings vs original

#### **Utilization Scoring**
```php
// Ideal utilization: 70-90% capacity
if ($utilization >= 0.7 && $utilization <= 0.9) {
    $utilizationScore = 1.0;  // Perfect
} else if ($utilization < 0.7) {
    $utilizationScore = $utilization / 0.7;  // Penalize empty space
} else {
    $utilizationScore = 0.5;  // Penalize cutting it close
}
```

---

## 🎯 **KEY INTEGRATION POINTS FOR BASE TEMPLATE**

### **1. Product Weight Resolution**
```sql
-- Weight fallback chain (used in CisFreight)
SELECT COALESCE(
    vp.avg_weight_grams,           -- Product specific
    cw.avg_weight_grams,           -- Category average  
    500                            -- Safe default (improved from 100g)
) as unit_weight_g
FROM vend_products vp
LEFT JOIN product_classification_unified pcu ON pcu.product_id = vp.id
LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
WHERE vp.id = :product_id
```

### **2. Freight Cost Calculation**
```php
// For BASE template integration
$freight_cost = CisFreight::priceCartConsolidated($transfer_items, $carrier_id);
$box_allocation = $box_service->generateOptimalAllocation();
$optimized_boxes = $optimizer->optimize($box_allocation['boxes']);
```

### **3. Container Selection UI**
```php
// Dynamic container options for freight panel
$containers = CisFreight::getAvailableContainers($carrier_id);
foreach ($containers as $container) {
    echo "<option value='{$container['container_code']}' 
                  data-max-weight='{$container['actual_cap_g']}'
                  data-cost='{$container['cost']}'>
            {$container['container_name']} - \${$container['cost']}
          </option>";
}
```

---

## 📊 **CRITICAL MYSQL VIEWS FOR BASE TEMPLATE**

### **1. v_product_pack_profile**
**Purpose:** Complete product profile with dimensions & weight
```sql
-- Returns comprehensive product info with fallbacks
SELECT product_id, sku, product_name, category_id,
       COALESCE(length_mm, typical_length_mm, 0) as length_mm,
       COALESCE(weight_g, typical_weight_g, 0) as unit_weight_g,
       CASE WHEN data_source = 'product' THEN 'high'
            WHEN data_source = 'category_default' THEN 'low'
            ELSE 'unknown' END as confidence
FROM v_product_pack_profile 
WHERE product_id = :id
```

### **2. v_carrier_container_prices**
**Purpose:** Unified carrier pricing with container specs
```sql
-- Essential for container selection
SELECT carrier_name, carrier_id, container_code, container_name,
       length_mm, width_mm, height_mm, 
       container_cap_g, rule_cap_g, max_units, cost
FROM v_carrier_container_prices
WHERE carrier_id = :carrier_id
  AND rule_cap_g >= :required_weight_g
ORDER BY cost ASC, rule_cap_g ASC
```

### **3. v_freight_rules_catalog**
**Purpose:** Complete freight options overview
```sql
-- For freight panel population
SELECT carrier_name, container_code, container_name,
       container_max_weight_g, max_units, cost
FROM v_freight_rules_catalog
ORDER BY carrier_name, container_code
```

---

## 🚨 **GOTCHAS & CRITICAL NOTES**

### **Database Column Names (EXACT)**
```sql
-- Transfer System
transfers.outlet_from               -- NOT outlet_id_from
transfers.outlet_to                 -- NOT outlet_id_to  
transfer_items.qty_requested        -- NOT quantity_requested
transfer_items.qty_sent_total       -- NOT quantity_sent

-- Product System
vend_products.avg_weight_grams      -- NOT weight_grams
vend_products.product_type_id       -- NOT category_id
vend_outlets.physical_address_1     -- NOT address_1
vend_outlets.is_warehouse           -- NOT warehouse_flag

-- Freight System  
containers.max_weight_grams         -- NOT max_weight_g
freight_rules.max_weight_grams      -- NOT weight_limit
pricing_rules.price                 -- NOT cost
carriers.volumetric_factor          -- NOT volumetric_rate
```

### **Weight Units Consistency**
- **Database:** All weights in **grams** (`max_weight_grams`, `avg_weight_grams`)
- **UI Display:** Convert to **kg** for user display
- **API:** Accept kg, convert to grams internally

### **Container Code Standards**
- **NZ Post:** `'DLE'`, `'A4'`, `'A5'`, `'PARCEL'`, `'Small Box'`
- **GSS:** `'SATCHEL'`, `'BOX_S'`, `'BOX_M'`, `'BOX_L'`
- **Manual:** `'MANUAL_COURIER'`, `'PICKUP'`, `'DROPOFF'`

---

## 🎯 **BASE TEMPLATE INTEGRATION STRATEGY**

### **1. Freight Panel Enhancement**
- ✅ **Auto-calculate** transfer weight using `CisFreight::totalWeightG()`
- ✅ **Dynamic container options** from `getAvailableContainers()`
- ✅ **Real-time cost updates** on container selection
- ✅ **Box allocation button** launching `BoxAllocationService`

### **2. Smart Validation**
- ✅ **Weight validation** against container capacity
- ✅ **Dimension validation** for oversized items
- ✅ **Cost estimation** before submission
- ✅ **Error handling** for invalid freight configurations

### **3. Mode-Specific Behavior**
```javascript
// Mode-specific freight constraints
const modeConstraints = {
    'GENERAL': { 
        allowedCarriers: [1, 2],  // NZ Post + GSS
        maxWeight: 22000,         // 22kg limit
        allowFragile: true 
    },
    'JUICE': { 
        allowedCarriers: [1],     // NZ Post only (compliance)
        maxWeight: 15000,         // 15kg limit  
        requiresSignature: true 
    },
    'STAFF': { 
        allowedCarriers: [2],     // GSS only (cost)
        maxWeight: 10000,         // 10kg limit
        requiresApproval: true 
    },
    'SUPPLIER': { 
        allowedCarriers: [1, 2],  // All carriers
        maxWeight: 25000,         // 25kg limit
        requiresEvidence: true 
    }
};
```

---

## 🚀 **READY FOR BASE TEMPLATE IMPLEMENTATION**

The freight system provides **production-ready infrastructure** for:

1. ✅ **Accurate weight calculation** with smart fallbacks
2. ✅ **Dynamic carrier selection** with live pricing
3. ✅ **Intelligent box allocation** with AI optimization  
4. ✅ **Cost optimization** across multiple carriers
5. ✅ **Regulatory compliance** for different transfer modes
6. ✅ **Error prevention** with sophisticated validation

**Next Step:** Create BASE transfer template integrating this freight system with:
- Smart freight panel using `CisFreight` API
- Auto-allocation button using `BoxAllocationService`
- Real-time cost updates using `pricing_matrix` view
- Mode-specific constraints using freight configuration

Ready to proceed with BASE template implementation! 🎯