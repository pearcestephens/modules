# 🌐 VAPESHED & ECIGDIS WEBSITES - COMPLETE ANALYSIS

> **Analyzed:** November 5, 2025
> **Purpose:** Understand existing e-commerce platforms before building Web Management Module

---

## 🎯 WHAT WE DISCOVERED

Both **vapeshed.co.nz** and **ecigdis.co.nz** are **FULL E-COMMERCE PLATFORMS** built with a proper shopping cart system, likely **OpenCart or custom PHP e-commerce**.

---

## 🛒 **VAPESHED.CO.NZ** - Retail E-Commerce

### **Platform Type:**
- ✅ Full e-commerce platform
- ✅ Shopping cart + checkout
- ✅ Product catalog with categories
- ✅ 17 physical store locations
- ✅ Age verification required (NZ vaping laws)

### **Current Features:**

#### **1. Homepage**
- Hero sections with device options
- Juice Finder tool (Flavor + Nicotine type search)
- Featured product categories
- Store locations map/links
- Company story ("Founded in 2015...")

#### **2. Product Categories** (Extensive!)
```
├── Starter Kits & Pod Systems
│   ├── Electronic Cigarette Starter Kits
│   ├── Sub Ohm Kits
│   ├── Mod & Tank Kits
│   └── Mod Only
│
├── Disposable Pods
│   ├── Complete Kits
│   └── Cartridge Refills
│
├── Medicinal Vaporisers
│
├── Vape Juice / E-Liquid
│   ├── Gamer Sauce (Special: $25.00/100ml, $19.90/30ml salts)
│   ├── Vape Shed Premium Juice
│   ├── Just Juice
│   ├── Dinner Lady Core
│   ├── Nasty Juice
│   ├── Freebase Import Juice
│   └── Nicotine Salts
│
└── Accessories
    ├── Tanks
    ├── Coils (by brand)
    ├── RDA | RTA | RDTA
    ├── RDA Coils
    ├── Pod Coils & Cartridges
    ├── Glass & Spare Parts
    ├── Drip Tips
    ├── Chargers
    ├── Batteries (18650 / 21700 / Li-ion)
    ├── Battery Cases
    ├── Battery Wraps
    ├── Cases & Carry Bags
    ├── Wire
    ├── Cotton
    ├── Tools & Tool Kits
    └── Plastic Bottles
```

#### **3. Content Pages**
- Store Locations (17 stores with Google Maps links)
- Track My Order
- Bank Account Information
- Sending To Australia (international shipping)
- Shipping & Returns
- Terms & Conditions
- Career Opportunities
- Privacy Policy
- About Us
- Contact Us

#### **4. Special Features**
- **Juice Finder:** Interactive tool (Flavour Profile + Nicotine Type)
- **Store Locator:** 17 physical locations across NZ:
  - Hamilton (2 stores: Grey Street HQ + Killarney Road)
  - Auckland (multiple: Papakura, Glenfield, Henderson, Browns Bay, Botany)
  - Whangarei, Huntly, Te Awamutu, Tauranga, Rotorua, Gisborne, Christchurch

#### **5. Branding**
- Logo: Vape Shed logo (yellow branding)
- Tagline: "The Vape Shed - Your Local Vape Shop"
- Company: "The Vape Shed™ is a registered Trademark of Ecigdis Ltd"
- Payment: Visa/Mastercard accepted
- Social: Facebook + Instagram

#### **6. Compliance**
- NZ Smokefree Environments Act compliant
- Age verification required
- "NZ Compliant Device Options" messaging

---

## 🏢 **ECIGDIS.CO.NZ** - Wholesale Portal

### **Platform Type:**
- ✅ B2B e-commerce platform
- ✅ Account-based access (login required)
- ✅ Same product catalog as Vape Shed
- ✅ Wholesale pricing (hidden until login)
- ✅ Business registration required

### **Current Features:**

#### **1. Homepage**
- Wholesale portal access gate
- "Verified Access" - only registered business accounts see pricing
- Business registration form
- Login for existing accounts

#### **2. Key Messaging**
- 🔒 **Verified Access:** "Only registered business accounts can view pricing and inventory levels"
- 📦 **Fast NZ Fulfilment:** "Orders ship daily via tracked courier. Flat-rate shipping or store pickup available"
- ✅ **NZ Compliant Products:** "All products are notified and listed under the Smokefree Environments Act"

#### **3. Target Audience**
- NZ vape store owners
- Retailers buying wholesale
- B2B clients only

#### **4. CTAs**
- [Sign In] button
- [Register for Wholesale] button

#### **5. Product Categories**
- **Same as Vape Shed** but with wholesale pricing
- Likely shows stock levels to logged-in users
- Bulk ordering capabilities

