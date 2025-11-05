<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Photos - Media Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .photo-card {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .photo-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .photo-card.assigned {
            border-color: #28a745;
        }

        .photo-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .photo-card-body {
            padding: 12px;
        }

        .photo-card-body small {
            display: block;
            color: #6c757d;
            margin-top: 4px;
        }

        .badge-issue {
            font-size: 11px;
            padding: 4px 8px;
        }

        .modal-photo {
            max-width: 100%;
            max-height: 70vh;
            margin: 0 auto;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-images"></i> Transfer Photos</h3>
            <button class="btn btn-primary" onclick="refreshPhotos()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 id="totalPhotos" class="text-primary">0</h2>
                        <small class="text-muted">Total Photos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 id="assignedPhotos" class="text-success">0</h2>
                        <small class="text-muted">Assigned</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 id="unassignedPhotos" class="text-warning">0</h2>
                        <small class="text-muted">Unassigned</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 id="damagedCount" class="text-danger">0</h2>
                        <small class="text-muted">Damaged Items</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Photos</h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="filterPhotos('all')">All</button>
                        <button class="btn btn-outline-secondary" onclick="filterPhotos('assigned')">Assigned</button>
                        <button class="btn btn-outline-secondary" onclick="filterPhotos('unassigned')">Unassigned</button>
                        <button class="btn btn-outline-secondary" onclick="filterPhotos('damaged')">Damaged</button>
                    </div>
                </div>

                <div id="photoGrid" class="photo-grid">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Detail Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Photo Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <img id="modalPhoto" class="modal-photo mb-3" src="" alt="Photo">

                    <form id="photoForm">
                        <input type="hidden" id="photoId" name="photo_id">

                        <div class="mb-3">
                            <label class="form-label">Assign to Product</label>
                            <select class="form-select" id="productId" name="product_id">
                                <option value="">-- Select Product --</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Issue Type</label>
                            <select class="form-select" id="issueType" name="issue_type">
                                <option value="">-- Select Issue --</option>
                                <option value="damaged">Damaged</option>
                                <option value="repaired">Repaired</option>
                                <option value="missing">Missing</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-danger" onclick="deletePhoto()">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Assignment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const transferId = <?= $_GET['transfer_id'] ?? 'null' ?>;
        let allPhotos = [];
        let currentFilter = 'all';
        let photoModal;

        document.addEventListener('DOMContentLoaded', function() {
            photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
            loadPhotos();
            loadProducts();

            document.getElementById('photoForm').addEventListener('submit', saveAssignment);
        });

        // Load photos
        async function loadPhotos() {
            try {
                const response = await fetch(`/modules/consignments/api/photo_upload_session.php?action=get_photos&transfer_id=${transferId}`);
                const result = await response.json();

                if (result.success) {
                    allPhotos = result.photos;
                    updateStats();
                    renderPhotos();
                }
            } catch (error) {
                console.error('Failed to load photos:', error);
                alert('Failed to load photos');
            }
        }

        // Update stats
        function updateStats() {
            const assigned = allPhotos.filter(p => p.product_id).length;
            const damaged = allPhotos.filter(p => p.issue_type === 'damaged').length;

            document.getElementById('totalPhotos').textContent = allPhotos.length;
            document.getElementById('assignedPhotos').textContent = assigned;
            document.getElementById('unassignedPhotos').textContent = allPhotos.length - assigned;
            document.getElementById('damagedCount').textContent = damaged;
        }

        // Render photos
        function renderPhotos() {
            const grid = document.getElementById('photoGrid');

            let filtered = allPhotos;
            if (currentFilter === 'assigned') {
                filtered = allPhotos.filter(p => p.product_id);
            } else if (currentFilter === 'unassigned') {
                filtered = allPhotos.filter(p => !p.product_id);
            } else if (currentFilter === 'damaged') {
                filtered = allPhotos.filter(p => p.issue_type === 'damaged');
            }

            if (filtered.length === 0) {
                grid.innerHTML = '<div class="text-center py-5 text-muted">No photos found</div>';
                return;
            }

            grid.innerHTML = filtered.map(photo => `
                <div class="photo-card ${photo.product_id ? 'assigned' : ''}" onclick="showPhotoDetail(${photo.photo_id})">
                    <img src="/uploads/transfer-photos/${transferId}/${photo.filename}" alt="Photo" onerror="this.src='/assets/img/no-image.png'">
                    <div class="photo-card-body">
                        ${photo.issue_type ? `<span class="badge badge-issue bg-${getIssueBadgeColor(photo.issue_type)}">${photo.issue_type}</span>` : ''}
                        <small>${new Date(photo.uploaded_at).toLocaleString()}</small>
                        ${photo.product_id ? `<small class="text-success"><i class="fas fa-check"></i> Assigned</small>` : ''}
                    </div>
                </div>
            `).join('');
        }

        // Filter photos
        function filterPhotos(filter) {
            currentFilter = filter;
            renderPhotos();

            // Update active button
            document.querySelectorAll('.btn-group .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        // Show photo detail
        function showPhotoDetail(photoId) {
            const photo = allPhotos.find(p => p.photo_id === photoId);
            if (!photo) return;

            document.getElementById('photoId').value = photo.photo_id;
            document.getElementById('modalPhoto').src = `/uploads/transfer-photos/${transferId}/${photo.filename}`;
            document.getElementById('productId').value = photo.product_id || '';
            document.getElementById('issueType').value = photo.issue_type || '';
            document.getElementById('notes').value = photo.notes || '';

            photoModal.show();
        }

        // Save assignment
        async function saveAssignment(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('action', 'assign_photo');

            try {
                const response = await fetch('/modules/consignments/api/photo_upload_session.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Photo assigned successfully');
                    photoModal.hide();
                    loadPhotos();
                } else {
                    alert('Failed to assign photo: ' + result.error);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        // Delete photo
        async function deletePhoto() {
            if (!confirm('Are you sure you want to delete this photo?')) return;

            const photoId = document.getElementById('photoId').value;
            const formData = new FormData();
            formData.append('action', 'delete_photo');
            formData.append('photo_id', photoId);

            try {
                const response = await fetch('/modules/consignments/api/photo_upload_session.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Photo deleted successfully');
                    photoModal.hide();
                    loadPhotos();
                } else {
                    alert('Failed to delete photo: ' + result.error);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        // Load products for dropdown
        async function loadProducts() {
            // TODO: Load products for this transfer
            // Populate productId select with options
        }

        // Get badge color for issue type
        function getIssueBadgeColor(type) {
            const colors = {
                'damaged': 'danger',
                'repaired': 'success',
                'missing': 'warning',
                'other': 'secondary'
            };
            return colors[type] || 'secondary';
        }

        // Refresh photos
        function refreshPhotos() {
            loadPhotos();
        }
    </script>
</body>
</html>
