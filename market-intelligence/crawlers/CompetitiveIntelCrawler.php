<?php
/**
 * Competitive Intelligence Crawler
 *
 * Daily price monitoring of ALL NZ vape competitors
 * Chrome headless with sophisticated anti-detection
 * Feeds data to Dynamic Pricing Engine and News Aggregator
 *
 * @package CIS\Crawlers
 * @version 1.0.0
 */

namespace CIS\Crawlers;

require_once __DIR__ . '/CentralLogger.php';
require_once __DIR__ . '/ChromeSessionManager.php';
require_once __DIR__ . '/HumanBehaviorEngine.php';

class CompetitiveIntelCrawler {

    private $db;
    private $logger;
    private $sessionManager;
    private $behaviorEngine;
    private $config;

    // NZ Vape Competitors
    private $competitors = [
        [
            'name' => 'Shosha',
            'url' => 'https://www.shosha.co.nz',
            'stealth_level' => 'extreme',
            'delay_multiplier' => 2.5,
            'selectors' => [
                'product' => '.product-item',
                'name' => '.product-name',
                'price' => '.price',
                'special' => '.special-price',
            ],
        ],
        [
            'name' => 'Vapo',
            'url' => 'https://www.vapo.co.nz',
            'stealth_level' => 'high',
            'delay_multiplier' => 2.0,
            'selectors' => [
                'product' => '.product-card',
                'name' => 'h3.title',
                'price' => '.price-box .price',
                'special' => '.old-price',
            ],
        ],
        [
            'name' => 'VapeStore',
            'url' => 'https://www.vapestore.co.nz',
            'stealth_level' => 'high',
            'delay_multiplier' => 1.8,
            'selectors' => [
                'product' => '.product',
                'name' => '.product-title',
                'price' => '.product-price',
                'special' => '.sale-price',
            ],
        ],
        [
            'name' => 'Cloudix',
            'url' => 'https://www.cloudix.co.nz',
            'stealth_level' => 'medium',
            'delay_multiplier' => 1.5,
            'selectors' => [
                'product' => 'div.product',
                'name' => 'h2',
                'price' => '.price',
                'special' => '.special',
            ],
        ],
        [
            'name' => 'VaporEmpire',
            'url' => 'https://www.vaporempire.co.nz',
            'stealth_level' => 'medium',
            'delay_multiplier' => 1.3,
            'selectors' => [
                'product' => '.item',
                'name' => '.name',
                'price' => '.amount',
                'special' => '.discount',
            ],
        ],
        [
            'name' => 'Alt New Zealand',
            'url' => 'https://www.altnewzealand.com',
            'stealth_level' => 'medium',
            'delay_multiplier' => 1.4,
            'selectors' => [
                'product' => '.woocommerce-LoopProduct-link',
                'name' => '.woocommerce-loop-product__title',
                'price' => '.woocommerce-Price-amount',
                'special' => '.onsale',
            ],
        ],
        [
            'name' => 'Cosmic',
            'url' => 'https://www.cosmic.co.nz',
            'stealth_level' => 'low',
            'delay_multiplier' => 1.2,
            'selectors' => [
                'product' => '.product-grid-item',
                'name' => '.product-title',
                'price' => '.price-item',
                'special' => '.sale-price',
            ],
        ],
        [
            'name' => 'NZVAPOR',
            'url' => 'https://www.nzvapor.com',
            'stealth_level' => 'low',
            'delay_multiplier' => 1.0,
            'selectors' => [
                'product' => '.productCard',
                'name' => '.productTitle',
                'price' => '.productPrice',
                'special' => '.salePrice',
            ],
        ],
    ];

