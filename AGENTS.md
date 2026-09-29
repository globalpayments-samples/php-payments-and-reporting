# Global Payments PHP Payments and Reporting

> Verify and charge cards tokenized in the browser with the GP-API hosted card form, then report them from the GP-API Reporting service plus a local JSON log, using the Global Payments PHP SDK. PHP only.

## Critical Patterns

1. **Every request builds its own `GpApiConfig` and access token.** `api/get-access-token.php` calls `GpApiService::generateTransactionKey()` with `permissions = ['PMT_POST_Create_Single']` and returns the token to the browser for `GlobalPayments.configure()`. Unlike sibling samples, this token is generated through the SDK, not a hand-built `/ucp/accesstoken` call. `api/process-payment.php` also calls `generateTransactionKey()` as a preflight and fails with a 500 if the token has no `transactionProcessingAccountID` or `transactionProcessingAccountName`, so credentials must be linked to a transaction processing account.

2. **Verification is a plain `verify()` and `verification_type` is cosmetic.** `performVerification()` in `api/verify-card.php` always calls `verify()->withCurrency()->withAllowDuplicates(true)` with a `VER_...` reference as client transaction ID and idempotency key. `validateRequest()` requires `verification_type` to be `basic`, `avs`, `cvv`, or `full`, but the value never reaches the SDK (the page always sends `basic`). `validateRequest()` also accepts raw `card_number` / `expiry_month` / `expiry_year`, yet `performVerification()` rejects anything that is not a `PMT_` or `TKN_` token. Any SDK error about `TRN_POST_Verify`, `action_type - VERIFY`, or missing merchant config is rewritten to "Card verification is not enabled for this account".

3. **The dashboard merges two sources with no de-duplication.** `api/transactions-api.php` combines `TransactionReporter::getRecentTransactions()` (SDK `ReportingService::findTransactions()` over the last 3 days) or `getTransactionsByDateRange()` with `getLocalTransactions()` (from `logs/all-transactions.json`), sorts, and slices. Payments are both recorded locally by `recordTransaction()` and returned by Reporting, so they can appear twice. Outside PHPUnit (`PHPUNIT_RUNNING` unset), `recordTransaction()` and `getLocalTransactions()` drop IDs that look like test data, including anything starting `ORDER-`. A declined payment with no transaction ID falls back to its `ORDER-...` order ID and is therefore never stored.

4. **`api/config.php` is broken.** It does `require_once '/../vendor/autoload.php'` (a filesystem-root path, not `__DIR__`), so every request to `/api/config.php` or `/config.php` ends in a PHP fatal error. The Dockerfile `HEALTHCHECK` and both compose healthchecks target this URL. No page calls it, so the payment, verification, and dashboard flows still work.

## Repository Structure

### API endpoints (`api/`)
- [`api/get-access-token.php`](api/get-access-token.php): SDK-generated tokenization token for the browser
- [`api/verify-card.php`](api/verify-card.php): `loadEnvironmentAndToken()`, `validateRequest()`, `performVerification()`; records a `type: verification` row
- [`api/process-payment.php`](api/process-payment.php): token preflight, `charge()` with order ID and idempotency key, decline detection by response-code list, local recording; `safeGetProperty()` helper
- [`api/transactions-api.php`](api/transactions-api.php): `validateParams()`, `handleRequest()`; merges Reporting and local data
- [`api/config.php`](api/config.php): static feature list; currently fatal (see Critical Patterns 4)

### Library (`src/`, PSR-4 `GlobalPayments\Examples\`)
- [`src/TransactionReporter.php`](src/TransactionReporter.php): the reference for reporting; `configureSdk()`, `getRecentTransactions()`, `getTransactionsByDateRange()`, `getTransactionDetails()`, `recordTransaction()`, `getLocalTransactions()`, private `formatTransactionForDashboard()` and `validateTransactionAuthenticity()`
- [`src/Logger.php`](src/Logger.php): file logger used by `process-payment.php` (writes to `logs/`; `maskSensitiveData()` masks a fixed list of keys)
- [`src/ErrorHandler.php`](src/ErrorHandler.php): not used by any endpoint; excluded from coverage in `phpunit.xml`

