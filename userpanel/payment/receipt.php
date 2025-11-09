<?php
session_start();
require_once __DIR__ . '/../../config/wa_config.php';
require_once __DIR__ . '/../auth.php';

require_auth();
$user = current_user();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) { http_response_code(400); echo 'Invalid payment id'; exit; }

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) { http_response_code(404); echo 'Payment not found'; exit; }

// Optionally ensure ownership
$player = player_get_by_phone($user);
if (!empty($payment['player_id']) && $player && ((int)$payment['player_id'] !== (int)$player['id'])) {
    http_response_code(403); echo 'Forbidden'; exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt #<?php echo htmlspecialchars($payment['id']); ?></title>
<style>
body { font-family: Arial, sans-serif; padding: 18px; }
.header { margin-bottom: 12px; }
.table { width: 100%; border-collapse: collapse; }
.table td { padding: 6px; border: 1px solid #ddd; }
</style>
</head>
<body>
<div class="header">
    <h2>LBL - Payment Receipt</h2>
    <div>Receipt ID: <?php echo htmlspecialchars($payment['id']); ?></div>
    <div>Date: <?php echo htmlspecialchars($payment['created_at']); ?></div>
</div>
<table class="table">
<tr><td>Player ID</td><td><?php echo htmlspecialchars($payment['player_id'] ?? '-'); ?></td></tr>
<tr><td>Order ID</td><td><?php echo htmlspecialchars($payment['order_id']); ?></td></tr>
<tr><td>Payment ID</td><td><?php echo htmlspecialchars($payment['payment_id'] ?? '-'); ?></td></tr>
<tr><td>Amount</td><td><?php echo htmlspecialchars(((int)$payment['amount'])/100 . ' ' . htmlspecialchars($payment['currency'])); ?></td></tr>
<tr><td>Status</td><td><?php echo htmlspecialchars($payment['status']); ?></td></tr>
</table>
<p><button onclick="window.print()">Print Receipt</button></p>
<p><a href="/userpanel/dashboard.php">Back to Dashboard</a></p>
</body>
</html>