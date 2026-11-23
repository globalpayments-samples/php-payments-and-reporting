# API Documentation

The PHP endpoints behind the payment, card verification and dashboard pages of this sample. They run on the GP API through the GlobalPayments PHP SDK.

## Base URL

```
http://localhost:8000/api/
```

## Authentication

The endpoints use the GP API credentials in your `.env` file (`GP_API_APP_ID`, `GP_API_APP_KEY`). No extra headers are needed for local development.

## How payments work in this sample

Card data never goes to this server. The pages load the Drop-In UI (globalpayments.js hosted fields), which tokenizes the card straight with Global Payments and hands the page a payment token (`PMT_...`). The page then posts that token to `verify-card.php` or `process-payment.php`.

So to call these endpoints yourself you need a payment token first:

1. `POST /api/get-access-token.php` returns a short lived access token that can only create single use payment tokens.
2. Tokenize a test card with that access token, the same way the Drop-In UI does:

```bash
curl --compressed https://apis.sandbox.globalpay.com/ucp/payment-methods \
  -H "Authorization: Bearer <accessToken>" \
  -H "X-GP-Version: 2021-03-22" \
  -H "Content-Type: application/json" \
  -d '{
    "reference": "my-test-1",
    "usage_mode": "SINGLE",
    "card": {
      "number": "4263970000005262",
      "expiry_month": "12",
      "expiry_year": "30",
      "cvv": "123"
    }
  }'
```

3. Use the `id` from that response (`PMT_...`) as `payment_token` below. Each token is single use, so get a new one for every request.

## Endpoints

### 1. SDK Configuration

**Endpoint:** `GET /api/config.php`

Returns what the frontend needs to know about the integration.

#### Response

```json
{
  "success": true,
  "data": {
    "apiVersion": "v1",
    "supportedCardTypes": ["visa", "mastercard", "amex", "discover", "jcb", "diners"],
    "features": {
      "tokenization": true,
      "avs_verification": true,
      "cvv_verification": true,
      "three_d_secure": true,
      "stored_credentials": true
    },
    "verification_types": {
      "basic": "Basic card validation",
      "avs": "Address Verification Service",
      "cvv": "Card Verification Value check",
      "full": "Complete verification with all checks"
    }
  },
  "timestamp": "2026-11-19T17:09:49+00:00",
  "version": "1.0.0"
}
```

---

### 2. Access Token

**Endpoint:** `POST /api/get-access-token.php`

Returns a GP API access token limited to creating single use payment tokens (`PMT_POST_Create_Single`). The Drop-In UI uses it to tokenize the card in the browser.

#### Response

```json
{
  "success": true,
  "data": {
    "accessToken": "4DX3T1SngX3p2HBtLyLGK7ke1QZJ",
    "environment": "sandbox"
  }
}
```

---

### 3. Card Verification

**Endpoint:** `POST /api/verify-card.php`

Runs a zero amount verification on the tokenized card.

#### Request Body

```json
{
  "payment_token": "PMT_...",
  "verification_type": "basic",
  "card_details": {
    "type": "visa",
    "last4": "5262",
    "exp_month": "12",
    "exp_year": "30"
  }
}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `payment_token` | string | Yes | Payment token from the Drop-In UI (`PMT_...`) |
| `verification_type` | string | Yes | One of `basic`, `avs`, `cvv`, `full`. The sample runs the same verification for all of them and echoes the value back |
| `currency` | string | No | Currency code (default: `GP_API_CURRENCY` or USD) |
| `card_details` | object | No | Card type, last 4 and expiry as shown on the page, only used to label the result |

#### Success Response

```json
{
  "success": true,
  "message": "Card verification successful",
  "verification_result": {
    "id": "VER_wwHhRLsT8VhNYQ4DNp4jymi9Ti2JEf",
    "status": "VERIFIED",
    "channel": "CNP",
    "country": "US",
    "reference": "VER_20261119171026_d615e39f",
    "payment_method": {
      "result": "SUCCESS",
      "message": "VERIFIED",
      "entry_mode": "ECOM",
      "card": {
        "brand": "visa",
        "masked_number_last4": "5262"
      }
    },
    "action": {
      "result_code": "SUCCESS"
    }
  },
  "data": {
    "verified": true,
    "verification_type": "basic",
    "transaction_id": "VER_wwHhRLsT8VhNYQ4DNp4jymi9Ti2JEf",
    "reference": "VER_20261119171026_d615e39f"
  }
}
```

#### Error Response (400)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": [
    "payment_token (from the Drop-In UI) is required"
  ]
}
```

