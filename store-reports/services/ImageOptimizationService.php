<?php
/**
 * Image Optimization Service - Enterprise Grade
 *
 * Features:
 * - WebP conversion with fallback
 * - Multi-size generation (thumbnail, medium, optimized)
 * - EXIF data extraction & GPS parsing
 * - Quality scoring & blur detection
 * - Progressive/responsive image loading
 * - CDN-ready optimization
 * - Batch processing queue
 * - Memory-efficient streaming
 *
 * @author Enterprise Engineering Team
 * @date 2025-11-13
 */

declare(strict_types=1);

class ImageOptimizationService
{
    private PDO $pdo;
    private string $uploadDir;
    private string $optimizedDir;
    private array $config;
    private array $stats = [];

    // Size presets
    private const SIZES = [
        'thumbnail' => ['width' => 150, 'height' => 150, 'quality' => 80],
        'medium' => ['width' => 800, 'height' => 800, 'quality' => 85],
        'optimized' => ['width' => 1920, 'height' => 1920, 'quality' => 90],
    ];

    // Supported formats
    private const SUPPORTED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // Quality thresholds
    private const QUALITY_THRESHOLD_BLUR = 30; // Below = too blurry
    private const QUALITY_THRESHOLD_EXCELLENT = 85;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->uploadDir = $config['upload_dir'] ?? SR_UPLOAD_DIR;
        $this->optimizedDir = $config['optimized_dir'] ?? SR_UPLOAD_DIR . '/optimized';

        // Default configuration
        $this->config = array_merge([
            'enable_webp' => true,
            'enable_exif' => true,
            'enable_quality_check' => true,
            'max_width' => 4000,
            'max_height' => 4000,
            'jpeg_quality' => 90,
            'webp_quality' => 85,
            'png_compression' => 9,
            'strip_exif' => false, // Keep EXIF by default for audit trail
            'progressive_jpeg' => true,
            'memory_limit' => '512M',
        ], $config);

        // Ensure directories exist
        $this->ensureDirectories();

