<?php
/**
 * Server endpoint to verify Razorpay checkout signature and finalize payment.
 *
 * This endpoint is intended to be called by the client after Razorpay Checkout
 * returns a successful payment object. It verifies the signature server-side,
 * updates the local payments table and (optionally) the player record, then
 * returns JSON to the client indicating success/failure and an optional redirect URL.
 *
 * Requirements:
 * - session/auth: userpanel/auth.php providing require_auth() and current_user()
 * - payment repository: userpanel/payment_repository.php providing:
 *     payment_find_by_order_id(), payment_verify_signature(), payment_mark_paid(), payment_mark_failed()
 * - player repo: userpanel/player_repository.php (optional) providing player_save_or_update()
 * - config/razorpay_config.php returning ['key_secret' => '...']
 *
 * Security:
 * - Only POST allowed.
 * - Server verifies signature using key_secret; does not trust client.
 * - Checks order belongs to current logged-in user (for additional safety).
 */

session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/payment_repository.php';
require_once __DIR__ . '/player_repository.php';
$config = require __DIR__ . '/../config/razorpay_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Ensure user is authenticated (client should send credentials)
require_auth();
$current_mobile = function_exists('current_user') ? current_user() : null;
if (empty($current_mobile)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Read input (support application/json and form POST)
$raw = file_get_contents('php://input');
$input = $_POST;
if (empty($input) && $raw) {
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        $input = $json;
    }
}

// Required fields from Razorpay Checkout
$razorpay_payment_id = trim($input['razorpay_payment_id'] ?? '');
$razorpay_order_id   = trim($input['razorpay_order_id'] ?? '');
$razorpay_signature  = trim($input['razorpay_signature'] ?? '');

if ($razorpay_payment_id === '' || $razorpay_order_id === '' || $razorpay_signature === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required payment parameters.']);
    exit;
}

// Load key_secret
$key_secret = $config['key_secret'] ?? '';
if (empty($key_secret)) {
    error_log('Razorpay callback: missing key_secret in config');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server misconfiguration.']);
    exit;
}

// Find local payment record for this order
$payment = payment_find_by_order_id($razorpay_order_id);
if (!$payment) {
    // Not found: possible mismatch or create_order not recorded
    error_log("Razorpay callback: order not found locally: {$razorpay_order_id}");
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found.']);
    exit;
}

// Optional: ensure order belongs to current logged-in user
$owner_mobile = $payment['user_mobile'] ?? null;
if (!empty($owner_mobile) && $owner_mobile !== $current_mobile) {
    // suspicious: client session doesn't match order owner
    error_log("Razorpay callback: order owner mismatch. order={$razorpay_order_id} owner={$owner_mobile} current={$current_mobile}");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Order does not belong to current user.']);
    exit;
}

// Idempotency: if already paid, return success
if (($payment['status'] ?? '') === 'paid') {
    echo json_encode([
        'success' => true,
        'message' => 'Payment already recorded.',
        'redirect' => '/userpanel/payment_success.php?order=' . urlencode($razorpay_order_id),
    ]);
    exit;
}

// Verify signature server-side
$ok = payment_verify_signature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature, $key_secret);

if ($ok) {
    // mark paid and store payment_id + signature + meta
    $meta = [
        'verified_at' => date('c'),
        'payload' => $input,
    ];
    $updated = payment_mark_paid($razorpay_order_id, $razorpay_payment_id, $razorpay_signature, $meta);
    if (!$updated) {
        error_log("Razorpay callback: failed to update payment row for order {$razorpay_order_id}");
        // continue, but still return success to client? Better return error
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update payment record.']);
        exit;
    }

    // Optionally update player record to mark payment status
    try {
        if (function_exists('player_save_or_update') && !empty($owner_mobile)) {
            // Update can be customized, e.g., set payment_status, payment_id etc.
            @player_save_or_update($owner_mobile, [
                'payment_status' => 'paid',
                'payment_id' => $razorpay_payment_id,
                'payment_order_id' => $razorpay_order_id,
            ]);
        }
    } catch (Throwable $e) {
        // Don't fail the request for this; only log.
        error_log('Razorpay callback: player update failed: ' . $e->getMessage());
    }

    // Return success with redirect to success page
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified and recorded.',
        'redirect' => '/userpanel/payment_success.php?order=' . urlencode($razorpay_order_id),
    ]);
    exit;
} else {
    // Signature mismatch -> record failure and return error
    $meta = [
        'verified_at' => date('c'),
        'payload' => $input,
        'error' => 'signature_mismatch',
    ];
    payment_mark_failed($razorpay_order_id, $meta);

    error_log("Razorpay callback: signature verification failed for order {$razorpay_order_id}");

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Signature verification failed.',
        'redirect' => '/userpanel/payment_failed.php?order=' . urlencode($razorpay_order_id),
    ]);
    exit;
}