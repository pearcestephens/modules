<?php

// Get CIS database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
/**
 * Dashboard Files Page
 * Browse and manage project files with filtering and search
 *
 * @package hdgwrzntwa/dashboard/admin
 * @category Dashboard Page
 */

$projectId = 1;
$page = isset($_GET['file_page']) ? (int)$_GET['file_page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$fileType = $_GET['type'] ?? '';


// Build query - use intelligence_files table which has actual file data
$query = "SELECT * FROM intelligence_files WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND file_path LIKE ?";
    $params[] = "%{$search}%";
}

if ($fileType) {
    $query .= " AND file_type = ?";
    $params[] = $fileType;
}

$limitInt = (int)$limit;
$offsetInt = (int)$offset;
$query .= " ORDER BY file_path ASC LIMIT $limitInt OFFSET $offsetInt";

// Get total count
$countQuery = "SELECT COUNT(*) FROM intelligence_files WHERE 1=1";
$countParams = [];
if ($search) {
    $countQuery .= " AND file_path LIKE ?";
    $countParams[] = "%{$search}%";
}
if ($fileType) {
    $countQuery .= " AND file_type = ?";
    $countParams[] = $fileType;
}

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($countParams);
$totalFiles = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalFiles / $limit);

// Get files
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get file type summary
$typeQuery = "
    SELECT file_type, COUNT(*) as count
    FROM intelligence_files
    WHERE 1=1
    GROUP BY file_type
    ORDER BY count DESC
";
$typeStmt = $pdo->prepare($typeQuery);
$typeStmt->execute([]);
$fileTypes = $typeStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Project Files</h1>
            <p class="text-muted">Browse and manage <?php echo number_format($totalFiles); ?> project files</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fas fa-upload"></i> Import Files
            </button>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search files..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-control" id="typeFilter">
                        <option value="">All File Types</option>
                        <?php foreach ($fileTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['file_type']); ?>"
                                    <?php echo $fileType === $type['file_type'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(strtoupper($type['file_type'])); ?> (<?php echo $type['count']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary w-100" onclick="applyFilters()">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- File Type Summary -->
    <div class="row mb-4">
        <?php foreach ($fileTypes as $type): ?>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase"><?php echo htmlspecialchars($type['file_type']); ?></h6>
                        <h3><?php echo number_format($type['count']); ?></h3>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Files Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>File Path</th>
                        <th>Type</th>
                        <th>Dependencies</th>
                        <th>Modified</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox"></i> No files found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($files as $file): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars(substr($file['file_path'], -60)); ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars(strtoupper($file['file_type'])); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-warning"><?php echo $file['dependency_count'] ?? 0; ?></span>
                                </td>
                                <td>
                                    <?php echo $file['last_modified'] ? date('M j, Y', strtotime($file['last_modified'])) : 'N/A'; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewFileDetails('<?php echo $file['id']; ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=files&file_page=1&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($fileType); ?>">First</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=files&file_page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($fileType); ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=files&file_page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($fileType); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=files&file_page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($fileType); ?>">Next</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=files&file_page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($fileType); ?>">Last</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const type = document.getElementById('typeFilter').value;
    window.location = `?page=files&search=${encodeURIComponent(search)}&type=${encodeURIComponent(type)}`;
}

function viewFileDetails(fileId) {
    API.get(`/dashboard/api/files/details/${fileId}`, function(data) {
        console.log(data);
        alert('File details: ' + JSON.stringify(data.data, null, 2));
    });
}

// Allow Enter to trigger search
document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') applyFilters();
});
</script>

<style>
.table tbody tr:hover {
    background-color: #f8f9fa;
}

code {
    background-color: #f5f5f5;
    padding: 4px 8px;
    border-radius: 3px;
    color: #d63384;
    font-size: 12px;
}

.badge {
    font-size: 11px;
    padding: 4px 8px;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
}
</style>
