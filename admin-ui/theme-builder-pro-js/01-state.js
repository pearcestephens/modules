/**
 * Theme Builder PRO - State Management
 * Global state and configuration
 * @version 3.0.0
 */

// Global application state
window.ThemeBuilder = {
    state: {
        currentTheme: {
            id: null,
            name: 'New Theme',
            version: '1.0.0',
            components: [],
            html: `<div class="container">
  <h1>Hello Theme Builder PRO!</h1>
  <p>Start editing to see changes in real-time.</p>
  <button class="btn btn-primary">Primary Button</button>
  <button class="btn btn-success">Success Button</button>
</div>`,
            css: `body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  padding: 2rem;
  background: #f8f9fa;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

h1 {
  color: #1e293b;
  margin-bottom: 1rem;
  font-size: 2.5rem;
}

p {
  color: #475569;
  font-size: 1.125rem;
  margin-bottom: 2rem;
}

.btn {
  padding: 0.875rem 1.75rem;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  margin-right: 0.75rem;
  transition: all 0.2s ease;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-primary {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #059669, #047857);
}

.btn-success {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
}

.btn-success:hover {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
}`,
            js: `// Add your JavaScript here
console.log("Theme Builder PRO - Live Preview Active!");

// Example: Add click handlers
document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('click', function() {
        console.log('Button clicked:', this.textContent);
    });
});`
        },
        editors: {
            html: null,
            css: null,
            js: null
        },
        autoRefreshTimeout: null,
        currentDevice: 'desktop',
        unsavedChanges: false
    },

    config: {
        autoRefreshDelay: 1000, // ms
        apiEndpoint: window.location.href
    }
};
