/**
 * 🤖 MCP INTEGRATION SYSTEM
 * Deep integration with MCP Server for AI-powered theme building
 * @version 2.0.0
 */

class MCPIntegration {
    constructor() {
        this.apiKey = '31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35';
        this.serverUrl = 'https://gpt.ecigdis.co.nz/mcp/server_v4.php';
        this.bots = this.initializeBots();
        this.streamingEnabled = true;
    }

    /**
     * Initialize AI Bot Specialists
     */
    initializeBots() {
        return {
            cssSpecialist: {
                name: 'CSS Master Bot',
                systemPrompt: `You are an expert CSS specialist. Generate clean, modern, production-ready CSS code.
                Focus on: Flexbox, Grid, animations, responsive design, accessibility, and performance.
                Always use CSS variables for colors and modern best practices.`,
                capabilities: ['generate-css', 'fix-css', 'optimize-css', 'create-animations']
            },
            layoutArchitect: {
                name: 'Layout Architect Bot',
                systemPrompt: `You are a master of HTML structure and layout design. Create semantic,
                accessible HTML layouts using modern techniques. Focus on responsive grids, flexbox,
                and component-based architecture.`,
                capabilities: ['generate-layout', 'create-sections', 'build-pages']
            },
            componentBuilder: {
                name: 'Component Factory Bot',
                systemPrompt: `You are a component building specialist. Create reusable, modular UI components
                with clean HTML and CSS. Include hover states, transitions, and responsive behavior.`,
                capabilities: ['build-component', 'create-variants', 'add-interactivity']
            },
            colorSchemer: {
                name: 'Color Scheme Bot',
                systemPrompt: `You are a color theory expert. Generate beautiful, accessible color palettes.
                Consider contrast ratios, color psychology, and modern design trends.`,
                capabilities: ['generate-palette', 'check-contrast', 'suggest-colors']
            },
            themeAnalyzer: {
                name: 'Theme Inspector Bot',
                systemPrompt: `You are a theme analysis expert. Analyze themes for performance, accessibility,
                best practices, and provide actionable improvement suggestions.`,
                capabilities: ['analyze-theme', 'suggest-improvements', 'check-accessibility']
            }
        };
    }

    /**
     * 🎨 GENERATE CSS - Main CSS generation function
     */
    async generateCSS(prompt, options = {}) {
        const query = `${this.bots.cssSpecialist.systemPrompt}\n\nTask: ${prompt}\n\nGenerate only the CSS code, no explanations.`;

        return await this.callMCP('ai-agent-query', {
            query: query,
            agent_id: 1,
            options: {
                temperature: 0.3,
                max_tokens: 2000
            }
        });
    }

    /**
     * 🏗️ GENERATE LAYOUT - Create HTML structure
     */
    async generateLayout(description, options = {}) {
        const query = `${this.bots.layoutArchitect.systemPrompt}\n\nCreate a ${description} layout.\n\nProvide semantic HTML5 structure with proper accessibility attributes.`;

        return await this.callMCP('ai-agent-query', {
            query: query,
            agent_id: 1,
            options: {
                temperature: 0.4,
                max_tokens: 3000
            }
        });
    }

    /**
     * 🧩 BUILD COMPONENT - Generate complete component
     */
    async buildComponent(componentType, style = 'modern') {
        const query = `${this.bots.componentBuilder.systemPrompt}\n\nBuild a ${style} ${componentType} component with HTML and CSS.\n\nInclude hover effects and responsive behavior.`;

        return await this.callMCP('ai-agent-query', {
            query: query,
            agent_id: 1,
            options: {
                temperature: 0.5,
                max_tokens: 2500
            }
        });
    }

    /**
     * 🌈 GENERATE COLOR SCHEME
     */
    async generateColorScheme(baseColor, mood = 'professional') {
        const query = `${this.bots.colorSchemer.systemPrompt}\n\nGenerate a ${mood} color scheme based on ${baseColor}.\n\nProvide: primary, secondary, accent, background, surface, text colors in hex format with CSS variables.`;

        return await this.callMCP('ai-agent-query', {
            query: query,
            agent_id: 1,
            options: {
                temperature: 0.6,
                max_tokens: 1500
            }
        });
    }