#### **6. Content Pages**
- Bank Information (wholesale payment details)
- Shipping Information
- Returns & Faulty Products
- Terms & Conditions
- Privacy Policy
- About Us
- Contact Us

---

## 🔍 **TECHNICAL ARCHITECTURE ANALYSIS**

### **What We Can Infer:**

#### **Platform Type: Likely OpenCart or Custom PHP**
Based on URL patterns:
- `.html` extensions (OpenCart default)
- `/brands/` folders (category structure)
- `/accessories/` folders (product hierarchy)
- `checkout.html`, `login.html` (standard ecommerce pages)

#### **Current Database Structure (Inferred):**
```sql
-- Products
products (id, name, sku, price, description, category_id, brand_id, images)
product_categories (id, name, slug, parent_id)
product_brands (id, name, logo)
product_images (id, product_id, image_url, sort_order)

-- Inventory
product_variants (id, product_id, size, color, sku, stock_qty)
inventory_tracking (product_id, outlet_id, qty_on_hand)

-- Orders
orders (id, customer_id, order_total, status, shipping_address)
order_items (id, order_id, product_id, qty, price)
order_tracking (id, order_id, courier, tracking_number)

-- Customers
customers (id, email, name, phone, address, is_wholesale)
customer_groups (id, name, discount_percent) -- for wholesale pricing

-- Store Locations
store_locations (id, name, address, phone, google_maps_url, region)

-- CMS Pages
cms_pages (id, title, slug, content, meta_title, meta_description)
```

#### **Integration Points with CIS:**
- ✅ **Vend/Lightspeed:** Products pulled from Vend API (already have 879-line SDK)
- ✅ **Inventory Sync:** Stock levels from 17 store outlets
- ✅ **Customer Data:** CIS customer management
- ✅ **Orders:** Order processing through CIS
- ⚠️ **Content Management:** Pages likely hardcoded or in separate CMS tables

---

## 🎯 **WHAT THIS MEANS FOR WEB MANAGEMENT MODULE**

### **✅ WHAT WE CAN MANAGE FROM CIS:**

#### **1. Content Pages** ⭐ HIGH PRIORITY
- About Us
- Store Locations
- Shipping & Returns
- Terms & Conditions
- Privacy Policy
- Contact Us
- Careers
- Track My Order

**These are perfect candidates for the Page Manager!**

#### **2. Homepage Sections** ⭐ HIGH PRIORITY
- Hero banners
- Featured categories
- Company story section
- Special promotions (e.g., "$25.00 Gamer Sauce Special")
- Compliance messaging

#### **3. Navigation Menus** ⭐ HIGH PRIORITY
- Main menu (Starter Kits, Juice, Accessories)
- Footer links (Bank Info, Shipping, Terms)
- Breadcrumbs

#### **4. Promotional Content** ⭐ MEDIUM PRIORITY
- Homepage hero sections
- Special offers banners
- Seasonal promotions
- New product announcements

#### **5. Store Locations** ⭐ MEDIUM PRIORITY
- Add/edit/remove store locations
- Google Maps integration
- Store hours
- Contact info per store

#### **6. Assets** ⭐ MEDIUM PRIORITY
- Product images (managed via Vend)
- Homepage banners
- Category images
- Logo variations
- Social media images

---

### **❌ WHAT WE **DON'T** NEED TO MANAGE (Already in Vend):**

- ❌ Products (managed in Vend/Lightspeed)
- ❌ Product categories (managed in Vend)
- ❌ Pricing (managed in Vend)
- ❌ Inventory levels (managed in Vend)
- ❌ Orders (managed in Vend)
- ❌ Customers (managed in Vend)

---

## 🚀 **RECOMMENDED WEB MANAGEMENT MODULE APPROACH**

### **Phase 1: Content Management** (Week 1)
1. **Site Manager**
   - Register vapeshed.co.nz + ecigdis.co.nz in CIS
   - Site selector in admin

2. **Page Manager**
   - Manage content pages (About, Terms, Shipping, etc.)
   - Homepage sections (hero, company story)
   - WYSIWYG editor

3. **Asset Manager**
   - Upload/organize homepage banners
   - Category images
   - Social media assets

### **Phase 2: Navigation & Templates** (Week 2)
4. **Navigation Manager**
   - Edit main menu
   - Edit footer links
   - Manage breadcrumbs

5. **Template Library**
   - Page templates (content page, landing page)
   - Section templates (hero, feature grid)
   - Reusable components

### **Phase 3: Promotions & Stores** (Week 3)
6. **Promotional Banners**
   - Homepage hero rotation
   - Special offers (e.g., "$25 Gamer Sauce")
   - Seasonal campaigns

