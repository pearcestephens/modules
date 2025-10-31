/**
 * AI Insights JavaScript Module
 *
 * Handles all frontend interactions for the AI insights dashboard including:
 * - Loading and rendering recommendations
 * - Real-time chart updates
 * - Bulk actions (accept/dismiss)
 * - Auto-refresh functionality
 * - Filters and search
 * - Modal interactions
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

const POAIInsights = {
    config: {
        apiBase: '/modules/consignments/api/purchase-orders',
        refreshInterval: 30000, // 30 seconds
        cacheTimeout: 300000, // 5 minutes
    },

    state: {
        insights: [],
        filters: {
            type: '',
            confidence: '',
            status: 'active',
            search: ''
        },
        selectedInsights: new Set(),
        selectedInsight: null,
        // modal instrumentation
        modalOpenedAt: null,
        modalActionTaken: false,
        autoRefresh: false,
        autoRefreshTimer: null,
        dataTable: null,
        chart: null
    },

    cache: {
        insights: new Map(),
        summary: null
    },

    /**
     * Initialize the AI insights module
     */
    init() {
        console.log('Initializing AI Insights module...');

        this.bindEvents();
        this.loadInsights();
        this.initDataTable();

        console.log('AI Insights module initialized');
    },

    /**
     * Bind all event listeners
     */
    bindEvents() {
        // Refresh button
        $('#refresh-insights-btn').on('click', () => {
            this.loadInsights(true);
        });

        // Auto-refresh toggle
        $('#auto-refresh-toggle').on('click', (e) => {
            this.toggleAutoRefresh();
        });

        // Filter changes
        $('#filter-type, #filter-confidence, #filter-status').on('change', () => {
            this.applyFilters();
        });

        $('#filter-search').on('keyup', this.debounce(() => {
            this.applyFilters();
        }, 300));

        // Select all checkbox
        $('#select-all').on('change', (e) => {
            this.selectAll(e.target.checked);
        });

        // Bulk actions
        $('#bulk-accept-btn').on('click', () => {
            this.bulkAccept();
        });

        $('#bulk-dismiss-btn').on('click', () => {
            this.bulkDismiss();
        });

        $('#clear-selection-btn').on('click', () => {
            this.clearSelection();
        });

        // Modal actions
        $('#modal-accept-btn').on('click', () => {
            this.acceptRecommendation(this.state.selectedInsight);
        });

        $('#modal-dismiss-btn').on('click', () => {
            this.dismissRecommendation(this.state.selectedInsight);
        });

        // Close modal on action (also log close)
        $('#recommendation-modal').on('hidden.bs.modal', () => {
            try {
                var sid = this.state.selectedInsight;
                var timeSpent = null;
                if (this.state.modalOpenedAt) {
                    timeSpent = (Date.now() - this.state.modalOpenedAt) / 1000.0;
                }
                var actionTaken = !!this.state.modalActionTaken;

                if (typeof InteractionLogger !== 'undefined' && InteractionLogger && InteractionLogger.track) {
                    InteractionLogger.track({
                        type: 'modal_closed',
                        modal_name: 'recommendation_modal',
                        page: 'ai_insights',
                        insight_id: sid || null,
                        time_spent_seconds: timeSpent,
                        action_taken: actionTaken,
                        timestamp: Date.now()
                    });
                }
            } catch (e) {
                console.warn('InteractionLogger error', e);
            }

            // reset modal state
            this.state.selectedInsight = null;
            this.state.modalOpenedAt = null;
            this.state.modalActionTaken = false;
        });
    },

    /**
     * Load insights from API
     */
    async loadInsights(forceRefresh = false) {
        this.showLoading();

        // Check cache if not forcing refresh
        if (!forceRefresh && this.cache.insights.has('all')) {
            const cached = this.cache.insights.get('all');
            const age = Date.now() - cached.timestamp;

            if (age < this.config.cacheTimeout) {
                this.state.insights = cached.data;
                this.renderInsights();
                this.hideLoading();
                return;
            }
        }

        try {
            const response = await fetch(`${this.config.apiBase}/get-ai-insights.php`);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();

            if (result.success) {
                this.state.insights = result.data.insights || [];

                // Update summary cards
                if (result.data.summary) {
                    this.updateSummaryCards(result.data.summary);
                }

                // Cache results
                this.cache.insights.set('all', {
                    data: this.state.insights,
                    timestamp: Date.now()
                });

                this.renderInsights();

                this.showAlert('Insights refreshed successfully', 'success');
            } else {
                throw new Error(result.error || 'Failed to load insights');
            }
        } catch (error) {
            console.error('Error loading insights:', error);
            this.showAlert('Failed to load insights: ' + error.message, 'danger');
        } finally {
            this.hideLoading();
        }
    },

    /**
     * Render insights in DataTable
     */
    renderInsights() {
        if (!this.state.dataTable) {
            this.initDataTable();
            return;
        }

        // Clear existing data
        this.state.dataTable.clear();

        // Apply filters
        let filteredInsights = this.filterInsights(this.state.insights);

        // Add rows
        filteredInsights.forEach(insight => {
            this.state.dataTable.row.add(this.createTableRow(insight));
        });

        this.state.dataTable.draw();

        // Rebind row events
        this.bindRowEvents();
    },

    /**
     * Initialize DataTable
     */
    initDataTable() {
        if ($.fn.DataTable.isDataTable('#recommendations-table')) {
            this.state.dataTable = $('#recommendations-table').DataTable();
            return;
        }

        this.state.dataTable = $('#recommendations-table').DataTable({
            order: [[7, 'desc']], // Sort by created date descending
            pageLength: 25,
            responsive: true,
            language: {
                emptyTable: 'No recommendations found',
                zeroRecords: 'No matching recommendations found'
            },
            columnDefs: [
                { orderable: false, targets: [0, 8] } // Checkbox and actions columns
            ]
        });
    },

    /**
     * Create table row HTML for insight
     */
    createTableRow(insight) {
        const poNumber = insight.po_number || 'N/A';
        const typeBadge = this.getTypeBadge(insight.insight_type);
        const confidenceBadge = this.getConfidenceBadge(insight.confidence_score);
        const statusBadge = this.getStatusBadge(insight.status);
        const savings = this.calculateSavings(insight);
        const createdDate = this.formatDate(insight.created_at);

        return [
            `<input type="checkbox" class="form-check-input insight-checkbox" data-insight-id="${insight.id}">`,
            `<a href="view.php?id=${insight.consignment_id}">#${poNumber}</a>`,
            typeBadge,
            this.getRecommendationSummary(insight),
            confidenceBadge,
            savings > 0 ? `<span class="text-success">$${savings.toFixed(2)}</span>` : '<span class="text-muted">-</span>',
            statusBadge,
            createdDate,
            `<div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary view-details-btn" data-insight-id="${insight.id}">
                    <i class="fas fa-eye"></i>
                </button>
                ${insight.status === 'active' ? `
                    <button class="btn btn-outline-success accept-btn" data-insight-id="${insight.id}">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-outline-danger dismiss-btn" data-insight-id="${insight.id}">
                        <i class="fas fa-times"></i>
                    </button>
                ` : ''}
            </div>`
        ];
    },

    /**
     * Bind events to table row buttons
     */
    bindRowEvents() {
        // View details
        $('.view-details-btn').off('click').on('click', (e) => {
            const insightId = parseInt($(e.currentTarget).data('insight-id'));
            this.viewDetails(insightId);
        });

        // Accept recommendation
        $('.accept-btn').off('click').on('click', (e) => {
            const insightId = parseInt($(e.currentTarget).data('insight-id'));
            this.acceptRecommendation(insightId);
        });

        // Dismiss recommendation
        $('.dismiss-btn').off('click').on('click', (e) => {
            const insightId = parseInt($(e.currentTarget).data('insight-id'));
            this.dismissRecommendation(insightId);
        });

        // Checkbox selection
        $('.insight-checkbox').off('change').on('change', (e) => {
            const insightId = parseInt($(e.target).data('insight-id'));
            if (e.target.checked) {
                this.state.selectedInsights.add(insightId);
            } else {
                this.state.selectedInsights.delete(insightId);
            }
            this.updateBulkActionsBar();
        });
    },

    /**
     * View insight details in modal
     */
    async viewDetails(insightId) {
        const insight = this.state.insights.find(i => i.id === insightId);

        if (!insight) {
            this.showAlert('Insight not found', 'danger');
            return;
        }

        this.state.selectedInsight = insightId;

        // Parse data if it's a string
        const data = typeof insight.data === 'string'
            ? JSON.parse(insight.data)
            : insight.data;

        // Build modal content
        let html = `
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label">PO Number</div>
                    <div class="detail-item-value">#${insight.po_number || 'N/A'}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Type</div>
                    <div class="detail-item-value">${this.getTypeName(insight.insight_type)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Status</div>
                    <div class="detail-item-value">${this.getStatusBadge(insight.status)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Created</div>
                    <div class="detail-item-value">${this.formatDate(insight.created_at)}</div>
                </div>
            </div>
        `;

        // Add confidence breakdown
        html += `
            <div class="confidence-breakdown">
                <h6>Confidence Score</h6>
                <div class="confidence-bar">
                    <div class="confidence-bar-fill" style="width: ${insight.confidence_score * 100}%">
                        ${(insight.confidence_score * 100).toFixed(0)}%
                    </div>
                </div>
            </div>
        `;

        // Add reasoning if available
        if (data.reasoning) {
            html += `
                <div class="reasoning-box">
                    <h6><i class="fas fa-lightbulb me-2"></i>AI Reasoning</h6>
                    <p class="mb-0">${this.escapeHtml(data.reasoning)}</p>
                </div>
            `;
        }

        // Add carrier recommendation details
        if (insight.insight_type === 'carrier_recommendation') {
            html += `
                <div class="detail-grid mt-3">
                    <div class="detail-item">
                        <div class="detail-item-label">Recommended Carrier</div>
                        <div class="detail-item-value">${this.escapeHtml(data.carrier || 'N/A')}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-item-label">Service Type</div>
                        <div class="detail-item-value">${this.escapeHtml(data.service || 'N/A')}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-item-label">Estimated Cost</div>
                        <div class="detail-item-value">$${(data.estimated_cost || 0).toFixed(2)}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-item-label">Estimated Delivery</div>
                        <div class="detail-item-value">${data.estimated_days || 'N/A'} days</div>
                    </div>
                </div>
            `;

            // Add alternatives
            if (data.alternatives && data.alternatives.length > 0) {
                html += `
                    <div class="alternatives-list">
                        <h6>Alternative Options</h6>
                `;

                data.alternatives.forEach((alt, index) => {
                    html += `
                        <div class="alternative-item">
                            <div>
                                <strong>${this.escapeHtml(alt.carrier)}</strong> - ${this.escapeHtml(alt.service || 'Standard')}
                            </div>
                            <div>
                                $${(alt.estimated_cost || 0).toFixed(2)} | ${alt.estimated_days || 'N/A'} days
                            </div>
                        </div>
                    `;
                });

                html += `</div>`;
            }
        }

        // Add box optimization details
        if (insight.insight_type === 'box_optimization') {
            if (data.containers && data.containers.length > 0) {
                html += `
                    <div class="mt-3">
                        <h6>Container Recommendations</h6>
                `;

                data.containers.forEach((container, index) => {
                    html += `
                        <div class="alternative-item">
                            <div>
                                <strong>${this.escapeHtml(container.type)}</strong>
                                <small class="text-muted ms-2">
                                    ${container.length}×${container.width}×${container.height} cm
                                </small>
                            </div>
                            <div>
                                ${container.utilization.toFixed(1)}% utilized
                            </div>
                        </div>
                    `;
                });

                html += `</div>`;
            }
        }

        $('#recommendation-detail').html(html);

        // Show/hide action buttons based on status
        if (insight.status === 'active') {
            $('#modal-accept-btn, #modal-dismiss-btn').show();
        } else {
            $('#modal-accept-btn, #modal-dismiss-btn').hide();
        }

        // Show modal
        const modal = new bootstrap.Modal($('#recommendation-modal')[0]);
        modal.show();

        // Log modal opened
        try {
            if (typeof InteractionLogger !== 'undefined' && InteractionLogger && InteractionLogger.track) {
                InteractionLogger.track({
                    type: 'modal_opened',
                    modal_name: 'recommendation_modal',
                    page: 'ai_insights',
                    insight_id: insightId,
                    timestamp: Date.now()
                });
            }
        } catch (e) {
            console.warn('InteractionLogger error', e);
        }
        // set modal opened timestamp for review time measurement
        this.state.modalOpenedAt = Date.now();
    },

    /**
     * Accept a recommendation
     */
    async acceptRecommendation(insightId) {
        if (!confirm('Accept this AI recommendation?')) {
            return;
        }

        this.showLoading();

        try {
            const response = await fetch(`${this.config.apiBase}/accept-ai-insight.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ insight_id: insightId })
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Recommendation accepted', 'success');

                // Update local state
                const insight = this.state.insights.find(i => i.id === insightId);
                if (insight) {
                    insight.status = 'accepted';
                }

                // Refresh display
                this.loadInsights(true);

                // Close modal if open
                $('#recommendation-modal').modal('hide');

                // Log accept action
                try {
                    if (typeof InteractionLogger !== 'undefined' && InteractionLogger && InteractionLogger.track) {
                        var reviewTime = null;
                        if (this.state.modalOpenedAt) {
                            reviewTime = (Date.now() - this.state.modalOpenedAt) / 1000.0;
                        }
                        InteractionLogger.track({
                            type: 'ai_recommendation_accepted',
                            insight_id: insightId,
                            page: 'ai_insights',
                            review_time_seconds: reviewTime,
                            timestamp: Date.now()
                        });
                        // mark that user took an action while modal was open
                        this.state.modalActionTaken = true;
                    }
                } catch (e) {
                    console.warn('InteractionLogger error', e);
                }
            } else {
                throw new Error(result.error || 'Failed to accept recommendation');
            }
        } catch (error) {
            console.error('Error accepting recommendation:', error);
            this.showAlert('Failed to accept recommendation: ' + error.message, 'danger');
        } finally {
            this.hideLoading();
        }
    },

    /**
     * Dismiss a recommendation
     */
    async dismissRecommendation(insightId) {
        if (!confirm('Dismiss this AI recommendation?')) {
            return;
        }

        this.showLoading();

        try {
            const response = await fetch(`${this.config.apiBase}/dismiss-ai-insight.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ insight_id: insightId })
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Recommendation dismissed', 'info');

                // Update local state
                const insight = this.state.insights.find(i => i.id === insightId);
                if (insight) {
                    insight.status = 'dismissed';
                }

                // Refresh display
                this.loadInsights(true);

                // Close modal if open
                $('#recommendation-modal').modal('hide');

                // Log dismiss action
                try {
                    if (typeof InteractionLogger !== 'undefined' && InteractionLogger && InteractionLogger.track) {
                        var reviewTime2 = null;
                        if (this.state.modalOpenedAt) {
                            reviewTime2 = (Date.now() - this.state.modalOpenedAt) / 1000.0;
                        }
                        InteractionLogger.track({
                            type: 'ai_recommendation_dismissed',
                            insight_id: insightId,
                            page: 'ai_insights',
                            review_time_seconds: reviewTime2,
                            timestamp: Date.now()
                        });
                        this.state.modalActionTaken = true;
                    }
                } catch (e) {
                    console.warn('InteractionLogger error', e);
                }
            } else {
                throw new Error(result.error || 'Failed to dismiss recommendation');
            }
        } catch (error) {
            console.error('Error dismissing recommendation:', error);
            this.showAlert('Failed to dismiss recommendation: ' + error.message, 'danger');
        } finally {
            this.hideLoading();
        }
    },

    /**
     * Bulk accept selected recommendations
     */
    async bulkAccept() {
        const insightIds = Array.from(this.state.selectedInsights);

        if (insightIds.length === 0) {
            this.showAlert('No recommendations selected', 'warning');
            return;
        }

        if (!confirm(`Accept ${insightIds.length} recommendations?`)) {
            return;
        }

        this.showLoading();

        try {
            const response = await fetch(`${this.config.apiBase}/bulk-accept-ai-insights.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ insight_ids: insightIds })
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert(`${result.data.accepted} recommendations accepted`, 'success');
                this.clearSelection();
                this.loadInsights(true);

                // Log bulk accept
                try {
                    if (typeof InteractionLogger !== 'undefined' && InteractionLogger && InteractionLogger.track) {
                        InteractionLogger.track({
                            type: 'ai_bulk_accept',
                            count: result.data.accepted || insightIds.length,
                            insight_ids: insightIds,
                            page: 'ai_insights',
                            timestamp: Date.now()
                        });
                        // bulk operations are user actions
                        this.state.modalActionTaken = true;
                    }
                } catch (e) {
                    console.warn('InteractionLogger error', e);
                }
            } else {
                throw new Error(result.error || 'Failed to accept recommendations');
            }
        } catch (error) {
            console.error('Error bulk accepting:', error);
            this.showAlert('Failed to accept recommendations: ' + error.message, 'danger');
        } finally {
            this.hideLoading();
        }
    },

    /**
     * Bulk dismiss selected recommendations
     */
    async bulkDismiss() {
        const insightIds = Array.from(this.state.selectedInsights);

        if (insightIds.length === 0) {
            this.showAlert('No recommendations selected', 'warning');
            return;
        }

        if (!confirm(`Dismiss ${insightIds.length} recommendations?`)) {
            return;
        }

        this.showLoading();

        try {
            const response = await fetch(`${this.config.apiBase}/bulk-dismiss-ai-insights.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ insight_ids: insightIds })
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert(`${result.data.dismissed} recommendations dismissed`, 'info');
                this.clearSelection();
                this.loadInsights(true);

                // Log bulk dismiss
                try {
                    if (typeof InteractionLogger !== 'undefined' && InteractionLogger && InteractionLogger.track) {
                        InteractionLogger.track({
                            type: 'ai_bulk_dismiss',
                            count: result.data.dismissed || insightIds.length,
                            insight_ids: insightIds,
                            page: 'ai_insights',
                            timestamp: Date.now()
                        });
                        this.state.modalActionTaken = true;
                    }
                } catch (e) {
                    console.warn('InteractionLogger error', e);
                }
            } else {
                throw new Error(result.error || 'Failed to dismiss recommendations');
            }
        } catch (error) {
            console.error('Error bulk dismissing:', error);
            this.showAlert('Failed to dismiss recommendations: ' + error.message, 'danger');
        } finally {
            this.hideLoading();
        }
    },

    /**
     * Apply filters to insights list
     */
    applyFilters() {
        this.state.filters.type = $('#filter-type').val();
        this.state.filters.confidence = $('#filter-confidence').val();
        this.state.filters.status = $('#filter-status').val();
        this.state.filters.search = $('#filter-search').val().toLowerCase();

        this.renderInsights();
    },

    /**
     * Filter insights based on current filters
     */
    filterInsights(insights) {
        return insights.filter(insight => {
            // Type filter
            if (this.state.filters.type && insight.insight_type !== this.state.filters.type) {
                return false;
            }

            // Confidence filter
            if (this.state.filters.confidence) {
                const confidence = insight.confidence_score;
                if (this.state.filters.confidence === 'high' && confidence <= 0.8) {
                    return false;
                }
                if (this.state.filters.confidence === 'medium' && (confidence < 0.6 || confidence > 0.8)) {
                    return false;
                }
                if (this.state.filters.confidence === 'low' && confidence >= 0.6) {
                    return false;
                }
            }

            // Status filter
            if (this.state.filters.status && insight.status !== this.state.filters.status) {
                return false;
            }

            // Search filter
            if (this.state.filters.search) {
                const searchStr = this.state.filters.search;
                const poNumber = (insight.po_number || '').toLowerCase();

                if (!poNumber.includes(searchStr)) {
                    return false;
                }
            }

            return true;
        });
    },

    /**
     * Select all visible insights
     */
    selectAll(checked) {
        $('.insight-checkbox').prop('checked', checked);

        if (checked) {
            $('.insight-checkbox').each((i, el) => {
                const insightId = parseInt($(el).data('insight-id'));
                this.state.selectedInsights.add(insightId);
            });
        } else {
            this.state.selectedInsights.clear();
        }

        this.updateBulkActionsBar();
    },

    /**
     * Clear all selections
     */
    clearSelection() {
        this.state.selectedInsights.clear();
        $('.insight-checkbox').prop('checked', false);
        $('#select-all').prop('checked', false);
        this.updateBulkActionsBar();
    },

    /**
     * Update bulk actions bar visibility and counter
     */
    updateBulkActionsBar() {
        const count = this.state.selectedInsights.size;

        $('#selected-count').text(count);

        if (count > 0) {
            $('#bulk-actions-bar').addClass('active');
        } else {
            $('#bulk-actions-bar').removeClass('active');
        }
    },

    /**
     * Toggle auto-refresh
     */
    toggleAutoRefresh() {
        this.state.autoRefresh = !this.state.autoRefresh;

        const btn = $('#auto-refresh-toggle');
        const indicator = $('#auto-refresh-indicator');

        if (this.state.autoRefresh) {
            btn.html('<i class="fas fa-clock me-2"></i>Auto-Refresh: ON')
               .removeClass('btn-primary').addClass('btn-success');
            indicator.addClass('active');
            this.startAutoRefresh();
        } else {
            btn.html('<i class="fas fa-clock me-2"></i>Auto-Refresh: OFF')
               .removeClass('btn-success').addClass('btn-primary');
            indicator.removeClass('active');
            this.stopAutoRefresh();
        }
    },

    /**
     * Start auto-refresh timer
     */
    startAutoRefresh() {
        this.state.autoRefreshTimer = setInterval(() => {
            console.log('Auto-refreshing insights...');
            this.loadInsights(true);
        }, this.config.refreshInterval);
    },

    /**
     * Stop auto-refresh timer
     */
    stopAutoRefresh() {
        if (this.state.autoRefreshTimer) {
            clearInterval(this.state.autoRefreshTimer);
            this.state.autoRefreshTimer = null;
        }
    },

    /**
     * Update summary cards
     */
    updateSummaryCards(summary) {
        $('#total-savings').text('$' + (summary.total_savings || 0).toFixed(2));
        $('#optimization-rate').text((summary.optimization_rate || 0).toFixed(1) + '%');
        $('#avg-confidence').text(((summary.avg_confidence || 0) * 100).toFixed(0) + '%');
        $('#active-recommendations').text(summary.active_count || 0);
    },

    // ==================== UTILITY METHODS ====================

    getTypeBadge(type) {
        const badges = {
            'carrier_recommendation': '<span class="insight-type-badge type-carrier">Carrier</span>',
            'box_optimization': '<span class="insight-type-badge type-box">Box Optimization</span>',
            'cost_prediction': '<span class="insight-type-badge type-cost">Cost Prediction</span>'
        };
        return badges[type] || '<span class="insight-type-badge">Unknown</span>';
    },

    getTypeName(type) {
        const names = {
            'carrier_recommendation': 'Carrier Recommendation',
            'box_optimization': 'Box Optimization',
            'cost_prediction': 'Cost Prediction'
        };
        return names[type] || 'Unknown';
    },

    getConfidenceBadge(score) {
        if (score > 0.8) {
            return `<span class="confidence-badge confidence-high">${(score * 100).toFixed(0)}%</span>`;
        } else if (score >= 0.6) {
            return `<span class="confidence-badge confidence-medium">${(score * 100).toFixed(0)}%</span>`;
        } else {
            return `<span class="confidence-badge confidence-low">${(score * 100).toFixed(0)}%</span>`;
        }
    },

    getStatusBadge(status) {
        const badges = {
            'active': '<span class="badge bg-primary">Active</span>',
            'accepted': '<span class="badge bg-success">Accepted</span>',
            'dismissed': '<span class="badge bg-secondary">Dismissed</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    },

    getRecommendationSummary(insight) {
        const data = typeof insight.data === 'string' ? JSON.parse(insight.data) : insight.data;

        if (insight.insight_type === 'carrier_recommendation') {
            return `${this.escapeHtml(data.carrier || 'N/A')} - ${this.escapeHtml(data.service || 'Standard')}`;
        } else if (insight.insight_type === 'box_optimization') {
            const containerCount = data.containers ? data.containers.length : 0;
            return `${containerCount} container${containerCount !== 1 ? 's' : ''} recommended`;
        } else if (insight.insight_type === 'cost_prediction') {
            return `Predicted: $${(data.predicted_cost || 0).toFixed(2)}`;
        }

        return 'N/A';
    },

    calculateSavings(insight) {
        const data = typeof insight.data === 'string' ? JSON.parse(insight.data) : insight.data;

        if (insight.insight_type === 'carrier_recommendation' && data.alternatives && data.alternatives.length > 0) {
            const recommendedCost = data.estimated_cost || 0;
            const baselineCost = data.alternatives[0].estimated_cost || 0;
            return Math.max(0, baselineCost - recommendedCost);
        }

        return 0;
    },

    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleString('en-NZ', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    showLoading() {
        $('#loading-overlay').addClass('active');
    },

    hideLoading() {
        $('#loading-overlay').removeClass('active');
    },

    showAlert(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3"
                 style="z-index: 10000; min-width: 300px;" role="alert">
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        $('body').append(alertHtml);

        setTimeout(() => {
            $('.alert').fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    },

    /**
     * Cleanup on page unload
     */
    cleanup() {
        this.stopAutoRefresh();
        this.cache.insights.clear();
    }
};

// Initialize on DOM ready
$(document).ready(function() {
    POAIInsights.init();
});

// Cleanup on page unload
$(window).on('beforeunload', function() {
    POAIInsights.cleanup();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = POAIInsights;
}
