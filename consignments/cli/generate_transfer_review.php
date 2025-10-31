<?php
declare(strict_types=1);

// CLI helper to generate transfer review for background jobs

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from CLI\n";
    exit(1);
}

if ($argc < 2) {
    echo "Usage: php generate_transfer_review.php <transferId>\n";
    exit(1);
}

$transferId = (int)$argv[1];

try {
    $service = new \CIS\Consignments\Services\TransferReviewService($pdo);
    $review = $service->generateReview($transferId);
    echo "Review generated for transfer: {$transferId}\n";
    exit(0);
} catch (\Exception $e) {
    error_log("[generate_transfer_review] Error: " . $e->getMessage());
    echo "Failed to generate review: " . $e->getMessage() . "\n";
    exit(2);
}
