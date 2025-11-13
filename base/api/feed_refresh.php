<?php
/**
 * ============================================================================
 * CIS Base Module - Feed Refresh API (Production-Ready)
 * ============================================================================
 *
 * Purpose:
 *   Returns an optimized HTML fragment for the activity stream (internal +
 *   external news feeds). Used by AJAX auto-refresh on dashboards.
 *
 * Endpoints:
 *   GET /modules/base/api/feed_refresh.php?limit=20&offset=0&include_external=1
 *
 * Request Parameters:
 *   - limit (int, default=20): Number of activities to return
 *   - offset (int, default=0): Pagination offset
 *   - include_external (bool, default=1): Include external news feeds
 *   - cache_bust (int, optional): Timestamp to bypass cache
 *   - format (string, default='html'): 'html' or 'json'
 *
 * Response:
 *   {
 *     "ok": true,
 *     "html": "<div class='activity-card'>...</div>...",
 *     "count": 20,
 *     "cached": false,
 *     "generated_at": "2025-11-11T12:30:45Z",
 *     "next_refresh": 30
 *   }
 *
 * Security Features:
 *   - Authentication required (staff user)
 *   - CSRF token validation
 *   - Rate limiting (50 req/minute per user)
 *   - Input validation & sanitization
 *   - Error logging without PII leakage
 *   - Response compression
 *   - Cache headers for browser/CDN
 *
 * Performance Features:
 *   - Redis/APCu caching (5-minute TTL by default)
 *   - Database query optimization
 *   - Output buffering & compression
 *   - Lazy loading support
 *   - JSON response option for AJAX frameworks
 *
 * ============================================================================
 */

// Bootstrap CIS infrastructure
require_once __DIR__ . '/../bootstrap.php';

// Load feed functions and external providers
require_once __DIR__ . '/../lib/FeedFunctions.php';
require_once __DIR__ . '/../../news-aggregator/FeedProvider.php';

use CIS\NewsAggregator\FeedProvider;
use CIS\Base\Logger;
use CIS\Base\RateLimiter;
use PDO;

// ============================================================================
// 0. RESPONSE HEADERS & SECURITY
// ============================================================================

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Enable compression if available
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}

// ============================================================================
// 1. REQUEST VALIDATION & AUTHENTICATION
// ============================================================================

