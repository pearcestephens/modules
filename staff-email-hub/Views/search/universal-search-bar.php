<!-- Universal Search Bar - The Search of the Decade UI -->
<style>
/* 🎨 BEAUTIFUL SEARCH BAR STYLES */
.universal-search-container {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    padding: 15px 20px;
}

.search-bar-wrapper {
    max-width: 800px;
    margin: 0 auto;
    position: relative;
}

.search-input-group {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 50px;
    padding: 8px 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.search-input-group:focus-within {
    box-shadow: 0 15px 60px rgba(102, 126, 234, 0.3);
    transform: translateY(-2px);
}

.search-icon {
    font-size: 24px;
    margin-right: 12px;
    color: #667eea;
}

.search-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 16px;
    padding: 10px 0;
    color: #333;
}

.search-input::placeholder {
    color: #999;
}

.search-shortcut {
    background: #f5f5f5;
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 12px;
    color: #666;
    margin: 0 10px;
    font-family: monospace;
}

.ai-mode-toggle {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border: none;
    border-radius: 25px;
    padding: 8px 20px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    margin-left: 10px;
}

.ai-mode-toggle:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4);
}

.ai-mode-toggle.active {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(79, 172, 254, 0.7); }
    50% { box-shadow: 0 0 0 10px rgba(79, 172, 254, 0); }
}

.search-filters-btn {
    background: transparent;
    border: 2px solid #667eea;
    border-radius: 25px;
    padding: 8px 18px;
    color: #667eea;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-left: 10px;
}

.search-filters-btn:hover {
    background: #667eea;
    color: white;
}

/* 📊 INSTANT RESULTS DROPDOWN */
.search-suggestions {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    right: 0;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    max-height: 600px;
    overflow-y: auto;
    display: none;
    z-index: 10000;
}

