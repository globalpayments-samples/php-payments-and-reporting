# Testing Guide

This document explains how to run and work with the PHPUnit tests for the Global Payments Card Authentication project.

## Test Structure

The tests are organized in the `tests/` directory with the following structure:

```
tests/
├── bootstrap.php                    # Test bootstrap file
├── TransactionReporterTest.php      # Unit tests for TransactionReporter
├── TransactionReporterIntegrationTest.php # Integration tests
└── TransactionsApiTest.php          # API endpoint tests
```

## Test Categories

### 1. Unit Tests (`TransactionReporterTest.php`)
- Tests private methods using reflection
- Tests transaction formatting logic
- Tests response code handling with fallbacks
- Tests transaction type determination
- Tests transaction status mapping
- Tests validation logic
- Tests timestamp handling
- Tests card data processing

### 2. Integration Tests (`TransactionReporterIntegrationTest.php`)
- Tests complete transaction recording and retrieval workflows
- Tests date range filtering
- Tests transaction limiting and sorting
- Tests data integrity across the full stack
- Tests default value handling

### 3. API Tests (`TransactionsApiTest.php`)
- Tests API parameter validation
- Tests response structure validation
- Tests security headers
- Tests error handling
- Tests HTTP method validation

## Prerequisites

1. **PHP 8.0 or higher**
2. **Composer dependencies installed**:
   ```bash
   composer install
   ```

3. **Environment file configured** (`.env`):
   ```bash
   cp .env.example .env
   # Edit .env with your test credentials
   ```

## Running Tests

### Basic Test Commands

1. **Run all tests:**
   ```bash
   composer test
   ```

2. **Run tests with detailed output:**
   ```bash
   composer test:unit
   ```

3. **Run specific test file:**
   ```bash
   composer test:filter TransactionReporterTest
   ```

4. **Run specific test method:**
   ```bash
   composer test:filter testFormatTransactionForDashboard
   ```

### Advanced Test Commands

1. **Run tests with coverage report:**
   ```bash
   composer test:coverage
   ```
   This generates an HTML coverage report in `coverage-html/` directory.

2. **Run only unit tests:**
   ```bash
   ./vendor/bin/phpunit tests/TransactionReporterTest.php --testdox
   ```

3. **Run only integration tests:**
   ```bash
   ./vendor/bin/phpunit tests/TransactionReporterIntegrationTest.php --testdox
   ```

4. **Run tests with verbose output:**
   ```bash
   ./vendor/bin/phpunit --verbose --testdox
   ```

### Quality Assurance

Run both linting and tests together:
```bash
composer quality
```

## Test Configuration

The tests use the following configuration files:

- **`phpunit.xml`**: Main PHPUnit configuration
- **`tests/bootstrap.php`**: Test environment setup
- **`.env`**: Environment variables (test credentials)

## Test Environment

The tests are configured to:
- Use a separate testing environment (`APP_ENV=testing`)
- Avoid hitting real APIs when possible
- Use test-specific log files that are cleaned up after tests
- Load test environment variables from `.env`

## Test Data

Tests use mock transaction data to avoid dependencies on external systems:
- Mock Global Payments API responses
- Simulated transaction objects with realistic data
- Test-specific transaction IDs (prefixed with TEST_, INTEGRATION_, etc.)

## Troubleshooting

### Common Issues

1. **Tests fail with "Class not found" errors:**
   ```bash
   composer dump-autoload
   ```

2. **Permission errors with log files:**
   ```bash
   chmod -R 755 logs/
   ```

3. **Memory issues with coverage:**
   ```bash
   php -d memory_limit=512M ./vendor/bin/phpunit --coverage-html coverage-html
   ```

4. **Environment variable issues:**
   - Ensure `.env` file exists
   - Check that required variables are set:
     - `SECRET_API_KEY`
     - `DEVELOPER_ID`
     - `VERSION_NUMBER`
     - `SERVICE_URL`

### Debug Mode

To run tests with detailed debugging:
```bash
./vendor/bin/phpunit --debug --verbose
```

## Test Coverage

The tests aim to cover:
- ✅ All public methods of TransactionReporter
- ✅ Critical private methods (via reflection)
- ✅ Error handling and edge cases
- ✅ Data validation and sanitization
- ✅ Response code fallback logic
- ✅ Transaction type determination
- ✅ Local transaction storage and retrieval
- ✅ Date filtering and pagination
- ✅ API parameter validation

### Coverage Report

Generate and view coverage report:
```bash
composer test:coverage
open coverage-html/index.html  # macOS
xdg-open coverage-html/index.html  # Linux
```

## Writing New Tests

### Test Naming Convention
- Test files: `*Test.php`
- Test methods: `test*()` or use `@test` annotation
- Test classes: extend `PHPUnit\Framework\TestCase`

### Example Test Structure
```php
<?php

namespace GlobalPayments\Examples\Tests;

use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase
{
    protected function setUp(): void
    {
        // Set up test data
    }

    protected function tearDown(): void
    {
        // Clean up after tests
    }

    public function testSomeFunctionality(): void
    {
        // Arrange
        $input = 'test data';
        
        // Act
        $result = $this->someMethod($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

## Continuous Integration

The tests are designed to run in CI/CD environments. Make sure to:
1. Set environment variables in your CI configuration
2. Install dependencies with `composer install --no-dev`
3. Run tests with `composer test`
4. Generate coverage reports if needed

## Best Practices

1. **Keep tests fast** - Use mocks instead of real API calls
2. **Make tests independent** - Each test should be able to run in isolation
3. **Use descriptive names** - Test method names should explain what they test
4. **Test edge cases** - Include tests for error conditions and boundary values
5. **Clean up after tests** - Remove test files and reset state in tearDown()

## Support

If you encounter issues with the tests:
1. Check this documentation
2. Verify your environment setup
3. Run tests with verbose output to see detailed error messages
4. Check the logs directory for error files