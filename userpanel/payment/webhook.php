<?php
/**
 * Razorpay Webhook Endpoint
 * 
 * Handles asynchronous payment notifications from Razorpay.
 * Verifies webhook signature and updates payment status accordingly.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/../../config/wa_config.php';
require_once __DIR__ . '/../player_repository.php';

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get webhook secret
$webhook_secret = RZP_WEBHOOK_SECRET;
if (empty($webhook_secret)) {
    error_log('Webhook secret not configured');
    http_response_code(500);
    echo json_encode(['error' => 'Webhook not configured']);
    exit;
}

// Get webhook signature from headers
$webhook_signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
if (empty($webhook_signature)) {
    error_log('Missing webhook signature');
    http_response_code(400);
    echo json_encode(['error' => 'Missing signature']);
    exit;
}

// Get request body
$webhook_body = file_get_contents('php://input');
if (empty($webhook_body)) {
    error_log('Empty webhook body');
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

// Verify webhook signature
$expected_signature = hash_hmac('sha256', $webhook_body, $webhook_secret);
if (!hash_equals($expected_signature, $webhook_signature)) {
    error_log('Invalid webhook signature');
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// Parse webhook payload
$payload = json_decode($webhook_body, true);
if (!$payload || !isset($payload['event'])) {
    error_log('Invalid webhook payload');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

try {
    $event = $payload['event'];
    $payment_entity = $payload['payload']['payment']['entity'] ?? null;
    
    if (!$payment_entity) {
        error_log('Missing payment entity in webhook');
        http_response_code(400);
        echo json_encode(['error' => 'Missing payment entity']);
        exit;
    }
    
    $order_id = $payment_entity['order_id'] ?? '';
    $payment_id = $payment_entity['id'] ?? '';
    $status = $payment_entity['status'] ?? '';
    
    if (empty($order_id)) {
        error_log('Missing order_id in webhook');
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id']);
        exit;
    }
    
    // Get payment record
    $payment_record = get_payment_by_order_id($order_id);
    if (!$payment_record) {
        error_log('Payment record not found for order: ' . $order_id);
        // Return 200 to prevent retries for non-existent orders
        http_response_code(200);
        echo json_encode(['message' => 'Order not found']);
        exit;
    }
    
    // Handle different events
    switch ($event) {
        case 'payment.authorized':
        case 'payment.captured':
            // Payment successful
            if ($status === 'captured' || $status === 'authorized') {
                mark_payment_paid($payment_record['id'], $payment_id);
                error_log('Payment marked as paid via webhook: ' . $payment_id);
            }
            break;
            
        case 'payment.failed':
            // Payment failed
            $error_description = $payment_entity['error_description'] ?? 'Payment failed';
            mark_payment_failed($payment_record['id'], $error_description);
            error_log('Payment marked as failed via webhook: ' . $payment_id);
            break;
            
        default:
            error_log('Unhandled webhook event: ' . $event);
    }
    
    // Return success
    http_response_code(200);
    echo json_encode(['message' => 'Webhook processed']);
    
} catch (Exception $e) {
    error_log('Webhook processing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Processing failed']);
}
