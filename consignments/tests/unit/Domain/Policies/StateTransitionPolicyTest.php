<?php declare(strict_types=1);

namespace Consignments\Tests\Unit\Domain\Policies;

use Consignments\Domain\ValueObjects\Status;
use Consignments\Domain\Policies\StateTransitionPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for State Transition Policy
 */
final class StateTransitionPolicyTest extends TestCase
{
    /**
     * Test all valid transitions
     */
    public function testAllowedTransitions(): void
    {
        // draft → sent
        StateTransitionPolicy::assertAllowed(Status::draft(), Status::sent());
        $this->assertTrue(true); // No exception thrown
        
        // draft → cancelled
        StateTransitionPolicy::assertAllowed(Status::draft(), Status::cancelled());
        $this->assertTrue(true);
        
        // sent → receiving
        StateTransitionPolicy::assertAllowed(Status::sent(), Status::receiving());
        $this->assertTrue(true);
        
        // sent → cancelled
        StateTransitionPolicy::assertAllowed(Status::sent(), Status::cancelled());
        $this->assertTrue(true);
        
        // receiving → received
        StateTransitionPolicy::assertAllowed(Status::receiving(), Status::received());
        $this->assertTrue(true);
        
        // receiving → cancelled
        StateTransitionPolicy::assertAllowed(Status::receiving(), Status::cancelled());
        $this->assertTrue(true);
        
        // received → completed
        StateTransitionPolicy::assertAllowed(Status::received(), Status::completed());
        $this->assertTrue(true);
    }
    
    /**
     * Test illegal transitions (should throw)
     */
    public function testIllegalTransitionDraftToCompleted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Illegal state transition: draft → completed');
        
        StateTransitionPolicy::assertAllowed(Status::draft(), Status::completed());
    }
    
    public function testIllegalTransitionDraftToReceiving(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Illegal state transition: draft → receiving');
        
        StateTransitionPolicy::assertAllowed(Status::draft(), Status::receiving());
    }
    
    public function testIllegalTransitionCompletedToAnything(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('terminal state');
        
        StateTransitionPolicy::assertAllowed(Status::completed(), Status::draft());
    }
    
    public function testIllegalTransitionCancelledToAnything(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        StateTransitionPolicy::assertAllowed(Status::cancelled(), Status::sent());
    }
    
    /**
     * Test idempotent same-state transitions (always allowed)
     */
    public function testSameStateIsAllowed(): void
    {
        StateTransitionPolicy::assertAllowed(Status::draft(), Status::draft());
        StateTransitionPolicy::assertAllowed(Status::sent(), Status::sent());
        StateTransitionPolicy::assertAllowed(Status::completed(), Status::completed());
        $this->assertTrue(true);
    }
    
    /**
     * Test isAllowed method (non-throwing)
     */
    public function testIsAllowedMethod(): void
    {
        $this->assertTrue(StateTransitionPolicy::isAllowed(Status::draft(), Status::sent()));
        $this->assertFalse(StateTransitionPolicy::isAllowed(Status::draft(), Status::completed()));
        $this->assertTrue(StateTransitionPolicy::isAllowed(Status::draft(), Status::draft()));
    }
    
    /**
     * Test getAllowedTransitions
     */
    public function testGetAllowedTransitions(): void
    {
        $allowed = StateTransitionPolicy::getAllowedTransitions(Status::draft());
        $this->assertContains('sent', $allowed);
        $this->assertContains('cancelled', $allowed);
        $this->assertCount(2, $allowed);
        
        $terminalAllowed = StateTransitionPolicy::getAllowedTransitions(Status::completed());
        $this->assertEmpty($terminalAllowed);
    }
    
    /**
     * Test validate method (returns structured response)
     */
    public function testValidateMethod(): void
    {
        $result = StateTransitionPolicy::validate(Status::draft(), Status::sent());
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
        
        $result = StateTransitionPolicy::validate(Status::draft(), Status::completed());
        $this->assertFalse($result['valid']);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('Illegal state transition', $result['error']);
    }
}
