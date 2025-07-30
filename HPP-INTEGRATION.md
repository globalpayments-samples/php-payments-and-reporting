# Global Payments Hosted Payment Page (HPP) Integration

> **⚠️ ARCHIVED DOCUMENTATION**
> 
> This document describes the legacy HPP integration that has been replaced with direct GlobalPayments SDK integration. This documentation is maintained for historical reference only.
> 
> **Current Implementation**: The system now uses direct SDK integration instead of HPP. See the main [README.md](README.md) for current documentation.

## Overview

This integration adds real transaction processing capability to the existing card verification system using Global Payments Hosted Payment Page (HPP). The system now supports both card authentication/verification (without charges) and real payment processing.

## Features Added

### 1. Hosted Payment Page Integration
- **HPP Session Creation**: Creates secure payment sessions that redirect users to Global Payments hosted payment form
- **Return URL Handling**: Processes successful payments, cancelled transactions, and errors
- **Webhook Support**: Real-time transaction notifications from Global Payments
- **Test Card Compatibility**: Full support for Global Payments test card suite

### 2. Dual Operation Modes
- **Authentication Mode**: Original card verification without charges
- **Payment Mode**: Real transaction processing via HPP

### 3. Enhanced Dashboard
- **Transaction Types**: Distinguishes between verification and payment transactions
- **Unified Display**: Shows both HPP transactions and verification data in one dashboard
- **Transaction Details**: Enhanced modal with payment-specific information

### 4. Local Transaction Storage
- **Persistent Storage**: HPP transactions stored locally in JSON format
- **Dashboard Integration**: Local transactions merged with API data
- **Data Retention**: Configurable transaction history retention

## Architecture

### Backend Components

#### 1. HPP Session Management (`hpp-create-session.php`)
```php
POST /hpp-create-session.php
```
**Purpose**: Creates Global Payments HPP sessions
**Input**: Transaction amount, currency, customer data, billing address
**Output**: HPP session ID and redirect URL

#### 2. Return URL Handlers
- **Success Handler** (`hpp-return.php`): Processes successful payments
- **Cancel Handler** (`hpp-cancel.php`): Handles cancelled transactions

#### 3. Webhook Handler (`hpp-webhook.php`)
```php
POST /hpp-webhook.php
```
**Purpose**: Receives real-time transaction notifications
**Security**: HMAC signature validation
**Processing**: Stores transaction data and updates local records

#### 4. Enhanced Transaction Storage
- **TransactionReporter**: Extended with `recordTransaction()` and `getLocalTransactions()` methods
- **File Storage**: JSON-based transaction logs with daily rotation
- **Data Merging**: Combines API and local transaction data

### Frontend Components

#### 1. Dual Mode Interface (`index.html`)
- **Mode Toggle**: Radio buttons to switch between authentication and payment modes
- **Dynamic Forms**: Context-sensitive form display
- **Payment Form**: Dedicated form for HPP transaction initiation

#### 2. Enhanced Dashboard (`dashboard.html`)
- **Transaction Type Column**: Visual indicators for payment vs verification
- **Unified Transaction List**: Merged display of all transaction types
- **Enhanced Details Modal**: Payment-specific information display

## Configuration

### Environment Variables
```env
# Existing Global Payments Configuration
SECRET_API_KEY=your_secret_key
PUBLIC_API_KEY=your_public_key
DEVELOPER_ID=000000
VERSION_NUMBER=0000
SERVICE_URL=https://cert.api2.heartlandportico.com

# HPP-Specific Configuration
HPP_WEBHOOK_SECRET=your_webhook_secret
HPP_STATUS_URL=https://yourdomain.com/hpp-webhook.php
```

### Return URLs
The system automatically configures return URLs based on the current domain:
- **Success**: `{domain}/hpp-return.php`
- **Cancel**: `{domain}/hpp-cancel.php`
- **Webhook**: `{domain}/hpp-webhook.php`

## File Structure

```
card-authentication/
├── hpp-create-session.php     # HPP session creation endpoint
├── hpp-return.php             # Success return handler
├── hpp-cancel.php             # Cancel return handler  
├── hpp-webhook.php            # Webhook notification handler
├── index.html                 # Enhanced UI with dual modes
├── dashboard.html             # Updated dashboard with transaction types
├── src/
│   └── TransactionReporter.php # Extended with local storage methods
├── css/
│   ├── index.css              # Updated with mode toggle styles
│   └── dashboard.css          # Updated with transaction type badges
└── logs/
    ├── transactions-YYYY-MM-DD.json # Daily transaction logs
    ├── all-transactions.json   # Master transaction log
    ├── hpp-sessions.json       # HPP session tracking
    ├── hpp-webhooks-YYYY-MM-DD.log # Webhook processing logs
    └── hpp-cancelled-YYYY-MM-DD.log # Cancelled transaction logs
```

