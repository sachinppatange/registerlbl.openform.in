<?php
/**
 * Create Razorpay Order (server endpoint)
 *
 * Expected: POST request with:
 *   - amount          (required) : amount in rupees (e.g. "199.50") OR amount_paise as integer (e.g. 19950)
 *   - receipt_note    (optional) : text to include in order notes/receipt
 *
 * Response: JSON
 *   - success: true/false
 *   - data: razorpay order response (on success)
 *   - error: message (on failure)
 *
 * Requirements:
 *   - A working db() PDO factory function available (from config/app_config.php)
 *   - payment_create() function available (userpanel/payment_repository.php)
 *   - config/razorpay_config.php present and configured
 *   - User auth middleware: require_auth() and current_user() (from userpanel/auth.php)
 *
 * Security:
 *   - This endpoint validates authenticated user via require_auth()
 *   - Amount is converted to smallest unit (paise) and validated as integer > 0
 *   - Keeps a local payment record with status='created' after order creation
 */

require_once __DIR__ . '/auth.php';                // should provide require_auth(), current_user()
require_once __DIR__ . '/payment_repository.php';  // provides payment_create()
$config = require __DIR__ . '/../config/razorpay_config.php';

header('Content-Type: application/json; charset=utf-8');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Require authenticated user
require_auth();
$mobile = function_exists('current_user') ? current_user() : null;
if (empty($mobile)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: user not found in session.']);
    exit;
}

// Read POST payload (supports application/json and form-encoded)
$raw = file_get_contents('php://input');
$input = $_POST;
if (empty($input) && $raw) {
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        $input = $json;
    }
}

// Amount handling:
// Accept either amount_paise (integer) OR amount (float/string rupees)
$amount_paise = null;
if (isset($input['amount_paise'])) {
    $amount_paise = (int)$input['amount_paise'];
} elseif (isset($input['amount'])) {
    // Convert rupees (possibly decimal) to paise
    $amount_float = (float)str_replace(',', '.', $input['amount']);
    $amount_paise = (int) round($amount_float * 100);
}

if ($amount_paise === null || $amount_paise <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing amount. Provide amount (in rupees) or amount_paise.']);
    exit;
}

// Load Razorpay config
$keyId = $config['key_id'] ?? null;
$keySecret = $config['key_secret'] ?? null;
$currency = $config['currency'] ?? 'INR';
$order_expiry = isset($config['order_expiry']) ? (int)$config['order_expiry'] : null;
$order_notes_default = $config['order_notes'] ?? [];

// Compose request payload for Razorpay Orders API
$receipt = sprintf('rcpt_%s_%s', preg_replace('/\D+/', '', $mobile), time());
$notes = $order_notes_default;
if (!empty($input['receipt_note'])) {
    $notes['user_note'] = (string)$input['receipt_note'];
}
$body = [
    'amount' => $amount_paise,
    'currency' => $currency,
    'receipt' => $receipt,
    'payment_capture' => 1, // auto-capture
];
// optional: set expiry (seconds)
if ($order_expiry && $order_expiry > 0) {
    $body['expire_by'] = time() + $order_expiry;
}
if (!empty($notes)) {
    $body['notes'] = $notes;
}

// Basic validation of keys
if (empty($keyId) || empty($keySecret)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Razorpay configuration missing. Contact administrator.']);
    exit;
}

// Call Razorpay Orders API via cURL (no dependency on SDK)
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = null;
if ($response === false) $curlErr = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Failed to call Razorpay Orders API: ' . $curlErr]);
    exit;
}

$respData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Invalid response from Razorpay Orders API', 'raw' => $response]);
    exit;
}

if ($httpcode < 200 || $httpcode >= 300) {
    // Razorpay returned an error
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Razorpay Orders API error', 'details' => $respData]);
    exit;
}

// Expecting order id in response
$order_id = $respData['id'] ?? null;
if (empty($order_id)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Razorpay did not return order id', 'raw' => $respData]);
    exit;
}

// Persist initial payment record in local DB
try {
    // payment_create expects user_mobile, order_id, amount (smallest unit), currency, status, meta
    $createData = [
        'user_mobile' => $mobile,
        'order_id' => $order_id,
        'amount' => $amount_paise,
        'currency' => $currency,
        'status' => 'created',
        'meta' => $respData, // store full razorpay order response
        'notes' => is_string($input['receipt_note'] ?? null) ? $input['receipt_note'] : null,
    ];
    $insertId = payment_create($createData);
    if ($insertId === false) {
        // Not fatal: record failed to insert; log and continue, but warn client
        error_log('Warning: payment_create failed for order ' . $order_id . ' user:' . $mobile);
        // still return order info so client can attempt checkout, but admin view may be missing entry
        echo json_encode(['success' => true, 'warning' => 'Order created but local DB insert failed', 'order' => $respData]);
        exit;
    }
} catch (Throwable $e) {
    // Log and proceed to return order to client (so UX isn't blocked)
    error_log('payment_create threw exception: ' . $e->getMessage());
    echo json_encode(['success' => true, 'warning' => 'Order created but DB insert threw exception', 'order' => $respData]);
    exit;
}

// Success
echo json_encode(['success' => true, 'order' => $respData]);
exit;