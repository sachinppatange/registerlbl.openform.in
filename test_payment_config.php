<?php
/**
 * Test Payment Configuration
 * Run this script to verify your payment setup
 * Usage: php test_payment_config.php
 */

echo "=== Payment Configuration Test ===\n\n";

// Check if composer dependencies are installed
$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    echo "❌ FAIL: Composer dependencies not installed\n";
    echo "   Run: composer require razorpay/razorpay\n\n";
    $hasVendor = false;
} else {
    echo "✓ PASS: Composer dependencies installed\n";
    $hasVendor = true;
}

// Check environment variables
require_once __DIR__ . '/config/payment_config.php';

echo "\n--- Environment Variables ---\n";

if (!empty(RZP_KEY_ID)) {
    $keyType = strpos(RZP_KEY_ID, 'rzp_test_') === 0 ? 'TEST' : 
               (strpos(RZP_KEY_ID, 'rzp_live_') === 0 ? 'LIVE' : 'UNKNOWN');
    echo "✓ RZP_KEY_ID: Set ({$keyType} mode, " . strlen(RZP_KEY_ID) . " chars)\n";
} else {
    echo "❌ RZP_KEY_ID: Not set\n";
}

if (!empty(RZP_KEY_SECRET)) {
    echo "✓ RZP_KEY_SECRET: Set (" . strlen(RZP_KEY_SECRET) . " chars)\n";
} else {
    echo "❌ RZP_KEY_SECRET: Not set\n";
}

if (!empty(RZP_WEBHOOK_SECRET)) {
    echo "✓ RZP_WEBHOOK_SECRET: Set (" . strlen(RZP_WEBHOOK_SECRET) . " chars)\n";
} else {
    echo "⚠ RZP_WEBHOOK_SECRET: Not set (optional for testing)\n";
}

// Check database connection
echo "\n--- Database Connection ---\n";
try {
    require_once __DIR__ . '/config/wa_config.php';
    $pdo = db();
    echo "✓ Database connection: OK\n";
    
    // Check if payments table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'payments'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Payments table: Exists\n";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE payments");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'player_id', 'order_id', 'payment_id', 'amount', 'currency', 'status', 'metadata', 'created_at', 'updated_at'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (empty($missingColumns)) {
            echo "✓ Table structure: Valid (" . count($columns) . " columns)\n";
        } else {
            echo "⚠ Table structure: Missing columns - " . implode(', ', $missingColumns) . "\n";
        }
    } else {
        echo "❌ Payments table: Not found\n";
        echo "   Run migration: mysql -u user -p database < migrations/2025_11_09_create_payments_table.sql\n";
    }
} catch (Exception $e) {
    echo "❌ Database connection: Failed\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

// Check required directories
echo "\n--- Directory Structure ---\n";

$dirs = [
    'userpanel/payment' => 'Payment endpoints directory',
    'userpanel/js' => 'JavaScript directory',
    'storage/logs' => 'Logs directory',
    'migrations' => 'Migrations directory',
    'libs' => 'Libraries directory'
];

foreach ($dirs as $dir => $desc) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        echo "✓ {$desc}: OK\n";
    } else {
        echo "❌ {$desc}: Not found\n";
    }
}

// Check required files
echo "\n--- Required Files ---\n";

$files = [
    'userpanel/payment/initiate_payment.php' => 'Initiate payment endpoint',
    'userpanel/payment/verify_payment.php' => 'Verify payment endpoint',
    'userpanel/payment/webhook.php' => 'Webhook handler',
    'userpanel/payment/return.php' => 'Return page',
    'userpanel/payment/receipt.php' => 'Receipt page',
    'userpanel/js/payment.js' => 'Payment JavaScript',
    'libs/RazorpayClient.php' => 'Razorpay client wrapper',
    'config/payment_config.php' => 'Payment configuration'
];

foreach ($files as $file => $desc) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ {$desc}: OK\n";
    } else {
        echo "❌ {$desc}: Not found\n";
    }
}

// Test Razorpay client initialization
if ($hasVendor && validate_payment_config()) {
    echo "\n--- Razorpay Client Test ---\n";
    try {
        require_once __DIR__ . '/libs/RazorpayClient.php';
        $client = new RazorpayClient();
        echo "✓ RazorpayClient: Initialized successfully\n";
    } catch (Exception $e) {
        echo "❌ RazorpayClient: Failed to initialize\n";
        echo "   Error: " . $e->getMessage() . "\n";
    }
}

// Summary
echo "\n=== Summary ===\n";
if ($hasVendor && validate_payment_config()) {
    echo "✓ Payment system is ready for testing!\n";
    echo "\nNext steps:\n";
    echo "1. Start your web server\n";
    echo "2. Navigate to userpanel/player_profile.php\n";
    echo "3. Fill in profile details\n";
    echo "4. Click 'Save & Pay' button\n";
    echo "5. Use Razorpay test card: 4111 1111 1111 1111\n";
} else {
    echo "⚠ Payment system setup incomplete. Please fix the issues above.\n";
}

echo "\n";
