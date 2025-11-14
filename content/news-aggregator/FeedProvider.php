<?php
/**
 * News Aggregator - Frontend Data Provider
 *
 * Provides unified feed data to theme layouts
 * Compatible with all CIS themes
 *
 * @package CIS_Themes
 * @subpackage NewsAggregator
 */

namespace CIS\NewsAggregator;

class FeedProvider {

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get unified feed (internal + external content mixed)
     *
     * @param array $options Filter and display options
     * @return array Feed items
     */
    public function getUnifiedFeed($options = []) {
        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'category' => null,
            'type' => null, // 'internal', 'external', 'all'
            'include_pinned' => true,
            'min_priority' => 10, // Show items with priority <= 10
        ];

        $options = array_merge($defaults, $options);

        $where = ['a.status = ?'];
        $params = ['approved'];

        if ($options['category']) {
            $where[] = 'a.category = ?';
            $params[] = $options['category'];
        }

        if ($options['min_priority']) {
            $where[] = 'a.priority <= ?';
            $params[] = $options['min_priority'];
        }

        // Get external articles
        $sql = "SELECT
                    'external' as item_type,
                    a.id,
                    a.title,
                    a.summary as content,
                    COALESCE(a.cached_image, a.image_url) as image_url,
                    a.author as author_name,
                    s.logo_url as author_avatar,
                    a.published_at,
                    a.category,
                    a.is_pinned,
                    a.priority,
                    a.url as external_url,
                    s.name as source_name,
                    a.click_count,
                    a.view_count,
                    JSON_OBJECT(
                        'source_id', s.id,
                        'source_name', s.name,
                        'source_logo', s.logo_url,
                        'source_country', s.country
                    ) as metadata
                FROM news_articles a
                JOIN news_sources s ON a.source_id = s.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.is_pinned DESC, a.priority ASC, a.published_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $options['limit'];
        $params[] = $options['offset'];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Parse metadata JSON
        foreach ($items as &$item) {
            $item['metadata'] = json_decode($item['metadata'], true);
        }

        return $items;
    }

    /**
     * Get articles by category
     */
    public function getByCategory($category, $limit = 10) {
        return $this->getUnifiedFeed([
            'category' => $category,
            'limit' => $limit
        ]);
    }

    /**
     * Get pinned articles
     */
    public function getPinned($limit = 5) {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.name as source_name,
                s.logo_url as source_logo,
                COALESCE(a.cached_image, a.image_url) as image
            FROM news_articles a
            JOIN news_sources s ON a.source_id = s.id
            WHERE a.status = 'approved' AND a.is_pinned = 1
            ORDER BY a.priority ASC, a.published_at DESC
            LIMIT ?
        ");

        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get trending articles (by clicks/views)
     */
    public function getTrending($limit = 10, $days = 7) {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.name as source_name,
                s.logo_url as source_logo,
                COALESCE(a.cached_image, a.image_url) as image,
                (a.click_count + a.view_count) as engagement
            FROM news_articles a
            JOIN news_sources s ON a.source_id = s.id
            WHERE a.status = 'approved'
            AND a.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY engagement DESC, a.published_at DESC
            LIMIT ?
        ");

        $stmt->execute([$days, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get articles from specific source
     */
    public function getBySource($sourceId, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.name as source_name,
                s.logo_url as source_logo,
                COALESCE(a.cached_image, a.image_url) as image
            FROM news_articles a
            JOIN news_sources s ON a.source_id = s.id
            WHERE a.status = 'approved' AND a.source_id = ?
            ORDER BY a.published_at DESC
            LIMIT ?
        ");

        $stmt->execute([$sourceId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Record article view
     */
    public function recordView($articleId) {
        $stmt = $this->db->prepare("UPDATE news_articles SET view_count = view_count + 1 WHERE id = ?");
        return $stmt->execute([$articleId]);
    }

    /**
     * Record article click
     */
    public function recordClick($articleId) {
        $stmt = $this->db->prepare("UPDATE news_articles SET click_count = click_count + 1 WHERE id = ?");
        return $stmt->execute([$articleId]);
    }

    /**
     * Get category list with counts
     */
    public function getCategories() {
        $stmt = $this->db->query("
            SELECT category, COUNT(*) as count
            FROM news_articles
            WHERE status = 'approved'
            GROUP BY category
            ORDER BY count DESC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get source list with counts
     */
    public function getSources() {
        $stmt = $this->db->query("
            SELECT
                s.id,
                s.name,
                s.logo_url,
                s.country,
                s.category,
                COUNT(a.id) as article_count
            FROM news_sources s
            LEFT JOIN news_articles a ON s.id = a.source_id AND a.status = 'approved'
            WHERE s.is_active = 1
            GROUP BY s.id
            ORDER BY s.name ASC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Search articles
     */
    public function search($query, $limit = 20) {
        $search = "%{$query}%";

        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.name as source_name,
                s.logo_url as source_logo,
                COALESCE(a.cached_image, a.image_url) as image
            FROM news_articles a
            JOIN news_sources s ON a.source_id = s.id
            WHERE a.status = 'approved'
            AND (a.title LIKE ? OR a.summary LIKE ? OR a.content LIKE ?)
            ORDER BY a.published_at DESC
            LIMIT ?
        ");

        $stmt->execute([$search, $search, $search, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get related articles (by category and tags)
     */
    public function getRelated($articleId, $limit = 5) {
        // Get current article's category and tags
        $stmt = $this->db->prepare("SELECT category, tags FROM news_articles WHERE id = ?");
        $stmt->execute([$articleId]);
        $article = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$article) {
            return [];
        }

        // Find related articles by category
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.name as source_name,
                s.logo_url as source_logo,
                COALESCE(a.cached_image, a.image_url) as image
            FROM news_articles a
            JOIN news_sources s ON a.source_id = s.id
            WHERE a.status = 'approved'
            AND a.id != ?
            AND a.category = ?
            ORDER BY a.published_at DESC
            LIMIT ?
        ");

        $stmt->execute([$articleId, $article['category'], $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

}
