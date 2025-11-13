# 🧪 ENTERPRISE API TEST SUITE - HARD MODE

## Overview

**Strict Standards Test Suite** for Store Reports API with 100% output validation, exact data type matching, and full interoperability testing.

---

## Test Coverage

### ✅ **Phase 1: Database Setup**
- Database connection verification
- Schema existence validation (9 core tables)
- Test data initialization

### ✅ **Phase 2: Authentication**
- Unauthenticated access (expect 401)
- Invalid credentials handling
- Expired session management

### ✅ **Phase 3: Report CRUD Operations**
- `POST /api/reports-create` - Valid request + schema validation
- `PUT /api/reports-update` - Partial updates + data types
- `GET /api/reports-view` - Complete report details
- `GET /api/reports-list` - Pagination + filtering
- `DELETE /api/reports-delete` - Soft/hard delete

### ✅ **Phase 4: Media Uploads**
- `POST /api/photos-upload` - Valid image upload
- Invalid file format handling (expect 400)
- File size limit enforcement
- `POST /api/voice-memo-upload` - Audio transcription

### ✅ **Phase 5: Autosave System**
- `POST /api/autosave-checkpoint` - Create checkpoint
- `GET /api/autosave-recover` - Recover with conflict detection
- Concurrent update handling

### ✅ **Phase 6: AI Integration**
- `POST /api/ai-analyze-image` - GPT-4 Vision analysis
- `GET /api/ai-conversation` - Conversation history
- `POST /api/ai-respond` - Follow-up questions

### ✅ **Phase 7: Configuration & Analytics**
- `GET /api/checklist-get` - Checklist versioning
- `GET /api/analytics-dashboard` - Dashboard metrics

### ✅ **Phase 8: Error Handling**
- SQL injection prevention
- XSS attack prevention
- Missing required fields (expect 400)
- Invalid data types handling

### ✅ **Phase 9: Data Type Validation**
- All endpoint responses validated against strict schemas
- Type checking: `integer`, `double`, `boolean`, `string`, `array`
- Nested object validation

### ✅ **Phase 10: Interoperability**
- End-to-end workflow (create → update → view → delete)
- Concurrent request handling
- Cross-endpoint data consistency

---

## Response Schema Validation

Each endpoint has a **strict schema** that validates:

### Example: `reports-create`
```json
{
  "success": "boolean",
  "report_id": "integer",
  "checklist": "array",
  "autosave_token": "string",
  "message": "string"
}
```

### Data Type Requirements:
- **integer** - Exact integer type, no strings
- **double** - Float or integer accepted for numeric values
- **boolean** - True/false only, no 1/0
- **string** - UTF-8 encoded text
- **array** - Indexed or associative arrays

---

## Running Tests

### Quick Start
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/store-reports/tests
php test-suite-strict.php
```

### Requirements
- ✅ PHP 7.4+ with `declare(strict_types=1)`
- ✅ PDO extension with MySQL
- ✅ cURL extension
- ✅ GD or Imagick for image tests
- ✅ Database schema deployed (`schema_v2_enterprise.sql`)
- ⚠️ OpenAI API key (optional, AI tests will be skipped)

### Expected Output
```
╔════════════════════════════════════════════════════════════════════════════╗
║              ENTERPRISE API TEST SUITE - HARD MODE                         ║
║              100% Output Match | Strict Data Types                         ║
╚════════════════════════════════════════════════════════════════════════════╝

📦 PHASE: DATABASE SETUP
────────────────────────────────────────────────────────────────────────────
🧪 Database Connection... ✅ PASS
🧪 Table exists: store_reports... ✅ PASS
🧪 Setup Test Data... ✅ PASS

... [more tests] ...

═══════════════════════════════════════════════════════════════════════════
TEST SUMMARY
═══════════════════════════════════════════════════════════════════════════

✅ Passed:   45
❌ Failed:   0
⚠️  Warnings: 5
📊 Total:    45
📈 Pass Rate: 100.00%

