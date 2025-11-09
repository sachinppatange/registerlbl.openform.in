# Payment Integration Guide - Razorpay

This document provides setup instructions for the Razorpay payment integration in the LBL Registration System.

## Overview

The payment system allows users to complete their registration by paying a fee (default Rs.1) through Razorpay. The integration includes:

- Payment initiation and order creation
- Razorpay checkout integration
- Payment verification with signature validation
- Webhook handling for payment events
- Payment receipts and status tracking
- Secure storage of payment records

## Prerequisites

- PHP 7.4 or higher
- MySQL/MariaDB database
- Composer (PHP dependency manager)
- Razorpay account (test or live)

## Installation Steps

### 1. Install Dependencies

Run the following command in the project root directory to install the Razorpay PHP SDK:

```bash
composer require razorpay/razorpay
```

This will install the `razorpay/razorpay` package and create a `vendor/` directory with all dependencies.

### 2. Database Migration

Execute the SQL migration file to create the `payments` table:

```bash
mysql -u your_username -p your_database < migrations/2025_11_09_create_payments_table.sql
```

Or manually run the SQL file using your preferred database management tool (phpMyAdmin, MySQL Workbench, etc.).

The migration creates a `payments` table with the following structure:
- `id` - Primary key
- `player_id` - Foreign key to players table (nullable)
- `order_id` - Razorpay order ID
- `payment_id` - Razorpay payment ID (set after payment)
- `amount` - Amount in paise (smallest currency unit)
- `currency` - Currency code (default: INR)
- `status` - Payment status (pending/paid/failed)
- `metadata` - JSON field for additional data
- `created_at` - Timestamp
- `updated_at` - Timestamp

### 3. Environment Variables

Set the following environment variables with your Razorpay credentials:

**For Test Environment:**
```bash
export RZP_KEY_ID="rzp_test_xxxxxxxxxxxxx"
export RZP_KEY_SECRET="your_test_secret_key"
export RZP_WEBHOOK_SECRET="your_webhook_secret"
```

**For Production Environment:**
```bash
export RZP_KEY_ID="rzp_live_xxxxxxxxxxxxx"
export RZP_KEY_SECRET="your_live_secret_key"
export RZP_WEBHOOK_SECRET="your_webhook_secret"
```

**Setting Environment Variables:**

- **Linux/Mac (bash/zsh)**: Add to `~/.bashrc` or `~/.zshrc`
- **Windows**: Use System Properties > Environment Variables
- **Apache**: Add to `.htaccess` or VirtualHost configuration:
  ```apache
  SetEnv RZP_KEY_ID "rzp_test_xxxxxxxxxxxxx"
  SetEnv RZP_KEY_SECRET "your_test_secret_key"
  SetEnv RZP_WEBHOOK_SECRET "your_webhook_secret"
  ```
- **PHP-FPM/Nginx**: Add to `php-fpm` pool configuration

**Important:** Never commit these keys to version control. They are read from environment variables only.

### 4. Get Razorpay Credentials

