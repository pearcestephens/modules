<?php
/**
 * Warehouse Configuration
 *
 * Controls warehouse operation modes and transition settings
 *
 * @package CIS\Config
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Warehouse Operation Mode
    |--------------------------------------------------------------------------
    |
    | Supported modes:
    | - 'single': Current setup (Frankton only)
    | - 'dual': Transition period (Frankton + Dedicated Warehouse)
    | - 'dedicated': Future state (Dedicated Warehouse only)
    |
    */
    'mode' => getenv('WAREHOUSE_MODE') ?: 'single',

    /*
    |--------------------------------------------------------------------------
    | Primary Warehouse
    |--------------------------------------------------------------------------
    |
    | Current warehouse: Frankton (hybrid: warehouse + retail + juice mfg)
    |
    */
    'primary_warehouse_id' => getenv('PRIMARY_WAREHOUSE_ID') ?: 'frankton_001',
    'primary_warehouse_name' => 'Frankton - Warehouse & Juice Manufacturing',

    /*
    |--------------------------------------------------------------------------
    | Dedicated Warehouse (Future)
    |--------------------------------------------------------------------------
    |
    | New dedicated warehouse for general merchandise
    | Juice manufacturing stays at Frankton
    |
    */
    'dedicated_warehouse_id' => getenv('DEDICATED_WAREHOUSE_ID') ?: null,
    'dedicated_warehouse_name' => getenv('DEDICATED_WAREHOUSE_NAME') ?: 'Dedicated Warehouse',

    /*
    |--------------------------------------------------------------------------
    | Juice Manufacturing
    |--------------------------------------------------------------------------
    |
    | Juice is ALWAYS manufactured and shipped from Frankton
    |
    */
    'juice_manufacturing_outlet_id' => getenv('JUICE_MFG_OUTLET_ID') ?: 'frankton_001',

    /*
    |--------------------------------------------------------------------------
    | Fallback Settings
    |--------------------------------------------------------------------------
    |
    | If warehouse doesn't have stock, allow fallback to hub/flagship stores
    |
    */
    'fallback_enabled' => (bool)getenv('WAREHOUSE_FALLBACK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Stock Source Priority
    |--------------------------------------------------------------------------
    |
    | Priority order when looking for stock sources
    |
    */
    'stock_source_priority' => [
        'warehouse',    // Try warehouses first
        'hub_store',    // Then hub stores (Frankton, Hamilton East, Christchurch)
        'flagship',     // Then flagship stores
        'any'           // Last resort: any outlet with stock
    ],

    /*
    |--------------------------------------------------------------------------
    | Transition Settings
    |--------------------------------------------------------------------------
    |
    | Rules for dual warehouse operation during transition
    |
    */
    'dual_mode_settings' => [
        // When both warehouses have stock, prefer:
        'prefer_dedicated' => true, // Prefer dedicated warehouse (reduce Frankton congestion)

        // Minimum stock threshold before using fallback
        'min_stock_threshold' => 10,

        // Products that MUST stay at Frankton
        'frankton_only_products' => [
            // Juice products automatically detected
            // Add any other Frankton-specific products here
        ],

        // Load balancing
        'enable_load_balancing' => true,
        'load_balance_threshold' => 100, // Orders/day per warehouse
    ],
];
