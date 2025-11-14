<?php
/**
 * Website Operations Module - Shipping Carrier Configuration
 *
 * Configuration for all shipping carriers and their rates
 * Used by the ShippingOptimizationService
 *
 * @version 1.0.0
 * @author Ecigdis Development Team
 * @date 2025-11-14
 */

return [
    /**
     * NZ Post - Standard nationwide carrier
     */
    'nz_post' => [
        'name' => 'NZ Post',
        'enabled' => true,
        'services' => [
            'standard' => [
                'name' => 'Standard Post',
                'delivery_days' => '3-5',
                'base_rate' => 5.00,
                'per_kg' => 2.50,
                'max_weight' => 30,  // kg
                'tracking' => false,
                'signature' => false
            ],
            'courier' => [
                'name' => 'CourierPost',
                'delivery_days' => '1-3',
                'base_rate' => 8.50,
                'per_kg' => 3.00,
                'max_weight' => 30,
                'tracking' => true,
                'signature' => false
            ],
            'express' => [
                'name' => 'Express Post',
                'delivery_days' => '1-2',
                'base_rate' => 12.00,
                'per_kg' => 4.00,
                'max_weight' => 25,
                'tracking' => true,
                'signature' => true
            ]
        ],
        'zones' => [
            'local' => [
                'name' => 'Local (0-50km)',
                'multiplier' => 1.0
            ],
            'regional' => [
                'name' => 'Regional (50-200km)',
                'multiplier' => 1.2
            ],
            'national' => [
                'name' => 'National (200km+)',
                'multiplier' => 1.5
            ]
        ]
    ],

    /**
     * CourierPost - Fast delivery service
     */
    'courierpost' => [
        'name' => 'CourierPost',
        'enabled' => true,
        'services' => [
            'standard' => [
                'name' => 'Standard Courier',
                'delivery_days' => '1-2',
                'base_rate' => 10.00,
                'per_kg' => 3.50,
                'max_weight' => 25,
                'tracking' => true,
                'signature' => true
            ],
            'overnight' => [
                'name' => 'Overnight',
                'delivery_days' => '1',
                'base_rate' => 15.00,
                'per_kg' => 4.50,
                'max_weight' => 20,
                'tracking' => true,
                'signature' => true
            ],
            'express' => [
                'name' => 'Express',
                'delivery_days' => '0-1',
                'base_rate' => 20.00,
                'per_kg' => 5.50,
                'max_weight' => 15,
                'tracking' => true,
                'signature' => true
            ]
        ],
        'zones' => [
            'metro' => [
                'name' => 'Metro Areas',
                'multiplier' => 1.0
            ],
            'rural' => [
                'name' => 'Rural Areas',
                'multiplier' => 1.4
            ]
        ]
    ],

    /**
     * Fastway - Budget-friendly option
     */
    'fastway' => [
        'name' => 'Fastway Couriers',
        'enabled' => true,
        'services' => [
            'parcel' => [
                'name' => 'Parcel Delivery',
                'delivery_days' => '2-4',
                'base_rate' => 6.50,
                'per_kg' => 2.00,
                'max_weight' => 25,
                'tracking' => true,
                'signature' => false
            ],
            'local' => [
                'name' => 'Local Delivery',
                'delivery_days' => '1-2',
                'base_rate' => 7.50,
                'per_kg' => 2.50,
                'max_weight' => 25,
                'tracking' => true,
                'signature' => false
            ]
        ],
        'zones' => [
            'local' => [
                'name' => 'Local (0-100km)',
                'multiplier' => 1.0
            ],
            'regional' => [
                'name' => 'Regional (100km+)',
                'multiplier' => 1.3
            ]
        ]
    ],

    /**
     * Store Collection - No shipping cost
     */
    'store_pickup' => [
        'name' => 'Store Pickup',
        'enabled' => true,
        'services' => [
            'pickup' => [
                'name' => 'Collect from Store',
                'delivery_days' => '0',
                'base_rate' => 0.00,
                'per_kg' => 0.00,
                'max_weight' => 999,
                'tracking' => false,
                'signature' => true  // Customer signs on pickup
            ]
        ],
        'zones' => [
            'any' => [
                'name' => 'Any Store',
                'multiplier' => 1.0
            ]
        ]
    ],

    /**
     * Pricing rules and modifiers
     */
    'pricing_rules' => [
        // Free shipping threshold
        'free_shipping_threshold' => 100.00,  // Orders over $100 get free shipping

        // Bulk discounts
        'bulk_discounts' => [
            ['min_weight' => 10, 'discount' => 0.10],  // 10% off 10kg+
            ['min_weight' => 20, 'discount' => 0.15],  // 15% off 20kg+
            ['min_weight' => 30, 'discount' => 0.20],  // 20% off 30kg+
        ],

        // Volume surcharges (for bulky items)
        'volume_surcharge' => [
            'threshold' => 0.1,  // 0.1 cubic meters
            'per_cubic_meter' => 15.00
        ],

        // Remote area surcharges
        'remote_areas' => [
            'Great Barrier Island' => 25.00,
            'Chatham Islands' => 50.00,
            'Stewart Island' => 30.00
        ],

        // Fuel surcharge (percentage)
        'fuel_surcharge' => 0.05,  // 5%

        // Insurance (optional, per $100 value)
        'insurance_per_100' => 2.00
    ],

    /**
     * Store locations with coordinates for distance calculation
     */
    'store_locations' => [
        1 => ['name' => 'Auckland CBD', 'lat' => -36.8485, 'lng' => 174.7633],
        2 => ['name' => 'Wellington', 'lat' => -41.2865, 'lng' => 174.7762],
        3 => ['name' => 'Christchurch', 'lat' => -43.5321, 'lng' => 172.6362],
        4 => ['name' => 'Hamilton', 'lat' => -37.7870, 'lng' => 175.2793],
        5 => ['name' => 'Tauranga', 'lat' => -37.6878, 'lng' => 176.1651],
        6 => ['name' => 'Dunedin', 'lat' => -45.8788, 'lng' => 170.5028],
        7 => ['name' => 'Palmerston North', 'lat' => -40.3523, 'lng' => 175.6082],
        8 => ['name' => 'Napier', 'lat' => -39.4928, 'lng' => 176.9120],
        9 => ['name' => 'New Plymouth', 'lat' => -39.0556, 'lng' => 174.0752],
        10 => ['name' => 'Rotorua', 'lat' => -38.1368, 'lng' => 176.2497],
        11 => ['name' => 'Whangarei', 'lat' => -35.7275, 'lng' => 174.3166],
        12 => ['name' => 'Invercargill', 'lat' => -46.4132, 'lng' => 168.3538],
        13 => ['name' => 'Nelson', 'lat' => -41.2706, 'lng' => 173.2840],
        14 => ['name' => 'Queenstown', 'lat' => -45.0312, 'lng' => 168.6626],
        15 => ['name' => 'Gisborne', 'lat' => -38.6627, 'lng' => 178.0174],
        16 => ['name' => 'Whanganui', 'lat' => -39.9333, 'lng' => 175.0500],
        17 => ['name' => 'Blenheim', 'lat' => -41.5131, 'lng' => 173.9545]
    ],

    /**
     * Delivery time estimations by distance
     */
    'delivery_times' => [
        'same_city' => [
            'min_days' => 1,
            'max_days' => 2
        ],
        'regional' => [
            'min_days' => 2,
            'max_days' => 4
        ],
        'national' => [
            'min_days' => 3,
            'max_days' => 7
        ],
        'rural' => [
            'min_days' => 4,
            'max_days' => 10
        ]
    ],

    /**
     * Package size limits
     */
    'package_limits' => [
        'max_weight_kg' => 30,
        'max_length_cm' => 120,
        'max_width_cm' => 80,
        'max_height_cm' => 80,
        'max_girth_cm' => 300  // length + 2*(width + height)
    ],

    /**
     * Optimization preferences
     */
    'optimization' => [
        // Priority: 'cost', 'speed', 'reliability'
        'priority' => 'cost',

        // Consider these factors
        'factors' => [
            'cost' => 1.0,           // Weight: 100%
            'delivery_time' => 0.3,  // Weight: 30%
            'tracking' => 0.2,       // Weight: 20%
            'reliability' => 0.5     // Weight: 50%
        ],

        // Preferred carriers (in order)
        'preferred_carriers' => ['fastway', 'nz_post', 'courierpost'],

        // Avoid carriers for certain product types
        'carrier_restrictions' => [
            'fragile' => ['fastway'],  // Don't use Fastway for fragile items
            'high_value' => [],        // All carriers ok for high value
            'hazardous' => ['store_pickup']  // Must use proper courier for hazardous
        ]
    ],

    /**
     * Tracking configuration
     */
    'tracking' => [
        'nz_post' => [
            'url_template' => 'https://www.nzpost.co.nz/tools/tracking?trackingNumber={tracking_number}',
            'api_endpoint' => 'https://api.nzpost.co.nz/parceltrack/v3/parcels/{tracking_number}',
            'api_key_required' => true
        ],
        'courierpost' => [
            'url_template' => 'https://www.courierpost.co.nz/track/{tracking_number}',
            'api_endpoint' => 'https://api.courierpost.co.nz/tracking/{tracking_number}',
            'api_key_required' => true
        ],
        'fastway' => [
            'url_template' => 'https://www.fastway.co.nz/track/{tracking_number}',
            'api_endpoint' => 'https://api.fastway.co.nz/track/{tracking_number}',
            'api_key_required' => true
        ]
    ]
];
