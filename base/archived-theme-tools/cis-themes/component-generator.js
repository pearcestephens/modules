/**
 * 🎯 COMPONENT GENERATOR PRO
 * AI-Powered Component Generation System
 * @version 1.0.0
 */

class ComponentGenerator {
    constructor(mcpIntegration) {
        this.mcp = mcpIntegration;
        this.templates = this.initializeTemplates();
        this.colorSchemes = this.initializeColorSchemes();
    }

    /**
     * 🎨 Initialize color schemes
     */
    initializeColorSchemes() {
        return {
            electric: {
                primary: '#667eea',
                secondary: '#764ba2',
                accent: '#f093fb',
                success: '#4ade80',
                warning: '#fbbf24',
                danger: '#f87171',
                dark: '#0f172a',
                light: '#f8fafc'
            },
            ocean: {
                primary: '#0ea5e9',
                secondary: '#06b6d4',
                accent: '#22d3ee',
                success: '#10b981',
                warning: '#f59e0b',
                danger: '#ef4444',
                dark: '#0c4a6e',
                light: '#f0f9ff'
            },
            sunset: {
                primary: '#f97316',
                secondary: '#ea580c',
                accent: '#fb923c',
                success: '#22c55e',
                warning: '#eab308',
                danger: '#dc2626',
                dark: '#431407',
                light: '#fff7ed'
            },
            forest: {
                primary: '#10b981',
                secondary: '#059669',
                accent: '#34d399',
                success: '#22c55e',
                warning: '#f59e0b',
                danger: '#ef4444',
                dark: '#064e3b',
                light: '#f0fdf4'
            },
            midnight: {
                primary: '#8b5cf6',
                secondary: '#7c3aed',
                accent: '#a78bfa',
                success: '#10b981',
                warning: '#f59e0b',
                danger: '#ef4444',
                dark: '#1e1b4b',
                light: '#faf5ff'
            }
        };
    }

    /**
     * 📦 Initialize component templates
     */
    initializeTemplates() {
        return {
            hero: {
                layouts: ['centered', 'split', 'minimal', 'video-bg', 'gradient-bg'],
                elements: ['title', 'subtitle', 'cta-buttons', 'image', 'video'],
                animations: ['fadeIn', 'slideUp', 'zoomIn', 'parallax']
            },
            card: {
                layouts: ['basic', 'hover-lift', 'flip', 'overlay', 'glass'],
                elements: ['image', 'icon', 'title', 'description', 'cta', 'badge'],
                animations: ['hover-grow', 'hover-shadow', 'tilt', 'shine']
            },
            navbar: {
                layouts: ['top', 'sticky', 'mega-menu', 'sidebar', 'minimal'],
                elements: ['logo', 'menu', 'search', 'cta', 'user-menu'],
                animations: ['slide-down', 'fade-in', 'backdrop-blur']
            },
            footer: {
                layouts: ['multi-column', 'centered', 'minimal', 'mega'],
                elements: ['links', 'social', 'newsletter', 'copyright', 'logo'],
                animations: ['fade-in', 'stagger']
            },
            form: {
                layouts: ['inline', 'stacked', 'floating-labels', 'material'],
                elements: ['text', 'email', 'select', 'checkbox', 'radio', 'submit'],
                animations: ['focus-highlight', 'shake-error', 'success-bounce']
            },
            gallery: {
                layouts: ['grid', 'masonry', 'carousel', 'lightbox', 'infinite-scroll'],
                elements: ['image', 'caption', 'overlay', 'controls', 'thumbnails'],
                animations: ['zoom-hover', 'slide', 'fade', 'ken-burns']
            }
        };
    }

    /**
     * 🎨 Generate component by description
     */
    async generateByDescription(description, options = {}) {
        const {
            colorScheme = 'electric',
            responsive = true,
            animated = true,
            framework = 'vanilla'
        } = options;

        const colors = this.colorSchemes[colorScheme];

        // Use MCP AI to generate component
        const prompt = `Create a modern web component:
Description: ${description}
Colors: ${JSON.stringify(colors)}
Responsive: ${responsive}
Animated: ${animated}
Framework: ${framework}

Generate complete HTML, CSS, and optionally JS.
Use modern CSS (Grid, Flexbox, CSS Variables).
Include smooth animations and transitions.
Make it production-ready and accessible.`;

        try {
            const result = await this.mcp.buildComponent(description, colorScheme);
            return this.parseGeneratedComponent(result);
        } catch (error) {
            console.error('Generation error:', error);
            return this.generateFallback(description, colors);
        }
    }

