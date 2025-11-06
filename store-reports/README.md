# 🏪 AI-Powered Store Reports Module

> **Next-generation store inspection system with OpenAI Vision analysis, intelligent photo requests, and automated scoring.**

---

## 📋 Overview

The **Store Reports** module revolutionizes retail store inspections by combining traditional checklists with cutting-edge AI image analysis. Staff take photos during store visits, and our AI automatically analyzes them for cleanliness, organization, safety, and compliance — providing instant feedback, requesting follow-up photos when needed, and generating comprehensive reports with minimal manual effort.

### 🎯 Key Features

- ✅ **AI Image Analysis** — GPT-4 Vision analyzes every photo for 5 key metrics
- 🤖 **Intelligent Photo Requests** — AI asks for specific follow-up shots when needed
- 📊 **Automated Scoring** — Independent AI scoring + human review capability
- 📸 **Drag & Drop Upload** — Modern, user-friendly photo submission
- 🎨 **Beautiful Dashboard** — Real-time progress, visual analytics, trend tracking
- 📈 **Store Benchmarking** — Compare stores, track improvements over time
- 🔄 **Data Migration** — Import from legacy store_quality tables
- 📱 **Mobile Optimized** — Perfect for on-site inspections

---

## 🗄️ Database Architecture

### Core Tables

| Table | Purpose | Key Features |
|-------|---------|--------------|
| `store_reports` | Main report records | AI/manual scoring, workflow status, executive summary |
| `store_report_items` | Checklist responses | Individual item scores with AI confidence |
| `store_report_checklist` | Master questions | Customizable criteria, AI analysis prompts |
| `store_report_images` | Photos + AI analysis | 5 dimension scoring, object detection, flags |
| `store_report_ai_requests` | AI photo requests | Intelligent follow-up requests with priority |
| `store_report_history` | Complete audit trail | Every change tracked with timestamp |

### AI Analysis Fields (per image)

```sql
ai_cleanliness_score     DECIMAL(5,2)  -- 0-100 hygiene assessment
ai_organization_score    DECIMAL(5,2)  -- 0-100 tidiness/arrangement
ai_safety_score          DECIMAL(5,2)  -- 0-100 hazard detection
ai_compliance_score      DECIMAL(5,2)  -- 0-100 regulatory compliance
ai_overall_score         DECIMAL(5,2)  -- 0-100 combined score
ai_confidence            DECIMAL(5,2)  -- AI confidence level
ai_description           TEXT          -- Detailed image description
ai_detected_objects      TEXT (JSON)   -- Objects/elements found
ai_detected_issues       TEXT (JSON)   -- Problems identified
ai_detected_positives    TEXT (JSON)   -- Good practices observed
ai_recommendations       TEXT (JSON)   -- Improvement suggestions
ai_flags                 TEXT (JSON)   -- Warning/danger flags
ai_follow_up_needed      BOOLEAN       -- Requests additional photo
ai_follow_up_request     TEXT          -- What AI wants to see
```

---

## 🧠 AI Vision Service

### Analysis Process

1. **Image Upload** → Staff uploads photos via drag-and-drop interface
2. **Queue** → Images queued for AI analysis (batch processing)
3. **AI Analysis** → OpenAI GPT-4 Vision analyzes each image:
   - Detailed description
   - Object detection
   - Issue identification
   - Scoring (5 dimensions)
   - Flag critical concerns
   - Request follow-ups if needed
4. **Results Storage** → Structured JSON data stored in database
5. **Report Generation** → AI generates executive summary from all analyses
6. **Human Review** → Staff can review and adjust AI findings

### AI Prompts

The system uses **contextual prompts** tailored to:
- Store location
- Specific checklist items
- Staff-provided captions
- Photo metadata

Example critical analysis areas:
- Dust, dirt, stains, spills
- Clutter, poor organization
- Safety hazards (cords, obstructions)
- Compliance issues (age warnings, signage)
- Product damage/expiry
- Poor lighting/visibility
- Unprofessional appearance

### AI Photo Requests

When AI detects it needs more information, it automatically creates photo requests:

