<?php
/**
 * Payment Receipt Page
 * Printable receipt for successful payments
 */

session_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../player_repository.php';
require_once __DIR__ . '/../../config/payment_config.php';

// Authentication required
require_auth();
$phone = current_user();

// Get payment ID from query parameter
$payment_id = $_GET['id'] ?? '';

if (empty($payment_id)) {
    header('Location: ../player_profile.php');
    exit;
}

// Fetch payment record
$payment = get_payment_by_id((int)$payment_id);

if (!$payment) {
    die('Payment not found');
}

// Verify user owns this payment
$player = player_get_by_phone($phone);
if ($payment['player_id'] != ($player['id'] ?? null)) {
    die('Unauthorized access');
}

// Only show receipt for paid payments
if ($payment['status'] !== 'paid') {
    die('Receipt not available for pending or failed payments');
}

$amount_rupees = paise_to_rupees($payment['amount']);
$metadata = json_decode($payment['metadata'] ?? '{}', true);

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - LBL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="../../assets/lbllogo.svg">
    <style>
        :root { --primary:#2563eb; --text:#0f172a; --muted:#64748b; --border:#e2e8f0;}
        body { font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--text); margin:0; padding: 20px;}
        .receipt { max-width:700px; margin:0 auto; border: 2px solid var(--border); padding: 32px; background: #fff;}
        .header { text-align: center; margin-bottom: 32px; border-bottom: 2px solid var(--border); padding-bottom: 20px;}
        .logo { width: 100px; height: 100px; margin:0 auto 12px auto; }
        .logo img { width: 100%; height: 100%; }
        h1 { font-size:28px; margin: 8px 0; color:var(--primary);}
        .subtitle { color:var(--muted); font-size:14px;}
        .section { margin: 24px 0;}
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 12px; color: var(--primary); border-bottom: 1px solid var(--border); padding-bottom: 8px;}
        .row { display: flex; justify-content: space-between; margin: 10px 0; font-size: 15px;}
        .label { font-weight: 500; color: var(--muted);}
        .value { color: var(--text); font-weight: 600;}
        .amount { font-size: 24px; font-weight: 700; color: var(--primary);}
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid var(--border); color: var(--muted); font-size: 13px;}
        .print-btn { text-align: center; margin: 20px 0;}
        .btn { display: inline-block; background:var(--primary); color:#fff; border-radius:8px; padding:12px 32px; font-size:16px; font-weight:600; border:0; cursor:pointer; text-decoration:none;}
        @media print {
            .print-btn { display: none; }
            body { padding: 0; }
            .receipt { border: none; }
        }
    </style>
</head>
<body>
    <div class="print-btn">
        <button class="btn" onclick="window.print()">Print Receipt</button>
    </div>
    
    <div class="receipt">
        <div class="header">
            <div class="logo">
                <img src="../../assets/lbllogo.svg" alt="LBL Logo">
            </div>
            <h1>Payment Receipt</h1>
            <div class="subtitle">Latur Badminton League</div>
        </div>
        
        <div class="section">
            <div class="section-title">Payment Details</div>
            <div class="row">
                <span class="label">Receipt No:</span>
                <span class="value">#<?php echo h($payment['id']); ?></span>
            </div>
            <div class="row">
                <span class="label">Payment ID:</span>
                <span class="value"><?php echo h($payment['payment_id']); ?></span>
            </div>
            <div class="row">
                <span class="label">Order ID:</span>
                <span class="value"><?php echo h($payment['order_id']); ?></span>
            </div>
            <div class="row">
                <span class="label">Payment Date:</span>
                <span class="value"><?php echo h(date('d F Y, g:i A', strtotime($payment['updated_at']))); ?></span>
            </div>
            <div class="row">
                <span class="label">Payment Method:</span>
                <span class="value">Razorpay</span>
            </div>
            <div class="row">
                <span class="label">Status:</span>
                <span class="value" style="color: #10b981;">PAID</span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Player Information</div>
            <div class="row">
                <span class="label">Name:</span>
                <span class="value"><?php echo h($metadata['player_name'] ?? $player['full_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="row">
                <span class="label">Mobile:</span>
                <span class="value"><?php echo h($metadata['phone'] ?? $phone); ?></span>
            </div>
            <div class="row">
                <span class="label">Village/City:</span>
                <span class="value"><?php echo h($player['village'] ?? 'N/A'); ?></span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Amount Paid</div>
            <div class="row">
                <span class="label">Registration Fee:</span>
                <span class="amount">₹<?php echo h(number_format($amount_rupees, 2)); ?></span>
            </div>
            <div class="row">
                <span class="label">Currency:</span>
                <span class="value"><?php echo h($payment['currency']); ?></span>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Thank you for registering with Latur Badminton League!</strong></p>
            <p>This is a computer-generated receipt and does not require a signature.</p>
            <p>For any queries, please contact us.</p>
        </div>
    </div>
    
    <div class="print-btn">
        <a href="../dashboard.php" class="btn" style="background: #64748b;">Back to Dashboard</a>
    </div>
</body>
</html>
