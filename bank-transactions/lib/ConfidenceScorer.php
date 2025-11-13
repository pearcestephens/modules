<?php
/**
 * Confidence Scorer - 300-Point Scoring System
 *
 * Calculates confidence scores for transaction matches using multiple factors:
 * - Amount match (100 points)
 * - Date match (50 points)
 * - Name match (50 points)
 * - Reference match (40 points)
 * - Type match (30 points)
 * - Outlet match (20 points)
 * - Timing match (10 points)
 *
 * Total: 300 points possible
 *
 * @package BankTransactions\Lib
 * @version 1.0.0
 */

namespace CIS\BankTransactions\Lib;

class ConfidenceScorer
{
    private $lastBreakdown = [];

    /**
     * Calculate confidence score for a match
     *
     * @param object $transaction Bank transaction
     * @param array $candidate Candidate match with 'type' and 'record'
        $transHour = isset($transaction->transaction_date) ? (int)date('H', strtotime($transaction->transaction_date)) : null;
     */
    public function scoreMatch($transaction, $candidate)
    {
        $this->lastBreakdown = [];
        $score = 0;

        $record = $candidate['record'];
        $type = $candidate['type'];

        // 1. Amount Match (100 points max)
        $amountScore = $this->scoreAmount($transaction, $record, $type);
        $score += $amountScore;
        $this->lastBreakdown['amount'] = [
            'score' => $amountScore,
            'max' => 100,
            'details' => $this->getAmountDetails($transaction, $record, $type)
        ];

        // 2. Date Match (50 points max)
        $dateScore = $this->scoreDate($transaction, $record, $type);
        $score += $dateScore;
        $this->lastBreakdown['date'] = [
            'score' => $dateScore,
            'max' => 50,
            'details' => $this->getDateDetails($transaction, $record, $type)
        ];

        // 3. Name Match (50 points max)
        $nameScore = $this->scoreName($transaction, $record, $type);
        $score += $nameScore;
        $this->lastBreakdown['name'] = [
            'score' => $nameScore,
            'max' => 50,
            'details' => $this->getNameDetails($transaction, $record, $type)
        ];

        // 4. Reference Match (40 points max)
        $refScore = $this->scoreReference($transaction, $record, $type);
        $score += $refScore;
        $this->lastBreakdown['reference'] = [
            'score' => $refScore,
            'max' => 40,
            'details' => $this->getReferenceDetails($transaction, $record, $type)
        ];

        // 5. Type Match (30 points max)
        $typeScore = $this->scoreType($transaction, $candidate);
        $score += $typeScore;
        $this->lastBreakdown['type'] = [
            'score' => $typeScore,
            'max' => 30,
            'details' => $this->getTypeDetails($transaction, $candidate)
        ];

        // 6. Outlet Match (20 points max)
        $outletScore = $this->scoreOutlet($transaction, $record, $type);
        $score += $outletScore;
        $this->lastBreakdown['outlet'] = [
            'score' => $outletScore,
            'max' => 20,
            'details' => $this->getOutletDetails($transaction, $record, $type)
        ];

        // 7. Timing Match (10 points max)
        $timingScore = $this->scoreTiming($transaction, $record, $type);
        $score += $timingScore;
        $this->lastBreakdown['timing'] = [
            'score' => $timingScore,
            'max' => 10,
            'details' => $this->getTimingDetails($transaction, $record, $type)
        ];

        $this->lastBreakdown['total'] = $score;
        $this->lastBreakdown['percentage'] = round(($score / 300) * 100, 1);

        return (int)min($score, 300); // Cap at 300 and cast to int
    }

    /**
     * Get detailed breakdown of last score calculation
     */
    public function getScoreBreakdown()
    {
        return $this->lastBreakdown;
    }

