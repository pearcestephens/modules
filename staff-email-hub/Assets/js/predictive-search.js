/**
 * Predictive Search UI - "It Knows What I'm Thinking"
 *
 * This JavaScript makes search feel psychic by:
 * - Showing predictions BEFORE user types
 * - Learning behavior patterns in real-time
 * - Tracking every interaction for ML
 * - Predicting intent from first 2 characters
 * - Proactively surfacing results
 *
 * Features:
 * - "You might be looking for..." suggestions
 * - Context-aware autocomplete
 * - Smart result ranking based on behavior
 * - Background preloading of predicted searches
 * - Real-time behavior tracking
 */

class PredictiveSearch {
    constructor(options = {}) {
        this.searchInput = options.searchInput || document.querySelector('#universal-search-input');
        this.suggestionsContainer = options.suggestionsContainer || document.querySelector('#search-suggestions');
        this.proactiveContainer = options.proactiveContainer || document.querySelector('#proactive-suggestions');

        this.apiEndpoint = options.apiEndpoint || '/api/search';
        this.staffId = options.staffId;
        this.sessionId = this.generateSessionId();

        this.typingTimer = null;
        this.typingDelay = 300; // ms

        this.currentContext = {};
        this.behaviorQueue = [];
        this.lastAction = null;
        this.lastActionTime = Date.now();

        this.init();
    }

    init() {
        // Show proactive suggestions on page load
        this.showProactiveSuggestions();

        // Track page view
        this.trackEvent('page_view', {
            page_url: window.location.pathname,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight
        });

        // Set up event listeners
        this.setupEventListeners();

        // Update context every 30 seconds
        setInterval(() => this.updateContext(), 30000);

        // Flush behavior queue every 5 seconds
        setInterval(() => this.flushBehaviorQueue(), 5000);

        // Track scroll depth
        this.trackScrollDepth();

        console.log('🔮 Predictive Search initialized - search just got psychic!');
    }

    setupEventListeners() {
        // Search input - predict as they type
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.typingTimer);

            const query = e.target.value.trim();

