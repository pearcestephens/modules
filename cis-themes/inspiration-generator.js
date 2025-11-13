/**
 * 💡 INSPIRATION GENERATOR
 * AI-powered design idea generation system
 * @version 1.0.0
 */

class InspirationGenerator {
    constructor(dataSeeds, componentGenerator) {
        this.seeds = dataSeeds;
        this.generator = componentGenerator;
        this.history = [];
    }

    /**
     * 🎨 Generate complete design system
     */
    generateDesignSystem(options = {}) {
        const {
            industry = null,
            mood = null,
            style = 'modern'
        } = options;

        let design;

        if (industry && this.seeds.industryTemplates[industry]) {
            // Industry-specific design
            const template = this.seeds.industryTemplates[industry];
            const colorScheme = this.seeds.designPatterns.colorCombinations.find(
                c => template.colors.includes(c.name)
            ) || this.seeds.getRandomDesign().colors;

            design = {
                industry,
                colors: colorScheme,
                typography: this.getTypographyForStyle(template.style),
                spacing: this.getSpacingForStyle(template.style),
                components: template.components,
                cta: template.cta
            };
        } else {
            // Random high-quality design
            design = this.seeds.getRandomDesign();
            design.components = this.seeds.getRandomLayout(industry).structure;
        }

        this.history.push({
            timestamp: Date.now(),
            design,
            options
        });

        return design;
    }

    /**
     * 🏗️ Generate complete page
     */
    async generateCompletePage(type, industry = null) {
        const pageTypes = {
            landing: {
                sections: ['hero', 'features', 'testimonials', 'cta', 'footer'],
                style: 'conversion-focused'
            },
            portfolio: {
                sections: ['hero-minimal', 'work-grid', 'about', 'contact', 'footer'],
                style: 'creative'
            },
            ecommerce: {
                sections: ['hero-product', 'product-grid', 'categories', 'featured', 'footer'],
                style: 'commercial'
            },
            blog: {
                sections: ['header', 'featured-post', 'article-grid', 'sidebar', 'footer'],
                style: 'editorial'
            },
            app: {
                sections: ['hero-app', 'features-alternating', 'screenshots', 'pricing', 'footer'],
                style: 'modern'
            }
        };

        const pageConfig = pageTypes[type] || pageTypes.landing;
        const designSystem = this.generateDesignSystem({ industry, style: pageConfig.style });

        const page = {
            type,
            industry,
            designSystem,
            sections: [],
            html: '',
            css: ''
        };

        // Generate each section
        for (const sectionType of pageConfig.sections) {
            const section = await this.generateSection(sectionType, designSystem);
            page.sections.push(section);
            page.html += section.html + '\n\n';
            page.css += section.css + '\n\n';
        }

        return page;
    }

    /**
     * 📦 Generate single section
     */
    async generateSection(type, designSystem) {
        const sectionMap = {
            'hero': { type: 'hero', layout: 'centered' },
            'hero-minimal': { type: 'hero', layout: 'minimal' },
            'hero-split': { type: 'hero', layout: 'split' },
            'hero-app': { type: 'hero', layout: 'centered' },
            'hero-product': { type: 'hero', layout: 'split' },
            'features': { type: 'features', layout: '3-column' },
            'features-alternating': { type: 'features', layout: 'alternating' },
            'testimonials': { type: 'testimonials', layout: 'slider' },
            'pricing': { type: 'pricing', layout: 'tiers' },
            'cta': { type: 'cta', layout: 'centered' },
            'footer': { type: 'footer', layout: 'multi-column' },
            'product-grid': { type: 'products', layout: 'grid' },
            'work-grid': { type: 'portfolio', layout: 'masonry' }
        };

        const config = sectionMap[type] || { type: 'generic', layout: 'default' };

        // Get color scheme name
        const colorSchemeName = this.getColorSchemeName(designSystem.colors);

        // Generate using component generator
        try {
            return this.generator.generateFromTemplate(
                config.type === 'hero' ? 'hero' : config.type,
                config.layout,
                this.getElementsForSection(config.type),
                { colorScheme: colorSchemeName }
            );
        } catch (error) {
            // Fallback to basic generation
            return this.generateBasicSection(type, designSystem);
        }
    }

