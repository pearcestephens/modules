<?php
/**
 * News Aggregator Service
 *
 * Crawls external vape news sources (RSS feeds, HTML scraping, APIs)
 * Aggregates content from manufacturers, local NZ companies, industry news, specials
 * Compatible with all CIS themes
 *
 * @package CIS_Themes
 * @subpackage NewsAggregator
 */

namespace CIS\NewsAggregator;

class AggregatorService {

    private $db;
    private $config;

    public function __construct($db, $config = []) {
        $this->db = $db;
        $this->config = array_merge([
            'user_agent' => 'CIS News Aggregator Bot/1.0 (+https://staff.vapeshed.co.nz)',
            'timeout' => 15,
            'max_redirects' => 3,
            'image_cache_dir' => '/uploads/news-images/',
            'max_image_size' => 5242880, // 5MB
            'rate_limit_delay' => 2, // seconds between requests to same domain
        ], $config);
    }

    /**
     * Run scheduled crawls for all active sources
     */
    public function runScheduledCrawls() {
        $sql = "SELECT * FROM news_sources
                WHERE is_active = 1
                AND (next_crawl_at IS NULL OR next_crawl_at <= NOW())
                ORDER BY next_crawl_at ASC
                LIMIT 10";

        $sources = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        foreach ($sources as $source) {
            $results[$source['id']] = $this->crawlSource($source);

            // Rate limiting
            if (count($sources) > 1) {
                sleep($this->config['rate_limit_delay']);
            }
        }

        return $results;
    }

