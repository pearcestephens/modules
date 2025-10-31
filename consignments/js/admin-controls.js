/**
 * Employee Mapping System - Admin Controls JavaScript
 * 
 * Handles all administrative functionality including:
 * - System configuration management
 * - Bulk operations processing
 * - Data management operations
 * - User permissions management
 * - Audit trail viewing
 * - System diagnostics
 * 
 * @package CIS\Consignments\JS
 * @version 2.0.0
 * @since 2025-01-01
 */

class AdminControls {
    constructor() {
        this.apiEndpoint = 'api/employee-mapping.php';
        this.currentAction = null;
        this.progressInterval = null;
        this.autoRefreshInterval = null;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadInitialData();
        this.startAutoRefresh();
    }

    bindEvents() {
        // System Configuration Events
        $('#generalSettingsForm').on('submit', (e) => this.saveGeneralSettings(e));
        $('#alertSettingsForm').on('submit', (e) => this.saveAlertSettings(e));

        // Bulk Operations Events
        $('#bulkAutoMatch').on('click', () => this.confirmAction('Process All Auto-Matches', () => this.processBulkAutoMatch()));
        $('#bulkApproveHighConfidence').on('click', () => this.confirmAction('Approve High Confidence Matches', () => this.approveHighConfidence()));
        $('#bulkResetMappings').on('click', () => this.confirmAction('Reset All Pending Mappings', () => this.resetMappings()));
        $('#bulkFlagForReview').on('click', () => this.flagForReview());
        $('#refreshAllData').on('click', () => this.refreshAllData());
        $('#recalculateAmounts').on('click', () => this.recalculateAmounts());

        // Import/Export Events
        $('#importFile').on('change', (e) => this.handleFileSelect(e));
        $('#importMappings').on('click', () => this.importMappings());
        $('#exportUnmapped').on('click', () => this.exportData('unmapped'));
        $('#exportMapped').on('click', () => this.exportData('mapped'));
        $('#exportAutoMatches').on('click', () => this.exportData('auto-matches'));
        $('#exportFullReport').on('click', () => this.exportData('full-report'));
        $('#downloadTemplate').on('click', () => this.downloadTemplate());

        // Data Management Events
        $('#optimizeDatabase').on('click', () => this.confirmAction('Optimize Database', () => this.optimizeDatabase()));
        $('#rebuildIndexes').on('click', () => this.confirmAction('Rebuild Database Indexes', () => this.rebuildIndexes()));
        $('#cleanupOldData').on('click', () => this.confirmAction('Cleanup Old Data', () => this.cleanupOldData()));
        $('#createBackup').on('click', () => this.createBackup());
        $('#viewBackups').on('click', () => this.viewBackups());
        $('#validateDataIntegrity').on('click', () => this.validateDataIntegrity());
        $('#repairCorruptedData').on('click', () => this.confirmAction('Repair Corrupted Data', () => this.repairCorruptedData()));

        // Cache Management Events
        $('#clearAllCache').on('click', () => this.confirmAction('Clear All Cache', () => this.clearCache('all')));
        $('#clearMappingCache').on('click', () => this.clearCache('mapping'));
        $('#preloadCache').on('click', () => this.preloadCache());
        $('#viewActiveSessions').on('click', () => this.viewActiveSessions());
        $('#terminateOldSessions').on('click', () => this.confirmAction('Terminate Old Sessions', () => this.terminateOldSessions()));

        // System Health Events
        $('#refreshHealthMetrics').on('click', () => this.refreshHealthMetrics());
        $('#systemHealthCheck').on('click', () => this.runSystemHealthCheck());
        $('#exportSystemData').on('click', () => this.exportSystemData());

        // User Management Events
        $('#addNewUser').on('click', () => $('#addUserModal').modal('show'));
        $('#saveNewUser').on('click', () => this.saveNewUser());
        $('#manageRoles').on('click', () => this.manageRoles());

        // Audit Trail Events
        $('#auditFilter').on('change', (e) => this.filterAuditLog(e.target.value));
        $('#exportAuditLog').on('click', () => this.exportAuditLog());

        // Diagnostic Events
        $('#testDatabaseConnection').on('click', () => this.testConnection('database'));
        $('#testVendAPI').on('click', () => this.testConnection('vend'));
        $('#testEmailService').on('click', () => this.testConnection('email'));
        $('#testQueryPerformance').on('click', () => this.testPerformance('query'));
        $('#testMemoryUsage').on('click', () => this.testPerformance('memory'));
        $('#testLoadCapacity').on('click', () => this.testPerformance('load'));
        $('#validateSystemIntegrity').on('click', () => this.validateSystem('integrity'));
        $('#validateDataConsistency').on('click', () => this.validateSystem('consistency'));
        $('#runFullDiagnostic').on('click', () => this.runFullDiagnostic());

        // Log Viewer Events
        $('#logLevel').on('change', (e) => this.filterLogs(e.target.value));
        $('#refreshLogs').on('click', () => this.refreshLogs());
        $('#downloadLogs').on('click', () => this.downloadLogs());

        // Modal Events
        $('#confirmAction').on('click', () => this.executeConfirmedAction());

        // Auto-refresh toggle
        $(document).on('visibilitychange', () => {
            if (document.hidden) {
                this.stopAutoRefresh();
            } else {
                this.startAutoRefresh();
            }
        });
    }

