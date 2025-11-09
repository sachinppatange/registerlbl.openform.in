<?php
// Razorpay configuration.
// Prefer environment variables. If not available, fill the values below (for local dev only).
// Use test keys (rzp_test_...) for development. Do NOT commit live secrets to git.

return [
    // Public key (example: rzp_test_abc123...) - visible in client (public)
    'key_id' => getenv('rzp_live_D53J9UWwYtGimn') ?: (getenv('RAZORPAY_KEY') ?: ''),

    // Secret key (example: abc12345...) - keep secret
    'key_secret' => getenv('w0SnqzH2SOOIc0gnUR7cYO3r') ?: (getenv('RAZORPAY_SECRET') ?: ''),

];