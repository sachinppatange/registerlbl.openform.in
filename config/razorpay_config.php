<?php
/**
 * config/razorpay_config.php
 *
 * DEV / temporary config. This file will be read by userpanel pages via:
 * require __DIR__ . '/../config/razorpay_config.php'
 *
 * WARNING: Do NOT commit this file to a public repository. If these are live keys,
 * rotate/revoke them after testing.
 */

return [
    // Public Key (Razorpay Key ID)
    'key_id' => 'rzp_live_D53J9UWwYtGimn',

    // Secret Key (Razorpay Key Secret)
    'key_secret' => 'w0SnqzH2SOOIc0gnUR7cYO3r',

    // Webhook secret (optional). Prefer storing in environment variable RAZORPAY_WEBHOOK_SECRET.
    'webhook_secret' => getenv('RAZORPAY_WEBHOOK_SECRET') ?: '',
];





