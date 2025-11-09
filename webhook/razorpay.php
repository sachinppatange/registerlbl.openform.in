<?php
/**
 * Public webhook endpoint for Razorpay events.
 *
 * Place this file at: /webhook/razorpay.php (publicly reachable)
 * Configure the webhook URL in Razorpay dashboard and set the webhook secret to match config/razorpay_config.php:webhook_secret
 *
 * Behavior:
 * - Verifies X-Razorpay-Signature using webhook_secret.
 * - Parses event and updates local payments table via payment_repository functions.
 * - Logs incoming events to a log file for debugging/audit.
 *
 * Requirements:
 * - config/razorpay_config.php
 * - userpanel/payment_repository.php (payment_find_by_order_id, payment_mark_paid, payment_mark_failed, payment_update_by_order_id)
 *
 * Notes:
 * - This endpoint is public; security relies on signature verification.
 * - Always respond with HTTP 200 quickly after processing to acknowledge receipt to Razorpay.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/razorpay_config.php';
require_once __DIR__ . '/../userpanel/payment_repository.php';

// set response header
header('Content-Type: application/json; charset=utf-8');

// Read raw body
$raw = file_get_contents('php://input');
if ($raw === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty payload']);
    exit;
}

// Get signature header
$signatureHeader = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
// Some servers might convert header names; also check apache_request_headers fallback
if (empty($signatureHeader) && function_exists('apache_request_headers')) {
    $hdrs = apache_request_headers();
    $signatureHeader = $hdrs['X-Razorpay-Signature'] ?? $hdrs['x-razorpay-signature'] ?? $signatureHeader;
}

// Load webhook secret from config
$config = require __DIR__ . '/../config/razorpay_config.php';
$webhook_secret = $config['webhook_secret'] ?? '';

// Log directory & file (ensure exists)
$logDir = __DIR__ . '/../adminpanel/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/razorpay_webhook.log';

// Helper: append to log
function webhook_log(string $line) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$ts] " . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Verify signature
if (empty($webhook_secret)) {
    webhook_log('ERROR: webhook_secret not configured');
    // Still return 200 to avoid retries, but inform in body
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Webhook secret not configured on server']);
    exit;
}

$computed = hash_hmac('sha256', $raw, $webhook_secret);
if (!hash_equals($computed, (string)$signatureHeader)) {
    webhook_log('Signature verification failed. Received: ' . substr((string)$signatureHeader,0,64) . ' computed: ' . substr($computed,0,64));
    // Respond 400 so Razorpay will retry (or return 200 to acknowledge). Prefer 400 to highlight verification failure.
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Signature verification failed']);
    exit;
}

// Parse JSON payload
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    webhook_log('Invalid JSON payload: ' . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$event = $data['event'] ?? '';
webhook_log('Received event: ' . $event . ' payload: ' . substr($raw, 0, 2000));

// Helper to safe-get nested entity
function _get_entity(array $payload, string $path) {
    $parts = explode('.', $path);
    $cur = $payload;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return null;
        $cur = $cur[$p];
    }
    return $cur;
}

// Process known events
try {
    // Common case: payment entity exists under payload.payment.entity
    $paymentEntity = _get_entity($data, 'payload.payment.entity');
    // Some events like refund will have payload.refund.entity etc.
    $orderId = null;
    if (is_array($paymentEntity) && !empty($paymentEntity['order_id'])) {
        $orderId = $paymentEntity['order_id'];
    } elseif (!empty($data['payload']['order']['entity']['id'])) {
        // fallback: order event
        $orderId = $data['payload']['order']['entity']['id'];
    }

    // Switch based on event type
    switch ($event) {
        case 'payment.captured':
            if (!empty($paymentEntity) && !empty($paymentEntity['order_id'])) {
                $r_payment_id = $paymentEntity['id'] ?? null;
                $r_order_id = $paymentEntity['order_id'];
                webhook_log("payment.captured for order {$r_order_id} payment {$r_payment_id}");
                // mark paid
                $meta = ['webhook_event' => $data];
                $ok = payment_mark_paid($r_order_id, (string)$r_payment_id, (string)($paymentEntity['signature'] ?? ''), $meta);
                if (!$ok) {
                    // fallback: update generic fields
                    payment_update_by_order_id($r_order_id, ['status' => 'paid', 'meta' => $meta]);
                }
            }
            break;

        case 'payment.failed':
            if (!empty($paymentEntity) && !empty($paymentEntity['order_id'])) {
                $r_order_id = $paymentEntity['order_id'];
                webhook_log("payment.failed for order {$r_order_id}");
                $meta = ['webhook_event' => $data];
                payment_mark_failed($r_order_id, $meta);
            }
            break;

        case 'order.paid':
            // order.paid indicates payment against order; try to extract payment id(s)
            $orderEntity = _get_entity($data, 'payload.order.entity');
            if (!empty($orderEntity['id'])) {
                $r_order_id = $orderEntity['id'];
                webhook_log("order.paid for order {$r_order_id}");
                // Optionally set status=paid if not already
                payment_update_by_order_id($r_order_id, ['status' => 'paid', 'meta' => $data]);
            }
            break;

        case 'payment.authorized':
            // Authorized but not captured. Leave status as pending/authorized.
            if (!empty($paymentEntity['order_id'])) {
                $r_order_id = $paymentEntity['order_id'];
                webhook_log("payment.authorized for order {$r_order_id}");
                payment_update_by_order_id($r_order_id, ['status' => 'authorized', 'meta' => $data]);
            }
            break;

        case 'refund.processed':
        case 'refund.created':
        case 'refund.failed':
        case 'refund.updated':
            // Refund-related events -> mark payment as refunded if applicable
            $refundEntity = _get_entity($data, 'payload.refund.entity');
            if (!empty($refundEntity['payment_id'])) {
                $r_payment_id = $refundEntity['payment_id'];
                // find payment by payment_id
                $payRow = payment_find_by_payment_id($r_payment_id);
                if ($payRow) {
                    $r_order_id = $payRow['order_id'] ?? null;
                    $status = (strpos($event, 'failed') !== false) ? 'refund_failed' : 'refunded';
                    webhook_log("refund event {$event} for payment {$r_payment_id} -> order {$r_order_id} status {$status}");
                    payment_update_by_order_id($r_order_id, ['status' => $status, 'meta' => $data]);
                }
            }
            break;

        default:
            // Unknown event; log and store raw payload if we can match an order_id
            webhook_log("Unhandled event type: {$event}");
            if ($orderId) {
                payment_update_by_order_id($orderId, ['meta' => $data]);
            }
            break;
    }

    // Always respond 200 OK to acknowledge receipt
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;

} catch (Throwable $e) {
    webhook_log('Exception while processing webhook: ' . $e->getMessage());
    // Return 500 for visibility; Razorpay will retry on non-2xx responses.
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal error']);
    exit;
}