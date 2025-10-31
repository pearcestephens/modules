<?php
declare(strict_types=1);

/**
 * Validation Helper
 *
 * Centralized validation functions for Purchase Orders module.
 * Implements Q31 3-tier validation with fuzzy matching and manual override.
 *
 * @package CIS\Consignments\Helpers
 * @version 1.0.0
 */

namespace CIS\Consignments\Helpers;

class ValidationHelper
{
    /**
     * Validate required fields in array
     *
     * @param array $data Data to validate
     * @param array $required Required field names
     * @return array Validation result ['valid' => bool, 'errors' => array]
     */
    public static function validateRequired(array $data, array $required): array
    {
        $errors = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[$field] = "Field '$field' is required";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool Valid
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate date format (YYYY-MM-DD)
     *
     * @param string $date Date string
     * @return bool Valid
     */
    public static function validateDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Validate UUID
     *
     * @param string $uuid UUID to validate
     * @return bool Valid
     */
    public static function validateUUID(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Validate purchase order data (Tier 1: Format validation)
     *
     * @param array $data PO data
     * @return array Validation result
     */
    public static function validatePOData(array $data): array
    {
        $errors = [];

        // Required fields
        $requiredResult = self::validateRequired($data, ['outlet_to', 'supplier_id', 'created_by']);
        if (!$requiredResult['valid']) {
            $errors = array_merge($errors, $requiredResult['errors']);
        }

        // UUID validation
        if (!empty($data['outlet_to']) && !self::validateUUID($data['outlet_to'])) {
            $errors['outlet_to'] = 'Invalid outlet UUID format';
        }

        if (!empty($data['supplier_id']) && !self::validateUUID($data['supplier_id'])) {
            $errors['supplier_id'] = 'Invalid supplier UUID format';
        }

        // Date validation
        if (!empty($data['expected_delivery_date']) && !self::validateDate($data['expected_delivery_date'])) {
            $errors['expected_delivery_date'] = 'Invalid date format (use YYYY-MM-DD)';
        }

        // Numeric validation
        if (isset($data['created_by']) && (!is_numeric($data['created_by']) || $data['created_by'] < 1)) {
            $errors['created_by'] = 'Invalid user ID';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'tier' => 1
        ];
    }

    /**
     * Validate line item data (Tier 1: Format validation)
     *
     * @param array $item Line item data
     * @return array Validation result
     */
    public static function validateLineItem(array $item): array
    {
        $errors = [];

        // Required fields
        $requiredResult = self::validateRequired($item, ['product_id', 'quantity']);
        if (!$requiredResult['valid']) {
            $errors = array_merge($errors, $requiredResult['errors']);
        }

        // Product ID (UUID)
        if (!empty($item['product_id']) && !self::validateUUID($item['product_id'])) {
            $errors['product_id'] = 'Invalid product UUID format';
        }

        // Quantity (positive integer)
        if (isset($item['quantity'])) {
            if (!is_numeric($item['quantity']) || $item['quantity'] < 1) {
                $errors['quantity'] = 'Quantity must be a positive integer';
            }
        }

        // Unit cost (non-negative)
        if (isset($item['unit_cost'])) {
            if (!is_numeric($item['unit_cost']) || $item['unit_cost'] < 0) {
                $errors['unit_cost'] = 'Unit cost must be a non-negative number';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'tier' => 1
        ];
    }

    /**
     * Validate product exists in database (Tier 2: Business logic validation)
     *
     * @param \PDO $pdo Database connection
     * @param string $productId Product UUID
     * @return array Validation result with product details
     */
    public static function validateProductExists(\PDO $pdo, string $productId): array
    {
        $stmt = $pdo->prepare("
            SELECT id, sku, name, supply_price, active
            FROM vend_products
            WHERE id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$product) {
            return [
                'valid' => false,
                'errors' => ['product_id' => 'Product not found in database'],
                'tier' => 2
            ];
        }

        if (!$product->active) {
            return [
                'valid' => false,
                'errors' => ['product_id' => 'Product is inactive'],
                'tier' => 2,
                'warning' => true // Soft error, can be overridden
            ];
        }

        return [
            'valid' => true,
            'product' => $product,
            'tier' => 2
        ];
    }

    /**
     * Validate supplier exists and is active (Tier 2: Business logic validation)
     *
     * @param \PDO $pdo Database connection
     * @param string $supplierId Supplier UUID
     * @return array Validation result
     */
    public static function validateSupplierExists(\PDO $pdo, string $supplierId): array
    {
        $stmt = $pdo->prepare("
            SELECT id, name, email, deleted_at
            FROM vend_suppliers
            WHERE id = ?
        ");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$supplier) {
            return [
                'valid' => false,
                'errors' => ['supplier_id' => 'Supplier not found in database'],
                'tier' => 2
            ];
        }

        if ($supplier->deleted_at !== null) {
            return [
                'valid' => false,
                'errors' => ['supplier_id' => 'Supplier is inactive'],
                'tier' => 2
            ];
        }

        return [
            'valid' => true,
            'supplier' => $supplier,
            'tier' => 2
        ];
    }

    /**
     * Validate outlet exists (Tier 2: Business logic validation)
     *
     * @param \PDO $pdo Database connection
     * @param string $outletId Outlet UUID
     * @return array Validation result
     */
    public static function validateOutletExists(\PDO $pdo, string $outletId): array
    {
        $stmt = $pdo->prepare("
            SELECT id, name, deleted_at
            FROM vend_outlets
            WHERE id = ?
        ");
        $stmt->execute([$outletId]);
        $outlet = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$outlet) {
            return [
                'valid' => false,
                'errors' => ['outlet_id' => 'Outlet not found in database'],
                'tier' => 2
            ];
        }

        if ($outlet->deleted_at !== null) {
            return [
                'valid' => false,
                'errors' => ['outlet_id' => 'Outlet is inactive'],
                'tier' => 2
            ];
        }

        return [
            'valid' => true,
            'outlet' => $outlet,
            'tier' => 2
        ];
    }

    /**
     * Fuzzy match product by SKU or name (Tier 3: Fuzzy matching with manual override)
     *
     * @param \PDO $pdo Database connection
     * @param string $search Search string (SKU or partial name)
     * @param int $limit Max results
     * @return array Matched products with confidence scores
     */
    public static function fuzzyMatchProduct(\PDO $pdo, string $search, int $limit = 5): array
    {
        // Clean search string
        $search = trim($search);
        $searchPattern = '%' . $search . '%';

        $stmt = $pdo->prepare("
            SELECT
                id,
                sku,
                name,
                supply_price,
                supplier_code,
                CASE
                    WHEN sku = :exact_search THEN 100
                    WHEN sku LIKE :search_pattern THEN 90
                    WHEN name LIKE :search_pattern THEN 70
                    WHEN supplier_code LIKE :search_pattern THEN 60
                    ELSE 50
                END AS confidence_score
            FROM vend_products
            WHERE (
                sku LIKE :search_pattern
                OR name LIKE :search_pattern
                OR supplier_code LIKE :search_pattern
            )
            AND active = 1
            ORDER BY confidence_score DESC, name ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':exact_search', $search);
        $stmt->bindValue(':search_pattern', $searchPattern);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'valid' => !empty($results),
            'matches' => $results,
            'requires_manual_selection' => count($results) > 1 || (isset($results[0]) && $results[0]['confidence_score'] < 90),
            'tier' => 3
        ];
    }

    /**
     * Sanitize string input
     *
     * @param string $input Input string
     * @return string Sanitized string
     */
    public static function sanitizeString(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize integer input
     *
     * @param mixed $input Input value
     * @return int|null Sanitized integer or null
     */
    public static function sanitizeInt($input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }

        return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : null;
    }

    /**
     * Sanitize float input
     *
     * @param mixed $input Input value
     * @return float|null Sanitized float or null
     */
    public static function sanitizeFloat($input): ?float
    {
        if ($input === null || $input === '') {
            return null;
        }

        return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : null;
    }

    /**
     * Validate state transition
     *
     * @param string $currentState Current PO state
     * @param string $newState Target state
     * @return array Validation result
     */
    public static function validateStateTransition(string $currentState, string $newState): array
    {
        $validTransitions = [
            'DRAFT' => ['OPEN', 'CANCELLED'],
            'OPEN' => ['PACKING', 'CANCELLED', 'DRAFT'],
            'PACKING' => ['PACKAGED', 'OPEN'],
            'PACKAGED' => ['SENT', 'PACKING'],
            'SENT' => ['RECEIVING', 'CANCELLED'],
            'RECEIVING' => ['PARTIAL', 'RECEIVED'],
            'PARTIAL' => ['RECEIVING', 'RECEIVED'],
            'RECEIVED' => ['CLOSED'],
            'CLOSED' => ['ARCHIVED'],
            'CANCELLED' => [],
            'ARCHIVED' => []
        ];

        if (!isset($validTransitions[$currentState])) {
            return [
                'valid' => false,
                'errors' => ['state' => "Invalid current state: $currentState"]
            ];
        }

        if (!in_array($newState, $validTransitions[$currentState])) {
            return [
                'valid' => false,
                'errors' => ['state' => "Cannot transition from $currentState to $newState"]
            ];
        }

        return ['valid' => true];
    }

    /**
     * Batch validate line items
     *
     * @param array $items Array of line items
     * @return array Validation summary
     */
    public static function batchValidateLineItems(array $items): array
    {
        $results = [
            'valid' => true,
            'total' => count($items),
            'passed' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($items as $index => $item) {
            $validation = self::validateLineItem($item);

            if ($validation['valid']) {
                $results['passed']++;
            } else {
                $results['failed']++;
                $results['valid'] = false;
                $results['errors'][$index] = $validation['errors'];
            }
        }

        return $results;
    }
}
