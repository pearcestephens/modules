# VAPEULTRA DESIGN SYSTEM
## The Official Style Guide & Regulation Framework

**Version:** 2.0.0
**Status:** 🔒 **LOCKED & ENFORCED**
**Date:** November 12, 2025

---

## 🎯 CORE PRINCIPLES

1. **CONSISTENCY IS LAW** - No deviations, no exceptions
2. **BEAUTY MEETS FUNCTION** - Elegant design that serves purpose
3. **ACCESSIBLE BY DEFAULT** - WCAG 2.1 AA minimum standard
4. **PERFORMANCE FIRST** - Every design decision considers speed
5. **FUTURE-PROOF** - Built to scale with the business

---

## 🎨 COLOR SYSTEM

### Primary Palette (Indigo - Premium & Professional)

**The primary color represents trust, professionalism, and premium quality.**

```css
/* Primary Indigo Scale */
--vape-primary-50:  #eef2ff;   /* Lightest - backgrounds, hovers */
--vape-primary-100: #e0e7ff;   /* Very light - subtle backgrounds */
--vape-primary-200: #c7d2fe;   /* Light - borders, dividers */
--vape-primary-300: #a5b4fc;   /* Medium light - disabled states */
--vape-primary-400: #818cf8;   /* Medium - secondary actions */
--vape-primary-500: #6366f1;   /* BASE PRIMARY - main brand color */
--vape-primary-600: #4f46e5;   /* Dark - hover states */
--vape-primary-700: #4338ca;   /* Darker - active states */
--vape-primary-800: #3730a3;   /* Very dark - text on light */
--vape-primary-900: #312e81;   /* Darkest - headings, emphasis */
```

**Usage:**
- `--vape-primary-500` - Default buttons, links, icons
- `--vape-primary-600` - Hover states
- `--vape-primary-700` - Active/pressed states
- `--vape-primary-100` - Backgrounds, badges
- `--vape-primary-900` - Text emphasis

---

### Secondary Palette (Purple - Innovation & Energy)

**The secondary color represents innovation, creativity, and forward-thinking.**

```css
/* Secondary Purple Scale */
--vape-secondary-50:  #faf5ff;
--vape-secondary-100: #f3e8ff;
--vape-secondary-200: #e9d5ff;
--vape-secondary-300: #d8b4fe;
--vape-secondary-400: #c084fc;
--vape-secondary-500: #a855f7;   /* BASE SECONDARY */
--vape-secondary-600: #9333ea;
--vape-secondary-700: #7e22ce;
--vape-secondary-800: #6b21a8;
--vape-secondary-900: #581c87;
```

**Usage:**
- Accents and highlights
- Call-to-action elements
- Special features
- Premium indicators

---

### Semantic Colors (State Communication)

**These colors communicate meaning and status universally.**

#### Success (Green)
```css
--vape-success-50:  #f0fdf4;
--vape-success-100: #dcfce7;
--vape-success-500: #22c55e;   /* BASE SUCCESS */
--vape-success-600: #16a34a;   /* Hover */
--vape-success-700: #15803d;   /* Active */
--vape-success-900: #14532d;   /* Text */
```
**Usage:** Confirmations, successful operations, positive metrics, "go" actions

#### Error/Danger (Red)
```css
--vape-error-50:  #fef2f2;
--vape-error-100: #fee2e2;
--vape-error-500: #ef4444;     /* BASE ERROR */
--vape-error-600: #dc2626;     /* Hover */
--vape-error-700: #b91c1c;     /* Active */
--vape-error-900: #7f1d1d;     /* Text */
```
**Usage:** Errors, destructive actions, alerts, critical warnings, "stop" actions

#### Warning (Amber)
```css
--vape-warning-50:  #fffbeb;
--vape-warning-100: #fef3c7;
--vape-warning-500: #f59e0b;   /* BASE WARNING */
--vape-warning-600: #d97706;   /* Hover */
--vape-warning-700: #b45309;   /* Active */
--vape-warning-900: #78350f;   /* Text */
```
**Usage:** Caution messages, pending states, requires attention, "yield" actions

