# PHP Card Authentication Example

A comprehensive PHP implementation demonstrating secure card authentication and verification using the Global Payments SDK. This project showcases various authentication scenarios without processing actual charges, making it perfect for subscription setups, card validation, and customer onboarding workflows.

## 🚀 Features

- **Multiple Verification Types**
  - Basic card validation (number + expiration)
  - Address Verification Service (AVS) checks
  - Card Verification Value (CVV) validation
  - Comprehensive verification with all checks combined

- **Advanced Security**
  - Secure card tokenization
  - PCI-compliant implementation
  - Client-side card data encryption
  - Server-side validation and sanitization

- **Flexible Authentication Options**
  - Real-time card verification
  - Stored card re-verification
  - Batch verification for multiple cards
  - Customer data integration

- **Transaction Dashboard** ✨ *NEW!*
  - Real-time transaction monitoring
  - Global Payments brand-compliant interface
  - Advanced filtering and search capabilities
  - Transaction history and analytics
  - Mobile-responsive design

- **Production-Ready Code**
  - Comprehensive error handling
  - Detailed logging and monitoring
  - Rate limiting and security headers
  - Docker containerization support

## 📋 Requirements

- **PHP 8.0+** with required extensions:
  - `curl` - For API communication
  - `dom` - For XML processing
  - `openssl` - For secure connections
  - `json` - For data handling
  - `mbstring` - For string manipulation
  - `fileinfo` - For file type detection
  - `intl` - For internationalization
  - `zlib` - For compression

- **Composer** for dependency management
- **Global Payments Account** with API credentials
- **SSL Certificate** for production use

## 🛠️ Installation

### 1. Clone the Repository

```bash
git clone https://github.com/your-org/php-card-authentication.git
cd php-card-authentication
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Configuration

```bash
# Copy environment template
cp .env.sample .env

# Edit with your API credentials
nano .env
```

**Required Environment Variables:**
```env
PUBLIC_API_KEY=pkapi_cert_your_public_key_here
SECRET_API_KEY=skapi_cert_your_secret_key_here
```

### 4. Start Development Server

```bash
# Using Composer script
composer serve

# Or manually
php -S localhost:8000
```

### 5. Access the Application

Open your browser and navigate to:
- **Web Interface**: http://localhost:8000
- **Configuration API**: http://localhost:8000/config.php
- **Verification API**: http://localhost:8000/verify-card.php

## 🏗️ Project Structure

```
php-card-authentication/
├── 📁 examples/                    # Example scripts
│   ├── basic-verification.php      # Basic card validation
│   ├── advanced-verification.php   # AVS + CVV checks
│   └── stored-card-verify.php     # Token-based verification
├── 📁 src/                        # Source code
│   ├── TransactionReporter.php    # ✨ Transaction reporting class
│   ├── ErrorHandler.php           # Error handling utilities
│   └── Logger.php                 # Logging utilities
├── 📁 logs/                       # Application logs
├── 📁 tests/                      # PHPUnit tests
├── 📁 docs/                       # Documentation
├── config.php                     # API configuration endpoint
├── index.html                     # Web interface
├── dashboard.html                 # ✨ Transaction dashboard
├── verify-card.php               # Main verification API
├── tokenize-card.php             # Tokenization endpoint
├── transactions-api.php          # ✨ Transaction reporting API
├── composer.json                 # Dependencies
├── .env.sample                   # Environment template
├── Dockerfile                    # Docker configuration
├── README.md                     # This documentation
└── run.sh                        # Development server script
```

## 🔧 API Endpoints

### GET /config.php
Returns public configuration for client-side SDK initialization.

**Response:**
```json
{
  "success": true,
  "data": {
    "publicApiKey": "pkapi_cert_...",
    "environment": "sandbox",
    "supportedCardTypes": ["visa", "mastercard", "amex", "discover"],
    "verification_types": {
      "basic": "Basic card validation",
      "avs": "Address Verification Service",
      "cvv": "Card Verification Value check", 
      "full": "Complete verification with all checks"
    }
  }
}
```

### POST /verify-card.php
Performs card authentication/verification.

### GET /transactions-api.php ✨ *NEW!*
Returns transaction data for the dashboard.

**Parameters:**
- `limit` - Number of transactions to return (default: 25, max: 100)
- `page` - Page number for pagination (default: 1)
- `start_date` - Start date filter (YYYY-MM-DD format)
- `end_date` - End date filter (YYYY-MM-DD format)
- `transaction_id` - Specific transaction ID to retrieve

**Example Response:**
```json
{
  "success": true,
  "data": {
    "transactions": [
      {
        "id": "TXN123456",
        "amount": "0.00",
        "status": "approved",
        "timestamp": "2024-01-15 10:30:00",
        "card": {
          "type": "visa",
          "last4": "1234"
        },
        "response": {
          "code": "00",
          "message": "Approved"
        }
      }
    ],
    "pagination": {
      "page": 1,
      "pageSize": 25,
      "totalCount": 1
    }
  }
}
```

**Request:**
```json
{
  "payment_token": "card_token_from_client",
  "verification_type": "full",
  "billing_address": {
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postal_code": "12345",
    "country": "US"
  },
  "customer": {
    "id": "CUST_123",
    "email": "customer@example.com",
    "phone": "5551234567"
  }
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Card verification successful",
  "verification_result": {
    "transaction_id": "TXN_123456789",
    "response_code": "00",
    "response_message": "APPROVAL",
    "avs_response_code": "Y",
    "cvn_response_code": "M",
    "card_type": "Visa"
  },
  "data": {
    "verified": true,
    "verification_type": "full"
  }
}
```

### POST /tokenize-card.php
Tokenizes card data for secure storage.

**Request:**
```json
{
  "card_number": "4111111111111111",
  "exp_month": 12,
  "exp_year": 2025,
  "cvv": "123",
  "cardholder_name": "John Doe",
  "verify_card": true
}
```

## 💡 Usage Examples

### Basic Verification

```php
use GlobalPayments\Api\PaymentMethods\CreditCardData;

