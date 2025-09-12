# System Architecture

This document provides a comprehensive overview of the Heartland Payment Integration system architecture, including design decisions, data flow, and component interactions.

## Table of Contents

- [Overview](#overview)
- [System Architecture](#system-architecture)
- [Component Architecture](#component-architecture)
- [Data Flow](#data-flow)
- [Security Architecture](#security-architecture)
- [API Design](#api-design)
- [Database Design](#database-design)
- [Deployment Architecture](#deployment-architecture)
- [Performance Considerations](#performance-considerations)
- [Future Considerations](#future-considerations)

## Overview

The Heartland Payment Integration system is a comprehensive payment processing solution built using modern PHP architecture principles. It provides both card verification and payment processing capabilities through a clean, maintainable codebase.

### Key Design Principles

- **Separation of Concerns**: Clear boundaries between frontend, API, and business logic
- **Security First**: Input validation, secure tokenization, and proper error handling
- **Testability**: Comprehensive test coverage with unit and integration tests
- **Maintainability**: Clean code structure following PSR standards
- **Scalability**: Modular design supporting future enhancements

## System Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Client Layer (Browser)                   │
├─────────────────────────────────────────────────────────────┤
│  index.html  │  card-verification.html  │  payment.html     │
│              │                          │                   │
│  dashboard.html              │          CSS Assets          │
└─────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────┐
│                    Web Server Layer                         │
├─────────────────────────────────────────────────────────────┤
│              Nginx / Apache HTTP Server                     │
│           SSL Termination & Load Balancing                  │
└─────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────┐
│                  Application Layer (PHP)                    │
├─────────────────────────────────────────────────────────────┤
│  API Endpoints  │  Business Logic  │  Utilities             │
│  ──────────────────────────────────────────────────         │
│  • config.php         • TransactionReporter.php            │
│  • verify-card.php    • ErrorHandler.php                   │
│  • process-payment.php • Logger.php                        │
│  • transactions-api.php                                     │
└─────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────┐
│                   External Services                         │
├─────────────────────────────────────────────────────────────┤
│           GlobalPayments API (Portico)                      │
│           • Card Verification                               │
│           • Payment Processing                              │
│           • Transaction Reporting                           │
└─────────────────────────────────────────────────────────────┘
```

### Component Layers

1. **Presentation Layer**: HTML/CSS/JavaScript frontend interfaces
2. **API Layer**: RESTful PHP endpoints handling HTTP requests
3. **Business Logic Layer**: Core application logic and processing
4. **Integration Layer**: GlobalPayments SDK integration
5. **Data Layer**: JSON-based transaction storage and logging

## Component Architecture

### Frontend Components

```
public/
├── index.html                 # Main navigation hub
├── card-verification.html     # Card verification interface
├── payment.html              # Payment processing interface
├── dashboard.html            # Transaction monitoring dashboard
└── css/
    ├── index.css             # Main styling
    └── dashboard.css         # Dashboard-specific styling
```

**Design Patterns:**
- **Single Page Architecture**: Each HTML file is self-contained
- **Progressive Enhancement**: Core functionality works without JavaScript
- **Responsive Design**: Mobile-first approach with flexible layouts
- **Component Isolation**: Each page has dedicated CSS and JavaScript

### API Layer

```
api/
├── config.php               # SDK configuration endpoint
├── verify-card.php          # Card verification processing
├── process-payment.php      # Payment transaction processing
├── transactions-api.php     # Transaction data retrieval
└── heartland-process-payment.php  # Legacy payment processor
```

**Design Patterns:**
- **RESTful API**: Standard HTTP methods and status codes
- **Consistent Response Format**: Uniform JSON response structure
- **Error Handling**: Comprehensive error responses with proper codes
- **Input Validation**: All inputs validated and sanitized

### Business Logic Layer

```
src/
├── TransactionReporter.php   # Transaction management and reporting
├── ErrorHandler.php          # Centralized error handling
└── Logger.php               # Application logging utilities
```

**Design Patterns:**
- **Single Responsibility**: Each class has one primary responsibility
- **Dependency Injection**: Dependencies passed via constructor
- **Strategy Pattern**: Different processing strategies for various operations
- **Observer Pattern**: Event-driven logging and error handling

### Testing Layer

```
tests/
├── TransactionReporterTest.php           # Unit tests
└── TransactionReporterIntegrationTest.php # Integration tests
```

**Testing Strategy:**
- **Unit Testing**: Individual component testing with mocks
- **Integration Testing**: End-to-end workflow testing
- **Test Coverage**: 100% pass rate with comprehensive assertions
- **Mock Objects**: External API calls mocked for reliable testing

## Data Flow

### Card Verification Flow

```
User Input → Frontend Validation → API Request → SDK Processing → 
GlobalPayments API → Response Processing → Transaction Logging → 
User Response
```

1. **User Interaction**: User enters card details in verification form
2. **Client Validation**: JavaScript validates input format
3. **API Request**: POST to `/api/verify-card.php`
4. **Server Validation**: PHP validates and sanitizes input
5. **SDK Integration**: GlobalPayments SDK processes verification
6. **External API Call**: Request sent to Portico API
7. **Response Processing**: API response formatted for frontend
8. **Transaction Logging**: Transaction details logged locally
9. **User Response**: Results displayed with security details

### Payment Processing Flow

```
Payment Request → Amount Validation → Tokenization → Payment Processing → 
Transaction Recording → Response Formatting → Dashboard Update
```

1. **Payment Initiation**: User submits payment form
2. **Input Validation**: Amount and card details validated
3. **Secure Tokenization**: Card details securely tokenized
4. **Payment Processing**: GlobalPayments processes transaction
5. **Response Handling**: Payment result processed and validated
6. **Data Storage**: Transaction stored in local JSON files
7. **Real-time Update**: Dashboard updated with new transaction
8. **User Notification**: Payment result displayed to user

### Transaction Reporting Flow

```
Dashboard Request → Data Aggregation → Filtering → Pagination → 
CSV Export → Response Delivery
```

1. **Data Request**: Dashboard requests transaction data
2. **Data Aggregation**: Combine API and local transaction data
3. **Filtering**: Apply date range and status filters
4. **Authentication**: Validate transaction authenticity
5. **Sorting**: Order by timestamp (newest first)
6. **Pagination**: Limit results for performance
7. **Export Capability**: Generate CSV format when requested
8. **Response Delivery**: Send formatted data to frontend

## Security Architecture

### Security Layers

```
┌─────────────────────────────────────────────────────────────┐
│                    Transport Security                        │
│                    (HTTPS/TLS 1.3)                         │
└─────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────┐
│                  Application Security                       │
│  • Input Validation      • CSRF Protection                 │
│  • Output Encoding       • Rate Limiting                   │
│  • Error Handling        • Security Headers                │
└─────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────┐
│                     Data Security                           │
│  • No Card Storage       • Secure Tokenization            │
│  • Encrypted Logs        • Access Control                  │
│  • Audit Trails          • Data Validation                 │
└─────────────────────────────────────────────────────────────┘
```

### Security Measures

1. **Input Security**
   - All inputs validated using filter_var()
   - SQL injection prevention (no direct DB queries)
   - XSS prevention through output encoding
   - CSRF protection on state-changing operations

2. **Data Security**
   - No sensitive card data stored locally
   - Secure tokenization through GlobalPayments
   - Transaction filtering for authentic data only
   - Proper error handling without information leakage

3. **Transport Security**
   - HTTPS enforcement in production
   - TLS 1.2+ for all API communications
   - Secure headers (HSTS, CSP, X-Frame-Options)
   - Certificate pinning for external APIs

4. **Access Control**
   - Environment-based configuration
   - API key management through .env files
   - File system permissions
   - Directory access restrictions

## API Design

### RESTful Principles

The API follows REST principles with consistent patterns:

```
GET    /api/config.php                    # Configuration
POST   /api/verify-card.php               # Card verification
POST   /api/process-payment.php           # Payment processing
GET    /api/transactions-api.php          # Transaction data
GET    /api/transactions-api.php?id=123   # Specific transaction
```

### Response Format

All API responses follow a consistent structure:

```json
{
  "success": boolean,
  "data": object,
  "message": string,
  "error": {
    "code": string,
    "details": string
  },
  "request_info": {
    "endpoint": string,
    "method": string,
    "timestamp": string
  }
}
```

### Error Handling Strategy

```php
// Centralized error handling pattern
try {
    $result = processPayment($data);
    return successResponse($result);
} catch (ValidationException $e) {
    return errorResponse('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (PaymentException $e) {
    return errorResponse('PAYMENT_ERROR', $e->getMessage(), 402);
} catch (Exception $e) {
    logError($e);
    return errorResponse('INTERNAL_ERROR', 'An error occurred', 500);
}
```

## Database Design

### Data Storage Strategy

The system uses a **file-based storage approach** instead of traditional databases:

```
logs/
├── all-transactions.json           # Master transaction log
├── transactions-YYYY-MM-DD.json   # Daily transaction files
├── transaction-errors.log          # Error logging
└── system-YYYY-MM-DD.log          # System event logs
```

**Design Rationale:**
- **Simplicity**: No database setup or maintenance required
- **Portability**: Easy to deploy and backup
- **Performance**: Fast reads for dashboard display
- **Compliance**: No sensitive data stored locally

### Transaction Data Structure

```json
{
  "id": "123456789",
  "amount": "10.99",
  "currency": "USD",
  "status": "approved",
  "type": "payment",
  "timestamp": "2025-01-30T10:30:00Z",
  "card": {
    "type": "Visa",
    "last4": "0002",
    "exp_month": "12",
    "exp_year": "25"
  },
  "response": {
    "code": "00",
    "message": "Approved"
  },
  "avs": {
    "code": "Y",
    "message": "Address and postal code match"
  },
  "cvv": {
    "code": "M",
    "message": "CVV matches"
  },
  "reference": "REF123456789",
  "batch_id": "BATCH001",
  "gateway_response_code": "00",
  "gateway_response_message": "Transaction approved"
}
```

### Data Lifecycle

1. **Creation**: Transaction data created during processing
2. **Storage**: Stored in daily JSON files and master log
3. **Retrieval**: Loaded for dashboard display with filtering
4. **Archival**: Old files can be compressed or archived
5. **Cleanup**: Automatic cleanup of old log files

## Deployment Architecture

### Single Server Deployment

```
┌─────────────────────────────────────────────────────────────┐
│                     Production Server                       │
├─────────────────────────────────────────────────────────────┤
│  Nginx (80, 443)  │  PHP-FPM (9000)  │  Application       │
│  ──────────────────────────────────────────────────────     │
│  • SSL Termination • Process Management • Business Logic   │
│  • Load Balancing  • Memory Management  • File Storage     │
│  • Static Assets   • Error Handling     • Logging          │
└─────────────────────────────────────────────────────────────┘
```

### Multi-Server Deployment

```
┌─────────────────────┐    ┌─────────────────────┐
│    Load Balancer    │    │    App Server 1     │
│   (Nginx/HAProxy)   │────│  (PHP-FPM + Files) │
│                     │    └─────────────────────┘
│                     │    ┌─────────────────────┐
│                     │────│    App Server 2     │
└─────────────────────┘    │  (PHP-FPM + Files) │
                           └─────────────────────┘
```

### Container Deployment

```
┌─────────────────────────────────────────────────────────────┐
│                    Docker Container                         │
├─────────────────────────────────────────────────────────────┤
│  Supervisor  │  Nginx   │  PHP-FPM  │  Application         │
│  ──────────────────────────────────────────────────         │
│  • Process   • HTTP     • FastCGI   • Business Logic       │
│    Control   • SSL      • Pool Mgmt • File Storage         │
│  • Logging   • Routing  • Memory    • Error Handling       │
└─────────────────────────────────────────────────────────────┘
```

## Performance Considerations

### Optimization Strategies

1. **PHP Optimization**
   ```ini
   ; OPcache configuration
   opcache.enable=1
   opcache.memory_consumption=256
   opcache.max_accelerated_files=10000
   opcache.validate_timestamps=0
   
   ; Memory management
   memory_limit=256M
   max_execution_time=30
   ```

2. **Web Server Optimization**
   ```nginx
   # Nginx configuration
   gzip on;
   gzip_types text/plain text/css application/json application/javascript;
   
   # Caching headers
   location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
       expires 1y;
       add_header Cache-Control "public, immutable";
   }
   ```

3. **Application Optimization**
   - Efficient JSON parsing and generation
   - Minimal file I/O operations
   - Optimized transaction filtering
   - Lazy loading of large datasets

### Scalability Patterns

1. **Horizontal Scaling**
   - Stateless application design
   - Shared file storage (NFS/EFS)
   - Load balancer distribution
   - Auto-scaling groups

2. **Vertical Scaling**
   - Increased memory allocation
   - More CPU cores
   - SSD storage for logs
   - PHP-FPM pool optimization

3. **Caching Strategy**
   - Application-level caching (APCu)
   - Static file caching
   - API response caching
   - CDN for static assets

## Future Considerations

### Planned Enhancements

1. **Database Migration**
   - PostgreSQL or MySQL integration
   - Advanced querying capabilities
   - Better transaction analytics
   - Improved data integrity

2. **Microservices Architecture**
   ```
   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐
   │  Verification │  │   Payment   │  │  Reporting  │
   │   Service    │  │   Service   │  │   Service   │
   └─────────────┘  └─────────────┘  └─────────────┘
           │                │                │
           └────────────────┼────────────────┘
                           │
                   ┌─────────────┐
                   │ API Gateway │
                   └─────────────┘
   ```

3. **Advanced Features**
   - Real-time notifications (WebSockets)
   - Advanced analytics and reporting
   - Multi-tenant support
   - API rate limiting and quotas

### Technology Evolution

1. **PHP 8.3+ Features**
   - Enhanced type system
   - Performance improvements
   - New language features
   - Better error handling

2. **Frontend Modernization**
   - React/Vue.js integration
   - Progressive Web App (PWA)
   - Real-time updates
   - Enhanced user experience

3. **DevOps Integration**
   - CI/CD pipelines
   - Infrastructure as Code
   - Monitoring and alerting
   - Automated testing

### Migration Path

1. **Phase 1**: Database integration while maintaining file storage
2. **Phase 2**: Microservices extraction for high-traffic components
3. **Phase 3**: Frontend modernization with API-first approach
4. **Phase 4**: Advanced features and analytics platform

This architecture provides a solid foundation for current requirements while supporting future growth and enhancement opportunities.