```json
{
  "title": "Close-up of product display cabinet",
  "description": "Unable to assess product arrangement from current angle. Please provide close-up shot of main display cabinet showing product organization.",
  "priority": "medium",
  "reason": "Current image too distant to evaluate product spacing and labeling",
  "request_type": "close_up"
}
```

Request types:
- `clarification` — Need better view of existing area
- `close_up` — Closer shot required
- `different_angle` — Alternative perspective needed
- `specific_area` — Target specific location
- `follow_up` — Re-check after issue addressed
- `compliance` — Verify regulatory requirement

---

## 📊 Scoring System

### Calculation

```
AI Score (weighted average of 5 dimensions):
- Cleanliness: 30%
- Organization: 25%
- Safety: 25%
- Compliance: 15%
- Visual Appeal: 5%

Manual Score:
- Human reviewer can adjust based on context

Final Score:
- Option 1: Use AI score only (fast, consistent)
- Option 2: Use manual score (human judgment)
- Option 3: Weighted average: 70% AI + 30% human
```

### Grading Scale

| Score Range | Grade |
|-------------|-------|
| 99-100 | A+ |
| 97-98 | A |
| 95-96 | A- |
| 93-94 | B+ |
| 91-92 | B |
| 89-90 | B- |
| 87-88 | C+ |
| 85-86 | C |
| 83-84 | C- |
| 81-82 | D+ |
| 79-80 | D |
| 77-78 | D- |
| 75-76 | E |
| 0-74 | F |

---

## 🚀 Getting Started

### 1. Install Database Schema

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/store-reports/database
mysql -u jcepnzzkmj -p jcepnzzkmj < schema.sql
```

### 2. Configure OpenAI API

Add to `.env`:

```bash
OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxx
```

### 3. Migrate Legacy Data (Optional)

```bash
php database/migrate_legacy_data.php
```

This imports from:
- `store_quality`
- `store_quality_scores`
- `store_quality_score_checklist`
- `store_quality_images`

### 4. Access Module

Navigate to:
```
https://staff.vapeshed.co.nz/modules/store-reports/
```

---

## 🎨 User Interface

### Dashboard Features

- **Quick Start** — Select store, start new report
- **Active Reports** — Resume in-progress inspections
- **Recent History** — View past reports, trends
- **Store Leaderboard** — Rankings by score
- **AI Insights** — System-wide patterns and recommendations

### Report Creation Flow

1. **Select Store** → Choose from outlets list
2. **Upload Photos** → Drag-and-drop or camera upload
3. **AI Analysis** → Real-time progress indicators
4. **Review Results** → See AI findings per photo
5. **Complete Checklist** — Answer any remaining questions
6. **Submit Report** → Generate final grade and summary
7. **AI Requests** — Fulfill any photo follow-up requests
8. **Manager Review** — Optional human review/override

---

## 🔧 API Endpoints

### Image Analysis

```php
POST /modules/store-reports/api/analyze-image.php
{
  "image_id": 123
}

Response:
{
  "success": true,
  "analysis": {
    "scores": {...},
    "issues": [...],
    "recommendations": [...]
  },
  "duration_ms": 3500
}
```

### Batch Analysis

```php
POST /modules/store-reports/api/analyze-report.php
{
  "report_id": 45
}

Response:
{
  "total": 12,
  "successful": 11,
  "failed": 1,
  "summary": "..."
}
```

### Photo Upload

```php
POST /modules/store-reports/api/upload-image.php
FormData:
  - report_id: 45
  - file: [image blob]
  - location_in_store: "Front Counter"
  - caption: "Main display cabinet"
```

---

## 📈 Analytics & Reporting

### Available Views

```sql
-- Store benchmarking
SELECT * FROM vw_store_report_benchmarks;

