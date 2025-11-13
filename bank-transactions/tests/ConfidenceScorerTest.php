<?php

use PHPUnit\Framework\TestCase;
use CIS\BankTransactions\Lib\ConfidenceScorer;

class ConfidenceScorerTest extends TestCase
{
    private $confidenceScorer;

    protected function setUp(): void
    {
        $this->confidenceScorer = new ConfidenceScorer();
    }

    public function testScoreMatch(): void
    {
        // Using actual database field names from bank_transactions_current table
        $transaction = (object) [
            'transaction_amount' => 100.00,
            'transaction_date' => '2025-11-13',
            'transaction_reference' => 'INV12345',
            'transaction_name' => 'Test Store Deposit'
        ];

        $candidate = [
            'type' => 'store_deposit',
            'record' => (object) [
                'actual_cash_total' => 100.00,
                'created' => '2025-11-13',
                'reference' => 'INV12345'
            ]
        ];

        $score = $this->confidenceScorer->scoreMatch($transaction, $candidate);

        $this->assertIsInt($score);
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual(300, $score);
    }
}
