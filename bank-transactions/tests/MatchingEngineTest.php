<?php

use PHPUnit\Framework\TestCase;
use CIS\BankTransactions\Lib\MatchingEngine;

class MatchingEngineTest extends TestCase
{
    private $matchingEngine;

    protected function setUp(): void
    {
        $this->matchingEngine = new MatchingEngine();
    }

    public function testFindPotentialMatches(): void
    {
        // Using actual database field names from bank_transactions_current table
        $transaction = (object) [
            'id' => 1,
            'transaction_amount' => 100.00,
            'transaction_date' => '2025-11-13',
            'transaction_reference' => 'INV12345',
            'transaction_name' => 'Test Transaction'
        ];

        $matches = $this->matchingEngine->findMatches($transaction);

        $this->assertIsArray($matches);
    }
}
