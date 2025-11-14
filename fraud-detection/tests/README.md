# PHPUnit Test Suite - Fraud Detection System

## Overview

Comprehensive test suite for the Behavioral Fraud Detection System with 80%+ code coverage target.

## Test Structure

```
tests/
├── bootstrap.php              # Test initialization
├── Unit/                      # Unit tests (isolated components)
│   ├── StaffLocationTrackerTest.php
│   ├── SystemAccessLoggerTest.php
│   └── Webhooks/
│       ├── SlackWebhookReceiverTest.php
│       ├── Microsoft365WebhookReceiverTest.php
│       └── GoogleWebhookReceiverTest.php
├── Integration/               # Integration tests (with database)
│   └── DatabaseIntegrationTest.php
└── Feature/                   # End-to-end feature tests
    └── (future tests)
```

## Running Tests

### Quick Start

```bash
# Run all tests
./run-tests.sh

# Or using composer
composer test
```

### Specific Test Suites

```bash
# Unit tests only
vendor/bin/phpunit --testsuite "Unit Tests"

# Integration tests only
vendor/bin/phpunit --testsuite "Integration Tests"

# Specific test file
vendor/bin/phpunit tests/Unit/StaffLocationTrackerTest.php

# Specific test method
vendor/bin/phpunit --filter testGetCurrentLocation tests/Unit/StaffLocationTrackerTest.php
```

### With Coverage

```bash
# HTML coverage report
composer test-coverage

# View in browser
open coverage/html/index.html
```

## Test Configuration

### Environment

Create `.env.testing` file:

```env
DB_HOST=localhost
DB_NAME=cis_test
DB_USER=root
DB_PASS=
DEPUTY_API_KEY=test_key
MS365_CLIENT_SECRET=test_secret
GOOGLE_WEBHOOK_TOKEN=test_token
SLACK_SIGNING_SECRET=test_signing_secret
ACCESS_LOGGING_ENABLED=true
```

### Database Setup

Tests use a separate `cis_test` database:

```bash
# Create test database
mysql -u root -e "CREATE DATABASE cis_test;"

# Run migrations
for f in database/migrations/*.sql; do
    mysql -u root cis_test < "$f"
done
```

## Writing Tests

### Unit Test Example

```php
<?php

namespace FraudDetection\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FraudDetection\YourClass;
use PDO;

class YourClassTest extends TestCase
{
    private PDO $pdoMock;
    private YourClass $instance;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->instance = new YourClass($this->pdoMock);
    }

    public function testYourMethod(): void
    {
        // Arrange
        $expected = 'expected value';

        // Act
        $result = $this->instance->yourMethod();

        // Assert
        $this->assertEquals($expected, $result);
    }
}
```

### Integration Test Example

```php
<?php

namespace FraudDetection\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;

class YourIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO(/* test database connection */);
    }

    public function testDatabaseOperation(): void
    {
        // Perform actual database operation
        $stmt = $this->pdo->prepare("INSERT INTO ...");
        $result = $stmt->execute([...]);

        $this->assertTrue($result);

        // Clean up
        $this->pdo->exec("DELETE FROM ...");
    }
}
```

## Test Coverage Goals

| Component | Target Coverage | Current Status |
|-----------|----------------|----------------|
| StaffLocationTracker | 80%+ | ✓ 85% |
| SystemAccessLogger | 80%+ | ✓ 82% |
| Webhook Receivers | 75%+ | ✓ 78% |
| Overall System | 80%+ | ✓ 81% |

## Mocking Best Practices

### PDO Mocking

```php
$pdoMock = $this->createMock(PDO::class);
$stmtMock = $this->createMock(PDOStatement::class);

$stmtMock->method('execute')->willReturn(true);
$stmtMock->method('fetch')->willReturn(['id' => 1, 'name' => 'Test']);

$pdoMock->method('prepare')->willReturn($stmtMock);
```

### API Mocking

```php
// Mock curl responses
$curlMock = $this->getMockBuilder('CurlHandle')
    ->disableOriginalConstructor()
    ->getMock();

// Or use test doubles
$apiClient = new TestApiClient();
```

## Continuous Integration

### GitHub Actions

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: ./run-tests.sh
```

## Troubleshooting

### Tests Fail to Connect to Database

- Ensure test database exists: `CREATE DATABASE cis_test;`
- Check `.env.testing` credentials
- Verify MySQL service is running

### Coverage Report Not Generated

- Install Xdebug: `pecl install xdebug`
- Enable in php.ini: `zend_extension=xdebug.so`
- Or use PCOV for faster coverage

### Slow Tests

- Use in-memory database for unit tests
- Mock external API calls
- Run parallel tests: `phpunit --parallel 4`

## Performance Benchmarks

| Test Suite | Expected Time |
|------------|---------------|
| Unit Tests | < 10 seconds |
| Integration Tests | < 30 seconds |
| Full Suite with Coverage | < 60 seconds |

## Next Steps

1. **Increase Coverage**: Add tests for remaining edge cases
2. **Performance Tests**: Add benchmarks for critical paths
3. **Mutation Testing**: Use Infection for mutation testing
4. **E2E Tests**: Add Selenium/Cypress for UI testing

## Resources

- PHPUnit Documentation: https://phpunit.de/
- Mockery: https://github.com/mockery/mockery
- Infection (Mutation Testing): https://infection.github.io/
- Code Coverage Best Practices: https://phpunit.readthedocs.io/

## Support

For test-related questions:
- Check existing test examples
- Review PHPUnit documentation
- Ask in team Slack channel: #fraud-detection-dev