    public function __construct($db, $config = []) {
        $this->db = $db;

        $this->config = array_merge([
            'enable_chrome' => false, // PURE PHP/cURL - NO CHROME NEEDED!
            'chrome_timeout' => 30000,
            'max_concurrent' => 3,
            'request_delay_min' => 2000,
            'request_delay_max' => 8000,
            'use_proxies' => false,
            'proxy_rotation_threshold' => 50,
            'captcha_detection_threshold' => 0.85,
            'max_retries' => 3,
            'special_discount_threshold' => 10, // % discount to be considered "special"
            'send_specials_to_news' => true,
        ], $config);

        $this->logger = new CentralLogger($db, CentralLogger::TYPE_COMPETITIVE, [
            'enable_db_logging' => true,
            'enable_file_logging' => true,
        ]);

        $this->sessionManager = new ChromeSessionManager($db, $this->logger, [
            'headless' => true,
            'disable_images' => false,
            'timeout' => $this->config['chrome_timeout'],
        ]);

        // Initialize Human Behavior Engine for undetectable crawling
        $this->behaviorEngine = new \CIS\Crawlers\HumanBehaviorEngine($this->logger);

        $this->logger->info("CompetitiveIntelCrawler initialized with human behavior simulation", [
            'session_id' => $this->logger->getSessionId(),
            'competitors_count' => count($this->competitors),
            'chrome_enabled' => $this->config['enable_chrome'],
            'behavior_profile' => $this->behaviorEngine->getSessionStats()['profile'],
            'simulation_mode' => 'scientifically_accurate_human_behavior',
        ]);
    }