7. **Store Locator Manager**
   - Add/edit 17 store locations
   - Google Maps integration
   - Store hours editor

### **Phase 4: SEO & Analytics** (Week 4)
8. **SEO Manager**
   - Meta tags per page
   - Sitemap generator
   - Redirects manager

9. **Analytics Dashboard**
   - Traffic comparison (vapeshed vs ecigdis)
   - Most visited pages
   - Conversion tracking

---

## 📊 **INTEGRATION STRATEGY**

### **Option A: CMS Overlay (RECOMMENDED)**
**Keep existing e-commerce platform, add CMS layer**

```
┌─────────────────────────────────────┐
│  Existing E-Commerce (OpenCart?)    │
│  - Products (from Vend API)         │
│  - Cart + Checkout                  │
│  - Customer accounts                │
└─────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────┐
│  CIS Web Management Module (NEW)    │
│  - Content pages                    │
│  - Homepage sections                │
│  - Navigation menus                 │
│  - Assets                           │
│  - Store locations                  │
└─────────────────────────────────────┘
```

**How it works:**
- E-commerce platform still handles products/cart/checkout
- CIS provides CMS interface for content pages
- API integration for content delivery
- Pages pull from CIS database via API

**Advantages:**
- ✅ Don't break existing e-commerce
- ✅ Gradual migration
- ✅ CIS becomes content source of truth
- ✅ Can manage both sites from one admin

### **Option B: Full Migration (FUTURE)**
**Replace entire platform with CIS**
- More work
- Higher risk
- Better long-term

---

## 🎯 **IMMEDIATE NEXT STEPS**

### **Discovery Phase:**
1. ✅ **Browse websites** (DONE)
2. ⏳ **Identify CMS tables** - Find where current content is stored
3. ⏳ **Map page types** - Which pages exist on both sites
4. ⏳ **Analyze templates** - How are pages currently rendered
5. ⏳ **Review integrations** - How does current site connect to Vend/CIS

### **Build Phase:**
1. **Create Web Management Module structure**
2. **Build Site Manager** (vapeshed + ecigdis registry)
3. **Build Page Manager** (start with simple content pages)
4. **Build Asset Manager** (upload banners/images)
5. **Test API integration** (serve content to public sites)

---

## 💡 **KEY INSIGHTS**

### **✅ Good News:**
1. **Both sites use same structure** - Can build one module for both
2. **Content is separate from products** - Don't need to touch Vend integration
3. **Clear separation** - Retail (vapeshed) vs Wholesale (ecigdis)
4. **17 store locations** - Store locator manager will be valuable
5. **Existing brand assets** - Logos, colors, messaging already defined

### **⚠️ Considerations:**
1. **Live e-commerce sites** - Can't break checkout/cart
2. **Age verification** - NZ compliance requirements
3. **Wholesale pricing** - Ecigdis shows different pricing when logged in
4. **SEO critical** - Can't lose search rankings during migration
5. **17 stores depend on it** - High stakes

### **🎯 Recommended Approach:**
Start with **CMS Overlay (Option A)** - manage content pages only, don't touch e-commerce core. Once proven, expand to homepage sections, navigation, then eventually full control.

---

## 📋 **SUMMARY**

| Aspect | vapeshed.co.nz | ecigdis.co.nz |
|--------|----------------|---------------|
| **Purpose** | B2C Retail | B2B Wholesale |
| **Platform** | E-commerce (OpenCart?) | E-commerce (OpenCart?) |
| **Products** | ~1000+ SKUs | Same as Vape Shed |
| **Pricing** | Public retail pricing | Hidden until login |
| **Access** | Public (age verified) | Business accounts only |
| **Features** | Cart, Checkout, Juice Finder | Login, Registration, Bulk orders |
| **Content Pages** | 10+ pages | 7+ pages |
| **Store Locations** | 17 physical stores | Pickup available |
| **Branding** | Yellow, consumer-friendly | Corporate, B2B |
| **Social** | Facebook, Instagram | N/A |

---

## ✅ **READY TO BUILD?**

We now know:
- ✅ Both sites are full e-commerce platforms
- ✅ Products managed by Vend (don't touch)
- ✅ Content pages need CMS (our focus)
- ✅ Same structure = one module for both
- ✅ Can start with content pages (low risk)

**Next:** Build Web Management Module Phase 1 (Site Manager + Page Manager + Asset Manager) with **CMS Overlay approach**.

---

**Document Status:** Complete analysis ready for build
**Last Updated:** November 5, 2025
**Next Action:** Build Web Management Module Phase 1