            if (query.length === 0) {
                // No query - show proactive suggestions
                this.showProactiveSuggestions();
            } else if (query.length >= 2) {
                // Start predicting after 2 characters
                this.typingTimer = setTimeout(() => {
                    this.predictFromPartialQuery(query);
                }, this.typingDelay);
            }
        });

        // Search input focus - show recent/predicted searches
        this.searchInput.addEventListener('focus', () => {
            if (this.searchInput.value.trim().length === 0) {
                this.showProactiveSuggestions();
            }
        });

        // Track clicks globally
        document.addEventListener('click', (e) => {
            this.trackClick(e);
        });

        // Track keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                this.searchInput.focus();
                this.trackEvent('keyboard_shortcut', { key: 'cmd_k' });
            }
        });

        // Before leaving page, flush remaining events
        window.addEventListener('beforeunload', () => {
            this.flushBehaviorQueue(true);
        });
    }

    /**
     * Show proactive suggestions BEFORE user types anything
     * "You might be looking for..."
     */
    async showProactiveSuggestions() {
        try {
            const response = await fetch(`${this.apiEndpoint}/predict-intent`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    context: this.currentContext,
                    staff_id: this.staffId
                })
            });

            const predictions = await response.json();

            if (predictions.data && predictions.data.length > 0) {
                this.renderProactiveSuggestions(predictions.data);

                // Preload high-confidence predictions in background
                this.preloadPredictions(predictions.data);
            }

        } catch (error) {
            console.error('Failed to get proactive suggestions:', error);
        }
    }

    /**
     * Predict what user wants from partial query (2+ characters)
     */
    async predictFromPartialQuery(query) {
        try {
            const response = await fetch(`${this.apiEndpoint}/predict-partial`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    query: query,
                    context: this.currentContext,
                    staff_id: this.staffId
                })
            });

            const predictions = await response.json();

            if (predictions.data && predictions.data.length > 0) {
                this.renderAutocomplete(predictions.data);
            }

            // Track that user is searching
            this.trackEvent('search_typing', {
                query: query,
                query_length: query.length
            });

        } catch (error) {
            console.error('Failed to predict from partial query:', error);
        }
    }

    /**
     * Render proactive suggestions
     */
    renderProactiveSuggestions(predictions) {
        if (!this.proactiveContainer) return;

        const highConfidence = predictions.filter(p => p.confidence >= 0.8);

        if (highConfidence.length === 0) {
            this.proactiveContainer.style.display = 'none';
            return;
        }

        let html = `
            <div class="proactive-suggestions-box">
                <div class="proactive-header">
                    <span class="icon">🔮</span>
                    <span class="title">You might be looking for...</span>
                </div>
                <div class="proactive-list">
        `;

        highConfidence.slice(0, 3).forEach(pred => {
            const icon = this.getContextIcon(pred.context);
            const confidenceClass = pred.confidence >= 0.9 ? 'high' : 'medium';

            html += `
                <div class="proactive-item ${confidenceClass}"
                     data-query="${this.escapeHtml(pred.query)}"
                     data-context="${pred.context}">
                    <span class="item-icon">${icon}</span>
                    <div class="item-content">
                        <div class="item-query">${this.escapeHtml(pred.query)}</div>
                        <div class="item-reason">${this.escapeHtml(pred.reason)}</div>
                    </div>
                    <span class="confidence-badge">${Math.round(pred.confidence * 100)}%</span>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        this.proactiveContainer.innerHTML = html;
        this.proactiveContainer.style.display = 'block';

        // Add click handlers
        this.proactiveContainer.querySelectorAll('.proactive-item').forEach(item => {
            item.addEventListener('click', () => {
                const query = item.dataset.query;
                const context = item.dataset.context;
                this.executeSearch(query, context);
                this.trackEvent('proactive_suggestion_clicked', { query, context });
            });
        });
    }

    /**
     * Render autocomplete suggestions
     */
    renderAutocomplete(predictions) {
        if (!this.suggestionsContainer) return;

        let html = '<div class="autocomplete-dropdown">';

        predictions.slice(0, 8).forEach((pred, index) => {
            const icon = this.getContextIcon(pred.context);
            const shortcut = index < 9 ? `<kbd>${index + 1}</kbd>` : '';

            html += `
                <div class="autocomplete-item"
                     data-query="${this.escapeHtml(pred.query)}"
                     data-context="${pred.context}"
                     data-index="${index}">
                    <span class="item-icon">${icon}</span>
                    <div class="item-content">
                        <div class="item-query">${this.highlightMatch(pred.query, this.searchInput.value)}</div>
                        ${pred.reason ? `<div class="item-hint">${this.escapeHtml(pred.reason)}</div>` : ''}
                    </div>
                    ${shortcut}
                </div>
            `;
        });

        html += '</div>';

        this.suggestionsContainer.innerHTML = html;
        this.suggestionsContainer.style.display = 'block';

        // Add click and keyboard handlers
        this.setupAutocompleteHandlers();
    }

    /**
     * Preload predicted searches in background (for instant results)
     */
    async preloadPredictions(predictions) {
        const highConfidence = predictions.filter(p => p.confidence >= 0.9);

        for (const pred of highConfidence.slice(0, 2)) {
            try {
                // Preload in background (don't await)
                fetch(`${this.apiEndpoint}/search`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        query: pred.query,
                        context: pred.context,
                        preload: true
                    })
                });
            } catch (error) {
                // Silent fail - this is just optimization
            }
        }
    }

    /**
     * Execute a search
     */
    executeSearch(query, context = 'all') {
        this.searchInput.value = query;

        // Track search execution
        this.trackEvent('search', {
            query: query,
            context: context,
            triggered_by: 'prediction'
        });

        // Navigate to search results
        window.location.href = `/search?q=${encodeURIComponent(query)}&context=${context}`;
    }

    /**
     * Track user behavior event
     */
    trackEvent(eventType, data = {}) {
        const event = {
            event_type: eventType,
            data: {
                ...data,
                page_url: window.location.pathname,
                session_id: this.sessionId,
                timestamp: Date.now()
            }
        };

        this.behaviorQueue.push(event);

        // Update last action for workflow learning
        if (eventType !== 'page_view') {
            const timeSinceLast = Date.now() - this.lastActionTime;
            event.data.last_action = this.lastAction;
            event.data.time_since_last = timeSinceLast;

            this.lastAction = eventType;
            this.lastActionTime = Date.now();
        }

        // Update current context
        if (eventType === 'search') {
            this.currentContext.last_search = data.query;
            this.currentContext.last_search_context = data.context;
        }
    }

    /**
     * Track click with element details
     */
    trackClick(event) {
        const element = event.target;

        this.trackEvent('click', {
            element_id: element.id,
            element_class: element.className,
            element_text: element.textContent?.substring(0, 50),
            x: event.clientX,
            y: event.clientY
        });
    }

    /**
     * Track scroll depth
     */
    trackScrollDepth() {
        let maxScroll = 0;
        let scrollTimer = null;

        window.addEventListener('scroll', () => {
            const scrollPercentage = Math.round(
                (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
            );

            if (scrollPercentage > maxScroll) {
                maxScroll = scrollPercentage;
            }

            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => {
                this.trackEvent('scroll', {
                    scroll_percentage: maxScroll
                });
            }, 1000);
        });
    }

    /**
     * Update current context
     */
    updateContext() {
        this.currentContext = {
            page: window.location.pathname,
            module: this.detectCurrentModule(),
            time_of_day: this.getTimeOfDay(),
            day_of_week: new Date().getDay(),
            time_on_page: this.getTimeOnPage(),
            engagement: this.calculateEngagement()
        };
    }

    /**
     * Flush behavior queue to server
     */
    async flushBehaviorQueue(synchronous = false) {
        if (this.behaviorQueue.length === 0) return;

        const events = [...this.behaviorQueue];
        this.behaviorQueue = [];

        try {
            const request = fetch(`${this.apiEndpoint}/track-behavior`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    staff_id: this.staffId,
                    events: events
                }),
                keepalive: synchronous // Important for beforeunload
            });

            if (synchronous) {
                // Use sendBeacon for synchronous requests
                navigator.sendBeacon(
                    `${this.apiEndpoint}/track-behavior`,
                    JSON.stringify({ staff_id: this.staffId, events })
                );
            } else {
                await request;
            }

        } catch (error) {
            console.error('Failed to flush behavior queue:', error);
        }
    }

    // Helper methods

    setupAutocompleteHandlers() {
        const items = this.suggestionsContainer.querySelectorAll('.autocomplete-item');

        items.forEach((item, index) => {
            // Click handler
            item.addEventListener('click', () => {
                const query = item.dataset.query;
                const context = item.dataset.context;
                this.executeSearch(query, context);
            });

            // Keyboard shortcuts (1-9 to select)
            document.addEventListener('keydown', (e) => {
                if (e.key === String(index + 1) && this.searchInput === document.activeElement) {
                    e.preventDefault();
                    const query = item.dataset.query;
                    const context = item.dataset.context;
                    this.executeSearch(query, context);
                }
            });
        });

        // Arrow key navigation
        let selectedIndex = -1;
        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                this.highlightItem(items, selectedIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                this.highlightItem(items, selectedIndex);
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                items[selectedIndex].click();
            }
        });
    }

    highlightItem(items, index) {
        items.forEach((item, i) => {
            if (i === index) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }

    highlightMatch(text, query) {
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return text.replace(regex, '<strong>$1</strong>');
    }

    getContextIcon(context) {
        const icons = {
            email: '📧',
            product: '📦',
            order: '🛒',
            customer: '👤',
            report: '📊',
            all: '🔍'
        };
        return icons[context] || '🔍';
    }

    detectCurrentModule() {
        const path = window.location.pathname;
        if (path.includes('/email')) return 'email';
        if (path.includes('/product')) return 'product';
        if (path.includes('/order')) return 'order';
        if (path.includes('/customer')) return 'customer';
        return 'dashboard';
    }

    getTimeOfDay() {
        const hour = new Date().getHours();
        if (hour >= 6 && hour < 11) return 'morning';
        if (hour >= 11 && hour < 14) return 'midday';
        if (hour >= 14 && hour < 17) return 'afternoon';
        return 'evening';
    }

    getTimeOnPage() {
        return Math.floor((Date.now() - this.pageLoadTime) / 1000);
    }

    calculateEngagement() {
        // Simple engagement: clicks per minute
        const eventsPerMinute = (this.behaviorQueue.length / this.getTimeOnPage()) * 60;
        if (eventsPerMinute > 10) return 'high';
        if (eventsPerMinute > 5) return 'medium';
        return 'low';
    }

    generateSessionId() {
        return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
}

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    const staffId = document.body.dataset.staffId;

    if (staffId) {
        window.predictiveSearch = new PredictiveSearch({
            staffId: staffId
        });
    }
});
