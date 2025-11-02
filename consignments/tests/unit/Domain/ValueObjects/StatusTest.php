<?php declare(strict_types=1);

namespace Consignments\Tests\Unit\Domain\ValueObjects;

use Consignments\Domain\ValueObjects\Status;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Status Value Object
 */
final class StatusTest extends TestCase
{
    public function testCanCreateValidStatuses(): void
    {
        $this->assertEquals('draft', Status::draft()->toString());
        $this->assertEquals('sent', Status::sent()->toString());
        $this->assertEquals('receiving', Status::receiving()->toString());
        $this->assertEquals('received', Status::received()->toString());
        $this->assertEquals('completed', Status::completed()->toString());
        $this->assertEquals('cancelled', Status::cancelled()->toString());
    }

    public function testCanCreateFromString(): void
    {
        $status = Status::fromString('draft');
        $this->assertEquals('draft', $status->toString());
    }

    public function testThrowsOnInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status "invalid"');

        Status::fromString('invalid');
    }

    public function testStatusEquality(): void
    {
        $status1 = Status::draft();
        $status2 = Status::draft();
        $status3 = Status::sent();

        $this->assertTrue($status1->equals($status2));
        $this->assertFalse($status1->equals($status3));
    }

    public function testStatusCheckers(): void
    {
        $draft = Status::draft();
        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isSent());
        $this->assertFalse($draft->isFinal());

        $completed = Status::completed();
        $this->assertTrue($completed->isCompleted());
        $this->assertTrue($completed->isFinal());

        $cancelled = Status::cancelled();
        $this->assertTrue($cancelled->isCancelled());
        $this->assertTrue($cancelled->isFinal());
    }

    public function testToString(): void
    {
        $status = Status::draft();
        $this->assertEquals('draft', (string) $status);
    }
}
