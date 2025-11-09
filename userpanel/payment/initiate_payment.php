<?php
/**
 * Initiate Payment Endpoint
 * Creates a Razorpay order and returns order details for checkout
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../player_repository.php';
require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/../../libs/RazorpayClient.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify authentication
try {
    require_auth();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$phone = current_user();

// Verify CSRF token
$csrf = $_POST['csrf'] ?? '';
if (empty($csrf) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Get amount (default to Rs.1)
$amountRupees = isset($_POST['amount']) ? (float)$_POST['amount'] : DEFAULT_AMOUNT_RUPEES;
if ($amountRupees <= 0) {
    $amountRupees = DEFAULT_AMOUNT_RUPEES;
}

$amountPaise = rupees_to_paise($amountRupees);

try {
    // Get player profile
    $player = player_get_by_phone($phone);
    $player_id = $player['id'] ?? null;
    
    if (!$player_id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Player profile not found. Please save your profile first.']);
        exit;
    }
    
    // Generate receipt ID
    $receipt = 'rcpt_' . $player_id . '_' . time();
    
    // Create Razorpay order
    $razorpay = new RazorpayClient();
    $order = $razorpay->createOrder($amountPaise, RAZORPAY_CURRENCY, $receipt);
    
    // Save payment record in database
    $metadata = [
        'phone' => $phone,
        'player_name' => $player['full_name'] ?? '',
        'receipt' => $receipt
    ];
    
    $payment_id = create_payment_record($player_id, $order['id'], $amountPaise, RAZORPAY_CURRENCY, $metadata);
    
    if (!$payment_id) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to create payment record']);
        exit;
    }
    
    // Return order details for frontend
    echo json_encode([
        'ok' => true,
        'order_id' => $order['id'],
        'key_id' => RZP_KEY_ID,
        'amount' => $amountPaise,
        'currency' => RAZORPAY_CURRENCY,
        'name' => $player['full_name'] ?? 'Player',
        'description' => 'LBL Registration Payment',
        'prefill' => [
            'name' => $player['full_name'] ?? '',
            'contact' => $phone
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Payment initiation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to initiate payment: ' . $e->getMessage()]);
}
