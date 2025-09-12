# API Documentation

This document provides comprehensive documentation for all API endpoints in the Heartland Payment Integration system.

## Base URL

```
http://localhost:8000/api/
```

## Authentication

All API endpoints use the GlobalPayments SDK with credentials configured in the `.env` file. No additional authentication headers are required for local development.

## Endpoints

### 1. SDK Configuration

**Endpoint:** `GET /api/config.php`

Returns SDK configuration for frontend JavaScript integration.

#### Response

```json
{
  "success": true,
  "config": {
    "apiKey": "public_api_key",
    "environment": "sandbox",
    "version": "v1"
  }
}
```

#### Error Response

```json
{
  "success": false,
  "error": "Configuration error message"
}
```

---

### 2. Card Verification

**Endpoint:** `POST /api/verify-card.php`

Performs zero-charge card verification with AVS and CVV validation.

#### Request Body

```json
{
  "number": "4000000000000002",
  "exp_month": "12",
  "exp_year": "25",
  "cvv": "123",
  "zip": "12345",
  "address": "123 Main St"
}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `number` | string | Yes | Card number (16 digits for Visa/MC, 15 for Amex) |
| `exp_month` | string | Yes | Expiration month (MM) |
| `exp_year` | string | Yes | Expiration year (YY) |
| `cvv` | string | Yes | Card security code (3-4 digits) |
| `zip` | string | No | Billing ZIP code for AVS |
| `address` | string | No | Billing address for AVS |

#### Success Response

```json
{
  "success": true,
  "data": {
    "transaction_id": "123456789",
    "status": "approved",
    "response_code": "00",
    "response_message": "Approved",
    "avs_response": {
      "code": "Y",
      "message": "Address and postal code match"
    },
    "cvv_response": {
      "code": "M",
      "message": "CVV matches"
    },
    "card": {
      "type": "Visa",
      "last4": "0002",
      "exp_month": "12",
      "exp_year": "25"
    },
    "timestamp": "2025-01-30T10:30:00Z"
  }
}
```

#### Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid card number",
    "details": "Card number must be 15-16 digits"
  }
}
```

---

### 3. Payment Processing

**Endpoint:** `POST /api/process-payment.php`

Processes secure payment transactions through GlobalPayments.

#### Request Body

```json
{
  "amount": "10.99",
  "currency": "USD",
  "number": "4000000000000002",
  "exp_month": "12",
  "exp_year": "25",
  "cvv": "123",
  "billing": {
    "name": "John Doe",
    "address": "123 Main St",
    "city": "Anytown",
    "state": "NY",
    "zip": "12345",
    "country": "US"
  }
}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `amount` | string | Yes | Payment amount (decimal format) |
| `currency` | string | No | Currency code (default: USD) |
| `number` | string | Yes | Card number |
| `exp_month` | string | Yes | Expiration month (MM) |
| `exp_year` | string | Yes | Expiration year (YY) |
| `cvv` | string | Yes | Card security code |
| `billing` | object | No | Billing information object |

#### Success Response

```json
{
  "success": true,
  "data": {
    "transaction_id": "987654321",
    "status": "approved",
    "amount": "10.99",
    "currency": "USD",
    "response_code": "00",
    "response_message": "Approved",
    "authorization_code": "123456",
    "reference_number": "REF123456789",
    "batch_id": "BATCH001",
    "card": {
      "type": "Visa",
      "last4": "0002",
      "exp_month": "12",
      "exp_year": "25"
    },
    "gateway_response": {
      "code": "00",
      "message": "Transaction approved"
    },
    "timestamp": "2025-01-30T10:30:00Z"
  }
}
```

#### Error Response

```json
{
  "success": false,
  "error": {
    "code": "PAYMENT_DECLINED",
    "message": "Transaction declined",
    "details": "Insufficient funds",
    "response_code": "51"
  }
}
```

---

### 4. Transaction Data API

**Endpoint:** `GET /api/transactions-api.php`

Retrieves transaction data for dashboard display with filtering and pagination.

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Number of transactions to return (1-100, default: 25) |
| `page` | integer | No | Page number for pagination (default: 1) |
| `start_date` | string | No | Start date filter (YYYY-MM-DD) |
| `end_date` | string | No | End date filter (YYYY-MM-DD) |
| `transaction_id` | string | No | Specific transaction ID to retrieve |

#### Examples

```bash
# Get recent transactions
GET /api/transactions-api.php?limit=50