1. Sign up at [Razorpay Dashboard](https://dashboard.razorpay.com/)
2. For testing, use **Test Mode** and get test API keys
3. Navigate to Settings > API Keys
4. Generate and note down:
   - Key ID (starts with `rzp_test_` for test mode)
   - Key Secret
5. For webhooks:
   - Go to Settings > Webhooks
   - Create a new webhook
   - Set URL: `https://yourdomain.com/userpanel/payment/webhook.php`
   - Select events: `payment.captured` and `payment.failed`
   - Note down the Webhook Secret

### 5. Configure Webhook URL

In your Razorpay dashboard:
1. Go to Settings > Webhooks
2. Add webhook URL: `https://yourdomain.com/userpanel/payment/webhook.php`
3. Select events to listen:
   - ✅ `payment.captured`
   - ✅ `payment.failed`
4. Set authentication (optional): Use the webhook secret
5. Save webhook configuration

**Note:** For local testing, use ngrok or similar tool to create a public URL:
```bash
ngrok http 80
# Use the generated URL: https://xxxx.ngrok.io/userpanel/payment/webhook.php
```

## File Structure

```
├── composer.json                           # Composer dependencies
├── config/
│   └── payment_config.php                  # Payment configuration
├── libs/
│   └── RazorpayClient.php                  # Razorpay wrapper class
├── migrations/
│   └── 2025_11_09_create_payments_table.sql # Database migration
├── userpanel/
│   ├── player_profile.php                  # Modified with payment button
│   ├── player_repository.php               # Modified with payment functions
│   ├── js/
│   │   └── payment.js                      # Frontend payment flow
│   └── payment/
│       ├── initiate_payment.php            # Create Razorpay order
│       ├── verify_payment.php              # Verify payment signature
│       ├── webhook.php                     # Webhook handler
│       ├── return.php                      # Payment status page
│       └── receipt.php                     # Printable receipt
├── storage/
│   └── logs/
│       └── payment_webhook.log             # Webhook event logs (auto-created)
└── README_PAYMENT.md                       # This file
```

## Usage

### For Users

1. Navigate to Player Profile page (`userpanel/player_profile.php`)
2. Fill in all required profile information
3. Click **"Save & Pay"** button
4. Complete payment through Razorpay checkout modal
5. View payment status and receipt

### Payment Flow

1. **Profile Save**: Form data is submitted via AJAX to save profile
2. **Payment Initiation**: Creates a Razorpay order and payment record (status: pending)
3. **Razorpay Checkout**: Opens Razorpay modal for payment
4. **Payment Verification**: Validates signature and updates status (status: paid)
5. **Redirect**: Redirects to payment status page with receipt link

### Webhook Events

The webhook handler processes these events:
- `payment.captured`: Updates payment status to 'paid'
- `payment.failed`: Updates payment status to 'failed'

All webhook events are logged to `storage/logs/payment_webhook.log` with minimal information (no PII or raw payloads).

## Testing

### Test with Razorpay Test Mode

1. Use test API keys (starting with `rzp_test_`)
2. Test card details (provided by Razorpay):
   - **Card Number**: `4111 1111 1111 1111`
   - **Expiry**: Any future date
   - **CVV**: Any 3 digits
   - **OTP**: Any 6 digits

3. Test payment scenarios:
   - **Successful payment**: Complete the payment normally
   - **Failed payment**: Use Razorpay's test failure cards
   - **Webhook testing**: Use ngrok or Razorpay's webhook test tool

### Test Locally

```bash
# Start local server
php -S localhost:8000

# In another terminal, start ngrok for webhook testing
ngrok http 8000

# Update webhook URL in Razorpay dashboard with ngrok URL
```

### Manual Testing Checklist

- [ ] Install dependencies with composer
- [ ] Run database migration
- [ ] Set environment variables
- [ ] Configure webhook URL
- [ ] Test profile save
- [ ] Test "Save & Pay" button
- [ ] Test successful payment
- [ ] Test failed payment
- [ ] Test payment verification
- [ ] Test webhook delivery
- [ ] View payment receipt
- [ ] Check webhook logs

## Security Considerations

✅ **Implemented Security Measures:**

1. **No Hardcoded Secrets**: All API keys read from environment variables
2. **CSRF Protection**: All POST endpoints verify CSRF tokens
3. **Authentication**: All endpoints verify user session
4. **Authorization**: Users can only access their own payment records
5. **Signature Verification**: Payment and webhook signatures validated
6. **SQL Injection Protection**: All queries use prepared statements
7. **Minimal Logging**: Webhook logs contain no PII or sensitive data
8. **HTTPS Required**: Payment endpoints should use HTTPS in production
9. **Input Validation**: All inputs validated and sanitized
10. **No Card Storage**: No card details or OTPs stored in database

⚠️ **Production Checklist:**

- [ ] Use HTTPS for all pages (especially payment endpoints)
- [ ] Use production Razorpay keys (starts with `rzp_live_`)
- [ ] Set up webhook with proper authentication
- [ ] Configure firewall to allow only Razorpay IP addresses for webhooks
- [ ] Enable Razorpay's 2FA and security features
- [ ] Monitor payment logs regularly
- [ ] Set up payment failure alerts
- [ ] Backup database regularly
- [ ] Keep Razorpay SDK updated

## Troubleshooting

### Common Issues

**1. "Razorpay credentials not configured" error**
- Ensure environment variables are set correctly
- Restart web server after setting environment variables
- Check if variables are accessible in PHP: `echo getenv('RZP_KEY_ID');`

**2. Payment initiation fails**
- Check if composer dependencies are installed
- Verify API keys are correct (test vs live mode)
- Check error logs: `storage/logs/payment_webhook.log`
- Ensure player profile is saved before initiating payment

**3. Webhook not receiving events**
- Verify webhook URL is publicly accessible
- Check webhook secret matches in Razorpay dashboard
- Ensure events are enabled in webhook configuration
- Check webhook logs in Razorpay dashboard

**4. Payment verification fails**
- Ensure signature verification is working
- Check if payment_id, order_id are correct
- Verify CSRF token is valid
- Check database for payment record

**5. Composer install fails**
- Ensure Composer is installed: `composer --version`
- Run `composer install` instead of `composer require`
- Clear Composer cache: `composer clear-cache`

## Support

### Razorpay Documentation
- [API Documentation](https://razorpay.com/docs/api/)
- [Payment Gateway](https://razorpay.com/docs/payments/)
- [Webhooks](https://razorpay.com/docs/webhooks/)
- [PHP SDK](https://razorpay.com/docs/payments/server-integration/php/)

### Test Mode Resources
- [Test Cards](https://razorpay.com/docs/payments/payments/test-card-details/)
- [Webhook Testing](https://razorpay.com/docs/webhooks/test/)

### Contact
For issues related to this integration, please contact the development team.

## License

This payment integration is part of the LBL Registration System.

---

**Last Updated**: November 9, 2025
