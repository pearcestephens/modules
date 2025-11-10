<?php
/**
 * News Aggregator Integration
 *
 * Provides mock data with external news mixed in
 * Compatible with all CIS themes - drop this into your theme's data loading
 *
 * @package CIS_Themes
 * @subpackage NewsAggregator
 */

namespace CIS\NewsAggregator;

class ThemeIntegration {

    private $feedProvider;

    public function __construct($db) {
        require_once __DIR__ . '/FeedProvider.php';
        $this->feedProvider = new FeedProvider($db);
    }

    /**
     * Get unified news feed for Facebook Feed layout
     * Mixes internal company posts with external aggregated content
     *
     * @param array $options
     * @return array Feed items formatted for theme display
     */
    public function getNewsFeed($options = []) {
        $defaults = [
            'limit' => 10,
            'include_external' => true,
            'include_pinned' => true,
            'category' => null,
        ];

        $options = array_merge($defaults, $options);

        $feed = [];

        // Get external news if enabled
        if ($options['include_external']) {
            $external = $this->feedProvider->getUnifiedFeed([
                'limit' => $options['limit'],
                'category' => $options['category'],
                'include_pinned' => $options['include_pinned'],
            ]);

            // Format for theme display
            foreach ($external as $item) {
                $feed[] = [
                    'id' => 'ext_' . $item['id'],
                    'type' => 'external',
                    'author' => $item['source_name'],
                    'author_role' => $item['metadata']['source_country'] ?? 'External',
                    'author_avatar' => $item['author_avatar'] ?? $this->getDefaultAvatar(),
                    'time' => $this->timeAgo($item['published_at']),
                    'time_iso' => $item['published_at'],
                    'content' => $item['content'],
                    'image' => $item['image_url'],
                    'external_url' => $item['external_url'],
                    'category' => $item['category'],
                    'is_pinned' => $item['is_pinned'],
                    'priority' => $item['priority'],
                    'reactions' => [
                        'likes' => $item['click_count'] ?? 0,
                        'comments' => 0,
                        'shares' => 0,
                    ],
                    'metadata' => $item['metadata'],
                ];
            }
        }

        // Sort by pinned, then priority, then date
        usort($feed, function($a, $b) {
            if ($a['is_pinned'] != $b['is_pinned']) {
                return $b['is_pinned'] - $a['is_pinned'];
            }
            if ($a['priority'] != $b['priority']) {
                return $a['priority'] - $b['priority'];
            }
            return strtotime($b['time_iso']) - strtotime($a['time_iso']);
        });

        return $feed;
    }

    /**
     * Get category filter options
     */
    public function getCategories() {
        return $this->feedProvider->getCategories();
    }

    /**
     * Get available news sources
     */
    public function getSources() {
        return $this->feedProvider->getSources();
    }

    /**
     * Record view when article is displayed
     */
    public function trackView($itemId) {
        if (strpos($itemId, 'ext_') === 0) {
            $articleId = str_replace('ext_', '', $itemId);
            $this->feedProvider->recordView($articleId);
        }
    }

    /**
     * Record click when user clicks external link
     */
    public function trackClick($itemId) {
        if (strpos($itemId, 'ext_') === 0) {
            $articleId = str_replace('ext_', '', $itemId);
            $this->feedProvider->recordClick($articleId);
        }
    }

    /**
     * Get trending articles widget
     */
    public function getTrendingWidget($limit = 5) {
        $trending = $this->feedProvider->getTrending($limit);

        return array_map(function($item) {
            return [
                'id' => 'ext_' . $item['id'],
                'title' => $item['title'],
                'source' => $item['source_name'],
                'source_logo' => $item['source_logo'],
                'url' => $item['url'],
                'image' => $item['image'],
                'engagement' => $item['engagement'],
                'time' => $this->timeAgo($item['published_at']),
            ];
        }, $trending);
    }

    /**
     * Get category badge for display
     */
    public function getCategoryBadge($category) {
        $badges = [
            'vape-news' => ['label' => 'Vape News', 'color' => '#0ea5e9'],
            'manufacturer' => ['label' => 'Manufacturer', 'color' => '#8b5cf6'],
            'local' => ['label' => 'NZ Local', 'color' => '#10b981'],
            'specials' => ['label' => 'Special Offer', 'color' => '#f59e0b'],
            'industry' => ['label' => 'Industry News', 'color' => '#6366f1'],
        ];

        return $badges[$category] ?? ['label' => ucfirst($category), 'color' => '#64748b'];
    }

    /**
     * Time ago helper
     */
    private function timeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', $time);
    }

    /**
     * Default avatar for external sources
     */
    private function getDefaultAvatar() {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><rect fill="#667eea" width="40" height="40"/><text x="20" y="25" font-family="Arial" font-size="18" fill="white" text-anchor="middle">📰</text></svg>');
    }
}
