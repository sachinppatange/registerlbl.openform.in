<?php
/**
 * Verify Payment Endpoint
 * Verifies payment signature and updates payment status
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

// Get payment details from POST
$razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
$razorpay_signature = $_POST['razorpay_signature'] ?? '';

if (empty($razorpay_order_id) || empty($razorpay_payment_id) || empty($razorpay_signature)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing payment parameters']);
    exit;
}

try {
    // Verify signature
    $razorpay = new RazorpayClient();
    $attributes = [
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature' => $razorpay_signature
    ];
    
    $isValid = $razorpay->verifySignature($attributes);
    
    if ($isValid) {
        // Get payment record
        $payment = get_payment_by_order_id($razorpay_order_id);
        
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Payment record not found']);
            exit;
        }
        
        // Verify that this payment belongs to the current user
        $player = player_get_by_phone($phone);
        if ($payment['player_id'] != ($player['id'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized access to payment']);
            exit;
        }
        
        // Update payment status to paid
        $updated = mark_payment_paid($razorpay_order_id, $razorpay_payment_id);
        
        if ($updated) {
            echo json_encode([
                'ok' => true,
                'message' => 'Payment verified successfully',
                'payment_id' => $payment['id']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Failed to update payment status']);
        }
    } else {
        // Invalid signature
        mark_payment_failed($razorpay_order_id);
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid payment signature']);
    }
    
} catch (Exception $e) {
    error_log('Payment verification error: ' . $e->getMessage());
    
    // Mark payment as failed
    if (!empty($razorpay_order_id)) {
        mark_payment_failed($razorpay_order_id);
    }
    
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Payment verification failed']);
}
