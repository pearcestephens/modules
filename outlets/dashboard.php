<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outlets Management - The Vape Shed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/outlets.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <h5 class="text-white px-3">CIS Portal</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="dashboard.php">
                                <i class="bi bi-shop"></i> Outlets
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-secondary" href="../business-intelligence/dashboard.php">
                                <i class="bi bi-graph-up"></i> Business Intelligence
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-shop-window text-primary"></i> Outlets Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="viewGrid">
                                <i class="bi bi-grid-3x3"></i> Grid
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="viewList">
                                <i class="bi bi-list-ul"></i> List
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="viewMap">
                                <i class="bi bi-geo-alt"></i> Map
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOutletModal">
                            <i class="bi bi-plus-circle"></i> Add Outlet
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Total Outlets</h6>
                                <h2 class="card-title" id="totalOutlets">19</h2>
                                <small class="text-success"><i class="bi bi-arrow-up"></i> +2 this year</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Active Stores</h6>
                                <h2 class="card-title text-success" id="activeStores">17</h2>
                                <small class="text-muted">2 temp closed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Lease Expiring Soon</h6>
                                <h2 class="card-title text-warning" id="expiringLeases">3</h2>
                                <small class="text-muted">Within 6 months</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Avg Monthly Revenue</h6>
                                <h2 class="card-title text-info" id="avgRevenue">$85K</h2>
                                <small class="text-success"><i class="bi bi-arrow-up"></i> +12% vs last year</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select form-select-sm" id="filterStatus">
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="closed_temporary">Temp Closed</option>
                                    <option value="closed_permanent">Permanently Closed</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">City</label>
                                <select class="form-select form-select-sm" id="filterCity">
                                    <option value="">All Cities</option>
                                    <option value="Auckland">Auckland</option>
                                    <option value="Wellington">Wellington</option>
                                    <option value="Christchurch">Christchurch</option>
                                    <option value="Hamilton">Hamilton</option>
                                    <option value="Tauranga">Tauranga</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control form-control-sm" id="searchOutlet" placeholder="Search outlets...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sort By</label>
                                <select class="form-select form-select-sm" id="sortBy">
                                    <option value="name">Name</option>
                                    <option value="revenue">Revenue</option>
                                    <option value="opened">Opened Date</option>
                                    <option value="city">City</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Outlets Grid -->
                <div id="outletsGrid" class="row g-3">
                    <!-- Dynamic content loaded here -->
                </div>

                <!-- Outlets List View (hidden by default) -->
                <div id="outletsListView" class="d-none">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Outlet</th>
                                <th>City</th>
                                <th>Status</th>
                                <th>Manager</th>
                                <th>Lease Expires</th>
                                <th>Revenue (30d)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="outletsTableBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>

                <!-- Map View (hidden by default) -->
                <div id="outletsMapView" class="d-none">
                    <div id="map" style="height: 600px; border-radius: 8px;"></div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Outlet Modal -->
    <div class="modal fade" id="addOutletModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Outlet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addOutletForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Outlet Name *</label>
                                <input type="text" class="form-control" name="outlet_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Outlet Code *</label>
                                <input type="text" class="form-control" name="outlet_code" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Street Address *</label>
                                <input type="text" class="form-control" name="street_address" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City *</label>
                                <input type="text" class="form-control" name="city" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Region</label>
                                <input type="text" class="form-control" name="region">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Postcode</label>
                                <input type="text" class="form-control" name="postcode">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveOutlet">Save Outlet</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY"></script>
    <script src="../assets/js/outlets.js"></script>
</body>
</html>