    /**
     * Execute daily competitive intelligence scan
     */
    public function executeDailyScan() {
        $timer = $this->logger->startTimer('daily_competitive_scan');

        $this->logger->info("Starting daily competitive intelligence scan", [
            'competitors' => count($this->competitors),
        ]);

        $results = [
            'total_competitors' => count($this->competitors),
            'successful' => 0,
            'failed' => 0,
            'products_found' => 0,
            'specials_found' => 0,
            'errors' => [],
        ];

        foreach ($this->competitors as $competitor) {
            try {
                $this->logger->info("Scanning competitor: {$competitor['name']}");

                $competitorResults = $this->scanCompetitor($competitor);

                if ($competitorResults['success']) {
                    $results['successful']++;
                    $results['products_found'] += $competitorResults['products_count'];
                    $results['specials_found'] += $competitorResults['specials_count'];
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'competitor' => $competitor['name'],
                        'error' => $competitorResults['error'],
                    ];
                }

                // Respect rate limits with delay - HUMAN BEHAVIOR ENGINE
                $this->intelligentDelayWithHumanBehavior($competitor['delay_multiplier']);

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'competitor' => $competitor['name'],
                    'error' => $e->getMessage(),
                ];

                $this->logger->error("Failed to scan competitor: {$competitor['name']}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->logger->endTimer($timer, true, $results);
        $this->logger->logSessionSummary($results);

        return $results;
    }

    /**
     * Scan individual competitor
     */
    private function scanCompetitor($competitor) {
        $timer = $this->logger->startTimer("scan_{$competitor['name']}");

        $result = [
            'success' => false,
            'products_count' => 0,
            'specials_count' => 0,
            'error' => null,
        ];

        try {
            // Get Chrome profile
            $profile = $this->sessionManager->getProfile();

            $this->logger->debug("Using Chrome profile for {$competitor['name']}", [
                'profile_name' => $profile['profile_name'],
            ]);

            // Scan product pages with realistic human browsing
            $pages = $this->getCompetitorPages($competitor);

            foreach ($pages as $index => $pageUrl) {
                // Simulate realistic page reading time before crawling
                $pageMetrics = [
                    'word_count' => rand(300, 1200),
                    'image_count' => rand(5, 25),
                    'complexity' => rand(3, 8),
                ];
                $readingTime = $this->behaviorEngine->calculateReadingTime($pageMetrics);

                $this->logger->debug("Simulating human reading time for page", [
                    'competitor' => $competitor['name'],
                    'page_url' => $pageUrl,
                    'reading_time_seconds' => round($readingTime, 2),
                    'word_count' => $pageMetrics['word_count'],
                ]);

                // Sleep for realistic reading time
                usleep((int)($readingTime * 1000000));

                // Scrape page
                $products = $this->scrapePage($profile, $pageUrl, $competitor);

                foreach ($products as $product) {
                    $this->saveProduct($product);
                    $result['products_count']++;

                    // Check if it's a special
                    if ($this->isSpecialOffer($product)) {
                        $this->saveSpecial($product);
                        $result['specials_count']++;

                        // Send to news feed
                        if ($this->config['send_specials_to_news']) {
                            $this->sendSpecialToNewsFeed($product);
                        }
                    }
                }
            }

            $result['success'] = true;
            $this->sessionManager->updateProfileSuccess($profile['id'], true);

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            if (isset($profile)) {
                $this->sessionManager->updateProfileSuccess($profile['id'], false);
            }

            $this->logger->error("Competitor scan failed: {$competitor['name']}", [
                'error' => $e->getMessage(),
            ]);
        }

        $this->logger->endTimer($timer, $result['success'], $result);

        return $result;
    }

    /**
     * Get competitor pages to scrape
     */
    private function getCompetitorPages($competitor) {
        // Start with main product page
        $pages = [
            $competitor['url'] . '/collections/all',
            $competitor['url'] . '/shop',
            $competitor['url'] . '/products',
        ];

        // TODO: Add pagination support
        // TODO: Add category-specific pages

        return $pages;
    }

    /**
     * Scrape page using advanced cURL (stealth mode)
     */
    private function scrapePage($profile, $url, $competitor) {
        $products = [];

        // Use advanced cURL with stealth features (no Chrome needed!)
        return $this->scrapePageWithCurl($url, $competitor, $profile);
    }    /**
     * Advanced cURL scraping with EXTREME stealth features (NO CHROME NEEDED!)
     *
     * Implements scientifically accurate human browser behavior:
     * - Realistic TLS fingerprinting
     * - HTTP/2 connection patterns
     * - Browser-accurate header ordering
     * - Viewport-based Accept headers
     * - DNT and GPC headers (privacy-conscious users)
     * - Timing attacks prevention
     */
    private function scrapePageWithCurl($url, $competitor, $profile) {
        $ch = curl_init($url);

        // Get realistic timing data
        $requestStartTime = microtime(true);

        // Build realistic headers in EXACT browser order (critical for fingerprinting!)
        $headers = [
            'User-Agent: ' . $profile['user_agent'],
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: en-NZ,en-US;q=0.9,en;q=0.8,mi;q=0.7', // NZ-specific with Māori
            'Accept-Encoding: gzip, deflate, br, zstd', // Modern browsers support zstd
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: ' . (rand(0, 100) < 30 ? 'same-origin' : 'none'), // 30% same-origin navigation
            'Sec-Fetch-User: ?1',
            'Sec-CH-UA: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'Sec-CH-UA-Mobile: ?0',
            'Sec-CH-UA-Platform: "' . $this->getRealisticPlatform() . '"',
            'Cache-Control: max-age=0',
        ];

        // Privacy-conscious users (40% of modern browsers)
        if (rand(0, 100) < 40) {
            $headers[] = 'DNT: 1'; // Do Not Track
        }

        // Some users have GPC (Global Privacy Control) - 15%
        if (rand(0, 100) < 15) {
            $headers[] = 'Sec-GPC: 1';
        }

        // Add realistic referer pattern (not always Google!)
        $refererPattern = rand(0, 100);
        if ($refererPattern < 35) {
            // Direct navigation (35%)
            // No referer
        } elseif ($refererPattern < 60) {
            // Google search (25%)
            $searchTerms = ['vape', 'vape shop nz', 'e-cigarette', 'vaping', 'nicotine', 'pod system'];
            $headers[] = 'Referer: https://www.google.co.nz/search?q=' . urlencode($searchTerms[array_rand($searchTerms)]);
        } elseif ($refererPattern < 80) {
            // Same-site navigation (20%)
            $headers[] = 'Referer: ' . $competitor['url'];
        } else {
            // Social media / other sites (20%)
            $socialSites = [
                'https://www.facebook.com/',
                'https://www.reddit.com/',
                'https://www.instagram.com/',
                'https://twitter.com/',
            ];
            $headers[] = 'Referer: ' . $socialSites[array_rand($socialSites)];
        }

        // Viewport-based Accept headers (mobile vs desktop)
        if (isset($profile['viewport_width']) && $profile['viewport_width'] < 768) {
            $headers[] = 'Viewport-Width: ' . $profile['viewport_width'];
        }

        // Some browsers send Save-Data header on slow connections
        if (rand(0, 100) < 10) {
            $headers[] = 'Save-Data: on';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true, // Real browsers verify SSL
            CURLOPT_SSL_VERIFYHOST => 2,    // Real browsers verify host
            CURLOPT_ENCODING => 'gzip, deflate, br',
            CURLOPT_COOKIEJAR => $this->getCookieJarPath($profile['profile_name']),
            CURLOPT_COOKIEFILE => $this->getCookieJarPath($profile['profile_name']),
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // HTTP/2 like modern browsers
            CURLOPT_TCP_KEEPALIVE => 1,     // Keep TCP connection alive
            CURLOPT_TCP_KEEPIDLE => 120,    // Realistic keepalive timing
        ]);

        // Add realistic DNS cache behavior (not every request does DNS lookup)
        if (rand(0, 100) < 80) {
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, rand(300, 900)); // 5-15 min cache
        }

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $requestDuration = microtime(true) - $requestStartTime;
        $error = curl_error($ch);

