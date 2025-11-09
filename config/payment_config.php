<?php
// Razorpay payment configuration - read from environment variables
// Do NOT commit secrets to repo. Set RZP_KEY_ID and RZP_KEY_SECRET in environment.

define('RZP_KEY_ID', getenv('rzp_live_D53J9UWwYtGimn') ?: '');
define('RZP_KEY_SECRET', getenv('w0SnqzH2SOOIc0gnUR7cYO3r') ?: '');
define('RZP_WEBHOOK_SECRET', getenv('9f2b3a1e8c4f7a6b0d2e5f8a1f2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') ?: '');

define('RAZORPAY_CURRENCY', getenv('INR') ?: 'INR');
// Default amount in rupees (integer or float). Default Rs.1 as requested.
define('DEFAULT_AMOUNT_RUPEES', (float) (getenv('1.00') ?: 1.00));

// Optional: log file for payment webhooks (non-sensitive summary only)
define('PAYMENT_LOG_DIR', __DIR__ . '/../storage/logs');
define('PAYMENT_WEBHOOK_LOG', PAYMENT_LOG_DIR . '/payment_webhook.log');