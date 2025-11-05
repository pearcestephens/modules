<?php
/**
 * Mobile Photo Upload Page
 *
 * Accessed via QR code - no login required
 * Uses temporary session token for authentication
 */

$token = $_GET['token'] ?? null;

if (!$token) {
    die('Invalid access link');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Upload Photos - CIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .upload-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .timer {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            text-align: center;
            padding: 20px;
        }

        .timer.warning {
            color: #f39c12;
        }

        .timer.danger {
            color: #e74c3c;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .upload-area {
            border: 3px dashed #dee2e6;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: #e9ecef;
        }

        .upload-area.dragover {
            border-color: #667eea;
            background: #e3e8ff;
        }

        .upload-area i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 16px;
        }

        .photo-preview {
            position: relative;
            display: inline-block;
            margin: 10px;
        }

        .photo-preview img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }

        .photo-preview .remove-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            background: #e74c3c;
            color: white;
            border: 2px solid white;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .upload-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 32px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            margin-top: 20px;
        }

        .stats {
            display: flex;
            justify-content: space-around;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
        }

        #cameraPreview {
            width: 100%;
            border-radius: 12px;
            margin-bottom: 16px;
            display: none;
        }

        .expired-message {
            text-align: center;
            padding: 40px 20px;
        }

        .expired-message i {
            font-size: 64px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="upload-container" id="app">
        <!-- Loading State -->
        <div id="loadingState" class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Validating session...</p>
            </div>
        </div>

        <!-- Valid Session State -->
        <div id="uploadState" style="display: none;">
            <div class="card">
                <div class="card-body">
                    <h4 class="text-center mb-3">
                        <i class="fas fa-camera"></i> Upload Photos
                    </h4>

                    <div class="timer" id="timer">
                        <i class="far fa-clock"></i> <span id="timeRemaining">15:00</span>
                    </div>

                    <p class="text-center text-muted mb-4">
                        Upload photos of damaged or repaired items
                    </p>

                    <!-- Camera Preview -->
                    <video id="cameraPreview" autoplay playsinline></video>

                    <!-- Upload Area -->
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h5>Tap to take photo or upload</h5>
                        <p class="text-muted mb-0">JPG or PNG, max 10MB</p>
                        <input type="file" id="fileInput" accept="image/*" capture="environment" multiple style="display: none;">
                    </div>

                    <!-- Photo Previews -->
                    <div id="photoPreviews" class="text-center mt-3"></div>

                    <!-- Upload Button -->
                    <button class="btn btn-primary upload-btn" id="uploadBtn" style="display: none;">
                        <i class="fas fa-upload"></i> Upload <span id="photoCount">0</span> Photo(s)
                    </button>

                    <!-- Stats -->
                    <div class="stats">
                        <div class="stat-item">
                            <div class="stat-value" id="statsQueued">0</div>
                            <div class="stat-label">Queued</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="statsUploaded">0</div>
                            <div class="stat-label">Uploaded</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="statsFailed">0</div>
                            <div class="stat-label">Failed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expired Session State -->
        <div id="expiredState" style="display: none;">
            <div class="card">
                <div class="card-body expired-message">
                    <i class="fas fa-clock"></i>
                    <h4>Session Expired</h4>
                    <p class="text-muted">This upload link has expired. Please request a new QR code from the desktop application.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const token = '<?= htmlspecialchars($token) ?>';
        let sessionData = null;
        let timerInterval = null;
        let selectedFiles = [];
        let uploadedCount = 0;
        let failedCount = 0;

        // Initialize
        async function init() {
            // Validate session
            const isValid = await validateSession();

            if (isValid) {
                showUploadState();
                startTimer();
                setupEventListeners();
            } else {
                showExpiredState();
            }
        }

        // Validate session with server
        async function validateSession() {
            try {
                const response = await fetch(`/modules/consignments/api/photo_upload_session.php?action=validate_session&token=${token}`);
                const result = await response.json();

                if (result.success && result.valid) {
                    sessionData = result.session;
                    return true;
                }
            } catch (error) {
                console.error('Session validation failed:', error);
            }
            return false;
        }

        // Show upload state
        function showUploadState() {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('uploadState').style.display = 'block';
        }

        // Show expired state
        function showExpiredState() {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('expiredState').style.display = 'block';
        }

        // Start countdown timer
        function startTimer() {
            let secondsRemaining = sessionData.seconds_remaining;

            timerInterval = setInterval(() => {
                secondsRemaining--;

                if (secondsRemaining <= 0) {
                    clearInterval(timerInterval);
                    showExpiredState();
                    return;
                }

                const minutes = Math.floor(secondsRemaining / 60);
                const seconds = secondsRemaining % 60;
                const timeStr = `${minutes}:${seconds.toString().padStart(2, '0')}`;

                document.getElementById('timeRemaining').textContent = timeStr;

                const timerEl = document.getElementById('timer');
                if (secondsRemaining < 60) {
                    timerEl.className = 'timer danger';
                } else if (secondsRemaining < 300) {
                    timerEl.className = 'timer warning';
                }
            }, 1000);
        }

        // Setup event listeners
        function setupEventListeners() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            const uploadBtn = document.getElementById('uploadBtn');

            // Click to select files
            uploadArea.addEventListener('click', () => fileInput.click());

            // File selection
            fileInput.addEventListener('change', (e) => handleFileSelect(e.target.files));

            // Drag and drop
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                handleFileSelect(e.dataTransfer.files);
            });

            // Upload button
            uploadBtn.addEventListener('click', uploadPhotos);
        }

        // Handle file selection
        function handleFileSelect(files) {
            for (let file of files) {
                if (file.type.startsWith('image/')) {
                    selectedFiles.push(file);
                    addPhotoPreview(file);
                }
            }

            updateUI();
        }

        // Add photo preview
        function addPhotoPreview(file) {
            const reader = new FileReader();
            const index = selectedFiles.length - 1;

            reader.onload = (e) => {
                const preview = document.createElement('div');
                preview.className = 'photo-preview';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <div class="remove-btn" onclick="removePhoto(${index})">
                        <i class="fas fa-times"></i>
                    </div>
                `;

                document.getElementById('photoPreviews').appendChild(preview);
            };

            reader.readAsDataURL(file);
        }

        // Remove photo
        function removePhoto(index) {
            selectedFiles.splice(index, 1);
            updateUI();

            // Rebuild previews
            const container = document.getElementById('photoPreviews');
            container.innerHTML = '';
            selectedFiles.forEach(file => addPhotoPreview(file));
        }

        // Update UI
        function updateUI() {
            const count = selectedFiles.length;
            document.getElementById('photoCount').textContent = count;
            document.getElementById('statsQueued').textContent = count;
            document.getElementById('uploadBtn').style.display = count > 0 ? 'block' : 'none';
        }

        // Upload photos
        async function uploadPhotos() {
            const uploadBtn = document.getElementById('uploadBtn');
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

            for (let file of selectedFiles) {
                const success = await uploadSinglePhoto(file);
                if (success) {
                    uploadedCount++;
                } else {
                    failedCount++;
                }

                document.getElementById('statsUploaded').textContent = uploadedCount;
                document.getElementById('statsFailed').textContent = failedCount;
            }

            // Reset
            selectedFiles = [];
            document.getElementById('photoPreviews').innerHTML = '';
            document.getElementById('statsQueued').textContent = '0';

            uploadBtn.disabled = false;
            uploadBtn.style.display = 'none';
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload <span id="photoCount">0</span> Photo(s)';

            if (failedCount === 0) {
                alert('All photos uploaded successfully!');
            } else {
                alert(`${uploadedCount} photos uploaded, ${failedCount} failed`);
            }
        }

        // Upload single photo
        async function uploadSinglePhoto(file) {
            const formData = new FormData();
            formData.append('action', 'upload_photo');
            formData.append('token', token);
            formData.append('photo', file);

            try {
                const response = await fetch('/modules/consignments/api/photo_upload_session.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                return result.success;
            } catch (error) {
                console.error('Upload failed:', error);
                return false;
            }
        }

        // Start app
        init();
    </script>
</body>
</html>
