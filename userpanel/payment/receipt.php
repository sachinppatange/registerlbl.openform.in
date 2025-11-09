<?php
/**
 * Payment Receipt Page
 * 
 * Displays a printable receipt for a completed payment.
 * Verifies ownership before displaying.
 */

session_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../player_repository.php';

// Authentication required
require_auth();
$phone = current_user();

// Get payment_id from query params
$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;

$payment = null;
$player = null;

if ($payment_id > 0) {
    $player = player_get_by_phone($phone);
    if ($player) {
        $payment = get_payment_by_id($payment_id);
        // Verify payment belongs to current user and is paid
        if (!$payment || $payment['player_id'] != $player['id'] || $payment['status'] !== 'paid') {
            $payment = null;
        }
    }
}

// Helper for escaping output
function h(?string $v): string { 
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - LBL Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="../../assets/lbllogo.svg">
    <style>
        :root { 
            --primary:#2563eb; 
            --bg:#fff; 
            --text:#0f172a; 
            --muted:#64748b; 
            --border:#e2e8f0;
        }
        body { 
            background:var(--bg); 
            font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; 
            color:var(--text); 
            margin:0;
            padding:20px;
        }
        .receipt { 
            max-width:700px; 
            margin:0 auto; 
            border:2px solid var(--border); 
            border-radius:10px; 
            padding:40px;
        }
        .header { 
            text-align:center; 
            margin-bottom:30px; 
            border-bottom:2px solid var(--border); 
            padding-bottom:20px;
        }
        .logo { 
            width:80px; 
            height:80px; 
            margin:0 auto 15px auto;
        }
        .logo img { 
            width:100%; 
            height:100%;
        }
        h1 { 
            font-size:32px; 
            margin:0 0 5px 0; 
            color:var(--primary);
        }
        .subtitle { 
            color:var(--muted); 
            font-size:16px;
        }
        .section { 
            margin-bottom:25px;
        }
        .section-title { 
            font-size:14px; 
            text-transform:uppercase; 
            color:var(--muted); 
            font-weight:600; 
            margin-bottom:10px;
        }
        .info-row { 
            display:flex; 
            justify-content:space-between; 
            padding:10px 0; 
            border-bottom:1px solid var(--border);
        }
        .info-row:last-child { 
            border-bottom:none;
        }
        .label { 
            color:var(--muted); 
            font-weight:500;
        }
        .value { 
            font-weight:600;
        }
        .amount-box { 
            background:#f8fafc; 
            border-radius:10px; 
            padding:20px; 
            text-align:center; 
            margin:20px 0;
        }
        .amount-label { 
            font-size:14px; 
            color:var(--muted); 
            margin-bottom:5px;
        }
        .amount-value { 
            font-size:36px; 
            font-weight:700; 
            color:var(--primary);
        }
        .footer { 
            text-align:center; 
            margin-top:30px; 
            padding-top:20px; 
            border-top:2px solid var(--border); 
            color:var(--muted); 
            font-size:14px;
        }
        .actions { 
            text-align:center; 
            margin-top:30px;
        }
        .btn { 
            display:inline-block; 
            background:var(--primary); 
            color:#fff; 
            border-radius:10px; 
            padding:12px 24px; 
            font-size:16px; 
            font-weight:600; 
            border:0; 
            cursor:pointer; 
            text-decoration:none; 
            margin:5px;
        }
        .btn-secondary { 
            background:var(--muted);
        }
        @media print {
            .actions { 
                display:none;
            }
            .receipt { 
                border:none;
            }
        }
        .error-message { 
            text-align:center; 
            color:#ef4444; 
            font-size:18px; 
            margin-top:50px;
        }
    </style>
</head>
<body>

<?php if ($payment && $player): ?>
    <div class="receipt">
        <div class="header">
            <div class="logo">
                <img src="../../assets/lbllogo.svg" alt="LBL Logo">
            </div>
            <h1>Payment Receipt</h1>
            <div class="subtitle">Latur Badminton League Registration</div>
        </div>
        
        <div class="section">
            <div class="section-title">Payment Information</div>
            <div class="info-row">
                <span class="label">Receipt No:</span>
                <span class="value">#<?php echo h($payment['id']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Payment ID:</span>
                <span class="value"><?php echo h($payment['payment_id'] ?: 'N/A'); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Order ID:</span>
                <span class="value"><?php echo h($payment['order_id']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Date:</span>
                <span class="value"><?php echo h(date('d M Y, h:i A', strtotime($payment['updated_at']))); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Status:</span>
                <span class="value">✓ Paid</span>
            </div>
        </div>
        
        <div class="amount-box">
            <div class="amount-label">Amount Paid</div>
            <div class="amount-value">₹<?php echo h(number_format($payment['amount'] / 100, 2)); ?></div>
        </div>
        
        <div class="section">
            <div class="section-title">Player Information</div>
            <div class="info-row">
                <span class="label">Name:</span>
                <span class="value"><?php echo h($player['full_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Mobile:</span>
                <span class="value"><?php echo h($player['mobile']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Village/City:</span>
                <span class="value"><?php echo h($player['village']); ?></span>
            </div>
        </div>
        
        <div class="footer">
            <p>Thank you for your registration!</p>
            <p>For any queries, please contact the LBL organizers.</p>
        </div>
        
        <div class="actions">
            <button class="btn" onclick="window.print()">Print Receipt</button>
            <a href="../dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
        </div>
    </div>
<?php else: ?>
    <div class="error-message">
        <p>Receipt not found or you don't have permission to view it.</p>
        <a href="../dashboard.php" class="btn">Go to Dashboard</a>
    </div>
<?php endif; ?>

</body>
</html>
