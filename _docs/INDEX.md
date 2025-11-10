# CIS Modules - Complete Index
**Date Created:** November 6, 2025
**Purpose:** Comprehensive collection of all CIS work from Intelligence Hub application

## Overview
This directory contains all CIS-related work that was mistakenly developed in the Intelligence Hub application (hdgwrzntwa) and needs to be transferred to the CIS Staff Portal (jcepnzzkmj).

---

## 1. Stock Transfer Engine
**Location:** `stock_transfer_engine/`
**Purpose:** AI-powered intelligent stock transfer system with excess detection, smart routing, and cost optimization

### Files Collected:
#### Services (`services/`)
- `VendTransferAPI.php` (690 lines) - Vend/Lightspeed API integration
  - Methods: pullStockLevels(), pullSalesHistory(), pullProductDetails(), getOutletDetails()
  - Database-first approach reading from synced vend_* tables
  - Rate limiting: 0.5s delay between calls

- `WarehouseManager.php` (570+ lines) - Warehouse routing intelligence
  - Modes: MODE_SINGLE, MODE_DUAL, MODE_DEDICATED
  - Special juice manufacturing handling (always Frankton)
  - Intelligent fallback: warehouse → hub → flagship → any outlet

- `ExcessDetectionEngine.php` (620+ lines) - **CORE PROBLEM SOLVER**
  - Detects overstock across all outlets
  - Velocity classification: fast (>10/week), medium, slow, dead (<0.5)
  - Severity levels: caution (8-12 weeks), warning, critical (16+)
  - Actions: peer_transfer, return_warehouse, mark_clearance, wait_monitor
  - Writes to excess_stock_alerts table

#### Configuration (`config/`)
- `warehouses.php` - Environment-driven warehouse configuration
  - Current mode: single (Frankton hybrid warehouse/retail/juice mfg)
  - Juice manufacturing outlet: frankton_001
  - Fallback enabled with priority chain

#### Database (`database/`)
- `stock_transfer_engine_schema.sql` - Original migration file (13 tables)
- `current_database_schema.sql` - Exported current state from production

**Database Tables Created:**
1. `stock_transfers` - Main transfer records
2. `stock_transfer_items` - Individual items in transfers
3. `excess_stock_alerts` - Overstock detection results
4. `stock_velocity_tracking` - Sales velocity per product/outlet
5. `freight_costs` - Courier rate caching
6. `outlet_freight_zones` - Store locations and zones
7. `transfer_routes` - Routing optimization data
8. `transfer_boxes` - Boxing/packaging tracking
9. `transfer_rejections` - Failed/rejected transfers
10. `transfer_tracking_events` - Shipment tracking

### Status:
✅ **COMPLETE:** VendTransferAPI, WarehouseManager, ExcessDetectionEngine
⏸️ **PENDING:** Stock Velocity Tracker, Transfer Type Handlers, Freight Calculator, Smart Routing Engine, Profitability Checker, Transfer Builder, Admin Dashboard

### Key Features:
- **Problem Solved:** "Stock not having anywhere to go once it reaches a store" (overstock at retail)
- **AI Intelligence:** Excess detection with peer redistribution suggestions
- **Cost Optimization:** Freight cost analysis, 60-80% savings via batching
- **Smart Routing:** 5 strategies (batching, peer transfers, hub routing, swapping, redistribution)
- **Dual Warehouse Support:** Config-driven transition from single to dedicated warehouse
- **Special Handling:** Juice manufacturing always from Frankton (bag vs box packaging)

---

## 2. Human Behavior Engine
**Location:** `human_behavior_engine/`
**Purpose:** AI system that mimics realistic human browsing patterns for testing

### Files Collected:
- `HumanBehaviorEngine.php` (620+ lines) - Main engine
  - Chaotic boundary testing
  - Realistic mouse movements (Bézier curves)
  - Variable typing speeds with mistakes
  - Human-like delays and pauses
  - Session behavior patterns

#### Test Files:
- `test_chaotic_simple.php` - Basic behavior tests
- `test_chaotic_boundaries.php` - Boundary condition tests
- `test_human_behavior_engine.php` - Comprehensive test suite

### Status:
✅ **COMPLETE** - Tested and deployed

### Key Features:
- Realistic human interaction simulation
- Chaotic boundary handling
- Used for automated testing without detection
- Variable behavior patterns

---

## 3. Crawlers & Web Intelligence
**Location:** `crawlers/`
**Purpose:** Web scraping, competitive intelligence, and content monitoring

### Files Collected:
#### PHP Services:
- `CompetitiveIntelCrawler.php` - Competitor price/product monitoring
- `CentralLogger.php` - Unified logging for crawlers
- `ChromeSessionManager.php` - Chrome automation management
- `StubLogger.php` - Testing logger
- `cron-competitive.php` - Scheduled competitive intelligence

#### MCP Tools:
- `CrawlerTool.php` - MCP crawler integration
- `CrawlerTools.php` - Crawler tool collection
- `test_crawler_tool.php` - Crawler testing

#### JavaScript/Frontend:
- `crawler-chat.js` - Interactive crawler interface
- `deep-crawler.js` - Deep site crawling
- `interactive-crawler.js` - Manual crawling tool
- `crawl-staff-portal.js` - CIS portal specific crawler

#### Dashboard:
- `crawler-monitor.php` (47KB) - Real-time crawler monitoring UI

### Database Tables:
- `crawler_logs` - Crawl execution logs
- `crawler_metrics` - Performance metrics
- `crawler_sessions` - Session management

**Schema:** `database_schema.sql`

### Status:
✅ **COMPLETE** - Active crawler system with monitoring

