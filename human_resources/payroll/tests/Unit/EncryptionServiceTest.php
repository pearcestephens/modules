<?php

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;
use HumanResources\Payroll\Services\EncryptionService;
use RuntimeException;

/**
 * EncryptionService Tests
 * 
 * Comprehensive test suite for AES-256-GCM encryption service.
 * 
 * Test Coverage:
 * - Key validation (size, format, base64)
 * - Encryption produces different output (random IV)
 * - Decryption recovers original plaintext
 * - Tamper detection (GCM authentication)
 * - Empty/null input handling
 * - Large data encryption (OAuth tokens)
 * - isEncrypted() heuristic check
 * 
 * @package HumanResources\Payroll\Tests\Unit
 * @version 1.0.0
 */
class EncryptionServiceTest extends TestCase
{
    private string $validKey;
    
    protected function setUp(): void
    {
        // Generate fresh key for each test
        $this->validKey = EncryptionService::generateKey();
    }
    
    /**
     * Test 1: Valid key accepted
     */
    public function testConstructorAcceptsValidKey(): void
    {
        $service = new EncryptionService($this->validKey);
        
        $this->assertInstanceOf(EncryptionService::class, $service);
        $this->assertEquals('aes-256-gcm', $service->getCipher());
        $this->assertEquals(12, $service->getIvLength());
        $this->assertEquals(16, $service->getTagLength());
    }
    
    /**
     * Test 2: Empty key rejected
     */
    public function testConstructorRejectsEmptyKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key missing');
        
