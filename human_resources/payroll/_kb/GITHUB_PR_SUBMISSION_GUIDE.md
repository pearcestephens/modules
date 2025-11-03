# 🚀 GitHub PR Submission Guide - Both Modules

**Created:** November 2, 2025
**Strategy:** Submit TWO separate PRs to GitHub for parallel AI Agent work

---

## 📋 QUICK STEPS

### 1. Create Consignments Branch (5 minutes)

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules

# Create new branch for consignments fixes
git checkout -b consignments-critical-fixes-20251102

# Commit something small to make branch non-empty (GitHub requires this)
touch consignments/docs/.gitkeep
git add consignments/docs/.gitkeep
git commit -m "chore: initialize consignments critical fixes branch"

# Push to GitHub
git push -u origin consignments-critical-fixes-20251102
```

---

### 2. Create Payroll Branch (5 minutes)

```bash
# Go to main/master branch first
git checkout main  # or master, depending on your default branch

# Create new branch for payroll sprint
git checkout -b payroll-sprint-phases-1-6-20251102

# Commit something small
touch human_resources/payroll/docs/.gitkeep
git add human_resources/payroll/docs/.gitkeep
git commit -m "chore: initialize payroll foundation sprint branch"

# Push to GitHub
git push -u origin payroll-sprint-phases-1-6-20251102
```

---

### 3. Create PR #1 - Consignments (5 minutes)

**Go to GitHub:**
1. Navigate to: `https://github.com/pearcestephens/modules`
2. Click **"Pull requests"** tab
3. Click **"New pull request"** button
4. Set:
   - **Base:** `main` (or your default branch)
   - **Compare:** `consignments-critical-fixes-20251102`
5. Click **"Create pull request"**

**Fill in PR form:**
- **Title:** `fix(consignments): Critical blockers - Queue worker + Webhooks + State validation`
- **Description:** Copy ENTIRE contents from:
  `/modules/human_resources/payroll/_kb/PR_DESCRIPTION_CONSIGNMENTS_CRITICAL_FIXES.md`
- **Labels:** Add `bug`, `critical`, `consignments`, `AI Agent`
- **Assignees:** Assign to yourself + mention `@github` for AI Agent
- Click **"Create pull request"**

---

### 4. Create PR #2 - Payroll (5 minutes)

**Go to GitHub:**
1. Still on `https://github.com/pearcestephens/modules`
2. Click **"Pull requests"** tab
3. Click **"New pull request"** button
4. Set:
   - **Base:** `main`
   - **Compare:** `payroll-sprint-phases-1-6-20251102`
5. Click **"Create pull request"**

**Fill in PR form:**
- **Title:** `feat(payroll): Foundation sprint - Phases 1-6 (Schema + Services + Intake + Payments)`
- **Description:** Copy ENTIRE contents from:
  `/modules/human_resources/payroll/_kb/PR_DESCRIPTION_PAYROLL_FOUNDATION_SPRINT.md`
- **Labels:** Add `enhancement`, `payroll`, `AI Agent`
- **Assignees:** Assign to yourself + mention `@github` for AI Agent
- Click **"Create pull request"**

---

### 5. Activate GitHub AI Agent (10 minutes)

**In each PR, add a comment:**

```markdown
@github I need your help building this feature.

**Context:**
- This is a production codebase for The Vape Shed (retail/inventory management)
- We have a tight Tuesday deadline (Nov 5, 2025)
- Complete specifications are in the PR description

**Your task:**
Please implement all items in the checklist above following these guidelines:
1. Follow existing code patterns in the repo (PSR-12, strict types)
2. Add comprehensive tests for all new code
3. Use existing helpers: `lib/Respond.php`, `lib/Validate.php`, `lib/Idempotency.php`
4. Log all actions with correlation IDs
5. Add PHPDoc comments to all methods
6. Create migrations for all schema changes
7. Test integration points (Deputy API, Xero API, Lightspeed API)

**Questions?**
Tag me (@pearcestephens) in comments if you need clarification.

Let me know when you're ready to start and I'll answer any questions!
```

**GitHub AI Agent should respond within minutes saying it's starting work.**

---

## 📊 What Happens Next?

