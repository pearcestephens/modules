# 🏪 Store Reports Module - Quick Reference

## 📁 File Locations

```
/home/master/applications/jcepnzzkmj/public_html/modules/store-reports/
```

## 🗄️ Database Tables

### Core Tables
- `store_reports` — Main inspection records
- `store_report_items` — Individual checklist responses
- `store_report_checklist` — Question definitions
- `store_report_images` — Photos with AI analysis
- `store_report_ai_requests` — AI follow-up photo requests
- `store_report_history` — Audit trail

### Legacy Tables (to migrate FROM)
- `store_quality`
- `store_quality_scores`
- `store_quality_score_checklist`
- `store_quality_images`

## 🚀 Installation

### 1. Install Schema
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/store-reports/database
mysql -u jcepnzzkmj -p jcepnzzkmj < schema.sql
```

### 2. Configure OpenAI API
Add to `/home/master/applications/jcepnzzkmj/public_html/.env`:
```bash
OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxx
```

### 3. Migrate Legacy Data
```bash
# Preview migration
php migrate_legacy_data.php --dry-run

# Execute migration
php migrate_legacy_data.php --execute

# Execute + AI re-analyze old photos
php migrate_legacy_data.php --re-analyze
```

## 🤖 AI Analysis Usage

### Analyze Single Image
```php
require_once 'services/AIVisionService.php';
$ai = new StoreReportAIVisionService();

$result = $ai->analyzeImage($imageId);
// Returns: analysis with scores, issues, recommendations
```

### Analyze Entire Report
```php
$results = $ai->analyzeReportImages($reportId);
// Batch processes all images, generates executive summary
```

## 📊 AI Scoring Dimensions

Each image scored 0-100 on:
1. **Cleanliness** — Dust, dirt, stains, hygiene
2. **Organization** — Product arrangement, tidiness
3. **Safety** — Hazards, obstructions, risks
4. **Compliance** — Regulations, signage, age restrictions
5. **Visual Appeal** — Professional appearance

Plus:
- **Overall Score** — Weighted average
- **Confidence** — AI's certainty level (0-100)

## 🎯 Grading Scale

| Score | Grade |
|-------|-------|
| 99-100 | A+ |
| 97-98  | A  |
| 95-96  | A- |
| 93-94  | B+ |
| 91-92  | B  |
| 89-90  | B- |
| 87-88  | C+ |
| 85-86  | C  |
| 83-84  | C- |
| 81-82  | D+ |
| 79-80  | D  |
| 77-78  | D- |
| 75-76  | E  |
| 0-74   | F  |

## 📸 AI Photo Requests

AI automatically requests follow-ups when:
- Image unclear or too distant
- Specific area needs closer inspection
- Compliance verification needed
- Issue detected but unclear severity

Request types:
- `clarification` — Better view needed
- `close_up` — Zoom in required
- `different_angle` — Alternative perspective
- `specific_area` — Target location (e.g., "bathroom")
- `follow_up` — Re-check after fix
- `compliance` — Verify regulation adherence

## 💰 Cost Estimates

**OpenAI Vision API:**
- ~$0.01-0.03 per image (high detail)
- Average report: 10-15 images = $0.10-0.45
- Monthly (17 stores × 2 reports): **$15-30/month**

## 🔐 Security Notes

- ✅ Images stored securely on server
- ✅ HTTPS encryption for OpenAI API
- ✅ Images not retained by OpenAI
- ✅ PII redacted from AI prompts
- ✅ Admin-only access to raw data
- ✅ Complete audit trail
- ✅ Soft delete (no permanent loss)

## 📞 Support Contacts

**Developer:** Pearce Stephens
**Email:** pearce.stephens@ecigdis.co.nz

**Docs:**
- Full README: `modules/store-reports/README.md`
- Status: `modules/store-reports/PROJECT_STATUS.md`
- Schema: `modules/store-reports/database/schema.sql`

## 🎯 Next Steps to Launch

1. ⏳ Build user interface (dashboard, upload, viewing)
2. ⏳ Create API endpoints (upload, analyze, fetch)
3. ⏳ Build controllers and models
4. ⏳ Testing and QA
5. ⏳ Deploy to production
6. ⏳ Staff training

**Estimated:** 1-2 weeks development

## ✅ Status

**Core Infrastructure:** ✅ COMPLETE
**User Interface:** ⏳ Pending
**API Endpoints:** ⏳ Pending
**Overall:** 🟡 60% Complete

---

**Version:** 0.6.0
**Last Updated:** November 5, 2025
**Status:** Development Phase — Core Ready
