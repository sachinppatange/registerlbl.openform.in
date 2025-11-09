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
    'webhook_secret' => getenv('9f2b3a1e8c4f7a6b0d2e5f8a1f2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') ?: '',
];





