# Theme Builder IDE - Production Deployment Checklist
## Step-by-Step Verification Guide

**Created:** 2025-10-27
**Version:** 1.0.0
**Purpose:** Ensure all requirements met before production deployment

---

## ✅ Pre-Deployment Phase (48 Hours Before)

### Code Quality

- [ ] **Code Review**
  - [ ] All pull requests merged and approved
  - [ ] No code review comments outstanding
  - [ ] Changes documented in CHANGELOG

- [ ] **Static Analysis**
  - [ ] PHPStan analysis clean (0 errors)
  - [ ] PHPCS style check passing
  - [ ] No security warnings in SAST scan

- [ ] **Linting**
  - [ ] PHP files: `php -l` all pass
  - [ ] JavaScript: ESLint clean
  - [ ] CSS: Stylelint clean

### Testing

- [ ] **Unit Tests**
  - [ ] All 151 endpoint tests passing
  - [ ] 100% pass rate achieved
  - [ ] No skipped tests

- [ ] **Integration Tests**
  - [ ] 27 user flows validated
  - [ ] All 6 API endpoints responding
  - [ ] Database queries optimized

- [ ] **Security Tests**
  - [ ] Blocklist enforcement verified (20+ functions)
  - [ ] SQL injection tests passing
  - [ ] XSS prevention working
  - [ ] CSRF protection enabled

- [ ] **Performance Tests**
  - [ ] All operations < target times
  - [ ] Validation: < 20ms ✓
  - [ ] Formatting: < 10ms ✓
  - [ ] Minification: < 100ms ✓
  - [ ] File operations: < 100ms ✓
  - [ ] PHP execution: < 50ms ✓
  - [ ] AI operations: < 300ms ✓

### Documentation

- [ ] **API Documentation**
  - [ ] All 6 endpoints documented
  - [ ] Request/response examples provided
  - [ ] Error codes explained

- [ ] **User Documentation**
  - [ ] User guide complete
  - [ ] Keyboard shortcuts documented
  - [ ] Troubleshooting guide included

- [ ] **Developer Documentation**
  - [ ] Architecture diagram updated
  - [ ] Database schema documented
  - [ ] Security considerations listed
  - [ ] Deployment instructions clear

---

## 🔒 Security Phase (24 Hours Before)

### Security Audit

- [ ] **Dependency Check**
  - [ ] All packages up-to-date
  - [ ] No known vulnerabilities in dependencies
  - [ ] Composer/npm lock files frozen

- [ ] **Secrets Management**
  - [ ] No secrets in code repositories
  - [ ] All credentials in `.env` only
  - [ ] Environment variables documented

- [ ] **Access Control**
  - [ ] Admin endpoints require auth
  - [ ] File operations limited to safe directories
  - [ ] Database user permissions correct
  - [ ] File permissions locked (644 files, 755 dirs)

- [ ] **Data Protection**
  - [ ] No PII logged or exposed
  - [ ] Sensitive data masked in logs
  - [ ] HTTPS enforced across all endpoints
  - [ ] HSTS header enabled

### Compliance

- [ ] **Regulatory**
  - [ ] GDPR compliance verified
  - [ ] Data retention policies documented
  - [ ] Privacy policy updated

- [ ] **Standards**
  - [ ] WCAG 2.1 AA accessibility
  - [ ] CSP headers configured
  - [ ] CORS properly configured
  - [ ] Security headers in place

---

## 🚀 Infrastructure Phase (12 Hours Before)

### Server Preparation

- [ ] **Web Server**
  - [ ] PHP 8.1+ installed and tested
  - [ ] Apache/Nginx configuration validated
  - [ ] SSL certificate valid (not expired)
  - [ ] HTTPS redirects working

- [ ] **Database**
  - [ ] Database backed up (recent backup exists)
  - [ ] Database user permissions correct
  - [ ] Slow query logging enabled
  - [ ] Connection pooling configured

