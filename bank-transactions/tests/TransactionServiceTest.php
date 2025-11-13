<?php

use PHPUnit\Framework\TestCase;
use CIS\BankTransactions\Lib\TransactionService;

class TransactionServiceTest extends TestCase
{
    private $transactionService;

    protected function setUp(): void
    {
        $mockDb = $this->createMock(\PDO::class);
        $this->transactionService = new TransactionService($mockDb);
    }

    public function testClassExists(): void
    {
        $this->assertInstanceOf(TransactionService::class, $this->transactionService);
    }

    public function testImportTransactionsMethodExists(): void
    {
        // Verify the method exists with correct signature
        $this->assertTrue(method_exists($this->transactionService, 'importTransactions'));

        $reflection = new \ReflectionMethod($this->transactionService, 'importTransactions');
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals(1, $reflection->getNumberOfParameters());
    }

    public function testGetStatisticsMethodExists(): void
    {
        // Verify the method exists
        $this->assertTrue(method_exists($this->transactionService, 'getStatistics'));

        $reflection = new \ReflectionMethod($this->transactionService, 'getStatistics');
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals(0, $reflection->getNumberOfParameters());
    }

    public function testAutoMatchTransactionsMethodExists(): void
    {
        // Verify core methods exist
        $this->assertTrue(method_exists($this->transactionService, 'autoMatchTransactions'));
        $this->assertTrue(method_exists($this->transactionService, 'getTransactionsForReview'));
        $this->assertTrue(method_exists($this->transactionService, 'markForReview'));
        $this->assertTrue(method_exists($this->transactionService, 'exportTransactions'));
    }
}