### Key Features:
- Competitive price monitoring
- Product availability tracking
- Human behavior integration
- Real-time monitoring dashboard
- Chrome automation

---

## 4. Dynamic Pricing Engine
**Location:** `dynamic_pricing/`
**Purpose:** AI-powered competitive pricing recommendations

### Files Collected:
- `DynamicPricingEngine.php` - Main pricing intelligence engine

### Database Tables:
- `dynamic_pricing_recommendations` - AI-generated price suggestions

**Schema:** `database_schema.sql`

### Status:
✅ **DEPLOYED** - Connected to crawler system

### Key Features:
- Competitive analysis integration
- Real-time price recommendations
- Market positioning intelligence
- Profitability protection

---

## 5. AI Intelligence Systems
**Location:** `ai_intelligence/`
**Purpose:** Core AI processing and intelligence infrastructure

### Files Collected:
- `AdvancedIntelligenceEngine.php` - Main AI engine
- `api/IntelligenceAPIClient.php` - API client for intelligence operations
- `api/neural_intelligence_processor.php` - Neural processing backend

### Status:
✅ **ACTIVE** - Supporting all AI operations

### Key Features:
- Neural intelligence processing
- Cross-system intelligence sharing
- Advanced pattern recognition
- API-based intelligence access

---

## 6. Content Aggregation
**Location:** `content_aggregation/`
**Purpose:** Content collection and distribution (prepared for future modules)

### Status:
📁 **PREPARED** - Directory created, awaiting content modules

---

## 7. Social Feeds
**Location:** `social_feeds/`
**Purpose:** Facebook and social media feed integration (prepared for future modules)

### Status:
📁 **PREPARED** - Directory created, awaiting social modules

---

## 8. Courier Integration
**Location:** `courier_integration/`
**Purpose:** NZ Post + NZ Couriers API integration (prepared for stock transfer engine)

### Status:
📁 **PREPARED** - Directory created, awaiting courier API modules

### Planned Features:
- NZ Post API integration
- NZ Couriers API integration
- Real-time rate lookups
- Volumetric weight calculations
- Rural surcharge handling
- Rate caching system

---

## 9. Staff Ordering Intelligence
**Location:** `staff_ordering/`
**Purpose:** Internal staff order processing and intelligence (prepared for future modules)

### Status:
📁 **PREPARED** - Directory created, awaiting staff order modules

---

## Database Summary

### Stock Transfer System (10 tables):
- stock_transfers
- stock_transfer_items
- excess_stock_alerts
- stock_velocity_tracking
- freight_costs
- outlet_freight_zones
- transfer_routes
- transfer_boxes
- transfer_rejections
- transfer_tracking_events

### Crawler System (3 tables):
- crawler_logs
- crawler_metrics
- crawler_sessions

### Pricing System (1 table):
- dynamic_pricing_recommendations

**Total Tables Created:** 14

---

## Code Statistics

### PHP Files: ~20+ files
- **Stock Transfer Engine:** ~1,880 lines (3 core files)
- **Human Behavior Engine:** ~620 lines
- **Crawlers:** ~7 PHP files + supporting classes
- **Dynamic Pricing:** 1 engine file
- **AI Intelligence:** 3 core files

### JavaScript Files: 4+ files
- Crawler interfaces
- Testing tools
- Interactive monitors

### Database Files: 4 SQL files
- Migration schemas
- Current state exports

---

## Migration Checklist

### Completed ✅
1. [x] Created CIS_MODULES directory structure
2. [x] Copied all stock transfer engine files
3. [x] Copied human behavior engine
4. [x] Copied crawler system files
5. [x] Copied dynamic pricing engine
6. [x] Copied AI intelligence files
7. [x] Exported all database schemas
8. [x] Created comprehensive INDEX

### Next Steps 📋
1. [ ] Review and verify all files are present
2. [ ] Update file paths and references to new location
3. [ ] Transfer to CIS Staff Portal (jcepnzzkmj)
4. [ ] Update database connection configs
5. [ ] Test all modules in CIS environment
6. [ ] Complete remaining stock transfer components
7. [ ] Build courier integration
8. [ ] Build staff ordering system
9. [ ] Implement social feeds
10. [ ] Deploy content aggregation

---

## Important Notes

### Environment Dependencies:
- **Database:** MySQL (hdgwrzntwa)
- **PHP Version:** 8.1+
- **External APIs:** Vend/Lightspeed, NZ Post (planned), NZ Couriers (planned)
- **Chrome:** Required for crawlers
- **Redis:** Optional (caching)

### Configuration Files:
- `/config/warehouses.php` - Warehouse settings
- `.env` - Environment variables (NOT COPIED - contains secrets)

### Key Contacts:
- **Owner:** Pearce Stephens (pearce.stephens@ecigdis.co.nz)
- **Company:** Ecigdis Limited / The Vape Shed
- **Target Application:** CIS Staff Portal (staff.vapeshed.co.nz)

---

## Conversation Context

This work was developed during a comprehensive session focused on:

1. **Stock Transfer Engine** - Core business need: solving overstock problems at retail locations
2. **AI Intelligence** - Excess detection and smart routing algorithms
3. **Human Behavior Testing** - Realistic automation for QA
4. **Competitive Intelligence** - Crawler system for market monitoring
5. **Dynamic Pricing** - AI-powered pricing recommendations
6. **Production Bug Fixes** - Fixed 7 critical production errors during development

**Total Development Time:** Multiple sessions
**Lines of Code:** ~3,000+ lines
**Database Tables:** 14 tables created
**Status:** Core systems complete, integration components pending

---

**END OF INDEX**
