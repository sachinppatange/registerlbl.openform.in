<?php
/**
 * Verify Payment Endpoint
 * 
 * Verifies Razorpay payment signature after client-side payment completion.
 * Updates payment record status to 'paid' if signature is valid.
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
    // Get payment details from request
    $razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
    $razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
    $razorpay_signature = $_POST['razorpay_signature'] ?? '';
    
    if (empty($razorpay_order_id) || empty($razorpay_payment_id) || empty($razorpay_signature)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing payment details']);
        exit;
    }
    
    // Get player details
    $player = player_get_by_phone($phone);
    if (!$player) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Player profile not found']);
        exit;
    }
    
    $player_id = $player['id'];
    
    // Verify order belongs to this player
    $payment_record = get_payment_by_order_id($razorpay_order_id);
    if (!$payment_record) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Payment record not found']);
        exit;
    }
    
    if ($payment_record['player_id'] != $player_id) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // Verify payment signature
    $razorpayClient = new RazorpayClient(RZP_KEY_ID, RZP_KEY_SECRET);
    $isValid = $razorpayClient->verifySignature([
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature' => $razorpay_signature
    ]);
    
    if (!$isValid) {
        // Mark payment as failed
        mark_payment_failed($payment_record['id'], 'Invalid signature');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Payment verification failed']);
        exit;
    }
    
    // Update payment record to paid status
    $updated = mark_payment_paid($payment_record['id'], $razorpay_payment_id);
    if (!$updated) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to update payment status']);
        exit;
    }
    
    // Success
    echo json_encode([
        'ok' => true,
        'message' => 'Payment verified successfully',
        'payment_id' => $payment_record['id']
    ]);
    
} catch (Exception $e) {
    error_log('Payment verification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Payment verification failed']);
}
