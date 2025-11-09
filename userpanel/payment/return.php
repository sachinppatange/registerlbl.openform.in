<?php
/**
 * Payment Return Page
 * 
 * Landing page after payment completion (success or failure).
 * Displays payment status and receipt link.
 */

session_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../player_repository.php';

// Authentication required
require_auth();
$phone = current_user();

// Get status from query params
$status = $_GET['status'] ?? 'unknown';
$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;

// Validate payment_id and ownership
$payment = null;
if ($payment_id > 0) {
    $player = player_get_by_phone($phone);
    if ($player) {
        $payment = get_payment_by_id($payment_id);
        // Verify payment belongs to current user
        if ($payment && $payment['player_id'] != $player['id']) {
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
    <title>Payment Status - LBL Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="../../assets/lbllogo.svg">
    <style>
        :root { 
            --primary:#2563eb; 
            --success:#10b981; 
            --error:#ef4444; 
            --bg:#f8fafc; 
            --card:#fff; 
            --text:#0f172a; 
            --muted:#64748b; 
            --border:#e2e8f0;
        }
        body { 
            background:var(--bg); 
            font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; 
            color:var(--text); 
            margin:0;
        }
        .wrap { 
            display:grid; 
            place-items:center; 
            min-height:100dvh; 
            padding:20px;
        }
        .card { 
            width:100%; 
            max-width:500px; 
            background:var(--card); 
            border-radius:13px; 
            box-shadow:0 4px 16px #2563eb14; 
            padding:40px 30px; 
            text-align:center;
        }
        .logo { 
            width:82px; 
            height:82px; 
            margin:0 auto 20px auto; 
            border-radius:50%; 
            background:#fff; 
            display:flex; 
            align-items:center; 
            justify-content:center; 
            box-shadow:0 2px 10px rgba(37,99,235,0.10); 
            overflow:hidden;
        }
        .logo img { 
            width:63px; 
            height:63px; 
        }
        .status-icon { 
            font-size:64px; 
            margin-bottom:20px;
        }
        .success .status-icon { 
            color:var(--success);
        }
        .error .status-icon { 
            color:var(--error);
        }
        h1 { 
            font-size:28px; 
            margin-bottom:10px;
        }
        .success h1 { 
            color:var(--success);
        }
        .error h1 { 
            color:var(--error);
        }
        .message { 
            color:var(--muted); 
            font-size:16px; 
            margin-bottom:30px;
        }
        .details { 
            background:#f8fafc; 
            border-radius:10px; 
            padding:20px; 
            margin-bottom:20px; 
            text-align:left;
        }
        .details .row { 
            display:flex; 
            justify-content:space-between; 
            margin-bottom:10px;
        }
        .details .label { 
            color:var(--muted); 
            font-weight:500;
        }
        .details .value { 
            font-weight:600;
        }
        .btn { 
            display:inline-block; 
            background:var(--primary); 
            color:#fff; 
            border-radius:10px; 
            padding:13px 24px; 
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
        .btn-success { 
            background:var(--success);
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="logo">
            <img src="../../assets/lbllogo.svg" alt="LBL Logo">
        </div>
        
        <?php if ($status === 'success' && $payment && $payment['status'] === 'paid'): ?>
            <div class="success">
                <div class="status-icon">✓</div>
                <h1>Payment Successful!</h1>
                <div class="message">Your payment has been processed successfully.</div>
                
                <div class="details">
                    <div class="row">
                        <span class="label">Payment ID:</span>
                        <span class="value">#<?php echo h($payment['id']); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Amount:</span>
                        <span class="value">₹<?php echo h(number_format($payment['amount'] / 100, 2)); ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Status:</span>
                        <span class="value">Paid</span>
                    </div>
                    <div class="row">
                        <span class="label">Date:</span>
                        <span class="value"><?php echo h(date('d M Y, h:i A', strtotime($payment['updated_at']))); ?></span>
                    </div>
                </div>
                
                <a href="receipt.php?payment_id=<?php echo h($payment['id']); ?>" class="btn btn-success">View Receipt</a>
                <a href="../dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
            </div>
        <?php elseif ($status === 'failed'): ?>
            <div class="error">
                <div class="status-icon">✕</div>
                <h1>Payment Failed</h1>
                <div class="message">Unfortunately, your payment could not be processed.</div>
                
                <a href="../player_profile.php" class="btn">Try Again</a>
                <a href="../dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
            </div>
        <?php else: ?>
            <div class="error">
                <div class="status-icon">?</div>
                <h1>Payment Status Unknown</h1>
                <div class="message">We couldn't determine the status of your payment.</div>
                
                <a href="../dashboard.php" class="btn">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
