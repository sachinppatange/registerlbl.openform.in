<?php
// Razorpay payment configuration - read from environment variables
// Do NOT commit secrets to repo. Set RZP_KEY_ID and RZP_KEY_SECRET in environment.

define('RZP_KEY_ID', getenv('rzp_live_D53J9UWwYtGimn') ?: '');
define('RZP_KEY_SECRET', getenv('w0SnqzH2SOOIc0gnUR7cYO3r') ?: '');

define('RAZORPAY_CURRENCY', getenv('INR') ?: 'INR');
// Default amount in rupees (integer or float). Default Rs.1 as requested.
define('DEFAULT_AMOUNT_RUPEES', (float) (getenv('1.00') ?: 1.00));

// Optional: log file for payment webhooks (non-sensitive summary only)
define('PAYMENT_LOG_DIR', __DIR__ . '/../storage/logs');
define('PAYMENT_WEBHOOK_LOG', PAYMENT_LOG_DIR . '/payment_webhook.log');