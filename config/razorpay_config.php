<?php
// Razorpay configuration.
// Prefer environment variables. If not available, fill the values below (for local dev only).
// Use test keys (rzp_test_...) for development. Do NOT commit live secrets to git.

return [
    // Public key (example: rzp_test_abc123...) - visible in client (public)
    'key_id' => getenv('rzp_live_D53J9UWwYtGimn') ?: (getenv('RAZORPAY_KEY') ?: ''),

    // Secret key (example: abc12345...) - keep secret
    'key_secret' => getenv('w0SnqzH2SOOIc0gnUR7cYO3r') ?: (getenv('RAZORPAY_SECRET') ?: ''),

    // Webhook secret (optional) - store in env
    'webhook_secret' => getenv('9f2b3a1e8c4f7a6b0d2e5f8a1f2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') ?: '',
];