<?php
/**
 * WebSocket Event Handlers for Real-Time Messenger & Notifications
 *
 * Handles WebSocket connections for real-time message delivery,
 * typing indicators, and notifications.
 *
 * @package    CIS\WebSocket
 * @version    1.0.0
 */

namespace CIS\WebSocket;

use CIS\Base\Logger;
use CIS\Base\Database;

/**
 * WebSocket Event Manager
 *
 * Handles all WebSocket events for messaging and notifications
 */
class EventManager
{
    /**
     * WebSocket event types
     */
    const EVENT_MESSAGE_NEW = 'message:new';
    const EVENT_MESSAGE_EDITED = 'message:edited';
    const EVENT_MESSAGE_DELETED = 'message:deleted';
    const EVENT_TYPING_START = 'typing:start';
    const EVENT_TYPING_STOP = 'typing:stop';
    const EVENT_REACTION_ADDED = 'reaction:added';
    const EVENT_REACTION_REMOVED = 'reaction:removed';
    const EVENT_NOTIFICATION_NEW = 'notification:new';
    const EVENT_USER_ONLINE = 'user:online';
    const EVENT_USER_OFFLINE = 'user:offline';
    const EVENT_CONVERSATION_CREATED = 'conversation:created';
    const EVENT_MEMBER_JOINED = 'member:joined';
    const EVENT_MEMBER_LEFT = 'member:left';

    /**
     * Logger instance
     * @var Logger
     */
    private $logger;

