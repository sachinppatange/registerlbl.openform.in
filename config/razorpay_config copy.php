<?php
/**
 * Razorpay configuration.
 *
 * Recommended: set real keys in environment variables (RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET, RAZORPAY_WEBHOOK_SECRET)
 * or replace the placeholders below before deploying to production.
 *
 * This file returns an array with config values. Example usage:
 *   $cfg = require __DIR__ . '/razorpay_config.php';
 *   $keyId = $cfg['key_id'];
 *
 * Note: Keep key_secret and webhook_secret safe (do not commit real secrets to git).
 */

return [
    // Razorpay API Key ID (public)
    // Example: 'rzp_test_abc123...'
    'key_id' => getenv('rzp_live_D53J9UWwYtGimn') ?: 'rzp_test_your_key_id_here',

    // Razorpay API Key Secret (private) - required for server-side verification/calls
    // Example: 'your_key_secret_here'
    'key_secret' => getenv('w0SnqzH2SOOIc0gnUR7cYO3r') ?: 'rzp_test_your_key_secret_here',

    // Webhook secret used to verify webhook payload signatures (set in Razorpay dashboard)
    'webhook_secret' => getenv('9f2b3a1e8c4f7a6b0d2e5f8a1f2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') ?: 'your_webhook_secret_here',

    // Mode: 'test' or 'live' (you can use this value to switch endpoints or behavior)
    'mode' => getenv('live') ?: 'test',

    // Default currency for orders/payments
    'currency' => getenv('INR') ?: 'INR',

    // Default order expiry (seconds) to pass when creating Razorpay orders (optional)
    // e.g., 3600 = 1 hour
    'order_expiry' => intval(getenv('3600') ?: 3600),

    // Optional: additional notes to attach to Razorpay orders by default
    'order_notes' => [],

    // Optional: webhook endpoint path (relative) for documentation / ease of use
    'webhook_path' => '/webhook/razorpay.php',
];