---

### 4. Payment Processing

**Endpoint:** `POST /api/process-payment.php`

Charges the tokenized card.

#### Request Body

```json
{
  "payment_token": "PMT_...",
  "amount": "10.99",
  "currency": "USD",
  "card_details": {
    "type": "visa",
    "last4": "5262",
    "exp_month": "12",
    "exp_year": "30"
  },
  "billing_address": {
    "street": "123 Main St",
    "city": "Anytown",
    "state": "NY",
    "postal_code": "12345",
    "country": "US"
  }
}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `payment_token` | string | Yes | Payment token from the Drop-In UI (`PMT_...`). `token_value` works too |
| `amount` | string | Yes | Amount in decimal format |
| `currency` | string | No | Currency code (default: `GP_API_CURRENCY` or USD) |
| `card_details` | object | No | Card type, last 4 and expiry for the local transaction record |
| `billing_address` | object | No | Billing address sent with the charge for AVS |
| `client_reference` | string | No | Your own order reference (one is generated if missing) |

#### Success Response

```json
{
  "success": true,
  "message": "Payment processed successfully",
  "data": {
    "transaction_id": "TRN_HrxO7rvJorRl988uAVSvfa5Rh2FDdb_0cc7f3a22d0b",
    "amount": "10.99",
    "currency": "USD",
    "status": "approved",
    "authorization_code": "123456",
    "response_code": "SUCCESS",
    "response_message": "CAPTURED",
    "processed_at": "2026-11-19T17:10:32+00:00",
    "card_type": "VISA",
    "card_last4": "XXXXXXXXXXXX5262",
    "avs_code": "MATCHED",
    "cvv_code": null
  }
}
```

#### Declined (422)

```json
{
  "success": false,
  "message": "Payment declined",
  "error": {
    "code": "DECLINED",
    "details": "DECLINED",
    "gp_response_code": "DECLINED",
    "gp_response_message": "DECLINED"
  }
}
```

#### Error Response (400)

```json
{
  "success": false,
  "message": "Payment token is required",
  "error": {
    "code": "PROCESSING_ERROR",
    "details": "Payment processing failed"
  }
}
```

---

### 5. Transaction Data API

**Endpoint:** `GET /api/transactions-api.php`

Returns transactions for the dashboard, from the GP API reporting endpoints plus the verifications this sample saved locally.

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Number of transactions to return (1-100, default: 25) |
| `page` | integer | No | Page number for pagination (default: 1) |
| `start_date` | string | No | Start date filter (YYYY-MM-DD), use with `end_date` |
| `end_date` | string | No | End date filter (YYYY-MM-DD), use with `start_date` |
| `transaction_id` | string | No | One transaction by its GP API id (`TRN_...`) |

#### Examples

```bash
# Recent transactions
GET /api/transactions-api.php?limit=50

# Transactions in a date range
GET /api/transactions-api.php?start_date=2026-11-01&end_date=2026-11-30

