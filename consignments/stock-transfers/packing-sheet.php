<?php
/**
 * Wholesale Packing Sheet - Printer-Friendly Document
 * Purpose: Professional packing/verification document for warehouse staff
 * Features: Tick boxes, dual signatures, photo verification prompts
 */

// Mock data - replace with real transfer data
$transferId = $_GET['transfer_id'] ?? '12345';
$fromStore = 'The Vape Shed Hamilton (TVS-HAM)';
$toStore = 'The Vape Shed Tauranga (TVS-TRG)';
$toAddress = '12 Cameron Road, Tauranga 3110, New Zealand';
$toPhone = '07 555 1234';
$packingDate = date('d/m/Y');
$expectedDelivery = date('d/m/Y', strtotime('+2 days'));
$freight = 'NZ Post Standard';
$tracking = 'NZP123456789';

// Mock products
$products = [
    ['sku' => 'JUUL-POD-VM', 'name' => 'JUUL Pods - Virginia Tobacco', 'qty' => 24, 'box' => 1],
    ['sku' => 'VUSE-POD-MM', 'name' => 'VUSE ePod Mint', 'qty' => 18, 'box' => 1],
    ['sku' => 'SMOK-NORD4', 'name' => 'SMOK Nord 4 Kit', 'qty' => 6, 'box' => 2],
    ['sku' => 'VAPE-JUICE-50', 'name' => 'Premium E-Liquid 50ml', 'qty' => 12, 'box' => 2],
    ['sku' => 'COIL-MESH-08', 'name' => 'Mesh Coils 0.8Ω (5pk)', 'qty' => 10, 'box' => 2],
    ['sku' => 'DEVICE-ASPIRE', 'name' => 'Aspire PockeX Starter Kit', 'qty' => 4, 'box' => 3],
    ['sku' => 'BATTERY-18650', 'name' => '18650 Battery 3000mAh', 'qty' => 8, 'box' => 3],
    ['sku' => 'CHARGER-USB-C', 'name' => 'USB-C Fast Charger', 'qty' => 5, 'box' => 3],
];

