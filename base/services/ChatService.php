<?php
/**
 * CIS Chat Service - Enterprise Grade
 *
 * Handles all chat operations: messages, channels, files, AI integration, gamification
 *
 * @package CIS\Base\Services
 * @version 3.0.0
 */

namespace CIS\Base\Services;

use CIS\Base\Database;
use CIS\Base\Logger;
use CIS\Base\AIService;
use PDO;
use Exception;

class ChatService
{
    private $db;
    private $logger;
    private $ai;
    private $redis;

    // Gamification points
    const POINTS_MESSAGE = 1;
    const POINTS_FILE_SHARE = 5;
    const POINTS_HELPFUL_REACTION = 3;
    const POINTS_FIRST_MESSAGE_DAY = 10;
    const POINTS_CHANNEL_CREATOR = 20;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('chat');
        $this->ai = new AIService();

        // Connect to Redis for real-time features
        try {
            $this->redis = new \Redis();
            $this->redis->connect('127.0.0.1', 6379);
        } catch (Exception $e) {
            $this->logger->error("Redis connection failed: " . $e->getMessage());
            $this->redis = null;
        }
    }

    /**
     * Send a message to a channel
     */
    public function sendMessage(int $userId, int $channelId, string $message, string $type = 'text', ?int $parentId = null): array
    {
        try {
            // Validate message
            if (empty(trim($message))) {
                return ['success' => false, 'error' => 'Message cannot be empty'];
            }

            // Check if user has access to channel
            if (!$this->userHasChannelAccess($userId, $channelId)) {
                return ['success' => false, 'error' => 'Access denied'];
            }

            // AI Content moderation
            $moderation = $this->ai->moderateContent($message);
            if ($moderation['flagged']) {
                $this->logger->warning("Message flagged by AI", [
                    'user_id' => $userId,
                    'reason' => $moderation['reason']
                ]);
                return ['success' => false, 'error' => 'Message violates content policy'];
            }

            // AI Enhancement: Extract mentions and links
            $mentions = $this->extractMentions($message);
            $hasLinks = $this->containsLinks($message);

            // Insert message
            $stmt = $this->db->prepare("
                INSERT INTO chat_messages
                (channel_id, user_id, message, message_type, parent_message_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([$channelId, $userId, $message, $type, $parentId]);
            $messageId = $this->db->lastInsertId();

            // Store mentions
            if (!empty($mentions)) {
                $this->storeMentions($messageId, $mentions);
            }

            // Gamification: Award points
            $this->awardPoints($userId, self::POINTS_MESSAGE, 'message_sent');

            // Check for first message of the day bonus
            if ($this->isFirstMessageToday($userId)) {
                $this->awardPoints($userId, self::POINTS_FIRST_MESSAGE_DAY, 'first_message_day');
            }

            // AI: Generate insights if message contains question
            $aiInsight = null;
            if ($this->isQuestion($message)) {
                $aiInsight = $this->generateAIInsight($message, $channelId);
            }

            // Broadcast to WebSocket
            $this->broadcastMessage($channelId, [
                'type' => 'message',
                'message_id' => $messageId,
                'user_id' => $userId,
                'channel_id' => $channelId,
                'message' => $message,
                'message_type' => $type,
                'parent_id' => $parentId,
                'mentions' => $mentions,
                'ai_insight' => $aiInsight,
                'timestamp' => time()
            ]);

            return [
                'success' => true,
                'message_id' => $messageId,
                'ai_insight' => $aiInsight
            ];

        } catch (Exception $e) {
            $this->logger->error("Send message failed", [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            return ['success' => false, 'error' => 'Failed to send message'];
        }
    }

    /**
     * Upload file attachment
     */
    public function uploadFile(int $userId, int $channelId, int $messageId, array $file): array
    {
        try {
            // Validate file
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                return ['success' => false, 'error' => 'File type not allowed'];
            }

            // Size limit: 10MB
            if ($file['size'] > 10 * 1024 * 1024) {
                return ['success' => false, 'error' => 'File too large (max 10MB)'];
            }

            // Generate unique filename
            $filename = uniqid() . '_' . basename($file['name']);
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/chat/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filePath = $uploadDir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return ['success' => false, 'error' => 'Upload failed'];
            }

            // Generate thumbnail for images
            $thumbnail = null;
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $thumbnail = $this->generateThumbnail($filePath, $ext);
            }

            // Store in database
            $stmt = $this->db->prepare("
                INSERT INTO chat_attachments
                (message_id, file_name, file_path, file_size, mime_type, thumbnail_path)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $messageId,
                basename($file['name']),
                '/uploads/chat/' . $filename,
                $file['size'],
                $file['type'],
                $thumbnail
            ]);

            // Gamification: Award file share points
            $this->awardPoints($userId, self::POINTS_FILE_SHARE, 'file_shared');

            // AI: Analyze image content
            $aiAnalysis = null;
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $aiAnalysis = $this->analyzeImage($filePath);
            }

            return [
                'success' => true,
                'file_id' => $this->db->lastInsertId(),
                'file_url' => '/uploads/chat/' . $filename,
                'thumbnail' => $thumbnail,
                'ai_analysis' => $aiAnalysis
            ];

        } catch (Exception $e) {
            $this->logger->error("File upload failed", [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            return ['success' => false, 'error' => 'Upload failed'];
        }
    }

    /**
     * Create new channel
     */
    public function createChannel(int $userId, string $name, string $description, string $type = 'group'): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_channels (name, description, channel_type, created_by)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([$name, $description, $type, $userId]);
            $channelId = $this->db->lastInsertId();

            // Add creator as channel owner
            $this->addChannelParticipant($channelId, $userId, 'owner');

            // Gamification: Award channel creation points
            $this->awardPoints($userId, self::POINTS_CHANNEL_CREATOR, 'channel_created');

            return [
                'success' => true,
                'channel_id' => $channelId
            ];

        } catch (Exception $e) {
            $this->logger->error("Channel creation failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to create channel'];
        }
    }

    /**
     * Add reaction to message
     */
    public function addReaction(int $userId, int $messageId, string $reaction): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_message_reactions (message_id, user_id, reaction)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)
            ");

            $stmt->execute([$messageId, $userId, $reaction]);

            // Gamification: Award points to message author if helpful reaction
            if (in_array($reaction, ['👍', '❤️', '🔥', '💯'])) {
                $msgStmt = $this->db->prepare("SELECT user_id FROM chat_messages WHERE id = ?");
                $msgStmt->execute([$messageId]);
                $authorId = $msgStmt->fetchColumn();

                if ($authorId && $authorId != $userId) {
                    $this->awardPoints($authorId, self::POINTS_HELPFUL_REACTION, 'helpful_reaction_received');
                }
            }

            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error("Add reaction failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to add reaction'];
        }
    }

    /**
     * AI: Generate insight for message
     */
    private function generateAIInsight(string $message, int $channelId): ?array
    {
        try {
            // Get recent context from channel
            $context = $this->getChannelContext($channelId, 10);

            $response = $this->ai->query([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful CIS assistant. Provide brief, actionable insights for staff questions. Reference CIS knowledge when relevant.'],
                    ['role' => 'user', 'content' => "Context: " . json_encode($context)],
                    ['role' => 'user', 'content' => "Question: $message"]
                ],
                'max_tokens' => 200
            ]);

            if ($response['success']) {
                return [
                    'insight' => $response['content'],
                    'confidence' => 0.85,
                    'sources' => []
                ];
            }

        } catch (Exception $e) {
            $this->logger->warning("AI insight generation failed", ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * AI: Analyze uploaded image
     */
    private function analyzeImage(string $filePath): ?array
    {
        try {
            // Use vision API to analyze image
            $base64 = base64_encode(file_get_contents($filePath));

            $response = $this->ai->query([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Analyze this image and provide: 1) Brief description, 2) Any text visible, 3) Business context if relevant'],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,$base64"]]
                        ]
                    ]
                ],
                'max_tokens' => 300
            ]);

            if ($response['success']) {
                return [
                    'description' => $response['content'],
                    'has_text' => strpos($response['content'], 'text') !== false
                ];
            }

        } catch (Exception $e) {
            $this->logger->warning("Image analysis failed", ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Search messages with full-text search
     */
    public function searchMessages(int $userId, string $query, ?int $channelId = null, ?int $limit = 50): array
    {
        try {
            $sql = "
                SELECT m.*, u.username, u.full_name,
                       MATCH(m.message) AGAINST(? IN BOOLEAN MODE) AS relevance
                FROM chat_messages m
                JOIN staff_accounts u ON m.user_id = u.id
                WHERE MATCH(m.message) AGAINST(? IN BOOLEAN MODE)
            ";

            $params = [$query, $query];

            if ($channelId) {
                $sql .= " AND m.channel_id = ?";
                $params[] = $channelId;
            }

            $sql .= " ORDER BY relevance DESC, m.created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return [
                'success' => true,
                'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];

        } catch (Exception $e) {
            $this->logger->error("Search failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Search failed'];
        }
    }

    /**
     * Get user gamification stats
     */
    public function getUserStats(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    total_points,
                    level,
                    messages_sent,
                    files_shared,
                    helpful_reactions,
                    channels_created,
                    streak_days
                FROM chat_user_stats
                WHERE user_id = ?
            ");

            $stmt->execute([$userId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stats) {
                // Initialize stats
                $this->initializeUserStats($userId);
                $stats = [
                    'total_points' => 0,
                    'level' => 1,
                    'messages_sent' => 0,
                    'files_shared' => 0,
                    'helpful_reactions' => 0,
                    'channels_created' => 0,
                    'streak_days' => 0
                ];
            }

            // Calculate level progress
            $nextLevel = ($stats['level'] + 1);
            $pointsForNextLevel = $nextLevel * 100; // 100 points per level
            $progress = ($stats['total_points'] % 100) / 100 * 100;

            return [
                'success' => true,
                'stats' => $stats,
                'level_progress' => round($progress, 2),
                'points_to_next_level' => $pointsForNextLevel - $stats['total_points']
            ];

        } catch (Exception $e) {
            $this->logger->error("Get user stats failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to load stats'];
        }
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(string $period = 'all_time', int $limit = 10): array
    {
        try {
            $sql = "
                SELECT
                    s.*,
                    u.username,
                    u.full_name,
                    u.outlet_id
                FROM chat_user_stats s
                JOIN staff_accounts u ON s.user_id = u.id
            ";

            if ($period === 'weekly') {
                $sql .= " WHERE s.week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
            } elseif ($period === 'monthly') {
                $sql .= " WHERE MONTH(s.last_updated) = MONTH(CURDATE())";
            }

            $sql .= " ORDER BY s.total_points DESC LIMIT ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);

            return [
                'success' => true,
                'leaderboard' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];

        } catch (Exception $e) {
            $this->logger->error("Leaderboard failed", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to load leaderboard'];
        }
    }

    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================

    private function userHasChannelAccess(int $userId, int $channelId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM chat_channel_participants
            WHERE channel_id = ? AND user_id = ?
        ");
        $stmt->execute([$channelId, $userId]);
        return $stmt->fetchColumn() > 0;
    }

    private function extractMentions(string $message): array
    {
        preg_match_all('/@(\w+)/', $message, $matches);
        return $matches[1] ?? [];
    }

    private function containsLinks(string $message): bool
    {
        return preg_match('/https?:\/\/\S+/', $message) === 1;
    }

    private function isQuestion(string $message): bool
    {
        return strpos($message, '?') !== false ||
               preg_match('/^(how|what|when|where|why|who|can|is|are|do|does)/i', $message);
    }

    private function storeMentions(int $messageId, array $usernames): void
    {
        foreach ($usernames as $username) {
            $stmt = $this->db->prepare("SELECT id FROM staff_accounts WHERE username = ?");
            $stmt->execute([$username]);
            $userId = $stmt->fetchColumn();

            if ($userId) {
                $stmt = $this->db->prepare("
                    INSERT INTO chat_mentions (message_id, mentioned_user_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$messageId, $userId]);
            }
        }
    }

    private function awardPoints(int $userId, int $points, string $reason): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_user_stats (user_id, total_points)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    total_points = total_points + ?,
                    level = FLOOR(total_points / 100) + 1
            ");
            $stmt->execute([$userId, $points, $points]);

            // Log achievement
            $this->logger->info("Points awarded", [
                'user_id' => $userId,
                'points' => $points,
                'reason' => $reason
            ]);

        } catch (Exception $e) {
            $this->logger->error("Award points failed", ['error' => $e->getMessage()]);
        }
    }

    private function isFirstMessageToday(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM chat_messages
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() == 1; // Returns true if this is the first message
    }

    private function broadcastMessage(int $channelId, array $data): void
    {
        if ($this->redis) {
            try {
                $this->redis->publish("chat:channel:$channelId", json_encode($data));
            } catch (Exception $e) {
                $this->logger->warning("Redis broadcast failed", ['error' => $e->getMessage()]);
            }
        }
    }

    private function getChannelContext(int $channelId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT message, user_id, created_at
            FROM chat_messages
            WHERE channel_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$channelId, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function addChannelParticipant(int $channelId, int $userId, string $role = 'member'): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO chat_channel_participants (channel_id, user_id, role)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$channelId, $userId, $role]);
    }

    private function initializeUserStats(int $userId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO chat_user_stats (user_id) VALUES (?)
        ");
        $stmt->execute([$userId]);
    }

    private function generateThumbnail(string $filePath, string $ext): ?string
    {
        try {
            $thumbnail = str_replace('.' . $ext, '_thumb.' . $ext, $filePath);

            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $src = imagecreatefromjpeg($filePath);
                    break;
                case 'png':
                    $src = imagecreatefrompng($filePath);
                    break;
                case 'gif':
                    $src = imagecreatefromgif($filePath);
                    break;
                default:
                    return null;
            }

            $width = imagesx($src);
            $height = imagesy($src);
            $newWidth = 200;
            $newHeight = floor($height * ($newWidth / $width));

            $tmp = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagejpeg($tmp, $thumbnail, 80);

            imagedestroy($src);
            imagedestroy($tmp);

            return str_replace($_SERVER['DOCUMENT_ROOT'], '', $thumbnail);

        } catch (Exception $e) {
            $this->logger->warning("Thumbnail generation failed", ['error' => $e->getMessage()]);
            return null;
        }
    }
}
