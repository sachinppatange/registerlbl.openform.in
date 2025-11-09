<?php
/**
 * Payment Return Page
 * Shows payment status after completion
 */

session_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../player_repository.php';
require_once __DIR__ . '/../../config/payment_config.php';

// Authentication required
require_auth();
$phone = current_user();

// Get payment ID from query parameter
$payment_id = $_GET['payment_id'] ?? '';

if (empty($payment_id)) {
    header('Location: ../player_profile.php');
    exit;
}

// Fetch payment record
$payment = get_payment_by_id((int)$payment_id);

if (!$payment) {
    $msg_error = 'Payment not found';
    $status = 'error';
} else {
    // Verify user owns this payment
    $player = player_get_by_phone($phone);
    if ($payment['player_id'] != ($player['id'] ?? null)) {
        $msg_error = 'Unauthorized access';
        $status = 'error';
    } else {
        $status = $payment['status'];
        $amount_rupees = paise_to_rupees($payment['amount']);
    }
}

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Status - LBL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="../../assets/lbllogo.svg">
    <style>
        :root { --primary:#2563eb; --secondary:#0ea5e9; --bg:#f8fafc; --card:#fff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --success:#10b981; --error:#ef4444;}
        body { background:var(--bg); font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--text); margin:0;}
        .wrap { display:grid; place-items:center; min-height:100dvh; padding:12px;}
        .card { width:100%; max-width:500px; background:var(--card); border-radius:13px; box-shadow:0 4px 16px #2563eb14; padding:32px; text-align:center;}
        .logo { width: 82px; height: 82px; margin:0 auto 16px auto; border-radius:50%; background:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 10px rgba(37,99,235,0.10); overflow:hidden; }
        .logo img { width: 63px; height: 63px; }
        .status-icon { font-size: 64px; margin-bottom: 16px; }
        .status-icon.success { color: var(--success); }
        .status-icon.error { color: var(--error); }
        .status-icon.pending { color: var(--muted); }
        h1 { font-size:24px; margin-bottom:12px; color:var(--primary);}
        .message { color:var(--muted); font-size:16px; margin-bottom:24px; line-height:1.5;}
        .details { background: var(--bg); border-radius: 10px; padding: 16px; margin: 20px 0; text-align: left;}
        .details .row { display: flex; justify-content: space-between; margin: 8px 0; font-size: 14px;}
        .details .label { font-weight: 600; color: var(--muted);}
        .details .value { color: var(--text);}
        .btn { display: inline-block; background:var(--primary); color:#fff; border-radius:10px; padding:12px 24px; font-size:16px; font-weight:600; border:0; cursor:pointer; text-decoration:none; margin: 8px;}
        .btn.secondary { background: var(--muted); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="logo">
            <img src="../../assets/lbllogo.svg" alt="LBL Logo">
        </div>
        
        <?php if ($status === 'paid'): ?>
            <div class="status-icon success">✓</div>
            <h1>Payment Successful!</h1>
            <div class="message">Your payment has been processed successfully. Thank you for registering with Latur Badminton League.</div>
            
            <div class="details">
                <div class="row">
                    <span class="label">Amount Paid:</span>
                    <span class="value">₹<?php echo h(number_format($amount_rupees, 2)); ?></span>
                </div>
                <div class="row">
                    <span class="label">Payment ID:</span>
                    <span class="value"><?php echo h($payment['payment_id'] ?? 'N/A'); ?></span>
                </div>
                <div class="row">
                    <span class="label">Order ID:</span>
                    <span class="value"><?php echo h($payment['order_id']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Date:</span>
                    <span class="value"><?php echo h(date('d M Y, g:i A', strtotime($payment['updated_at']))); ?></span>
                </div>
            </div>
            
            <a href="receipt.php?id=<?php echo h($payment['id']); ?>" class="btn">View Receipt</a>
            <a href="../dashboard.php" class="btn secondary">Go to Dashboard</a>
            
        <?php elseif ($status === 'pending'): ?>
            <div class="status-icon pending">⏳</div>
            <h1>Payment Pending</h1>
            <div class="message">Your payment is being processed. This may take a few moments. Please check back later.</div>
            <a href="../dashboard.php" class="btn">Go to Dashboard</a>
            
        <?php elseif ($status === 'failed'): ?>
            <div class="status-icon error">✗</div>
            <h1>Payment Failed</h1>
            <div class="message">Unfortunately, your payment could not be processed. Please try again.</div>
            <a href="../player_profile.php" class="btn">Try Again</a>
            <a href="../dashboard.php" class="btn secondary">Go to Dashboard</a>
            
        <?php else: ?>
            <div class="status-icon error">✗</div>
            <h1>Error</h1>
            <div class="message"><?php echo h($msg_error ?? 'An error occurred'); ?></div>
            <a href="../dashboard.php" class="btn">Go to Dashboard</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