    /**
     * Score amount match (100 points max)
     */
    private function scoreAmount($transaction, $record, $type)
    {
        $transAmount = $transaction->transaction_amount ?? 0;

        // Get record amount based on type
        $recordAmount = match($type) {
            'store_deposit' => $record->actual_cash_total ?? 0,
            'retail_order', 'wholesale_order' => $record->total_price ?? 0,
            'eftpos' => $record->settlement_amount ?? 0,
            default => 0
        };

        if ($transAmount == $recordAmount) {
            return 100; // Exact match
        }

        $diff = abs($transAmount - $recordAmount);
        $percentDiff = ($diff / $transAmount) * 100;

        if ($percentDiff < 1) return 90;      // Within 1%
        if ($percentDiff < 2) return 80;      // Within 2%
        if ($percentDiff < 5) return 70;      // Within 5%
        if ($percentDiff < 10) return 40;     // Within 10%
        if ($percentDiff < 20) return 20;     // Within 20%

        return 0; // More than 20% difference
    }

    /**
     * Score date match (50 points max)
     */
    private function scoreDate($transaction, $record, $type)
    {
        $transDate = isset($transaction->transaction_date) ? new \DateTime($transaction->transaction_date) : null;

        // Get record date based on type
        $recordDateStr = match($type) {
            'store_deposit' => $record->created ?? null,
            'retail_order', 'wholesale_order' => $record->date_ordered ?? null,
            'eftpos' => $record->settlement_date ?? null,
            default => null
        };

        if (!$recordDateStr) {
            return 0;
        }

        $recordDate = new \DateTime($recordDateStr);
        $daysDiff = abs($transDate->diff($recordDate)->days);

        if ($daysDiff == 0) return 50;      // Same day
        if ($daysDiff == 1) return 40;      // Next/previous day
        if ($daysDiff <= 3) return 25;      // Within 3 days
        if ($daysDiff <= 7) return 10;      // Within week

        return 0; // More than a week apart
    }

    /**
     * Score name match (50 points max) using fuzzy matching
     */
    private function scoreName($transaction, $record, $type)
    {
        $transName = $transaction->transaction_reference ?? '';

        // Get record name based on type
        $recordName = match($type) {
            'store_deposit' => $record->reference ?? '',
            'retail_order', 'wholesale_order' => ($record->first_name ?? '') . ' ' . ($record->last_name ?? ''),
            'eftpos' => $record->terminal_id ?? '',
            default => ''
        };

        if (empty($transName) || empty($recordName)) {
            return 0;
        }

        // Use fuzzy matching
        $matchingEngine = new MatchingEngine();
        $similarity = $matchingEngine->fuzzyNameMatch($transName, $recordName);

        return round($similarity * 50);
    }

    /**
     * Score reference match (40 points max)
     */
    private function scoreReference($transaction, $record, $type)
    {
        $ref = strtoupper($transaction->transaction_reference ?? '');

        if ($type === 'retail_order' || $type === 'wholesale_order') {
            $orderId = $record->order_id ?? '';

            // Check if order ID appears in reference
            if (strpos($ref, (string)$orderId) !== false) {
                return 40; // Order ID found
            }

            // Check for partial match
            $orderDigits = substr($orderId, -6);
            if (strpos($ref, $orderDigits) !== false) {
                return 20; // Partial order ID found
            }
        }

        if ($type === 'store_deposit') {
            $depRef = strtoupper($record->reference ?? '');
            if (strpos($ref, $depRef) !== false || strpos($depRef, $ref) !== false) {
                return 40; // Reference overlap
            }
        }

        return 0;
    }

    /**
     * Score type match (30 points max)
     */
    private function scoreType($transaction, $candidate)
    {
        $ref = strtoupper($transaction->transaction_reference ?? '');
        $type = $candidate['type'];

        $typeIndicators = [
            'store_deposit' => ['CASH', 'DEPOSIT', 'BANKING'],
            'retail_order' => ['ORDER', 'PAYMENT', 'PURCHASE'],
            'wholesale_order' => ['WHOLESALE', 'LTD', 'CORP', 'INVOICE'],
            'eftpos' => ['EFTPOS', 'TERMINAL', 'SETTLEMENT']
        ];

        if (isset($typeIndicators[$type])) {
            foreach ($typeIndicators[$type] as $indicator) {
                if (strpos($ref, $indicator) !== false) {
                    return 30; // Type matches reference
                }
            }
        }

        return 0;
    }

