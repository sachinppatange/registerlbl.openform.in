<?php
session_start();
require_once __DIR__ . '../config/payment_config.php';
require_once __DIR__ . '../config/wa_config.php';
require_once __DIR__ . '../auth.php';
require_once __DIR__ . '../player_repository.php';
require_once __DIR__ . '../libs/RazorpayClient.php';

header('Content-Type: application/json');

try {
    require_auth();
    $user = current_user();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Invalid method', 405);

    // CSRF validation
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) throw new Exception('Invalid CSRF token', 400);

    // Determine amount in rupees (float) - prefer POST amount else default
    $amount_rupees = isset($_POST['amount']) ? (float)$_POST['amount'] : (float) DEFAULT_AMOUNT_RUPEES;
    if ($amount_rupees <= 0) $amount_rupees = DEFAULT_AMOUNT_RUPEES;

    // Convert to paise for Razorpay (integer)
    $amount_paise = (int) round($amount_rupees * 100);

    // Create DB record (pending)
    $pdo = db();
    $pdo->beginTransaction();
    // Insert minimal payment row; set order_id after order creation
    $sql = "INSERT INTO payments (player_id, order_id, amount, currency, status, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $player = player_get_by_phone($user);
    $player_id = $player['id'] ?? null;
    $dummyOrderId = ''; // update later
    $stmt->execute([$player_id, $dummyOrderId, $amount_paise, RAZORPAY_CURRENCY, 'pending', json_encode(['created_by' => $user])]);
    $payment_id = (int) $pdo->lastInsertId();

    // Create Razorpay order
    $receipt = 'payment_' . $payment_id . '_player_' . ($player_id ?? 'guest');
    $client = new App\Libs\RazorpayClient();
    $order = $client->createOrder($amount_paise, RAZORPAY_CURRENCY, $receipt);
    $order_id = $order['id'] ?? null;
    if (!$order_id) throw new Exception('Failed to create order with gateway');

    // Update DB row with order_id
    $update = $pdo->prepare("UPDATE payments SET order_id = ? WHERE id = ?");
    $update->execute([$order_id, $payment_id]);

    $pdo->commit();

    // Return required data to client
    echo json_encode([
        'ok' => true,
        'order_id' => $order_id,
        'key_id' => RZP_KEY_ID,
        'amount_paise' => $amount_paise
    ]);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}