# Get transactions by date range
GET /api/transactions-api.php?start_date=2025-01-01&end_date=2025-01-31

# Get specific transaction
GET /api/transactions-api.php?transaction_id=123456789
```

#### Success Response (List)

```json
{
  "success": true,
  "data": {
    "transactions": [
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
        "reference": "REF123456789",
        "batch_id": "BATCH001"
      }
    ],
    "total_count": 1,
    "api_transactions": 1,
    "local_transactions": 0
  },
  "message": "Transactions retrieved successfully",
  "request_info": {
    "endpoint": "/api/transactions-api.php",
    "method": "GET",
    "timestamp": "2025-01-30T10:30:00Z",
    "parameters": {
      "limit": 25,
      "page": 1
    }
  }
}
```

#### Success Response (Single Transaction)

```json
{
  "success": true,
  "data": {
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
  },
  "message": "Transaction details retrieved successfully"
}
```

#### Error Response

```json
{
  "success": false,
  "message": "Transaction not found",
  "error": {
    "code": "TRANSACTION_NOT_FOUND",
    "details": "Transaction with ID '123456789' not found"
  }
}
```

---

## Response Codes

### Transaction Status Codes

| Code | Status | Description |
|------|--------|-------------|
| `00` | Approved | Transaction approved |
| `10` | Partially Approved | Partial approval |
| `96` | Declined | Transaction declined |
| `91` | Error | System error |

### AVS Response Codes

| Code | Description |
|------|-------------|
| `Y` | Address and postal code match |
| `A` | Address matches, postal code does not |
| `Z` | Postal code matches, address does not |
| `N` | Neither address nor postal code match |
| `U` | Address information unavailable |

### CVV Response Codes

| Code | Description |
|------|-------------|
| `M` | CVV matches |
| `N` | CVV does not match |
| `P` | CVV not processed |
| `S` | CVV should be present but is not |
| `U` | CVV service unavailable |

---

## Error Handling

All endpoints follow consistent error response format:

```json
{
  "success": false,
  "message": "Human-readable error message",
  "error": {
    "code": "ERROR_CODE",
    "details": "Detailed error information"
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_ERROR` | 400 | Invalid request parameters |
| `PAYMENT_DECLINED` | 400 | Payment was declined |
| `TRANSACTION_NOT_FOUND` | 404 | Transaction does not exist |
| `METHOD_NOT_ALLOWED` | 405 | Invalid HTTP method |
| `INTERNAL_ERROR` | 500 | Server error |
| `REPORTING_API_ERROR` | 502 | GlobalPayments API error |

---

## Rate Limiting

Currently, no rate limiting is implemented for development purposes. In production:

- Maximum 1000 requests per hour per IP
- Maximum 100 requests per minute per IP
- Burst limit of 10 requests per second

---

## Testing

### Test Cards

Use these test card numbers for development:

| Card Number | Type | Expected Result |
|-------------|------|-----------------|
| 4000000000000002 | Visa | Approved |
| 5400000000000005 | MasterCard | Approved |
| 370000000000002 | Amex | Approved |
| 4000000000000010 | Visa | Declined |
| 4000000000000028 | Visa | Expired Card |
| 4000000000000036 | Visa | Invalid CVV |

### Example Requests

#### cURL Examples

```bash
# Card Verification
curl -X POST http://localhost:8000/api/verify-card.php \
  -H "Content-Type: application/json" \
  -d '{
    "number": "4000000000000002",
    "exp_month": "12",
    "exp_year": "25",
    "cvv": "123",
    "zip": "12345"
  }'

# Payment Processing
curl -X POST http://localhost:8000/api/process-payment.php \
  -H "Content-Type: application/json" \
  -d '{
    "amount": "10.99",
    "number": "4000000000000002",
    "exp_month": "12",
    "exp_year": "25",
    "cvv": "123"
  }'

# Get Transactions
curl "http://localhost:8000/api/transactions-api.php?limit=10"
```

---

## Security Considerations

1. **Input Validation**: All inputs are validated and sanitized
2. **Error Handling**: Sensitive information is never exposed in error messages
3. **HTTPS**: Use HTTPS in production environments
4. **API Keys**: Secure storage of GlobalPayments credentials
5. **Transaction Filtering**: Only authentic API transactions are displayed

---

## Support

For API support:

1. Check error messages and response codes
2. Verify request format matches documentation
3. Test with provided test cards
4. Check application logs in `logs/` directory
5. Use debug tools in `tools/` directory