    /**
     * 🏗️ Generate component from template
     */
    generateFromTemplate(type, layout, elements, options = {}) {
        const {
            colorScheme = 'electric',
            size = 'medium',
            variant = 'default'
        } = options;

        const colors = this.colorSchemes[colorScheme];
        const template = this.templates[type];

        if (!template) {
            throw new Error(`Template type "${type}" not found`);
        }

        const generators = {
            hero: () => this.generateHero(layout, elements, colors, options),
            card: () => this.generateCard(layout, elements, colors, options),
            navbar: () => this.generateNavbar(layout, elements, colors, options),
            footer: () => this.generateFooter(layout, elements, colors, options),
            form: () => this.generateForm(layout, elements, colors, options),
            gallery: () => this.generateGallery(layout, elements, colors, options)
        };

        return generators[type] ? generators[type]() : this.generateGeneric(type, colors);
    }

    /**
     * 🦸 Generate Hero Section
     */
    generateHero(layout, elements, colors, options) {
        const html = `<section class="hero-section hero-${layout}">
  <div class="hero-content">
    ${elements.includes('title') ? '<h1 class="hero-title">Transform Your Business</h1>' : ''}
    ${elements.includes('subtitle') ? '<p class="hero-subtitle">Innovative solutions for modern challenges</p>' : ''}
    ${elements.includes('cta-buttons') ? `
      <div class="hero-ctas">
        <button class="btn-primary">Get Started</button>
        <button class="btn-secondary">Learn More</button>
      </div>
    ` : ''}
  </div>
  ${elements.includes('image') ? '<div class="hero-image"><img src="https://via.placeholder.com/800x600" alt="Hero"></div>' : ''}
</section>`;

        const css = `/* Hero Section - ${layout} */
.hero-section {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  background: linear-gradient(135deg, ${colors.primary} 0%, ${colors.secondary} 100%);
  color: white;
  position: relative;
  overflow: hidden;
}

.hero-content {
  max-width: 800px;
  text-align: center;
  z-index: 10;
}

.hero-title {
  font-size: clamp(2.5rem, 5vw, 4.5rem);
  font-weight: 800;
  margin-bottom: 24px;
  line-height: 1.2;
  animation: fadeInUp 0.8s ease-out;
}

.hero-subtitle {
  font-size: clamp(1.1rem, 2vw, 1.5rem);
  margin-bottom: 40px;
  opacity: 0.95;
  animation: fadeInUp 0.8s ease-out 0.2s both;
}

.hero-ctas {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
  animation: fadeInUp 0.8s ease-out 0.4s both;
}

.btn-primary, .btn-secondary {
  padding: 16px 40px;
  font-size: 1.1rem;
  font-weight: 600;
  border: none;
  border-radius: 50px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-primary {
  background: white;
  color: ${colors.primary};
}

.btn-secondary {
  background: transparent;
  color: white;
  border: 2px solid white;
}

.btn-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 30px rgba(0,0,0,0.3);
}

.btn-secondary:hover {
  background: white;
  color: ${colors.primary};
  transform: translateY(-3px);
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(40px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .hero-section {
    min-height: 80vh;
  }
  .hero-ctas {
    flex-direction: column;
    align-items: center;
  }
  .btn-primary, .btn-secondary {
    width: 100%;
    max-width: 300px;
  }
}`;

        return { html, css, js: '' };
    }

    /**
     * 🃏 Generate Card Component
     */
    generateCard(layout, elements, colors, options) {
        const html = `<div class="card card-${layout}">
  ${elements.includes('image') ? '<img src="https://via.placeholder.com/400x250" alt="Card" class="card-image">' : ''}
  <div class="card-body">
    ${elements.includes('badge') ? '<span class="card-badge">New</span>' : ''}
    ${elements.includes('title') ? '<h3 class="card-title">Card Title</h3>' : ''}
    ${elements.includes('description') ? '<p class="card-description">This is a description of the card content.</p>' : ''}
    ${elements.includes('cta') ? '<button class="card-btn">Learn More</button>' : ''}
  </div>
</div>`;

        const css = `/* Card Component - ${layout} */
.card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.card:hover {
  transform: translateY(-12px) scale(1.02);
  box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.card-image {
  width: 100%;
  height: 250px;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.card:hover .card-image {
  transform: scale(1.1);
}

.card-body {
  padding: 24px;
  position: relative;
}

.card-badge {
  position: absolute;
  top: -12px;
  right: 24px;
  background: ${colors.primary};
  color: white;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 600;
}

.card-title {
  font-size: 1.5rem;
  margin-bottom: 12px;
  color: #1e293b;
}

.card-description {
  color: #64748b;
  line-height: 1.6;
  margin-bottom: 20px;
}

.card-btn {
  width: 100%;
  padding: 12px 24px;
  background: ${colors.primary};
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.card-btn:hover {
  background: ${colors.secondary};
  transform: translateY(-2px);
}`;

        return { html, css, js: '' };
    }

