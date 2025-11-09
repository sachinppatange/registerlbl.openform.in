<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/payment_repository.php';
require_once __DIR__ . '/player_repository.php';

require_auth();
$current_mobile = function_exists('current_user') ? current_user() : null;

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Read order or payment identifier from query params
$order_id = trim($_GET['order'] ?? $_GET['order_id'] ?? $_GET['razorpay_order_id'] ?? '');
$payment_id = trim($_GET['payment'] ?? $_GET['payment_id'] ?? '');

// Try find payment by order_id first, then by payment_id
$payment = null;
if ($order_id !== '') {
    $payment = payment_find_by_order_id($order_id);
}
if (!$payment && $payment_id !== '') {
    $payment = payment_find_by_payment_id($payment_id);
}

// Determine message and ownership
$owner_mobile = $payment['user_mobile'] ?? null;
$owner_ok = true;
if ($owner_mobile && $current_mobile && $owner_mobile !== $current_mobile) {
    $owner_ok = false;
}

if (!$payment) {
    $title = "Payment Not Found";
    $message = "We could not find the payment record for the provided identifier.";
} elseif (!$owner_ok) {
    $title = "Access Denied";
    $message = "This payment does not belong to your account.";
} else {
    $status = $payment['status'] ?? 'failed';
    $title = "Payment " . strtoupper($status);
    // Friendly message for failed/cancelled
    if ($status === 'failed' || $status === 'created' || $status === 'pending') {
        $message = "Your payment was not successful. You can try again.";
    } else {
        $message = "Payment status: " . h($status);
    }
}

// Format amount helper (paise -> rupees)
function fmt_amount_rupees($amtPaise, $currency = 'INR') {
    if ($amtPaise === null || $amtPaise === '') return '';
    $rupees = (int)$amtPaise / 100;
    return number_format($rupees, 2) . ' ' . strtoupper($currency);
}

// Extract useful error info from meta if available
$meta_display = '—';
if (!empty($payment['meta'])) {
    if (is_string($payment['meta'])) {
        $json = json_decode($payment['meta'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $meta_display = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $meta_display = $payment['meta'];
        }
    } elseif (is_array($payment['meta'])) {
        $meta_display = json_encode($payment['meta'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        $meta_display = (string)$payment['meta'];
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo h($title); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{--bg:#fff7f7;--card:#fff;--primary:#7f1d1d;--accent:#ef4444;--muted:#64748b}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); color:var(--primary); margin:0; padding:20px;}
    .wrap{max-width:820px;margin:28px auto;padding:18px;}
    .card{background:var(--card);border-radius:12px;padding:22px;box-shadow:0 6px 20px rgba(239,68,68,0.06);}
    h1{margin:0 0 8px;font-size:20px;color:var(--accent)}
    .sub{color:var(--muted);margin-bottom:16px}
    .grid{display:grid;grid-template-columns:1fr 320px;gap:18px}
    .box{background:#fff7f7;border:1px solid #fff1f2;padding:12px;border-radius:10px}
    .kbd{font-family:monospace;background:#7f1d1d;color:#fff;padding:6px 10px;border-radius:6px;font-size:13px}
    pre{background:#1f2937;color:#f8fafc;padding:12px;border-radius:8px;overflow:auto;font-size:13px}
    .btn{display:inline-block;padding:10px 14px;background:var(--accent);color:#fff;border-radius:9px;text-decoration:none;font-weight:600}
    .btn.secondary{background:#0ea5e9;margin-left:8px;color:#042a2b}
    .muted{color:var(--muted);font-size:14px}
    table{width:100%;border-collapse:collapse}
    td{padding:8px 6px;border-bottom:1px dashed #ffecec;vertical-align:top}
    td.label{width:38%;color:var(--muted);font-weight:600}
    .alert{background:#fff1f2;border:1px solid #fecaca;padding:12px;border-radius:8px;color:#7f1d1d;margin-bottom:12px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1><?php echo h($title); ?></h1>
      <div class="sub">Payment attempt summary</div>

      <div class="alert"><?php echo h($message); ?></div>

      <?php if (!$payment || !$owner_ok): ?>
        <div style="margin-top:8px">
          <a class="btn" href="dashboard.php">Back to Dashboard</a>
        </div>
      <?php else: ?>
        <div class="grid">
          <div>
            <div class="box">
              <table>
                <tr>
                  <td class="label">Order ID</td>
                  <td><span class="kbd"><?php echo h($payment['order_id'] ?? '—'); ?></span></td>
                </tr>
                <tr>
                  <td class="label">Payment ID</td>
                  <td><span class="kbd"><?php echo h($payment['payment_id'] ?? '—'); ?></span></td>
                </tr>
                <tr>
                  <td class="label">Status</td>
                  <td><?php echo h($payment['status'] ?? 'failed'); ?></td>
                </tr>
                <tr>
                  <td class="label">Amount</td>
                  <td><?php echo h(fmt_amount_rupees($payment['amount'] ?? 0, $payment['currency'] ?? 'INR')); ?></td>
                </tr>
                <tr>
                  <td class="label">Attempted By (Mobile)</td>
                  <td><?php echo h($payment['user_mobile'] ?? '—'); ?></td>
                </tr>
                <tr>
                  <td class="label">Recorded At</td>
                  <td><?php echo h($payment['created_at'] ?? '—'); ?></td>
                </tr>
              </table>
            </div>

            <div style="margin-top:12px">
              <!-- Retry: redirect to profile where Save & Pay button is available -->
              <a class="btn" href="player_profile.php">Retry Payment</a>
              <a class="btn secondary" href="dashboard.php">Back to Dashboard</a>
            </div>
          </div>

          <div>
            <div class="box">
              <div class="muted">Raw metadata / webhook payload</div>
              <div style="margin-top:8px">
                <pre><?php echo h($meta_display); ?></pre>
              </div>
            </div>

            <div style="margin-top:12px" class="box">
              <div class="muted">Help</div>
              <div style="margin-top:8px">
                <p class="muted" style="margin:0 0 8px">If this issue persists, please contact support with your Order ID.</p>
                <?php if (!empty($payment['order_id'])): ?>
                  <p><a class="btn secondary" href="https://dashboard.razorpay.com/app/payments/<?php echo urlencode($payment['payment_id'] ?? ''); ?>" target="_blank">Open on Razorpay Dashboard</a></p>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>