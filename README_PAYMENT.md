# Payment Integration Documentation

This document describes the Razorpay payment integration for the Latur Badminton League registration system.

## Overview

The payment system allows players to complete their registration by paying online through Razorpay. The flow includes:
1. Player saves their profile
2. Player clicks "Save & Pay" to initiate payment
3. Razorpay Checkout modal opens for payment
4. Payment is verified on the backend
5. Player receives a receipt

## Prerequisites

- PHP 7.4 or higher
- MySQL database
- Composer (PHP dependency manager)
- Razorpay account with API credentials

## Installation

### 1. Install Dependencies

Install the Razorpay PHP SDK using Composer:

```bash
composer require razorpay/razorpay
```

This will create a `vendor/` directory with the Razorpay SDK and autoloader.

### 2. Set Environment Variables

The payment system requires three environment variables to be set. These should **NEVER** be hardcoded in the repository.

#### On Unix/Linux/Mac:

Add to your `.bashrc`, `.zshrc`, or set in your web server configuration:

```bash
export RZP_KEY_ID="your_razorpay_key_id"
export RZP_KEY_SECRET="your_razorpay_key_secret"
export RZP_WEBHOOK_SECRET="your_razorpay_webhook_secret"
```

#### On Windows:

```cmd
set RZP_KEY_ID=your_razorpay_key_id
set RZP_KEY_SECRET=your_razorpay_key_secret
set RZP_WEBHOOK_SECRET=your_razorpay_webhook_secret
```

#### For Apache (in `.htaccess` or `httpd.conf`):

```apache
SetEnv RZP_KEY_ID "your_razorpay_key_id"
SetEnv RZP_KEY_SECRET "your_razorpay_key_secret"
SetEnv RZP_WEBHOOK_SECRET "your_razorpay_webhook_secret"
```

#### For Nginx (in `fastcgi_params` or server block):

```nginx
fastcgi_param RZP_KEY_ID "your_razorpay_key_id";
fastcgi_param RZP_KEY_SECRET "your_razorpay_key_secret";
fastcgi_param RZP_WEBHOOK_SECRET "your_razorpay_webhook_secret";
```

### 3. Run Database Migration

Execute the SQL migration to create the `payments` table:

```bash
mysql -u your_username -p your_database < migrations/2025_11_09_create_payments_table.sql
```

Or run the SQL manually in your MySQL client:

```sql
-- Content from migrations/2025_11_09_create_payments_table.sql
```

### 4. Configure Webhook (Optional but Recommended)

Webhooks allow Razorpay to notify your server about payment events asynchronously.

