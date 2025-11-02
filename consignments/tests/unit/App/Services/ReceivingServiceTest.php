<?php
/**
 * Unit Tests for ReceivingService
 *
 * @package Consignments\Tests\Unit\App\Services
 */

declare(strict_types=1);

namespace Consignments\Tests\Unit\App\Services;

use Consignments\App\Services\ReceivingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReceivingServiceTest extends TestCase
{
    private \PDO $mockPdo;
    private \PDOStatement $mockStmt;
    private LoggerInterface $mockLogger;
    private ReceivingService $service;
    private string $tempUploadPath;

    protected function setUp(): void
    {
        $this->mockPdo = $this->createMock(\PDO::class);
        $this->mockStmt = $this->createMock(\PDOStatement::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        // Create temp directory for uploads
        $this->tempUploadPath = sys_get_temp_dir() . '/test_receiving_' . uniqid();
        mkdir($this->tempUploadPath, 0755, true);

        $this->service = new ReceivingService($this->mockPdo, $this->mockLogger, $this->tempUploadPath);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempUploadPath)) {
            $this->recursiveRemoveDirectory($this->tempUploadPath);
        }
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ========================================================================
    // uploadPhoto() Tests
    // ========================================================================

    public function testUploadPhotoRequiresValidFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file upload');

        $this->service->uploadPhoto(1, 1, ['error' => null]);
    }

    public function testUploadPhotoRejectsOversizedFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File exceeds maximum size');

        // Create temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, str_repeat('a', 6 * 1024 * 1024)); // 6MB

        $file = [
            'name' => 'large.jpg',
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 6 * 1024 * 1024,
        ];

        try {
            $this->service->uploadPhoto(1, 1, $file);
        } finally {
            if (file_exists($tempFile)) unlink($tempFile);
        }
    }

    public function testUploadPhotoRejectsInvalidFileType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        // Create temp PHP file (not allowed)
        $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.php';
        file_put_contents($tempFile, '<?php echo "test"; ?>');

        $file = [
            'name' => 'malicious.php',
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tempFile),
        ];

        try {
            $this->service->uploadPhoto(1, 1, $file);
        } finally {
            if (file_exists($tempFile)) unlink($tempFile);
        }
    }

    public function testUploadPhotoRejectsInvalidExtension(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file extension');

        // Create temp file with valid JPEG content but .exe extension
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        // Minimal JPEG header
        file_put_contents($tempFile, "\xFF\xD8\xFF\xE0\x00\x10JFIF");

        $file = [
            'name' => 'malicious.exe',
            'tmp_name' => $tempFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tempFile),
        ];

        try {
            $this->service->uploadPhoto(1, 1, $file);
        } finally {
            if (file_exists($tempFile)) unlink($tempFile);
        }
    }

    public function testUploadPhotoPreventsPathTraversal(): void
    {
        // Test the filename sanitization using reflection
        $reflection = new \ReflectionClass(ReceivingService::class);
        $method = $reflection->getMethod('generateSafeFilename');
        $method->setAccessible(true);

        $safeFilename = $method->invoke($this->service, '../../etc/passwd.jpg');

        // Verify filename doesn't contain ../
        $this->assertStringNotContainsString('..', $safeFilename);
        $this->assertStringNotContainsString('/', $safeFilename);
        $this->assertStringEndsWith('.jpg', $safeFilename);
    }

    // ========================================================================
    // captureSignature() Tests
    // ========================================================================

    public function testCaptureSignatureRequiresData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature data is required');

        // Mock transfer exists check
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function ($sql) {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['id' => 1]);
                return $stmt;
            });

        $this->service->captureSignature(1, '');
    }

    public function testCaptureSignatureRejectsInvalidBase64(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid base64 signature data');

        // Mock transfer exists
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function ($sql) {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['id' => 1]);
                return $stmt;
            });

        $this->service->captureSignature(1, 'not-valid-base64!!!');
    }

    public function testCaptureSignatureRejectsNonImageData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature image type');

        // Mock transfer exists
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function ($sql) {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['id' => 1]);
                return $stmt;
            });

        // Base64 encode text (not an image)
        $this->service->captureSignature(1, base64_encode('This is not an image'));
    }

    public function testCaptureSignatureSucceedsWithValidPng(): void
    {
        // Create minimal PNG
        $pngData = $this->createMinimalPng();
        $base64 = base64_encode($pngData);

        // Mock transfer exists and insert
        $callCount = 0;
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function ($sql) use (&$callCount) {
                $callCount++;
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                if ($callCount === 1) {
                    // First call: check transfer exists
                    $stmt->method('fetch')->willReturn(['id' => 1]);
                } else {
                    // Second call: insert evidence
                    $stmt->method('fetch')->willReturn(null);
                }

                return $stmt;
            });

        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('456');

        $result = $this->service->captureSignature(1, $base64);

        $this->assertArrayHasKey('evidence_id', $result);
        $this->assertArrayHasKey('file_path', $result);
        $this->assertEquals(456, $result['evidence_id']);
        $this->assertStringStartsWith('signature_', $result['filename']);
        $this->assertFileExists($result['file_path']);
    }

    public function testCaptureSignatureHandlesDataUriPrefix(): void
    {
        // Create minimal PNG with data URI prefix
        $pngData = $this->createMinimalPng();
        $dataUri = 'data:image/png;base64,' . base64_encode($pngData);

        // Mock transfer exists and insert
        $callCount = 0;
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function ($sql) use (&$callCount) {
                $callCount++;
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                if ($callCount === 1) {
                    $stmt->method('fetch')->willReturn(['id' => 1]);
                } else {
                    $stmt->method('fetch')->willReturn(null);
                }

                return $stmt;
            });

        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('789');

        $result = $this->service->captureSignature(1, $dataUri);

        $this->assertEquals(789, $result['evidence_id']);
        $this->assertFileExists($result['file_path']);
    }

    // ========================================================================
    // addDamageNote() Tests
    // ========================================================================

    public function testAddDamageNoteRequiresText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Note text is required');

        // Mock transfer exists
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function ($sql) {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['id' => 1]);
                return $stmt;
            });

        $this->service->addDamageNote(1, 1, '');
    }

    public function testAddDamageNoteSucceeds(): void
    {
        // Mock transfer exists and insert
        $callCount = 0;
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function ($sql) use (&$callCount) {
                $callCount++;
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                if ($callCount === 1) {
                    $stmt->method('fetch')->willReturn(['id' => 1]);
                } else {
                    $stmt->method('fetch')->willReturn(null);
                }

                return $stmt;
            });

        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('999');

        $result = $this->service->addDamageNote(1, 1, 'Package damaged on corner');

        $this->assertArrayHasKey('evidence_id', $result);
        $this->assertArrayHasKey('note', $result);
        $this->assertEquals(999, $result['evidence_id']);
        $this->assertEquals('Package damaged on corner', $result['note']);
    }

    // ========================================================================
    // markItemReceived() Tests
    // ========================================================================

    public function testMarkItemReceivedRequiresPositiveQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be positive');

        // Mock transfer exists
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function ($sql) {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);
                $stmt->method('fetch')->willReturn(['id' => 1]);
                return $stmt;
            });

        $this->service->markItemReceived(1, 1, 0, []);
    }

    public function testMarkItemReceivedSucceeds(): void
    {
        // Mock transfer exists check
        $callCount = 0;
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function ($sql) use (&$callCount) {
                $callCount++;
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                if ($callCount === 1) {
                    // First: check transfer exists
                    $stmt->method('fetch')->willReturn(['id' => 1]);
                } else {
                    // Second: update received_qty
                    $stmt->method('fetch')->willReturn(null);
                }

                return $stmt;
            });

        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $result = $this->service->markItemReceived(1, 1, 5, []);

        $this->assertArrayHasKey('transfer_id', $result);
        $this->assertArrayHasKey('item_id', $result);
        $this->assertArrayHasKey('quantity', $result);
        $this->assertEquals(1, $result['transfer_id']);
        $this->assertEquals(1, $result['item_id']);
        $this->assertEquals(5, $result['quantity']);
    }

    public function testMarkItemReceivedLinksEvidence(): void
    {
        // Mock transfer exists, update item, and update 3 evidence records
        // Total prepare calls: 1 (check transfer) + 1 (update item) + 3 (update evidence) = 5
        $callCount = 0;
        $this->mockPdo->expects($this->exactly(5))
            ->method('prepare')
            ->willReturnCallback(function ($sql) use (&$callCount) {
                $callCount++;
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturn(true);

                if ($callCount === 1) {
                    $stmt->method('fetch')->willReturn(['id' => 1]);
                } else {
                    $stmt->method('fetch')->willReturn(null);
                }

                return $stmt;
            });

        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $result = $this->service->markItemReceived(1, 1, 5, [100, 101, 102]);

        $this->assertEquals(3, $result['evidence_count']);
    }    public function testMarkItemReceivedRollsBackOnError(): void
    {
        // Mock transfer exists, then force error
        $callCount = 0;
        $this->mockPdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function ($sql) use (&$callCount) {
                $callCount++;

                if ($callCount === 1) {
                    // First call: check transfer exists
                    $stmt = $this->createMock(\PDOStatement::class);
                    $stmt->method('execute')->willReturn(true);
                    $stmt->method('fetch')->willReturn(['id' => 1]);
                    return $stmt;
                } else {
                    // Second call: throw error during update
                    throw new \PDOException('Simulated database error');
                }
            });

        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $this->mockPdo->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        $this->mockPdo->expects($this->never())
            ->method('commit');

        $this->expectException(\PDOException::class);

        $this->service->markItemReceived(1, 1, 5, []);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    private function createMinimalJpeg(): string
    {
        // Minimal valid JPEG structure
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
    }

    private function createMinimalPng(): string
    {
        // Minimal valid PNG structure
        return "\x89PNG\r\n\x1a\n" . // PNG signature
               "\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xDE" . // IHDR chunk
               "\x00\x00\x00\x0CIDAT\x08\xD7c\xF8\x0F\x00\x00\x01\x01\x00\x05\x18\x0Dd\x87" . // IDAT chunk
               "\x00\x00\x00\x00IEND\xAEB`\x82"; // IEND chunk
    }
}
