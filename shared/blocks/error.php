<?php
/**
 * CIS Error Display Block
 * 
 * Reusable error display template for consistent error handling across modules
 * 
 * Usage:
 *   include($_SERVER['DOCUMENT_ROOT'] . '/modules/shared/blocks/error.php');
 * 
 * Required variables (set before including):
 *   $errorMessage - string (the error message to display)
 * 
 * Optional variables:
 *   $errorTitle - string (default: "Unable to Process Request")
 *   $errorIcon - string (default: "fa-exclamation-triangle")
 *   $errorType - string (default: "warning") - Options: warning, danger, info
 *   $backUrl - string (default: "index.php") - URL for back button
 *   $backLabel - string (default: "Back to List")
 *   $retryUrl - string|null (if set, shows retry button)
 *   $showDetails - bool (default: false) - Show troubleshooting details
 *   $details - array (custom troubleshooting steps)
 * 
 * @package CIS\Shared\Blocks
 * @version 1.0.0
 */

// Set defaults if not provided
$errorTitle = $errorTitle ?? "Unable to Process Request";
$errorIcon = $errorIcon ?? "fa-exclamation-triangle";
$errorType = $errorType ?? "warning"; // warning, danger, info
$backUrl = $backUrl ?? "index.php";
$backLabel = $backLabel ?? "Back to List";
$retryUrl = $retryUrl ?? null;
$showDetails = $showDetails ?? false;
$details = $details ?? [];

// Default troubleshooting details
$defaultDetails = [
    "Verify all required parameters are provided",
    "Check you have permission to access this resource",
    "Ensure the requested item exists in the system",
    "Try refreshing the page or clearing your browser cache"
];

$troubleshootingSteps = !empty($details) ? $details : $defaultDetails;

// Error type color mapping
$errorColors = [
    'warning' => '#ffc107',
    'danger' => '#dc3545',
    'info' => '#17a2b8'
];
$errorColor = $errorColors[$errorType] ?? $errorColors['warning'];

// Error type background mapping
$errorBackgrounds = [
    'warning' => '#fff3cd',
    'danger' => '#f8d7da',
    'info' => '#d1ecf1'
];
$errorBackground = $errorBackgrounds[$errorType] ?? $errorBackgrounds['warning'];

// Error type text color mapping
$errorTextColors = [
    'warning' => '#856404',
    'danger' => '#721c24',
    'info' => '#0c5460'
];
$errorTextColor = $errorTextColors[$errorType] ?? $errorTextColors['warning'];
?>

<!-- Load Error Display CSS -->
<link rel="stylesheet" href="/modules/shared/css/error-display.css">

<div class="cis-error-display">
    <div class="cis-error-icon" style="background: linear-gradient(135deg, <?php echo $errorColor; ?> 0%, <?php echo $errorColor; ?>dd 100%);">
        <i class="fa <?php echo htmlspecialchars($errorIcon); ?>"></i>
    </div>
    
    <h2 class="cis-error-title"><?php echo htmlspecialchars($errorTitle); ?></h2>
    
    <div class="cis-error-message" style="background: <?php echo $errorBackground; ?>; border-left-color: <?php echo $errorColor; ?>; color: <?php echo $errorTextColor; ?>;">
        <?php echo htmlspecialchars($errorMessage); ?>
    </div>
    
    <?php if ($showDetails): ?>
    <div class="cis-error-details">
        <h6>Troubleshooting Steps</h6>
        <ul>
            <?php foreach ($troubleshootingSteps as $step): ?>
            <li><?php echo htmlspecialchars($step); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="cis-error-actions">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-primary">
            <i class="fa fa-arrow-left"></i> <?php echo htmlspecialchars($backLabel); ?>
        </a>
        <?php if ($retryUrl): ?>
        <a href="<?php echo htmlspecialchars($retryUrl); ?>" class="btn btn-secondary">
            <i class="fa fa-refresh"></i> Retry
        </a>
        <?php endif; ?>
    </div>
</div>
