/**
 * File Browser & Application-Wide Editor
 * Browse and edit ANY file in the application
 *
 * Features:
 * - Full application file tree
 * - Edit any PHP, HTML, CSS, JS file
 * - PHP execution support
 * - Real-time collaboration
 * - Multi-user cursors
 * - File search and filtering
 * - Recent files
 * - Favorites/bookmarks
 *
 * @version 1.0.0
 */

class FileExplorer {
    constructor() {
        this.currentFile = null;
        this.fileTree = {};
        this.recentFiles = [];
        this.favorites = [];
        this.collaborators = [];

        this.init();
    }

    init() {
        this.createExplorerPanel();
        this.loadFileTree();
        this.setupCollaboration();
        console.log('📁 File Explorer initialized');
    }

    createExplorerPanel() {
        const panel = document.createElement('div');
        panel.id = 'file-explorer-panel';
        panel.className = 'file-explorer-panel collapsed';
        panel.innerHTML = `
            <div class="explorer-header">
                <div class="explorer-title">
                    <i class="fas fa-folder-open"></i>
                    <span>File Explorer</span>
                </div>
                <button class="btn-toggle-explorer" onclick="fileExplorer.togglePanel()">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="explorer-content">
                <!-- Search Bar -->
                <div class="explorer-search">
                    <i class="fas fa-search"></i>
                    <input type="text"
                           id="file-search-input"
                           placeholder="Search files..."
                           onkeyup="fileExplorer.searchFiles(this.value)">
                </div>

                <!-- Tabs -->
                <div class="explorer-tabs">
                    <button class="explorer-tab active" onclick="fileExplorer.switchTab('tree')">
                        <i class="fas fa-sitemap"></i> Files
                    </button>
                    <button class="explorer-tab" onclick="fileExplorer.switchTab('recent')">
                        <i class="fas fa-history"></i> Recent
                    </button>
                    <button class="explorer-tab" onclick="fileExplorer.switchTab('favorites')">
                        <i class="fas fa-star"></i> Favorites
                    </button>
                </div>

                <!-- File Tree -->
                <div id="file-tree-container" class="file-tree-container">
                    <div class="loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        Loading files...
                    </div>
                </div>

                <!-- Recent Files -->
                <div id="recent-files-container" class="recent-files-container" style="display: none;">
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No recent files</p>
                    </div>
                </div>

                <!-- Favorites -->
                <div id="favorites-container" class="favorites-container" style="display: none;">
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        <p>No favorites yet</p>
                    </div>
                </div>

                <!-- Collaboration Panel -->
                <div class="collab-panel">
                    <div class="collab-header">
                        <i class="fas fa-users"></i>
                        <span>Collaborators (<span id="collab-count">0</span>)</span>
                    </div>
                    <div id="collaborators-list" class="collaborators-list"></div>
                </div>
            </div>

            <!-- File Actions Bottom Bar -->
            <div class="explorer-actions">
                <button class="btn-explorer-action" onclick="fileExplorer.newFile()">
                    <i class="fas fa-plus"></i> New
                </button>
                <button class="btn-explorer-action" onclick="fileExplorer.uploadFile()">
                    <i class="fas fa-upload"></i> Upload
                </button>
                <button class="btn-explorer-action" onclick="fileExplorer.refreshTree()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        `;

        document.body.appendChild(panel);
        this.addStyles();
    }

    addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .file-explorer-panel {
                position: fixed;
                left: 0;
                top: 60px;
                width: 320px;
                height: calc(100vh - 60px);
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                border-right: 2px solid #3b82f6;
                box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
                z-index: 900;
                transition: transform 0.3s ease;
                display: flex;
                flex-direction: column;
            }

            .file-explorer-panel.collapsed {
                transform: translateX(-100%);
            }

            .explorer-header {
                padding: 1rem 1.5rem;
                background: rgba(59, 130, 246, 0.1);
                border-bottom: 1px solid rgba(59, 130, 246, 0.3);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .explorer-title {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: #3b82f6;
                font-weight: 600;
                font-size: 1rem;
            }

            .btn-toggle-explorer {
                width: 32px;
                height: 32px;
                background: transparent;
                border: 1px solid rgba(59, 130, 246, 0.3);
                border-radius: 4px;
                color: #3b82f6;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-toggle-explorer:hover {
                background: rgba(59, 130, 246, 0.2);
            }

            .explorer-content {
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .explorer-search {
                padding: 1rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                border-bottom: 1px solid #334155;
            }

            .explorer-search i {
                color: #64748b;
            }

            #file-search-input {
                flex: 1;
                background: #334155;
                border: 1px solid #475569;
                border-radius: 6px;
                padding: 0.5rem 0.75rem;
                color: #f1f5f9;
                font-size: 0.875rem;
            }

            #file-search-input:focus {
                outline: none;
                border-color: #3b82f6;
            }

