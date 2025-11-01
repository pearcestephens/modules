<?php
/**
 * VapeShed Database Connection for Payroll Module
 *
 * Uses base/Database.php vapeshed() connection for email queue functionality.
 *
 * @package Payroll
 * @version 2.0.0
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Lib;

// Load base Database class
require_once __DIR__ . '/../../../base/Database.php';

use CIS\Base\Database;

/**
 * Get VapeShed database connection (mysqli object)
 *
 * @return \mysqli mysqli object
 * @throws \RuntimeException If connection fails
 */
function getVapeShedConnection(): \mysqli
{
    return Database::vapeshed();
}
