<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Mapping Demo - CIS Staff Accounts</title>
    
    <!-- Bootstrap 4.2 CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/employee-mapping.css">
    
    <style>
        .demo-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .demo-feature {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .demo-feature:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
        }
        
        .demo-feature h5 {
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .test-scenarios {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .scenario-badge {
            background: linear-gradient(45deg, #fd7e14, #ffc107);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-weight: 500;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Demo Header -->
    <div class="demo-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-4 mb-3">
                        <i class="fas fa-user-edit"></i>
                        Manual Mapping Tools Demo
                    </h1>
                    <p class="lead mb-0">
                        Advanced customer search and manual employee-customer mapping system
                    </p>
                    <p class="mt-2 mb-0">
                        <span class="badge badge-light mr-2">56 Unmapped Employees</span>
                        <span class="badge badge-success mr-2">$9,543.36 Blocked</span>
                        <span class="badge badge-info">Real Production Data</span>
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="employee-mapping.php" class="btn btn-light btn-lg">
                        <i class="fas fa-arrow-left"></i> Back to Main System
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Current System Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number">56</div>
                    <div class="text-muted">Unmapped Employees</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-danger">$9,543.36</div>
                    <div class="text-muted">Blocked Amount</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-success">31</div>
                    <div class="text-muted">Auto-Matches Available</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-info">25</div>
                    <div class="text-muted">Manual Review Needed</div>
                </div>
            </div>
        </div>

        <!-- Features Overview -->
        <div class="row">
            <div class="col-md-6">
                <div class="demo-feature">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h5><i class="fas fa-users text-primary"></i> Advanced Customer Search</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Real-time search with autocomplete</li>
                        <li><i class="fas fa-check text-success"></i> Filter by store, email, customer group</li>
                        <li><i class="fas fa-check text-success"></i> Name similarity algorithms</li>
                        <li><i class="fas fa-check text-success"></i> Purchase history analysis</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="demo-feature">
                    <div class="feature-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h5><i class="fas fa-clipboard-check text-info"></i> Smart Validation</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Pre-mapping validation checks</li>
                        <li><i class="fas fa-check text-success"></i> Duplicate mapping prevention</li>
                        <li><i class="fas fa-check text-success"></i> Name similarity scoring</li>
                        <li><i class="fas fa-check text-success"></i> Purchase pattern analysis</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Test Scenarios -->
        <div class="test-scenarios">
            <h4 class="mb-3">
                <i class="fas fa-flask text-warning"></i>
                Test Scenarios Available
            </h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <span class="scenario-badge">Scenario 1</span>
                        <strong>Perfect Name Match</strong>
                        <p class="mt-2 mb-0 text-muted">Employee: "John Smith" → Customer: "John Smith"</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <span class="scenario-badge">Scenario 2</span>
                        <strong>Partial Name Match</strong>
                        <p class="mt-2 mb-0 text-muted">Employee: "Sarah Johnson" → Customer: "S. Johnson"</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <span class="scenario-badge">Scenario 3</span>
                        <strong>Email Match</strong>
                        <p class="mt-2 mb-0 text-muted">Employee: "mike@email.com" → Customer: same email</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Demo Interface -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-desktop"></i>
                    Live Manual Mapping Interface
                </h4>
            </div>
            <div class="card-body p-0">
                <!-- Include the actual manual mapping interface -->
                <div id="manual-mapping-section">
                    <?php include 'manual-mapping.php'; ?>
                </div>
            </div>
        </div>

        <!-- How to Use Guide -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-book text-info"></i>
                            How to Use Manual Mapping
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center mb-3">
                                    <div class="feature-icon mx-auto">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <h6>1. Select Employee</h6>
                                    <p class="text-muted small">Choose an unmapped employee from the dropdown</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center mb-3">
                                    <div class="feature-icon mx-auto">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <h6>2. Search Customers</h6>
                                    <p class="text-muted small">Use advanced filters to find potential matches</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center mb-3">
                                    <div class="feature-icon mx-auto">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <h6>3. Review Details</h6>
                                    <p class="text-muted small">View side-by-side comparison and validation</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center mb-3">
                                    <div class="feature-icon mx-auto">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <h6>4. Confirm Mapping</h6>
                                    <p class="text-muted small">Create the mapping with optional notes</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Technical Details -->
        <div class="row mt-4 mb-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-code text-warning"></i>
                            Technical Implementation
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><i class="fas fa-server text-primary"></i> <strong>Backend:</strong> PHP 8.1 with PDO</li>
                            <li><i class="fas fa-database text-info"></i> <strong>Database:</strong> MySQL with optimized indexes</li>
                            <li><i class="fas fa-shield-alt text-success"></i> <strong>Security:</strong> CSRF protection, input validation</li>
                            <li><i class="fas fa-tachometer-alt text-warning"></i> <strong>Performance:</strong> Pagination, lazy loading</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-cogs text-secondary"></i>
                            System Features
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><i class="fas fa-history text-primary"></i> <strong>Audit Trail:</strong> Complete mapping history</li>
                            <li><i class="fas fa-undo text-info"></i> <strong>Rollback:</strong> Safe transaction handling</li>
                            <li><i class="fas fa-chart-line text-success"></i> <strong>Analytics:</strong> Mapping success rates</li>
                            <li><i class="fas fa-bell text-warning"></i> <strong>Notifications:</strong> Real-time feedback</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js"></script>
    <script src="../js/manual-mapping.js"></script>
    
    <script>
        $(document).ready(function() {
            console.log('Manual Mapping Demo initialized');
            
            // Add demo-specific enhancements
            if (typeof initManualMapping === 'function') {
                initManualMapping();
            }
            
            // Add demo tour functionality
            setTimeout(function() {
                showDemoWelcome();
            }, 1000);
        });
        
        function showDemoWelcome() {
            const alertHtml = `
                <div class="alert alert-info alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                    <h6><i class="fas fa-info-circle"></i> Demo Ready!</h6>
                    <p class="mb-2">This is a live demo of the Manual Mapping Tools with real data:</p>
                    <ul class="mb-2">
                        <li>56 unmapped employees ready for testing</li>
                        <li>Advanced customer search with filters</li>
                        <li>Complete validation and mapping workflow</li>
                    </ul>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            `;
            
            $('body').append(alertHtml);
        }
    </script>
</body>
</html>