#### Info (Blue)
```css
--vape-info-50:  #eff6ff;
--vape-info-100: #dbeafe;
--vape-info-500: #3b82f6;      /* BASE INFO */
--vape-info-600: #2563eb;      /* Hover */
--vape-info-700: #1d4ed8;      /* Active */
--vape-info-900: #1e3a8a;      /* Text */
```
**Usage:** Informational messages, help text, tooltips, FYI notifications

---

### Neutral Palette (Grayscale - Universal)

**The foundation of all layouts. Used for text, backgrounds, borders, shadows.**

```css
/* Neutral Gray Scale */
--vape-gray-50:  #f9fafb;      /* Lightest - page backgrounds */
--vape-gray-100: #f3f4f6;      /* Very light - card backgrounds */
--vape-gray-200: #e5e7eb;      /* Light - borders, dividers */
--vape-gray-300: #d1d5db;      /* Medium light - disabled text */
--vape-gray-400: #9ca3af;      /* Medium - placeholder text */
--vape-gray-500: #6b7280;      /* BASE GRAY - secondary text */
--vape-gray-600: #4b5563;      /* Dark - body text */
--vape-gray-700: #374151;      /* Darker - headings */
--vape-gray-800: #1f2937;      /* Very dark - emphasis text */
--vape-gray-900: #111827;      /* Darkest - primary text */
--vape-white:    #ffffff;      /* Pure white */
--vape-black:    #000000;      /* Pure black (use sparingly) */
```

**Usage Guidelines:**
- **Body text:** `--vape-gray-600` on light backgrounds
- **Headings:** `--vape-gray-900`
- **Secondary text:** `--vape-gray-500`
- **Disabled text:** `--vape-gray-300`
- **Borders:** `--vape-gray-200` or `--vape-gray-300`
- **Card backgrounds:** `--vape-white` or `--vape-gray-50`
- **Page backgrounds:** `--vape-gray-50` or `--vape-gray-100`

---

### Surface Colors (Layers & Elevation)

```css
--vape-surface-base:      #ffffff;              /* Base layer (cards, panels) */
--vape-surface-raised:    #ffffff;              /* Raised elements (dropdowns) */
--vape-surface-overlay:   rgba(0, 0, 0, 0.5);   /* Modal backdrops */
--vape-surface-dark:      #1f2937;              /* Dark mode base */
--vape-surface-dark-raised: #374151;            /* Dark mode raised */
```

---

### Color Contrast Requirements (WCAG 2.1 AA)

**Minimum contrast ratios that MUST be met:**

- **Normal text (< 18px):** 4.5:1 contrast ratio
- **Large text (≥ 18px or ≥ 14px bold):** 3:1 contrast ratio
- **UI components:** 3:1 contrast ratio
- **Graphical objects:** 3:1 contrast ratio

**Pre-approved combinations:**
✅ `--vape-gray-900` on `--vape-white` (15.3:1)
✅ `--vape-gray-700` on `--vape-white` (9.7:1)
✅ `--vape-gray-600` on `--vape-white` (7.1:1)
✅ `--vape-primary-500` on `--vape-white` (4.6:1)
✅ `--vape-white` on `--vape-primary-600` (4.8:1)

---

## 📝 TYPOGRAPHY SYSTEM

### Font Families

```css
/* Sans-serif (Primary) - Modern, clean, professional */
--vape-font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI',
                  Roboto, 'Helvetica Neue', Arial, sans-serif;

/* Serif (Headings - Optional) - Traditional, authoritative */
--vape-font-serif: 'Georgia', 'Times New Roman', Times, serif;

/* Monospace (Code) - Technical content */
--vape-font-mono: 'Monaco', 'Courier New', Courier, monospace;

/* System Font Stack (Fallback) */
--vape-font-system: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
                    'Helvetica Neue', Arial, sans-serif;
```

