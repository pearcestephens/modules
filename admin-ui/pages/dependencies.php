<?php
/**
 * Dashboard Dependencies Page
 * Visualize file dependencies and relationships
 *
 * @package hdgwrzntwa/dashboard/admin
 * @category Dashboard Page
 */

$projectId = 1;
$pdo = new PDO("mysql:host=localhost;dbname=hdgwrzntwa", "hdgwrzntwa", "bFUdRjh4Jx");

// Get dependency graph data
$query = "
    SELECT
        id,
        source_file,
        target_file,
        dependency_type as relationship_type,
        1 as weight
    FROM code_dependencies
    ORDER BY id DESC
    LIMIT 50
";

$stmt = $pdo->prepare($query);
$stmt->execute([]);
$dependencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get circular dependencies
$circularQuery = "
    SELECT
        chain as file_chain,
        severity,
        dependency_type,
        detected_at
    FROM circular_dependencies
    LIMIT 20
";

$circularStmt = $pdo->prepare($circularQuery);
$circularStmt->execute([]);
$circularDeps = $circularStmt->fetchAll(PDO::FETCH_ASSOC);

// Get most depended on files
$mostDepended = "
    SELECT
        target_file as file_path,
        COUNT(*) as depended_count
    FROM code_dependencies
    WHERE target_file IS NOT NULL
    GROUP BY target_file
    ORDER BY depended_count DESC
    LIMIT 10
";

$deptStmt = $pdo->prepare($mostDepended);
$deptStmt->execute([]);
$mostDependedFiles = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1>Dependencies</h1>
        <p class="text-muted">View file relationships and dependency analysis</p>
    </div>

    <!-- Alerts for Circular Dependencies -->
    <?php if (!empty($circularDeps)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <strong>Circular Dependencies Found!</strong>
            <p class="mb-0">Your project has <?php echo count($circularDeps); ?> circular dependency patterns that should be refactored.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Most Depended On Files -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Most Depended On Files (Core Files)</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if (empty($mostDependedFiles)): ?>
                            <p class="text-muted">No dependencies found</p>
                        <?php else: ?>
                            <?php foreach ($mostDependedFiles as $file): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <code><?php echo htmlspecialchars(substr($file['file_path'], -50)); ?></code>
                                    <span class="badge bg-danger"><?php echo $file['depended_count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Circular Dependencies Alert -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Circular Dependencies</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($circularDeps)): ?>
                        <div class="alert alert-success mb-0" role="alert">
                            <i class="fas fa-check-circle"></i> No circular dependencies detected!
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($circularDeps as $circ): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <code><?php echo htmlspecialchars(substr($circ['file1'], -40)); ?></code>
                                        <i class="fas fa-exchange-alt text-danger"></i>
                                        <code><?php echo htmlspecialchars(substr($circ['file2'], -40)); ?></code>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Dependency Graph -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Dependency Distribution</h6>
        </div>
        <div class="card-body">
            <canvas id="dependencyChart" height="80"></canvas>
        </div>
    </div>

    <!-- Dependency Table -->
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">All Dependencies</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>File</th>
                        <th>Type</th>
                        <th>Dependencies</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($dependencies, 0, 20) as $dep): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars(substr($dep['file_path'], -50)); ?></code></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($dep['dependency_type']); ?></span></td>
                            <td><span class="badge bg-warning"><?php echo $dep['dependency_count']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('dependencyChart')?.getContext('2d');
    if (ctx) {
        const depData = <?php echo json_encode(array_slice(array_map(fn($d) => $d['dependency_count'], $dependencies), 0, 10)); ?>;
        const labels = Array.from({length: depData.length}, (_, i) => `Rank ${i+1}`);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Dependency Count',
                    data: depData,
                    backgroundColor: '#4e73df',
                    borderColor: '#224abe',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
</script>
