# Razorpay Integration (Save & Pay)

This document explains how to set up the Razorpay "Save & Pay" flow for the LBL registration app.

## Overview
- Default payment amount: Rs.1 (configurable via environment variable DEFAULT_AMOUNT_RUPEES)
- Payments are created as Razorpay Orders on the server, and verified server-side after client checkout.
- A webhook endpoint is provided to handle async events.

## Composer
Install Razorpay PHP SDK:
```bash
composer require razorpay/razorpay
```

## Environment variables
Set the following in your environment (not in repo):
- RZP_KEY_ID=<your_razorpay_key_id>
- RZP_KEY_SECRET=<your_razorpay_key_secret>
- RZP_WEBHOOK_SECRET=<your_webhook_secret> (set in Razorpay dashboard for webhook signing)
(Optional)
- DEFAULT_AMOUNT_RUPEES (default 1)

## Migration
Run the SQL in `migrations/2025_11_09_create_payments_table.sql` on your database to create the `payments` table.

## Webhook
- Configure the webhook URL: `https://<your-domain>/userpanel/payment/webhook.php`
- Use the same webhook secret value in `RZP_WEBHOOK_SECRET`.

## Security notes
- Do NOT commit keys into the repo.
- Webhook logs are minimal and stored at `storage/logs/payment_webhook.log`.
- Only store gateway ids and statuses. Do not store card data or PII in webhook logs.

## Testing (Razorpay test keys)
- Use test keys provided by Razorpay.
- Complete a test flow using the Save & Pay button on the player profile page. After payment success, the server verifies signature and updates the payment status.