# One transaction
GET /api/transactions-api.php?transaction_id=TRN_HrxO7rvJorRl988uAVSvfa5Rh2FDdb_0cc7f3a22d0b
```

#### Success Response (List)

```json
{
  "success": true,
  "data": {
    "transactions": [
      {
        "id": "TRN_HrxO7rvJorRl988uAVSvfa5Rh2FDdb_0cc7f3a22d0b",
        "reference": "ORDER-1795108230-53585535",
        "status": "approved",
        "amount": "10.99",
        "currency": "USD",
        "type": "payment",
        "card": {
          "type": "visa",
          "last4": "5262",
          "exp_month": "12",
          "exp_year": "30"
        },
        "response": {
          "code": "SUCCESS",
          "message": "CAPTURED"
        },
        "gateway_response_code": "SUCCESS",
        "avs": {
          "code": "MATCHED",
          "message": ""
        },
        "cvv": {
          "code": "",
          "message": "MATCHED"
        },
        "timestamp": "2026-11-19T17:10:32+00:00",
        "gateway_response_message": "CAPTURED",
        "batch_id": "BAT_1756607",
        "auth_code": "123456"
      }
    ],
    "total_count": 1,
    "source": "global_payments_api_and_local",
    "note": "Showing transactions from Global Payments API and local verification records"
  },
  "message": "Transactions retrieved successfully from multiple sources",
  "request_info": {
    "endpoint": "/api/transactions-api.php?limit=1",
    "method": "GET",
    "timestamp": "2026-11-19T17:10:41+00:00",
    "parameters": {
      "limit": 1,
      "page": 1
    }
  }
}
```

#### Error Response

```json
{
  "success": false,
  "message": "Failed to retrieve transaction details: Status Code: RESOURCE_NOT_FOUND - Transactions TRN_nope not found at this /ucp/transactions/TRN_nope",
  "error": {
    "code": "REPORTING_API_ERROR",
    "details": "Status Code: RESOURCE_NOT_FOUND - Transactions TRN_nope not found at this /ucp/transactions/TRN_nope"
  }
}
```

---

## Response Values

| Field | Values |
|-------|--------|
| `status` | `approved`, `declined` |
| `response_code` | GP API result, e.g. `SUCCESS`, `DECLINED` |
| `response_message` | GP API transaction status, e.g. `CAPTURED`, `VERIFIED`, `DECLINED` |
| `avs_code` | GP API AVS result, e.g. `MATCHED`, `NOT_MATCHED`, `NOT_CHECKED` |

---

## Error Handling

Errors come back with `success: false` and a `message`. Validation errors on verify-card list them in `errors`; the other endpoints put a code in `error.code`:

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `PROCESSING_ERROR` | 400 | Missing or invalid request fields |
| `DECLINED` | 422 | The payment was declined |
| `METHOD_NOT_ALLOWED` | 405 | Wrong HTTP method |
| `TRANSACTION_NOT_FOUND` | 404 | Transaction does not exist |
| `REPORTING_API_ERROR` | 502 | The GP API reporting call failed |

---

## Testing

### Test Cards

These approve on the GP API sandbox with any future expiry (e.g. 12/30):

| Card Number | Type | CVV | Expected Result |
|-------------|------|-----|-----------------|
| 4263970000005262 | Visa | 123 | Approved |
| 4000000000000002 | Visa | 123 | Approved |
| 5425230000004415 | MasterCard | 123 | Approved |
| 2223000010005780 | MasterCard | 123 | Approved |
| 374101000000608 | Amex | 1234 | Approved |
| 4000000000000010 | Visa | 123 | Declined |

More test cards: [GP API test cards](https://developer.globalpay.com/resources/test-card-numbers).

### cURL Examples

```bash
# Access token for tokenizing
curl -X POST http://localhost:8000/api/get-access-token.php

# Card verification (payment_token from the Drop-In UI or the tokenize call above)
curl -X POST http://localhost:8000/api/verify-card.php \
  -H "Content-Type: application/json" \
  -d '{
    "payment_token": "PMT_...",
    "verification_type": "basic"
  }'

# Payment (needs a new payment_token)
curl -X POST http://localhost:8000/api/process-payment.php \
  -H "Content-Type: application/json" \
  -d '{
    "payment_token": "PMT_...",
    "amount": "10.99",
    "currency": "USD"
  }'

# Transactions
curl "http://localhost:8000/api/transactions-api.php?limit=10"
```

---

## Security Considerations

1. **No card data on the server**: the Drop-In UI tokenizes the card with Global Payments, the server only sees the payment token
2. **Restricted access token**: the token handed to the browser can only create single use payment tokens
3. **HTTPS**: use HTTPS in production
4. **Credentials**: keep the GP API app key in `.env`, never in the frontend

---

## Support

1. Check the error message and code in the response
2. Check the request against the parameters above
3. Test with the cards above
4. Check the application logs in `logs/`