    /**
     * 🎲 Generate random inspiration
     */
    generateRandomInspiration() {
        const ideas = [
            {
                category: 'color-scheme',
                idea: this.seeds.designPatterns.colorCombinations[
                    Math.floor(Math.random() * this.seeds.designPatterns.colorCombinations.length)
                ]
            },
            {
                category: 'layout',
                idea: this.seeds.layoutTemplates.landingPages[
                    Math.floor(Math.random() * this.seeds.layoutTemplates.landingPages.length)
                ]
            },
            {
                category: 'component-idea',
                idea: this.getRandomComponentIdea()
            },
            {
                category: 'animation',
                idea: this.seeds.componentIdeas.animations[
                    Math.floor(Math.random() * this.seeds.componentIdeas.animations.length)
                ]
            },
            {
                category: 'effect',
                idea: this.getRandomEffect()
            }
        ];

        return ideas[Math.floor(Math.random() * ideas.length)];
    }

    /**
     * 🔮 Generate design variations
     */
    generateVariations(baseDesign, count = 5) {
        const variations = [];

        for (let i = 0; i < count; i++) {
            const variation = { ...baseDesign };

            // Randomly modify aspects
            const aspectToChange = ['colors', 'typography', 'spacing'][Math.floor(Math.random() * 3)];

            switch (aspectToChange) {
                case 'colors':
                    variation.colors = this.seeds.designPatterns.colorCombinations[
                        Math.floor(Math.random() * this.seeds.designPatterns.colorCombinations.length)
                    ];
                    break;
                case 'typography':
                    variation.typography = this.seeds.designPatterns.typography[
                        Math.floor(Math.random() * this.seeds.designPatterns.typography.length)
                    ];
                    break;
                case 'spacing':
                    variation.spacing = this.seeds.designPatterns.spacing[
                        Math.floor(Math.random() * this.seeds.designPatterns.spacing.length)
                    ];
                    break;
            }

            variations.push(variation);
        }

        return variations;
    }

    /**
     * 💬 Generate design prompt from description
     */
    generatePromptFromDescription(description) {
        const keywords = this.extractKeywords(description);
        const mood = this.detectMood(description);
        const industry = this.detectIndustry(description);
        const components = this.suggestComponents(description);

        return {
            description,
            keywords,
            mood,
            industry,
            suggestedComponents: components,
            colorSchemes: this.suggestColorSchemes(mood, industry),
            layoutSuggestion: this.suggestLayout(industry, components)
        };
    }

    /**
     * 🎯 Helper Methods
     */
    getTypographyForStyle(style) {
        const styleMap = {
            'modern-clean': this.seeds.designPatterns.typography.find(t => t.name === 'Modern Sans'),
            'creative-bold': this.seeds.designPatterns.typography.find(t => t.name === 'Creative'),
            'editorial-clean': this.seeds.designPatterns.typography.find(t => t.name === 'Editorial'),
            'professional-caring': this.seeds.designPatterns.typography.find(t => t.name === 'Corporate')
        };

        return styleMap[style] || this.seeds.designPatterns.typography[0];
    }

    getSpacingForStyle(style) {
        if (style.includes('luxurious') || style.includes('editorial')) {
            return this.seeds.designPatterns.spacing.find(s => s.name === 'Spacious');
        } else if (style.includes('compact') || style.includes('minimal')) {
            return this.seeds.designPatterns.spacing.find(s => s.name === 'Compact');
        }
        return this.seeds.designPatterns.spacing.find(s => s.name === 'Default');
    }

    getElementsForSection(type) {
        const elementMap = {
            'hero': ['title', 'subtitle', 'cta-buttons', 'image'],
            'features': ['icon', 'title', 'description'],
            'testimonials': ['quote', 'author', 'role', 'image'],
            'pricing': ['title', 'price', 'features', 'cta'],
            'cta': ['title', 'description', 'button'],
            'footer': ['logo', 'links', 'social', 'copyright'],
            'generic': ['title', 'content']
        };

        return elementMap[type] || elementMap['generic'];
    }

    getColorSchemeName(colors) {
        // Find matching color scheme name
        const match = this.seeds.designPatterns.colorCombinations.find(
            c => c.primary === colors.primary
        );

        if (match) {
            // Map to generator color scheme names
            const nameMap = {
                'Electric Dreams': 'electric',
                'Ocean Breeze': 'ocean',
                'Sunset Glow': 'sunset',
                'Forest Zen': 'forest',
                'Midnight Purple': 'midnight'
            };
            return nameMap[match.name] || 'electric';
        }

        return 'electric';
    }

    generateBasicSection(type, designSystem) {
        return {
            html: `<section class="${type}-section">\n  <h2>${type.toUpperCase()}</h2>\n  <p>Section content here</p>\n</section>`,
            css: `.${type}-section {\n  padding: 80px 20px;\n  background: ${designSystem.colors.primary};\n  color: white;\n}`,
            js: ''
        };
    }

