<?php
/**
 * Payment Configuration
 * 
 * Razorpay integration settings.
 * All sensitive credentials must be set via environment variables.
 */

// Razorpay API credentials (from environment variables)
define('RZP_KEY_ID', getenv('RZP_KEY_ID') ?: '');
define('RZP_KEY_SECRET', getenv('RZP_KEY_SECRET') ?: '');
define('RZP_WEBHOOK_SECRET', getenv('RZP_WEBHOOK_SECRET') ?: '');

// Payment settings
define('RAZORPAY_CURRENCY', 'INR');
define('PAYMENT_DEFAULT_AMOUNT', 500); // Default amount in INR (rupees)

// Payment statuses
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_PAID', 'paid');
define('PAYMENT_STATUS_FAILED', 'failed');

/**
 * Validate that required Razorpay credentials are configured.
 * 
 * @return bool True if credentials are set, false otherwise
 */
function razorpay_credentials_configured(): bool {
    return !empty(RZP_KEY_ID) && !empty(RZP_KEY_SECRET);
}
