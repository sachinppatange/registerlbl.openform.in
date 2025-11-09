<?php
/**
 * Razorpay Webhook Handler
 * Handles payment.captured and payment.failed events
 * Logs events without storing PII or raw payloads
 */

require_once __DIR__ . '/../player_repository.php';
require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/../../libs/RazorpayClient.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Get raw POST body
$webhookBody = file_get_contents('php://input');
$webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($webhookSignature)) {
    http_response_code(400);
    exit('Missing signature');
}

// Verify webhook signature
try {
    $razorpay = new RazorpayClient();
    $isValid = $razorpay->verifyWebhookSignature($webhookBody, $webhookSignature, RZP_WEBHOOK_SECRET);
    
    if (!$isValid) {
        http_response_code(400);
        log_webhook_event('signature_invalid', '', '', '', 'Signature verification failed');
        exit('Invalid signature');
    }
} catch (Exception $e) {
    http_response_code(500);
    log_webhook_event('error', '', '', '', 'Webhook error: ' . $e->getMessage());
    exit('Webhook error');
}

// Parse webhook payload
$payload = json_decode($webhookBody, true);

if (!$payload || !isset($payload['event'])) {
    http_response_code(400);
    log_webhook_event('invalid_payload', '', '', '', 'Invalid payload');
    exit('Invalid payload');
}

$event = $payload['event'];
$paymentEntity = $payload['payload']['payment']['entity'] ?? null;

if (!$paymentEntity) {
    http_response_code(400);
    log_webhook_event($event, '', '', '', 'Missing payment entity');
    exit('Missing payment entity');
}

$payment_id = $paymentEntity['id'] ?? '';
$order_id = $paymentEntity['order_id'] ?? '';
$status = $paymentEntity['status'] ?? '';

// Handle different events
switch ($event) {
    case 'payment.captured':
        // Payment successful
        if (!empty($order_id) && !empty($payment_id)) {
            $updated = mark_payment_paid($order_id, $payment_id);
            if ($updated) {
                log_webhook_event($event, $order_id, $payment_id, 'paid', 'Payment captured successfully');
                http_response_code(200);
                echo json_encode(['status' => 'success']);
            } else {
                log_webhook_event($event, $order_id, $payment_id, 'error', 'Failed to update payment status');
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to update payment']);
            }
        } else {
            log_webhook_event($event, $order_id, $payment_id, 'error', 'Missing order_id or payment_id');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        }
        break;
        
    case 'payment.failed':
        // Payment failed
        if (!empty($order_id)) {
            $updated = mark_payment_failed($order_id);
            if ($updated) {
                log_webhook_event($event, $order_id, $payment_id, 'failed', 'Payment failed');
                http_response_code(200);
                echo json_encode(['status' => 'success']);
            } else {
                log_webhook_event($event, $order_id, $payment_id, 'error', 'Failed to update payment status');
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to update payment']);
            }
        } else {
            log_webhook_event($event, $order_id, $payment_id, 'error', 'Missing order_id');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing order_id']);
        }
        break;
        
    default:
        // Unhandled event type
        log_webhook_event($event, $order_id, $payment_id, 'ignored', 'Unhandled event type');
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        break;
}

/**
 * Log webhook events (minimal info, no PII or raw payloads)
 * 
 * @param string $event Event type
 * @param string $order_id Order ID
 * @param string $payment_id Payment ID
 * @param string $status Status
 * @param string $message Log message
 */
function log_webhook_event(string $event, string $order_id, string $payment_id, string $status, string $message): void {
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/payment_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf(
        "[%s] Event: %s | Order: %s | Payment: %s | Status: %s | Message: %s\n",
        $timestamp,
        $event,
        $order_id ?: 'N/A',
        $payment_id ?: 'N/A',
        $status,
        $message
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
