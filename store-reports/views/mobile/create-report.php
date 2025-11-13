<?php
/**
 * Store Reports - Mobile Report Creation
 * Mobile-first PWA interface for store managers
 */
require_once __DIR__ . '/../../../../private_html/check-login.php';
require_once __DIR__ . '/../../config.php';

// Get user info
$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user_name'] ?? 'Unknown';
$userOutlets = $_SESSION['user_outlets'] ?? []; // Array of outlet IDs user can access

// Get outlets for dropdown
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // If user has specific outlets, filter to those
    if (!empty($userOutlets)) {
        $placeholders = str_repeat('?,', count($userOutlets) - 1) . '?';
        $stmt = $db->prepare("SELECT id, name FROM vend_outlets WHERE id IN ($placeholders) ORDER BY name");
        $stmt->execute($userOutlets);
    } else {
        $stmt = $db->query("SELECT id, name FROM vend_outlets ORDER BY name");
    }
    $outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $outlets = [];
    error_log("Store Reports - Failed to load outlets: " . $e->getMessage());
}

// Get active checklist version
try {
    $stmt = $db->query("SELECT id, version_name, effective_date FROM store_report_checklist_versions WHERE is_active = 1 ORDER BY effective_date DESC LIMIT 1");
    $activeChecklist = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeChecklist) {
        // Get checklist categories and items
        $stmt = $db->prepare("SELECT * FROM store_report_checklist_categories WHERE version_id = ? ORDER BY display_order");
        $stmt->execute([$activeChecklist['id']]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categories as &$category) {
            $stmt = $db->prepare("SELECT * FROM store_report_checklist_items WHERE category_id = ? ORDER BY display_order");
            $stmt->execute([$category['id']]);
            $category['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $categories = [];
    }
} catch (PDOException $e) {
    $categories = [];
    error_log("Store Reports - Failed to load checklist: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#1a1a2e">
    <title>Create Store Report - Mobile</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Store Reports Mobile CSS -->
    <link rel="stylesheet" href="/assets/css/store-reports/mobile.css">

    <style>
        /* Mobile-first critical CSS */
        :root {
            --sr-primary: #4a90e2;
            --sr-success: #4caf50;
            --sr-warning: #ff9800;
            --sr-danger: #f44336;
            --sr-dark: #1a1a2e;
            --sr-light: #f5f6fa;
        }

        body {
            background: var(--sr-light);
            padding-top: 56px; /* Fixed header height */
            padding-bottom: 70px; /* Fixed footer height */
            overflow-x: hidden;
        }

        /* Fixed Header */
        .sr-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: var(--sr-dark);
            color: white;
            padding: 12px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .sr-header h1 {
            font-size: 18px;
            margin: 0;
            font-weight: 600;
        }

        /* Progress Bar */
        .sr-progress {
            position: fixed;
            top: 56px;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(255,255,255,0.1);
            z-index: 999;
        }

        .sr-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--sr-primary), var(--sr-success));
            transition: width 0.3s ease;
            width: 0%;
        }

        /* Main Content */
        .sr-content {
            padding: 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Section Cards */
        .sr-section {
            background: white;
            border-radius: 12px;
            margin-bottom: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .sr-section-header {
            padding: 16px;
            background: linear-gradient(135deg, var(--sr-dark), #2c2c54);
            color: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sr-section-header h3 {
            font-size: 16px;
            margin: 0;
            font-weight: 600;
        }

        .sr-section-badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
        }

        .sr-section-body {
            padding: 16px;
        }

        /* Checklist Items */
        .sr-checklist-item {
            border-bottom: 1px solid #eee;
            padding: 16px 0;
        }

        .sr-checklist-item:last-child {
            border-bottom: none;
        }

        .sr-item-label {
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 12px;
            display: block;
        }

        .sr-item-description {
            font-size: 13px;
            color: #666;
            margin-bottom: 12px;
        }

        /* Button Grid */
        .sr-button-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }

        .sr-btn {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .sr-btn.active {
            border-color: var(--sr-primary);
            background: var(--sr-primary);
            color: white;
        }

        .sr-btn-pass.active {
            border-color: var(--sr-success);
            background: var(--sr-success);
        }

        .sr-btn-fail.active {
            border-color: var(--sr-danger);
            background: var(--sr-danger);
        }

        /* Photo/Voice Actions */
        .sr-actions {
            display: flex;
            gap: 8px;
        }

        .sr-action-btn {
            flex: 1;
            padding: 10px;
            border: 1px dashed #ddd;
            border-radius: 8px;
            background: #f9f9f9;
            font-size: 12px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }

        .sr-action-btn:hover {
            border-color: var(--sr-primary);
            background: #e3f2fd;
        }

        .sr-action-btn i {
            display: block;
            font-size: 20px;
            margin-bottom: 4px;
        }

        /* Camera Preview */
        .sr-camera-preview {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: black;
            z-index: 9999;
            display: none;
        }

        .sr-camera-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sr-camera-controls {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .sr-camera-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            border: 3px solid #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .sr-camera-btn-capture {
            width: 70px;
            height: 70px;
            background: var(--sr-danger);
        }

        /* Fixed Footer */
        .sr-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #ddd;
            padding: 12px 16px;
            display: flex;
            gap: 8px;
            z-index: 1000;
        }

        .sr-footer-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .sr-footer-btn-secondary {
            background: #f5f5f5;
            color: #333;
        }

        .sr-footer-btn-primary {
            background: var(--sr-success);
            color: white;
        }

        /* Loading Overlay */
        .sr-loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            color: white;
            flex-direction: column;
            gap: 16px;
        }

        .sr-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* AI Chat Bubble */
        .sr-ai-fab {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 999;
            font-size: 24px;
        }

        /* Responsive */
        @media (max-width: 375px) {
            .sr-button-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="sr-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1><i class="fas fa-clipboard-check me-2"></i>Store Report</h1>
            <div>
                <span class="badge bg-light text-dark" id="sr-status">Draft</span>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="sr-progress">
        <div class="sr-progress-bar" id="sr-progress-bar"></div>
    </div>

    <!-- Main Content -->
    <div class="sr-content">
        <!-- Store Selection -->
        <div class="sr-section">
            <div class="sr-section-header">
                <h3><i class="fas fa-store me-2"></i>Store Location</h3>
            </div>
            <div class="sr-section-body">
                <select class="form-select" id="sr-outlet-select" required>
                    <option value="">Select your store...</option>
                    <?php foreach ($outlets as $outlet): ?>
                        <option value="<?= htmlspecialchars($outlet['id']) ?>">
                            <?= htmlspecialchars($outlet['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Checklist Sections -->
        <?php foreach ($categories as $category): ?>
        <div class="sr-section" data-category-id="<?= $category['id'] ?>">
            <div class="sr-section-header" onclick="toggleSection(this)">
                <h3>
                    <i class="fas <?= $category['icon'] ?? 'fa-list' ?> me-2"></i>
                    <?= htmlspecialchars($category['category_name']) ?>
                </h3>
                <div>
                    <span class="sr-section-badge" id="badge-<?= $category['id'] ?>">
                        0/<?= count($category['items']) ?>
                    </span>
                    <i class="fas fa-chevron-down ms-2"></i>
                </div>
            </div>
            <div class="sr-section-body" style="display: none;">
                <?php foreach ($category['items'] as $item): ?>
                <div class="sr-checklist-item" data-item-id="<?= $item['id'] ?>">
                    <label class="sr-item-label">
                        <?= htmlspecialchars($item['item_text']) ?>
                        <?php if ($item['is_critical']): ?>
                            <i class="fas fa-exclamation-triangle text-danger ms-1" title="Critical Item"></i>
                        <?php endif; ?>
                    </label>

                    <?php if ($item['description']): ?>
                    <div class="sr-item-description">
                        <?= htmlspecialchars($item['description']) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Response Buttons -->
                    <div class="sr-button-grid">
                        <button class="sr-btn sr-btn-pass" data-value="pass" onclick="setItemResponse(<?= $item['id'] ?>, 'pass', this)">
                            <i class="fas fa-check"></i> Pass
                        </button>
                        <button class="sr-btn sr-btn-fail" data-value="fail" onclick="setItemResponse(<?= $item['id'] ?>, 'fail', this)">
                            <i class="fas fa-times"></i> Fail
                        </button>
                        <button class="sr-btn" data-value="na" onclick="setItemResponse(<?= $item['id'] ?>, 'na', this)">
                            <i class="fas fa-minus"></i> N/A
                        </button>
                    </div>

                    <!-- Actions -->
                    <div class="sr-actions">
                        <button class="sr-action-btn" onclick="takePhoto(<?= $item['id'] ?>)">
                            <i class="fas fa-camera"></i>
                            <span>Photo</span>
                        </button>
                        <button class="sr-action-btn" onclick="recordVoice(<?= $item['id'] ?>)">
                            <i class="fas fa-microphone"></i>
                            <span>Voice Memo</span>
                        </button>
                    </div>

                    <!-- Notes -->
                    <textarea
                        class="form-control form-control-sm mt-2"
                        placeholder="Add notes (optional)..."
                        id="notes-<?= $item['id'] ?>"
                        rows="2"
                        style="font-size: 13px;"
                    ></textarea>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Camera Preview -->
    <div class="sr-camera-preview" id="sr-camera">
        <video id="sr-camera-video" autoplay playsinline></video>
        <canvas id="sr-camera-canvas" style="display: none;"></canvas>
        <div class="sr-camera-controls">
            <button class="sr-camera-btn" onclick="closeCamera()">
                <i class="fas fa-times"></i>
            </button>
            <button class="sr-camera-btn sr-camera-btn-capture" onclick="capturePhoto()">
                <i class="fas fa-camera"></i>
            </button>
            <button class="sr-camera-btn" onclick="switchCamera()">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- AI Assistant FAB -->
    <div class="sr-ai-fab" onclick="openAIChat()" title="AI Assistant">
        <i class="fas fa-robot"></i>
    </div>

    <!-- Footer Actions -->
    <div class="sr-footer">
        <button class="sr-footer-btn sr-footer-btn-secondary" onclick="saveDraft()">
            <i class="fas fa-save me-1"></i> Save Draft
        </button>
        <button class="sr-footer-btn sr-footer-btn-primary" onclick="submitReport()">
            <i class="fas fa-check-circle me-1"></i> Submit Report
        </button>
    </div>

    <!-- Loading Overlay -->
    <div class="sr-loading" id="sr-loading">
        <div class="sr-spinner"></div>
        <div id="sr-loading-text">Processing...</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/store-reports/mobile.js"></script>
    <script>
        // Initialize report data
        const reportData = {
            userId: <?= $userId ?>,
            outletId: null,
            checklistVersionId: <?= $activeChecklist['id'] ?? 'null' ?>,
            items: {},
            photos: [],
            voiceMemos: [],
            status: 'draft'
        };

        // Auto-save every 30 seconds
        setInterval(() => {
            if (reportData.outletId) {
                autoSave();
            }
        }, 30000);

        // Update progress on any change
        document.addEventListener('change', updateProgress);
        document.addEventListener('input', updateProgress);

        // Initialize
        updateProgress();
    </script>
</body>
</html>
