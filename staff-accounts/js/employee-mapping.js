/**
 * Employee Mapping JavaScript - Main Controller
 * 
 * Handles all frontend interactions for employee mapping system
 * Integrates with backend API endpoints for real-time data
 * 
 * Features:
 * - Dashboard data loading and updates
 * - Unmapped employees table with search/sort
 * - Auto-match suggestions with approval workflow
 * - Manual mapping interface
 * - Real-time notifications and progress tracking
 * 
 * @package CIS\StaffAccounts\JS
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Global variables
    let csrfToken = '';
    let currentTab = 'dashboard';
    let unmappedEmployees = [];
    let autoMatches = [];
    let selectedEmployees = [];
    let refreshInterval = null;

    // Configuration
    const CONFIG = {
        apiBaseUrl: '../',
        refreshIntervalMs: 30000, // 30 seconds
        notificationDuration: 5000, // 5 seconds
        tablePageSize: 25,
        searchDebounceMs: 300
    };

    // API endpoints
    const API = {
        csrf: CONFIG.apiBaseUrl + '?endpoint=get-csrf-token',
        mappings: CONFIG.apiBaseUrl + '?endpoint=employee-mappings',
        unmapped: CONFIG.apiBaseUrl + '?endpoint=employee-mappings-unmapped',
        autoMatch: CONFIG.apiBaseUrl + '?endpoint=employee-mappings-auto-match',
        createMapping: CONFIG.apiBaseUrl + '?endpoint=employee-mappings',
        updateMapping: CONFIG.apiBaseUrl + '?endpoint=employee-mappings',
        deleteMapping: CONFIG.apiBaseUrl + '?endpoint=employee-mappings'
    };

    // Initialize application
    $(document).ready(function() {
        console.log('Employee Mapping System - Initializing...');
        
        // Initialize CSRF token
        initializeCSRF().then(function() {
            console.log('CSRF token loaded successfully');
            
            // Load initial data
            loadDashboardData();
            
            // Set up event listeners
            setupEventListeners();
            
            // Start auto-refresh
            startAutoRefresh();
            
            console.log('Employee Mapping System - Ready!');
        }).catch(function(error) {
            console.error('Failed to initialize CSRF token:', error);
            showNotification('System initialization failed. Please refresh the page.', 'error');
        });
    });

    // ========================================
    // INITIALIZATION FUNCTIONS
    // ========================================

    /**
     * Initialize CSRF token for secure API calls
     */
    function initializeCSRF() {
        return new Promise(function(resolve, reject) {
            $.get(API.csrf)
                .done(function(response) {
                    if (response.success && response.data.csrf_token) {
                        csrfToken = response.data.csrf_token;
                        resolve(csrfToken);
                    } else {
                        reject('Invalid CSRF response');
                    }
                })
                .fail(function() {
                    reject('CSRF request failed');
                });
        });
    }

    /**
     * Set up all event listeners
     */
    function setupEventListeners() {
        // Tab navigation
        $('#mappingTabs a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            currentTab = $(e.target).attr('href').substring(1);
            console.log('Switched to tab:', currentTab);
            loadTabContent(currentTab);
        });

        // Dashboard quick actions
        $('#processAutoMatches').on('click', function() {
            switchToTab('automatch');
        });

        $('#viewUnmapped').on('click', function() {
            switchToTab('unmapped');
        });

        $('#createManualMapping').on('click', function() {
            switchToTab('manual');
        });

        // Global actions
        $('#refreshData').on('click', function() {
            refreshAllData();
        });

        $('#bulkOperations').on('click', function() {
            showBulkOperationsModal();
        });

        // Unmapped employees table
        $('#searchUnmapped').on('input', debounce(function() {
            filterUnmappedTable();
        }, CONFIG.searchDebounceMs));

        $('#sortUnmapped').on('change', function() {
            sortUnmappedTable();
        });

        $('#selectAllUnmapped').on('change', function() {
            toggleSelectAllUnmapped();
        });

        // Auto-match actions
        $('#approveAllMatches').on('click', function() {
            approveAllAutoMatches();
        });

        $('#refreshMatches').on('click', function() {
            loadAutoMatches();
        });

        // Refresh buttons
        $('#refreshUnmapped').on('click', function() {
            loadUnmappedEmployees();
        });

        // Export functionality
        $('#exportUnmapped').on('click', function() {
            exportUnmappedData();
        });
    }

    /**
     * Start automatic data refresh
     */
    function startAutoRefresh() {
        refreshInterval = setInterval(function() {
            if (currentTab === 'dashboard') {
                loadDashboardData();
            }
        }, CONFIG.refreshIntervalMs);
    }

    // ========================================
    // DATA LOADING FUNCTIONS
    // ========================================

    /**
     * Load dashboard summary data
     */
    function loadDashboardData() {
        console.log('Loading dashboard data...');
        
        // Update status
        updateStatus('Loading system data...');

        // Load basic mappings data
        $.get(API.mappings)
            .done(function(response) {
                if (response.success) {
                    updateDashboardCards(response.data);
                    updateStatus('System operational', 'success');
                } else {
                    console.error('Dashboard API error:', response.error);
                    updateStatus('Dashboard load failed', 'error');
                }
            })
            .fail(function(xhr) {
                console.error('Dashboard request failed:', xhr);
                updateStatus('Connection error', 'error');
                $('#apiStatus').html('<i class="fas fa-times"></i> Offline').removeClass('badge-success').addClass('badge-danger');
            });

        // Load unmapped count
        $.get(API.unmapped)
            .done(function(response) {
                if (response.success) {
                    const unmappedCount = response.data.employees.length;
                    const blockedAmount = response.data.total_blocked_amount;
                    
                    $('#unmappedCount').text(unmappedCount);
                    $('#unmappedBadge').text(unmappedCount);
                    $('#unmappedAvailable').text(unmappedCount);
                    $('#blockedAmount').text('$' + formatNumber(blockedAmount));
                }
            });

        // Load auto-match count
        $.get(API.autoMatch)
            .done(function(response) {
                if (response.success) {
                    const matchCount = response.data.suggestions.length;
                    $('#autoMatchCount').text(matchCount);
                    $('#automatchBadge').text(matchCount);
                    $('#autoMatchesAvailable').text(matchCount);
                }
            });
    }

    /**
     * Update dashboard summary cards
     */
    function updateDashboardCards(data) {
        const stats = data.stats || {};
        
        // Update mapped count
        $('#mappedCount').text(stats.total_mapped || 0);
        
        // Update last update time
        $('#updateTime').text(new Date().toLocaleTimeString());
        
        // Update system health indicators
        $('#apiStatus').html('<i class="fas fa-check"></i> Online').removeClass('badge-danger').addClass('badge-success');
        $('#dbStatus').html('<i class="fas fa-check"></i> Connected').removeClass('badge-danger').addClass('badge-success');
    }

    /**
     * Load content for specific tab
     */
    function loadTabContent(tabName) {
        switch(tabName) {
            case 'unmapped':
                loadUnmappedEmployees();
                break;
            case 'automatch':
                loadAutoMatches();
                break;
            case 'manual':
                loadManualMappingInterface();
                break;
            case 'analytics':
                loadAnalytics();
                break;
            case 'admin':
                loadAdminInterface();
                break;
        }
    }

    /**
     * Load unmapped employees data
     */
    function loadUnmappedEmployees() {
        console.log('Loading unmapped employees...');
        
        // Show loading state
        $('#unmappedTableBody').html(
            '<tr><td colspan="7" class="text-center py-4">' +
            '<i class="fas fa-spinner fa-spin fa-2x text-muted"></i>' +
            '<br><span class="text-muted">Loading unmapped employees...</span>' +
            '</td></tr>'
        );

        $.get(API.unmapped)
            .done(function(response) {
                if (response.success) {
                    unmappedEmployees = response.data.employees;
                    renderUnmappedTable(unmappedEmployees);
                    console.log('Loaded', unmappedEmployees.length, 'unmapped employees');
                } else {
                    console.error('Unmapped employees API error:', response.error);
                    showNotification('Failed to load unmapped employees: ' + response.error.message, 'error');
                }
            })
            .fail(function(xhr) {
                console.error('Unmapped employees request failed:', xhr);
                showNotification('Failed to load unmapped employees. Please try again.', 'error');
                $('#unmappedTableBody').html(
                    '<tr><td colspan="7" class="text-center py-4 text-danger">' +
                    '<i class="fas fa-exclamation-triangle fa-2x"></i>' +
                    '<br>Failed to load data. Please refresh and try again.' +
                    '</td></tr>'
                );
            });
    }

    /**
     * Render unmapped employees table
     */
    function renderUnmappedTable(employees) {
        if (!employees || employees.length === 0) {
            $('#unmappedTableBody').html(
                '<tr><td colspan="7" class="text-center py-4 text-success">' +
                '<i class="fas fa-check-circle fa-2x"></i>' +
                '<br><strong>All employees are mapped!</strong>' +
                '<br>No unmapped employees found.' +
                '</td></tr>'
            );
            return;
        }

        let html = '';
        employees.forEach(function(employee, index) {
            const priority = getPriorityClass(employee.total_amount);
            const amountClass = getAmountClass(employee.total_amount);
            
            html += `
                <tr data-employee-id="${employee.employee_id}" data-index="${index}">
                    <td>
                        <input type="checkbox" class="employee-checkbox" value="${employee.employee_id}">
                    </td>
                    <td data-label="Employee Name">
                        <strong>${escapeHtml(employee.employee_name)}</strong>
                    </td>
                    <td data-label="Email">
                        ${employee.employee_email ? escapeHtml(employee.employee_email) : '<span class="text-muted">N/A</span>'}
                    </td>
                    <td data-label="Blocked Amount" class="${amountClass}">
                        $${formatNumber(employee.total_amount)}
                    </td>
                    <td data-label="Deduction Count">
                        <span class="badge badge-secondary">${employee.deduction_count}</span>
                    </td>
                    <td data-label="Priority">
                        <span class="badge ${priority.class}">${priority.text}</span>
                    </td>
                    <td data-label="Actions">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary action-btn" 
                                    onclick="viewEmployeeDetails('${employee.employee_id}')" 
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-success action-btn" 
                                    onclick="quickMapEmployee('${employee.employee_id}')" 
                                    title="Quick Map">
                                <i class="fas fa-link"></i>
                            </button>
                            <button type="button" class="btn btn-outline-warning action-btn" 
                                    onclick="findAutoMatch('${employee.employee_id}')" 
                                    title="Find Matches">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#unmappedTableBody').html(html);
        
        // Set up checkbox event listeners
        $('.employee-checkbox').on('change', function() {
            updateSelectedEmployees();
        });
    }

    /**
     * Load auto-match suggestions
     */
    function loadAutoMatches() {
        console.log('Loading auto-match suggestions...');
        
        // Show loading state
        $('#autoMatchContainer').html(
            '<div class="text-center py-4">' +
            '<i class="fas fa-spinner fa-spin fa-2x text-muted"></i>' +
            '<br><span class="text-muted">Loading auto-match suggestions...</span>' +
            '</div>'
        );

        $.get(API.autoMatch)
            .done(function(response) {
                if (response.success) {
                    autoMatches = response.data.suggestions;
                    renderAutoMatches(autoMatches);
                    console.log('Loaded', autoMatches.length, 'auto-match suggestions');
                } else {
                    console.error('Auto-match API error:', response.error);
                    showNotification('Failed to load auto-match suggestions: ' + response.error.message, 'error');
                }
            })
            .fail(function(xhr) {
                console.error('Auto-match request failed:', xhr);
                showNotification('Failed to load auto-match suggestions. Please try again.', 'error');
            });
    }

    /**
     * Render auto-match suggestions
     */
    function renderAutoMatches(matches) {
        if (!matches || matches.length === 0) {
            $('#autoMatchContainer').html(
                '<div class="text-center py-4 text-info">' +
                '<i class="fas fa-info-circle fa-2x"></i>' +
                '<br><strong>No auto-matches available</strong>' +
                '<br>All employees have been processed or no suitable matches found.' +
                '</div>'
            );
            return;
        }

        let html = '<div class="row">';
        
        matches.forEach(function(match, index) {
            const confidenceClass = getConfidenceClass(match.confidence_score);
            const matchType = match.confidence_score >= 95 ? 'exact' : 'fuzzy';
            
            html += `
                <div class="col-md-6 mb-3">
                    <div class="auto-match-card match-${matchType}" data-match-index="${index}">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="badge ${confidenceClass.class} badge-lg">
                                ${match.confidence_score}% Confidence
                            </span>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-success" 
                                        onclick="approveAutoMatch(${index})" 
                                        title="Approve Match">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="rejectAutoMatch(${index})" 
                                        title="Reject Match">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="match-comparison">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="match-employee">
                                            <h6 class="mb-2">
                                                <i class="fas fa-user text-warning"></i> Employee
                                            </h6>
                                            <div><strong>${escapeHtml(match.employee_name)}</strong></div>
                                            <div class="text-muted small">${match.employee_email ? escapeHtml(match.employee_email) : 'No email'}</div>
                                            <div class="mt-2">
                                                <span class="badge badge-warning">$${formatNumber(match.total_amount)} blocked</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="match-customer">
                                            <h6 class="mb-2">
                                                <i class="fas fa-user-check text-info"></i> Customer Match
                                            </h6>
                                            <div><strong>${escapeHtml(match.customer_name)}</strong></div>
                                            <div class="text-muted small">ID: ${match.customer_id}</div>
                                            <div class="mt-2">
                                                <span class="badge badge-info">${match.match_reason}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        $('#autoMatchContainer').html(html);
    }

    // ========================================
    // UI INTERACTION FUNCTIONS
    // ========================================

    /**
     * Switch to a specific tab
     */
    function switchToTab(tabName) {
        $(`#${tabName}-tab`).tab('show');
    }

    /**
     * Update status message
     */
    function updateStatus(message, type = 'info') {
        $('#statusMessage').text(message);
        $('#statusAlert')
            .removeClass('alert-info alert-success alert-warning alert-danger')
            .addClass(`alert-${type}`);
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info', duration = CONFIG.notificationDuration) {
        const notificationHtml = `
            <div class="notification alert alert-${type} alert-dismissible fade" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `;
        
        // Create notification container if it doesn't exist
        if ($('.notification-container').length === 0) {
            $('body').append('<div class="notification-container"></div>');
        }
        
        const $notification = $(notificationHtml);
        $('.notification-container').append($notification);
        
        // Animate in
        setTimeout(() => $notification.addClass('show'), 10);
        
        // Auto-dismiss
        if (duration > 0) {
            setTimeout(() => {
                $notification.removeClass('show');
                setTimeout(() => $notification.remove(), 300);
            }, duration);
        }
    }

    /**
     * Show loading modal
     */
    function showLoadingModal(text = 'Processing...') {
        $('#loadingText').text(text);
        $('#loadingModal').modal('show');
    }

    /**
     * Hide loading modal
     */
    function hideLoadingModal() {
        $('#loadingModal').modal('hide');
    }

    // ========================================
    // UTILITY FUNCTIONS
    // ========================================

    /**
     * Format number with commas
     */
    function formatNumber(num) {
        return parseFloat(num).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /**
     * Get priority class based on amount
     */
    function getPriorityClass(amount) {
        const amt = parseFloat(amount);
        if (amt >= 500) {
            return { class: 'badge-danger priority-high', text: 'High' };
        } else if (amt >= 100) {
            return { class: 'badge-warning priority-medium', text: 'Medium' };
        } else {
            return { class: 'badge-success priority-low', text: 'Low' };
        }
    }

    /**
     * Get amount class for styling
     */
    function getAmountClass(amount) {
        const amt = parseFloat(amount);
        if (amt >= 500) {
            return 'amount-large';
        } else if (amt >= 100) {
            return 'amount-medium';
        } else {
            return 'amount-small';
        }
    }

    /**
     * Get confidence class for badges
     */
    function getConfidenceClass(confidence) {
        if (confidence >= 95) {
            return { class: 'confidence-exact', text: 'Exact Match' };
        } else if (confidence >= 85) {
            return { class: 'confidence-high', text: 'High Confidence' };
        } else if (confidence >= 75) {
            return { class: 'confidence-medium', text: 'Medium Confidence' };
        } else {
            return { class: 'confidence-low', text: 'Low Confidence' };
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Debounce function for search input
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = function() {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // ========================================
    // GLOBAL FUNCTIONS (called from HTML)
    // ========================================

    /**
     * View employee details
     */
    window.viewEmployeeDetails = function(employeeId) {
        console.log('Viewing details for employee:', employeeId);
        // Implementation coming in next phase
        showNotification('Employee details view coming soon!', 'info');
    };

    /**
     * Quick map employee
     */
    window.quickMapEmployee = function(employeeId) {
        console.log('Quick mapping employee:', employeeId);
        // Implementation coming in next phase
        showNotification('Quick mapping feature coming soon!', 'info');
    };

    /**
     * Find auto-match for employee
     */
    window.findAutoMatch = function(employeeId) {
        console.log('Finding auto-match for employee:', employeeId);
        // Implementation coming in next phase
        showNotification('Auto-match search coming soon!', 'info');
    };

    /**
     * Approve auto-match
     */
    window.approveAutoMatch = function(matchIndex) {
        console.log('Approving auto-match:', matchIndex);
        // Implementation coming in next phase
        showNotification('Auto-match approval coming soon!', 'info');
    };

    /**
     * Reject auto-match
     */
    window.rejectAutoMatch = function(matchIndex) {
        console.log('Rejecting auto-match:', matchIndex);
        // Implementation coming in next phase
        showNotification('Auto-match rejection coming soon!', 'info');
    };

    // ========================================
    // PLACEHOLDER FUNCTIONS
    // ========================================

    function filterUnmappedTable() {
        // Implementation for table filtering
        console.log('Filtering unmapped table...');
    }

    function sortUnmappedTable() {
        // Implementation for table sorting
        console.log('Sorting unmapped table...');
    }

    function toggleSelectAllUnmapped() {
        // Implementation for select all toggle
        console.log('Toggling select all...');
    }

    function updateSelectedEmployees() {
        // Implementation for tracking selected employees
        console.log('Updating selected employees...');
    }

    function approveAllAutoMatches() {
        // Implementation for bulk approval
        console.log('Approving all auto-matches...');
    }

    function refreshAllData() {
        // Implementation for full data refresh
        console.log('Refreshing all data...');
        loadDashboardData();
        if (currentTab !== 'dashboard') {
            loadTabContent(currentTab);
        }
        showNotification('Data refreshed successfully!', 'success');
    }

    function showBulkOperationsModal() {
        // Implementation for bulk operations modal
        console.log('Showing bulk operations modal...');
        showNotification('Bulk operations coming soon!', 'info');
    }

    function exportUnmappedData() {
        // Implementation for data export
        console.log('Exporting unmapped data...');
        showNotification('Data export coming soon!', 'info');
    }

    function loadManualMappingInterface() {
        // Implementation for manual mapping interface
        console.log('Loading manual mapping interface...');
        $('#manualMappingContainer').html(
            '<div class="text-center py-4 text-info">' +
            '<i class="fas fa-tools fa-2x"></i>' +
            '<br><strong>Manual Mapping Interface</strong>' +
            '<br>Coming in Phase 2 Stage 3!' +
            '</div>'
        );
    }

    function loadAnalytics() {
        // Implementation for analytics dashboard
        console.log('Loading analytics...');
        $('#analyticsContainer').html(
            '<div class="text-center py-4 text-info">' +
            '<i class="fas fa-chart-line fa-2x"></i>' +
            '<br><strong>Analytics Dashboard</strong>' +
            '<br>Coming in Phase 2 Stage 4!' +
            '</div>'
        );
    }

    function loadAdminInterface() {
        // Implementation for admin interface
        console.log('Loading admin interface...');
        $('#adminContainer').html(
            '<div class="text-center py-4 text-info">' +
            '<i class="fas fa-tools fa-2x"></i>' +
            '<br><strong>Admin Interface</strong>' +
            '<br>Coming in Phase 2 Stage 5!' +
            '</div>'
        );
    }

})(jQuery);