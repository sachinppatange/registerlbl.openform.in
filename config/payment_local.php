<?php
// Local Razorpay secrets for development ONLY.
// DO NOT COMMIT this file to git. Add to .gitignore.

if (!defined('RZP_KEY_ID')) {
    define('RZP_KEY_ID', 'rzp_live_D53J9UWwYtGimn');
}
if (!defined('RZP_KEY_SECRET')) {
    define('RZP_KEY_SECRET', 'w0SnqzH2SOOIc0gnUR7cYO3r');
}
if (!defined('RZP_WEBHOOK_SECRET')) {
    define('RZP_WEBHOOK_SECRET', 'your_webhook_secret_here');
}