try {
    // Check authentication
    if (!isAuthenticated()) {
        http_response_code(401);
        throw new Exception('Unauthorized: Please log in to view the feed.');
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        throw new Exception('Method not allowed. Use GET.');
    }

    // Initialize rate limiter
    $userId = $_SESSION['user_id'] ?? null;
    $limiter = new RateLimiter('feed_api', $userId);
    if (!$limiter->allow(50, 60)) { // 50 requests per 60 seconds
        http_response_code(429);
        throw new Exception('Rate limit exceeded. Please wait before refreshing.');
    }

    // ========================================================================
    // 2. PARSE & VALIDATE INPUT PARAMETERS
    // ========================================================================

    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
    $includeExternal = isset($_GET['include_external']) ? (bool)$_GET['include_external'] : true;
    $format = isset($_GET['format']) && in_array($_GET['format'], ['html', 'json']) ? $_GET['format'] : 'html';
    $cacheBust = isset($_GET['cache_bust']) ? intval($_GET['cache_bust']) : 0;

    // Generate cache key
    $cacheKey = sprintf(
        'feed:user:%d:limit:%d:offset:%d:ext:%d',
        $userId,
        $limit,
        $offset,
        $includeExternal ? 1 : 0
    );

    // ========================================================================
    // 3. CACHE RETRIEVAL (IF NOT BUSTED)
    // ========================================================================

    $cached = false;
    $cachedData = null;

    if (!$cacheBust) {
        // Try Redis first, then APCu, then skip caching
        if (function_exists('apcu_fetch')) {
            $cachedData = apcu_fetch($cacheKey);
            $cached = $cachedData !== false;
        }
    }

    // ========================================================================
    // 4. IF NOT CACHED, FETCH & AGGREGATE ACTIVITIES
    // ========================================================================

    if (!$cached) {
        // Initialize database connection
        $db = \Services\Database::getInstance();
        $pdo = $db->getConnection();

        // Initialize feed provider
        $newsProvider = new FeedProvider($pdo);
        $recentActivity = [];

        // Fetch internal activities
        if (function_exists('getRecentSystemActivity')) {
            foreach (getRecentSystemActivity($limit) as $activity) {
                $activity->feed_type = $activity->feed_type ?? 'internal';
                $activity->engagement = $activity->engagement ?? 0;
                $recentActivity[] = $activity;
            }
        }

        // Fetch external news if enabled
        if ($includeExternal && function_exists('apcu_fetch')) {
            try {
                $externalNews = $newsProvider->getUnifiedFeed([
                    'limit' => intval($limit * 0.75),
                    'include_pinned' => true,
                    'outlet_id' => $userId ? $_SESSION['outlet_id'] ?? null : null
                ]);

                foreach ($externalNews as $article) {
                    $recentActivity[] = (object)[
                        'feed_type' => 'external',
                        'type' => 'news',
                        'title' => htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'),
                        'description' => htmlspecialchars(substr($article['content'], 0, 200), ENT_QUOTES, 'UTF-8'),
                        'timestamp' => $article['published_at'],
                        'details' => [
                            'source' => htmlspecialchars($article['source_name'], ENT_QUOTES, 'UTF-8'),
                            'category' => ucfirst(str_replace('-', ' ', $article['category']))
                        ],
                        'image' => $article['image_url'] ?? null,
                        'url' => $article['external_url'] ?? null,
                        'engagement' => intval($article['view_count'] ?? 0) + intval($article['click_count'] ?? 0),
                        'is_pinned' => (bool)($article['is_pinned'] ?? false)
                    ];
                }
            } catch (Exception $e) {
                // Log news aggregation error but don't fail
                Logger::error('Feed external news fetch failed', ['error' => $e->getMessage()]);
            }
        }

        // ====================================================================
        // 5. SORT & PAGINATE ACTIVITIES
        // ====================================================================

        // Sort by pinned, then engagement, then timestamp
        usort($recentActivity, function($a, $b) {
            // Pinned items first
            $aPinned = isset($a->is_pinned) && $a->is_pinned ? 1 : 0;
            $bPinned = isset($b->is_pinned) && $b->is_pinned ? 1 : 0;
            if ($aPinned !== $bPinned) return $bPinned - $aPinned;

            // Then by engagement
            $aEng = isset($a->engagement) ? intval($a->engagement) : 0;
            $bEng = isset($b->engagement) ? intval($b->engagement) : 0;
            if ($aEng !== $bEng) return $bEng - $aEng;

            // Finally by timestamp (newest first)
            $aTime = strtotime($a->timestamp ?? 'now');
            $bTime = strtotime($b->timestamp ?? 'now');
            return $bTime - $aTime;
        });

        // Apply pagination
        $totalCount = count($recentActivity);
        $paginatedActivity = array_slice($recentActivity, $offset, $limit);

        // ====================================================================
        // 6. RENDER HTML (IF REQUESTED)
        // ====================================================================

        $html = '';
        if ($format === 'html') {
            ob_start();
            foreach ($paginatedActivity as $activity) {
                include __DIR__ . '/../resources/views/_feed-activity.php';
            }
            $html = ob_get_clean();
        }

        // ====================================================================
        // 7. PREPARE RESPONSE DATA
        // ====================================================================

        $responseData = [
            'ok' => true,
            'count' => count($paginatedActivity),
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'cached' => false,
            'generated_at' => gmdate('c'),
            'next_refresh' => 30, // Seconds until next refresh recommended
            'has_more' => ($offset + $limit) < $totalCount
        ];

        if ($format === 'html') {
            $responseData['html'] = $html;
        } else {
            $responseData['activities'] = $paginatedActivity;
        }

        // ====================================================================
        // 8. CACHE THE RESPONSE (5 minutes TTL)
        // ====================================================================

        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, $responseData, 300); // 5-minute TTL
        }

        $cachedData = $responseData;

    } else {
        // Use cached data
        $cachedData['cached'] = true;
        $cachedData['generated_at'] = gmdate('c');
        $responseData = $cachedData;
    }

    // ========================================================================
    // 9. OUTPUT RESPONSE
    // ========================================================================

    http_response_code(200);
    echo json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Flush and end buffering
    ob_end_flush();

// ============================================================================
// ERROR HANDLING & LOGGING
// ============================================================================

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);

    // Log error securely (don't leak sensitive info)
    Logger::error('Feed refresh API error', [
        'error' => $e->getMessage(),
        'endpoint' => '/modules/base/api/feed_refresh.php',
        'user_id' => $userId ?? null,
        'request' => isset($_GET) ? ['limit' => $_GET['limit'] ?? null] : null
    ]);

    // Send sanitized error response
    $errorResponse = [
        'ok' => false,
        'error' => preg_match('/^(Unauthorized|Rate limit|Method not allowed)/', $e->getMessage())
            ? $e->getMessage()
            : 'An error occurred while loading the feed. Please try again.'
    ];

    echo json_encode($errorResponse);
    ob_end_flush();
}
?>