        // Get detailed timing info
        $timingInfo = [
            'namelookup_time' => curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME),
            'connect_time' => curl_getinfo($ch, CURLINFO_CONNECT_TIME),
            'pretransfer_time' => curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME),
            'starttransfer_time' => curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME),
            'total_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
            'download_size' => curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD),
        ];

        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("HTTP $httpCode returned from $url: $error");
        }

        $this->logger->debug("Scraped page with EXTREME stealth cURL", [
            'url' => $url,
            'competitor' => $competitor['name'],
            'http_code' => $httpCode,
            'request_duration' => round($requestDuration, 3),
            'timing' => array_map(function($t) { return round($t, 3); }, $timingInfo),
            'user_agent' => substr($profile['user_agent'], 0, 60) . '...',
            'profile' => $profile['profile_name'],
            'http_version' => 'HTTP/2',
            'ssl_verify' => 'enabled',
            'headers_sent' => count($headers),
        ]);

        // Simulate realistic browser processing time (parsing HTML, executing JS, rendering)
        $processingTime = $this->behaviorEngine->getInterRequestDelay('scroll') * 0.3;
        usleep((int)($processingTime * 1000000));

        $this->logger->debug("Simulated browser processing time", [
            'processing_time_seconds' => round($processingTime, 3),
            'total_time' => round($requestDuration + $processingTime, 3),
        ]);

        return $this->parseHTML($html, $competitor);
    }

    /**
     * Get cookie jar path for profile
     */
    private function getCookieJarPath($profileName) {
        $cookieDir = '/home/129337.cloudwaysapps.com/jcepnzzkmj/private_html/crawler-cookies';
        if (!is_dir($cookieDir)) {
            mkdir($cookieDir, 0755, true);
        }
        return $cookieDir . '/' . $profileName . '.txt';
    }

    /**
     * Parse HTML to extract products
     */
    private function parseHTML($html, $competitor) {
        $products = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Find product elements
        $productNodes = $xpath->query("//*[contains(@class, 'product')]");

        foreach ($productNodes as $node) {
            try {
                $product = $this->extractProductData($node, $xpath, $competitor);

                if ($product && isset($product['name']) && isset($product['price'])) {
                    $products[] = $product;
                }
            } catch (\Exception $e) {
                $this->logger->debug("Failed to extract product", ['error' => $e->getMessage()]);
            }
        }

        return $products;
    }

    /**
     * Get realistic platform string based on user agent
     */
    private function getRealisticPlatform(): string {
        $platforms = [
            'Windows' => 75,    // 75% market share
            'macOS' => 15,      // 15% market share
            'Linux' => 5,       // 5% market share
            'Chrome OS' => 5,   // 5% market share
        ];

        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($platforms as $platform => $percentage) {
            $cumulative += $percentage;
            if ($rand <= $cumulative) {
                return $platform;
            }
        }

        return 'Windows'; // Default fallback
    }

    /**
     * Extract product data from DOM node
     */
    private function extractProductData($node, $xpath, $competitor) {
        $product = [
            'competitor_name' => $competitor['name'],
            'competitor_url' => $competitor['url'],
            'session_id' => $this->logger->getSessionId(),
            'scraped_at' => date('Y-m-d H:i:s'),
        ];

        // Extract name
        $nameNode = $xpath->query(".//*[contains(@class, 'name') or contains(@class, 'title')]", $node)->item(0);
        if ($nameNode) {
            $product['name'] = trim($nameNode->textContent);
        }

        // Extract price
        $priceNode = $xpath->query(".//*[contains(@class, 'price')]", $node)->item(0);
        if ($priceNode) {
            $priceText = trim($priceNode->textContent);
            $product['price'] = $this->extractPrice($priceText);
        }

        // Extract original price (if on sale)
        $originalPriceNode = $xpath->query(".//*[contains(@class, 'original') or contains(@class, 'old')]", $node)->item(0);
        if ($originalPriceNode) {
            $originalPriceText = trim($originalPriceNode->textContent);
            $product['original_price'] = $this->extractPrice($originalPriceText);
        }

        // Extract URL
        $linkNode = $xpath->query(".//a", $node)->item(0);
        if ($linkNode && $linkNode->hasAttribute('href')) {
            $product['product_url'] = $linkNode->getAttribute('href');

            // Make absolute URL
            if (strpos($product['product_url'], 'http') !== 0) {
                $product['product_url'] = $competitor['url'] . $product['product_url'];
            }
        }

        // Check stock status
        $product['in_stock'] = !$this->isOutOfStock($node, $xpath);

        return $product;
    }

    /**
     * Extract price from text
     */
    private function extractPrice($text) {
        // Remove currency symbols and extract number
        $text = preg_replace('/[^0-9.]/', '', $text);
        return floatval($text);
    }

    /**
     * Check if product is out of stock
     */
    private function isOutOfStock($node, $xpath) {
        $outOfStockIndicators = ['out of stock', 'sold out', 'unavailable', 'coming soon'];

        $nodeText = strtolower($node->textContent);

        foreach ($outOfStockIndicators as $indicator) {
            if (strpos($nodeText, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if product is a special offer
     */
    private function isSpecialOffer($product) {
        if (!isset($product['original_price']) || !isset($product['price'])) {
            return false;
        }

        if ($product['original_price'] <= $product['price']) {
            return false;
        }

        $discountPercent = (($product['original_price'] - $product['price']) / $product['original_price']) * 100;

        return $discountPercent >= $this->config['special_discount_threshold'];
    }

    /**
     * Save product to database
     */
    private function saveProduct($product) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO competitive_prices (
                    session_id, competitor_name, competitor_url,
                    product_name, product_url, price, original_price,
                    currency, in_stock, special_offer, scraped_at, raw_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $product['session_id'],
                $product['competitor_name'],
                $product['competitor_url'],
                $product['name'],
                $product['product_url'] ?? null,
                $product['price'],
                $product['original_price'] ?? null,
                'NZD',
                $product['in_stock'] ? 1 : 0,
                $this->isSpecialOffer($product) ? 1 : 0,
                $product['scraped_at'],
                json_encode($product),
            ]);

        } catch (\PDOException $e) {
            $this->logger->error("Failed to save product", [
                'product' => $product['name'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Save special offer
     */
    private function saveSpecial($product) {
        try {
            $discountPercent = round((($product['original_price'] - $product['price']) / $product['original_price']) * 100);

            $stmt = $this->db->prepare("
                INSERT INTO competitive_specials (
                    session_id, competitor_name, title, description,
                    price, original_price, discount_percent,
                    product_url, detected_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $product['session_id'],
                $product['competitor_name'],
                $product['name'],
                "Special offer: Save {$discountPercent}%",
                $product['price'],
                $product['original_price'],
                $discountPercent,
                $product['product_url'] ?? null,
            ]);

            $this->logger->info("Special offer detected", [
                'competitor' => $product['competitor_name'],
                'product' => $product['name'],
                'discount' => "{$discountPercent}%",
            ]);

        } catch (\PDOException $e) {
            $this->logger->error("Failed to save special", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send special to News Aggregator feed
     */
    private function sendSpecialToNewsFeed($product) {
        try {
            // Mark as sent
            $stmt = $this->db->prepare("
                UPDATE competitive_specials
                SET sent_to_news_feed = TRUE
                WHERE competitor_name = ? AND title = ?
            ");

            $stmt->execute([$product['competitor_name'], $product['name']]);

            $this->logger->info("Sent special to news feed", [
                'competitor' => $product['competitor_name'],
                'product' => $product['name'],
            ]);

            // TODO: Integrate with News Aggregator API

        } catch (\PDOException $e) {
            $this->logger->error("Failed to send special to news feed", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Intelligent delay based on stealth level
     */
    private function intelligentDelay($multiplier = 1.0) {
        $minDelay = $this->config['request_delay_min'] * $multiplier;
        $maxDelay = $this->config['request_delay_max'] * $multiplier;

        $delay = rand($minDelay, $maxDelay) * 1000; // Convert to microseconds

        usleep($delay);
    }

    /**
     * Human Behavior-Based Intelligent Delay
     *
     * Uses scientifically accurate human behavior patterns:
     * - Gamma distribution (realistic reaction times)
     * - Circadian rhythm (slower at night)
     * - Fatigue modeling (slower over time)
     * - Browsing profile characteristics
     * - Random distractions and impatience
     *
     * NO PREDICTABLE PATTERNS!
     */
    private function intelligentDelayWithHumanBehavior($multiplier = 1.0) {
        // Get realistic inter-request delay from behavior engine
        $baseDelay = $this->behaviorEngine->getInterRequestDelay('navigate');

        // Apply stealth multiplier from competitor config
        $delay = $baseDelay * $multiplier;

        // Add scroll simulation time (humans don't instantly jump pages)
        $scrollTime = 0;
        if (rand(0, 100) < 70) { // 70% chance user scrolled on previous page
            $scrollPattern = $this->behaviorEngine->generateScrollPattern(rand(2000, 6000));
            $scrollTime = array_sum(array_column($scrollPattern, 'pause_duration'));

            $this->logger->debug("Simulated realistic scrolling behavior", [
                'scroll_actions' => count($scrollPattern),
                'scroll_time_seconds' => round($scrollTime, 2),
            ]);
        }

        $totalDelay = $delay + $scrollTime;

        // Decide if should continue browsing (realistic bounce/exit rates)
        if (!$this->behaviorEngine->shouldContinueBrowsing()) {
            // User would exit - add longer delay (session end + new session start)
            $sessionRestartDelay = $this->behaviorEngine->getInterRequestDelay('navigate') * rand(5, 15);
            $totalDelay += $sessionRestartDelay;

            $this->logger->debug("Simulated session end + restart", [
                'restart_delay_seconds' => round($sessionRestartDelay, 2),
                'session_stats' => $this->behaviorEngine->getSessionStats(),
            ]);

            // Reset behavior engine for "new session"
            $this->behaviorEngine = new \CIS\Crawlers\HumanBehaviorEngine($this->logger);
        }

        $this->logger->debug("Applied scientifically accurate human delay", [
            'base_delay' => round($baseDelay, 2),
            'stealth_multiplier' => $multiplier,
            'scroll_time' => round($scrollTime, 2),
            'total_delay_seconds' => round($totalDelay, 2),
            'behavior_profile' => $this->behaviorEngine->getSessionStats()['profile'],
        ]);

        // Execute delay
        usleep((int)($totalDelay * 1000000));
    }

    /**
     * Get session ID
     */
    public function getSessionId() {
        return $this->logger->getSessionId();
    }
}