    /**
     * 🔍 ANALYZE THEME
     */
    async analyzeTheme(html, css) {
        const query = `${this.bots.themeAnalyzer.systemPrompt}\n\nAnalyze this theme:\n\nHTML:\n${html.substring(0, 1000)}\n\nCSS:\n${css.substring(0, 1000)}\n\nProvide: performance score, accessibility issues, improvement suggestions.`;

        return await this.callMCP('ai-agent-query', {
            query: query,
            agent_id: 1,
            options: {
                temperature: 0.3,
                max_tokens: 2000
            }
        });
    }

    /**
     * 🎪 ORCHESTRATED WORKFLOW - Multi-bot collaboration
     */
    async orchestratePageBuild(pageType, requirements) {
        const workflow = [
            { bot: 'layoutArchitect', task: `Create ${pageType} page structure` },
            { bot: 'componentBuilder', task: `Build components for ${pageType}` },
            { bot: 'cssSpecialist', task: `Style ${pageType} page` },
            { bot: 'themeAnalyzer', task: `Review and optimize` }
        ];

        const results = {
            workflow: pageType,
            steps: [],
            output: {}
        };

        for (const step of workflow) {
            console.log(`🤖 Executing: ${step.bot} - ${step.task}`);

            const result = await this.callMCP('ai-agent-query', {
                query: `${this.bots[step.bot].systemPrompt}\n\n${step.task}\n\nRequirements: ${requirements}`,
                agent_id: 1
            });

            results.steps.push({
                bot: step.bot,
                task: step.task,
                completed: true,
                output: result
            });
        }

        return results;
    }

    /**
     * 📥 IMPORT & RECONSTRUCT PAGE
     */
    async importPage(url) {
        // Use crawler tool to fetch page
        const crawlResult = await this.callMCP('crawler-web', {
            url: url,
            mode: 'quick',
            viewport: 'desktop'
        });

        // Analyze and rebuild with AI
        const reconstructQuery = `Analyze this crawled page and create an editable version:\n\n${JSON.stringify(crawlResult)}\n\nExtract key components and provide clean HTML/CSS.`;

        return await this.callMCP('ai-agent-query', {
            query: reconstructQuery,
            agent_id: 1,
            options: {
                max_tokens: 4000
            }
        });
    }

    /**
     * 🔧 OPTIMIZE CODE
     */
    async optimizeCode(code, type = 'css') {
        const query = `Optimize this ${type} code for production. Minimize size, improve performance, add prefixes if needed:\n\n${code}`;

        return await this.callMCP('ai-agent-query', {
            query: query,
            agent_id: 1,
            options: {
                temperature: 0.2,
                max_tokens: 3000
            }
        });
    }

    /**
     * 🎨 SMART SUGGESTIONS - Analyze and suggest improvements
     */
    async getSmartSuggestions(currentTheme) {
        const query = `Review this theme and provide 5 specific, actionable improvement suggestions:\n\nHTML: ${currentTheme.html?.substring(0, 500)}\nCSS: ${currentTheme.css?.substring(0, 500)}`;

        return await this.callMCP('ai-agent-query', {
            query: query,
            agent_id: 1,
            options: {
                temperature: 0.7,
                max_tokens: 1500
            }
        });
    }

    /**
     * 📊 SEARCH KNOWLEDGE BASE
     */
    async searchKnowledgeBase(query) {
        return await this.callMCP('semantic-search', {
            query: query,
            limit: 10,
            file_type: 'documentation'
        });
    }

    /**
     * 🗄️ QUERY DATABASE - Get theme data
     */
    async queryDatabase(sql) {
        return await this.callMCP('db-query', {
            query: sql,
            options: {
                max_rows: 50
            }
        });
    }