        new EncryptionService('');
    }
    
    /**
     * Test 3: Invalid base64 rejected
     */
    public function testConstructorRejectsInvalidBase64(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not valid base64');
        
        new EncryptionService('not-valid-base64!!!');
    }
    
    /**
     * Test 4: Wrong key size rejected (too short)
     */
    public function testConstructorRejectsShortKey(): void
    {
        $shortKey = base64_encode(random_bytes(16)); // Only 16 bytes (AES-128)
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be 32 bytes');
        
        new EncryptionService($shortKey);
    }
    
    /**
     * Test 5: Wrong key size rejected (too long)
     */
    public function testConstructorRejectsLongKey(): void
    {
        $longKey = base64_encode(random_bytes(64)); // 64 bytes (too long)
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be 32 bytes');
        
        new EncryptionService($longKey);
    }
    
    /**
     * Test 6: Encryption produces base64 output
     */
    public function testEncryptProducesBase64Output(): void
    {
        $service = new EncryptionService($this->validKey);
        $plaintext = 'sensitive OAuth token';
        
        $encrypted = $service->encrypt($plaintext);
        
        // Should be valid base64
        $this->assertNotFalse(base64_decode($encrypted, true));
        
        // Should be longer than plaintext (IV + ciphertext + tag)
        $this->assertGreaterThan(strlen($plaintext), strlen($encrypted));
    }
    
    /**
     * Test 7: Encryption produces different output each time (random IV)
     */
    public function testEncryptionProducesDifferentOutputEachTime(): void
    {
        $service = new EncryptionService($this->validKey);
        $plaintext = 'same plaintext repeated';
        
        $encrypted1 = $service->encrypt($plaintext);
        $encrypted2 = $service->encrypt($plaintext);
        $encrypted3 = $service->encrypt($plaintext);
        
        // Should all be different (random IV)
        $this->assertNotEquals($encrypted1, $encrypted2);
        $this->assertNotEquals($encrypted2, $encrypted3);
        $this->assertNotEquals($encrypted1, $encrypted3);
    }
    
    /**
     * Test 8: Decryption recovers original plaintext
     */
    public function testDecryptionRecoversOriginalPlaintext(): void
    {
        $service = new EncryptionService($this->validKey);
        $plaintext = 'OAuth access token: ya29.a0AfH6SMBx...';
        
        $encrypted = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($encrypted);
        
        $this->assertEquals($plaintext, $decrypted);
    }
    
    /**
     * Test 9: Encryption/decryption round-trip (multiple plaintexts)
     */
    public function testEncryptionDecryptionRoundTrip(): void
    {
        $service = new EncryptionService($this->validKey);
        
        $testCases = [
            'short',
            'Medium length OAuth token with special chars: !@#$%^&*()',
            str_repeat('Long token ', 100), // 1100 chars
            'Unicode: 日本語 🎉 émojis',
            '{"json":"token","expires":1234567890}',
        ];
        
        foreach ($testCases as $plaintext) {
            $encrypted = $service->encrypt($plaintext);
            $decrypted = $service->decrypt($encrypted);
            
            $this->assertEquals($plaintext, $decrypted, "Round-trip failed for: {$plaintext}");
        }
    }
    
    /**
     * Test 10: Decryption with wrong key fails
     */
    public function testDecryptionWithWrongKeyFails(): void
    {
        $service1 = new EncryptionService($this->validKey);
        $service2 = new EncryptionService(EncryptionService::generateKey()); // Different key
        
        $plaintext = 'secret data';
        $encrypted = $service1->encrypt($plaintext);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        
        $service2->decrypt($encrypted); // Wrong key
    }
    
    /**
     * Test 11: Tampered ciphertext detected (GCM authentication)
     */
    public function testTamperedCiphertextDetected(): void
    {
        $service = new EncryptionService($this->validKey);
        $plaintext = 'authentic data';
        
        $encrypted = $service->encrypt($plaintext);
        $encryptedBytes = base64_decode($encrypted);
        
        // Tamper with ciphertext (flip a bit in the middle)
        $tamperedBytes = $encryptedBytes;
        $tamperedBytes[20] = chr(ord($tamperedBytes[20]) ^ 0xFF);
        $tamperedEncrypted = base64_encode($tamperedBytes);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        
        $service->decrypt($tamperedEncrypted);
    }
    
    /**
     * Test 12: Empty plaintext rejected
     */
    public function testEncryptRejectsEmptyString(): void
    {
        $service = new EncryptionService($this->validKey);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot encrypt empty string');
        
        $service->encrypt('');
    }
    
    /**
     * Test 13: Empty ciphertext rejected
     */
    public function testDecryptRejectsEmptyString(): void
    {
        $service = new EncryptionService($this->validKey);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot decrypt empty string');
        
        $service->decrypt('');
    }
    
    /**
     * Test 14: Invalid base64 ciphertext rejected
     */
    public function testDecryptRejectsInvalidBase64(): void
    {
        $service = new EncryptionService($this->validKey);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid base64 encoding');
        
        $service->decrypt('not-valid-base64!!!');
    }
    
    /**
     * Test 15: Too short ciphertext rejected
     */
    public function testDecryptRejectsTooShortCiphertext(): void
    {
        $service = new EncryptionService($this->validKey);
        
        // Create ciphertext shorter than IV + Tag (12 + 16 = 28 bytes)
        $tooShort = base64_encode(random_bytes(20));
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('too short');
        
        $service->decrypt($tooShort);
    }
    
    /**
     * Test 16: Large data encryption (OAuth token size)
     */
    public function testLargeDataEncryption(): void
    {
        $service = new EncryptionService($this->validKey);
        
        // Typical OAuth access token: 1000-2000 chars
        $largeToken = str_repeat('x', 2000);
        
        $encrypted = $service->encrypt($largeToken);
        $decrypted = $service->decrypt($encrypted);
        
        $this->assertEquals($largeToken, $decrypted);
        $this->assertEquals(2000, strlen($decrypted));
    }
    
    /**
     * Test 17: isEncrypted detects encrypted data
     */
    public function testIsEncryptedDetectsEncryptedData(): void
    {
        $service = new EncryptionService($this->validKey);
        $plaintext = 'OAuth token';
        
        $encrypted = $service->encrypt($plaintext);
        
        $this->assertTrue($service->isEncrypted($encrypted));
        $this->assertFalse($service->isEncrypted($plaintext));
    }
    
    /**
     * Test 18: isEncrypted handles null/empty
     */
    public function testIsEncryptedHandlesNullAndEmpty(): void
    {
        $service = new EncryptionService($this->validKey);
        
        $this->assertFalse($service->isEncrypted(null));
        $this->assertFalse($service->isEncrypted(''));
    }
    
    /**
     * Test 19: isEncrypted detects plaintext
     */
    public function testIsEncryptedDetectsPlaintext(): void
    {
        $service = new EncryptionService($this->validKey);
        
        $plaintextCases = [
            'simple token',
            'ya29.a0AfH6SMBx...',
            '{"json":"token"}',
            str_repeat('x', 100),
        ];
        
        foreach ($plaintextCases as $plaintext) {
            $this->assertFalse($service->isEncrypted($plaintext), "Should detect plaintext: {$plaintext}");
        }
    }
    
    /**
     * Test 20: isValidKey static method
     */
    public function testIsValidKeyStaticMethod(): void
    {
        $validKey = EncryptionService::generateKey();
        $this->assertTrue(EncryptionService::isValidKey($validKey));
        
        $this->assertFalse(EncryptionService::isValidKey(''));
        $this->assertFalse(EncryptionService::isValidKey('not-base64!!!'));
        $this->assertFalse(EncryptionService::isValidKey(base64_encode(random_bytes(16)))); // Wrong size
    }
    
    /**
     * Test 21: generateKey produces valid keys
     */
    public function testGenerateKeyProducesValidKeys(): void
    {
        $key1 = EncryptionService::generateKey();
        $key2 = EncryptionService::generateKey();
        $key3 = EncryptionService::generateKey();
        
        // Should all be valid
        $this->assertTrue(EncryptionService::isValidKey($key1));
        $this->assertTrue(EncryptionService::isValidKey($key2));
        $this->assertTrue(EncryptionService::isValidKey($key3));
        
        // Should all be different (random)
        $this->assertNotEquals($key1, $key2);
        $this->assertNotEquals($key2, $key3);
        $this->assertNotEquals($key1, $key3);
        
        // Should all be 44 chars (base64 of 32 bytes)
        $this->assertEquals(44, strlen($key1));
        $this->assertEquals(44, strlen($key2));
        $this->assertEquals(44, strlen($key3));
    }
    
    /**
     * Test 22: Multiple service instances with same key decrypt each other's data
     */
    public function testMultipleInstancesWithSameKey(): void
    {
        $service1 = new EncryptionService($this->validKey);
        $service2 = new EncryptionService($this->validKey); // Same key
        
        $plaintext = 'shared secret';
        
        $encrypted1 = $service1->encrypt($plaintext);
        $encrypted2 = $service2->encrypt($plaintext);
        
        // Different instances should decrypt each other's data
        $this->assertEquals($plaintext, $service2->decrypt($encrypted1));
        $this->assertEquals($plaintext, $service1->decrypt($encrypted2));
    }
    
    /**
     * Test 23: Encrypted data is database-safe (VARCHAR compatible)
     */
    public function testEncryptedDataIsDatabaseSafe(): void
    {
        $service = new EncryptionService($this->validKey);
        $plaintext = 'OAuth token with special chars: !@#$%^&*()';
        
        $encrypted = $service->encrypt($plaintext);
        
        // Should only contain base64 chars: A-Za-z0-9+/=
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $encrypted);
        
        // Should fit in VARCHAR(2000) (typical OAuth token column)
        $this->assertLessThan(2000, strlen($encrypted));
    }
}
