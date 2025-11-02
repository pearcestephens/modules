<?php
declare(strict_types=1);

namespace Consignments\Tests;

use PHPUnit\Framework\TestCase;
use Consignments\Lib\Idempotency;

final class IdempotencyTest extends TestCase
{
    public function testSamePayloadProducesSameHash(): void
    {
        $hash1 = Idempotency::hashFor('POST', '/api/consignments', ['id' => 123, 'name' => 'test']);
        $hash2 = Idempotency::hashFor('POST', '/api/consignments', ['id' => 123, 'name' => 'test']);
        
        $this->assertSame($hash1, $hash2);
    }

    public function testPermutedKeysProduceSameHash(): void
    {
        $hash1 = Idempotency::hashFor('POST', '/api/consignments', ['id' => 123, 'name' => 'test', 'status' => 'active']);
        $hash2 = Idempotency::hashFor('POST', '/api/consignments', ['name' => 'test', 'status' => 'active', 'id' => 123]);
        
        $this->assertSame($hash1, $hash2);
    }

    public function testChangedValueProducesDifferentHash(): void
    {
        $hash1 = Idempotency::hashFor('POST', '/api/consignments', ['id' => 123, 'name' => 'test']);
        $hash2 = Idempotency::hashFor('POST', '/api/consignments', ['id' => 123, 'name' => 'different']);
        
        $this->assertNotSame($hash1, $hash2);
    }

    public function testDifferentMethodProducesDifferentHash(): void
    {
        $hash1 = Idempotency::hashFor('POST', '/api/consignments', ['id' => 123]);
        $hash2 = Idempotency::hashFor('PUT', '/api/consignments', ['id' => 123]);
        
        $this->assertNotSame($hash1, $hash2);
    }

    public function testDifferentPathProducesDifferentHash(): void
    {
        $hash1 = Idempotency::hashFor('POST', '/api/consignments', ['id' => 123]);
        $hash2 = Idempotency::hashFor('POST', '/api/orders', ['id' => 123]);
        
        $this->assertNotSame($hash1, $hash2);
    }

    public function testEmptyPayloadProducesConsistentHash(): void
    {
        $hash1 = Idempotency::hashFor('GET', '/api/consignments', []);
        $hash2 = Idempotency::hashFor('GET', '/api/consignments', []);
        
        $this->assertSame($hash1, $hash2);
    }

    public function testMethodIsCaseInsensitive(): void
    {
        $hash1 = Idempotency::hashFor('post', '/api/consignments', ['id' => 123]);
        $hash2 = Idempotency::hashFor('POST', '/api/consignments', ['id' => 123]);
        
        $this->assertSame($hash1, $hash2);
    }

    public function testNestedArraysAreHandledConsistently(): void
    {
        $payload = ['items' => [['id' => 1], ['id' => 2]], 'name' => 'test'];
        $hash1 = Idempotency::hashFor('POST', '/api/consignments', $payload);
        $hash2 = Idempotency::hashFor('POST', '/api/consignments', $payload);
        
        $this->assertSame($hash1, $hash2);
    }

    public function testHashIsHex64Characters(): void
    {
        $hash = Idempotency::hashFor('POST', '/api/consignments', ['id' => 123]);
        
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }
}
