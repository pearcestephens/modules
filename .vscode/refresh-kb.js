#!/usr/bin/env node

/**
 * CIS Knowledge Base Auto-Refresh System
 * Scans modules and docs, generates searchable index and documentation
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const CONFIG = {
    rootDir: process.cwd(),
    copilotDir: path.join(process.cwd(), '_copilot'),
    modulesDir: path.join(process.cwd()),
    docsDir: path.join(process.cwd(), 'docs'),
    searchIndex: path.join(process.cwd(), '_copilot/SEARCH/index.json'),
    statusFile: path.join(process.cwd(), '_copilot/STATUS.md'),
    serverMode: process.argv.includes('--server-mode')
};

class KnowledgeBaseRefresher {
    constructor() {
        this.stats = {
            modulesScanned: 0,
            filesScanned: 0,
            docsGenerated: 0,
            docsUnchanged: 0,
            errors: []
        };
        this.searchEntries = [];
        this.lintIssues = {
            bootstrapMixing: 0,
            duplicateBodies: 0,
            rawIncludes: 0,
            oversizedFiles: 0
        };
    }

    async run() {
        try {
            this.log('🔄 Starting Knowledge Base refresh...');
            
            // Ensure directory structure exists
            this.ensureDirectories();
            
            // Scan modules
            await this.scanModules();
            
            // Scan docs if present
            if (fs.existsSync(CONFIG.docsDir)) {
                await this.scanDocs();
            }
            
            // Generate search index
            await this.generateSearchIndex();
            
            // Update status file
            await this.updateStatus();
            
            this.log(`✅ Refresh complete: ${this.stats.modulesScanned} modules, ${this.stats.filesScanned} files scanned, ${this.stats.docsGenerated} docs generated`);
            
        } catch (error) {
            this.error('❌ Refresh failed:', error.message);
            process.exit(1);
        }
    }

    ensureDirectories() {
        const dirs = [
            CONFIG.copilotDir,
            path.join(CONFIG.copilotDir, 'MODULES'),
            path.join(CONFIG.copilotDir, 'SEARCH'),
            path.join(CONFIG.copilotDir, 'logs')
        ];
        
        dirs.forEach(dir => {
            if (!fs.existsSync(dir)) {
                fs.mkdirSync(dir, { recursive: true });
            }
        });
    }

    async scanModules() {
        const items = fs.readdirSync(CONFIG.modulesDir, { withFileTypes: true });
        
        for (const item of items) {
            if (item.isDirectory() && this.isModuleDirectory(item.name)) {
                await this.processModule(item.name);
                this.stats.modulesScanned++;
            }
        }
    }

    isModuleDirectory(name) {
        // Skip special directories
        const skipDirs = ['.git', '.vscode', '_copilot', '_kb', 'node_modules'];
        return !skipDirs.includes(name) && !name.startsWith('.');
    }

    async processModule(moduleName) {
        const modulePath = path.join(CONFIG.modulesDir, moduleName);
        const moduleDocsDir = path.join(CONFIG.copilotDir, 'MODULES', moduleName);
        
        // Ensure module docs directory exists
        if (!fs.existsSync(moduleDocsDir)) {
            fs.mkdirSync(moduleDocsDir, { recursive: true });
        }

        // Scan module files
        const moduleFiles = this.scanDirectoryRecursive(modulePath);
        this.stats.filesScanned += moduleFiles.length;

        // Extract module information
        const moduleInfo = this.extractModuleInfo(modulePath, moduleFiles);
        
        // Generate documentation files
        const docFiles = [
            'README.md', 'routes.md', 'controllers.md', 
            'views.md', 'templates.md', 'data-flows.md', 'testing-notes.md'
        ];

        for (const docFile of docFiles) {
            await this.generateModuleDoc(moduleName, docFile, moduleInfo, moduleDocsDir);
        }

        // Add to search index
        this.addToSearchIndex(moduleName, moduleInfo, moduleDocsDir);
    }

    scanDirectoryRecursive(dir) {
        let files = [];
        try {
            const items = fs.readdirSync(dir, { withFileTypes: true });
            
            for (const item of items) {
                const fullPath = path.join(dir, item.name);
                
                if (item.isDirectory() && !item.name.startsWith('.')) {
                    files = files.concat(this.scanDirectoryRecursive(fullPath));
                } else if (item.isFile()) {
                    files.push(fullPath);
                    this.lintFile(fullPath);
                }
            }
        } catch (error) {
            // Directory not accessible, skip
        }
        
        return files;
    }

    extractModuleInfo(modulePath, files) {
        const info = {
            name: path.basename(modulePath),
            path: modulePath,
            files: files,
            phpFiles: files.filter(f => f.endsWith('.php')),
            jsFiles: files.filter(f => f.endsWith('.js')),
            cssFiles: files.filter(f => f.endsWith('.css')),
            routes: [],
            controllers: [],
            views: [],
            apis: []
        };

        // Extract routes, controllers, etc. (basic pattern matching)
        info.phpFiles.forEach(file => {
            const relativePath = path.relative(modulePath, file);
            
            if (relativePath.includes('api/')) {
                info.apis.push(relativePath);
            }
            if (relativePath.includes('controller') || relativePath.includes('Controller')) {
                info.controllers.push(relativePath);
            }
            if (relativePath.includes('view') || relativePath.includes('template')) {
                info.views.push(relativePath);
            }
        });

        return info;
    }

    async generateModuleDoc(moduleName, docType, moduleInfo, outputDir) {
        const outputPath = path.join(outputDir, docType);
        let content = '';

        // Generate content based on docType
        switch (docType) {
            case 'README.md':
                content = this.generateReadmeContent(moduleName, moduleInfo);
                break;
            case 'routes.md':
                content = this.generateRoutesContent(moduleName, moduleInfo);
                break;
            case 'controllers.md':
                content = this.generateControllersContent(moduleName, moduleInfo);
                break;
            case 'views.md':
                content = this.generateViewsContent(moduleName, moduleInfo);
                break;
            default:
                content = this.generatePlaceholderContent(moduleName, docType);
        }

        // Check if content has changed
        if (this.shouldUpdateFile(outputPath, content)) {
            fs.writeFileSync(outputPath, content);
            this.stats.docsGenerated++;
        } else {
            this.stats.docsUnchanged++;
        }
    }

    generateReadmeContent(moduleName, moduleInfo) {
        return `# ${moduleName.charAt(0).toUpperCase() + moduleName.slice(1)} Module

## Overview
${this.getModuleDescription(moduleName)}

**Module Path:** \`./\${moduleName}/\`  
**Module Type:** ${this.getModuleType(moduleInfo)}  
**Last Updated:** ${new Date().toISOString().split('T')[0]} ${new Date().toISOString().split('T')[1].split('.')[0]} UTC

## Statistics
- **Total Files:** ${moduleInfo.files.length}
- **PHP Files:** ${moduleInfo.phpFiles.length}
- **JavaScript Files:** ${moduleInfo.jsFiles.length}
- **CSS Files:** ${moduleInfo.cssFiles.length}
- **API Endpoints:** ${moduleInfo.apis.length}
- **Controllers:** ${moduleInfo.controllers.length}

## Key Files
${this.generateKeyFilesList(moduleInfo)}

## Quick Start
\`\`\`php
// Module initialization
require_once './${moduleName}/';
\`\`\`

## Related Documentation
- [Routes](./routes.md) - Routing configuration
- [Controllers](./controllers.md) - Controller classes and methods
- [Views](./views.md) - Template and view files
- [Templates](./templates.md) - UI component structure
- [Data Flows](./data-flows.md) - Database and API interactions
- [Testing Notes](./testing-notes.md) - Test cases and validation

---
*Generated by CIS Knowledge Base System*`;
    }

    generateRoutesContent(moduleName, moduleInfo) {
        return `# ${moduleName.charAt(0).toUpperCase() + moduleName.slice(1)} Module - Routes

## API Endpoints
${moduleInfo.apis.map(api => `- \`${api}\``).join('\n') || 'No API endpoints found'}

## Route Files
${moduleInfo.files.filter(f => f.includes('route')).map(f => `- \`${path.relative(moduleInfo.path, f)}\``).join('\n') || 'No explicit route files found'}

---
*Generated by CIS Knowledge Base System*`;
    }

    generateControllersContent(moduleName, moduleInfo) {
        return `# ${moduleName.charAt(0).toUpperCase() + moduleName.slice(1)} Module - Controllers

## Controller Files
${moduleInfo.controllers.map(ctrl => `- \`${ctrl}\``).join('\n') || 'No controller files found'}

---
*Generated by CIS Knowledge Base System*`;
    }

    generateViewsContent(moduleName, moduleInfo) {
        return `# ${moduleName.charAt(0).toUpperCase() + moduleName.slice(1)} Module - Views

## View Files
${moduleInfo.views.map(view => `- \`${view}\``).join('\n') || 'No view files found'}

---
*Generated by CIS Knowledge Base System*`;
    }

    generatePlaceholderContent(moduleName, docType) {
        const title = docType.replace('.md', '').split('-').map(w => 
            w.charAt(0).toUpperCase() + w.slice(1)
        ).join(' ');

        return `# ${moduleName.charAt(0).toUpperCase() + moduleName.slice(1)} Module - ${title}

## Overview
Documentation for ${moduleName} module ${title.toLowerCase()}.

**Last Updated:** ${new Date().toISOString().split('T')[0]} ${new Date().toISOString().split('T')[1].split('.')[0]} UTC

---
*Generated by CIS Knowledge Base System*`;
    }

    getModuleDescription(moduleName) {
        const descriptions = {
            'consignments': 'Transfer and inventory management system with pack/receive operations.',
            'core': 'Core system utilities and bootstrapping functionality.',
            'template': 'Common UI components and layout templates.',
            'CIS TEMPLATE': 'Common UI components and layout templates.'
        };
        return descriptions[moduleName] || `${moduleName} module functionality.`;
    }

    getModuleType(moduleInfo) {
        if (moduleInfo.apis.length > 5) return 'Full-stack MVC';
        if (moduleInfo.controllers.length > 0) return 'MVC Module';
        if (moduleInfo.views.length > 5) return 'UI Templates';
        if (moduleInfo.phpFiles.length > 0) return 'PHP Module';
        return 'Static Assets';
    }

    generateKeyFilesList(moduleInfo) {
        const keyFiles = [];
        
        // Add main entry points
        const entryFiles = moduleInfo.files.filter(f => {
            const basename = path.basename(f);
            return basename === 'index.php' || basename === 'module_bootstrap.php';
        });
        
        entryFiles.forEach(file => {
            keyFiles.push(`- \`${path.relative(moduleInfo.path, file)}\` - Module entry point`);
        });

        // Add first few controllers
        moduleInfo.controllers.slice(0, 3).forEach(ctrl => {
            keyFiles.push(`- \`${ctrl}\` - Controller`);
        });

        // Add first few APIs
        moduleInfo.apis.slice(0, 3).forEach(api => {
            keyFiles.push(`- \`${api}\` - API endpoint`);
        });

        return keyFiles.join('\n') || 'No key files identified';
    }

    shouldUpdateFile(filePath, newContent) {
        if (!fs.existsSync(filePath)) return true;
        
        const currentContent = fs.readFileSync(filePath, 'utf8');
        const currentHash = crypto.createHash('md5').update(currentContent).digest('hex');
        const newHash = crypto.createHash('md5').update(newContent).digest('hex');
        
        return currentHash !== newHash;
    }

    addToSearchIndex(moduleName, moduleInfo, moduleDocsDir) {
        const docFiles = fs.readdirSync(moduleDocsDir).filter(f => f.endsWith('.md'));
        
        docFiles.forEach(docFile => {
            const filePath = path.join(moduleDocsDir, docFile);
            const content = fs.readFileSync(filePath, 'utf8');
            
            // Extract headings and create snippets
            const headings = this.extractHeadings(content);
            const snippets = this.extractSnippets(content);
            
            this.searchEntries.push({
                path: path.relative(CONFIG.rootDir, filePath),
                title: `${moduleName} - ${docFile.replace('.md', '')}`,
                headings: headings,
                tags: ['modules', moduleName, 'documentation'],
                snippets: snippets,
                mtime: Math.floor(Date.now() / 1000)
            });
        });
    }

    extractHeadings(content) {
        const headingRegex = /^#{1,6}\s+(.+)$/gm;
        const headings = [];
        let match;
        
        while ((match = headingRegex.exec(content)) !== null) {
            headings.push(match[1].trim());
        }
        
        return headings;
    }

    extractSnippets(content) {
        // Extract first few meaningful lines as snippets
        const lines = content.split('\n')
            .filter(line => line.trim() && !line.startsWith('#'))
            .slice(0, 3);
            
        return lines.map(line => line.trim().substring(0, 100));
    }

    async scanDocs() {
        // Scan existing docs directory if present
        if (fs.existsSync(CONFIG.docsDir)) {
            const docFiles = this.scanDirectoryRecursive(CONFIG.docsDir);
            docFiles.forEach(file => {
                if (file.endsWith('.md')) {
                    const content = fs.readFileSync(file, 'utf8');
                    const headings = this.extractHeadings(content);
                    const snippets = this.extractSnippets(content);
                    
                    this.searchEntries.push({
                        path: path.relative(CONFIG.rootDir, file),
                        title: path.basename(file, '.md'),
                        headings: headings,
                        tags: ['docs', 'documentation'],
                        snippets: snippets,
                        mtime: Math.floor(fs.statSync(file).mtime.getTime() / 1000)
                    });
                }
            });
        }
    }

    async generateSearchIndex() {
        const index = {
            generated: new Date().toISOString(),
            entries: this.searchEntries,
            stats: {
                totalEntries: this.searchEntries.length,
                modules: this.stats.modulesScanned,
                size: JSON.stringify(this.searchEntries).length
            }
        };

        fs.writeFileSync(CONFIG.searchIndex, JSON.stringify(index, null, 2));
    }

    lintFile(filePath) {
        try {
            const content = fs.readFileSync(filePath, 'utf8');
            const fileName = path.basename(filePath);
            
            // Check file size
            if (content.length > 25600) { // 25KB
                this.lintIssues.oversizedFiles++;
            }

            // Check for Bootstrap 4/5 mixing
            if (content.includes('data-dismiss') && content.includes('data-bs-dismiss')) {
                this.lintIssues.bootstrapMixing++;
            }

            // Check for duplicate body tags
            const bodyMatches = content.match(/<body[^>]*>/g);
            if (bodyMatches && bodyMatches.length > 1) {
                this.lintIssues.duplicateBodies++;
            }

            // Check for raw includes in views
            if (fileName.includes('view') || fileName.includes('template')) {
                if (content.match(/require_once|include_once|require|include/) && 
                    !fileName.includes('bootstrap') && !fileName.includes('config')) {
                    this.lintIssues.rawIncludes++;
                }
            }
        } catch (error) {
            // File not readable, skip linting
        }
    }

    async updateStatus() {
        const timestamp = new Date().toISOString().replace('T', ' ').split('.')[0] + ' UTC';
        
        const status = `# Knowledge Base Status Report

## Last Refresh
- **Timestamp:** ${timestamp}
- **Status:** SUCCESS
- **Modules Scanned:** ${this.stats.modulesScanned}
- **Files Scanned:** ${this.stats.filesScanned}
- **Docs Generated:** ${this.stats.docsGenerated}
- **Docs Unchanged:** ${this.stats.docsUnchanged}

## Lint Results
### Bootstrap 4/5 Mixing Issues
- ${this.lintIssues.bootstrapMixing === 0 ? '✅' : '❌'} Found ${this.lintIssues.bootstrapMixing} instances of data-dismiss vs data-bs-dismiss mixing

### Security Issues  
- ${this.lintIssues.rawIncludes === 0 ? '✅' : '❌'} Found ${this.lintIssues.rawIncludes} raw include/require statements in views
- ${this.lintIssues.duplicateBodies === 0 ? '✅' : '❌'} Found ${this.lintIssues.duplicateBodies} duplicate <body> tags

### Performance Issues
- ${this.lintIssues.oversizedFiles === 0 ? '✅' : '❌'} Found ${this.lintIssues.oversizedFiles} files over 25KB limit

## Search Index Stats  
- **Total Entries:** ${this.searchEntries.length}
- **Index Size:** ${Math.round(JSON.stringify(this.searchEntries).length / 1024 * 10) / 10}KB
- **Last Updated:** ${timestamp}

---
*Auto-generated by CIS Knowledge Base System*
${timestamp} - refresh completed
`;

        fs.writeFileSync(CONFIG.statusFile, status);
    }

    log(message) {
        if (!CONFIG.serverMode) {
            console.log(message);
        }
    }

    error(message, ...args) {
        if (!CONFIG.serverMode) {
            console.error(message, ...args);
        }
    }
}

// Run the refresher
if (require.main === module) {
    const refresher = new KnowledgeBaseRefresher();
    refresher.run().catch(error => {
        console.error('Fatal error:', error);
        process.exit(1);
    });
}

module.exports = KnowledgeBaseRefresher;