## Security Considerations

### 1. PCI Compliance
- **Hosted Payment Page**: Reduces PCI scope to SAQ A (simplest level)
- **No Card Data Storage**: Card details processed securely by Global Payments
- **Token-Based Authentication**: Uses Global Payments tokens for card verification

### 2. Webhook Security
- **HMAC Signature Validation**: Verifies webhook authenticity
- **IP Whitelisting**: Consider restricting webhook access to Global Payments IPs
- **HTTPS Only**: All endpoints require secure connections

### 3. Data Protection
- **Local Storage Encryption**: Consider encrypting local transaction logs
- **Access Controls**: Implement appropriate file permissions
- **Audit Logging**: Comprehensive logging for security monitoring

## Usage Instructions

### For Card Authentication (Original Feature)
1. Select "Card Authentication" mode
2. Choose verification type (Basic, AVS, CVV, Full)
3. Fill in card details via Global Payments secure form
4. View verification results immediately

### For Real Payments (New Feature)
1. Select "Real Payment" mode
2. Enter transaction amount and reference
3. Fill in customer and billing information
4. Click "Start Secure Payment"
5. Complete payment on Global Payments hosted page
6. Return to application with transaction results

### Dashboard Usage
- **View All Transactions**: Both authentication and payment transactions
- **Filter by Type**: Use transaction type badges to identify transaction types
- **Transaction Details**: Click "View Details" for comprehensive transaction information
- **Export Data**: Export filtered transaction data to CSV

## Troubleshooting

### Common Issues

#### 1. HPP Session Creation Fails
- **Check API Keys**: Verify SECRET_API_KEY is correct
- **Review Logs**: Check `logs/system-YYYY-MM-DD.log` for errors
- **Validate Input**: Ensure amount and reference are provided

#### 2. Webhook Not Receiving Data
- **URL Accessibility**: Ensure webhook URL is publicly accessible
- **HTTPS Required**: Webhook URL must use HTTPS in production
- **Signature Validation**: Check webhook secret configuration

#### 3. Transactions Not Appearing in Dashboard
- **Check Local Storage**: Verify `logs/all-transactions.json` exists
- **File Permissions**: Ensure PHP can write to logs directory
- **Merge Logic**: Check transactions-api.php for merge issues

### Debug Mode
Enable debug logging by setting:
```php
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/debug.log');
```

## Performance Considerations

### 1. Transaction Storage
- **Daily Rotation**: Transaction logs rotate daily
- **Size Limits**: Maximum 1000 transactions per daily file, 5000 in master log
- **Cleanup**: Implement periodic cleanup of old log files

### 2. Dashboard Loading
- **Pagination**: Large transaction sets are paginated
- **Caching**: Consider implementing response caching for better performance
- **Indexing**: For high-volume scenarios, consider database storage

### 3. Webhook Processing
- **Async Processing**: Consider queuing webhook processing for high volume
- **Rate Limiting**: Implement rate limiting for webhook endpoints
- **Monitoring**: Monitor webhook processing times and success rates

## Future Enhancements

### Planned Features
1. **Database Storage**: Migration from JSON to database storage
2. **Advanced Reporting**: Enhanced analytics and reporting features
3. **Multi-Currency Support**: Extended currency support beyond USD
4. **Subscription Management**: Recurring payment support
5. **Refund Processing**: Transaction refund capabilities

### Integration Opportunities
1. **CRM Integration**: Customer data synchronization
2. **Accounting System**: Automated transaction recording
3. **Notification System**: Email/SMS transaction notifications
4. **Mobile App**: Mobile-specific payment flows

## Support and Maintenance

### Regular Maintenance Tasks
1. **Log Rotation**: Archive and cleanup old transaction logs
2. **Security Updates**: Keep Global Payments SDK updated
3. **Performance Monitoring**: Monitor transaction processing times
4. **Backup Strategy**: Regular backup of transaction data

### Monitoring and Alerts
- **Transaction Success Rates** 
- **Webhook Processing Health**
- **API Response Times**
- **Error Rate Thresholds**

---

## Quick Start Checklist

- [ ] Configure Global Payments API credentials
- [ ] Set up webhook endpoint and secret
- [ ] Test with Global Payments test cards
- [ ] Verify transaction storage and dashboard display
- [ ] Configure return URLs for your domain
- [ ] Test both authentication and payment modes
- [ ] Set up monitoring and logging
- [ ] Implement backup strategy for transaction data

For technical support, refer to the Global Payments developer documentation or contact your integration specialist.