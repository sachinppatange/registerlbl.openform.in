<?php
session_start();
require_once __DIR__ . '/../../config/wa_config.php';
require_once __DIR__ . '/../auth.php';

require_auth();
$user = current_user();

// Expect ?payment_id= or ?order_id=
$payment_id = $_GET['payment_id'] ?? null;
$order_id = $_GET['order_id'] ?? null;

// Try to fetch payment info
$pdo = db();
if ($payment_id) {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? LIMIT 1");
    $stmt->execute([$payment_id]);
} elseif ($order_id) {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? LIMIT 1");
    $stmt->execute([$order_id]);
} else {
    $stmt = null;
}

$payment = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

$status = $payment['status'] ?? 'unknown';
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Payment Status</title></head>
<body>
<h1>Payment Status: <?php echo htmlspecialchars(ucfirst($status)); ?></h1>
<?php if ($payment): ?>
    <p>Order ID: <?php echo htmlspecialchars($payment['order_id']); ?></p>
    <p>Payment ID: <?php echo htmlspecialchars($payment['payment_id'] ?? '-'); ?></p>
    <p>Amount: <?php echo htmlspecialchars((int)$payment['amount'] / 100); ?> <?php echo htmlspecialchars($payment['currency']); ?></p>
    <p><a href="/userpanel/payment/receipt.php?id=<?php echo urlencode($payment['id']); ?>">View Receipt</a></p>
<?php else: ?>
    <p>Payment record not found.</p>
<?php endif; ?>
<p><a href="/userpanel/dashboard.php">Back to Dashboard</a></p>
</body>
</html>