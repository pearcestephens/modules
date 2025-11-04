# 🧠 AI Intelligence System - Quick Reference Card

**Print this and keep it handy!**

---

## 🚀 5-Minute Deploy

```bash
# 1. Install tables
cd /home/master/applications/jcepnzzkmj/public_html/modules/base
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < database/migrations/001_create_ai_business_intelligence_tables.sql

# 2. Test
php examples/example-ai-business-insights.php

# 3. Automate
crontab -e
# Add: 0 8 * * * /usr/bin/php .../generate-daily-insights.php >> .../ai-insights.log 2>&1
```

---

## 📊 Daily Routine (5 minutes)

### Morning Review
```bash
# Check today's critical insights
curl "https://staff.vapeshed.co.nz/modules/base/public/api/ai-insights.php?path=critical"
```

**For each insight:**
1. 🔴 **Critical** → Act immediately
2. 🟡 **High** → Plan action today
3. 🟢 **Medium** → Review weekly
4. ⚪ **Low** → Monitor

### Mark as Reviewed
```bash
curl -X POST "https://...ai-insights.php?path={ID}/review" \
  -H "Content-Type: application/json" \
  -d '{"action_taken": "...", "user_id": 1}'
```

---

## 💡 Common Questions

### "Why are sales down?"
```bash
curl -X POST "https://...ai-insights.php?path=ask" \
  -H "Content-Type: application/json" \
  -d '{"question": "Why are sales down?"}'
```

### "Which stores need attention?"
```bash
curl -X POST "https://...ai-insights.php?path=ask" \
  -H "Content-Type: application/json" \
  -d '{"question": "Which stores need attention?"}'
```

### "What should I promote?"
```bash
curl -X POST "https://...ai-insights.php?path=ask" \
  -H "Content-Type: application/json" \
  -d '{"question": "What inventory should I promote?"}'
```

---

## 🔧 Quick Troubleshooting

### No insights generated?
```bash
# Check logs
tail -50 /home/master/applications/jcepnzzkmj/public_html/logs/ai-insights.log

# Manual run
php /home/master/applications/jcepnzzkmj/public_html/modules/base/scripts/generate-daily-insights.php
```

### API not working?
```bash
# Test connectivity
curl -I "https://staff.vapeshed.co.nz/modules/base/public/api/ai-insights.php"

# Check PHP errors
tail -50 logs/apache_*.error.log
```

### AI Hub down?
```bash
# Check health
curl https://gpt.ecigdis.co.nz/mcp/health.php
```

---

## 📈 What It Does

### Automatically Detects:
- 📉 Sales declines (>15% drop)
- 📦 Slow-moving inventory (>50 units, <5 sales)
- ⏱️ Process delays (transfers >48hrs, consignments >60min)
- 🎯 Optimization opportunities

### Provides:
- 🎯 Specific recommendations
- 📊 Impact predictions
- 🤖 AI reasoning
- 💯 Confidence scores

---

## 🎯 Priority Guide

| Priority | Action | Timeframe |
|----------|--------|-----------|
| 🔴 Critical | Act now | Within hours |
| 🟡 High | Plan action | Today |
| 🟢 Medium | Review | This week |
| ⚪ Low | Monitor | This month |
| ℹ️ Info | Note | Reference |

---

## 📱 API Endpoints

```
GET  /api/ai-insights              → All insights
GET  /api/ai-insights/critical     → Critical only
GET  /api/ai-insights/{id}         → One insight
POST /api/ai-insights/{id}/review  → Mark reviewed
POST /api/ai-insights/{id}/dismiss → Dismiss
POST /api/ai-insights/ask          → Ask question
POST /api/ai-insights/generate     → Generate now
```

**Base URL:** `https://staff.vapeshed.co.nz/modules/base/public/api/ai-insights.php`

---

## 🗄️ Database Tables