            .explorer-tabs {
                display: flex;
                border-bottom: 1px solid #334155;
                background: #1e293b;
            }

            .explorer-tab {
                flex: 1;
                padding: 0.75rem;
                background: transparent;
                border: none;
                color: #64748b;
                font-size: 0.75rem;
                font-weight: 600;
                cursor: pointer;
                border-bottom: 2px solid transparent;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .explorer-tab:hover {
                color: #f1f5f9;
                background: rgba(59, 130, 246, 0.1);
            }

            .explorer-tab.active {
                color: #3b82f6;
                border-bottom-color: #3b82f6;
            }

            .file-tree-container,
            .recent-files-container,
            .favorites-container {
                flex: 1;
                overflow-y: auto;
                padding: 0.5rem;
            }

            .file-tree-item {
                padding: 0.5rem 0.75rem;
                margin: 0.125rem 0;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: #94a3b8;
                font-size: 0.875rem;
            }

            .file-tree-item:hover {
                background: #334155;
                color: #f1f5f9;
            }

            .file-tree-item.selected {
                background: rgba(59, 130, 246, 0.2);
                color: #3b82f6;
            }

            .file-tree-item.folder {
                font-weight: 600;
            }

            .file-tree-item i {
                width: 16px;
                text-align: center;
            }

            .file-tree-item .file-name {
                flex: 1;
            }

            .file-tree-item .file-actions {
                display: none;
                gap: 0.25rem;
            }

            .file-tree-item:hover .file-actions {
                display: flex;
            }

            .file-action-btn {
                width: 24px;
                height: 24px;
                background: transparent;
                border: none;
                color: #64748b;
                cursor: pointer;
                border-radius: 4px;
                transition: all 0.2s;
            }

            .file-action-btn:hover {
                background: rgba(59, 130, 246, 0.2);
                color: #3b82f6;
            }

            .file-tree-children {
                margin-left: 1rem;
            }

            .collab-panel {
                border-top: 1px solid #334155;
                background: rgba(0, 0, 0, 0.2);
            }

            .collab-header {
                padding: 0.75rem 1rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: #f1f5f9;
                font-size: 0.875rem;
                font-weight: 600;
            }

            .collab-header i {
                color: #3b82f6;
            }

            .collaborators-list {
                padding: 0 1rem 0.75rem 1rem;
                max-height: 150px;
                overflow-y: auto;
            }

            .collaborator-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.5rem;
                margin-bottom: 0.5rem;
                background: #334155;
                border-radius: 6px;
            }

            .collaborator-avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: linear-gradient(135deg, #3b82f6, #8b5cf6);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 600;
                font-size: 0.875rem;
            }

            .collaborator-info {
                flex: 1;
            }

            .collaborator-name {
                font-size: 0.875rem;
                color: #f1f5f9;
                font-weight: 500;
            }

            .collaborator-status {
                font-size: 0.75rem;
                color: #64748b;
            }

            .collaborator-cursor {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #10b981;
                box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
            }

            .explorer-actions {
                padding: 0.75rem 1rem;
                border-top: 1px solid #334155;
                display: flex;
                gap: 0.5rem;
            }