    /**
     * 📁 FILE OPERATIONS
     */
    async readFile(path) {
        return await this.callMCP('fs-read', { path: path });
    }

    async writeFile(path, content) {
        return await this.callMCP('fs-write', {
            path: path,
            content: content,
            backup: true
        });
    }

    /**
     * 🚀 CORE MCP CALL FUNCTION - With timeout and error handling
     */
    async callMCP(toolName, args, timeout = 30000) {
        // Create AbortController for timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(this.serverUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': this.apiKey,
                    'X-Project-ID': '1',
                    'X-Unit-ID': '2',
                    'X-User-ID': '1'
                },
                body: JSON.stringify({
                    jsonrpc: '2.0',
                    method: 'tools/call',
                    params: {
                        name: toolName,
                        arguments: args
                    },
                    id: Date.now()
                }),
                signal: controller.signal
            }).catch(err => {
                // Handle fetch errors with better messages
                if (err.name === 'AbortError') {
                    throw new Error(`Request timeout after ${timeout}ms`);
                }
                throw new Error(`Network error: ${err.message}`);
            });

            // Clear timeout if request completes
            clearTimeout(timeoutId);

            // Check HTTP status
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json().catch(err => {
                throw new Error(`Invalid JSON response: ${err.message}`);
            });

            if (data.error) {
                throw new Error(data.error.message || 'MCP call failed');
            }

            return this.parseResult(data.result);
        } catch (error) {
            console.error(`❌ MCP Error [${toolName}]:`, error.message);

            // Re-throw with context
            throw new Error(`MCP ${toolName} failed: ${error.message}`);
        } finally {
            // Always clear timeout
            clearTimeout(timeoutId);
        }
    }

    /**
     * Parse MCP result
     */
    parseResult(result) {
        if (!result) return null;

        // Handle text content
        if (result.content && Array.isArray(result.content)) {
            const textContent = result.content.find(c => c.type === 'text');
            if (textContent) {
                return textContent.text;
            }
        }

        // Handle direct data
        if (result.data) {
            return result.data;
        }

        return result;
    }

    /**
     * 🎯 STREAMING SUPPORT - Real-time bot responses with error handling
     */
    async streamingCall(toolName, args, onChunk, timeout = 60000) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(this.serverUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': this.apiKey,
                    'X-Enable-Streaming': 'true',
                    'Accept': 'text/event-stream'
                },
                body: JSON.stringify({
                    jsonrpc: '2.0',
                    method: 'tools/call',
                    params: {
                        name: toolName,
                        arguments: args
                    },
                    id: Date.now()
                }),
                signal: controller.signal
            }).catch(err => {
                if (err.name === 'AbortError') {
                    throw new Error(`Streaming timeout after ${timeout}ms`);
                }
                throw new Error(`Streaming network error: ${err.message}`);
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            try {
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop(); // Keep incomplete line

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.substring(6);
                            if (data === '[DONE]') continue;

                            try {
                                const parsed = JSON.parse(data);
                                if (onChunk) onChunk(parsed);
                            } catch (e) {
                                console.warn('Failed to parse streaming chunk:', e);
                            }
                        }
                    }
                }
            } finally {
                reader.releaseLock();
            }
        } catch (error) {
            console.error(`❌ Streaming Error [${toolName}]:`, error.message);
            throw error;
        } finally {
            clearTimeout(timeoutId);
        }
    }

    /**
     * 🎪 BATCH OPERATIONS - With error handling for each item
     */
    async batchGenerate(items) {
        const results = await Promise.allSettled(
            items.map(item => this.callMCP(item.tool, item.args))
        );

        // Return results with success/failure status
        return results.map((result, index) => ({
            item: items[index],
            success: result.status === 'fulfilled',
            data: result.status === 'fulfilled' ? result.value : null,
            error: result.status === 'rejected' ? result.reason.message : null
        }));
    }
}

// Export for use in Theme Builder
window.MCP = new MCPIntegration();

console.log('🤖 MCP Integration System loaded!');
console.log('Available bots:', Object.keys(window.MCP.bots));
