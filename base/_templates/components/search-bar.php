<!-- Universal AI Search Bar Component -->
<div class="cis-search-bar">
    <div class="search-input-wrapper">
        <i class="fas fa-search search-icon"></i>
        <input 
            type="text" 
            class="search-input" 
            id="universalSearch" 
            placeholder="Search CIS... (Ctrl+K)"
            autocomplete="off"
        >
        <div class="search-shortcut">
            <kbd>Ctrl</kbd> + <kbd>K</kbd>
        </div>
    </div>
    
    <!-- Search Results Dropdown (hidden by default) -->
    <div class="search-results" id="searchResults" style="display: none;">
        <div class="search-loading" id="searchLoading">
            <i class="fas fa-spinner fa-spin"></i> Searching...
        </div>
        
        <div class="search-results-content" id="searchResultsContent">
            <!-- Results will be populated here -->
        </div>
        
        <div class="search-footer">
            <span class="search-tip">
                <i class="fas fa-lightbulb"></i> Powered by AI
            </span>
        </div>
    </div>
</div>

<style>
    .cis-search-bar {
        position: relative;
        width: 100%;
        max-width: 600px;
    }
    
    .search-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .search-icon {
        position: absolute;
        left: 1rem;
        color: var(--cis-gray-500);
        pointer-events: none;
    }
    
    .search-input {
        width: 100%;
        padding: 0.625rem 7rem 0.625rem 2.5rem;
        border: 1px solid var(--cis-border-color);
        border-radius: var(--cis-border-radius);
        font-size: var(--cis-font-size-sm);
        transition: all 0.2s;
    }
    
    .search-input:focus {
        outline: none;
        border-color: var(--cis-primary);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
    
    .search-shortcut {
        position: absolute;
        right: 1rem;
        display: flex;
        gap: 0.25rem;
        pointer-events: none;
    }
    
    .search-shortcut kbd {
        padding: 0.125rem 0.375rem;
        font-size: 0.75rem;
        background-color: var(--cis-gray-100);
        border: 1px solid var(--cis-border-color);
        border-radius: 3px;
        font-family: monospace;
    }
    
    .search-results {
        position: absolute;
        top: calc(100% + 0.5rem);
        left: 0;
        right: 0;
        max-height: 500px;
        background-color: var(--cis-white);
        border: 1px solid var(--cis-border-color);
        border-radius: var(--cis-border-radius);
        box-shadow: var(--cis-shadow-lg);
        overflow: hidden;
        z-index: var(--cis-z-dropdown);
    }
    
    .search-loading {
        padding: 2rem;
        text-align: center;
        color: var(--cis-gray-600);
    }
    
    .search-results-content {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .search-category {
        padding: 0.5rem 1rem;
        background-color: var(--cis-gray-100);
        font-size: var(--cis-font-size-xs);
        font-weight: var(--cis-font-weight-bold);
        color: var(--cis-gray-600);
        text-transform: uppercase;
    }
    
    .search-result-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--cis-border-color);
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .search-result-item:hover,
    .search-result-item.selected {
        background-color: var(--cis-gray-100);
    }
    
    .search-result-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--cis-gray-200);
        border-radius: var(--cis-border-radius);
        margin-right: 0.75rem;
        flex-shrink: 0;
    }
    
    .search-result-icon i {
        color: var(--cis-gray-600);
    }
    
    .search-result-content {
        flex: 1;
        min-width: 0;
    }
    
    .search-result-title {
        font-weight: var(--cis-font-weight-semibold);
        color: var(--cis-gray-800);
        margin-bottom: 0.125rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .search-result-description {
        font-size: var(--cis-font-size-sm);
        color: var(--cis-gray-600);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .search-result-badge {
        padding: 0.125rem 0.5rem;
        font-size: var(--cis-font-size-xs);
        background-color: var(--cis-primary);
        color: var(--cis-white);
        border-radius: 12px;
        margin-left: 0.5rem;
    }
    
    .search-footer {
        padding: 0.5rem 1rem;
        background-color: var(--cis-gray-100);
        border-top: 1px solid var(--cis-border-color);
        text-align: center;
    }
    
    .search-tip {
        font-size: var(--cis-font-size-xs);
        color: var(--cis-gray-600);
    }
    
    .search-tip i {
        color: var(--cis-warning);
    }
    
    .search-empty {
        padding: 2rem;
        text-align: center;
        color: var(--cis-gray-600);
    }
    
    @media (max-width: 768px) {
        .search-shortcut {
            display: none;
        }
        
        .search-input {
            padding-right: 1rem;
        }
    }
</style>

<script>
    $(document).ready(function() {
        let searchTimeout;
        let selectedIndex = -1;
        
        // Keyboard shortcut (Ctrl+K)
        $(document).on('keydown', function(e) {
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                $('#universalSearch').focus();
            }
            
            // Escape to close
            if (e.key === 'Escape') {
                $('#searchResults').hide();
                $('#universalSearch').blur();
            }
        });
        
        // Search input with debounce
        $('#universalSearch').on('input', function() {
            const query = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                $('#searchResults').hide();
                return;
            }
            
            $('#searchResults').show();
            $('#searchLoading').show();
            $('#searchResultsContent').empty();
            
            searchTimeout = setTimeout(function() {
                performSearch(query);
            }, 300);
        });
        
        // Focus/blur handlers
        $('#universalSearch').on('focus', function() {
            const query = $(this).val().trim();
            if (query.length >= 2) {
                $('#searchResults').show();
            }
        });
        
        // Click outside to close
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.cis-search-bar').length) {
                $('#searchResults').hide();
            }
        });
        
        // Perform AI search
        function performSearch(query) {
            $.ajax({
                url: '/api/ai-search.php',
                method: 'POST',
                data: { query: query },
                dataType: 'json',
                success: function(response) {
                    $('#searchLoading').hide();
                    
                    if (response.success && response.data.length > 0) {
                        displayResults(response.data);
                    } else {
                        displayEmpty();
                    }
                },
                error: function() {
                    $('#searchLoading').hide();
                    displayError();
                }
            });
        }
        
        // Display search results
        function displayResults(results) {
            const $content = $('#searchResultsContent');
            $content.empty();
            
            // Group by category
            const grouped = {};
            results.forEach(function(item) {
                const category = item.category || 'Other';
                if (!grouped[category]) {
                    grouped[category] = [];
                }
                grouped[category].push(item);
            });
            
            // Render grouped results
            Object.keys(grouped).forEach(function(category) {
                $content.append('<div class="search-category">' + category + '</div>');
                
                grouped[category].forEach(function(item) {
                    const $item = $('<div class="search-result-item"></div>');
                    $item.attr('data-url', item.url);
                    
                    $item.html(`
                        <div class="search-result-icon">
                            <i class="${item.icon || 'fas fa-file'}"></i>
                        </div>
                        <div class="search-result-content">
                            <div class="search-result-title">${item.title}</div>
                            <div class="search-result-description">${item.description || ''}</div>
                        </div>
                        ${item.badge ? '<span class="search-result-badge">' + item.badge + '</span>' : ''}
                    `);
                    
                    $item.on('click', function() {
                        window.location.href = $(this).data('url');
                    });
                    
                    $content.append($item);
                });
            });
        }
        
        // Display empty state
        function displayEmpty() {
            $('#searchResultsContent').html(`
                <div class="search-empty">
                    <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                    <p>No results found</p>
                </div>
            `);
        }
        
        // Display error state
        function displayError() {
            $('#searchResultsContent').html(`
                <div class="search-empty">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; color: var(--cis-danger);"></i>
                    <p>Search error. Please try again.</p>
                </div>
            `);
        }
    });
</script>