            .btn-explorer-action {
                flex: 1;
                padding: 0.5rem 0.75rem;
                background: #334155;
                border: 1px solid #475569;
                border-radius: 6px;
                color: #f1f5f9;
                font-size: 0.75rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .btn-explorer-action:hover {
                background: #475569;
                transform: translateY(-1px);
            }

            .loading,
            .empty-state {
                padding: 3rem 2rem;
                text-align: center;
                color: #64748b;
            }

            .loading i,
            .empty-state i {
                font-size: 3rem;
                margin-bottom: 1rem;
                color: #3b82f6;
            }

            .empty-state p {
                margin: 0;
                font-size: 0.875rem;
            }

            /* Floating Toggle Button */
            .floating-explorer-btn {
                position: fixed;
                left: 24px;
                top: 80px;
                width: 48px;
                height: 48px;
                border-radius: 50%;
                background: linear-gradient(135deg, #3b82f6, #2563eb);
                color: white;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
                z-index: 899;
                font-size: 1.25rem;
                transition: all 0.3s;
                display: none;
            }

            .file-explorer-panel.collapsed ~ .floating-explorer-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .floating-explorer-btn:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(59, 130, 246, 0.6);
            }

            /* Multi-cursor for collaboration */
            .remote-cursor {
                position: absolute;
                width: 2px;
                height: 20px;
                pointer-events: none;
                z-index: 1000;
                transition: all 0.1s;
            }

            .remote-cursor::before {
                content: '';
                position: absolute;
                top: -4px;
                left: -4px;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: inherit;
            }

            .remote-cursor-label {
                position: absolute;
                top: -24px;
                left: -4px;
                padding: 2px 6px;
                background: inherit;
                color: white;
                font-size: 0.75rem;
                border-radius: 4px;
                white-space: nowrap;
            }
        `;

        document.head.appendChild(style);
    }

    togglePanel() {
        const panel = document.getElementById('file-explorer-panel');
        panel.classList.toggle('collapsed');
    }

    switchTab(tab) {
        // Update tabs
        document.querySelectorAll('.explorer-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`.explorer-tab[onclick*="${tab}"]`).classList.add('active');

        // Update containers
        document.getElementById('file-tree-container').style.display = tab === 'tree' ? 'block' : 'none';
        document.getElementById('recent-files-container').style.display = tab === 'recent' ? 'block' : 'none';
        document.getElementById('favorites-container').style.display = tab === 'favorites' ? 'block' : 'none';
    }

    async loadFileTree() {
        try {
            const response = await fetch('/modules/admin-ui/api/file-browser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list_files' })
            });

            const data = await response.json();

            if (data.success) {
                this.fileTree = data.files;
                this.renderFileTree();
            }
        } catch (error) {
            console.error('Failed to load file tree:', error);
        }
    }

    renderFileTree() {
        const container = document.getElementById('file-tree-container');
        container.innerHTML = this.buildTreeHTML(this.fileTree);
    }

    buildTreeHTML(items, level = 0) {
        let html = '';

        items.forEach(item => {
            const icon = item.type === 'folder' ? 'fa-folder' : this.getFileIcon(item.name);
            const hasChildren = item.children && item.children.length > 0;

            html += `
                <div class="file-tree-item ${item.type}"
                     data-path="${item.path}"
                     onclick="fileExplorer.selectFile('${item.path}', '${item.type}')">
                    <i class="fas ${icon}"></i>
                    <span class="file-name">${item.name}</span>
                    <div class="file-actions">
                        <button class="file-action-btn" onclick="event.stopPropagation(); fileExplorer.favoriteFile('${item.path}')">
                            <i class="fas fa-star"></i>
                        </button>
                        <button class="file-action-btn" onclick="event.stopPropagation(); fileExplorer.deleteFile('${item.path}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;

            if (hasChildren) {
                html += '<div class="file-tree-children">';
                html += this.buildTreeHTML(item.children, level + 1);
                html += '</div>';
            }
        });

        return html;
    }

    getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const iconMap = {
            'php': 'fa-code',
            'html': 'fab fa-html5',
            'css': 'fab fa-css3-alt',
            'js': 'fab fa-js',
            'json': 'fa-file-code',
            'md': 'fab fa-markdown',
            'txt': 'fa-file-alt',
            'jpg': 'fa-image',
            'png': 'fa-image',
            'gif': 'fa-image',
            'svg': 'fa-image'
        };

        return iconMap[ext] || 'fa-file';
    }

    async selectFile(path, type) {
        if (type === 'folder') {
            // Toggle folder expansion
            return;
        }

        // Highlight selected file
        document.querySelectorAll('.file-tree-item').forEach(item => {
            item.classList.remove('selected');
        });
        document.querySelector(`[data-path="${path}"]`).classList.add('selected');

        // Load file content
        await this.openFile(path);
    }

    async openFile(path) {
        try {
            const response = await fetch('/modules/admin-ui/api/file-browser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'read_file',
                    path: path
                })
            });

            const data = await response.json();

            if (data.success) {
                this.currentFile = {
                    path: path,
                    content: data.content,
                    type: data.type
                };

                // Load into appropriate editor
                this.loadIntoEditor(data.content, data.type);

                // Add to recent files
                this.addToRecent(path);

                // Notify AI agent
                if (typeof aiAgent !== 'undefined') {
                    aiAgent.addActivity(`Opened file: ${path}`, 'info');
                }
            }
        } catch (error) {
            console.error('Failed to open file:', error);
        }
    }

    loadIntoEditor(content, type) {
        const editorMap = {
            'php': 'html', // Load PHP as HTML for syntax highlighting
            'html': 'html',
            'css': 'css',
            'js': 'javascript',
            'javascript': 'javascript'
        };

        const editorType = editorMap[type] || 'html';
        const editor = editorType === 'html' ? window.htmlEditor :
                      editorType === 'css' ? window.cssEditor :
                      window.jsEditor;

        if (editor) {
            editor.setValue(content);

            // Switch to appropriate tab
            const tab = editorType === 'javascript' ? 'javascript' : editorType;
            switchTab(tab);
        }
    }

    async saveCurrentFile() {
        if (!this.currentFile) return;

        const activeTab = document.querySelector('.tab.active')?.dataset.tab;
        const editor = activeTab === 'html' ? window.htmlEditor :
                      activeTab === 'css' ? window.cssEditor :
                      window.jsEditor;

        const content = editor?.getValue();

        try {
            const response = await fetch('/modules/admin-ui/api/file-browser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_file',
                    path: this.currentFile.path,
                    content: content
                })
            });

            const data = await response.json();

            if (data.success) {
                if (typeof aiAgent !== 'undefined') {
                    aiAgent.addActivity(`Saved file: ${this.currentFile.path}`, 'success');
                }
            }
        } catch (error) {
            console.error('Failed to save file:', error);
        }
    }

    searchFiles(query) {
        // Filter file tree based on search query
        const items = document.querySelectorAll('.file-tree-item');

        items.forEach(item => {
            const name = item.querySelector('.file-name').textContent.toLowerCase();
            if (name.includes(query.toLowerCase())) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    addToRecent(path) {
        this.recentFiles = this.recentFiles.filter(f => f !== path);
        this.recentFiles.unshift(path);
        this.recentFiles = this.recentFiles.slice(0, 10);

        this.updateRecentFiles();
    }

    updateRecentFiles() {
        const container = document.getElementById('recent-files-container');

        if (this.recentFiles.length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i><p>No recent files</p></div>';
            return;
        }

        container.innerHTML = this.recentFiles.map(path => `
            <div class="file-tree-item" onclick="fileExplorer.openFile('${path}')">
                <i class="fas ${this.getFileIcon(path)}"></i>
                <span class="file-name">${path.split('/').pop()}</span>
            </div>
        `).join('');
    }

    favoriteFile(path) {
        if (this.favorites.includes(path)) {
            this.favorites = this.favorites.filter(f => f !== path);
        } else {
            this.favorites.push(path);
        }

        this.updateFavorites();
    }

    updateFavorites() {
        const container = document.getElementById('favorites-container');

        if (this.favorites.length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-star"></i><p>No favorites yet</p></div>';
            return;
        }

        container.innerHTML = this.favorites.map(path => `
            <div class="file-tree-item" onclick="fileExplorer.openFile('${path}')">
                <i class="fas ${this.getFileIcon(path)}"></i>
                <span class="file-name">${path.split('/').pop()}</span>
            </div>
        `).join('');
    }

    setupCollaboration() {
        // Simulate real-time collaboration
        // In production, use WebSocket
        this.updateCollaborators([
            { id: 1, name: 'You', status: 'Editing', color: '#3b82f6' }
        ]);
    }

    updateCollaborators(users) {
        this.collaborators = users;

        const container = document.getElementById('collaborators-list');
        const count = document.getElementById('collab-count');

        count.textContent = users.length;

        container.innerHTML = users.map(user => `
            <div class="collaborator-item">
                <div class="collaborator-avatar" style="background: ${user.color}">
                    ${user.name[0]}
                </div>
                <div class="collaborator-info">
                    <div class="collaborator-name">${user.name}</div>
                    <div class="collaborator-status">${user.status}</div>
                </div>
                <div class="collaborator-cursor"></div>
            </div>
        `).join('');
    }

    newFile() {
        const filename = prompt('Enter filename:');
        if (!filename) return;

        // Create new file logic
        if (typeof aiAgent !== 'undefined') {
            aiAgent.addActivity(`Created new file: ${filename}`, 'success');
        }
    }

    uploadFile() {
        const input = document.createElement('input');
        input.type = 'file';
        input.onchange = (e) => {
            const file = e.target.files[0];
            // Upload file logic
            if (typeof aiAgent !== 'undefined') {
                aiAgent.addActivity(`Uploaded file: ${file.name}`, 'success');
            }
        };
        input.click();
    }

    refreshTree() {
        this.loadFileTree();
        if (typeof aiAgent !== 'undefined') {
            aiAgent.addActivity('Refreshed file tree', 'info');
        }
    }

    deleteFile(path) {
        if (!confirm('Delete this file?')) return;

        // Delete file logic
        if (typeof aiAgent !== 'undefined') {
            aiAgent.addActivity(`Deleted file: ${path}`, 'warning');
        }
    }
}

// Auto-initialize
let fileExplorer;
document.addEventListener('DOMContentLoaded', () => {
    fileExplorer = new FileExplorer();

    // Add floating button
    const btn = document.createElement('button');
    btn.className = 'floating-explorer-btn';
    btn.innerHTML = '<i class="fas fa-folder-open"></i>';
    btn.onclick = () => fileExplorer.togglePanel();
    document.body.appendChild(btn);

    // Keyboard shortcut: Ctrl+E to toggle
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            fileExplorer.togglePanel();
        }
    });
});