### Frontend (`public/`)
- [`public/index.html`](public/index.html): landing page linking the three tools
- [`public/card-verification.html`](public/card-verification.html) and [`public/payment.html`](public/payment.html): load `https://js.globalpay.com/4.1.11/globalpayments.js`, fetch a token, render `GlobalPayments.creditCard.form()`, post the `paymentReference` as `payment_token`
- [`public/dashboard.html`](public/dashboard.html): calls `transactions-api.php?limit=100` and filters client-side; detail view uses `?transaction_id=`

### Root
- [`router.php`](router.php): router for `php -S`; serves `/api/*`, aliases `/config.php`, `/verify-card.php`, `/process-payment.php`, `/transactions-api.php`, redirects `/` to `/public/index.html`. It also maps `/heartland-process-payment.php` to a file that does not exist (returns 404).
- [`run.sh`](run.sh): checks PHP extensions and Composer, creates `.env` from `.env.example` if missing, frees the port, starts the router
- [`tests/`](tests/): `TransactionReporterTest.php` and `TransactionReporterIntegrationTest.php` (17 tests, local only, no network)
- [`Dockerfile`](Dockerfile) and [`docker-compose.yml`](docker-compose.yml): `development` and `production` targets, `app`, `app-prod` (profile `production`), and `tests` (profile `testing`)
- [`docs/`](docs/): `API.md`, `ARCHITECTURE.md`, `DEPLOYMENT.md`; partly stale (Heartland / Portico naming, `SECRET_API_KEY` deployment steps)

## API Surface

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/get-access-token.php` | Returns `{success, data: {accessToken, environment}}` |
| POST | `/api/verify-card.php` | JSON `{payment_token, verification_type, currency?, card_details?}`; zero-amount verification |
| POST | `/api/process-payment.php` | JSON `{payment_token or token_value, amount, currency?, billing_address?, client_reference?, card_details?}`; sale |
| GET | `/api/transactions-api.php` | `limit` (1 to 100, default 25), `page` (echoed only), `start_date` / `end_date` (`YYYY-MM-DD`), `transaction_id` |
| GET | `/api/config.php` | Broken (fatal error) |

`amount` is in major units (for example `10.99`), capped at 999999.99. All endpoints send `Access-Control-Allow-Origin: *`.

## Environment Variables

```bash
GP_API_APP_ID=your_app_id_here       # Required
GP_API_APP_KEY=your_app_key_here     # Required
GP_API_ENVIRONMENT=sandbox           # Only "production" switches to Environment::PRODUCTION; anything else is TEST
GP_API_ACCOUNT_ID=your_account_id_here  # Optional; verify-card sets it on accessTokenInfo, process-payment only logs a mismatch
ENABLE_REQUEST_LOGGING=false         # "true" attaches the SDK SampleRequestLogger in TransactionReporter
# Read by code but missing from .env.example:
# GP_API_COUNTRY=US      # verify-card and process-payment; default US
# GP_API_CURRENCY=USD    # default currency when the request has none
# GP_API_MERCHANT_ID=    # optional, process-payment only
```

A single `.env.example` sits at the repo root; copy it to `.env`. It also lists `GP_API_ACCOUNT_NAME`, `SERVICE_URL`, `APP_ENV`, `SESSION_TIMEOUT`, `MAX_REQUEST_SIZE`, `LOG_LEVEL`, `LOG_RETENTION_DAYS`, `DEBUG_MODE`, and `SHOW_ERRORS`, none of which are read by the code. `docker-compose.yml` injects Portico variables (`SECRET_API_KEY`, `DEVELOPER_ID`, `VERSION_NUMBER`, a Portico `SERVICE_URL`) that are also ignored; the GP-API values come from `env_file: .env`.

## Test Cards

| Brand | Number | Expected | CVV | Expiry |
|-------|--------|----------|-----|--------|
| Visa | 4263970000005262 | Approved | 123 | Any future date |
| Mastercard | 5425230000004415 | Approved | 123 | Any future date |
| Visa | 4000120000001154 | Declined | 123 | Any future date |

`public/payment.html` lists the two Visa numbers. The table in `README.md` uses different numbers and is not the GP-API sandbox set. Get sandbox credentials at [developer.globalpayments.com](https://developer.globalpayments.com).

## Architecture Summary

**Tokenize:** page → `POST /api/get-access-token.php` → `generateTransactionKey()` → `GlobalPayments.configure({accessToken, env})` → hosted card form → `PMT_...` payment reference.

**Verify / Pay:** page → `verify-card.php` or `process-payment.php` with the reference → new `GpApiConfig` → `CreditCardData` with `token` → `verify()` or `charge()` → `TransactionReporter::recordTransaction()` writes `logs/transactions-YYYY-MM-DD.json` and `logs/all-transactions.json` (last 5000).

**Report:** `dashboard.html` → `transactions-api.php` → Reporting API results plus local log → merged, sorted, limited.

## Security Notes

No authentication on any endpoint, CORS open to `*`, and transaction history stored as plain JSON under `logs/`. `process-payment.php` writes the access token to `error_log` and logs `token_value` unmasked through `src/Logger.php` (its `maskSensitiveData()` covers `payment_token` and `token` but not `token_value`); `verify-card.php` logs the full request body to `error_log`. For production: add auth, restrict CORS, remove token logging, and move storage to a real datastore.

## How to Run

```bash
composer install
cp .env.example .env         # then set GP_API_APP_ID / GP_API_APP_KEY
./run.sh                     # :8000, php -S 0.0.0.0:8000 -t . router.php
./run.sh -p 9000             # other port; -c checks only, -t runs tests first, -l tees logs/server.log
php -S localhost:8000 router.php   # equivalent without the checks
```

`run.sh` kills whatever is already listening on the chosen port. It requires the `curl`, `dom`, `openssl`, `json`, `mbstring`, `fileinfo`, and `intl` extensions. The card form is rendered in iframes by `globalpayments.js`, so verification and payment need a real browser at `http://localhost:8000/public/card-verification.html` and `/public/payment.html`.

