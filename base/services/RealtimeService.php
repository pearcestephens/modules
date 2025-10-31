<?php
declare(strict_types=1);

/**
 * Real-Time Notification Service
 * 
 * Handles WebSocket connections and real-time push notifications
 * Uses Redis pub/sub for message distribution
 * 
 * Features:
 * - Toast notifications (Toastr.js)
 * - Notification bell updates
 * - Single-tab page locking
 * - Chat message delivery
 * 
 * @package    CIS
 * @subpackage Services
 * @version    2.0.0
 */

use Base\Database;
use Base\Logger;

class RealtimeService
{
    private $redis;
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->initRedis();
    }
    
    /**
     * Initialize Redis connection
     */
    private function initRedis(): void
    {
        try {
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        } catch (Exception $e) {
            Logger::error('Redis connection failed', [
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Real-time service unavailable');
        }
    }
    
    /**
     * Publish notification to user(s)
     * 
     * @param int|array $userIds User ID(s)
     * @param string $type Notification type (info, success, warning, error)
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data
     */
    public function notify($userIds, string $type, string $title, string $message, array $data = []): void
    {
        $userIds = is_array($userIds) ? $userIds : [$userIds];
        
        $notification = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];
        
        foreach ($userIds as $userId) {
            // Store in database
            $this->db->insert('notifications', [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => json_encode($data),
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Publish to Redis channel
            $channel = "user:{$userId}:notifications";
            $this->redis->publish($channel, $notification);
            
            // Increment unread count
            $this->redis->incr("user:{$userId}:unread_count");
        }
        
        Logger::info('Notification sent', [
            'user_ids' => $userIds,
            'type' => $type,
            'title' => $title
        ]);
    }
    
    /**
     * Get unread notification count for user
     * 
     * @param int $userId User ID
     * @return int Unread count
     */
    public function getUnreadCount(int $userId): int
    {
        $count = $this->redis->get("user:{$userId}:unread_count");
        return $count ? (int)$count : 0;
    }
    
    /**
     * Mark notification as read
     * 
     * @param int $notificationId Notification ID
     * @param int $userId User ID
     */
    public function markAsRead(int $notificationId, int $userId): void
    {
        $this->db->update('notifications', 
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            ['notification_id' => $notificationId, 'user_id' => $userId]
        );
        
        // Decrement unread count
        $this->redis->decr("user:{$userId}:unread_count");
    }
    
    /**
     * Mark all notifications as read
     * 
     * @param int $userId User ID
     */
    public function markAllAsRead(int $userId): void
    {
        $this->db->update('notifications',
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            ['user_id' => $userId, 'is_read' => 0]
        );
        
        // Reset unread count
        $this->redis->set("user:{$userId}:unread_count", 0);
    }
    
    /**
     * Get recent notifications for user
     * 
     * @param int $userId User ID
     * @param int $limit Limit (default 50)
     * @return array Notifications
     */
    public function getNotifications(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT :limit",
            [':user_id' => $userId, ':limit' => $limit]
        );
        
        return $stmt->fetchAll();
    }
    
    /**
     * Lock page for single-tab editing
     * 
     * @param string $pageKey Page identifier (e.g., 'transfer:123')
     * @param int $userId User ID
     * @param int $ttl Lock TTL in seconds (default 300 = 5 minutes)
     * @return bool True if lock acquired, false if already locked
     */
    public function acquirePageLock(string $pageKey, int $userId, int $ttl = 300): bool
    {
        $lockKey = "page_lock:{$pageKey}";
        
        // Try to set lock (NX = only if not exists)
        $acquired = $this->redis->set($lockKey, [
            'user_id' => $userId,
            'username' => $_SESSION['user_name'] ?? 'Unknown',
            'locked_at' => time()
        ], ['NX', 'EX' => $ttl]);
        
        if (!$acquired) {
            Logger::warning('Page lock conflict', [
                'page_key' => $pageKey,
                'user_id' => $userId
            ]);
        }
        
        return (bool)$acquired;
    }
    
    /**
     * Release page lock
     * 
     * @param string $pageKey Page identifier
     * @param int $userId User ID (must match lock owner)
     * @return bool True if released, false if not owned
     */
    public function releasePageLock(string $pageKey, int $userId): bool
    {
        $lockKey = "page_lock:{$pageKey}";
        $lock = $this->redis->get($lockKey);
        
        if ($lock && $lock['user_id'] === $userId) {
            $this->redis->del($lockKey);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get page lock info
     * 
     * @param string $pageKey Page identifier
     * @return array|null Lock info or null if not locked
     */
    public function getPageLock(string $pageKey): ?array
    {
        $lockKey = "page_lock:{$pageKey}";
        $lock = $this->redis->get($lockKey);
        
        return $lock ?: null;
    }
    
    /**
     * Refresh page lock (extend TTL)
     * 
     * @param string $pageKey Page identifier
     * @param int $userId User ID (must match lock owner)
     * @param int $ttl New TTL in seconds
     * @return bool True if refreshed, false if not owned
     */
    public function refreshPageLock(string $pageKey, int $userId, int $ttl = 300): bool
    {
        $lockKey = "page_lock:{$pageKey}";
        $lock = $this->redis->get($lockKey);
        
        if ($lock && $lock['user_id'] === $userId) {
            $this->redis->expire($lockKey, $ttl);
            return true;
        }
        
        return false;
    }
    
    /**
     * Broadcast system message to all users
     * 
     * @param string $type Message type (info, success, warning, error)
     * @param string $title Message title
     * @param string $message Message content
     */
    public function broadcastSystem(string $type, string $title, string $message): void
    {
        $broadcast = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'timestamp' => time()
        ];
        
        // Publish to system broadcast channel
        $this->redis->publish('system:broadcast', $broadcast);
        
        Logger::info('System broadcast sent', [
            'type' => $type,
            'title' => $title
        ]);
    }
}
