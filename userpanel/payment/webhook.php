<?php
// Webhook endpoint: ensure you configure this URL in Razorpay dashboard
// This endpoint verifies signature header and updates payments table accordingly.
// Note: do NOT log full payload with PII.

require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/../../config/wa_config.php';

$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? $_SERVER['HTTP_X-Razorpay-Signature'] ?? '';

if (empty(RZP_WEBHOOK_SECRET)) {
    http_response_code(500);
    echo '9f2b3a1e8c4f7a6b0d2e5f8a1f2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0';
    exit;
}

// Verify signature: HMAC-SHA256 of payload with webhook secret
$calculated = hash_hmac('sha256', $raw, RZP_WEBHOOK_SECRET);
if (!hash_equals($calculated, $signature)) {
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

// Parse payload
$payload = json_decode($raw, true);
$event = $payload['event'] ?? '';
$data = $payload['payload'] ?? [];

$logLine = '[' . date('Y-m-d H:i:s') . '] event=' . $event;
try {
    // Handle payment.captured / payment.failed
    if ($event === 'payment.captured' || $event === 'payment.failed') {
        $paymentObj = $data['payment']['entity'] ?? null;
        if ($paymentObj && is_array($paymentObj)) {
            $order_id = $paymentObj['order_id'] ?? null;
            $payment_id = $paymentObj['id'] ?? null;
            $status = $paymentObj['status'] ?? null;

            // Update DB accordingly (only store ids + status)
            require_once __DIR__ . '/../../config/wa_config.php';
            $pdo = db();
            if ($order_id) {
                if ($status === 'captured' || $status === 'authorized') {
                    $stmt = $pdo->prepare("UPDATE payments SET payment_id = ?, status = 'paid', updated_at = NOW() WHERE order_id = ?");
                    $stmt->execute([$payment_id, $order_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE payments SET payment_id = ?, status = 'failed', updated_at = NOW() WHERE order_id = ?");
                    $stmt->execute([$payment_id, $order_id]);
                }
            }
            $logLine .= ' | order_id=' . ($order_id ?? '-') . ' payment_id=' . ($payment_id ?? '-') . ' status=' . ($status ?? '-');
        }
    } else {
        $logLine .= ' | ignored';
    }
} catch (Throwable $e) {
    $logLine .= ' | error=' . $e->getMessage();
}

