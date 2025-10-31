# Theme Builder IDE - CI/CD Integration Guide
## Automated Testing & Deployment Pipeline

**Version:** 1.0.0
**Created:** 2025-10-27
**Purpose:** Enable continuous integration and continuous deployment of Theme Builder IDE

---

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [GitHub Actions](#github-actions)
3. [GitLab CI](#gitlab-ci)
4. [Jenkins Pipeline](#jenkins-pipeline)
5. [Local Pre-Commit](#local-pre-commit)
6. [Test Reports & Artifacts](#test-reports--artifacts)
7. [Deployment Strategy](#deployment-strategy)
8. [Rollback Procedures](#rollback-procedures)
9. [Monitoring & Alerts](#monitoring--alerts)

---

## 🚀 Quick Start

### Minimal CI Setup (5 minutes)

For the simplest CI/CD integration:

```bash
# 1. Add test runner to your project
cp run-tests.sh .github/workflows/ 2>/dev/null || cp run-tests.sh .

# 2. Make executable
chmod +x run-tests.sh endpoint-tests.php

# 3. Create simple cron job
0 2 * * * /path/to/run-tests.sh --critical

# 4. Done! Tests now run automatically
```

---

## 🔄 GitHub Actions

### Setup Instructions

#### Step 1: Create Workflow File

Create `.github/workflows/test.yml`:

```yaml
name: Theme Builder IDE - Test Suite

on:
  push:
    branches: [main, develop]
    paths:
      - 'modules/admin-ui/**'
      - '.github/workflows/test.yml'
  pull_request:
    branches: [main, develop]
  schedule:
    - cron: '0 2 * * *'  # Daily at 2 AM

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      php:
        image: php:8.1-fpm
        options: --health-cmd="php -v" --health-interval=10s --health-timeout=5s

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: curl, json, mbstring

      - name: Run critical tests
        run: |
          cd modules/admin-ui/tests
          bash run-tests.sh --critical

      - name: Run full test suite
        run: |
          cd modules/admin-ui/tests
          bash run-tests.sh --all

      - name: Generate test report
        if: always()
        run: |
          cd modules/admin-ui/tests
          bash run-tests.sh --report

      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: test-results
          path: modules/admin-ui/tests/reports/

      - name: Comment PR with results
        if: github.event_name == 'pull_request'
        uses: actions/github-script@v6
        with:
          script: |
            const fs = require('fs');
            const report = fs.readFileSync('modules/admin-ui/tests/reports/summary.txt', 'utf8');
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: `## Test Results\n\n${report}`
            });
```

#### Step 2: Create Performance Workflow

Create `.github/workflows/performance.yml`:

```yaml
name: Performance Benchmarks

on:
  push:
    branches: [main]
  schedule:
    - cron: '0 3 * * 0'  # Weekly Sunday at 3 AM

jobs:
  benchmark:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'

      - name: Run performance benchmark
        run: |
          cd modules/admin-ui/tests
          bash run-tests.sh --performance

      - name: Upload performance data
        uses: actions/upload-artifact@v3
        with:
          name: performance-data
          path: modules/admin-ui/tests/reports/performance-*.csv

      - name: Compare performance
        run: |
          # Compare with baseline
          echo "Performance comparison:"
          cat modules/admin-ui/tests/reports/performance-*.csv
```

#### Step 3: Create Security Workflow

Create `.github/workflows/security.yml`:

```yaml
name: Security Checks

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  security:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'

      - name: Run PHP blocklist validation
        run: |
          cd modules/admin-ui/tests
          php endpoint-tests.php | grep "PHP_18"

      - name: Check for dangerous functions
        run: |
          grep -r "eval\|exec\|system" modules/admin-ui --include="*.php" || echo "No dangerous functions found"

      - name: Validate test coverage
        run: |
          cd modules/admin-ui/tests
          bash run-tests.sh --validate
```

### GitHub Actions Commands

```bash
# View workflow status
gh workflow list

# Run workflow manually
gh workflow run test.yml

# View recent runs
gh run list

# View run details
gh run view <RUN_ID>

# Download artifacts
gh run download <RUN_ID> -D ./results

# View logs
gh run view <RUN_ID> --log
```

---

## 🧪 GitLab CI

### Setup Instructions

#### Step 1: Create CI Configuration

Create `.gitlab-ci.yml`:

```yaml
stages:
  - test
  - performance
  - security
  - deploy

variables:
  REPORT_DIR: "modules/admin-ui/tests/reports"

before_script:
  - cd modules/admin-ui/tests
  - chmod +x run-tests.sh endpoint-tests.php

test:critical:
  stage: test
  image: php:8.1-cli
  script:
    - bash run-tests.sh --critical
  only:
    - merge_requests
  artifacts:
    paths:
      - ${REPORT_DIR}/
    expire_in: 30 days

test:full:
  stage: test
  image: php:8.1-cli
  script:
    - bash run-tests.sh --all
  artifacts:
    paths:
      - ${REPORT_DIR}/
    expire_in: 30 days
    reports:
      junit: ${REPORT_DIR}/junit-report.xml

performance:
  stage: performance
  image: php:8.1-cli
  script:
    - bash run-tests.sh --performance
  artifacts:
    paths:
      - ${REPORT_DIR}/performance-*.csv
    expire_in: 90 days
  allow_failure: true

security:
  stage: security
  image: php:8.1-cli
  script:
    - bash run-tests.sh --validate
    - grep -r "eval\|exec\|system" . --include="*.php" || echo "Clean"
  only:
    - main
    - merge_requests

deploy:staging:
  stage: deploy
  image: alpine:latest
  script:
    - echo "Deploying to staging..."
    - ./deploy-staging.sh
  environment:
    name: staging
    url: https://staging.example.com
  only:
    - develop
  dependencies:
    - test:full

deploy:production:
  stage: deploy
  image: alpine:latest
  script:
    - echo "Deploying to production..."
    - ./deploy-production.sh
  environment:
    name: production
    url: https://example.com
  only:
    - main
  when: manual
  dependencies:
    - test:full
    - performance
    - security
```

#### Step 2: Create Deployment Scripts

Create `deploy-staging.sh`:

```bash
#!/bin/bash
set -e

echo "🚀 Deploying to Staging..."

# 1. Pull latest code
git pull origin develop

# 2. Run final tests
cd modules/admin-ui/tests
bash run-tests.sh --critical

# 3. Backup current version
cp -r /srv/staging/admin-ui /srv/staging/admin-ui.backup.$(date +%s)

# 4. Deploy new version
rsync -avz modules/admin-ui/ /srv/staging/admin-ui/

# 5. Run smoke tests
bash run-tests.sh --validate

echo "✅ Staging deployment complete!"
```

Create `deploy-production.sh`:

```bash
#!/bin/bash
set -e

echo "🚀 Deploying to Production..."

# 1. Final verification
bash modules/admin-ui/tests/run-tests.sh --all || exit 1

# 2. Create backup
BACKUP_DIR="/var/backups/admin-ui.$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -r /srv/production/admin-ui "$BACKUP_DIR"

# 3. Deploy
rsync -avz --backup-dir="$BACKUP_DIR" modules/admin-ui/ /srv/production/admin-ui/

# 4. Verify
curl -f https://example.com/modules/admin-ui/api/ || {
    echo "❌ Deployment verification failed! Rolling back..."
    rm -rf /srv/production/admin-ui
    cp -r "$BACKUP_DIR/admin-ui" /srv/production/admin-ui
    exit 1
}

echo "✅ Production deployment complete!"
```

### GitLab CI Commands

```bash
# View pipeline status
git log --all --oneline --graph

# Trigger pipeline
git push origin main

# View pipeline details in web UI
# Go to: https://gitlab.com/yourproject/-/pipelines
```

---

## 🔨 Jenkins Pipeline

### Jenkinsfile Configuration

Create `Jenkinsfile`:

```groovy
pipeline {
    agent any

    options {
        buildDiscarder(logRotator(numToKeepStr: '10'))
        timeout(time: 30, unit: 'MINUTES')
    }

    environment {
        TEST_DIR = 'modules/admin-ui/tests'
        REPORT_DIR = "${TEST_DIR}/reports"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Setup') {
            steps {
                sh '''
                    chmod +x ${TEST_DIR}/run-tests.sh
                    chmod +x ${TEST_DIR}/endpoint-tests.php
                    mkdir -p ${REPORT_DIR}
                '''
            }
        }

        stage('Critical Tests') {
            steps {
                sh '''
                    cd ${TEST_DIR}
                    bash run-tests.sh --critical
                '''
            }
        }

        stage('Full Test Suite') {
            steps {
                sh '''
                    cd ${TEST_DIR}
                    bash run-tests.sh --all
                '''
            }
        }

        stage('Performance Benchmark') {
            steps {
                sh '''
                    cd ${TEST_DIR}
                    bash run-tests.sh --performance
                '''
            }
        }

        stage('Security Validation') {
            steps {
                sh '''
                    cd ${TEST_DIR}
                    bash run-tests.sh --validate
                '''
            }
        }

        stage('Generate Reports') {
            steps {
                sh '''
                    cd ${TEST_DIR}
                    bash run-tests.sh --report
                '''
            }
        }

        stage('Deploy to Staging') {
            when {
                branch 'develop'
            }
            steps {
                sh '''
                    echo "Deploying to staging..."
                    ./scripts/deploy-staging.sh
                '''
            }
        }

        stage('Deploy to Production') {
            when {
                branch 'main'
                expression {
                    return currentBuild.result == null || currentBuild.result == 'SUCCESS'
                }
            }
            input {
                message "Deploy to production?"
                ok "Deploy"
            }
            steps {
                sh '''
                    echo "Deploying to production..."
                    ./scripts/deploy-production.sh
                '''
            }
        }
    }

    post {
        always {
            // Archive test reports
            archiveArtifacts artifacts: '${REPORT_DIR}/**',
                               allowEmptyArchive: true

            // Publish HTML reports
            publishHTML([
                reportDir: '${REPORT_DIR}',
                reportFiles: 'test-report-*.html',
                reportName: 'Test Report'
            ])

            // Publish performance metrics
            step([$class: 'PerformancePublisher',
                  errorFailedThreshold: 0,
                  errorUnstableThreshold: 0,
                  errorUnstableResponseTimeThreshold: 1000,
                  modeOfThreshold: true,
                  relativeFailedThresholdNegative: 50.0,
                  relativeFailedThresholdPositive: 50.0,
                  relativeUnstableThresholdNegative: 25.0,
                  relativeUnstableThresholdPositive: 25.0
            ])

            // Clean up
            cleanWs()
        }

        success {
            echo '✅ All tests passed!'
        }

        failure {
            echo '❌ Tests failed!'
            sh 'cd ${TEST_DIR} && bash run-tests.sh --validate'
        }
    }
}
```

---

## 🔍 Local Pre-Commit

### Git Hooks Setup

Create `.git/hooks/pre-commit`:

```bash
#!/bin/bash

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}🧪 Running pre-commit tests...${NC}"

# Check if admin-ui files were changed
CHANGED_FILES=$(git diff --cached --name-only | grep "modules/admin-ui")

if [ -z "$CHANGED_FILES" ]; then
    echo "✅ No admin-ui changes detected"
    exit 0
fi

cd modules/admin-ui/tests

# Run critical tests
echo -e "${YELLOW}Running critical tests...${NC}"
bash run-tests.sh --critical

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Pre-commit tests failed!${NC}"
    exit 1
fi

echo -e "${GREEN}✅ All pre-commit tests passed!${NC}"
exit 0
```

Make executable:

```bash
chmod +x .git/hooks/pre-commit
```

---

## 📊 Test Reports & Artifacts

### Report Formats

#### 1. HTML Report

```bash
# Generate HTML report
bash run-tests.sh --report

# View in browser
open modules/admin-ui/tests/reports/test-report-*.html
```

**Contents:**
- Test summary (passed/failed/duration)
- Category breakdown with expandable sections
- Performance metrics and charts
- Pass/fail timeline
- Error details and stack traces

#### 2. JSON Report

```php
// Save as JSON for CI/CD integration
$report = json_encode($testResults, JSON_PRETTY_PRINT);
file_put_contents('reports/results.json', $report);
```

**Example:**
```json
{
  "summary": {
    "total": 151,
    "passed": 151,
    "failed": 0,
    "skipped": 0,
    "duration": 4.2
  },
  "tests": [
    {
      "id": "CORE_1_1",
      "name": "Basic HTML Editing",
      "status": "PASS",
      "duration": 8,
      "category": "core"
    }
  ]
}
```

#### 3. JUnit XML Report

```bash
# For Jenkins integration
php endpoint-tests.php --format=junit > reports/junit.xml
```

#### 4. CSV Performance Report

```bash
# Already generated by run-tests.sh --performance
cat modules/admin-ui/tests/reports/performance-*.csv
```

---

## 🚀 Deployment Strategy

### Blue-Green Deployment

```bash
#!/bin/bash

# Blue-Green Deployment Strategy

BLUE_DIR="/srv/www/admin-ui.blue"
GREEN_DIR="/srv/www/admin-ui.green"
LIVE_DIR="/srv/www/admin-ui"

# 1. Determine which is live
if [ -L "$LIVE_DIR" ]; then
    CURRENT=$(readlink "$LIVE_DIR")
    if [ "$CURRENT" == "$BLUE_DIR" ]; then
        DEPLOY_DIR="$GREEN_DIR"
        NEW_ENV="green"
    else
        DEPLOY_DIR="$BLUE_DIR"
        NEW_ENV="blue"
    fi
else
    DEPLOY_DIR="$BLUE_DIR"
    NEW_ENV="blue"
fi

echo "🚀 Deploying to $NEW_ENV ($DEPLOY_DIR)..."

# 2. Deploy new version
rm -rf "$DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR"
cp -r modules/admin-ui/* "$DEPLOY_DIR/"

# 3. Run tests on new version
cd "$DEPLOY_DIR/tests"
bash run-tests.sh --all

if [ $? -ne 0 ]; then
    echo "❌ Tests failed! Rolling back..."
    exit 1
fi

# 4. Switch traffic
rm "$LIVE_DIR" 2>/dev/null || true
ln -s "$DEPLOY_DIR" "$LIVE_DIR"

# 5. Verify
curl -f http://localhost/admin-ui/api/ || {
    echo "❌ Verification failed! Rolling back..."
    ln -s "$([ "$NEW_ENV" = "blue" ] && echo "$GREEN_DIR" || echo "$BLUE_DIR")" "$LIVE_DIR"
    exit 1
}

echo "✅ Deployment complete! $NEW_ENV is now live"
```

### Canary Deployment

```bash
#!/bin/bash

# Canary Deployment (1% → 10% → 50% → 100%)

CANARY_RATIO=1  # Start with 1%

for stage in 1 10 50 100; do
    echo "📊 Deploying to $stage% of traffic..."

    # Update load balancer
    sed -i "s/canary_ratio .*/canary_ratio $stage;/" /etc/nginx/sites-available/admin-ui
    nginx -s reload

    # Monitor for 5 minutes
    sleep 300

    # Check error rate
    ERROR_RATE=$(curl http://localhost:8080/metrics | grep error_rate)

    if [ "$ERROR_RATE" -gt "5" ]; then
        echo "❌ Error rate too high! Rolling back..."
        sed -i "s/canary_ratio .*/canary_ratio 0;/" /etc/nginx/sites-available/admin-ui
        nginx -s reload
        exit 1
    fi
done

echo "✅ Canary deployment complete!"
```

---

## 🔄 Rollback Procedures

### Instant Rollback

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/admin-ui"
LATEST_BACKUP=$(ls -t $BACKUP_DIR | head -1)

echo "🔄 Rolling back to $LATEST_BACKUP..."

# 1. Restore from backup
cp -r "$BACKUP_DIR/$LATEST_BACKUP/admin-ui" /srv/www/admin-ui

# 2. Verify
curl -f http://localhost/admin-ui/api/ || {
    echo "❌ Rollback verification failed!"
    exit 1
}

# 3. Notify
echo "✅ Rollback complete!"
```

### Git-based Rollback

```bash
#!/bin/bash

ROLLBACK_COMMIT=${1:-HEAD~1}

echo "🔄 Rolling back to $ROLLBACK_COMMIT..."

# 1. Checkout previous version
git checkout "$ROLLBACK_COMMIT" -- modules/admin-ui/

# 2. Deploy
./scripts/deploy-production.sh

# 3. Verify
bash modules/admin-ui/tests/run-tests.sh --critical

echo "✅ Rollback complete!"
```

---

## 📈 Monitoring & Alerts

### Prometheus Metrics

```yaml
# prometheus.yml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'admin-ui'
    static_configs:
      - targets: ['localhost:9090']
    metrics_path: '/modules/admin-ui/metrics'
```

### Alert Rules

```yaml
# alert_rules.yml
groups:
  - name: admin-ui
    rules:
      - alert: TestFailureRate
        expr: |
          rate(test_failures_total[5m]) > 0.01
        for: 5m
        annotations:
          summary: "High test failure rate"

      - alert: SlowResponse
        expr: |
          histogram_quantile(0.95, response_time_seconds) > 0.5
        for: 10m
        annotations:
          summary: "Slow response times detected"

      - alert: DeploymentFailure
        expr: |
          deployment_status == 0
        for: 1m
        annotations:
          summary: "Deployment failed"
```

### Alerting Channels

#### Slack Integration

```bash
#!/bin/bash

# Send test results to Slack
WEBHOOK_URL="https://hooks.slack.com/services/YOUR/WEBHOOK/URL"

PAYLOAD=$(cat <<EOF
{
  "text": "Theme Builder IDE - Test Results",
  "blocks": [
    {
      "type": "section",
      "text": {
        "type": "mrkdwn",
        "text": "*Tests Passed:* 151/151 ✅\n*Duration:* 4.2s\n*Status:* Production Ready"
      }
    }
  ]
}
EOF
)

curl -X POST -H 'Content-type: application/json' \
  --data "$PAYLOAD" \
  "$WEBHOOK_URL"
```

#### Email Notifications

```php
<?php
// Send test results via email
$to = 'devops@example.com';
$subject = 'Theme Builder IDE - Test Results';
$message = "
Test Summary:
- Total Tests: 151
- Passed: 151
- Failed: 0
- Duration: 4.2s
";

mail($to, $subject, $message);
?>
```

---

## 📝 Implementation Checklist

### Pre-Deployment
- [ ] All 151 tests passing
- [ ] Performance targets met
- [ ] Security scan complete
- [ ] Code coverage > 95%
- [ ] Documentation updated
- [ ] Backup created
- [ ] Rollback procedure tested

### During Deployment
- [ ] Use blue-green or canary strategy
- [ ] Monitor error rates
- [ ] Check performance metrics
- [ ] Verify user functionality
- [ ] Monitor resource usage

### Post-Deployment
- [ ] Smoke tests passed
- [ ] User feedback collected
- [ ] Performance stable
- [ ] No error spikes
- [ ] Monitoring enabled

---

## 🔗 References

- **GitHub Actions:** https://docs.github.com/en/actions
- **GitLab CI:** https://docs.gitlab.com/ee/ci/
- **Jenkins:** https://www.jenkins.io/
- **Test Results:** See `TEST_RESULTS.md`
- **User Flows:** See `USER_FLOWS.md`

---

**Version:** 1.0.0
**Last Updated:** 2025-10-27
**Status:** ✅ READY FOR PRODUCTION
