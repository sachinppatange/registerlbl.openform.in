<?php
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/../../config/wa_config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../player_repository.php';
require_once __DIR__ . '/../../libs/RazorpayClient.php';

header('Content-Type: application/json; charset=utf-8');

// API auth & csrf helpers will send JSON and exit on failure
require_auth_json();
require_csrf_json();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        ob_end_clean();
        echo json_encode(['ok' => false, 'error' => 'Invalid method']);
        exit;
    }

    $user = current_user();
    $amount_rupees = isset($_POST['amount']) ? (float)$_POST['amount'] : (float) DEFAULT_AMOUNT_RUPEES;
    if ($amount_rupees <= 0) $amount_rupees = (float) DEFAULT_AMOUNT_RUPEES;
    $amount_paise = (int) round($amount_rupees * 100);

    $pdo = db();
    $pdo->beginTransaction();

    $player = player_get_by_phone($user);
    $player_id = $player['id'] ?? null;
    $metaJson = json_encode(['created_by' => $user], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("INSERT INTO payments (player_id, order_id, amount, currency, status, metadata, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
    $stmt->execute([$player_id, '', $amount_paise, RAZORPAY_CURRENCY, $metaJson]);
    $payment_row_id = (int)$pdo->lastInsertId();

    // Create order via Razorpay wrapper (may throw)
    $client = new App\Libs\RazorpayClient();
    $order = $client->createOrder($amount_paise, RAZORPAY_CURRENCY, 'receipt_'.$payment_row_id);
    if (empty($order['id'])) {
        throw new Exception('Gateway order creation failed');
    }
    $order_id = $order['id'];

    $upd = $pdo->prepare("UPDATE payments SET order_id = ? WHERE id = ?");
    $upd->execute([$order_id, $payment_row_id]);

    $pdo->commit();

    ob_end_clean();
    echo json_encode(['ok' => true, 'order_id' => $order_id, 'key_id' => RZP_KEY_ID, 'amount_paise' => $amount_paise]);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    // log full error for your server logs
    error_log('[initiate_payment] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}