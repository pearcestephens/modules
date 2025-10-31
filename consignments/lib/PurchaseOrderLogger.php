<?php
/**
 * Purchase Order Module Logger
 *
 * Comprehensive logging, security monitoring, fraud detection, and AI tracking
 * for the consignments/purchase orders module.
 *
 * This wrapper provides:
 * - Semantic, type-safe logging methods
 * - Security monitoring (DevTools, keyboard patterns, mouse behavior)
 * - Fraud detection (suspicious values, input patterns)
 * - Performance timing (page loads, API calls, user actions)
 * - AI recommendation tracking with confidence scores
 * - User interaction tracking (clicks, modals, forms)
 * - Fail-safe error handling (never breaks the application)
 *
 * Design Philosophy:
 * - Uses CISLogger universal tables for cross-system AI analysis
 * - Logs to cis_action_log, cis_ai_context, cis_security_log, cis_performance_metrics
 * - Never hides errors - always propagates user-friendly messages
 * - Fails gracefully - if logging fails, logs to error_log and continues
 * - AI-centered - captures data patterns for machine learning
 *
 * Usage:
 *   use CIS\Consignments\Lib\PurchaseOrderLogger as Logger;
 *
 *   Logger::poCreated($poId, $supplierId, $context);
 *   Logger::freightQuoteGenerated($poId, $carrier, $quotes, $timingMs);
 *   Logger::aiRecommendationAccepted($insightId, $poId, $savings);
 *   Logger::securityDevToolsDetected($poId, $page);
 *   Logger::fraudSuspiciousValue($poId, $field, $value, $expected);
 *
 * @package CIS\Consignments\Lib
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Consignments\Lib;

class PurchaseOrderLogger {

    private const CATEGORY = 'purchase_orders';

    /**
     * Initialize logger (called automatically by bootstrap)
     */
    public static function init(): void {
        if (class_exists('CISLogger', false)) {
            \CISLogger::init();
        }
    }

    // ========================================================================
    // PURCHASE ORDER OPERATIONS
    // ========================================================================

    /**
     * Log PO creation
     */
    public static function poCreated(
        int $poId,
        int $supplierId,
        int $outletId,
        float $totalAmount,
        int $itemCount,
        array $context = []
    ): void {
        $context = array_merge([
            'supplier_id' => $supplierId,
            'outlet_id' => $outletId,
            'total_amount' => $totalAmount,
            'item_count' => $itemCount,
        ], $context);

        self::log('po_created', 'success', 'purchase_order', $poId, $context);
    }

    /**
     * Log PO approval
     */
    public static function poApproved(
        int $poId,
        int $approverId,
        ?string $approverName = null,
        ?float $approvalTimeSeconds = null,
        array $context = []
    ): void {
        $context = array_merge([
            'approver_id' => $approverId,
            'approver_name' => $approverName,
            'approval_time_seconds' => $approvalTimeSeconds,
        ], $context);

        self::log('po_approved', 'success', 'purchase_order', $poId, $context);

        // Track approval timing
        if ($approvalTimeSeconds !== null) {
            self::logPerformance('user_action', 'po_approval', $approvalTimeSeconds * 1000, 'ms', [
                'po_id' => $poId,
                'approver_id' => $approverId
            ]);
        }
    }

    /**
     * Log PO rejection
     */
    public static function poRejected(
        int $poId,
        int $rejectorId,
        string $reason,
        ?float $reviewTimeSeconds = null,
        array $context = []
    ): void {
        $context = array_merge([
            'rejector_id' => $rejectorId,
            'rejection_reason' => $reason,
            'review_time_seconds' => $reviewTimeSeconds,
        ], $context);

        self::log('po_rejected', 'success', 'purchase_order', $poId, $context);
    }

    /**
     * Log PO receiving started
     */
    public static function poReceivingStarted(
        int $poId,
        int $receiverId,
        array $context = []
    ): void {
        $context['receiver_id'] = $receiverId;
        self::log('po_receiving_started', 'success', 'purchase_order', $poId, $context);
    }

    /**
     * Log PO receiving completed
     */
    public static function poReceivingCompleted(
        int $poId,
        int $receiverId,
        int $itemsReceived,
        int $itemsExpected,
        float $receivingTimeSeconds,
        array $discrepancies = [],
        array $context = []
    ): void {
        $context = array_merge([
            'receiver_id' => $receiverId,
            'items_received' => $itemsReceived,
            'items_expected' => $itemsExpected,
            'receiving_time_seconds' => $receivingTimeSeconds,
            'discrepancies' => $discrepancies,
            'match_rate' => $itemsExpected > 0 ? round(($itemsReceived / $itemsExpected) * 100, 2) : 0,
        ], $context);

        self::log('po_receiving_completed', 'success', 'purchase_order', $poId, $context);

        // Track receiving efficiency
        self::logPerformance('user_action', 'po_receiving', $receivingTimeSeconds * 1000, 'ms', [
            'po_id' => $poId,
            'items_count' => $itemsReceived,
            'discrepancy_count' => count($discrepancies)
        ]);
    }

    /**
     * Log that an automated transfer/transfer-review job was scheduled
     */
    public static function reviewScheduled(
        int $poId,
        int $scheduledBy,
        array $context = []
    ): void {
        $context = array_merge([
            'scheduled_by' => $scheduledBy,
            'module' => self::CATEGORY
        ], $context);

        // Use a distinct event so scheduling vs completion are separable
        self::log('transfer_review_scheduled', 'info', 'purchase_order', $poId, $context);
    }

    /**
     * Log item quantity adjustment during receiving
     */
    public static function itemQuantityAdjusted(
        int $poId,
        int $productId,
        int $expectedQty,
        int $receivedQty,
        ?string $reason = null,
        array $context = []
    ): void {
        $context = array_merge([
            'product_id' => $productId,
            'expected_qty' => $expectedQty,
            'received_qty' => $receivedQty,
            'adjustment' => $receivedQty - $expectedQty,
            'reason' => $reason,
        ], $context);

        self::log('item_quantity_adjusted', 'success', 'purchase_order', $poId, $context);

        // Flag large discrepancies for fraud detection
        $discrepancyPercent = abs($receivedQty - $expectedQty) / max($expectedQty, 1) * 100;
        if ($discrepancyPercent > 20) {
            self::fraudLargeDiscrepancy($poId, $productId, $expectedQty, $receivedQty, $discrepancyPercent);
        }
    }

    /**
     * Log item damaged during receiving
     */
    public static function itemDamaged(
        int $poId,
        int $productId,
        int $damagedQty,
        string $damageType,
        ?string $photoPath = null,
        array $context = []
    ): void {
        $context = array_merge([
            'product_id' => $productId,
            'damaged_qty' => $damagedQty,
            'damage_type' => $damageType,
            'photo_path' => $photoPath,
        ], $context);

        self::log('item_damaged', 'partial', 'purchase_order', $poId, $context);
    }

    // ========================================================================
    // FREIGHT OPERATIONS
    // ========================================================================

    /**
     * Log freight quote generation
     */
    public static function freightQuoteGenerated(
        int $poId,
        array $carriers,
        int $quoteCount,
        ?float $timingMs = null,
        array $context = []
    ): void {
        $context = array_merge([
            'carriers' => $carriers,
            'quote_count' => $quoteCount,
            'timing_ms' => $timingMs,
        ], $context);

        self::log('freight_quote_generated', 'success', 'purchase_order', $poId, $context);

        if ($timingMs !== null) {
            self::logPerformance('api_call', 'freight_quote', $timingMs, 'ms', [
                'po_id' => $poId,
                'carriers' => $carriers
            ]);
        }
    }

    /**
     * Log carrier selection
     */
    public static function carrierSelected(
        int $poId,
        string $carrier,
        string $service,
        float $cost,
        ?string $selectionReason = null,
        ?float $decisionTimeSeconds = null,
        array $context = []
    ): void {
        $context = array_merge([
            'carrier' => $carrier,
            'service' => $service,
            'cost' => $cost,
            'selection_reason' => $selectionReason,
            'decision_time_seconds' => $decisionTimeSeconds,
        ], $context);

        self::log('carrier_selected', 'success', 'purchase_order', $poId, $context);

        // Track user decision speed
        if ($decisionTimeSeconds !== null) {
            self::logPerformance('user_action', 'carrier_selection', $decisionTimeSeconds * 1000, 'ms', [
                'po_id' => $poId,
                'carrier' => $carrier
            ]);
        }
    }

    /**
     * Log freight label creation
     */
    public static function freightLabelCreated(
        int $poId,
        string $carrier,
        string $trackingNumber,
        ?string $labelPath = null,
        array $context = []
    ): void {
        $context = array_merge([
            'carrier' => $carrier,
            'tracking_number' => $trackingNumber,
            'label_path' => $labelPath,
        ], $context);

        self::log('freight_label_created', 'success', 'purchase_order', $poId, $context);
    }

    /**
     * Log freight label printed
     */
    public static function freightLabelPrinted(
        int $poId,
        string $trackingNumber,
        array $context = []
    ): void {
        $context['tracking_number'] = $trackingNumber;
        self::log('freight_label_printed', 'success', 'purchase_order', $poId, $context);
    }

    /**
     * Log tracking viewed
     */
    public static function trackingViewed(
        int $poId,
        string $trackingNumber,
        ?string $currentStatus = null,
        array $context = []
    ): void {
        $context = array_merge([
            'tracking_number' => $trackingNumber,
            'current_status' => $currentStatus,
        ], $context);

        self::log('tracking_viewed', 'success', 'purchase_order', $poId, $context);
    }

    // ========================================================================
    // AI OPERATIONS & RECOMMENDATIONS
    // ========================================================================

    /**
     * Log AI recommendation generated
     */
    public static function aiRecommendationGenerated(
        int $poId,
        string $recommendationType,
        array $recommendation,
        float $confidence,
        ?string $reasoning = null,
        ?float $executionTimeMs = null,
        array $context = []
    ): void {
        // Log action
        $actionContext = array_merge([
            'recommendation_type' => $recommendationType,
            'confidence' => $confidence,
            'execution_time_ms' => $executionTimeMs,
        ], $context);

        self::log('ai_recommendation_generated', 'success', 'purchase_order', $poId, $actionContext, 'bot');

        // Log AI context for training
        self::logAI(
            'recommendation_generation',
            $recommendationType,
            json_encode(['po_id' => $poId, 'type' => $recommendationType]),
            json_encode($recommendation),
            $reasoning,
            ['po_id' => $poId, 'type' => $recommendationType],
            $recommendation,
            $confidence,
            ['purchase_orders', 'ai_recommendation', $recommendationType]
        );

        // Track AI performance
        if ($executionTimeMs !== null) {
            self::logPerformance('ai_operation', "ai_{$recommendationType}", $executionTimeMs, 'ms', [
                'po_id' => $poId,
                'confidence' => $confidence
            ]);
        }
    }

    /**
     * Log AI recommendation accepted by user
     */
    public static function aiRecommendationAccepted(
        int $insightId,
        int $poId,
        string $recommendationType,
        ?float $estimatedSavings = null,
        ?float $reviewTimeSeconds = null,
        array $context = []
    ): void {
        $context = array_merge([
            'insight_id' => $insightId,
            'recommendation_type' => $recommendationType,
            'estimated_savings' => $estimatedSavings,
            'review_time_seconds' => $reviewTimeSeconds,
        ], $context);

        self::log('ai_recommendation_accepted', 'success', 'purchase_order', $poId, $context);

        // Track user decision timing
        if ($reviewTimeSeconds !== null) {
            self::logPerformance('user_action', 'ai_recommendation_review', $reviewTimeSeconds * 1000, 'ms', [
                'insight_id' => $insightId,
                'accepted' => true
            ]);
        }
    }

    /**
     * Log AI recommendation dismissed by user
     */
    public static function aiRecommendationDismissed(
        int $insightId,
        int $poId,
        string $recommendationType,
        ?string $reason = null,
        ?float $reviewTimeSeconds = null,
        array $context = []
    ): void {
        $context = array_merge([
            'insight_id' => $insightId,
            'recommendation_type' => $recommendationType,
            'dismissal_reason' => $reason,
            'review_time_seconds' => $reviewTimeSeconds,
        ], $context);

        self::log('ai_recommendation_dismissed', 'success', 'purchase_order', $poId, $context);
    }

    /**
     * Log bulk AI recommendations processed
     */
    public static function aiBulkRecommendationsProcessed(
        array $insightIds,
        string $action,
        int $successCount,
        int $failureCount,
        array $context = []
    ): void {
        $context = array_merge([
            'insight_ids' => $insightIds,
            'action' => $action,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'total' => count($insightIds),
        ], $context);

        self::log('ai_bulk_recommendations_processed', 'success', 'bulk_operation', null, $context);
    }

    // ========================================================================
    // SECURITY MONITORING
    // ========================================================================

    /**
     * Log DevTools detection
     */
    public static function securityDevToolsDetected(
        ?int $poId,
        string $page,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'page' => $page,
            'module' => self::CATEGORY,
        ], $context);

        self::logSecurity(
            'devtools_detected',
            'warning',
            null,
            $context,
            'user_flagged'
        );
    }

    /**
     * Log rapid keyboard entry (potential bot or fraud)
     */
    public static function securityRapidKeyboardEntry(
        int $poId,
        string $field,
        float $entriesPerSecond,
        int $totalEntries,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'field' => $field,
            'entries_per_second' => $entriesPerSecond,
            'total_entries' => $totalEntries,
            'module' => self::CATEGORY,
        ], $context);

        $severity = $entriesPerSecond > 10 ? 'critical' : 'warning';

        self::logSecurity(
            'rapid_keyboard_entry',
            $severity,
            null,
            $context,
            'flagged_for_review'
        );
    }

    /**
     * Log copy-paste behavior (potential fraud)
     */
    public static function securityCopyPasteBehavior(
        int $poId,
        string $field,
        int $pasteCount,
        ?string $pattern = null,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'field' => $field,
            'paste_count' => $pasteCount,
            'pattern' => $pattern,
            'module' => self::CATEGORY,
        ], $context);

        self::logSecurity(
            'copy_paste_behavior',
            'warning',
            null,
            $context,
            'logged'
        );
    }

    /**
     * Log erratic mouse behavior
     */
    public static function securityErraticMouseBehavior(
        int $poId,
        string $page,
        array $metrics,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'page' => $page,
            'metrics' => $metrics,
            'module' => self::CATEGORY,
        ], $context);

        self::logSecurity(
            'erratic_mouse_behavior',
            'warning',
            null,
            $context,
            'behavior_flagged'
        );
    }

    /**
     * Log modal focus loss (user distracted or suspicious)
     */
    public static function securityModalFocusLost(
        int $poId,
        string $modalName,
        float $timeBeforeFocusLossSeconds,
        int $focusLossCount,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'modal_name' => $modalName,
            'time_before_focus_loss_seconds' => $timeBeforeFocusLossSeconds,
            'focus_loss_count' => $focusLossCount,
            'module' => self::CATEGORY,
        ], $context);

        // Only log if excessive focus loss (> 3 times)
        if ($focusLossCount > 3) {
            self::logSecurity(
                'modal_focus_lost',
                'info',
                null,
                $context,
                'logged'
            );
        }
    }

    /**
     * Log tab switch during critical operation
     */
    public static function securityTabSwitchDuringOperation(
        int $poId,
        string $operation,
        float $timeAwaySeconds,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'operation' => $operation,
            'time_away_seconds' => $timeAwaySeconds,
            'module' => self::CATEGORY,
        ], $context);

        self::logSecurity(
            'tab_switch_during_operation',
            'info',
            null,
            $context,
            'logged'
        );
    }

    // ========================================================================
    // FRAUD DETECTION
    // ========================================================================

    /**
     * Log suspicious value entered (99 vs 9 pattern)
     */
    public static function fraudSuspiciousValue(
        int $poId,
        string $field,
        $enteredValue,
        $expectedValue,
        ?string $pattern = null,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'field' => $field,
            'entered_value' => $enteredValue,
            'expected_value' => $expectedValue,
            'pattern' => $pattern,
            'module' => self::CATEGORY,
        ], $context);

        self::logSecurity(
            'suspicious_value_entered',
            'warning',
            null,
            $context,
            'flagged_for_review'
        );
    }

    /**
     * Log large quantity discrepancy
     */
    public static function fraudLargeDiscrepancy(
        int $poId,
        int $productId,
        int $expectedQty,
        int $receivedQty,
        float $discrepancyPercent,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'product_id' => $productId,
            'expected_qty' => $expectedQty,
            'received_qty' => $receivedQty,
            'discrepancy_percent' => $discrepancyPercent,
            'module' => self::CATEGORY,
        ], $context);

        $severity = $discrepancyPercent > 50 ? 'critical' : 'warning';

        self::logSecurity(
            'large_quantity_discrepancy',
            $severity,
            null,
            $context,
            'flagged_for_review'
        );
    }

    /**
     * Log pattern in input values (repeated numbers, sequences)
     */
    public static function fraudInputPattern(
        int $poId,
        string $field,
        array $values,
        string $patternType,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'field' => $field,
            'values' => $values,
            'pattern_type' => $patternType,
            'module' => self::CATEGORY,
        ], $context);

        self::logSecurity(
            'input_pattern_detected',
            'warning',
            null,
            $context,
            'pattern_flagged'
        );
    }

    /**
     * Log intentional mistake detected
     */
    public static function fraudIntentionalMistake(
        int $poId,
        string $mistakeType,
        array $evidence,
        float $confidenceScore,
        array $context = []
    ): void {
        $context = array_merge([
            'po_id' => $poId,
            'mistake_type' => $mistakeType,
            'evidence' => $evidence,
            'confidence_score' => $confidenceScore,
            'module' => self::CATEGORY,
        ], $context);

        $severity = $confidenceScore > 0.8 ? 'critical' : 'warning';

        self::logSecurity(
            'intentional_mistake_detected',
            $severity,
            null,
            $context,
            'escalated_for_review'
        );
    }

    // ========================================================================
    // USER INTERACTIONS
    // ========================================================================

    /**
     * Log button click
     */
    public static function buttonClicked(
        string $buttonId,
        string $page,
        ?int $poId = null,
        array $context = []
    ): void {
        $context = array_merge([
            'button_id' => $buttonId,
            'page' => $page,
            'po_id' => $poId,
        ], $context);

        self::log('button_clicked', 'success', 'ui_interaction', null, $context);
    }

    /**
     * Log modal opened
     */
    public static function modalOpened(
        string $modalName,
        string $page,
        ?int $poId = null,
        array $context = []
    ): void {
        $context = array_merge([
            'modal_name' => $modalName,
            'page' => $page,
            'po_id' => $poId,
        ], $context);

        self::log('modal_opened', 'success', 'ui_interaction', null, $context);
    }

    /**
     * Log modal closed with time spent
     */
    public static function modalClosed(
        string $modalName,
        float $timeSpentSeconds,
        bool $actionTaken,
        ?int $poId = null,
        array $context = []
    ): void {
        $context = array_merge([
            'modal_name' => $modalName,
            'time_spent_seconds' => $timeSpentSeconds,
            'action_taken' => $actionTaken,
            'po_id' => $poId,
        ], $context);

        self::log('modal_closed', 'success', 'ui_interaction', null, $context);

        // Track modal interaction time
        self::logPerformance('user_interaction', "modal_{$modalName}", $timeSpentSeconds * 1000, 'ms', [
            'action_taken' => $actionTaken,
            'po_id' => $poId
        ]);
    }

    /**
     * Log filter changed
     */
    public static function filterChanged(
        string $page,
        string $filterName,
        $filterValue,
        ?int $resultCount = null,
        array $context = []
    ): void {
        $context = array_merge([
            'page' => $page,
            'filter_name' => $filterName,
            'filter_value' => $filterValue,
            'result_count' => $resultCount,
        ], $context);

        self::log('filter_changed', 'success', 'ui_interaction', null, $context);
    }

    /**
     * Log search performed
     */
    public static function searchPerformed(
        string $page,
        string $searchQuery,
        int $resultCount,
        ?float $searchTimeMs = null,
        array $context = []
    ): void {
        $context = array_merge([
            'page' => $page,
            'search_query' => $searchQuery,
            'result_count' => $resultCount,
            'search_time_ms' => $searchTimeMs,
        ], $context);

        self::log('search_performed', 'success', 'search', null, $context);

        if ($searchTimeMs !== null) {
            self::logPerformance('search', $page, $searchTimeMs, 'ms', [
                'query_length' => strlen($searchQuery),
                'result_count' => $resultCount
            ]);
        }
    }

    /**
     * Log form autosave
     */
    public static function formAutosaved(
        string $formName,
        int $poId,
        array $changedFields,
        array $context = []
    ): void {
        $context = array_merge([
            'form_name' => $formName,
            'changed_fields' => $changedFields,
            'field_count' => count($changedFields),
        ], $context);

        self::log('form_autosaved', 'success', 'purchase_order', $poId, $context);
    }

    // ========================================================================
    // PERFORMANCE TRACKING
    // ========================================================================

    /**
     * Log page load
     */
    public static function pageLoad(
        string $pageName,
        float $loadTimeMs,
        array $context = []
    ): void {
        self::logPerformance('page_load', $pageName, $loadTimeMs, 'ms', $context);
    }

    /**
     * Log API call
     */
    public static function apiCall(
        string $endpoint,
        float $responseTimeMs,
        bool $success,
        ?string $error = null,
        array $context = []
    ): void {
        $context = array_merge([
            'success' => $success,
            'error' => $error,
        ], $context);

        self::logPerformance('api_call', $endpoint, $responseTimeMs, 'ms', $context);
    }

    /**
     * Log database query
     */
    public static function dbQuery(
        string $queryName,
        float $durationMs,
        ?int $rowCount = null,
        array $context = []
    ): void {
        $context = array_merge([
            'row_count' => $rowCount,
        ], $context);

        self::logPerformance('db_query', $queryName, $durationMs, 'ms', $context);
    }

    /**
     * Log slow operation warning
     */
    public static function slowOperation(
        string $operationName,
        float $durationMs,
        float $thresholdMs,
        array $context = []
    ): void {
        $context = array_merge([
            'duration_ms' => $durationMs,
            'threshold_ms' => $thresholdMs,
            'exceeded_by_ms' => $durationMs - $thresholdMs,
        ], $context);

        self::log('slow_operation', 'warning', 'performance', null, $context);
        self::logPerformance('slow_operation', $operationName, $durationMs, 'ms', $context);
    }

    // ========================================================================
    // ERROR TRACKING
    // ========================================================================

    /**
     * Log user-facing error with context
     */
    public static function userError(
        string $errorType,
        string $userMessage,
        string $technicalDetails,
        ?int $poId = null,
        array $context = []
    ): void {
        $context = array_merge([
            'user_message' => $userMessage,
            'technical_details' => $technicalDetails,
            'po_id' => $poId,
        ], $context);

        self::log($errorType, 'failure', 'error', $poId, $context);
    }

    /**
     * Log API error
     */
    public static function apiError(
        string $endpoint,
        int $httpCode,
        string $errorMessage,
        ?int $poId = null,
        array $context = []
    ): void {
        $context = array_merge([
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'error_message' => $errorMessage,
            'po_id' => $poId,
        ], $context);

        self::log('api_error', 'failure', 'api', null, $context);
    }

    /**
     * Log validation error
     */
    public static function validationError(
        string $field,
        string $errorMessage,
        $invalidValue,
        ?int $poId = null,
        array $context = []
    ): void {
        $context = array_merge([
            'field' => $field,
            'error_message' => $errorMessage,
            'invalid_value' => $invalidValue,
            'po_id' => $poId,
        ], $context);

        self::log('validation_error', 'failure', 'validation', null, $context);
    }

    /**
     * Log queue error (critical - must notify user)
     */
    public static function queueError(
        string $queueName,
        string $errorMessage,
        array $payload,
        ?int $poId = null,
        array $context = []
    ): void {
        $context = array_merge([
            'queue_name' => $queueName,
            'error_message' => $errorMessage,
            'payload' => $payload,
            'po_id' => $poId,
        ], $context);

        self::log('queue_error', 'failure', 'queue', null, $context);

        // Also log as security event (system failure)
        self::logSecurity(
            'queue_processing_failed',
            'critical',
            null,
            $context,
            'system_alert_sent'
        );
    }

    // ========================================================================
    // INTERNAL HELPERS
    // ========================================================================

    /**
     * Core logging wrapper
     */
    private static function log(
        string $actionType,
        string $result,
        ?string $entityType = null,
        ?int $entityId = null,
        array $context = [],
        string $actorType = 'user'
    ): void {
        if (!class_exists('CISLogger', false)) {
            error_log("[PurchaseOrderLogger] CISLogger not available");
            return;
        }

        try {
            \CISLogger::action(
                self::CATEGORY,
                $actionType,
                $result,
                $entityType,
                $entityId ? (string)$entityId : null,
                $context,
                $actorType
            );
        } catch (\Exception $e) {
            // NEVER let logging break the application
            error_log("[PurchaseOrderLogger] Failed to log action '{$actionType}': " . $e->getMessage());
        }
    }

    /**
     * AI logging wrapper
     */
    private static function logAI(
        string $contextType,
        string $sourceSystem,
        ?string $prompt = null,
        ?string $response = null,
        ?string $reasoning = null,
        array $inputData = [],
        array $outputData = [],
        ?float $confidence = null,
        array $tags = []
    ): void {
        if (!class_exists('CISLogger', false)) {
            return;
        }

        try {
            \CISLogger::ai(
                $contextType,
                $sourceSystem,
                $prompt,
                $response,
                $reasoning,
                $inputData,
                $outputData,
                $confidence,
                $tags
            );
        } catch (\Exception $e) {
            error_log("[PurchaseOrderLogger] Failed to log AI: " . $e->getMessage());
        }
    }

    /**
     * Security logging wrapper
     */
    private static function logSecurity(
        string $eventType,
        string $severity,
        ?int $userId,
        array $threatIndicators = [],
        ?string $actionTaken = null
    ): void {
        if (!class_exists('CISLogger', false)) {
            return;
        }

        try {
            \CISLogger::security(
                $eventType,
                $severity,
                $userId,
                $threatIndicators,
                $actionTaken
            );
        } catch (\Exception $e) {
            error_log("[PurchaseOrderLogger] Failed to log security: " . $e->getMessage());
        }
    }

    /**
     * Performance logging wrapper
     */
    private static function logPerformance(
        string $metricType,
        string $metricName,
        float $value,
        string $unit = 'ms',
        array $context = []
    ): void {
        if (!class_exists('CISLogger', false)) {
            return;
        }

        try {
            \CISLogger::performance(
                $metricType,
                $metricName,
                $value,
                $unit,
                $context
            );
        } catch (\Exception $e) {
            error_log("[PurchaseOrderLogger] Failed to log performance: " . $e->getMessage());
        }
    }
}
