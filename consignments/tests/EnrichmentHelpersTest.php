<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EnrichmentHelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../shared/functions/transfers.php';
    }

    public function testCountsByStateReturnsArray(): void
    {
        $counts = getTransferCountsByState('STOCK');
        $this->assertIsArray($counts);
        $this->assertArrayHasKey('TOTAL', $counts);
    }

    public function testRecentTransfersReturnsArray(): void
    {
        $rows = getRecentTransfersEnrichedDB(1, 'STOCK');
        $this->assertIsArray($rows);
        if (!empty($rows)) {
            $first = $rows[0];
            $this->assertArrayHasKey('cis_internal_id', $first);
            $this->assertArrayHasKey('consignment_number', $first);
        }
    }
}