// Create card object with token
$card = new CreditCardData();
$card->token = 'your_payment_token';

// Perform basic verification
$response = $card->verify()
    ->withAllowDuplicates(true)
    ->execute();

if ($response->responseCode === '00') {
    echo "Card verified successfully!";
    echo "Transaction ID: " . $response->transactionReference->transactionId;
}
```

### Advanced Verification with AVS

```php
use GlobalPayments\Api\Entities\Address;

// Create address for AVS check
$address = new Address();
$address->streetAddress1 = '123 Main St';
$address->city = 'New York';
$address->state = 'NY';
$address->postalCode = '12345';

// Verify with address
$response = $card->verify()
    ->withAddress($address)
    ->execute();

// Check AVS results
$avsMatch = in_array($response->avsResponseCode, ['A', 'B', 'D', 'M', 'X', 'Y']);
echo "AVS Match: " . ($avsMatch ? 'Yes' : 'No');
```

### Stored Card Verification

```php
// Verify previously tokenized card
$storedCard = new CreditCardData();
$storedCard->token = 'stored_token_value';

$response = $storedCard->verify()->execute();

if ($response->responseCode === '00') {
    echo "Stored card is still valid!";
} else {
    echo "Card may be expired or invalid: " . $response->responseMessage;
}
```

## 🧪 Testing

### Run Unit Tests

```bash
# Install dev dependencies
composer install --dev

# Run PHPUnit tests
composer test

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Example Test Scripts

```bash
# Basic verification test
php examples/basic-verification.php

# Advanced verification test  
php examples/advanced-verification.php

# Stored card verification test
php examples/stored-card-verify.php
```

### Test Cards

Use these test card numbers in sandbox environment:

| Card Type   | Number           | Exp | CVV  | Expected Result |
|-------------|------------------|-----|------|-----------------|
| Visa        | 4111111111111111 | 12/25 | 123 | Approval       |
| MasterCard  | 5454545454545454 | 11/25 | 999 | Approval       |
| Amex        | 378282246310005  | 10/25 | 1234| Approval       |
| Discover    | 6011000000000012 | 09/25 | 123 | Approval       |
| Declined    | 4000000000000002 | 12/25 | 123 | Decline        |

## 🐳 Docker Support

### Using Docker

```bash
# Build the image
docker build -t php-card-auth .

# Run the container
docker run -p 8000:8000 --env-file .env php-card-auth

# Or use docker-compose
docker-compose up -d
```

### docker-compose.yml

```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8000:8000"
    env_file:
      - .env
    volumes:
      - .:/app
    environment:
      - PHP_ENV=development
```

## 🔒 Security Considerations

### Production Checklist

- [ ] **Environment Variables**: Store sensitive data in environment variables
- [ ] **HTTPS Only**: Force HTTPS in production
- [ ] **Input Validation**: Validate and sanitize all input data
- [ ] **Rate Limiting**: Implement API rate limiting
- [ ] **Error Handling**: Don't expose sensitive error details
- [ ] **Logging**: Log security events and failed attempts
- [ ] **CORS**: Configure appropriate CORS headers
- [ ] **Security Headers**: Implement security headers (CSP, HSTS, etc.)
- [ ] **Token Security**: Secure token storage and transmission
- [ ] **PCI Compliance**: Follow PCI DSS requirements

### Security Headers

The application automatically sets security headers:

```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
```

## 📊 Response Codes

### Transaction Response Codes

| Code | Message | Description |
|------|---------|-------------|
| 00   | APPROVAL | Transaction approved |
| 05   | DECLINE | Transaction declined |
| 14   | INVALID_CARD | Invalid card number |
| 41   | LOST_CARD | Lost card - pick up |
| 43   | STOLEN_CARD | Stolen card - pick up |
| 54   | EXPIRED | Expired card |
| 51   | INSUFFICIENT_FUNDS | Insufficient funds |

