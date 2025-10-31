<?php
/**
 * Employee Mapping Service
 * 
 * Manages mapping between Xero employees and Vend customers for payment allocation
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 1.0.0
 */

namespace CIS\Modules\StaffAccounts;

class EmployeeMappingService
{
    private $pdo;
    
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all employee mappings
     * 
     * @param array $filters Optional filters (status, search, etc.)
     * @return array
     */
    public function getAllMappings(array $filters = []): array
    {
        $sql = "SELECT * FROM employee_mappings WHERE 1=1";
        $params = [];
        
        // Filter by mapped/unmapped status
        if (isset($filters['status'])) {
            if ($filters['status'] === 'mapped') {
                $sql .= " AND vend_customer_id IS NOT NULL";
            } elseif ($filters['status'] === 'unmapped') {
                $sql .= " AND vend_customer_id IS NULL";
            }
        }
        
        // Search by name or email
        if (!empty($filters['search'])) {
            $sql .= " AND (employee_name LIKE :search OR employee_email LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Filter by confidence threshold
        if (isset($filters['min_confidence'])) {
            $sql .= " AND mapping_confidence >= :min_confidence";
            $params['min_confidence'] = (float)$filters['min_confidence'];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        // Pagination
        if (isset($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params['limit'] = (int)$filters['limit'];
            
            if (isset($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params['offset'] = (int)$filters['offset'];
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind parameters with proper types
        foreach ($params as $key => $value) {
            if ($key === 'limit' || $key === 'offset') {
                $stmt->bindValue(":$key", $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$key", $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get mapping by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getMappingById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM employee_mappings WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get mapping by Xero employee ID
     * 
     * @param string $xeroEmployeeId
     * @return array|null
     */
    public function getMappingByXeroId(string $xeroEmployeeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM employee_mappings WHERE xero_employee_id = ?
        ");
        $stmt->execute([$xeroEmployeeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get Vend customer ID for a Xero employee
     * 
     * @param string $xeroEmployeeId
     * @return string|null
     */
    public function getVendCustomerId(string $xeroEmployeeId): ?string
    {
        $mapping = $this->getMappingByXeroId($xeroEmployeeId);
        return $mapping['vend_customer_id'] ?? null;
    }
    
    /**
     * Check if employee is mapped
     * 
     * @param string $xeroEmployeeId
     * @return bool
     */
    public function isMapped(string $xeroEmployeeId): bool
    {
        $vendId = $this->getVendCustomerId($xeroEmployeeId);
        return !empty($vendId);
    }
    
    /**
     * Create new mapping
     * 
     * @param array $data
     * @return array Success/error response with created mapping
     */
    public function createMapping(array $data): array
    {
        // Validate required fields
        $required = ['xero_employee_id', 'employee_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: $field"
                ];
            }
        }
        
        // Check for duplicate
        $existing = $this->getMappingByXeroId($data['xero_employee_id']);
        if ($existing) {
            return [
                'success' => false,
                'error' => 'Employee already mapped',
                'existing_mapping' => $existing
            ];
        }
        
        // Prepare insert
        $sql = "
            INSERT INTO employee_mappings (
                xero_employee_id,
                employee_name,
                employee_email,
                vend_customer_id,
                vend_customer_name,
                mapping_confidence,
                mapped_by,
                mapped_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['xero_employee_id'],
            $data['employee_name'],
            $data['employee_email'] ?? null,
            $data['vend_customer_id'] ?? null,
            $data['vend_customer_name'] ?? null,
            $data['mapping_confidence'] ?? 1.00,
            $data['mapped_by'] ?? 'manual',
            $data['mapped_by_user_id'] ?? null
        ]);
        
        $mappingId = $this->pdo->lastInsertId();
        $created = $this->getMappingById((int)$mappingId);
        
        return [
            'success' => true,
            'mapping' => $created,
            'message' => 'Mapping created successfully'
        ];
    }
    
    /**
     * Update existing mapping
     * 
     * @param int $id
     * @param array $data
     * @return array Success/error response
     */
    public function updateMapping(int $id, array $data): array
    {
        $existing = $this->getMappingById($id);
        if (!$existing) {
            return [
                'success' => false,
                'error' => 'Mapping not found'
            ];
        }
        
        // Build update query dynamically
        $allowedFields = [
            'employee_name',
            'employee_email',
            'vend_customer_id',
            'vend_customer_name',
            'mapping_confidence',
            'mapped_by',
            'mapped_by_user_id'
        ];
        
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return [
                'success' => false,
                'error' => 'No valid fields to update'
            ];
        }
        
        // Add ID to params
        $params[] = $id;
        
        $sql = "UPDATE employee_mappings SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $updated = $this->getMappingById($id);
        
        return [
            'success' => true,
            'mapping' => $updated,
            'message' => 'Mapping updated successfully'
        ];
    }
    
    /**
     * Delete mapping
     * 
     * @param int $id
     * @return array Success/error response
     */
    public function deleteMapping(int $id): array
    {
        $existing = $this->getMappingById($id);
        if (!$existing) {
            return [
                'success' => false,
                'error' => 'Mapping not found'
            ];
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM employee_mappings WHERE id = ?");
        $stmt->execute([$id]);
        
        return [
            'success' => true,
            'message' => 'Mapping deleted successfully',
            'deleted_mapping' => $existing
        ];
    }
    
    /**
     * Get unmapped employees from Xero payroll data
     * 
     * @return array
     */
    public function getUnmappedEmployees(): array
    {
        // Get distinct employees from xero_payroll_deductions who don't have mappings
        // Note: xero_payroll_deductions only has employee_name, not employee_email
        $sql = "
            SELECT DISTINCT
                xd.xero_employee_id,
                xd.employee_name,
                COUNT(xd.id) as pending_deductions,
                SUM(xd.amount) as total_amount
            FROM xero_payroll_deductions xd
            LEFT JOIN employee_mappings em ON xd.xero_employee_id = em.xero_employee_id
            WHERE em.id IS NULL
            AND xd.allocation_status = 'pending'
            GROUP BY xd.xero_employee_id, xd.employee_name
            ORDER BY total_amount DESC
        ";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Auto-match employees to Vend customers
     * 
     * @return array Match results with confidence scores
     */
    public function autoMatchEmployees(): array
    {
        $unmapped = $this->getUnmappedEmployees();
        $matches = [];
        
        foreach ($unmapped as $employee) {
            $match = $this->findBestVendMatch($employee);
            if ($match) {
                $matches[] = array_merge($employee, $match);
            }
        }
        
        return $matches;
    }
    
    /**
     * Find best Vend customer match for employee
     * 
     * @param array $employee Employee data
     * @return array|null Match data with confidence score
     */
    private function findBestVendMatch(array $employee): ?array
    {
        // Strategy 1: Exact name match (95% confidence)
        // Note: vend_customers has first_name, last_name (not customer_name)
        $stmt = $this->pdo->prepare("
            SELECT 
                id, 
                customer_code, 
                CONCAT(first_name, ' ', last_name) as customer_name,
                email
            FROM vend_customers
            WHERE CONCAT(first_name, ' ', last_name) = ?
            LIMIT 1
        ");
        $stmt->execute([$employee['employee_name']]);
        $match = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($match) {
            return [
                'vend_customer_id' => $match['id'],
                'vend_customer_name' => $match['customer_name'],
                'mapping_confidence' => 0.95,
                'matched_by' => 'exact_name'
            ];
        }
        
        // Strategy 2: Fuzzy name match (80% confidence)
        // Split name and try partial matches
        $nameParts = explode(' ', $employee['employee_name']);
        if (count($nameParts) >= 2) {
            $firstName = $nameParts[0];
            $lastName = end($nameParts);
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    id, 
                    customer_code, 
                    CONCAT(first_name, ' ', last_name) as customer_name,
                    email
                FROM vend_customers
                WHERE first_name LIKE ? OR last_name LIKE ?
                   OR CONCAT(first_name, ' ', last_name) LIKE ?
                LIMIT 1
            ");
            $stmt->execute([
                "%{$firstName}%",
                "%{$lastName}%",
                "%{$firstName}%{$lastName}%"
            ]);
            $match = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($match) {
                return [
                    'vend_customer_id' => $match['id'],
                    'vend_customer_name' => $match['customer_name'],
                    'mapping_confidence' => 0.80,
                    'matched_by' => 'fuzzy_name'
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Get mapping statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        $stats = [];
        
        // Total mappings
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM employee_mappings");
        $stats['total_mappings'] = (int)$stmt->fetchColumn();
        
        // Mapped (with Vend customer)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM employee_mappings WHERE vend_customer_id IS NOT NULL");
        $stats['mapped_count'] = (int)$stmt->fetchColumn();
        
        // Unmapped
        $stats['unmapped_count'] = $stats['total_mappings'] - $stats['mapped_count'];
        
        // Unmapped employees with pending deductions
        $stmt = $this->pdo->query("
            SELECT COUNT(DISTINCT xero_employee_id)
            FROM xero_payroll_deductions
            WHERE allocation_status = 'pending'
            AND xero_employee_id NOT IN (SELECT xero_employee_id FROM employee_mappings)
        ");
        $stats['unmapped_with_pending'] = (int)$stmt->fetchColumn();
        
        // Average confidence
        $stmt = $this->pdo->query("
            SELECT AVG(mapping_confidence)
            FROM employee_mappings
            WHERE vend_customer_id IS NOT NULL
        ");
        $stats['avg_confidence'] = round((float)$stmt->fetchColumn(), 2);
        
        // Blocked amount
        $stmt = $this->pdo->query("
            SELECT SUM(xd.amount)
            FROM xero_payroll_deductions xd
            LEFT JOIN employee_mappings em ON xd.xero_employee_id = em.xero_employee_id
            WHERE xd.allocation_status = 'pending'
            AND em.id IS NULL
        ");
        $stats['blocked_amount'] = round((float)$stmt->fetchColumn(), 2);
        
        return $stats;
    }

    /**
     * Get auto-match suggestions with confidence scores
     * 
     * @param int $limit Maximum number of suggestions to return
     * @param float $minConfidence Minimum confidence threshold (0.0 - 1.0)
     * @return array
     */
    public function getAutoMatchSuggestions(int $limit = 50, float $minConfidence = 0.6): array
    {
        $sql = "
            SELECT 
                em.id as mapping_id,
                em.xero_employee_id,
                em.employee_name,
                em.employee_email,
                em.auto_match_customer_id,
                em.mapping_confidence,
                em.match_reasoning,
                em.created_at,
                
                -- Vend customer details
                vc.name as customer_name,
                vc.email as customer_email,
                vc.phone as customer_phone,
                vc.customer_code,
                vc.contact_first_name,
                vc.contact_last_name,
                
                -- Calculate blocked amount for this employee
                COALESCE(SUM(xd.amount), 0) as blocked_amount,
                COUNT(xd.id) as deduction_count
                
            FROM employee_mappings em
            LEFT JOIN vend_customers vc ON em.auto_match_customer_id = vc.id
            LEFT JOIN xero_payroll_deductions xd ON em.xero_employee_id = xd.xero_employee_id 
                AND xd.allocation_status = 'pending'
            
            WHERE em.status = 'auto_matched' 
            AND em.mapping_confidence >= :min_confidence
            AND em.vend_customer_id IS NULL  -- Not yet approved
            
            GROUP BY em.id, em.xero_employee_id, em.employee_name, em.employee_email,
                     em.auto_match_customer_id, em.mapping_confidence, em.match_reasoning,
                     em.created_at, vc.name, vc.email, vc.phone, vc.customer_code,
                     vc.contact_first_name, vc.contact_last_name
            
            ORDER BY em.mapping_confidence DESC, blocked_amount DESC
            LIMIT :limit
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':min_confidence', $minConfidence, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $suggestions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Add additional match analysis for each suggestion
        foreach ($suggestions as &$suggestion) {
            $suggestion['confidence_level'] = $this->getConfidenceLevel($suggestion['mapping_confidence']);
            $suggestion['match_details'] = $this->parseMatchReasoning($suggestion['match_reasoning']);
            $suggestion['risk_factors'] = $this->analyzeMatchRisks($suggestion);
        }
        
        return $suggestions;
    }

    /**
     * Approve an auto-match suggestion
     * 
     * @param int $mappingId The mapping ID to approve
     * @param string $approvedBy User who approved the match
     * @param string $notes Optional approval notes
     * @return bool
     */
    public function approveAutoMatch(int $mappingId, string $approvedBy, string $notes = ''): bool
    {
        try {
            $this->pdo->beginTransaction();
            
            // Get the mapping details
            $stmt = $this->pdo->prepare("
                SELECT xero_employee_id, auto_match_customer_id, mapping_confidence 
                FROM employee_mappings 
                WHERE id = :id AND status = 'auto_matched'
            ");
            $stmt->bindValue(':id', $mappingId, \PDO::PARAM_INT);
            $stmt->execute();
            $mapping = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$mapping) {
                throw new \Exception('Auto-match suggestion not found or already processed');
            }
            
            // Update the mapping to approved status
            $stmt = $this->pdo->prepare("
                UPDATE employee_mappings 
                SET vend_customer_id = :customer_id,
                    status = 'mapped',
                    approved_by = :approved_by,
                    approved_at = NOW(),
                    approval_notes = :notes,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->bindValue(':customer_id', $mapping['auto_match_customer_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':approved_by', $approvedBy, \PDO::PARAM_STR);
            $stmt->bindValue(':notes', $notes, \PDO::PARAM_STR);
            $stmt->bindValue(':id', $mappingId, \PDO::PARAM_INT);
            $stmt->execute();
            
            // Log the approval action
            $this->logMappingAction($mappingId, 'auto_match_approved', [
                'customer_id' => $mapping['auto_match_customer_id'],
                'confidence' => $mapping['mapping_confidence'],
                'approved_by' => $approvedBy,
                'notes' => $notes
            ]);
            
            $this->pdo->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log('Auto-match approval failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject an auto-match suggestion
     * 
     * @param int $mappingId The mapping ID to reject
     * @param string $rejectedBy User who rejected the match
     * @param string $reason Reason for rejection
     * @return bool
     */
    public function rejectAutoMatch(int $mappingId, string $rejectedBy, string $reason): bool
    {
        try {
            $this->pdo->beginTransaction();
            
            // Update the mapping to rejected status
            $stmt = $this->pdo->prepare("
                UPDATE employee_mappings 
                SET status = 'auto_match_rejected',
                    auto_match_customer_id = NULL,
                    mapping_confidence = NULL,
                    match_reasoning = NULL,
                    rejected_by = :rejected_by,
                    rejected_at = NOW(),
                    rejection_reason = :reason,
                    updated_at = NOW()
                WHERE id = :id AND status = 'auto_matched'
            ");
            
            $stmt->bindValue(':rejected_by', $rejectedBy, \PDO::PARAM_STR);
            $stmt->bindValue(':reason', $reason, \PDO::PARAM_STR);
            $stmt->bindValue(':id', $mappingId, \PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                throw new \Exception('Auto-match suggestion not found or already processed');
            }
            
            // Log the rejection action
            $this->logMappingAction($mappingId, 'auto_match_rejected', [
                'rejected_by' => $rejectedBy,
                'reason' => $reason
            ]);
            
            $this->pdo->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log('Auto-match rejection failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Bulk approve multiple auto-match suggestions
     * 
     * @param array $mappingIds Array of mapping IDs to approve
     * @param string $approvedBy User who approved the matches
     * @param string $notes Optional approval notes
     * @return array Results with success/failure for each ID
     */
    public function bulkApproveAutoMatches(array $mappingIds, string $approvedBy, string $notes = ''): array
    {
        $results = [];
        
        foreach ($mappingIds as $mappingId) {
            $results[$mappingId] = $this->approveAutoMatch($mappingId, $approvedBy, $notes);
        }
        
        return $results;
    }

    /**
     * Get confidence level description
     * 
     * @param float $confidence Confidence score (0.0 - 1.0)
     * @return array
     */
    private function getConfidenceLevel(float $confidence): array
    {
        if ($confidence >= 0.9) {
            return [
                'level' => 'Very High',
                'color' => 'success',
                'description' => 'Excellent match - highly recommended for approval'
            ];
        } elseif ($confidence >= 0.8) {
            return [
                'level' => 'High',
                'color' => 'info',
                'description' => 'Good match - recommended for approval'
            ];
        } elseif ($confidence >= 0.7) {
            return [
                'level' => 'Medium',
                'color' => 'warning',
                'description' => 'Reasonable match - review carefully'
            ];
        } else {
            return [
                'level' => 'Low',
                'color' => 'danger',
                'description' => 'Uncertain match - manual review required'
            ];
        }
    }

    /**
     * Parse match reasoning JSON into structured data
     * 
     * @param string $reasoning JSON-encoded reasoning
     * @return array
     */
    private function parseMatchReasoning(?string $reasoning): array
    {
        if (empty($reasoning)) {
            return [];
        }
        
        $decoded = json_decode($reasoning, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['raw' => $reasoning];
        }
        
        return $decoded;
    }

    /**
     * Analyze potential risks with an auto-match
     * 
     * @param array $suggestion Auto-match suggestion data
     * @return array
     */
    private function analyzeMatchRisks(array $suggestion): array
    {
        $risks = [];
        
        // Low confidence risk
        if ($suggestion['mapping_confidence'] < 0.8) {
            $risks[] = [
                'type' => 'low_confidence',
                'level' => 'warning',
                'message' => 'Confidence score below 80% - verify match accuracy'
            ];
        }
        
        // High value risk
        if ($suggestion['blocked_amount'] > 1000) {
            $risks[] = [
                'type' => 'high_value',
                'level' => 'info',
                'message' => 'High blocked amount - significant financial impact'
            ];
        }
        
        // Email mismatch risk
        if (!empty($suggestion['employee_email']) && !empty($suggestion['customer_email'])) {
            if (strtolower($suggestion['employee_email']) !== strtolower($suggestion['customer_email'])) {
                $risks[] = [
                    'type' => 'email_mismatch',
                    'level' => 'warning',
                    'message' => 'Employee and customer emails do not match'
                ];
            }
        }
        
        return $risks;
    }

    /**
     * Search for customers with advanced filtering
     * 
     * @param string $query Search query (name, email, phone, code)
     * @param array $filters Additional filters (store, created_date, etc.)
     * @param int $limit Maximum results to return
     * @param int $offset Pagination offset
     * @return array
     */
    public function searchCustomers(string $query = '', array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $whereConditions = [];
        $params = [];
        
        // Base search query across multiple fields
        if (!empty($query)) {
            $whereConditions[] = "(
                vc.name LIKE :query OR 
                vc.email LIKE :query OR 
                vc.phone LIKE :query OR 
                vc.customer_code LIKE :query OR
                vc.contact_first_name LIKE :query OR
                vc.contact_last_name LIKE :query
            )";
            $params[':query'] = '%' . $query . '%';
        }
        
        // Store filter
        if (!empty($filters['store_id'])) {
            $whereConditions[] = "vc.outlet_id = :store_id";
            $params[':store_id'] = $filters['store_id'];
        }
        
        // Email filter
        if (!empty($filters['has_email'])) {
            if ($filters['has_email'] === 'yes') {
                $whereConditions[] = "vc.email IS NOT NULL AND vc.email != ''";
            } else {
                $whereConditions[] = "(vc.email IS NULL OR vc.email = '')";
            }
        }
        
        // Customer group filter
        if (!empty($filters['customer_group'])) {
            $whereConditions[] = "vc.customer_group_id = :customer_group";
            $params[':customer_group'] = $filters['customer_group'];
        }
        
        // Date range filter
        if (!empty($filters['created_from'])) {
            $whereConditions[] = "DATE(vc.created_at) >= :created_from";
            $params[':created_from'] = $filters['created_from'];
        }
        if (!empty($filters['created_to'])) {
            $whereConditions[] = "DATE(vc.created_at) <= :created_to";
            $params[':created_to'] = $filters['created_to'];
        }
        
        // Exclude already mapped customers
        if (!empty($filters['exclude_mapped'])) {
            $whereConditions[] = "vc.id NOT IN (
                SELECT DISTINCT vend_customer_id 
                FROM employee_mappings 
                WHERE vend_customer_id IS NOT NULL 
                AND status = 'mapped'
            )";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "
            SELECT 
                vc.id,
                vc.name,
                vc.email,
                vc.phone,
                vc.customer_code,
                vc.contact_first_name,
                vc.contact_last_name,
                vc.company_name,
                vc.outlet_id,
                vc.customer_group_id,
                vc.created_at,
                vc.updated_at,
                
                -- Get outlet info
                vo.name as outlet_name,
                
                -- Check if already mapped
                CASE 
                    WHEN em.id IS NOT NULL THEN 'mapped'
                    ELSE 'available'
                END as mapping_status,
                
                -- Get mapping info if exists
                em.employee_name as mapped_employee_name,
                em.created_at as mapped_at,
                
                -- Purchase stats
                COALESCE(purchase_stats.total_purchases, 0) as total_purchases,
                COALESCE(purchase_stats.total_amount, 0) as total_amount,
                COALESCE(purchase_stats.last_purchase, NULL) as last_purchase_date
                
            FROM vend_customers vc
            LEFT JOIN vend_outlets vo ON vc.outlet_id = vo.id
            LEFT JOIN employee_mappings em ON vc.id = em.vend_customer_id AND em.status = 'mapped'
            LEFT JOIN (
                SELECT 
                    customer_id,
                    COUNT(*) as total_purchases,
                    SUM(total_price) as total_amount,
                    MAX(sale_date) as last_purchase
                FROM vend_sales 
                GROUP BY customer_id
            ) purchase_stats ON vc.id = purchase_stats.customer_id
            
            {$whereClause}
            
            ORDER BY 
                CASE WHEN :query_sort != '' THEN
                    CASE 
                        WHEN vc.name LIKE :query_sort THEN 1
                        WHEN vc.email LIKE :query_sort THEN 2
                        WHEN vc.customer_code LIKE :query_sort THEN 3
                        ELSE 4
                    END
                ELSE 1 END,
                vc.created_at DESC
                
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        // Bind pagination and sorting
        $stmt->bindValue(':query_sort', !empty($query) ? '%' . $query . '%' : '', \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        
        $stmt->execute();
        $customers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $countSql = "
            SELECT COUNT(DISTINCT vc.id) as total
            FROM vend_customers vc
            LEFT JOIN employee_mappings em ON vc.id = em.vend_customer_id AND em.status = 'mapped'
            {$whereClause}
        ";
        
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];
        
        return [
            'customers' => $customers,
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ];
    }

    /**
     * Get detailed customer information for mapping preview
     * 
     * @param int $customerId Customer ID to get details for
     * @return array|null
     */
    public function getCustomerDetails(int $customerId): ?array
    {
        $sql = "
            SELECT 
                vc.*,
                vo.name as outlet_name,
                vo.physical_address as outlet_address,
                
                -- Purchase statistics
                COALESCE(purchase_stats.total_purchases, 0) as total_purchases,
                COALESCE(purchase_stats.total_amount, 0) as total_amount,
                COALESCE(purchase_stats.first_purchase, NULL) as first_purchase_date,
                COALESCE(purchase_stats.last_purchase, NULL) as last_purchase_date,
                COALESCE(purchase_stats.avg_order_value, 0) as avg_order_value,
                
                -- Recent purchases
                recent_purchases.recent_items,
                
                -- Mapping status
                CASE 
                    WHEN em.id IS NOT NULL THEN 'mapped'
                    ELSE 'available'
                END as mapping_status,
                em.employee_name as mapped_employee_name,
                em.created_at as mapped_at,
                em.created_by as mapped_by
                
            FROM vend_customers vc
            LEFT JOIN vend_outlets vo ON vc.outlet_id = vo.id
            LEFT JOIN employee_mappings em ON vc.id = em.vend_customer_id AND em.status = 'mapped'
            LEFT JOIN (
                SELECT 
                    customer_id,
                    COUNT(*) as total_purchases,
                    SUM(total_price) as total_amount,
                    MIN(sale_date) as first_purchase,
                    MAX(sale_date) as last_purchase,
                    AVG(total_price) as avg_order_value
                FROM vend_sales 
                GROUP BY customer_id
            ) purchase_stats ON vc.id = purchase_stats.customer_id
            LEFT JOIN (
                SELECT 
                    customer_id,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'date', sale_date,
                            'amount', total_price,
                            'items', COALESCE(line_items, 0)
                        )
                    ) as recent_items
                FROM (
                    SELECT customer_id, sale_date, total_price, line_items
                    FROM vend_sales 
                    ORDER BY sale_date DESC 
                    LIMIT 10
                ) recent
                GROUP BY customer_id
            ) recent_purchases ON vc.id = recent_purchases.customer_id
            
            WHERE vc.id = :customer_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':customer_id', $customerId, \PDO::PARAM_INT);
        $stmt->execute();
        
        $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($customer && !empty($customer['recent_items'])) {
            $customer['recent_purchases'] = json_decode($customer['recent_items'], true);
            unset($customer['recent_items']);
        }
        
        return $customer ?: null;
    }

    /**
     * Create manual employee-customer mapping
     * 
     * @param int $xeroEmployeeId Xero employee ID
     * @param int $vendCustomerId Vend customer ID
     * @param string $createdBy User who created the mapping
     * @param string $notes Optional mapping notes
     * @param array $verification Verification data (email check, etc.)
     * @return bool
     */
    public function createManualMapping(
        int $xeroEmployeeId, 
        int $vendCustomerId, 
        string $createdBy, 
        string $notes = '', 
        array $verification = []
    ): bool {
        try {
            $this->pdo->beginTransaction();
            
            // Check if employee already has a mapping
            $existingStmt = $this->pdo->prepare("
                SELECT id, vend_customer_id 
                FROM employee_mappings 
                WHERE xero_employee_id = :employee_id 
                AND status = 'mapped'
            ");
            $existingStmt->bindValue(':employee_id', $xeroEmployeeId, \PDO::PARAM_INT);
            $existingStmt->execute();
            $existing = $existingStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing) {
                throw new \Exception('Employee already has an active mapping to customer ID: ' . $existing['vend_customer_id']);
            }
            
            // Check if customer is already mapped
            $customerStmt = $this->pdo->prepare("
                SELECT id, employee_name 
                FROM employee_mappings 
                WHERE vend_customer_id = :customer_id 
                AND status = 'mapped'
            ");
            $customerStmt->bindValue(':customer_id', $vendCustomerId, \PDO::PARAM_INT);
            $customerStmt->execute();
            $customerMapping = $customerStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($customerMapping) {
                throw new \Exception('Customer already mapped to employee: ' . $customerMapping['employee_name']);
            }
            
            // Get employee details
            $employeeStmt = $this->pdo->prepare("
                SELECT employee_name, employee_email 
                FROM employee_mappings 
                WHERE xero_employee_id = :employee_id 
                LIMIT 1
            ");
            $employeeStmt->bindValue(':employee_id', $xeroEmployeeId, \PDO::PARAM_INT);
            $employeeStmt->execute();
            $employee = $employeeStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$employee) {
                throw new \Exception('Employee not found in system');
            }
            
            // Create or update the mapping
            $mappingStmt = $this->pdo->prepare("
                INSERT INTO employee_mappings (
                    xero_employee_id, 
                    employee_name, 
                    employee_email, 
                    vend_customer_id, 
                    status, 
                    mapping_type,
                    created_by, 
                    created_at, 
                    updated_at, 
                    manual_notes,
                    verification_data
                ) VALUES (
                    :employee_id, 
                    :employee_name, 
                    :employee_email, 
                    :customer_id, 
                    'mapped', 
                    'manual',
                    :created_by, 
                    NOW(), 
                    NOW(), 
                    :notes,
                    :verification
                )
                ON DUPLICATE KEY UPDATE
                    vend_customer_id = :customer_id,
                    status = 'mapped',
                    mapping_type = 'manual',
                    created_by = :created_by,
                    updated_at = NOW(),
                    manual_notes = :notes,
                    verification_data = :verification
            ");
            
            $mappingStmt->bindValue(':employee_id', $xeroEmployeeId, \PDO::PARAM_INT);
            $mappingStmt->bindValue(':employee_name', $employee['employee_name'], \PDO::PARAM_STR);
            $mappingStmt->bindValue(':employee_email', $employee['employee_email'], \PDO::PARAM_STR);
            $mappingStmt->bindValue(':customer_id', $vendCustomerId, \PDO::PARAM_INT);
            $mappingStmt->bindValue(':created_by', $createdBy, \PDO::PARAM_STR);
            $mappingStmt->bindValue(':notes', $notes, \PDO::PARAM_STR);
            $mappingStmt->bindValue(':verification', json_encode($verification), \PDO::PARAM_STR);
            
            $mappingStmt->execute();
            $mappingId = $this->pdo->lastInsertId();
            
            // Log the manual mapping action
            $this->logMappingAction($mappingId, 'manual_mapping_created', [
                'customer_id' => $vendCustomerId,
                'created_by' => $createdBy,
                'notes' => $notes,
                'verification' => $verification
            ]);
            
            $this->pdo->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log('Manual mapping creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get available stores/outlets for filtering
     * 
     * @return array
     */
    public function getAvailableStores(): array
    {
        $sql = "
            SELECT 
                vo.id,
                vo.name,
                vo.physical_address,
                COUNT(vc.id) as customer_count
            FROM vend_outlets vo
            LEFT JOIN vend_customers vc ON vo.id = vc.outlet_id
            GROUP BY vo.id, vo.name, vo.physical_address
            ORDER BY vo.name
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get customer groups for filtering
     * 
     * @return array
     */
    public function getCustomerGroups(): array
    {
        $sql = "
            SELECT 
                id,
                name,
                COUNT(*) as customer_count
            FROM vend_customer_groups vcg
            WHERE EXISTS (
                SELECT 1 FROM vend_customers vc 
                WHERE vc.customer_group_id = vcg.id
            )
            GROUP BY id, name
            ORDER BY name
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Validate manual mapping before creation
     * 
     * @param int $xeroEmployeeId Employee ID
     * @param int $vendCustomerId Customer ID
     * @return array Validation results
     */
    public function validateManualMapping(int $xeroEmployeeId, int $vendCustomerId): array
    {
        $validation = [
            'valid' => true,
            'warnings' => [],
            'errors' => [],
            'suggestions' => []
        ];
        
        // Check employee exists and get details
        $employeeStmt = $this->pdo->prepare("
            SELECT employee_name, employee_email, vend_customer_id, status 
            FROM employee_mappings 
            WHERE xero_employee_id = :employee_id
        ");
        $employeeStmt->bindValue(':employee_id', $xeroEmployeeId, \PDO::PARAM_INT);
        $employeeStmt->execute();
        $employee = $employeeStmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$employee) {
            $validation['errors'][] = 'Employee not found in system';
            $validation['valid'] = false;
            return $validation;
        }
        
        if ($employee['status'] === 'mapped' && !empty($employee['vend_customer_id'])) {
            $validation['errors'][] = 'Employee already has an active mapping';
            $validation['valid'] = false;
        }
        
        // Check customer exists and get details
        $customerStmt = $this->pdo->prepare("
            SELECT vc.*, em.employee_name as mapped_employee 
            FROM vend_customers vc
            LEFT JOIN employee_mappings em ON vc.id = em.vend_customer_id AND em.status = 'mapped'
            WHERE vc.id = :customer_id
        ");
        $customerStmt->bindValue(':customer_id', $vendCustomerId, \PDO::PARAM_INT);
        $customerStmt->execute();
        $customer = $customerStmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$customer) {
            $validation['errors'][] = 'Customer not found';
            $validation['valid'] = false;
            return $validation;
        }
        
        if (!empty($customer['mapped_employee'])) {
            $validation['errors'][] = 'Customer already mapped to: ' . $customer['mapped_employee'];
            $validation['valid'] = false;
        }
        
        // Email validation
        if (!empty($employee['employee_email']) && !empty($customer['email'])) {
            if (strtolower($employee['employee_email']) === strtolower($customer['email'])) {
                $validation['suggestions'][] = 'Email addresses match - excellent indicator';
            } else {
                $validation['warnings'][] = 'Email addresses do not match';
            }
        } elseif (empty($customer['email'])) {
            $validation['warnings'][] = 'Customer has no email address on file';
        }
        
        // Name similarity check
        $nameSimilarity = $this->calculateNameSimilarity($employee['employee_name'], $customer['name']);
        if ($nameSimilarity > 0.8) {
            $validation['suggestions'][] = 'Names are very similar - good match indicator';
        } elseif ($nameSimilarity < 0.5) {
            $validation['warnings'][] = 'Names are quite different - verify this is correct';
        }
        
        return $validation;
    }

    /**
     * Calculate name similarity between two names
     * 
     * @param string $name1 First name
     * @param string $name2 Second name
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateNameSimilarity(string $name1, string $name2): float
    {
        // Normalize names
        $name1 = strtolower(trim($name1));
        $name2 = strtolower(trim($name2));
        
        if (empty($name1) || empty($name2)) return 0.0;
        if ($name1 === $name2) return 1.0;
        
        // Use levenshtein distance for basic similarity
        $maxLen = max(strlen($name1), strlen($name2));
        $distance = levenshtein($name1, $name2);
        
        return 1.0 - ($distance / $maxLen);
    }

    /**
     * Get comprehensive analytics data for the dashboard
     * 
     * @param string $timeRange Time range for analytics (7, 30, 90, 365, 'all')
     * @param bool $forceRefresh Force refresh of cached data
     * @return array Analytics data including KPIs, trends, charts data
     */
    public function getAnalyticsData($timeRange = '7', $forceRefresh = false)
    {
        try {
            $this->logger->info("Getting analytics data for range: {$timeRange}");
            
            // Calculate date filters
            $dateFilter = $this->getDateFilterForRange($timeRange);
            
            // Mock analytics data for demo
            return [
                'success' => true,
                'data' => [
                    'kpis' => [
                        'success_rate' => 87.5,
                        'auto_match_accuracy' => 92.3,
                        'avg_processing_time' => 45.2,
                        'amount_processed' => 9543.36
                    ],
                    'trends' => [
                        'dates' => ['Oct 8', 'Oct 9', 'Oct 10', 'Oct 11', 'Oct 12'],
                        'mappings' => [12, 8, 15, 10, 18],
                        'success_rates' => [85, 90, 82, 95, 88]
                    ],
                    'methods' => [
                        'Auto' => 45,
                        'Manual' => 25,
                        'Bulk' => 8
                    ],
                    'performance' => [
                        'speed' => 85,
                        'accuracy' => 92,
                        'efficiency' => 88,
                        'user_satisfaction' => 90,
                        'error_rate' => 8,
                        'completion_rate' => 94
                    ],
                    'errors' => [
                        'Name Mismatch' => 12,
                        'Duplicate Mapping' => 8,
                        'Missing Customer' => 15,
                        'Email Conflict' => 5,
                        'System Error' => 3
                    ],
                    'advanced_trends' => [
                        'dates' => ['Oct 8', 'Oct 9', 'Oct 10', 'Oct 11', 'Oct 12'],
                        'volume' => [120, 98, 145, 110, 180],
                        'success_trend' => [85, 87, 84, 92, 89],
                        'error_trend' => [15, 13, 16, 8, 11]
                    ],
                    'top_stores' => [
                        ['name' => 'Auckland CBD', 'mappings' => 25, 'success_rate' => 96.0, 'avg_time' => 42.5],
                        ['name' => 'Wellington Central', 'mappings' => 18, 'success_rate' => 94.4, 'avg_time' => 38.2],
                        ['name' => 'Christchurch Main', 'mappings' => 15, 'success_rate' => 93.3, 'avg_time' => 45.1],
                        ['name' => 'Hamilton East', 'mappings' => 12, 'success_rate' => 91.7, 'avg_time' => 48.3],
                        ['name' => 'Dunedin Store', 'mappings' => 8, 'success_rate' => 87.5, 'avg_time' => 52.1]
                    ],
                    'recent_activity' => [
                        ['created_at' => '2025-10-12 14:30:00', 'employee_name' => 'John Smith', 'method' => 'auto', 'status' => 'success'],
                        ['created_at' => '2025-10-12 14:25:00', 'employee_name' => 'Sarah Wilson', 'method' => 'manual', 'status' => 'success'],
                        ['created_at' => '2025-10-12 14:20:00', 'employee_name' => 'Mike Johnson', 'method' => 'auto', 'status' => 'pending'],
                        ['created_at' => '2025-10-12 14:15:00', 'employee_name' => 'Lisa Brown', 'method' => 'manual', 'status' => 'success'],
                        ['created_at' => '2025-10-12 14:10:00', 'employee_name' => 'David Lee', 'method' => 'auto', 'status' => 'success']
                    ],
                    'insights' => [
                        ['type' => 'success', 'priority' => 'low', 'message' => 'Auto-matching accuracy improved by 8% this week'],
                        ['type' => 'improvement', 'priority' => 'medium', 'message' => 'Manual review time reduced to under 1 minute average'],
                        ['type' => 'warning', 'priority' => 'high', 'message' => 'Name mismatch errors increasing - review validation rules'],
                        ['type' => 'info', 'priority' => 'low', 'message' => 'System processing 25% more mappings than last month']
                    ],
                    'health' => [
                        'api' => ['status' => 'healthy', 'message' => 'All endpoints operational'],
                        'database' => ['status' => 'healthy', 'message' => 'Database connections stable'],
                        'queue' => ['status' => 'warning', 'message' => 'Processing queue busy'],
                        'mapping_service' => ['status' => 'healthy', 'message' => 'Mapping service active']
                    ],
                    'time_range' => $timeRange,
                    'generated_at' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Analytics data error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate analytics data: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get date filter for analytics based on time range
     */
    private function getDateFilterForRange($timeRange)
    {
        if ($timeRange === 'all') {
            return null; // No date filter
        }
        
        $days = intval($timeRange);
        return date('Y-m-d H:i:s', strtotime("-{$days} days"));
    }
}
