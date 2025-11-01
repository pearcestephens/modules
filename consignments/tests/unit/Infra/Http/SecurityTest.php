<?php declare(strict_types=1);

namespace Consignments\Tests\Unit\Infra\Http;

use Consignments\Infra\Http\Security;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Security Helper
 */
final class SecurityTest extends TestCase
{
    private string $testBaseDir;

    protected function setUp(): void
    {
        $this->testBaseDir = sys_get_temp_dir() . '/consignments_test_' . uniqid();
        mkdir($this->testBaseDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testBaseDir)) {
            rmdir($this->testBaseDir);
        }
    }

    public function testSecurePathAllowsValidPath(): void
    {
        $safePath = Security::securePath('subdir/file.txt', $this->testBaseDir);
        $this->assertStringStartsWith($this->testBaseDir, $safePath);
        $this->assertStringContainsString('subdir/file.txt', $safePath);
    }

    public function testSecurePathRejectsDoubleDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path traversal detected');

        Security::securePath('../../../etc/passwd', $this->testBaseDir);
    }

    public function testSecurePathRejectsNullByte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Null byte');

        Security::securePath("file.txt\0.jpg", $this->testBaseDir);
    }

    public function testSecurePathEnforcesExtensionWhitelist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File extension');

        Security::securePath('file.exe', $this->testBaseDir, ['jpg', 'png', 'pdf']);
    }

    public function testSecurePathAllowsWhitelistedExtension(): void
    {
        $safePath = Security::securePath('file.jpg', $this->testBaseDir, ['jpg', 'png']);
        $this->assertStringContainsString('file.jpg', $safePath);
    }

    public function testVerifyCsrfThrowsOnMissingToken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSRF token missing from request');

        Security::verifyCsrf('', 'session_token_here');
    }

    public function testVerifyCsrfThrowsOnMismatch(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSRF token validation failed');

        Security::verifyCsrf('wrong_token', 'correct_token');
    }

    public function testVerifyCsrfSucceedsOnMatch(): void
    {
        $token = 'test_token_12345';
        Security::verifyCsrf($token, $token);
        $this->assertTrue(true); // No exception = success
    }

    public function testEscapeHtmlSanitizesSpecialChars(): void
    {
        $dirty = '<script>alert("XSS")</script>';
        $clean = Security::escapeHtml($dirty);

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringContainsString('&lt;script&gt;', $clean);
    }

    public function testEscapeJsEncodesForJsContext(): void
    {
        $value = "'; alert('XSS'); //";
        $encoded = Security::escapeJs($value);

        $this->assertStringStartsWith('"', $encoded);
        $this->assertStringEndsWith('"', $encoded);
        $this->assertStringNotContainsString("';", $encoded);
    }

    public function testRequireMethodThrowsOnMismatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Method POST required');

        Security::requireMethod('POST');
    }

    public function testRequireMethodSucceedsOnMatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        Security::requireMethod('POST');
        $this->assertTrue(true); // No exception = success
    }
}