**Default:** All body text uses `--vape-font-sans`

---

### Font Size Scale (Perfect Fourth - 1.333 ratio)

```css
/* Font Sizes with Line Heights */
--vape-text-xs:     0.75rem;    /* 12px */
--vape-text-sm:     0.875rem;   /* 14px */
--vape-text-base:   1rem;       /* 16px - DEFAULT */
--vape-text-lg:     1.125rem;   /* 18px */
--vape-text-xl:     1.25rem;    /* 20px */
--vape-text-2xl:    1.5rem;     /* 24px */
--vape-text-3xl:    1.875rem;   /* 30px */
--vape-text-4xl:    2.25rem;    /* 36px */
--vape-text-5xl:    3rem;       /* 48px */
--vape-text-6xl:    3.75rem;    /* 60px */

/* Line Heights (optimized for readability) */
--vape-leading-none:    1;
--vape-leading-tight:   1.25;
--vape-leading-snug:    1.375;
--vape-leading-normal:  1.5;     /* DEFAULT for body text */
--vape-leading-relaxed: 1.625;
--vape-leading-loose:   2;
```

**Line Height Pairing:**
- `xs` → `1rem` (16px)
- `sm` → `1.25rem` (20px)
- `base` → `1.5rem` (24px)
- `lg` → `1.75rem` (28px)
- `xl` → `1.75rem` (28px)
- `2xl` → `2rem` (32px)
- `3xl` → `2.25rem` (36px)
- `4xl` → `2.5rem` (40px)
- `5xl+` → `1` (tight)

---

### Font Weights

```css
--vape-font-thin:       100;
--vape-font-extralight: 200;
--vape-font-light:      300;
--vape-font-normal:     400;    /* DEFAULT - body text */
--vape-font-medium:     500;    /* Emphasis */
--vape-font-semibold:   600;    /* Subheadings */
--vape-font-bold:       700;    /* Headings */
--vape-font-extrabold:  800;    /* Extra emphasis */
--vape-font-black:      900;    /* Hero text */
```

**Usage:**
- **Body text:** `400` (normal)
- **Links:** `500` (medium)
- **Buttons:** `500` (medium)
- **H1-H2:** `700` (bold)
- **H3-H6:** `600` (semibold)
- **Labels:** `500` (medium)

---

### Typography Hierarchy (Pre-defined Styles)

```css
/* Heading 1 - Page titles */
.vape-h1 {
    font-size: var(--vape-text-4xl);      /* 36px */
    line-height: var(--vape-leading-tight); /* 1.25 */
    font-weight: var(--vape-font-bold);    /* 700 */
    color: var(--vape-gray-900);
    letter-spacing: -0.025em;              /* Tight tracking */
}

/* Heading 2 - Section titles */
.vape-h2 {
    font-size: var(--vape-text-3xl);      /* 30px */
    line-height: var(--vape-leading-tight);
    font-weight: var(--vape-font-bold);
    color: var(--vape-gray-900);
    letter-spacing: -0.025em;
}

/* Heading 3 - Subsection titles */
.vape-h3 {
    font-size: var(--vape-text-2xl);      /* 24px */
    line-height: var(--vape-leading-snug);
    font-weight: var(--vape-font-semibold); /* 600 */
    color: var(--vape-gray-900);
}

/* Heading 4-6 - Smaller headings */
.vape-h4 {
    font-size: var(--vape-text-xl);       /* 20px */
    line-height: var(--vape-leading-snug);
    font-weight: var(--vape-font-semibold);
    color: var(--vape-gray-800);
}

/* Body text - Default */
.vape-body {
    font-size: var(--vape-text-base);     /* 16px */
    line-height: var(--vape-leading-normal); /* 1.5 */
    font-weight: var(--vape-font-normal);  /* 400 */
    color: var(--vape-gray-600);
}

/* Small text - Secondary content */
.vape-small {
    font-size: var(--vape-text-sm);       /* 14px */
    line-height: var(--vape-leading-normal);
    color: var(--vape-gray-500);
}

/* Caption - Metadata, timestamps */
.vape-caption {
    font-size: var(--vape-text-xs);       /* 12px */
    line-height: var(--vape-leading-normal);
    color: var(--vape-gray-400);
}

/* Lead paragraph - Introductory text */
.vape-lead {
    font-size: var(--vape-text-lg);       /* 18px */
    line-height: var(--vape-leading-relaxed);
    color: var(--vape-gray-600);
}
```

