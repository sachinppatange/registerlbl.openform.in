<?php
session_start();
require_once __DIR__ . '/../../config/payment_config.php';
require_once __DIR__ . '/../../config/wa_config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../libs/RazorpayClient.php';

header('Content-Type: application/json');

try {
    require_auth();
    $user = current_user();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Invalid method', 405);

    // CSRF
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) throw new Exception('Invalid CSRF token', 400);

    $razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
    $razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
    $razorpay_signature = $_POST['razorpay_signature'] ?? '';

    if (!$razorpay_payment_id || !$razorpay_order_id || !$razorpay_signature) {
        throw new Exception('Missing payment verification parameters', 400);
    }

    // Verify that order belongs to current player's pending payment
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, player_id, amount FROM payments WHERE order_id = ? LIMIT 1");
    $stmt->execute([$razorpay_order_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) throw new Exception('Payment record not found', 404);

    // Optionally check ownership: player_id must match current player's id (if player exists)
    $player = null;
    try { $player = player_get_by_phone($user); } catch (Throwable $_) { $player = null; }
    if (!empty($payment['player_id']) && $player && ((int)$payment['player_id'] !== (int)$player['id'])) {
        throw new Exception('Unauthorized payment', 403);
    }

    // Verify signature using RazorpayClient
    $client = new App\Libs\RazorpayClient();
    $attributes = [
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature' => $razorpay_signature
    ];

    $client->verifySignature($attributes); // throws on failure

    // Mark as paid
    $update = $pdo->prepare("UPDATE payments SET payment_id = ?, status = 'paid', updated_at = NOW() WHERE id = ?");
    $update->execute([$razorpay_payment_id, $payment['id']]);

    echo json_encode(['ok' => true, 'payment_id' => $razorpay_payment_id]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}