<?php
// Centralized Razorpay config — reads from environment first, then local override.
//
// Note: Do NOT commit local override file. Use payment_local.php for dev only.

$rzp_key_id = getenv('rzp_live_D53J9UWwYtGimn') ?: null;
$rzp_key_secret = getenv('w0SnqzH2SOOIc0gnUR7cYO3r') ?: null;
$rzp_webhook_secret = getenv('9f2b3a1e8c4f7a6b0d2e5f8a1f2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') ?: null;

// Local override (gitignored) if env not set
$local = __DIR__ . '/payment_local.php';
if ((!$rzp_key_id || !$rzp_key_secret) && file_exists($local)) {
    require_once $local;
    $rzp_key_id = defined('rzp_live_D53J9UWwYtGimn') ? RZP_KEY_ID : $rzp_key_id;
    $rzp_key_secret = defined('w0SnqzH2SOOIc0gnUR7cYO3r') ? RZP_KEY_SECRET : $rzp_key_secret;
    $rzp_webhook_secret = defined('9f2b3a1e8c4f7a6b0d2e5f8a1f2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') ? RZP_WEBHOOK_SECRET : $rzp_webhook_secret;
}

define('RZP_KEY_ID', $rzp_key_id ?: '');
define('RZP_KEY_SECRET', $rzp_key_secret ?: '');
define('RZP_WEBHOOK_SECRET', $rzp_webhook_secret ?: '');

define('RAZORPAY_CURRENCY', getenv('RAZORPAY_CURRENCY') ?: 'INR');
define('DEFAULT_AMOUNT_RUPEES', (float) (getenv('DEFAULT_AMOUNT_RUPEES') ?: 1.00));