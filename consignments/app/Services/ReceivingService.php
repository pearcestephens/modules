<?php
/**
 * Receiving Service
 *
 * Handles receiving evidence: photos, signatures, damage notes.
 * Provides secure file upload with path traversal protection.
 *
 * Security:
 * - Path traversal prevention (basename, realpath)
 * - File type validation (JPEG, PNG only)
 * - Max file size: 5MB
 * - Files stored in jailed directory
 *
 * @package Consignments\App\Services
 */

declare(strict_types=1);

namespace Consignments\App\Services;

use Psr\Log\LoggerInterface;

class ReceivingService
{
    private \PDO $pdo;
    private LoggerInterface $logger;
    private string $uploadBasePath;
    private int $maxFileSize = 5242880; // 5MB
    private array $allowedMimeTypes = ['image/jpeg', 'image/png'];
    private array $allowedExtensions = ['jpg', 'jpeg', 'png'];

    public function __construct(\PDO $pdo, LoggerInterface $logger, string $uploadBasePath = '/uploads/receiving')
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->uploadBasePath = rtrim($uploadBasePath, '/');
    }

    /**
     * Upload photo evidence
     *
     * @param int $transferId Transfer ID
     * @param int $itemId Item ID
     * @param array $file $_FILES array entry
     * @return array Upload result with file path
     * @throws \InvalidArgumentException if file invalid
     * @throws \RuntimeException if upload fails
     */
    public function uploadPhoto(int $transferId, int $itemId, array $file): array
    {
        // Validate file upload
        $this->validateFileUpload($file);

        // Validate transfer exists
        $this->validateTransferExists($transferId);

        // Security: prevent path traversal
        $safeFilename = $this->generateSafeFilename($file['name']);
        $transferDir = $this->createTransferDirectory($transferId);
        $fullPath = $transferDir . '/' . $safeFilename;

        // Security: verify path is within jail
        $realTransferDir = realpath($transferDir);
        $realBasePath = realpath($this->uploadBasePath);

        if ($realTransferDir === false || $realBasePath === false) {
            throw new \RuntimeException('Upload directory does not exist');
        }

        if (strpos($realTransferDir, $realBasePath) !== 0) {
            throw new \RuntimeException('Path traversal attempt detected');
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $this->logger->error('Failed to move uploaded file', [
                'transfer_id' => $transferId,
                'item_id' => $itemId,
                'filename' => $safeFilename,
            ]);
            throw new \RuntimeException('Failed to save uploaded file');
        }

        // Set permissions
        chmod($fullPath, 0644);

        // Record in database
        $evidenceId = $this->recordEvidence($transferId, $itemId, 'photo', $fullPath, null);

        $this->logger->info('Photo evidence uploaded', [
            'transfer_id' => $transferId,
            'item_id' => $itemId,
            'evidence_id' => $evidenceId,
            'filename' => $safeFilename,
        ]);

        return [
            'evidence_id' => $evidenceId,
            'file_path' => $fullPath,
            'filename' => $safeFilename,
        ];
    }

    /**
     * Capture signature evidence
     *
     * @param int $transferId Transfer ID
     * @param string $signatureData Base64 encoded signature image
     * @return array Signature details
     * @throws \InvalidArgumentException if signature invalid
     */
    public function captureSignature(int $transferId, string $signatureData): array
    {
        // Validate transfer exists
        $this->validateTransferExists($transferId);

        // Validate base64 data
        if (empty($signatureData)) {
            throw new \InvalidArgumentException('Signature data is required');
        }

        // Extract image data (remove data:image/png;base64, prefix if present)
        $imageData = $signatureData;
        if (preg_match('/^data:image\/(png|jpeg);base64,(.+)$/', $signatureData, $matches)) {
            $imageData = $matches[2];
        }

        // Decode base64
        $decodedImage = base64_decode($imageData, true);
        if ($decodedImage === false) {
            throw new \InvalidArgumentException('Invalid base64 signature data');
        }

        // Security: verify it's actually an image
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($decodedImage);

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('Invalid signature image type');
        }

        // Generate filename
        $filename = 'signature_' . time() . '_' . bin2hex(random_bytes(8)) . '.png';
        $transferDir = $this->createTransferDirectory($transferId);
        $fullPath = $transferDir . '/' . $filename;

        // Save signature
        if (file_put_contents($fullPath, $decodedImage) === false) {
            throw new \RuntimeException('Failed to save signature');
        }

        chmod($fullPath, 0644);

        // Record in database
        $evidenceId = $this->recordEvidence($transferId, null, 'signature', $fullPath, null);

        $this->logger->info('Signature captured', [
            'transfer_id' => $transferId,
            'evidence_id' => $evidenceId,
        ]);

        return [
            'evidence_id' => $evidenceId,
            'file_path' => $fullPath,
            'filename' => $filename,
        ];
    }

    /**
     * Add damage note
     *
     * @param int $transferId Transfer ID
     * @param int $itemId Item ID
     * @param string $note Damage description
     * @return array Note details
     */
    public function addDamageNote(int $transferId, int $itemId, string $note): array
    {
        // Validate transfer exists
        $this->validateTransferExists($transferId);

        if (empty($note)) {
            throw new \InvalidArgumentException('Note text is required');
        }

        // Record in database
        $evidenceId = $this->recordEvidence($transferId, $itemId, 'note', null, $note);

        $this->logger->info('Damage note added', [
            'transfer_id' => $transferId,
            'item_id' => $itemId,
            'evidence_id' => $evidenceId,
        ]);

        return [
            'evidence_id' => $evidenceId,
            'note' => $note,
        ];
    }

    /**
     * Mark item received with evidence
     *
     * @param int $transferId Transfer ID
     * @param int $itemId Item ID
     * @param int $quantity Quantity received
     * @param array $evidence Evidence IDs to associate
     * @return array Receiving details
     */
    public function markItemReceived(int $transferId, int $itemId, int $quantity, array $evidence = []): array
    {
        $this->validateTransferExists($transferId);

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        $this->pdo->beginTransaction();
        try {
            // Update received quantity
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfer_items
                SET received_qty = received_qty + :quantity,
                    last_received_at = NOW()
                WHERE id = :item_id AND transfer_id = :transfer_id
            ");
            $stmt->execute([
                'quantity' => $quantity,
                'item_id' => $itemId,
                'transfer_id' => $transferId,
            ]);

            // Link evidence to this receive action
            if (!empty($evidence)) {
                foreach ($evidence as $evidenceId) {
                    $stmt = $this->pdo->prepare("
                        UPDATE receiving_evidence
                        SET item_id = :item_id
                        WHERE id = :evidence_id AND transfer_id = :transfer_id
                    ");
                    $stmt->execute([
                        'item_id' => $itemId,
                        'evidence_id' => $evidenceId,
                        'transfer_id' => $transferId,
                    ]);
                }
            }

            $this->pdo->commit();

            $this->logger->info('Item marked as received', [
                'transfer_id' => $transferId,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'evidence_count' => count($evidence),
            ]);

            return [
                'transfer_id' => $transferId,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'evidence_count' => count($evidence),
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to mark item received', [
                'transfer_id' => $transferId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get all evidence for transfer
     *
     * @param int $transferId Transfer ID
     * @return array Evidence records
     */
    public function getEvidence(int $transferId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM receiving_evidence
            WHERE transfer_id = :transfer_id
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute(['transfer_id' => $transferId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ========================================================================
    // Private Helper Methods
    // ========================================================================

    /**
     * Validate file upload
     *
     * @param array $file $_FILES array entry
     * @throws \InvalidArgumentException if invalid
     */
    private function validateFileUpload(array $file): void
    {
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \InvalidArgumentException('Invalid file upload');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new \InvalidArgumentException('File exceeds maximum size (5MB)');
            case UPLOAD_ERR_PARTIAL:
                throw new \InvalidArgumentException('File upload incomplete');
            case UPLOAD_ERR_NO_FILE:
                throw new \InvalidArgumentException('No file uploaded');
            default:
                throw new \InvalidArgumentException('Unknown upload error');
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            throw new \InvalidArgumentException('File exceeds maximum size (5MB)');
        }

        // Check MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('Invalid file type. Only JPEG and PNG images allowed');
        }

        // Check extension (additional security layer)
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new \InvalidArgumentException('Invalid file extension');
        }
    }

    /**
     * Generate safe filename (prevent path traversal)
     *
     * @param string $originalFilename
     * @return string Safe filename
     */
    private function generateSafeFilename(string $originalFilename): string
    {
        // Security: use basename to strip any directory components
        $basename = basename($originalFilename);

        // Remove any remaining unsafe characters
        $basename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $basename);

        // Add timestamp and random component to avoid collisions
        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($basename, PATHINFO_FILENAME);

        return $nameWithoutExt . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    }

    /**
     * Create transfer directory
     *
     * @param int $transferId
     * @return string Directory path
     * @throws \RuntimeException if creation fails
     */
    private function createTransferDirectory(int $transferId): string
    {
        $dir = $this->uploadBasePath . '/' . $transferId;

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException('Failed to create upload directory');
            }
        }

        return $dir;
    }

    /**
     * Validate transfer exists
     *
     * @param int $transferId
     * @throws \RuntimeException if not found
     */
    private function validateTransferExists(int $transferId): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM stock_transfers WHERE id = :id");
        $stmt->execute(['id' => $transferId]);

        if (!$stmt->fetch()) {
            throw new \RuntimeException("Transfer {$transferId} not found");
        }
    }

    /**
     * Record evidence in database
     *
     * @param int $transferId
     * @param int|null $itemId
     * @param string $evidenceType
     * @param string|null $filePath
     * @param string|null $note
     * @return int Evidence ID
     */
    private function recordEvidence(
        int $transferId,
        ?int $itemId,
        string $evidenceType,
        ?string $filePath,
        ?string $note
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO receiving_evidence (
                transfer_id, item_id, evidence_type, file_path, note, uploaded_at
            ) VALUES (
                :transfer_id, :item_id, :evidence_type, :file_path, :note, NOW()
            )
        ");
        $stmt->execute([
            'transfer_id' => $transferId,
            'item_id' => $itemId,
            'evidence_type' => $evidenceType,
            'file_path' => $filePath,
            'note' => $note,
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
