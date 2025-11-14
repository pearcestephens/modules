<?php
/**
 * Email Search Module
 *
 * Advanced email search with threading, sentiment analysis, attachment search,
 * sender intelligence, and conversation context.
 *
 * Makes Gmail's search look like a toy from the 90s.
 *
 * @package StaffEmailHub\Services\Search
 */

namespace StaffEmailHub\Services\Search;

class EmailSearchModule
{
    private $db;
    private $logger;
    private $staffId;

    public function __construct($db, $logger, $staffId)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->staffId = $staffId;
    }

    /**
     * Search emails with advanced filtering
     *
     * @param array $parsedQuery Parsed query with keywords, entities, filters
     * @param array $options Additional options
     * @return array Search results
     */
    public function search(array $parsedQuery, array $options = []): array
    {
        try {
            $query = $this->buildEmailQuery($parsedQuery, $options);
            $results = $this->executeQuery($query, $parsedQuery);

            return [
                'results' => $results,
                'total' => count($results),
                'filters_applied' => $parsedQuery['filters'] ?? [],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Email search failed', [
                'error' => $e->getMessage(),
            ]);

            return ['results' => [], 'total' => 0];
        }
    }

    /**
     * Build email search SQL query
     */
    private function buildEmailQuery(array $parsedQuery, array $options): array
    {
        $sql = "
            SELECT
                e.*,
                e.sentiment_score,
                e.priority_level,
                COUNT(DISTINCT a.id) as attachment_count,
                GROUP_CONCAT(DISTINCT a.filename) as attachment_names
            FROM emails e
            LEFT JOIN email_attachments a ON e.id = a.email_id
            WHERE e.staff_id = ?
        ";

        $params = [$this->staffId];
        $conditions = [];

        // Keyword search (full-text on subject + body)
        if (!empty($parsedQuery['keywords'])) {
            $keywords = implode(' ', $parsedQuery['keywords']);
            $conditions[] = "MATCH(e.subject, e.body) AGAINST(? IN BOOLEAN MODE)";
            $params[] = $keywords;
        }

        // Entity filtering
        if (!empty($parsedQuery['entities'])) {
            if (isset($parsedQuery['entities']['email'])) {
                $emailAddrs = $parsedQuery['entities']['email'];
                $placeholders = implode(',', array_fill(0, count($emailAddrs), '?'));
                $conditions[] = "(e.from_email IN ($placeholders) OR e.to_email IN ($placeholders))";
                $params = array_merge($params, $emailAddrs, $emailAddrs);
            }
        }

        // Filter: Date range
        if (isset($parsedQuery['filters']['date'])) {
            $dateFilter = $this->parseDateFilter($parsedQuery['filters']['date']);
            $conditions[] = "e.received_at >= ?";
            $params[] = $dateFilter;
        }

        // Filter: Sentiment
        if (isset($parsedQuery['filters']['sentiment'])) {
            $sentiment = $parsedQuery['filters']['sentiment'];
            if ($sentiment === 'urgent') {
                $conditions[] = "e.sentiment_score < -0.5 OR e.priority_level = 'urgent'";
            } elseif ($sentiment === 'positive') {
                $conditions[] = "e.sentiment_score > 0.5";
            } elseif ($sentiment === 'negative') {
                $conditions[] = "e.sentiment_score < -0.2";
            }
        }

        // Filter: Has attachments
        if (isset($options['has_attachments']) && $options['has_attachments']) {
            $conditions[] = "EXISTS (SELECT 1 FROM email_attachments WHERE email_id = e.id)";
        }

        // Filter: Unread only
        if (isset($options['unread_only']) && $options['unread_only']) {
            $conditions[] = "e.is_read = 0";
        }

        // Filter: Folder
        if (isset($options['folder'])) {
            $conditions[] = "e.folder = ?";
            $params[] = $options['folder'];
        }

        // Add conditions to query
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        // Group by email
        $sql .= " GROUP BY e.id";

        // Order by relevance + recency
        $sql .= " ORDER BY
            CASE WHEN e.priority_level = 'urgent' THEN 1 ELSE 0 END DESC,
            e.received_at DESC
        ";

        // Limit results
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Execute search query
     */
    private function executeQuery(array $query, array $parsedQuery): array
    {
        $stmt = $this->db->prepare($query['sql']);
        $stmt->execute($query['params']);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Enhance results with threading and context
        foreach ($results as &$result) {
            $result['conversation_thread'] = $this->getConversationThread($result['id']);
            $result['sender_frequency'] = $this->getSenderFrequency($result['from_email']);
            $result['highlighted_text'] = $this->highlightMatches($result, $parsedQuery['keywords']);
        }

        return $results;
    }

    /**
     * Get conversation thread for an email
     */
    private function getConversationThread(int $emailId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, subject, from_email, received_at
            FROM emails
            WHERE conversation_id = (SELECT conversation_id FROM emails WHERE id = ?)
            ORDER BY received_at ASC
        ");
        $stmt->execute([$emailId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get sender frequency (how often they email)
     */
    private function getSenderFrequency(string $email): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM emails
            WHERE from_email = ? AND staff_id = ?
        ");
        $stmt->execute([$email, $this->staffId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    /**
     * Highlight keyword matches in text
     */
    private function highlightMatches(array $result, array $keywords): array
    {
        $highlighted = [];

        foreach (['subject', 'body'] as $field) {
            $text = $result[$field] ?? '';
            foreach ($keywords as $keyword) {
                $text = preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<mark>$1</mark>', $text);
            }
            $highlighted[$field] = $text;
        }

        return $highlighted;
    }

    /**
     * Parse date filter into SQL date
     */
    private function parseDateFilter(string $filter): string
    {
        switch ($filter) {
            case 'today':
                return date('Y-m-d 00:00:00');
            case 'yesterday':
                return date('Y-m-d 00:00:00', strtotime('-1 day'));
            case 'last_week':
            case 'last_7_days':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'last_month':
            case 'last_30_days':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            default:
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
        }
    }
}
