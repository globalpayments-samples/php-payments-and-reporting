# Project Structure

This document outlines the organized structure of the Heartland Payment Integration system following software engineering best practices.

## Directory Structure

```
card-authentication/
├── docs/                           # Documentation
│   ├── API.md                     # Complete API documentation
│   ├── ARCHITECTURE.md            # System architecture overview
│   └── DEPLOYMENT.md              # Production deployment guide
├── public/                        # Web root (frontend files)
│   ├── index.html                 # Main navigation hub
│   ├── card-verification.html     # Card verification interface
│   ├── payment.html               # Payment processing interface
│   ├── dashboard.html             # Transaction monitoring dashboard
│   └── css/                       # Stylesheets
│       ├── index.css              # Main application styles
│       └── dashboard.css          # Dashboard-specific styles
├── api/                           # API endpoints
│   ├── config.php                 # SDK configuration endpoint
│   ├── verify-card.php            # Card verification processor
│   ├── process-payment.php        # Payment processing endpoint
│   ├── heartland-process-payment.php # Legacy payment processor
│   └── transactions-api.php       # Transaction data API
├── src/                           # PHP source code
│   ├── TransactionReporter.php    # Transaction management and reporting
│   ├── ErrorHandler.php           # Centralized error handling
│   └── Logger.php                 # Application logging utilities
├── tests/                         # PHPUnit test suite
│   ├── TransactionReporterTest.php
│   └── TransactionReporterIntegrationTest.php
├── tools/                         # Development and debugging tools
│   ├── debug-payment.php          # System diagnostics
│   └── test-payment.php           # Environment setup checker
├── logs/                          # Application logs (gitignored)
│   ├── all-transactions.json      # Master transaction storage
│   ├── transactions-YYYY-MM-DD.json # Daily transaction files
│   ├── transaction-errors.log     # Error logging
│   └── system-YYYY-MM-DD.log      # System event logs
├── vendor/                        # Composer dependencies (gitignored)
├── .env.example                   # Environment configuration template
├── .gitignore                     # Git ignore rules
├── composer.json                  # PHP dependencies and scripts
├── composer.lock                  # Dependency lock file
├── phpunit.xml                    # PHPUnit test configuration
├── README.md                      # Main project documentation
├── TESTING.md                     # Testing guide and procedures
├── PROJECT_STRUCTURE.md           # This file
└── HPP-INTEGRATION.md             # Legacy HPP documentation (archived)
```

## Component Organization

### 1. Frontend Components (`public/`)

**Purpose**: User interface files and static assets

| File | Purpose | Dependencies |
|------|---------|--------------|
| `index.html` | Main navigation hub and entry point | `api/config.php` |
| `card-verification.html` | Zero-charge card verification interface | `api/verify-card.php` |
| `payment.html` | Payment processing interface | `api/process-payment.php` |
| `dashboard.html` | Transaction monitoring and reporting | `api/transactions-api.php` |
| `css/index.css` | Main application styles | None |
| `css/dashboard.css` | Dashboard-specific styles | None |

**Key Features**:
- Responsive design with mobile-first approach
- Progressive enhancement for accessibility
- Real-time form validation
- Professional UI/UX design

### 2. API Layer (`api/`)

**Purpose**: RESTful API endpoints for backend processing

| File | HTTP Method | Purpose | Response Format |
|------|-------------|---------|-----------------|
| `config.php` | GET | SDK configuration for frontend | JSON |
| `verify-card.php` | POST | Card verification processing | JSON |
| `process-payment.php` | POST | Payment transaction processing | JSON |
| `transactions-api.php` | GET | Transaction data retrieval | JSON |
| `heartland-process-payment.php` | POST | Legacy payment processor | JSON |

**Design Patterns**:
- RESTful API design
- Consistent error handling
- Input validation and sanitization
- Comprehensive logging

### 3. Business Logic (`src/`)

**Purpose**: Core application logic and utilities

| Class | Responsibility | Key Methods |
|-------|----------------|-------------|
| `TransactionReporter` | Transaction management and reporting | `getRecentTransactions()`, `recordTransaction()`, `getLocalTransactions()` |
| `ErrorHandler` | Centralized error handling and logging | `handleError()`, `logError()`, `formatError()` |
| `Logger` | Application logging and debugging | `log()`, `debug()`, `error()` |

**Architecture Principles**:
- Single Responsibility Principle
- Dependency Injection
- Clean Code practices
- Comprehensive error handling

### 4. Testing Suite (`tests/`)

**Purpose**: Comprehensive test coverage

| Test File | Type | Coverage |
|-----------|------|----------|
| `TransactionReporterTest.php` | Unit Tests | Individual method testing with mocks |
| `TransactionReporterIntegrationTest.php` | Integration Tests | End-to-end workflow testing |

**Test Metrics**:
- 17 test cases with 128 assertions
- 100% pass rate
- Unit and integration test coverage
- Mock objects for external dependencies

### 5. Development Tools (`tools/`)

**Purpose**: Development and debugging utilities