### AVS Response Codes

| Code | Description |
|------|-------------|
| A    | Address match only |
| B    | Address match only (international) |
| D    | Address and postal code match (international) |
| M    | Address and postal code match |
| N    | No match |
| P    | Postal code match only (international) |
| X    | Address and postal code match |
| Y    | Address and postal code match |
| Z    | Postal code match only |

### CVV Response Codes

| Code | Description |
|------|-------------|
| M    | CVV match |
| N    | CVV no match |
| P    | Not processed |
| S    | CVV should be on card but merchant indicated not present |
| U    | Issuer not certified or not provided |

## 📊 Transaction Dashboard ✨ *NEW!*

The project now includes a comprehensive transaction dashboard that provides real-time monitoring of your Global Payments transactions.

### Features

- **Real-time Transaction Monitoring**: View completed transactions from your Global Payments account
- **Global Payments Branding**: Follows official GP brand guidelines with proper colors and typography
- **Advanced Filtering**: Filter by date range, transaction status, and search specific transactions
- **Responsive Design**: Works seamlessly on desktop and mobile devices
- **Transaction Analytics**: View key metrics like approval rates and total amounts
- **Direct Integration**: Seamlessly integrates with existing card authentication flow

### Accessing the Dashboard

1. **From the Main Interface**: Click the "📊 View Transaction Dashboard" button on the main card authentication page
2. **Direct Access**: Navigate to `/dashboard.html` in your browser
3. **API Integration**: Use the `/transactions-api.php` endpoint for programmatic access

### Dashboard Components

- **Statistics Cards**: Overview of total transactions, approvals, declines, and amounts
- **Transaction Table**: Detailed view of all transactions with key information
- **Filtering Controls**: Date range selection and status filtering
- **Search Functionality**: Find specific transactions by ID, card number, or response message
- **Pagination**: Handle large datasets with efficient pagination

### API Usage

The dashboard uses the Global Payments Reporting API to fetch transaction data:

```php
// Example: Get recent transactions
$reporter = new TransactionReporter();
$transactions = $reporter->getRecentTransactions(50, 1);

// Example: Get transactions by date range
$transactions = $reporter->getTransactionsByDateRange('2024-01-01', '2024-01-31');

// Example: Get specific transaction details
$transaction = $reporter->getTransactionDetails('TXN123456');
```

### Brand Compliance

The dashboard follows Global Payments brand guidelines:
- **Primary Color**: #262AFF (Global Blue)
- **Typography**: DM Sans font family
- **Design Elements**: Rounded corners, clean layouts, and proper spacing
- **Responsive**: Mobile-first design approach

## 🚀 Production Deployment

### Server Requirements

- **PHP 8.0+** with required extensions
- **Nginx/Apache** web server
- **SSL Certificate** (required)
- **Composer** for dependency management
- **Redis** (optional, for caching/rate limiting)

### Deployment Steps

1. **Clone repository** to production server
2. **Install dependencies**: `composer install --no-dev --optimize-autoloader`
3. **Configure environment**: Copy `.env.sample` to `.env` with production values
4. **Set permissions**: Ensure proper file permissions
5. **Configure web server**: Set up virtual host with SSL
6. **Test endpoints**: Verify all APIs are working
7. **Monitor logs**: Set up log monitoring and alerts

### Environment Variables (Production)

```env
APP_ENV=production
APP_DEBUG=false
SERVICE_URL=https://api2.heartlandportico.com
PUBLIC_API_KEY=pkapi_prod_your_key
SECRET_API_KEY=skapi_prod_your_key
ALLOWED_ORIGINS=https://yourdomain.com
```

## 📝 Changelog

### Version 1.0.0 (Latest)

- ✅ Initial release
- ✅ Basic card verification
- ✅ AVS and CVV checks
- ✅ Stored card verification
- ✅ Comprehensive error handling
- ✅ Docker support
- ✅ Security best practices
- ✅ Complete documentation

## 🤝 Contributing

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** your changes (`git commit -m 'Add amazing feature'`)
4. **Push** to the branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

### Development Guidelines

- Follow **PSR-12** coding standards
- Write **unit tests** for new features
- Update **documentation** for API changes
- Run **linting** before committing: `composer lint`
- Ensure **security** best practices

## 📞 Support

- **Documentation**: https://developer.globalpay.com
- **SDK Repository**: https://github.com/globalpayments/php-sdk
- **Developer Support**: developer@globalpay.com
- **Community**: https://developer.globalpay.com/community

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## ⚠️ Disclaimer

This is a demonstration application for educational purposes. Always follow PCI DSS requirements and security best practices when handling card data in production environments.

---

**🔗 Quick Links:**
- [Global Payments Developer Portal](https://developer.globalpay.com)
- [PHP SDK Documentation](https://github.com/globalpayments/php-sdk)
- [API Reference](https://developer.globalpay.com/api)
- [Security Guidelines](https://developer.globalpay.com/security)