    loadInitialData() {
        this.refreshHealthMetrics();
        this.loadAuditLog();
        this.loadSystemLogs();
        this.updateSystemStats();
    }

    startAutoRefresh() {
        this.autoRefreshInterval = setInterval(() => {
            this.refreshHealthMetrics();
            this.updateSystemStats();
        }, 60000); // Refresh every minute
    }

    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    // System Configuration Methods
    saveGeneralSettings(e) {
        e.preventDefault();
        
        const settings = {
            autoMatchThreshold: $('#autoMatchThreshold').val(),
            mappingTimeout: $('#mappingTimeout').val(),
            maxBatchSize: $('#maxBatchSize').val(),
            enableAutoApproval: $('#enableAutoApproval').is(':checked'),
            enableEmailNotifications: $('#enableEmailNotifications').is(':checked'),
            enableDetailedLogging: $('#enableDetailedLogging').is(':checked')
        };

        this.showProgress('Saving settings...');

        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: {
                action: 'save_settings',
                settings: JSON.stringify(settings)
            },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert('Settings saved successfully!', 'success');
                    this.logAuditEvent('Config Change', 'General settings updated');
                } else {
                    this.showAlert('Failed to save settings: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error saving settings', 'danger');
            }
        });
    }

    saveAlertSettings(e) {
        e.preventDefault();
        
        const settings = {
            alertEmail: $('#alertEmail').val(),
            alertLargeAmounts: $('#alertLargeAmounts').is(':checked'),
            alertFailedMappings: $('#alertFailedMappings').is(':checked'),
            alertSystemErrors: $('#alertSystemErrors').is(':checked'),
            alertDailySummary: $('#alertDailySummary').is(':checked'),
            alertFrequency: $('#alertFrequency').val()
        };

        this.showProgress('Saving alert settings...');

        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: {
                action: 'save_alert_settings',
                settings: JSON.stringify(settings)
            },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert('Alert settings saved successfully!', 'success');
                    this.logAuditEvent('Config Change', 'Alert settings updated');
                } else {
                    this.showAlert('Failed to save alert settings: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error saving alert settings', 'danger');
            }
        });
    }

    // Bulk Operations Methods
    processBulkAutoMatch() {
        this.showProgress('Processing auto-matches...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'bulk_auto_match' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Successfully processed ${response.processed} auto-matches!`, 'success');
                    this.logAuditEvent('Bulk Operation', `Processed ${response.processed} auto-matches`);
                    this.refreshDashboardData();
                } else {
                    this.showAlert('Failed to process auto-matches: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error processing auto-matches', 'danger');
            }
        });
    }

    approveHighConfidence() {
        this.showProgress('Approving high confidence matches...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'approve_high_confidence' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Approved ${response.approved} high confidence matches!`, 'success');
                    this.logAuditEvent('Bulk Operation', `Approved ${response.approved} high confidence matches`);
                    this.refreshDashboardData();
                } else {
                    this.showAlert('Failed to approve matches: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error approving matches', 'danger');
            }
        });
    }

    resetMappings() {
        this.showProgress('Resetting mappings...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'reset_mappings' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Reset ${response.reset} pending mappings!`, 'warning');
                    this.logAuditEvent('Bulk Operation', `Reset ${response.reset} pending mappings`);
                    this.refreshDashboardData();
                } else {
                    this.showAlert('Failed to reset mappings: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error resetting mappings', 'danger');
            }
        });
    }

    flagForReview() {
        this.showProgress('Flagging for review...');
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'flag_for_review' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Flagged ${response.flagged} items for manual review!`, 'info');
                    this.logAuditEvent('Bulk Operation', `Flagged ${response.flagged} items for review`);
                } else {
                    this.showAlert('Failed to flag items: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error flagging items', 'danger');
            }
        });
    }

    refreshAllData() {
        this.showProgress('Refreshing all data...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'refresh_all_data' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert('All data refreshed successfully!', 'success');
                    this.logAuditEvent('Data Operation', 'All employee data refreshed');
                    this.refreshDashboardData();
                } else {
                    this.showAlert('Failed to refresh data: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error refreshing data', 'danger');
            }
        });
    }

    recalculateAmounts() {
        this.showProgress('Recalculating amounts...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'recalculate_amounts' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Recalculated amounts. New total: $${response.total}`, 'success');
                    this.logAuditEvent('Data Operation', `Recalculated blocked amounts: $${response.total}`);
                    this.refreshDashboardData();
                } else {
                    this.showAlert('Failed to recalculate amounts: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error recalculating amounts', 'danger');
            }
        });
    }

    // Import/Export Methods
    handleFileSelect(e) {
        const file = e.target.files[0];
        if (file) {
            const fileName = file.name;
            $(e.target).next('.custom-file-label').text(fileName);
            
            // Validate file type
            const allowedTypes = ['.csv', '.xlsx', '.xls'];
            const fileExtension = fileName.toLowerCase().substring(fileName.lastIndexOf('.'));
            
            if (!allowedTypes.includes(fileExtension)) {
                this.showAlert('Invalid file type. Please select a CSV or Excel file.', 'warning');
                $(e.target).val('');
                $(e.target).next('.custom-file-label').text('Choose file...');
            }
        }
    }

    importMappings() {
        const fileInput = $('#importFile')[0];
        if (!fileInput.files[0]) {
            this.showAlert('Please select a file to import', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('action', 'import_mappings');

        this.showProgress('Importing mappings...');

        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Successfully imported ${response.imported} mappings!`, 'success');
                    this.logAuditEvent('Import Operation', `Imported ${response.imported} mappings from file`);
                    this.refreshDashboardData();
                    
                    // Reset file input
                    $('#importFile').val('');
                    $('.custom-file-label').text('Choose file...');
                } else {
                    this.showAlert('Import failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error importing file', 'danger');
            }
        });
    }

    exportData(type) {
        this.showProgress(`Exporting ${type} data...`);
        
        const url = `${this.apiEndpoint}?action=export_data&type=${type}&timestamp=${Date.now()}`;
        
        // Create temporary download link
        const link = document.createElement('a');
        link.href = url;
        link.download = `employee-mapping-${type}-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.hideProgress();
        this.showAlert(`${type} data export started`, 'info');
        this.logAuditEvent('Export Operation', `Exported ${type} data`);
    }

    downloadTemplate() {
        const url = `${this.apiEndpoint}?action=download_template&timestamp=${Date.now()}`;
        
        const link = document.createElement('a');
        link.href = url;
        link.download = 'employee-mapping-template.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showAlert('Template download started', 'info');
    }

    // Data Management Methods
    optimizeDatabase() {
        this.showProgress('Optimizing database...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'optimize_database' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert('Database optimized successfully!', 'success');
                    this.logAuditEvent('Database Operation', 'Database optimization completed');
                } else {
                    this.showAlert('Database optimization failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error optimizing database', 'danger');
            }
        });
    }

    rebuildIndexes() {
        this.showProgress('Rebuilding indexes...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'rebuild_indexes' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert('Database indexes rebuilt successfully!', 'success');
                    this.logAuditEvent('Database Operation', 'Database indexes rebuilt');
                } else {
                    this.showAlert('Index rebuild failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error rebuilding indexes', 'danger');
            }
        });
    }

    cleanupOldData() {
        this.showProgress('Cleaning up old data...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'cleanup_old_data' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Cleaned up ${response.cleaned} old records!`, 'success');
                    this.logAuditEvent('Database Operation', `Cleaned up ${response.cleaned} old records`);
                } else {
                    this.showAlert('Cleanup failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error cleaning up data', 'danger');
            }
        });
    }

    createBackup() {
        this.showProgress('Creating backup...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'create_backup' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Backup created: ${response.filename}`, 'success');
                    this.logAuditEvent('Backup Operation', `Manual backup created: ${response.filename}`);
                } else {
                    this.showAlert('Backup failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error creating backup', 'danger');
            }
        });
    }

    viewBackups() {
        $.ajax({
            url: this.apiEndpoint,
            method: 'GET',
            data: { action: 'list_backups' },
            success: (response) => {
                if (response.success) {
                    this.showBackupList(response.backups);
                } else {
                    this.showAlert('Failed to load backup list: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.showAlert('Error loading backup list', 'danger');
            }
        });
    }

    validateDataIntegrity() {
        this.showProgress('Validating data integrity...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'validate_integrity' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    const issues = response.issues || 0;
                    if (issues === 0) {
                        this.showAlert('Data integrity validation passed!', 'success');
                    } else {
                        this.showAlert(`Found ${issues} integrity issues. Check logs for details.`, 'warning');
                    }
                    this.logAuditEvent('Validation', `Data integrity check: ${issues} issues found`);
                } else {
                    this.showAlert('Validation failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error validating data integrity', 'danger');
            }
        });
    }

    repairCorruptedData() {
        this.showProgress('Repairing corrupted data...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'repair_data' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Repaired ${response.repaired} corrupted records!`, 'success');
                    this.logAuditEvent('Data Operation', `Repaired ${response.repaired} corrupted records`);
                } else {
                    this.showAlert('Repair failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error repairing data', 'danger');
            }
        });
    }

    // Cache Management Methods
    clearCache(type) {
        this.showProgress(`Clearing ${type} cache...`);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'clear_cache', cache_type: type },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`${type} cache cleared successfully!`, 'success');
                    this.logAuditEvent('Cache Operation', `Cleared ${type} cache`);
                    this.refreshHealthMetrics();
                } else {
                    this.showAlert('Cache clear failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error clearing cache', 'danger');
            }
        });
    }

    preloadCache() {
        this.showProgress('Preloading cache...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'preload_cache' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert('Cache preloaded successfully!', 'success');
                    this.logAuditEvent('Cache Operation', 'Cache preloaded with frequently used data');
                    this.refreshHealthMetrics();
                } else {
                    this.showAlert('Cache preload failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error preloading cache', 'danger');
            }
        });
    }

    viewActiveSessions() {
        $.ajax({
            url: this.apiEndpoint,
            method: 'GET',
            data: { action: 'list_sessions' },
            success: (response) => {
                if (response.success) {
                    this.showSessionList(response.sessions);
                } else {
                    this.showAlert('Failed to load session list: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.showAlert('Error loading session list', 'danger');
            }
        });
    }

    terminateOldSessions() {
        this.showProgress('Terminating old sessions...');
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'terminate_old_sessions' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert(`Terminated ${response.terminated} old sessions!`, 'success');
                    this.logAuditEvent('Session Operation', `Terminated ${response.terminated} old sessions`);
                } else {
                    this.showAlert('Session termination failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error terminating sessions', 'danger');
            }
        });
    }

    // System Health Methods
    refreshHealthMetrics() {
        $.ajax({
            url: this.apiEndpoint,
            method: 'GET',
            data: { action: 'health_metrics' },
            success: (response) => {
                if (response.success) {
                    this.updateHealthDisplay(response.metrics);
                }
            },
            error: () => {
                console.warn('Failed to refresh health metrics');
            }
        });
    }

    runSystemHealthCheck() {
        this.showProgress('Running system health check...', true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'health_check' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showHealthCheckResults(response.results);
                } else {
                    this.showAlert('Health check failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error running health check', 'danger');
            }
        });
    }

    exportSystemData() {
        this.showProgress('Exporting system data...');
        
        const url = `${this.apiEndpoint}?action=export_system_data&timestamp=${Date.now()}`;
        
        const link = document.createElement('a');
        link.href = url;
        link.download = `system-data-${new Date().toISOString().split('T')[0]}.zip`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.hideProgress();
        this.showAlert('System data export started', 'info');
        this.logAuditEvent('Export Operation', 'System data exported');
    }

    // User Management Methods
    saveNewUser() {
        const userData = {
            name: $('#newUserName').val(),
            email: $('#newUserEmail').val(),
            role: $('#newUserRole').val(),
            password: $('#newUserPassword').val(),
            sendWelcome: $('#sendWelcomeEmail').is(':checked')
        };

        // Validate required fields
        if (!userData.name || !userData.email || !userData.role || !userData.password) {
            this.showAlert('Please fill in all required fields', 'warning');
            return;
        }

        this.showProgress('Creating user...');

        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: {
                action: 'create_user',
                user: JSON.stringify(userData)
            },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showAlert('User created successfully!', 'success');
                    this.logAuditEvent('User Management', `Created new user: ${userData.name}`);
                    $('#addUserModal').modal('hide');
                    this.refreshUserTable();
                    
                    // Reset form
                    $('#addUserForm')[0].reset();
                } else {
                    this.showAlert('Failed to create user: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error creating user', 'danger');
            }
        });
    }

    manageRoles() {
        // This would open a role management interface
        this.showAlert('Role management interface not yet implemented', 'info');
    }

    // Audit Trail Methods
    loadAuditLog() {
        $.ajax({
            url: this.apiEndpoint,
            method: 'GET',
            data: { action: 'audit_log' },
            success: (response) => {
                if (response.success) {
                    this.updateAuditTable(response.log);
                }
            },
            error: () => {
                console.warn('Failed to load audit log');
            }
        });
    }

    filterAuditLog(filter) {
        $.ajax({
            url: this.apiEndpoint,
            method: 'GET',
            data: { action: 'audit_log', filter: filter },
            success: (response) => {
                if (response.success) {
                    this.updateAuditTable(response.log);
                }
            },
            error: () => {
                this.showAlert('Error filtering audit log', 'danger');
            }
        });
    }

    exportAuditLog() {
        const filter = $('#auditFilter').val();
        const url = `${this.apiEndpoint}?action=export_audit&filter=${filter}&timestamp=${Date.now()}`;
        
        const link = document.createElement('a');
        link.href = url;
        link.download = `audit-log-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showAlert('Audit log export started', 'info');
        this.logAuditEvent('Export Operation', 'Audit log exported');
    }

    // Diagnostic Methods
    testConnection(type) {
        this.showProgress(`Testing ${type} connection...`);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'test_connection', type: type },
            success: (response) => {
                this.hideProgress();
                const result = response.success ? 'SUCCESS' : 'FAILED';
                const alertType = response.success ? 'success' : 'danger';
                
                this.showAlert(`${type.toUpperCase()} connection test: ${result}`, alertType);
                this.addDiagnosticResult(`${type.toUpperCase()} Connection`, result, response.message || '');
            },
            error: () => {
                this.hideProgress();
                this.showAlert(`Error testing ${type} connection`, 'danger');
                this.addDiagnosticResult(`${type.toUpperCase()} Connection`, 'ERROR', 'Request failed');
            }
        });
    }

    testPerformance(type) {
        this.showProgress(`Testing ${type} performance...`, true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'test_performance', type: type },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.addDiagnosticResult(`${type.toUpperCase()} Performance`, 'COMPLETED', response.message);
                } else {
                    this.addDiagnosticResult(`${type.toUpperCase()} Performance`, 'FAILED', response.error);
                }
            },
            error: () => {
                this.hideProgress();
                this.addDiagnosticResult(`${type.toUpperCase()} Performance`, 'ERROR', 'Request failed');
            }
        });
    }

    validateSystem(type) {
        this.showProgress(`Validating ${type}...`, true);
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'validate_system', type: type },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    const issues = response.issues || 0;
                    const status = issues === 0 ? 'PASSED' : 'ISSUES FOUND';
                    this.addDiagnosticResult(`${type.toUpperCase()} Validation`, status, `${issues} issues found`);
                } else {
                    this.addDiagnosticResult(`${type.toUpperCase()} Validation`, 'FAILED', response.error);
                }
            },
            error: () => {
                this.hideProgress();
                this.addDiagnosticResult(`${type.toUpperCase()} Validation`, 'ERROR', 'Request failed');
            }
        });
    }

    runFullDiagnostic() {
        this.showProgress('Running full diagnostic suite...', true);
        $('#diagnosticResults').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Running comprehensive diagnostics...</p></div>');
        
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: { action: 'full_diagnostic' },
            success: (response) => {
                this.hideProgress();
                if (response.success) {
                    this.showFullDiagnosticResults(response.results);
                    this.logAuditEvent('Diagnostic', 'Full diagnostic suite completed');
                } else {
                    this.showAlert('Full diagnostic failed: ' + response.error, 'danger');
                }
            },
            error: () => {
                this.hideProgress();
                this.showAlert('Error running full diagnostic', 'danger');
            }
        });
    }

    // Log Viewer Methods
    loadSystemLogs() {
        $.ajax({
            url: this.apiEndpoint,
            method: 'GET',
            data: { action: 'system_logs' },
            success: (response) => {
                if (response.success) {
                    this.updateLogViewer(response.logs);
                }
            },
            error: () => {
                console.warn('Failed to load system logs');
            }
        });
    }

    filterLogs(level) {
        $.ajax({
            url: this.apiEndpoint,
            method: 'GET',
            data: { action: 'system_logs', level: level },
            success: (response) => {
                if (response.success) {
                    this.updateLogViewer(response.logs);
                }
            },
            error: () => {
                this.showAlert('Error filtering logs', 'danger');
            }
        });
    }

    refreshLogs() {
        this.loadSystemLogs();
        this.showAlert('Logs refreshed', 'info');
    }

    downloadLogs() {
        const level = $('#logLevel').val();
        const url = `${this.apiEndpoint}?action=download_logs&level=${level}&timestamp=${Date.now()}`;
        
        const link = document.createElement('a');
        link.href = url;
        link.download = `system-logs-${new Date().toISOString().split('T')[0]}.txt`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showAlert('Log download started', 'info');
    }

    // UI Helper Methods
    showProgress(message, showPercentage = false) {
        const progressHtml = `
            <div class="progress-overlay">
                <div class="progress-modal">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                        <p class="progress-message">${message}</p>
                        ${showPercentage ? '<div class="progress mb-2"><div class="progress-bar" style="width: 0%"></div></div><div class="progress-percentage">0%</div>' : ''}
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(progressHtml);
        
        if (showPercentage) {
            this.simulateProgress();
        }
    }

    hideProgress() {
        $('.progress-overlay').remove();
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
    }

    simulateProgress() {
        let progress = 0;
        this.progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 95) progress = 95;
            
            $('.progress-bar').css('width', progress + '%');
            $('.progress-percentage').text(Math.round(progress) + '%');
        }, 500);
    }

    showAlert(message, type = 'info') {
        const alertClass = `alert-${type}`;
        const iconClass = {
            'success': 'fa-check-circle',
            'danger': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        }[type] || 'fa-info-circle';

        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <i class="fas ${iconClass} mr-2"></i>
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `;
        
        $('body').append(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            $('.alert').alert('close');
        }, 5000);
    }

    confirmAction(message, callback) {
        $('#confirmationMessage').text(`Are you sure you want to ${message.toLowerCase()}?`);
        this.currentAction = callback;
        $('#confirmationModal').modal('show');
    }

    executeConfirmedAction() {
        if (this.currentAction) {
            $('#confirmationModal').modal('hide');
            this.currentAction();
            this.currentAction = null;
        }
    }

    // Data Update Methods
    refreshDashboardData() {
        // Trigger refresh of dashboard data in parent interface
        if (window.employeeMapping && typeof window.employeeMapping.loadDashboard === 'function') {
            window.employeeMapping.loadDashboard();
        }
    }

    updateSystemStats() {
        // Update any real-time system statistics
        const timestamp = new Date().toLocaleTimeString();
        $('.last-updated').text(`Last updated: ${timestamp}`);
    }

    logAuditEvent(action, details) {
        $.ajax({
            url: this.apiEndpoint,
            method: 'POST',
            data: {
                action: 'log_audit',
                audit_action: action,
                details: details
            },
            success: (response) => {
                if (response.success) {
                    this.loadAuditLog(); // Refresh audit log
                }
            },
            error: () => {
                console.warn('Failed to log audit event');
            }
        });
    }

    // Display Update Methods
    updateHealthDisplay(metrics) {
        if (metrics.cpu) $('.health-metrics .progress-bar').eq(0).css('width', metrics.cpu + '%').text(metrics.cpu + '%');
        if (metrics.memory) $('.health-metrics .progress-bar').eq(1).css('width', metrics.memory + '%').text(metrics.memory + '%');
        if (metrics.disk) $('.health-metrics .progress-bar').eq(2).css('width', metrics.disk + '%').text(metrics.disk + '%');
        if (metrics.database) $('.health-metrics .progress-bar').eq(3).css('width', metrics.database + '%').text(metrics.database + '%');
    }

    updateAuditTable(log) {
        let html = '';
        log.forEach(entry => {
            html += `
                <tr>
                    <td>${entry.timestamp}</td>
                    <td>${entry.user}</td>
                    <td><span class="badge badge-${entry.badge_class}">${entry.action}</span></td>
                    <td>${entry.details}</td>
                    <td><span class="badge badge-${entry.result_class}">${entry.result}</span></td>
                    <td>${entry.ip_address}</td>
                </tr>
            `;
        });
        $('#auditTable tbody').html(html);
    }

    updateLogViewer(logs) {
        let html = '';
        logs.forEach(log => {
            html += `<div class="log-line">${log}</div>`;
        });
        $('.log-viewer').html(html);
        
        // Scroll to bottom
        $('.log-viewer').scrollTop($('.log-viewer')[0].scrollHeight);
    }

    addDiagnosticResult(test, status, message) {
        const statusClass = {
            'SUCCESS': 'text-success',
            'PASSED': 'text-success',
            'COMPLETED': 'text-success',
            'FAILED': 'text-danger',
            'ERROR': 'text-danger',
            'ISSUES FOUND': 'text-warning'
        }[status] || 'text-info';

        const icon = {
            'SUCCESS': 'fa-check-circle',
            'PASSED': 'fa-check-circle',
            'COMPLETED': 'fa-check-circle',
            'FAILED': 'fa-times-circle',
            'ERROR': 'fa-exclamation-circle',
            'ISSUES FOUND': 'fa-exclamation-triangle'
        }[status] || 'fa-info-circle';

        const resultHtml = `
            <div class="diagnostic-result mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <strong>${test}</strong>
                    <span class="${statusClass}">
                        <i class="fas ${icon} mr-1"></i>
                        ${status}
                    </span>
                </div>
                ${message ? `<small class="text-muted">${message}</small>` : ''}
            </div>
        `;

        if ($('#diagnosticResults').find('.text-center').length) {
            $('#diagnosticResults').html('');
        }
        
        $('#diagnosticResults').append(resultHtml);
    }

    showFullDiagnosticResults(results) {
        let html = '<div class="diagnostic-summary mb-3">';
        html += `<h6>Diagnostic Summary</h6>`;
        html += `<div class="row">`;
        html += `<div class="col-md-6"><strong>Tests Run:</strong> ${results.total}</div>`;
        html += `<div class="col-md-6"><strong>Passed:</strong> <span class="text-success">${results.passed}</span></div>`;
        html += `<div class="col-md-6"><strong>Failed:</strong> <span class="text-danger">${results.failed}</span></div>`;
        html += `<div class="col-md-6"><strong>Warnings:</strong> <span class="text-warning">${results.warnings}</span></div>`;
        html += `</div></div>`;

        results.details.forEach(result => {
            this.addDiagnosticResult(result.test, result.status, result.message);
        });

        $('#diagnosticResults').prepend(html);
    }

    showHealthCheckResults(results) {
        let message = `System Health Check Results:\n`;
        message += `Database: ${results.database ? 'OK' : 'FAILED'}\n`;
        message += `API: ${results.api ? 'OK' : 'FAILED'}\n`;
        message += `Cache: ${results.cache ? 'OK' : 'FAILED'}\n`;
        message += `Storage: ${results.storage ? 'OK' : 'FAILED'}`;

        const alertType = results.overall ? 'success' : 'warning';
        this.showAlert(message.replace(/\n/g, '<br>'), alertType);
    }

    showBackupList(backups) {
        // This would show a modal with backup list
        let html = '<div class="backup-list">';
        backups.forEach(backup => {
            html += `<div class="backup-item">${backup.filename} - ${backup.size} - ${backup.date}</div>`;
        });
        html += '</div>';
        
        this.showAlert('Backup list loaded', 'info');
    }

    showSessionList(sessions) {
        // This would show a modal with active sessions
        this.showAlert(`${sessions.length} active sessions found`, 'info');
    }

    refreshUserTable() {
        // Refresh the user management table
        location.reload(); // Simple refresh for now
    }
}

// Initialize admin controls when document is ready
$(document).ready(function() {
    window.adminControls = new AdminControls();
});

// Custom CSS for progress overlay
const progressCSS = `
<style>
.progress-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
}

.progress-modal {
    background: white;
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    min-width: 300px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.progress-message {
    font-size: 16px;
    margin-bottom: 0;
    color: #495057;
}

.diagnostic-result {
    border: 1px solid #e9ecef;
    padding: 10px;
    border-radius: 5px;
    background: #f8f9fa;
}

.diagnostic-summary {
    background: #e7f3ff;
    border: 1px solid #b8daff;
    padding: 15px;
    border-radius: 5px;
}
</style>
`;

$('head').append(progressCSS);