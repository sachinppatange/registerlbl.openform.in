<?php
/**
 * config/razorpay_config.php
 *
 * DEV/QUICK-START CONFIG — သ
 * - This file contains your Razorpay keys for immediate testing/use.
 * - WARNING: These are live credentials. Do NOT commit this file to git or store in a public repository.
 * - Recommended: Use environment variables in production and remove this file after testing.
 *
 * If you prefer env-first behavior, replace the literal values with getenv(...) calls.
 */

return [
    // Public Key (Razorpay Key ID)
    'key_id' => 'rzp_live_D53J9UWwYtGimn',

    // Secret Key (Razorpay Key Secret) — keep this secret
    'key_secret' => 'w0SnqzH2SOOIc0gnUR7cYO3r',

    // Webhook secret (optional). Prefer storing in environment variable RAZORPAY_WEBHOOK_SECRET.
    'webhook_secret' => getenv('9f2b3a1e8c4f7a6b0d2e5f8a1f2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') ?: '',
];