### Expected Timeline

**PR #1 (Consignments):**
- AI Agent starts: Within 10 minutes
- First commits: Within 1 hour
- Substantial progress: 6-8 hours
- **Complete:** Sunday morning (Nov 3) - 17-18 hours total

**PR #2 (Payroll):**
- AI Agent starts: Within 10 minutes
- First commits: Within 1 hour
- Substantial progress: 8-10 hours
- **Complete:** Monday evening (Nov 4) - 20-24 hours total

**Both PRs run in PARALLEL** - AI Agent can work on both simultaneously!

---

## 🔍 How to Monitor Progress

### Check PR Activity
1. Go to each PR page
2. Watch the **"Commits"** tab - New commits = progress
3. Watch **"Files changed"** tab - See what's being built
4. Watch **"Conversation"** tab - AI Agent will post updates

### Review Code as It's Built
```bash
# Fetch both branches
git fetch origin

# Review consignments work
git checkout consignments-critical-fixes-20251102
git pull
# Review files in consignments/

# Review payroll work
git checkout payroll-sprint-phases-1-6-20251102
git pull
# Review files in human_resources/payroll/
```

### Provide Feedback
When AI Agent posts commits:
1. Review the code changes
2. Add comments on specific lines if issues found
3. AI Agent will address your feedback and push new commits

---

## ✅ When PRs Are Ready

### PR #1 (Consignments) - Sunday Morning
**Checklist before merging:**
- [ ] All critical tasks checked ✅ in PR description
- [ ] Tests pass (`Run tests` action succeeds)
- [ ] Queue worker config exists and tested
- [ ] Webhook endpoint tested with Lightspeed sandbox
- [ ] State validation prevents illegal transitions
- [ ] CSRF tokens added to all forms
- [ ] No secrets in code (grep returns clean)
- [ ] Staging deployment succeeds

**When ready:**
1. Deploy to staging first
2. Run smoke tests (all 11 API endpoints)
3. If all pass, merge PR
4. Deploy to production

---

### PR #2 (Payroll) - Monday Evening
**Checklist before merging:**
- [ ] All phase tasks checked ✅ in PR description
- [ ] All 9 tables created (schema verified)
- [ ] All 4 services implemented and tested
- [ ] Can import timesheets from Deputy sandbox
- [ ] Pay calculations correct (verified with test data)
- [ ] Xero integration creates pay run in sandbox
- [ ] Error handling works (DLQ captures failures)
- [ ] All unit tests pass (30+ tests)
- [ ] Health endpoint returns payroll status

**When ready:**
1. Test end-to-end flow in staging:
   - Import timesheet from Deputy
   - Calculate pay
   - Create pay run in Xero
   - Verify amounts match
2. If all pass, merge PR
3. Deploy to production

---

## 🚨 If AI Agent Gets Stuck

### Common Issues & Solutions

**Issue:** AI Agent asks for clarification
**Solution:** Answer questions in PR comments immediately

**Issue:** Tests fail
**Solution:** Review failure logs, provide guidance on fix

**Issue:** AI Agent makes wrong assumption
**Solution:** Correct in comment: "Actually, we use X pattern, not Y. Here's an example from [file]..."

**Issue:** Integration tests fail (Deputy/Xero)
**Solution:** Check credentials in `.env`, verify sandbox access

**Issue:** AI Agent goes silent
**Solution:**
1. Check if waiting for your response
2. Tag again: `@github are you still working on this?`
3. Provide encouragement: `@github great progress so far! Please continue with [next task]`

---

## 💡 Pro Tips

### 1. Review Early, Review Often
Don't wait for PR completion. Review commits as they come in. Faster feedback = faster completion.

### 2. Provide Examples
If AI Agent struggles, paste example code from similar working files:
```markdown
@github For reference, see how we do CSRF validation in:
`consignments/app/api/transfers/create.php` lines 15-25
```

### 3. Prioritize Critical Tasks
If time is tight, comment:
```markdown
@github Focus on tasks 1-3 first (critical blockers).
Tasks 7-9 (medium priority) can be deferred if needed.
```

