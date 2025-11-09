<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/payment_repository.php';
require_once __DIR__ . '/player_repository.php';

require_auth();
$current_mobile = function_exists('current_user') ? current_user() : null;

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Read order identifier from query params (support multiple names)
$order_id = trim($_GET['order'] ?? $_GET['order_id'] ?? $_GET['razorpay_order_id'] ?? '');

$payment = null;
if ($order_id !== '') {
    $payment = payment_find_by_order_id($order_id);
}

// If not found by order_id, allow lookup by payment_id
if (!$payment && !empty($_GET['payment'])) {
    $payment = payment_find_by_payment_id(trim($_GET['payment']));
}

$status = $payment['status'] ?? null;

// Security: ensure this payment belongs to logged-in user (if user_mobile available)
$owner_mobile = $payment['user_mobile'] ?? null;
$owner_ok = true;
if ($owner_mobile && $current_mobile && $owner_mobile !== $current_mobile) {
    $owner_ok = false;
}

// Fallback messaging
if (!$payment) {
    $title = "Payment Information";
    $message = "No payment record found for the provided identifier.";
} elseif (!$owner_ok) {
    $title = "Access Denied";
    $message = "This payment does not belong to your account.";
} else {
    $title = "Payment " . strtoupper($status ?? 'UNKNOWN');
    $message = "";
}

// Helper to format amount (paise -> rupees)
function fmt_amount_rupees($amtPaise, $currency = 'INR') {
    if ($amtPaise === null || $amtPaise === '') return '';
    $rupees = (int)$amtPaise / 100;
    // show 2 decimals
    return number_format($rupees, 2) . ' ' . strtoupper($currency);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo h($title); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{--bg:#f8fafc;--card:#fff;--primary:#0f172a;--accent:#2563eb;--muted:#64748b}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); color:var(--primary); margin:0; padding:20px;}
    .wrap{max-width:820px;margin:28px auto;padding:18px;}
    .card{background:var(--card);border-radius:12px;padding:22px;box-shadow:0 6px 20px rgba(37,99,235,0.06);}
    h1{margin:0 0 8px;font-size:20px;color:var(--accent)}
    .sub{color:var(--muted);margin-bottom:16px}
    .grid{display:grid;grid-template-columns:1fr 320px;gap:18px}
    .box{background:#fbfdff;border:1px solid #eef2ff;padding:12px;border-radius:10px}
    .kbd{font-family:monospace;background:#0f172a;color:#fff;padding:6px 10px;border-radius:6px;font-size:13px}
    pre{background:#0b1220;color:#d1e7ff;padding:12px;border-radius:8px;overflow:auto;font-size:13px}
    .meta{font-size:13px;color:#0b1220}
    .btn{display:inline-block;padding:10px 14px;background:var(--accent);color:#fff;border-radius:9px;text-decoration:none;font-weight:600}
    .btn.secondary{background:#0ea5e9;margin-left:8px}
    .muted{color:var(--muted);font-size:14px}
    .row{margin-bottom:10px}
    table{width:100%;border-collapse:collapse}
    td{padding:8px 6px;border-bottom:1px dashed #eef2ff;vertical-align:top}
    td.label{width:38%;color:var(--muted);font-weight:600}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1><?php echo h($title); ?></h1>
      <div class="sub">Transaction summary</div>

      <?php if ($message): ?>
        <div class="box">
          <p class="muted"><?php echo h($message); ?></p>
          <p><a class="btn" href="dashboard.php">Back to Dashboard</a></p>
        </div>
      <?php else: ?>
        <div class="grid">
          <div>
            <div class="box">
              <table>
                <tr>
                  <td class="label">Order ID</td>
                  <td><span class="kbd"><?php echo h($payment['order_id'] ?? ''); ?></span></td>
                </tr>
                <tr>
                  <td class="label">Payment ID</td>
                  <td><span class="kbd"><?php echo h($payment['payment_id'] ?? '—'); ?></span></td>
                </tr>
                <tr>
                  <td class="label">Status</td>
                  <td><?php echo h($payment['status'] ?? '—'); ?></td>
                </tr>
                <tr>
                  <td class="label">Amount</td>
                  <td><?php echo h(fmt_amount_rupees($payment['amount'] ?? 0, $payment['currency'] ?? 'INR')); ?></td>
                </tr>
                <tr>
                  <td class="label">Paid By (Mobile)</td>
                  <td><?php echo h($payment['user_mobile'] ?? '—'); ?></td>
                </tr>
                <tr>
                  <td class="label">Recorded At</td>
                  <td><?php echo h($payment['created_at'] ?? '—'); ?></td>
                </tr>
              </table>
            </div>

            <div style="margin-top:12px">
              <a class="btn" href="dashboard.php">Go to Dashboard</a>
              <a class="btn secondary" href="player_profile.php">View Profile</a>
            </div>
          </div>

          <div>
            <div class="box">
              <div class="muted">Signature</div>
              <div style="margin-top:8px">
                <pre><?php echo h($payment['signature'] ?? '—'); ?></pre>
              </div>

              <div style="margin-top:12px" class="muted">Raw metadata</div>
              <div style="margin-top:8px" class="meta">
                <?php
                  $meta = $payment['meta'] ?? null;
                  if (is_string($meta)) {
                      $json = json_decode($meta, true);
                      if (json_last_error() === JSON_ERROR_NONE) {
                          echo '<pre>' . h(json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . '</pre>';
                      } else {
                          echo '<pre>' . h($meta) . '</pre>';
                      }
                  } elseif (is_array($meta)) {
                      echo '<pre>' . h(json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . '</pre>';
                  } else {
                      echo '<pre>—</pre>';
                  }
                ?>
              </div>
            </div>

            <div style="margin-top:12px" class="box">
              <div class="muted">Receipt / Actions</div>
              <div style="margin-top:8px">
                <?php if (!empty($payment['payment_id'])): ?>
                  <p><a class="btn" href="https://dashboard.razorpay.com/app/payments/<?php echo urlencode($payment['payment_id']); ?>" target="_blank">Open on Razorpay Dashboard</a></p>
                <?php endif; ?>
                <p style="margin-top:8px"><a class="btn secondary" href="player_repository.php">My Registrations</a></p>
              </div>
            </div>

          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>