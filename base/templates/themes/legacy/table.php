<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $pageTitle ?? 'CIS - Central Information System' ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    
    <!-- CIS Core CSS -->
    <link rel="stylesheet" href="/assets/css/cis-core.css">
    
    <!-- DataTables CSS (for enhanced tables) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    
    <!-- Additional Page CSS -->
    <?php if (!empty($pageCSS)): ?>
        <?php foreach ($pageCSS as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <style>
        body.layout-table {
            background-color: var(--cis-gray-100);
            margin: 0;
        }
        
        .table-header {
            background-color: var(--cis-white);
            border-bottom: 1px solid var(--cis-border-color);
            padding: 1.5rem;
            box-shadow: var(--cis-shadow-sm);
            position: sticky;
            top: 0;
            z-index: var(--cis-z-sticky);
        }
        
        .table-title {
            font-size: var(--cis-font-size-xl);
            font-weight: var(--cis-font-weight-bold);
            margin: 0 0 0.5rem 0;
        }
        
        .table-subtitle {
            color: var(--cis-gray-600);
            margin: 0;
        }
        
        .table-actions {
            display: flex;
            gap: var(--cis-space-2);
            margin-top: var(--cis-space-3);
            flex-wrap: wrap;
        }
        
        .table-container {
            padding: 1.5rem;
        }
        
        .table-wrapper {
            background-color: var(--cis-white);
            border-radius: var(--cis-border-radius);
            box-shadow: var(--cis-shadow);
            overflow: hidden;
        }
        
        .table-filters {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--cis-border-color);
            background-color: var(--cis-gray-100);
        }
        
        .table-content {
            overflow-x: auto;
        }
        
        .table-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--cis-border-color);
            background-color: var(--cis-gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .table-header,
            .table-container {
                padding: 1rem;
            }
            
            .table-actions {
                flex-direction: column;
            }
            
            .table-actions .btn {
                width: 100%;
            }
            
            .table-footer {
                flex-direction: column;
                gap: var(--cis-space-3);
            }
        }
    </style>
    
    <!-- Inline Styles -->
    <?php if (!empty($inlineStyles)): ?>
        <style><?= $inlineStyles ?></style>
    <?php endif; ?>
</head>
<body class="layout-table">
    
    <!-- Header Section -->
    <div class="table-header">
        <h1 class="table-title"><?= $tableTitle ?? 'Data Table' ?></h1>
        <?php if (!empty($tableSubtitle)): ?>
            <p class="table-subtitle"><?= $tableSubtitle ?></p>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="table-actions">
            <?= $headerActions ?? '' ?>
        </div>
    </div>
    
    <!-- Main Table Container -->
    <div class="table-container">
        <div class="table-wrapper">
            
            <!-- Filters Section (optional) -->
            <?php if (!empty($tableFilters)): ?>
                <div class="table-filters">
                    <?= $tableFilters ?>
                </div>
            <?php endif; ?>
            
            <!-- Table Content -->
            <div class="table-content">
                <?= $content ?>
            </div>
            
            <!-- Footer Section (optional) -->
            <?php if (!empty($tableFooterContent)): ?>
                <div class="table-footer">
                    <?= $tableFooterContent ?>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    
    <!-- Auto-initialize DataTables -->
    <script>
        $(document).ready(function() {
            // Auto-initialize tables with .datatable class
            if ($.fn.DataTable) {
                $('.datatable').DataTable({
                    responsive: true,
                    pageLength: <?= $pageLength ?? 25 ?>,
                    order: <?= json_encode($defaultOrder ?? [[0, 'asc']]) ?>,
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        infoFiltered: "(filtered from _MAX_ total entries)",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                });
            }
        });
    </script>
    
    <!-- Additional Page JS -->
    <?php if (!empty($pageJS)): ?>
        <?php foreach ($pageJS as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Inline Scripts -->
    <?php if (!empty($inlineScripts)): ?>
        <script><?= $inlineScripts ?></script>
    <?php endif; ?>
    
</body>
</html>