1. Log in to your [Razorpay Dashboard](https://dashboard.razorpay.com/)
2. Navigate to Settings → Webhooks
3. Click "Add New Webhook"
4. Set the webhook URL to: `https://yourdomain.com/userpanel/payment/webhook.php`
5. Select events to listen for:
   - `payment.authorized`
   - `payment.captured`
   - `payment.failed`
6. Copy the webhook secret and set it as the `RZP_WEBHOOK_SECRET` environment variable

## Configuration

### Default Payment Amount

The default payment amount is configured in `config/payment_config.php`:

```php
define('PAYMENT_DEFAULT_AMOUNT', 500); // Default amount in INR (rupees)
```

You can modify this value to change the default registration fee.

### Currency

The system uses INR (Indian Rupees) by default. This is configured in `config/payment_config.php`:

```php
define('RAZORPAY_CURRENCY', 'INR');
```

## File Structure

```
├── composer.json                              # Composer dependencies
├── config/
│   └── payment_config.php                     # Payment configuration
├── libs/
│   └── RazorpayClient.php                     # Razorpay SDK wrapper
├── migrations/
│   └── 2025_11_09_create_payments_table.sql  # Database schema
├── userpanel/
│   ├── js/
│   │   └── payment.js                         # Frontend payment logic
│   ├── payment/
│   │   ├── initiate_payment.php              # Create order endpoint
│   │   ├── verify_payment.php                # Verify signature endpoint
│   │   ├── webhook.php                       # Webhook handler
│   │   ├── return.php                        # Success/failure page
│   │   └── receipt.php                       # Printable receipt
│   ├── player_profile.php                    # Updated with "Save & Pay" button
│   └── player_repository.php                 # Payment helper functions
└── README_PAYMENT.md                          # This file
```

## Security

### CSRF Protection

All payment endpoints verify CSRF tokens using the session-based CSRF mechanism already present in the application:

```php
if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    // Reject request
}
```

### Authentication

All payment endpoints require user authentication:

```php
require_auth();
$phone = current_user();
```

### Signature Verification

Payment signatures are verified using Razorpay's signature verification:

```php
$razorpayClient->verifySignature([
    'razorpay_order_id' => $order_id,
    'razorpay_payment_id' => $payment_id,
    'razorpay_signature' => $signature
]);
```

### Webhook Signature Verification

Webhook requests are verified using HMAC SHA256:

```php
$expected = hash_hmac('sha256', $webhook_body, RZP_WEBHOOK_SECRET);
if (!hash_equals($expected, $webhook_signature)) {
    // Reject webhook
}
```

### Database Security

- All database queries use prepared statements to prevent SQL injection
- Payment records are linked to players and ownership is verified before display
- Sensitive data is not logged

### Environment Variables

- **NEVER** commit API keys or secrets to the repository
- Always use environment variables for credentials
- Rotate keys periodically

## Usage

### For End Users

1. Fill out the player profile form
2. Click "Save & Pay" button
3. Complete payment in Razorpay Checkout modal
4. View/print receipt after successful payment

### For Developers

#### Creating a Payment Order

```php
require_once 'vendor/autoload.php';
require_once 'config/payment_config.php';
use App\RazorpayClient;

$client = new RazorpayClient(RZP_KEY_ID, RZP_KEY_SECRET);
$order = $client->createOrder(
    50000,              // Amount in paise (500.00 INR)
    'INR',              // Currency
    'rcpt_12345',       // Receipt ID
    ['player_id' => 1]  // Notes
);
```

#### Verifying Payment Signature

```php
$isValid = $client->verifySignature([
    'razorpay_order_id' => $order_id,
    'razorpay_payment_id' => $payment_id,
    'razorpay_signature' => $signature
]);
```

## Testing

### Test Mode

Razorpay provides test credentials for development:

1. Use test API keys from Razorpay Dashboard (Test Mode)
2. Use test card numbers from [Razorpay Test Cards](https://razorpay.com/docs/payments/payments/test-card-details/)
3. Test webhooks using [Razorpay Webhook Tools](https://razorpay.com/docs/webhooks/test/)

### Test Card Details

- **Card Number**: 4111 1111 1111 1111
- **Expiry**: Any future date
- **CVV**: Any 3 digits
- **OTP**: Any 6 digits

## Troubleshooting

### Payment Fails to Initiate

- Check that Razorpay credentials are set correctly
- Verify `composer install` has been run
- Check PHP error logs for exceptions
- Ensure player profile is saved before payment

### Signature Verification Fails

- Verify `RZP_KEY_SECRET` matches your Razorpay account
- Check that the signature is being passed correctly from frontend
- Ensure order_id and payment_id are correct

### Webhook Not Working

- Verify webhook URL is publicly accessible
- Check that `RZP_WEBHOOK_SECRET` is set correctly
- Review webhook logs in Razorpay Dashboard
- Ensure webhook endpoint returns 200 status

### Database Errors

- Verify the payments table exists
- Check database credentials in `config/wa_config.php`
- Ensure foreign key constraints are satisfied (players table exists)

## API Endpoints

### POST /userpanel/payment/initiate_payment.php

Creates a Razorpay order and payment record.

**Request:**
```
amount: 500 (optional, in INR)
csrf: <csrf_token>
```

**Response:**
```json
{
  "ok": true,
  "order_id": "order_xxx",
  "key_id": "rzp_xxx",
  "amount": 50000,
  "currency": "INR"
}
```

### POST /userpanel/payment/verify_payment.php

Verifies payment signature after completion.

**Request:**
```
razorpay_order_id: order_xxx
razorpay_payment_id: pay_xxx
razorpay_signature: signature_xxx
csrf: <csrf_token>
```

**Response:**
```json
{
  "ok": true,
  "message": "Payment verified successfully",
  "payment_id": 123
}
```

### POST /userpanel/payment/webhook.php

Receives asynchronous payment notifications from Razorpay.

**Headers:**
```
X-Razorpay-Signature: <signature>
```

**Response:**
```json
{
  "message": "Webhook processed"
}
```

## Support

For issues related to:
- Razorpay integration: Check [Razorpay Documentation](https://razorpay.com/docs/)
- Application bugs: Contact the development team
- Payment issues: Contact Razorpay support

## License

This payment integration is part of the Latur Badminton League registration system.
