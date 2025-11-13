<?php
/**
 * Authentication Controller
 *
 * Handles user login, logout, registration, and password reset.
 *
 * @package CIS\Core\Controllers
 * @version 2.0.0
 */

declare(strict_types=1);

namespace CIS\Core\Controllers;

require_once __DIR__ . '/../bootstrap.php';

class AuthController
{
    private $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * Show login page
     */
    public function showLogin(): void
    {
        require_guest();

        $flash = get_flash_message();
        render_view('auth/login', [
            'flash' => $flash,
            'page_title' => 'Login - CIS'
        ]);
    }

    /**
     * Handle login submission
     */
    public function login(): void
    {
        require_guest();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_with_message('/modules/core/public/login.php', 'Invalid request method', 'error');
            return;
        }

        // Validate CSRF token
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            redirect_with_message('/modules/core/public/login.php', 'Invalid security token', 'error');
            return;
        }

        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // Validate inputs
        if (empty($email) || empty($password)) {
            redirect_with_message('/modules/core/public/login.php', 'Email and password are required', 'error');
            return;
        }

        if (!is_valid_email($email)) {
            redirect_with_message('/modules/core/public/login.php', 'Invalid email format', 'error');
            return;
        }

        // Get user by email
        $user = get_user_by_email($email);

        if (!$user) {
            redirect_with_message('/modules/core/public/login.php', 'Invalid credentials', 'error');
            return;
        }

        // Verify password
        if (!verify_password($password, $user['password_hash'])) {
            redirect_with_message('/modules/core/public/login.php', 'Invalid credentials', 'error');
            return;
        }

        // Check if account is active
        if (isset($user['is_active']) && !$user['is_active']) {
            redirect_with_message('/modules/core/public/login.php', 'Account is inactive', 'error');
            return;
        }

        // Login successful - create session using BASE helper
        loginUser($user);

        // Update last login
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$user['id']]);

        // Set remember me cookie if requested
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (86400 * 30), '/', '', true, true); // 30 days

            // Store token in database (implement remember_tokens table)
            // $this->storeRememberToken($user['id'], $token);
        }

        // Log activity
        log_activity('user_login', [
            'user_id' => $user['id'],
            'email' => $email
        ]);

        // Redirect to dashboard
        redirect_with_message('/modules/core/public/index.php', 'Welcome back!', 'success');
    }

    /**
     * Show registration page
     */
    public function showRegister(): void
    {
        require_guest();

        $flash = get_flash_message();
        render_view('auth/register', [
            'flash' => $flash,
            'page_title' => 'Register - CIS'
        ]);
    }

    /**
     * Handle registration submission
     */
    public function register(): void
    {
        require_guest();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_with_message('/modules/core/public/register.php', 'Invalid request method', 'error');
            return;
        }

        // Validate CSRF token
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            redirect_with_message('/modules/core/public/register.php', 'Invalid security token', 'error');
            return;
        }

        $username = sanitize_input($_POST['username'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $firstName = sanitize_input($_POST['first_name'] ?? '');
        $lastName = sanitize_input($_POST['last_name'] ?? '');

        // Validate inputs
        if (empty($username) || empty($email) || empty($password)) {
            redirect_with_message('/modules/core/public/register.php', 'All fields are required', 'error');
            return;
        }

        if (!is_valid_username($username)) {
            redirect_with_message('/modules/core/public/register.php', 'Username must be 3-20 characters (letters, numbers, underscore only)', 'error');
            return;
        }

        if (!is_valid_email($email)) {
            redirect_with_message('/modules/core/public/register.php', 'Invalid email format', 'error');
            return;
        }

        if (strlen($password) < 8) {
            redirect_with_message('/modules/core/public/register.php', 'Password must be at least 8 characters', 'error');
            return;
        }

        if ($password !== $passwordConfirm) {
            redirect_with_message('/modules/core/public/register.php', 'Passwords do not match', 'error');
            return;
        }

        // Check if username exists
        if (get_user_by_username($username)) {
            redirect_with_message('/modules/core/public/register.php', 'Username already taken', 'error');
            return;
        }

        // Check if email exists
        if (get_user_by_email($email)) {
            redirect_with_message('/modules/core/public/register.php', 'Email already registered', 'error');
            return;
        }

        // Create user
        $passwordHash = hash_password($password);

        try {
            $stmt = $this->db->prepare('
                INSERT INTO users (username, email, password_hash, first_name, last_name, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName]);

            $userId = $this->db->lastInsertId();

            // Create default settings
            $stmt = $this->db->prepare('
                INSERT INTO user_settings (user_id, created_at)
                VALUES (?, NOW())
            ');
            $stmt->execute([$userId]);

            // Create default preferences
            $stmt = $this->db->prepare('
                INSERT INTO user_preferences (user_id, created_at)
                VALUES (?, NOW())
            ');
            $stmt->execute([$userId]);

            // Log activity
            log_activity('user_registration', [
                'user_id' => $userId,
                'username' => $username,
                'email' => $email
            ]);

            redirect_with_message('/modules/core/public/login.php', 'Registration successful! Please login.', 'success');

        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            redirect_with_message('/modules/core/public/register.php', 'Registration failed. Please try again.', 'error');
        }
    }

    /**
     * Handle logout
     */
    public function logout(): void
    {
        // Clear remember me cookie before logout
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            // TODO: Delete token from database
        }

        // Destroy session using BASE helper (includes audit logging)
        logoutUser();

        // Redirect with message (session already fresh from logoutUser)
        redirect_with_message('/modules/core/public/login.php', 'You have been logged out', 'success');
    }

    /**
     * Show forgot password page
     */
    public function showForgotPassword(): void
    {
        require_guest();

        $flash = get_flash_message();
        render_view('auth/forgot-password', [
            'flash' => $flash,
            'page_title' => 'Forgot Password - CIS'
        ]);
    }

    /**
     * Handle forgot password submission
     */
    public function forgotPassword(): void
    {
        require_guest();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_with_message('/modules/core/public/forgot-password.php', 'Invalid request method', 'error');
            return;
        }

        // Validate CSRF token
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            redirect_with_message('/modules/core/public/forgot-password.php', 'Invalid security token', 'error');
            return;
        }

        $email = sanitize_input($_POST['email'] ?? '');

        if (!is_valid_email($email)) {
            redirect_with_message('/modules/core/public/forgot-password.php', 'Invalid email format', 'error');
            return;
        }

        $user = get_user_by_email($email);

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            // Store reset token (implement password_resets table)
            $stmt = $this->db->prepare('
                INSERT INTO password_resets (user_id, token, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()
            ');
            $stmt->execute([$user['id'], $token, $expiry, $token, $expiry]);

            // Send reset email (implement email service)
            // sendPasswordResetEmail($user['email'], $token);

            log_activity('password_reset_requested', ['user_id' => $user['id']]);
        }

        // Always show success message (security best practice)
        redirect_with_message(
            '/modules/core/public/login.php',
            'If that email exists, a password reset link has been sent.',
            'success'
        );
    }
}