-- AI performance metrics
SELECT * FROM vw_ai_analysis_metrics;
```

### Custom Reports

Generate insights on:
- Store-by-store comparison
- Trend analysis (improving vs declining)
- Common issues across all stores
- AI confidence levels
- Response times
- Photo request fulfillment rates

---

## 🔄 Data Migration from Legacy System

### Legacy Tables

The old system used:
- `store_quality` — Main report records
- `store_quality_scores` — Individual item scores
- `store_quality_score_checklist` — Question definitions
- `store_quality_images` — Photos (NO AI analysis)

### Migration Strategy

```bash
php database/migrate_legacy_data.php --dry-run   # Preview
php database/migrate_legacy_data.php --execute   # Run migration
php database/migrate_legacy_data.php --re-analyze # AI re-analyze old photos
```

Process:
1. Copy reports from `store_quality` → `store_reports`
2. Copy responses from `store_quality_scores` → `store_report_items`
3. Copy checklist from `store_quality_score_checklist` → `store_report_checklist`
4. Copy images from `store_quality_images` → `store_report_images`
5. **NEW:** Run AI analysis on all historical photos (optional)
6. Generate AI summaries for migrated reports

---

## 🛠️ File Structure

```
modules/store-reports/
├── index.php                    # Main entry point
├── controllers/
│   └── StoreReportController.php   # Business logic
├── models/
│   ├── StoreReport.php            # Report CRUD
│   ├── StoreReportImage.php       # Image handling
│   └── StoreReportChecklist.php   # Checklist management
├── views/
│   ├── dashboard.php              # Main dashboard
│   ├── create-report.php          # New report wizard
│   ├── view-report.php            # Report details
│   ├── upload-photos.php          # Photo upload interface
│   ├── ai-analysis.php            # AI results display
│   └── history.php                # Past reports
├── services/
│   ├── AIVisionService.php        # OpenAI integration
│   ├── ScoreCalculator.php        # Scoring logic
│   └── ReportGenerator.php        # PDF/export generation
├── api/
│   ├── analyze-image.php          # Single image analysis
│   ├── analyze-report.php         # Batch analysis
│   ├── upload-image.php           # Photo upload handler
│   └── ai-requests.php            # Follow-up requests
├── database/
│   ├── schema.sql                 # Complete schema
│   ├── migrate_legacy_data.php    # Migration script
│   └── seed_checklist.php         # Default questions
├── assets/
│   ├── css/store-reports.css      # Module styles
│   └── js/store-reports.js        # Frontend logic
└── README.md                      # This file
```

---

## 🔐 Security & Privacy

- ✅ All images stored securely on server (not sent to third parties except OpenAI)
- ✅ OpenAI API calls use HTTPS encryption
- ✅ Images not retained by OpenAI after analysis
- ✅ PII redacted from AI prompts
- ✅ Admin-only access to raw AI data
- ✅ Audit trail tracks all access and changes
- ✅ Soft delete (no permanent data loss)

---

## ⚡ Performance Considerations

### Optimization

- **Batch Processing** — Analyze multiple images in queue
- **Caching** — Store AI results permanently
- **Rate Limiting** — Prevent API quota exhaustion
- **Retry Logic** — Auto-retry failed analyses
- **Progressive Upload** — Upload + analyze in background

### Costs

OpenAI Vision API pricing (as of 2025):
- GPT-4 Vision: ~$0.01-0.03 per image (high detail)
- Average report: 10-15 images = $0.10-0.45 per report
- Monthly estimate (17 stores × 2 reports/mo): ~$15-30/month

---

## 🎯 Roadmap

### Phase 1: ✅ Core System (Current)
- Database schema
- AI Vision integration
- Basic UI
- Data migration

### Phase 2: 🚧 Enhanced Features (Next)
- Mobile app (React Native)
- Real-time collaboration
- Voice notes (speech-to-text)
- Video analysis (short clips)
- Automated scheduling/reminders

### Phase 3: 🔮 Advanced AI (Future)
- Trend prediction
- Automated action items
- Staff performance correlation
- Sales impact analysis
- Competitor benchmarking (anonymized)

---

## 🤝 Contributing

Developed for **Ecigdis Limited / The Vape Shed**
Contact: pearce.stephens@ecigdis.co.nz

---

## 📝 License

Proprietary — Internal use only
© 2025 Ecigdis Limited

---

## 🆘 Support

For issues, feature requests, or questions:
- Internal Wiki: https://wiki.vapeshed.co.nz/store-reports
- Helpdesk: https://helpdesk.vapeshed.co.nz
- Email: it@ecigdis.co.nz

---

**Built with ❤️ and 🤖 AI**
