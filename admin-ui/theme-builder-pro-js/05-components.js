/**
 * Theme Builder PRO - UI Components Management
 * Component library functions
 * @version 3.0.0
 */

window.ThemeBuilder.components = {

    // Load and render components list
    loadList: function() {
        window.ThemeBuilder.api.listComponents(function(components) {
            const list = $('#components-list');
            list.empty();

            if (components.length === 0) {
                list.append(`
                    <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                        <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No components yet.<br>Create your first one!</p>
                    </div>
                `);
                return;
            }

            components.forEach(comp => {
                const iconClass = window.ThemeBuilder.components.getIconClass(comp.type);
                list.append(`
                    <div class="component-item" data-id="${comp.id}">
                        <div style="display: flex; align-items: center; flex: 1;" onclick="ThemeBuilder.components.load('${comp.id}')">
                            <div class="component-icon ${comp.type}">
                                <i class="${iconClass}"></i>
                            </div>
                            <div>
                                <div style="font-weight: 500; font-size: 0.9375rem;">${comp.name}</div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">${comp.type}</div>
                            </div>
                        </div>
                        <button class="btn-icon-sm" onclick="event.stopPropagation(); ThemeBuilder.components.delete('${comp.id}')" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                `);
            });
        });
    },

    // Get icon for component type
    getIconClass: function(type) {
        const icons = {
            button: 'fas fa-hand-pointer',
            card: 'fas fa-id-card',
            form: 'fas fa-wpforms',
            navbar: 'fas fa-bars',
            footer: 'fas fa-shoe-prints',
            custom: 'fas fa-cube'
        };
        return icons[type] || 'fas fa-cube';
    },

    // Load component into editor
    load: function(componentId) {
        window.ThemeBuilder.api.loadComponent(componentId, function(component) {
            // Insert component HTML into editor
            const currentHtml = window.ThemeBuilder.state.editors.html.getValue();
            const newHtml = currentHtml + '\n\n<!-- Component: ' + component.name + ' -->\n' + component.html;
            window.ThemeBuilder.state.editors.html.setValue(newHtml);

            // Append component CSS
            if (component.css) {
                const currentCss = window.ThemeBuilder.state.editors.css.getValue();
                const newCss = currentCss + '\n\n/* Component: ' + component.name + ' */\n' + component.css;
                window.ThemeBuilder.state.editors.css.setValue(newCss);
            }

            window.ThemeBuilder.refreshPreview();

            // Show success message
            window.ThemeBuilder.ui.showNotification('Component inserted: ' + component.name, 'success');
        });
    },

    // Delete component
    delete: function(componentId) {
        window.ThemeBuilder.api.deleteComponent(componentId, function() {
            window.ThemeBuilder.components.loadList();
            window.ThemeBuilder.ui.showNotification('Component deleted', 'success');
        });
    },

    // Open new component modal
    showCreateModal: function() {
        $('#component-modal').addClass('show');
        $('#component-form')[0].reset();
    },

    // Save new component
    save: function(formData) {
        const component = {
            name: formData.get('name'),
            type: formData.get('type'),
            html: formData.get('html'),
            css: formData.get('css')
        };

        window.ThemeBuilder.api.saveComponent(component, function() {
            window.ThemeBuilder.ui.closeModal('component-modal');
            window.ThemeBuilder.components.loadList();
            window.ThemeBuilder.ui.showNotification('Component saved!', 'success');
        });
    }
};
