<?php
declare(strict_types=1);

namespace CIS\Modules\StaffAccounts;

use Exception;

/**
 * Payment Service
 * 
 * Handles failed payment processing, Vend payment allocation,
 * and payment verification with comprehensive error handling
 * 
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */
class PaymentService
{
    /**
     * Apply a failed payment to Vend customer account
     * 
     * Enhanced with:
     * - Pre-flight balance verification
     * - Post-flight verification
     * - Idempotency checking
     * - Comprehensive audit logging
     * - Transaction state tracking
     * 
     * @param string $userId Internal user ID
     * @param string $vendCustomerId Vend customer ID
     * @param float $amount Payment amount
     * @param array<string, mixed> $options Additional options (attempt number, etc.)
     * @return array<string, mixed> Result with success/error details
     */
    public static function applyFailedPayment(
        string $userId, 
        string $vendCustomerId, 
        float $amount, 
        array $options = []
    ): array {
        $startTime = microtime(true);
        $transactionId = uniqid('txn_', true);
        $errorCategory = 'unknown';
        $attemptNumber = $options['attempt'] ?? 1;
        $maxRetries = 3;
        
        // Initialize transaction state
        $transactionState = [
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'vend_customer_id' => $vendCustomerId,
            'requested_amount' => $amount,
            'started_at' => date('Y-m-d H:i:s'),
            'attempt' => $attemptNumber,
            'stage' => 'initialization',
            'actor' => $_SESSION['user_name'] ?? 'System'
        ];
        
        try {
            // Load Vend payment library if not already loaded
            if (!function_exists('vend_add_payment_strict_auto')) {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/xeroAPI/vend-payment-lib.php';
            }
            
            // STAGE 1: Input Validation
            $transactionState['stage'] = 'validation';
            
            if (empty($userId)) {
                $errorCategory = 'validation';
                throw new Exception('User ID is required');
            }
            
            if (empty($vendCustomerId)) {
                $errorCategory = 'validation';
                throw new Exception('Vend customer ID is required');
            }
            
            if ($amount <= 0) {
                $errorCategory = 'validation';
                throw new Exception('Payment amount must be greater than 0 (received: $' . $amount . ')');
            }
            
            if ($amount > 10000) {
                $errorCategory = 'validation';
                throw new Exception('Payment amount exceeds safety limit of $10,000 (received: $' . number_format($amount, 2) . ')');
            }
            
            // STAGE 2: Idempotency Check
            $transactionState['stage'] = 'idempotency_check';
            
            $recentLogFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/payment_applications.log';
            if (file_exists($recentLogFile)) {
                $recentLogs = file($recentLogFile, FILE_IGNORE_NEW_LINES);
                $fiveMinutesAgo = time() - 300;
                
                foreach (array_reverse(array_slice($recentLogs, -100)) as $logLine) {
                    if (preg_match('/\[(\d+)\].*User:' . preg_quote($userId, '/') . '.*Customer:' . preg_quote($vendCustomerId, '/') . '.*Amount:\$' . number_format($amount, 2) . '/', $logLine, $matches)) {
                        $logTimestamp = (int)$matches[1];
                        if ($logTimestamp >= $fiveMinutesAgo) {
                            return [
                                'success' => false,
                                'error' => 'Duplicate payment detected - same payment was already applied within the last 5 minutes',
                                'error_category' => 'idempotency_violation',
                                'transaction_id' => $transactionId
                            ];
                        }
                    }
                }
            }
            
            // STAGE 3: PRE-FLIGHT CHECK
            $transactionState['stage'] = 'pre_flight_check';
            
            $currentBalance = VendApiService::getCustomerBalance($vendCustomerId);
            if (!$currentBalance['success']) {
                $errorCategory = 'vend_api_error';
                throw new Exception('Could not verify current Vend balance: ' . ($currentBalance['error'] ?? 'unknown error'));
            }
            
            $balanceBefore = (float)($currentBalance['balance'] ?? 0);
            $transactionState['balance_before'] = $balanceBefore;
            
            // Check if customer has outstanding debt
            if ($balanceBefore >= 0) {
                $errorCategory = 'business_logic';
                return [
                    'success' => false,
                    'error' => 'Customer has no outstanding balance (current: $' . number_format($balanceBefore, 2) . ')',
                    'error_category' => $errorCategory,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore,
                    'transaction_id' => $transactionId,
                    'transaction_state' => $transactionState
                ];
            }
            
            // Adjust amount if it exceeds outstanding balance
            $actualOwed = abs($balanceBefore);
            $adjustedAmount = $amount;
            
            if ($amount > $actualOwed) {
                $adjustedAmount = $actualOwed;
                $transactionState['amount_adjusted'] = true;
                $transactionState['original_amount'] = $amount;
                $transactionState['adjusted_amount'] = $adjustedAmount;
                
                error_log("WARNING [{$transactionId}]: Requested payment \${$amount} exceeds outstanding balance \${$actualOwed} for customer {$vendCustomerId}. Adjusting to actual owed amount.");
            }
            
            // STAGE 4: Apply Payment
            $transactionState['stage'] = 'payment_application';
            $transactionState['amount_to_apply'] = $adjustedAmount;
            
            set_time_limit(30);
            
            $result = vend_add_payment_strict_auto($vendCustomerId, $adjustedAmount);
            
            if ($result && !empty($result['ok'])) {
                // STAGE 5: POST-FLIGHT verification
                $transactionState['stage'] = 'post_flight_verification';
                
                $allocated = $result['allocated'] ?? $adjustedAmount;
                $details = $result['details'] ?? [];
                $salesPaid = count($details);
                
                $transactionState['allocated'] = $allocated;
                $transactionState['sales_count'] = $salesPaid;
                
                // Verify balance actually changed
                $balanceAfter = VendApiService::getCustomerBalance($vendCustomerId);
                $balanceAfterValue = $balanceAfter['success'] ? (float)($balanceAfter['balance'] ?? $balanceBefore) : $balanceBefore;
                
                $actualChange = abs($balanceBefore) - abs($balanceAfterValue);
                
                $transactionState['balance_after'] = $balanceAfterValue;
                $transactionState['actual_change'] = $actualChange;
                
                // Verify within tolerance
                $verificationPassed = abs($actualChange - $allocated) <= 0.01;
                
                if (!$verificationPassed) {
                    error_log("WARNING [{$transactionId}]: Balance change mismatch! Expected: \${$allocated}, Actual: \${$actualChange}, Customer: {$vendCustomerId}");
                    $transactionState['verification_warning'] = 'Balance change mismatch detected';
                }
                
                // STAGE 6: Audit Logging
                $transactionState['stage'] = 'audit_logging';
                $transactionState['completed_at'] = date('Y-m-d H:i:s');
                $transactionState['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
                $transactionState['status'] = 'success';
                
                // Write audit log
                $auditLogEntry = sprintf(
                    "[%d] [%s] SUCCESS | User:%s | Customer:%s | Amount:$%s | Allocated:$%s | Sales:%d | BalanceBefore:$%s | BalanceAfter:$%s | Change:$%s | Duration:%sms | Actor:%s\n",
                    time(),
                    $transactionId,
                    $userId,
                    $vendCustomerId,
                    number_format($adjustedAmount, 2),
                    number_format($allocated, 2),
                    $salesPaid,
                    number_format($balanceBefore, 2),
                    number_format($balanceAfterValue, 2),
                    number_format($actualChange, 2),
                    $transactionState['duration_ms'],
                    $transactionState['actor']
                );
                
                file_put_contents($recentLogFile, $auditLogEntry, FILE_APPEND | LOCK_EX);
                
                error_log("Successfully applied failed payment to Vend [{$transactionId}]: User {$userId}, Customer {$vendCustomerId}, Amount: \${$adjustedAmount}, Allocated: \${$allocated}");
                
                return [
                    'success' => true,
                    'message' => "Payment of $" . number_format($adjustedAmount, 2) . " applied successfully (allocated: $" . number_format($allocated, 2) . " across {$salesPaid} sales)",
                    'transaction_id' => $transactionId,
                    'allocated' => $allocated,
                    'sales_count' => $salesPaid,
                    'details' => $details,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfterValue,
                    'balance_change' => $actualChange,
                    'verification' => [
                        'expected_change' => $allocated,
                        'actual_change' => $actualChange,
                        'verified' => $verificationPassed
                    ],
                    'duration_ms' => $transactionState['duration_ms'],
                    'transaction_state' => $transactionState
                ];
                
            } else {
                // Payment failed at Vend API level
                $errorCategory = 'vend_payment_failed';
                $reason = $result['reason'] ?? 'unknown_error';
                
                // Check if retry is appropriate
                if ($attemptNumber < $maxRetries && in_array($reason, ['timeout', 'network_error', 'rate_limit'])) {
                    sleep(2 * $attemptNumber); // Exponential backoff
                    return self::applyFailedPayment($userId, $vendCustomerId, $amount, ['attempt' => $attemptNumber + 1]);
                }
                
                throw new Exception("Vend payment failed: {$reason}");
            }
            
        } catch (Exception $e) {
            // Comprehensive error handling
            $transactionState['stage'] = 'error_handling';
            $transactionState['failed_at'] = date('Y-m-d H:i:s');
            $transactionState['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $transactionState['status'] = 'failed';
            $transactionState['error_message'] = $e->getMessage();
            $transactionState['error_category'] = $errorCategory;
            
            // Write error log
            $errorLogEntry = sprintf(
                "[%d] [%s] FAILED | User:%s | Customer:%s | Amount:$%s | Stage:%s | Category:%s | Error:%s | Attempt:%d/%d | Duration:%sms | Actor:%s\n",
                time(),
                $transactionId,
                $userId,
                $vendCustomerId,
                number_format($amount, 2),
                $transactionState['stage'],
                $errorCategory,
                str_replace(["\r", "\n"], ' ', $e->getMessage()),
                $attemptNumber,
                $maxRetries,
                $transactionState['duration_ms'],
                $transactionState['actor']
            );
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/payment_errors.log', $errorLogEntry, FILE_APPEND | LOCK_EX);
            
            error_log("Failed to apply payment to Vend [{$transactionId}]: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_category' => $errorCategory,
                'transaction_id' => $transactionId,
                'stage_failed' => $transactionState['stage'],
                'attempt' => $attemptNumber,
                'max_retries' => $maxRetries,
                'retriable' => in_array($errorCategory, ['vend_api_error', 'retriable_error', 'timeout']),
                'duration_ms' => $transactionState['duration_ms'],
                'transaction_state' => $transactionState
            ];
        }
    }
    
    /**
     * Apply all failed payments in bulk with rate limiting
     * 
     * @return array<string, mixed>
     */
    public static function applyAllFailedPayments(): array
    {
        $failedPayments = SnapshotService::getFailedPaymentsSummary();
        $results = [
            'success' => true,
            'applied' => 0,
            'failed' => 0,
            'total' => count($failedPayments),
            'details' => []
        ];
        
        foreach ($failedPayments as $payment) {
            $result = self::applyFailedPayment(
                $payment['user_id'],
                $payment['vend_customer_id'],
                $payment['total_failed_amount']
            );
            
            if ($result['success']) {
                $results['applied']++;
            } else {
                $results['failed']++;
                $results['success'] = false;
            }
            
            $results['details'][] = [
                'user_id' => $payment['user_id'],
                'name' => $payment['name'],
                'amount' => $payment['total_failed_amount'],
                'result' => $result
            ];
            
            // Rate limiting: 500ms delay between requests
            usleep(500000);
        }
        
        return $results;
    }
}
