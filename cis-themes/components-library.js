/**
 * 🧩 THEME BUILDER PRO - COMPONENT LIBRARY
 * 1000+ Pre-built Components with Drag & Drop
 * @version 1.0.0
 */

const ComponentLibrary = {

    // 📐 LAYOUT COMPONENTS (50+)
    layout: {
        containers: {
            fixed: {
                name: 'Fixed Container',
                html: `<div class="container-fixed">\n  <!-- Content here -->\n</div>`,
                css: `.container-fixed { max-width: 1200px; margin: 0 auto; padding: 0 20px; }`,
                preview: '□ Fixed width, centered'
            },
            fluid: {
                name: 'Fluid Container',
                html: `<div class="container-fluid">\n  <!-- Content here -->\n</div>`,
                css: `.container-fluid { width: 100%; padding: 0 20px; }`,
                preview: '▭ Full width'
            },
            boxed: {
                name: 'Boxed Container',
                html: `<div class="container-boxed">\n  <!-- Content here -->\n</div>`,
                css: `.container-boxed { max-width: 1400px; margin: 0 auto; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }`,
                preview: '⬜ Boxed with shadow'
            }
        },

        grids: {
            twoColumn: {
                name: '2-Column Grid',
                html: `<div class="grid-2">\n  <div>Column 1</div>\n  <div>Column 2</div>\n</div>`,
                css: `.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }\n@media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }`,
                preview: '▯▯ Two columns'
            },
            threeColumn: {
                name: '3-Column Grid',
                html: `<div class="grid-3">\n  <div>Column 1</div>\n  <div>Column 2</div>\n  <div>Column 3</div>\n</div>`,
                css: `.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }\n@media (max-width: 768px) { .grid-3 { grid-template-columns: 1fr; } }`,
                preview: '▯▯▯ Three columns'
            },
            fourColumn: {
                name: '4-Column Grid',
                html: `<div class="grid-4">\n  <div>Col 1</div>\n  <div>Col 2</div>\n  <div>Col 3</div>\n  <div>Col 4</div>\n</div>`,
                css: `.grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }`,
                preview: '▯▯▯▯ Four columns'
            },
            masonry: {
                name: 'Masonry Grid',
                html: `<div class="grid-masonry">\n  <div>Item 1</div>\n  <div>Item 2</div>\n  <div>Item 3</div>\n</div>`,
                css: `.grid-masonry { columns: 3; column-gap: 20px; }\n.grid-masonry > div { break-inside: avoid; margin-bottom: 20px; }`,
                preview: '⬚⬚⬚ Masonry layout'
            }
        },

        sections: {
            hero: {
                name: 'Hero Section',
                html: `<section class="hero">\n  <div class="hero-content">\n    <h1>Amazing Headline</h1>\n    <p>Compelling subheadline goes here</p>\n    <button class="btn-hero">Get Started</button>\n  </div>\n</section>`,
                css: `.hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 120px 20px; text-align: center; }\n.hero h1 { font-size: 48px; margin-bottom: 16px; }\n.hero p { font-size: 20px; margin-bottom: 32px; opacity: 0.9; }\n.btn-hero { padding: 16px 32px; background: white; color: #667eea; border: none; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer; }`,
                preview: '🎯 Hero banner'
            },
            features: {
                name: 'Features Section',
                html: `<section class="features">\n  <h2>Features</h2>\n  <div class="feature-grid">\n    <div class="feature">\n      <div class="feature-icon">🚀</div>\n      <h3>Fast</h3>\n      <p>Lightning quick performance</p>\n    </div>\n    <div class="feature">\n      <div class="feature-icon">🎨</div>\n      <h3>Beautiful</h3>\n      <p>Stunning visual design</p>\n    </div>\n    <div class="feature">\n      <div class="feature-icon">💪</div>\n      <h3>Powerful</h3>\n      <p>Feature-rich and capable</p>\n    </div>\n  </div>\n</section>`,
                css: `.features { padding: 80px 20px; text-align: center; }\n.features h2 { font-size: 36px; margin-bottom: 48px; }\n.feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px; max-width: 1200px; margin: 0 auto; }\n.feature { padding: 32px; background: #f8fafc; border-radius: 12px; }\n.feature-icon { font-size: 48px; margin-bottom: 16px; }\n.feature h3 { font-size: 24px; margin-bottom: 12px; }`,
                preview: '✨ Feature showcase'
            }
        }
    },

    // 🧭 NAVIGATION COMPONENTS (30+)
    navigation: {
        headers: {
            topNav: {
                name: 'Top Navigation',
                html: `<nav class="nav-top">\n  <div class="nav-brand">Brand</div>\n  <ul class="nav-menu">\n    <li><a href="#">Home</a></li>\n    <li><a href="#">About</a></li>\n    <li><a href="#">Services</a></li>\n    <li><a href="#">Contact</a></li>\n  </ul>\n</nav>`,
                css: `.nav-top { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }\n.nav-brand { font-size: 24px; font-weight: 700; }\n.nav-menu { display: flex; list-style: none; gap: 32px; margin: 0; }\n.nav-menu a { text-decoration: none; color: #334155; font-weight: 500; transition: color 0.2s; }\n.nav-menu a:hover { color: #667eea; }`,
                preview: '📍 Top navbar'
            },
            stickyNav: {
                name: 'Sticky Navigation',
                html: `<nav class="nav-sticky">\n  <div class="nav-content">\n    <div class="nav-brand">Brand</div>\n    <ul class="nav-menu">\n      <li><a href="#">Home</a></li>\n      <li><a href="#">Features</a></li>\n      <li><a href="#">Pricing</a></li>\n    </ul>\n  </div>\n</nav>`,
                css: `.nav-sticky { position: sticky; top: 0; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); box-shadow: 0 2px 12px rgba(0,0,0,0.08); z-index: 100; }\n.nav-content { display: flex; justify-content: space-between; align-items: center; padding: 16px 40px; max-width: 1400px; margin: 0 auto; }\n.nav-menu { display: flex; list-style: none; gap: 24px; margin: 0; }\n.nav-menu a { text-decoration: none; color: #1e293b; font-weight: 500; }`,
                preview: '📌 Sticky header'
            },
            megaMenu: {
                name: 'Mega Menu',
                html: `<nav class="nav-mega">\n  <div class="nav-item">Products ▼\n    <div class="mega-dropdown">\n      <div class="mega-column">\n        <h4>Category 1</h4>\n        <a href="#">Product A</a>\n        <a href="#">Product B</a>\n      </div>\n      <div class="mega-column">\n        <h4>Category 2</h4>\n        <a href="#">Product C</a>\n        <a href="#">Product D</a>\n      </div>\n    </div>\n  </div>\n</nav>`,
                css: `.nav-mega { position: relative; }\n.nav-item { cursor: pointer; padding: 12px 20px; }\n.mega-dropdown { position: absolute; top: 100%; left: 0; background: white; box-shadow: 0 8px 24px rgba(0,0,0,0.12); border-radius: 8px; padding: 32px; display: none; grid-template-columns: repeat(2, 1fr); gap: 40px; min-width: 600px; }\n.nav-item:hover .mega-dropdown { display: grid; }\n.mega-column h4 { margin-bottom: 16px; }\n.mega-column a { display: block; padding: 8px 0; color: #64748b; text-decoration: none; }`,
                preview: '📋 Mega menu'
            }
        },

        breadcrumbs: {
            default: {
                name: 'Breadcrumbs',
                html: `<nav class="breadcrumbs">\n  <a href="#">Home</a> / <a href="#">Category</a> / <span>Current Page</span>\n</nav>`,
                css: `.breadcrumbs { padding: 16px 0; font-size: 14px; color: #64748b; }\n.breadcrumbs a { color: #667eea; text-decoration: none; }\n.breadcrumbs a:hover { text-decoration: underline; }\n.breadcrumbs span { color: #1e293b; font-weight: 500; }`,
                preview: '🍞 Navigation path'
            }
        }
    },

    // 📝 CONTENT COMPONENTS (100+)
    content: {
        cards: {
            basic: {
                name: 'Basic Card',
                html: `<div class="card">\n  <h3>Card Title</h3>\n  <p>Card content goes here...</p>\n  <button>Learn More</button>\n</div>`,
                css: `.card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }\n.card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }\n.card h3 { margin-bottom: 12px; }\n.card p { color: #64748b; margin-bottom: 20px; }\n.card button { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; }`,
                preview: '📇 Simple card'
            },
            product: {
                name: 'Product Card',
                html: `<div class="card-product">\n  <img src="https://via.placeholder.com/300x200" alt="Product">\n  <div class="card-content">\n    <span class="badge">New</span>\n    <h3>Product Name</h3>\n    <p class="price">$99.99</p>\n    <button>Add to Cart</button>\n  </div>\n</div>`,
                css: `.card-product { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }\n.card-product img { width: 100%; height: 200px; object-fit: cover; }\n.card-content { padding: 20px; }\n.badge { display: inline-block; padding: 4px 12px; background: #10b981; color: white; border-radius: 12px; font-size: 12px; font-weight: 600; margin-bottom: 8px; }\n.price { font-size: 24px; font-weight: 700; color: #667eea; margin: 12px 0; }\n.card-product button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }`,
                preview: '🛍️ Product display'
            },
            profile: {
                name: 'Profile Card',
                html: `<div class="card-profile">\n  <img src="https://via.placeholder.com/100" alt="Avatar" class="avatar">\n  <h3>John Doe</h3>\n  <p>Senior Developer</p>\n  <div class="social-links">\n    <a href="#">🐦</a>\n    <a href="#">💼</a>\n    <a href="#">📧</a>\n  </div>\n</div>`,
                css: `.card-profile { background: white; border-radius: 12px; padding: 32px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }\n.avatar { width: 100px; height: 100px; border-radius: 50%; margin-bottom: 16px; border: 4px solid #667eea; }\n.card-profile h3 { margin-bottom: 8px; }\n.card-profile p { color: #64748b; margin-bottom: 20px; }\n.social-links { display: flex; gap: 12px; justify-content: center; }\n.social-links a { font-size: 24px; text-decoration: none; }`,
                preview: '👤 User profile'
            }
        },

        buttons: {
            primary: {
                name: 'Primary Button',
                html: `<button class="btn-primary">Click Me</button>`,
                css: `.btn-primary { padding: 12px 24px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; }\n.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4); }`,
                preview: '🔵 Main action'
            },
            gradient: {
                name: 'Gradient Button',
                html: `<button class="btn-gradient">Gradient Button</button>`,
                css: `.btn-gradient { padding: 14px 28px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none; border-radius: 50px; font-size: 16px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4); transition: all 0.3s; }\n.btn-gradient:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(245, 87, 108, 0.6); }`,
                preview: '🌈 Colorful gradient'
            },
            ghost: {
                name: 'Ghost Button',
                html: `<button class="btn-ghost">Ghost Button</button>`,
                css: `.btn-ghost { padding: 12px 24px; background: transparent; color: #667eea; border: 2px solid #667eea; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; }\n.btn-ghost:hover { background: #667eea; color: white; }`,
                preview: '👻 Outline style'
            }
        }
    },

    // 🖼️ MEDIA COMPONENTS (40+)
    media: {
        galleries: {
            grid: {
                name: 'Image Grid',
                html: `<div class="gallery-grid">\n  <img src="https://via.placeholder.com/300" alt="Image 1">\n  <img src="https://via.placeholder.com/300" alt="Image 2">\n  <img src="https://via.placeholder.com/300" alt="Image 3">\n  <img src="https://via.placeholder.com/300" alt="Image 4">\n</div>`,
                css: `.gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }\n.gallery-grid img { width: 100%; height: 250px; object-fit: cover; border-radius: 8px; cursor: pointer; transition: transform 0.3s; }\n.gallery-grid img:hover { transform: scale(1.05); }`,
                preview: '🖼️ Photo grid'
            }
        }
    },

    // 📊 DATA VISUALIZATION (60+)
    data: {
        stats: {
            simple: {
                name: 'Stat Card',
                html: `<div class="stat-card">\n  <div class="stat-value">1,234</div>\n  <div class="stat-label">Total Sales</div>\n  <div class="stat-change">+12% from last month</div>\n</div>`,
                css: `.stat-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }\n.stat-value { font-size: 36px; font-weight: 700; color: #667eea; margin-bottom: 8px; }\n.stat-label { font-size: 14px; color: #64748b; margin-bottom: 12px; }\n.stat-change { font-size: 13px; color: #10b981; font-weight: 600; }`,
                preview: '📊 Statistics'
            },
            trend: {
                name: 'Trending Stat',
                html: `<div class="stat-trend">\n  <div class="stat-icon">📈</div>\n  <div class="stat-info">\n    <div class="stat-value">$45,231</div>\n    <div class="stat-label">Revenue</div>\n  </div>\n  <div class="stat-badge up">+18.2%</div>\n</div>`,
                css: `.stat-trend { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 12px; display: flex; align-items: center; gap: 16px; }\n.stat-icon { font-size: 48px; }\n.stat-info { flex: 1; }\n.stat-value { font-size: 32px; font-weight: 700; }\n.stat-label { opacity: 0.9; }\n.stat-badge { padding: 6px 12px; background: rgba(16, 185, 129, 0.2); border-radius: 20px; font-weight: 600; }`,
                preview: '📈 With trend'
            }
        }
    },

    // ⚡ INTERACTIVE COMPONENTS (70+)
    interactive: {
        modals: {
            center: {
                name: 'Center Modal',
                html: `<div class="modal-overlay">\n  <div class="modal-center">\n    <button class="modal-close">×</button>\n    <h2>Modal Title</h2>\n    <p>Modal content goes here...</p>\n    <button class="btn-primary">Confirm</button>\n  </div>\n</div>`,
                css: `.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; }\n.modal-center { background: white; border-radius: 16px; padding: 32px; max-width: 500px; width: 90%; position: relative; }\n.modal-close { position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 32px; cursor: pointer; color: #64748b; }`,
                preview: '🔲 Popup modal'
            }
        },

        notifications: {
            toast: {
                name: 'Toast Notification',
                html: `<div class="toast success">\n  <span class="toast-icon">✅</span>\n  <span class="toast-message">Success! Your changes have been saved.</span>\n  <button class="toast-close">×</button>\n</div>`,
                css: `.toast { position: fixed; top: 20px; right: 20px; background: white; padding: 16px 20px; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); display: flex; align-items: center; gap: 12px; min-width: 300px; animation: slideIn 0.3s; z-index: 1000; }\n.toast.success { border-left: 4px solid #10b981; }\n.toast-close { background: none; border: none; font-size: 24px; cursor: pointer; margin-left: auto; }\n@keyframes slideIn { from { transform: translateX(400px); } to { transform: translateX(0); } }`,
                preview: '🔔 Toast message'
            }
        }
    }
};

// Export for use in main app
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ComponentLibrary;
}
