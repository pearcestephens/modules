<?php
/**
 * Notification & Messenger System - Core Engine
 *
 * Handles notification triggering, routing, and delivery across
 * multiple channels (in-app, email, push, SMS) with intelligent
 * user preference management.
 *
 * @package    CIS\Notifications
 * @version    1.0.0
 * @author     CIS Development Team
 */

namespace CIS\Notifications;

use CIS\Base\Logger;
use CIS\Base\Database;
use CIS\Base\Cache;

/**
 * NotificationEngine - Core notification system
 *
 * Handles notification creation, routing, and multi-channel delivery
 */
class NotificationEngine
{
    /**
     * Notification categories
     */
    const CATEGORY_MESSAGE = 'message';
    const CATEGORY_NEWS = 'news';
    const CATEGORY_ISSUE = 'issue';
    const CATEGORY_ALERT = 'alert';

    /**
     * Notification priorities
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    /**
     * Delivery channels
     */
    const CHANNEL_IN_APP = 'in-app';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_PUSH = 'push';
    const CHANNEL_SMS = 'sms';

    /**
     * Database connection
     * @var Database
     */
    private $db;

    /**
     * Cache handler
     * @var Cache
     */
    private $cache;

    /**
     * Logger
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
        $this->logger = Logger::getInstance();
    }

    /**
     * Trigger a notification event
     *
     * Usage:
     *   $engine->trigger('message', 'direct_message_received', [
     *       'user_id' => 123,
     *       'triggered_by_user_id' => 456,
     *       'event_reference_id' => 'msg_789',
     *       'title' => 'New Message from John',
     *       'message' => 'Hey, are you available for a quick call?',
     *       'priority' => 'high',
     *       'action_url' => '/messenger/conversation/456'
     *   ]);
     *
     * @param string $category Notification category
     * @param string $event Event identifier
     * @param array $data Event data
     * @return string Notification ID
     */
    public function trigger($category, $event, array $data)
    {
        try {
            // Validate inputs
            if (empty($data['user_id'])) {
                throw new \InvalidArgumentException('user_id is required');
            }

            $userId = $data['user_id'];
            $triggeredByUserId = $data['triggered_by_user_id'] ?? null;
            $priority = $data['priority'] ?? self::PRIORITY_NORMAL;
            $title = $data['title'] ?? 'Notification';
            $message = $data['message'] ?? '';
            $actionUrl = $data['action_url'] ?? null;
            $actionLabel = $data['action_label'] ?? null;
            $eventReferenceId = $data['event_reference_id'] ?? null;
            $eventReferenceType = $data['event_reference_type'] ?? null;

            // Get user preferences
            $preferences = $this->getUserPreferences($userId);

            // Determine routing (which channels to use)
            $channels = $this->determineChannels(
                $category,
                $priority,
                $preferences
            );

            // Create notification record
            $notificationId = $this->createNotification([
                'user_id' => $userId,
                'category' => $category,
                'priority' => $priority,
                'title' => $title,
                'message' => $message,
                'triggered_by_user_id' => $triggeredByUserId,
                'event_reference_id' => $eventReferenceId,
                'event_reference_type' => $eventReferenceType,
                'action_url' => $actionUrl,
                'action_label' => $actionLabel,
                'channels' => json_encode($channels),
                'data' => json_encode($data),
            ]);

            // Queue delivery to each channel
            foreach ($channels as $channel) {
                $this->queueDelivery($notificationId, $userId, $channel, [
                    'title' => $title,
                    'message' => $message,
                    'priority' => $priority,
                    'action_url' => $actionUrl,
                ]);
            }

            // Log event
            $this->logger->info('Notification triggered', [
                'notification_id' => $notificationId,
                'category' => $category,
                'user_id' => $userId,
                'channels' => $channels,
            ]);

            return $notificationId;

        } catch (\Exception $e) {
            $this->logger->error('Failed to trigger notification', [
                'error' => $e->getMessage(),
                'category' => $category,
                'event' => $event,
                'user_id' => $data['user_id'] ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * Get user's notification preferences
     *
     * @param int $userId User ID
     * @return array Preferences (with defaults if none exist)
     */
    public function getUserPreferences($userId)
    {
        // Try cache first
        $cacheKey = "user_notif_prefs_{$userId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Get from database
        $result = $this->db->query(
            "SELECT * FROM notification_preferences WHERE user_id = ?",
            [$userId]
        );

        if (!empty($result)) {
            $prefs = $result[0];
        } else {
            // Return defaults if no preferences exist
            $prefs = $this->getDefaultPreferences();
        }

        // Cache for 1 hour
        $this->cache->set($cacheKey, $prefs, 3600);

        return $prefs;
    }

    /**
     * Save user notification preferences
     *
     * @param int $userId User ID
     * @param array $preferences Preference settings
     * @return bool Success
     */
    public function saveUserPreferences($userId, array $preferences)
    {
        // Check if record exists
        $existing = $this->db->query(
            "SELECT preference_id FROM notification_preferences WHERE user_id = ?",
            [$userId]
        );

        try {
            if (!empty($existing)) {
                // Update existing
                $this->db->query(
                    "UPDATE notification_preferences SET ? WHERE user_id = ?",
                    [$preferences, $userId]
                );
            } else {
                // Insert new
                $preferences['user_id'] = $userId;
                $this->db->query(
                    "INSERT INTO notification_preferences SET ?",
                    [$preferences]
                );
            }

            // Clear cache
            $this->cache->delete("user_notif_prefs_{$userId}");

            $this->logger->info('Notification preferences saved', [
                'user_id' => $userId,
                'preferences_updated' => array_keys($preferences),
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to save preferences', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return false;
        }
    }

    /**
     * Get default notification preferences
     *
     * @return array Default preferences
     */
    private function getDefaultPreferences()
    {
        return [
            'in_app_enabled' => true,
            'in_app_sound' => true,
            'in_app_desktop_alert' => true,
            'email_enabled' => true,
            'email_frequency' => 'daily',
            'email_critical_only' => false,
            'push_enabled' => true,
            'push_vibration' => true,
            'push_sound' => true,
            'sms_enabled' => false,
            'sms_critical_only' => true,
            'message_notifications' => true,
            'news_notifications' => true,
            'issue_notifications' => true,
            'alert_notifications' => true,
            'dnd_enabled' => false,
            'dnd_allow_critical' => true,
        ];
    }

    /**
     * Determine which channels to use for a notification
     *
     * @param string $category Notification category
     * @param string $priority Priority level
     * @param array $preferences User preferences
     * @return array Channel names to use
     */
    private function determineChannels($category, $priority, $preferences)
    {
        $channels = [];

        // In-app: Always send if enabled (for this category)
        if ($preferences['in_app_enabled'] ?? true) {
            $categoryKey = "{$category}_notifications";
            if ($preferences[$categoryKey] ?? true) {
                $channels[] = self::CHANNEL_IN_APP;
            }
        }

        // Check Do Not Disturb
        if ($this->isInDoNotDisturb($preferences)) {
            // Only allow critical through
            if ($priority === self::PRIORITY_CRITICAL && $preferences['dnd_allow_critical']) {
                // Continue to check other channels
            } else {
                // Skip non-critical channels
                return $channels; // Only in-app (if enabled above)
            }
        }

        // Email: Send if enabled AND (critical/high OR not email_critical_only)
        if ($preferences['email_enabled'] ?? true) {
            if ($priority === self::PRIORITY_CRITICAL ||
                $priority === self::PRIORITY_HIGH ||
                !($preferences['email_critical_only'] ?? false)) {
                $channels[] = self::CHANNEL_EMAIL;
            }
        }

        // Push: Send if enabled AND mobile app installed AND (critical/high)
        if ($preferences['push_enabled'] ?? true) {
            if ($priority === self::PRIORITY_CRITICAL || $priority === self::PRIORITY_HIGH) {
                // TODO: Check if user has mobile app registered
                $channels[] = self::CHANNEL_PUSH;
            }
        }

        // SMS: Send ONLY if critical AND SMS enabled AND phone verified
        if ($priority === self::PRIORITY_CRITICAL &&
            ($preferences['sms_enabled'] ?? false) &&
            ($preferences['sms_verified'] ?? false)) {
            $channels[] = self::CHANNEL_SMS;
        }

        return array_unique($channels);
    }

    /**
     * Check if user is in Do Not Disturb hours
     *
     * @param array $preferences User preferences
     * @return bool True if in DND hours
     */
    private function isInDoNotDisturb($preferences)
    {
        if (!($preferences['dnd_enabled'] ?? false)) {
            return false;
        }

        $currentTime = date('H:i:s');
        $startTime = $preferences['dnd_start_time'] ?? '22:00:00';
        $endTime = $preferences['dnd_end_time'] ?? '08:00:00';

        // Handle overnight ranges (e.g., 22:00 to 08:00)
        if ($startTime < $endTime) {
            // Normal range within a day
            return $currentTime >= $startTime && $currentTime < $endTime;
        } else {
            // Overnight range
            return $currentTime >= $startTime || $currentTime < $endTime;
        }
    }

    /**
     * Create notification record in database
     *
     * @param array $data Notification data
     * @return string Notification ID
     */
    private function createNotification(array $data)
    {
        $result = $this->db->query(
            "INSERT INTO notifications SET ?",
            [$data]
        );

        return $this->db->getLastInsertId();
    }

    /**
     * Queue notification for delivery via specific channel
     *
     * @param string $notificationId Notification ID
     * @param int $userId User ID
     * @param string $channel Channel name
     * @param array $data Notification data
     * @return bool Success
     */
    private function queueDelivery($notificationId, $userId, $channel, array $data)
    {
        // Real-time in-app: Deliver immediately via WebSocket
        if ($channel === self::CHANNEL_IN_APP) {
            $this->deliverInApp($notificationId, $userId, $data);
            return true;
        }

        // Other channels: Queue for async delivery
        $queueData = [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'channel' => $channel,
            'data' => json_encode($data),
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'pending',
        ];

        try {
            $this->db->query(
                "INSERT INTO notification_delivery_queue SET ?",
                [$queueData]
            );
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to queue delivery', [
                'error' => $e->getMessage(),
                'channel' => $channel,
                'user_id' => $userId,
            ]);
            return false;
        }
    }

    /**
     * Deliver notification via in-app channel (real-time)
     *
     * @param string $notificationId Notification ID
     * @param int $userId User ID
     * @param array $data Notification data
     */
    private function deliverInApp($notificationId, $userId, array $data)
    {
        // Broadcast via WebSocket to user
        // This assumes WebSocket server is running
        $event = [
            'type' => 'notification:new',
            'notification_id' => $notificationId,
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'priority' => $data['priority'] ?? 'normal',
            'action_url' => $data['action_url'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Queue WebSocket broadcast
        // TODO: Implement WebSocket broadcast
        // $websocketServer->broadcast('notification:' . $userId, $event);

        $this->logger->debug('In-app notification queued for broadcast', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Mark notification as read
     *
     * @param string $notificationId Notification ID
     * @param int $userId User ID
     * @return bool Success
     */
    public function markAsRead($notificationId, $userId)
    {
        try {
            $this->db->query(
                "UPDATE notifications SET is_read = TRUE, read_at = NOW()
                 WHERE notification_id = ? AND user_id = ?",
                [$notificationId, $userId]
            );

            // Clear unread cache
            $this->cache->delete("unread_count_{$userId}");

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark notification as read', [
                'error' => $e->getMessage(),
                'notification_id' => $notificationId,
            ]);
            return false;
        }
    }

    /**
     * Get unread notification count for user
     *
     * @param int $userId User ID
     * @return array Count by category
     */
    public function getUnreadCount($userId)
    {
        // Try cache first
        $cacheKey = "unread_count_{$userId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Query database
        $result = $this->db->query(
            "SELECT category, COUNT(*) as count FROM notifications
             WHERE user_id = ? AND is_read = FALSE AND is_archived = FALSE
             GROUP BY category",
            [$userId]
        );

        $counts = ['total' => 0];
        foreach ($result as $row) {
            $counts[$row['category']] = $row['count'];
            $counts['total'] += $row['count'];
        }

        // Cache for 5 minutes
        $this->cache->set($cacheKey, $counts, 300);

        return $counts;
    }

    /**
     * Get user's notifications
     *
     * @param int $userId User ID
     * @param array $filters Filter criteria
     * @param int $limit Number to return
     * @param int $offset Offset for pagination
     * @return array Notifications
     */
    public function getNotifications($userId, array $filters = [], $limit = 50, $offset = 0)
    {
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];

        // Apply filters
        if (!empty($filters['category'])) {
            $query .= " AND category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['priority'])) {
            $query .= " AND priority = ?";
            $params[] = $filters['priority'];
        }

        if ($filters['unread_only'] ?? false) {
            $query .= " AND is_read = FALSE";
        }

        // Order by created_at DESC, limit
        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->query($query, $params);
    }
}

/**
 * MessengerEngine - Direct messages and group chats
 *
 * Handles conversations, messages, read receipts, typing indicators
 */
class MessengerEngine
{
    /**
     * Conversation types
     */
    const TYPE_DIRECT = 'direct';
    const TYPE_GROUP = 'group';
    const TYPE_BROADCAST = 'broadcast';
    const TYPE_BOT = 'bot';

    /**
     * Database connection
     * @var Database
     */
    private $db;

    /**
     * Cache handler
     * @var Cache
     */
    private $cache;

    /**
     * Logger
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
        $this->logger = Logger::getInstance();
    }

    /**
     * Send a message
     *
     * Usage:
     *   $messenger->sendMessage([
     *       'conversation_id' => 123,
     *       'sender_user_id' => 456,
     *       'message_text' => 'Hello everyone!',
     *       'mentions' => [789, 101],
     *       'reply_to_message_id' => 50,
     *   ]);
     *
     * @param array $data Message data
     * @return string Message ID
     */
    public function sendMessage(array $data)
    {
        try {
            // Validate required fields
            if (empty($data['conversation_id']) || empty($data['sender_user_id']) || empty($data['message_text'])) {
                throw new \InvalidArgumentException('conversation_id, sender_user_id, and message_text are required');
            }

            $conversationId = $data['conversation_id'];
            $senderUserId = $data['sender_user_id'];
            $messageText = $data['message_text'];
            $mentions = $data['mentions'] ?? [];
            $replyToMessageId = $data['reply_to_message_id'] ?? null;
            $attachments = $data['attachments'] ?? [];

            // Create message record
            $messageData = [
                'conversation_id' => $conversationId,
                'sender_user_id' => $senderUserId,
                'message_text' => $messageText,
                'mentions' => !empty($mentions) ? json_encode(['user_ids' => $mentions]) : null,
                'reply_to_message_id' => $replyToMessageId,
                'attachments' => !empty($attachments) ? json_encode(['files' => $attachments]) : null,
            ];

            $messageId = $this->db->query(
                "INSERT INTO chat_messages SET ?",
                [$messageData]
            );

            $messageId = $this->db->getLastInsertId();

            // Update conversation metadata
            $this->db->query(
                "UPDATE chat_conversations SET last_message_id = ?, last_message_at = NOW(), message_count = message_count + 1
                 WHERE conversation_id = ?",
                [$messageId, $conversationId]
            );

            // Trigger notifications for mentions
            if (!empty($mentions)) {
                $this->notifyMentions($conversationId, $messageId, $senderUserId, $mentions);
            }

            // Broadcast message via WebSocket
            $this->broadcastMessage($conversationId, [
                'message_id' => $messageId,
                'conversation_id' => $conversationId,
                'sender_user_id' => $senderUserId,
                'text' => $messageText,
                'mentions' => $mentions,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logger->info('Message sent', [
                'message_id' => $messageId,
                'conversation_id' => $conversationId,
                'sender_user_id' => $senderUserId,
            ]);

            return $messageId;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send message', [
                'error' => $e->getMessage(),
                'conversation_id' => $data['conversation_id'] ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * Get messages for a conversation
     *
     * @param int $conversationId Conversation ID
     * @param int $limit Number to return
     * @param int $offset Offset for pagination
     * @return array Messages
     */
    public function getMessages($conversationId, $limit = 50, $offset = 0)
    {
        return $this->db->query(
            "SELECT m.*, u.name as sender_name, u.avatar_url as sender_avatar
             FROM chat_messages m
             LEFT JOIN cis_users u ON m.sender_user_id = u.user_id
             WHERE m.conversation_id = ? AND m.is_deleted = FALSE
             ORDER BY m.created_at DESC
             LIMIT ? OFFSET ?",
            [$conversationId, $limit, $offset]
        );
    }

    /**
     * Mark messages as read
     *
     * @param int $conversationId Conversation ID
     * @param int $userId User ID
     * @param int $upToMessageId Mark all messages up to this ID
     * @return bool Success
     */
    public function markConversationAsRead($conversationId, $userId, $upToMessageId = null)
    {
        try {
            if ($upToMessageId) {
                $this->db->query(
                    "INSERT IGNORE INTO chat_message_read_receipts (message_id, user_id, read_at)
                     SELECT message_id, ?, NOW()
                     FROM chat_messages
                     WHERE conversation_id = ? AND message_id <= ? AND sender_user_id != ?",
                    [$userId, $conversationId, $upToMessageId, $userId]
                );
            }

            // Update group member metadata
            $this->db->query(
                "UPDATE chat_group_members SET last_read_at = NOW(), last_read_message_id = ?
                 WHERE conversation_id = ? AND user_id = ?",
                [$upToMessageId, $conversationId, $userId]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark messages as read', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId,
            ]);
            return false;
        }
    }

    /**
     * Update typing indicator
     *
     * @param int $conversationId Conversation ID
     * @param int $userId User ID
     * @param bool $isTyping Is user typing?
     * @return bool Success
     */
    public function updateTypingIndicator($conversationId, $userId, $isTyping)
    {
        try {
            if ($isTyping) {
                $this->db->query(
                    "INSERT INTO chat_typing_indicators (conversation_id, user_id, is_typing)
                     VALUES (?, ?, TRUE)
                     ON DUPLICATE KEY UPDATE is_typing = TRUE, updated_at = NOW()",
                    [$conversationId, $userId]
                );
            } else {
                $this->db->query(
                    "UPDATE chat_typing_indicators SET is_typing = FALSE WHERE conversation_id = ? AND user_id = ?",
                    [$conversationId, $userId]
                );
            }

            // Broadcast typing indicator
            $this->broadcastTyping($conversationId, $userId, $isTyping);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update typing indicator', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId,
            ]);
            return false;
        }
    }

    /**
     * Add reaction to message
     *
     * @param int $messageId Message ID
     * @param int $userId User ID
     * @param string $emoji Emoji/reaction
     * @param bool $add Add or remove reaction
     * @return bool Success
     */
    public function addReaction($messageId, $userId, $emoji, $add = true)
    {
        try {
            // Get current reactions
            $message = $this->db->query(
                "SELECT reactions FROM chat_messages WHERE message_id = ?",
                [$messageId]
            );

            if (empty($message)) {
                throw new \Exception('Message not found');
            }

            $reactions = json_decode($message[0]['reactions'], true) ?? [];

            if ($add) {
                if (!isset($reactions[$emoji])) {
                    $reactions[$emoji] = [];
                }
                if (!in_array($userId, $reactions[$emoji])) {
                    $reactions[$emoji][] = $userId;
                }
            } else {
                if (isset($reactions[$emoji])) {
                    $key = array_search($userId, $reactions[$emoji]);
                    if ($key !== false) {
                        unset($reactions[$emoji][$key]);
                    }
                    if (empty($reactions[$emoji])) {
                        unset($reactions[$emoji]);
                    }
                }
            }

            // Update message
            $this->db->query(
                "UPDATE chat_messages SET reactions = ? WHERE message_id = ?",
                [json_encode($reactions), $messageId]
            );

            // Broadcast reaction update
            $this->broadcastReaction($messageId, $emoji, $reactions);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to add reaction', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
            ]);
            return false;
        }
    }

    /**
     * Search messages in conversation
     *
     * @param int $conversationId Conversation ID
     * @param string $query Search query
     * @param int $limit Results limit
     * @return array Search results
     */
    public function searchMessages($conversationId, $query, $limit = 50)
    {
        return $this->db->query(
            "SELECT * FROM chat_messages
             WHERE conversation_id = ? AND is_deleted = FALSE
             AND MATCH(message_text) AGAINST(? IN BOOLEAN MODE)
             ORDER BY created_at DESC
             LIMIT ?",
            [$conversationId, $query, $limit]
        );
    }

    /**
     * Notify users mentioned in message
     *
     * @param int $conversationId Conversation ID
     * @param int $messageId Message ID
     * @param int $senderUserId User who sent message
     * @param array $mentionedUserIds Users to notify
     */
    private function notifyMentions($conversationId, $messageId, $senderUserId, array $mentionedUserIds)
    {
        $engine = new NotificationEngine();

        foreach ($mentionedUserIds as $userId) {
            if ($userId === $senderUserId) {
                continue; // Don't notify sender
            }

            $engine->trigger(
                NotificationEngine::CATEGORY_MESSAGE,
                'mention_in_group',
                [
                    'user_id' => $userId,
                    'triggered_by_user_id' => $senderUserId,
                    'title' => 'You were mentioned',
                    'message' => 'You were mentioned in a group chat',
                    'priority' => NotificationEngine::PRIORITY_HIGH,
                    'event_reference_id' => $messageId,
                    'event_reference_type' => 'message_id',
                    'action_url' => "/messenger/conversation/{$conversationId}",
                ]
            );
        }
    }

    /**
     * Broadcast message via WebSocket
     *
     * @param int $conversationId Conversation ID
     * @param array $message Message data
     */
    private function broadcastMessage($conversationId, array $message)
    {
        // TODO: Implement WebSocket broadcast
        // $websocketServer->broadcast("conversation:{$conversationId}", [
        //     'event' => 'message:new',
        //     'data' => $message
        // ]);
    }

    /**
     * Broadcast typing indicator
     *
     * @param int $conversationId Conversation ID
     * @param int $userId User ID
     * @param bool $isTyping Is typing?
     */
    private function broadcastTyping($conversationId, $userId, $isTyping)
    {
        // TODO: Implement WebSocket broadcast
    }

    /**
     * Broadcast reaction update
     *
     * @param int $messageId Message ID
     * @param string $emoji Emoji
     * @param array $reactions All reactions on message
     */
    private function broadcastReaction($messageId, $emoji, array $reactions)
    {
        // TODO: Implement WebSocket broadcast
    }
}