## How to Verify

```bash
composer test     # Expected: OK (17 tests, 128 assertions)
composer lint     # PSR-12 on src/

curl -X POST http://localhost:8000/api/get-access-token.php
# Expected: {"success":true,"data":{"accessToken":"...","environment":"sandbox"}}

curl "http://localhost:8000/api/transactions-api.php?limit=5"
# Expected: {"success":true,"data":{"transactions":[...],"total_count":N,"source":"global_payments_api_and_local",...},"message":"...","request_info":{...}}

# Needs a real PMT_ reference from the browser form
curl -X POST http://localhost:8000/api/verify-card.php -H "Content-Type: application/json" \
  -d '{"payment_token":"PMT_xxx","verification_type":"basic","currency":"USD"}'
# Expected: {"success":true,"message":"Card verification successful","verification_result":{...},"data":{"verified":true,...}}

curl -X POST http://localhost:8000/api/process-payment.php -H "Content-Type: application/json" \
  -d '{"payment_token":"PMT_xxx","amount":10.00,"currency":"USD"}'
# Expected: {"success":true,"message":"Payment processed successfully","data":{"transaction_id":"TRN_...",...}}
```

Bad credentials return `{"success":false,...,"error":{"code":"VERIFICATION_ERROR","details":"Failed to generate access token: ..."}}` from `verify-card.php`. `/api/config.php` has no valid expected output until its `require_once` path is fixed.

## Making Changes

This is a single-language sample; do not add other language implementations without explicit instruction. The `public/*.html` pages parse the endpoint JSON directly, so keep response shapes stable or update the pages in the same change. When adding an endpoint under `api/`, add a root alias in `router.php` if the old-style path should work. Run `composer test` after touching `src/`. Running the tests writes test rows into the real `logs/` files and rewrites the tracked `test-results.xml`; do not commit that file by accident.

## SDK Versions

- **PHP**: `globalpayments/php-sdk` ^13.1 (committed `composer.lock` resolves 13.2.1), `vlucas/phpdotenv` ^5.5, PHP >=8.0
- **Dev**: `phpunit/phpunit` ^9.0, `squizlabs/php_codesniffer` ^3.6
- **JS**: `globalpayments.js` 4.1.11 from `js.globalpay.com`
- `composer.lock` is listed in `.gitignore` but is committed.
