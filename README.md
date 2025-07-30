# Heartland Payment Integration - Production MVP

A comprehensive card verification and payment processing solution built with the GlobalPayments PHP SDK, featuring real-time transaction monitoring, secure payment processing, and professional dashboard interfaces.

## Table of Contents

- [Features](#features)
- [Quick Start](#quick-start)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Testing](#testing)
- [Security](#security)
- [Deployment](#deployment)
- [Contributing](#contributing)

## Features

### Core Functionality
- **Card Verification**: Zero-charge validation with AVS/CVV checks
- **Payment Processing**: Secure payment transactions via GlobalPayments SDK
- **Transaction Dashboard**: Real-time monitoring with filtering and export capabilities
- **Multi-format Support**: CSV export, detailed transaction views, and comprehensive reporting

### Technical Features
- **Modern Architecture**: Clean separation of concerns with PSR-4 autoloading
- **Comprehensive Testing**: PHPUnit test suite with 100% pass rate (17 tests, 128 assertions)
- **Security First**: Input validation, secure tokenization, and error handling
- **Production Ready**: Proper logging, monitoring, and deployment configuration

## Quick Start

### Prerequisites
- PHP 8.0 or higher
- Composer
- GlobalPayments sandbox account and API credentials

### Installation

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd card-authentication
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your GlobalPayments credentials
   ```

4. **Start development server:**
   ```bash
   php -S localhost:8000
   ```

5. **Access the application:**
   ```
   http://localhost:8000
   ```

## Project Structure

```
card-authentication/
├── docs/                           # Documentation
│   ├── API.md                     # API documentation
│   ├── DEPLOYMENT.md              # Deployment guide
│   └── ARCHITECTURE.md            # System architecture
├── public/                        # Web root (frontend files)
│   ├── index.html                 # Main navigation hub
│   ├── card-verification.html     # Card verification interface
│   ├── payment.html               # Payment processing interface
│   ├── dashboard.html             # Transaction monitoring dashboard
│   └── css/                       # Stylesheets
│       ├── index.css
│       └── dashboard.css
├── src/                           # PHP source code
│   ├── TransactionReporter.php    # Transaction management and reporting
│   ├── ErrorHandler.php           # Error handling utilities
│   └── Logger.php                 # Logging utilities
├── api/                           # API endpoints
│   ├── config.php                 # SDK configuration
│   ├── verify-card.php            # Card verification endpoint
│   ├── process-payment.php        # Payment processing endpoint
│   └── transactions-api.php       # Transaction data API
├── tests/                         # PHPUnit tests
│   ├── TransactionReporterTest.php
│   └── TransactionReporterIntegrationTest.php
├── tools/                         # Development and debugging tools
│   ├── debug-payment.php          # System diagnostics
│   └── test-payment.php           # Environment setup checker
├── logs/                          # Application logs (gitignored)
├── vendor/                        # Composer dependencies
├── .env.example                   # Environment configuration template
├── composer.json                  # PHP dependencies and scripts
├── phpunit.xml                    # Test configuration
└── README.md                      # This file
```

## Installation

### System Requirements
- **PHP**: 8.0 or higher with extensions:
  - `curl`
  - `json`
  - `mbstring`
  - `openssl`
- **Composer**: Latest version
- **Web Server**: Apache, Nginx, or PHP built-in server

### Step-by-Step Setup

1. **Environment Setup:**
   ```bash
   # Create environment file
   cp .env.example .env
   
   # Configure your GlobalPayments credentials in .env:
   SECRET_API_KEY=your_secret_api_key_here
   DEVELOPER_ID=your_developer_id
   VERSION_NUMBER=your_version_number
   SERVICE_URL=https://cert.api2.heartlandportico.com
   ```

2. **Install Dependencies:**
   ```bash
   composer install --optimize-autoloader
   ```

3. **Verify Installation:**
   ```bash
   # Run tests to ensure everything is working
   composer test
   
   # Check system requirements
   php tools/test-payment.php
   ```

## Configuration

### Environment Variables

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `SECRET_API_KEY` | GlobalPayments secret API key | Yes | - |
| `DEVELOPER_ID` | Your developer ID | Yes | `000000` |
| `VERSION_NUMBER` | API version number | Yes | `0000` |
| `SERVICE_URL` | GlobalPayments service URL | Yes | Sandbox URL |
| `ENABLE_REQUEST_LOGGING` | Enable detailed API request logging | No | `false` |

### Logging Configuration

Logs are stored in the `logs/` directory with the following structure:
- `transaction-errors.log` - Transaction processing errors
- `system-YYYY-MM-DD.log` - General system logs
- `payment-YYYY-MM-DD.log` - Payment processing logs
- `transactions-YYYY-MM-DD.json` - Transaction data storage

## Usage

### Card Verification

1. Navigate to `http://localhost:8000/card-verification.html`
2. Enter test card details (provided on the page)
3. Submit for zero-charge verification
4. View results including AVS/CVV validation

### Payment Processing

1. Navigate to `http://localhost:8000/payment.html`
2. Enter payment amount and card details
3. Process secure payment through GlobalPayments
4. Monitor results in real-time

### Transaction Monitoring

1. Navigate to `http://localhost:8000/dashboard.html`
2. View all transactions with filtering options
3. Export data to CSV format
4. Access detailed transaction information

### Test Cards

Use these GlobalPayments test cards for development:

| Card Number | Type | CVV | Exp | Expected Result |
|-------------|------|-----|-----|-----------------|
| 4000000000000002 | Visa | 123 | 12/25 | Approved |
| 5400000000000005 | MasterCard | 123 | 12/25 | Approved |
| 370000000000002 | Amex | 1234 | 12/25 | Approved |
| 4000000000000010 | Visa | 123 | 12/25 | Declined |

## API Documentation

### Endpoints

#### Card Verification
```
POST /api/verify-card.php
Content-Type: application/json

{
  "number": "4000000000000002",
  "exp_month": "12",
  "exp_year": "25",
  "cvv": "123",
  "zip": "12345"
}
```

#### Payment Processing
```
POST /api/process-payment.php
Content-Type: application/json

{
  "amount": "10.99",
  "currency": "USD",
  "number": "4000000000000002",
  "exp_month": "12",
  "exp_year": "25",
  "cvv": "123"
}
```

#### Transaction Data
```
GET /api/transactions-api.php?limit=50&start_date=2025-01-01&end_date=2025-01-31
```

For complete API documentation, see [docs/API.md](docs/API.md).

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test file
./vendor/bin/phpunit tests/TransactionReporterTest.php

# Run with verbose output
./vendor/bin/phpunit --verbose --testdox
```

### Test Coverage

Current test coverage:
- **17 test cases** with **128 assertions**
- **100% pass rate**
- Coverage includes unit tests, integration tests, and edge cases

For detailed testing information, see [TESTING.md](TESTING.md).

## Security

### Security Measures Implemented

- **Input Validation**: All user inputs are validated and sanitized
- **Secure Tokenization**: No sensitive card data is stored locally
- **API Security**: Proper headers and authentication
- **Error Handling**: No sensitive information exposed in errors
- **Transaction Filtering**: Only genuine API transactions displayed

### Best Practices

- Keep your `.env` file secure and never commit it to version control
- Regularly rotate API keys
- Monitor logs for suspicious activity
- Use HTTPS in production environments
- Keep dependencies updated

## Deployment

### Production Deployment

1. **Environment Setup:**
   ```bash
   # Use production API credentials
   SECRET_API_KEY=prod_secret_key
   SERVICE_URL=https://api2.heartlandportico.com
   ENABLE_REQUEST_LOGGING=false
   ```

2. **Optimize for Production:**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Web Server Configuration:**
   - Set document root to project directory
   - Configure HTTPS with valid SSL certificate
   - Set appropriate file permissions
   - Configure log rotation

For detailed deployment instructions, see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

### Docker Deployment

```bash
# Build and run with Docker
docker-compose up -d

# Or using Docker directly
docker build -t heartland-payment .
docker run -p 8000:8000 heartland-payment
```

## Contributing

### Development Workflow

1. **Clone and Setup:**
   ```bash
   git clone <repository-url>
   cd card-authentication
   composer install
   ```

2. **Create Feature Branch:**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Development:**
   - Write code following PSR-12 standards
   - Add tests for new functionality
   - Update documentation as needed

4. **Testing:**
   ```bash
   composer test
   composer lint
   ```

5. **Submit Pull Request:**
   - Ensure all tests pass
   - Include comprehensive description
   - Reference any related issues

### Code Standards

- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Include PHPDoc comments for all public methods
- Write tests for all new functionality
- Keep security as a top priority

## Support

### Troubleshooting

Common issues and solutions:

1. **API Connection Issues:**
   - Verify credentials in `.env` file
   - Check network connectivity
   - Ensure API endpoints are accessible

2. **Test Failures:**
   - Run `composer dump-autoload`
   - Check file permissions on `logs/` directory
   - Verify all dependencies are installed

3. **Performance Issues:**
   - Enable PHP OPcache in production
   - Optimize Composer autoloader
   - Monitor system resources

### Getting Help

- Check the [documentation](docs/)
- Review the [test results](test-results.xml)
- Examine application logs in the `logs/` directory
- Use the debug tools in the `tools/` directory

## License

This project is licensed under the MIT License. See the LICENSE file for details.

## Changelog

### Version 1.0.0 (Current MVP)
- Complete card verification system
- Secure payment processing with GlobalPayments SDK
- Real-time transaction dashboard
- Comprehensive test suite
- Production-ready architecture
- Professional documentation

---

**Built with GlobalPayments PHP SDK** | **Production Ready** | **Comprehensive Testing**