- [ ] **Monitoring**
  - [ ] APM agent installed (if applicable)
  - [ ] Log aggregation configured
  - [ ] Error tracking enabled (Sentry/similar)
  - [ ] Uptime monitoring active

- [ ] **Backups**
  - [ ] Full filesystem backup created
  - [ ] Database backup created and tested
  - [ ] Backup restoration procedure verified
  - [ ] Backup retention policy documented

### Capacity Planning

- [ ] **Resources**
  - [ ] CPU: Adequate for peak load
  - [ ] Memory: 50% headroom available
  - [ ] Disk: 80% free space minimum
  - [ ] Network: Adequate bandwidth

- [ ] **Load Testing**
  - [ ] 100 concurrent users tested
  - [ ] Response times acceptable
  - [ ] No memory leaks detected
  - [ ] Database queries optimized

---

## 📋 Final Phase (24 Hours Before)

### Deployment Readiness

- [ ] **Deployment Plan**
  - [ ] Deployment strategy defined (blue-green/canary)
  - [ ] Rollback procedure documented
  - [ ] Deployment timeline scheduled
  - [ ] Communication plan ready

- [ ] **Smoke Tests**
  - [ ] Critical tests identified (22 tests)
  - [ ] Smoke test script created
  - [ ] Manual smoke tests documented

- [ ] **Communication**
  - [ ] Stakeholders notified
  - [ ] Team trained on procedures
  - [ ] Support team briefed on changes
  - [ ] Incident response plan ready

---

## 🎯 Day-Of Deployment (2 Hours Before Start)

### Final Verification

- [ ] **All Previous Checks**
  - [ ] Re-run critical tests (22 tests)
  - [ ] Verify backups one final time
  - [ ] Check monitoring systems online
  - [ ] Verify rollback procedure

- [ ] **Team Assembly**
  - [ ] DevOps team present
  - [ ] Development team available
  - [ ] QA team on standby
  - [ ] Support team briefed