    /**
     * 🧭 Generate Navbar
     */
    generateNavbar(layout, elements, colors, options) {
        const html = `<nav class="navbar navbar-${layout}">
  <div class="navbar-container">
    ${elements.includes('logo') ? '<div class="navbar-logo">LOGO</div>' : ''}
    ${elements.includes('menu') ? `
      <ul class="navbar-menu">
        <li><a href="#home">Home</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#contact">Contact</a></li>
      </ul>
    ` : ''}
    ${elements.includes('cta') ? '<button class="navbar-cta">Get Started</button>' : ''}
  </div>
</nav>`;

        const css = `/* Navbar - ${layout} */
.navbar {
  position: ${layout === 'sticky' ? 'sticky' : 'relative'};
  top: 0;
  background: white;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  z-index: 1000;
  transition: all 0.3s;
}

.navbar-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px 40px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.navbar-logo {
  font-size: 1.5rem;
  font-weight: 700;
  background: linear-gradient(135deg, ${colors.primary}, ${colors.secondary});
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.navbar-menu {
  display: flex;
  list-style: none;
  gap: 40px;
  margin: 0;
  padding: 0;
}

.navbar-menu a {
  text-decoration: none;
  color: #334155;
  font-weight: 500;
  position: relative;
  transition: color 0.3s;
}

.navbar-menu a::after {
  content: '';
  position: absolute;
  bottom: -4px;
  left: 0;
  right: 0;
  height: 2px;
  background: ${colors.primary};
  transform: scaleX(0);
  transition: transform 0.3s;
}

.navbar-menu a:hover {
  color: ${colors.primary};
}

.navbar-menu a:hover::after {
  transform: scaleX(1);
}

.navbar-cta {
  padding: 12px 28px;
  background: ${colors.primary};
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.navbar-cta:hover {
  background: ${colors.secondary};
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
}

@media (max-width: 768px) {
  .navbar-menu {
    display: none;
  }
}`;

        return { html, css, js: '' };
    }

    /**
     * 📄 Generate Footer
     */
    generateFooter(layout, elements, colors, options) {
        const html = `<footer class="footer footer-${layout}">
  <div class="footer-container">
    ${elements.includes('logo') ? '<div class="footer-logo">LOGO</div>' : ''}
    ${elements.includes('links') ? `
      <div class="footer-links">
        <div class="footer-column">
          <h4>Product</h4>
          <a href="#">Features</a>
          <a href="#">Pricing</a>
          <a href="#">FAQ</a>
        </div>
        <div class="footer-column">
          <h4>Company</h4>
          <a href="#">About</a>
          <a href="#">Blog</a>
          <a href="#">Careers</a>
        </div>
        <div class="footer-column">
          <h4>Support</h4>
          <a href="#">Help Center</a>
          <a href="#">Contact</a>
          <a href="#">Status</a>
        </div>
      </div>
    ` : ''}
    ${elements.includes('social') ? `
      <div class="footer-social">
        <a href="#">Twitter</a>
        <a href="#">GitHub</a>
        <a href="#">LinkedIn</a>
      </div>
    ` : ''}
    ${elements.includes('copyright') ? '<div class="footer-copyright">© 2025 Company. All rights reserved.</div>' : ''}
  </div>
</footer>`;

        const css = `/* Footer - ${layout} */
.footer {
  background: #0f172a;
  color: white;
  padding: 60px 20px 20px;
}

.footer-container {
  max-width: 1400px;
  margin: 0 auto;
}

.footer-logo {
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 40px;
  color: ${colors.primary};
}

.footer-links {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 40px;
  margin-bottom: 40px;
}

.footer-column h4 {
  margin-bottom: 16px;
  color: ${colors.primary};
}

.footer-column a {
  display: block;
  color: #94a3b8;
  text-decoration: none;
  margin-bottom: 12px;
  transition: color 0.2s;
}

.footer-column a:hover {
  color: white;
}

.footer-social {
  display: flex;
  gap: 24px;
  margin-bottom: 40px;
  padding-top: 40px;
  border-top: 1px solid #334155;
}

.footer-social a {
  color: #94a3b8;
  text-decoration: none;
  transition: color 0.2s;
}

.footer-social a:hover {
  color: ${colors.primary};
}

.footer-copyright {
  text-align: center;
  color: #64748b;
  padding-top: 20px;
  border-top: 1px solid #334155;
}`;

        return { html, css, js: '' };
    }

