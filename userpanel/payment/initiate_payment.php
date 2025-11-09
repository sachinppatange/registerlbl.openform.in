<?php
// userpanel/payment/initiate_payment.php
// Robust: returns JSON on all code paths, cleans stray output, handles auth & CSRF.

if (session_status() === PHP_SESSION_NONE) session_start();

// Prevent accidental HTML output from notices
ob_start();

require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/../../config/wa_config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../player_repository.php';
require_once __DIR__ . '/../../libs/RazorpayClient.php';

header('Content-Type: application/json; charset=utf-8');

// API auth & csrf (these functions send JSON+exit on failure)
require_auth_json();
require_csrf_json();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Invalid method']);
        ob_end_flush();
        exit;
    }

    $user = current_user();
    $amount_rupees = isset($_POST['amount']) ? (float)$_POST['amount'] : (float) DEFAULT_AMOUNT_RUPEES;
    if ($amount_rupees <= 0) $amount_rupees = (float) DEFAULT_AMOUNT_RUPEES;
    $amount_paise = (int) round($amount_rupees * 100);

    $pdo = db();
    $pdo->beginTransaction();

    // create placeholder DB row (order_id updated after gateway order creation)
    $stmt = $pdo->prepare("INSERT INTO payments (player_id, order_id, amount, currency, status, metadata, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
    $player = player_get_by_phone($user);
    $player_id = $player['id'] ?? null;
    $metaJson = json_encode(['created_by' => $user], JSON_UNESCAPED_UNICODE);
    $stmt->execute([$player_id, '', $amount_paise, RAZORPAY_CURRENCY, $metaJson]);
    $payment_row_id = (int)$pdo->lastInsertId();

    // Create Razorpay order using wrapper
    $receipt = 'payment_' . $payment_row_id . '_player_' . ($player_id ?? 'guest');
    $client = new App\Libs\RazorpayClient();
    $order = $client->createOrder($amount_paise, RAZORPAY_CURRENCY, $receipt);
    if (empty($order['id'])) {
        throw new Exception('Gateway order creation failed');
    }
    $order_id = $order['id'];

    // update DB with order_id
    $upd = $pdo->prepare("UPDATE payments SET order_id = ? WHERE id = ?");
    $upd->execute([$order_id, $payment_row_id]);

    $pdo->commit();

    // ensure no stray buffered output breaks JSON
    ob_end_clean();
    echo json_encode([
        'ok' => true,
        'order_id' => $order_id,
        'key_id' => RZP_KEY_ID,
        'amount_paise' => $amount_paise
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    // clear any buffered stray output
    ob_end_clean();
    http_response_code(500);
    // log server-side error to file (do not expose stacktrace in response)
    error_log('[initiate_payment] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}