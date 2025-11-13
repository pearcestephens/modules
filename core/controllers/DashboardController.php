<?php
/**
 * Dashboard Controller
 *
 * Handles the main dashboard/home page for authenticated users.
 *
 * @package CIS\Core\Controllers
 * @version 2.0.0
 */

declare(strict_types=1);

namespace CIS\Core\Controllers;

require_once __DIR__ . '/../bootstrap.php';

class DashboardController
{
    private $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * Show dashboard/home page
     */
    public function index(): void
    {
        require_auth();

        $userId = auth_user_id();
        $user = auth_user();
        $flash = get_flash_message();

        // Get user statistics
        $stats = $this->getUserStats($userId);

        // Get recent activity
        $recentActivity = $this->getRecentActivity($userId, 10);

        // Get quick actions
        $quickActions = $this->getQuickActions();

        // Get notifications count
        $notificationsCount = $this->getUnreadNotificationsCount($userId);

        // Get messages count
        $messagesCount = $this->getUnreadMessagesCount($userId);

        render_view('dashboard/index', [
            'user' => $user,
            'flash' => $flash,
            'stats' => $stats,
            'recent_activity' => $recentActivity,
            'quick_actions' => $quickActions,
            'notifications_count' => $notificationsCount,
            'messages_count' => $messagesCount,
            'page_title' => 'Dashboard - CIS'
        ]);
    }

    /**
     * Get user statistics
     */
    private function getUserStats(int $userId): array
    {
        $stats = [
            'profile_completion' => 0,
            'total_logins' => 0,
            'account_age_days' => 0,
            'last_active' => null
        ];

        try {
            // Get user data
            $stmt = $this->db->prepare('
                SELECT
                    username, email, first_name, last_name, phone, bio,
                    avatar_url, created_at, last_login_at, last_seen_at
                FROM users
                WHERE id = ?
            ');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Calculate profile completion
                $fields = ['username', 'email', 'first_name', 'last_name', 'phone', 'bio', 'avatar_url'];
                $completed = 0;
                foreach ($fields as $field) {
                    if (!empty($user[$field])) {
                        $completed++;
                    }
                }
                $stats['profile_completion'] = round(($completed / count($fields)) * 100);

                // Account age
                $createdAt = new DateTime($user['created_at']);
                $now = new DateTime();
                $stats['account_age_days'] = $createdAt->diff($now)->days;

                // Last active
                $stats['last_active'] = $user['last_seen_at'] ?? $user['last_login_at'];
            }

            // Get login count (if you have login_history table)
            // $stmt = $this->db->prepare('SELECT COUNT(*) FROM login_history WHERE user_id = ?');
            // $stmt->execute([$userId]);
            // $stats['total_logins'] = $stmt->fetchColumn();

        } catch (Exception $e) {
            error_log('Error getting user stats: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(int $userId, int $limit = 10): array
    {
        $activity = [];

        try {
            // If you have activity_log table
            // $stmt = $this->db->prepare('
            //     SELECT action, details, created_at
            //     FROM activity_log
            //     WHERE user_id = ?
            //     ORDER BY created_at DESC
            //     LIMIT ?
            // ');
            // $stmt->execute([$userId, $limit]);
            // $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // For now, return sample activity
            $activity = [
                [
                    'action' => 'profile_updated',
                    'description' => 'Updated profile information',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                    'icon' => 'fas fa-user-edit',
                    'color' => 'primary'
                ],
                [
                    'action' => 'settings_changed',
                    'description' => 'Changed account settings',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'icon' => 'fas fa-cog',
                    'color' => 'info'
                ],
                [
                    'action' => 'user_login',
                    'description' => 'Logged in successfully',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'icon' => 'fas fa-sign-in-alt',
                    'color' => 'success'
                ]
            ];

        } catch (Exception $e) {
            error_log('Error getting recent activity: ' . $e->getMessage());
        }

        return $activity;
    }

    /**
     * Get quick actions
     */
    private function getQuickActions(): array
    {
        return [
            [
                'title' => 'Edit Profile',
                'description' => 'Update your profile information',
                'url' => '/modules/core/public/profile.php',
                'icon' => 'fas fa-user-edit',
                'color' => 'primary'
            ],
            [
                'title' => 'Settings',
                'description' => 'Manage your account settings',
                'url' => '/modules/core/public/settings.php',
                'icon' => 'fas fa-cog',
                'color' => 'secondary'
            ],
            [
                'title' => 'Security',
                'description' => 'Update security preferences',
                'url' => '/modules/core/public/security.php',
                'icon' => 'fas fa-shield-alt',
                'color' => 'danger'
            ],
            [
                'title' => 'Messages',
                'description' => 'Check your messages',
                'url' => '/modules/core/public/messages.php',
                'icon' => 'fas fa-envelope',
                'color' => 'success'
            ]
        ];
    }

    /**
     * Get unread notifications count
     */
    private function getUnreadNotificationsCount(int $userId): int
    {
        try {
            // If you have notifications table
            // $stmt = $this->db->prepare('
            //     SELECT COUNT(*) FROM notifications
            //     WHERE user_id = ? AND read_at IS NULL
            // ');
            // $stmt->execute([$userId]);
            // return (int) $stmt->fetchColumn();

            return 0; // Placeholder

        } catch (Exception $e) {
            error_log('Error getting notifications count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get unread messages count
     */
    private function getUnreadMessagesCount(int $userId): int
    {
        try {
            // If you have messages table
            // $stmt = $this->db->prepare('
            //     SELECT COUNT(*) FROM messages
            //     WHERE recipient_id = ? AND read_at IS NULL
            // ');
            // $stmt->execute([$userId]);
            // return (int) $stmt->fetchColumn();

            return 0; // Placeholder

        } catch (Exception $e) {
            error_log('Error getting messages count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get welcome message based on time of day
     */
    public static function getWelcomeMessage(): string
    {
        $hour = (int) date('H');

        if ($hour < 12) {
            return 'Good morning';
        } elseif ($hour < 18) {
            return 'Good afternoon';
        } else {
            return 'Good evening';
        }
    }
}