### 4. Test Integration Points Yourself
While AI Agent builds, test Deputy/Xero sandbox access:
```bash
# Test Deputy API
curl -H "Authorization: Bearer $DEPUTY_TOKEN" \
  https://api.deputy.com/api/v1/resource/Timesheet

# Test Xero API
curl -H "Authorization: Bearer $XERO_TOKEN" \
  https://api.xero.com/api.xro/2.0/PayRuns
```

### 5. Have Rollback Plan
Before merging, ensure you can rollback:
```bash
# Tag current production state
git tag production-backup-20251102
git push origin production-backup-20251102

# If issues after merge, rollback:
git revert <merge-commit-sha>
```

---

## 📈 Success Metrics

### PR #1 (Consignments) Success Criteria
- ✅ Queue worker processes 10 jobs in < 5 minutes
- ✅ Webhook receives test event from Lightspeed
- ✅ State transition validation blocks illegal changes (422 error)
- ✅ CSRF validation blocks requests without token (403 error)
- ✅ All 11 API endpoints return 200 for valid input
- ✅ Staging smoke test: 100% pass rate

### PR #2 (Payroll) Success Criteria
- ✅ Schema created: 9 tables with proper indexes
- ✅ Services working: All 4 services respond without errors
- ✅ Deputy import: Successfully imports 10 test timesheets
- ✅ Pay calculation: Matches manual calculation (within $0.01)
- ✅ Xero sync: Creates pay run in sandbox with correct amounts
- ✅ Error handling: Failed operation moves to DLQ
- ✅ Tests: 30+ tests pass, 0 failures
- ✅ End-to-end: Complete flow works (timesheet → pay → Xero)

---

## 🎯 Timeline Summary

| Day | Time | Activity | Milestone |
|-----|------|----------|-----------|
| **Sat Nov 2** | 10:00 AM | Create branches + PRs | PRs submitted ✅ |
| **Sat Nov 2** | 11:00 AM | AI Agent starts work | Both PRs active ✅ |
| **Sat Nov 2** | 6:00 PM | Check progress | First commits visible ✅ |
| **Sun Nov 3** | 8:00 AM | PR #1 review | Consignments substantial progress |
| **Sun Nov 3** | 12:00 PM | PR #1 complete | Consignments ready for staging ✅ |
| **Sun Nov 3** | 2:00 PM | Deploy consignments staging | Smoke tests pass ✅ |
| **Sun Nov 3** | 6:00 PM | PR #2 review | Payroll substantial progress |
| **Mon Nov 4** | 8:00 AM | Continue PR #2 | Payroll nearing completion |
| **Mon Nov 4** | 6:00 PM | PR #2 complete | Payroll ready for staging ✅ |
| **Mon Nov 4** | 8:00 PM | Deploy payroll staging | End-to-end tests pass ✅ |
| **Tue Nov 5** | 9:00 AM | Merge both PRs | Both in production ✅ |
| **Tue Nov 5** | 12:00 PM | Production smoke tests | Deadline met! 🎉 |

---

## 📞 Need Help?

### During Setup (Today)
If branches/PRs fail to create, check:
- Git configured correctly: `git config --list`
- Remote set: `git remote -v`
- Permissions: Can you push to `pearcestephens/modules`?

### During AI Agent Work (Sat-Mon)
If AI Agent struggles, you can:
1. Provide more context in PR comments
2. Push starter code to help AI Agent (example files)
3. Ask specific questions in comments
4. Tag me (@pearcestephens) if stuck

### Emergency Fallback
If GitHub AI Agent can't complete by Monday evening:
1. We have all specifications documented
2. You can complete manually using PR descriptions as guides
3. Prioritize critical items, defer medium priority

---

## 🚀 READY TO LAUNCH!

**Execute these 5 steps now:**

1. ✅ Create consignments branch + push
2. ✅ Create payroll branch + push
3. ✅ Create PR #1 (consignments) with full description
4. ✅ Create PR #2 (payroll) with full description
5. ✅ Activate AI Agent on both PRs with comment

**Estimated time:** 30 minutes total

**After that, AI Agent works overnight while you sleep! 😴**

---

**Good luck! Let me know when PRs are created and I can monitor progress with you! 🎯**
