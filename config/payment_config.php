<?php
// Payment configuration
// Environment variables for Razorpay credentials

// Razorpay API credentials (read from environment)
define('RZP_KEY_ID', getenv('RZP_KEY_ID') ?: '');
define('RZP_KEY_SECRET', getenv('RZP_KEY_SECRET') ?: '');
define('RZP_WEBHOOK_SECRET', getenv('RZP_WEBHOOK_SECRET') ?: '');

// Payment defaults
define('RAZORPAY_CURRENCY', 'INR');
define('DEFAULT_AMOUNT_RUPEES', 1);

// Validate that keys are set (in production)
function validate_payment_config(): bool {
    return !empty(RZP_KEY_ID) && !empty(RZP_KEY_SECRET);
}

// Convert rupees to paise (Razorpay requires amount in smallest currency unit)
function rupees_to_paise(float $rupees): int {
    return (int)($rupees * 100);
}

// Convert paise to rupees
function paise_to_rupees(int $paise): float {
    return $paise / 100;
}
