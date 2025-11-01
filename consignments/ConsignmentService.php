<?php
declare(strict_types=1);

/**
 * Legacy ConsignmentService Shim (BC Compatibility)
 *
 * Provides backwards compatibility for legacy code using:
 *   require_once 'ConsignmentService.php';
 *   $service = new ConsignmentService(...);
 *
 * This simply aliases to the canonical namespaced version.
 */

require_once __DIR__ . '/src/Services/ConsignmentService.php';
class_alias(\Consignments\Services\ConsignmentService::class, 'ConsignmentService');