    /**
     * 📝 Generate Form
     */
    generateForm(layout, elements, colors, options) {
        const html = `<form class="form form-${layout}">
  ${elements.includes('text') ? '<input type="text" placeholder="Name" class="form-input">' : ''}
  ${elements.includes('email') ? '<input type="email" placeholder="Email" class="form-input">' : ''}
  ${elements.includes('select') ? `
    <select class="form-input">
      <option>Select an option</option>
      <option>Option 1</option>
      <option>Option 2</option>
    </select>
  ` : ''}
  ${elements.includes('submit') ? '<button type="submit" class="form-submit">Submit</button>' : ''}
</form>`;

        const css = `/* Form - ${layout} */
.form {
  max-width: 500px;
  margin: 0 auto;
  padding: 40px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.form-input {
  width: 100%;
  padding: 14px 20px;
  margin-bottom: 20px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 1rem;
  transition: all 0.3s;
}

.form-input:focus {
  outline: none;
  border-color: ${colors.primary};
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-submit {
  width: 100%;
  padding: 16px;
  background: ${colors.primary};
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.form-submit:hover {
  background: ${colors.secondary};
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}`;

        return { html, css, js: '' };
    }

    /**
     * 🖼️ Generate Gallery
     */
    generateGallery(layout, elements, colors, options) {
        const html = `<div class="gallery gallery-${layout}">
  <div class="gallery-item">
    <img src="https://via.placeholder.com/400x300" alt="Gallery 1">
    ${elements.includes('caption') ? '<div class="gallery-caption">Image 1</div>' : ''}
  </div>
  <div class="gallery-item">
    <img src="https://via.placeholder.com/400x300" alt="Gallery 2">
    ${elements.includes('caption') ? '<div class="gallery-caption">Image 2</div>' : ''}
  </div>
  <div class="gallery-item">
    <img src="https://via.placeholder.com/400x300" alt="Gallery 3">
    ${elements.includes('caption') ? '<div class="gallery-caption">Image 3</div>' : ''}
  </div>
</div>`;

        const css = `/* Gallery - ${layout} */
.gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 24px;
  padding: 20px;
}

.gallery-item {
  position: relative;
  overflow: hidden;
  border-radius: 12px;
  cursor: pointer;
}

.gallery-item img {
  width: 100%;
  height: 300px;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.gallery-item:hover img {
  transform: scale(1.1);
}

.gallery-caption {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 20px;
  background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
  color: white;
  transform: translateY(100%);
  transition: transform 0.3s ease;
}

.gallery-item:hover .gallery-caption {
  transform: translateY(0);
}`;

        return { html, css, js: '' };
    }

    /**
     * 🔍 Parse generated component
     */
    parseGeneratedComponent(result) {
        // Extract HTML, CSS, JS from AI response
        const htmlMatch = result.match(/```html\n([\s\S]*?)\n```/);
        const cssMatch = result.match(/```css\n([\s\S]*?)\n```/);
        const jsMatch = result.match(/```javascript\n([\s\S]*?)\n```/);

        return {
            html: htmlMatch ? htmlMatch[1] : '',
            css: cssMatch ? cssMatch[1] : '',
            js: jsMatch ? jsMatch[1] : ''
        };
    }

    /**
     * 🛡️ Generate fallback component
     */
    generateFallback(description, colors) {
        return {
            html: `<div class="component-fallback">\n  <h3>${description}</h3>\n  <p>Component generated</p>\n</div>`,
            css: `.component-fallback {\n  padding: 40px;\n  background: ${colors.primary};\n  color: white;\n  border-radius: 12px;\n}`,
            js: ''
        };
    }

    /**
     * 🎨 Apply color scheme to component
     */
    applyColorScheme(component, schemeName) {
        const colors = this.colorSchemes[schemeName];
        if (!colors) return component;

        let css = component.css;

        // Replace color variables
        Object.keys(colors).forEach(key => {
            const regex = new RegExp(`var\\(--${key}\\)`, 'g');
            css = css.replace(regex, colors[key]);
        });

        return { ...component, css };
    }
}

// Export
window.ComponentGenerator = ComponentGenerator;
console.log('🎯 Component Generator loaded!');
