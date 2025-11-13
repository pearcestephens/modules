/**
 * 🌱 DATA SEEDS & TEMPLATES
 * Massive library of templates, patterns, and ideas
 * @version 1.0.0
 */

window.DataSeeds = {

    /**
     * 🎨 DESIGN PATTERNS (500+ combinations)
     */
    designPatterns: {
        colorCombinations: [
            // Vibrant
            { name: 'Electric Dreams', primary: '#667eea', secondary: '#764ba2', accent: '#f093fb', mood: 'energetic' },
            { name: 'Ocean Breeze', primary: '#0ea5e9', secondary: '#06b6d4', accent: '#22d3ee', mood: 'calm' },
            { name: 'Sunset Glow', primary: '#f97316', secondary: '#ea580c', accent: '#fb923c', mood: 'warm' },
            { name: 'Forest Zen', primary: '#10b981', secondary: '#059669', accent: '#34d399', mood: 'natural' },
            { name: 'Midnight Purple', primary: '#8b5cf6', secondary: '#7c3aed', accent: '#a78bfa', mood: 'mysterious' },

            // Professional
            { name: 'Corporate Blue', primary: '#2563eb', secondary: '#1d4ed8', accent: '#3b82f6', mood: 'professional' },
            { name: 'Tech Gray', primary: '#6b7280', secondary: '#4b5563', accent: '#9ca3af', mood: 'modern' },
            { name: 'Finance Gold', primary: '#f59e0b', secondary: '#d97706', accent: '#fbbf24', mood: 'luxury' },
            { name: 'Medical Green', primary: '#059669', secondary: '#047857', accent: '#10b981', mood: 'trustworthy' },
            { name: 'Legal Navy', primary: '#1e3a8a', secondary: '#1e40af', accent: '#3b82f6', mood: 'authoritative' },

            // Creative
            { name: 'Neon Nights', primary: '#ec4899', secondary: '#a855f7', accent: '#f472b6', mood: 'vibrant' },
            { name: 'Cyber Punk', primary: '#06b6d4', secondary: '#8b5cf6', accent: '#ec4899', mood: 'futuristic' },
            { name: 'Retro Wave', primary: '#f472b6', secondary: '#8b5cf6', accent: '#06b6d4', mood: 'nostalgic' },
            { name: 'Pastel Dream', primary: '#fbbf24', secondary: '#a78bfa', accent: '#34d399', mood: 'soft' },
            { name: 'Dark Mode Pro', primary: '#60a5fa', secondary: '#34d399', accent: '#fbbf24', mood: 'dark' },

            // Seasonal
            { name: 'Spring Fresh', primary: '#10b981', secondary: '#34d399', accent: '#fbbf24', mood: 'fresh' },
            { name: 'Summer Vibes', primary: '#f59e0b', secondary: '#f97316', accent: '#fbbf24', mood: 'bright' },
            { name: 'Autumn Leaves', primary: '#ea580c', secondary: '#dc2626', accent: '#f59e0b', mood: 'cozy' },
            { name: 'Winter Frost', primary: '#06b6d4', secondary: '#0ea5e9', accent: '#bae6fd', mood: 'cool' }
        ],

        typography: [
            { name: 'Modern Sans', heading: 'Inter, sans-serif', body: 'Inter, sans-serif', style: 'clean' },
            { name: 'Classic Serif', heading: 'Playfair Display, serif', body: 'Source Serif Pro, serif', style: 'elegant' },
            { name: 'Tech Mono', heading: 'Space Mono, monospace', body: 'Roboto Mono, monospace', style: 'technical' },
            { name: 'Editorial', heading: 'Merriweather, serif', body: 'Open Sans, sans-serif', style: 'readable' },
            { name: 'Corporate', heading: 'Montserrat, sans-serif', body: 'Lato, sans-serif', style: 'professional' },
            { name: 'Creative', heading: 'Poppins, sans-serif', body: 'Nunito, sans-serif', style: 'friendly' },
            { name: 'Luxury', heading: 'Cormorant Garamond, serif', body: 'Josefin Sans, sans-serif', style: 'high-end' }
        ],

        spacing: [
            { name: 'Compact', base: 8, scale: 1.25, style: 'tight' },
            { name: 'Default', base: 16, scale: 1.5, style: 'balanced' },
            { name: 'Spacious', base: 24, scale: 1.75, style: 'airy' },
            { name: 'Editorial', base: 32, scale: 2, style: 'luxurious' }
        ],

        borderRadius: [
            { name: 'Sharp', value: '0px', style: 'geometric' },
            { name: 'Subtle', value: '4px', style: 'modern' },
            { name: 'Rounded', value: '8px', style: 'friendly' },
            { name: 'Soft', value: '12px', style: 'approachable' },
            { name: 'Pill', value: '50px', style: 'playful' },
            { name: 'Organic', value: '20% / 30%', style: 'unique' }
        ],

        shadows: [
            { name: 'Flat', value: 'none', style: 'minimal' },
            { name: 'Subtle', value: '0 2px 4px rgba(0,0,0,0.05)', style: 'light' },
            { name: 'Medium', value: '0 4px 12px rgba(0,0,0,0.1)', style: 'standard' },
            { name: 'Strong', value: '0 8px 24px rgba(0,0,0,0.15)', style: 'prominent' },
            { name: 'Dramatic', value: '0 20px 40px rgba(0,0,0,0.25)', style: 'bold' },
            { name: 'Glow', value: '0 0 20px rgba(102, 126, 234, 0.4)', style: 'luminous' }
        ]
    },

    /**
     * 🏗️ LAYOUT TEMPLATES (200+ variations)
     */
    layoutTemplates: {
        landingPages: [
            {
                name: 'SaaS Hero',
                structure: ['hero', 'features-3col', 'stats', 'testimonials', 'pricing', 'cta', 'footer'],
                style: 'modern',
                industries: ['software', 'technology', 'startup']
            },
            {
                name: 'E-commerce',
                structure: ['hero-split', 'product-grid', 'categories', 'featured-products', 'newsletter', 'footer'],
                style: 'commercial',
                industries: ['retail', 'fashion', 'lifestyle']
            },
            {
                name: 'Agency Portfolio',
                structure: ['hero-minimal', 'services', 'portfolio-grid', 'process', 'team', 'contact', 'footer'],
                style: 'creative',
                industries: ['design', 'marketing', 'creative']
            },
            {
                name: 'App Landing',
                structure: ['hero-app', 'features-alternating', 'screenshots', 'pricing-tiers', 'download-cta', 'footer'],
                style: 'mobile-first',
                industries: ['mobile', 'app', 'technology']
            },
            {
                name: 'Blog/Magazine',
                structure: ['header-nav', 'featured-post', 'article-grid', 'categories-sidebar', 'newsletter', 'footer'],
                style: 'editorial',
                industries: ['media', 'publishing', 'content']
            }
        ],

        sections: [
            // Hero Variations
            { type: 'hero', variant: 'centered', elements: ['title', 'subtitle', 'cta-buttons', 'image'] },
            { type: 'hero', variant: 'split', elements: ['title', 'subtitle', 'cta', 'hero-image'] },
            { type: 'hero', variant: 'video-background', elements: ['title', 'subtitle', 'cta', 'video'] },
            { type: 'hero', variant: 'minimal', elements: ['title', 'subtitle', 'arrow-down'] },
            { type: 'hero', variant: 'fullscreen', elements: ['title', 'subtitle', 'cta', 'scroll-indicator'] },

            // Feature Variations
            { type: 'features', variant: '3-column-icons', elements: ['icon', 'title', 'description'] },
            { type: 'features', variant: 'alternating', elements: ['image', 'title', 'description', 'cta'] },
            { type: 'features', variant: 'cards-grid', elements: ['icon', 'title', 'description', 'link'] },
            { type: 'features', variant: 'timeline', elements: ['number', 'title', 'description'] },
            { type: 'features', variant: 'tabbed', elements: ['tabs', 'content', 'image'] },

            // Content Variations
            { type: 'content', variant: 'two-column', elements: ['image', 'text'] },
            { type: 'content', variant: 'centered-text', elements: ['title', 'body', 'cta'] },
            { type: 'content', variant: 'image-gallery', elements: ['images-grid', 'lightbox'] },
            { type: 'content', variant: 'video-embed', elements: ['video', 'caption'] },
            { type: 'content', variant: 'accordion', elements: ['questions', 'answers'] }
        ]
    },

    /**
     * 💡 COMPONENT IDEAS (1000+ combinations)
     */
    componentIdeas: {
        buttons: [
            'Gradient hover', 'Ripple effect', 'Shake on error', 'Success checkmark',
            'Loading spinner', 'Icon left/right', 'Split color', 'Glow effect',
            ' 3D press', 'Slide reveal', 'Border grow', 'Particle burst'
        ],

        cards: [
            'Flip on hover', 'Tilt effect', 'Parallax image', 'Hover lift',
            'Expand details', 'Slide content', 'Gradient border', 'Glass morphism',
            'Neumorphism', 'Spotlight effect', 'Corner peel', 'Stack reveal'
        ],

        navigation: [
            'Sticky on scroll', 'Transparent to solid', 'Mega menu', 'Hamburger animated',
            'Blur background', 'Underline grow', 'Slide from side', 'Circular menu',
            'Breadcrumb trail', 'Progress indicator', 'Search expand', 'User dropdown'
        ],

        forms: [
            'Floating labels', 'Inline validation', 'Multi-step wizard', 'Auto-save',
            'Password strength', 'File drag-drop', 'Character counter', 'Email verify',
            'Phone format', 'Date picker', 'Color picker', 'Range slider'
        ],

        animations: [
            'Fade in up', 'Slide from left', 'Zoom in', 'Rotate in',
            'Bounce', 'Shake', 'Pulse', 'Flash',
            'Swing', 'Wobble', 'Flip', 'Roll',
            'Parallax scroll', 'Reveal on scroll', 'Stagger children', 'Morphing shapes'
        ]
    },

    /**
     * 🎯 INDUSTRY TEMPLATES
     */
    industryTemplates: {
        saas: {
            colors: ['Electric Dreams', 'Tech Gray', 'Corporate Blue'],
            components: ['hero-centered', 'features-3col', 'pricing-tiers', 'testimonials-slider'],
            style: 'modern-clean',
            cta: 'Start Free Trial'
        },

        ecommerce: {
            colors: ['Sunset Glow', 'Forest Zen', 'Pastel Dream'],
            components: ['hero-split', 'product-grid', 'categories', 'featured-deals'],
            style: 'vibrant-commercial',
            cta: 'Shop Now'
        },

        agency: {
            colors: ['Midnight Purple', 'Neon Nights', 'Cyber Punk'],
            components: ['hero-minimal', 'portfolio-masonry', 'team-grid', 'contact-form'],
            style: 'creative-bold',
            cta: 'Start a Project'
        },

        blog: {
            colors: ['Classic Serif', 'Editorial', 'Autumn Leaves'],
            components: ['header-minimal', 'featured-post', 'article-grid', 'author-bio'],
            style: 'editorial-clean',
            cta: 'Read More'
        },

        restaurant: {
            colors: ['Sunset Glow', 'Autumn Leaves', 'Luxury Gold'],
            components: ['hero-fullscreen', 'menu-tabs', 'gallery-masonry', 'reservation-form'],
            style: 'appetizing-elegant',
            cta: 'View Menu'
        },

        fitness: {
            colors: ['Forest Zen', 'Electric Dreams', 'Neon Nights'],
            components: ['hero-video', 'programs-grid', 'trainer-profiles', 'schedule-calendar'],
            style: 'energetic-motivating',
            cta: 'Start Training'
        },

        medical: {
            colors: ['Medical Green', 'Corporate Blue', 'Winter Frost'],
            components: ['hero-trust', 'services-list', 'doctor-profiles', 'appointment-booking'],
            style: 'professional-caring',
            cta: 'Book Appointment'
        },

        education: {
            colors: ['Corporate Blue', 'Forest Zen', 'Pastel Dream'],
            components: ['hero-centered', 'courses-grid', 'testimonials', 'enrollment-form'],
            style: 'trustworthy-inspiring',
            cta: 'Enroll Now'
        }
    },

    /**
     * 📝 CONTENT SEEDS
     */
    contentSeeds: {
        headlines: [
            'Transform Your [Industry] Today',
            'The Future of [Product] is Here',
            'Build Something Amazing',
            'Your Success Starts Now',
            'Powerful [Service] Made Simple',
            'Join Thousands of Happy Customers',
            'Discover What\'s Possible',
            'Elevate Your [Business]',
            'The Smart Way to [Action]',
            'Experience the Difference'
        ],

        subheadlines: [
            'Everything you need to succeed in one platform',
            'Trusted by leading companies worldwide',
            'Get started in minutes, no credit card required',
            'The complete solution for modern businesses',
            'Designed for teams of all sizes',
            'Beautiful, fast, and easy to use',
            'Powerful features, simple pricing',
            'Join our community of creators'
        ],

        ctas: [
            'Get Started Free', 'Start Your Trial', 'See It In Action',
            'Request Demo', 'Download Now', 'Sign Up Free',
            'Learn More', 'Contact Sales', 'Try It Free',
            'Join Waitlist', 'Book a Call', 'Get Early Access'
        ],

        features: [
            { title: 'Lightning Fast', desc: 'Optimized for speed and performance', icon: '⚡' },
            { title: 'Fully Secure', desc: 'Enterprise-grade security built-in', icon: '🔒' },
            { title: 'Easy Setup', desc: 'Get started in under 5 minutes', icon: '🚀' },
            { title: '24/7 Support', desc: 'We\'re here whenever you need us', icon: '💬' },
            { title: 'Scalable', desc: 'Grows with your business', icon: '📈' },
            { title: 'Integrations', desc: 'Connect with your favorite tools', icon: '🔗' },
            { title: 'Analytics', desc: 'Deep insights into your data', icon: '📊' },
            { title: 'Customizable', desc: 'Make it yours with powerful options', icon: '🎨' }
        ],

        testimonials: [
            {
                quote: 'This has completely transformed how we work. Couldn\'t imagine going back!',
                author: 'Sarah Johnson',
                role: 'CEO, TechStart',
                rating: 5
            },
            {
                quote: 'The best investment we\'ve made for our business. ROI in just 2 months.',
                author: 'Michael Chen',
                role: 'Director of Marketing',
                rating: 5
            },
            {
                quote: 'Intuitive, powerful, and beautiful. Everything I needed in one place.',
                author: 'Emma Williams',
                role: 'Product Designer',
                rating: 5
            }
        ]
    },

    /**
     * 🎨 CSS EFFECTS LIBRARY
     */
    cssEffects: {
        hovers: {
            lift: 'transform: translateY(-8px); box-shadow: 0 12px 24px rgba(0,0,0,0.15);',
            grow: 'transform: scale(1.05);',
            shrink: 'transform: scale(0.95);',
            rotate: 'transform: rotate(5deg);',
            glow: 'box-shadow: 0 0 20px rgba(102, 126, 234, 0.6);',
            brightness: 'filter: brightness(1.2);',
            blur: 'filter: blur(2px);',
            grayscale: 'filter: grayscale(0);'
        },

        animations: {
            fadeIn: '@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }',
            slideUp: '@keyframes slideUp { from { transform: translateY(40px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }',
            zoomIn: '@keyframes zoomIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }',
            bounce: '@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }',
            pulse: '@keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }',
            shake: '@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-10px); } 75% { transform: translateX(10px); } }',
            spin: '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }',
            wiggle: '@keyframes wiggle { 0%, 100% { transform: rotate(0deg); } 25% { transform: rotate(-5deg); } 75% { transform: rotate(5deg); } }'
        },

        gradients: {
            sunset: 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)',
            ocean: 'linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%)',
            forest: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
            purple: 'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)',
            pink: 'linear-gradient(135deg, #ec4899 0%, #f472b6 100%)',
            fire: 'linear-gradient(135deg, #ef4444 0%, #f97316 100%)',
            ice: 'linear-gradient(135deg, #06b6d4 0%, #0ea5e9 100%)',
            neon: 'linear-gradient(135deg, #a855f7 0%, #ec4899 100%)'
        },

        backgrounds: {
            grid: 'background-image: linear-gradient(#e5e7eb 1px, transparent 1px), linear-gradient(90deg, #e5e7eb 1px, transparent 1px); background-size: 20px 20px;',
            dots: 'background-image: radial-gradient(circle, #e5e7eb 1px, transparent 1px); background-size: 20px 20px;',
            diagonal: 'background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, #e5e7eb 10px, #e5e7eb 11px);',
            noise: 'background-image: url("data:image/svg+xml,%3Csvg viewBox=\'0 0 400 400\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'noiseFilter\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'4\' /%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23noiseFilter)\'/%3E%3C/svg%3E"); opacity: 0.05;'
        }
    },

    /**
     * 🔍 SEARCH & FILTER
     */
    search(query, category = null) {
        const results = [];
        const lowerQuery = query.toLowerCase();

        if (!category || category === 'colors') {
            this.designPatterns.colorCombinations.forEach(color => {
                if (color.name.toLowerCase().includes(lowerQuery) ||
                    color.mood.toLowerCase().includes(lowerQuery)) {
                    results.push({ type: 'color', data: color });
                }
            });
        }

        if (!category || category === 'layouts') {
            this.layoutTemplates.landingPages.forEach(layout => {
                if (layout.name.toLowerCase().includes(lowerQuery) ||
                    layout.style.toLowerCase().includes(lowerQuery)) {
                    results.push({ type: 'layout', data: layout });
                }
            });
        }

        if (!category || category === 'industries') {
            Object.keys(this.industryTemplates).forEach(industry => {
                if (industry.includes(lowerQuery)) {
                    results.push({ type: 'industry', data: this.industryTemplates[industry] });
                }
            });
        }

        return results;
    },

    /**
     * 🎲 RANDOM GENERATION
     */
    getRandomDesign() {
        const colors = this.designPatterns.colorCombinations;
        const typography = this.designPatterns.typography;
        const spacing = this.designPatterns.spacing;
        const borders = this.designPatterns.borderRadius;
        const shadows = this.designPatterns.shadows;

        return {
            colors: colors[Math.floor(Math.random() * colors.length)],
            typography: typography[Math.floor(Math.random() * typography.length)],
            spacing: spacing[Math.floor(Math.random() * spacing.length)],
            borders: borders[Math.floor(Math.random() * borders.length)],
            shadows: shadows[Math.floor(Math.random() * shadows.length)]
        };
    },

    getRandomLayout(industry = null) {
        if (industry && this.industryTemplates[industry]) {
            return this.industryTemplates[industry];
        }

        const layouts = this.layoutTemplates.landingPages;
        return layouts[Math.floor(Math.random() * layouts.length)];
    },

    /**
     * 📊 STATISTICS
     */
    getStats() {
        return {
            colorSchemes: this.designPatterns.colorCombinations.length,
            typographyPairs: this.designPatterns.typography.length,
            layoutTemplates: this.layoutTemplates.landingPages.length,
            sectionVariants: this.layoutTemplates.sections.length,
            industries: Object.keys(this.industryTemplates).length,
            contentSeeds: Object.keys(this.contentSeeds).reduce((sum, key) => sum + this.contentSeeds[key].length, 0),
            cssEffects: Object.keys(this.cssEffects).reduce((sum, key) => sum + Object.keys(this.cssEffects[key]).length, 0)
        };
    }
};

// Export
console.log('🌱 Data Seeds loaded!');
console.log('📊 Stats:', window.DataSeeds.getStats());