    /**
     * Database instance
     * @var Database
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->db = Database::getInstance();
    }

    /**
     * Handle incoming WebSocket message
     *
     * @param array $data Event data from WebSocket client
     * @param int $userId User ID sending the event
     * @param array $context Additional context (connection ID, etc.)
     */
    public function handle(array $data, $userId, array $context = [])
    {
        try {
            // Validate message structure
            if (empty($data['event'])) {
                $this->logger->error('Invalid WebSocket message: missing event type');
                return;
            }

            $event = $data['event'];
            $payload = $data['payload'] ?? [];

            $this->logger->debug("WebSocket event: {$event}", [
                'user_id' => $userId,
                'context' => $context,
            ]);

            // Route to appropriate handler
            switch ($event) {
                case self::EVENT_MESSAGE_NEW:
                    $this->handleNewMessage($payload, $userId);
                    break;

                case self::EVENT_MESSAGE_EDITED:
                    $this->handleMessageEdited($payload, $userId);
                    break;

                case self::EVENT_MESSAGE_DELETED:
                    $this->handleMessageDeleted($payload, $userId);
                    break;

                case self::EVENT_TYPING_START:
                    $this->handleTypingStart($payload, $userId);
                    break;

                case self::EVENT_TYPING_STOP:
                    $this->handleTypingStop($payload, $userId);
                    break;

                case self::EVENT_REACTION_ADDED:
                    $this->handleReactionAdded($payload, $userId);
                    break;

                case self::EVENT_REACTION_REMOVED:
                    $this->handleReactionRemoved($payload, $userId);
                    break;

                case self::EVENT_USER_ONLINE:
                    $this->handleUserOnline($userId, $context);
                    break;

                case self::EVENT_USER_OFFLINE:
                    $this->handleUserOffline($userId);
                    break;

                default:
                    $this->logger->warning("Unknown event type: {$event}");
            }

        } catch (\Exception $e) {
            $this->logger->error("WebSocket event handling error", [
                'error' => $e->getMessage(),
                'event' => $data['event'] ?? 'unknown',
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Handle new message event
     *
     * Event data:
     * {
     *   "event": "message:new",
     *   "payload": {
     *     "conversation_id": 123,
     *     "message_text": "Hello everyone!",
     *     "mentions": [456, 789],
     *     "reply_to_message_id": null
     *   }
     * }
     *
     * @param array $payload Message payload
     * @param int $userId Sender user ID
     */
    private function handleNewMessage($payload, $userId)
    {
        if (empty($payload['conversation_id']) || empty($payload['message_text'])) {
            $this->logger->error('Invalid message payload', ['payload' => $payload]);
            return;
        }

        // Verify user is member of conversation
        $isMember = $this->db->query(
            "SELECT 1 FROM chat_group_members
             WHERE conversation_id = ? AND user_id = ? AND is_active = TRUE",
            [$payload['conversation_id'], $userId]
        );

        if (empty($isMember)) {
            $this->logger->warning("User {$userId} not member of conversation {$payload['conversation_id']}");
            return;
        }

        // Save message to database
        $messageData = [
            'conversation_id' => $payload['conversation_id'],
            'sender_user_id' => $userId,
            'message_text' => $payload['message_text'],
            'mentions' => !empty($payload['mentions']) ? json_encode(['user_ids' => $payload['mentions']]) : null,
            'reply_to_message_id' => $payload['reply_to_message_id'] ?? null,
        ];

        try {
            $this->db->query(
                "INSERT INTO chat_messages SET ?",
                [$messageData]
            );

            $messageId = $this->db->getLastInsertId();

            // Update conversation
            $this->db->query(
                "UPDATE chat_conversations
                 SET last_message_id = ?, last_message_at = NOW(), message_count = message_count + 1
                 WHERE conversation_id = ?",
                [$messageId, $payload['conversation_id']]
            );

            // Broadcast message to all conversation members
            $this->broadcastToConversation($payload['conversation_id'], [
                'event' => self::EVENT_MESSAGE_NEW,
                'data' => [
                    'message_id' => $messageId,
                    'conversation_id' => $payload['conversation_id'],
                    'sender_user_id' => $userId,
                    'text' => $payload['message_text'],
                    'mentions' => $payload['mentions'] ?? [],
                    'created_at' => date('Y-m-d H:i:s'),
                ],
            ]);

            // Handle mentions
            if (!empty($payload['mentions'])) {
                $this->handleMentions($payload['conversation_id'], $messageId, $userId, $payload['mentions']);
            }

            $this->logger->info("Message created: {$messageId}", [
                'conversation_id' => $payload['conversation_id'],
                'sender_user_id' => $userId,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create message', [
                'error' => $e->getMessage(),
                'conversation_id' => $payload['conversation_id'],
            ]);
        }
    }

    /**
     * Handle message edited event
     *
     * @param array $payload Event payload
     * @param int $userId User editing message
     */
    private function handleMessageEdited($payload, $userId)
    {
        if (empty($payload['message_id']) || empty($payload['message_text'])) {
            $this->logger->error('Invalid edited message payload', ['payload' => $payload]);
            return;
        }

        try {
            // Verify user is sender
            $message = $this->db->query(
                "SELECT conversation_id, sender_user_id FROM chat_messages WHERE message_id = ?",
                [$payload['message_id']]
            );

            if (empty($message) || $message[0]['sender_user_id'] != $userId) {
                $this->logger->warning("User {$userId} cannot edit message {$payload['message_id']}");
                return;
            }

            // Update message
            $this->db->query(
                "UPDATE chat_messages
                 SET message_text = ?, is_edited = TRUE, edited_at = NOW()
                 WHERE message_id = ?",
                [$payload['message_text'], $payload['message_id']]
            );

            // Broadcast edit to conversation
            $this->broadcastToConversation($message[0]['conversation_id'], [
                'event' => self::EVENT_MESSAGE_EDITED,
                'data' => [
                    'message_id' => $payload['message_id'],
                    'text' => $payload['message_text'],
                    'edited_at' => date('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to edit message', [
                'error' => $e->getMessage(),
                'message_id' => $payload['message_id'],
            ]);
        }
    }

    /**
     * Handle message deleted event
     *
     * @param array $payload Event payload
     * @param int $userId User deleting message
     */
    private function handleMessageDeleted($payload, $userId)
    {
        if (empty($payload['message_id'])) {
            $this->logger->error('Invalid deleted message payload', ['payload' => $payload]);
            return;
        }

        try {
            // Verify user is sender or admin
            $message = $this->db->query(
                "SELECT conversation_id, sender_user_id FROM chat_messages WHERE message_id = ?",
                [$payload['message_id']]
            );

            if (empty($message)) {
                $this->logger->warning("Message {$payload['message_id']} not found");
                return;
            }

            if ($message[0]['sender_user_id'] != $userId) {
                // TODO: Check if user is admin
            }

            // Soft delete message
            $this->db->query(
                "UPDATE chat_messages
                 SET is_deleted = TRUE, deleted_by_user_id = ?
                 WHERE message_id = ?",
                [$userId, $payload['message_id']]
            );

            // Broadcast deletion
            $this->broadcastToConversation($message[0]['conversation_id'], [
                'event' => self::EVENT_MESSAGE_DELETED,
                'data' => [
                    'message_id' => $payload['message_id'],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete message', [
                'error' => $e->getMessage(),
                'message_id' => $payload['message_id'],
            ]);
        }
    }

    /**
     * Handle typing start event
     *
     * Event data:
     * {
     *   "event": "typing:start",
     *   "payload": {
     *     "conversation_id": 123
     *   }
     * }
     *
     * @param array $payload Event payload
     * @param int $userId User typing
     */
    private function handleTypingStart($payload, $userId)
    {
        if (empty($payload['conversation_id'])) {
            return;
        }

        try {
            // Update typing indicator
            $this->db->query(
                "INSERT INTO chat_typing_indicators (conversation_id, user_id, is_typing)
                 VALUES (?, ?, TRUE)
                 ON DUPLICATE KEY UPDATE is_typing = TRUE, updated_at = NOW()",
                [$payload['conversation_id'], $userId]
            );

            // Broadcast typing indicator
            $this->broadcastToConversation($payload['conversation_id'], [
                'event' => self::EVENT_TYPING_START,
                'data' => [
                    'conversation_id' => $payload['conversation_id'],
                    'user_id' => $userId,
                ],
            ], [$userId]); // Don't send to typing user

        } catch (\Exception $e) {
            $this->logger->error('Failed to update typing indicator', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle typing stop event
     *
     * @param array $payload Event payload
     * @param int $userId User who stopped typing
     */
    private function handleTypingStop($payload, $userId)
    {
        if (empty($payload['conversation_id'])) {
            return;
        }

        try {
            // Clear typing indicator
            $this->db->query(
                "UPDATE chat_typing_indicators
                 SET is_typing = FALSE
                 WHERE conversation_id = ? AND user_id = ?",
                [$payload['conversation_id'], $userId]
            );

            // Broadcast stop typing
            $this->broadcastToConversation($payload['conversation_id'], [
                'event' => self::EVENT_TYPING_STOP,
                'data' => [
                    'conversation_id' => $payload['conversation_id'],
                    'user_id' => $userId,
                ],
            ], [$userId]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to clear typing indicator', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle reaction added event
     *
     * Event data:
     * {
     *   "event": "reaction:added",
     *   "payload": {
     *     "message_id": 123,
     *     "emoji": "👍"
     *   }
     * }
     *
     * @param array $payload Event payload
     * @param int $userId User adding reaction
     */
    private function handleReactionAdded($payload, $userId)
    {
        if (empty($payload['message_id']) || empty($payload['emoji'])) {
            return;
        }

        try {
            // Get current reactions
            $message = $this->db->query(
                "SELECT reactions, conversation_id FROM chat_messages WHERE message_id = ?",
                [$payload['message_id']]
            );

            if (empty($message)) {
                return;
            }

            $reactions = json_decode($message[0]['reactions'], true) ?? [];

            // Add reaction
            if (!isset($reactions[$payload['emoji']])) {
                $reactions[$payload['emoji']] = [];
            }
            if (!in_array($userId, $reactions[$payload['emoji']])) {
                $reactions[$payload['emoji']][] = $userId;
            }

            // Save reactions
            $this->db->query(
                "UPDATE chat_messages SET reactions = ? WHERE message_id = ?",
                [json_encode($reactions), $payload['message_id']]
            );

            // Broadcast reaction update
            $this->broadcastToConversation($message[0]['conversation_id'], [
                'event' => self::EVENT_REACTION_ADDED,
                'data' => [
                    'message_id' => $payload['message_id'],
                    'emoji' => $payload['emoji'],
                    'reactions' => $reactions,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to add reaction', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle reaction removed event
     *
     * @param array $payload Event payload
     * @param int $userId User removing reaction
     */
    private function handleReactionRemoved($payload, $userId)
    {
        if (empty($payload['message_id']) || empty($payload['emoji'])) {
            return;
        }

        try {
            // Get current reactions
            $message = $this->db->query(
                "SELECT reactions, conversation_id FROM chat_messages WHERE message_id = ?",
                [$payload['message_id']]
            );

            if (empty($message)) {
                return;
            }

            $reactions = json_decode($message[0]['reactions'], true) ?? [];

            // Remove reaction
            if (isset($reactions[$payload['emoji']])) {
                $key = array_search($userId, $reactions[$payload['emoji']]);
                if ($key !== false) {
                    unset($reactions[$payload['emoji']][$key]);
                }
                if (empty($reactions[$payload['emoji']])) {
                    unset($reactions[$payload['emoji']]);
                }
            }

            // Save reactions
            $this->db->query(
                "UPDATE chat_messages SET reactions = ? WHERE message_id = ?",
                [json_encode($reactions), $payload['message_id']]
            );

            // Broadcast reaction update
            $this->broadcastToConversation($message[0]['conversation_id'], [
                'event' => self::EVENT_REACTION_REMOVED,
                'data' => [
                    'message_id' => $payload['message_id'],
                    'emoji' => $payload['emoji'],
                    'reactions' => $reactions,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to remove reaction', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle user online event
     *
     * @param int $userId User ID
     * @param array $context Connection context
     */
    private function handleUserOnline($userId, $context = [])
    {
        try {
            // TODO: Update user online status
            // Update Redis/cache with user online status

            // Get user's conversations
            $conversations = $this->db->query(
                "SELECT conversation_id FROM chat_group_members
                 WHERE user_id = ? AND is_active = TRUE",
                [$userId]
            );

            // Broadcast user online to all their conversations
            foreach ($conversations as $conv) {
                $this->broadcastToConversation($conv['conversation_id'], [
                    'event' => self::EVENT_USER_ONLINE,
                    'data' => [
                        'user_id' => $userId,
                    ],
                ], [$userId]); // Don't send to user
            }

            $this->logger->debug("User {$userId} online", ['context' => $context]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to handle user online', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle user offline event
     *
     * @param int $userId User ID
     */
    private function handleUserOffline($userId)
    {
        try {
            // TODO: Clear user online status
            // Update Redis/cache with user offline status

            // Get user's conversations
            $conversations = $this->db->query(
                "SELECT conversation_id FROM chat_group_members
                 WHERE user_id = ? AND is_active = TRUE",
                [$userId]
            );

            // Broadcast user offline to all their conversations
            foreach ($conversations as $conv) {
                $this->broadcastToConversation($conv['conversation_id'], [
                    'event' => self::EVENT_USER_OFFLINE,
                    'data' => [
                        'user_id' => $userId,
                    ],
                ], [$userId]);
            }

            // Clear typing indicators
            $this->db->query(
                "DELETE FROM chat_typing_indicators WHERE user_id = ?",
                [$userId]
            );

            $this->logger->debug("User {$userId} offline");

        } catch (\Exception $e) {
            $this->logger->error('Failed to handle user offline', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast event to all members of a conversation
     *
     * @param int $conversationId Conversation ID
     * @param array $event Event data to broadcast
     * @param array $excludeUserIds User IDs to exclude from broadcast
     */
    private function broadcastToConversation($conversationId, array $event, array $excludeUserIds = [])
    {
        // Get conversation members
        $members = $this->db->query(
            "SELECT user_id FROM chat_group_members
             WHERE conversation_id = ? AND is_active = TRUE",
            [$conversationId]
        );

        // TODO: Send event via WebSocket to each member (except excluded)
        // For each member:
        //   if (!in_array($member['user_id'], $excludeUserIds)) {
        //     websocket_server->send_to_user($member['user_id'], $event);
        //   }
    }

    /**
     * Handle mentions in message
     *
     * @param int $conversationId Conversation ID
     * @param int $messageId Message ID
     * @param int $senderUserId User who sent message
     * @param array $mentionedUserIds Users mentioned
     */
    private function handleMentions($conversationId, $messageId, $senderUserId, array $mentionedUserIds)
    {
        $engine = new \CIS\Notifications\NotificationEngine();

        foreach ($mentionedUserIds as $userId) {
            if ($userId === $senderUserId) {
                continue;
            }

            // Send notification
            $engine->trigger(
                \CIS\Notifications\NotificationEngine::CATEGORY_MESSAGE,
                'mention_in_group',
                [
                    'user_id' => $userId,
                    'triggered_by_user_id' => $senderUserId,
                    'title' => 'You were mentioned',
                    'message' => 'You were mentioned in a conversation',
                    'priority' => \CIS\Notifications\NotificationEngine::PRIORITY_HIGH,
                    'event_reference_id' => $messageId,
                    'event_reference_type' => 'message_id',
                    'action_url' => "/messenger/conversation/{$conversationId}",
                ]
            );
        }
    }
}