---

## 📏 SPACING SYSTEM (8px Base Grid)

**All spacing MUST be multiples of 4px (0.25rem). This ensures visual harmony and consistency.**

```css
/* Spacing Scale */
--vape-space-0:   0;
--vape-space-px:  1px;          /* 1px - hairline borders */
--vape-space-0-5: 0.125rem;     /* 2px */
--vape-space-1:   0.25rem;      /* 4px */
--vape-space-1-5: 0.375rem;     /* 6px */
--vape-space-2:   0.5rem;       /* 8px */
--vape-space-2-5: 0.625rem;     /* 10px */
--vape-space-3:   0.75rem;      /* 12px */
--vape-space-3-5: 0.875rem;     /* 14px */
--vape-space-4:   1rem;         /* 16px - BASE UNIT */
--vape-space-5:   1.25rem;      /* 20px */
--vape-space-6:   1.5rem;       /* 24px */
--vape-space-7:   1.75rem;      /* 28px */
--vape-space-8:   2rem;         /* 32px */
--vape-space-9:   2.25rem;      /* 36px */
--vape-space-10:  2.5rem;       /* 40px */
--vape-space-11:  2.75rem;      /* 44px */
--vape-space-12:  3rem;         /* 48px */
--vape-space-14:  3.5rem;       /* 56px */
--vape-space-16:  4rem;         /* 64px */
--vape-space-20:  5rem;         /* 80px */
--vape-space-24:  6rem;         /* 96px */
--vape-space-28:  7rem;         /* 112px */
--vape-space-32:  8rem;         /* 128px */
--vape-space-36:  9rem;         /* 144px */
--vape-space-40:  10rem;        /* 160px */
--vape-space-44:  11rem;        /* 176px */
--vape-space-48:  12rem;        /* 192px */
--vape-space-52:  13rem;        /* 208px */
--vape-space-56:  14rem;        /* 224px */
--vape-space-60:  15rem;        /* 240px */
--vape-space-64:  16rem;        /* 256px */
```

**Common Usage:**
- **Button padding:** `--vape-space-2` `--vape-space-4` (8px 16px)
- **Card padding:** `--vape-space-6` (24px)
- **Section spacing:** `--vape-space-8` or `--vape-space-12` (32px or 48px)
- **Element gaps:** `--vape-space-4` (16px)
- **Input padding:** `--vape-space-3` (12px)

---

## 🔲 BORDER RADIUS

```css
--vape-radius-none: 0;
--vape-radius-sm:   0.125rem;   /* 2px - tight corners */
--vape-radius-base: 0.25rem;    /* 4px - DEFAULT */
--vape-radius-md:   0.375rem;   /* 6px - cards */
--vape-radius-lg:   0.5rem;     /* 8px - modals, large cards */
--vape-radius-xl:   0.75rem;    /* 12px - special elements */
--vape-radius-2xl:  1rem;       /* 16px - hero elements */
--vape-radius-3xl:  1.5rem;     /* 24px - large features */
--vape-radius-full: 9999px;     /* Pill shape - buttons, badges */
```