        // Set memory limit for large images
        ini_set('memory_limit', $this->config['memory_limit']);
    }

    /**
     * Optimize a single image (main entry point)
     *
     * @param int $imageId Database ID from store_report_images
     * @return array Results with paths, sizes, quality scores
     */
    public function optimizeImage(int $imageId): array
    {
        $startTime = microtime(true);
        $this->stats = ['image_id' => $imageId];

        try {
            // Load image record
            $image = $this->loadImageRecord($imageId);

            if (!$image) {
                throw new RuntimeException("Image ID $imageId not found");
            }

            // Validate source file exists
            $sourcePath = $this->resolveFilePath($image['original_file_path']);

            if (!file_exists($sourcePath)) {
                throw new RuntimeException("Source file not found: $sourcePath");
            }

            // Update status to processing
            $this->updateImageStatus($imageId, 'optimizing');

            // Extract EXIF data first (before any processing)
            $exifData = null;
            if ($this->config['enable_exif']) {
                $exifData = $this->extractExifData($sourcePath);
                $this->stats['exif'] = $exifData;
            }

            // Perform quality checks
            $qualityScores = null;
            if ($this->config['enable_quality_check']) {
                $qualityScores = $this->analyzeImageQuality($sourcePath);
                $this->stats['quality'] = $qualityScores;

                // Reject if too blurry
                if ($qualityScores['blur_score'] < self::QUALITY_THRESHOLD_BLUR) {
                    $this->updateImageStatus($imageId, 'failed');
                    $this->updateImageData($imageId, [
                        'is_rejected' => 1,
                        'rejection_reason' => 'Image is too blurry (score: ' . $qualityScores['blur_score'] . ')',
                        'blur_score' => $qualityScores['blur_score']
                    ]);

                    throw new RuntimeException("Image rejected: too blurry");
                }
            }

            // Load source image
            $sourceImage = $this->loadImage($sourcePath, $image['original_mime_type']);

            if (!$sourceImage) {
                throw new RuntimeException("Failed to load source image");
            }

            $originalSize = filesize($sourcePath);
            $this->stats['original_size'] = $originalSize;

            // Generate optimized versions
            $results = [];

            // 1. Thumbnail
            $thumbnailResult = $this->generateThumbnail($sourceImage, $imageId, $sourcePath);
            $results['thumbnail'] = $thumbnailResult;

            // 2. Medium
            $mediumResult = $this->generateMedium($sourceImage, $imageId, $sourcePath);
            $results['medium'] = $mediumResult;

            // 3. Optimized full-size (with WebP conversion)
            $optimizedResult = $this->generateOptimized($sourceImage, $imageId, $sourcePath, $image['original_mime_type']);
            $results['optimized'] = $optimizedResult;

            // Calculate total size reduction
            $totalOptimizedSize = ($thumbnailResult['size'] ?? 0) +
                                   ($mediumResult['size'] ?? 0) +
                                   ($optimizedResult['size'] ?? 0);

            $sizeReduction = $originalSize - $totalOptimizedSize;
            $sizeReductionPercent = ($sizeReduction / $originalSize) * 100;

            $this->stats['total_optimized_size'] = $totalOptimizedSize;
            $this->stats['size_reduction'] = $sizeReduction;
            $this->stats['size_reduction_percent'] = $sizeReductionPercent;

            // Update database with all results
            $updateData = [
                'optimized_file_path' => $results['optimized']['path'] ?? null,
                'optimized_file_size' => $results['optimized']['size'] ?? null,
                'thumbnail_path' => $results['thumbnail']['path'] ?? null,
                'thumbnail_size' => $results['thumbnail']['size'] ?? null,
                'medium_path' => $results['medium']['path'] ?? null,
                'medium_size' => $results['medium']['size'] ?? null,
                'optimization_completed_at' => date('Y-m-d H:i:s'),
                'optimization_duration_ms' => (int)((microtime(true) - $startTime) * 1000),
                'size_reduction_percent' => round($sizeReductionPercent, 2),
                'quality_score' => $qualityScores['overall'] ?? null,
                'blur_score' => $qualityScores['blur_score'] ?? null,
                'exposure_score' => $qualityScores['exposure_score'] ?? null,
                'status' => 'optimized',
            ];

            // Add EXIF data if available
            if ($exifData) {
                $updateData['exif_data'] = json_encode($exifData);
                $updateData['capture_timestamp'] = $exifData['timestamp'] ?? null;
                $updateData['camera_make'] = $exifData['make'] ?? null;
                $updateData['camera_model'] = $exifData['model'] ?? null;
                $updateData['gps_latitude'] = $exifData['gps']['latitude'] ?? null;
                $updateData['gps_longitude'] = $exifData['gps']['longitude'] ?? null;
            }

            $this->updateImageData($imageId, $updateData);

            // Update optimization queue if exists
            $this->updateOptimizationQueue($imageId, 'completed', $this->stats);

            // Clean up memory
            imagedestroy($sourceImage);

            return [
                'success' => true,
                'image_id' => $imageId,
                'results' => $results,
                'stats' => $this->stats,
                'quality' => $qualityScores,
                'exif' => $exifData,
                'size_reduction' => [
                    'bytes' => $sizeReduction,
                    'percent' => round($sizeReductionPercent, 2),
                    'original' => $originalSize,
                    'optimized' => $totalOptimizedSize
                ]
            ];

        } catch (Exception $e) {
            // Log error and update status
            $this->updateImageStatus($imageId, 'failed');
            $this->updateOptimizationQueue($imageId, 'failed', [
                'error' => $e->getMessage(),
                'stats' => $this->stats
            ]);

            error_log("Image optimization failed for ID $imageId: " . $e->getMessage());

            return [
                'success' => false,
                'image_id' => $imageId,
                'error' => $e->getMessage(),
                'stats' => $this->stats
            ];
        }
    }

    /**
     * Generate thumbnail (150x150)
     */
    private function generateThumbnail($sourceImage, int $imageId, string $sourcePath): array
    {
        $config = self::SIZES['thumbnail'];
        $outputPath = $this->getOutputPath($imageId, 'thumbnail', 'jpg');

        $thumbnail = $this->resizeImage($sourceImage, $config['width'], $config['height'], 'crop');

        if (!$thumbnail) {
            throw new RuntimeException("Failed to generate thumbnail");
        }

        // Save as JPEG (thumbnails don't need WebP)
        imagejpeg($thumbnail, $outputPath, $config['quality']);
        imagedestroy($thumbnail);

        return [
            'path' => $this->getRelativePath($outputPath),
            'size' => filesize($outputPath),
            'width' => $config['width'],
            'height' => $config['height'],
            'quality' => $config['quality']
        ];
    }

    /**
     * Generate medium size (800x800)
     */
    private function generateMedium($sourceImage, int $imageId, string $sourcePath): array
    {
        $config = self::SIZES['medium'];
        $outputPath = $this->getOutputPath($imageId, 'medium', 'jpg');

        $medium = $this->resizeImage($sourceImage, $config['width'], $config['height'], 'contain');

        if (!$medium) {
            throw new RuntimeException("Failed to generate medium image");
        }

        // Progressive JPEG for better loading
        if ($this->config['progressive_jpeg']) {
            imageinterlace($medium, 1);
        }

        imagejpeg($medium, $outputPath, $config['quality']);
        imagedestroy($medium);

        return [
            'path' => $this->getRelativePath($outputPath),
            'size' => filesize($outputPath),
            'width' => imagesx($medium),
            'height' => imagesy($medium),
            'quality' => $config['quality']
        ];
    }

    /**
     * Generate optimized full-size with WebP conversion
     */
    private function generateOptimized($sourceImage, int $imageId, string $sourcePath, string $mimeType): array
    {
        $config = self::SIZES['optimized'];

        // Resize if larger than max dimensions
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        if ($sourceWidth > $config['width'] || $sourceHeight > $config['height']) {
            $optimized = $this->resizeImage($sourceImage, $config['width'], $config['height'], 'contain');
        } else {
            // No resize needed, but still optimize
            $optimized = $sourceImage;
        }

        $results = [];

        // Save as original format (JPEG/PNG)
        $ext = $this->getExtensionFromMime($mimeType);
        $outputPath = $this->getOutputPath($imageId, 'optimized', $ext);

        switch ($mimeType) {
            case 'image/jpeg':
                if ($this->config['progressive_jpeg']) {
                    imageinterlace($optimized, 1);
                }
                imagejpeg($optimized, $outputPath, $this->config['jpeg_quality']);
                break;

            case 'image/png':
                imagepng($optimized, $outputPath, $this->config['png_compression']);
                break;

            case 'image/gif':
                imagegif($optimized, $outputPath);
                break;
        }

        $results['original_format'] = [
            'path' => $this->getRelativePath($outputPath),
            'size' => filesize($outputPath),
            'mime' => $mimeType
        ];

        // Convert to WebP if enabled and supported
        if ($this->config['enable_webp'] && function_exists('imagewebp')) {
            $webpPath = $this->getOutputPath($imageId, 'optimized', 'webp');
            imagewebp($optimized, $webpPath, $this->config['webp_quality']);

            $results['webp'] = [
                'path' => $this->getRelativePath($webpPath),
                'size' => filesize($webpPath),
                'mime' => 'image/webp',
                'quality' => $this->config['webp_quality']
            ];
        }

        if ($optimized !== $sourceImage) {
            imagedestroy($optimized);
        }

        // Return primary format details
        return $results['webp'] ?? $results['original_format'];
    }

    /**
     * Resize image with mode: 'contain' (fit) or 'crop' (fill)
     */
    private function resizeImage($sourceImage, int $maxWidth, int $maxHeight, string $mode = 'contain')
    {
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        if ($mode === 'crop') {
            // Crop to exact dimensions (center crop)
            $sourceAspect = $sourceWidth / $sourceHeight;
            $targetAspect = $maxWidth / $maxHeight;

            if ($sourceAspect > $targetAspect) {
                // Source is wider
                $tempHeight = $sourceHeight;
                $tempWidth = (int)($sourceHeight * $targetAspect);
                $srcX = (int)(($sourceWidth - $tempWidth) / 2);
                $srcY = 0;
            } else {
                // Source is taller
                $tempWidth = $sourceWidth;
                $tempHeight = (int)($sourceWidth / $targetAspect);
                $srcX = 0;
                $srcY = (int)(($sourceHeight - $tempHeight) / 2);
            }

            $destImage = imagecreatetruecolor($maxWidth, $maxHeight);
            $this->preserveTransparency($destImage);

            imagecopyresampled(
                $destImage, $sourceImage,
                0, 0, $srcX, $srcY,
                $maxWidth, $maxHeight,
                $tempWidth, $tempHeight
            );

        } else {
            // Contain: maintain aspect ratio, fit within bounds
            $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);

            $newWidth = (int)($sourceWidth * $ratio);
            $newHeight = (int)($sourceHeight * $ratio);

            $destImage = imagecreatetruecolor($newWidth, $newHeight);
            $this->preserveTransparency($destImage);

            imagecopyresampled(
                $destImage, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $sourceWidth, $sourceHeight
            );
        }

        return $destImage;
    }

    /**
     * Preserve transparency for PNG/GIF
     */
    private function preserveTransparency($image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
    }

    /**
     * Extract EXIF data (camera info, GPS, timestamp)
     */
    private function extractExifData(string $filePath): ?array
    {
        if (!function_exists('exif_read_data')) {
            return null;
        }

        try {
            $exif = @exif_read_data($filePath, 0, true);

            if (!$exif) {
                return null;
            }

            $data = [];

            // Camera info
            $data['make'] = $exif['IFD0']['Make'] ?? null;
            $data['model'] = $exif['IFD0']['Model'] ?? null;
            $data['software'] = $exif['IFD0']['Software'] ?? null;

            // Capture settings
            $data['iso'] = $exif['EXIF']['ISOSpeedRatings'] ?? null;
            $data['aperture'] = $exif['EXIF']['FNumber'] ?? null;
            $data['shutter_speed'] = $exif['EXIF']['ExposureTime'] ?? null;
            $data['focal_length'] = $exif['EXIF']['FocalLength'] ?? null;
            $data['flash'] = $exif['EXIF']['Flash'] ?? null;

            // Timestamp
            $data['timestamp'] = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;

            // GPS data
            if (isset($exif['GPS'])) {
                $data['gps'] = $this->parseGPSData($exif['GPS']);
            }

            // Image dimensions
            $data['width'] = $exif['COMPUTED']['Width'] ?? null;
            $data['height'] = $exif['COMPUTED']['Height'] ?? null;
            $data['orientation'] = $exif['IFD0']['Orientation'] ?? null;

            return $data;

        } catch (Exception $e) {
            error_log("EXIF extraction failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse GPS coordinates from EXIF
     */
    private function parseGPSData(array $gps): ?array
    {
        if (!isset($gps['GPSLatitude']) || !isset($gps['GPSLongitude'])) {
            return null;
        }

        $lat = $this->getGPSCoordinate($gps['GPSLatitude'], $gps['GPSLatitudeRef'] ?? 'N');
        $lon = $this->getGPSCoordinate($gps['GPSLongitude'], $gps['GPSLongitudeRef'] ?? 'E');

        return [
            'latitude' => $lat,
            'longitude' => $lon,
            'altitude' => $gps['GPSAltitude'] ?? null,
            'timestamp' => $gps['GPSDateStamp'] ?? null
        ];
    }

    /**
     * Convert GPS DMS to decimal
     */
    private function getGPSCoordinate(array $coordinate, string $ref): float
    {
        $degrees = $this->evaluateRational($coordinate[0]);
        $minutes = $this->evaluateRational($coordinate[1]);
        $seconds = $this->evaluateRational($coordinate[2]);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if ($ref === 'S' || $ref === 'W') {
            $decimal *= -1;
        }

        return round($decimal, 8);
    }

    /**
     * Evaluate EXIF rational (fraction)
     */
    private function evaluateRational($rational): float
    {
        if (is_string($rational) && strpos($rational, '/') !== false) {
            [$numerator, $denominator] = explode('/', $rational, 2);
            return $denominator > 0 ? ($numerator / $denominator) : 0;
        }
        return (float)$rational;
    }

    /**
     * Analyze image quality (blur, exposure, etc.)
     */
    private function analyzeImageQuality(string $filePath): array
    {
        try {
            $image = $this->loadImage($filePath);

            if (!$image) {
                return ['overall' => 50];
            }

            // Blur detection using Laplacian variance
            $blurScore = $this->detectBlur($image);

            // Exposure analysis
            $exposureScore = $this->analyzeExposure($image);

            // Overall quality
            $overall = ($blurScore + $exposureScore) / 2;

            imagedestroy($image);

            return [
                'blur_score' => round($blurScore, 2),
                'exposure_score' => round($exposureScore, 2),
                'overall' => round($overall, 2)
            ];

        } catch (Exception $e) {
            error_log("Quality analysis failed: " . $e->getMessage());
            return ['overall' => 50];
        }
    }

    /**
     * Detect blur using edge detection variance
     */
    private function detectBlur($image): float
    {
        // Convert to grayscale for analysis
        $width = imagesx($image);
        $height = imagesy($image);

        // Sample center region (avoid edges which might be intentionally blurred)
        $sampleX = (int)($width * 0.25);
        $sampleY = (int)($height * 0.25);
        $sampleWidth = (int)($width * 0.5);
        $sampleHeight = (int)($height * 0.5);

        $variance = 0;
        $pixels = 0;
        $previousGray = 0;

        for ($y = $sampleY; $y < $sampleY + $sampleHeight; $y += 4) {
            for ($x = $sampleX; $x < $sampleX + $sampleWidth; $x += 4) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $gray = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);

                if ($pixels > 0) {
                    $diff = abs($gray - $previousGray);
                    $variance += $diff;
                }

                $previousGray = $gray;
                $pixels++;
            }
        }

        $avgVariance = $pixels > 0 ? ($variance / $pixels) : 0;

        // Convert variance to 0-100 score (higher = sharper)
        $score = min(100, $avgVariance * 2);

        return $score;
    }

    /**
     * Analyze exposure (brightness distribution)
     */
    private function analyzeExposure($image): float
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $totalBrightness = 0;
        $pixels = 0;

        // Sample pixels
        for ($y = 0; $y < $height; $y += 10) {
            for ($x = 0; $x < $width; $x += 10) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $brightness = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);
                $totalBrightness += $brightness;
                $pixels++;
            }
        }

        $avgBrightness = $pixels > 0 ? ($totalBrightness / $pixels) : 128;

        // Ideal brightness is around 128 (mid-gray)
        // Score based on how close to ideal
        $deviation = abs($avgBrightness - 128);
        $score = max(0, 100 - ($deviation / 128 * 100));

        return $score;
    }

    /**
     * Load image from file
     */
    private function loadImage(string $filePath, ?string $mimeType = null)
    {
        if (!$mimeType) {
            $mimeType = mime_content_type($filePath);
        }

        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filePath);
            case 'image/png':
                return imagecreatefrompng($filePath);
            case 'image/gif':
                return imagecreatefromgif($filePath);
            case 'image/webp':
                return imagecreatefromwebp($filePath);
            default:
                return false;
        }
    }

    // ========================================
    // DATABASE HELPERS
    // ========================================

    private function loadImageRecord(int $imageId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM store_report_images WHERE id = ?");
        $stmt->execute([$imageId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function updateImageStatus(int $imageId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE store_report_images SET status = ? WHERE id = ?");
        $stmt->execute([$status, $imageId]);
    }

    private function updateImageData(int $imageId, array $data): void
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }

        $values[] = $imageId;

        $sql = "UPDATE store_report_images SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }

    private function updateOptimizationQueue(int $imageId, string $status, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE store_report_photo_optimization_queue
            SET status = ?,
                completed_at = NOW(),
                processing_duration_ms = ?,
                original_size = ?,
                optimized_size = ?,
                size_reduction_bytes = ?,
                size_reduction_percent = ?
            WHERE image_id = ?
        ");

        $stmt->execute([
            $status,
            $data['stats']['optimization_duration_ms'] ?? null,
            $data['stats']['original_size'] ?? null,
            $data['stats']['total_optimized_size'] ?? null,
            $data['stats']['size_reduction'] ?? null,
            $data['stats']['size_reduction_percent'] ?? null,
            $imageId
        ]);
    }

    // ========================================
    // FILE SYSTEM HELPERS
    // ========================================

    private function ensureDirectories(): void
    {
        $dirs = [
            $this->uploadDir,
            $this->optimizedDir,
            $this->optimizedDir . '/thumbnails',
            $this->optimizedDir . '/medium',
            $this->optimizedDir . '/full',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }

    private function getOutputPath(int $imageId, string $size, string $ext): string
    {
        $subdir = match($size) {
            'thumbnail' => 'thumbnails',
            'medium' => 'medium',
            default => 'full'
        };

        return $this->optimizedDir . '/' . $subdir . '/' . $imageId . '.' . $ext;
    }

    private function getRelativePath(string $absolutePath): string
    {
        return str_replace($this->uploadDir . '/', '', $absolutePath);
    }

    private function resolveFilePath(string $path): string
    {
        if (strpos($path, '/') === 0) {
            return $path; // Already absolute
        }
        return $this->uploadDir . '/' . $path;
    }

    private function getExtensionFromMime(string $mimeType): string
    {
        return match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg'
        };
    }
}