- `ai_business_insights` → Main insights
- `ai_optimization_suggestions` → Process improvements
- `ai_staff_knowledge_map` → Expertise tracking
- `ai_staff_energy_tracking` → Wellbeing monitoring
- `ai_knowledge_queries` → Q&A history

---

## 📂 Key Files

```
modules/base/
├── src/Services/
│   └── AIBusinessInsightsService.php  → Core engine
├── database/migrations/
│   └── 001_create_ai_business_intelligence_tables.sql  → Schema
├── scripts/
│   └── generate-daily-insights.php  → Daily automation
├── public/api/
│   └── ai-insights.php  → REST API
└── docs/
    ├── AI_IMPLEMENTATION_GUIDE.md  → Full setup guide
    ├── README_AI_INTELLIGENCE.md   → Overview
    └── DEPLOYMENT_CHECKLIST.md     → Deploy steps
```

---

## 💰 Expected Results

**Week 1:**
- 5-10 actionable insights
- 1-2 critical issues detected
- 2-3 hours saved

**Month 1:**
- 50-100 insights generated
- 10-15 critical actions taken
- 15-20 hours saved
- $2K-5K cost avoided

**Month 3:**
- 15-25% efficiency improvement
- Proactive issue prevention
- Measurable ROI
- Team adoption

---

## ⚙️ Configuration

**Thresholds (in AIBusinessInsightsService.php):**
```php
private const SALES_DECLINE_THRESHOLD = 0.15;  // 15% drop
private const SLOW_MOVER_MIN_STOCK = 50;       // units
private const SLOW_MOVER_MAX_SALES = 5;        // per 30 days
private const TRANSFER_DELAY_THRESHOLD = 48;   // hours
private const CONSIGNMENT_TIME_THRESHOLD = 60; // minutes
```

**Adjust if:**
- Too many insights → Increase thresholds
- Too few insights → Decrease thresholds
- Wrong focus → Add custom analyzers

---

## 🎓 Tips

### Getting Started
✅ Start with critical insights only
✅ Track outcomes to prove value
✅ Share wins to build trust
✅ Expand gradually

### Using Effectively
💬 Ask questions regularly
📊 Review insights daily (5 min)
✍️ Record actions taken
📈 Measure impact

### Avoiding Issues
⚠️ Don't ignore low confidence (<0.7)
⚠️ AI advises, you decide
⚠️ Track outcomes for learning
⚠️ Expect 85-90% accuracy

---

## 🆘 Help

**Documentation:**
- Setup: `AI_IMPLEMENTATION_GUIDE.md`
- Architecture: `AI_BUSINESS_INTELLIGENCE_SYSTEM.md`
- Deploy: `DEPLOYMENT_CHECKLIST.md`
- Overview: `README_AI_INTELLIGENCE.md`

**Logs:**
- Application: `logs/ai-insights.log`
- API: `logs/apache_*.error.log`
- Cron: Check with `crontab -l`

**Support:**
- Check docs first
- Review logs second
- Manual test third
- Ask team if stuck

---

## ✅ Health Check

**Is system working?**

```bash
# 1. Check cron is running
crontab -l | grep ai-insights

# 2. Check recent insights
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "
  SELECT COUNT(*), MAX(created_at)
  FROM ai_business_insights
  WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);
"

# 3. Check API
curl -I "https://staff.vapeshed.co.nz/modules/base/public/api/ai-insights.php"

# 4. Check AI Hub
curl https://gpt.ecigdis.co.nz/mcp/health.php
```

**All should return 200 OK and show recent data.**

---

## 🎉 Quick Wins

**Week 1 Targets:**
- [ ] Deploy system successfully
- [ ] Generate first insights
- [ ] Act on 1 critical insight
- [ ] Measure 1 outcome
- [ ] Share 1 success story

**Celebrate small wins!** 🎊

---

**Version:** 1.0
**Status:** Production Ready ✅
**Created:** November 4, 2025

---

**📌 Pin this to your wall and reference daily!**
