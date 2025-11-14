<?php
/**
 * CIS Theme System - Notification & Messaging Mock Data
 * Realistic notifications, messages, and chat room data
 */

namespace CIS\Themes;

class NotificationData {

    /**
     * Get unread notifications
     */
    public static function getNotifications() {
        return [
            [
                'id' => 1,
                'type' => 'order',
                'icon' => '📦',
                'title' => 'New Order Received',
                'message' => 'Order #1234 from Auckland CBD - $234.50',
                'time' => '2 mins ago',
                'timestamp' => time() - 120,
                'read' => false,
                'priority' => 'high',
                'action_url' => '/orders/1234'
            ],
            [
                'id' => 2,
                'type' => 'stock',
                'icon' => '⚠️',
                'title' => 'Low Stock Alert',
                'message' => 'JUUL Starter Kit - Only 3 units left at Wellington',
                'time' => '15 mins ago',
                'timestamp' => time() - 900,
                'read' => false,
                'priority' => 'warning',
                'action_url' => '/inventory/alerts'
            ],
            [
                'id' => 3,
                'type' => 'message',
                'icon' => '💬',
                'title' => 'New Message',
                'message' => 'Sarah Mitchell: Can you check the stock levels?',
                'time' => '1 hour ago',
                'timestamp' => time() - 3600,
                'read' => false,
                'priority' => 'normal',
                'action_url' => '/messages/5'
            ],
            [
                'id' => 4,
                'type' => 'achievement',
                'icon' => '🎉',
                'title' => 'Sales Milestone',
                'message' => 'Christchurch store hit $50K monthly sales!',
                'time' => '3 hours ago',
                'timestamp' => time() - 10800,
                'read' => true,
                'priority' => 'normal',
                'action_url' => '/reports/sales'
            ],
            [
                'id' => 5,
                'type' => 'system',
                'icon' => '⚙️',
                'title' => 'System Update',
                'message' => 'CIS will undergo maintenance tonight at 11 PM',
                'time' => '5 hours ago',
                'timestamp' => time() - 18000,
                'read' => true,
                'priority' => 'low',
                'action_url' => '/system/updates'
            ]
        ];
    }

    /**
     * Get recent direct messages
     */
    public static function getMessages() {
        return [
            [
                'id' => 1,
                'from_user' => 'Sarah Mitchell',
                'from_avatar' => 'SM',
                'from_role' => 'Store Manager',
                'message' => 'Hey, can you check the stock levels for Auckland? We\'re running low on JUUL pods.',
                'time' => '2 mins ago',
                'timestamp' => time() - 120,
                'read' => false,
                'online' => true
            ],
            [
                'id' => 2,
                'from_user' => 'James Parker',
                'from_avatar' => 'JP',
                'from_role' => 'Store Manager',
                'message' => 'Thanks for approving the PO! Shipment should arrive tomorrow.',
                'time' => '30 mins ago',
                'timestamp' => time() - 1800,
                'read' => false,
                'online' => true
            ],
            [
                'id' => 3,
                'from_user' => 'Emma Wilson',
                'from_avatar' => 'EW',
                'from_role' => 'Store Manager',
                'message' => 'Quick question about the new pricing structure...',
                'time' => '1 hour ago',
                'timestamp' => time() - 3600,
                'read' => true,
                'online' => false
            ],
            [
                'id' => 4,
                'from_user' => 'Tech Support',
                'from_avatar' => 'TS',
                'from_role' => 'Support Team',
                'message' => 'Your ticket #456 has been resolved. POS issue fixed.',
                'time' => '2 hours ago',
                'timestamp' => time() - 7200,
                'read' => true,
                'online' => true
            ]
        ];
    }

    /**
     * Get chat rooms
     */
    public static function getChatRooms() {
        return [
            [
                'id' => 1,
                'name' => 'All Staff',
                'icon' => '👥',
                'description' => 'Company-wide announcements and discussions',
                'members_count' => 47,
                'unread_count' => 3,
                'online_count' => 12,
                'last_message' => 'Great work everyone on hitting our Q4 targets!',
                'last_message_from' => 'Pearce Stephens',
                'last_message_time' => '5 mins ago',
                'pinned' => true,
                'type' => 'public'
            ],
            [
                'id' => 2,
                'name' => 'Store Managers',
                'icon' => '👔',
                'description' => 'Private channel for store management',
                'members_count' => 17,
                'unread_count' => 5,
                'online_count' => 8,
                'last_message' => 'Can we schedule a meeting for next week?',
                'last_message_from' => 'Sarah Mitchell',
                'last_message_time' => '10 mins ago',
                'pinned' => true,
                'type' => 'private'
            ],
            [
                'id' => 3,
                'name' => 'Auckland Team',
                'icon' => '🏙️',
                'description' => 'Auckland CBD store team chat',
                'members_count' => 12,
                'unread_count' => 0,
                'online_count' => 4,
                'last_message' => 'Stock delivery confirmed for tomorrow morning',
                'last_message_from' => 'Sarah Mitchell',
                'last_message_time' => '1 hour ago',
                'pinned' => false,
                'type' => 'team'
            ],
            [
                'id' => 4,
                'name' => 'Wellington Team',
                'icon' => '🌊',
                'description' => 'Wellington Central store team chat',
                'members_count' => 9,
                'unread_count' => 2,
                'online_count' => 3,
                'last_message' => 'Anyone available for overtime this weekend?',
                'last_message_from' => 'James Parker',
                'last_message_time' => '2 hours ago',
                'pinned' => false,
                'type' => 'team'
            ],
            [
                'id' => 5,
                'name' => 'Tech Support',
                'icon' => '🔧',
                'description' => 'IT issues and technical questions',
                'members_count' => 8,
                'unread_count' => 0,
                'online_count' => 2,
                'last_message' => 'POS system back online at all locations',
                'last_message_from' => 'Tech Team',
                'last_message_time' => '3 hours ago',
                'pinned' => false,
                'type' => 'support'
            ],
            [
                'id' => 6,
                'name' => 'Product Updates',
                'icon' => '📦',
                'description' => 'New products, pricing, and inventory updates',
                'members_count' => 34,
                'unread_count' => 1,
                'online_count' => 7,
                'last_message' => 'New JUUL2 devices arriving next week!',
                'last_message_from' => 'Operations',
                'last_message_time' => '4 hours ago',
                'pinned' => false,
                'type' => 'announcement'
            ]
        ];
    }

