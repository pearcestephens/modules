<?php
/**
 * News Aggregator - Admin Controller
 *
 * CRUD operations for news sources, content moderation, scheduling
 *
 * @package CIS_Themes
 * @subpackage NewsAggregator
 */

namespace CIS\NewsAggregator;

class AdminController {

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get all news sources
     */
    public function getSources($filters = []) {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }

        if (isset($filters['is_active'])) {
            $where[] = "is_active = ?";
            $params[] = $filters['is_active'];
        }

        $sql = "SELECT s.*,
                (SELECT COUNT(*) FROM news_articles WHERE source_id = s.id) as article_count,
                (SELECT COUNT(*) FROM news_articles WHERE source_id = s.id AND status = 'pending') as pending_count
                FROM news_sources s
                WHERE " . implode(' AND ', $where) . "
                ORDER BY name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get single source by ID
     */
    public function getSource($id) {
        $stmt = $this->db->prepare("SELECT * FROM news_sources WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create new news source
     */
    public function createSource($data) {
        $stmt = $this->db->prepare("
            INSERT INTO news_sources (name, url, type, category, country, logo_url,
                                    is_active, crawl_frequency, next_crawl_at, selector_config)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");

        return $stmt->execute([
            $data['name'],
            $data['url'],
            $data['type'] ?? 'rss',
            $data['category'],
            $data['country'] ?? 'NZ',
            $data['logo_url'] ?? null,
            $data['is_active'] ?? 1,
            $data['crawl_frequency'] ?? 3600,
            isset($data['selector_config']) ? json_encode($data['selector_config']) : null
        ]);
    }

    /**
     * Update news source
     */
    public function updateSource($id, $data) {
        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'url', 'type', 'category', 'country', 'logo_url',
                               'is_active', 'crawl_frequency', 'selector_config'])) {
                $fields[] = "$key = ?";
                $params[] = ($key === 'selector_config' && is_array($value)) ? json_encode($value) : $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE news_sources SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete news source (and cascade articles)
     */
    public function deleteSource($id) {
        $stmt = $this->db->prepare("DELETE FROM news_sources WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get articles with filters and pagination
     */
    public function getArticles($filters = [], $limit = 50, $offset = 0) {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['source_id'])) {
            $where[] = "a.source_id = ?";
            $params[] = $filters['source_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "a.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $where[] = "a.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(a.title LIKE ? OR a.summary LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
        }

        $sql = "SELECT a.*, s.name as source_name, s.logo_url as source_logo
                FROM news_articles a
                JOIN news_sources s ON a.source_id = s.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.is_pinned DESC, a.priority ASC, a.published_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get article count for pagination
     */
    public function getArticleCount($filters = []) {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['source_id'])) {
            $where[] = "source_id = ?";
            $params[] = $filters['source_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }

        $sql = "SELECT COUNT(*) FROM news_articles WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Moderate article (approve/reject/hide)
     */
    public function moderateArticle($id, $status, $userId) {
        $stmt = $this->db->prepare("
            UPDATE news_articles
            SET status = ?, moderated_by = ?, moderated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$status, $userId, $id]);
    }

    /**
     * Update article (title, summary, priority, pinned)
     */
    public function updateArticle($id, $data) {
        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['title', 'summary', 'content', 'category',
                               'priority', 'is_pinned', 'status'])) {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE news_articles SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete article
     */
    public function deleteArticle($id) {
        $stmt = $this->db->prepare("DELETE FROM news_articles WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Bulk approve articles
     */
    public function bulkApprove($ids, $userId) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            UPDATE news_articles
            SET status = 'approved', moderated_by = ?, moderated_at = NOW()
            WHERE id IN ($placeholders)
        ");

        return $stmt->execute(array_merge([$userId], $ids));
    }

    /**
     * Bulk reject articles
     */
    public function bulkReject($ids, $userId) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            UPDATE news_articles
            SET status = 'rejected', moderated_by = ?, moderated_at = NOW()
            WHERE id IN ($placeholders)
        ");

        return $stmt->execute(array_merge([$userId], $ids));
    }

    /**
     * Get crawl logs
     */
    public function getCrawlLogs($sourceId = null, $limit = 50) {
        $where = $sourceId ? "WHERE source_id = ?" : "WHERE 1=1";
        $params = $sourceId ? [$sourceId, $limit] : [$limit];

        $sql = "SELECT l.*, s.name as source_name
                FROM news_crawl_log l
                JOIN news_sources s ON l.source_id = s.id
                $where
                ORDER BY l.started_at DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get dashboard stats
     */
    public function getDashboardStats() {
        $stats = [];

        // Total sources
        $stats['total_sources'] = $this->db->query("SELECT COUNT(*) FROM news_sources")->fetchColumn();
        $stats['active_sources'] = $this->db->query("SELECT COUNT(*) FROM news_sources WHERE is_active = 1")->fetchColumn();

        // Total articles
        $stats['total_articles'] = $this->db->query("SELECT COUNT(*) FROM news_articles")->fetchColumn();
        $stats['pending_articles'] = $this->db->query("SELECT COUNT(*) FROM news_articles WHERE status = 'pending'")->fetchColumn();
        $stats['approved_articles'] = $this->db->query("SELECT COUNT(*) FROM news_articles WHERE status = 'approved'")->fetchColumn();

        // Today's crawls
        $stats['crawls_today'] = $this->db->query("SELECT COUNT(*) FROM news_crawl_log WHERE DATE(started_at) = CURDATE()")->fetchColumn();
        $stats['successful_crawls_today'] = $this->db->query("SELECT COUNT(*) FROM news_crawl_log WHERE DATE(started_at) = CURDATE() AND status = 'success'")->fetchColumn();

        // Articles by category
        $stmt = $this->db->query("SELECT category, COUNT(*) as count FROM news_articles GROUP BY category");
        $stats['by_category'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Recent activity
        $stats['recent_articles'] = $this->db->query("
            SELECT a.title, s.name as source_name, a.published_at
            FROM news_articles a
            JOIN news_sources s ON a.source_id = s.id
            ORDER BY a.scraped_at DESC
            LIMIT 10
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return $stats;
    }

    /**
     * Trigger manual crawl for a source
     */
    public function triggerManualCrawl($sourceId) {
        // Reset next_crawl_at to trigger immediate crawl
        $stmt = $this->db->prepare("UPDATE news_sources SET next_crawl_at = NOW() WHERE id = ?");
        return $stmt->execute([$sourceId]);
    }

}