    /**
     * Score outlet match (20 points max)
     */
    private function scoreOutlet($transaction, $record, $type)
    {
        // This would check if outlet IDs match or are in same region
        // For now, simplified implementation
        if ($type === 'store_deposit') {
            // Could check outlet_id from transaction vs record
            return 10; // Partial credit for store deposits
        }

        return 0;
    }

    /**
     * Score timing match (10 points max)
     */
    private function scoreTiming($transaction, $record, $type)
    {
        // Check if transaction occurred within expected banking hours/patterns
        // This is a simplified implementation

        $transHour = isset($transaction->transaction_date) ? (int)date('H', strtotime($transaction->transaction_date)) : null;

        // Banking hours typically 9am-5pm
        if ($transHour !== null && $transHour >= 9 && $transHour <= 17) {
            return 10;
        }

        return 5; // Partial credit for outside hours
    }

    // Helper methods for breakdown details

    private function getAmountDetails($transaction, $record, $type)
    {
           $transAmount = $transaction->transaction_amount ?? 0;
        $recordAmount = match($type) {
            'store_deposit' => $record->actual_cash_total ?? 0,
            'retail_order', 'wholesale_order' => $record->total_price ?? 0,
            'eftpos' => $record->settlement_amount ?? 0,
            default => 0
        };

        $diff = abs($transAmount - $recordAmount);

        if ($transAmount == $recordAmount) {
            return "Exact amount match: $" . number_format($transAmount, 2);
        }

        return sprintf(
            "Amount difference: $%.2f (Transaction: $%.2f, Record: $%.2f)",
            $diff,
            $transAmount,
            $recordAmount
        );
    }

    private function getDateDetails($transaction, $record, $type)
    {
           $transDate = isset($transaction->transaction_date) ? new \DateTime($transaction->transaction_date) : null;
        $recordDateStr = match($type) {
            'store_deposit' => $record->created ?? null,
            'retail_order', 'wholesale_order' => $record->date_ordered ?? null,
            'eftpos' => $record->settlement_date ?? null,
            default => null
        };

        if (!$recordDateStr) {
            return "Record date not available";
        }

        $recordDate = new \DateTime($recordDateStr);
        $daysDiff = abs($transDate->diff($recordDate)->days);

        if ($daysDiff == 0) {
            return "Same day: " . $transDate->format('Y-m-d');
        }

        return sprintf(
            "%d days apart (Transaction: %s, Record: %s)",
            $daysDiff,
            $transDate->format('Y-m-d'),
            $recordDate->format('Y-m-d')
        );
    }

    private function getNameDetails($transaction, $record, $type)
    {
        $transName = $transaction->transaction_reference ?? '';
        $recordName = match($type) {
            'store_deposit' => $record->reference ?? '',
            'retail_order', 'wholesale_order' => ($record->first_name ?? '') . ' ' . ($record->last_name ?? ''),
            'eftpos' => $record->terminal_id ?? '',
            default => ''
        };

        $matchingEngine = new MatchingEngine();
        $similarity = $matchingEngine->fuzzyNameMatch($transName, $recordName);

        return sprintf(
            "Name similarity: %.1f%% (Transaction: '%s', Record: '%s')",
            $similarity * 100,
            substr($transName, 0, 30),
            substr($recordName, 0, 30)
        );
    }

    private function getReferenceDetails($transaction, $record, $type)
    {
        $ref = $transaction->transaction_reference ?? '';

        if ($type === 'retail_order' || $type === 'wholesale_order') {
            $orderId = $record->order_id ?? '';
            if (strpos($ref, (string)$orderId) !== false) {
                return "Order ID found in reference: #$orderId";
            }
            return "Order ID #$orderId not found in reference";
        }

        return "Reference: " . substr($ref, 0, 50);
    }

    private function getTypeDetails($transaction, $candidate)
    {
        return "Transaction type: " . $candidate['type'];
    }

    private function getOutletDetails($transaction, $record, $type)
    {
        return "Outlet matching (simplified)";
    }

    private function getTimingDetails($transaction, $record, $type)
    {
        $transTime = isset($transaction->transaction_date) ? date('H:i', strtotime($transaction->transaction_date)) : 'Unknown';
        return "Transaction time: $transTime";
    }
}