**Usage:**
- **Buttons:** `--vape-radius-md` (6px)
- **Inputs:** `--vape-radius-md` (6px)
- **Cards:** `--vape-radius-lg` (8px)
- **Badges:** `--vape-radius-full` (pill)
- **Avatars:** `--vape-radius-full` (circle)
- **Modals:** `--vape-radius-xl` (12px)

---

## 🌑 SHADOW SYSTEM (Elevation)

**Shadows create depth and hierarchy. Use consistently to indicate elevation levels.**

```css
/* Shadow Scale (0-5 levels + special cases) */
--vape-shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
/* Subtle - hover states */

--vape-shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1),
                  0 1px 2px 0 rgba(0, 0, 0, 0.06);
/* Small - buttons, inputs */

--vape-shadow-base: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
                    0 2px 4px -1px rgba(0, 0, 0, 0.06);
/* Base - cards */

--vape-shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
                  0 4px 6px -2px rgba(0, 0, 0, 0.05);
/* Medium - dropdowns */

--vape-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1),
                  0 10px 10px -5px rgba(0, 0, 0, 0.04);
/* Large - modals */

--vape-shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
/* Extra large - overlays */

--vape-shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
/* Maximum - special features */

--vape-shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
/* Inner - pressed buttons, inputs */

--vape-shadow-none: none;
/* No shadow */
```

**Elevation Mapping:**
- **Level 0 (flat):** `--vape-shadow-none` - Background elements
- **Level 1 (raised):** `--vape-shadow-sm` - Buttons, inputs
- **Level 2 (cards):** `--vape-shadow-base` - Cards, panels
- **Level 3 (floating):** `--vape-shadow-md` - Dropdowns, tooltips
- **Level 4 (modal):** `--vape-shadow-lg` - Modals, drawers
- **Level 5 (overlay):** `--vape-shadow-xl` - Overlays, full-screen

---

## 📚 Z-INDEX LAYERS (Stacking Order)

**Never use arbitrary z-index values. Always use these predefined layers.**

```css
--vape-z-base:      0;      /* Normal flow */
--vape-z-dropdown:  1000;   /* Dropdown menus */
--vape-z-sticky:    1020;   /* Sticky headers */
--vape-z-fixed:     1030;   /* Fixed elements */
--vape-z-backdrop:  1040;   /* Modal backdrops */
--vape-z-modal:     1050;   /* Modal dialogs */
--vape-z-popover:   1060;   /* Popovers */
--vape-z-tooltip:   1070;   /* Tooltips */
--vape-z-toast:     1080;   /* Toast notifications */
--vape-z-debug:     9999;   /* Debug overlays (dev only) */
```

**Usage Rules:**
1. Never use `z-index: 9999` in production
2. If you need a new layer, add it to this system
3. Elements in the same layer should not overlap
4. Always specify which layer an element belongs to

---

## ⏱️ TRANSITIONS & ANIMATIONS

### Duration Scale

```css
--vape-duration-instant:  75ms;     /* Instant feedback */
--vape-duration-fast:     150ms;    /* Fast interactions */
--vape-duration-base:     200ms;    /* DEFAULT - most UI */
--vape-duration-moderate: 300ms;    /* Moderate - complex animations */
--vape-duration-slow:     500ms;    /* Slow - page transitions */
--vape-duration-slower:   750ms;    /* Slower - special effects */
```

### Easing Functions

```css
/* Pre-defined easing curves */
--vape-ease-linear:     linear;
--vape-ease-in:         cubic-bezier(0.4, 0, 1, 1);
--vape-ease-out:        cubic-bezier(0, 0, 0.2, 1);        /* DEFAULT */
--vape-ease-in-out:     cubic-bezier(0.4, 0, 0.2, 1);
--vape-ease-bounce:     cubic-bezier(0.68, -0.55, 0.265, 1.55);
--vape-ease-elastic:    cubic-bezier(0.175, 0.885, 0.32, 1.275);
```

### Standard Transitions

