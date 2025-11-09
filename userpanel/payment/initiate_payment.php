<?php
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start(); // capture stray output

require_once __DIR__ . '/../config/payment_config.php';
require_once __DIR__ . '/../config/wa_config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../libs/RazorpayClient.php';
require_once __DIR__ . '/../player_repository.php';

header('Content-Type: application/json; charset=utf-8');

require_auth_json();
require_csrf_json();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        ob_end_clean();
        echo json_encode(['ok' => false, 'error' => 'Invalid method']);
        exit;
    }

    // amount rupees -> paise
    $amount_rupees = isset($_POST['amount']) ? (float)$_POST['amount'] : (float) DEFAULT_AMOUNT_RUPEES;
    $amount_paise = max(1, (int) round($amount_rupees * 100));

    $pdo = db();
    $pdo->beginTransaction();

    // insert pending payment row
    $player = player_get_by_phone(current_user());
    $player_id = $player['id'] ?? null;
    $meta = json_encode(['created_by' => current_user()], JSON_UNESCAPED_UNICODE);
    $ins = $pdo->prepare("INSERT INTO payments (player_id, order_id, amount, currency, status, metadata, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
    $ins->execute([$player_id, '', $amount_paise, RAZORPAY_CURRENCY, $meta]);
    $pay_id = (int)$pdo->lastInsertId();

    // Create order with Razorpay (this can throw)
    $client = new App\Libs\RazorpayClient(); // ensure credentials configured
    $order = $client->createOrder($amount_paise, RAZORPAY_CURRENCY, 'receipt_'.$pay_id);
    if (empty($order['id'])) throw new Exception('Gateway order creation failed');
    $order_id = $order['id'];

    $upd = $pdo->prepare("UPDATE payments SET order_id = ? WHERE id = ?");
    $upd->execute([$order_id, $pay_id]);
    $pdo->commit();

    ob_end_clean();
    echo json_encode(['ok' => true, 'order_id' => $order_id, 'key_id' => RZP_KEY_ID, 'amount_paise' => $amount_paise]);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    error_log('[initiate_payment] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}