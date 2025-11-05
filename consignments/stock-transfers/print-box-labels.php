<?php
/**
 * BOX LABELS - Internal Identification Labels
 * Print labels for boxes BEFORE shipping labels are created
 * Shows: Box # of Total, From/To Store, Transfer ID, (Optional Tracking)
 */

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Print Box Labels - Transfer #12345');
$theme->showTimestamps = false;

// Mock data - Replace with actual database query
$transfer = [
    'id' => 12345,
    'from_store' => 'Auckland Central',
    'from_address' => '123 Queen Street, Auckland CBD, Auckland 1010',
    'to_store' => 'WELLINGTON LAMBTON QUAY',
    'to_address' => '456 Lambton Quay, Wellington Central, Wellington 6011',
    'boxes' => [
        [
            'box_number' => 1,
            'tracking' => 'NZ123456789WLG', // May be empty if not yet created
            'weight' => '5.2kg',
            'items' => 12
        ],
        [
            'box_number' => 2,
            'tracking' => 'NZ987654321WLG',
            'weight' => '6.8kg',
            'items' => 15
        ],
        [
            'box_number' => 3,
            'tracking' => 'NZ456789123WLG',
            'weight' => '3.7kg',
            'items' => 5
        ]
    ]
];

$totalBoxes = count($transfer['boxes']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Box Labels - Transfer #<?= $transfer['id'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            background: #f6f8fa;
            padding: 20px;
        }

        /* SCREEN VIEW */
        .screen-view {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-card {
            background: #fff;
            border: 2px solid #0366d6;
            border-radius: 6px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .header-card h1 {
            font-size: 28px;
            color: #24292e;
            margin-bottom: 10px;
        }

        .transfer-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }

        .info-box {
            background: #f6f8fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #0366d6;
        }

        .info-label {
            font-size: 11px;
            color: #6a737d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #24292e;
            font-weight: 600;
        }

        .info-address {
            font-size: 13px;
            color: #586069;
            margin-top: 5px;
        }

        .alert-important {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-important i {
            font-size: 32px;
            color: #856404;
        }

        .alert-important .content {
            flex: 1;
        }

        .alert-important h3 {
            font-size: 18px;
            color: #856404;
            margin-bottom: 8px;
        }

        .alert-important p {
            font-size: 14px;
            color: #856404;
            line-height: 1.5;
        }

        .destination-display {
            background: #dc3545;
            color: #fff;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            border: 4px solid #a71d2a;
        }

        .destination-display h2 {
            font-size: 48px;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .destination-display .address {
            font-size: 20px;
            font-weight: 600;
            margin-top: 10px;
        }

        .print-controls {
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #0366d6;
            color: #fff;
        }

        .btn-primary:hover {
            background: #0256b8;
        }

        .btn-success {
            background: #28a745;
            color: #fff;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-lg {
            padding: 15px 30px;
            font-size: 16px;
        }

        .labels-preview {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        /* BOX LABEL DESIGN */
        .box-label {
            background: #fff;
            border: 3px solid #24292e;
            border-radius: 8px;
            padding: 20px;
            page-break-after: always;
            page-break-inside: avoid;
        }

        .box-label-header {
            text-align: center;
            border-bottom: 3px solid #24292e;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .box-number {
            font-size: 64px;
            font-weight: 900;
            color: #24292e;
            line-height: 1;
            margin-bottom: 10px;
        }

        .box-total {
            font-size: 24px;
            color: #6a737d;
            font-weight: 600;
        }

        .destination-section {
            background: #dc3545;
            color: #fff;
            padding: 25px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
        }

        .destination-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            opacity: 0.9;
        }

        .destination-name {
            font-size: 42px;
            font-weight: 900;
            letter-spacing: 1px;
            line-height: 1.1;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .destination-address {
            font-size: 16px;
            font-weight: 600;
            line-height: 1.4;
        }

        .transfer-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .detail-item {
            background: #f6f8fa;
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #0366d6;
        }

        .detail-label {
            font-size: 10px;
            color: #6a737d;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .detail-value {
            font-size: 16px;
            color: #24292e;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .tracking-section {
            background: #fff8e1;
            border: 2px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }

        .tracking-label {
            font-size: 11px;
            color: #856404;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .tracking-number {
            font-size: 20px;
            font-weight: 900;
            color: #856404;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .tracking-empty {
            font-size: 14px;
            color: #6a737d;
            font-style: italic;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .screen-view {
                max-width: none;
            }

            .header-card,
            .alert-important,
            .destination-display,
            .print-controls,
            .no-print {
                display: none !important;
            }

            .labels-preview {
                display: block;
            }

            .box-label {
                margin: 0;
                padding: 30px;
                page-break-after: always;
                page-break-inside: avoid;
            }

            .box-label:last-child {
                page-break-after: auto;
            }

            .box-number {
                font-size: 72px;
            }

            .destination-name {
                font-size: 48px;
            }
        }

        @page {
            size: A4;
            margin: 10mm;
        }
    </style>
</head>
<body>
    <div class="screen-view">
        <!-- Header Card -->
        <div class="header-card no-print">
            <h1><i class="fa fa-print"></i> Print Box Labels - Transfer #<?= $transfer['id'] ?></h1>

            <div class="transfer-info">
                <div class="info-box">
                    <div class="info-label">From</div>
                    <div class="info-value"><?= $transfer['from_store'] ?></div>
                    <div class="info-address"><?= $transfer['from_address'] ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">To</div>
                    <div class="info-value"><?= $transfer['to_store'] ?></div>
                    <div class="info-address"><?= $transfer['to_address'] ?></div>
                </div>
            </div>
        </div>

        <!-- Important Alert -->
        <div class="alert-important no-print">
            <i class="fa fa-exclamation-triangle"></i>
            <div class="content">
                <h3>⚠️ CRITICAL: Verify Destination Store</h3>
                <p>
                    <strong>These labels are for INTERNAL IDENTIFICATION ONLY.</strong> They help identify boxes in the warehouse.
                    Always double-check the destination store is correct before applying labels to boxes.
                    Shipping labels with tracking numbers will be generated separately via courier API.
                </p>
            </div>
        </div>

        <!-- Destination Display -->
        <div class="destination-display no-print">
            <div style="font-size: 18px; font-weight: 600; margin-bottom: 10px;">DESTINATION STORE:</div>
            <h2><?= strtoupper($transfer['to_store']) ?></h2>
            <div class="address"><?= $transfer['to_address'] ?></div>
        </div>

        <!-- Print Controls -->
        <div class="print-controls no-print">
            <div>
                <strong>Total Boxes:</strong> <?= $totalBoxes ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="window.history.back()">
                    <i class="fa fa-arrow-left"></i> Back to Packing
                </button>
                <button class="btn btn-primary btn-lg" onclick="window.print()">
                    <i class="fa fa-print"></i> Print Labels Only (No Submit)
                </button>
                <button class="btn btn-success btn-lg" onclick="printAndContinue()">
                    <i class="fa fa-print"></i> Print & Continue to Shipping
                </button>
            </div>
        </div>

        <!-- Labels Preview / Print Area -->
        <div class="labels-preview">
            <?php foreach ($transfer['boxes'] as $box): ?>
                <div class="box-label">
                    <!-- Box Number -->
                    <div class="box-label-header">
                        <div class="box-number">BOX <?= $box['box_number'] ?></div>
                        <div class="box-total">OF <?= $totalBoxes ?></div>
                    </div>

                    <!-- Destination (HUGE) -->
                    <div class="destination-section">
                        <div class="destination-label">DESTINATION:</div>
                        <div class="destination-name"><?= strtoupper($transfer['to_store']) ?></div>
                        <div class="destination-address"><?= $transfer['to_address'] ?></div>
                    </div>

                    <!-- Transfer Details -->
                    <div class="transfer-details">
                        <div class="detail-item">
                            <div class="detail-label">Transfer ID</div>
                            <div class="detail-value">#<?= $transfer['id'] ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">From Store</div>
                            <div class="detail-value" style="font-size: 12px;"><?= $transfer['from_store'] ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Weight</div>
                            <div class="detail-value"><?= $box['weight'] ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Items</div>
                            <div class="detail-value"><?= $box['items'] ?> items</div>
                        </div>
                    </div>

                    <!-- Tracking Number (if exists) -->
                    <div class="tracking-section">
                        <div class="tracking-label">Courier Tracking Number:</div>
                        <?php if (!empty($box['tracking'])): ?>
                            <div class="tracking-number"><?= $box['tracking'] ?></div>
                        <?php else: ?>
                            <div class="tracking-empty">Not yet generated</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function printAndContinue() {
            if (confirm('This will print the box labels and then take you to generate shipping labels via courier API. Continue?')) {
                window.print();

                // After print dialog closes, redirect to shipping label generation
                setTimeout(function() {
                    // Replace with actual shipping label page
                    window.location.href = 'generate-shipping-labels.php?transfer_id=<?= $transfer['id'] ?>';
                }, 1000);
            }
        }

        // Keyboard shortcut: Ctrl+P
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