$totalItems = array_sum(array_column($products, 'qty'));
$totalBoxes = max(array_column($products, 'box'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing Sheet - Transfer #<?= $transferId ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 11pt; line-height: 1.4; color: #1a1a1a; background: #fff; padding: 20mm; }
        .page { max-width: 210mm; margin: 0 auto; background: #fff; }
        .header { border-bottom: 3px solid #0366d6; padding-bottom: 8mm; margin-bottom: 6mm; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4mm; }
        .logo { font-size: 20pt; font-weight: 800; color: #0366d6; letter-spacing: -0.5px; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 16pt; font-weight: 700; color: #1a1a1a; margin-bottom: 2mm; }
        .doc-title .transfer-id { font-size: 22pt; font-weight: 800; color: #0366d6; }
        .transfer-info { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; margin-bottom: 6mm; }
        .info-box { border: 2px solid #d8dee4; border-radius: 3mm; padding: 3mm 4mm; background: #f8fafc; }
        .info-box h2 { font-size: 10pt; font-weight: 700; text-transform: uppercase; color: #57606a; margin-bottom: 2mm; letter-spacing: 0.5px; }
        .info-box .store-name { font-size: 12pt; font-weight: 700; color: #1a1a1a; margin-bottom: 1mm; }
        .info-box .detail { font-size: 9pt; color: #4a5568; margin-bottom: 0.5mm; }
        .info-box .detail strong { color: #1a1a1a; font-weight: 600; }
        .alert-box { background: #fff8e1; border: 2px solid #f59e0b; border-radius: 3mm; padding: 3mm 4mm; margin-bottom: 6mm; }
        .alert-box strong { color: #8d6e00; font-weight: 700; font-size: 10pt; display: block; margin-bottom: 1mm; }
        .alert-box p { font-size: 9pt; color: #4a5568; }
        .products-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        .products-table thead { background: #0366d6; color: #fff; }
        .products-table th { text-align: left; padding: 3mm; font-size: 9pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .products-table tbody tr { border-bottom: 1px solid #e5e7eb; }
        .products-table tbody tr:nth-child(even) { background: #f8fafc; }
        .products-table td { padding: 2.5mm 3mm; font-size: 10pt; vertical-align: middle; }
        .products-table td.checkbox { text-align: center; width: 15mm; }
        .products-table td.checkbox input { width: 5mm; height: 5mm; cursor: pointer; }
        .checkbox-cell { width: 20mm; text-align: center; }
        .checkbox-spacer { display: inline-block; width: 5mm; height: 5mm; border: 1.5px solid #6a737d; border-radius: 1mm; vertical-align: middle; }
        .qty-cell { text-align: center; width: 15mm; font-weight: 700; color: #0366d6; font-size: 11pt; }
        .box-cell { text-align: center; width: 15mm; font-weight: 700; background: #e6f4ff; color: #0366d6; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3mm; margin-bottom: 6mm; }
        .summary-box { border: 2px solid #d8dee4; border-radius: 3mm; padding: 2mm 3mm; text-align: center; background: #f8fafc; }
        .summary-box .label { font-size: 8pt; font-weight: 600; text-transform: uppercase; color: #57606a; margin-bottom: 1mm; }
        .summary-box .value { font-size: 18pt; font-weight: 800; color: #0366d6; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 5mm; margin-top: 8mm; page-break-inside: avoid; }
        .signature-box { border: 2px solid #d8dee4; border-radius: 3mm; padding: 4mm; background: #f8fafc; }
        .signature-box h3 { font-size: 10pt; font-weight: 700; color: #1a1a1a; margin-bottom: 3mm; text-transform: uppercase; }
        .signature-box .sig-line { border-bottom: 2px solid #1a1a1a; height: 15mm; margin-bottom: 2mm; }
        .signature-box .sig-label { font-size: 8pt; color: #57606a; margin-bottom: 1mm; }
        .signature-box .sig-input { border-bottom: 1px solid #6a737d; padding: 1mm 0; margin-bottom: 2mm; font-size: 9pt; color: #4a5568; }
        .photo-prompt { border: 2px dashed #0366d6; border-radius: 3mm; padding: 4mm; text-align: center; background: #e6f4ff; margin-top: 6mm; page-break-inside: avoid; }
        .photo-prompt h3 { font-size: 11pt; font-weight: 700; color: #0366d6; margin-bottom: 2mm; }
        .photo-prompt p { font-size: 9pt; color: #4a5568; margin-bottom: 2mm; }
        .photo-boxes { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3mm; margin-top: 3mm; }
        .photo-box { border: 2px solid #d8dee4; border-radius: 2mm; padding: 10mm; background: #fff; min-height: 30mm; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 8pt; }
        .footer { margin-top: 8mm; padding-top: 4mm; border-top: 2px solid #e5e7eb; font-size: 8pt; color: #6a737d; text-align: center; }

        @media print {
            body { padding: 10mm; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            .page-break-avoid { page-break-inside: avoid; }
        }

        .print-controls { position: fixed; top: 10mm; right: 10mm; z-index: 1000; display: flex; gap: 2mm; }
        .print-controls button { padding: 2mm 4mm; font-size: 10pt; border: 2px solid #0366d6; background: #0366d6; color: #fff; border-radius: 2mm; cursor: pointer; font-weight: 600; }
        .print-controls button:hover { background: #0256c4; }
        .print-controls button.secondary { background: #fff; color: #0366d6; }
        .print-controls button.secondary:hover { background: #f0f7ff; }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button onclick="window.print()">🖨️ Print</button>
        <button class="secondary" onclick="window.close()">✕ Close</button>
    </div>

    <div class="page">
        <!-- HEADER -->
        <div class="header">
            <div class="header-top">
                <div class="logo">THE VAPE SHED</div>
                <div class="doc-title">
                    <h1>WHOLESALE PACKING SHEET</h1>
                    <div class="transfer-id">#<?= $transferId ?></div>
                </div>
            </div>
            <div style="font-size: 9pt; color: #6a737d;">
                <strong>Date Issued:</strong> <?= $packingDate ?> &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong>Expected Delivery:</strong> <?= $expectedDelivery ?> &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong>Courier:</strong> <?= $freight ?> &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong>Tracking:</strong> <?= $tracking ?>
            </div>
        </div>

        <!-- TRANSFER INFO -->
        <div class="transfer-info">
            <div class="info-box">
                <h2>📦 From (Packing Location)</h2>
                <div class="store-name"><?= $fromStore ?></div>
                <div class="detail"><strong>Packed By:</strong> _______________________________</div>
                <div class="detail"><strong>Date Packed:</strong> ______ / ______ / __________</div>
                <div class="detail"><strong>Time Packed:</strong> ______ : ______</div>
            </div>
            <div class="info-box">
                <h2>🏪 To (Destination)</h2>
                <div class="store-name"><?= $toStore ?></div>
                <div class="detail"><?= $toAddress ?></div>
                <div class="detail"><strong>Phone:</strong> <?= $toPhone ?></div>
                <div class="detail"><strong>Received By:</strong> _______________________________</div>
            </div>
        </div>

        <!-- ALERT BOX -->
        <div class="alert-box">
            <strong>⚠️ IMPORTANT PACKING INSTRUCTIONS</strong>
            <p>✓ Verify ALL items against this list • ✓ Check for damage before packing • ✓ Tick each item as packed • ✓ Sign and date at bottom • ✓ Place ONE copy inside Box #1 • ✓ Keep ONE copy for your records</p>
        </div>

        <!-- PRODUCTS TABLE -->
        <table class="products-table">
            <thead>
                <tr>
                    <th class="checkbox-cell">Packed ✓</th>
                    <th class="checkbox-cell">Received ✓</th>
                    <th>SKU</th>
                    <th>Product Description</th>
                    <th class="qty-cell">Qty</th>
                    <th class="box-cell">Box #</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td class="checkbox"><span class="checkbox-spacer"></span></td>
                    <td class="checkbox"><span class="checkbox-spacer"></span></td>
                    <td style="font-weight: 600; color: #4a5568; font-size: 9pt;"><?= $product['sku'] ?></td>
                    <td><?= $product['name'] ?></td>
                    <td class="qty-cell"><?= $product['qty'] ?></td>
                    <td class="box-cell"><?= $product['box'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- SUMMARY -->
        <div class="summary">
            <div class="summary-box">
                <div class="label">Total Items</div>
                <div class="value"><?= $totalItems ?></div>
            </div>
            <div class="summary-box">
                <div class="label">Total Boxes</div>
                <div class="value"><?= $totalBoxes ?></div>
            </div>
            <div class="summary-box">
                <div class="label">Total Products</div>
                <div class="value"><?= count($products) ?></div>
            </div>
        </div>

        <!-- SIGNATURES -->
        <div class="signatures page-break-avoid">
            <div class="signature-box">
                <h3>✍️ Packer Verification (<?= $fromStore ?>)</h3>
                <div class="sig-label">I confirm I have packed all items listed above and checked for accuracy:</div>
                <div class="sig-line"></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2mm;">
                    <div>
                        <div class="sig-label">Signature:</div>
                        <div class="sig-input">_______________________________</div>
                    </div>
                    <div>
                        <div class="sig-label">Date:</div>
                        <div class="sig-input">______ / ______ / __________</div>
                    </div>
                </div>
                <div class="sig-label">Print Name:</div>
                <div class="sig-input">_______________________________</div>
            </div>

            <div class="signature-box">
                <h3>✅ Receiver Verification (<?= $toStore ?>)</h3>
                <div class="sig-label">I confirm I have received and counted all items listed above:</div>
                <div class="sig-line"></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2mm;">
                    <div>
                        <div class="sig-label">Signature:</div>
                        <div class="sig-input">_______________________________</div>
                    </div>
                    <div>
                        <div class="sig-label">Date:</div>
                        <div class="sig-input">______ / ______ / __________</div>
                    </div>
                </div>
                <div class="sig-label">Print Name:</div>
                <div class="sig-input">_______________________________</div>
            </div>
        </div>

        <!-- PHOTO VERIFICATION -->
        <div class="photo-prompt page-break-avoid">
            <h3>📸 PHOTO VERIFICATION (REQUIRED)</h3>
            <p>Take photos of: (1) All packed boxes together, (2) Box labels/tracking, (3) Any visible damage</p>
            <p style="font-size: 8pt; color: #6a737d; margin-top: 2mm;">Upload photos to: transfers@vapeshed.co.nz with subject "TRANSFER #<?= $transferId ?>"</p>
            <div class="photo-boxes">
                <div class="photo-box">Photo 1: All Boxes</div>
                <div class="photo-box">Photo 2: Labels</div>
                <div class="photo-box">Photo 3: Condition</div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <p>The Vape Shed • Wholesale Transfer Document • Generated: <?= date('d/m/Y H:i') ?></p>
            <p style="font-size: 7pt; margin-top: 2mm; color: #9ca3af;">This document must be retained for 12 months as per company records policy. For queries contact: operations@vapeshed.co.nz</p>
        </div>
    </div>
</body>
</html>
