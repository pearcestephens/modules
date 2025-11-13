<?php
/**
 * Notifications API Endpoints
 *
 * Routes:
 *   GET    /api/notifications              - Get user's notifications
 *   GET    /api/notifications/unread       - Get unread count
 *   POST   /api/notifications/:id/read     - Mark as read
 *   GET    /api/notifications/preferences  - Get user preferences
 *   POST   /api/notifications/preferences  - Save preferences
 *   POST   /api/notifications/trigger      - Admin: Trigger notification (testing)
 *
 * @package    CIS\API
 * @version    1.0.0
 */

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/NotificationEngine.php';

use CIS\Notifications\NotificationEngine;
use CIS\Base\Response;

// Initialize engine
$engine = new NotificationEngine();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $path);

// Determine endpoint
$endpoint = isset($parts[2]) ? $parts[2] : null;
$action = isset($parts[3]) ? $parts[3] : null;
$resourceId = isset($parts[3]) ? $parts[3] : null;

// Verify authentication
$userId = verifyAuth();

if (!$userId) {
    Response::error('Unauthorized', 401);
}

try {
    switch ($endpoint) {
        case 'notifications':
            handleNotificationsEndpoint($method, $action, $resourceId, $userId, $engine);
            break;
        default:
            Response::error('Endpoint not found', 404);
    }
} catch (\Exception $e) {
    \CIS\Base\Logger::getInstance()->error('API Error', [
        'endpoint' => $endpoint,
        'error' => $e->getMessage(),
    ]);
    Response::error($e->getMessage(), 400);
}

/**
 * Handle /api/notifications/* endpoints
 */
function handleNotificationsEndpoint($method, $action, $resourceId, $userId, $engine)
{
    if ($method === 'GET') {
        if ($action === 'unread') {
            // GET /api/notifications/unread
            $counts = $engine->getUnreadCount($userId);
            Response::success($counts);
        } elseif ($action === 'preferences') {
            // GET /api/notifications/preferences
            $prefs = $engine->getUserPreferences($userId);
            Response::success($prefs);
        } else {
            // GET /api/notifications
            $category = $_GET['category'] ?? null;
            $priority = $_GET['priority'] ?? null;
            $unreadOnly = $_GET['unread_only'] ?? false;
            $limit = (int) ($_GET['limit'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);

            // Validate limit
            if ($limit > 100) {
                $limit = 100;
            }

            $filters = [];
            if ($category) {
                $filters['category'] = $category;
            }
            if ($priority) {
                $filters['priority'] = $priority;
            }
            if ($unreadOnly) {
                $filters['unread_only'] = true;
            }

            $notifications = $engine->getNotifications($userId, $filters, $limit, $offset);
            Response::success([
                'notifications' => $notifications,
                'total' => count($notifications),
                'limit' => $limit,
                'offset' => $offset,
            ]);
        }
    } elseif ($method === 'POST') {
        // POST /api/notifications/:id/read
        if ($action && is_numeric($action)) {
            $notificationId = $action;
            if (!$engine->markAsRead($notificationId, $userId)) {
                Response::error('Failed to mark as read', 400);
            }
            Response::success(['message' => 'Marked as read']);
        } elseif ($action === 'preferences') {
            // POST /api/notifications/preferences
            $body = json_decode(file_get_contents('php://input'), true);
            if (!$body) {
                Response::error('Invalid JSON', 400);
            }

            if (!$engine->saveUserPreferences($userId, $body)) {
                Response::error('Failed to save preferences', 400);
            }

            Response::success(['message' => 'Preferences saved']);
        } elseif ($action === 'trigger' && isAdmin($userId)) {
            // POST /api/notifications/trigger (admin only for testing)
            $body = json_decode(file_get_contents('php://input'), true);
            if (!$body || !isset($body['category'], $body['event'], $body['target_user_id'])) {
                Response::error('Missing required fields: category, event, target_user_id', 400);
            }

            $body['user_id'] = $body['target_user_id'];
            unset($body['target_user_id']);

            try {
                $notificationId = $engine->trigger(
                    $body['category'],
                    $body['event'],
                    $body
                );
                Response::success(['notification_id' => $notificationId]);
            } catch (\Exception $e) {
                Response::error($e->getMessage(), 400);
            }
        } else {
            Response::error('Invalid endpoint', 404);
        }
    } else {
        Response::error('Method not allowed', 405);
    }
}

/**
 * Verify user authentication
 *
 * @return int|null User ID if authenticated, null otherwise
 */
function verifyAuth()
{
    // Check Bearer token or session
    $token = null;

    // Check Authorization header
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches);
        $token = $matches[1] ?? null;
    }

    // Check session
    session_start();
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }

    // TODO: Validate JWT token
    if ($token) {
        // Validate and return user_id from token
        // return \CIS\Auth\JWT::verify($token);
    }

    return null;
}

/**
 * Check if user is admin (for testing endpoints)
 *
 * @param int $userId User ID
 * @return bool True if admin
 */
function isAdmin($userId)
{
    // Check user role/permissions
    // TODO: Implement proper role check
    return true; // For now, allow all
}
