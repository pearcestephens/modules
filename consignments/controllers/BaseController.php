<?php
/**
 * Consignments Base Controller
 *
 * ALL consignments pages MUST extend this controller.
 * Enforces authentication, base template inheritance, and design patterns.
 *
 * DESIGN PATTERN ENFORCEMENT:
 * - Pages cannot render without extending this controller
 * - Base template is automatically applied
 * - Authentication is automatically enforced
 * - CSRF tokens automatically available
 * - Database connection automatically available
 *
 * @package CIS\Consignments
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Consignments\Controllers;

use CIS\Base\Database;
use CIS\Base\Session;
use CIS\Base\SecurityMiddleware;
use CIS\Base\Response;

abstract class BaseController
{
    protected \PDO $db;
    protected array $viewData = [];
    protected bool $requireAuth = true;
    protected ?array $currentUser = null;

    /**
     * Constructor - Enforces security and setup
     */
    public function __construct()
    {
        // Ensure base is initialized
        Session::init();
        SecurityMiddleware::init();

        // Enforce authentication (unless explicitly disabled)
        if ($this->requireAuth) {
            $this->enforceAuth();
        }

        // Setup database
        $this->db = Database::pdo();

        // Load current user
        $this->currentUser = $this->getCurrentUser();

        // Set default view data (available to all views)
        $this->viewData = [
            'pageTitle' => 'Consignments',
            'moduleName' => 'consignments',
            'moduleUrl' => '/modules/consignments/',
            'currentUser' => $this->currentUser,
            'csrfToken' => SecurityMiddleware::generateToken(),
            'csrfField' => SecurityMiddleware::tokenField(),
            'breadcrumbs' => [],
            'pageCSS' => [],
            'pageJS' => [],
            'alerts' => $_SESSION['alerts'] ?? [],
        ];

        // Clear alerts after loading
        unset($_SESSION['alerts']);
    }

    /**
     * Enforce authentication - redirect to login if not authenticated
     */
    protected function enforceAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/modules/consignments/';
            Response::redirect('/login.php');
        }
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return !empty($_SESSION['userID']) || !empty($_SESSION['userID']);
    }

    /**
     * Get current user info
     */
    protected function getCurrentUser(): ?array
    {
        $userId = $_SESSION['userID'] ?? $_SESSION['userID'] ?? 0;
        if (!$userId) return null;

        $stmt = $this->db->prepare('
            SELECT user_id, username, email, role, outlet_id
            FROM users
            WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get current user ID
     */
    protected function getUserId(): int
    {
        return (int)($_SESSION['userID'] ?? $_SESSION['userID'] ?? 0);
    }

    /**
     * Check if user has role
     */
    protected function hasRole(string $role): bool
    {
        return ($this->currentUser['role'] ?? '') === $role;
    }

    /**
     * Require specific role or fail with 403
     */
    protected function requireRole(string $role): void
    {
        if (!$this->hasRole($role)) {
            http_response_code(403);
            $this->render('error', 'errors/403', [
                'message' => 'You do not have permission to access this page.',
            ]);
            exit;
        }
    }

    /**
     * Add alert message to session (shown on next page load)
     */
    protected function addAlert(string $type, string $message): void
    {
        if (!isset($_SESSION['alerts'])) {
            $_SESSION['alerts'] = [];
        }
        $_SESSION['alerts'][] = [
            'type' => $type, // success, info, warning, danger
            'message' => $message,
        ];
    }

    /**
     * Render page using base template
     *
     * THIS IS THE CORE METHOD - ALL PAGES GO THROUGH HERE
     *
     * @param string $layout Layout template name (dashboard, table, card, blank)
     * @param string $contentView View file path (relative to views/)
     * @param array $data Additional data for the view
     */
    protected function render(string $layout, string $contentView, array $data = []): void
    {
        // Merge data with defaults
        $this->viewData = array_merge($this->viewData, $data);

        // Extract variables for templates (makes them available as $varName)
        extract($this->viewData);

        // Load content view first (capture output)
        $contentFile = $this->getViewPath($contentView);

        if (!file_exists($contentFile)) {
            throw new \RuntimeException("View not found: {$contentView} (looked in: {$contentFile})");
        }

        // Capture content view output
        ob_start();
        require $contentFile;
        $pageContent = ob_get_clean();

        // Load layout template (will inject $pageContent)
        $layoutFile = $this->getLayoutPath($layout);

        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout} (looked in: {$layoutFile})");
        }

        require $layoutFile;
    }

    /**
     * Get full path to view file
     */
    private function getViewPath(string $view): string
    {
        // Remove .php extension if present
        $view = preg_replace('/\.php$/', '', $view);

        // Look in module views first
        $modulePath = __DIR__ . '/../../views/' . $view . '.php';
        if (file_exists($modulePath)) {
            return $modulePath;
        }

        // Fall back to absolute path if starts with /
        if ($view[0] === '/') {
            return $_SERVER['DOCUMENT_ROOT'] . $view . '.php';
        }

        return $modulePath; // Return module path even if doesn't exist (error will show)
    }

    /**
     * Get full path to layout file
     */
    private function getLayoutPath(string $layout): string
    {
        // Remove .php extension if present
        $layout = preg_replace('/\.php$/', '', $layout);

        // Base templates path
        return $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/' . $layout . '.php';
    }

    /**
     * Render JSON response (for AJAX endpoints within controllers)
     */
    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Render success JSON
     */
    protected function jsonSuccess($data = null, string $message = 'Success'): void
    {
        $this->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Render error JSON
     */
    protected function jsonError(string $message, int $code = 400): void
    {
        $this->json([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c'),
        ], $code);
    }

    /**
     * Redirect to URL
     */
    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    /**
     * Redirect back to referrer or fallback URL
     */
    protected function redirectBack(string $fallback = '/modules/consignments/'): void
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        Response::redirect($referrer);
    }
}
