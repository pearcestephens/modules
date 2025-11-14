<?php
/**
 * Product Intelligence Configuration
 *
 * Core configuration for SKU-less product matching, multi-strategy extraction,
 * GPT Vision integration, Chrome automation, and anti-bot bypasses.
 *
 * @version 3.0.0
 */

return [
    /**
     * Product Matching Configuration
     */
    'matching' => [
        'use_ml_scoring' => true,
        'use_image_matching' => true,
        'use_brand_extraction' => true,
        'min_confidence' => 0.50,

        'thresholds' => [
            'exact_match' => 0.95,
            'strong_match' => 0.85,
            'medium_match' => 0.70,
            'weak_match' => 0.50,
        ],

        'weights' => [
            'name' => 0.40,
            'brand' => 0.20,
            'sku' => 0.25,
            'attributes' => 0.15,
            'image' => 0.10,
        ],
    ],

    /**
     * Multi-Strategy Extraction Configuration
     */
    'extraction' => [
        'use_all_strategies' => true,
        'stop_on_first_success' => false,
        'min_confidence' => 0.70,
        'max_extraction_time' => 30, // seconds

        'strategy_priority' => [
            'api' => 10,        // Highest - cleanest data
            'schema' => 9,      // Structured data
            'dom' => 8,         // Primary fallback
            'dropdown' => 6,    // Good for variants
            'hidden' => 5,      // Often contains IDs
            'network' => 4,     // Traffic analysis
            'screenshot' => 2,  // GPT Vision fallback
        ],
    ],

    /**
     * Price Extraction Configuration
     */
    'price' => [
        'default_currency' => 'NZD',
        'assume_gst_incl' => true, // NZ default
        'detect_sale_prices' => true,
        'detect_price_ranges' => true,
        'gst_rate' => 0.15, // 15% for NZ
    ],

    /**
     * Chrome Automation Configuration
     */
    'chrome' => [
        'puppeteer_url' => getenv('PUPPETEER_URL') ?: 'http://localhost:3000',
        'profiles_dir' => '/home/master/applications/jcepnzzkmj/private_html/chrome-profiles/',
        'headless' => true,
        'default_viewport' => [
            'width' => 1920,
            'height' => 1080,
        ],
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0',
        'enable_cdp' => true, // Chrome DevTools Protocol
        'intercept_requests' => true,
        'capture_console' => true,
        'simulate_google_referrer' => true,
        'auto_bypass_age_gates' => true,
    ],

    /**
     * GPT Vision Configuration
     */
    'vision' => [
        'enabled' => true,
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => 'gpt-4-vision-preview',
        'max_tokens' => 4096,
        'detail_level' => 'high', // low, high, auto

        'screenshot' => [
            'full_page' => true,
            'element_closeups' => true,
            'format' => 'png',
            'quality' => 90,
        ],

        'prompts' => [
            'product_extraction' => "Analyze this product page image and extract:\n1. Product name\n2. Brand\n3. Price (with currency)\n4. Stock availability\n5. Any variants (flavors, colors, sizes)\n6. SKU or model number if visible\n\nReturn as structured JSON.",

            'price_extraction' => "Extract all prices visible in this image. For each price, identify:\n1. The amount\n2. Currency\n3. Whether GST is included or excluded\n4. If it's a sale price or regular price\n\nReturn as structured JSON.",
        ],
    ],

    /**
     * Cloudflare Bypass Configuration
     */
    'cloudflare' => [
        'enabled' => true,
        'max_retries' => 3,
        'retry_delay' => 5000, // ms
        'use_browser' => true, // Required for JS challenges
        'solver_service' => getenv('CAPTCHA_SOLVER'), // 2captcha, anticaptcha
        'solver_api_key' => getenv('CAPTCHA_API_KEY'),
    ],

    /**
     * reCAPTCHA v3 Configuration
     */
    'recaptcha' => [
        'enabled' => true,
        'min_score' => 0.7,
        'solver_service' => getenv('CAPTCHA_SOLVER'),
        'solver_api_key' => getenv('CAPTCHA_API_KEY'),
    ],

    /**
     * PerimeterX Bypass Configuration
     */
    'perimeterx' => [
        'enabled' => true,
        'use_browser' => true, // Required
        'advanced_fingerprinting' => true,
    ],

    /**
     * DataDome Bypass Configuration
     */
    'datadome' => [
        'enabled' => true,
        'use_browser' => true, // Required
        'challenge_timeout' => 30000, // ms
    ],

    /**
     * Storage Configuration
     */
    'storage' => [
        'extracted_data_ttl' => 86400, // 24 hours
        'screenshot_ttl' => 604800, // 7 days
        'cache_enabled' => true,
        'cache_prefix' => 'product_intel:',

        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'database' => 2, // Different from crawler
            'password' => getenv('REDIS_PASSWORD'),
        ],
    ],

    /**
     * Logging Configuration
     */
    'logging' => [
        'level' => getenv('LOG_LEVEL') ?: 'info',
        'channels' => ['file', 'stdout'],
        'log_dir' => '/home/master/applications/jcepnzzkmj/private_html/logs/product-intelligence/',
        'max_files' => 30,
        'log_extraction' => true,
        'log_matching' => true,
        'log_bypass_attempts' => true,
    ],

    /**
     * Rate Limiting Configuration
     */
    'rate_limiting' => [
        'enabled' => true,
        'requests_per_second' => 1.0,
        'burst_size' => 5,
        'per_domain' => true,
    ],

    /**
     * Database Configuration
     */
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_DATABASE') ?: 'cis',
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
];
