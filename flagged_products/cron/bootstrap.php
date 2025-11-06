<?php
/**
 * Cron Bootstrap - Flagged Products Module
 *
 * Initializes environment for cron jobs to run independently
 * Loads core CIS dependencies and module-specific components
 *
 * @package CIS\FlaggedProducts\Cron
 */

// Define paths
define('CIS_ROOT', realpath(__DIR__ . '/../../../'));
define('MODULE_ROOT', realpath(__DIR__ . '/..'));
define('CRON_ROOT', __DIR__);

// Load core CIS bootstrap
require_once CIS_ROOT . '/app.php';

// Load CIS services
require_once CIS_ROOT . '/assets/services/CISLogger.php';

// Load module components
require_once MODULE_ROOT . '/lib/Logger.php';
require_once MODULE_ROOT . '/models/FlaggedProductsRepository.php';

// Set error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('Pacific/Auckland');
