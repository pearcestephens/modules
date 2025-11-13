<?php
/**
 * CORE Module - Logout Handler
 *
 * Securely logs out the user and destroys session
 *
 * @package CIS\Core
 * @version 2.0.0 - Production Ready
 */

declare(strict_types=1);

// Load BASE bootstrap (includes middleware and session)
require_once dirname(__DIR__) . '/base/bootstrap.php';

// Load CORE bootstrap
require_once __DIR__ . '/bootstrap.php';

// Logout user (uses BASE logoutUser() function - includes audit logging)
logoutUser(true);

// Set success message
flash('success', 'You have been logged out successfully.');

// Redirect to login page
header('Location: /modules/core/login.php');
exit;
