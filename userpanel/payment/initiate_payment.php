<?php
/**
 * Initiate Payment Endpoint
 * 
 * Creates a Razorpay order and stores a pending payment record.
 * Returns order details for client-side Razorpay Checkout.
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/../../config/wa_config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../player_repository.php';

use App\RazorpayClient;

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify authentication
try {
    require_auth();
    $phone = current_user();
    if (!$phone) {
        throw new Exception('Not authenticated');
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

// Verify CSRF token
$csrf_token = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Verify Razorpay credentials are configured
if (!razorpay_credentials_configured()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Payment gateway not configured']);
    exit;
}

try {
    // Get player details
    $player = player_get_by_phone($phone);
    if (!$player) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Player profile not found. Please save your profile first.']);
        exit;
    }
    
    $player_id = $player['id'];
    
    // Get amount (from POST or use default)
    $amount_inr = isset($_POST['amount']) ? floatval($_POST['amount']) : PAYMENT_DEFAULT_AMOUNT;
    if ($amount_inr <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid amount']);
        exit;
    }
    
    // Convert amount from INR to paise (smallest unit)
    $amount_paise = (int)($amount_inr * 100);
    
    // Generate receipt ID
    $receipt_id = 'rcpt_' . $player_id . '_' . time();
    
    // Create payment record in DB with pending status
    $payment_id = create_payment_record(
        $player_id,
        '', // order_id will be set after Razorpay order creation
        $amount_paise,
        RAZORPAY_CURRENCY,
        ['receipt' => $receipt_id, 'phone' => $phone]
    );
    
    if (!$payment_id) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to create payment record']);
        exit;
    }
    
    // Create Razorpay order
    $razorpayClient = new RazorpayClient(RZP_KEY_ID, RZP_KEY_SECRET);
    $order = $razorpayClient->createOrder(
        $amount_paise,
        RAZORPAY_CURRENCY,
        $receipt_id,
        [
            'player_id' => $player_id,
            'phone' => $phone,
            'payment_record_id' => $payment_id
        ]
    );
    
    // Update payment record with order_id
    if (!update_payment_order_id($payment_id, $order['id'])) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to update payment record']);
        exit;
    }
    
    // Return order details for client
    echo json_encode([
        'ok' => true,
        'order_id' => $order['id'],
        'key_id' => $razorpayClient->getKeyId(),
        'amount' => $amount_paise,
        'currency' => RAZORPAY_CURRENCY,
        'name' => 'Latur Badminton League',
        'description' => 'Player Registration Fee',
        'prefill' => [
            'contact' => $phone,
            'name' => $player['full_name'] ?? ''
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Payment initiation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Payment initiation failed']);
}
