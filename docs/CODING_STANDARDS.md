# 🏆 CIS Modules: Enterprise Coding Standards
**Version:** 2.0  
**Effective Date:** October 12, 2025  
**Scope:** All modules within `/public_html/modules/`  
**Authority:** Ecigdis Limited Engineering Standards

---

## 📋 Table of Contents

1. [Core Principles](#core-principles)
2. [PHP Standards](#php-standards)
3. [Security Requirements](#security-requirements)
4. [Architecture Patterns](#architecture-patterns)
5. [View Layer Standards](#view-layer-standards)
6. [JavaScript Standards](#javascript-standards)
7. [Database Standards](#database-standards)
8. [Testing Requirements](#testing-requirements)
9. [Documentation Standards](#documentation-standards)
10. [Performance Standards](#performance-standards)
11. [Deployment & Git Standards](#deployment--git-standards)
12. [Code Review Checklist](#code-review-checklist)

---

## Core Principles

### Golden Rules

1. **Security First** – Never compromise on security for convenience
2. **Separation of Concerns** – MVC architecture strictly enforced
3. **Fail-Safe Defaults** – Secure by default, explicit opt-in for permissive behavior
4. **Explicit Over Implicit** – No magic, no surprises, no hidden behavior
5. **Test Before Deploy** – All changes require automated tests
6. **Document as You Build** – Code is written once, read hundreds of times

### Quality Metrics

| Metric | Target | Enforcement |
|--------|--------|-------------|
| PHPStan Level | 5+ | CI/CD pipeline |
| Code Coverage | ≥80% | Pre-merge check |
| Cyclomatic Complexity | ≤10 per method | SonarQube |
| File Size | ≤500 lines | Manual review |
| Method Length | ≤50 lines | Manual review |
| Class Coupling | ≤10 dependencies | PHPMetrics |

---

## PHP Standards

### PSR Compliance

**Required:**
- ✅ **PSR-1** – Basic Coding Standard
- ✅ **PSR-4** – Autoloading Standard
- ✅ **PSR-12** – Extended Coding Style

**Optional (but recommended):**
- ✅ **PSR-3** – Logger Interface (use for all logging)
- ✅ **PSR-7** – HTTP Message Interface (for API responses)

### File Structure

```php
<?php
declare(strict_types=1);

namespace Modules\Consignments\Controllers;

use Modules\Base\Controller\PageController;
use Modules\Consignments\Services\TransferService;
use Modules\Consignments\Exceptions\TransferNotFoundException;

/**
 * Handles transfer pack operations
 *
 * @package Modules\Consignments
 * @author  Ecigdis Engineering <dev@ecigdis.co.nz>
 * @since   2.0.0
 */
final class PackController extends PageController
{
    private const MAX_ITEMS_PER_TRANSFER = 100;
    
    public function __construct(
        private readonly TransferService $transferService
    ) {
        parent::__construct();
    }
    
    /**
     * Display pack interface for specified transfer
     *
     * @param int $id Transfer ID
     * @return void
     * @throws TransferNotFoundException
     */
    public function show(int $id): void
    {
        // Implementation
    }
}
```

### Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| **Classes** | PascalCase | `TransferService` |
| **Methods** | camelCase | `getTransferById()` |
| **Variables** | camelCase | `$transferId` |
| **Constants** | UPPER_SNAKE_CASE | `MAX_RETRY_ATTEMPTS` |
| **Database Tables** | snake_case | `transfer_lines` |
| **Database Columns** | snake_case | `outlet_from_id` |
| **Namespaces** | PascalCase | `Modules\Consignments\Services` |

### Type Declarations

**REQUIRED on all new code:**

```php
// ✅ GOOD
public function getTransfer(int $id): ?Transfer
{
    return $this->repository->find($id);
}

// ❌ BAD - No type hints
public function getTransfer($id)
{
    return $this->repository->find($id);
}
```

**Strict types REQUIRED:**
```php
<?php
declare(strict_types=1);
```

### Error Handling

**Use typed exceptions:**
```php
// ✅ GOOD
throw new TransferNotFoundException("Transfer #{$id} not found");

// ❌ BAD - Generic exception
throw new Exception("Not found");
```

**Never suppress errors silently:**
```php
// ❌ BAD
@file_get_contents($path);

// ✅ GOOD
try {
    $content = file_get_contents($path);
} catch (Throwable $e) {
    $this->logger->error("Failed to read file", ['path' => $path, 'error' => $e->getMessage()]);
    throw new FileReadException("Unable to read {$path}", 0, $e);
}
```

---

## Security Requirements

### Input Validation

**ALL user input MUST be validated:**

```php
// ✅ GOOD - Explicit validation
public function updateQuantity(array $input): void
{
    $validated = $this->validator->validate($input, [
        'transfer_id' => 'required|integer|min:1',
        'sku' => 'required|string|max:50|regex:/^[A-Z0-9-]+$/',
        'qty' => 'required|integer|min:1|max:1000',
    ]);
    
    $this->service->update($validated);
}

// ❌ BAD - Direct use of user input
public function updateQuantity(): void
{
    $this->service->update($_POST['transfer_id'], $_POST['qty']);
}
```

### Output Escaping

**Context-aware escaping REQUIRED:**

```php
// ✅ GOOD - Proper escaping
<?= htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8') ?>

// Use helper function (create in View.php):
<?= e($userInput) ?>

// ❌ BAD - Raw output
<?= $userInput ?>
```

### SQL Injection Prevention

**NEVER concatenate SQL:**

```php
// ❌ BAD - SQL injection vulnerability
$sql = "SELECT * FROM transfers WHERE id = {$_GET['id']}";

// ✅ GOOD - Prepared statement
$stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
$stmt->execute([$transferId]);
```

### CSRF Protection

**ALL state-changing operations MUST verify CSRF token:**

```php
// In form:
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

// In controller:
Security::assertCsrf($_POST['csrf_token'] ?? '');
```

### Authentication & Authorization

```php
// ✅ GOOD - Check permissions
public function delete(int $id): void
{
    if (!$this->auth->hasPermission('transfers.delete')) {
        throw new UnauthorizedException();
    }
    
    $this->service->delete($id);
}

// ❌ BAD - No permission check
public function delete(int $id): void
{
    $this->service->delete($id);
}
```

### Content Security Policy

**NO inline JavaScript:**

```html
<!-- ❌ BAD - Violates CSP -->
<button onclick="deleteTransfer(123)">Delete</button>

<!-- ✅ GOOD - Event delegation -->
<button class="js-delete-transfer" data-transfer-id="123">Delete</button>
```

```javascript
// In external JS file
document.addEventListener('click', (e) => {
    if (e.target.matches('.js-delete-transfer')) {
        const id = e.target.dataset.transferId;
        deleteTransfer(id);
    }
});
```

---

## Architecture Patterns

### MVC Structure

```
modules/consignments/
├── controllers/          # Request handling ONLY
│   ├── PackController.php
│   └── Api/
│       └── PackApiController.php
├── services/            # Business logic
│   └── TransferService.php
├── repositories/        # Data access
│   └── TransferRepository.php
├── models/              # Domain models
│   └── Transfer.php
├── views/               # Presentation ONLY
│   └── pack/
│       └── full.php
├── lib/                 # Utilities
│   └── Security.php
└── tests/               # Test suite
    ├── Unit/
    └── Integration/
```

### Dependency Injection

**Use constructor injection:**

```php
// ✅ GOOD
final class TransferService
{
    public function __construct(
        private readonly TransferRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcher $events
    ) {}
}

// ❌ BAD - Global state
final class TransferService
{
    public function getTransfer(int $id): Transfer
    {
        global $db;
        return $db->query("SELECT * FROM transfers WHERE id = {$id}");
    }
}
```

### Single Responsibility Principle

**Each class should have ONE reason to change:**

```php
// ✅ GOOD - Separate concerns
class TransferService {
    public function create(array $data): Transfer { /* ... */ }
}

class TransferNotifier {
    public function notifyCreated(Transfer $transfer): void { /* ... */ }
}

// ❌ BAD - Mixed responsibilities
class TransferService {
    public function create(array $data): Transfer { /* ... */ }
    public function sendEmail(Transfer $transfer): void { /* ... */ }
    public function generatePDF(Transfer $transfer): void { /* ... */ }
}
```

---

## View Layer Standards

### Separation of Concerns

**Views contain ONLY presentation logic:**

```php
// ❌ BAD - Business logic in view
<?php
require_once __DIR__ . '/lib/Db.php';
$pdo = Db::pdo();
$transfer = $pdo->query("SELECT * FROM transfers WHERE id = {$_GET['id']}")->fetch();
?>

// ✅ GOOD - Data provided by controller
<?php
/** @var Transfer $transfer */
?>
<h1>Transfer #<?= e($transfer->getId()) ?></h1>
```

### Component Pattern

**Use explicit component helper:**

```php
// In controller:
public function show(int $id): void
{
    $transfer = $this->service->getById($id);
    
    $this->render('pack/full', [
        'transfer' => $transfer,
        'outlets' => $this->outletService->getAll(),
    ]);
}

// In view:
<?php $this->component('pack/header', ['transfer' => $transfer]); ?>
```

### No Business Logic

**Forbidden in views:**
- ❌ Database queries
- ❌ Session manipulation
- ❌ File operations
- ❌ API calls
- ❌ Complex calculations
- ❌ State mutations

**Allowed in views:**
- ✅ Variable output (escaped)
- ✅ Simple loops/conditionals
- ✅ Component includes
- ✅ Formatting (dates, numbers)

---

## JavaScript Standards

### Modern ES6+ Syntax

```javascript
// ✅ GOOD - Modern syntax
const fetchTransfer = async (id) => {
    try {
        const response = await fetch(`/api/transfers/${id}`);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Failed to fetch transfer:', error);
        throw error;
    }
};

// ❌ BAD - Old patterns
var fetchTransfer = function(id) {
    $.ajax({
        url: '/api/transfers/' + id,
        success: function(data) { /* ... */ }
    });
};
```

### Event Delegation

**Use class-based selectors with `js-` prefix:**

```javascript
// ✅ GOOD - Event delegation
document.addEventListener('click', (e) => {
    if (e.target.matches('.js-delete-btn')) {
        handleDelete(e.target.dataset.id);
    }
});

// ❌ BAD - Inline handlers
// <button onclick="delete(123)">Delete</button>
```

### Error Handling

```javascript
// ✅ GOOD - Proper error handling
async function submitTransfer(data) {
    try {
        const response = await fetch('/api/transfers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    } catch (error) {
        logger.error('Transfer submission failed', { error, data });
        showErrorToast('Failed to submit transfer');
        throw error;
    }
}
```

---

## Database Standards

### Schema Design

```sql
-- ✅ GOOD - Proper constraints
CREATE TABLE transfer_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    qty_requested INT UNSIGNED NOT NULL DEFAULT 0,
    qty_packed INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_transfer FOREIGN KEY (transfer_id) 
        REFERENCES transfers(id) ON DELETE CASCADE,
    CONSTRAINT fk_product FOREIGN KEY (product_id) 
        REFERENCES vend_products(id) ON DELETE RESTRICT,
    CONSTRAINT check_qty CHECK (qty_packed IS NULL OR qty_packed <= qty_requested),
    
    INDEX idx_transfer (transfer_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Query Optimization

**ALWAYS use prepared statements:**
```php
// ✅ GOOD
$stmt = $pdo->prepare("
    SELECT t.*, o.name AS outlet_name
    FROM transfers t
    JOIN outlets o ON o.id = t.outlet_to_id
    WHERE t.status = :status
    ORDER BY t.created_at DESC
    LIMIT :limit
");
$stmt->execute(['status' => 'pending', 'limit' => 50]);
```

**Index hot paths:**
```sql
-- Queries with WHERE, JOIN, ORDER BY need indexes
CREATE INDEX idx_status_created ON transfers(status, created_at);
```

---

## Testing Requirements

### Unit Tests

**Coverage target: ≥80%**

```php
namespace Modules\Consignments\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TransferServiceTest extends TestCase
{
    public function test_create_transfer_with_valid_data(): void
    {
        $repository = $this->createMock(TransferRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->willReturn(new Transfer(['id' => 123]));
        
        $service = new TransferService($repository);
        $transfer = $service->create([
            'outlet_from' => 'A1B2',
            'outlet_to' => 'C3D4',
        ]);
        
        $this->assertSame(123, $transfer->getId());
    }
}
```

### Integration Tests

**Test full request flow:**
```php
public function test_pack_endpoint_returns_transfer_data(): void
{
    $response = $this->get('/consignments/pack?transfer=123');
    
    $response->assertStatus(200);
    $response->assertSee('Transfer #123');
    $response->assertSee('Pack Transfer');
}
```

---

## Documentation Standards

### File Headers

```php
<?php
declare(strict_types=1);

namespace Modules\Consignments\Services;

/**
 * Transfer business logic service
 *
 * Handles creation, validation, and state transitions for transfers.
 * Enforces business rules and triggers events.
 *
 * @package Modules\Consignments
 * @author  Ecigdis Engineering <dev@ecigdis.co.nz>
 * @since   2.0.0
 */
final class TransferService
{
    // ...
}
```

### Method Documentation

```php
/**
 * Create new transfer between outlets
 *
 * Validates outlet IDs, checks permissions, creates transfer record,
 * and dispatches TransferCreated event.
 *
 * @param array{outlet_from: string, outlet_to: string, items: array} $data
 * @return Transfer Created transfer instance
 * @throws OutletNotFoundException If outlet IDs invalid
 * @throws InsufficientStockException If requested items unavailable
 * @throws UnauthorizedException If user lacks permission
 */
public function create(array $data): Transfer
{
    // Implementation
}
```

---

## Performance Standards

### Response Time Targets

| Endpoint Type | p50 | p95 | p99 |
|--------------|-----|-----|-----|
| Page Load | <500ms | <1000ms | <2000ms |
| API Call | <200ms | <500ms | <1000ms |
| Database Query | <50ms | <200ms | <500ms |

### Optimization Requirements

1. **Database queries:** Use EXPLAIN to verify index usage
2. **N+1 queries:** Use eager loading / JOIN
3. **Large datasets:** Always paginate (max 100 rows)
4. **Heavy operations:** Queue for background processing
5. **Static assets:** Version and cache (1 year)

```php
// ✅ GOOD - Eager loading
$transfers = $this->repository->findWithLines($filters);

// ❌ BAD - N+1 problem
$transfers = $this->repository->find($filters);
foreach ($transfers as $transfer) {
    $lines = $this->lineRepository->findByTransfer($transfer->id);
}
```

---

## Deployment & Git Standards

### Branch Strategy

```
main ← develop ← feature/TICKET-123-add-bulk-pack
                ↑
            hotfix/fix-csrf-validation
```

### Commit Messages

**Format:** `type(scope): subject`

```
feat(pack): add bulk quantity update
fix(csrf): validate token before form submission
refactor(views): extract component helper
docs(api): document pack endpoint parameters
test(transfer): add integration tests for workflow
```

### Pull Request Requirements

**Before merge:**
- ✅ All tests passing
- ✅ PHPStan level 5 clean
- ✅ Code coverage ≥80%
- ✅ Peer review approved
- ✅ Documentation updated
- ✅ CHANGELOG.md entry added

---

## Code Review Checklist

### Security
- [ ] All user input validated
- [ ] Output properly escaped
- [ ] SQL uses prepared statements
- [ ] CSRF token verified
- [ ] Authorization checked
- [ ] No secrets in code

### Architecture
- [ ] MVC separation maintained
- [ ] Single Responsibility Principle
- [ ] Dependency injection used
- [ ] No business logic in views
- [ ] No global state
- [ ] Error handling comprehensive

### Code Quality
- [ ] PSR-12 compliant
- [ ] Type declarations present
- [ ] Methods <50 lines
- [ ] Classes <500 lines
- [ ] Cyclomatic complexity <10
- [ ] No code duplication

### Testing
- [ ] Unit tests added
- [ ] Integration tests for critical paths
- [ ] Edge cases covered
- [ ] Coverage target met

### Documentation
- [ ] File headers complete
- [ ] Methods documented
- [ ] README updated
- [ ] CHANGELOG updated

### Performance
- [ ] Queries optimized
- [ ] Indexes verified
- [ ] No N+1 queries
- [ ] Pagination implemented

---

## Enforcement

### Automated Checks (CI/CD)

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - name: PHPStan
        run: vendor/bin/phpstan analyse --level=5
      
      - name: PHP CS Fixer
        run: vendor/bin/php-cs-fixer fix --dry-run --diff
      
      - name: PHPUnit
        run: vendor/bin/phpunit --coverage-clover coverage.xml
      
      - name: Coverage Check
        run: |
          coverage=$(php -r "echo round(simplexml_load_file('coverage.xml')->project->metrics['coveredstatements'] / simplexml_load_file('coverage.xml')->project->metrics['statements'] * 100, 2);")
          if (( $(echo "$coverage < 80" | bc -l) )); then
            echo "Coverage $coverage% is below 80% threshold"
            exit 1
          fi
```

### Pre-commit Hooks

```bash
#!/bin/sh
# .git/hooks/pre-commit

echo "Running PHPStan..."
vendor/bin/phpstan analyse --level=5 --no-progress || exit 1

echo "Running PHP CS Fixer..."
vendor/bin/php-cs-fixer fix --dry-run --diff || exit 1

echo "Running tests..."
vendor/bin/phpunit --no-coverage || exit 1

echo "✅ All checks passed"
```

---

## References

- [PSR-1: Basic Coding Standard](https://www.php-fig.org/psr/psr-1/)
- [PSR-12: Extended Coding Style](https://www.php-fig.org/psr/psr-12/)
- [OWASP Secure Coding Practices](https://owasp.org/www-project-secure-coding-practices-quick-reference-guide/)
- [Clean Code by Robert C. Martin](https://www.amazon.com/Clean-Code-Handbook-Software-Craftsmanship/dp/0132350882)

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.0 | 2025-10-12 | Complete rewrite for module architecture |
| 1.0 | 2024-01-15 | Initial standards document |

---

**Questions or clarifications?**  
Contact: dev@ecigdis.co.nz  
Docs: https://wiki.vapeshed.co.nz/coding-standards