| Tool | Purpose | Usage |
|------|---------|-------|
| `debug-payment.php` | System diagnostics and troubleshooting | `php tools/debug-payment.php` |
| `test-payment.php` | Environment setup verification | `php tools/test-payment.php` |

### 6. Documentation (`docs/`)

**Purpose**: Comprehensive project documentation

| Document | Content | Audience |
|----------|---------|----------|
| `API.md` | Complete API reference with examples | Developers, Integrators |
| `ARCHITECTURE.md` | System design and architecture overview | Architects, Senior Developers |
| `DEPLOYMENT.md` | Production deployment procedures | DevOps, System Administrators |

## File Dependencies

### Frontend → API Flow

```
index.html
├── → tools/test-payment.php (diagnostics)
└── → tools/debug-payment.php (system check)

card-verification.html
├── → api/config.php (SDK configuration)
└── → api/verify-card.php (verification processing)

payment.html
├── → api/config.php (SDK configuration)
└── → api/process-payment.php (payment processing)

dashboard.html
└── → api/transactions-api.php (transaction data)
```

### API → Business Logic Flow

```
api/verify-card.php
└── → src/TransactionReporter.php (transaction recording)

api/process-payment.php
└── → src/TransactionReporter.php (transaction recording)

api/transactions-api.php
└── → src/TransactionReporter.php (data retrieval)

All API files
├── → src/ErrorHandler.php (error handling)
└── → src/Logger.php (logging)
```

### Data Flow

```
User Input → Frontend Validation → API Processing → 
Business Logic → External API → Response Processing → 
Transaction Storage → Dashboard Display
```

## Configuration Management

### Environment Configuration

```
.env.example                    # Template configuration
.env                           # Actual configuration (gitignored)
```

**Key Environment Variables**:
- `SECRET_API_KEY`: GlobalPayments API key
- `DEVELOPER_ID`: Developer identification
- `VERSION_NUMBER`: API version
- `SERVICE_URL`: API endpoint URL
- `ENABLE_REQUEST_LOGGING`: Debug logging control

### Dependency Management

```
composer.json                  # Dependency definitions
composer.lock                  # Locked dependency versions
vendor/                        # Installed packages (gitignored)
```

## Logging and Storage

### Log File Structure

```
logs/
├── all-transactions.json      # Master transaction log
├── transactions-2025-01-30.json # Daily transaction files
├── transaction-errors.log     # Error tracking
├── system-2025-01-30.log     # System events
└── payment-2025-01-30.log    # Payment processing logs
```

### Data Storage Strategy

- **JSON-based**: Simple, portable data storage
- **Daily Files**: Organized by date for easy archival
- **Master Log**: Comprehensive transaction history
- **Error Logs**: Separate error tracking for debugging

## Security Considerations

### File Permissions

```bash
# Recommended permissions
find public/ -type f -exec chmod 644 {} \;
find api/ -type f -exec chmod 644 {} \;
find src/ -type f -exec chmod 644 {} \;
find tools/ -type f -exec chmod 755 {} \;
chmod -R 755 logs/
chmod 600 .env
```

### Access Control

- **Public Access**: `public/` directory only
- **API Access**: `api/` directory via web server configuration
- **Restricted Access**: `src/`, `tests/`, `tools/`, `logs/`, `vendor/`
- **No Access**: `.env`, configuration files

## Development Workflow

### Directory Usage by Role

| Role | Primary Directories | Access Level |
|------|-------------------|--------------|
| **Frontend Developer** | `public/`, `docs/API.md` | Read/Write |
| **Backend Developer** | `api/`, `src/`, `tests/` | Read/Write |
| **DevOps Engineer** | `docs/DEPLOYMENT.md`, `.env.example` | Read/Write |
| **QA Engineer** | `tests/`, `tools/` | Read/Execute |
| **System Administrator** | `logs/`, deployment configs | Read/Monitor |

### Build and Deploy Process

1. **Development**: Work in respective directories
2. **Testing**: Run tests from project root
3. **Building**: `composer install --optimize-autoloader`
4. **Deployment**: Follow `docs/DEPLOYMENT.md`
5. **Monitoring**: Check `logs/` directory

## Best Practices Implemented

### Code Organization

- **Separation of Concerns**: Clear boundaries between components
- **Single Responsibility**: Each file has one primary purpose
- **Dependency Direction**: Dependencies flow inward (frontend → API → business logic)
- **Test Isolation**: Tests separate from production code

### File Naming Conventions

- **Kebab-case**: HTML and CSS files (`card-verification.html`)
- **PascalCase**: PHP classes (`TransactionReporter.php`)
- **lowercase**: Directories and configuration files
- **UPPERCASE**: Documentation files (`README.md`)

### Documentation Standards

- **README-first**: Comprehensive main documentation
- **API Documentation**: Complete endpoint reference
- **Architecture Documentation**: System design overview
- **Deployment Documentation**: Production setup guide

This structure follows industry best practices for maintainability, scalability, and security while providing clear separation of concerns and comprehensive documentation.