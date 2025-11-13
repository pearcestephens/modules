<?php
/**
 * Consignments JSON API - Enterprise Endpoint
 *
 * Modern REST-like API for AJAX consignment operations.
 * Follows BASE module envelope design pattern with standardized responses.
 *
 * All requests must be POST with JSON payload.
 *
 * Actions:
 * - get_recent: Get recent consignments
 * - get_consignment: Get single consignment with items
 * - create_consignment: Create new consignment (requires CSRF)
 * - add_item: Add item to consignment (requires CSRF)
 * - update_status: Update consignment status (requires CSRF)
 * - search_consignments: Search consignments by ref_code/outlet
 * - get_stats: Get consignment statistics
 * - update_item_qty: Update item packed quantity (requires CSRF)
 *
 * Request format:
 * {
 *   "action": "get_recent",
 *   "data": { ...action-specific params... }
 * }
 *
 * Success Response Envelope (BASE Standard):
 * {
 *   "success": true,
 *   "message": "Operation successful",
 *   "timestamp": "2025-11-04 12:34:56",
 *   "request_id": "req_1730700896_a1b2c3d4",
 *   "data": { ...results... },
 *   "meta": {
 *     "duration_ms": 45.23,
 *     "memory_usage": "2.5 MB"
 *   }
 * }
 *
 * Error Response Envelope (BASE Standard):
 * {
 *   "success": false,
 *   "error": {
 *     "code": "VALIDATION_ERROR",
 *     "message": "Missing required field: email",
 *     "timestamp": "2025-11-04 12:34:56",
 *     "details": { ...additional context... }
 *   },
 *   "request_id": "req_1730700896_a1b2c3d4"
 * }
 *
 * @package CIS\Consignments\API
 * @version 2.0.0
 * @created 2025-10-31
 * @updated 2025-11-04 - Converted to BASE envelope pattern
 */

declare(strict_types=1);

// Bootstrap module
require_once __DIR__ . '/bootstrap.php';

// Load ConsignmentsAPI class (extends BaseAPI)
require_once __DIR__ . '/lib/ConsignmentsAPI.php';

use CIS\Consignments\Lib\ConsignmentsAPI;

// Create API instance and handle request
$api = new ConsignmentsAPI();
$api->handleRequest();
