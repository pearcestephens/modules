<?php declare(strict_types=1);

namespace Consignments\Tests\Unit\Infra\Lightspeed;

use Consignments\Domain\ValueObjects\Status;
use Consignments\Infra\Lightspeed\StatusMap;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Lightspeed Status Mapping
 */
final class StatusMapTest extends TestCase
{
    /**
     * Test CIS → Lightspeed mappings
     */
    public function testToLightspeedMappings(): void
    {
        $this->assertEquals('OPEN', StatusMap::toLightspeed(Status::draft()));
        $this->assertEquals('SENT', StatusMap::toLightspeed(Status::sent()));
        $this->assertEquals('DISPATCHED', StatusMap::toLightspeed(Status::receiving()));
        $this->assertEquals('RECEIVED', StatusMap::toLightspeed(Status::received()));
        $this->assertEquals('RECEIVED', StatusMap::toLightspeed(Status::completed())); // Both map to RECEIVED
        $this->assertEquals('CANCELLED', StatusMap::toLightspeed(Status::cancelled()));
    }

    /**
     * Test Lightspeed → CIS mappings
     */
    public function testToInternalMappings(): void
    {
        $this->assertEquals('draft', StatusMap::toInternal('OPEN')->toString());
        $this->assertEquals('sent', StatusMap::toInternal('SENT')->toString());
        $this->assertEquals('receiving', StatusMap::toInternal('DISPATCHED')->toString());
        $this->assertEquals('received', StatusMap::toInternal('RECEIVED')->toString()); // Not completed
        $this->assertEquals('cancelled', StatusMap::toInternal('CANCELLED')->toString());
    }

    /**
     * Test case insensitive and whitespace handling
     */
    public function testToInternalNormalization(): void
    {
        $this->assertEquals('sent', StatusMap::toInternal('sent')->toString());
        $this->assertEquals('sent', StatusMap::toInternal('  SENT  ')->toString());
        $this->assertEquals('sent', StatusMap::toInternal('SeNt')->toString());
    }

    /**
     * Test unknown Lightspeed status falls back to draft
     */
    public function testUnknownLightspeedStatusFallsBackToDraft(): void
    {
        $status = StatusMap::toInternal('UNKNOWN_STATUS');
        $this->assertEquals('draft', $status->toString());
    }

    /**
     * Test validation
     */
    public function testIsValidLightspeedStatus(): void
    {
        $this->assertTrue(StatusMap::isValidLightspeedStatus('OPEN'));
        $this->assertTrue(StatusMap::isValidLightspeedStatus('sent'));
        $this->assertTrue(StatusMap::isValidLightspeedStatus('  RECEIVED  '));
        $this->assertFalse(StatusMap::isValidLightspeedStatus('INVALID'));
    }

    /**
     * Test round-trip conversion (except completed)
     */
    public function testRoundTripConversion(): void
    {
        $statuses = [
            Status::draft(),
            Status::sent(),
            Status::receiving(),
            Status::received(),
            Status::cancelled(),
        ];

        foreach ($statuses as $original) {
            $ls = StatusMap::toLightspeed($original);
            $roundTrip = StatusMap::toInternal($ls);
            $this->assertEquals($original->toString(), $roundTrip->toString());
        }

        // Completed is special: CIS→LS→CIS becomes received (not completed)
        $ls = StatusMap::toLightspeed(Status::completed());
        $this->assertEquals('RECEIVED', $ls);
        $roundTrip = StatusMap::toInternal($ls);
        $this->assertEquals('received', $roundTrip->toString()); // Not completed
    }

    /**
     * Test helper methods
     */
    public function testGetLightspeedStatuses(): void
    {
        $statuses = StatusMap::getLightspeedStatuses();
        $this->assertContains('OPEN', $statuses);
        $this->assertContains('SENT', $statuses);
        $this->assertContains('DISPATCHED', $statuses);
        $this->assertContains('RECEIVED', $statuses);
        $this->assertContains('CANCELLED', $statuses);
        $this->assertCount(5, $statuses);
    }

    public function testGetLabel(): void
    {
        $this->assertEquals('Draft', StatusMap::getLabel(Status::draft()));
        $this->assertEquals('Sent', StatusMap::getLabel(Status::sent()));
        $this->assertEquals('Completed', StatusMap::getLabel(Status::completed()));
    }

    public function testGetCssClass(): void
    {
        $this->assertEquals('badge-secondary', StatusMap::getCssClass(Status::draft()));
        $this->assertEquals('badge-info', StatusMap::getCssClass(Status::sent()));
        $this->assertEquals('badge-success', StatusMap::getCssClass(Status::completed()));
        $this->assertEquals('badge-danger', StatusMap::getCssClass(Status::cancelled()));
    }
}
