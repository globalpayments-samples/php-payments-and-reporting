# 🔐 Heartland Payment Integration - Clean Project Structure

## 📁 Core Application Files

### Frontend (HTML)
- `index.html` - Main hub with navigation
- `card-verification.html` - Card verification system  
- `payment.html` - Payment processing system
- `dashboard.html` - Transaction monitoring dashboard

### Backend (PHP)
- `config.php` - SDK configuration endpoint
- `verify-card.php` - Card verification processor
- `process-payment.php` - Payment processor (recommended)
- `heartland-process-payment.php` - Alternative payment processor
- `transactions-api.php` - Transaction data API

### Developer Tools (Optional)
- `test-payment.php` - Setup guide and environment check
- `debug-payment.php` - System diagnostics

### Configuration
- `.env` - Environment variables and API keys
- `vendor/` - Composer dependencies
- `src/` - Custom PHP classes

## 🎯 File Usage Map

```
index.html
├── → test-payment.php (setup guide)
└── → debug-payment.php (diagnostics)

card-verification.html
├── → config.php (SDK config)
├── → verify-card.php (verification)
└── → heartland-process-payment.php (payment fallback)

payment.html  
├── → config.php (SDK config)
└── → process-payment.php (payment processing)

dashboard.html
└── → transactions-api.php (transaction data)
```

## ✨ Clean & Production Ready
- ✅ All unused files removed
- ✅ Only essential functionality preserved  
- ✅ Clear separation of concerns
- ✅ Complete MVP solution
