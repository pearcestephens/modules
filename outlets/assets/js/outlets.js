/**
 * Outlets Module - JavaScript
 * Handles outlet management, map display, and AJAX operations
 */

$(document).ready(function() {
    let outlets = [];
    let currentView = 'grid';
    let map = null;
    let markers = [];

    // Load outlets on page load
    loadOutlets();

    /**
     * Load all outlets from API
     */
    function loadOutlets() {
        $.ajax({
            url: '../api/get-outlets.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    outlets = response.data;
                    renderOutlets();
                    updateSummaryCards(response.summary);
                } else {
                    showAlert('Error loading outlets: ' + response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Failed to connect to server', 'danger');
            }
        });
    }

    /**
     * Render outlets based on current view
     */
    function renderOutlets() {
        const filteredOutlets = filterOutlets();

        if (currentView === 'grid') {
            renderGridView(filteredOutlets);
        } else if (currentView === 'list') {
            renderListView(filteredOutlets);
        } else if (currentView === 'map') {
            renderMapView(filteredOutlets);
        }
    }

    /**
     * Filter outlets based on search and filters
     */
    function filterOutlets() {
        let filtered = [...outlets];

        // Status filter
        const status = $('#filterStatus').val();
        if (status) {
            filtered = filtered.filter(o => o.status === status);
        }

        // City filter
        const city = $('#filterCity').val();
        if (city) {
            filtered = filtered.filter(o => o.city === city);
        }

        // Search
        const search = $('#searchOutlet').val().toLowerCase();
        if (search) {
            filtered = filtered.filter(o =>
                o.outlet_name.toLowerCase().includes(search) ||
                o.outlet_code.toLowerCase().includes(search) ||
                o.city.toLowerCase().includes(search)
            );
        }

        // Sort
        const sortBy = $('#sortBy').val();
        filtered.sort((a, b) => {
            if (sortBy === 'name') return a.outlet_name.localeCompare(b.outlet_name);
            if (sortBy === 'revenue') return (b.revenue_last_30_days || 0) - (a.revenue_last_30_days || 0);
            if (sortBy === 'opened') return new Date(a.opened_date) - new Date(b.opened_date);
            if (sortBy === 'city') return a.city.localeCompare(b.city);
            return 0;
        });

        return filtered;
    }

    /**
     * Render grid view
     */
    function renderGridView(outletList) {
        const $grid = $('#outletsGrid');
        $grid.empty();

        outletList.forEach(outlet => {
            const statusBadge = getStatusBadge(outlet.status);
            const revenue = formatCurrency(outlet.revenue_last_30_days || 0);
            const imageUrl = outlet.primary_photo || '../assets/img/outlet-placeholder.jpg';

            const card = `
                <div class="col-md-4 col-lg-3">
                    <div class="card outlet-card h-100">
                        <img src="${imageUrl}" class="card-img-top outlet-image" alt="${outlet.outlet_name}">
                        <div class="card-body">
                            <h5 class="card-title">${outlet.outlet_name}</h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt"></i> ${outlet.city}<br>
                                    <i class="bi bi-code-square"></i> ${outlet.outlet_code}
                                </small>
                            </p>
                            <div class="mb-2">
                                ${statusBadge}
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">30d Revenue:</small>
                                <strong class="text-success">${revenue}</strong>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="btn-group btn-group-sm w-100">
                                <button class="btn btn-outline-primary" onclick="viewOutlet(${outlet.id})">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button class="btn btn-outline-secondary" onclick="editOutlet(${outlet.id})">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $grid.append(card);
        });
    }

    /**
     * Render list view
     */
    function renderListView(outletList) {
        const $tbody = $('#outletsTableBody');
        $tbody.empty();

        outletList.forEach(outlet => {
            const statusBadge = getStatusBadge(outlet.status);
            const revenue = formatCurrency(outlet.revenue_last_30_days || 0);
            const leaseExpiry = outlet.lease_end_date || 'N/A';

            const row = `
                <tr>
                    <td>
                        <strong>${outlet.outlet_name}</strong><br>
                        <small class="text-muted">${outlet.outlet_code}</small>
                    </td>
                    <td>${outlet.city}</td>
                    <td>${statusBadge}</td>
                    <td>${outlet.manager_name || 'Unassigned'}</td>
                    <td>${leaseExpiry}</td>
                    <td class="text-success"><strong>${revenue}</strong></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewOutlet(${outlet.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="editOutlet(${outlet.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    /**
     * Render map view
     */
    function renderMapView(outletList) {
        // Initialize map if not already done
        if (!map) {
            map = new google.maps.Map(document.getElementById('map'), {
                center: { lat: -40.9006, lng: 174.8860 }, // Center of NZ
                zoom: 6
            });
        }

        // Clear existing markers
        markers.forEach(marker => marker.setMap(null));
        markers = [];

        // Add markers for each outlet
        outletList.forEach(outlet => {
            if (outlet.latitude && outlet.longitude) {
                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(outlet.latitude), lng: parseFloat(outlet.longitude) },
                    map: map,
                    title: outlet.outlet_name,
                    icon: getMarkerIcon(outlet.status)
                });

                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="padding: 10px;">
                            <h6><strong>${outlet.outlet_name}</strong></h6>
                            <p style="margin: 5px 0;">
                                ${outlet.street_address}<br>
                                ${outlet.city}<br>
                                ${getStatusBadge(outlet.status)}
                            </p>
                            <p style="margin: 5px 0;">
                                <strong>30d Revenue:</strong> ${formatCurrency(outlet.revenue_last_30_days || 0)}
                            </p>
                            <button class="btn btn-sm btn-primary" onclick="viewOutlet(${outlet.id})">
                                View Details
                            </button>
                        </div>
                    `
                });

                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });

                markers.push(marker);
            }
        });
    }

    /**
     * Get status badge HTML
     */
    function getStatusBadge(status) {
        const badges = {
            'active': '<span class="badge bg-success">Active</span>',
            'inactive': '<span class="badge bg-secondary">Inactive</span>',
            'closed_temporary': '<span class="badge bg-warning">Temp Closed</span>',
            'closed_permanent': '<span class="badge bg-danger">Closed</span>',
            'coming_soon': '<span class="badge bg-info">Coming Soon</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    }

    /**
     * Get marker icon based on status
     */
    function getMarkerIcon(status) {
        const colors = {
            'active': 'green',
            'inactive': 'gray',
            'closed_temporary': 'orange',
            'closed_permanent': 'red',
            'coming_soon': 'blue'
        };
        const color = colors[status] || 'gray';
        return `http://maps.google.com/mapfiles/ms/icons/${color}-dot.png`;
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        return '$' + parseFloat(amount).toLocaleString('en-NZ', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    /**
     * Update summary cards
     */
    function updateSummaryCards(summary) {
        if (summary) {
            $('#totalOutlets').text(summary.total || 0);
            $('#activeStores').text(summary.active || 0);
            $('#expiringLeases').text(summary.expiring_leases || 0);
            $('#avgRevenue').text(formatCurrency(summary.avg_revenue || 0));
        }
    }

    /**
     * Show alert message
     */
    function showAlert(message, type = 'info') {
        const alert = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('main').prepend(alert);

        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
    }

    // Event Listeners
    $('#viewGrid').click(function() {
        currentView = 'grid';
        $('.btn-toolbar .btn').removeClass('active');
        $(this).addClass('active');
        $('#outletsGrid').removeClass('d-none');
        $('#outletsListView').addClass('d-none');
        $('#outletsMapView').addClass('d-none');
        renderOutlets();
    });

    $('#viewList').click(function() {
        currentView = 'list';
        $('.btn-toolbar .btn').removeClass('active');
        $(this).addClass('active');
        $('#outletsGrid').addClass('d-none');
        $('#outletsListView').removeClass('d-none');
        $('#outletsMapView').addClass('d-none');
        renderOutlets();
    });

    $('#viewMap').click(function() {
        currentView = 'map';
        $('.btn-toolbar .btn').removeClass('active');
        $(this).addClass('active');
        $('#outletsGrid').addClass('d-none');
        $('#outletsListView').addClass('d-none');
        $('#outletsMapView').removeClass('d-none');
        renderOutlets();
    });

    // Filters
    $('#filterStatus, #filterCity, #searchOutlet, #sortBy').on('change keyup', function() {
        renderOutlets();
    });

    // Save new outlet
    $('#saveOutlet').click(function() {
        const formData = $('#addOutletForm').serialize();

        $.ajax({
            url: '../api/save-outlet.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Outlet added successfully!', 'success');
                    $('#addOutletModal').modal('hide');
                    $('#addOutletForm')[0].reset();
                    loadOutlets(); // Reload
                } else {
                    showAlert('Error: ' + response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Failed to save outlet', 'danger');
            }
        });
    });
});

/**
 * View outlet details (global function)
 */
function viewOutlet(outletId) {
    window.location.href = `outlet-detail.php?id=${outletId}`;
}

/**
 * Edit outlet (global function)
 */
function editOutlet(outletId) {
    window.location.href = `edit-outlet.php?id=${outletId}`;
}