🎉 ALL TESTS PASSED! 100% SUCCESS!
```

---

## Test Assertions

### Status Code Assertions
```php
assertStatusCode(200, $response)  // HTTP 200 OK
assertStatusCode(400, $response)  // HTTP 400 Bad Request
assertStatusCode(401, $response)  // HTTP 401 Unauthorized
assertStatusCode(403, $response)  // HTTP 403 Forbidden
assertStatusCode(404, $response)  // HTTP 404 Not Found
assertStatusCode(500, $response)  // HTTP 500 Server Error
```

### Data Type Assertions
```php
assertDataType('integer', $value)   // Exact integer
assertDataType('double', $value)    // Float/integer
assertDataType('boolean', $value)   // True/false
assertDataType('string', $value)    // String
assertDataType('array', $value)     // Array
```

### Schema Validation
```php
validateSchema('reports-create', $responseData)
// Validates all required keys exist
// Validates all data types match schema
// Returns false with detailed error on mismatch
```

---

## Error Scenarios Tested

### Security
- ✅ SQL Injection attempts blocked
- ✅ XSS attacks sanitized
- ✅ CSRF token validation (if enabled)
- ✅ Rate limiting enforcement

### Input Validation
- ✅ Missing required fields (400 error)
- ✅ Invalid data types (graceful handling)
- ✅ Out-of-range values
- ✅ Malformed JSON

### Business Logic
- ✅ Duplicate submissions prevented
- ✅ Ownership/permission checks
- ✅ State transitions validated
- ✅ Referential integrity maintained

### External Dependencies
- ⚠️ OpenAI API failures (graceful degradation)
- ⚠️ Database connection loss (error handling)
- ⚠️ File system errors (cleanup)

---

## Interoperability Matrix

| Endpoint         | Creates Data For | Depends On        |
|------------------|------------------|-------------------|
| reports-create   | All endpoints    | checklist, outlets|
| reports-update   | history, autosave| reports-create    |
| photos-upload    | ai-analyze-image | reports-create    |
| ai-analyze-image | ai-conversation  | photos-upload     |
| ai-respond       | ai-conversation  | reports-create    |
| autosave-checkpoint | autosave-recover | reports-create |
| reports-view     | -                | reports-create    |
| reports-delete   | -                | reports-create    |

---

## Performance Benchmarks

### Target Response Times
- CRUD operations: < 100ms
- Image upload: < 500ms
- AI analysis: < 5s (OpenAI dependent)
- Dashboard analytics: < 200ms
- List operations: < 150ms

### Stress Test Thresholds
- Concurrent users: 50+
- Reports per minute: 1000+
- Images per hour: 5000+
- AI requests per hour: 500+

---

## Continuous Integration

### Pre-Deployment Checklist
```bash
# 1. Run strict test suite
php tests/test-suite-strict.php

# 2. Verify 100% pass rate
# Exit code 0 = all passed

# 3. Check for warnings
# Warnings indicate optional features skipped (e.g., AI tests)

# 4. Review test output log
# Look for performance degradation
```

### CI/CD Integration
```yaml
# .github/workflows/test.yml
- name: Run API Tests
  run: php modules/store-reports/tests/test-suite-strict.php

- name: Check Pass Rate
  run: |
    if [ $? -ne 0 ]; then
      echo "Tests failed!"
      exit 1
    fi
```

---

## Troubleshooting

### Common Issues

**Database Connection Failed**
```bash
# Check database credentials in .env
DB_HOST=localhost
DB_NAME=jcepnzzkmj
DB_USER=your_user
DB_PASS=your_pass
```

**Schema Not Found**
```bash
# Deploy schema first
mysql -u user -p jcepnzzkmj < database/schema_v2_enterprise.sql
```

**cURL Errors**
```bash
# Ensure Apache/Nginx is running
sudo systemctl status apache2
# or
sudo systemctl status nginx
```

**OpenAI Tests Skipped**
```bash
# Add API key to .env
OPENAI_API_KEY=sk-proj-...
```

---

## Extending Tests

### Adding New Test
```php
private function testMyNewFeature(): void
{
    $this->test("My Feature Test", function() {
        $_SESSION['user_id'] = $this->testData['user_id'];

        $response = $this->apiCall('POST', '/api/my-endpoint', [
            'param' => 'value'
        ]);

        if (!$this->assertStatusCode(200, $response)) return false;
        if (!$this->validateSchema('my-endpoint', $response['body'])) return false;

        return $this->assertDataType('string', $response['body']['result']);
    });
}
```

### Adding New Schema
```php
'my-endpoint' => [
    'success' => 'boolean',
    'result' => 'string',
    'count' => 'integer'
]
```

---

## Maintenance

### Regular Tasks
- [ ] Run full test suite after any API change
- [ ] Update schemas when response structure changes
- [ ] Add tests for new endpoints
- [ ] Review and update benchmarks quarterly
- [ ] Check for deprecated PHP features

### Version Compatibility
- PHP 7.4+: Full support
- PHP 8.0+: Full support with strict types
- MySQL 5.7+: Required
- MariaDB 10.3+: Supported

---

## Contact

For issues or questions about the test suite:
- Review test output logs
- Check `/modules/store-reports/logs/` for detailed errors
- Verify all prerequisites are met
- Ensure database schema is current version

---

**Last Updated**: 2025-11-13
**Test Suite Version**: 1.0.0
**API Endpoints Covered**: 15/15 (100%)