```css
/* Common transition combinations */
--vape-transition-colors: color var(--vape-duration-base) var(--vape-ease-out),
                          background-color var(--vape-duration-base) var(--vape-ease-out),
                          border-color var(--vape-duration-base) var(--vape-ease-out);

--vape-transition-opacity: opacity var(--vape-duration-base) var(--vape-ease-out);

--vape-transition-shadow: box-shadow var(--vape-duration-base) var(--vape-ease-out);

--vape-transition-transform: transform var(--vape-duration-base) var(--vape-ease-out);

--vape-transition-all: all var(--vape-duration-base) var(--vape-ease-out);
```

**Usage Guidelines:**
- **Hover states:** `--vape-duration-fast` (150ms)
- **Active/focus:** `--vape-duration-instant` (75ms)
- **Modal open/close:** `--vape-duration-moderate` (300ms)
- **Page transitions:** `--vape-duration-slow` (500ms)
- **Default:** `--vape-duration-base` (200ms) with `ease-out`

---

## 📐 RESPONSIVE BREAKPOINTS

**Mobile-first approach. All styles default to mobile, then scale up.**

```css
/* Breakpoint Values */
--vape-breakpoint-sm:  640px;     /* Small devices (landscape phones) */
--vape-breakpoint-md:  768px;     /* Medium devices (tablets) */
--vape-breakpoint-lg:  1024px;    /* Large devices (desktops) */
--vape-breakpoint-xl:  1280px;    /* Extra large devices */
--vape-breakpoint-2xl: 1536px;    /* 2X large devices */
```

**Media Query Usage:**
```css
/* Mobile first - no media query needed for base styles */
.element {
    padding: var(--vape-space-4);
}

/* Tablet and up */
@media (min-width: 768px) {
    .element {
        padding: var(--vape-space-6);
    }
}

/* Desktop and up */
@media (min-width: 1024px) {
    .element {
        padding: var(--vape-space-8);
    }
}
```

**Container Max Widths:**
```css
--vape-container-sm:  640px;
--vape-container-md:  768px;
--vape-container-lg:  1024px;
--vape-container-xl:  1280px;
--vape-container-2xl: 1536px;
```

---

## 🎯 COMPONENT STANDARDS

### Buttons

**Size Variants:**
- **xs:** `0.5rem 0.75rem` (8px 12px) - Tiny actions
- **sm:** `0.5rem 1rem` (8px 16px) - Small actions
- **md:** `0.625rem 1.25rem` (10px 20px) - **DEFAULT**
- **lg:** `0.75rem 1.5rem` (12px 24px) - Large actions
- **xl:** `1rem 2rem` (16px 32px) - Hero actions

**Color Variants:**
- **Primary:** `--vape-primary-500` background
- **Secondary:** `--vape-secondary-500` background
- **Success:** `--vape-success-500` background
- **Danger:** `--vape-error-500` background
- **Ghost:** Transparent with border
- **Link:** No background, looks like link

**States:**
- **Default:** Base colors
- **Hover:** Darken by 1 step (e.g., 500 → 600)
- **Active:** Darken by 2 steps (e.g., 500 → 700)
- **Disabled:** Opacity 0.5, cursor not-allowed
- **Loading:** Show spinner, disable interactions

---

### Form Inputs

**Height Scale:**
- **sm:** `2rem` (32px)
- **md:** `2.5rem` (40px) - **DEFAULT**
- **lg:** `3rem` (48px)

**Padding:** `--vape-space-3` (12px) horizontal, `--vape-space-2` (8px) vertical

**Border:** `1px solid --vape-gray-300`

**Focus State:**
- Border color: `--vape-primary-500`
- Box shadow: `0 0 0 3px rgba(99, 102, 241, 0.1)`
- Outline: `none` (custom focus ring instead)

---

### Cards