    getRandomComponentIdea() {
        const categories = Object.keys(this.seeds.componentIdeas);
        const category = categories[Math.floor(Math.random() * categories.length)];
        const ideas = this.seeds.componentIdeas[category];
        return {
            category,
            idea: ideas[Math.floor(Math.random() * ideas.length)]
        };
    }

    getRandomEffect() {
        const effectTypes = Object.keys(this.seeds.cssEffects);
        const type = effectTypes[Math.floor(Math.random() * effectTypes.length)];
        const effects = Object.keys(this.seeds.cssEffects[type]);
        const effect = effects[Math.floor(Math.random() * effects.length)];
        return {
            type,
            name: effect,
            css: this.seeds.cssEffects[type][effect]
        };
    }

    extractKeywords(text) {
        const keywords = ['modern', 'minimal', 'bold', 'elegant', 'creative', 'professional',
                         'fun', 'serious', 'colorful', 'dark', 'light', 'corporate', 'startup'];
        return keywords.filter(k => text.toLowerCase().includes(k));
    }

    detectMood(text) {
        const moods = {
            'energetic': ['fast', 'quick', 'dynamic', 'energetic', 'vibrant'],
            'calm': ['calm', 'peaceful', 'serene', 'relaxing', 'zen'],
            'professional': ['professional', 'corporate', 'business', 'formal'],
            'creative': ['creative', 'artistic', 'unique', 'innovative', 'bold'],
            'friendly': ['friendly', 'approachable', 'warm', 'welcoming']
        };

        for (const [mood, words] of Object.entries(moods)) {
            if (words.some(w => text.toLowerCase().includes(w))) {
                return mood;
            }
        }

        return 'neutral';
    }

    detectIndustry(text) {
        const industries = ['saas', 'ecommerce', 'agency', 'blog', 'restaurant',
                           'fitness', 'medical', 'education'];
        return industries.find(i => text.toLowerCase().includes(i)) || null;
    }

    suggestComponents(text) {
        const suggestions = [];

        if (text.includes('hero') || text.includes('header')) suggestions.push('hero');
        if (text.includes('feature')) suggestions.push('features');
        if (text.includes('pricing') || text.includes('price')) suggestions.push('pricing');
        if (text.includes('testimonial') || text.includes('review')) suggestions.push('testimonials');
        if (text.includes('contact') || text.includes('form')) suggestions.push('contact-form');
        if (text.includes('footer')) suggestions.push('footer');

        return suggestions.length > 0 ? suggestions : ['hero', 'features', 'cta'];
    }

    suggestColorSchemes(mood, industry) {
        const suggestions = [];

        // Mood-based suggestions
        if (mood === 'energetic') {
            suggestions.push('Electric Dreams', 'Neon Nights', 'Sunset Glow');
        } else if (mood === 'calm') {
            suggestions.push('Ocean Breeze', 'Winter Frost', 'Pastel Dream');
        } else if (mood === 'professional') {
            suggestions.push('Corporate Blue', 'Tech Gray', 'Finance Gold');
        }

        // Industry-based suggestions
        if (industry && this.seeds.industryTemplates[industry]) {
            suggestions.push(...this.seeds.industryTemplates[industry].colors);
        }

        return [...new Set(suggestions)].slice(0, 5);
    }

    suggestLayout(industry, components) {
        if (industry && this.seeds.industryTemplates[industry]) {
            const template = this.seeds.layoutTemplates.landingPages.find(
                l => l.industries.includes(industry)
            );
            return template || this.seeds.layoutTemplates.landingPages[0];
        }

        return this.seeds.layoutTemplates.landingPages[0];
    }

    /**
     * 📊 Get inspiration history
     */
    getHistory() {
        return this.history;
    }

    /**
     * 🔄 Get similar designs
     */
    getSimilarDesigns(design, count = 5) {
        const similar = [];
        const mood = design.colors.mood;

        // Find designs with similar mood
        this.seeds.designPatterns.colorCombinations
            .filter(c => c.mood === mood)
            .slice(0, count)
            .forEach(colors => {
                similar.push({
                    colors,
                    typography: this.seeds.getRandomDesign().typography,
                    spacing: this.seeds.getRandomDesign().spacing
                });
            });

        return similar;
    }
}

// Export
window.InspirationGenerator = InspirationGenerator;
console.log('💡 Inspiration Generator loaded!');
