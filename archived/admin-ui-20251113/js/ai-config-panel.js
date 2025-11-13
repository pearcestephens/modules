/**
 * AI Configuration Panel - Manage and configure AI agents
 * Supports multiple AI providers (OpenAI, Local, Anthropic)
 *
 * @version 1.0.0
 */

class AIConfigPanel {
    constructor(options = {}) {
        this.apiEndpoint = options.apiEndpoint || '/modules/admin-ui/api/ai-config-api.php';
        this.storageKey = options.storageKey || 'admin-ui-ai-config';
        this.selectedAgent = options.selectedAgent || 'local';
        this.agents = {};
        this.autoSave = options.autoSave !== false;

        this.init();
    }

    /**
     * Initialize AI config panel
     */
    async init() {
        try {
            await this.loadAgents();
            console.log('AI Configuration Panel initialized');
        } catch (e) {
            console.error('Failed to initialize AI config panel:', e);
        }
    }

    /**
     * Load available AI agents
     */
    async loadAgents() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=list`);
            const data = await response.json();

            if (data.success) {
                this.agents = data.agents;
                this.loadSelectedAgent();
            } else {
                throw new Error(data.error || 'Failed to load agents');
            }
        } catch (e) {
            console.error('Error loading AI agents:', e);
        }
    }

    /**
     * Get specific agent configuration
     */
    async getAgent(agentId) {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=get&agent=${agentId}`);
            const data = await response.json();

            if (data.success) {
                return data.config;
            } else {
                throw new Error(data.error || 'Failed to get agent');
            }
        } catch (e) {
            console.error(`Error getting agent ${agentId}:`, e);
            return null;
        }
    }

    /**
     * Update agent configuration
     */
    async updateAgent(agentId, settings) {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update',
                    agent: agentId,
                    settings: settings,
                })
            });

            const data = await response.json();

            if (data.success) {
                this.selectedAgent = agentId;
                if (this.autoSave) {
                    this.saveSelectedAgent();
                }

                window.dispatchEvent(new CustomEvent('ai-config-changed', {
                    detail: { agent: agentId, settings: settings }
                }));

                return true;
            } else {
                throw new Error(data.error || 'Failed to update agent');
            }
        } catch (e) {
            console.error('Error updating agent:', e);
            return false;
        }
    }

    /**
     * Test AI agent connection
     */
    async testAgent(agentId = 'all') {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'test',
                    agent: agentId,
                })
            });

            const data = await response.json();
            return data;
        } catch (e) {
            console.error('Error testing agent:', e);
            return { success: false, error: e.message };
        }
    }

    /**
     * Get enabled AI agents
     */
    getEnabledAgents() {
        return Object.entries(this.agents)
            .filter(([_, agent]) => agent.enabled)
            .map(([id, agent]) => ({ id, ...agent }));
    }

    /**
     * Save selected agent to localStorage
     */
    saveSelectedAgent() {
        try {
            localStorage.setItem(this.storageKey, this.selectedAgent);
        } catch (e) {
            console.warn('Failed to save selected agent:', e);
        }
    }

    /**
     * Load selected agent from localStorage
     */
    loadSelectedAgent() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            if (saved && this.agents[saved]) {
                this.selectedAgent = saved;
            }
        } catch (e) {
            console.warn('Failed to load selected agent:', e);
        }
    }

    /**
     * Create AI config panel HTML
     */
    createConfigPanel(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const panel = document.createElement('div');
        panel.className = 'ai-config-panel';
        panel.innerHTML = `
            <div class="ai-config-header">
                <h2>AI Agent Configuration</h2>
                <button class="ai-config-test-all" data-action="test-all">Test All Connections</button>
            </div>

            <div class="ai-config-tabs">
                ${Object.entries(this.agents)
                    .map(([id, agent]) => `
                    <button class="ai-config-tab ${this.selectedAgent === id ? 'active' : ''}"
                            data-agent="${id}"
                            data-enabled="${agent.enabled}">
                        <span class="ai-status-indicator ${agent.enabled ? 'enabled' : 'disabled'}"></span>
                        ${agent.name}
                    </button>
                `)
                    .join('')}
            </div>

            <div class="ai-config-content">
                ${Object.entries(this.agents)
                    .map(([id, agent]) => `
                    <div class="ai-config-agent ${this.selectedAgent === id ? 'active' : ''}"
                         data-agent="${id}">

                        <div class="ai-agent-info">
                            <p class="ai-agent-description">${agent.description || ''}</p>

                            <div class="ai-agent-stats">
                                <div class="stat">
                                    <span class="stat-label">Status:</span>
                                    <span class="stat-value ${agent.status}">${this.formatStatus(agent.status)}</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Type:</span>
                                    <span class="stat-value">${agent.type}</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Cost:</span>
                                    <span class="stat-value">${agent.cost || 'Free'}</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Response Time:</span>
                                    <span class="stat-value">${agent.response_time || 'N/A'}</span>
                                </div>
                            </div>

                            <div class="ai-agent-capabilities">
                                <h4>Capabilities</h4>
                                <ul>
                                    ${(agent.capabilities || [])
                                        .map(cap => `<li>${this.formatCapability(cap)}</li>`)
                                        .join('')}
                                </ul>
                            </div>
                        </div>

                        <div class="ai-agent-settings">
                            <h4>Settings</h4>
                            ${this.generateSettingsForm(id, agent)}
                        </div>

                        <div class="ai-agent-actions">
                            <button class="btn-test" data-agent="${id}">Test Connection</button>
                            <button class="btn-enable-disable" data-agent="${id}">
                                ${agent.enabled ? 'Disable' : 'Enable'}
                            </button>
                            <button class="btn-reset" data-agent="${id}">Reset to Defaults</button>
                        </div>
                    </div>
                `)
                    .join('')}
            </div>

            <div class="ai-config-status">
                <p id="ai-status-message"></p>
            </div>
        `;

        container.appendChild(panel);
        this.attachEventListeners(panel);

        return panel;
    }

    /**
     * Generate settings form for agent
     */
    generateSettingsForm(agentId, agent) {
        const settings = agent.settings || {};
        let form = '<div class="settings-form">';

        Object.entries(settings).forEach(([key, value]) => {
            const label = this.formatLabel(key);
            const inputId = `${agentId}-${key}`;

            if (typeof value === 'boolean') {
                form += `
                    <div class="form-group">
                        <label for="${inputId}">${label}</label>
                        <input type="checkbox" id="${inputId}" data-setting="${key}" ${value ? 'checked' : ''}>
                    </div>
                `;
            } else if (typeof value === 'number') {
                form += `
                    <div class="form-group">
                        <label for="${inputId}">${label}</label>
                        <input type="number" id="${inputId}" data-setting="${key}" value="${value}">
                    </div>
                `;
            } else {
                form += `
                    <div class="form-group">
                        <label for="${inputId}">${label}</label>
                        <input type="text" id="${inputId}" data-setting="${key}" value="${value}">
                    </div>
                `;
            }
        });

        form += '</div>';
        return form;
    }

    /**
     * Attach event listeners to panel
     */
    attachEventListeners(panel) {
        // Tab switching
        panel.querySelectorAll('.ai-config-tab').forEach(tab => {
            tab.addEventListener('click', () => this.switchTab(tab, panel));
        });

        // Test buttons
        panel.querySelectorAll('.btn-test').forEach(btn => {
            btn.addEventListener('click', async () => {
                const agentId = btn.dataset.agent;
                await this.testAgentUI(agentId);
            });
        });

        // Test all
        const testAllBtn = panel.querySelector('.ai-config-test-all');
        if (testAllBtn) {
            testAllBtn.addEventListener('click', async () => {
                await this.testAgentUI('all');
            });
        }

        // Enable/Disable buttons
        panel.querySelectorAll('.btn-enable-disable').forEach(btn => {
            btn.addEventListener('click', async () => {
                const agentId = btn.dataset.agent;
                await this.toggleAgent(agentId);
            });
        });

        // Settings input changes
        panel.querySelectorAll('.settings-form input').forEach(input => {
            input.addEventListener('change', async () => {
                await this.saveAgentSettings(panel);
            });
        });
    }

    /**
     * Switch to different tab
     */
    switchTab(tab, panel) {
        const agentId = tab.dataset.agent;

        // Update active tab
        panel.querySelectorAll('.ai-config-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Update active content
        panel.querySelectorAll('.ai-config-agent').forEach(c => c.classList.remove('active'));
        const content = panel.querySelector(`.ai-config-agent[data-agent="${agentId}"]`);
        if (content) content.classList.add('active');

        this.selectedAgent = agentId;
        if (this.autoSave) {
            this.saveSelectedAgent();
        }
    }

    /**
     * Test agent with UI feedback
     */
    async testAgentUI(agentId) {
        const statusMsg = document.getElementById('ai-status-message');
        if (statusMsg) {
            statusMsg.textContent = `Testing ${agentId}...`;
            statusMsg.className = 'status-testing';
        }

        const result = await this.testAgent(agentId);

        if (result.success) {
            if (statusMsg) {
                statusMsg.textContent = `✓ ${agentId} connection test passed`;
                statusMsg.className = 'status-success';
            }
        } else {
            if (statusMsg) {
                statusMsg.textContent = `✗ ${agentId} connection test failed: ${result.error}`;
                statusMsg.className = 'status-error';
            }
        }
    }

    /**
     * Save agent settings from form
     */
    async saveAgentSettings(panel) {
        const activeAgent = panel.querySelector('.ai-config-agent.active');
        if (!activeAgent) return;

        const agentId = activeAgent.dataset.agent;
        const settings = {};

        activeAgent.querySelectorAll('[data-setting]').forEach(input => {
            const key = input.dataset.setting;
            const value = input.type === 'checkbox' ? input.checked : input.value;
            settings[key] = value;
        });

        await this.updateAgent(agentId, settings);
    }

    /**
     * Toggle agent enabled/disabled
     */
    async toggleAgent(agentId) {
        const agent = this.agents[agentId];
        if (!agent) return;

        agent.enabled = !agent.enabled;

        // Update UI
        const tab = document.querySelector(`[data-agent="${agentId}"]`);
        if (tab) {
            tab.dataset.enabled = agent.enabled;
            const btn = tab.querySelector('.btn-enable-disable');
            if (btn) {
                btn.textContent = agent.enabled ? 'Disable' : 'Enable';
            }
        }

        const statusMsg = document.getElementById('ai-status-message');
        if (statusMsg) {
            statusMsg.textContent = `${agent.name} ${agent.enabled ? 'enabled' : 'disabled'}`;
            statusMsg.className = agent.enabled ? 'status-success' : 'status-info';
        }
    }

    /**
     * Format status for display
     */
    formatStatus(status) {
        return status
            .split('_')
            .map(w => w.charAt(0).toUpperCase() + w.slice(1))
            .join(' ');
    }

    /**
     * Format capability name
     */
    formatCapability(cap) {
        return cap
            .split('_')
            .map(w => w.charAt(0).toUpperCase() + w.slice(1))
            .join(' ');
    }

    /**
     * Format label from key
     */
    formatLabel(key) {
        return key
            .split('_')
            .map((w, i) => i === 0 ? w.charAt(0).toUpperCase() + w.slice(1) : w)
            .join(' ');
    }

    /**
     * Listen for configuration changes
     */
    onConfigChange(callback) {
        window.addEventListener('ai-config-changed', (e) => {
            callback(e.detail);
        });
    }

    /**
     * Get current configuration summary
     */
    getSummary() {
        const enabled = this.getEnabledAgents();
        return {
            selected: this.selectedAgent,
            enabled: enabled.map(a => a.id),
            total: Object.keys(this.agents).length,
        };
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AIConfigPanel;
}

// Auto-initialize if data attribute is present
document.addEventListener('DOMContentLoaded', () => {
    if (document.currentScript && document.currentScript.dataset.autoInit === 'true') {
        window.aiConfigPanel = new AIConfigPanel();
    }
});