    /**
     * Crawl a single news source
     */
    public function crawlSource($source) {
        $logId = $this->startCrawlLog($source['id']);
        $startTime = microtime(true);

        try {
            switch ($source['type']) {
                case 'rss':
                    $result = $this->crawlRSS($source);
                    break;
                case 'html':
                    $result = $this->crawlHTML($source);
                    break;
                case 'api':
                    $result = $this->crawlAPI($source);
                    break;
                default:
                    throw new \Exception("Unknown source type: {$source['type']}");
            }

            $executionTime = microtime(true) - $startTime;
            $this->completeCrawlLog($logId, 'success', $result, $executionTime);
            $this->updateSourceStats($source['id'], true);

            return [
                'success' => true,
                'articles_found' => $result['found'],
                'articles_new' => $result['new'],
                'execution_time' => round($executionTime, 3)
            ];

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->completeCrawlLog($logId, 'failed', ['error' => $e->getMessage()], $executionTime);
            $this->updateSourceStats($source['id'], false);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 3)
            ];
        }
    }

    /**
     * Crawl RSS feed
     */
    private function crawlRSS($source) {
        $xml = $this->fetchURL($source['url']);

        // Try SimpleXML first
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);

        if ($feed === false) {
            throw new \Exception("Failed to parse RSS feed");
        }

        $articles = [];
        $found = 0;
        $new = 0;

        // Handle RSS 2.0
        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $found++;
                $article = $this->parseRSSItem($item, $source);
                if ($this->saveArticle($article, $source['id'])) {
                    $new++;
                }
            }
        }
        // Handle Atom
        elseif (isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                $found++;
                $article = $this->parseAtomEntry($entry, $source);
                if ($this->saveArticle($article, $source['id'])) {
                    $new++;
                }
            }
        }

        return ['found' => $found, 'new' => $new, 'updated' => 0];
    }

    /**
     * Parse RSS 2.0 item
     */
    private function parseRSSItem($item, $source) {
        return [
            'title' => (string) $item->title,
            'summary' => (string) ($item->description ?? $item->summary ?? ''),
            'content' => (string) ($item->children('content', true)->encoded ?? $item->description ?? ''),
            'url' => (string) $item->link,
            'external_id' => (string) ($item->guid ?? $item->link),
            'author' => (string) ($item->author ?? $item->children('dc', true)->creator ?? null),
            'published_at' => $this->parseDate((string) ($item->pubDate ?? $item->children('dc', true)->date ?? 'now')),
            'image_url' => $this->extractImageFromRSS($item),
            'category' => $source['category'],
        ];
    }

    /**
     * Parse Atom entry
     */
    private function parseAtomEntry($entry, $source) {
        $link = '';
        foreach ($entry->link as $l) {
            if ((string) $l['rel'] === 'alternate' || !isset($l['rel'])) {
                $link = (string) $l['href'];
                break;
            }
        }

        return [
            'title' => (string) $entry->title,
            'summary' => (string) $entry->summary,
            'content' => (string) ($entry->content ?? $entry->summary),
            'url' => $link,
            'external_id' => (string) $entry->id,
            'author' => (string) ($entry->author->name ?? null),
            'published_at' => $this->parseDate((string) ($entry->published ?? $entry->updated ?? 'now')),
            'image_url' => $this->extractImageFromAtom($entry),
            'category' => $source['category'],
        ];
    }

    /**
     * Crawl HTML page (for sites without RSS)
     */
    private function crawlHTML($source) {
        // This requires custom selectors per site
        // Stored in source->selector_config JSON
        throw new \Exception("HTML scraping not yet implemented - requires custom selectors");
    }

    /**
     * Crawl API endpoint
     */
    private function crawlAPI($source) {
        throw new \Exception("API crawling not yet implemented");
    }

    /**
     * Save article to database (with deduplication)
     */
    private function saveArticle($article, $sourceId) {
        // Check if article already exists
        $stmt = $this->db->prepare("
            SELECT id FROM news_articles
            WHERE source_id = ? AND external_id = ?
        ");
        $stmt->execute([$sourceId, $article['external_id']]);

        if ($stmt->fetch()) {
            return false; // Already exists
        }

        // Cache image if present
        $cachedImage = null;
        if (!empty($article['image_url'])) {
            $cachedImage = $this->cacheImage($article['image_url']);
        }

        // Auto-detect tags from content
        $tags = $this->extractTags($article['title'] . ' ' . $article['summary']);

        // Insert new article
        $stmt = $this->db->prepare("
            INSERT INTO news_articles (
                source_id, external_id, title, summary, content, url,
                image_url, cached_image, author, published_at, category,
                tags, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $sourceId,
            $article['external_id'],
            $article['title'],
            $article['summary'],
            $article['content'],
            $article['url'],
            $article['image_url'],
            $cachedImage,
            $article['author'],
            $article['published_at'],
            $article['category'],
            json_encode($tags)
        ]);

        return true; // New article saved
    }

    /**
     * Fetch URL with cURL
     */
    private function fetchURL($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->config['max_redirects'],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_USERAGENT => $this->config['user_agent'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => 'gzip,deflate',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL error: $error");
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("HTTP error: $httpCode");
        }

        return $response;
    }

    /**
     * Cache remote image locally
     */
    private function cacheImage($imageUrl) {
        try {
            $imageData = $this->fetchURL($imageUrl);

            if (strlen($imageData) > $this->config['max_image_size']) {
                return null; // Image too large
            }

            // Generate filename
            $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $ext = 'jpg';
            }

            $filename = 'news_' . time() . '_' . substr(md5($imageUrl), 0, 8) . '.' . $ext;
            $cacheDir = $_SERVER['DOCUMENT_ROOT'] . $this->config['image_cache_dir'];

            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            $filepath = $cacheDir . $filename;
            file_put_contents($filepath, $imageData);

            return $this->config['image_cache_dir'] . $filename;

        } catch (\Exception $e) {
            return null; // Failed to cache, will use original URL
        }
    }

    /**
     * Extract image URL from RSS item
     */
    private function extractImageFromRSS($item) {
        // Try media:content (Media RSS)
        $media = $item->children('media', true);
        if (isset($media->content)) {
            return (string) $media->content->attributes()->url;
        }

        // Try enclosure
        if (isset($item->enclosure) && (string) $item->enclosure['type'] === 'image/jpeg') {
            return (string) $item->enclosure['url'];
        }

        // Try to extract from content/description
        $content = (string) ($item->children('content', true)->encoded ?? $item->description ?? '');
        if (preg_match('/<img[^>]+src="([^"]+)"/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract image URL from Atom entry
     */
    private function extractImageFromAtom($entry) {
        // Try media:thumbnail
        $media = $entry->children('media', true);
        if (isset($media->thumbnail)) {
            return (string) $media->thumbnail->attributes()->url;
        }

        // Try to extract from content
        $content = (string) ($entry->content ?? $entry->summary ?? '');
        if (preg_match('/<img[^>]+src="([^"]+)"/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Parse date string to MySQL datetime
     */
    private function parseDate($dateString) {
        try {
            $date = new \DateTime($dateString);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return date('Y-m-d H:i:s');
        }
    }

    /**
     * Extract tags from text (simple keyword extraction)
     */
    private function extractTags($text) {
        $keywords = [
            'vaping', 'e-cigarette', 'mod', 'tank', 'coil', 'nicotine',
            'juice', 'flavor', 'regulation', 'health', 'study', 'ban',
            'device', 'battery', 'pod', 'disposable', 'safety', 'FDA'
        ];

        $found = [];
        $text = strtolower($text);

        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $found[] = $keyword;
            }
        }

        return array_unique($found);
    }

    /**
     * Start crawl log entry
     */
    private function startCrawlLog($sourceId) {
        $stmt = $this->db->prepare("
            INSERT INTO news_crawl_log (source_id, started_at, status)
            VALUES (?, NOW(), 'running')
        ");
        $stmt->execute([$sourceId]);
        return $this->db->lastInsertId();
    }

    /**
     * Complete crawl log entry
     */
    private function completeCrawlLog($logId, $status, $result, $executionTime) {
        $stmt = $this->db->prepare("
            UPDATE news_crawl_log
            SET completed_at = NOW(),
                status = ?,
                articles_found = ?,
                articles_new = ?,
                articles_updated = ?,
                error_message = ?,
                execution_time = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $result['found'] ?? 0,
            $result['new'] ?? 0,
            $result['updated'] ?? 0,
            $result['error'] ?? null,
            $executionTime,
            $logId
        ]);
    }

    /**
     * Update source statistics
     */
    private function updateSourceStats($sourceId, $success) {
        // Update last_crawled_at and next_crawl_at
        $stmt = $this->db->prepare("
            UPDATE news_sources
            SET last_crawled_at = NOW(),
                next_crawl_at = DATE_ADD(NOW(), INTERVAL crawl_frequency SECOND),
                total_articles = (SELECT COUNT(*) FROM news_articles WHERE source_id = ?)
            WHERE id = ?
        ");
        $stmt->execute([$sourceId, $sourceId]);

        // Update success rate (simple rolling average)
        if ($success) {
            $stmt = $this->db->prepare("
                UPDATE news_sources
                SET success_rate = LEAST(100, success_rate + 1)
                WHERE id = ?
            ");
        } else {
            $stmt = $this->db->prepare("
                UPDATE news_sources
                SET success_rate = GREATEST(0, success_rate - 5)
                WHERE id = ?
            ");
        }
        $stmt->execute([$sourceId]);
    }

}