- [ ] **Communication Channels**
  - [ ] Slack channel active (#deployment)
  - [ ] Status page ready
  - [ ] Incident channel open (#incidents)
  - [ ] Video call connected (if needed)

---

## 🚀 Deployment Execution

### Step 1: Pre-Deployment (15 min)

**Time: T-15 minutes**

```bash
# 1. Final test run
cd /modules/admin-ui/tests
bash run-tests.sh --critical

# 2. Verify all tests pass
# Expected: 22/22 tests pass in < 30 seconds

# 3. Create pre-deployment snapshot
tar -czf /var/backups/admin-ui.pre-deploy.$(date +%s).tar.gz modules/admin-ui/

# 4. Verify backups
ls -lh /var/backups/admin-ui*
```

**Checklist:**
- [ ] All critical tests pass
- [ ] Backup created successfully
- [ ] No errors in test output

---

### Step 2: Deployment (30-45 min)

**Time: T-0 minutes**

```bash
# 1. Stop services (if needed)
systemctl stop admin-ui-worker || true

# 2. Deploy new version (using blue-green)
bash scripts/deploy-production.sh

# 3. Run endpoint validation
cd /modules/admin-ui/tests
bash run-tests.sh --validate

# 4. Verify endpoints online
curl -I http://localhost/modules/admin-ui/api/

# 5. Start services
systemctl start admin-ui-worker
```

**Checklist:**
- [ ] Deployment script completed successfully
- [ ] All 6 endpoints responding
- [ ] Services started
- [ ] No error messages

---

### Step 3: Post-Deployment Verification (15 min)

**Time: T+15 minutes**

```bash
# 1. Run full smoke tests
cd /modules/admin-ui/tests
bash run-tests.sh --all

# 2. Check application logs
tail -50 /logs/apache_*.error.log | grep -i error

# 3. Monitor performance
curl http://localhost/modules/admin-ui/metrics

# 4. User acceptance tests
# (Manually test key workflows - 3 minutes)
```

**Checklist:**
- [ ] All 151 tests pass
- [ ] No new errors in logs
- [ ] Performance metrics normal
- [ ] Manual tests successful
- [ ] Status page updated

---

### Step 4: Continue Monitoring (60 min post-deployment)

**Time: T+60 minutes**

```bash
# 1. Check error rate (should be < 0.1%)
curl http://localhost/modules/admin-ui/metrics | grep error_rate

# 2. Check response times (should be < 50ms avg)
curl http://localhost/modules/admin-ui/metrics | grep response_time

# 3. Check active users
curl http://localhost/modules/admin-ui/metrics | grep active_users

# 4. Review logs for issues
tail -100 /logs/apache_*.error.log
```

**Expected Behavior:**
- Error rate: < 0.1% ✓
- Response times: < 50ms avg ✓
- Active users: Normal for time of day ✓
- Logs: No error spikes ✓

---

## 🚨 Rollback Procedures

### Quick Rollback (< 2 minutes)

If critical issues detected:

```bash
#!/bin/bash

echo "🔄 ROLLING BACK..."

# 1. Switch to previous version (symlink)
ln -sf /var/backups/admin-ui.blue /srv/www/admin-ui

# 2. Restart services
systemctl restart admin-ui-worker

# 3. Verify
curl -f http://localhost/modules/admin-ui/api/ || {
    echo "❌ Rollback failed!"
    exit 1
}

echo "✅ Rollback complete!"
```

### Full Rollback (< 5 minutes)

If comprehensive rollback needed:

```bash
#!/bin/bash

BACKUP_TIME=${1:-"latest"}
BACKUP_FILE="/var/backups/admin-ui.pre-deploy.$BACKUP_TIME.tar.gz"

echo "🔄 Full rollback from $BACKUP_FILE..."

# 1. Extract backup
tar -xzf "$BACKUP_FILE" -C /srv/www/

# 2. Restore database (if needed)
# mysql < /var/backups/database.sql

# 3. Clear caches
rm -rf /var/cache/admin-ui/*

# 4. Restart services
systemctl restart admin-ui-worker

# 5. Verify
bash /modules/admin-ui/tests/run-tests.sh --critical

echo "✅ Full rollback complete!"
```

**Rollback Triggers:**
- [ ] Error rate > 1% for 2 minutes
- [ ] Response time p95 > 500ms for 5 minutes
- [ ] Critical endpoint failing (> 50 errors/min)
- [ ] Memory leak detected (> 90% usage)
- [ ] Database connection failures
- [ ] Manual decision by DevOps lead

---

## 📊 Post-Deployment Validation

### Hour 1

- [ ] **System Health**
  - [ ] CPU usage: < 50%
  - [ ] Memory usage: < 70%
  - [ ] Disk I/O: normal
  - [ ] Network I/O: normal

- [ ] **Application Metrics**
  - [ ] Error rate: < 0.1%
  - [ ] Response time avg: < 50ms
  - [ ] Response time p95: < 100ms
  - [ ] Requests per second: as expected

- [ ] **User Experience**
  - [ ] No user-reported issues in support chat
  - [ ] UI loads correctly
  - [ ] All features responding

### Hour 4

- [ ] **Database**
  - [ ] Query performance normal
  - [ ] No deadlocks detected
  - [ ] Replication lag: < 1 second (if applicable)
  - [ ] Disk usage growth: expected rate

- [ ] **Security**
  - [ ] No security alerts
  - [ ] API rate limits working
  - [ ] No suspicious access patterns
  - [ ] Authentication/authorization working

### Day 1

- [ ] **Stability**
  - [ ] No memory leaks (memory stable)
  - [ ] No connection pool exhaustion
  - [ ] Error rate remains low
  - [ ] Performance consistent

- [ ] **Business Metrics**
  - [ ] Feature adoption: as expected
  - [ ] User retention: baseline or better
  - [ ] Conversion rate: no negative impact
  - [ ] User satisfaction: positive feedback

---

## 📝 Documentation

### Update These After Deployment

- [ ] `CHANGELOG.md` - Add deployment entry
- [ ] `README.md` - Update version number
- [ ] `DEPLOYMENT.md` - Update deployment history
- [ ] Internal wiki - Mark version as live
- [ ] Status page - Update to "operational"

### Log the Following

- [ ] Deployment start time
- [ ] Deployment end time
- [ ] Total duration
- [ ] Team members involved
- [ ] Any rollbacks performed
- [ ] Key metrics pre/post deployment
- [ ] User feedback
- [ ] Issues encountered and resolved

---

## ✅ Final Sign-Off

### Deployment Manager Sign-Off

- [ ] All checklists completed
- [ ] All tests passing
- [ ] No critical issues
- [ ] Ready for production

**Name:** ________________
**Date:** ________________
**Time:** ________________

### Product Manager Sign-Off

- [ ] Feature requirements met
- [ ] Quality acceptable
- [ ] Performance acceptable
- [ ] User experience satisfactory

**Name:** ________________
**Date:** ________________
**Time:** ________________

---

## 📞 Support Contacts

### During Deployment

| Role | Name | Contact | Status |
|------|------|---------|--------|
| DevOps Lead | [Name] | [Phone/Slack] | On-call |
| Backend Lead | [Name] | [Phone/Slack] | Available |
| Frontend Lead | [Name] | [Phone/Slack] | Available |
| DBA | [Name] | [Phone/Slack] | Standby |

### Escalation Path

1. **Level 1:** Notify team leads (Slack channel)
2. **Level 2:** Page DevOps on-call
3. **Level 3:** Notify VP of Engineering
4. **Level 4:** Execute rollback

---

## 🎯 Success Criteria

### Deployment is **SUCCESSFUL** if:

✅ All 151 tests passing
✅ Error rate < 0.1% for 1 hour
✅ Response times < 50ms average
✅ Zero critical issues
✅ No user complaints
✅ All monitoring green
✅ Performance baseline met

### Deployment is **FAILED** if:

❌ Any critical test fails
❌ Error rate > 1% for 5 min
❌ Response times > 500ms
❌ API endpoints down
❌ Database issues
❌ Memory leak detected
❌ Security issue found

---

## 📋 Quick Reference

### Critical Commands

```bash
# Run all tests
bash /modules/admin-ui/tests/run-tests.sh --all

# Run critical tests only (< 1 min)
bash /modules/admin-ui/tests/run-tests.sh --critical

# Check endpoints
bash /modules/admin-ui/tests/run-tests.sh --validate

# View logs
tail -100 /logs/apache_*.error.log

# Check resources
free -h
df -h
top -b -n1

# Quick rollback
ln -sf /var/backups/admin-ui.blue /srv/www/admin-ui
systemctl restart admin-ui-worker
```

---

## 📅 Deployment History

| Date | Version | Status | Notes |
|------|---------|--------|-------|
| 2025-10-27 | 1.0.0 | ✅ Ready | First production release |

---

**Version:** 1.0.0
**Last Updated:** 2025-10-27
**Status:** ✅ READY FOR DEPLOYMENT

---

## Print This Checklist!

Before deployment, print this checklist and check off items as you complete them. Keep a copy on hand throughout the deployment process.

```
[ ] Phase 1: Code Quality
[ ] Phase 2: Testing
[ ] Phase 3: Security
[ ] Phase 4: Infrastructure
[ ] Phase 5: Final Phase
[ ] Phase 6: Pre-Deployment (2 hours before)
[ ] Phase 7: Deployment
[ ] Phase 8: Verification
[ ] Phase 9: Monitoring
[ ] All sign-offs complete
```

🚀 **YOU ARE READY TO DEPLOY!**