    /**
     * Get chat room messages
     */
    public static function getChatMessages($roomId = 1) {
        $messages = [
            1 => [ // All Staff
                [
                    'id' => 1,
                    'user' => 'Pearce Stephens',
                    'avatar' => 'PS',
                    'role' => 'Director',
                    'message' => 'Great work everyone on hitting our Q4 targets! 🎉',
                    'time' => '5 mins ago',
                    'timestamp' => time() - 300,
                    'reactions' => ['👍' => 12, '🎉' => 8, '❤️' => 5]
                ],
                [
                    'id' => 2,
                    'user' => 'Sarah Mitchell',
                    'avatar' => 'SM',
                    'role' => 'Store Manager',
                    'message' => 'Thanks! The Auckland team worked really hard this quarter.',
                    'time' => '3 mins ago',
                    'timestamp' => time() - 180,
                    'reactions' => ['👍' => 5]
                ],
                [
                    'id' => 3,
                    'user' => 'James Parker',
                    'avatar' => 'JP',
                    'role' => 'Store Manager',
                    'message' => 'Wellington team was on fire too! Proud of everyone.',
                    'time' => '2 mins ago',
                    'timestamp' => time() - 120,
                    'reactions' => ['🔥' => 7]
                ]
            ],
            2 => [ // Store Managers
                [
                    'id' => 4,
                    'user' => 'Sarah Mitchell',
                    'avatar' => 'SM',
                    'role' => 'Store Manager',
                    'message' => 'Can we schedule a meeting for next week to discuss inventory?',
                    'time' => '10 mins ago',
                    'timestamp' => time() - 600,
                    'reactions' => []
                ],
                [
                    'id' => 5,
                    'user' => 'Emma Wilson',
                    'avatar' => 'EW',
                    'role' => 'Store Manager',
                    'message' => 'Tuesday afternoon works for me',
                    'time' => '8 mins ago',
                    'timestamp' => time() - 480,
                    'reactions' => ['👍' => 2]
                ]
            ]
        ];

        return $messages[$roomId] ?? [];
    }

    /**
     * Get online users
     */
    public static function getOnlineUsers() {
        return [
            [
                'id' => 1,
                'name' => 'Sarah Mitchell',
                'avatar' => 'SM',
                'role' => 'Store Manager - Auckland',
                'status' => 'online',
                'status_message' => 'Available',
                'last_seen' => 'Now'
            ],
            [
                'id' => 2,
                'name' => 'James Parker',
                'avatar' => 'JP',
                'role' => 'Store Manager - Wellington',
                'status' => 'online',
                'status_message' => 'In a meeting',
                'last_seen' => 'Now'
            ],
            [
                'id' => 3,
                'name' => 'Emma Wilson',
                'avatar' => 'EW',
                'role' => 'Store Manager - Christchurch',
                'status' => 'away',
                'status_message' => 'On break',
                'last_seen' => '5 mins ago'
            ],
            [
                'id' => 4,
                'name' => 'Tech Support',
                'avatar' => 'TS',
                'role' => 'IT Support Team',
                'status' => 'online',
                'status_message' => 'Available for help',
                'last_seen' => 'Now'
            ],
            [
                'id' => 5,
                'name' => 'Mike Johnson',
                'avatar' => 'MJ',
                'role' => 'Sales Associate',
                'status' => 'busy',
                'status_message' => 'With customer',
                'last_seen' => 'Now'
            ]
        ];
    }

    /**
     * Get notification count
     */
    public static function getUnreadCount() {
        return count(array_filter(self::getNotifications(), function($n) {
            return !$n['read'];
        }));
    }

    /**
     * Get message count
     */
    public static function getUnreadMessageCount() {
        return count(array_filter(self::getMessages(), function($m) {
            return !$m['read'];
        }));
    }

    /**
     * Get total chat room unread count
     */
    public static function getTotalChatUnread() {
        return array_sum(array_column(self::getChatRooms(), 'unread_count'));
    }
}