**Padding Scale:**
- **Compact:** `--vape-space-4` (16px)
- **Default:** `--vape-space-6` (24px)
- **Comfortable:** `--vape-space-8` (32px)

**Elevation:**
- **Flat:** No shadow, border only
- **Raised:** `--vape-shadow-sm`
- **Elevated:** `--vape-shadow-base` - **DEFAULT**
- **Floating:** `--vape-shadow-md`

**Hover State:** Increase shadow by 1 level

---

## 🔤 ICON SYSTEM

**Primary Icon Library:** Bootstrap Icons 1.11.1

**Icon Sizes:**
```css
--vape-icon-xs:  0.75rem;   /* 12px */
--vape-icon-sm:  1rem;      /* 16px */
--vape-icon-base: 1.25rem;  /* 20px - DEFAULT */
--vape-icon-lg:  1.5rem;    /* 24px */
--vape-icon-xl:  2rem;      /* 32px */
--vape-icon-2xl: 3rem;      /* 48px */
```

**Icon Colors:**
- Inherit from parent text color by default
- Use semantic colors for status indicators
- Use `--vape-gray-400` for decorative/secondary icons

---

## ♿ ACCESSIBILITY REQUIREMENTS

### Focus States
All interactive elements MUST have visible focus indicators:
```css
:focus {
    outline: 2px solid var(--vape-primary-500);
    outline-offset: 2px;
}
```

### Touch Targets
Minimum size: **44x44px** (iOS/Android standard)

### Color Independence
Never communicate information by color alone. Always include:
- Icons or symbols
- Text labels
- Patterns or shapes

### Alt Text
All images must have descriptive alt text (or alt="" for decorative images).

---

## 📦 IMPLEMENTATION

### CSS Variables File
All variables should be defined in `/css/variables.css`:

```css
:root {
    /* Colors - Primary */
    --vape-primary-50: #eef2ff;
    --vape-primary-500: #6366f1;
    /* ... (all other variables) */
}
```

### Dark Mode Support (Future)
```css
@media (prefers-color-scheme: dark) {
    :root {
        --vape-gray-50: #1f2937;
        --vape-gray-900: #f9fafb;
        /* Invert grayscale, adjust colors */
    }
}
```

---

## ✅ COMPLIANCE CHECKLIST

Before shipping any design:

- [ ] All colors from approved palette
- [ ] Contrast ratios meet WCAG 2.1 AA (4.5:1 text, 3:1 UI)
- [ ] Spacing uses 8px grid system
- [ ] Typography uses predefined scale
- [ ] Shadows from approved elevation system
- [ ] Transitions use standard durations
- [ ] Responsive at all breakpoints
- [ ] Accessible keyboard navigation
- [ ] Visible focus indicators
- [ ] Touch targets ≥ 44x44px
- [ ] Semantic HTML elements
- [ ] ARIA labels where needed

---

## 🚫 FORBIDDEN PRACTICES

**NEVER DO THESE:**

1. ❌ Use arbitrary color values (e.g., `#FF5733` not in palette)
2. ❌ Use odd spacing values (e.g., `margin: 13px`)
3. ❌ Use `!important` (except for utility classes)
4. ❌ Use inline styles (use classes)
5. ❌ Use arbitrary z-index values
6. ❌ Rely on color alone to convey meaning
7. ❌ Create <44px touch targets
8. ❌ Remove focus outlines without replacement
9. ❌ Use non-standard breakpoints
10. ❌ Deviate from this system without approval

---

## 📞 DESIGN SYSTEM GOVERNANCE

**Questions or proposed changes?**
- Contact: Design Team Lead
- Process: Submit design proposal with rationale
- Approval: Required before implementation
- Updates: Versioned and documented in this file

---

**This design system is LOCKED and ENFORCED. All code must comply. No exceptions.**

**Version 2.0.0 - Effective November 12, 2025**

🔒 **REGULATION FRAMEWORK - STRICTLY ENFORCED** 🔒