.search-suggestions.active {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.suggestions-header {
    padding: 20px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 20px 20px 0 0;
    font-weight: 600;
    color: #333;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.context-pills {
    display: flex;
    gap: 10px;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.context-pill {
    padding: 6px 16px;
    border-radius: 20px;
    background: white;
    border: 2px solid #e0e0e0;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 13px;
    font-weight: 500;
}

.context-pill:hover {
    border-color: #667eea;
    color: #667eea;
}

.context-pill.active {
    background: #667eea;
    border-color: #667eea;
    color: white;
}

.suggestion-item {
    padding: 12px 20px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 12px;
}

.suggestion-item:hover {
    background: #f8f9fa;
    padding-left: 25px;
}

.suggestion-icon {
    font-size: 20px;
    width: 30px;
    text-align: center;
}

.suggestion-text {
    flex: 1;
}

.suggestion-type {
    font-size: 11px;
    text-transform: uppercase;
    color: #999;
    letter-spacing: 0.5px;
}

.suggestion-meta {
    font-size: 12px;
    color: #666;
    background: #f5f5f5;
    padding: 2px 8px;
    border-radius: 10px;
}

/* 🔍 SEARCH RESULTS PAGE */
.search-results-container {
    max-width: 1200px;
    margin: 100px auto 40px;
    padding: 0 20px;
}

.search-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.search-query-display {
    font-size: 24px;
    font-weight: 600;
    color: #333;
}

.search-query-display mark {
    background: linear-gradient(120deg, #84fab0 0%, #8fd3f4 100%);
    color: #333;
    padding: 2px 8px;
    border-radius: 4px;
}

.search-actions {
    display: flex;
    gap: 10px;
}

.action-btn {
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    background: #f5f5f5;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: #667eea;
    color: white;
}

/* 📧 RESULT SECTIONS */
.results-section {
    margin-bottom: 30px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}

.section-header {
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-count {
    background: rgba(255,255,255,0.3);
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 14px;
}

.view-all-link {
    color: white;
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
}

.result-item {
    padding: 20px;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.2s ease;
}

.result-item:hover {
    background: #f8f9fa;
    padding-left: 30px;
}

.result-item:last-child {
    border-bottom: none;
}

.result-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.result-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.result-meta {
    font-size: 13px;
    color: #666;
    display: flex;
    gap: 15px;
}

.result-snippet {
    font-size: 14px;
    color: #555;
    line-height: 1.6;
    margin: 10px 0;
}

.result-snippet mark {
    background: #fff3cd;
    padding: 2px 4px;
    border-radius: 3px;
}

.result-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.result-action-btn {
    padding: 6px 14px;
    border-radius: 8px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.result-action-btn:hover {
    border-color: #667eea;
    color: #667eea;
}

/* 🤖 AI MODE EXPLANATION */
.ai-explanation {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(79, 172, 254, 0.3);
}

.ai-explanation-header {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}

.ai-explanation-text {
    font-size: 15px;
    line-height: 1.6;
    opacity: 0.95;
}

/* ⏱️ LOADING STATES */
.search-loading {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 📱 RESPONSIVE */
@media (max-width: 768px) {
    .universal-search-container {
        padding: 10px;
    }

    .search-shortcut {
        display: none;
    }

    .search-input {
        font-size: 14px;
    }
}
</style>

<div class="universal-search-container">
    <div class="search-bar-wrapper">
        <div class="search-input-group">
            <span class="search-icon">🔍</span>
            <input
                type="text"
                class="search-input"
                id="universalSearchInput"
                placeholder="Search emails, products, orders, customers..."
                autocomplete="off"
            />
            <span class="search-shortcut">⌘K</span>
            <button class="ai-mode-toggle" id="aiModeToggle">
                <span class="ai-icon">🤖</span> AI Mode
            </button>
            <button class="search-filters-btn" id="filtersToggle">
                ⚙️ Filters
            </button>
        </div>

        <!-- Instant Suggestions Dropdown -->
        <div class="search-suggestions" id="searchSuggestions">
            <div class="suggestions-header">
                <span>Search suggestions</span>
                <span id="suggestionCount"></span>
            </div>
            <div class="context-pills">
                <div class="context-pill active" data-context="all">All</div>
                <div class="context-pill" data-context="emails">📧 Emails</div>
                <div class="context-pill" data-context="products">📦 Products</div>
                <div class="context-pill" data-context="orders">🛒 Orders</div>
                <div class="context-pill" data-context="customers">👥 Customers</div>
            </div>
            <div id="suggestionsContent"></div>
        </div>
    </div>
</div>

<script>
// 🚀 UNIVERSAL SEARCH - JAVASCRIPT MAGIC
(function() {
    const searchInput = document.getElementById('universalSearchInput');
    const suggestions = document.getElementById('searchSuggestions');
    const suggestionsContent = document.getElementById('suggestionsContent');
    const aiModeToggle = document.getElementById('aiModeToggle');
    const contextPills = document.querySelectorAll('.context-pill');

    let isAIMode = false;
    let selectedContext = 'all';
    let debounceTimer;

    // Keyboard shortcut: Cmd/Ctrl + K
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            searchInput.focus();
        }

        // Escape to close suggestions
        if (e.key === 'Escape') {
            suggestions.classList.remove('active');
        }
    });

    // Search input with debounce
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();

        clearTimeout(debounceTimer);

        if (query.length < 2) {
            suggestions.classList.remove('active');
            return;
        }

        // Debounce: wait 300ms after user stops typing
        debounceTimer = setTimeout(() => {
            if (isAIMode) {
                showAIMode(query);
            } else {
                fetchSuggestions(query);
            }
        }, 300);
    });

    // Focus: show suggestions
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.length >= 2) {
            suggestions.classList.add('active');
        }
    });

    // AI Mode toggle
    aiModeToggle.addEventListener('click', () => {
        isAIMode = !isAIMode;
        aiModeToggle.classList.toggle('active');

        if (isAIMode) {
            searchInput.placeholder = "Ask me anything... (e.g., 'urgent emails from last week about deliveries')";
        } else {
            searchInput.placeholder = "Search emails, products, orders, customers...";
        }
    });

    // Context pills
    contextPills.forEach(pill => {
        pill.addEventListener('click', () => {
            contextPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            selectedContext = pill.dataset.context;

            if (searchInput.value.length >= 2) {
                fetchSuggestions(searchInput.value);
            }
        });
    });

    // Fetch suggestions (AJAX)
    function fetchSuggestions(query) {
        suggestions.classList.add('active');
        suggestionsContent.innerHTML = '<div class="search-loading"><div class="loading-spinner"></div></div>';

        fetch('/api/search/suggestions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query, context: selectedContext })
        })
        .then(res => res.json())
        .then(data => {
            displaySuggestions(data.suggestions);
            document.getElementById('suggestionCount').textContent = `${data.suggestions.length} suggestions`;
        })
        .catch(err => {
            console.error('Suggestions failed:', err);
            suggestionsContent.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Failed to load suggestions</div>';
        });
    }

    // Display suggestions
    function displaySuggestions(suggestions) {
        if (suggestions.length === 0) {
            suggestionsContent.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No suggestions found</div>';
            return;
        }

        const html = suggestions.map(s => `
            <div class="suggestion-item" onclick="selectSuggestion('${s.text}')">
                <span class="suggestion-icon">${s.icon}</span>
                <div class="suggestion-text">
                    <div>${highlightMatch(s.text, searchInput.value)}</div>
                    <div class="suggestion-type">${s.type}</div>
                </div>
                ${s.count ? `<span class="suggestion-meta">${s.count} times</span>` : ''}
            </div>
        `).join('');

        suggestionsContent.innerHTML = html;
    }

    // Show AI mode explanation
    function showAIMode(query) {
        suggestions.classList.add('active');
        suggestionsContent.innerHTML = `
            <div style="padding: 20px;">
                <div class="ai-explanation">
                    <div class="ai-explanation-header">
                        🤖 AI is analyzing your query...
                    </div>
                    <div class="ai-explanation-text">
                        I'll interpret "${query}" and fetch exactly what you're looking for.
                        This may take a moment...
                    </div>
                </div>
                <div class="search-loading"><div class="loading-spinner"></div></div>
            </div>
        `;
    }

    // Select suggestion
    window.selectSuggestion = function(text) {
        searchInput.value = text;
        performSearch(text);
    };

    // Perform search (Enter key or suggestion click)
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            performSearch(searchInput.value);
        }
    });

    function performSearch(query) {
        suggestions.classList.remove('active');

        if (isAIMode) {
            window.location.href = `/search/ai?q=${encodeURIComponent(query)}`;
        } else {
            window.location.href = `/search?q=${encodeURIComponent(query)}&context=${selectedContext}`;
        }
    }

    // Highlight matching text
    function highlightMatch(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    // Click outside to close
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-bar-wrapper')) {
            suggestions.classList.remove('active');
        }
    });
})();
</script>
